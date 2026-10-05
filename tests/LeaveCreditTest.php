<?php
namespace Wedo\Tests;

use Wedo\Tests\Support\AppTestCase;

/**
 * Leave Credit Viewer: starting a new leave year and setting credits by hand.
 *
 *   includes/leave-credit-lib.php    rules (reset, edit, add)
 *   query/leavecredit-action.php     start_year / save (gated + token)
 *   leavecredit.php                  the screen
 */
final class LeaveCreditTest extends AppTestCase
{
    private const TOKEN = 'lc-test-token';

    protected function setUp(): void
    {
        parent::setUp();
        self::db()->exec("UPDATE accessrights SET lcreaditview = 2, lcreditedit = 2 WHERE EmpID = '" . self::ADMIN . "'");
        // last year: admin used 5 of 15, the employee used 9.5 of 12
        $ins = self::db()->prepare("INSERT INTO credit (EmpID, CT, CTH) VALUES (?, ?, ?)");
        $ins->execute([self::ADMIN, 10, 15]);
        $ins->execute([self::EMP, 2.5, 12]);
    }

    private function hr(): array
    {
        return array_merge($this->asAdmin(), ['lc_csrf' => self::TOKEN]);
    }

    private function action(array $post, ?array $session = null): array
    {
        $res = $this->request('query/leavecredit-action.php', [], $post + ['token' => self::TOKEN], $session ?? $this->hr());
        $this->assertNoPhpErrors($res['body']);
        $res['json'] = json_decode($res['body'], true);
        return $res;
    }

    private function credit(string $emp): ?array
    {
        return $this->row('SELECT CT, CTH FROM credit WHERE EmpID = ?', [$emp]);
    }

    private function startYear(array $credits, ?int $year = null): array
    {
        return $this->action(['action' => 'start_year', 'year' => $year ?? (int) date('Y'), 'credit' => $credits]);
    }

    // ------------------------------------------------------------------ new leave year

    public function testStartingTheYearSetsEachEmployeesNewCreditAndDropsUnused(): void
    {
        $res = $this->startYear([self::ADMIN => '16', self::EMP => '12.5']);

        $this->assertSame(200, $res['status'], $res['body']);
        $this->assertEquals(['CT' => 16, 'CTH' => 16], $this->credit(self::ADMIN));
        $this->assertEquals(['CT' => 12.5, 'CTH' => 12.5], $this->credit(self::EMP));

        $year = $this->row('SELECT * FROM credit_years WHERE leave_year = ?', [(int) date('Y')]);
        $this->assertSame(self::ADMIN, $year['started_by']);
        $this->assertEquals(2, $year['employees']);

        $log = $this->row("SELECT * FROM credit_log WHERE EmpID = ? AND action = 'new_year'", [self::EMP]);
        $this->assertEquals([2.5, 12, 12.5, 12.5], [$log['old_ct'], $log['old_cth'], $log['new_ct'], $log['new_cth']]);
    }

    public function testTheSameYearCannotBeStartedTwice(): void
    {
        $this->assertSame(200, $this->startYear([self::ADMIN => '15', self::EMP => '12'])['status']);
        // a leave gets approved, then someone clicks again
        self::db()->exec("UPDATE credit SET CT = 14 WHERE EmpID = '" . self::ADMIN . "'");

        $res = $this->startYear([self::ADMIN => '15', self::EMP => '12']);

        $this->assertSame(422, $res['status']);
        $this->assertStringContainsString('already been started', $res['json']['msg']);
        $this->assertEquals(['CT' => 14, 'CTH' => 15], $this->credit(self::ADMIN));
    }

    public function testOnlyTheCurrentYearCanBeStarted(): void
    {
        $res = $this->startYear([self::ADMIN => '15', self::EMP => '12'], (int) date('Y') + 1);

        $this->assertSame(422, $res['status']);
        $this->assertEquals(['CT' => 10, 'CTH' => 15], $this->credit(self::ADMIN));
        $this->assertSame([], $this->rows('SELECT * FROM credit_years'));
    }

    public function testAMissingOrInvalidCreditChangesNobody(): void
    {
        $res = $this->startYear([self::ADMIN => '15']);
        $this->assertSame(422, $res['status']);
        $this->assertStringContainsString('Enter a leave credit for Juan Dela Cruz', $res['json']['msg']);

        foreach (['', '-1', 'abc', '366', '1.23456'] as $bad) {
            $res = $this->startYear([self::ADMIN => '15', self::EMP => $bad]);
            $this->assertSame(422, $res['status'], "accepted '$bad'");
        }

        $this->assertEquals(['CT' => 10, 'CTH' => 15], $this->credit(self::ADMIN));
        $this->assertEquals(['CT' => 2.5, 'CTH' => 12], $this->credit(self::EMP));
        $this->assertSame([], $this->rows('SELECT * FROM credit_years'));
        $this->assertSame([], $this->rows('SELECT * FROM credit_log'));
    }

    public function testResignedEmployeesAreLeftAlone(): void
    {
        self::db()->exec("UPDATE employees SET EmpStatusID = 2 WHERE EmpID = '" . self::EMP . "'");

        $this->assertSame(200, $this->startYear([self::ADMIN => '15'])['status']);
        $this->assertEquals(['CT' => 2.5, 'CTH' => 12], $this->credit(self::EMP));
    }

    // ------------------------------------------------------------------ edit / add

    public function testEditingSetsCreditAndRemaining(): void
    {
        $res = $this->action(['action' => 'save', 'emp' => self::EMP, 'cth' => '13', 'ct' => '3.5']);

        $this->assertSame(200, $res['status'], $res['body']);
        $this->assertEquals(['CT' => 3.5, 'CTH' => 13], $this->credit(self::EMP));
        $this->assertSame('edit', $this->row('SELECT action FROM credit_log WHERE EmpID = ?', [self::EMP])['action']);
    }

    public function testRemainingCannotExceedTheYearsCredit(): void
    {
        $res = $this->action(['action' => 'save', 'emp' => self::EMP, 'cth' => '12', 'ct' => '13']);

        $this->assertSame(422, $res['status']);
        $this->assertEquals(['CT' => 2.5, 'CTH' => 12], $this->credit(self::EMP));
    }

    public function testAddingAnEmployeeWithNoCreditRow(): void
    {
        self::db()->exec("DELETE FROM credit WHERE EmpID = '" . self::EMP . "'");

        $res = $this->action(['action' => 'save', 'emp' => self::EMP, 'cth' => '12', 'ct' => '12']);

        $this->assertSame(200, $res['status'], $res['body']);
        $this->assertEquals(['CT' => 12, 'CTH' => 12], $this->credit(self::EMP));
        $this->assertSame('add', $this->row('SELECT action FROM credit_log WHERE EmpID = ?', [self::EMP])['action']);
    }

    public function testInactiveOrUnknownEmployeesCannotBeSaved(): void
    {
        self::db()->exec("UPDATE employees SET EmpStatusID = 2 WHERE EmpID = '" . self::EMP . "'");
        $this->assertSame(422, $this->action(['action' => 'save', 'emp' => self::EMP, 'cth' => '12', 'ct' => '12'])['status']);
        $this->assertSame(422, $this->action(['action' => 'save', 'emp' => 'NOPE', 'cth' => '12', 'ct' => '12'])['status']);
        $this->assertNull($this->credit('NOPE'));
    }

    // ------------------------------------------------------------------ access

    public function testViewOnlyUsersAndBadTokensCannotWrite(): void
    {
        self::db()->exec("UPDATE accessrights SET lcreaditview = 2, lcreditedit = 1 WHERE EmpID = '" . self::ADMIN . "'");
        $this->assertSame(403, $this->startYear([self::ADMIN => '15', self::EMP => '12'])['status']);

        self::db()->exec("UPDATE accessrights SET lcreditedit = 2 WHERE EmpID = '" . self::ADMIN . "'");
        $res = $this->request('query/leavecredit-action.php', [],
            ['action' => 'save', 'emp' => self::EMP, 'cth' => '1', 'ct' => '1', 'token' => 'wrong'], $this->hr());
        $this->assertSame(419, $res['status']);

        $this->assertEquals(['CT' => 2.5, 'CTH' => 12], $this->credit(self::EMP));
    }

    public function testThePageNeedsTheViewRight(): void
    {
        $res = $this->request('leavecredit.php', [], [], $this->asEmployee());
        $this->assertSame(302, $res['status']);
        $this->assertStringNotContainsString('Employee leave credits', $res['body']);
    }

    // ------------------------------------------------------------------ the screen

    public function testScreenOffersToStartTheYearUntilItIsStarted(): void
    {
        $year = (int) date('Y');
        // an old filing still waiting for HR
        self::db()->prepare("INSERT INTO hleaves (EmpID, LType, LStart, LEnd, LStatus) VALUES (?, 22, ?, ?, 2)")
            ->execute([self::EMP, ($year - 1) . '-12-29', ($year - 1) . '-12-29']);

        $html = $this->request('leavecredit.php', [], [], $this->hr())['body'];
        $this->assertNoPhpErrors($html);
        $this->assertStringContainsString("Start $year leave year", $html);
        $this->assertStringContainsString('1 leave filing dated ' . ($year - 1) . ' or earlier is still awaiting approval', $html);
        $this->assertSame('12', $this->inputValue($html, 'credit[' . self::EMP . ']'));

        $this->assertSame(200, $this->startYear([self::ADMIN => '15', self::EMP => '12'])['status']);

        $html = $this->request('leavecredit.php', [], [], $this->hr())['body'];
        $this->assertNoPhpErrors($html);
        $this->assertStringNotContainsString("Start $year leave year", $html);
        $this->assertStringContainsString("The $year leave year was started", $html);
    }

    public function testScreenListsActiveEmployeesWithoutCredits(): void
    {
        self::db()->exec("DELETE FROM credit WHERE EmpID = '" . self::EMP . "'");

        $html = $this->request('leavecredit.php', [], [], $this->hr())['body'];
        $this->assertNoPhpErrors($html);
        $this->assertStringContainsString('1 active employee has no leave credits', $html);
        $this->assertNotNull($this->dom($html)->query("//select[@id='lcPick']/option[@value='" . self::EMP . "']")->item(0));
    }
}
