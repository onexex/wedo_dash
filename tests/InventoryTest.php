<?php
namespace Wedo\Tests;

use Wedo\Tests\Support\AppTestCase;

/**
 * Inventory: the register, custody and lots, My Items, supplies, repairs,
 * files, alerts, physical counts, and its links to separation and the 201 file.
 */
final class InventoryTest extends AppTestCase
{
    private const TOKEN = 'inv-test-token';

    private int $furn;
    private int $docs;
    private int $sup;
    private int $hq;
    private int $store;
    private string $uploadDir;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        require_once dirname(__DIR__) . '/includes/inventory-lib.php';
    }

    protected function setUp(): void
    {
        parent::setUp();
        date_default_timezone_set('Asia/Manila');
        $db = self::db();
        $db->exec("UPDATE accessrights SET inventory = 2, separation = 2 WHERE EmpID = '" . self::ADMIN . "'");
        foreach ([['FUR', 'Furniture', 'asset'], ['DOC', 'Documents', 'document'], ['SUP', 'Supplies', 'consumable']] as $c) {
            $db->prepare("INSERT INTO inv_categories (Code, Name, Kind) VALUES (?, ?, ?)")->execute($c);
        }
        $this->furn = (int) $db->query("SELECT id FROM inv_categories WHERE Code = 'FUR'")->fetchColumn();
        $this->docs = (int) $db->query("SELECT id FROM inv_categories WHERE Code = 'DOC'")->fetchColumn();
        $this->sup  = (int) $db->query("SELECT id FROM inv_categories WHERE Code = 'SUP'")->fetchColumn();
        foreach (['Head Office', 'Storeroom'] as $l) { $db->prepare("INSERT INTO inv_locations (Name) VALUES (?)")->execute([$l]); }
        $this->hq    = (int) $db->query("SELECT id FROM inv_locations WHERE Name = 'Head Office'")->fetchColumn();
        $this->store = (int) $db->query("SELECT id FROM inv_locations WHERE Name = 'Storeroom'")->fetchColumn();
        $this->uploadDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'wedo-inv-' . bin2hex(random_bytes(4));
        $_SESSION = ['id' => self::ADMIN];
    }

    protected function tearDown(): void
    {
        $rm = function ($d) use (&$rm) {
            if (!is_dir($d)) { return; }
            foreach (array_diff(scandir($d), ['.', '..']) as $f) { is_dir("$d/$f") ? $rm("$d/$f") : unlink("$d/$f"); }
            rmdir($d);
        };
        $rm($this->uploadDir);
        parent::tearDown();
    }

    private function manager(): array { return $this->asAdmin() + ['inv_csrf' => self::TOKEN]; }
    private function staff(): array { return $this->asEmployee() + ['inv_csrf' => self::TOKEN]; }

    private function act(string $action, array $post = [], ?array $session = null, bool $withToken = true): array
    {
        $post = ['action' => $action] + $post + ($withToken ? ['token' => self::TOKEN] : []);
        $res = $this->request('query/inventory-action.php', [], $post, $session ?? $this->manager());
        $this->assertNoPhpErrors($res['body']);
        $res['json'] = json_decode($res['body'], true);
        return $res;
    }

    /** Add an item through the endpoint; returns its id. */
    private function add(array $over = []): int
    {
        $r = $this->act('item_save', $over + ['name' => 'Office chair', 'categoryId' => $this->furn, 'locationId' => $this->hq, 'qty' => 1, 'condition' => 'good']);
        $this->assertSame(200, $r['status'], $r['body']);
        return (int) $r['json']['id'];
    }

    private function item(int $id): array { return $this->row('SELECT * FROM inv_items WHERE id = ?', [$id]); }
    private function moves(int $id): array { return array_column($this->rows('SELECT Type FROM inv_movements WHERE ItemID = ? ORDER BY id', [$id]), 'Type'); }

    // ------------------------------------------------------------------ core

    public function testEndpointGuards(): void
    {
        $this->assertSame(401, $this->request('query/inventory-action.php', [], ['action' => 'items'], null)['status']);
        $this->assertSame(405, $this->request('query/inventory-action.php', [], [], $this->manager())['status']);
        $this->assertSame(419, $this->act('items', [], null, false)['status']);
        $this->assertSame(403, $this->act('items', [], $this->staff())['status']);
        $this->assertSame(403, $this->act('item_save', ['name' => 'X', 'categoryId' => $this->furn, 'locationId' => $this->hq], $this->staff())['status']);
        $this->assertSame(0, (int) self::db()->query('SELECT COUNT(*) FROM inv_items')->fetchColumn());
        $this->assertSame(200, $this->act('my_items', [], $this->staff())['status']);
        $this->assertSame(400, $this->act('nope')['status']);
    }

    public function testTagsRunPerCategoryAndATypedTagIsKept(): void
    {
        $a = $this->add();
        $b = $this->add();
        $this->assertSame('FUR-0001', $this->item($a)['TagNo']);
        $this->assertSame('FUR-0002', $this->item($b)['TagNo']);
        $this->assertSame(['added'], $this->moves($a));

        $this->assertSame('OLD-77', $this->item($this->add(['tag' => 'old-77']))['TagNo']);
        $this->assertSame(422, $this->act('item_save', ['name' => 'Dup', 'categoryId' => $this->furn, 'locationId' => $this->hq, 'tag' => 'OLD-77'])['status']);
        $this->assertSame(422, $this->act('item_save', ['name' => 'Bad', 'categoryId' => $this->furn, 'locationId' => $this->hq, 'tag' => 'a b'])['status']);
    }

    public function testNextTagSkipsATypedStickerNumber(): void
    {
        $this->add(['tag' => 'FUR-0001']);
        $this->assertSame('FUR-0002', $this->item($this->add())['TagNo']);
    }

    public function testValidationKeepsBadValuesOut(): void
    {
        $this->assertSame(422, $this->act('item_save', ['name' => ' ', 'categoryId' => $this->furn, 'locationId' => $this->hq])['status']);
        $this->assertSame(422, $this->act('item_save', ['name' => 'X', 'categoryId' => 999, 'locationId' => $this->hq])['status']);
        $this->assertSame(422, $this->act('item_save', ['name' => 'X', 'categoryId' => $this->furn, 'locationId' => 999])['status']);
        $this->assertSame(422, $this->act('item_save', ['name' => 'X', 'categoryId' => $this->furn, 'locationId' => $this->hq, 'qty' => 0])['status']);
        $this->assertSame(422, $this->act('item_save', ['name' => 'X', 'categoryId' => $this->furn, 'locationId' => $this->hq, 'unitCost' => 'abc'])['status']);
        $id = $this->add(['acquiredOn' => 'not a date', 'warrantyUntil' => '', 'unitCost' => '1,250.50']);
        $row = $this->item($id);
        $this->assertNull($row['AcquiredOn']);
        $this->assertNull($row['WarrantyUntil']);
        $this->assertSame('1250.50', $row['UnitCost']);
    }

    public function testEditLeavesCustodyAlone(): void
    {
        $id = $this->add();
        $r = $this->act('item_save', ['id' => $id, 'name' => 'Better chair', 'categoryId' => $this->furn, 'qty' => 2, 'condition' => 'fair', 'locationId' => $this->store]);
        $this->assertSame(200, $r['status'], $r['body']);
        $row = $this->item($id);
        $this->assertSame('Better chair', $row['Name']);
        $this->assertSame('2', (string) $row['Qty']);
        $this->assertSame($this->hq, (int) $row['LocationID']);
        $this->assertSame(['added', 'edited'], $this->moves($id));
    }

    public function testDeleteOnlyWithoutHistory(): void
    {
        $free = $this->add();
        $this->assertSame(200, $this->act('item_delete', ['id' => $free])['status']);
        $this->assertNull($this->row('SELECT id FROM inv_items WHERE id = ?', [$free]));
        $this->assertSame([], $this->moves($free));

        $used = $this->add();
        $this->act('issue', ['id' => $used, 'emp' => self::EMP]);
        $this->act('return', ['id' => $used]);
        $this->assertSame(422, $this->act('item_delete', ['id' => $used])['status']);
    }

    public function testCategoryKindAndCodeFreezeOnceUsedLocationsNeverDelete(): void
    {
        $this->add();
        $this->assertSame(422, $this->act('cat_save', ['id' => $this->furn, 'code' => 'FUR', 'name' => 'Furniture', 'kind' => 'consumable', 'active' => 1])['status']);
        $this->assertSame(422, $this->act('cat_save', ['id' => $this->furn, 'code' => 'FRN', 'name' => 'Furniture', 'kind' => 'asset', 'active' => 1])['status']);
        $this->assertSame(200, $this->act('cat_save', ['id' => $this->furn, 'code' => 'FUR', 'name' => 'Furniture & Fittings', 'kind' => 'asset', 'active' => 1])['status']);
        $this->assertSame(422, $this->act('cat_save', ['code' => 'FUR', 'name' => 'Again', 'kind' => 'asset'])['status']);

        $this->assertSame(200, $this->act('loc_save', ['id' => $this->hq, 'name' => 'Head Office'])['status']);   // no `active`: switched off
        $this->assertSame('0', (string) $this->row('SELECT Active FROM inv_locations WHERE id = ?', [$this->hq])['Active']);
        $this->assertSame(422, $this->act('item_save', ['name' => 'X', 'categoryId' => $this->furn, 'locationId' => $this->hq])['status']);
        $this->assertSame(422, $this->act('loc_save', ['name' => 'Storeroom', 'active' => 1])['status']);
    }

    // ------------------------------------------------------------------ custody and lots

    public function testIssueSetsHolderAndClearsConfirmation(): void
    {
        $id = $this->add();
        self::db()->exec("UPDATE inv_items SET AckAt = '2026-01-01 00:00:00' WHERE id = $id");
        $r = $this->act('issue', ['id' => $id, 'emp' => self::EMP, 'date' => '2026-11-01']);
        $this->assertSame(200, $r['status'], $r['body']);
        $row = $this->item($id);
        $this->assertSame(self::EMP, $row['HolderEmpID']);
        $this->assertSame('2026-11-01', $row['IssuedOn']);
        $this->assertNull($row['AckAt']);
        $this->assertSame(422, $this->act('issue', ['id' => $id, 'emp' => self::ADMIN])['status'], 'already issued');
    }

    public function testEmployeeSearchListsOnlyPeopleStillEmployed(): void
    {
        $names = fn() => array_column($this->act('emp_search', ['q' => 'Juan'])['json']['employees'], 'empId');
        $this->assertContains(self::EMP, $names());
        self::db()->exec("UPDATE employees SET EmpStatusID = 2 WHERE EmpID = '" . self::EMP . "'");
        $this->assertNotContains(self::EMP, $names());
    }

    public function testStatsCountTheRegister(): void
    {
        $this->add(['qty' => 3, 'unitCost' => 100]);
        $id = $this->add();
        $this->act('issue', ['id' => $id, 'emp' => self::EMP]);
        $s = $this->act('stats')['json']['stats'];
        $this->assertSame(2, $s['items']);
        $this->assertSame(1, $s['issued']);
        $this->assertEquals(300, $s['cost']);
    }

    public function testCannotIssueToSomeoneWhoLeftOrIssueASupply(): void
    {
        $id = $this->add();
        self::db()->exec("UPDATE employees SET EmpStatusID = 2 WHERE EmpID = '" . self::EMP . "'");
        $this->assertSame(422, $this->act('issue', ['id' => $id, 'emp' => self::EMP])['status']);
        self::db()->exec("UPDATE employees SET EmpStatusID = 1 WHERE EmpID = '" . self::EMP . "'");
        $sup = $this->add(['name' => 'Bond paper', 'categoryId' => $this->sup, 'qty' => 5]);
        $this->assertSame(422, $this->act('issue', ['id' => $sup, 'emp' => self::EMP])['status']);
    }

    public function testEmployeeSeesAndConfirmsOnlyTheirOwnItems(): void
    {
        $mine = $this->add(['unitCost' => 500]);
        $this->act('issue', ['id' => $mine, 'emp' => self::EMP]);
        $other = $this->add();
        $this->act('issue', ['id' => $other, 'emp' => self::ADMIN]);

        $r = $this->act('my_items', [], $this->staff());
        $this->assertCount(1, $r['json']['items']);
        $this->assertSame(1, $r['json']['pending']);
        $this->assertArrayNotHasKey('unitCost', $r['json']['items'][0], 'costs stay off the employee view');

        $this->assertSame(422, $this->act('my_ack', ['id' => $other], $this->staff())['status']);
        $this->assertNull($this->item($other)['AckAt']);
        $this->assertSame(200, $this->act('my_ack', ['id' => $mine], $this->staff())['status']);
        $this->assertNotNull($this->item($mine)['AckAt']);
        $this->assertContains('acknowledged', $this->moves($mine));
        $this->assertSame(0, $this->act('my_items', [], $this->staff())['json']['pending']);
    }

    public function testReturnAndTransfer(): void
    {
        $id = $this->add();
        $this->assertSame(422, $this->act('return', ['id' => $id])['status'], 'not issued');
        $this->act('issue', ['id' => $id, 'emp' => self::EMP]);
        $this->assertSame(422, $this->act('transfer', ['id' => $id, 'location' => $this->store])['status'], 'issued items stay put');
        $this->assertSame(200, $this->act('return', ['id' => $id, 'location' => $this->store, 'condition' => 'poor'])['status']);
        $row = $this->item($id);
        $this->assertNull($row['HolderEmpID']);
        $this->assertSame($this->store, (int) $row['LocationID']);
        $this->assertSame('poor', $row['ItemCondition']);

        $b = $this->add();
        $this->assertSame(422, $this->act('transfer', ['ids' => [$id, $b], 'location' => $this->store])['status'], 'one is already there: nothing moves');
        $this->assertSame($this->hq, (int) $this->item($b)['LocationID']);
        $this->assertSame(200, $this->act('transfer', ['ids' => [$b], 'location' => $this->store, 'spot' => 'Rack 2'])['status']);
        $this->assertSame('Rack 2', $this->item($b)['Spot']);
    }

    public function testPartialIssueSplitsTheLotUnderTheRoot(): void
    {
        $root = $this->add(['qty' => 20]);
        $r = $this->act('issue', ['id' => $root, 'emp' => self::EMP, 'qty' => 5]);
        $child = (int) $r['json']['id'];
        $this->assertNotSame($root, $child);
        $this->assertSame('FUR-0001-1', $this->item($child)['TagNo']);
        $this->assertSame(15, (int) $this->item($root)['Qty']);
        $this->assertSame(5, (int) $this->item($child)['Qty']);
        $this->assertSame($root, (int) $this->item($child)['ParentID']);
        $this->assertContains('split', $this->moves($root));
        $this->assertContains('split', $this->moves($child));

        // splitting the child again still hangs under the root, with the root's numbering
        $grand = (int) $this->act('return', ['id' => $child, 'qty' => 2])['json']['id'];
        $this->assertSame($root, (int) $this->item($grand)['ParentID']);
        $this->assertSame('FUR-0001-2', $this->item($grand)['TagNo']);
        $this->assertNull($this->item($grand)['HolderEmpID']);
        $this->assertSame(self::EMP, $this->item($child)['HolderEmpID']);
        $this->assertSame(3, (int) $this->item($child)['Qty']);

        $this->assertSame(422, $this->act('issue', ['id' => $root, 'emp' => self::EMP, 'qty' => 99])['status']);
        $this->assertSame(422, $this->act('issue', ['id' => $root, 'emp' => self::EMP, 'qty' => 0])['status']);
    }

    public function testLotMergeBackIsSoftAndGuarded(): void
    {
        $root = $this->add(['qty' => 10]);
        $child = (int) $this->act('issue', ['id' => $root, 'emp' => self::EMP, 'qty' => 4])['json']['id'];
        $this->assertSame(422, $this->act('lot_merge', ['id' => $child])['status'], 'still issued');
        $this->act('return', ['id' => $child, 'location' => $this->store]);
        $this->assertSame(422, $this->act('lot_merge', ['id' => $child])['status'], 'elsewhere');
        $this->act('transfer', ['id' => $child, 'location' => $this->hq]);
        $this->act('item_save', ['id' => $child, 'name' => 'Office chair', 'categoryId' => $this->furn, 'qty' => 4, 'condition' => 'damaged']);
        $this->assertSame(422, $this->act('lot_merge', ['id' => $child])['status'], 'other condition');
        $this->act('item_save', ['id' => $child, 'name' => 'Office chair', 'categoryId' => $this->furn, 'qty' => 4, 'condition' => 'good']);

        $this->assertSame(200, $this->act('lot_merge', ['id' => $child])['status']);
        $this->assertSame(10, (int) $this->item($root)['Qty']);
        $row = $this->item($child);
        $this->assertSame('merged', $row['Status']);
        $this->assertSame(0, (int) $row['Qty']);
        $this->assertSame($root, (int) $row['MergedIntoID']);
        $this->assertSame($root, (int) $this->act('tag_lookup', ['tag' => $row['TagNo']])['json']['id'], 'the old sticker still resolves');
        $list = array_column($this->act('items')['json']['items'], 'id');
        $this->assertNotContains($child, $list);
    }

    public function testLostKeepsTheHolderFoundRestoresDisposeReleases(): void
    {
        $id = $this->add();
        $this->act('issue', ['id' => $id, 'emp' => self::EMP]);
        $this->assertSame(200, $this->act('mark_lost', ['id' => $id, 'notes' => 'Left in a taxi'])['status']);
        $this->assertSame('lost', $this->item($id)['Status']);
        $this->assertSame(self::EMP, $this->item($id)['HolderEmpID']);
        $this->assertSame(422, $this->act('return', ['id' => $id])['status'], 'lost: find it first');
        $this->assertSame(200, $this->act('mark_found', ['id' => $id])['status']);
        $this->assertSame('active', $this->item($id)['Status']);

        $this->act('mark_lost', ['id' => $id]);
        $this->assertSame(422, $this->act('dispose', ['id' => $id, 'method' => 'nope', 'date' => '2026-11-01'])['status']);
        $this->assertSame(422, $this->act('dispose', ['id' => $id, 'method' => 'other'])['status'], 'needs a date');
        $this->assertSame(200, $this->act('dispose', ['id' => $id, 'method' => 'other', 'date' => '2026-11-01', 'notes' => 'Written off'])['status']);
        $row = $this->item($id);
        $this->assertSame('disposed', $row['Status']);
        $this->assertNull($row['HolderEmpID']);
        $this->assertNotContains($id, array_column($this->act('items')['json']['items'], 'id'));
        $this->assertContains($id, array_column($this->act('items', ['filterStatus' => 'disposed'])['json']['items'], 'id'));
    }

    // ------------------------------------------------------------------ repairs, files, alerts

    public function testRepairCycleAndHolderReport(): void
    {
        $id = $this->add();
        $this->act('issue', ['id' => $id, 'emp' => self::EMP]);
        $this->assertSame(422, $this->act('my_report', ['id' => $id, 'problem' => 'x'], $this->manager())['status'], 'not the holder');
        $this->assertSame(422, $this->act('my_report', ['id' => $id, 'problem' => ' '], $this->staff())['status']);
        $this->assertSame(200, $this->act('my_report', ['id' => $id, 'problem' => 'Wheel broke'], $this->staff())['status']);
        $this->assertSame(422, $this->act('my_report', ['id' => $id, 'problem' => 'Again'], $this->staff())['status'], 'no duplicates');
        $rid = (int) $this->row("SELECT id FROM inv_repairs WHERE ItemID = ?", [$id])['id'];

        $this->assertSame(200, $this->act('repair_open', ['repairId' => $rid, 'vendor' => 'FixIt'])['status']);
        $this->assertSame('repair', $this->item($id)['Status']);
        $this->assertSame(422, $this->act('repair_open', ['repairId' => $rid])['status'], 'already handled');
        $this->assertSame(422, $this->act('dispose', ['id' => $id, 'method' => 'other', 'date' => '2026-11-01'])['status'], 'open repair');

        $this->assertSame(200, $this->act('repair_close', ['repairId' => $rid, 'cost' => '350', 'result' => 'New wheel'])['status']);
        $this->assertSame('active', $this->item($id)['Status']);
        $this->assertSame('350.00', $this->row('SELECT Cost FROM inv_repairs WHERE id = ?', [$rid])['Cost']);
    }

    public function testClosingOneRepairKeepsAnotherOpen(): void
    {
        $id = $this->add();
        $r1 = (int) $this->act('repair_open', ['id' => $id, 'problem' => 'One'])['json']['id'];
        self::db()->exec("INSERT INTO inv_repairs (ItemID, Status, Problem) VALUES ($id, 'open', 'Two')");
        $this->act('repair_close', ['repairId' => $r1]);
        $this->assertSame('repair', $this->item($id)['Status']);
    }

    public function testPhotoIsRedrawnAndAFakePdfIsRefused(): void
    {
        $id = $this->add();
        $png = tempnam(sys_get_temp_dir(), 'inv');
        $im = imagecreatetruecolor(120, 90);
        imagepng($im, $png);
        $f = ['name' => 'chair.png', 'type' => 'image/png', 'tmp_name' => $png, 'error' => UPLOAD_ERR_OK, 'size' => filesize($png)];
        $r = \inv_file_add(self::db(), $id, 'photo', $f, self::ADMIN, false, $this->uploadDir);
        $this->assertTrue($r['ok'], $r['msg'] ?? '');
        $row = $this->row('SELECT * FROM inv_files WHERE id = ?', [$r['id']]);
        $this->assertStringEndsWith('.jpg', $row['FileKey']);
        $path = \inv_file_path($row['FileKey'], $this->uploadDir);
        $this->assertNotNull($path);
        $this->assertSame(IMAGETYPE_JPEG, getimagesize($path)[2]);
        $this->assertFileExists($this->uploadDir . '/.htaccess');

        $fake = tempnam(sys_get_temp_dir(), 'inv');
        file_put_contents($fake, 'just some plain words, not a pdf');
        $bad = \inv_file_add(self::db(), $id, 'scan', ['name' => 'permit.pdf', 'tmp_name' => $fake, 'error' => UPLOAD_ERR_OK, 'size' => 30], self::ADMIN, false, $this->uploadDir);
        $this->assertFalse($bad['ok']);
        $wrong = \inv_file_add(self::db(), $id, 'scan', ['name' => 'notes.txt', 'tmp_name' => $fake, 'error' => UPLOAD_ERR_OK, 'size' => 30], self::ADMIN, false, $this->uploadDir);
        $this->assertFalse($wrong['ok']);
        $this->assertSame(422, $this->act('item_delete', ['id' => $id])['status'], 'files are history');

        $this->assertTrue(\inv_file_delete(self::db(), $r['id'], $this->uploadDir)['ok']);
        $this->assertFileDoesNotExist($path);
        @unlink($png); @unlink($fake);
    }

    public function testFileAccessByViewer(): void
    {
        $id = $this->add();
        $this->act('issue', ['id' => $id, 'emp' => self::EMP]);
        foreach (['photo', 'scan'] as $k) {
            self::db()->prepare("INSERT INTO inv_files (ItemID, Kind, FileKey) VALUES (?, ?, '2026/11/" . str_repeat('a', 32) . ".jpg')")->execute([$id, $k]);
        }
        $photo = (int) $this->row("SELECT id FROM inv_files WHERE Kind = 'photo'")['id'];
        $scan  = (int) $this->row("SELECT id FROM inv_files WHERE Kind = 'scan'")['id'];
        $this->assertNotNull(\inv_file_for_viewer(self::db(), $scan, self::ADMIN, true), 'manager: everything');
        $this->assertNotNull(\inv_file_for_viewer(self::db(), $photo, self::EMP, false), 'holder: photos');
        $this->assertNull(\inv_file_for_viewer(self::db(), $scan, self::EMP, false), 'holder: no scans');
        $this->assertNull(\inv_file_for_viewer(self::db(), $photo, 'someone-else', false));

        $this->assertSame(401, $this->request('query/inventory-file.php', ['id' => $photo], [], null)['status']);
        $this->assertSame(403, $this->request('query/inventory-file.php', ['id' => $scan], [], $this->staff())['status']);
    }

    public function testFileKeysAreChecked(): void
    {
        $this->assertNull(\inv_file_path('../../w_conn.php', $this->uploadDir));
        $this->assertNull(\inv_file_path('2026/11/' . str_repeat('a', 32) . '.php', $this->uploadDir));
    }

    public function testAlerts(): void
    {
        $day = fn(int $n) => (new \DateTime("$n days"))->format('Y-m-d');
        $soon = $this->add(['name' => 'Business permit', 'categoryId' => $this->docs, 'expiresOn' => $day(10), 'remindDays' => 30]);
        $this->add(['name' => 'Far off', 'categoryId' => $this->docs, 'expiresOn' => $day(90), 'remindDays' => 30]);
        $old = $this->add(['name' => 'Old license', 'categoryId' => $this->docs, 'expiresOn' => $day(-3), 'remindDays' => 30]);
        $this->add(['name' => 'Aircon', 'warrantyUntil' => $day(20)]);
        $this->add(['name' => 'Long warranty', 'warrantyUntil' => $day(200)]);
        $this->add(['name' => 'Ink', 'categoryId' => $this->sup, 'qty' => 2, 'reorderLevel' => 5]);
        $this->add(['name' => 'Enough ink', 'categoryId' => $this->sup, 'qty' => 9, 'reorderLevel' => 5]);
        $stale = $this->add(['name' => 'Laptop']);
        $this->act('issue', ['id' => $stale, 'emp' => self::EMP, 'date' => $day(-10)]);
        $fresh = $this->add(['name' => 'Mouse']);
        $this->act('issue', ['id' => $fresh, 'emp' => self::EMP]);
        $this->act('my_report', ['id' => $fresh, 'problem' => 'Broken'], $this->staff());

        $alerts = $this->act('alerts')['json']['alerts'];
        $by = [];
        foreach ($alerts as $a) { $by[$a['type']][] = $a['name']; }
        $this->assertEqualsCanonicalizing(['Business permit', 'Old license'], $by['expiry']);
        $this->assertSame(['Aircon'], $by['warranty']);
        $this->assertSame(['Ink'], $by['low']);
        $this->assertSame(['Mouse'], $by['problem']);
        $this->assertSame(['Laptop'], $by['unconfirmed']);
        $this->assertSame(count($alerts), \inv_alert_count(self::db()));
        $tones = array_column(array_filter($alerts, fn($a) => $a['type'] === 'expiry'), 'tone', 'itemId');
        $this->assertSame('warn', $tones[$soon]);
        $this->assertSame('danger', $tones[$old]);

        $this->act('dispose', ['id' => $old, 'method' => 'other', 'date' => $day(0)]);
        $this->assertNotContains('Old license', array_column($this->act('alerts')['json']['alerts'], 'name'));
    }

    // ------------------------------------------------------------------ consumables

    private function supply(int $qty = 10, array $over = []): int
    {
        return $this->add($over + ['name' => 'Bond paper', 'categoryId' => $this->sup, 'qty' => $qty, 'unit' => 'ream']);
    }

    private function ledgerSum(int $id): int
    {
        return (int) $this->row('SELECT COALESCE(SUM(Qty), 0) AS s FROM inv_stock_moves WHERE ItemID = ?', [$id])['s'];
    }

    public function testOpeningBalanceAndStockInUpdatesCost(): void
    {
        $id = $this->supply(10);
        $this->assertSame(10, $this->ledgerSum($id));
        $r = $this->act('stock_in', ['id' => $id, 'qty' => 5, 'unitCost' => '240.50', 'supplier' => 'Paper Co', 'reference' => 'PO-1']);
        $this->assertSame(200, $r['status'], $r['body']);
        $this->assertSame(15, $r['json']['balance']);
        $row = $this->item($id);
        $this->assertSame(15, (int) $row['Qty']);
        $this->assertSame('240.50', $row['UnitCost']);
        $this->assertSame('Paper Co', $row['Supplier']);
        $this->assertSame(15, (int) $this->row('SELECT BalanceAfter FROM inv_stock_moves WHERE ItemID = ? ORDER BY id DESC', [$id])['BalanceAfter']);
        $this->assertSame(422, $this->act('stock_in', ['id' => $id, 'qty' => 0])['status']);
    }

    public function testStockOutNeverGoesNegativeAndRecordsTheDepartment(): void
    {
        $id = $this->supply(3);
        $this->assertSame(422, $this->act('stock_out', ['id' => $id, 'qty' => 4, 'emp' => self::EMP, 'notes' => 'Reports'])['status']);
        $this->assertSame(3, (int) $this->item($id)['Qty']);
        $this->assertSame(422, $this->act('stock_out', ['id' => $id, 'qty' => 1, 'emp' => self::EMP])['status'], 'needs a purpose');
        $this->assertSame(422, $this->act('stock_out', ['id' => $id, 'qty' => 1, 'notes' => 'Reports'])['status'], 'needs a receiver');

        $this->assertSame(200, $this->act('stock_out', ['id' => $id, 'qty' => 2, 'emp' => self::EMP, 'notes' => 'Reports'])['status']);
        $row = $this->row("SELECT * FROM inv_stock_moves WHERE ItemID = ? AND Type = 'out'", [$id]);
        $this->assertSame(-2, (int) $row['Qty']);
        $this->assertSame(1, (int) $row['ToDepartmentID'], "the employee's department is kept on the row");
        $this->assertSame(200, $this->act('stock_out', ['id' => $id, 'qty' => 1, 'department' => 2, 'notes' => 'Pantry'])['status']);
        $this->assertSame(0, (int) $this->item($id)['Qty']);
    }

    public function testAdjustNeedsAReasonAndTheLedgerAlwaysSumsToTheBalance(): void
    {
        $id = $this->supply(10);
        $this->assertSame(422, $this->act('stock_adjust', ['id' => $id, 'qty' => 8])['status']);
        $this->assertSame(422, $this->act('stock_adjust', ['id' => $id, 'qty' => 10, 'notes' => 'Same'])['status']);
        $this->assertSame(200, $this->act('stock_adjust', ['id' => $id, 'qty' => 8, 'notes' => 'Water damage'])['status']);
        $this->act('stock_in', ['id' => $id, 'qty' => 4]);
        $this->act('stock_out', ['id' => $id, 'qty' => 3, 'emp' => self::EMP, 'notes' => 'x']);
        $this->assertSame(9, (int) $this->item($id)['Qty']);
        $this->assertSame(9, $this->ledgerSum($id));
    }

    public function testKindsDoNotCross(): void
    {
        $asset = $this->add();
        $sup = $this->supply(5);
        $this->assertSame(422, $this->act('stock_in', ['id' => $asset, 'qty' => 1])['status']);
        $this->assertSame(422, $this->act('stock_out', ['id' => $asset, 'qty' => 1, 'emp' => self::EMP, 'notes' => 'x'])['status']);
        $this->assertSame(422, $this->act('transfer', ['id' => $sup, 'location' => $this->store])['status']);
        $this->assertSame(422, $this->act('dispose', ['id' => $sup, 'method' => 'other', 'date' => '2026-11-01'])['status']);
        $this->assertSame(422, $this->act('repair_open', ['id' => $sup, 'problem' => 'x'])['status']);
        // a consumable's quantity can't be edited around the ledger
        $this->act('item_save', ['id' => $sup, 'name' => 'Paper', 'categoryId' => $this->sup, 'qty' => 999, 'condition' => 'good']);
        $this->assertSame(5, (int) $this->item($sup)['Qty']);
    }

    public function testUsageReportGroupsInARange(): void
    {
        $id = $this->supply(20, ['unitCost' => 100]);
        $this->act('stock_out', ['id' => $id, 'qty' => 2, 'emp' => self::EMP, 'notes' => 'a']);
        $this->act('stock_out', ['id' => $id, 'qty' => 3, 'emp' => self::EMP, 'notes' => 'b']);
        $this->act('stock_out', ['id' => $id, 'qty' => 4, 'department' => 2, 'notes' => 'c']);
        $this->act('stock_in', ['id' => $id, 'qty' => 9]);
        $today = date('Y-m-d');
        $rows = $this->act('usage_report', ['from' => $today, 'to' => $today])['json']['rows'];
        $this->assertCount(2, $rows);
        $byDept = array_column($rows, null, 'department');
        $this->assertSame(5, $byDept['Engineering']['released']);
        $this->assertSame(2, $byDept['Engineering']['lines']);
        $this->assertSame('Juan Dela Cruz', $byDept['Engineering']['employee']);
        $this->assertEquals(500, $byDept['Engineering']['value']);
        $this->assertSame(4, $byDept['People']['released']);
        $this->assertSame([], $this->act('usage_report', ['from' => '2020-01-01', 'to' => '2020-01-31'])['json']['rows']);
    }

    // ------------------------------------------------------------------ counts

    private function countLine(int $count, int $item): ?array
    {
        return $this->row('SELECT * FROM inv_count_lines WHERE CountID = ? AND ItemID = ?', [$count, $item]);
    }

    public function testStartSnapshotsTheLocationOneOpenCountEach(): void
    {
        $a = $this->add();
        $gone = $this->add();
        $this->act('dispose', ['id' => $gone, 'method' => 'other', 'date' => '2026-11-01']);
        $lost = $this->add();
        $this->act('mark_lost', ['id' => $lost]);
        $elsewhere = $this->add(['locationId' => $this->store]);

        $c = (int) $this->act('count_start', ['location' => $this->hq])['json']['id'];
        $this->assertNotNull($this->countLine($c, $a));
        foreach ([$gone, $lost, $elsewhere] as $not) { $this->assertNull($this->countLine($c, $not)); }
        $this->assertSame(422, $this->act('count_start', ['location' => $this->hq])['status']);
        $this->assertSame(200, $this->act('count_start', ['location' => $this->store])['status']);
    }

    public function testCheckByIdAndTagResolvesLotStickersAndFlagsMisplaced(): void
    {
        $root = $this->add(['qty' => 10]);
        $moved = (int) $this->act('transfer', ['id' => $root, 'location' => $this->store, 'qty' => 4])['json']['moved'];
        $this->assertSame(1, $moved);
        $child = (int) $this->row('SELECT id FROM inv_items WHERE ParentID = ?', [$root])['id'];
        $this->assertSame($this->store, (int) $this->item($child)['LocationID']);

        // the storeroom's chairs still carry the root's sticker
        $c = (int) $this->act('count_start', ['location' => $this->store])['json']['id'];
        $r = $this->act('count_check', ['id' => $c, 'tag' => 'fur-0001']);
        $this->assertSame(200, $r['status'], $r['body']);
        $this->assertSame('found', $this->countLine($c, $child)['Result']);

        $stray = $this->add(['name' => 'Stray lamp']);
        $this->assertSame('misplaced', $this->act('count_check', ['id' => $c, 'itemId' => $stray])['json']['result']);
        $this->assertSame(422, $this->act('count_check', ['id' => $c, 'tag' => 'NOPE-1'])['status']);
        $this->assertSame(422, $this->act('count_extra', ['id' => $c, 'name' => ' '])['status']);
        $this->assertSame(200, $this->act('count_extra', ['id' => $c, 'name' => 'Unknown shelf', 'qty' => 2])['status']);

        // undo: a misplaced line goes away, a snapshot line is cleared
        $line = (int) $this->countLine($c, $stray)['id'];
        $this->assertSame(200, $this->act('count_uncheck', ['lineId' => $line])['status']);
        $this->assertNull($this->countLine($c, $stray));
        $line = (int) $this->countLine($c, $child)['id'];
        $this->act('count_uncheck', ['lineId' => $line]);
        $this->assertNull($this->countLine($c, $child)['Result']);
    }

    public function testCloseMarksMissingOrMovedAndApplyRunsOnce(): void
    {
        $here = $this->add(['name' => 'Found one']);
        $short = $this->add(['name' => 'Chairs', 'qty' => 10]);
        $gone = $this->add(['name' => 'Not there']);
        $left = $this->add(['name' => 'Moved after start']);
        $stray = $this->add(['name' => 'Stray', 'locationId' => $this->store]);
        $paper = $this->add(['name' => 'Paper', 'categoryId' => $this->sup, 'qty' => 10]);
        $ink = $this->add(['name' => 'Ink', 'categoryId' => $this->sup, 'qty' => 5]);

        $c = (int) $this->act('count_start', ['location' => $this->hq])['json']['id'];
        $this->act('transfer', ['id' => $left, 'location' => $this->store]);
        $this->act('count_check', ['id' => $c, 'itemId' => $here]);
        $this->act('count_check', ['id' => $c, 'itemId' => $short, 'found' => 7]);
        $this->act('count_check', ['id' => $c, 'itemId' => $stray]);
        $this->act('count_check', ['id' => $c, 'itemId' => $paper, 'found' => 12]);
        $this->assertSame(422, $this->act('count_apply', ['id' => $c, 'what' => 'missing'])['status'], 'close first');

        $this->assertSame(200, $this->act('count_close', ['id' => $c])['status']);
        $this->assertSame('missing', $this->countLine($c, $gone)['Result']);
        $this->assertSame('moved', $this->countLine($c, $left)['Result']);
        $this->assertSame('missing', $this->countLine($c, $ink)['Result']);
        $this->assertNotNull($this->item($here)['LastCountedOn']);
        $this->assertSame(422, $this->act('count_check', ['id' => $c, 'itemId' => $here])['status'], 'closed');

        $this->assertSame(1, $this->act('count_apply', ['id' => $c, 'what' => 'misplaced'])['json']['applied']);
        $this->assertSame($this->hq, (int) $this->item($stray)['LocationID']);

        $this->assertSame(2, $this->act('count_apply', ['id' => $c, 'what' => 'missing'])['json']['applied']);
        $this->assertSame('lost', $this->item($gone)['Status']);
        $this->assertSame('active', $this->item($short)['Status'], 'a partial shortage splits');
        $this->assertSame(7, (int) $this->item($short)['Qty']);
        $split = $this->row('SELECT * FROM inv_items WHERE ParentID = ?', [$short]);
        $this->assertSame(3, (int) $split['Qty']);
        $this->assertSame('lost', $split['Status']);
        $this->assertSame('active', $this->item($left)['Status'], 'moved is not missing');

        $this->assertSame(2, $this->act('count_apply', ['id' => $c, 'what' => 'stock'])['json']['applied']);
        $this->assertSame(12, (int) $this->item($paper)['Qty']);
        $this->assertSame(0, (int) $this->item($ink)['Qty']);
        $this->assertSame(12, $this->ledgerSum($paper));
        $this->assertSame($c, (int) $this->row("SELECT CountID FROM inv_stock_moves WHERE ItemID = ? AND Type = 'adjust'", [$paper])['CountID']);

        foreach (['misplaced', 'missing', 'stock'] as $what) {
            $this->assertSame(0, $this->act('count_apply', ['id' => $c, 'what' => $what])['json']['applied'], "$what applies once");
        }
    }

    public function testExpectedQuantityIsReadAgainWhenChecked(): void
    {
        $id = $this->add(['qty' => 10]);
        $c = (int) $this->act('count_start', ['location' => $this->hq])['json']['id'];
        $this->act('transfer', ['id' => $id, 'location' => $this->store, 'qty' => 4]);
        $this->act('count_check', ['id' => $c, 'itemId' => $id]);
        $line = $this->countLine($c, $id);
        $this->assertSame(6, (int) $line['ExpectedQty']);
        $this->assertSame(6, (int) $line['FoundQty']);
    }

    // ------------------------------------------------------------------ integration

    public function testSeparationClearanceWaitsForTheItems(): void
    {
        $id = $this->add();
        $this->act('issue', ['id' => $id, 'emp' => self::EMP]);
        $sep = fn(array $over = []) => $this->request('query/lifecycle-action.php', [], $over + ['action' => 'separate', 'token' => 'T', 'emp' => self::EMP,
            'type' => 'resigned', 'lastDay' => '2026-11-01', 'reason' => 'Moving on', 'rehire' => 'yes'], $this->asAdmin() + ['lcy_csrf' => 'T']);

        $r = $sep(['clearance' => '1']);
        $this->assertSame(422, $r['status'], $r['body']);
        $this->assertStringContainsString('1 company item', json_decode($r['body'], true)['msg']);
        $this->assertSame(1, (int) $this->row('SELECT EmpStatusID FROM employees WHERE EmpID = ?', [self::EMP])['EmpStatusID'], 'nothing saved');

        // without clearance the separation goes through; the items stay on record
        $this->assertSame(200, $sep()['status']);
        $sid = (int) $this->row('SELECT id FROM emp_separations WHERE EmpID = ?', [self::EMP])['id'];
        $timeline = $this->request('query/lifecycle-action.php', [], ['action' => 'timeline', 'token' => 'T', 'emp' => self::EMP], $this->asAdmin() + ['lcy_csrf' => 'T']);
        $this->assertSame(1, json_decode($timeline['body'], true)['items']['count']);

        $update = ['action' => 'sep_update', 'token' => 'T', 'id' => $sid, 'lastDay' => '2026-11-01', 'reason' => 'Moving on', 'rehire' => 'yes', 'clearance' => '1'];
        $this->assertSame(422, $this->request('query/lifecycle-action.php', [], $update, $this->asAdmin() + ['lcy_csrf' => 'T'])['status']);
        $this->act('return', ['id' => $id]);
        $this->assertSame(200, $this->request('query/lifecycle-action.php', [], $update, $this->asAdmin() + ['lcy_csrf' => 'T'])['status']);
        $this->assertSame(1, (int) $this->row('SELECT ClearanceDone FROM emp_separations WHERE id = ?', [$sid])['ClearanceDone']);

        // already-cleared records stay editable even if something is issued later
        self::db()->exec("UPDATE inv_items SET HolderEmpID = '" . self::EMP . "' WHERE id = $id");
        $this->assertSame(200, $this->request('query/lifecycle-action.php', [], $update, $this->asAdmin() + ['lcy_csrf' => 'T'])['status']);
    }

    public function testLostItemsStillBlockClearanceUntilWrittenOff(): void
    {
        $id = $this->add();
        $this->act('issue', ['id' => $id, 'emp' => self::EMP]);
        $this->act('mark_lost', ['id' => $id]);
        $this->assertSame(1, \inv_held_summary(self::db(), self::EMP)['count']);
        $this->act('dispose', ['id' => $id, 'method' => 'other', 'date' => '2026-11-01']);
        $this->assertSame(0, \inv_held_summary(self::db(), self::EMP)['count']);
    }

    public function testPages(): void
    {
        $page = $this->request('inventory.php', [], [], $this->manager());
        $this->assertSame(200, $page['status'], $page['body']);
        $this->assertNoPhpErrors($page['body']);
        $this->assertStringContainsString('<h1>Inventory</h1>', $page['body']);
        $this->assertStringContainsString('href="inventory"', $page['body']);
        $this->assertStringContainsString('href="myitems"', $page['body']);

        $this->assertSame(302, $this->request('inventory.php', [], [], $this->staff())['status']);

        $mine = $this->request('myitems.php', [], [], $this->staff());
        $this->assertSame(200, $mine['status'], $mine['body']);
        $this->assertNoPhpErrors($mine['body']);
        $this->assertStringContainsString('<h1>My Items</h1>', $mine['body']);
        $this->assertStringContainsString('href="myitems"', $mine['body']);
        $this->assertStringNotContainsString('href="inventory"', $mine['body']);
    }

    public function testMenuBadgesCountAlertsAndUnconfirmedItems(): void
    {
        $id = $this->add();
        $this->act('issue', ['id' => $id, 'emp' => self::EMP]);
        $body = $this->request('myitems.php', [], [], $this->staff())['body'];
        $this->assertMatchesRegularExpression('#href="myitems".*?wd-nav__badge[^>]*>1<#s', $body);
    }

    public function testAnE201CardShowsWhatIsIssued(): void
    {
        $id = $this->add(['name' => 'Company laptop', 'unitCost' => 55000]);
        $this->act('issue', ['id' => $id, 'emp' => self::EMP]);
        $body = $this->request('e201.php', [], [], $this->asEmployee())['body'];
        $this->assertNoPhpErrors($body);
        $this->assertStringContainsString('Company property', $body);
        $this->assertStringContainsString('Company laptop', $body);
        $this->assertStringNotContainsString('55,000', $body);
    }

    public function testQrScanSignedOutComesBackAfterSignIn(): void
    {
        $res = $this->request('inventory.php', ['tag' => 'FUR-0001'], [], null);
        $this->assertSame(302, $res['status']);
        $this->assertSame('', trim($res['body']));

        require_once dirname(__DIR__) . '/includes/account-gate.php';
        $_SESSION = ['wd_after_tag' => 'FUR-0001'];
        $this->assertSame('inventory?tag=FUR-0001', \wd_after_login());
        $this->assertArrayNotHasKey('wd_after_tag', $_SESSION, 'used once');
        $this->assertSame('index.php', \wd_after_login());
        $_SESSION = ['wd_after_tag' => 'https://evil.example/x'];
        $this->assertSame('index.php', \wd_after_login(), 'never an outside address');
    }

    public function testQrScanByAnEmployeeLandsOnMyItems(): void
    {
        $this->assertSame(302, $this->request('inventory.php', ['tag' => 'FUR-0001'], [], $this->staff())['status']);
    }
}
