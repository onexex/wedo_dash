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
        $this->assertStringNotContainsString('id="subImport"', $page['body'], 'importing logins needs the logins right');
        $this->grantCreds();
        $this->assertStringContainsString('id="subImport"', $this->request('subscriptions.php', [], [], $this->admin())['body']);
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

    public function testInfrastructureNeverExpires(): void
    {
        // whatever the form still carried, an Infrastructure item has no date, cost or renewal
        $id = $this->add(['category' => 'infra', 'name' => 'Production server', 'provider' => 'GoDaddy',
                          'expiresOn' => $this->days(5), 'cost' => '999', 'autoRenew' => 1, 'renewUrl' => 'https://example.com']);
        $row = $this->row('SELECT * FROM sub_items WHERE id = ?', [$id]);
        $this->assertSame('infra', $row['Category']);
        $this->assertNull($row['ExpiresOn']);
        $this->assertNull($row['Cost']);
        $this->assertNull($row['RenewUrl']);
        $this->assertSame(0, (int) $row['CycleMonths']);
        $this->assertSame(0, (int) $row['AutoRenew']);

        $this->add(['name' => 'undated.ph', 'expiresOn' => '']);
        $ov = $this->act('overview')['json'];
        $this->assertSame(2, $ov['stats']['active']);
        $this->assertSame(1, $ov['stats']['noDate'], 'only the undated domain is missing a date');
        $this->assertSame(1, $ov['stats']['autoOff'], 'the domain, not the server');
        $this->assertSame([], $ov['attention']);
        $this->assertSame([], sub_my_due(self::db(), self::ADMIN), 'never reminds');
        $this->assertSame(['infra'], $this->act('lookups')['json']['noExpiry'], 'the page hides dates for these');

        $r = $this->act('renew', ['id' => $id, 'newExpiry' => $this->days(400)]);
        $this->assertSame(422, $r['status'], 'nothing to renew');

        // its logins go in the vault like any other item
        $this->vaultKey(self::VAULT_KEY);
        $this->grantCreds();
        $r = $this->act('cred_save', ['itemId' => $id, 'label' => 'Server SSH', 'username' => 'deploy', 'secret' => 'S3cret!']);
        $this->assertSame(200, $r['status'], $r['body']);
        // office devices are reached by IP, not a web page
        foreach (['192.168.1.1', '10.0.0.5:21', 'mrtg.example.net/login'] as $addr) {
            $r = $this->act('cred_save', ['itemId' => $id, 'label' => 'Router', 'url' => $addr, 'secret' => 'x']);
            $this->assertSame(200, $r['status'], $addr . ' ' . $r['body']);
        }
        foreach (['javascript:alert(1)', 'data:text/html,x', '<b>x</b>'] as $bad) {
            $this->assertSame(422, $this->act('cred_save', ['itemId' => $id, 'label' => 'Router', 'url' => $bad, 'secret' => 'x'])['status'], $bad);
        }
    }

    // ------------------------------------------------------------------ contacts and IP addresses

    public function testBlockMaths(): void
    {
        $b = sub_parse_cidr('126.209.8.32/28');
        $this->assertTrue($b['ok']);
        $this->assertSame(['255.255.255.240', '126.209.8.33', '126.209.8.46', '126.209.8.47', 14],
                          [$b['mask'], $b['first'], $b['last'], $b['broadcast'], $b['usable']]);
        $b = sub_parse_cidr('209.146.22.56 / 30');
        $this->assertSame(['255.255.255.252', '209.146.22.57', '209.146.22.58', 2], [$b['mask'], $b['first'], $b['last'], $b['usable']]);
        $this->assertSame(1, sub_parse_cidr('10.0.0.5/32')['usable']);
        $this->assertStringContainsString('126.209.8.32/28', sub_parse_cidr('126.209.8.33/28')['msg'], 'suggests the real start');
        $this->assertStringContainsString('/28', sub_parse_cidr('126.209.8.32')['msg'], 'asks for the size');
        $this->assertFalse(sub_parse_cidr('10.0.0.0/16')['ok'], 'too big to list');
        $this->assertFalse(sub_parse_cidr('300.1.1.0/24')['ok']);
    }

    public function testIpBlocksAndAddresses(): void
    {
        $id = $this->add(['category' => 'infra', 'name' => 'Infinivan internet']);
        $save = function (array $d) use ($id) { return $this->act('ipblock_save', $d + ['itemId' => $id]); };

        $r = $save(['cidr' => '126.209.8.32/28', 'gateway' => '126.209.8.33', 'assign' => json_encode([['ip' => '126.209.8.33', 'note' => 'Router']])]);
        $this->assertSame(422, $r['status'], 'the gateway cannot be assigned');
        $this->assertNull($this->row('SELECT id FROM sub_ipblocks'), 'nothing half-saved');
        $this->assertSame(422, $save(['cidr' => '126.209.8.32/28', 'gateway' => '126.209.9.1'])['status'], 'gateway outside the block');

        $r = $save(['cidr' => '126.209.8.32/28', 'gateway' => '126.209.8.33', 'dns' => '8.8.8.8', 'label' => 'Public block',
                    'assign' => json_encode([['ip' => '126.209.8.38', 'note' => 'CCTV'], ['ip' => '126.209.8.37', 'state' => 'conflict', 'note' => 'IP conflict'],
                                             ['ip' => '126.209.8.36', 'note' => '']])]);
        $this->assertSame(200, $r['status'], $r['body']);
        $block = (int) $r['json']['id'];

        $net = $this->act('item', ['id' => $id])['json']['item']['network'][0];
        $this->assertSame('255.255.255.240', $net['mask']);
        $this->assertSame(['used' => 1, 'reserved' => 0, 'conflict' => 1, 'free' => 11], $net['counts'], 'blank lines are not stored');
        $this->assertSame(['gateway', 'free 34-36', 'conflict', 'used', 'free 39-46'], array_map(function ($row) {
            if (!empty($row['gateway'])) { return 'gateway'; }
            if (!empty($row['free'])) { return 'free ' . substr($row['from'], 10) . '-' . substr($row['to'], 10); }
            return $row['state'];
        }, $net['rows']), 'runs of free addresses fold into one row');

        // blocks of one company never overlap
        $other = $this->add(['category' => 'infra', 'name' => 'Office LAN']);
        $r = $this->act('ipblock_save', ['itemId' => $other, 'cidr' => '126.209.8.40/29']);
        $this->assertSame(422, $r['status']);
        $this->assertStringContainsString('Infinivan internet', $r['json']['msg']);

        // one address at a time
        $ip = function (array $d) use ($block) { return $this->act('ip_save', $d + ['blockId' => $block]); };
        $this->assertSame(422, $ip(['ip' => '126.209.8.47', 'note' => 'x'])['status'], 'the broadcast address is not usable');
        $this->assertSame(422, $ip(['ip' => '126.209.8.45'])['status'], 'say what uses it');
        $this->assertSame(200, $ip(['ip' => '126.209.8.45', 'note' => 'Backup Dialer 3'])['status']);
        $this->assertSame(200, $ip(['ip' => '126.209.8.40', 'state' => 'reserved'])['status'], 'reserved needs no note');
        $this->assertSame('Backup Dialer 3', $this->row("SELECT Note FROM sub_ips WHERE Ip = '126.209.8.45'")['Note']);
        $this->assertSame(200, $this->act('ip_free', ['blockId' => $block, 'ip' => '126.209.8.45'])['status']);
        $this->assertNull($this->row("SELECT id FROM sub_ips WHERE Ip = '126.209.8.45'"));

        // shrinking must keep every address that is set
        $r = $save(['id' => $block, 'cidr' => '126.209.8.32/30', 'gateway' => '126.209.8.33']);
        $this->assertSame(422, $r['status']);
        $this->assertStringContainsString('126.209.8.37', $r['json']['msg']);

        // a login can be linked by those who may see logins; deleting it clears the link
        $this->assertSame(200, $ip(['ip' => '126.209.8.38', 'note' => 'CCTV', 'credId' => 999])['status'], 'ignored without the logins right');
        $this->assertNull($this->row("SELECT CredID FROM sub_ips WHERE Ip = '126.209.8.38'")['CredID']);
        $this->vaultKey(self::VAULT_KEY);
        $this->grantCreds();
        $cred = (int) $this->act('cred_save', ['itemId' => $id, 'label' => '3004-A CCTV', 'url' => '126.209.8.38', 'username' => 'admin', 'secret' => 'x'])['json']['id'];
        $this->assertSame(422, $ip(['ip' => '126.209.8.38', 'note' => 'CCTV', 'credId' => 999])['status']);
        $this->assertSame(200, $ip(['ip' => '126.209.8.38', 'note' => 'CCTV', 'credId' => $cred])['status']);
        $item = $this->act('item', ['id' => $id])['json']['item'];
        $row = array_values(array_filter($item['network'][0]['rows'], function ($x) { return ($x['ip'] ?? '') === '126.209.8.38'; }))[0];
        $this->assertSame('3004-A CCTV', $row['credLabel']);
        $this->assertContains($cred, array_column($item['credOptions'], 'id'));
        $this->act('cred_delete', ['id' => $cred]);
        $this->assertNull($this->row("SELECT CredID FROM sub_ips WHERE Ip = '126.209.8.38'")['CredID']);

        // deleting the item takes its blocks and addresses with it
        $this->assertSame(200, $this->act('item_delete', ['id' => $id])['status']);
        $this->assertNull($this->row('SELECT id FROM sub_ipblocks WHERE ItemID = ?', [$id]));
        $this->assertNull($this->row('SELECT id FROM sub_ips WHERE BlockID = ?', [$block]));
    }

    public function testContacts(): void
    {
        $id = $this->add(['category' => 'infra', 'name' => 'Infinivan internet']);
        $r = $this->act('contact_save', ['itemId' => $id, 'name' => 'Jen', 'role' => 'Account manager', 'phone' => '#09178469655']);
        $this->assertSame(200, $r['status'], $r['body']);
        $this->assertSame('09178469655', $this->row("SELECT Phone FROM sub_contacts WHERE Name = 'Jen'")['Phone'], 'the sheet\'s # is dropped');

        $this->assertSame(422, $this->act('contact_save', ['itemId' => $id, 'name' => 'Nobody'])['status'], 'a phone or e-mail is needed');
        $this->assertSame(422, $this->act('contact_save', ['itemId' => $id, 'name' => 'X', 'email' => 'not-an-email'])['status']);
        $this->assertSame(422, $this->act('contact_save', ['itemId' => $id, 'name' => 'X', 'phone' => 'call me'])['status']);
        $this->assertSame(200, $this->act('contact_save', ['itemId' => $id, 'name' => 'Office', 'phone' => '(02) 8634-7482 loc 12'])['status']);

        // several at once (pasted), all or nothing
        $list = [['name' => 'Ma\'am Maricel', 'role' => 'Building admin', 'phone' => '09770236870'], ['name' => 'Storage', 'email' => 'bad@']];
        $this->assertSame(422, $this->act('contact_save', ['itemId' => $id, 'list' => json_encode($list)])['status']);
        $this->assertNull($this->row("SELECT id FROM sub_contacts WHERE Name = 'Ma''am Maricel'"));
        $list[1]['email'] = 'storage@example.com';
        $r = $this->act('contact_save', ['itemId' => $id, 'list' => json_encode($list)]);
        $this->assertSame(200, $r['status'], $r['body']);
        $this->assertSame(2, $r['json']['count']);

        $item = $this->act('item', ['id' => $id])['json']['item'];
        $this->assertSame(['Jen', 'Office', 'Ma\'am Maricel', 'Storage'], array_column($item['contacts'], 'name'), 'in the order added');

        // the Contacts tab: every item's contacts, with the item
        $all = $this->act('contacts')['json']['contacts'];
        $this->assertCount(4, $all);
        $this->assertSame('Infinivan internet', $all[0]['item']);

        $jen = (int) $item['contacts'][0]['id'];
        $this->assertSame(200, $this->act('contact_save', ['id' => $jen, 'name' => 'Jen', 'phone' => '09170000000'])['status']);
        $this->assertSame('09170000000', $this->row('SELECT Phone FROM sub_contacts WHERE id = ?', [$jen])['Phone']);
        $this->assertSame(200, $this->act('contact_delete', ['id' => $jen])['status']);
        $this->assertCount(3, $this->act('contacts')['json']['contacts']);

        $this->act('item_delete', ['id' => $id]);
        $this->assertCount(0, $this->act('contacts')['json']['contacts'], 'they go with the item');
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

    public function testImportLoginsFromASpreadsheet(): void
    {
        $groups = function (array $g) { return ['groups' => json_encode($g), 'companyId' => 'TC']; };
        $routers = ['target' => 'new', 'category' => 'infra', 'name' => 'Office routers', 'rows' => [
            ['label' => 'Router A', 'url' => '192.168.1.1', 'username' => 'WEDO-A', 'secret' => 'r0uter!', 'notes' => ''],
            ['label' => 'Router B', 'url' => '192.168.35.1:8080', 'username' => 'WEDO-B', 'secret' => 'r0uter2', 'notes' => 'Back room'],
        ]];

        // the logins right and an open vault come first
        $this->assertSame(403, $this->act('cred_import', $groups([$routers]))['status'], 'Subscriptions alone is not enough');
        $this->grantCreds();
        $this->vaultKey(null);
        $this->assertSame(422, $this->act('cred_import', $groups([$routers]))['status'], 'locked vault');
        $this->vaultKey(self::VAULT_KEY);

        $hosting = $this->add(['category' => 'hosting', 'name' => 'WeDo hosting']);
        $count = function () { return (int) $this->row('SELECT COUNT(*) AS n FROM sub_creds')['n']; };

        // one bad row stops everything: nothing is half-imported
        $bad = ['target' => (string) $hosting, 'name' => 'cPanel', 'rows' => [['label' => 'cPanel', 'url' => 'javascript:alert(1)', 'secret' => 'x']]];
        $r = $this->act('cred_import', $groups([$routers, $bad]));
        $this->assertSame(422, $r['status']);
        $this->assertStringContainsString('cPanel', $r['json']['msg'], 'says which row');
        $this->assertSame(0, $count());
        $this->assertNull($this->row("SELECT id FROM sub_items WHERE Name = 'Office routers'"));

        $this->assertSame(422, $this->act('cred_import', $groups([['target' => 'new', 'category' => 'philgeps', 'name' => 'PG', 'rows' => $routers['rows']]]))['status'],
            'a PhilGEPS registration is not made by an import');
        $this->assertSame(422, $this->act('cred_import', ['groups' => 'not json'])['status']);
        $this->assertSame(422, $this->act('cred_import', $groups([['target' => 'new', 'name' => 'Empty', 'rows' => []]]))['status'], 'nothing to import');

        // a new Infrastructure item + logins onto an existing item, in one go
        $cpanel = ['target' => (string) $hosting, 'name' => 'cPanel', 'rows' => [
            ['label' => 'WeDo cPanel', 'url' => 'https://host.example.com:2083', 'username' => 'wedocp', 'secret' => 'cp-secret'],
        ]];
        $r = $this->act('cred_import', $groups([$routers, $cpanel]));
        $this->assertSame(200, $r['status'], $r['body']);
        $this->assertSame(3, $r['json']['logins']);
        $this->assertSame(1, $r['json']['items']);
        $this->assertCount(2, $r['json']['itemIds']);
        $this->assertSame(3, $count());

        $item = $this->row("SELECT * FROM sub_items WHERE Name = 'Office routers'");
        $this->assertSame('infra', $item['Category']);
        $this->assertSame('TC', $item['CompanyID']);
        $this->assertNull($item['ExpiresOn']);
        $this->assertSame(0, (int) $item['CycleMonths']);

        // encrypted like any other login, and readable again
        $cred = $this->row("SELECT * FROM sub_creds WHERE Label = 'Router A'");
        $this->assertStringStartsWith('v1:', $cred['SecretEnc']);
        $this->assertStringNotContainsString('r0uter', implode(' ', $cred));
        $this->assertSame((int) $item['id'], (int) $cred['ItemID']);
        $v = $this->act('cred_reveal', ['id' => $cred['id'], 'why' => 'view'])['json']['cred'];
        $this->assertSame('r0uter!', $v['secret']);
        $this->assertSame('192.168.1.1', $v['url']);
        $this->assertSame((string) $hosting, (string) $this->row("SELECT ItemID FROM sub_creds WHERE Label = 'WeDo cPanel'")['ItemID']);

        // each login is logged as imported
        $this->assertSame(3, (int) $this->row("SELECT COUNT(*) AS n FROM sub_cred_log WHERE Action = 'import'")['n']);
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
