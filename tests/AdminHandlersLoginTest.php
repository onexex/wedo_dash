<?php
namespace Wedo\Tests;

use Wedo\Tests\Support\AppTestCase;

/**
 * Admin AJAX/form handlers that must only act for a signed-in user:
 *   query/query-resetpassword.php       signed in, and not a regular employee (UserType 3)
 *   query/query-payrollscript.php       signed in, with the Payroll access right (accessrights.payroll = 2)
 *   query/Query-UpdateBlog.php          signed in (same as newblog.php)
 *   query/Query-updatemaintenance.php   signed in (normally included by maintenance.php after its own check)
 * Anyone else gets 401/403 and nothing is written.
 */
final class AdminHandlersLoginTest extends AppTestCase
{
    private const PAYDATE = '2026-09-30';

    protected function setUp(): void
    {
        parent::setUp();
        $db = self::db();
        foreach (['payrol', 'adjusment2', 'blog_post'] as $t) { $db->exec("DELETE FROM `$t`"); }
        $db->exec("UPDATE accessrights SET payroll=2 WHERE EmpID='" . self::ADMIN . "'");
        $db->prepare("UPDATE empdetails SET EmpPW=? WHERE EmpID=?")->execute([password_hash('own-secret', PASSWORD_DEFAULT), self::EMP]);
    }

    // ------------------------------------------------------------ reset password

    private function empPw(string $empId): ?string
    {
        return $this->row('SELECT EmpPW FROM empdetails WHERE EmpID=?', [$empId])['EmpPW'] ?? null;
    }

    public function testResetPasswordRequiresSignIn(): void
    {
        $before = $this->empPw(self::EMP);
        $res = $this->request('query/query-resetpassword.php', [], ['data' => self::EMP]);
        $this->assertSame(401, $res['status']);
        $this->assertSame($before, $this->empPw(self::EMP));
    }

    public function testResetPasswordRefusesRegularEmployees(): void
    {
        $before = $this->empPw(self::ADMIN);
        $res = $this->request('query/query-resetpassword.php', [], ['data' => self::ADMIN], $this->asEmployee());
        $this->assertSame(403, $res['status']);
        $this->assertSame($before, $this->empPw(self::ADMIN));
    }

    public function testResetPasswordWorksForHr(): void
    {
        $res = $this->request('query/query-resetpassword.php', [], ['data' => self::EMP], $this->asAdmin());
        $this->assertSame(200, $res['status']);
        $this->assertNoPhpErrors($res['body']);
        $this->assertTrue(password_verify('wedoinc2023', (string)$this->empPw(self::EMP)));
        $this->assertNull($this->empPw(self::ADMIN), 'other employees untouched');
    }

    public function testResetPasswordMatchesTheIdLiterally(): void
    {
        $before = $this->empPw(self::EMP);
        $res = $this->request('query/query-resetpassword.php', [], ['data' => "x' OR '1'='1"], $this->asAdmin());
        $this->assertSame(200, $res['status']);
        $this->assertNoPhpErrors($res['body']);
        $this->assertSame($before, $this->empPw(self::EMP));
        $this->assertNull($this->empPw(self::ADMIN));
    }

    // ------------------------------------------------------------ payroll

    private function seedPayroll(): void
    {
        $cols = ['PYEmpID' => self::EMP, 'PYDate' => self::PAYDATE, 'PYDateFrom' => '2026-09-16', 'PYDateTo' => '2026-09-30',
                 'PYBasic' => 12500, 'PYAllowance' => 1000, 'PYHourRate' => 150, 'PYTAH' => 0, 'PYTBH' => 88, 'PYGross' => 13500,
                 'PYOverTime' => 0, 'PYAdj' => 0, 'PYSSS' => 0, 'PYSSSLoan' => '0', 'PYPhilHealth' => 0, 'PYPagibig' => 0,
                 'PYPILoan' => '0', 'PYTaxIncome' => 0, 'PYIncTax' => 0, 'PYNetPay' => 13500, 'PYRecivable' => 13500];
        $names = implode(',', array_map(fn($c) => "`$c`", array_keys($cols)));
        $ph    = implode(',', array_fill(0, count($cols), '?'));
        self::db()->prepare("INSERT INTO payrol ($names) VALUES ($ph)")->execute(array_values($cols));
    }

    private function hrApproved(): int
    {
        return (int)$this->row('SELECT hrApproved FROM payrol WHERE PYDate=?', [self::PAYDATE])['hrApproved'];
    }

    private function adjustmentCount(): int
    {
        return (int)self::db()->query('SELECT COUNT(*) FROM adjusment2')->fetchColumn();
    }

    public function testPayrollApprovalRequiresSignIn(): void
    {
        $this->seedPayroll();
        $res = $this->request('query/query-payrollscript.php', ['approvedPayroll' => ''], ['pdate' => self::PAYDATE]);
        $this->assertSame(401, $res['status']);
        $this->assertSame(0, $this->hrApproved());

        $res = $this->request('query/query-payrollscript.php', ['addadjustment' => ''],
                              ['emp' => self::EMP, 'amount' => '500', 'pdate' => self::PAYDATE]);
        $this->assertSame(401, $res['status']);
        $this->assertSame(0, $this->adjustmentCount());
    }

    public function testPayrollApprovalRequiresPayrollAccess(): void
    {
        $this->seedPayroll();
        $res = $this->request('query/query-payrollscript.php', ['approvedPayroll' => ''], ['pdate' => self::PAYDATE], $this->asEmployee());
        $this->assertSame(403, $res['status']);
        $this->assertSame(0, $this->hrApproved());
    }

    public function testPayrollApprovalWorksWithPayrollAccess(): void
    {
        $this->seedPayroll();
        $res = $this->request('query/query-payrollscript.php', ['approvedPayroll' => ''], ['pdate' => self::PAYDATE], $this->asAdmin());
        $this->assertSame(200, $res['status']);
        $this->assertNoPhpErrors($res['body']);
        $this->assertSame(['error' => 2], json_decode($res['body'], true));
        $this->assertSame(1, $this->hrApproved());

        $res = $this->request('query/query-payrollscript.php', ['addadjustment' => ''],
                              ['emp' => self::EMP, 'amount' => '500', 'pdate' => self::PAYDATE], $this->asAdmin());
        $this->assertSame(200, $res['status']);
        $this->assertSame(1, $this->adjustmentCount());
    }

    // ------------------------------------------------------------ blog

    private function seedPost(): int
    {
        self::db()->prepare("INSERT INTO blog_post (Post_Category,Post_Title,Post_Publish_Date,Post_Content,Post_image,Author_ID,Post_URL,Post_Stat,Post_IsEdit)
                             VALUES (1,'Original title',NOW(),'Original body','',?,'',1,0)")->execute([self::ADMIN]);
        return (int)self::db()->lastInsertId();
    }

    private function blogEdit(): array
    {
        return ['uptitlepost' => 'Edited title', 'Contentup' => 'Edited body', 'categoryup' => 1, 'statusup' => 1, 'editup' => 1];
    }

    public function testBlogUpdateRequiresSignIn(): void
    {
        $pid = $this->seedPost();
        $res = $this->request('query/Query-UpdateBlog.php', ['pid' => $pid], $this->blogEdit());
        $this->assertSame(401, $res['status']);
        $this->assertSame('Original title', $this->row('SELECT Post_Title FROM blog_post WHERE Post_ID=?', [$pid])['Post_Title']);
    }

    public function testBlogUpdateWorksWhenSignedIn(): void
    {
        $pid = $this->seedPost();
        $res = $this->request('query/Query-UpdateBlog.php', ['pid' => $pid], $this->blogEdit(), $this->asAdmin());
        $this->assertSame(200, $res['status']);
        $this->assertNoPhpErrors($res['body']);
        $row = $this->row('SELECT Post_Title, Post_Content FROM blog_post WHERE Post_ID=?', [$pid]);
        $this->assertSame(['Post_Title' => 'Edited title', 'Post_Content' => 'Edited body'], $row);
    }

    // ------------------------------------------------------------ maintenance edit forms

    private function departmentName(): string
    {
        return (string)$this->row('SELECT DepartmentDesc FROM departments WHERE DepartmentID=1')['DepartmentDesc'];
    }

    public function testMaintenanceUpdateHandlerRequiresSignIn(): void
    {
        $res = $this->request('query/Query-updatemaintenance.php', ['updtdep' => 1], ['depname' => 'Renamed']);
        $this->assertSame(401, $res['status']);
        $this->assertSame('Engineering', $this->departmentName());
    }

    public function testMaintenancePageStillRunsTheEditForm(): void
    {
        $res = $this->request('maintenance.php', ['updtdep' => 1], ['depname' => 'Renamed'], $this->asAdmin());
        $this->assertSame(302, $res['status']);
        $this->assertSame('Renamed', $this->departmentName());
    }
}
