<?php
namespace Wedo\Tests;

use Wedo\Tests\Support\AppTestCase;

/**
 * Management > Message Review: HR / admin with the `msgaudit` access right read
 * every conversation (read-only), deleted messages included. Nothing about a
 * review is recorded (the review log was removed; msg_review_log stays empty).
 *
 *   includes/msg-review.php          who may review, conversation list / transcript, archive
 *   query/msgreview-action.php       list | thread | people (403 without the right)
 *   query/msgreview-file.php         pictures/documents incl. a deleted message's kept file
 *   includes/msg-delete.php          archives the original before wiping it
 */
final class MessageReviewTest extends AppTestCase
{
    private const TOKEN = 'review-test-token';
    private const B = 'TC-0011', C = 'TC-0012';

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        require_once dirname(__DIR__) . '/includes/msg-review.php';
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
                'CompID' => 'TC', 'msg_csrf' => self::TOKEN, 'mr_csrf' => self::TOKEN];
    }

    private function grant(string $emp, string $right = 'msgaudit'): void
    {
        self::db()->prepare("UPDATE accessrights SET `$right` = 2 WHERE EmpID = ?")->execute([$emp]);
    }

    /** Send through the real Messages endpoint; returns the message id. */
    private function say(string $from, string $to, string $text): int
    {
        $res = $this->request('query/Query-messages.php', ['action' => 'send'],
            ['action' => 'send', 'token' => self::TOKEN, 'with' => $to, 'text' => $text], $this->as($from));
        $this->assertSame(200, $res['status'], $res['body']);
        return json_decode($res['body'], true)['message']['id'];
    }

    private function review(array $q, string $who = self::ADMIN): array
    {
        $res = $this->request('query/msgreview-action.php', $q, [], $this->as($who));
        $this->assertNoPhpErrors($res['body']);
        $res['json'] = json_decode($res['body'], true);
        return $res;
    }

    private function logActions(): array
    {
        return self::db()->query("SELECT action FROM msg_review_log ORDER BY id")->fetchAll(\PDO::FETCH_COLUMN);
    }

    public function testEverythingIsClosedWithoutTheRight(): void
    {
        $this->say(self::EMP, self::B, 'hello');
        // super user without the right: still no
        $this->assertSame(302, $this->request('msgreview.php', [], [], $this->as(self::ADMIN))['status']);
        $this->assertSame(403, $this->review(['action' => 'list'])['status']);
        $this->assertSame(403, $this->review(['action' => 'thread', 'key' => self::EMP . '_' . self::B])['status']);
        $this->assertSame(403, $this->request('query/msgreview-file.php', ['id' => 1], [], $this->as(self::ADMIN))['status']);
        $this->assertSame(401, $this->request('query/msgreview-action.php', ['action' => 'list'])['status']);
        $this->assertSame([], $this->logActions());
    }

    public function testThePageOpensWithTheRight(): void
    {
        $this->grant(self::ADMIN);
        $res = $this->request('msgreview.php', [], [], $this->as(self::ADMIN));
        $this->assertSame(200, $res['status']);
        $this->assertNoPhpErrors($res['body']);
        $this->assertStringContainsString('Message Review', $res['body']);
    }

    /** Only the `rgemana` login sees the switch in Access Rights and may change it. */
    public function testOnlyRgemanaCanGrantTheRight(): void
    {
        $switch = fn() => $this->request('searchaccessrights.php', ['q' => self::EMP], [], $this->asAdmin())['body'];
        $toggle = fn() => $this->request('updateaccess.php', ['empid' => self::EMP, 'mponoff' => 'ON', 'term' => 'msgaudit'], [], $this->asAdmin())['status'];
        $right  = fn() => (int) self::db()->query("SELECT msgaudit FROM accessrights WHERE EmpID = '" . self::EMP . "'")->fetchColumn();

        // a super user with any other login: no switch, and a forged request is refused
        $this->assertStringNotContainsString('msgaudit', $switch());
        $this->assertStringContainsString('msgdel', $switch());
        $this->assertSame(403, $toggle());
        $this->assertSame(1, $right());

        self::db()->exec("UPDATE empdetails SET EmpUN = 'rgemana' WHERE EmpID = '" . self::ADMIN . "'");
        $this->assertStringContainsString('msgaudit', $switch());
        $this->assertSame(200, $toggle());
        $this->assertSame(2, $right());
    }

    public function testReviewerReadsConversationsTheyAreNotInWithoutMarkingThemRead(): void
    {
        $this->grant(self::ADMIN);
        $this->say(self::EMP, self::B, 'see you at 5');
        $this->say(self::B, self::C, 'you will regret this');

        $list = $this->review(['action' => 'list'])['json'];
        $this->assertTrue($list['ok']);
        $this->assertCount(2, $list['items']);
        $this->assertSame('Ben Bautista & Cora Cruz', $list['items'][0]['title']);   // newest first

        $key = $list['items'][0]['key'];
        $t = $this->review(['action' => 'thread', 'key' => $key])['json'];
        $this->assertSame(['you will regret this'], array_column($t['messages'], 'text'));
        $this->assertSame(self::B, $t['messages'][0]['sender']['id']);
        $this->assertFalse($t['messages'][0]['read']);

        // read-only: still unread for Cora
        $this->assertSame(1, (int) self::db()->query("SELECT Status FROM messages WHERE MHID = " . self::db()->quote($key))->fetchColumn());
    }

    public function testFiltersByPersonWordsAndType(): void
    {
        $this->grant(self::ADMIN);
        $this->say(self::EMP, self::B, 'lunch?');
        $this->say(self::B, self::C, 'Send me your PASSWORD now');
        $res = $this->request('query/Query-messages.php', ['action' => 'group_create'],
            ['action' => 'group_create', 'token' => self::TOKEN, 'name' => 'Ops', 'members' => [self::B, self::C]], $this->as(self::EMP));
        $this->assertSame(200, $res['status'], $res['body']);

        $titles = fn(array $q) => array_column($this->review(['action' => 'list'] + $q)['json']['items'], 'title');
        $this->assertSame(['Juan Dela Cruz & Ben Bautista'], $titles(['emp' => self::EMP, 'type' => 'dm']));
        $this->assertSame([], $titles(['emp' => self::EMP, 'type' => 'group']));   // a group with only its "created" event isn't listed
        $hit = $this->review(['action' => 'list', 'q' => 'password'])['json']['items'];
        $this->assertSame(['Ben Bautista & Cora Cruz'], array_column($hit, 'title'));
        $this->assertSame(1, $hit[0]['hits']);
        $this->assertSame([], $titles(['q' => 'password', 'to' => '2000-01-01']));

        $people = $this->review(['action' => 'people', 'q' => 'cruz'])['json']['people'];
        $this->assertEqualsCanonicalizing([self::EMP, self::C], array_column($people, 'id'));
    }

    public function testADeletedMessageIsKeptForReviewers(): void
    {
        $this->grant(self::ADMIN);
        $this->grant(self::B, 'msgdel');
        $id = $this->say(self::B, self::EMP, 'something nasty');
        $res = $this->request('query/Query-messages.php', ['action' => 'delete'],
            ['action' => 'delete', 'token' => self::TOKEN, 'id' => $id], $this->as(self::B));
        $this->assertSame(200, $res['status'], $res['body']);

        // the chat shows it as deleted…
        $this->assertSame('', self::db()->query("SELECT Message FROM messages WHERE MSID = $id")->fetchColumn());
        // …the reviewer sees the original
        $list = $this->review(['action' => 'list', 'q' => 'nasty'])['json']['items'];
        $this->assertSame(1, $list[0]['deleted']);
        $this->assertSame(1, $list[0]['hits']);
        $m = $this->review(['action' => 'thread', 'key' => $list[0]['key']])['json']['messages'][0];
        $this->assertSame('deleted', $m['kind']);
        $this->assertSame('something nasty', $m['original']['text']);
        $this->assertSame('text', $m['original']['kind']);
    }

    public function testADeletedPictureCanStillBeOpenedByReviewersOnly(): void
    {
        $this->grant(self::ADMIN);
        $this->grant(self::EMP, 'msgdel');
        $mhid = self::EMP . '_' . self::B;
        self::db()->prepare("INSERT INTO messageheader (MHID, SenderID, RecieverID, dateMessage) VALUES (?, ?, ?, NOW())")
            ->execute([$mhid, self::EMP, self::B]);
        $key  = date('Y') . '/' . date('m') . '/' . bin2hex(random_bytes(16)) . '.pdf';
        $path = mf_dir() . '/' . $key;
        if (!is_dir(dirname($path))) { mkdir(dirname($path), 0775, true); }
        file_put_contents($path, "%PDF-1.4\n%%EOF\n");
        self::db()->prepare("INSERT INTO messages (MHID, SenderID, Message, Kind, DateSent, Status) VALUES (?, ?, ?, 'file', NOW(), 1)")
            ->execute([$mhid, self::EMP, json_encode(['k' => $key, 'n' => 'proof.pdf', 's' => 15], JSON_UNESCAPED_SLASHES)]);
        $id = (int) self::db()->lastInsertId();

        try {
            // live file: reviewers may open it too
            $this->assertSame(200, $this->request('query/msgreview-file.php', ['id' => $id], [], $this->as(self::ADMIN))['status']);
            $res = $this->request('query/Query-messages.php', ['action' => 'delete'],
                ['action' => 'delete', 'token' => self::TOKEN, 'id' => $id], $this->as(self::EMP));
            $this->assertSame(200, $res['status'], $res['body']);

            $this->assertFileExists($path);
            $this->assertSame(404, $this->request('query/msg-file.php', ['id' => $id], [], $this->as(self::B))['status']);
            $this->assertSame(403, $this->request('query/msgreview-file.php', ['id' => $id], [], $this->as(self::B))['status']);
            $res = $this->request('query/msgreview-file.php', ['id' => $id], [], $this->as(self::ADMIN));
            $this->assertSame(200, $res['status']);
            $this->assertStringStartsWith('%PDF', $res['body']);

            $m = $this->review(['action' => 'thread', 'key' => $mhid])['json']['messages'][0];
            $this->assertSame('file', $m['original']['kind']);
            $this->assertTrue($m['original']['file']);
        } finally {
            @unlink($path);
        }
    }

    /** The review log is gone: no log/print actions, and reviewing records nothing. */
    public function testReviewingIsNotLogged(): void
    {
        $this->grant(self::ADMIN);
        $this->say(self::EMP, self::B, 'hi');
        $key = self::EMP . '_' . self::B;
        $this->review(['action' => 'list', 'q' => 'hi']);
        $this->review(['action' => 'thread', 'key' => $key]);
        $this->assertSame(400, $this->review(['action' => 'log'])['status']);
        $this->assertSame(400, $this->request('query/msgreview-action.php', ['action' => 'print'],
            ['action' => 'print', 'key' => $key], $this->as(self::ADMIN))['status']);
        $this->assertStringNotContainsString('Review log', $this->request('msgreview.php', [], [], $this->as(self::ADMIN))['body']);
        $this->assertSame([], $this->logActions());
    }
}
