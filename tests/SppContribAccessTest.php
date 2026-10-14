<?php
namespace Wedo\Tests;

use Wedo\Tests\Support\AppTestCase;

/**
 * Maintenance > SSS / Pag-IBIG / PhilHealth contributions and SIL Loan are gated
 * by the `SPPContrib` access right: the menu items already were, but the pages,
 * the save/edit endpoints and the table-refresh endpoint were open to every
 * logged-in user, and Access Rights had no switch for it.
 */
final class SppContribAccessTest extends AppTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        self::db()->exec("DELETE FROM pagibig");
        self::db()->exec("INSERT INTO pagibig (PIID, EE, ER) VALUES (1, 123.45, 678.9)");
    }

    private function grant(string $empId): void
    {
        self::db()->exec("UPDATE accessrights SET SPPContrib = 2 WHERE EmpID = '$empId'");
    }

    public function testAccessRightsListsTheSwitch(): void
    {
        $body = $this->request('searchaccessrights.php', ['q' => self::EMP], [], $this->asAdmin())['body'];
        $this->assertStringContainsString('SPPContrib', $body);
    }

    public function testPagesAreBlockedWithoutTheRight(): void
    {
        foreach (['sss', 'pagibig', 'philhealth', 'silloan'] as $page) {
            $res = $this->request('maintenance.php', [$page => ''], [], $this->asEmployee());
            $this->assertSame(302, $res['status'], $page);
        }
        $res = $this->request('maintenance.php', ['pagibig' => ''], [], $this->asEmployee());
        $this->assertStringNotContainsString('678.9', $res['body']);
    }

    public function testPageOpensWithTheRight(): void
    {
        $this->grant(self::ADMIN);
        $res = $this->request('maintenance.php', ['pagibig' => ''], [], $this->asAdmin());
        $this->assertSame(200, $res['status']);
        $this->assertStringContainsString('678.9', $res['body']);
    }

    public function testListEndpointIsBlockedWithoutTheRight(): void
    {
        $res = $this->request('query/query-searchmaintenance.php', ['pagibig' => ''], [], $this->asEmployee());
        $this->assertSame(403, $res['status']);
        $this->assertStringNotContainsString('678.9', $res['body']);

        $this->grant(self::EMP);
        $res = $this->request('query/query-searchmaintenance.php', ['pagibig' => ''], [], $this->asEmployee());
        $this->assertSame(200, $res['status']);
        $this->assertStringContainsString('678.9', $res['body']);
    }

    public function testSaveEndpointIsBlockedWithoutTheRight(): void
    {
        $post  = ['ee' => '1', 'er' => '2'];
        $count = fn() => (int) self::db()->query("SELECT COUNT(*) FROM pagibig")->fetchColumn();

        $this->assertSame(403, $this->request('query/query-maintenance.php', ['pagibig' => ''], $post, $this->asEmployee())['status']);
        $this->assertSame(1, $count());

        $this->grant(self::ADMIN);
        $this->assertSame(200, $this->request('query/query-maintenance.php', ['pagibig' => ''], $post, $this->asAdmin())['status']);
        $this->assertSame(2, $count());
    }

    public function testEditIsBlockedWithoutTheRight(): void
    {
        $ee = fn() => (float) self::db()->query("SELECT EE FROM pagibig WHERE PIID = 1")->fetchColumn();

        $res = $this->request('query/Query-updatemaintenance.php', ['updatepagibig' => '1'], ['ee' => '999', 'er' => '1'], $this->asEmployee());
        $this->assertSame(403, $res['status']);
        $this->assertEqualsWithDelta(123.45, $ee(), 0.01);
    }
}
