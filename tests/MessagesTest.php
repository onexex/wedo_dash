<?php
namespace Wedo\Tests;

use Wedo\Tests\Support\AppTestCase;

/**
 * One-to-one messaging.
 *
 *   includes/messages-lib.php    conversations, read state, who may message whom, search
 *   query/Query-messages.php     JSON API (session + per-session token on send)
 *   messages.php                 the screen; e201 links into it
 */
final class MessagesTest extends AppTestCase
{
    private const TOKEN   = 'msg-test-token';
    private const OUTSIDE = 'XC-0001';   // another company, no relationship to the seeded people
    private const LEFT    = 'TC-0002';   // same company, resigned (EmpStatusID 2)
    private const DATED   = 'TC-0003';   // same company, employed, but EmpDateResigned filled in (as in the real data)

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        require_once dirname(__DIR__) . '/includes/messages-lib.php';
    }

    protected function setUp(): void
    {
        parent::setUp();
        $db = self::db();
        $db->exec("INSERT INTO companies (CompanyID, CompanyDesc, compcode, comcolor) VALUES ('XC', 'OtherCo', 'XC', '#000')");
        foreach ([[self::OUTSIDE, 'Olga', 'Outsider', 'XC', '0000-00-00', 1],
                  [self::LEFT, 'Lena', 'Leaver', 'TC', '2025-12-31', 2],
                  [self::DATED, 'Dani', 'Dated', 'TC', '2022-01-01', 1]] as [$id, $fn, $ln, $co, $resigned, $status]) {
            $db->prepare("INSERT INTO employees (EmpID, EmpFN, EmpLN, EmpMN, EmpSuffix, PosID, EmpStatusID, EmployeeIDNumber)
                          VALUES (?, ?, ?, '', '', 10, ?, ?)")->execute([$id, $fn, $ln, $status, $id]);
            $db->prepare("INSERT INTO empdetails (EmpID, EmpUN, EmpRoleID, EmpISID, EmpdepID, EmpCompID, EmpStatID, AgencyID, HMO_ID,
                          EmpDateHired, EmpDateResigned) VALUES (?, ?, 3, 'N/A', 1, ?, 1, 1, 1, '2022-01-01', ?)")
               ->execute([$id, strtolower($fn), $co, $resigned]);
        }
    }

    private function as(string $id, int $type = 3): array
    {
        return ['id' => $id, 'UserType' => $type, 'CompanyName' => 'TestCo', 'CompanyColor' => '#f93627',
                'CompID' => 'TC', 'msg_csrf' => self::TOKEN];
    }

    private function api(array $get, array $post = [], ?array $session = null): array
    {
        $res = $this->request('query/Query-messages.php', $get, $post, $session);
        $this->assertNoPhpErrors($res['body']);
        $res['json'] = json_decode($res['body'], true);
        $this->assertIsArray($res['json'], "not JSON:\n" . $res['body']);
        return $res;
    }

    private function send(string $from, string $to, string $text, int $type = 3): array
    {
        return $this->api(['action' => 'send'], ['with' => $to, 'text' => $text, 'token' => self::TOKEN], $this->as($from, $type));
    }

    // ------------------------------------------------------------ sending + reading

    public function testFirstMessageStartsAConversationBothSidesSee(): void
    {
        $res = $this->send(self::EMP, self::ADMIN, "  Hi boss,\r\nleave tomorrow?  ");
        $this->assertSame(200, $res['status']);
        $this->assertSame("Hi boss,\nleave tomorrow?", $res['json']['message']['text']);

        $this->assertSame(1, (int) $this->row('SELECT COUNT(*) n FROM messageheader')['n']);
        $this->assertSame(self::EMP . '_' . self::ADMIN, $this->row('SELECT MHID FROM messageheader')['MHID']);

        $mine = msg_threads(self::db(), self::EMP);
        $this->assertSame(self::ADMIN, $mine[0]['id']);
        $this->assertSame('Ada Admin', $mine[0]['name']);
        $this->assertTrue($mine[0]['lastMine']);
        $this->assertSame(0, $mine[0]['unread']);

        $theirs = msg_threads(self::db(), self::ADMIN);
        $this->assertSame(self::EMP, $theirs[0]['id']);
        $this->assertSame(1, $theirs[0]['unread']);
        $this->assertSame(1, msg_unread_threads(self::db(), self::ADMIN));
        $this->assertSame(0, msg_unread_threads(self::db(), self::EMP));
    }

    public function testReplyReusesTheConversationStartedByTheOtherSide(): void
    {
        $this->send(self::EMP, self::ADMIN, 'Question');
        $this->send(self::ADMIN, self::EMP, 'Answer');
        $this->assertSame(1, (int) $this->row('SELECT COUNT(*) n FROM messageheader')['n']);
        $this->assertSame(2, (int) $this->row('SELECT COUNT(*) n FROM messages')['n']);
    }

    public function testOpeningAConversationMarksOnlyTheirMessagesRead(): void
    {
        $this->send(self::EMP, self::ADMIN, 'one');
        $this->send(self::ADMIN, self::EMP, 'two');

        $res = $this->api(['action' => 'thread', 'with' => self::EMP], [], $this->as(self::ADMIN, 1));
        $this->assertSame(['one', 'two'], array_column($res['json']['messages'], 'text'));
        $this->assertSame([false, true], array_column($res['json']['messages'], 'mine'));
        $this->assertTrue($res['json']['canSend']);

        $status = array_column($this->rows('SELECT SenderID, Status FROM messages ORDER BY MSID'), 'Status', 'SenderID');
        $this->assertSame(2, (int) $status[self::EMP]);     // what Ada received: now read
        $this->assertSame(1, (int) $status[self::ADMIN]);   // what Ada sent: still unread by Juan
        $this->assertSame(1, msg_unread_threads(self::db(), self::EMP));
    }

    public function testAfterReturnsOnlyNewerMessages(): void
    {
        $first = $this->send(self::EMP, self::ADMIN, 'old')['json']['message']['id'];
        $this->send(self::EMP, self::ADMIN, 'new');
        $res = $this->api(['action' => 'thread', 'with' => self::ADMIN, 'after' => $first], [], $this->as(self::EMP));
        $this->assertSame(['new'], array_column($res['json']['messages'], 'text'));
    }

    // ------------------------------------------------------------ receipts, presence, typing

    public function testSeenReceiptAndFirstUnreadMarker(): void
    {
        $a = $this->send(self::EMP, self::ADMIN, 'a')['json']['message']['id'];
        $b = $this->send(self::EMP, self::ADMIN, 'b')['json']['message']['id'];

        $mine = $this->api(['action' => 'thread', 'with' => self::ADMIN], [], $this->as(self::EMP));
        $this->assertSame(0, $mine['json']['seenUpTo']);                 // Ada hasn't opened it yet: "Sent"

        $hers = $this->api(['action' => 'thread', 'with' => self::EMP], [], $this->as(self::ADMIN, 1));
        $this->assertSame($a, $hers['json']['firstUnread']);             // "New messages" line goes above 'a'

        $again = $this->api(['action' => 'thread', 'with' => self::EMP], [], $this->as(self::ADMIN, 1));
        $this->assertSame(0, $again['json']['firstUnread']);             // already read now

        $mine = $this->api(['action' => 'thread', 'with' => self::ADMIN], [], $this->as(self::EMP));
        $this->assertSame($b, $mine['json']['seenUpTo']);                // "Seen"
    }

    public function testOnlineStatusAndTypingIndicator(): void
    {
        $typing = fn(string $to) => $this->api(['action' => 'typing'], ['with' => $to, 'token' => self::TOKEN], $this->as(self::EMP));
        $adaSees = fn() => $this->api(['action' => 'thread', 'with' => self::EMP], [], $this->as(self::ADMIN, 1))['json']['presence'];

        $this->api(['action' => 'threads'], [], $this->as(self::EMP));   // Juan has Messages open
        $p = $adaSees();
        $this->assertTrue($p['online']);
        $this->assertFalse($p['typing']);
        $this->assertLessThan(10, $p['ago']);

        $this->assertSame(200, $typing(self::ADMIN)['status']);
        $this->assertTrue($adaSees()['typing']);
        $this->send(self::EMP, self::ADMIN, 'done typing');            // sending clears it
        $this->assertFalse($adaSees()['typing']);

        $typing(self::ADMIN);
        $this->assertTrue(msg_threads(self::db(), self::ADMIN) !== []);
        $list = $this->api(['action' => 'threads'], [], $this->as(self::ADMIN, 1))['json']['threads'];
        $this->assertTrue($list[0]['typing']);
        $this->assertTrue($list[0]['online']);

        // a typing ping goes stale after a few seconds, and going quiet clears it
        self::db()->exec("UPDATE msg_presence SET typing_at = typing_at - INTERVAL 30 SECOND WHERE EmpID = '" . self::EMP . "'");
        $this->assertFalse($adaSees()['typing']);
        $typing(self::ADMIN);
        $typing('');
        $this->assertFalse($adaSees()['typing']);

        // long gone = not online, but "Active … ago" still known
        self::db()->exec("UPDATE msg_presence SET last_seen = last_seen - INTERVAL 2 HOUR WHERE EmpID = '" . self::EMP . "'");
        $p = msg_presence(self::db(), self::ADMIN, [self::EMP])[self::EMP];
        $this->assertFalse($p['online']);
        $this->assertGreaterThan(7000, $p['ago']);
    }

    public function testOnlineNowListsColleaguesSeenOnAnyPage(): void
    {
        $onlineFor = fn(string $who, int $type = 3) => array_column(
            $this->api(['action' => 'threads'], [], $this->as($who, $type))['json']['online'], 'id');

        // Juan is on some other WeDo page: only the call ringer is checking in
        $this->request('query/Query-calls.php', ['action' => 'incoming'], [], $this->as(self::EMP));
        $this->assertContains(self::EMP, $onlineFor(self::ADMIN, 1));
        $this->assertNotContains(self::ADMIN, $onlineFor(self::ADMIN, 1));      // never myself

        // who you see follows who you may message
        $this->api(['action' => 'threads'], [], $this->as(self::OUTSIDE));      // Olga (other company) is online too
        $this->assertContains(self::OUTSIDE, $onlineFor(self::ADMIN, 1));      // super users see everyone
        $this->assertNotContains(self::OUTSIDE, $onlineFor(self::EMP));        // Juan can't message her, so he doesn't see her
        $this->assertContains(self::ADMIN, $onlineFor(self::EMP));             // but he sees his superior

        // resigned people never show; people gone for over a minute drop off
        $this->api(['action' => 'threads'], [], $this->as(self::LEFT));
        $this->assertNotContains(self::LEFT, $onlineFor(self::ADMIN, 1));
        self::db()->exec("UPDATE msg_presence SET last_seen = last_seen - INTERVAL 2 MINUTE WHERE EmpID = '" . self::EMP . "'");
        $this->assertNotContains(self::EMP, $onlineFor(self::ADMIN, 1));
    }

    public function testTypingNeedsTheTokenAndIsOnlyShownToReachablePeople(): void
    {
        $res = $this->api(['action' => 'typing'], ['with' => self::ADMIN, 'token' => 'nope'], $this->as(self::EMP));
        $this->assertSame(419, $res['status']);

        // a regular employee can't message someone in another company, so can't "type" to them either
        $this->api(['action' => 'typing'], ['with' => self::OUTSIDE, 'token' => self::TOKEN], $this->as(self::EMP));
        $this->assertFalse(msg_presence(self::db(), self::OUTSIDE, [self::EMP])[self::EMP]['typing']);
        $this->assertNull($this->row('SELECT typing_to FROM msg_presence WHERE EmpID = ?', [self::EMP])['typing_to']);
    }

    public function testMessagingStillWorksBeforeThePresenceMigration(): void
    {
        self::db()->exec('RENAME TABLE msg_presence TO msg_presence_off');
        try {
            $this->assertSame(200, $this->send(self::EMP, self::ADMIN, 'still works')['status']);
            $this->assertSame(200, $this->api(['action' => 'typing'], ['with' => self::ADMIN, 'token' => self::TOKEN], $this->as(self::EMP))['status']);
            $res = $this->api(['action' => 'thread', 'with' => self::EMP], [], $this->as(self::ADMIN, 1));
            $this->assertSame(200, $res['status']);
            $this->assertFalse($res['json']['presence']['online']);
            $this->assertSame(['still works'], array_column($res['json']['messages'], 'text'));
        } finally {
            self::db()->exec('RENAME TABLE msg_presence_off TO msg_presence');
        }
    }

    public function testEmojiAndAccentsSurviveTheRoundTrip(): void
    {
        $text = 'Salamat po! 😀👍 Niño — café';
        $this->assertSame($text, $this->send(self::EMP, self::ADMIN, $text)['json']['message']['text']);
        $back = $this->api(['action' => 'thread', 'with' => self::EMP], [], $this->as(self::ADMIN, 1));
        $this->assertSame([$text], array_column($back['json']['messages'], 'text'));   // not "Salamat po! ??"
    }

    public function testMarkupIsStoredAndReturnedAsPlainText(): void
    {
        $evil = '<img src=x onerror=alert(1)><script>alert(2)</script>';
        $this->send(self::EMP, self::ADMIN, $evil);
        $res = $this->api(['action' => 'thread', 'with' => self::EMP], [], $this->as(self::ADMIN, 1));
        $this->assertSame($evil, $res['json']['messages'][0]['text']);
        $this->assertStringNotContainsString('<script>', $res['body']);   // JSON-escaped, never raw HTML
    }

    public function testConversationsAreOrderedByLatestMessage(): void
    {
        $this->send(self::ADMIN, self::EMP, 'first', 1);
        $this->send(self::ADMIN, self::OUTSIDE, 'second', 1);
        self::db()->exec("UPDATE messages SET DateSent = '2026-01-01 08:00:00' WHERE Message = 'first'");
        self::db()->exec("UPDATE messages SET DateSent = '2026-01-02 08:00:00' WHERE Message = 'second'");
        $this->assertSame([self::OUTSIDE, self::EMP], array_column(msg_threads(self::db(), self::ADMIN), 'id'));

        $this->send(self::EMP, self::ADMIN, 'bump');
        $this->assertSame([self::EMP, self::OUTSIDE], array_column(msg_threads(self::db(), self::ADMIN), 'id'));
    }

    // ------------------------------------------------------------ guards

    public function testSendRejectsBadInput(): void
    {
        foreach ([['   ', 'Write a message first.'],
                  [str_repeat('a', MSG_MAX_LEN + 1), 'limited to']] as [$text, $msg]) {
            $res = $this->send(self::EMP, self::ADMIN, $text);
            $this->assertSame(422, $res['status']);
            $this->assertStringContainsString($msg, $res['json']['msg']);
        }
        $this->assertSame(422, $this->send(self::EMP, self::EMP, 'me')['status']);
        $this->assertSame(422, $this->send(self::EMP, 'NOPE-1', 'ghost')['status']);
        $this->assertSame(200, $this->send(self::EMP, self::ADMIN, str_repeat('ñ', MSG_MAX_LEN))['status']);
        $this->assertSame(1, (int) $this->row('SELECT COUNT(*) n FROM messages')['n']);
    }

    public function testSendNeedsTheSessionTokenAndALogin(): void
    {
        $res = $this->api(['action' => 'send'], ['with' => self::ADMIN, 'text' => 'hi', 'token' => 'wrong'], $this->as(self::EMP));
        $this->assertSame(419, $res['status']);
        $this->assertSame(401, $this->api(['action' => 'threads'])['status']);
        $this->assertSame(0, (int) $this->row('SELECT COUNT(*) n FROM messages')['n']);
    }

    public function testRegularUsersCannotStartChatsOutsideTheirCompany(): void
    {
        $this->assertSame(422, $this->send(self::EMP, self::OUTSIDE, 'hello')['status']);
        // a super user can, and the outsider may then reply in that conversation
        $this->assertSame(200, $this->send(self::ADMIN, self::OUTSIDE, 'hello', 1)['status']);
        $this->assertSame(200, $this->send(self::OUTSIDE, self::ADMIN, 'hi back')['status']);
    }

    public function testImmediateSuperiorIsReachableAcrossCompanies(): void
    {
        self::db()->exec("UPDATE empdetails SET EmpCompID = 'XC' WHERE EmpID = '" . self::ADMIN . "'");
        $this->assertTrue(msg_can_message(self::db(), self::EMP, self::ADMIN, 3));
        $this->assertTrue(msg_can_message(self::db(), self::ADMIN, self::EMP, 3));
    }

    // ------------------------------------------------------------ search

    public function testSearchMatchesPartialNamesAndSkipsMeResignedAndOtherCompanies(): void
    {
        $ids = fn(string $term, string $me = self::EMP, int $type = 3) => array_column(msg_search(self::db(), $me, $term, $type), 'id');

        $this->assertSame([self::ADMIN], $ids('adm'));
        $this->assertSame([self::ADMIN], $ids('Ada Ad'));
        $this->assertSame([self::ADMIN], $ids('A-1'));            // employee ID number
        $this->assertSame([], $ids('Juan'));                      // myself
        $this->assertSame([], $ids('Leaver'));                    // resigned (status, not date)
        $this->assertSame([self::DATED], $ids('Dated'));          // employed even though a resign date is filled in
        $this->assertSame([], $ids('Outsider'));                  // other company
        $this->assertSame([self::OUTSIDE], $ids('Outsider', self::ADMIN, 1));
        $this->assertSame([], $ids('a'));                         // too short
        $this->assertSame([], $ids('%'));                         // LIKE wildcards are literal
    }

    // ------------------------------------------------------------ pages

    public function testMessagesPageRendersWithTokenAndRequestedConversation(): void
    {
        $res = $this->request('messages.php', ['with' => self::ADMIN], [], $this->as(self::EMP));
        $this->assertNoPhpErrors($res['body']);
        $app = $this->dom($res['body'])->query("//*[@id='msgApp']")->item(0);
        $this->assertNotNull($app);
        $this->assertSame(self::TOKEN, $app->getAttribute('data-token'));
        $this->assertSame(self::ADMIN, $app->getAttribute('data-with'));

        $this->assertSame(302, $this->request('messages.php')['status']);
    }

    public function testE201LinksToMessagesWithUnreadBadge(): void
    {
        $this->send(self::ADMIN, self::EMP, 'Please update your info', 1);
        $res = $this->request('e201.php', [], [], $this->asEmployee());
        $this->assertNoPhpErrors($res['body']);
        $x = $this->dom($res['body']);

        $is = $x->query("//a[@id='sndmessage']")->item(0);
        $this->assertNotNull($is, 'Message IS link');
        $this->assertSame('messages?with=' . self::ADMIN, $is->getAttribute('href'));
        $this->assertSame('1', trim($x->query("//a[@id='msg']//b")->item(0)->textContent));
        $this->assertNotNull($x->query("//a[@id='wdMsgBtn']/span[contains(@class,'wd-iconbtn__dot')]")->item(0), 'topbar dot');
        $this->assertSame(0, $x->query("//*[contains(@class,'com-container')]")->length, 'old chat popup is gone');
    }
}
