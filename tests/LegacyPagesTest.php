<?php
namespace Wedo\Tests;

use Wedo\Tests\Support\AppTestCase;

/**
 * Older pages (includes/header.php shell) that must work on Linux, where MySQL
 * table names are case-sensitive: `EmpDetails` is not `empdetails` there.
 * CI runs on Linux, so these catch a capitalised table name that Windows/XAMPP
 * would quietly accept.
 */
final class LegacyPagesTest extends AppTestCase
{
    /** A report page: no crash, the employee rows came back, and it got as far as its header. */
    private function assertReportLists(string $page, string $expect = 'Dela Cruz', ?array $session = null): void
    {
        $body = $this->request($page, [], [], $session ?? $this->asAdmin())['body'];
        $this->assertStringNotContainsString('Uncaught', $body, $page);
        $this->assertStringContainsString($expect, $body, $page);
        $this->assertStringContainsString('assets/js/wedo-call.js', $body, $page);
    }

    public function testPersonalInfoReport(): void       { $this->assertReportLists('PersonalInfo.php'); }
    public function testComplianceDocumentReport(): void { $this->assertReportLists('ComDocData.php'); }
    public function testEmploymentInfoReport(): void     { $this->assertReportLists('EmpInfo.php'); }
    public function testE201Report(): void               { $this->assertReportLists('reportse201.php'); }

    public function testOvertimeObPageShowsMyDepartmentAndPosition(): void
    {
        $this->assertReportLists('otob.php', 'Developer', $this->asEmployee());
    }

    public function testReportSearches(): void
    {
        self::db()->prepare("INSERT INTO dars (EmpID, EmpActivity, DarDateTime) VALUES (?, 'Filed a leave', '2026-10-01 09:00:00')")
            ->execute([self::EMP]);
        $range = ['Eid' => 'All', 'dtfrom' => '2026-10-01', 'dtto' => '2026-10-02'];

        $dar = $this->request('query/Query-searchdarreports.php', ['srchdar' => 1], $range, $this->asAdmin())['body'];
        $this->assertStringNotContainsString('Uncaught', $dar);
        $this->assertStringContainsString('Filed a leave', $dar);

        $docs = $this->request('query/Query-searchdarreports.php', ['srchcomdata' => 1], ['Eid' => 'all'] + $range, $this->asAdmin())['body'];
        $this->assertStringNotContainsString('Uncaught', $docs);
        $this->assertStringContainsString('Dela Cruz', $docs);
    }
}
