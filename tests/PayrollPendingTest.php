<?php
namespace Wedo\Tests;

use Wedo\Tests\Support\AppTestCase;

/**
 * Payroll > Generate refuses while filings in the cut-off are pending; the
 * warning now lists who they are.
 *
 *   query/Query-payroll.php      the check (codes 10000–10005)
 *   query/payroll-pending.php    the list behind the warning
 *   assets/js/payroll.js         showPendingFilings()
 */
final class PayrollPendingTest extends AppTestCase
{
    private const CUT = ['dtfr' => '2026-10-01', 'dtto' => '2026-10-15'];

    protected function setUp(): void
    {
        parent::setUp();
        $db = self::db();
        $db->exec("UPDATE accessrights SET payroll = 2 WHERE EmpID = '" . self::ADMIN . "'");
        $db->exec("INSERT INTO leaves (LeaveID, LeaveDesc) VALUES (30, 'SIL Medical')");
    }

    private function pending(array $post = self::CUT, ?array $session = null): array
    {
        $res = $this->request('query/payroll-pending.php', [], $post, $session ?? $this->asAdmin());
        $this->assertNoPhpErrors($res['body']);
        $res['json'] = json_decode($res['body'], true);
        return $res;
    }

    private function group(array $json, string $key): array
    {
        foreach ($json['groups'] as $g) { if ($g['key'] === $key) { return $g['items']; } }
        $this->fail("no $key group");
    }

    private function exec(string $sql, array $params): void
    {
        self::db()->prepare($sql)->execute($params);
    }

    public function testListsEachPendingFilingWithWhoMustApproveIt(): void
    {
        // leave waiting for the superior (Ada Admin), OT waiting for HR
        $this->exec("INSERT INTO hleavesbd (FID, EmpID, EmpSID, LType, LFDate, LStart, LEnd, LPurpose, LDuration, LStatus)
                     VALUES (1, ?, ?, 30, '2026-10-07', '2026-10-08', '2026-10-08', 'Fever', 600, 1)", [self::EMP, self::ADMIN]);
        $this->exec("INSERT INTO otattendancelog (EmpID, EmpISID, TimeIn, TimeOut, Duration, Purpose, Status)
                     VALUES (?, ?, '2026-10-10 18:00:00', '2026-10-10 21:00:00', 3, 'Month end', 2)", [self::EMP, self::ADMIN]);
        $this->exec("INSERT INTO obshbd (OBID, EmpID, EmpSID, OBDateFrom, OBDateTo, OBTimeFrom, OBTimeTo, OBIFrom, OBITo, OBPurpose, OBStatus)
                     VALUES (1, ?, ?, '2026-10-03', '2026-10-03', '08:00', '17:00', 'Office', 'Client', 'Meeting', 2)", [self::ADMIN, 'N/A']);
        $this->exec("INSERT INTO earlyout (EMPID, EmpISID, DFile, EOfrom, Purpose, Status)
                     VALUES (?, ?, '2026-10-05', '03:00 PM', 'Check-up', 1)", [self::EMP, self::ADMIN]);

        $res = $this->pending();

        $this->assertSame(200, $res['status'], $res['body']);
        $this->assertSame(4, $res['json']['total']);

        [$leave] = $this->group($res['json'], 'leave');
        $this->assertSame('Dela Cruz, Juan', $leave['name']);
        $this->assertSame(self::EMP, $leave['empid']);
        $this->assertSame('2026-10-08', $leave['date']);
        $this->assertSame('SIL Medical', $leave['detail']);
        $this->assertSame('Immediate superior', $leave['waiting']);
        $this->assertSame('Ada Admin', $leave['approver']);

        [$ot] = $this->group($res['json'], 'ot');
        $this->assertSame('HR', $ot['waiting']);
        $this->assertSame('', $ot['approver']);
        $this->assertStringContainsString('06:00 PM', $ot['detail']);

        $this->assertCount(1, $this->group($res['json'], 'ob'));
        $this->assertCount(1, $this->group($res['json'], 'eo'));
    }

    public function testSkipsWhatTheGenerateCheckSkips(): void
    {
        // approved, cancelled, outside the cut-off, and LType 9 — none of these block Generate
        $leave = "INSERT INTO hleavesbd (FID, EmpID, EmpSID, LType, LStart, LEnd, LStatus) VALUES (1, ?, ?, ?, ?, ?, ?)";
        $this->exec($leave, [self::EMP, self::ADMIN, 30, '2026-10-08', '2026-10-08', 4]);
        $this->exec($leave, [self::EMP, self::ADMIN, 30, '2026-10-09', '2026-10-09', 7]);
        $this->exec($leave, [self::EMP, self::ADMIN, 30, '2026-10-16', '2026-10-16', 1]);
        $this->exec($leave, [self::EMP, self::ADMIN, 9, '2026-10-10', '2026-10-10', 1]);

        $res = $this->pending();

        $this->assertSame(200, $res['status'], $res['body']);
        $this->assertSame(0, $res['json']['total']);
    }

    public function testListsUnapprovedPayrollDates(): void
    {
        self::db()->exec("INSERT INTO payrol (PYEmpID, PYDate, hrApproved) VALUES ('" . self::EMP . "', '2026-10-05', 0)");

        $res = $this->pending();

        $this->assertSame(['2026-10-05'], $res['json']['unapproved_payroll']);
    }

    public function testNeedsThePayrollRight(): void
    {
        $this->assertSame(403, $this->pending(self::CUT, $this->asEmployee())['status']);
    }

    public function testRejectsMissingDates(): void
    {
        $this->assertSame(422, $this->pending(['dtfr' => '', 'dtto' => '2026-10-15'])['status']);
    }
}
