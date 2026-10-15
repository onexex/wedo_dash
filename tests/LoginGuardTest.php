<?php
namespace Wedo\Tests;

use Wedo\Tests\Support\AppTestCase;

/**
 * Brute-force protection on the sign-in (query/query-login.php +
 * includes/bot-guard.php): lockouts per username and per address, the same
 * answer for unknown usernames and wrong passwords, server-side Turnstile,
 * and default passwords forced to change.
 */
final class LoginGuardTest extends AppTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        require_once dirname(__DIR__) . '/includes/bot-guard.php';
    }

    protected function setUp(): void
    {
        parent::setUp();
        self::db()->prepare("UPDATE empdetails SET EmpPW = ? WHERE EmpID = ?")->execute([password_hash('Right-pass-1', PASSWORD_DEFAULT), self::EMP]);
    }

    /** A timestamp N seconds ago in the app's clock (Asia/Manila), like the sign-in writes them. */
    private static function manila(int $ago): string
    {
        return (new \DateTime('@' . (time() - $ago)))->setTimezone(new \DateTimeZone('Asia/Manila'))->format('Y-m-d H:i:s');
    }

    protected function tearDown(): void
    {
        putenv('TURNSTILE_SITE');
        putenv('TURNSTILE_SECRET');
        parent::tearDown();
    }

    private function login(string $user, string $pass, array $extra = []): string
    {
        $res = $this->request('query/query-login.php', [], ['uname' => $user, 'pass' => $pass] + $extra);
        $this->assertNoPhpErrors($res['body']);
        return trim($res['body']);
    }

    private function signedIn(string $answer): bool
    {
        return !in_array($answer, ['0', '1', '428'], true) && strpos($answer, '429|') !== 0;
    }

    public function testUnknownUsernameAndWrongPasswordLookTheSame(): void
    {
        $this->assertSame('1', $this->login('nobody-here', 'whatever1'));
        $this->assertSame('1', $this->login('jdelacruz', 'wrong-pass-1'));
        $this->assertTrue($this->signedIn($this->login('jdelacruz', 'Right-pass-1')));
    }

    public function testFiveWrongPasswordsLockTheUsername(): void
    {
        for ($i = 0; $i < \WD_LOGIN_USER_MAX; $i++) { $this->assertSame('1', $this->login('jdelacruz', "guess-$i")); }
        $locked = $this->login('jdelacruz', 'Right-pass-1');
        $this->assertStringStartsWith('429|', $locked, 'even the right password waits out the lock');
        [, $mins, $secs, $scope] = explode('|', $locked);
        $this->assertGreaterThanOrEqual(1, (int) $mins);
        $this->assertGreaterThan(850, (int) $secs, 'about 15 minutes, in seconds');
        $this->assertSame('user', $scope, 'an account lock');
        $this->assertStringStartsWith('429|', $this->login('JDelaCruz', 'Right-pass-1'), 'the username is not case-sensitive for the lock');
        $this->assertCount(0, $this->rows("SELECT * FROM login_attempts WHERE Username LIKE '%pass%'"), 'passwords are never stored');
    }

    public function testTheLockEndsAfterTheWindow(): void
    {
        $old = self::manila(16 * 60);
        for ($i = 0; $i < 5; $i++) {
            self::db()->prepare("INSERT INTO login_attempts (Username, IP, Success, CreatedAt) VALUES ('jdelacruz', '', 0, ?)")->execute([$old]);
        }
        $this->assertTrue($this->signedIn($this->login('jdelacruz', 'Right-pass-1')), 'failures older than 15 minutes do not count');
    }

    public function testFailuresBeforeASuccessfulSignInDoNotCount(): void
    {
        for ($i = 0; $i < 4; $i++) { $this->login('jdelacruz', "guess-$i"); }
        $this->assertTrue($this->signedIn($this->login('jdelacruz', 'Right-pass-1')));
        $this->assertSame('1', $this->login('jdelacruz', 'guess-x'), 'a fresh count after signing in');
    }

    public function testOneAddressTryingManyAccountsIsBlocked(): void
    {
        $db = self::db();
        $now = self::manila(0);
        // an office sharing one address: 49 failures across many accounts must not lock anyone out …
        for ($i = 0; $i < \WD_LOGIN_IP_MAX - 1; $i++) {
            $db->prepare("INSERT INTO login_attempts (Username, IP, Success, CreatedAt) VALUES (?, '', 0, ?)")->execute(["user$i", $now]);
        }
        $this->assertTrue($this->signedIn($this->login('jdelacruz', 'Right-pass-1')), 'under the limit');
        // … but a bot spraying past the limit is stopped for every account, with the reason
        $this->login('user-x', 'guess');
        $answer = $this->login('jdelacruz', 'Right-pass-1');
        $this->assertStringStartsWith('429|', $answer);
        $this->assertStringEndsWith('|ip', $answer, 'a network lock');
    }

    public function testTurnstileIsCheckedOnTheServerWhenConfigured(): void
    {
        putenv('TURNSTILE_SITE=test-site');
        putenv('TURNSTILE_SECRET=test-secret');
        $this->assertSame('428', $this->login('jdelacruz', 'Right-pass-1'), 'no Turnstile answer = refused, before the password is checked');
        $this->assertSame(1, (int) $this->row("SELECT COUNT(*) n FROM login_attempts WHERE Success = 0")['n'], 'counts toward the limits');
    }

    public function testSigningInWithADefaultPasswordForcesAChange(): void
    {
        self::db()->prepare("UPDATE empdetails SET EmpPW = ?, must_change_pw = 0 WHERE EmpID = ?")->execute([password_hash('P@zzword', PASSWORD_DEFAULT), self::EMP]);
        $this->assertTrue($this->signedIn($this->login('jdelacruz', 'P@zzword')));
        $this->assertSame('1', (string) $this->row("SELECT must_change_pw FROM empdetails WHERE EmpID = ?", [self::EMP])['must_change_pw']);
    }
}

