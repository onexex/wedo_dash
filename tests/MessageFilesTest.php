<?php
namespace Wedo\Tests;

use Wedo\Tests\Support\AppTestCase;

/**
 * Pictures and documents in Messages, gated by the `msgfile` access right.
 *
 *   includes/msg-files.php       checks + storage (uploads/messages), cards, who may open a file
 *   query/Query-messages.php     action=send_file (403 without the right), `files` flag on action=threads
 *   query/msg-file.php           serves a file only to people in that conversation
 */
final class MessageFilesTest extends AppTestCase
{
    private const TOKEN = 'file-test-token';
    private const B = 'TC-0011', C = 'TC-0012';

    private array $cleanup = [];

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        require_once dirname(__DIR__) . '/includes/msg-files.php';
        require_once dirname(__DIR__) . '/includes/msg-heads-lib.php';
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
        foreach ($this->cleanup as $p) { is_dir($p) ? self::rmTree($p) : @unlink($p); }
        parent::tearDown();
    }

    private static function rmTree(string $dir): void
    {
        foreach (scandir($dir) ?: [] as $e) {
            if ($e === '.' || $e === '..') { continue; }
            is_dir("$dir/$e") ? self::rmTree("$dir/$e") : @unlink("$dir/$e");
        }
        @rmdir($dir);
    }

    private function as(string $id): array
    {
        return ['id' => $id, 'UserType' => $id === self::ADMIN ? 1 : 3, 'CompanyName' => 'TestCo', 'CompanyColor' => '#f93627',
                'CompID' => 'TC', 'msg_csrf' => self::TOKEN];
    }

    private function json(array $res): array
    {
        $this->assertNoPhpErrors($res['body']);
        $res['json'] = json_decode($res['body'], true);
        return $res;
    }

    private function grant(string $emp): void
    {
        self::db()->prepare("UPDATE accessrights SET msgfile = 2 WHERE EmpID = ?")->execute([$emp]);
    }

    /** a real PNG / PDF on disk, as an upload would leave it */
    private function tmpFile(string $kind): string
    {
        $p = tempnam(sys_get_temp_dir(), 'wdmf');
        $this->cleanup[] = $p;
        if ($kind === 'png') {
            $im = imagecreatetruecolor(40, 30);
            imagepng($im, $p);
            imagedestroy($im);
        } elseif ($kind === 'pdf') {
            file_put_contents($p, "%PDF-1.4\n1 0 obj<<>>endobj\ntrailer<<>>\n%%EOF\n");
        } else {
            file_put_contents($p, $kind);
        }
        return $p;
    }

    private function upload(string $tmp, string $name): array
    {
        return ['name' => $name, 'tmp_name' => $tmp, 'error' => UPLOAD_ERR_OK, 'size' => filesize($tmp)];
    }

    private function storeIn(string $dir, string $tmp, string $name): array
    {
        return mf_store($this->upload($tmp, $name), false, $dir);   // $dir is removed whole in tearDown
    }

    private function tmpDir(): string
    {
        $d = sys_get_temp_dir() . '/wedo-msgfiles-' . uniqid();
        $this->cleanup[] = $d;
        return $d;
    }

    // ------------------------------------------------------------ the right

    public function testOnlyHoldersOfTheRightGetThePaperclip(): void
    {
        $this->assertFalse($this->json($this->request('query/Query-messages.php', ['action' => 'threads'], [], $this->as(self::EMP)))['json']['files']);
        $this->grant(self::EMP);
        $this->assertTrue($this->json($this->request('query/Query-messages.php', ['action' => 'threads'], [], $this->as(self::EMP)))['json']['files']);
        $this->assertTrue(mf_can_send(self::db(), self::EMP));
        $this->assertFalse(mf_can_send(self::db(), self::ADMIN));    // super user too: only with the right
    }

    public function testSendingWithoutTheRightIsRefused(): void
    {
        $res = $this->json($this->request('query/Query-messages.php', ['action' => 'send_file'],
            ['action' => 'send_file', 'token' => self::TOKEN, 'with' => self::ADMIN], $this->as(self::EMP)));
        $this->assertSame(403, $res['status'], $res['body']);
        $this->assertSame(0, (int) self::db()->query("SELECT COUNT(*) FROM messages")->fetchColumn());
    }

    public function testTheRightIsOffUntilTheMigrationRuns(): void
    {
        self::db()->exec("ALTER TABLE accessrights CHANGE msgfile msgfile_off INT(11) NOT NULL DEFAULT 1");
        try {
            $this->assertFalse(mf_can_send(self::db(), self::EMP));
        } finally {
            self::db()->exec("ALTER TABLE accessrights CHANGE msgfile_off msgfile INT(11) NOT NULL DEFAULT 1");
        }
    }

    // ------------------------------------------------------------ checks + storage

    public function testPicturesAndDocumentsAreStoredUnderRandomNames(): void
    {
        $dir = $this->tmpDir();
        $pic = $this->storeIn($dir, $this->tmpFile('png'), 'Site visit.PNG');
        $this->assertTrue($pic['ok'], $pic['error'] ?? '');
        $this->assertSame('image', $pic['kind']);
        $card = json_decode($pic['text'], true);
        $this->assertSame(['n' => 'Site visit.PNG', 'w' => 40, 'h' => 30], ['n' => $card['n'], 'w' => $card['w'], 'h' => $card['h']]);
        $this->assertMatchesRegularExpression('#^\d{4}/\d{2}/[a-f0-9]{32}\.png$#', $card['k']);
        $this->assertFileExists($dir . '/' . $card['k']);
        $this->assertFileExists($dir . '/.htaccess');                      // the folder denies web access
        $this->assertStringContainsString('Require all denied', file_get_contents($dir . '/.htaccess'));

        $doc = $this->storeIn($dir, $this->tmpFile('pdf'), '../../Payroll Oct.pdf');
        $this->assertTrue($doc['ok'], $doc['error'] ?? '');
        $this->assertSame('file', $doc['kind']);
        $this->assertSame('Payroll Oct.pdf', json_decode($doc['text'], true)['n']);   // no path from the sender
        $this->assertLessThanOrEqual(MSG_MAX_LEN, mb_strlen($doc['text']));
    }

    public function testDangerousOrFakeFilesAreRefused(): void
    {
        $dir = $this->tmpDir();
        $php = $this->tmpFile('<?php echo 1;');
        foreach (['shell.php', 'shell.phtml', 'run.exe', 'page.html', 'pic.svg', 'noext'] as $name) {
            $this->assertFalse($this->storeIn($dir, $php, $name)['ok'], $name);
        }
        $this->assertFalse($this->storeIn($dir, $php, 'shell.png')['ok'], 'PHP renamed to .png');
        $this->assertFalse($this->storeIn($dir, $php, 'shell.pdf')['ok'], 'PHP renamed to .pdf');
        $this->assertFalse($this->storeIn($dir, $this->tmpFile('png'), 'pic.jpg')['ok'], 'PNG named .jpg');
        $this->assertFalse(mf_store(['name' => 'x.png', 'tmp_name' => '', 'error' => UPLOAD_ERR_INI_SIZE], false, $dir)['ok']);
        $this->assertFalse(mf_store(['name' => 'x.png', 'tmp_name' => $php, 'error' => UPLOAD_ERR_OK], true, $dir)['ok'], 'not a real upload');
        $this->assertDirectoryDoesNotExist($dir . '/' . date('Y'));        // nothing was written
    }

    public function testOnlyStoredFileCardsAreRecognised(): void
    {
        $this->assertNotNull(mf_card('{"k":"2026/10/' . str_repeat('a', 32) . '.pdf","n":"a.pdf","s":5}'));
        foreach (['', 'hello', '{"k":"../../w_conn.php"}', '{"k":"2026/10/' . str_repeat('a', 32) . '.php"}', '{"k":"2026/10/x.pdf"}'] as $bad) {
            $this->assertNull(mf_card($bad), $bad);
        }
    }

    // ------------------------------------------------------------ who may open a file

    private function fileMessage(string $mhid, string $sender, string $kind = 'image'): array
    {
        $key  = date('Y') . '/' . date('m') . '/' . bin2hex(random_bytes(16)) . ($kind === 'image' ? '.png' : '.pdf');
        $root = mf_dir();
        if (!is_dir($root . '/' . dirname($key))) { mkdir($root . '/' . dirname($key), 0775, true); }
        copy($this->tmpFile($kind === 'image' ? 'png' : 'pdf'), $root . '/' . $key);
        $this->cleanup[] = $root . '/' . $key;
        $text = json_encode(['k' => $key, 'n' => 'x', 's' => filesize($root . '/' . $key)], JSON_UNESCAPED_SLASHES);
        self::db()->prepare("INSERT INTO messages (MHID, SenderID, Message, Kind, DateSent, Status) VALUES (?, ?, ?, ?, NOW(), 1)")
            ->execute([$mhid, $sender, $text, $kind]);
        return ['id' => (int) self::db()->lastInsertId(), 'path' => $root . '/' . $key];
    }

    public function testOnlyPeopleInTheChatCanOpenAFile(): void
    {
        $mhid = self::EMP . '_' . self::B;
        self::db()->prepare("INSERT INTO messageheader (MHID, SenderID, RecieverID, dateMessage) VALUES (?, ?, ?, NOW())")
            ->execute([$mhid, self::EMP, self::B]);
        $f = $this->fileMessage($mhid, self::EMP);

        foreach ([self::EMP, self::B] as $who) {
            $res = $this->request('query/msg-file.php', ['id' => $f['id']], [], $this->as($who));
            $this->assertSame(200, $res['status'], $who);
            $this->assertSame(file_get_contents($f['path']), $res['body'], $who);
        }
        foreach ([self::C, self::ADMIN] as $who) {                       // not in the chat: not even a super user
            $this->assertSame(404, $this->request('query/msg-file.php', ['id' => $f['id']], [], $this->as($who))['status'], $who);
        }
        $this->assertSame(401, $this->request('query/msg-file.php', ['id' => $f['id']])['status']);

        $this->assertNull(mf_for_viewer(self::db(), self::EMP, 999999));
        $this->assertSame('📷 Photo', msg_threads(self::db(), self::B)[0]['last']);
    }

    public function testGroupFilesAreForMembersOnly(): void
    {
        $res = $this->json($this->request('query/Query-messages.php', ['action' => 'group_create'],
            ['action' => 'group_create', 'token' => self::TOKEN, 'name' => 'Ops', 'members' => [self::B, self::ADMIN]], $this->as(self::EMP)));
        $this->assertSame(200, $res['status'], $res['body']);
        $gid = grp_id_from_key($res['json']['key']);
        $f = $this->fileMessage($res['json']['key'], self::EMP, 'file');

        $this->assertNotNull(mf_for_viewer(self::db(), self::B, $f['id']));
        $this->assertNull(mf_for_viewer(self::db(), self::C, $f['id']));
        $this->assertSame('Sent 📎 x', mh_preview('file', json_encode(['n' => 'x'])));
        $this->assertNotNull($gid);
    }
}
