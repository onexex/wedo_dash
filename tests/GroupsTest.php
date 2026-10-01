<?php
namespace Wedo\Tests;

use Wedo\Tests\Support\AppTestCase;

/**
 * Group chats in Messages.
 *
 *   includes/msg-groups.php      groups, members (admin | member), read positions, events
 *   query/Query-messages.php     group_* actions + threads/thread/send/typing with key 'grp:<id>'
 */
final class GroupsTest extends AppTestCase
{
    private const TOKEN   = 'grp-test-token';
    private const OUTSIDE = 'XC-0001';
    private const B = 'TC-0011', C = 'TC-0012', D = 'TC-0013';

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        require_once dirname(__DIR__) . '/includes/msg-groups.php';
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

    /** Juan creates "Payroll" with Ada, Ben and Cora; returns the group id. */
    private function payroll(): int
    {
        $res = $this->post(self::EMP, 'group_create', ['name' => '  Payroll   Team ', 'members' => [self::ADMIN, self::B, self::C]]);
        $this->assertSame(200, $res['status'], $res['body']);
        return (int) substr($res['json']['key'], 4);
    }

    private function say(string $who, int $gid, string $text): int
    {
        $res = $this->post($who, 'send', ['with' => 'grp:' . $gid, 'text' => $text]);
        $this->assertSame(200, $res['status'], $res['body']);
        return $res['json']['message']['id'];
    }

    private function thread(string $who, int $gid, int $after = 0): array
    {
        return $this->get($who, ['action' => 'thread', 'with' => 'grp:' . $gid, 'after' => $after]);
    }

    // ------------------------------------------------------------ creating

    public function testCreatingAGroup(): void
    {
        $gid = $this->payroll();
        $g = grp_get(self::db(), $gid);
        $this->assertSame('Payroll Team', $g['name']);                     // spaces tidied
        $roles = array_column(grp_members(self::db(), $gid), 'role', 'id');
        $this->assertSame('admin', $roles[self::EMP]);
        $this->assertSame('member', $roles[self::B]);
        $this->assertCount(4, $roles);

        $first = $this->thread(self::B, $gid)['json']['messages'][0];
        $this->assertSame('event', $first['kind']);
        $this->assertSame('Juan created the group “Payroll Team”', $first['text']);
    }

    public function testCreateRules(): void
    {
        $err = fn(array $data) => $this->post(self::EMP, 'group_create', $data)['json']['msg'] ?? null;
        $this->assertStringContainsString('name', $err(['name' => ' ', 'members' => [self::B, self::C]]));
        $this->assertStringContainsString('at least two', $err(['name' => 'Duo', 'members' => [self::B]]));
        $this->assertStringContainsString('at least two', $err(['name' => 'Me+1', 'members' => [self::B, self::EMP]]));   // me doesn't count
        $this->assertStringContainsString('can’t add', $err(['name' => 'X', 'members' => [self::B, self::OUTSIDE]]));     // other company
        $this->assertSame(0, (int) $this->row('SELECT COUNT(*) n FROM msg_groups')['n']);
    }

    // ------------------------------------------------------------ chatting

    public function testGroupMessagesUnreadAndSeenBy(): void
    {
        $gid = $this->payroll();
        $this->thread(self::ADMIN, $gid);  $this->thread(self::B, $gid);  $this->thread(self::C, $gid);   // everyone caught up
        $m1 = $this->say(self::EMP, $gid, 'Cut-off tomorrow 📅');

        $list = $this->get(self::B, ['action' => 'threads'])['json'];
        $item = array_values(array_filter($list['threads'], fn($t) => $t['key'] === 'grp:' . $gid))[0];
        $this->assertSame('group', $item['type']);
        $this->assertSame(1, $item['unread']);
        $this->assertSame('Juan', $item['lastSender']);
        $this->assertSame('Cut-off tomorrow 📅', $item['last']);
        $this->assertSame(1, $list['unread']);                              // counts in the header badge too

        $t = $this->thread(self::B, $gid)['json'];
        $this->assertSame($m1, $t['firstUnread']);
        $last = end($t['messages']);
        $this->assertSame('Juan Dela Cruz', $last['sender']['name']);
        $this->assertFalse($last['mine']);
        $this->assertSame(0, msg_unread_threads(self::db(), self::B));      // opening it read it

        // Juan sees who has read his message
        $readers = array_column($this->thread(self::EMP, $gid)['json']['readers'], 'lastRead', 'name');
        $this->assertGreaterThanOrEqual($m1, $readers['Ben']);
        $this->assertLessThan($m1, $readers['Cora']);
    }

    public function testOnlyMembersCanReadOrWrite(): void
    {
        $gid = $this->payroll();
        $this->assertSame(404, $this->thread(self::D, $gid)['status']);
        $this->assertSame(422, $this->post(self::D, 'send', ['with' => 'grp:' . $gid, 'text' => 'hi'])['status']);
        $this->assertFalse(in_array('grp:' . $gid, array_column($this->get(self::D, ['action' => 'threads'])['json']['threads'], 'key'), true));
    }

    public function testTypingShowsTheTypistsFirstName(): void
    {
        $gid = $this->payroll();
        $this->post(self::B, 'typing', ['with' => 'grp:' . $gid]);
        $this->assertSame(['Ben'], $this->thread(self::C, $gid)['json']['typing']);
        $item = array_values(array_filter($this->get(self::C, ['action' => 'threads'])['json']['threads'], fn($t) => $t['key'] === 'grp:' . $gid))[0];
        $this->assertSame('Ben', $item['typing']);
        $this->post(self::D, 'typing', ['with' => 'grp:' . $gid]);      // not a member: not shown
        $this->assertSame(['Ben'], $this->thread(self::C, $gid)['json']['typing']);
    }

    // ------------------------------------------------------------ managing

    public function testAdminsAddRemoveAndRenameMembersCannot(): void
    {
        $gid = $this->payroll();
        $this->say(self::EMP, $gid, 'old news');

        $this->assertSame(422, $this->post(self::B, 'group_add', ['id' => $gid, 'members' => [self::D]])['status']);      // Ben isn't admin
        $this->assertSame(200, $this->post(self::EMP, 'group_add', ['id' => $gid, 'members' => [self::D]])['status']);
        // a new member sees the group as unread only because of "Juan added Dino" — not the old history
        $this->assertSame(1, msg_unread_threads(self::db(), self::D));
        $first = $this->thread(self::D, $gid)['json'];
        $unread = array_values(array_filter($first['messages'], fn($m) => $m['id'] >= $first['firstUnread']));
        $this->assertSame(['Juan added Dino'], array_column($unread, 'text'));
        $this->assertSame(0, msg_unread_threads(self::db(), self::D));
        $this->say(self::B, $gid, 'welcome Dino');
        $this->assertSame(1, msg_unread_threads(self::db(), self::D));        // and sees what comes next

        $this->assertSame(422, $this->post(self::B, 'group_remove', ['id' => $gid, 'member' => self::C])['status']);
        $this->assertSame(200, $this->post(self::EMP, 'group_remove', ['id' => $gid, 'member' => self::C])['status']);
        $this->assertSame(404, $this->thread(self::C, $gid)['status']);

        $this->assertSame(422, $this->post(self::B, 'group_rename', ['id' => $gid, 'name' => 'Ben’s club'])['status']);
        $this->assertSame(200, $this->post(self::EMP, 'group_rename', ['id' => $gid, 'name' => 'Payroll Squad'])['status']);

        $events = array_column(array_filter($this->thread(self::EMP, $gid)['json']['messages'], fn($m) => $m['kind'] === 'event'), 'text');
        $this->assertContains('Juan added Dino', $events);
        $this->assertContains('Juan removed Cora', $events);
        $this->assertContains('Juan renamed the group to “Payroll Squad”', $events);
    }

    public function testLastAdminLeavingHandsOverAdmin(): void
    {
        $gid = $this->payroll();
        $this->assertSame(200, $this->post(self::EMP, 'group_leave', ['id' => $gid])['status']);
        $roles = array_column(grp_members(self::db(), $gid), 'role', 'id');
        $this->assertArrayNotHasKey(self::EMP, $roles);
        $this->assertSame(1, count(array_filter($roles, fn($r) => $r === 'admin')));   // someone took over
        $this->assertContains('Juan left the group', array_column($this->thread(self::B, $gid)['json']['messages'], 'text'));
    }

    /** Any unexpected server error comes back as JSON with a reference — never an HTML page the screen can't read. */
    public function testUnexpectedErrorsAreReadableJson(): void
    {
        $gid = $this->payroll();
        self::db()->exec('RENAME TABLE msg_groups TO msg_groups_off');   // break something the code doesn't expect
        try {
            $admin = $this->request('query/Query-messages.php', ['action' => 'thread', 'with' => 'grp:' . $gid], [], $this->as(self::ADMIN));
            $json = json_decode($admin['body'], true);
            $this->assertIsArray($json, "not JSON:\n" . $admin['body']);
            $this->assertSame('error', $json['status']);
            $this->assertMatchesRegularExpression('/^[0-9a-f]{6}$/', $json['ref']);
            $this->assertStringContainsString('msg_groups', $json['msg']);           // super users see the real error

            $ben = json_decode($this->request('query/Query-messages.php', ['action' => 'thread', 'with' => 'grp:' . $gid], [], $this->as(self::B))['body'], true);
            $this->assertStringNotContainsString('msg_groups', $ben['msg']);         // everyone else gets a plain message + ref
            $this->assertStringContainsString('ref ' . $ben['ref'], $ben['msg']);
        } finally {
            self::db()->exec('RENAME TABLE msg_groups_off TO msg_groups');
        }
    }

    public function testOneToOneStillWorksBeforeTheGroupsMigration(): void
    {
        self::db()->exec('RENAME TABLE msg_group_members TO msg_group_members_off');
        try {
            $sent = $this->post(self::EMP, 'send', ['with' => self::ADMIN, 'text' => 'still here']);
            $this->assertSame(200, $sent['status'], $sent['body']);
            $t = $this->get(self::ADMIN, ['action' => 'threads'])['json'];
            $this->assertFalse($t['groups']);
            $this->assertSame([self::EMP], array_column($t['threads'], 'key'));
            $res = $this->post(self::EMP, 'group_create', ['name' => 'X', 'members' => [self::B, self::C]]);
            $this->assertSame(409, $res['status']);
            $this->assertSame('disabled', $res['json']['code']);
        } finally {
            self::db()->exec('RENAME TABLE msg_group_members_off TO msg_group_members');
        }
    }
}
