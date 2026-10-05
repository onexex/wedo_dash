<?php
namespace Wedo\Tests;

use Wedo\Tests\Support\AppTestCase;

/**
 * Messenger-style chat heads on every page.
 *
 *   includes/msg-heads-lib.php   unread conversations, signature, previews
 *   query/Query-calls.php        the ringer's check-in carries them (action=incoming&hs=)
 *   includes/msg-heads.php       loaded on every page except Messages
 */
final class MessageHeadsTest extends AppTestCase
{
    private const B = 'TC-0011', C = 'TC-0012';

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
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

    private function as(string $id, int $type = 3): array
    {
        return ['id' => $id, 'UserType' => $type, 'CompanyName' => 'TestCo', 'CompanyColor' => '#f93627', 'CompID' => 'TC'];
    }

    private function dm(string $from, string $to, string $text, string $kind = 'text'): void
    {
        $res = msg_send_dm(self::db(), $from, $to, $text, 3, $kind);
        $this->assertTrue($res['ok'], $res['error'] ?? '');
    }

    /** Ben's group with Ada and Cora; Cora says something. */
    private function groupWithNews(): int
    {
        $g = grp_create(self::db(), self::B, 'Ops Team', [self::ADMIN, self::C], 3);
        $this->assertTrue($g['ok'], $g['error'] ?? '');
        $this->assertTrue(grp_send(self::db(), $g['id'], self::C, 'Standup moved to 10')['ok']);
        return $g['id'];
    }

    private function checkIn(string $who, ?string $hs): array
    {
        $get = ['action' => 'incoming'];
        if ($hs !== null) { $get['hs'] = $hs; }
        $res = $this->request('query/Query-calls.php', $get, [], $this->as($who));
        $this->assertNoPhpErrors($res['body']);
        $json = json_decode($res['body'], true);
        $this->assertIsArray($json, "not JSON:\n" . $res['body']);
        return $json;
    }

    // ------------------------------------------------------------ which heads

    public function testNothingUnreadMeansNoHeads(): void
    {
        $this->assertSame(['sig' => '0', 'total' => 0, 'heads' => []], mh_state(self::db(), self::ADMIN));
    }

    public function testUnreadChatsAndGroupsNewestFirstWithPreview(): void
    {
        $this->dm(self::EMP, self::ADMIN, 'Hi boss');
        $this->dm(self::EMP, self::ADMIN, "Leave   tomorrow?\nThanks");
        $gid = $this->groupWithNews();

        $s = mh_state(self::db(), self::ADMIN);

        $this->assertSame(2, $s['total']);
        [$group, $juan] = $s['heads'];
        $this->assertSame('grp:' . $gid, $group['key']);
        $this->assertTrue($group['group']);
        $this->assertSame('Ops Team', $group['name']);
        $this->assertSame('Cora', $group['from']);
        $this->assertSame('Standup moved to 10', $group['preview']);

        $this->assertSame(self::EMP, $juan['key']);
        $this->assertFalse($juan['group']);
        $this->assertSame('Juan Dela Cruz', $juan['name']);
        $this->assertSame(2, $juan['count']);
        $this->assertSame('Leave tomorrow? Thanks', $juan['preview']);
        $this->assertGreaterThan($juan['last'], $group['last']);
    }

    public function testMyOwnMessagesNeverMakeAHead(): void
    {
        $this->dm(self::EMP, self::ADMIN, 'Hi boss');
        $this->groupWithNews();

        $this->assertSame(0, mh_state(self::db(), self::EMP)['total']);
        $this->assertSame(0, mh_state(self::db(), self::C)['total']);   // Cora wrote the group's only message
        $this->assertSame(1, mh_state(self::db(), self::B)['total']);   // ...which is news to Ben
    }

    public function testReadingAConversationRemovesItsHead(): void
    {
        $this->dm(self::EMP, self::ADMIN, 'Hi boss');
        $gid = $this->groupWithNews();

        msg_mark_read(self::db(), self::ADMIN, self::EMP);
        $this->assertSame(['grp:' . $gid], array_column(mh_state(self::db(), self::ADMIN)['heads'], 'key'));

        grp_mark_read(self::db(), $gid, self::ADMIN);
        $this->assertSame(0, mh_state(self::db(), self::ADMIN)['total']);
    }

    public function testCountMatchesTheEnvelopeBadge(): void
    {
        $this->dm(self::EMP, self::ADMIN, 'one');
        $this->dm(self::B, self::ADMIN, 'two');
        $this->groupWithNews();

        $this->assertSame(msg_unread_threads(self::db(), self::ADMIN), mh_state(self::db(), self::ADMIN)['total']);
    }

    public function testPreviewsAreOneShortLine(): void
    {
        $this->assertSame('Sent a GIF', mh_preview('gif', '{"f":"wave.gif"}'));
        $long = mh_preview('text', str_repeat('word ', 40));
        $this->assertSame(90, mb_strlen($long));
        $this->assertStringEndsWith('…', $long);
    }

    // ------------------------------------------------------------ the check-in

    public function testDetailsAreOnlySentWhenSomethingChanged(): void
    {
        $this->dm(self::EMP, self::ADMIN, 'Hi boss');
        $first = mh_state(self::db(), self::ADMIN);

        $same = mh_state(self::db(), self::ADMIN, $first['sig']);
        $this->assertSame($first['sig'], $same['sig']);
        $this->assertNull($same['heads']);

        $this->dm(self::EMP, self::ADMIN, 'Still there?');
        $next = mh_state(self::db(), self::ADMIN, $first['sig']);
        $this->assertNotSame($first['sig'], $next['sig']);
        $this->assertSame('Still there?', $next['heads'][0]['preview']);
    }

    public function testCheckInCarriesHeadsOnlyWhenThePageAsks(): void
    {
        $this->dm(self::EMP, self::ADMIN, 'Hi boss');

        $plain = $this->checkIn(self::ADMIN, null);
        $this->assertSame(1, $plain['unread']);
        $this->assertArrayNotHasKey('heads', $plain);

        $asked = $this->checkIn(self::ADMIN, '');
        $this->assertSame(1, $asked['unread']);
        $this->assertSame('Juan Dela Cruz', $asked['heads'][0]['name']);

        $again = $this->checkIn(self::ADMIN, $asked['hs']);
        $this->assertSame($asked['hs'], $again['hs']);
        $this->assertArrayNotHasKey('heads', $again);
    }

    // ------------------------------------------------------------ pages

    public function testHeadsLoadOnEveryPageButMessages(): void
    {
        $this->dm(self::EMP, self::ADMIN, 'Hi boss');

        $body = $this->request('idcard.php', [], [], $this->as(self::ADMIN, 1))['body'];
        $this->assertNoPhpErrors($body);
        $this->assertStringContainsString('assets/js/wedo-msg-heads.js', $body);
        // loaded before the call widget, whose check-in feeds it
        $this->assertLessThan(strpos($body, 'assets/js/wedo-call.js'), strpos($body, 'window.WD_HEADS'));
        // first paint already knows the unread conversation
        $this->assertMatchesRegularExpression('/window\.WD_HEADS = \{.*"total":1.*"name":"Juan Dela Cruz"/', $body);

        // older pages (includes/header.php) get them too
        // (the old header has warnings of its own against the test fixture; only ours must be clean)
        $body = $this->request('PersonalInfo.php', [], [], $this->as(self::ADMIN, 1))['body'];
        $this->assertDoesNotMatchRegularExpression('/(Warning|Notice|Fatal error|Uncaught)\b.*?msg-heads/i', $body);
        $this->assertLessThan(strpos($body, 'assets/js/wedo-call.js'), strpos($body, 'window.WD_HEADS'));

        $body = $this->request('messages.php', [], [], $this->as(self::ADMIN, 1))['body'];
        $this->assertNoPhpErrors($body);
        $this->assertStringNotContainsString('window.WD_HEADS', $body);
        $this->assertStringContainsString('assets/js/wedo-call.js', $body);
    }
}
