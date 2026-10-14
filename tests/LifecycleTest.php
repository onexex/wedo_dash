<?php
namespace Wedo\Tests;

use Wedo\Tests\Support\AppTestCase;

/**
 * Employee lifecycle: separation (Resigned / Terminated / AWOL), history,
 * COE requests and employment verification (CI).
 *
 *   includes/lifecycle-lib.php       rules
 *   query/lifecycle-action.php       every read/write (gated + token)
 *   separation.php, coe.php, verify.php   the screens
 *   query/Query-updateEmployee.php   writes history on status / classification change
 */
final class LifecycleTest extends AppTestCase
{
    private const TOKEN = 'lcy-test-token';

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        require_once dirname(__DIR__) . '/includes/lifecycle-lib.php';
    }

    protected function setUp(): void
    {
        parent::setUp();
        $db = self::db();
        $db->exec("INSERT IGNORE INTO estatus (ID, StatusEmpDesc) VALUES (4, 'Terminated'), (5, 'AWOL')");
        $db->exec("INSERT IGNORE INTO empstatus (EmpStatID, EmpCODE, EmpStatDesc) VALUES (3, 'TR', 'Trainee')");
        foreach (['app_remember', 'push_devices'] as $t) { $db->exec("DELETE FROM `$t`"); }
        // the HR user runs separations, verification and COE approval
        $db->exec("UPDATE accessrights SET separation = 2, empverify = 2, coefunction = 2 WHERE EmpID = '" . self::ADMIN . "'");
    }

    private function hr(): array { return $this->asAdmin() + ['lcy_csrf' => self::TOKEN]; }
    private function emp(): array { return $this->asEmployee() + ['lcy_csrf' => self::TOKEN]; }

    private function act(string $action, array $post, ?array $session = null, bool $withToken = true): array
    {
        $post = ['action' => $action] + $post + ($withToken ? ['token' => self::TOKEN] : []);
        $res = $this->request('query/lifecycle-action.php', [], $post, $session ?? $this->hr());
        $this->assertNoPhpErrors($res['body']);
        $res['json'] = json_decode($res['body'], true);
        $this->assertIsArray($res['json'], "not JSON: " . $res['body']);
        return $res;
    }

    private function separate(array $over = [], ?array $session = null): array
    {
        return $this->act('separate', $over + ['emp' => self::EMP, 'type' => 'awol', 'lastDay' => '2026-10-01',
            'reason' => 'No call, no show since Oct 2', 'rehire' => 'no', 'rehireNote' => 'AWOL', 'clearance' => '0'], $session);
    }

    // ------------------------------------------------------------ separation

    public function testSeparationSetsStatusLastDayRecordAndSignsOut(): void
    {
        self::db()->prepare("UPDATE empdetails SET remember_hash = 'x', remember_expiry = ? WHERE EmpID = ?")
            ->execute([date('Y-m-d H:i:s', time() + 3600), self::EMP]);

        $res = $this->separate();
        $this->assertSame(200, $res['status'], $res['body']);

        $this->assertSame('5', (string) $this->row("SELECT EmpStatusID FROM employees WHERE EmpID = ?", [self::EMP])['EmpStatusID']);
        $this->assertSame('2026-10-01', $this->row("SELECT EmpDateResigned FROM empdetails WHERE EmpID = ?", [self::EMP])['EmpDateResigned']);
        $sep = $this->row("SELECT * FROM emp_separations WHERE EmpID = ?", [self::EMP]);
        $this->assertSame(['awol', '2026-10-01', 'no', self::ADMIN], [$sep['Type'], $sep['LastDay'], $sep['RehireEligible'], $sep['RecordedBy']]);
        $ev = $this->row("SELECT * FROM emp_lifecycle_events WHERE EmpID = ? AND EventType = 'separated'", [self::EMP]);
        $this->assertSame(['Employed', 'AWOL'], [$ev['FromVal'], $ev['ToVal']]);
        $this->assertNull($this->row("SELECT remember_hash FROM empdetails WHERE EmpID = ?", [self::EMP])['remember_hash'], 'signed out');
        require_once dirname(__DIR__) . '/includes/account-gate.php';
        $this->assertTrue(wd_account_blocked(self::db(), self::EMP), 'AWOL may not sign in');
    }

    public function testSeparationNeedsTheRightAndTheToken(): void
    {
        $this->assertSame(403, $this->separate([], $this->emp())['status']);
        $this->assertSame(419, $this->act('separate', ['emp' => self::EMP], $this->hr(), false)['status']);
        $this->assertSame('1', (string) $this->row("SELECT EmpStatusID FROM employees WHERE EmpID = ?", [self::EMP])['EmpStatusID']);
    }

    public function testSeparationIsValidated(): void
    {
        $this->assertSame(422, $this->separate(['reason' => ' '])['status'], 'reason required');
        $this->assertSame(422, $this->separate(['lastDay' => '2026-02-30'])['status'], 'real date');
        $this->assertSame(422, $this->separate(['lastDay' => '2019-01-01'])['status'], 'not before hire');
        $this->assertSame(422, $this->separate(['type' => 'retired'])['status'], 'known type');
        $this->assertSame(200, $this->separate()['status']);
        $again = $this->separate();
        $this->assertSame(422, $again['status'], 'already separated');
        $this->assertStringContainsString('already AWOL', $again['json']['msg']);
    }

    public function testEditingASeparationRecordsTheRehireDecision(): void
    {
        $this->separate(['rehire' => 'review']);
        $id = (int) $this->row("SELECT id FROM emp_separations WHERE EmpID = ?", [self::EMP])['id'];

        $res = $this->act('sep_update', ['id' => $id, 'lastDay' => '2026-10-03', 'reason' => 'Abandoned work',
                                         'rehire' => 'no', 'rehireNote' => 'Policy: AWOL', 'clearance' => '1']);
        $this->assertSame(200, $res['status'], $res['body']);
        $sep = $this->row("SELECT * FROM emp_separations WHERE id = ?", [$id]);
        $this->assertSame(['2026-10-03', 'no', '1'], [$sep['LastDay'], $sep['RehireEligible'], (string) $sep['ClearanceDone']]);
        $this->assertSame('2026-10-03', $this->row("SELECT EmpDateResigned FROM empdetails WHERE EmpID = ?", [self::EMP])['EmpDateResigned']);
        $flag = $this->row("SELECT * FROM emp_lifecycle_events WHERE EmpID = ? AND EventType = 'rehire_flag'", [self::EMP]);
        $this->assertSame('Not eligible for rehire', $flag['ToVal']);
    }

    public function testUpdateEmployeeRecordsTraineeToIcAndOpensASeparationRecord(): void
    {
        self::db()->exec("UPDATE accessrights SET updte = 2 WHERE EmpID = '" . self::ADMIN . "'");
        self::db()->exec("UPDATE empdetails SET EmpStatID = 3 WHERE EmpID = '" . self::EMP . "'");   // Trainee
        $form = $this->formData($this->request('UpdateEmployeeInfo.php', ['sid' => self::EMP], [], $this->asAdmin())['body']);

        // Trainee -> classification 2 (the fixture's second classification)
        $res = $this->request('query/Query-updateEmployee.php', [], ['empclassification' => '2'] + $form, $this->asAdmin());
        $this->assertSame('', trim($res['body']), 'save prints nothing on success');
        $ev = $this->row("SELECT * FROM emp_lifecycle_events WHERE EmpID = ? AND EventType = 'classification'", [self::EMP]);
        $this->assertSame(['Trainee', 'Probationary'], [$ev['FromVal'], $ev['ToVal']]);
        $this->assertNull($this->row("SELECT id FROM emp_separations WHERE EmpID = ?", [self::EMP]), 'still employed');

        // then resigned in the same screen: a separation record to finish
        $res = $this->request('query/Query-updateEmployee.php', [], ['empclassification' => '2', 'empst' => '2'] + $form, $this->asAdmin());
        $this->assertSame('', trim($res['body']));
        $sep = $this->row("SELECT * FROM emp_separations WHERE EmpID = ?", [self::EMP]);
        $this->assertSame(['resigned', 'review'], [$sep['Type'], $sep['RehireEligible']]);
        $open = $this->act('sep_open', [])['json']['open'];
        $this->assertSame([self::EMP], array_column($open, 'empId'));
    }

    public function testTimelineStartsWithTheHireDateForPeopleEnrolledBefore(): void
    {
        $t = $this->act('timeline', ['emp' => self::EMP])['json'];
        $this->assertSame('hired', $t['events'][0]['type']);
        $this->assertSame('2021-02-01', $t['events'][0]['date']);
        $this->assertSame('Juan Santos Dela Cruz Jr', $t['employee']['name']);
        $this->assertSame(403, $this->act('timeline', ['emp' => self::EMP], $this->emp())['status']);
    }

    public function testEnrollingNeedsTheEnrollRight(): void
    {
        $res = $this->request('query/Query-InsertEmployeeInfo.php', [], ['empidn' => 'TC-9999', 'empfn' => 'X', 'empln' => 'Y'], $this->asEmployee());
        $this->assertSame(403, $res['status']);
        $this->assertNull($this->row("SELECT EmpID FROM employees WHERE EmpID = 'TC-9999'"));
        $this->assertSame(401, $this->request('query/Query-InsertEmployeeInfo.php', [], ['empidn' => 'TC-9999'])['status']);
        $page = $this->request('newemployee.php', [], [], $this->asEmployee());
        $this->assertStringNotContainsString('id="fdata"', $page['body'], 'the enroll page itself is gated too');
    }

    // ------------------------------------------------------------ COE

    public function testEmployeeRequestsHrApprovesEmployeePrints(): void
    {
        $res = $this->act('coe_request', ['purpose' => 'Bank loan', 'salary' => '1'], $this->emp());
        $this->assertSame(200, $res['status'], $res['body']);
        $id = $res['json']['id'];
        $this->assertSame('pending', $this->row("SELECT Status FROM coe_requests WHERE id = ?", [$id])['Status']);

        $this->assertSame(403, $this->act('coe_cert', ['id' => $id], $this->emp())['status'], 'not before approval');
        $this->assertSame(403, $this->act('coe_decide', ['id' => $id, 'decision' => 'approved'], $this->emp())['status'], 'employees cannot approve');

        $ok = $this->act('coe_decide', ['id' => $id, 'decision' => 'approved']);
        $this->assertSame(200, $ok['status'], $ok['body']);
        $this->assertSame('COE-' . date('Y') . '-0001', $ok['json']['request']['controlNo']);

        $cert = $this->act('coe_cert', ['id' => $id], $this->emp())['json']['cert'];
        $this->assertFalse($cert['separated'], 'present tense');
        $this->assertSame(['Mr.', 'Dela Cruz', 'Developer', 'February 1, 2021'], [$cert['salutation'], $cert['lastName'], $cert['position'], $cert['hired']]);
        $this->assertSame('TWENTY FIVE THOUSAND (Php 25,000.00)', $cert['salary']);

        // printed twice: one history entry, the first print time kept
        $this->act('coe_printed', ['id' => $id], $this->emp());
        $this->act('coe_printed', ['id' => $id], $this->emp());
        $this->assertCount(1, $this->rows("SELECT id FROM emp_lifecycle_events WHERE EmpID = ? AND EventType = 'coe_issued'", [self::EMP]));
        $this->assertNotNull($this->row("SELECT PrintedAt FROM coe_requests WHERE id = ?", [$id])['PrintedAt']);
    }

    public function testOnlyTheOwnerOrHrSeesACertificate(): void
    {
        $id = $this->act('coe_issue', ['emp' => self::ADMIN, 'purpose' => 'Visa', 'salary' => '1'])['json']['id'];
        $this->assertSame(403, $this->act('coe_cert', ['id' => $id], $this->emp())['status']);
        $this->assertSame(403, $this->act('coe_issue', ['emp' => self::EMP, 'purpose' => 'x'], $this->emp())['status']);
        $this->assertSame(403, $this->act('coe_queue', [], $this->emp())['status']);
    }

    public function testRejectingNeedsAReasonAndCancellingIsOwnOnly(): void
    {
        $id = $this->act('coe_request', ['purpose' => 'Visa'], $this->emp())['json']['id'];
        $this->assertSame(422, $this->act('coe_decide', ['id' => $id, 'decision' => 'rejected'])['status']);
        $this->assertSame(422, $this->act('coe_cancel', ['id' => $id])['status'], 'HR cannot cancel the employee\'s request');
        $this->assertSame(200, $this->act('coe_cancel', ['id' => $id], $this->emp())['status']);
        $this->assertSame('cancelled', $this->row("SELECT Status FROM coe_requests WHERE id = ?", [$id])['Status']);
    }

    public function testHrIssuesAPastTenseCoeForSomeoneWhoLeft(): void
    {
        $this->separate(['type' => 'resigned', 'lastDay' => '2026-09-30', 'rehire' => 'yes']);
        $res = $this->act('coe_issue', ['emp' => self::EMP, 'purpose' => 'New employer requirement']);
        $this->assertSame(200, $res['status'], $res['body']);
        $row = $this->row("SELECT * FROM coe_requests WHERE id = ?", [$res['json']['id']]);
        $this->assertSame(['approved', 'hr'], [$row['Status'], $row['Channel']]);

        $cert = $this->act('coe_cert', ['id' => $row['id']])['json']['cert'];
        $this->assertTrue($cert['separated'], 'past tense');
        $this->assertSame('September 30, 2026', $cert['lastDay']);
        $this->assertNull($cert['salary'], 'no salary unless asked');
    }

    public function testOldCoeEndpointNoLongerLeaksOtherPeoplesSalary(): void
    {
        $res = $this->request('query/coe-phpscript.php', ['getemplooyeedata' => ''], ['id' => self::ADMIN], $this->asEmployee());
        $this->assertSame(403, $res['status']);
        $this->assertStringNotContainsString('EmpBasic', $res['body']);
    }

    public function testSignaturesAreServedOnlyToThoseWhoPrintSignedDocuments(): void
    {
        $sig = fn(array $get, ?array $s) => $this->request('query/signature.php', $get, [], $s)['status'];
        $this->assertSame(401, $sig(['s' => 'ogm'], null));
        $this->assertSame(403, $sig(['s' => 'ogm'], $this->asEmployee()), 'a regular employee');
        $this->assertSame(404, $sig(['s' => '../../w_conn'], $this->asAdmin()), 'only known names');
        $this->assertSame(200, $sig(['s' => 'ogm'], $this->asAdmin()), 'COE approver');
        $this->assertFileExists(dirname(__DIR__) . '/assets/images/sign/.htaccess', 'no direct web access');

        // the employee's own approved request unlocks it for that print only
        $id = $this->act('coe_request', ['purpose' => 'Loan'], $this->emp())['json']['id'];
        $this->assertSame(403, $sig(['s' => 'ogm', 'r' => $id], $this->asEmployee()), 'not before approval');
        $this->act('coe_decide', ['id' => $id, 'decision' => 'approved']);
        $this->assertSame(200, $sig(['s' => 'ogm', 'r' => $id], $this->asEmployee()));
        $this->assertSame(403, $sig(['s' => 'elt', 'r' => $id], $this->asEmployee()), 'other signatures need Debit Advise');
    }

    public function testCoeControlNumberCheck(): void
    {
        $id = $this->act('coe_issue', ['emp' => self::EMP, 'purpose' => 'Visa'])['json']['id'];
        $no = $this->row("SELECT ControlNo FROM coe_requests WHERE id = ?", [$id])['ControlNo'];
        $ok = $this->act('coe_check', ['controlNo' => strtolower($no)])['json']['coe'];
        $this->assertSame(['approved', 'Juan Santos Dela Cruz Jr', 'Developer'], [$ok['status'], $ok['name'], $ok['position']]);
        $this->assertNull($this->act('coe_check', ['controlNo' => 'COE-2026-9999'])['json']['coe'], 'fake number');
        $this->assertNull($this->act('coe_check', ['controlNo' => "x' OR 1=1"])['json']['coe']);
        $this->assertSame(403, $this->act('coe_check', ['controlNo' => $no], $this->emp())['status']);
    }

    public function testAmountInWords(): void
    {
        $this->assertSame('TWENTY FIVE THOUSAND (Php 25,000.00)', lcy_amount_words(25000));
        $this->assertSame('ONE MILLION TWO HUNDRED THIRTY FOUR THOUSAND FIVE HUNDRED SIXTY SEVEN AND 50/100 (Php 1,234,567.50)',
                          lcy_amount_words('1234567.50'));
        $this->assertSame('SIXTEEN THOUSAND ONE HUNDRED (Php 16,100.00)', lcy_amount_words(16100));
    }

    // ------------------------------------------------------------ verification (CI)

    public function testLookupFindsWeDoAndArchiveRecordsAndLogsTheCall(): void
    {
        $this->separate(['type' => 'terminated', 'rehire' => 'no']);
        self::db()->exec("INSERT INTO amsarchive (id, fname, lname, pos, empdatesfrom, empdatesto, derogatoryrecords)
                          VALUES (7, 'Juana', 'Dela Cruz', 'Agent', '2015', '2017', 'None')");

        $res = $this->act('verify_lookup', ['q' => 'dela cruz']);
        $this->assertSame(200, $res['status'], $res['body']);
        $this->assertSame([self::EMP], array_column($res['json']['employees'], 'empId'));
        $this->assertSame('no', $res['json']['employees'][0]['rehire']);
        $this->assertSame([7], array_column($res['json']['archive'], 'amsId'));

        $call = ['emp' => self::EMP, 'caller' => 'Ana Reyes', 'company' => 'BDO', 'purpose' => 'ci', 'disclosed' => ['dates', 'salary']];
        $this->assertSame(422, $this->act('verify_log', $call)['status'], 'salary needs consent');
        $this->assertSame(200, $this->act('verify_log', $call + ['consent' => '1'])['status']);
        $this->assertSame(200, $this->act('verify_log', ['ams' => 7, 'caller' => 'Ben', 'company' => 'Acme', 'purpose' => 'background'])['status']);

        $again = $this->act('verify_lookup', ['q' => 'dela cruz'])['json'];
        $this->assertSame('BDO', $again['employees'][0]['calls'][0]['company']);
        $this->assertSame(['dates', 'salary'], $again['employees'][0]['calls'][0]['disclosed']);
        $this->assertSame('Juana Dela Cruz', $again['archive'][0]['calls'][0]['subject']);
    }

    public function testVerificationNeedsTheRight(): void
    {
        $this->assertSame(403, $this->act('verify_lookup', ['q' => 'dela'], $this->emp())['status']);
        $this->assertSame(403, $this->act('verify_log', ['emp' => self::EMP], $this->emp())['status']);
    }

    // ------------------------------------------------------------ screens

    public function testScreensRenderAndAreGated(): void
    {
        foreach (['separation.php', 'verify.php', 'coe.php'] as $page) {
            $res = $this->request($page, [], [], $this->asAdmin());
            $this->assertSame(200, $res['status'], $page);
            $this->assertNoPhpErrors($res['body'], " ($page)");
            $this->assertStringContainsString('lifecycle-common.js', $res['body'], $page);
        }
        foreach (['separation.php', 'verify.php'] as $page) {
            $res = $this->request($page, [], [], $this->asEmployee());
            $this->assertStringNotContainsString('lifecycle-common.js', $res['body'], "$page is HR only");
        }
        $coe = $this->request('coe.php', [], [], $this->asEmployee())['body'];
        $this->assertNoPhpErrors($coe);
        $this->assertStringContainsString('coeRequestForm', $coe, 'everyone may request a COE');
        $this->assertStringNotContainsString('coeIssueModal', $coe, 'only approvers issue');

        $menu = $this->request('idcard.php', [], [], $this->asAdmin())['body'];
        $this->assertStringContainsString('href="separation"', $menu);
        $this->assertStringContainsString('href="verify"', $menu);
    }
}
