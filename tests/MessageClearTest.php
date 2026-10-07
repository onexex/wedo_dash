<?php
namespace Wedo\Tests;

use Wedo\Tests\Support\AppTestCase;

/**
 * "Delete conversation" in Messages — for me only (includes/msg-clear.php,
 * query/Query-messages.php action=clear). Anyone may do it to their own conversations.
 */
final class MessageClearTest extends AppTestCase
{
    private const TOKEN = 'clear-test-token';
    private const C = 'TC-0031';   // more colleagues for the group tests
    private const D = 'TC-0032';

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        require_once dirname(__DIR__) . '/includes/msg-groups.php';
        require_once dirname(__DIR__) . '/includes/msg-heads-lib.php';
    }

    protected function setUp(): void
    {
        parent::setUp();
        $db = self::db();
        foreach ([[self::C, 'Cora', 'Cruz'], [self::D, 'Dino', 'Diaz']] as [$id, $fn, $ln]) {
            $db->prepare("INSERT INTO employees (EmpID, EmpFN, EmpLN, EmpMN, EmpSuffix, PosID, EmpStatusID, EmployeeIDNumber)
                          VALUES (?, ?, ?, '', '', 10, 1, ?)")->execute([$id, $fn, $ln, $id]);
            $db->prepare("INSERT INTO empdetails (EmpID, EmpUN, EmpRoleID, EmpISID, EmpdepID, EmpCompID, EmpStatID, AgencyID, HMO_ID, EmpDateHired)
                          VALUES (?, ?, 3, 'N/A', 1, 'TC', 1, 1, 1, '2022-01-01')")->execute([$id, strtolower($fn)]);
        }
    }

    private function as(string $id, int $type = 3): array
    {
        return ['id' => $id, 'UserType' => $type, 'CompanyName' => 'TestCo', 'CompanyColor' => '#f93627', 'CompID' => 'TC', 'msg_csrf' => self::TOKEN];
    }

    private function post(string $who, string $action, array $data, int $type = 3): array
    {
        $res = $this->request('query/Query-messages.php', ['action' => $action], $data + ['action' => $action, 'token' => self::TOKEN], $this->as($who, $type));
        $this->assertNoPhpErrors($res['body']);
        $res['json'] = json_decode($res['body'], true);
        $this->assertIsArray($res['json'], "not JSON:\n" . $res['body']);
        return $res;
    }

    private function send(string $from, string $to, string $text): void
    {
        $this->assertSame(200, $this->post($from, 'send', ['with' => $to, 'text' => $text])['status']);
    }

    private function threadKeys(string $who): array
    {
        return array_column(msg_threads(self::db(), $who), 'id');
    }

    public function testDeletingHidesTheConversationOnlyForMe(): void
    {
        $this->send(self::EMP, self::ADMIN, 'hello boss');
        $this->send(self::ADMIN, self::EMP, 'hi Juan');

        $res = $this->post(self::EMP, 'clear', ['with' => self::ADMIN]);
        $this->assertSame(200, $res['status']);

        $this->assertSame([], $this->threadKeys(self::EMP), 'gone from my list');
        $this->assertSame([], msg_messages(self::db(), self::EMP, self::ADMIN), 'and from my view');
        $this->assertSame([self::EMP], $this->threadKeys(self::ADMIN), 'the other person still has it');
        $this->assertCount(2, msg_messages(self::db(), self::ADMIN, self::EMP));
        $this->assertSame(2, (int) $this->row('SELECT COUNT(*) n FROM messages')['n'], 'nothing is actually deleted');
    }

    public function testUnreadMessagesIClearedStopCountingButAreNotMarkedSeen(): void
    {
        $this->send(self::ADMIN, self::EMP, 'are you there?');
        $this->assertSame(1, msg_unread_threads(self::db(), self::EMP));

        $this->post(self::EMP, 'clear', ['with' => self::ADMIN]);

        $this->assertSame(0, msg_unread_threads(self::db(), self::EMP));
        $this->assertSame([], mh_unread_rows(self::db(), self::EMP), 'no chat head either');
        $this->assertSame(0, msg_seen_up_to(self::db(), self::ADMIN, self::EMP), 'Ada must not see a fake "Seen"');
    }

    public function testANewMessageBringsItBackWithOnlyWhatCameAfter(): void
    {
        $this->send(self::EMP, self::ADMIN, 'old one');
        $this->post(self::EMP, 'clear', ['with' => self::ADMIN]);
        $this->send(self::ADMIN, self::EMP, 'new one');

        $this->assertSame([self::ADMIN], $this->threadKeys(self::EMP));
        $msgs = msg_messages(self::db(), self::EMP, self::ADMIN);
        $this->assertSame(['new one'], array_column($msgs, 'text'));
        $this->assertSame(1, msg_unread_threads(self::db(), self::EMP));
        $this->assertSame((int) $msgs[0]['id'], msg_first_unread(self::db(), self::EMP, self::ADMIN));
    }

    public function testNothingToDeleteIsRefused(): void
    {
        $res = $this->post(self::EMP, 'clear', ['with' => self::ADMIN]);
        $this->assertSame(422, $res['status']);
        $this->assertSame(0, (int) $this->row('SELECT COUNT(*) n FROM msg_cleared')['n']);
    }

    public function testNeedsThePageToken(): void
    {
        $this->send(self::EMP, self::ADMIN, 'hello');
        $res = $this->request('query/Query-messages.php', ['action' => 'clear'], ['action' => 'clear', 'with' => self::ADMIN, 'token' => 'wrong'], $this->as(self::EMP));
        $this->assertSame(419, $res['status']);
        $this->assertSame([self::ADMIN], $this->threadKeys(self::EMP));
    }

    public function testGroupConversationClearsForMeOnly(): void
    {
        if (!grp_ready(self::db())) { $this->markTestSkipped('group tables not in the test schema'); }
        $g = grp_create(self::db(), self::EMP, 'Team', [self::ADMIN, self::C], 3);
        $this->assertTrue($g['ok'], json_encode($g));
        $key = 'grp:' . $g['id'];
        $this->send(self::ADMIN, $key, 'meeting at 3');

        $this->assertSame(200, $this->post(self::C, 'clear', ['with' => $key])['status']);

        $cora = array_column(grp_threads(self::db(), self::C), 'key');
        $this->assertNotContains($key, $cora, 'gone from my list');
        $this->assertSame([], grp_messages(self::db(), (int) $g['id'], self::C));
        $this->assertContains($key, array_column(grp_threads(self::db(), self::EMP), 'key'), 'others keep it');
        $this->assertNotEmpty(grp_messages(self::db(), (int) $g['id'], self::EMP));

        $this->send(self::ADMIN, $key, 'moved to 4');
        $this->assertSame(['moved to 4'], array_column(grp_messages(self::db(), (int) $g['id'], self::C), 'text'));
    }

    public function testCannotClearAGroupYouAreNotIn(): void
    {
        if (!grp_ready(self::db())) { $this->markTestSkipped('group tables not in the test schema'); }
        $g = grp_create(self::db(), self::EMP, 'Private', [self::ADMIN, self::D], 3);
        $this->assertTrue($g['ok'], json_encode($g));
        $this->send(self::EMP, 'grp:' . $g['id'], 'secret');

        $this->assertSame(422, $this->post(self::C, 'clear', ['with' => 'grp:' . $g['id']])['status']);
    }
}
