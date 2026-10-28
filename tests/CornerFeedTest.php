<?php
namespace Wedo\Tests;

use Wedo\Tests\Support\AppTestCase;

/**
 * The Corner page's announcements feed and Today strip: corner.php,
 * query/corner-feed.php (post / edit / delete / older posts) and the feed
 * helpers in includes/corner-lib.php.
 */
final class CornerFeedTest extends AppTestCase
{
    private const TOKEN = 'feed-test-token';

    private function admin(): array    { return $this->asAdmin() + ['corner_csrf' => self::TOKEN]; }
    private function employee(): array { return $this->asEmployee() + ['corner_csrf' => self::TOKEN]; }

    private function api(string $action, array $post, array $session, bool $withToken = true): array
    {
        $post = ['action' => $action] + $post + ($withToken ? ['token' => self::TOKEN] : []);
        $res = $this->request('query/corner-feed.php', [], $post, $session);
        $this->assertNoPhpErrors($res['body'], $action);
        return ['status' => $res['status'], 'json' => json_decode($res['body'], true) ?? []];
    }

    private function announce(string $by, string $text, ?string $at = null): int
    {
        date_default_timezone_set('Asia/Manila');
        self::db()->prepare("INSERT INTO announcements (EmpID, Title, ADesc, ADate) VALUES (?, 'Announcement', ?, ?)")
            ->execute([$by, $text, $at ?? date('Y-m-d H:i:s')]);
        return (int) self::db()->lastInsertId();
    }

    private function page(array $session): string
    {
        $res = $this->request('corner.php', [], [], $session);
        $this->assertSame(200, $res['status']);
        $this->assertNoPhpErrors($res['body']);
        return $res['body'];
    }

    // ------------------------------------------------------------ page

    public function testPostersGetTheComposerEmployeesDoNot(): void
    {
        $this->assertStringContainsString('id="cfCompose"', $this->page($this->asAdmin()));
        $this->assertStringNotContainsString('id="cfCompose"', $this->page($this->asEmployee()));
    }

    public function testUnseenPostsAreMarkedNewOnlyOnTheFirstVisit(): void
    {
        $this->announce(self::ADMIN, 'Town hall moved to Friday 3 PM.');
        $first = $this->page($this->asEmployee());
        $this->assertStringContainsString('Town hall moved to Friday 3 PM.', $first);
        $this->assertStringContainsString('class="cf__new"', $first);
        $this->assertStringNotContainsString('class="cf__new"', $this->page($this->asEmployee()), 'seen on the first visit');
    }

    public function testSameDayBirthdayPostsShareOneCard(): void
    {
        foreach (['Ana Reyes', 'Ben Cruz'] as $who) {
            $this->announce('admin', "Happy Birthday $who <i class='fa fa-birthday-cake'></i>");
        }
        self::db()->exec("INSERT INTO employees (EmpID, EmpFN, EmpLN, EmpMN, PosID, EmpStatusID) VALUES ('admin', 'admin', '', '', 10, 1)");
        $html = $this->page($this->asEmployee());
        $this->assertSame(1, substr_count($html, 'data-kind="bday"'));
        $this->assertStringContainsString('Ana Reyes', $html);
        $this->assertStringContainsString('Ben Cruz', $html);
    }

    public function testTodayStripShowsBirthdaysAndTheNextHoliday(): void
    {
        date_default_timezone_set('Asia/Manila');
        self::db()->prepare("UPDATE empprofiles SET EmpDOB = ? WHERE EmpID = ?")->execute([date('1990-m-d'), self::EMP]);
        self::db()->exec("DELETE FROM holidays");
        self::db()->prepare("INSERT INTO holidays (Hdate, Htype, Hdescription, HCompID, HOffsetEmpID) VALUES (?, '1', 'Founders Day', 'TC', '')")
            ->execute([date('Y-m-d', strtotime('+3 days'))]);

        $admin = $this->page($this->asAdmin());
        $this->assertStringContainsString('Juan Dela Cruz', $admin);
        $this->assertStringContainsString('messages?with=' . self::EMP, $admin);
        $this->assertStringContainsString('Founders Day', $admin);
        $this->assertStringContainsString('in 3 days', $admin);
        $this->assertStringContainsString('Happy birthday to you!', $this->page($this->asEmployee()));
        self::db()->exec("DELETE FROM holidays");
    }

    // ------------------------------------------------------------ post

    public function testOnlyPostersPostAndTheTokenIsChecked(): void
    {
        $this->assertSame(403, $this->api('post', ['body' => 'Hello everyone!'], $this->employee())['status']);
        $this->assertSame(403, $this->api('post', ['body' => 'Hello everyone!'], $this->admin(), false)['status']);
        $this->assertSame(422, $this->api('post', ['body' => 'Hi'], $this->admin())['status']);

        $r = $this->api('post', ['body' => "Office closes early today.\nSee you Monday!", 'title' => 'Early out'], $this->admin());
        $this->assertSame(200, $r['status']);
        $this->assertStringContainsString('Early out', $r['json']['html']);
        $row = $this->row("SELECT Title, ADesc FROM announcements WHERE EmpID = ?", [self::ADMIN]);
        $this->assertSame('Early out', $row['Title']);
    }

    public function testTheOldPostHandlerAlsoChecksWhoMayPost(): void
    {
        $res = $this->request('corner.php', ['addannoun' => ''], ['announ' => 'Sneaky post'], $this->asEmployee());
        $this->assertSame(403, $res['status']);
        $this->assertSame(0, (int) self::db()->query("SELECT COUNT(*) FROM announcements")->fetchColumn());
    }

    // ------------------------------------------------------------ edit / delete

    public function testOnlyTheAuthorEditsInBothTheApiAndTheOldHandler(): void
    {
        $aid = $this->announce(self::ADMIN, 'Original text here.');
        $this->assertSame(403, $this->api('edit', ['aid' => $aid, 'body' => 'Hacked text!'], $this->employee())['status']);
        $old = $this->request('corner.php', ['updateann' => $aid], ['announ' => 'Hacked text!'], $this->asEmployee());
        $this->assertSame(403, $old['status']);
        $this->assertSame('Original text here.', $this->row("SELECT ADesc FROM announcements WHERE aid = ?", [$aid])['ADesc']);

        $r = $this->api('edit', ['aid' => $aid, 'body' => 'Corrected text here.'], $this->admin());
        $this->assertSame(200, $r['status']);
        $this->assertStringContainsString('Corrected text here.', $r['json']['html']);
    }

    public function testDeleteRemovesThePostAndItsSeenMarks(): void
    {
        $aid = $this->announce(self::ADMIN, 'Wrong post, sorry.');
        $this->page($this->asEmployee());   // marks it seen
        $this->assertSame(403, $this->api('delete', ['aid' => $aid], $this->employee())['status']);
        $this->assertSame(200, $this->api('delete', ['aid' => $aid], $this->admin())['status']);
        $this->assertSame(0, (int) self::db()->query("SELECT COUNT(*) FROM announcements")->fetchColumn());
        $this->assertSame(0, (int) self::db()->query("SELECT COUNT(*) FROM annseen")->fetchColumn());
    }

    // ------------------------------------------------------------ paging

    public function testOlderPostsLoadAPageAtATime(): void
    {
        for ($i = 1; $i <= 20; $i++) { $this->announce(self::ADMIN, "Post number $i of twenty"); }
        $html = $this->page($this->asEmployee());
        $this->assertSame(15, substr_count($html, 'data-kind="news"'));
        $this->assertMatchesRegularExpression('/id="cfOlder" data-before="(\d+)"/', $html);
        preg_match('/id="cfOlder" data-before="(\d+)"/', $html, $m);

        $more = $this->request('query/corner-feed.php', ['action' => 'more', 'before' => $m[1]], [], $this->employee());
        $json = json_decode($more['body'], true);
        $this->assertSame(5, substr_count($json['html'], 'data-kind="news"'));
        $this->assertFalse($json['more']);
        $this->assertStringContainsString('Post number 1 of twenty', $json['html']);
    }
}
