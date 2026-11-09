<?php
namespace Wedo\Tests;

use Wedo\Tests\Support\AppTestCase;

/**
 * Subscriptions: the register, company scope, "Mark renewed", PhilGEPS
 * eligibility, the overview's answers, who is reminded, the once-a-day run,
 * the page, bell, card and pop-up wiring, and the encrypted logins vault.
 */
final class SubscriptionsTest extends AppTestCase
{
    private const TOKEN = 'sub-test-token';
    private string $today;
    private string $uploadDir;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        require_once dirname(__DIR__) . '/includes/subscriptions-lib.php';
    }

    protected function setUp(): void
    {
        parent::setUp();
        date_default_timezone_set('Asia/Manila');
        $this->today = date('Y-m-d');
        $db = self::db();
        $db->exec("UPDATE accessrights SET subscriptions = 2 WHERE EmpID = '" . self::ADMIN . "'");
        $db->exec("INSERT INTO companies (CompanyID, CompanyDesc, compcode, comcolor) VALUES ('OC', 'OtherCo', 'OC', '#000000')");
        $this->uploadDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'wedo-sub-' . bin2hex(random_bytes(4));
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
        putenv('WEDO_SUB_VAULT_KEY');
        parent::tearDown();
    }

    private function admin(): array { return $this->asAdmin() + ['sub_csrf' => self::TOKEN]; }
    private function staff(): array { return $this->asEmployee() + ['sub_csrf' => self::TOKEN]; }
    private function grantStaff(): void { self::db()->exec("UPDATE accessrights SET subscriptions = 2 WHERE EmpID = '" . self::EMP . "'"); }

    private function act(string $action, array $post = [], ?array $session = null, bool $withToken = true): array
    {
        $post = ['action' => $action] + $post + ($withToken ? ['token' => self::TOKEN] : []);
        $res = $this->request('query/subscriptions-action.php', [], $post, $session ?? $this->admin());
        $this->assertNoPhpErrors($res['body']);
        $res['json'] = json_decode($res['body'], true);
        return $res;
    }

    private function days(int $n): string { return date('Y-m-d', strtotime("$n days")); }

    /** Add through the endpoint; returns the id. */
    private function add(array $over = [], ?array $session = null): int
    {
        $r = $this->act('item_save', $over + ['category' => 'domain', 'name' => 'WeDo.ph', 'companyId' => 'TC', 'expiresOn' => $this->days(100),
                                              'cycle' => 12, 'cost' => '1,200', 'ownerId' => self::ADMIN], $session);
        $this->assertSame(200, $r['status'], $r['body']);
        return (int) $r['json']['id'];
    }

    private function scopeAdmin(): array { return ['all' => true, 'company' => 'TC']; }

    // ------------------------------------------------------------------ access

    public function testEndpointNeedsTheRightAndTheToken(): void
    {
        $this->assertSame(403, $this->act('overview', [], $this->staff())['status']);
        $this->assertSame(419, $this->act('overview', [], null, false)['status']);
        $this->assertSame(200, $this->act('overview')['status']);
    }

    public function testPageGateAndShell(): void
    {
        $this->assertSame(302, $this->request('subscriptions.php', [], [], $this->staff())['status']);
        $page = $this->request('subscriptions.php', [], [], $this->admin());
        $this->assertSame(200, $page['status']);
        $this->assertNoPhpErrors($page['body']);
        $this->assertStringContainsString('id="subAnswer"', $page['body']);
        $this->assertStringContainsString('assets/js/subscriptions.js', $page['body']);
        $this->assertStringContainsString('href="subscriptions"', $page['body'], 'menu entry');
    }

    // ------------------------------------------------------------------ register

    public function testSaveValidatesAndNormalises(): void
    {
        $r = $this->act('item_save', ['category' => 'domain', 'name' => '', 'companyId' => 'TC']);
        $this->assertSame(422, $r['status']);
        $r = $this->act('item_save', ['category' => 'nope', 'name' => 'x', 'companyId' => 'TC']);
        $this->assertSame(422, $r['status']);
        $r = $this->act('item_save', ['category' => 'domain', 'name' => 'x.ph', 'companyId' => 'TC', 'renewUrl' => 'javascript:alert(1)']);
        $this->assertSame(422, $r['status']);
        $r = $this->act('item_save', ['category' => 'domain', 'name' => 'x.ph', 'companyId' => 'TC', 'ownerId' => self::EMP]);
        $this->assertSame(422, $r['status'], 'owner must have the right');

        $id = $this->add(['watchers' => [self::ADMIN, 'NOBODY']]);
        $row = $this->row('SELECT * FROM sub_items WHERE id = ?', [$id]);
        $this->assertSame('wedo.ph', $row['Name'], 'domains are lower case');
        $this->assertSame('1200.00', $row['Cost']);
        $this->assertSame(30, (int) $row['NotifyDays']);
        $this->assertSame([], $this->rows('SELECT * FROM sub_watchers WHERE ItemID = ?', [$id]), 'owner and non-holders are not watchers');
    }

    public function testCompanyScope(): void
    {
        $this->grantStaff();
        $other = $this->add(['companyId' => 'OC', 'name' => 'other.ph']);
        // a non-super user always saves into their own company, whatever they send
        $mine = $this->add(['companyId' => 'OC', 'name' => 'mine.ph', 'ownerId' => self::EMP], $this->staff());
        $this->assertSame('TC', $this->row('SELECT CompanyID FROM sub_items WHERE id = ?', [$mine])['CompanyID']);

        $ov = $this->act('overview', ['company' => 'all'], $this->staff())['json'];
        $this->assertSame(['mine.ph'], array_column($ov['items'], 'name'));
        $this->assertSame(404, $this->act('item', ['id' => $other], $this->staff())['status']);
        $this->assertSame(422, $this->act('item_cancel', ['id' => $other], $this->staff())['status']);

        $all = $this->act('overview', ['company' => 'all'])['json'];
        $this->assertCount(2, $all['items'], 'super users see every company');
    }

    public function testParentCannotLoop(): void
    {
        $a = $this->add(['name' => 'a.ph']);
        $b = $this->add(['category' => 'hosting', 'name' => 'Hosting', 'parentId' => $a]);
        $r = $this->act('item_save', ['id' => $a, 'category' => 'domain', 'name' => 'a.ph', 'companyId' => 'TC', 'parentId' => $b]);
        $this->assertSame(422, $r['status']);
        $d = $this->act('item', ['id' => $a])['json']['item'];
        $this->assertSame('Hosting', $d['children'][0]['name']);
    }

    public function testCancelResumeDelete(): void
    {
        $id = $this->add(['expiresOn' => $this->days(3)]);
        $this->act('renew', ['id' => $id, 'newExpiry' => $this->days(400)]);
        $this->assertSame(200, $this->act('item_cancel', ['id' => $id])['status']);
        $this->assertSame('cancelled', $this->row('SELECT Status FROM sub_items WHERE id = ?', [$id])['Status']);
        $this->assertSame(422, $this->act('renew', ['id' => $id, 'newExpiry' => $this->days(800)])['status'], 'no renewing while cancelled');
        $this->assertSame(200, $this->act('item_resume', ['id' => $id])['status']);
        $this->assertSame(200, $this->act('item_delete', ['id' => $id])['status']);
        $this->assertNull($this->row('SELECT * FROM sub_items WHERE id = ?', [$id]));
        $this->assertSame([], $this->rows('SELECT * FROM sub_renewals WHERE ItemID = ?', [$id]));
    }

    // ------------------------------------------------------------------ renewing

    public function testMarkRenewed(): void
    {
        $id = $this->add(['expiresOn' => $this->days(5)]);
        $this->assertNotEmpty(sub_my_due(self::db(), self::ADMIN), 'inside the window');

        $old = $this->days(5);
        $r = $this->act('renew', ['id' => $id, 'newExpiry' => $old]);
        $this->assertSame(422, $r['status'], 'must be later than the current expiry');

        $new = sub_add_months($old, 12);
        $this->assertSame($new, $this->act('item', ['id' => $id])['json']['item']['suggestExpiry']);
        $r = $this->act('renew', ['id' => $id, 'newExpiry' => $new, 'amount' => '1,350.50', 'paidOn' => $this->today, 'reference' => 'INV-9']);
        $this->assertSame(200, $r['status'], $r['body']);
        $this->assertSame($new, $this->row('SELECT ExpiresOn FROM sub_items WHERE id = ?', [$id])['ExpiresOn']);
        $ren = $this->row('SELECT * FROM sub_renewals WHERE ItemID = ?', [$id]);
        $this->assertSame($old, $ren['OldExpiry']);
        $this->assertSame('1350.50', $ren['Amount']);
        $this->assertSame([], sub_my_due(self::db(), self::ADMIN), 'reminders stop at once');
    }

    public function testRenewKeepsAReceipt(): void
    {
        $id = $this->add(['expiresOn' => $this->days(5)]);
        $pdf = $this->uploadDir . '-in.pdf';
        file_put_contents($pdf, "%PDF-1.4\n1 0 obj<<>>endobj\ntrailer<<>>\n%%EOF\n");
        $r = sub_renew(self::db(), $id, null, ['newExpiry' => $this->days(370)], ['name' => 'receipt.pdf', 'tmp_name' => $pdf, 'error' => 0, 'size' => filesize($pdf)],
                       $this->scopeAdmin(), self::ADMIN, false, $this->uploadDir);
        @unlink($pdf);
        $this->assertTrue($r['ok'], $r['msg'] ?? '');
        $f = $this->row('SELECT * FROM sub_files WHERE ItemID = ?', [$id]);
        $this->assertSame('receipt', $f['Kind']);
        $this->assertSame((int) $r['renewalId'], (int) $f['RenewalID']);
        $this->assertNotNull(sub_file_path($f['FileKey'], $this->uploadDir));

        $bad = $this->uploadDir . '-in.exe';
        file_put_contents($bad, 'MZ');
        $r = sub_file_add(self::db(), $id, null, 'other', ['name' => 'x.exe', 'tmp_name' => $bad, 'error' => 0, 'size' => 2], $this->scopeAdmin(), self::ADMIN, false, $this->uploadDir);
        @unlink($bad);
        $this->assertFalse($r['ok']);
    }

    // ------------------------------------------------------------------ PhilGEPS

    public function testPhilgepsEligibility(): void
    {
        $id = $this->add(['category' => 'philgeps', 'name' => 'PhilGEPS Platinum', 'expiresOn' => $this->days(200), 'cycle' => 12]);
        $this->assertSame(45, (int) $this->row('SELECT NotifyDays FROM sub_items WHERE id = ?', [$id])['NotifyDays']);
        $reqs = $this->rows('SELECT * FROM sub_requirements WHERE ItemID = ? ORDER BY SortOrder', [$id]);
        $this->assertCount(count(SUB_PHILGEPS_DOCS), $reqs, 'the usual documents are listed');

        $permit = (int) $reqs[0]['id'];
        $tax = (int) $reqs[1]['id'];
        $this->act('req_save', ['itemId' => $id, 'id' => $permit, 'name' => "Mayor's permit", 'expiresOn' => $this->days(70)]);
        $this->act('req_save', ['itemId' => $id, 'id' => $tax, 'name' => 'Tax clearance', 'expiresOn' => $this->days(40)]);

        $pg = $this->act('overview')['json']['philgeps'][0];
        $this->assertTrue($pg['canBid']);
        $this->assertSame($this->days(40), $pg['until']);
        $this->assertSame('Tax clearance', $pg['limitedBy']);

        // the tax clearance is inside its 45-day window: it is an entry of its own
        $att = $this->act('overview')['json']['attention'];
        $this->assertSame([$tax], array_column(array_filter($att, fn($e) => $e['reqId'] !== null), 'reqId'));

        $this->act('req_save', ['itemId' => $id, 'id' => $tax, 'name' => 'Tax clearance', 'expiresOn' => $this->days(-2)]);
        $pg = $this->act('overview')['json']['philgeps'][0];
        $this->assertFalse($pg['canBid']);
        $this->assertSame(['Tax clearance'], $pg['expired']);

        $r = $this->act('renew', ['id' => $id, 'reqId' => $tax, 'newExpiry' => $this->days(363), 'issuedOn' => $this->today]);
        $this->assertSame(200, $r['status'], $r['body']);
        $this->assertTrue($this->act('overview')['json']['philgeps'][0]['canBid']);
        $this->assertSame($tax, (int) $this->row('SELECT ReqID FROM sub_renewals WHERE ItemID = ?', [$id])['ReqID']);
        $this->assertSame($this->days(200), $this->row('SELECT ExpiresOn FROM sub_items WHERE id = ?', [$id])['ExpiresOn'], 'the certificate is untouched');
    }

    // ------------------------------------------------------------------ the answers

    public function testOverviewAnswers(): void
    {
        $this->add(['name' => 'gone.ph', 'expiresOn' => $this->days(-3), 'cost' => '1000']);
        $this->add(['name' => 'soon.ph', 'expiresOn' => $this->days(5), 'cost' => '500', 'autoRenew' => 1]);
        $this->add(['name' => 'later.ph', 'expiresOn' => $this->days(20), 'cost' => '']);
        $this->add(['name' => 'far.ph', 'expiresOn' => $this->days(200), 'cost' => '300']);
        $this->add(['category' => 'security', 'name' => 'SSL', 'expiresOn' => $this->days(40), 'cycle' => 1, 'cost' => '100', 'notifyDays' => 45]);
        $ov = $this->act('overview')['json'];
        $s = $ov['stats'];
        $this->assertSame(1, $s['expired']);
        $this->assertSame(1, $s['due7']);
        $this->assertSame(2, $s['due30'], 'soon + later');
        $this->assertSame(4, $s['autoOff']);
        $this->assertEquals(1500, $s['due30Cost'], 'gone + soon; later has no cost');
        $this->assertSame(['gone.ph', 'soon.ph', 'later.ph', 'SSL'], array_column($ov['attention'], 'name'), 'most urgent first, own windows');
        $this->assertSame('soon.ph', $ov['next']['name']);

        // timeline: the monthly SSL renews every month in range; the overdue domain counts this month
        $t = $ov['timeline'];
        $this->assertCount(12, $t['months']);
        $this->assertContains('gone.ph', array_column($t['months'][0]['items'], 'name'));
        $ssl = 0;
        foreach ($t['months'] as $m) { foreach ($m['items'] as $x) { if ($x['name'] === 'SSL') { $ssl++; } } }
        $this->assertGreaterThanOrEqual(10, $ssl);
        $this->assertLessThanOrEqual(11, $ssl);
    }

    public function testDateHelpers(): void
    {
        $this->assertSame('2027-02-28', sub_add_months('2027-01-31', 1));
        $this->assertSame('2028-02-28', sub_add_months('2027-02-28', 12), 'Feb 28 stays Feb 28');
        $this->assertSame('2028-02-29', sub_add_months('2027-03-29', 11));
        $this->assertSame(-1, sub_days_left('2026-01-01', '2026-01-02'));
        $this->assertSame('expired', sub_tone(-1, 30));
        $this->assertSame('urgent', sub_tone(7, 30));
        $this->assertSame('soon', sub_tone(8, 30));
        $this->assertSame('ok', sub_tone(31, 30));
    }

    // ------------------------------------------------------------------ reminders

    public function testWhoIsReminded(): void
    {
        $this->grantStaff();
        $db = self::db();
        $inWindow = $this->add(['name' => 'watch.ph', 'expiresOn' => $this->days(10), 'watchers' => [self::EMP]]);
        $this->add(['name' => 'quiet.ph', 'expiresOn' => $this->days(60)]);
        $d = sub_digests($db);
        $this->assertSame(['watch.ph'], array_column($d[self::ADMIN], 'name'));
        $this->assertSame(['watch.ph'], array_column($d[self::EMP], 'name'), 'watchers too');

        // expired and owned by someone else: everyone with the right in that company hears about it
        $db->exec("UPDATE sub_watchers SET EmpID = 'x' WHERE ItemID = $inWindow");
        $this->add(['name' => 'lapsed.ph', 'expiresOn' => $this->days(-1), 'ownerId' => self::ADMIN]);
        $d = sub_digests($db);
        $this->assertContains('lapsed.ph', array_column($d[self::EMP], 'name'));
        $this->assertNotContains('watch.ph', array_column($d[self::EMP], 'name'));

        // losing the right stops the reminders
        $db->exec("UPDATE accessrights SET subscriptions = 1 WHERE EmpID = '" . self::EMP . "'");
        $this->assertArrayNotHasKey(self::EMP, sub_digests($db));

        [$title, $body] = sub_digest_text(sub_digests($db)[self::ADMIN]);
        $this->assertStringContainsString('2 renewals need attention (1 expired)', $title);
        $this->assertStringContainsString('lapsed.ph expired', $body);
    }

    public function testDailyRunSendsOncePerDay(): void
    {
        $db = self::db();
        $this->add(['name' => 'watch.ph', 'expiresOn' => $this->days(3)]);
        $seven = strtotime($this->today . ' 07:30:00');
        $nine = strtotime($this->today . ' 09:00:00');

        $this->assertSame('early', sub_run_daily($db, 'page', $seven)['status'], 'the page fallback waits for 8 AM');
        $dry = sub_run_daily($db, 'cron', $nine, true);
        $this->assertSame('dry-run', $dry['status']);
        $this->assertSame([], $this->rows('SELECT * FROM sub_runs'), 'a dry run records nothing');

        $r = sub_run_daily($db, 'page', $nine);
        $this->assertSame('sent', $r['status']);
        $this->assertSame(1, $r['people']);
        $this->assertSame('done', sub_run_daily($db, 'cron', $nine + 60)['status']);
        $log = $this->rows('SELECT * FROM sub_notify_log');
        $this->assertCount(1, $log);
        $this->assertStringContainsString('watch.ph expires in 3 days', $log[0]['Title']);
        $this->assertSame('page', $this->row('SELECT Source FROM sub_runs')['Source']);

        // a run that died half-way is picked up again later, without sending twice
        $db->exec("UPDATE sub_runs SET FinishedAt = NULL, StartedAt = '" . date('Y-m-d H:i:s', $nine - 3600) . "'");
        $this->assertSame('sent', ($r = sub_run_daily($db, 'cron', $nine + 1800))['status']);
        $this->assertSame(0, $r['people']);
        $this->assertCount(1, $this->rows('SELECT * FROM sub_notify_log'));

        $rem = $this->act('reminders')['json'];
        $this->assertSame(1, $rem['runs'][0]['people']);
        $this->assertCount(1, $rem['mine']['entries']);
    }

    public function testCliDryRun(): void
    {
        $this->add(['name' => 'watch.ph', 'expiresOn' => $this->days(3)]);
        $c = $GLOBALS['WEDO_TEST_DB'];
        $env = array_merge(getenv(), ['DB_HOST' => $c['host'], 'DB_USER' => $c['user'], 'DB_PASS' => $c['pass'], 'DB_NAME' => $c['name']]);
        $p = proc_open([PHP_BINARY, dirname(__DIR__) . '/tools/subscriptions-daily.php', '--dry-run'], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, dirname(__DIR__), $env);
        $out = stream_get_contents($pipes[1]) . stream_get_contents($pipes[2]);
        fclose($pipes[1]); fclose($pipes[2]);
        $this->assertSame(0, proc_close($p), $out);
        $this->assertStringContainsString('1 people would be reminded', $out);
        $this->assertStringContainsString(self::ADMIN . ': watch.ph expires in 3 days', $out);
    }

    // ------------------------------------------------------------------ the shell

    public function testBellCardAndPopUp(): void
    {
        $this->add(['name' => 'watch.ph', 'expiresOn' => $this->days(3)]);
        self::db()->exec("UPDATE accessrights SET dashboard = 2 WHERE EmpID IN ('" . self::ADMIN . "', '" . self::EMP . "')");
        $page = $this->request('dashboard.php', [], [], $this->admin() + ['CompanyLogo' => '']);
        $this->assertSame(200, $page['status']);
        $this->assertNoPhpErrors($page['body']);
        $this->assertStringContainsString('Renewals due', $page['body'], 'dashboard card');
        $this->assertStringContainsString('id="wdSubPop"', $page['body'], 'desktop pop-up');
        $this->assertMatchesRegularExpression('#href="subscriptions"[^>]*>.*?wd-nav__badge[^>]*>1<#s', $page['body'], 'sidebar badge');

        $note = $this->request('notifications.php', [], [], $this->admin() + ['CompanyLogo' => '', 'EmpISID' => 'N/A']);
        $this->assertNoPhpErrors($note['body']);
        $this->assertStringContainsString('Renewals due', $note['body']);

        // someone without the right sees none of it
        $staff = $this->request('dashboard.php', [], [], $this->staff() + ['CompanyLogo' => '']);
        $this->assertStringNotContainsString('Renewals due', $staff['body']);
        $this->assertStringNotContainsString('id="wdSubPop"', $staff['body']);
    }

    // ------------------------------------------------------------------ logins vault

    private const VAULT_KEY = '00112233445566778899aabbccddeeff00112233445566778899aabbccddeeff';

    private function vaultKey(?string $key): void
    {
        // the env var wins over config.local.php even when it is not a valid key, so 'none' = locked
        putenv('WEDO_SUB_VAULT_KEY=' . ($key ?? 'none'));
    }

    private function grantCreds(string $emp = self::ADMIN): void
    {
        self::db()->exec("UPDATE accessrights SET subscriptions = 2, subcreds = 2 WHERE EmpID = '" . $emp . "'");
    }

    public function testVaultNeedsItsOwnRightAndAKey(): void
    {
        $this->vaultKey(self::VAULT_KEY);
        $id = $this->add();
        // Subscriptions alone: no logins, only how many there are
        $r = $this->act('cred_save', ['itemId' => $id, 'label' => 'Registrar', 'username' => 'it@wedo.ph', 'secret' => 'S3cret!']);
        $this->assertSame(403, $r['status']);
        $this->grantCreds();
        $this->assertSame(200, $this->act('cred_save', ['itemId' => $id, 'label' => 'Registrar', 'username' => 'it@wedo.ph', 'secret' => 'S3cret!'])['status']);
        self::db()->exec("UPDATE accessrights SET subcreds = 1 WHERE EmpID = '" . self::ADMIN . "'");
        $v = $this->act('item', ['id' => $id])['json']['item']['vault'];
        $this->assertFalse($v['can']);
        $this->assertSame(1, $v['count']);
        $this->assertSame([], $v['creds']);
        $this->assertSame(403, $this->act('cred_reveal', ['id' => 1, 'why' => 'view'])['status']);

        // the right but no key on this server: locked, nothing can be saved or read
        $this->grantCreds();
        $this->vaultKey(null);
        $v = $this->act('item', ['id' => $id])['json']['item']['vault'];
        $this->assertTrue($v['locked']);
        $this->assertSame(422, $this->act('cred_save', ['itemId' => $id, 'label' => 'cPanel', 'secret' => 'x'])['status']);
        $this->assertSame(422, $this->act('cred_reveal', ['id' => 1, 'why' => 'view'])['status']);
    }

    public function testVaultEncryptsRevealsAndLogs(): void
    {
        $this->vaultKey(self::VAULT_KEY);
        $this->grantCreds();
        $id = $this->add();
        $this->assertSame(422, $this->act('cred_save', ['itemId' => $id, 'label' => ''])['status']);
        $this->assertSame(422, $this->act('cred_save', ['itemId' => $id, 'label' => 'x', 'url' => 'javascript:alert(1)', 'secret' => 'a'])['status']);
        $this->assertSame(422, $this->act('cred_save', ['itemId' => $id, 'label' => 'x'])['status'], 'nothing to save');

        $r = $this->act('cred_save', ['itemId' => $id, 'label' => 'Registrar account', 'url' => 'https://account.godaddy.com',
                                      'username' => 'it@wedo.ph', 'secret' => 'Tr0ub4dor&3', 'notes' => 'Recovery: 1234-5678']);
        $this->assertSame(200, $r['status'], $r['body']);
        $cid = (int) $r['json']['id'];

        // only ciphertext in the database
        $row = $this->row('SELECT * FROM sub_creds WHERE id = ?', [$cid]);
        foreach (['UserEnc', 'SecretEnc', 'NotesEnc'] as $col) {
            $this->assertStringStartsWith('v1:', $row[$col]);
        }
        $this->assertStringNotContainsString('Tr0ub4dor', implode(' ', $row));
        $this->assertStringNotContainsString('it@wedo.ph', implode(' ', $row));

        // the panel lists the username but never the password or notes
        $item = $this->act('item', ['id' => $id]);
        $c = $item['json']['item']['vault']['creds'][0];
        $this->assertSame(['it@wedo.ph', true, true], [$c['username'], $c['hasSecret'], $c['hasNotes']]);
        $this->assertStringNotContainsString('Tr0ub4dor', $item['body']);
        $this->assertStringNotContainsString('1234-5678', $item['body']);

        // view and copy: decrypted on request, logged
        $v = $this->act('cred_reveal', ['id' => $cid, 'why' => 'view'])['json']['cred'];
        $this->assertSame(['Tr0ub4dor&3', 'Recovery: 1234-5678'], [$v['secret'], $v['notes']]);
        $this->act('cred_reveal', ['id' => $cid, 'why' => 'copy']);
        $log = $this->rows('SELECT Action, EmpID FROM sub_cred_log WHERE CredID = ? ORDER BY id', [$cid]);
        $this->assertSame(['add', 'view', 'copy'], array_column($log, 'Action'));
        $this->assertSame(self::ADMIN, $log[1]['EmpID']);
        $vault = $this->act('item', ['id' => $id])['json']['item']['vault'];
        $this->assertSame(['Copied', 'Viewed', 'Added'], array_column($vault['log'], 'action'), 'newest first');

        // editing writes what is sent: a blank password removes it
        $this->assertSame(200, $this->act('cred_save', ['id' => $cid, 'label' => 'Registrar account', 'username' => 'it@wedo.ph', 'secret' => ''])['status']);
        $this->assertFalse($this->act('item', ['id' => $id])['json']['item']['vault']['creds'][0]['hasSecret']);

        // a value moved to another item does not decrypt (bound to its item)
        $this->act('cred_save', ['id' => $cid, 'label' => 'Registrar account', 'username' => 'it@wedo.ph', 'secret' => 'n3w']);
        $other = $this->add(['name' => 'other.ph']);
        self::db()->prepare('UPDATE sub_creds SET ItemID = ? WHERE id = ?')->execute([$other, $cid]);
        $this->assertSame(422, $this->act('cred_reveal', ['id' => $cid, 'why' => 'view'])['status']);
        $this->assertTrue($this->act('item', ['id' => $other])['json']['item']['vault']['creds'][0]['unreadable']);
        self::db()->prepare('UPDATE sub_creds SET ItemID = ? WHERE id = ?')->execute([$id, $cid]);

        // another key cannot read it either
        $this->vaultKey(str_repeat('ab', 32));
        $this->assertSame(422, $this->act('cred_reveal', ['id' => $cid, 'why' => 'view'])['status']);
        $this->vaultKey(self::VAULT_KEY);

        // deleting the subscription deletes its logins; the log stays
        $this->assertSame(200, $this->act('item_delete', ['id' => $id])['status']);
        $this->assertSame([], $this->rows('SELECT id FROM sub_creds WHERE ItemID = ?', [$id]));
        $this->assertSame('delete', $this->row('SELECT Action FROM sub_cred_log WHERE CredID = ? ORDER BY id DESC LIMIT 1', [$cid])['Action']);
    }

    public function testVaultFollowsTheCompanyScope(): void
    {
        $this->vaultKey(self::VAULT_KEY);
        $this->grantCreds();
        $this->grantCreds(self::EMP);
        $other = $this->add(['companyId' => 'OC', 'name' => 'other.ph']);
        $cid = (int) $this->act('cred_save', ['itemId' => $other, 'label' => 'cPanel', 'secret' => 'hidden'])['json']['id'];
        // a non-super user in TC cannot read, change or delete OtherCo's logins
        $this->assertSame(422, $this->act('cred_reveal', ['id' => $cid, 'why' => 'view'], $this->staff())['status']);
        $this->assertSame(422, $this->act('cred_save', ['id' => $cid, 'label' => 'x', 'secret' => 'y'], $this->staff())['status']);
        $this->assertSame(422, $this->act('cred_save', ['itemId' => $other, 'label' => 'x', 'secret' => 'y'], $this->staff())['status']);
        $this->assertSame(422, $this->act('cred_delete', ['id' => $cid], $this->staff())['status']);
        $this->assertSame(1, (int) $this->row('SELECT COUNT(*) AS n FROM sub_creds')['n']);
        $this->assertSame(['add'], array_column($this->rows('SELECT Action FROM sub_cred_log'), 'Action'), 'refused requests are not logged');
        $this->assertSame(200, $this->act('cred_delete', ['id' => $cid])['status']);
    }

    public function testSealIsBoundToKeyItemAndField(): void
    {
        $k = hex2bin(self::VAULT_KEY);
        $s = sub_seal('pässwörd', $k, 7, 'secret');
        $this->assertNotSame($s, sub_seal('pässwörd', $k, 7, 'secret'), 'fresh IV each time');
        $this->assertSame('pässwörd', sub_unseal($s, $k, 7, 'secret'));
        $this->assertNull(sub_unseal($s, $k, 8, 'secret'));
        $this->assertNull(sub_unseal($s, $k, 7, 'notes'));
        $this->assertNull(sub_unseal($s, random_bytes(32), 7, 'secret'));
        $this->assertNull(sub_unseal('v1:' . base64_encode('short'), $k, 7, 'secret'));
        $this->assertNull(sub_seal('', $k, 7, 'secret'));
        $this->assertSame('', sub_unseal(null, $k, 7, 'secret'));
        putenv('WEDO_SUB_VAULT_KEY=' . base64_encode($k));
        $this->assertSame($k, sub_vault_key(), 'base64 keys work too');
        putenv('WEDO_SUB_VAULT_KEY=too-short');
        $this->assertNull(sub_vault_key());
    }
}
