<?php
namespace Wedo\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use Wedo\Tests\Support\AppTestCase;

/**
 * Login guards must stop the script: a logged-out request gets the redirect
 * to the login page and nothing else (no page body, no database changes).
 */
final class LoggedOutRedirectTest extends AppTestCase
{
    /** Scripts behind the standard login guard. */
    public static function guardedScripts(): array
    {
        $scripts = [
            // pages
            'ComDocData.php', 'EmpInfo.php', 'PersonalInfo.php', 'SendToOB.php', 'alas.php', 'attachement_13.php',
            'checkregister.php', 'coe.php', 'corner.php', 'dailyactivtyreport.php', 'debitadvise.php', 'e201.php',
            'earlyout.php', 'generalreport.php', 'includes/maintenance.php', 'leavecredit.php', 'memo.php',
            'modulehistory.php', 'newblog.php', 'notifications.php', 'ob.php', 'otfilling.php', 'otob.php',
            'payroll.php', 'payslip.php', 'questionnaire.php', 'reportse201.php', 'reset.php', 'uploadblogspicture.php',
            // query/ handlers
            'query/Query-CenarLogout.php', 'query/Query-DateParamCorner.php', 'query/Query-GetAutoLogout.php',
            'query/Query-IUCorner.php', 'query/Query-InsertBlog.php', 'query/Query-InsertDar.php',
            'query/Query-InsertDeleteJJD.php', 'query/Query-InsertOB.php', 'query/Query-LiveSearch.php',
            'query/Query-NotifDateParam.php', 'query/Query-Notifications.php', 'query/Query-SDarLilo.php',
            'query/Query-SearchJobDesc.php', 'query/Query-SearchOBEOOV.php', 'query/Query-UpdateApp.php',
            'query/Query-UpdateAppDis.php', 'query/Query-Viewalas.php', 'query/Query-alascalculateduration.php',
            'query/Query-checkinout.php', 'query/Query-insertalas.php', 'query/Query-inserteo.php',
            'query/Query-insertlilo.php', 'query/Query-insertot.php', 'query/Query-insertsendtoob.php',
            'query/Query-newpass.php', 'query/Query-obsearch.php', 'query/Query-payroll.php',
            'query/Query-searchemprole.php', 'query/Query-searchurole.php', 'query/Query-sendtoobsearch.php',
            'query/administrative.php', 'query/ams-phpscript.php', 'query/bookletreg-phpscript.php',
            'query/check-phpscript.php', 'query/coe-phpscript.php', 'query/debitPhpScript.php', 'query/deleteeo.php',
            'query/general-phpscript.php', 'query/memo-phpscript.php', 'query/ogmLeaveValidator.php',
            'query/ot-phpscript.php', 'query/payeereg-phpscript.php', 'query/payslip-phpscript.php',
            'query/searchemp.php', 'query/sessionex_.php',
        ];
        return array_combine($scripts, array_map(fn($s) => [$s], $scripts));
    }

    #[DataProvider('guardedScripts')]
    public function testLoggedOutGetsOnlyTheRedirect(string $script): void
    {
        $res = $this->request($script);
        $this->assertSame(302, $res['status'], "$script did not redirect; it sent:\n" . substr($res['body'], 0, 1500));
        $this->assertSame('', trim($res['body']), "$script sent a body to a logged-out caller");
    }

    /** Pages with the old WeDoID "remember me" block: a cookie that matches nobody must not get the page either. */
    public function testUnknownRememberCookieGetsOnlyTheRedirect(): void
    {
        foreach (['payroll.php', 'corner.php', 'notifications.php', 'debitadvise.php', 'alas.php'] as $page) {
            $res = $this->request($page, [], [], null, ['WeDoID' => 'not-a-real-token']);
            $this->assertSame(302, $res['status'], $page);
            $this->assertSame('', trim($res['body']), "$page sent a body for an unknown WeDoID cookie");
        }
    }

    public function testLoggedOutWriteHandlersWriteNothing(): void
    {
        $db = self::db();
        $db->prepare("INSERT INTO obs (EmpID, OBPurpose, OBStatus, OBUpdated) VALUES (?, 'Client visit', 1, '2026-10-01 09:00:00')")
           ->execute([self::EMP]);
        $obId   = (int) $db->lastInsertId();
        $status = fn() => (int) $db->query("SELECT OBStatus FROM obs WHERE OBID = $obId")->fetchColumn();
        $memos  = fn() => (int) $db->query("SELECT COUNT(*) FROM memo")->fetchColumn();
        $memosBefore = $memos();

        try {
            $this->request('query/Query-UpdateApp.php', ['ntype' => 'OB', 'id' => $obId]);
            $this->assertSame(1, $status(), 'OB not approved by a logged-out caller');

            $this->request('query/deleteeo.php', ['ob' => '', 'data' => $obId]);
            $this->assertSame(1, $status(), 'OB not cancelled by a logged-out caller');

            $this->request('query/memo-phpscript.php', ['store' => ''], ['data_to' => 'All', 'data_from' => 'x', 'data_date' => '2026-10-08',
                'data_subject' => 'Written while logged out', 'data_body' => 'x', 'memoid' => 'M-1']);
            $this->assertSame($memosBefore, $memos(), 'no memo inserted by a logged-out caller');
        } finally {
            $db->exec("DELETE FROM obs WHERE OBID = $obId");
        }
    }

    public function testSignedInUsersStillGetThePage(): void
    {
        $res = $this->request('coe.php', [], [], $this->asEmployee());
        $this->assertSame(200, $res['status']);
        $this->assertStringContainsString('<html', $res['body']);

        $before = (int) self::db()->query("SELECT COUNT(*) FROM dars")->fetchColumn();
        $this->request('query/Query-InsertDar.php', [], ['dreact' => 'Signed-in activity'], $this->asEmployee());
        $this->assertSame($before + 1, (int) self::db()->query("SELECT COUNT(*) FROM dars")->fetchColumn());
    }
}
