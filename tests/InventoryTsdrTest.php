<?php
namespace Wedo\Tests;

use Wedo\Tests\Support\AppTestCase;

/**
 * TSDR (Technical Services Data Report) in Inventory: the form starts from an
 * employee's issued items, numbers continue from the highest one, the rules on
 * services and items hold, an edit keeps returned items, the list searches,
 * and the old projInventory forms import (old tables as TEMPORARY tables).
 */
final class InventoryTsdrTest extends AppTestCase
{
    private const TOKEN = 'inv-test-token';
    private const OLD_TABLES = ['tb_user', 'tb_equipment', 'tb_eqpdeploy', 'tbl_tsdr', 'tbl_tsdrsbr', 'tbl_tsdritem'];

    private int $eqp;
    private int $hq;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        require_once dirname(__DIR__) . '/includes/inventory-import-lib.php';   // pulls in inventory-lib and inventory-tsdr-lib
    }

    protected function setUp(): void
    {
        parent::setUp();
        date_default_timezone_set('Asia/Manila');
        $db = self::db();
        $db->exec("UPDATE accessrights SET inventory = 2 WHERE EmpID = '" . self::ADMIN . "'");
        $db->exec("INSERT INTO inv_categories (Code, Name, Kind) VALUES ('EQP', 'IT & Office Equipment', 'asset')");
        $db->exec("INSERT INTO inv_locations (Name) VALUES ('IT Room')");
        $this->eqp = (int) $db->query("SELECT id FROM inv_categories WHERE Code = 'EQP'")->fetchColumn();
        $this->hq  = (int) $db->query("SELECT id FROM inv_locations WHERE Name = 'IT Room'")->fetchColumn();
        $_SESSION = ['id' => self::ADMIN];
    }

    protected function tearDown(): void
    {
        foreach (self::OLD_TABLES as $t) { self::db()->exec("DROP TEMPORARY TABLE IF EXISTS `$t`"); }
        parent::tearDown();
    }

    private function manager(): array { return $this->asAdmin() + ['inv_csrf' => self::TOKEN]; }

    private function act(string $action, array $post = [], ?array $session = null): array
    {
        $res = $this->request('query/inventory-action.php', [], ['action' => $action] + $post + ['token' => self::TOKEN], $session ?? $this->manager());
        $this->assertNoPhpErrors($res['body']);
        $res['json'] = json_decode($res['body'], true);
        return $res;
    }

    /** A laptop issued to the test employee; returns its id. */
    private function issued(string $serial, string $name = 'Laptop'): int
    {
        $r = $this->act('item_save', ['name' => $name, 'categoryId' => $this->eqp, 'locationId' => $this->hq, 'qty' => 1,
            'condition' => 'good', 'brand' => 'LENOVO', 'model' => 'T14', 'serial' => $serial]);
        $this->assertSame(200, $r['status'], $r['body']);
        $id = (int) $r['json']['id'];
        $this->assertSame(200, $this->act('issue', ['id' => $id, 'emp' => self::EMP])['status']);
        return $id;
    }

    private function save(array $over = []): array
    {
        return $this->act('tsdr_save', $over + ['emp' => self::EMP, 'services' => ['install', 'configure'], 'status' => 'done',
            'accountDept' => 'Accounting', 'dateNeeded' => '2026-11-12']);
    }

    public function testDraftTicksItemsWithoutATsdr(): void
    {
        $a = $this->issued('SN-A');
        $b = $this->issued('SN-B', 'Monitor');
        $r = $this->act('tsdr_draft', ['emp' => self::EMP]);
        $this->assertSame(200, $r['status'], $r['body']);
        $this->assertSame(self::EMP, $r['json']['employee']['empId']);
        $this->assertSame([$a => true, $b => true], array_column($r['json']['items'], 'pending', 'id'));
        $this->assertStringContainsString('Laptop · LENOVO T14 · SN SN-A (EQP-', $r['json']['items'][0]['text']);
        $this->assertSame(date('ymd') . '_1', $r['json']['nextNo']);
        $this->assertArrayHasKey('install', $r['json']['services']);
        $this->assertSame(self::ADMIN, $r['json']['defaults']['sysadId']);
        $this->assertSame('Choose the employee.', $this->act('tsdr_draft', ['emp' => ''])['json']['msg'], 'the TSDR tab asks for one first');

        $this->assertSame(200, $this->save(['items' => [$a]])['status']);
        $r = $this->act('tsdr_draft', ['emp' => self::EMP]);
        $this->assertSame([$a => false, $b => true], array_column($r['json']['items'], 'pending', 'id'), 'the TSDR covers the laptop now');
    }

    public function testNumbersContinueFromTheHighest(): void
    {
        self::db()->exec("INSERT INTO inv_tsdr (TsdrNo, Seq, EmpID, Requisitioner, Source) VALUES ('230105_41', 41, '" . self::EMP . "', 'Juan', 'projinventory')");
        $r = $this->save();
        $this->assertSame(200, $r['status'], $r['body']);
        $t = $this->row('SELECT * FROM inv_tsdr WHERE id = ?', [$r['json']['id']]);
        $this->assertSame(date('ymd') . '_42', $t['TsdrNo']);
        $this->assertSame('42', (string) $t['Seq']);
        $this->assertSame('install,configure', $t['Services']);
        $this->assertSame('Juan Santos Dela Cruz Jr', $t['Requisitioner']);
        $this->assertSame(self::ADMIN, $t['SysadEmpID'], 'SysAd defaults to whoever saves it');
        $this->assertNotNull($t['AccomplishedAt'], 'done means accomplished now when no time is given');
        $this->assertSame('wedo', $t['Source']);
        $this->assertSame(date('ymd') . '_43', $this->save()['json']['no']);
        $this->assertNotNull($this->row("SELECT DARSID FROM dars WHERE EmpActivity LIKE ?", ['%made TSDR ' . date('ymd') . '_42 for ' . self::EMP . '%']));
    }

    public function testRules(): void
    {
        $item = $this->issued('SN-A');
        $this->assertSame(422, $this->save(['services' => []])['status'], 'a service is required');
        $this->assertSame(422, $this->save(['services' => ['nonsense']])['status']);
        $this->assertSame(422, $this->save(['services' => ['others']])['status'], 'others needs the details');
        $this->assertSame(200, $this->save(['services' => ['others'], 'otherService' => 'Set up the printer'])['status']);
        $this->assertSame(422, $this->save(['emp' => 'NOBODY'])['status']);
        $this->assertSame(422, $this->save(['status' => 'maybe'])['status']);
        $this->assertSame(422, $this->save(['approvedBy' => 'NOBODY'])['status']);

        // an item issued to someone else (or not issued) cannot go on Juan's TSDR
        $this->act('return', ['id' => $item, 'location' => $this->hq]);
        $r = $this->save(['items' => [$item]]);
        $this->assertSame(422, $r['status']);
        $this->assertStringContainsString('is not issued to', $r['json']['msg']);
        $this->assertSame('1', (string) $this->row('SELECT COUNT(*) AS n FROM inv_tsdr')['n']);
    }

    public function testEditKeepsReturnedItemsAndLegacyLines(): void
    {
        $a = $this->issued('SN-A');
        $b = $this->issued('SN-B', 'Monitor');
        $id = (int) $this->save(['items' => [$a, $b], 'approvedBy' => self::ADMIN])['json']['id'];
        self::db()->prepare("INSERT INTO inv_tsdr_items (TsdrID, ItemID, ItemText) VALUES (?, NULL, 'KEYBOARD · SN OLD-1')")->execute([$id]);
        $this->act('return', ['id' => $a, 'location' => $this->hq]);

        $r = $this->save(['id' => $id, 'emp' => 'IGNORED', 'items' => [$a, $b], 'services' => ['troubleshoot'], 'status' => 'pending',
            'statusDetails' => 'Waiting for parts', 'approvedBy' => self::ADMIN]);
        $this->assertSame(200, $r['status'], $r['body']);
        $t = $this->act('tsdr', ['id' => $id])['json']['tsdr'];
        $this->assertSame(self::EMP, $t['empId'], 'the requisitioner does not change');
        $this->assertSame(['troubleshoot'], $t['services']);
        $this->assertSame('Pending', $t['statusLabel']);
        $this->assertSame('Ada Admin', $t['approvedBy']);
        $this->assertSame([$a, $b, null], array_column($t['items'], 'itemId'));
        $this->assertSame('KEYBOARD · SN OLD-1', $t['items'][2]['text']);

        // dropping the returned item is fine; adding it back now is not
        $this->assertSame(200, $this->save(['id' => $id, 'items' => [$b]])['status']);
        $this->assertSame(422, $this->save(['id' => $id, 'items' => [$a, $b]])['status']);
        $this->assertSame(422, $this->save(['id' => 999999])['status']);
    }

    public function testListAndSearch(): void
    {
        self::db()->exec("INSERT INTO inv_tsdr (TsdrNo, Seq, EmpID, Requisitioner, AccountDept, Source) VALUES ('230105_7', 7, '" . self::ADMIN . "', 'Ada Admin', 'Sales', 'projinventory')");
        $a = $this->issued('PF37WYW6');
        $this->save(['items' => [$a], 'accountDept' => 'Accounting']);

        $all = $this->act('tsdr_list')['json']['tsdrs'];
        $this->assertSame([date('ymd') . '_8', '230105_7'], array_column($all, 'no'), 'newest number first');
        $this->assertSame(1, $all[0]['itemCount']);
        $this->assertSame(['230105_7'], array_column($this->act('tsdr_list', ['q' => 'sales'])['json']['tsdrs'], 'no'));
        $this->assertSame([date('ymd') . '_8'], array_column($this->act('tsdr_list', ['q' => 'PF37'])['json']['tsdrs'], 'no'), 'item words match');
        $this->assertSame([date('ymd') . '_8'], array_column($this->act('tsdr_list', ['q' => 'juan accounting'])['json']['tsdrs'], 'no'));
        $this->assertSame(['230105_7'], array_column($this->act('tsdr_list', ['emp' => self::ADMIN])['json']['tsdrs'], 'no'));
        $this->assertSame([], $this->act('tsdr_list', ['q' => '100%'])['json']['tsdrs']);
    }

    private function added(string $serial, string $name = 'Laptop'): int
    {
        $r = $this->act('item_save', ['name' => $name, 'categoryId' => $this->eqp, 'locationId' => $this->hq, 'qty' => 1, 'condition' => 'good', 'serial' => $serial]);
        $this->assertSame(200, $r['status'], $r['body']);
        return (int) $r['json']['id'];
    }

    public function testIssueManyThenOneTsdr(): void
    {
        $a = $this->added('SN-A');
        $b = $this->added('SN-B', 'Monitor');
        $r = $this->act('issue_many', ['ids' => [$a, $b], 'emp' => self::EMP, 'date' => '2026-11-10', 'notes' => 'New hire kit']);
        $this->assertSame(200, $r['status'], $r['body']);
        $this->assertSame([$a, $b], $r['json']['ids']);
        $this->assertSame(self::EMP, $r['json']['empId']);
        foreach ([$a, $b] as $id) {
            $this->assertSame([self::EMP, '2026-11-10'], array_values($this->row('SELECT HolderEmpID, IssuedOn FROM inv_items WHERE id = ?', [$id])));
        }
        $this->assertSame('2', (string) $this->row("SELECT COUNT(*) AS n FROM inv_movements WHERE Type = 'issued' AND Notes = 'New hire kit'")['n']);

        // one TSDR for both, then each item and the person's list show it
        $no = $this->save(['items' => [$a, $b]])['json']['no'];
        $person = $this->act('person', ['emp' => self::EMP])['json']['items'];
        $this->assertSame([$no, $no], array_map(function ($i) { return $i['tsdr']['no']; }, $person));
        $listed = array_column($this->act('items', ['kind' => 'property'])['json']['items'], 'tsdr', 'id');
        $this->assertSame($no, $listed[$a]['no']);
        $detail = $this->act('item', ['id' => $a])['json'];
        $this->assertSame([$no], array_column($detail['tsdrs'], 'no'));
    }

    public function testIssueManyIsAllOrNothing(): void
    {
        $a = $this->added('SN-A');
        $b = $this->issued('SN-B', 'Monitor');   // already with Juan
        $r = $this->act('issue_many', ['ids' => [$a, $b], 'emp' => self::ADMIN]);
        $this->assertSame(422, $r['status']);
        $this->assertMatchesRegularExpression('/^EQP-\d+: This item is already issued/', $r['json']['msg']);
        $this->assertNull($this->row('SELECT HolderEmpID FROM inv_items WHERE id = ?', [$a])['HolderEmpID'], 'the first one is not issued either');
        $this->assertSame(422, $this->act('issue_many', ['ids' => [], 'emp' => self::EMP])['status']);
        $this->assertSame(422, $this->act('issue_many', ['ids' => [$a], 'emp' => 'NOBODY'])['status']);
    }

    public function testItemsWithoutATsdrSayNull(): void
    {
        $a = $this->issued('SN-A');
        $this->assertNull($this->act('person', ['emp' => self::EMP])['json']['items'][0]['tsdr']);
        $this->assertSame([], $this->act('item', ['id' => $a])['json']['tsdrs']);
    }

    public function testStaffCannotUseIt(): void
    {
        $staff = $this->asEmployee() + ['inv_csrf' => self::TOKEN];
        $this->assertSame(403, $this->act('tsdr_list', [], $staff)['status']);
        $this->assertSame(403, $this->act('tsdr_save', ['emp' => self::EMP, 'services' => ['install'], 'status' => 'done'], $staff)['status']);
    }

    // ------------------------------------------------------------------ projInventory import

    private function oldTables(): void
    {
        $db = self::db();
        foreach (self::OLD_TABLES as $t) { $db->exec("DROP TEMPORARY TABLE IF EXISTS `$t`"); }
        $db->exec("CREATE TEMPORARY TABLE tb_user (id INT PRIMARY KEY, empName VARCHAR(100), ESID VARCHAR(15))");
        $db->exec("CREATE TEMPORARY TABLE tb_equipment (id INT PRIMARY KEY, eqpn_nm VARCHAR(50), sn VARCHAR(50))");
        $db->exec("CREATE TEMPORARY TABLE tb_eqpdeploy (id INT PRIMARY KEY, eqp_id INT)");
        $db->exec("CREATE TEMPORARY TABLE tbl_tsdr (id INT PRIMARY KEY, id_tsdr TEXT, id_Emp VARCHAR(30), tsdr_AccDep TEXT, tsdr_Requis TEXT,
            tsdr_DateNeeded TEXT, tsdr_ApprovedBy TEXT, tsdr_RequestFrm TEXT, tsdr_RecievedBy TEXT, tsdr_SPA TEXT, tsdr_StatusReq TEXT,
            tsdr_StatusComment TEXT, tsdr_Eva TEXT, tsdr_Recom TEXT, tsdr_DTAccom TEXT, tsdr_otherspecify TEXT,
            created_at TIMESTAMP(6) NULL, updated_at TIMESTAMP(6) NULL)");
        $db->exec("CREATE TEMPORARY TABLE tbl_tsdrsbr (id_tsdrSbr INT PRIMARY KEY, id_tsdr TEXT, sbr_install INT, sbr_update INT, sbr_troubleshoot INT,
            sbr_transfer INT, sbr_format INT, sbr_load INT, sbr_create INT, sbr_pullout INT, sbr_convert INT, sbr_replace INT, sbr_configure INT,
            sbr_patch INT, sbr_newsoftware INT, sbr_software INT, sbr_actDec INT, sbr_others INT)");
        $db->exec("CREATE TEMPORARY TABLE tbl_tsdritem (id_tsdritem INT PRIMARY KEY, id_tsdr TEXT, id_eqp INT)");

        $db->exec("INSERT INTO tb_user VALUES (1, 'Admin Ada', '" . self::ADMIN . "')");
        $db->exec("INSERT INTO tb_equipment VALUES (1, 'LAPTOP', 'SN-A'), (2, 'KEYBOARD', 'GONE-9')");
        $db->exec("INSERT INTO tb_eqpdeploy VALUES (10, 1), (11, 2)");
        $t = $db->prepare("INSERT INTO tbl_tsdr VALUES (?, ?, ?, 'IT Dept', 'Juan', '2024-05-02', ?, ?, 'Mark', ?, ?, 'ok', 'Works', 'None', ?, ?, '2024-05-01 09:00:00', '2024-05-03 10:00:00')");
        $t->execute([1, '240501_5', self::EMP, self::ADMIN, '1', 'Admin Ada', '1', '2024-05-03 / 02:30 PM', null]);
        $t->execute([2, '240502_6', self::EMP, 'Someone', '2', 'Unknown Person', '4', '', 'Set up VPN']);
        $t->execute([3, 'BAD-NO', self::EMP, '', '1', '', '1', '', null]);
        $s = $db->prepare("INSERT INTO tbl_tsdrsbr (id_tsdrSbr, id_tsdr, sbr_install, sbr_configure, sbr_others) VALUES (?, ?, ?, ?, ?)");
        $s->execute([1, '240501_5', 0, 0, 0]);
        $s->execute([2, '240501_5', 1, 1, 0]);   // the later row wins
        $s->execute([3, '240502_6', 0, 0, 1]);
        $db->exec("INSERT INTO tbl_tsdritem VALUES (1, '240501_5', 10), (2, '240501_5', 11)");
    }

    public function testImportFromProjInventory(): void
    {
        $item = $this->issued('SN-A');
        $this->oldTables();
        $db = self::db();

        $dry = invimp_tsdr_run($db, $db, ['apply' => false]);
        $this->assertTrue($dry['ok']);
        $this->assertFalse($dry['applied']);
        $this->assertSame(2, $dry['tsdrs']);
        $this->assertCount(1, $dry['warnings'], 'BAD-NO is left out');
        $this->assertSame('0', (string) $this->row('SELECT COUNT(*) AS n FROM inv_tsdr')['n'], 'a dry run saves nothing');

        $r = invimp_tsdr_run($db, $db, ['apply' => true]);
        $this->assertTrue($r['applied']);
        $this->assertSame([2, 2, 1], [$r['tsdrs'], $r['items'], $r['unmatched']]);

        $a = $this->row("SELECT * FROM inv_tsdr WHERE TsdrNo = '240501_5'");
        $this->assertSame(['5', 'install,configure', 'done', 'it', 'projinventory'], [(string) $a['Seq'], $a['Services'], $a['Status'], $a['RequestedFrom'], $a['Source']]);
        $this->assertSame('Juan Santos Dela Cruz Jr', $a['Requisitioner']);
        $this->assertSame([self::ADMIN, 'Ada Admin'], [$a['SysadEmpID'], $a['Sysad']], 'SysAd found through tb_user');
        $this->assertSame([self::ADMIN, 'Ada Admin'], [$a['ApprovedByEmpID'], $a['ApprovedBy']]);
        $this->assertSame(['2024-05-02', '2024-05-03 14:30:00', '2024-05-01 09:00:00'], [$a['DateNeeded'], $a['AccomplishedAt'], $a['CreatedAt']]);
        $lines = $this->rows('SELECT ItemID, ItemText FROM inv_tsdr_items WHERE TsdrID = ? ORDER BY id', [$a['id']]);
        $this->assertSame([(string) $item, null], array_map(function ($l) { return $l['ItemID'] === null ? null : (string) $l['ItemID']; }, $lines));
        $this->assertStringContainsString('SN SN-A', $lines[0]['ItemText']);
        $this->assertSame('Keyboard · SN GONE-9', $lines[1]['ItemText'], 'unmatched lines stay as text');

        $b = $this->row("SELECT * FROM inv_tsdr WHERE TsdrNo = '240502_6'");
        $this->assertSame(['others', 'Set up VPN', 'others', 'dev', 'Unknown Person', null], [$b['Services'], $b['OtherService'], $b['Status'], $b['RequestedFrom'], $b['Sysad'], $b['SysadEmpID']]);

        // the next TSDR made here continues after the imported numbers; an old TSDR dated before the issue does not cover it
        $this->assertSame(date('ymd') . '_7', $this->save()['json']['no']);
        $this->assertSame([$item => true], array_column($this->act('tsdr_draft', ['emp' => self::EMP])['json']['items'], 'pending', 'id'));

        $again = invimp_tsdr_run($db, $db, ['apply' => true]);
        $this->assertSame([0, 2], [$again['tsdrs'], $again['skipped']], 'a second run skips them');
    }
}
