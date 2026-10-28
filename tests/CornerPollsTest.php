<?php
namespace Wedo\Tests;

use Wedo\Tests\Support\AppTestCase;

/**
 * Corner polls: corner.php (the Polls card + "New poll" dialog),
 * query/corner-polls.php (JSON API), includes/corner-polls-lib.php.
 * Posters (wd_corner_can_post) start polls; every employee votes.
 */
final class CornerPollsTest extends AppTestCase
{
    private const TOKEN = 'poll-test-token';

    private function admin(): array    { return $this->asAdmin() + ['poll_csrf' => self::TOKEN]; }
    private function employee(): array { return $this->asEmployee() + ['poll_csrf' => self::TOKEN]; }

    private function api(string $action, array $post, array $session, bool $withToken = true): array
    {
        $post = ['action' => $action] + $post + ($withToken ? ['token' => self::TOKEN] : []);
        $res = $this->request('query/corner-polls.php', [], $post, $session);
        $this->assertNoPhpErrors($res['body'], $action);
        return ['status' => $res['status'], 'json' => json_decode($res['body'], true) ?? []];
    }

    private function create(array $over = []): int
    {
        $r = $this->api('create', $over + ['question' => 'Where do we eat on Friday?', 'options' => ['🍕 Pizza', '🍜 Ramen', 'Salad'],
                                           'closes' => 'none'], $this->admin());
        $this->assertSame(200, $r['status'], json_encode($r['json']));
        return (int) $r['json']['id'];
    }

    private function optionId(int $poll, string $label): int
    {
        return (int) $this->row("SELECT OptionID FROM corner_poll_options WHERE PollID = ? AND Label = ?", [$poll, $label])['OptionID'];
    }

    private function myVotes(int $poll, string $emp): array
    {
        $st = self::db()->prepare("SELECT o.Label FROM corner_poll_votes v JOIN corner_poll_options o ON o.OptionID = v.OptionID
                                   WHERE v.PollID = ? AND v.EmpID = ? ORDER BY o.Label");
        $st->execute([$poll, $emp]);
        return $st->fetchAll(\PDO::FETCH_COLUMN);
    }

    // ------------------------------------------------------------ page

    public function testPostersGetTheNewPollDialogEmployeesJustTheCard(): void
    {
        $admin = $this->request('corner.php', [], [], $this->asAdmin())['body'];
        $this->assertNoPhpErrors($admin);
        $this->assertStringContainsString('id="cornerPolls"', $admin);
        $this->assertStringContainsString('id="pollModal"', $admin);

        $emp = $this->request('corner.php', [], [], $this->asEmployee())['body'];
        $this->assertNoPhpErrors($emp);
        $this->assertStringContainsString('id="cornerPolls"', $emp);
        $this->assertStringNotContainsString('id="pollModal"', $emp);
    }

    public function testOpenPollShowsOnTheCornerWithEmojiIntact(): void
    {
        $this->create();
        $html = $this->request('corner.php', [], [], $this->asEmployee())['body'];
        $this->assertStringContainsString('Where do we eat on Friday?', $html);
        $this->assertStringContainsString('🍕 Pizza', $html);
        $this->assertStringContainsString('Vote to see the results', $html);
    }

    // ------------------------------------------------------------ create

    public function testOnlyPostersCreateAndTheTokenIsChecked(): void
    {
        $poll = ['question' => 'Lunch?', 'options' => ['A', 'B'], 'closes' => 'none'];
        $this->assertSame(403, $this->api('create', $poll, $this->employee())['status']);
        $this->assertSame(403, $this->api('create', $poll, $this->admin(), false)['status']);
        $this->assertSame(0, (int) self::db()->query("SELECT COUNT(*) FROM corner_polls")->fetchColumn());
    }

    public function testCreateNeedsAQuestionAndTwoDifferentOptions(): void
    {
        $r = $this->api('create', ['question' => 'Lunch?', 'options' => ['Pizza', ' pizza '], 'closes' => 'none'], $this->admin());
        $this->assertSame(422, $r['status']);
        $this->assertStringContainsString('2 different options', $r['json']['msg']);

        $this->assertSame(422, $this->api('create', ['question' => 'Hi', 'options' => ['A', 'B']], $this->admin())['status']);
        $past = $this->api('create', ['question' => 'Lunch?', 'options' => ['A', 'B'], 'closes' => 'custom', 'closes_at' => '2020-01-01T10:00'], $this->admin());
        $this->assertSame(422, $past['status']);
    }

    public function testClosingPresetSetsTheEndTime(): void
    {
        $id = $this->create(['closes' => '3d']);
        date_default_timezone_set('Asia/Manila');   // the app stores Manila time
        $closes = strtotime($this->row("SELECT ClosesAt FROM corner_polls WHERE PollID = ?", [$id])['ClosesAt']);
        $this->assertEqualsWithDelta(strtotime('+3 days'), $closes, 120);
    }

    // ------------------------------------------------------------ voting

    public function testSingleChoiceSwitchesAndTappingAgainTakesItBack(): void
    {
        $id = $this->create();
        $pizza = $this->optionId($id, '🍕 Pizza');
        $ramen = $this->optionId($id, '🍜 Ramen');

        $r = $this->api('vote', ['id' => $id, 'option' => $pizza], $this->employee());
        $this->assertSame(200, $r['status']);
        $this->assertSame(['🍕 Pizza'], $this->myVotes($id, self::EMP));
        $this->assertStringContainsString('100%', $r['json']['html'], 'results show once you voted');

        $this->api('vote', ['id' => $id, 'option' => $ramen], $this->employee());
        $this->assertSame(['🍜 Ramen'], $this->myVotes($id, self::EMP));

        $this->api('vote', ['id' => $id, 'option' => $ramen], $this->employee());
        $this->assertSame([], $this->myVotes($id, self::EMP));
    }

    public function testMultipleChoiceKeepsEveryPick(): void
    {
        $id = $this->create(['multi' => 1]);
        $this->api('vote', ['id' => $id, 'option' => $this->optionId($id, 'Salad')], $this->employee());
        $this->api('vote', ['id' => $id, 'option' => $this->optionId($id, '🍜 Ramen')], $this->employee());
        $this->assertSame(['Salad', '🍜 Ramen'], $this->myVotes($id, self::EMP));
    }

    public function testAnOptionFromAnotherPollIsRefused(): void
    {
        $a = $this->create();
        $b = $this->create(['question' => 'Another poll?']);
        $r = $this->api('vote', ['id' => $a, 'option' => $this->optionId($b, 'Salad')], $this->employee());
        $this->assertSame(409, $r['status']);
        $this->assertSame([], $this->myVotes($a, self::EMP));
    }

    public function testVoterNamesShowUnlessThePollIsAnonymous(): void
    {
        $open = $this->create();
        $anon = $this->create(['question' => 'Rate the outing?', 'anonymous' => 1]);
        $this->api('vote', ['id' => $open, 'option' => $this->optionId($open, 'Salad')], $this->employee());
        $this->api('vote', ['id' => $anon, 'option' => $this->optionId($anon, 'Salad')], $this->employee());

        $cards = $this->request('query/corner-polls.php', ['action' => 'cards', 'ids' => "$open,$anon"], [], $this->admin());
        $json = json_decode($cards['body'], true);
        $this->assertStringContainsString('Juan Dela Cruz', $json['cards'][$open]);
        $this->assertStringNotContainsString('Juan', $json['cards'][$anon]);
        $this->assertStringContainsString('1 person voted', $json['cards'][$anon]);
    }

    // ------------------------------------------------------------ suggestions

    public function testSuggestionsOnlyWhenAllowedAndTheyCarryYourVote(): void
    {
        $closed = $this->create();
        $this->assertSame(409, $this->api('add_option', ['id' => $closed, 'label' => 'Tacos'], $this->employee())['status']);

        $id = $this->create(['question' => 'Team outing?', 'allow_add' => 1]);
        $r = $this->api('add_option', ['id' => $id, 'label' => '  Tacos  '], $this->employee());
        $this->assertSame(200, $r['status']);
        $this->assertSame(['Tacos'], $this->myVotes($id, self::EMP));
        $this->assertStringContainsString('suggested', $r['json']['html']);

        // the same idea in other words' casing votes for the existing option instead of adding a copy
        $this->api('add_option', ['id' => $id, 'label' => 'salad'], $this->employee());
        $this->assertSame(['Salad'], $this->myVotes($id, self::EMP));
        $this->assertSame(4, (int) $this->row("SELECT COUNT(*) AS n FROM corner_poll_options WHERE PollID = ?", [$id])['n']);
    }

    // ------------------------------------------------------------ close / delete

    public function testOnlyTheAuthorClosesAndAClosedPollTakesNoVotes(): void
    {
        $id = $this->create();
        $salad = $this->optionId($id, 'Salad');
        $this->assertSame(403, $this->api('close', ['id' => $id], $this->employee())['status']);

        $this->assertSame(200, $this->api('close', ['id' => $id], $this->admin())['status']);
        $r = $this->api('vote', ['id' => $id, 'option' => $salad], $this->employee());
        $this->assertSame(409, $r['status']);
        $this->assertStringContainsString('closed', $r['json']['msg']);
        $this->assertStringContainsString('Closed', $r['json']['html']);
    }

    public function testAPollPastItsEndTimeIsClosedAndListedUnderRecentlyClosed(): void
    {
        $id = $this->create();
        date_default_timezone_set('Asia/Manila');
        self::db()->prepare("UPDATE corner_polls SET ClosesAt = ? WHERE PollID = ?")->execute([date('Y-m-d H:i:s', strtotime('-1 hour')), $id]);
        $this->assertSame(409, $this->api('vote', ['id' => $id, 'option' => $this->optionId($id, 'Salad')], $this->employee())['status']);
        $html = $this->request('corner.php', [], [], $this->asEmployee())['body'];
        $this->assertStringContainsString('Recently closed (1)', $html);
    }

    public function testDeleteRemovesThePollItsOptionsAndVotes(): void
    {
        $id = $this->create();
        $this->api('vote', ['id' => $id, 'option' => $this->optionId($id, 'Salad')], $this->employee());
        $this->assertSame(403, $this->api('delete', ['id' => $id], $this->employee())['status']);

        $r = $this->api('delete', ['id' => $id], $this->admin());
        $this->assertSame(200, $r['status']);
        $this->assertSame('', $r['json']['html']);
        foreach (['corner_polls', 'corner_poll_options', 'corner_poll_votes'] as $t) {
            $this->assertSame(0, (int) self::db()->query("SELECT COUNT(*) FROM $t")->fetchColumn(), $t);
        }
    }

    public function testSignedOutGets401(): void
    {
        $res = $this->request('query/corner-polls.php', [], ['action' => 'vote', 'id' => 1, 'option' => 1]);
        $this->assertSame(401, $res['status']);
    }
}
