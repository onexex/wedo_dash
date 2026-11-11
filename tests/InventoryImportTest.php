<?php
namespace Wedo\Tests;

use Wedo\Tests\Support\AppTestCase;

/**
 * The projInventory import (includes/inventory-import-lib.php): old equipment
 * becomes Inventory items with holder, condition, disposal and history, a dry
 * run saves nothing, and a second run skips what is already there. The old
 * tables are created as TEMPORARY tables on the test connection.
 */
final class InventoryImportTest extends AppTestCase
{
    private const OLD_TABLES = ['tb_user', 'tb_brand', 'tb_eqpname', 'tb_equipment', 'tb_specsdsc', 'tb_eqpdeploy'];

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        require_once dirname(__DIR__) . '/includes/inventory-import-lib.php';
    }

    protected function setUp(): void
    {
        parent::setUp();
        date_default_timezone_set('Asia/Manila');
        $db = self::db();
        foreach ([['APP', 'Appliances', 'asset'], ['EQP', 'IT & Office Equipment', 'asset'], ['DOC', 'Documents', 'document'], ['OTH', 'Others', 'asset']] as $c) {
            $db->prepare("INSERT INTO inv_categories (Code, Name, Kind) VALUES (?, ?, ?)")->execute($c);
        }
        foreach (self::OLD_TABLES as $t) { $db->exec("DROP TEMPORARY TABLE IF EXISTS `$t`"); }
        $db->exec("CREATE TEMPORARY TABLE tb_user (id INT PRIMARY KEY, ESID VARCHAR(15))");
        $db->exec("CREATE TEMPORARY TABLE tb_brand (id INT PRIMARY KEY, brand_name VARCHAR(255))");
        $db->exec("CREATE TEMPORARY TABLE tb_equipment (id INT PRIMARY KEY, eqpn_id INT, eqpn_nm VARCHAR(50), sn VARCHAR(50), brand VARCHAR(30),
            model VARCHAR(30), status VARCHAR(20), `condition` VARCHAR(20), remarks LONGTEXT, user_id INT, created_at TIMESTAMP(6) NULL, updated_at TIMESTAMP(6) NULL)");
        $db->exec("CREATE TEMPORARY TABLE tb_specsdsc (id INT PRIMARY KEY, equip_id INT, specs_dsc VARCHAR(50), details VARCHAR(50))");
        $db->exec("CREATE TEMPORARY TABLE tb_eqpdeploy (id INT PRIMARY KEY, eqp_id INT, status VARCHAR(20), user_id INT, deployed_to TEXT,
            created_at TIMESTAMP(6) NULL, updated_at TIMESTAMP(6) NULL, remarks TEXT, issued_TSDR INT, date_Dep TEXT, date_Ret TEXT)");

        $db->exec("INSERT INTO tb_user VALUES (1, '" . self::ADMIN . "')");
        $db->exec("INSERT INTO tb_brand VALUES (1, 'LENOVO'), (2, 'SAMSUNG')");
        $eq = $db->prepare("INSERT INTO tb_equipment VALUES (?, 1, ?, ?, ?, ?, ?, ?, ?, 1, ?, ?)");
        $eq->execute([1, 'LAPTOP',         'PF-001', '1', 'T14',   'Stock',     'Working',   'W/ charger', '2024-01-05 09:00:00', '2024-06-01 10:00:00']);
        $eq->execute([2, 'IP CAMERA',      'CAM-01', '2', 'N/A',   'Deployed',  'N/A',       null,         '2024-02-01 09:00:00', '2024-02-01 09:00:00']);
        $eq->execute([3, 'MICROWAVE OVEN', 'MW-01',  '2', 'MS23',  'Disposed',  'Defective', 'Burnt',      '2023-01-01 09:00:00', '2025-03-10 15:00:00']);
        $eq->execute([4, 'MOUSE',          'MS-01',  '1', '',      'Defective', 'N/A',       null,         '2024-03-01 09:00:00', '2024-03-01 09:00:00']);
        $eq->execute([5, 'MONITOR',        'MN-01',  '1', 'L24',   'Deployed',  'Working',   null,         '2024-03-01 09:00:00', '2024-03-01 09:00:00']);
        $db->exec("INSERT INTO tb_specsdsc VALUES (1, 1, 'RAM', '16GB'), (2, 1, 'PROCESSOR', 'i5'), (3, 1, 'COLOR', 'N/A')");
        $dep = $db->prepare("INSERT INTO tb_eqpdeploy VALUES (?, ?, ?, 1, ?, NULL, NULL, ?, ?, ?, ?)");
        $dep->execute([1, 1, 'Returned', self::EMP, 'Training', 0, '2024-02-01 09:00 AM', '2024-03-01 05:30 PM']);
        $dep->execute([2, 1, 'Returned', null,      'OJT',      0, '2024-01-10 08:00 AM', '2024-01-20 05:00 PM']);
        $dep->execute([3, 2, 'Deployed', self::EMP, null,       2, '2024-04-02 01:15 PM', null]);
        $dep->execute([4, 5, 'Deployed', 'GONE-1',  null,       2, '2024-04-03 10:00 AM', null]);
        $dep->execute([5, 99, 'Returned', self::EMP, null,      0, '2024-04-03 10:00 AM', '2024-04-04 10:00 AM']);
        $_SESSION = ['id' => self::ADMIN];
    }

    protected function tearDown(): void
    {
        foreach (self::OLD_TABLES as $t) { self::db()->exec("DROP TEMPORARY TABLE IF EXISTS `$t`"); }
        parent::tearDown();
    }

    private function run_import(bool $apply): array
    {
        return invimp_run(self::db(), self::db(), ['apply' => $apply, 'location' => 'IT Room']);
    }

    private function item(string $serial): array
    {
        return $this->row("SELECT i.*, c.Code FROM inv_items i JOIN inv_categories c ON c.id = i.CategoryID WHERE SerialNo = ?", [$serial]);
    }

    public function testDryRunSavesNothing(): void
    {
        $r = $this->run_import(false);
        $this->assertTrue($r['ok']);
        $this->assertFalse($r['applied']);
        $this->assertSame(5, $r['items']);
        $this->assertSame('0', (string) $this->row("SELECT COUNT(*) AS n FROM inv_items")['n']);
        $this->assertSame('0', (string) $this->row("SELECT COUNT(*) AS n FROM inv_locations")['n']);
    }

    public function testImportsItemsWithTheirState(): void
    {
        $r = $this->run_import(true);
        $this->assertTrue($r['applied']);
        $this->assertSame(5, $r['items']);
        $this->assertSame(1, $r['issued']);
        $this->assertSame(1, $r['disposed']);

        $laptop = $this->item('PF-001');
        $this->assertSame('EQP', $laptop['Code']);
        $this->assertSame('Laptop', $laptop['Name']);
        $this->assertSame('LENOVO', $laptop['Brand']);
        $this->assertSame('Ram: 16GB; Processor: i5', $laptop['Description']);   // N/A specs left out
        $this->assertSame('active', $laptop['Status']);
        $this->assertSame('good', $laptop['ItemCondition']);
        $this->assertNull($laptop['HolderEmpID']);
        $this->assertSame('From projInventory #1. W/ charger', $laptop['Notes']);

        $cam = $this->item('CAM-01');
        $this->assertSame('IP Camera', $cam['Name']);
        $this->assertNull($cam['Model']);                                         // "N/A"
        $this->assertSame(self::EMP, $cam['HolderEmpID']);
        $this->assertSame('2024-04-02', $cam['IssuedOn']);
        $this->assertSame('2024-04-02 13:15:00', $cam['AckAt']);                  // TSDR issued = acknowledged

        $oven = $this->item('MW-01');
        $this->assertSame('APP', $oven['Code']);
        $this->assertSame('disposed', $oven['Status']);
        $this->assertSame('damaged', $oven['ItemCondition']);
        $this->assertSame('2025-03-10', $oven['DisposedOn']);
        $this->assertSame('Burnt', $oven['DisposalNote']);

        $this->assertSame('damaged', $this->item('MS-01')['ItemCondition']);       // Defective
        $this->assertSame('active', $this->item('MS-01')['Status']);

        $monitor = $this->item('MN-01');                                           // holder not an employee
        $this->assertNull($monitor['HolderEmpID']);
        $this->assertCount(2, $r['warnings']);
        $this->assertStringContainsString('GONE-1', implode("\n", $r['warnings']));
        $this->assertStringContainsString('#99', implode("\n", $r['warnings']));

        $loc = $this->row("SELECT id FROM inv_locations WHERE Name = 'IT Room'");
        $this->assertSame('5', (string) $this->row("SELECT COUNT(*) AS n FROM inv_items WHERE LocationID = ?", [$loc['id']])['n']);
        $this->assertSame(['EQP-0001', 'EQP-0002', 'EQP-0003', 'EQP-0004'], $r['tags']['EQP']);
        $this->assertSame(['APP-0001'], $r['tags']['APP']);
    }

    public function testHistoryIsInDateOrder(): void
    {
        $this->run_import(true);
        $id = $this->item('PF-001')['id'];
        $moves = $this->rows("SELECT Type, ToEmpID, FromEmpID, Notes, ByEmpID, CreatedAt FROM inv_movements WHERE ItemID = ? ORDER BY id", [$id]);
        $this->assertSame(['added', 'issued', 'returned', 'issued', 'returned'], array_column($moves, 'Type'));
        $this->assertSame(['2024-01-05 09:00:00', '2024-01-10 08:00:00', '2024-01-20 17:00:00', '2024-02-01 09:00:00', '2024-03-01 17:30:00'],
            array_column($moves, 'CreatedAt'));
        $this->assertNull($moves[1]['ToEmpID']);
        $this->assertSame('Recipient not recorded in projInventory. OJT', $moves[1]['Notes']);
        $this->assertSame(self::EMP, $moves[3]['ToEmpID']);
        $this->assertSame(self::EMP, $moves[4]['FromEmpID']);
        $this->assertSame(self::ADMIN, $moves[4]['ByEmpID']);

        // the item shows up for its holder in My Items
        $mine = inv_my_items(self::db(), self::EMP);
        $this->assertSame(['IP Camera'], array_column($mine, 'name'));
    }

    public function testSecondRunSkipsImportedItems(): void
    {
        $this->run_import(true);
        $r = $this->run_import(true);
        $this->assertSame(0, $r['items']);
        $this->assertCount(5, $r['skipped']);
        $this->assertSame('5', (string) $this->row("SELECT COUNT(*) AS n FROM inv_items")['n']);
    }

    public function testRefusesWithoutTheInventoryCategories(): void
    {
        self::db()->exec("DELETE FROM inv_categories WHERE Code = 'OTH'");
        $r = $this->run_import(true);
        $this->assertFalse($r['ok']);
        $this->assertStringContainsString('OTH', $r['msg']);
        $this->assertSame('0', (string) $this->row("SELECT COUNT(*) AS n FROM inv_items")['n']);
    }
}
