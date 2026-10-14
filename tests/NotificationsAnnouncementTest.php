<?php
namespace Wedo\Tests;

use Wedo\Tests\Support\AppTestCase;

/**
 * Corner announcements are pushed to phones, so they are listed on the Notifications page too
 * (newest first with the filings, last 30 days, not my own) and count on the bell until it is opened.
 */
final class NotificationsAnnouncementTest extends AppTestCase
{
    private function announce(string $author, string $text, string $when): void
    {
        self::db()->prepare("INSERT INTO announcements (EmpID, Title, ADesc, ADate) VALUES (?, 'Announcement', ?, ?)")
            ->execute([$author, $text, $when]);
    }

    public function testRecentAnnouncementIsListedAndOpensTheCorner(): void
    {
        $this->announce(self::ADMIN, 'Office closed on <b>Friday</b> for the holiday', date('Y-m-d H:i:s', time() - 3600));

        $res = $this->request('notifications.php', [], [], $this->asEmployee() + ['EmpISID' => self::ADMIN]);
        $this->assertSame(200, $res['status']);
        $this->assertNoPhpErrors($res['body'], 'notifications.php');
        $this->assertStringContainsString('Announcement from Ada Admin', $res['body']);
        $this->assertStringContainsString('Office closed on Friday for the holiday', $res['body'], 'tags stripped');
        $this->assertStringContainsString('href="corner"', $res['body']);
    }

    public function testOldAndOwnAnnouncementsAreNotListed(): void
    {
        $this->announce(self::ADMIN, 'Last quarter town hall', date('Y-m-d H:i:s', time() - 40 * 86400));
        $this->announce(self::EMP, 'My own post', date('Y-m-d H:i:s', time() - 60));

        $body = $this->request('notifications.php', [], [], $this->asEmployee() + ['EmpISID' => self::ADMIN])['body'];
        $this->assertStringNotContainsString('Last quarter town hall', $body);
        $this->assertStringNotContainsString('My own post', $body);
    }

    public function testNewAnnouncementLightsTheBell(): void
    {
        $quiet = $this->request('e201.php', [], [], $this->asEmployee())['body'];
        $this->assertStringContainsString('title="0 notification(s)"', $quiet);

        $this->announce(self::ADMIN, 'Payroll cut-off moved', date('Y-m-d H:i:s', time() - 60));
        $body = $this->request('e201.php', [], [], $this->asEmployee())['body'];
        $this->assertStringContainsString('title="1 notification(s)"', $body);
    }
}
