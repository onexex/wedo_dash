<?php
namespace Wedo\Tests;

use Wedo\Tests\Support\AppTestCase;

/**
 * Video calls — 1-to-1 and group calls up to 4 (server side: state, members, signalling;
 * the media itself is browser-to-browser).
 *
 *   includes/msg-calls.php     call + member states, time rules, notes in the conversation
 *   query/Query-calls.php      JSON API (session + token on writes)
 *   includes/call-widget.php   ringer + call window on every page
 */
final class CallsTest extends AppTestCase
{
    private const TOKEN   = 'call-test-token';
    private const OUTSIDE = 'XC-0001';   // another company, unreachable for regular employees
    private const B = 'TC-0011', C = 'TC-0012', D = 'TC-0013', E = 'TC-0014';   // more colleagues for group calls

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        require_once dirname(__DIR__) . '/includes/msg-calls.php';
    }

    protected function setUp(): void
    {
        parent::setUp();
        $db = self::db();
        $db->exec("INSERT INTO companies (CompanyID, CompanyDesc, compcode, comcolor) VALUES ('XC', 'OtherCo', 'XC', '#000')");
        foreach ([[self::OUTSIDE, 'Olga', 'Outsider', 'XC'], [self::B, 'Ben', 'Bautista', 'TC'], [self::C, 'Cora', 'Cruz', 'TC'],
                  [self::D, 'Dino', 'Diaz', 'TC'], [self::E, 'Ella', 'Estrada', 'TC']] as [$id, $fn, $ln, $co]) {
            $db->prepare("INSERT INTO employees (EmpID, EmpFN, EmpLN, EmpMN, EmpSuffix, PosID, EmpStatusID, EmployeeIDNumber)
                          VALUES (?, ?, ?, '', '', 10, 1, ?)")->execute([$id, $fn, $ln, $id]);
            $db->prepare("INSERT INTO empdetails (EmpID, EmpUN, EmpRoleID, EmpISID, EmpdepID, EmpCompID, EmpStatID, AgencyID, HMO_ID, EmpDateHired)
                          VALUES (?, ?, 3, 'N/A', 1, ?, 1, 1, 1, '2022-01-01')")->execute([$id, strtolower($fn), $co]);
        }
    }

    private function as(string $id, int $type = 3): array
    {
        return ['id' => $id, 'UserType' => $type, 'CompanyName' => 'TestCo', 'CompanyColor' => '#f93627', 'CompID' => 'TC', 'msg_csrf' => self::TOKEN];
    }

    private function api(string $who, array $get, array $post = [], int $type = 3): array
    {
        if ($post) { $post += ['token' => self::TOKEN]; }
        $res = $this->request('query/Query-calls.php', $get, $post, $this->as($who, $type));
        $this->assertNoPhpErrors($res['body']);
        $res['json'] = json_decode($res['body'], true);
        $this->assertIsArray($res['json'], "not JSON:\n" . $res['body']);
        return $res;
    }

    private function post(string $who, string $action, array $data = [], int $type = 3): array
    {
        return $this->api($who, ['action' => $action], ['action' => $action] + $data, $type);
    }

    /** Juan (employee) calls Ada (his superior). */
    private function startCall(): int
    {
        $res = $this->post(self::EMP, 'start', ['with' => self::ADMIN]);
        $this->assertSame(200, $res['status'], $res['body']);
        return $res['json']['call']['id'];
    }

    private function callRow(int $id): array { return $this->row('SELECT * FROM msg_calls WHERE id = ?', [$id]); }

    private function memberState(int $id, string $emp): string
    {
        return $this->row('SELECT state FROM msg_call_members WHERE call_id = ? AND EmpID = ?', [$id, $emp])['state'];
    }

    private function notes(): array
    {
        return array_column($this->rows('SELECT Message FROM messages ORDER BY MSID'), 'Message');
    }

    /** a group of Juan (admin) + Ada + Ben + Cora + Dino + Ella */
    private function makeGroup(): int
    {
        $res = grp_create(self::db(), self::EMP, 'Ops Team', [self::ADMIN, self::B, self::C, self::D, self::E], 3);
        $this->assertTrue($res['ok'], $res['error'] ?? '');
        return $res['id'];
    }

    // ------------------------------------------------------------ 1-to-1: ringing

    public function testCallRingsForTheCalleeOnly(): void
    {
        $id = $this->startCall();
        $res = $this->post(self::EMP, 'start', ['with' => self::ADMIN]);
        $this->assertSame(409, $res['status']);                               // can't start a second call
        $this->assertSame('busy_me', $res['json']['code']);

        $ring = $this->api(self::ADMIN, ['action' => 'incoming'], [], 1)['json']['call'];
        $this->assertSame($id, $ring['id']);
        $this->assertSame('Juan Dela Cruz', $ring['starter']['name']);
        $this->assertNull($ring['group']);
        $this->assertNull($this->api(self::EMP, ['action' => 'incoming'])['json']['call']);   // the caller isn't rung
    }

    public function testYouCanOnlyCallPeopleYouCanMessageAndNotSomeoneAlreadyOnACall(): void
    {
        $res = $this->post(self::EMP, 'start', ['with' => self::OUTSIDE]);
        $this->assertSame(409, $res['status']);
        $this->assertSame('forbidden', $res['json']['code']);

        $id = $this->startCall();                                             // Ada is now being called by Juan
        $res = $this->post(self::OUTSIDE, 'start', ['with' => self::ADMIN], 1);
        $this->assertSame('busy', $res['json']['code']);
        $this->assertSame(404, $this->post(self::OUTSIDE, 'join', ['id' => $id], 1)['status']);   // not part of this call
    }

    // ------------------------------------------------------------ 1-to-1: answering + signalling

    public function testAnswerAndExchangeSignalsBetweenThePair(): void
    {
        $id = $this->startCall();
        $sig = fn(string $from, string $to, string $kind, array $data, int $type = 3) =>
            $this->post($from, 'signal', ['id' => $id, 'to' => $to, 'kind' => $kind, 'payload' => json_encode($data)], $type);

        $this->assertSame(422, $sig(self::EMP, self::ADMIN, 'offer', ['sdp' => 'x'])['status']);   // Ada hasn't joined yet

        $ans = $this->post(self::ADMIN, 'answer', ['id' => $id], 1);             // 'answer' = join
        $this->assertSame(200, $ans['status'], $ans['body']);
        $this->assertSame('active', $ans['json']['call']['status']);
        $this->assertNotEmpty($ans['json']['iceServers']);
        $this->assertNotNull($this->callRow($id)['connected_at']);

        $this->assertSame(200, $sig(self::EMP, self::ADMIN, 'offer', ['type' => 'offer', 'sdp' => 'v=0 juan'])['status']);
        $this->assertSame(200, $sig(self::EMP, self::ADMIN, 'ice', ['candidate' => 'c1'])['status']);
        $bad = $this->post(self::EMP, 'signal', ['id' => $id, 'to' => self::ADMIN, 'kind' => 'ice', 'payload' => 'not json']);
        $this->assertSame(422, $bad['status']);
        $this->assertSame(422, $sig(self::EMP, self::EMP, 'ice', ['candidate' => 'self'])['status']);   // not to myself

        $state = $this->api(self::ADMIN, ['action' => 'state', 'id' => $id, 'after' => 0], [], 1)['json'];
        $this->assertSame(['offer', 'ice'], array_column($state['signals'], 'kind'));
        $this->assertSame([self::EMP, self::EMP], array_column($state['signals'], 'from'));
        $this->assertSame('v=0 juan', $state['signals'][0]['data']['sdp']);
        $this->assertSame([], $this->api(self::EMP, ['action' => 'state', 'id' => $id, 'after' => 0])['json']['signals']);   // never my own

        $this->assertSame(404, $this->api(self::OUTSIDE, ['action' => 'state', 'id' => $id], [], 1)['status']);   // outsiders can't read it
    }

    // ------------------------------------------------------------ 1-to-1: how calls end

    public function testDecliningEndsTheCallWithANote(): void
    {
        $id = $this->startCall();
        $res = $this->post(self::ADMIN, 'hangup', ['id' => $id], 1);           // 'hangup' = leave = decline while ringing
        $this->assertSame('ended', $res['json']['call']['status']);
        $this->assertSame('declined', $res['json']['call']['reason']);
        $this->assertSame(['📹 Video call declined'], $this->notes());
        $this->assertSame('event', $this->row('SELECT Kind FROM messages')['Kind']);
        $this->assertSame(0, (int) $this->row('SELECT COUNT(*) n FROM msg_call_signals')['n']);
    }

    public function testCallerHangingUpWhileRingingIsAMissedCallForTheCallee(): void
    {
        $id = $this->startCall();
        $this->post(self::EMP, 'leave', ['id' => $id]);
        $this->assertSame('cancelled', $this->callRow($id)['end_reason']);
        $this->assertSame(['📹 Missed video call'], $this->notes());
        $this->assertSame(1, msg_unread_threads(self::db(), self::ADMIN));   // shows up as unread for Ada
    }

    public function testUnansweredCallBecomesMissedAfter45Seconds(): void
    {
        $id = $this->startCall();
        self::db()->exec("UPDATE msg_calls SET created_at = created_at - INTERVAL 60 SECOND WHERE id = $id");
        $this->assertNull($this->api(self::ADMIN, ['action' => 'incoming'], [], 1)['json']['call']);
        $this->assertSame('missed', $this->callRow($id)['end_reason']);
        $this->assertSame('missed', $this->memberState($id, self::ADMIN));
        $this->assertSame(['📹 Missed video call'], $this->notes());
    }

    public function testFinishedCallNotesItsDuration(): void
    {
        $id = $this->startCall();
        $this->post(self::ADMIN, 'join', ['id' => $id], 1);
        self::db()->exec("UPDATE msg_calls SET connected_at = connected_at - INTERVAL 133 SECOND WHERE id = $id");
        $res = $this->post(self::EMP, 'leave', ['id' => $id]);
        $this->assertSame('ended', $res['json']['call']['status']);
        $this->assertCount(1, $this->notes());
        $this->assertMatchesRegularExpression('/^📹 Video call · 2m 1[3-6]s$/u', $this->notes()[0]);   // 133 s + test run time
    }

    public function testCallEndsWhenTheOtherSideDisappears(): void
    {
        $id = $this->startCall();
        $this->post(self::ADMIN, 'join', ['id' => $id], 1);
        self::db()->exec("UPDATE msg_call_members SET ping = ping - INTERVAL 30 SECOND WHERE call_id = $id AND EmpID = '" . self::ADMIN . "'");
        $state = $this->api(self::EMP, ['action' => 'state', 'id' => $id])['json'];
        $this->assertSame('ended', $state['call']['status']);
        $this->assertSame('left', $this->memberState($id, self::ADMIN));
    }

    // ------------------------------------------------------------ group calls

    public function testGroupCallRingsEveryoneAndStopsAtFourPeople(): void
    {
        $gid = $this->makeGroup();
        $res = $this->post(self::EMP, 'start', ['with' => 'grp:' . $gid]);
        $this->assertSame(200, $res['status'], $res['body']);
        $id = $res['json']['call']['id'];
        $this->assertSame('Ops Team', $res['json']['call']['group']['name']);

        foreach ([self::ADMIN, self::B, self::C, self::D, self::E] as $who) {
            $ring = $this->api($who, ['action' => 'incoming'], [], $who === self::ADMIN ? 1 : 3)['json']['call'];
            $this->assertSame($id, $ring['id'], "$who should be rung");
        }

        $this->assertSame(200, $this->post(self::B, 'join', ['id' => $id])['status']);
        $this->assertSame(200, $this->post(self::C, 'join', ['id' => $id])['status']);
        $this->assertSame(200, $this->post(self::D, 'join', ['id' => $id])['status']);   // 4 now: Juan, Ben, Cora, Dino
        $full = $this->post(self::E, 'join', ['id' => $id]);
        $this->assertSame(409, $full['status']);
        $this->assertSame('full', $full['json']['code']);

        // Ada is still being rung, so for her it's "you're already in a call"…
        $this->assertSame('busy_me', $this->post(self::ADMIN, 'start', ['with' => 'grp:' . $gid], 1)['json']['code']);
        // …and once Ella declines, she can't start a second call in the same group while this one is going
        $this->post(self::E, 'leave', ['id' => $id]);
        $this->assertSame('in_progress', $this->post(self::E, 'start', ['with' => 'grp:' . $gid])['json']['code']);
        // people outside the group can't start one there
        $this->assertSame('forbidden', $this->post(self::OUTSIDE, 'start', ['with' => 'grp:' . $gid], 1)['json']['code']);
    }

    public function testGroupSignalsAreAddressedToOnePerson(): void
    {
        $gid = $this->makeGroup();
        $id = $this->post(self::EMP, 'start', ['with' => 'grp:' . $gid])['json']['call']['id'];
        $this->post(self::B, 'join', ['id' => $id]);
        $this->post(self::C, 'join', ['id' => $id]);

        $this->post(self::B, 'signal', ['id' => $id, 'to' => self::C, 'kind' => 'offer', 'payload' => '{"sdp":"ben-to-cora"}']);
        $this->post(self::B, 'signal', ['id' => $id, 'to' => self::EMP, 'kind' => 'offer', 'payload' => '{"sdp":"ben-to-juan"}']);

        $cora = $this->api(self::C, ['action' => 'state', 'id' => $id])['json'];
        $this->assertSame(['ben-to-cora'], array_column(array_column($cora['signals'], 'data'), 'sdp'));
        $juan = $this->api(self::EMP, ['action' => 'state', 'id' => $id])['json'];
        $this->assertSame(['ben-to-juan'], array_column(array_column($juan['signals'], 'data'), 'sdp'));
        $this->assertSame(3, count(array_filter($juan['call']['members'], fn($m) => $m['state'] === 'joined')));
    }

    public function testGroupCallKeepsGoingUntilOnePersonIsLeftThenNotesTheGroup(): void
    {
        $gid = $this->makeGroup();
        $id = $this->post(self::EMP, 'start', ['with' => 'grp:' . $gid])['json']['call']['id'];
        $this->post(self::B, 'join', ['id' => $id]);
        $this->post(self::C, 'join', ['id' => $id]);
        foreach ([self::ADMIN, self::D, self::E] as $who) { $this->post($who, 'leave', ['id' => $id], $who === self::ADMIN ? 1 : 3); }   // decline

        $this->post(self::EMP, 'leave', ['id' => $id]);                       // the starter leaves; Ben + Cora carry on
        $this->assertSame('active', $this->callRow($id)['status']);
        $this->post(self::B, 'leave', ['id' => $id]);                         // only Cora left, nobody being rung -> over
        $this->assertSame('ended', $this->callRow($id)['status']);

        $last = $this->row("SELECT Message, Kind, MHID FROM messages ORDER BY MSID DESC LIMIT 1");
        $this->assertSame('grp:' . $gid, $last['MHID']);
        $this->assertSame('event', $last['Kind']);
        $this->assertStringStartsWith('📹 Group call · ', $last['Message']);
    }

    public function testUnansweredGroupCallIsAMissedGroupCall(): void
    {
        $gid = $this->makeGroup();
        $id = $this->post(self::EMP, 'start', ['with' => 'grp:' . $gid])['json']['call']['id'];
        self::db()->exec("UPDATE msg_calls SET created_at = created_at - INTERVAL 60 SECOND WHERE id = $id");
        $this->api(self::EMP, ['action' => 'state', 'id' => $id]);
        $this->assertSame('missed', $this->callRow($id)['end_reason']);
        $this->assertSame('📹 Missed group call', $this->row("SELECT Message FROM messages ORDER BY MSID DESC LIMIT 1")['Message']);
    }

    // ------------------------------------------------------------ guards + rollout

    public function testWritesNeedTheToken(): void
    {
        $res = $this->request('query/Query-calls.php', ['action' => 'start'], ['action' => 'start', 'with' => self::ADMIN, 'token' => 'nope'], $this->as(self::EMP));
        $this->assertSame(419, $res['status']);
        $this->assertSame(0, (int) $this->row('SELECT COUNT(*) n FROM msg_calls')['n']);
    }

    public function testRingerStaysQuietBeforeTheMigration(): void
    {
        self::db()->exec('RENAME TABLE msg_call_members TO msg_call_members_off');
        try {
            $res = $this->api(self::ADMIN, ['action' => 'incoming'], [], 1);
            $this->assertSame(200, $res['status']);
            $this->assertTrue($res['json']['disabled']);
            $res = $this->post(self::EMP, 'start', ['with' => self::ADMIN]);
            $this->assertSame(409, $res['status']);                            // never 5xx: hosts replace those with HTML
            $this->assertSame('disabled', $res['json']['code']);
        } finally {
            self::db()->exec('RENAME TABLE msg_call_members_off TO msg_call_members');
        }
    }

    /** What production had on 2026-10-01: msg_calls from an early draft (caller/callee). */
    public function testDraftCallTablesSwitchCallsOffInsteadOfCrashing(): void
    {
        $db = self::db();
        $db->exec('RENAME TABLE msg_calls TO msg_calls_real');
        $db->exec("CREATE TABLE msg_calls (id INT AUTO_INCREMENT PRIMARY KEY, caller VARCHAR(50) NOT NULL, callee VARCHAR(50) NOT NULL,
                   status VARCHAR(12) NOT NULL DEFAULT 'ringing', created_at DATETIME NOT NULL)");
        try {
            $this->assertTrue($this->api(self::ADMIN, ['action' => 'incoming'], [], 1)['json']['disabled']);
            $res = $this->post(self::EMP, 'start', ['with' => self::ADMIN]);
            $this->assertSame(409, $res['status'], $res['body']);
            $this->assertSame('disabled', $res['json']['code']);
        } finally {
            $db->exec('DROP TABLE msg_calls');
            $db->exec('RENAME TABLE msg_calls_real TO msg_calls');
        }
    }

    public function testEveryPageLoadsTheCallWidget(): void
    {
        foreach (['messages.php', 'idcard.php'] as $page) {
            $body = $this->request($page, [], [], $this->as(self::ADMIN, 1))['body'];
            $this->assertStringContainsString('window.WD_CALL', $body, $page);
            $this->assertStringContainsString('assets/js/wedo-call.js', $body, $page);
        }
    }
}
