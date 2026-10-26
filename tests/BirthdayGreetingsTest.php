<?php
namespace Wedo\Tests;

use Wedo\Tests\Support\AppTestCase;

/**
 * Corner birthday greetings (includes/corner-lib.php wd_post_birthday_greetings): the first
 * sign-in of the day posts one "Happy Birthday" announcement per active employee born that day,
 * once — whether that sign-in is the password form or a page opened with a remembered session.
 */
final class BirthdayGreetingsTest extends AppTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        require_once dirname(__DIR__) . '/includes/corner-lib.php';
        self::db()->exec("DELETE FROM tblsyslog");   // the fixture marks today as done; these tests start fresh
    }

    private function bornOn(string $empId, string $dob, int $status = 1): void
    {
        self::db()->prepare("UPDATE empprofiles SET EmpDOB = ? WHERE EmpID = ?")->execute([$dob, $empId]);
        self::db()->prepare("UPDATE employees SET EmpStatusID = ? WHERE EmpID = ?")->execute([$status, $empId]);
    }

    private function greetings(): array
    {
        return self::db()->query("SELECT EmpID, Title, ADesc FROM announcements ORDER BY aid")->fetchAll();
    }

    private function today(): \DateTime
    {
        return new \DateTime('now', new \DateTimeZone('Asia/Manila'));
    }

    public function testActiveEmployeeIsGreetedOnceOnTheirBirthday(): void
    {
        $this->bornOn(self::EMP, '1990-10-26');

        $this->assertSame(1, wd_post_birthday_greetings(self::db(), '2026-10-26'));
        $this->assertSame(0, wd_post_birthday_greetings(self::db(), '2026-10-26'), 'the day is already handled');

        $rows = $this->greetings();
        $this->assertCount(1, $rows);
        $this->assertSame('admin', $rows[0]['EmpID']);
        $this->assertSame('Announcement', $rows[0]['Title']);
        $this->assertSame("Happy Birthday Juan Dela Cruz <i class='fa fa-birthday-cake'></i>", $rows[0]['ADesc']);
        $this->assertSame('1', (string) self::db()->query("SELECT COUNT(*) FROM tblsyslog WHERE datecheck = '2026-10-26'")->fetchColumn());
    }

    public function testInactiveEmployeeIsNotGreeted(): void
    {
        $this->bornOn(self::EMP, '1990-10-26', 2);

        $this->assertSame(0, wd_post_birthday_greetings(self::db(), '2026-10-26'));
        $this->assertSame([], $this->greetings());
        $this->assertSame('1', (string) self::db()->query("SELECT COUNT(*) FROM tblsyslog WHERE datecheck = '2026-10-26'")->fetchColumn(),
            'a day without birthdays is still marked as handled');
    }

    public function testLeapDayBirthdayIsGreetedOnMarchFirstOfAnOrdinaryYear(): void
    {
        $this->bornOn(self::EMP, '1992-02-29');

        $this->assertSame(1, wd_post_birthday_greetings(self::db(), '2027-03-01'));
        $this->assertSame(0, wd_post_birthday_greetings(self::db(), '2028-03-01'), 'leap year: not on Mar 1…');
        $this->assertSame(1, wd_post_birthday_greetings(self::db(), '2028-02-29'), '…but on the day itself');
    }

    public function testResignedLeapDayBirthdayIsNotGreetedOnMarchFirst(): void
    {
        // the old query's OR wasn't bracketed, so this one slipped past the active-only check
        $this->bornOn(self::EMP, '1992-02-29', 2);

        $this->assertSame(0, wd_post_birthday_greetings(self::db(), '2027-03-01'));
        $this->assertSame([], $this->greetings());
    }

    public function testPasswordSignInPostsGreetingAndAnswersOnly900(): void
    {
        $this->bornOn(self::EMP, '1990-' . $this->today()->format('m-d'));
        self::db()->prepare("UPDATE empdetails SET EmpPW = ? WHERE EmpID = ?")->execute([password_hash('secret-pass', PASSWORD_DEFAULT), self::EMP]);

        $res = $this->request('query/query-login.php', [], ['uname' => 'jdelacruz', 'pass' => 'secret-pass']);

        $this->assertSame('900', trim($res['body']), 'no employee id echoed in front of the answer');
        $this->assertCount(1, $this->greetings());
    }

    public function testOpeningAPageWithARememberedSessionPostsGreetingOnce(): void
    {
        $this->bornOn(self::EMP, '1990-' . $this->today()->format('m-d'));

        $res = $this->request('notifications.php', [], [], $this->asEmployee() + ['EmpISID' => self::ADMIN]);
        $this->assertSame(200, $res['status']);
        $this->assertNoPhpErrors($res['body'], 'notifications.php');
        $this->assertCount(1, $this->greetings());

        $this->request('notifications.php', [], [], $this->asEmployee() + ['EmpISID' => self::ADMIN]);
        $this->assertCount(1, $this->greetings(), 'not posted twice');
    }
}
