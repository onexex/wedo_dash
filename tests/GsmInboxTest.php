<?php
namespace Wedo\Tests;

use DateTimeImmutable;
use DateTimeZone;
use Wedo\Tests\Support\AppTestCase;

/**
 * Management > SIM Inbox: the office-PC collector posts the GSM gateway's texts,
 * WeDo keeps them once, reads each SIM's load from carrier texts, and holders
 * of the `gsminbox` access right read it all.
 *
 *   api/gsm-ingest.php      the collector's door in (key, https, dedupe, load parsing)
 *   gsm.php                 the page (redirects away without the right)
 *   query/gsm-action.php    refresh / messages / save_line (401 / 405 / 419 / 403 / JSON)
 *   tools/gsm-collector.php reading the GoIP inbox page (no real gateway is ever contacted)
 */
final class GsmInboxTest extends AppTestCase
{
    private const TOKEN = 'gsm-test-token';
    private const KEY   = 'gsm-test-key-0123456789abcdef0123456789abcdef';

    public static function setUpBeforeClass(): void
    {
        require_once dirname(__DIR__) . '/includes/gsm-lib.php';
        if (!defined('GSMC_LIBRARY_ONLY')) { define('GSMC_LIBRARY_ONLY', true); }
        require_once dirname(__DIR__) . '/tools/gsm-collector.php';
    }

    protected function setUp(): void
    {
        parent::setUp();
        putenv('WEDO_GSM_KEY=' . self::KEY);
    }

    protected function tearDown(): void
    {
        putenv('WEDO_GSM_KEY');
        parent::tearDown();
    }

    private function as(string $id): array
    {
        return ['id' => $id, 'UserType' => $id === self::ADMIN ? 1 : 3, 'CompanyName' => 'TestCo', 'CompanyColor' => '#f93627',
                'CompID' => 'TC', 'gsm_csrf' => self::TOKEN];
    }

    private function grant(string $emp): void
    {
        self::db()->prepare("UPDATE accessrights SET gsminbox = 2 WHERE EmpID = ?")->execute([$emp]);
    }

    /** Post to the ingest API as the collector would. @return array{0:int, 1:array} */
    private function ingest(array $body, ?string $key = self::KEY, array $server = []): array
    {
        $server += ['HTTPS' => 'on', 'REMOTE_ADDR' => '198.51.100.7'];
        if ($key !== null) { $server['HTTP_X_WEDO_KEY'] = $key; }
        $res = $this->request('api/gsm-ingest.php', [], $body ?: ['collector' => 'x'], null, [], $server);
        $this->assertNoPhpErrors($res['body']);
        return [$res['status'], json_decode($res['body'], true) ?? []];
    }

    private function text(int $line, string $sender, string $text, string $time = '11-19 08:00:01', ?string $seen = null): array
    {
        return ['line' => $line, 'sender' => $sender, 'text' => $text, 'device_time' => $time] + ($seen ? ['seen_at' => $seen] : []);
    }

    /** @return array{0:int, 1:array} */
    private function act(?array $session, array $post, string $token = self::TOKEN): array
    {
        $res = $this->request('query/gsm-action.php', [], $post + ['token' => $token], $session);
        $this->assertNoPhpErrors($res['body']);
        return [$res['status'], json_decode($res['body'], true) ?? []];
    }

    // ------------------------------------------------------------- reading texts

    public function testDatesAreReadTheWaysCarriersWriteThem(): void
    {
        $ref = new DateTimeImmutable('2026-11-19 09:00', new DateTimeZone('Asia/Manila'));
        $cases = [
            '12/31/2026' => '2026-12-31', '12-31-26' => '2026-12-31', '2026-12-31 23:59' => '2026-12-31',
            'Dec 31, 2026' => '2026-12-31', 'December 31 2026' => '2026-12-31', '31 Dec 2026' => '2026-12-31',
            '31/12/2026' => '2026-12-31',            // day first only when it can't be a month
            'Dec 5' => '2026-12-05', '01/10' => '2027-01-10',   // no year: the next one
            'Nov 19' => '2026-11-19', '13/13/2026' => null, 'soon' => null, '02/30/2026' => null,
        ];
        foreach ($cases as $in => $want) { $this->assertSame($want, gsm_parse_date($in, $ref), $in); }
    }

    public function testLoadAndExpiryAreReadOnlyWhenPlainlySaid(): void
    {
        $at = new DateTimeImmutable('2026-11-19 09:00', new DateTimeZone('Asia/Manila'));
        $cases = [
            'Your current balance is P12.50 valid until 12/31/2026.'        => [12.5, '2026-12-31'],
            'Bal: PHP 1,250.00. Load expires on Jan 15, 2027. Thank you!'   => [1250.0, '2027-01-15'],
            'Your load balance of P5.00 will expire in 3 days.'            => [5.0, '2026-11-22'],
            'You have P0.00 balance. Reload now!'                           => [0.0, null],
            'Load P50 and get 5GB valid for 3 days! Text GO50 to 8080.'     => [null, null],
            'Your GoSURF50 is valid until Nov 21, 2026 11:59PM.'            => [null, '2026-11-21'],
            'Hi, see you at 3pm'                                            => [null, null],
        ];
        foreach ($cases as $text => [$bal, $exp]) {
            $this->assertSame(['balance' => $bal, 'expires' => $exp], gsm_parse_load($text, $at), $text);
        }
    }

    public function testCarrierSendersAreShortCodesOrNames(): void
    {
        foreach (['222', '8080', 'Globe', 'GCash', '2600'] as $s) { $this->assertTrue(gsm_is_carrier_sender($s), $s); }
        foreach (['+639171234567', '09171234567', ''] as $s) { $this->assertFalse(gsm_is_carrier_sender($s), $s); }
    }

    public function testExpiryStates(): void
    {
        $today = new DateTimeImmutable('2026-11-19', new DateTimeZone('Asia/Manila'));
        $this->assertSame('unknown', gsm_expiry_state(null, 3, $today)['state']);
        $this->assertSame('expired', gsm_expiry_state('2026-11-18', 3, $today)['state']);
        $this->assertSame(['state' => 'soon', 'days' => 0, 'text' => 'Expires today'], gsm_expiry_state('2026-11-19', 3, $today));
        $this->assertSame('soon', gsm_expiry_state('2026-11-22', 3, $today)['state']);
        $this->assertSame('ok', gsm_expiry_state('2026-11-23', 3, $today)['state']);
    }

    // ------------------------------------------------------------- the collector

    public function testTheCollectorReadsTheGoipInboxPage(): void
    {
        $html = '<html><script>
            var l1_sms_store = new Array();
            sms= ["11-19 08:00:01,222,Your current balance is P12.50, valid until 12/31/2026.","",""];
            pos=1;
            sms_row_insert(l1_sms_store, sms, pos, 1);
            sms= [];
            pos=0;
            sms_row_insert(l2_sms_store, sms, pos, 2);
            sms= ["+639171234567,11-19 09:30:00,Hi, it\'s me: \"Juan\"\\nsee you","11-18 21:00:00,Globe,Ang iyong load ay ₱5.00"];
            pos=2;
            sms_row_insert(l6_sms_store, sms, pos, 6);
            </script></html>';
        $got = gsmc_parse_inbox($html);
        $this->assertSame([
            ['line' => 1, 'device_time' => '11-19 08:00:01', 'sender' => '222', 'text' => 'Your current balance is P12.50, valid until 12/31/2026.'],
            ['line' => 6, 'device_time' => '11-19 09:30:00', 'sender' => '+639171234567', 'text' => "Hi, it's me: \"Juan\"\nsee you"],
            ['line' => 6, 'device_time' => '11-18 21:00:00', 'sender' => 'Globe', 'text' => 'Ang iyong load ay ₱5.00'],
        ], $got);
        $this->assertSame([], gsmc_parse_inbox('<html>Please login</html>'));

        // the collector and WeDo fingerprint a text the same way, so it is stored once
        $this->assertSame(gsm_hash(1, '11-19 08:00:01', '222', $got[0]['text']), gsmc_hash($got[0]));
    }

    public function testTheCollectorConfigIsChecked(): void
    {
        $dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'wedo-gsmc-' . bin2hex(random_bytes(4));
        mkdir($dir);
        $file = $dir . DIRECTORY_SEPARATOR . 'c.php';
        try {
            $this->assertNull(gsmc_load_config($file . '.missing')[0]);
            file_put_contents($file, '<?php return ["goip" => ["url" => "http://192.168.35.51/", "user" => "admin", "pass" => "x"],
                "lines" => [1, 6, "7", 99], "targets" => [["url" => "https://example.test/api/gsm-ingest.php", "key" => "' . self::KEY . '"]]];');
            [$cfg, $err] = gsmc_load_config($file);
            $this->assertNull($err);
            $this->assertSame('http://192.168.35.51', $cfg['goip']['url']);
            $this->assertSame([1, 6, 7], $cfg['lines']);
            $this->assertNull($cfg['balance'], 'no balance texts unless asked for');

            file_put_contents($file, '<?php return ["goip" => ["url" => "http://192.168.35.51", "user" => "admin"],
                "targets" => [["url" => "https://example.test/api/gsm-ingest.php", "key" => "short"]]];');
            $this->assertStringContainsString('32+', (string) gsmc_load_config($file)[1]);

            file_put_contents($file, '<?php return ["goip" => ["url" => "http://192.168.35.51", "user" => "admin"],
                "targets" => [["url" => "https://example.test/x", "key" => "' . self::KEY . '"]], "balance_check" => ["number" => "222", "text" => "BAL"]];');
            $this->assertStringContainsString("'lines'", (string) gsmc_load_config($file)[1], 'balance texts only from listed lines');
        } finally {
            @unlink($file); @rmdir($dir);
        }
    }

    // ------------------------------------------------------------- ingest API

    public function testIngestRefusesWithoutTheRightKeyOrHttps(): void
    {
        $body = ['collector' => 'office-pc', 'messages' => [$this->text(1, '222', 'Your balance is P1.00')]];
        $this->assertSame(401, $this->ingest($body, null)[0]);
        $this->assertSame(401, $this->ingest($body, 'wrong-key-wrong-key-wrong-key-wrong-key')[0]);
        $this->assertSame(403, $this->ingest($body, self::KEY, ['HTTPS' => '', 'REMOTE_ADDR' => '203.0.113.5'])[0]);
        $this->assertSame(405, $this->request('api/gsm-ingest.php', [], [], null, [], ['HTTPS' => 'on'])['status']);
        putenv('WEDO_GSM_KEY=too-short');
        $this->assertSame(503, $this->ingest($body, 'too-short')[0], 'a short key never switches it on');
        $this->assertSame([], $this->rows("SELECT id FROM gsm_sms"));
        $this->assertSame([], $this->rows("SELECT Name FROM gsm_collectors"));
    }

    public function testIngestStoresEachTextOnceAndReadsTheLoad(): void
    {
        $now = new DateTimeImmutable('now', new DateTimeZone('Asia/Manila'));
        $body = ['collector' => 'office-pc', 'version' => '1.0', 'ok' => true, 'lines' => [1, 6, 7, 8], 'messages' => [
            $this->text(1, '222', 'Your current balance is P12.50 valid until 12/31/2026.', '11-19 08:00:01', $now->modify('-2 hours')->format('c')),
            $this->text(6, '+639171234567', 'Hello, nasaan ka?', '11-19 08:05:00'),
            $this->text(99, '222', 'not a slot'),
        ]];
        [$code, $j] = $this->ingest($body);
        $this->assertSame(200, $code);
        $this->assertSame([2, 0, 1], [$j['stored'], $j['duplicates'], $j['skipped']]);
        $this->assertCount(2, $j['accepted']);

        [$code, $j] = $this->ingest($body);
        $this->assertSame([2, 0, 2], [$j['duplicates'], $j['stored'], count($j['accepted'])], 'sent again: nothing new');
        $this->assertCount(2, $this->rows("SELECT id FROM gsm_sms"));

        $sms = $this->rows("SELECT Line, Sender, IsCarrier, DeviceTime, ReceivedAt FROM gsm_sms ORDER BY id");
        $this->assertSame(['1', '222', '1', '11-19 08:00:01'], [(string) $sms[0]['Line'], $sms[0]['Sender'], (string) $sms[0]['IsCarrier'], $sms[0]['DeviceTime']]);
        $this->assertSame($now->modify('-2 hours')->format('Y-m-d H'), substr($sms[0]['ReceivedAt'], 0, 13), 'kept the time the collector first saw it');
        $this->assertSame('0', (string) $sms[1]['IsCarrier'], 'a phone number is a person');

        $lines = $this->rows("SELECT Line, Balance, ExpiresOn, ExpirySource FROM gsm_lines ORDER BY Line");
        $this->assertSame(['1', '6', '7', '8'], array_map(fn($r) => (string) $r['Line'], $lines), 'every line the collector reads is listed');
        $this->assertSame(['12.50', '2026-12-31', 'text'], [$lines[0]['Balance'], $lines[0]['ExpiresOn'], $lines[0]['ExpirySource']]);

        $col = $this->row("SELECT * FROM gsm_collectors WHERE Name = 'office-pc'");
        $this->assertNull($col['LastError']);
        $this->assertSame('1.0', $col['Version']);
        $this->assertSame('198.51.100.7', $col['IP']);
    }

    public function testAnOlderCarrierTextArrivingLateDoesNotUndoANewerOne(): void
    {
        $now = new DateTimeImmutable('now', new DateTimeZone('Asia/Manila'));
        $this->ingest(['messages' => [$this->text(1, '222', 'Your balance is P30.00 valid until 12/31/2026.', '11-19 10:00:00', $now->modify('-1 hour')->format('c'))]]);
        $this->ingest(['messages' => [$this->text(1, '222', 'Your balance is P5.00 valid until 11/20/2026.', '11-18 10:00:00', $now->modify('-1 day')->format('c'))]]);
        $l = $this->row("SELECT Balance, ExpiresOn FROM gsm_lines WHERE Line = 1");
        $this->assertSame(['30.00', '2026-12-31'], [$l['Balance'], $l['ExpiresOn']]);
    }

    public function testTheCollectorReportsWhenItCannotReadTheGateway(): void
    {
        [$code] = $this->ingest(['collector' => 'office-pc', 'ok' => false, 'error' => 'The gateway did not answer.', 'messages' => []]);
        $this->assertSame(200, $code);
        $col = $this->row("SELECT LastOkAt, LastError FROM gsm_collectors");
        $this->assertNull($col['LastOkAt']);
        $this->assertSame('The gateway did not answer.', $col['LastError']);

        $this->ingest(['collector' => 'office-pc', 'ok' => true, 'messages' => []]);
        $col = $this->row("SELECT LastOkAt, LastError FROM gsm_collectors");
        $this->assertNotNull($col['LastOkAt']);
        $this->assertNull($col['LastError']);
    }

    public function testIngestRefusesAnOversizedBatch(): void
    {
        $many = [];
        for ($i = 0; $i < GSM_BATCH_MAX + 1; $i++) { $many[] = $this->text(1, '222', "t$i", '11-19 08:00:' . $i); }
        $this->assertSame(413, $this->ingest(['messages' => $many])[0]);
        $this->assertSame([], $this->rows("SELECT id FROM gsm_sms"));
    }

    // ------------------------------------------------------------- the page

    public function testRefusedWhenNotSignedInOrWithoutTheRight(): void
    {
        $this->assertSame(302, $this->request('gsm.php')['status']);
        $this->assertSame(401, $this->act(null, ['action' => 'refresh'])[0]);
        foreach ([self::ADMIN, self::EMP] as $who) {
            $this->assertSame(302, $this->request('gsm.php', [], [], $this->as($who))['status'], $who);
            $this->assertSame(403, $this->act($this->as($who), ['action' => 'messages'])[0], $who);
        }
        $menu = $this->request('idcard.php', [], [], $this->as(self::ADMIN))['body'];
        $this->assertNull($this->dom($menu)->query("//a[@href='gsm']")->item(0));
    }

    public function testRefusesAForgedRequest(): void
    {
        $this->grant(self::ADMIN);
        $this->assertSame(419, $this->act($this->as(self::ADMIN), ['action' => 'messages'], 'not-the-token')[0]);
        $this->assertSame(405, $this->request('query/gsm-action.php', ['action' => 'messages', 'token' => self::TOKEN], [], $this->as(self::ADMIN))['status']);
    }

    public function testThePageShowsTheTextsAndIsInTheManagementMenu(): void
    {
        $this->ingest(['collector' => 'office-pc', 'lines' => [1], 'messages' => [$this->text(1, '222', 'Your balance is P12.50 valid until 12/31/2026. <b>x</b>')]]);
        $this->grant(self::ADMIN);
        $res = $this->request('gsm.php', [], [], $this->as(self::ADMIN));
        $this->assertSame(200, $res['status']);
        $this->assertNoPhpErrors($res['body']);
        $x = $this->dom($res['body']);
        $this->assertNotNull($x->query("//div[@data-sec='management']//a[@href='gsm' and contains(@class,'is-active')]")->item(0));
        $this->assertStringContainsString('Your balance is P12.50', $res['body']);
        $this->assertStringNotContainsString('<b>x</b>', $res['body'], 'texts never reach the page as markup');
        $this->assertStringNotContainsString(self::KEY, $res['body'], 'the collector key never reaches the browser');
    }

    public function testGrantedFromAccessRightsAsASensitiveRight(): void
    {
        $body = $this->request('searchaccessrights.php', ['q' => self::EMP], [], $this->asAdmin())['body'];
        $this->assertNoPhpErrors($body);
        $this->assertNotNull($this->dom($body)->query("//*[@id='gsminbox']")->item(0), 'SIM Inbox switch');
        $this->assertNotNull($this->dom($body)->query("//*[@data-sensitive='1' and (@id='gsminbox' or .//*[@id='gsminbox'])]")->item(0), 'marked sensitive');
    }

    public function testFilteringAndPaging(): void
    {
        $msgs = [];
        for ($i = 1; $i <= 55; $i++) { $msgs[] = $this->text(6, '+639171234567', "message $i", '11-19 09:' . sprintf('%02d', $i)); }
        $msgs[] = $this->text(1, '222', 'Your balance is P3.00', '11-19 10:00:00');
        $this->ingest(['messages' => $msgs]);
        $this->grant(self::EMP);
        $me = $this->as(self::EMP);

        [$code, $j] = $this->act($me, ['action' => 'messages']);
        $this->assertSame(200, $code);
        $this->assertCount(50, $j['messages']);
        $this->assertSame('Your balance is P3.00', $j['messages'][0]['text'], 'newest first');

        [, $older] = $this->act($me, ['action' => 'messages', 'before' => $j['messages'][49]['id']]);
        $this->assertCount(6, $older['messages']);

        [, $c] = $this->act($me, ['action' => 'messages', 'carrier' => '1']);
        $this->assertSame(['222'], array_column($c['messages'], 'sender'));
        [, $q] = $this->act($me, ['action' => 'messages', 'q' => 'message 5', 'line' => 6]);
        $this->assertSame(['message 55', 'message 54', 'message 53', 'message 52', 'message 51', 'message 50', 'message 5'], array_column($q['messages'], 'text'));
        [, $pct] = $this->act($me, ['action' => 'messages', 'q' => '%']);
        $this->assertSame([], $pct['messages'], '% is searched for, not a wildcard');

        $newest = $j['messages'][0]['id'];
        $this->ingest(['messages' => [$this->text(8, '8080', 'Promo!', '11-19 11:00:00')]]);
        [, $r] = $this->act($me, ['action' => 'refresh', 'after' => $newest]);
        $this->assertSame(['Promo!'], array_column($r['messages'], 'text'));
        $this->assertSame([1, 6, 8], array_column($r['lines'], 'line'));
        $this->assertSame('ok', $r['collectors'][0]['state']);
    }

    public function testALineIsNamedAndItsLoadCorrectedByHand(): void
    {
        $this->ingest(['lines' => [1], 'messages' => []]);
        $this->grant(self::EMP);
        $me = $this->as(self::EMP);

        [$code, $j] = $this->act($me, ['action' => 'save_line', 'line' => 1, 'label' => '  HR   hotline ', 'number' => '0917 123 4567', 'expires' => '2026-12-15', 'balance' => '₱1,020.50']);
        $this->assertSame(200, $code, json_encode($j));
        $l = $this->row("SELECT * FROM gsm_lines WHERE Line = 1");
        $this->assertSame(['HR hotline', '0917 123 4567', '2026-12-15', 'manual', '1020.50', self::EMP],
                          [$l['Label'], $l['Number'], $l['ExpiresOn'], $l['ExpirySource'], $l['Balance'], $l['UpdatedBy']]);
        $this->assertSame('HR hotline', $j['lines'][0]['label']);

        foreach ([['expires' => '2026-02-30'], ['number' => 'call me'], ['balance' => 'lots']] as $bad) {
            [$code] = $this->act($me, $bad + ['action' => 'save_line', 'line' => 1, 'label' => 'x', 'number' => '', 'expires' => '', 'balance' => '']);
            $this->assertSame(422, $code, json_encode($bad));
        }
        $this->assertSame(422, $this->act($me, ['action' => 'save_line', 'line' => 9, 'label' => 'x'])[0], 'only lines in the list');

        // a newer carrier text takes over again
        $this->ingest(['messages' => [$this->text(1, '222', 'Your balance is P9.00 valid until 01/31/2027.', '11-19 12:00:00', date('c', time() + 60))]]);
        $l = $this->row("SELECT Balance, ExpiresOn, ExpirySource, Label FROM gsm_lines WHERE Line = 1");
        $this->assertSame(['9.00', '2027-01-31', 'text', 'HR hotline'], [$l['Balance'], $l['ExpiresOn'], $l['ExpirySource'], $l['Label']]);
    }

    public function testTheMenuBadgeCountsLoadsRunningOut(): void
    {
        $this->ingest(['lines' => [1, 6], 'messages' => []]);
        self::db()->exec("UPDATE gsm_lines SET ExpiresOn = CURDATE() + INTERVAL 1 DAY WHERE Line = 1");
        self::db()->exec("UPDATE gsm_lines SET ExpiresOn = CURDATE() + INTERVAL 30 DAY WHERE Line = 6");
        $this->assertSame(1, gsm_menu_count(self::db()));
        self::db()->exec("UPDATE gsm_collectors SET LastSeenAt = NOW() - INTERVAL 1 HOUR");
        $this->assertSame(2, gsm_menu_count(self::db()), 'a collector that stopped counts too');
    }
}
