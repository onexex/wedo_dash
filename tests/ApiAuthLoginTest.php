<?php
namespace Wedo\Tests;

use Wedo\Tests\Support\AppTestCase;

/**
 * Sign-in API for other apps (api/auth-login.php + includes/api-auth-lib.php):
 * API keys, HTTPS, rate limits, and the website's sign-in rules (lockouts,
 * resigned accounts, default passwords) applied to API sign-ins too.
 */
final class ApiAuthLoginTest extends AppTestCase
{
    private const KEY   = 'test-key-0123456789abcdef0123456789abcdef';
    private const OTHER = 'other-key-0123456789abcdef0123456789abcde';

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        require_once dirname(__DIR__) . '/includes/bot-guard.php';
    }

    protected function setUp(): void
    {
        parent::setUp();
        putenv('WEDO_API_CLIENTS=testapp:' . self::KEY . ',otherapp:' . self::OTHER);
        self::db()->prepare("UPDATE empdetails SET EmpPW = ?, must_change_pw = 0 WHERE EmpID = ?")
            ->execute([password_hash('Right-pass-1', PASSWORD_DEFAULT), self::EMP]);
    }

    protected function tearDown(): void
    {
        putenv('WEDO_API_CLIENTS');
        parent::tearDown();
    }

    /** A timestamp N seconds ago in the app's clock (Asia/Manila). */
    private static function manila(int $ago = 0): string
    {
        return (new \DateTime('@' . (time() - $ago)))->setTimezone(new \DateTimeZone('Asia/Manila'))->format('Y-m-d H:i:s');
    }

    /** POST to the API over HTTPS from the client app's server; $server overrides headers / HTTPS / address. */
    private function api(array $post, ?string $key = self::KEY, array $server = []): array
    {
        $server += ['HTTPS' => 'on', 'REMOTE_ADDR' => '198.51.100.7', 'REQUEST_METHOD' => 'POST'];
        if ($key !== null && !isset($server['HTTP_AUTHORIZATION'])) { $server += ['HTTP_X_WEDO_KEY' => $key]; }
        $res = $this->request('api/auth-login.php', [], $post, null, [], $server);
        $this->assertNoPhpErrors($res['body']);
        $json = json_decode($res['body'], true);
        $this->assertIsArray($json, "not JSON:\n" . $res['body']);
        return [$res['status'], $json];
    }

    private function signIn(string $pass = 'Right-pass-1', string $user = 'jdelacruz', array $extra = []): array
    {
        return $this->api(['username' => $user, 'password' => $pass] + $extra);
    }

    private function lockOut(string $user, int $n = \WD_LOGIN_USER_MAX): void
    {
        for ($i = 0; $i < $n; $i++) {
            self::db()->prepare("INSERT INTO login_attempts (Username, IP, Success, CreatedAt) VALUES (?, '', 0, ?)")->execute([$user, self::manila()]);
        }
    }

    private function failures(): int
    {
        return (int) $this->row("SELECT COUNT(*) n FROM login_attempts WHERE Success = 0")['n'];
    }

    public function testAMissingOrWrongApiKeyIsRefused(): void
    {
        foreach ([null, '', 'wrong-key-0123456789abcdef0123456789abcdef'] as $key) {
            [$code, $j] = $this->api(['username' => 'jdelacruz', 'password' => 'Right-pass-1'], $key);
            $this->assertSame(401, $code);
            $this->assertSame('invalid_api_key', $j['error']);
            $this->assertArrayNotHasKey('token', $j);
        }
        $this->assertSame(0, $this->failures(), 'the password is never checked without a valid key');

        [$code, $j] = $this->api(['username' => 'jdelacruz', 'password' => 'Right-pass-1'], null, ['HTTP_AUTHORIZATION' => 'Bearer ' . self::KEY]);
        $this->assertSame(200, $code, 'Authorization: Bearer works too');
    }

    public function testWrongKeysFromOneAddressAreSlowedDown(): void
    {
        for ($i = 0; $i < 20; $i++) {
            self::db()->prepare("INSERT INTO api_auth_requests (ClientID, IP, Result, CreatedAt) VALUES ('', '198.51.100.7', 'bad_key', ?)")->execute([self::manila()]);
        }
        [$code, $j] = $this->api(['username' => 'jdelacruz', 'password' => 'x'], 'wrong-key-0123456789abcdef0123456789abcdef');
        $this->assertSame(429, $code);
        $this->assertGreaterThan(0, $j['retry_after']);
        [$code] = $this->signIn();
        $this->assertSame(200, $code, 'a valid key from the same address still works');
    }

    public function testPlainHttpAndGetAreRefused(): void
    {
        [$code, $j] = $this->api(['username' => 'jdelacruz', 'password' => 'Right-pass-1'], self::KEY, ['HTTPS' => '', 'REMOTE_ADDR' => '203.0.113.5']);
        $this->assertSame(403, $code);
        $this->assertSame('https_required', $j['error']);

        [$code] = $this->api([], self::KEY, ['REQUEST_METHOD' => 'GET']);
        $this->assertSame(405, $code);
    }

    public function testWrongPasswordAndUnknownUsernameGetTheSameAnswerAndCount(): void
    {
        [$c1, $wrong]   = $this->signIn('wrong-pass-1');
        [$c2, $unknown] = $this->signIn('wrong-pass-1', 'nobody-here');
        $this->assertSame(401, $c1);
        $this->assertSame(401, $c2);
        $this->assertSame($wrong, $unknown, 'an unknown username looks exactly like a wrong password');
        $this->assertSame('invalid_credentials', $wrong['error']);
        $this->assertSame(2, $this->failures(), 'both count toward the sign-in lockout');
        $this->assertCount(0, $this->rows("SELECT * FROM login_attempts WHERE Username LIKE '%pass%'"), 'passwords are never stored');
        $this->assertCount(0, $this->rows("SELECT * FROM api_auth_requests WHERE Username LIKE '%pass%'"));
    }

    public function testApiAndWebsiteWrongPasswordsLockTogether(): void
    {
        $this->lockOut('jdelacruz', \WD_LOGIN_USER_MAX - 1);
        $this->signIn('wrong-pass-1');   // the last allowed wrong password, through the API
        $web = trim($this->request('query/query-login.php', [], ['uname' => 'jdelacruz', 'pass' => 'Right-pass-1'])['body']);
        $this->assertStringStartsWith('429|', $web, 'the website sign-in is locked as well');
    }

    public function testALockedAccountIsRefusedEvenWithTheRightPassword(): void
    {
        $this->lockOut('jdelacruz');
        [$code, $j] = $this->signIn();
        $this->assertSame(429, $code);
        $this->assertSame('locked', $j['error']);
        $this->assertGreaterThan(850, $j['retry_after']);
        $this->assertArrayNotHasKey('token', $j);
    }

    public function testTheEndUsersAddressCountsTowardThePerAddressLimit(): void
    {
        for ($i = 0; $i < \WD_LOGIN_IP_MAX; $i++) {
            self::db()->prepare("INSERT INTO login_attempts (Username, IP, Success, CreatedAt) VALUES (?, '192.0.2.44', 0, ?)")->execute(["user$i", self::manila()]);
        }
        [$code, $j] = $this->signIn('Right-pass-1', 'jdelacruz', ['ip' => '192.0.2.44']);
        $this->assertSame(429, $code);
        $this->assertSame('locked', $j['error']);
        [$code] = $this->signIn('Right-pass-1', 'jdelacruz', ['ip' => '192.0.2.45']);
        $this->assertSame(200, $code, 'other people using the same app are not affected');
    }

    public function testAResignedAccountIsRefusedUnlessAllowedAfterSeparation(): void
    {
        self::db()->exec("UPDATE employees SET EmpStatusID = 2 WHERE EmpID = '" . self::EMP . "'");
        [$code, $j] = $this->signIn();
        $this->assertSame(403, $code);
        $this->assertSame('account_inactive', $j['error']);
        $this->assertArrayNotHasKey('token', $j);

        self::db()->exec("UPDATE accessrights SET aftersep = 2 WHERE EmpID = '" . self::EMP . "'");
        [$code] = $this->signIn();
        $this->assertSame(200, $code, 'the `aftersep` access right still lets them in');
    }

    public function testADefaultPasswordMustBeChangedInWeDoFirst(): void
    {
        self::db()->prepare("UPDATE empdetails SET EmpPW = ? WHERE EmpID = ?")->execute([password_hash('P@zzword', PASSWORD_DEFAULT), self::EMP]);
        [$code, $j] = $this->signIn('P@zzword');
        $this->assertSame(403, $code);
        $this->assertSame('password_change_required', $j['error']);
        $this->assertArrayNotHasKey('token', $j);
        $this->assertSame('1', (string) $this->row("SELECT must_change_pw FROM empdetails WHERE EmpID = ?", [self::EMP])['must_change_pw']);
    }

    public function testSigningInReturnsASignedTokenAndTheBasicProfile(): void
    {
        [$code, $j] = $this->signIn('Right-pass-1', 'JDelaCruz', ['ip' => '192.0.2.10']);
        $this->assertSame(200, $code);
        $this->assertTrue($j['ok']);
        $this->assertSame('Bearer', $j['token_type']);
        $this->assertSame(3600, $j['expires_in']);
        $this->assertSame([
            'emp_id' => self::EMP, 'username' => 'jdelacruz', 'first_name' => 'Juan', 'middle_name' => 'Santos',
            'last_name' => 'Dela Cruz', 'name' => 'Juan Dela Cruz Jr', 'company_id' => 'TC', 'company' => 'TestCo',
            'role_id' => 3, 'role' => 'employee',
        ], $j['user']);
        $this->assertStringNotContainsString('$2y$', json_encode($j), 'no password hash leaves WeDo');

        // a JWT (HS256) the client app can check with its own key
        [$h, $p, $s] = explode('.', $j['token']);
        $b64 = fn(string $x) => base64_decode(strtr($x, '-_', '+/'));
        $this->assertSame(['alg' => 'HS256', 'typ' => 'JWT'], json_decode($b64($h), true));
        $this->assertTrue(hash_equals(hash_hmac('sha256', "$h.$p", self::KEY, true), $b64($s)), 'signed with the client key');
        $claims = json_decode($b64($p), true);
        $this->assertSame('testapp', $claims['aud']);
        $this->assertSame(self::EMP, $claims['sub']);
        $this->assertEqualsWithDelta(time() + 3600, $claims['exp'], 10);

        $this->assertNotNull($this->row("SELECT * FROM dars WHERE EmpID = ? AND EmpActivity = ?", [self::EMP, 'Signed in to testapp (sign-in API)']));
        $this->assertNotNull($this->row("SELECT * FROM login_attempts WHERE Username = 'jdelacruz' AND Success = 1 AND IP = '192.0.2.10'"));
        $this->assertNotNull($this->row("SELECT * FROM api_auth_requests WHERE ClientID = 'testapp' AND Result = 'ok' AND IP = '192.0.2.10'"));
    }

    public function testEachKeyIsRateLimited(): void
    {
        for ($i = 0; $i < 60; $i++) {
            self::db()->prepare("INSERT INTO api_auth_requests (ClientID, IP, Username, Result, CreatedAt) VALUES ('testapp', '', 'x', 'ok', ?)")->execute([self::manila()]);
        }
        [$code, $j] = $this->signIn();
        $this->assertSame(429, $code);
        $this->assertSame('rate_limited', $j['error']);
        $this->assertSame(0, $this->failures(), 'refused before the password is checked');

        [$code] = $this->api(['username' => 'jdelacruz', 'password' => 'Right-pass-1'], self::OTHER);
        $this->assertSame(200, $code, 'another app has its own limit');
    }

    public function testMissingFieldsAreRefused(): void
    {
        [$code, $j] = $this->api(['username' => 'jdelacruz']);
        $this->assertSame(422, $code);
        $this->assertSame('missing_fields', $j['error']);
    }
}
