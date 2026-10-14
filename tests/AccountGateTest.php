<?php
namespace Wedo\Tests;

use Wedo\Tests\Support\AppTestCase;

/**
 * Resigned / terminated employees can't use WeDo — includes/account-gate.php
 * (sign-in, both "remember me" tokens, signed-in sessions, HR status change),
 * with the per-person exception `aftersep` (Access Rights > Others).
 */
final class AccountGateTest extends AppTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        require_once dirname(__DIR__) . '/includes/account-gate.php';
        require_once dirname(__DIR__) . '/includes/app-remember.php';
    }

    protected function setUp(): void
    {
        parent::setUp();
        $db = self::db();
        $db->exec("INSERT IGNORE INTO estatus (ID, StatusEmpDesc) VALUES (4, 'Terminated')");
        foreach (['app_remember', 'push_devices'] as $t) { $db->exec("DELETE FROM `$t`"); }
    }

    protected function tearDown(): void
    {
        unset($_COOKIE[APP_REMEMBER_COOKIE]);
        parent::tearDown();
    }

    private function setStatus(string $empId, int $status): void
    {
        self::db()->prepare("UPDATE employees SET EmpStatusID = ? WHERE EmpID = ?")->execute([$status, $empId]);
    }

    private function allowAfterSeparation(string $empId): void
    {
        self::db()->prepare("UPDATE accessrights SET aftersep = 2 WHERE EmpID = ?")->execute([$empId]);
    }

    /** A signed-in phone + browser: app token, push device, website remember-me. */
    private function signedInEverywhere(string $empId): void
    {
        $db = self::db();
        $db->prepare("INSERT INTO app_remember (EmpID, token_hash, expires_at, created_at, last_used) VALUES (?, ?, ?, NOW(), NOW())")
           ->execute([$empId, hash('sha256', str_repeat('ab', 32)), date('Y-m-d H:i:s', time() + 86400)]);
        $db->prepare("INSERT INTO push_devices (EmpID, token, platform, created_at, last_seen) VALUES (?, 'fcm-token-1', 'android', NOW(), NOW())")
           ->execute([$empId]);
        $db->prepare("UPDATE empdetails SET remember_hash = 'x', remember_expiry = ? WHERE EmpID = ?")
           ->execute([date('Y-m-d H:i:s', time() + 3600), $empId]);
    }

    private function assertSignedOutEverywhere(string $empId): void
    {
        $this->assertCount(0, $this->rows("SELECT id FROM app_remember WHERE EmpID = ?", [$empId]), 'app token kept');
        $this->assertCount(0, $this->rows("SELECT token FROM push_devices WHERE EmpID = ?", [$empId]), 'push device kept');
        $this->assertNull($this->row("SELECT remember_hash FROM empdetails WHERE EmpID = ?", [$empId])['remember_hash'], 'remember-me kept');
    }

    public function testOnlyResignedAndTerminatedAreBlocked(): void
    {
        $this->assertFalse(wd_account_blocked(self::db(), self::EMP), 'employed');
        $this->setStatus(self::EMP, 2);
        $this->assertTrue(wd_account_blocked(self::db(), self::EMP), 'resigned');
        $this->setStatus(self::EMP, 4);
        $this->assertTrue(wd_account_blocked(self::db(), self::EMP), 'terminated');
        $this->assertFalse(wd_account_blocked(self::db(), 'NO-SUCH-EMPLOYEE'), 'no employees row (system account)');
    }

    public function testAfterSeparationRightIsTheException(): void
    {
        $this->setStatus(self::EMP, 2);
        $this->allowAfterSeparation(self::EMP);
        $this->assertFalse(wd_account_blocked(self::db(), self::EMP));
        $this->assertSame([], wd_blocked_among(self::db(), [self::EMP, self::ADMIN]));
    }

    public function testSignInIsRefusedWithTheInactiveCode(): void
    {
        self::db()->prepare("UPDATE empdetails SET EmpPW = ? WHERE EmpID = ?")->execute([password_hash('secret-pass', PASSWORD_DEFAULT), self::EMP]);
        $this->setStatus(self::EMP, 4);

        $res = $this->request('query/query-login.php', [], ['uname' => 'jdelacruz', 'pass' => 'secret-pass']);
        $this->assertSame('0101', trim($res['body']));

        $this->allowAfterSeparation(self::EMP);
        $res = $this->request('query/query-login.php', [], ['uname' => 'jdelacruz', 'pass' => 'secret-pass']);
        $this->assertStringNotContainsString('0101', $res['body'], 'the exception may sign in');
    }

    public function testAppTokenOfABlockedEmployeeIsRefusedAndDropped(): void
    {
        $this->signedInEverywhere(self::EMP);
        $this->setStatus(self::EMP, 2);
        $_COOKIE[APP_REMEMBER_COOKIE] = str_repeat('ab', 32);

        $this->assertNull(app_remember_restore(self::db()));
        $this->assertCount(0, $this->rows("SELECT id FROM app_remember WHERE EmpID = ?", [self::EMP]));
    }

    public function testAppTokenStillWorksWithTheException(): void
    {
        $this->signedInEverywhere(self::EMP);
        $this->setStatus(self::EMP, 2);
        $this->allowAfterSeparation(self::EMP);
        $_COOKIE[APP_REMEMBER_COOKIE] = str_repeat('ab', 32);

        $this->assertSame(self::EMP, app_remember_restore(self::db()));
    }

    public function testSignedInSessionIsEndedOnTheNextPage(): void
    {
        $this->signedInEverywhere(self::EMP);
        $this->setStatus(self::EMP, 2);

        $res = $this->request('e201.php', [], [], $this->asEmployee());
        $redirected = $res['status'] === 302 || str_contains($res['body'], 'login.php?inactive=1');
        $this->assertTrue($redirected, "e201.php kept serving a resigned employee:\n" . substr($res['body'], 0, 800));
        $this->assertStringNotContainsString('wd-sidebar', $res['body']);
        $this->assertSignedOutEverywhere(self::EMP);
    }

    /** An open page polls the call check every few seconds; an open chat talks to Query-messages only. */
    public function testBackgroundRequestsOfABlockedSessionGetTheInactiveAnswer(): void
    {
        foreach (['query/Query-calls.php', 'query/Query-messages.php'] as $endpoint) {
            $this->signedInEverywhere(self::EMP);
            $this->setStatus(self::EMP, 2);

            $res = $this->request($endpoint, ['action' => 'incoming'], [], $this->asEmployee());
            $this->assertSame(401, $res['status'], "$endpoint: " . $res['body']);
            $json = json_decode($res['body'], true);
            $this->assertTrue($json['inactive'] ?? false, "$endpoint: " . $res['body']);
            $this->assertSame('login.php?inactive=1', $json['redirect']);
            $this->assertSignedOutEverywhere(self::EMP);
            $this->setStatus(self::EMP, 1);
        }
    }

    public function testBackgroundRequestsOfAnEmployedSessionWork(): void
    {
        $res = $this->request('query/Query-calls.php', ['action' => 'incoming'], [], $this->asEmployee());
        $this->assertSame(200, $res['status'], $res['body']);
        $this->assertSame('ok', json_decode($res['body'], true)['status'] ?? null);
    }

    public function testEveryPageListensForTheInactiveAnswer(): void
    {
        $body = $this->request('e201.php', [], [], $this->asEmployee())['body'];
        $this->assertStringContainsString("getResponseHeader('X-WeDo-Inactive')", $body);
        $this->assertStringContainsString("location.replace('login.php?inactive=1')", $body);
    }

    public function testEmployedSessionIsUntouched(): void
    {
        $this->signedInEverywhere(self::EMP);
        $res = $this->request('e201.php', [], [], $this->asEmployee());
        $this->assertSame(200, $res['status']);
        $this->assertStringContainsString('wd-sidebar', $res['body'], 'the page itself rendered');
        $this->assertStringNotContainsString('location.replace("login.php?inactive=1")', $res['body'], 'no redirect');
        $this->assertCount(1, $this->rows("SELECT id FROM app_remember WHERE EmpID = ?", [self::EMP]));
    }

    public function testHrSettingResignedSignsEveryDeviceOut(): void
    {
        $this->signedInEverywhere(self::EMP);
        self::db()->exec("UPDATE accessrights SET updte = 2 WHERE EmpID = '" . self::ADMIN . "'");
        $form = $this->formData($this->request('UpdateEmployeeInfo.php', ['sid' => self::EMP], [], $this->asAdmin())['body']);
        $form['empst'] = '2';

        $res = $this->request('query/Query-updateEmployee.php', [], $form, $this->asAdmin());
        $this->assertSame(200, $res['status'], $res['body']);
        $this->assertSame('2', (string) $this->row("SELECT EmpStatusID FROM employees WHERE EmpID = ?", [self::EMP])['EmpStatusID']);
        $this->assertSignedOutEverywhere(self::EMP);
    }

    public function testInactiveMessageOnTheSignInPage(): void
    {
        $res = $this->request('login.php', ['inactive' => '1']);
        $this->assertStringContainsString('This account is no longer active. Please contact HR.', $res['body']);
        $this->assertStringNotContainsString('no longer active', $this->request('login.php')['body']);
    }
}
