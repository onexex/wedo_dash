<?php
namespace Wedo\Tests;

use Wedo\Tests\Support\AppTestCase;

/**
 * Maintenance > Family Details for Parental (maintenance?parentalfamilydetails)
 * is gated by the `pfam` access right (sql/2026-10-05-add-parental-family-access-right.sql):
 * the menu item, the page, the save endpoint and the table-refresh endpoint.
 * It used to be open to every logged-in user.
 */
final class ParentalFamilyAccessTest extends AppTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        self::db()->exec("DELETE FROM parentalrel");
        self::db()->exec("INSERT INTO parentalrel (EmpID, DateofBirth, Name) VALUES ('" . self::EMP . "', '2024-03-02', 'Baby Dela Cruz')");
    }

    private function grant(string $empId): void
    {
        self::db()->exec("UPDATE accessrights SET pfam = 2 WHERE EmpID = '$empId'");
    }

    public function testMenuItemOnlyShowsWithTheRight(): void
    {
        $link = "//a[@href='maintenance?parentalfamilydetails']";
        $body = $this->request('idcard.php', [], [], $this->asAdmin())['body'];
        $this->assertNull($this->dom($body)->query($link)->item(0), 'hidden without pfam');

        $this->grant(self::ADMIN);
        $body = $this->request('idcard.php', [], [], $this->asAdmin())['body'];
        $this->assertNotNull($this->dom($body)->query($link)->item(0), 'shown with pfam');
    }

    public function testPageIsBlockedWithoutTheRight(): void
    {
        $res = $this->request('maintenance.php', ['parentalfamilydetails' => ''], [], $this->asEmployee());
        $this->assertSame(302, $res['status']);
        $this->assertStringNotContainsString('Baby Dela Cruz', $res['body']);
    }

    public function testPageOpensWithTheRight(): void
    {
        $this->grant(self::ADMIN);
        $res = $this->request('maintenance.php', ['parentalfamilydetails' => ''], [], $this->asAdmin());
        $this->assertSame(200, $res['status']);
        $this->assertStringContainsString('Baby Dela Cruz', $res['body']);
    }

    public function testListEndpointIsBlockedWithoutTheRight(): void
    {
        $res = $this->request('query/query-searchmaintenance.php', ['familyparental' => ''], [], $this->asEmployee());
        $this->assertSame(403, $res['status']);
        $this->assertStringNotContainsString('Baby Dela Cruz', $res['body']);

        $this->grant(self::EMP);
        $res = $this->request('query/query-searchmaintenance.php', ['familyparental' => ''], [], $this->asEmployee());
        $this->assertSame(200, $res['status']);
        $this->assertStringContainsString('Baby Dela Cruz', $res['body']);
    }

    public function testSaveEndpointIsBlockedWithoutTheRight(): void
    {
        $post = ['empname' => self::EMP, 'dob' => '2025-01-01', 'nameoffamily' => 'Second Baby'];
        $count = fn() => (int) self::db()->query("SELECT COUNT(*) FROM parentalrel")->fetchColumn();

        $this->assertSame(403, $this->request('query/query-maintenance.php', ['familyparental' => ''], $post, $this->asEmployee())['status']);
        $this->assertSame(401, $this->request('query/query-maintenance.php', ['familyparental' => ''], $post)['status'], 'not logged in');
        $this->assertSame(1, $count());

        $this->grant(self::ADMIN);
        $this->assertSame(200, $this->request('query/query-maintenance.php', ['familyparental' => ''], $post, $this->asAdmin())['status']);
        $this->assertSame(2, $count());
    }
}
