<?php
namespace Wedo\Tests;

use Wedo\Tests\Support\AppTestCase;

/**
 * Emoji reactions (1-to-1 and groups) and @mentions (groups).
 *
 *   includes/msg-reactions.php   one reaction per person per message; toggle / replace
 *   includes/msg-mentions.php    "@Full Name" / "@everyone" recorded on send
 *   query/Query-messages.php     action=react, `reactions` on action=thread, `mentions` on threads
 */
final class ReactionsMentionsTest extends AppTestCase
{
    private const TOKEN   = 'rx-test-token';
    private const OUTSIDE = 'XC-0001';
    private const B = 'TC-0011', C = 'TC-0012', D = 'TC-0013';

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        require_once dirname(__DIR__) . '/includes/msg-groups.php';
        require_once dirname(__DIR__) . '/includes/msg-reactions.php';
    }

    protected function setUp(): void
    {
        parent::setUp();
        $db = self::db();
        $db->exec("INSERT INTO companies (CompanyID, CompanyDesc, compcode, comcolor) VALUES ('XC', 'OtherCo', 'XC', '#000')");
        foreach ([[self::OUTSIDE, 'Olga', 'Outsider', 'XC'], [self::B, 'Ben', 'Bautista', 'TC'],
                  [self::C, 'Cora', 'Cruz', 'TC'], [self::D, 'Dino', 'Diaz', 'TC']] as [$id, $fn, $ln, $co]) {
            $db->prepare("INSERT INTO employees (EmpID, EmpFN, EmpLN, EmpMN, EmpSuffix, PosID, EmpStatusID, EmployeeIDNumber)
                          VALUES (?, ?, ?, '', '', 10, 1, ?)")->execute([$id, $fn, $ln, $id]);
            $db->prepare("INSERT INTO empdetails (EmpID, EmpUN, EmpRoleID, EmpISID, EmpdepID, EmpCompID, EmpStatID, AgencyID, HMO_ID, EmpDateHired)
                          VALUES (?, ?, 3, 'N/A', 1, ?, 1, 1, 1, '2022-01-01')")->execute([$id, strtolower($fn), $co]);
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

    private function send(string $who, string $to, string $text): int
    {
        $res = $this->post($who, 'send', ['with' => $to, 'text' => $text]);
        $this->assertSame(200, $res['status'], $res['body']);
        return $res['json']['message']['id'];
    }

    private function react(string $who, int $id, string $emoji): array
    {
        return $this->post($who, 'react', ['id' => $id, 'emoji' => $emoji]);
    }

    /** Juan's group with Ada, Ben and Cora. */
    private function group(): string
    {
        $res = $this->post(self::EMP, 'group_create', ['name' => 'Payroll', 'members' => [self::ADMIN, self::B, self::C]]);
        $this->assertSame(200, $res['status'], $res['body']);
        return $res['json']['key'];
    }

    // ------------------------------------------------------------ reactions

    public function testReactingTogglesAndReplacesInAOneToOneChat(): void
    {
        $id = $this->send(self::EMP, self::ADMIN, 'Payslips are out');

        $res = $this->react(self::ADMIN, $id, '❤️');
        $this->assertSame(200, $res['status'], $res['body']);
        $this->assertSame([['emoji' => '❤️', 'count' => 1, 'mine' => true, 'names' => ['You']]], $res['json']['reactions']);

        // the sender sees it on the next thread load, with the reactor's first name
        $t = $this->get(self::EMP, ['action' => 'thread', 'with' => self::ADMIN])['json'];
        $this->assertSame([['emoji' => '❤️', 'count' => 1, 'mine' => false, 'names' => ['Ada']]], $t['reactions'][(string) $id]);

        // another emoji replaces mine; the same one again removes it
        $this->assertSame('😂', $this->react(self::ADMIN, $id, '😂')['json']['reactions'][0]['emoji']);
        $this->assertSame(1, (int) self::db()->query("SELECT COUNT(*) FROM msg_reactions")->fetchColumn());
        $this->assertSame([], $this->react(self::ADMIN, $id, '😂')['json']['reactions']);
        $t = $this->get(self::EMP, ['action' => 'thread', 'with' => self::ADMIN])['json'];
        $this->assertSame([], $t['reactions']);
    }

    public function testDifferentEmojiStayApartAndTheMostUsedComesFirst(): void
    {
        $key = $this->group();
        $id  = $this->send(self::EMP, $key, 'Lunch at 12?');
        $this->react(self::ADMIN, $id, '😮');
        $mf = $this->react(self::C, $id, '🖕');
        $this->assertSame(200, $mf['status'], $mf['body']);
        $this->assertEqualsCanonicalizing(['😮', '🖕'], array_column($mf['json']['reactions'], 'emoji'));
        $this->react(self::C, $id, '🖕');   // and off again
        $this->react(self::B, $id, '😂');     // utf8mb4_bin: not "equal" to 😮
        $this->react(self::C, $id, '😂');
        $this->react(self::EMP, $id, '😂');

        $rx = $this->get(self::B, ['action' => 'thread', 'with' => $key])['json']['reactions'][(string) $id];
        $this->assertSame(['😂', '😮'], array_column($rx, 'emoji'));
        $this->assertSame(3, $rx[0]['count']);
        $this->assertTrue($rx[0]['mine']);
        $this->assertSame('You', $rx[0]['names'][0]);                  // "You" leads
        $this->assertEqualsCanonicalizing(['You', 'Cora', 'Juan'], $rx[0]['names']);
        $this->assertSame(['Ada'], $rx[1]['names']);
    }

    public function testOnlyPeopleInTheConversationCanReact(): void
    {
        $dm  = $this->send(self::EMP, self::ADMIN, 'Private note');
        $key = $this->group();
        $grp = $this->send(self::EMP, $key, 'Group note');

        $this->assertSame(422, $this->react(self::D, $dm, '👍')['status']);        // not in the chat
        $this->assertSame(422, $this->react(self::D, $grp, '👍')['status']);       // not in the group
        $this->assertSame(422, $this->react(self::ADMIN, $dm, '🍕')['status']);    // not one of the seven
        $this->assertSame(422, $this->react(self::ADMIN, 999999, '👍')['status']); // no such message

        $event = (int) self::db()->query("SELECT MSID FROM messages WHERE Kind = 'event' ORDER BY MSID LIMIT 1")->fetchColumn();
        $this->assertGreaterThan(0, $event);
        $this->assertSame(422, $this->react(self::B, $event, '👍')['status']);     // "Juan created the group" lines can't

        $this->assertSame(0, (int) self::db()->query("SELECT COUNT(*) FROM msg_reactions")->fetchColumn());
    }

    public function testReactionsStayOffUntilTheMigrationRuns(): void
    {
        $id = $this->send(self::EMP, self::ADMIN, 'hello');
        self::db()->exec('RENAME TABLE msg_reactions TO msg_reactions_off');
        try {
            $t = $this->get(self::ADMIN, ['action' => 'thread', 'with' => self::EMP]);
            $this->assertSame(200, $t['status'], $t['body']);
            $this->assertNull($t['json']['reactions']);                               // the page hides the react button
            $res = $this->react(self::ADMIN, $id, '👍');
            $this->assertSame(409, $res['status']);
            $this->assertSame('disabled', $res['json']['code']);
        } finally {
            self::db()->exec('RENAME TABLE msg_reactions_off TO msg_reactions');
        }
    }

    // ------------------------------------------------------------ mentions

    public function testMentionsAreRecordedAndFlaggedUntilRead(): void
    {
        $key = $this->group();
        $id  = $this->send(self::EMP, $key, "@Ben Bautista and @cora cruz — please check. @Ada Adminx isn't a name, nor is @Dino Diaz");

        $who = self::db()->query("SELECT EmpID FROM msg_mentions WHERE MSID = $id ORDER BY EmpID")->fetchAll(\PDO::FETCH_COLUMN);
        $this->assertSame([self::B, self::C], $who);    // Dino isn't in the group; "Adminx" isn't Ada

        $ben = array_values(array_filter($this->get(self::B, ['action' => 'threads'])['json']['threads'], fn($t) => $t['key'] === $key))[0];
        $this->assertSame(1, $ben['mentions']);
        $ada = array_values(array_filter($this->get(self::ADMIN, ['action' => 'threads'])['json']['threads'], fn($t) => $t['key'] === $key))[0];
        $this->assertSame(0, $ada['mentions']);

        $msgs = $this->get(self::B, ['action' => 'thread', 'with' => $key])['json']['messages'];
        $mine = array_values(array_filter($msgs, fn($m) => $m['id'] === $id))[0];
        $this->assertTrue($mine['mentionsMe']);
        $msgsAda = $this->get(self::ADMIN, ['action' => 'thread', 'with' => $key])['json']['messages'];
        $this->assertFalse(array_values(array_filter($msgsAda, fn($m) => $m['id'] === $id))[0]['mentionsMe']);

        // Ben opened it: the "@" goes away
        $ben = array_values(array_filter($this->get(self::B, ['action' => 'threads'])['json']['threads'], fn($t) => $t['key'] === $key))[0];
        $this->assertSame(0, $ben['mentions']);
    }

    public function testEveryoneMentionsTheWholeGroupButTheSender(): void
    {
        $key = $this->group();
        $id  = $this->send(self::B, $key, '@everyone meeting in 5');
        $who = self::db()->query("SELECT EmpID FROM msg_mentions WHERE MSID = $id ORDER BY EmpID")->fetchAll(\PDO::FETCH_COLUMN);
        $this->assertEqualsCanonicalizing([self::ADMIN, self::EMP, self::C], $who);
    }

    public function testMentionMatchingRules(): void
    {
        $this->assertTrue(mn_names_in('hi @Ben Bautista!', 'Ben Bautista'));
        $this->assertTrue(mn_names_in('@BEN BAUTISTA', 'Ben Bautista'));
        $this->assertFalse(mn_names_in('hi @Ben Bautistas', 'Ben Bautista'));
        $this->assertFalse(mn_names_in('Ben Bautista', 'Ben Bautista'));       // no "@"
        $this->assertTrue(mn_names_in('ok @José Peña.', 'José Peña'));
    }
}
