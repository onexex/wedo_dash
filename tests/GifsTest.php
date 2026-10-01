<?php
namespace Wedo\Tests;

use Wedo\Tests\Support\AppTestCase;

/**
 * GIF stickers in Messages, from the built-in library (assets/gifs).
 *
 *   includes/msg-gifs.php        the library (gifs.json titles + any .gif dropped in), message card
 *   query/Query-messages.php     action=gifs, action=send_gif, `gifs` flag on action=threads
 */
final class GifsTest extends AppTestCase
{
    private const TOKEN = 'gif-test-token';
    private const B = 'TC-0011', C = 'TC-0012';

    private ?string $tmp = null;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        require_once dirname(__DIR__) . '/includes/msg-groups.php';
        require_once dirname(__DIR__) . '/includes/msg-gifs.php';
    }

    protected function setUp(): void
    {
        parent::setUp();
        $db = self::db();
        foreach ([[self::B, 'Ben', 'Bautista'], [self::C, 'Cora', 'Cruz']] as [$id, $fn, $ln]) {
            $db->prepare("INSERT INTO employees (EmpID, EmpFN, EmpLN, EmpMN, EmpSuffix, PosID, EmpStatusID, EmployeeIDNumber)
                          VALUES (?, ?, ?, '', '', 10, 1, ?)")->execute([$id, $fn, $ln, $id]);
            $db->prepare("INSERT INTO empdetails (EmpID, EmpUN, EmpRoleID, EmpISID, EmpdepID, EmpCompID, EmpStatID, AgencyID, HMO_ID, EmpDateHired)
                          VALUES (?, ?, 3, 'N/A', 1, 'TC', 1, 1, 1, '2022-01-01')")->execute([$id, strtolower($fn)]);
        }
    }

    protected function tearDown(): void
    {
        if ($this->tmp) {
            array_map('unlink', glob($this->tmp . '/*') ?: []);
            rmdir($this->tmp);
        }
        parent::tearDown();
    }

    private function as(string $id): array
    {
        return ['id' => $id, 'UserType' => $id === self::ADMIN ? 1 : 3, 'CompanyName' => 'TestCo', 'CompanyColor' => '#f93627',
                'CompID' => 'TC', 'msg_csrf' => self::TOKEN];
    }

    private function get(string $who, array $q): array
    {
        $res = $this->request('query/Query-messages.php', $q, [], $this->as($who));
        $this->assertNoPhpErrors($res['body']);
        $res['json'] = json_decode($res['body'], true);
        return $res;
    }

    private function post(string $who, string $action, array $data): array
    {
        $res = $this->request('query/Query-messages.php', ['action' => $action], ['action' => $action, 'token' => self::TOKEN] + $data, $this->as($who));
        $this->assertNoPhpErrors($res['body']);
        $res['json'] = json_decode($res['body'], true);
        return $res;
    }

    // ------------------------------------------------------------ the library

    public function testTheStarterSetIsThereWithTitlesInOrder(): void
    {
        $lib = gif_library();
        $this->assertGreaterThanOrEqual(20, count($lib));
        $first = array_values($lib)[0];
        $this->assertSame(['file' => 'thank-you.gif', 'title' => 'Thank you!', 'w' => 240, 'h' => 160],
                          ['file' => $first['file'], 'title' => $first['title'], 'w' => $first['w'], 'h' => $first['h']]);
        $this->assertStringContainsString('salamat', $lib['salamat.gif']['tags']);
        $manifest = json_decode(file_get_contents(dirname(__DIR__) . '/assets/gifs/gifs.json'), true);
        foreach ($manifest as $e) {
            $this->assertArrayHasKey($e['file'], $lib, 'gifs.json lists a file that is missing: ' . $e['file']);
        }
    }

    public function testFilesDroppedInAreTitledFromTheirNameAndJunkIsIgnored(): void
    {
        $this->tmp = sys_get_temp_dir() . '/wedo-gifs-' . uniqid();
        mkdir($this->tmp);
        $real = dirname(__DIR__) . '/assets/gifs/noted.gif';
        copy($real, $this->tmp . '/happy-new-year.gif');
        copy($real, $this->tmp . '/Bad Name.gif');                       // not a safe file name
        copy($real, $this->tmp . '/x.php.gif');                          // dots in the name: refused
        file_put_contents($this->tmp . '/fake.gif', '<?php echo 1;');      // not really a GIF

        $lib = gif_library($this->tmp);
        $this->assertSame(['happy-new-year.gif'], array_keys($lib));
        $this->assertSame('Happy new year', $lib['happy-new-year.gif']['title']);
        $this->assertSame('', $lib['happy-new-year.gif']['tags']);

        $empty = sys_get_temp_dir() . '/wedo-gifs-empty-' . uniqid();
        mkdir($empty);
        try {
            $this->assertSame([], gif_library($empty));
            $this->assertFalse(gif_enabled(self::db(), $empty));            // nothing to send = no GIF button
        } finally {
            rmdir($empty);
        }
    }

    public function testOnlyLibraryFilesBecomeMessages(): void
    {
        $res = gif_message_text('thank-you.gif');
        $this->assertTrue($res['ok']);
        $this->assertSame(['f' => 'thank-you.gif', 'w' => 240, 'h' => 160, 't' => 'Thank you!'], json_decode($res['text'], true));

        foreach (['../config.local.php', 'missing.gif', 'thank-you.GIF', '', 'https://evil.example/x.gif'] as $bad) {
            $this->assertFalse(gif_message_text($bad)['ok'], $bad);
        }
    }

    // ------------------------------------------------------------ endpoints

    public function testSendingAGifOneToOne(): void
    {
        $this->assertTrue($this->get(self::EMP, ['action' => 'threads'])['json']['gifs']);
        $list = $this->get(self::EMP, ['action' => 'gifs'])['json']['gifs'];
        $this->assertSame('thank-you.gif', $list[0]['file']);

        $res = $this->post(self::EMP, 'send_gif', ['with' => self::ADMIN, 'gif' => 'congrats.gif']);
        $this->assertSame(200, $res['status'], $res['body']);
        $this->assertSame('gif', $res['json']['message']['kind']);

        $msg = $this->get(self::ADMIN, ['action' => 'thread', 'with' => self::EMP])['json']['messages'][0];
        $this->assertSame('gif', $msg['kind']);
        $this->assertSame('congrats.gif', json_decode($msg['text'], true)['f']);
        $this->assertSame('GIF', $this->get(self::ADMIN, ['action' => 'threads'])['json']['threads'][0]['last']);

        $bad = $this->post(self::EMP, 'send_gif', ['with' => self::ADMIN, 'gif' => '../w_conn.php']);
        $this->assertSame(422, $bad['status']);
        $this->assertSame(1, (int) self::db()->query("SELECT COUNT(*) FROM messages")->fetchColumn());
    }

    public function testAGifInAGroupMentionsNobody(): void
    {
        $res = $this->post(self::EMP, 'group_create', ['name' => 'Fun', 'members' => [self::B, self::C]]);
        $key = $res['json']['key'];
        $sent = $this->post(self::EMP, 'send_gif', ['with' => $key, 'gif' => 'yay.gif']);
        $this->assertSame(200, $sent['status'], $sent['body']);
        $grp = array_values(array_filter($this->get(self::B, ['action' => 'threads'])['json']['threads'], fn($t) => $t['key'] === $key))[0];
        $this->assertSame('GIF', $grp['last']);
        $this->assertSame(0, $grp['mentions']);
        $this->assertSame(0, (int) self::db()->query("SELECT COUNT(*) FROM msg_mentions")->fetchColumn());
        // people outside the group can't post into it
        $this->assertSame(422, $this->post(self::ADMIN, 'send_gif', ['with' => $key, 'gif' => 'yay.gif'])['status']);
    }

    public function testGifsStayOffBeforeTheGroupsMigration(): void
    {
        self::db()->exec("ALTER TABLE messages CHANGE Kind Kind_off VARCHAR(10) NOT NULL DEFAULT 'text'");
        try {
            $this->assertFalse($this->get(self::EMP, ['action' => 'threads'])['json']['gifs']);
            $res = $this->post(self::EMP, 'send_gif', ['with' => self::ADMIN, 'gif' => 'yay.gif']);
            $this->assertSame(409, $res['status']);
            $this->assertSame('disabled', $res['json']['code']);
        } finally {
            self::db()->exec("ALTER TABLE messages CHANGE Kind_off Kind VARCHAR(10) NOT NULL DEFAULT 'text'");
        }
    }
}
