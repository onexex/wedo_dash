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
    public function testPersonalInfoReportListsEmployeesAndLoadsTheHeader(): void
    {
        $body = $this->request('PersonalInfo.php', [], [], $this->asAdmin())['body'];

        $this->assertStringNotContainsString('Uncaught', $body);
        $this->assertStringContainsString('Dela Cruz', $body);            // the report's rows came back
        $this->assertStringContainsString('assets/js/wedo-call.js', $body); // and the page got as far as its header
    }
}
