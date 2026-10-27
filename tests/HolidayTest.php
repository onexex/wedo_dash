<?php
namespace Wedo\Tests;

use Wedo\Tests\Support\AppTestCase;

/**
 * Maintenance > Holidays: holders of the `hldy` right see the answers (today,
 * next, this year), ask about a date, filter the list and add / edit / delete.
 *
 *   maintenance.php?holiday       the page (includes/holiday.php; redirects away without the right)
 *   query/holiday-action.php      add / update / delete (JSON; 401 / 403 / 400 / 404)
 *   includes/holiday-lib.php      who may, the answers, validation, the writes
 */
final class HolidayTest extends AppTestCase
{
    private const TOKEN = 'holiday-test-token';

    private function as(string $id): array
    {
        return ['id' => $id, 'UserType' => $id === self::ADMIN ? 1 : 3, 'CompanyName' => 'TestCo', 'CompanyColor' => '#f93627',
                'CompID' => 'TC', 'hld_csrf' => self::TOKEN];
    }

    private function grant(string $emp): void
    {
        self::db()->prepare("UPDATE accessrights SET hldy = 2 WHERE EmpID = ?")->execute([$emp]);
    }

    private function page(?array $session): array
    {
        return $this->request('maintenance.php', ['holiday' => ''], [], $session);
    }

    private function act(?array $session, array $post): array
    {
        $res = $this->request('query/holiday-action.php', [], $post + ['token' => self::TOKEN], $session);
        $res['json'] = json_decode($res['body'], true);
        return $res;
    }

    private function seed(string $date, string $desc, int $type = 1, string $comp = 'TC'): int
    {
        $db = self::db();
        $db->prepare("INSERT INTO holidays (Hdate, Htype, Hdescription, HCompID, HOffsetEmpID) VALUES (?, ?, ?, ?, 'seed')")
           ->execute([$date, $type, $desc, $comp]);
        return (int) $db->lastInsertId();
    }

    private function day(int $offset): string
    {
        return (new \DateTimeImmutable('today', new \DateTimeZone('Asia/Manila')))->modify(($offset >= 0 ? '+' : '') . "$offset days")->format('Y-m-d');
    }

    protected function setUp(): void
    {
        parent::setUp();
        self::db()->exec("DELETE FROM holidays");
    }

    public function testRefusedWhenNotSignedInOrWithoutTheRight(): void
    {
        $this->assertSame(302, $this->page(null)['status']);
        $this->assertSame(401, $this->act(null, ['act' => 'add'])['status']);

        foreach ([self::ADMIN, self::EMP] as $who) {   // a super user without the right: still no
            $this->assertSame(302, $this->page($this->as($who))['status'], $who);
            $this->assertSame(403, $this->act($this->as($who), ['act' => 'add', 'hldate' => '2030-01-01', 'desc' => 'X', 'hldtype' => 1])['status'], $who);
        }
        $this->assertSame(0, (int) self::db()->query("SELECT COUNT(*) FROM holidays")->fetchColumn());

        // and the menu doesn't offer it
        $menu = $this->request('idcard.php', [], [], $this->as(self::ADMIN))['body'];
        $this->assertNull($this->dom($menu)->query("//a[@href='maintenance?holiday']")->item(0));
    }

    public function testThePageAnswersTodayNextAndThisYear(): void
    {
        $this->grant(self::ADMIN);
        $year = (int) substr($this->day(0), 0, 4);
        $this->seed($this->day(0), 'Founding Day', 2);
        $this->seed($this->day(10), 'Heroes Day', 1);
        $this->seed($this->day(-30), 'Past Day', 1);
        $this->seed(($year - 1) . '-12-25', 'Christmas Day', 1);   // last year: not in this year's counts
        $this->seed($this->day(5), 'Other Co Day', 1, 'ZZ');      // another company: never shown

        $res = $this->page($this->as(self::ADMIN));
        $this->assertSame(200, $res['status']);
        $this->assertNoPhpErrors($res['body']);
        $x = $this->dom($res['body']);
        $this->assertNotNull($x->query("//div[@data-sec='maintenance']//a[@href='maintenance?holiday' and contains(@class,'is-active')]")->item(0), 'menu highlights Holidays');

        $today = $x->query("//*[@id='hldToday']")->item(0)->textContent;
        $this->assertStringContainsString('Founding Day', $today);
        $this->assertStringContainsString('Special', $today);

        $next = $x->query("//*[@id='hldNext']")->item(0)->textContent;
        $this->assertStringContainsString('Heroes Day', $next);
        $this->assertStringContainsString('in 10 days', $next);

        // this year: the three TC rows dated this year (today, +10, -30 — unless -30 or +10 crossed a year boundary)
        $thisYear = array_filter([$this->day(0), $this->day(10), $this->day(-30)], fn($d) => (int) substr($d, 0, 4) === $year);
        $this->assertStringContainsString((string) count($thisYear), $x->query("//*[@id='hldYear']//div[contains(@class,'wd-stat__value')]")->item(0)->textContent);
        $left = count(array_filter($thisYear, fn($d) => $d > $this->day(0)));
        $this->assertSame((string) $left, trim($x->query("//*[@id='hldLeft']//div[contains(@class,'wd-stat__value')]")->item(0)->textContent));

        // the list: every TC holiday rendered with the attributes the filters use; the other company's never
        $rows = $x->query("//table[@id='hldTable']/tbody/tr");
        $this->assertSame(4, $rows->length);
        $this->assertStringNotContainsString('Other Co Day', $res['body']);
        $first = $rows->item(0);
        $this->assertSame(($year - 1) . '-12-25', $first->getAttribute('data-date'), 'oldest first');
        $this->assertSame((string) ($year - 1), $first->getAttribute('data-year'));
        $this->assertSame('1', $first->getAttribute('data-type'));
        $this->assertSame('christmas day', $first->getAttribute('data-q'));
        $this->assertNotNull($x->query("//table[@id='hldTable']/tbody/tr[contains(@class,'is-today')]")->item(0));
        $this->assertNotNull($x->query("//table[@id='hldTable']/tbody/tr[contains(@class,'is-past')]")->item(0));

        // filters offered: this year selected, next year and every year with a holiday listed
        $this->assertSame((string) $year, $this->selectedOf($res['body'], 'hldFYear'));
        foreach ([$year - 1, $year, $year + 1] as $y) {
            $this->assertNotNull($x->query("//select[@id='hldFYear']/option[@value='$y']")->item(0), "year $y");
        }
        $this->assertNotNull($x->query("//*[@id='hldFType']")->item(0));
        $this->assertNotNull($x->query("//*[@id='hldFQ']")->item(0));
        $this->assertNotNull($x->query("//*[@id='hldFWhen']/button[@data-when='up']")->item(0));
        $this->assertNotNull($x->query("//input[@id='hldAskDate']")->item(0), 'ask-a-date box');
        $this->assertSame(self::TOKEN, $x->query("//form[@id='hldForm']//input[@name='token']")->item(0)->getAttribute('value'));
        // the date box's data: the TC rows only
        $this->assertSame(1, preg_match('/window\.HLD = \{ rows: (\[.*?\]),/', $res['body'], $m));
        $this->assertCount(4, json_decode($m[1], true));
    }

    public function testEmptyAndNoHolidayToday(): void
    {
        $this->grant(self::ADMIN);
        $res = $this->page($this->as(self::ADMIN));
        $this->assertSame(200, $res['status']);
        $this->assertNoPhpErrors($res['body']);
        $this->assertStringContainsString('Not a holiday', $res['body']);
        $this->assertStringContainsString('None scheduled', $res['body']);
        $this->assertStringContainsString('No holidays yet', $res['body']);
    }

    public function testAddsValidatesAndRefusesADuplicateDate(): void
    {
        $this->grant(self::ADMIN);
        $me = $this->as(self::ADMIN);

        $r = $this->act($me, ['act' => 'add', 'hldate' => '2030-06-12', 'desc' => '  Independence   Day ', 'hldtype' => '1']);
        $this->assertSame(200, $r['status'], $r['body']);
        $this->assertTrue($r['json']['ok']);
        $row = $this->row("SELECT * FROM holidays WHERE SID = ?", [$r['json']['id']]);
        $this->assertSame(['2030-06-12', '1', 'Independence Day', 'TC', self::ADMIN],
                          [$row['Hdate'], $row['Htype'], $row['Hdescription'], $row['HCompID'], $row['HOffsetEmpID']]);
        $this->assertSame(1, (int) $this->row("SELECT COUNT(*) AS n FROM dars WHERE EmpActivity LIKE 'Holiday added: Independence Day%'")['n']);

        $bad = [
            'same date again'   => ['hldate' => '2030-06-12', 'desc' => 'Another', 'hldtype' => '2'],
            'no date'           => ['hldate' => '', 'desc' => 'X', 'hldtype' => '1'],
            'not a date'        => ['hldate' => '2030-02-30', 'desc' => 'X', 'hldtype' => '1'],
            'no name'           => ['hldate' => '2030-06-13', 'desc' => '   ', 'hldtype' => '1'],
            'name too long'     => ['hldate' => '2030-06-13', 'desc' => str_repeat('x', 51), 'hldtype' => '1'],
            'unknown type'      => ['hldate' => '2030-06-13', 'desc' => 'X', 'hldtype' => '3'],
        ];
        foreach ($bad as $why => $post) {
            $r = $this->act($me, ['act' => 'add'] + $post);
            $this->assertSame(400, $r['status'], $why);
            $this->assertFalse($r['json']['ok'], $why);
            $this->assertNotSame('', (string) $r['json']['msg'], $why);
        }
        $this->assertStringContainsString('Independence Day', $this->act($me, ['act' => 'add'] + $bad['same date again'])['json']['msg']);
        $this->assertSame(1, (int) self::db()->query("SELECT COUNT(*) FROM holidays")->fetchColumn());

        // forged / wrong method
        $this->assertSame(400, $this->request('query/holiday-action.php', [], ['token' => 'nope', 'act' => 'add', 'hldate' => '2030-01-01', 'desc' => 'X', 'hldtype' => 1], $me)['status']);
        $this->assertSame(400, $this->request('query/holiday-action.php', ['token' => self::TOKEN, 'act' => 'add'], [], $me)['status']);
    }

    public function testUpdatesAndDeletesOnlyItsOwnCompanysRows(): void
    {
        $this->grant(self::ADMIN);
        $me = $this->as(self::ADMIN);
        $a = $this->seed('2030-06-12', 'Independence Day', 1);
        $b = $this->seed('2030-08-21', 'Ninoy Aquino Day', 2);
        $z = $this->seed('2030-06-12', 'Other Co', 1, 'ZZ');

        // edit: keep the date, change name + type
        $r = $this->act($me, ['act' => 'update', 'id' => $a, 'hldate' => '2030-06-12', 'desc' => 'Independence Day (PH)', 'hldtype' => '2']);
        $this->assertSame(200, $r['status'], $r['body']);
        $row = $this->row("SELECT Hdate, Htype, Hdescription FROM holidays WHERE SID = ?", [$a]);
        $this->assertSame(['2030-06-12', '2', 'Independence Day (PH)'], array_values($row));

        // edit: move onto the other holiday's date -> refused, nothing changed
        $r = $this->act($me, ['act' => 'update', 'id' => $a, 'hldate' => '2030-08-21', 'desc' => 'Moved', 'hldtype' => '1']);
        $this->assertSame(400, $r['status']);
        $this->assertStringContainsString('Ninoy Aquino Day', $r['json']['msg']);
        $this->assertSame('2030-06-12', $this->row("SELECT Hdate FROM holidays WHERE SID = ?", [$a])['Hdate']);

        // edit: move to a free date
        $this->assertSame(200, $this->act($me, ['act' => 'update', 'id' => $a, 'hldate' => '2030-06-13', 'desc' => 'Moved', 'hldtype' => '1'])['status']);
        $this->assertSame('2030-06-13', $this->row("SELECT Hdate FROM holidays WHERE SID = ?", [$a])['Hdate']);

        // another company's row: not ours to edit or delete
        $this->assertSame(404, $this->act($me, ['act' => 'update', 'id' => $z, 'hldate' => '2030-01-01', 'desc' => 'Hijack', 'hldtype' => '1'])['status']);
        $this->assertSame(404, $this->act($me, ['act' => 'delete', 'id' => $z])['status']);
        $this->assertSame('Other Co', $this->row("SELECT Hdescription FROM holidays WHERE SID = ?", [$z])['Hdescription']);

        // delete ours
        $r = $this->act($me, ['act' => 'delete', 'id' => $b]);
        $this->assertSame(200, $r['status']);
        $this->assertTrue($r['json']['ok']);
        $this->assertNull($this->row("SELECT SID FROM holidays WHERE SID = ?", [$b]));
        $this->assertSame(404, $this->act($me, ['act' => 'delete', 'id' => $b])['status'], 'already gone');
        $this->assertSame(400, $this->act($me, ['act' => 'delete'])['status'], 'no id');
        $this->assertSame(400, $this->act($me, ['act' => 'what'])['status']);
        $this->assertSame(1, (int) $this->row("SELECT COUNT(*) AS n FROM dars WHERE EmpActivity LIKE 'Holiday deleted: Ninoy Aquino Day%'")['n']);
    }

    /** It can be switched on like any other right (Access Rights > Maintenance). */
    public function testGrantedFromAccessRights(): void
    {
        $this->assertSame(200, $this->request('updateaccess.php', ['empid' => self::EMP, 'mponoff' => 'ON', 'term' => 'hldy'], [], $this->asAdmin())['status']);
        $this->assertSame(2, (int) $this->row("SELECT hldy FROM accessrights WHERE EmpID = ?", [self::EMP])['hldy']);
        $res = $this->page($this->as(self::EMP));
        $this->assertSame(200, $res['status']);
        $this->assertNotNull($this->dom($res['body'])->query("//a[@href='maintenance?holiday']")->item(0), 'now in the menu');
    }
}
