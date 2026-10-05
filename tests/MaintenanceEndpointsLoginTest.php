<?php
namespace Wedo\Tests;

use Wedo\Tests\Support\AppTestCase;

/**
 * The Maintenance AJAX endpoints require a logged-in session:
 *   query/query-maintenance.php        (adds: agency, company, sss, ...) had no login check at all
 *   query/query-searchmaintenance.php  (table refreshes) redirected but kept running, so the rows were still sent
 * A logged-out caller now gets 401 and nothing else.
 */
final class MaintenanceEndpointsLoginTest extends AppTestCase
{
    private function agencyCount(): int
    {
        return (int) self::db()->query("SELECT COUNT(*) FROM agency")->fetchColumn();
    }

    public function testWriteEndpointRejectsLoggedOutCallers(): void
    {
        $post = ['agencyno' => 9, 'agencyname' => 'Sneaky Agency', 'agencystatus' => 1];
        $before = $this->agencyCount();

        $this->assertSame(401, $this->request('query/query-maintenance.php', ['agency' => ''], $post)['status']);
        $this->assertSame($before, $this->agencyCount(), 'nothing written while logged out');

        $res = $this->request('query/query-maintenance.php', ['agency' => ''], $post, $this->asAdmin());
        $this->assertSame(200, $res['status']);
        $this->assertNoPhpErrors($res['body']);
        $this->assertSame($before + 1, $this->agencyCount());
    }

    public function testListEndpointSendsNothingToLoggedOutCallers(): void
    {
        $res = $this->request('query/query-searchmaintenance.php', ['agency' => '']);
        $this->assertSame(401, $res['status']);
        $this->assertSame('', trim($res['body']));
        $this->assertStringNotContainsString('Direct Hire', $res['body']);
    }
}
