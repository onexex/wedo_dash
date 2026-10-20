<?php
namespace Wedo\Tests;

use Wedo\Tests\Support\AppTestCase;

/**
 * Maintenance > Database Backup: holders of the `dbbackup` access right
 * download the whole database as a gzipped SQL dump.
 *
 *   dbbackup.php                    the page (redirects away without the right)
 *   query/dbbackup-download.php     the download (401 / 403 / 400 / the .sql.gz)
 *   includes/db-backup-lib.php      who may back up, the pure-PDO dump
 */
final class DatabaseBackupTest extends AppTestCase
{
    private const TOKEN = 'backup-test-token';
    private const PROBE = 'zz_backup_probe';

    protected function tearDown(): void
    {
        $db = self::db();
        $db->exec("DROP TRIGGER IF EXISTS zz_backup_probe_bi");
        $db->exec("DROP VIEW IF EXISTS zz_backup_probe_v");
        $db->exec("DROP TABLE IF EXISTS " . self::PROBE);
        $db->exec("DROP DATABASE IF EXISTS `" . $this->restoreDb() . "`");
        parent::tearDown();
    }

    private function as(string $id): array
    {
        return ['id' => $id, 'UserType' => $id === self::ADMIN ? 1 : 3, 'CompanyName' => 'TestCo', 'CompanyColor' => '#f93627',
                'CompID' => 'TC', 'dbb_csrf' => self::TOKEN];
    }

    private function grant(string $emp): void
    {
        self::db()->prepare("UPDATE accessrights SET dbbackup = 2 WHERE EmpID = ?")->execute([$emp]);
    }

    private function download(?array $session, string $token = self::TOKEN): array
    {
        return $this->request('query/dbbackup-download.php', [], ['token' => $token], $session);
    }

    private function backupLog(): array
    {
        return $this->rows("SELECT EMPID, EmpActivity FROM dars WHERE EmpActivity LIKE 'Database backup%' ORDER BY DARSID");
    }

    private function restoreDb(): string
    {
        return $GLOBALS['WEDO_TEST_DB']['name'] . '_restore';
    }

    public function testRefusedWhenNotSignedIn(): void
    {
        $res = $this->download(null);
        $this->assertSame(401, $res['status']);
        $this->assertStringNotContainsString('CREATE TABLE', $res['body']);
        $this->assertSame(302, $this->request('dbbackup.php')['status']);
        $this->assertSame([], $this->backupLog());
    }

    public function testRefusedWithoutTheRight(): void
    {
        // a super user without the right: still no
        foreach ([self::ADMIN, self::EMP] as $who) {
            $res = $this->download($this->as($who));
            $this->assertSame(403, $res['status'], $who);
            $this->assertStringNotContainsString('CREATE TABLE', $res['body']);
            $this->assertSame(302, $this->request('dbbackup.php', [], [], $this->as($who))['status'], $who);
        }
        $this->assertSame([], $this->backupLog());

        // and the menu doesn't offer it
        $menu = $this->request('idcard.php', [], [], $this->as(self::ADMIN))['body'];
        $this->assertNull($this->dom($menu)->query("//a[@href='dbbackup']")->item(0));
    }

    public function testRefusesAForgedRequest(): void
    {
        $this->grant(self::ADMIN);
        $this->assertSame(400, $this->download($this->as(self::ADMIN), 'not-the-token')['status']);
        $this->assertSame(400, $this->request('query/dbbackup-download.php', ['token' => self::TOKEN], [], $this->as(self::ADMIN))['status']);   // GET
        $this->assertSame([], $this->backupLog());
    }

    public function testThePageOpensWithTheRightAndIsInTheMaintenanceMenu(): void
    {
        $this->grant(self::ADMIN);
        $res = $this->request('dbbackup.php', [], [], $this->as(self::ADMIN));
        $this->assertSame(200, $res['status']);
        $this->assertNoPhpErrors($res['body']);
        $x = $this->dom($res['body']);
        $this->assertNotNull($x->query("//div[@data-sec='maintenance']//a[@href='dbbackup' and contains(@class,'is-active')]")->item(0));
        $form = $x->query("//form[@action='query/dbbackup-download.php' and @method='post']")->item(0);
        $this->assertNotNull($form, 'Back up now form');
        $this->assertSame(self::TOKEN, $x->query(".//input[@name='token']", $form)->item(0)->getAttribute('value'));
    }

    public function testDownloadsAGzippedSqlDumpAndLogsWhoRanIt(): void
    {
        $this->grant(self::ADMIN);
        $res = $this->download($this->as(self::ADMIN));
        $this->assertSame(200, $res['status'], substr($res['body'], 0, 500));
        $this->assertSame("\x1f\x8b", substr($res['body'], 0, 2), 'gzip magic bytes');

        $sql = gzdecode($res['body']);
        $this->assertIsString($sql, 'valid gzip');
        $this->assertStringContainsString('CREATE TABLE `accessrights`', $sql);
        $this->assertStringContainsString('CREATE TABLE `employees`', $sql);
        $this->assertStringContainsString('INSERT INTO `employees` (`ESID`, `EmpID`, ', $sql);
        $this->assertMatchesRegularExpression('/^\(\d+,\'TC-0001\',/m', $sql);
        $this->assertStringContainsString("'Dela Cruz'", $sql);
        $this->assertMatchesRegularExpression('/-- Dump completed: \d+ tables, \d+ rows, \d{4}-\d\d-\d\d \d\d:\d\d:\d\d\n$/', $sql);
        $this->assertStringNotContainsString('BACKUP INCOMPLETE', $sql);

        $log = $this->backupLog();
        $this->assertCount(1, $log);
        $this->assertSame(self::ADMIN, $log[0]['EMPID']);
        $this->assertMatchesRegularExpression('/^Database backup downloaded: wedo_test-backup-\d{4}-\d\d-\d\d_\d{6}\.sql\.gz$/', $log[0]['EmpActivity']);

        // the page lists it
        $page = $this->request('dbbackup.php', [], [], $this->as(self::ADMIN))['body'];
        $this->assertStringContainsString('Ada Admin', $page);
        $this->assertStringNotContainsString('No backups yet', $page);
    }

    /** The dump restores into an empty database exactly: same rows, awkward values included. */
    public function testDumpRestoresExactly(): void
    {
        $db = self::db();
        $db->exec("CREATE TABLE " . self::PROBE . " (
            id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
            txt TEXT CHARACTER SET utf8mb4 NULL,
            bin BLOB NULL,
            flags BIT(4) NULL,
            amount DECIMAL(12,2) NULL,
            ratio DOUBLE NULL,
            at TIMESTAMP NULL DEFAULT NULL,
            day DATE NULL,
            binlen INT AS (LENGTH(bin)) VIRTUAL
        ) ENGINE=InnoDB DEFAULT CHARSET=latin1");
        $db->exec("CREATE VIEW zz_backup_probe_v AS SELECT id, txt FROM " . self::PROBE);
        $db->exec("CREATE TRIGGER zz_backup_probe_bi BEFORE INSERT ON " . self::PROBE . " FOR EACH ROW SET NEW.amount = COALESCE(NEW.amount, 0)");
        $ins = $db->prepare("INSERT INTO " . self::PROBE . " (id, txt, bin, flags, amount, ratio, at, day) VALUES (?, ?, ?, b'1010', ?, ?, ?, ?)");
        $ins->execute([0, "zero id", null, null, null, null, null]);   // id 0 must survive (NO_AUTO_VALUE_ON_ZERO)
        $ins->execute([7, "it's a \"quote\" \\ back\\slash\nnew line\r\n\ttab \0 nul \x1a ; DROP TABLE x; -- ñ 日本 😀",
                       "\x00\xff\x01binary\x27\x5c", '-1234.50', '3.141592653589793', '2026-03-01 12:34:56', '0000-00-00']);
        $ins->execute([8, '', '', '0', '1e10', '2038-01-01 00:00:00', '2026-10-20']);
        $big = $db->prepare("INSERT INTO " . self::PROBE . " (txt) VALUES (?)");
        for ($i = 0; $i < 1200; $i++) { $big->execute([str_repeat(chr(65 + $i % 26), 900) . $i]); }   // several INSERT batches

        $this->grant(self::ADMIN);
        $res = $this->download($this->as(self::ADMIN));
        $this->assertSame(200, $res['status']);
        $sql = gzdecode($res['body']);
        $this->assertIsString($sql);
        $this->assertSame(1, substr_count($sql, 'CREATE TABLE `' . self::PROBE . '`'));
        $this->assertGreaterThan(2, substr_count($sql, 'INSERT INTO `' . self::PROBE . '`'), 'big table split over several INSERTs');
        $this->assertStringNotContainsString('DEFINER=', $sql);

        // restore into an empty database the way the mysql client does (it handles DELIMITER itself)
        $c = $GLOBALS['WEDO_TEST_DB'];
        $restore = $this->restoreDb();
        $db->exec("DROP DATABASE IF EXISTS `$restore`");
        $db->exec("CREATE DATABASE `$restore` CHARACTER SET utf8mb4");
        $m = new \mysqli($c['host'], $c['user'], $c['pass'], $restore);
        $script = preg_replace('/^DELIMITER .*\n/m', '', $sql);
        $script = str_replace(";;\n", ";\n", $script);
        // in batches under max_allowed_packet; a raw newline never occurs inside a dumped string, so ";\n" ends a statement
        $batches = [''];
        foreach (preg_split('/(?<=;\n)/', $script) as $piece) {
            if (strlen(end($batches)) + strlen($piece) > 500000) { $batches[] = ''; }
            $batches[count($batches) - 1] .= $piece;
        }
        foreach ($batches as $batch) {
            $m->multi_query($batch);
            do { if ($r = $m->store_result()) { $r->free(); } } while ($m->more_results() && $m->next_result());
            $this->assertSame(0, $m->errno, $m->error);
        }
        $m->close();

        // every table: same rows (CHECKSUM covers every column of every row)
        $tables = $db->query("SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'")->fetchAll(\PDO::FETCH_COLUMN);
        $this->assertContains(self::PROBE, $tables);
        foreach ($tables as $t) {
            $a = $db->query("CHECKSUM TABLE `$t`")->fetch()['Checksum'];
            $b = $db->query("CHECKSUM TABLE `$restore`.`$t`")->fetch()['Checksum'];
            $this->assertSame($a, $b, "table $t differs after restore");
        }
        $this->assertSame(1203, (int) $db->query("SELECT COUNT(*) FROM `$restore`.`" . self::PROBE . "`")->fetchColumn());
        $row = $db->query("SELECT txt, HEX(bin) AS bin, flags + 0 AS flags, binlen FROM `$restore`.`" . self::PROBE . "` WHERE id = 7")->fetch();
        $this->assertSame("it's a \"quote\" \\ back\\slash\nnew line\r\n\ttab \0 nul \x1a ; DROP TABLE x; -- ñ 日本 😀", $row['txt']);
        $this->assertSame('00FF0162696E617279275C', $row['bin']);
        $this->assertSame(10, (int) $row['flags']);
        $this->assertSame(11, (int) $row['binlen']);   // generated column recomputed, not dumped
        $this->assertSame('zero id', $db->query("SELECT txt FROM `$restore`.`" . self::PROBE . "` WHERE id = 0")->fetchColumn());

        // the view and the trigger came along too
        $this->assertSame(1203, (int) $db->query("SELECT COUNT(*) FROM `$restore`.zz_backup_probe_v")->fetchColumn());
        $db->exec("INSERT INTO `$restore`.`" . self::PROBE . "` (txt) VALUES ('after restore')");
        $this->assertSame('0.00', $db->query("SELECT amount FROM `$restore`.`" . self::PROBE . "` WHERE txt = 'after restore'")->fetchColumn());
    }

    /** It can be switched on like any other right (Access Rights > Maintenance). */
    public function testGrantedFromAccessRights(): void
    {
        $body = $this->request('searchaccessrights.php', ['q' => self::EMP], [], $this->asAdmin())['body'];
        $this->assertNoPhpErrors($body);
        $this->assertStringContainsString('id="dbbackup"', $body);

        $this->assertSame(200, $this->request('updateaccess.php', ['empid' => self::EMP, 'mponoff' => 'ON', 'term' => 'dbbackup'], [], $this->asAdmin())['status']);
        $this->assertSame(2, (int) $this->row("SELECT dbbackup FROM accessrights WHERE EmpID = ?", [self::EMP])['dbbackup']);
        $this->assertSame(200, $this->download($this->as(self::EMP))['status']);
    }
}
