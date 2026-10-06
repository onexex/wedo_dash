<?php
namespace Wedo\Tests;

use Wedo\Tests\Support\AppTestCase;

/**
 * Deleting your own messages, gated by the `msgdel` access right.
 *
 *   includes/msg-delete.php      who may delete what; what a deleted message leaves behind
 *   query/Query-messages.php     action=delete (403 without the right), `canDelete`, `deleted` ids on action=thread
 */
final class MessageDeleteTest extends AppTestCase
{
    private const TOKEN = 'del-test-token';
    private const B = 'TC-0011', C = 'TC-0012';

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        require_once dirname(__DIR__) . '/includes/msg-delete.php';
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
            $db->prepare("INSERT INTO accessrights (EmpID) VALUES (?)")->execute([$id]);
        }
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

    private function grant(string $emp): void
    {
        self::db()->prepare("UPDATE accessrights SET msgdel = 2 WHERE EmpID = ?")->execute([$emp]);
    }

    private function say(string $from, string $to, string $text): int
    {
        $res = $this->post($from, 'send', ['with' => $to, 'text' => $text]);
        $this->assertSame(200, $res['status'], $res['body']);
        return $res['json']['message']['id'];
    }

    private function msgRow(int $id): array
    {
        $st = self::db()->prepare("SELECT Message, Kind FROM messages WHERE MSID = ?");
        $st->execute([$id]);
        return $st->fetch(\PDO::FETCH_ASSOC);
    }

    public function testOnlyHoldersOfTheRightGetTheDeleteButton(): void
    {
        $this->assertFalse($this->get(self::EMP, ['action' => 'threads'])['json']['canDelete']);
        $this->grant(self::EMP);
        $this->assertTrue($this->get(self::EMP, ['action' => 'threads'])['json']['canDelete']);
        $this->assertFalse(md_can_delete(self::db(), self::ADMIN));     // super user too: only with the right
    }

    public function testWithoutTheRightNothingIsDeleted(): void
    {
        $id = $this->say(self::EMP, self::B, 'keep me');
        $res = $this->post(self::EMP, 'delete', ['id' => $id]);
        $this->assertSame(403, $res['status'], $res['body']);
        $this->assertSame(['Message' => 'keep me', 'Kind' => 'text'], $this->msgRow($id));
    }

    public function testDeletingMyOwnMessageLeavesANoteForEveryone(): void
    {
        $this->grant(self::EMP);
        $keep = $this->say(self::EMP, self::B, 'hello');
        $id   = $this->say(self::EMP, self::B, 'oops, wrong chat');
        $this->post(self::B, 'react', ['id' => $id, 'emoji' => '👍']);

        $res = $this->post(self::EMP, 'delete', ['id' => $id]);
        $this->assertSame(200, $res['status'], $res['body']);
        $this->assertSame(['Message' => '', 'Kind' => 'deleted'], $this->msgRow($id));
        $this->assertSame('text', $this->msgRow($keep)['Kind']);
        $this->assertSame(0, (int) self::db()->query("SELECT COUNT(*) FROM msg_reactions WHERE MSID = $id")->fetchColumn());

        // the other person: the message stays in place as "deleted", and the id list lets an open chat update it
        $thread = $this->get(self::B, ['action' => 'thread', 'with' => self::EMP])['json'];
        $this->assertSame(['text', 'deleted'], array_column($thread['messages'], 'kind'));
        $this->assertSame('', $thread['messages'][1]['text']);
        $this->assertSame([$id], $thread['deleted']);
        $this->assertSame([$id], $this->get(self::B, ['action' => 'thread', 'with' => self::EMP, 'after' => $id])['json']['deleted']);
        $this->assertSame('Message deleted', $this->get(self::B, ['action' => 'threads'])['json']['threads'][0]['last']);
        $this->assertSame('Deleted a message', mh_preview('deleted', ''));

        // a deleted message can't take reactions; deleting it again is harmless
        $this->assertSame(422, $this->post(self::B, 'react', ['id' => $id, 'emoji' => '😂'])['status']);
        $this->assertSame(200, $this->post(self::EMP, 'delete', ['id' => $id])['status']);
    }

    public function testNobodyCanDeleteSomeoneElsesMessage(): void
    {
        $this->grant(self::EMP);
        $this->grant(self::B);
        $theirs = $this->say(self::B, self::EMP, 'mine, not yours');
        $res = $this->post(self::EMP, 'delete', ['id' => $theirs]);
        $this->assertSame(422, $res['status']);
        $this->assertSame('text', $this->msgRow($theirs)['Kind']);
        $this->assertSame(422, $this->post(self::EMP, 'delete', ['id' => 999999])['status']);
    }

    public function testDeletingAPictureRemovesTheFile(): void
    {
        $this->grant(self::EMP);
        $mhid = self::EMP . '_' . self::B;
        self::db()->prepare("INSERT INTO messageheader (MHID, SenderID, RecieverID, dateMessage) VALUES (?, ?, ?, NOW())")
            ->execute([$mhid, self::EMP, self::B]);
        $key  = date('Y') . '/' . date('m') . '/' . bin2hex(random_bytes(16)) . '.pdf';
        $path = mf_dir() . '/' . $key;
        if (!is_dir(dirname($path))) { mkdir(dirname($path), 0775, true); }
        file_put_contents($path, "%PDF-1.4\n%%EOF\n");
        self::db()->prepare("INSERT INTO messages (MHID, SenderID, Message, Kind, DateSent, Status) VALUES (?, ?, ?, 'file', NOW(), 1)")
            ->execute([$mhid, self::EMP, json_encode(['k' => $key, 'n' => 'a.pdf', 's' => 15], JSON_UNESCAPED_SLASHES)]);
        $id = (int) self::db()->lastInsertId();

        try {
            $this->assertSame(200, $this->post(self::EMP, 'delete', ['id' => $id])['status']);
            $this->assertFileDoesNotExist($path);
            $this->assertSame(404, $this->request('query/msg-file.php', ['id' => $id], [], $this->as(self::B))['status']);
        } finally {
            @unlink($path);
        }
    }

    public function testInAGroupItAlsoClearsMentions(): void
    {
        $this->grant(self::EMP);
        $key = $this->post(self::EMP, 'group_create', ['name' => 'Ops', 'members' => [self::B, self::C]])['json']['key'];
        $id  = $this->say(self::EMP, $key, '@everyone stand-up moved');
        $this->assertGreaterThan(0, (int) self::db()->query("SELECT COUNT(*) FROM msg_mentions WHERE MSID = $id")->fetchColumn());

        $this->assertSame(200, $this->post(self::EMP, 'delete', ['id' => $id])['status']);
        $this->assertSame(0, (int) self::db()->query("SELECT COUNT(*) FROM msg_mentions WHERE MSID = $id")->fetchColumn());
        $this->assertSame([$id], $this->get(self::C, ['action' => 'thread', 'with' => $key])['json']['deleted']);
    }

    public function testTheRightIsOffUntilTheMigrationRuns(): void
    {
        self::db()->exec("ALTER TABLE accessrights CHANGE msgdel msgdel_off INT(11) NOT NULL DEFAULT 1");
        try {
            $this->assertFalse(md_can_delete(self::db(), self::EMP));
        } finally {
            self::db()->exec("ALTER TABLE accessrights CHANGE msgdel_off msgdel INT(11) NOT NULL DEFAULT 1");
        }
    }
}
