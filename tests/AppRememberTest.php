<?php
namespace Wedo\Tests;

use Wedo\Tests\Support\AppTestCase;

/**
 * Keeping the mobile app signed in — includes/app-remember.php
 * (per-phone 30-day token, cookie WeDoApp, table app_remember).
 */
final class AppRememberTest extends AppTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        require_once dirname(__DIR__) . '/includes/app-remember.php';
    }

    protected function tearDown(): void
    {
        unset($_COOKIE[APP_REMEMBER_COOKIE], $_SERVER['HTTP_USER_AGENT']);
        parent::tearDown();
    }

    /** Issue a token the way query-login.php does and hand back the cookie value the phone would keep. */
    private function issue(string $empId): string
    {
        // the cookie can't be read back under PHPUnit (headers already sent), only the stored hash;
        // tests that need a usable cookie plant their own token instead
        app_remember_issue(self::db(), $empId);
        $rows = $this->rows("SELECT token_hash FROM app_remember WHERE EmpID = ?", [$empId]);
        $this->assertCount(1, $rows);
        return $rows[0]['token_hash'];
    }

    private function plant(string $empId, string $token, string $expires): void
    {
        self::db()->prepare("INSERT INTO app_remember (EmpID, token_hash, expires_at, created_at, last_used) VALUES (?, ?, ?, NOW(), NOW())")
            ->execute([$empId, hash('sha256', $token), $expires]);
    }

    public function testOnlyTheAppIsRecognised(): void
    {
        $_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 (Linux; Android 15) Chrome/138 Mobile Safari/537.36';
        $this->assertFalse(app_is_app());
        $_SERVER['HTTP_USER_AGENT'] .= ' WeDoApp/1.0';
        $this->assertTrue(app_is_app());
    }

    public function testIssueStoresOnlyAHashValidForThirtyDays(): void
    {
        $hash = $this->issue(self::EMP);
        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $hash);

        $exp = strtotime($this->row("SELECT expires_at FROM app_remember WHERE EmpID = ?", [self::EMP])['expires_at']);
        $this->assertEqualsWithDelta(time() + 30 * 86400, $exp, 120);
    }

    public function testEachPhoneHasItsOwnToken(): void
    {
        $this->issue(self::EMP);
        $_COOKIE = [];   // a second phone
        app_remember_issue(self::db(), self::EMP);
        $this->assertCount(2, $this->rows("SELECT id FROM app_remember WHERE EmpID = ?", [self::EMP]));
    }

    public function testRestoreSignsThePhoneInAndExtendsTheToken(): void
    {
        $this->plant(self::EMP, str_repeat('ab', 32), date('Y-m-d H:i:s', time() + 3 * 86400));
        $_COOKIE[APP_REMEMBER_COOKIE] = str_repeat('ab', 32);

        $this->assertSame(self::EMP, app_remember_restore(self::db()));
        $exp = strtotime($this->row("SELECT expires_at FROM app_remember WHERE EmpID = ?", [self::EMP])['expires_at']);
        $this->assertEqualsWithDelta(time() + 30 * 86400, $exp, 120, 'each use pushes expiry 30 days out');
    }

    public function testRestoreRefusesExpiredUnknownAndMalformedTokens(): void
    {
        $this->plant(self::EMP, str_repeat('cd', 32), date('Y-m-d H:i:s', time() - 60));

        $_COOKIE[APP_REMEMBER_COOKIE] = str_repeat('cd', 32);   // expired
        $this->assertNull(app_remember_restore(self::db()));
        $_COOKIE[APP_REMEMBER_COOKIE] = str_repeat('ef', 32);   // never issued
        $this->assertNull(app_remember_restore(self::db()));
        $_COOKIE[APP_REMEMBER_COOKIE] = "' OR 1=1 --";          // garbage
        $this->assertNull(app_remember_restore(self::db()));
    }

    public function testRestoreRefusesResignedEmployees(): void
    {
        self::db()->prepare("UPDATE employees SET EmpStatusID = 2 WHERE EmpID = ?")->execute([self::EMP]);
        $this->plant(self::EMP, str_repeat('12', 32), date('Y-m-d H:i:s', time() + 86400));
        $_COOKIE[APP_REMEMBER_COOKIE] = str_repeat('12', 32);

        $this->assertNull(app_remember_restore(self::db()));
    }

    public function testSignOutForgetsOnlyThisPhone(): void
    {
        $this->plant(self::EMP, str_repeat('34', 32), date('Y-m-d H:i:s', time() + 86400));
        $this->plant(self::EMP, str_repeat('56', 32), date('Y-m-d H:i:s', time() + 86400));   // another phone
        $_COOKIE[APP_REMEMBER_COOKIE] = str_repeat('34', 32);

        app_remember_revoke(self::db());

        $left = $this->rows("SELECT token_hash FROM app_remember WHERE EmpID = ?", [self::EMP]);
        $this->assertSame([['token_hash' => hash('sha256', str_repeat('56', 32))]], $left);
    }

    public function testWebsiteRememberMeIsUntouched(): void
    {
        self::db()->prepare("UPDATE empdetails SET remember_hash = 'web-hash' WHERE EmpID = ?")->execute([self::EMP]);
        $this->issue(self::EMP);
        $this->assertSame('web-hash', $this->row("SELECT remember_hash FROM empdetails WHERE EmpID = ?", [self::EMP])['remember_hash']);
    }
}
