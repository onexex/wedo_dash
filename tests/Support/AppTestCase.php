<?php
namespace Wedo\Tests\Support;

use PHPUnit\Framework\TestCase;
use DOMDocument;
use DOMXPath;
use PDO;

/**
 * Base class for regression tests that drive the real PHP pages/endpoints
 * against the throwaway test database built in tests/bootstrap.php.
 */
abstract class AppTestCase extends TestCase
{
    public const ADMIN = 'TC-ADMIN';
    public const EMP   = 'TC-0001';

    protected static ?PDO $pdo = null;

    /** Tables the fixture seeds or the code under test writes — wiped before every test. */
    private const TABLES = [
        'companies', 'departments', 'positions', 'joblevel', 'empstatus', 'estatus', 'hmo', 'agency',
        'workschedule', 'workdays', 'frelationship', 'employees', 'empdetails', 'empdetails2', 'empprofiles',
        'empeducationalbackground', 'fdetails', 'accessrights', 'dars', 'profile_change_requests',
        'idcard_cards', 'idcard_print_log', 'idcard_settings', 'credit', 'credit_years', 'credit_log', 'hleaves', 'messageheader', 'messages', 'msg_presence', 'msg_calls', 'msg_call_members', 'msg_call_signals', 'msg_groups', 'msg_group_members', 'msg_reactions', 'msg_mentions', 'push_devices', 'app_remember', 'msg_cleared',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->resetAndSeed();
    }

    protected static function db(): PDO
    {
        if (self::$pdo === null) {
            $c = $GLOBALS['WEDO_TEST_DB'];
            self::$pdo = new PDO("mysql:host={$c['host']};dbname={$c['name']};charset=utf8mb4", $c['user'], $c['pass'],
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
            // allow explicit id 0 (workschedule's "Rest Day" row)
            self::$pdo->exec("SET SESSION sql_mode = CONCAT(@@sql_mode, ',NO_AUTO_VALUE_ON_ZERO')");
        }
        return self::$pdo;
    }

    // ------------------------------------------------------------------ fixture

    private function resetAndSeed(): void
    {
        $db = self::db();
        foreach (self::TABLES as $t) { $db->exec("DELETE FROM `$t`"); }

        $ins = function (string $table, array $row) use ($db) {
            $cols = implode(',', array_map(fn($c) => "`$c`", array_keys($row)));
            $ph   = implode(',', array_fill(0, count($row), '?'));
            $db->prepare("INSERT INTO `$table` ($cols) VALUES ($ph)")->execute(array_values($row));
        };

        $ins('companies', ['CompanyID' => 'TC', 'CompanyDesc' => 'TestCo', 'compcode' => 'TC', 'comcolor' => '#f93627']);
        $ins('departments', ['DepartmentID' => 1, 'DepartmentDesc' => 'Engineering', 'CompID' => 'TC']);
        $ins('departments', ['DepartmentID' => 2, 'DepartmentDesc' => 'People', 'CompID' => 'TC']);
        $ins('joblevel', ['jobLevelID' => 1, 'jobLevelDesc' => 'Rank and File']);
        $ins('joblevel', ['jobLevelID' => 2, 'jobLevelDesc' => 'Supervisory']);
        $ins('positions', ['PSID' => 10, 'PositionDesc' => 'Developer', 'DepartmentID' => 1, 'EmpJobLevelID' => 1]);
        $ins('positions', ['PSID' => 11, 'PositionDesc' => 'Recruiter', 'DepartmentID' => 2, 'EmpJobLevelID' => 2]);
        $ins('empstatus', ['EmpStatID' => 1, 'EmpCODE' => 'REG', 'EmpStatDesc' => 'Regular']);
        $ins('empstatus', ['EmpStatID' => 2, 'EmpCODE' => 'PRB', 'EmpStatDesc' => 'Probationary']);
        $ins('estatus', ['ID' => 1, 'StatusEmpDesc' => 'Employed']);
        $ins('estatus', ['ID' => 2, 'StatusEmpDesc' => 'Resigned']);
        $ins('hmo', ['HMO_ID' => 1, 'HMO_PROVIDER' => 'Maxicare']);
        $ins('hmo', ['HMO_ID' => 2, 'HMO_PROVIDER' => 'Intellicare']);
        $ins('agency', ['AgencyID' => 1, 'AgencyName' => 'Direct Hire', 'IsActive' => 1]);
        $ins('agency', ['AgencyID' => 2, 'AgencyName' => 'Partner Agency', 'IsActive' => 1]);
        $ins('workschedule', ['WorkSchedID' => 0, 'TimeFrom' => 'Rest', 'TimeTo' => 'Day', 'TimeCross' => 0]);
        $ins('workschedule', ['WorkSchedID' => 5, 'TimeFrom' => '08:00 AM', 'TimeTo' => '05:00 PM', 'TimeCross' => 0]);
        $ins('workschedule', ['WorkSchedID' => 6, 'TimeFrom' => '01:00 PM', 'TimeTo' => '10:00 PM', 'TimeCross' => 0]);
        $ins('frelationship', ['FRelID' => 1, 'FRelDesc' => 'Mother']);
        $ins('frelationship', ['FRelID' => 2, 'FRelDesc' => 'Spouse']);

        // the HR user doing the editing
        $ins('employees', ['EmpID' => self::ADMIN, 'EmpFN' => 'Ada', 'EmpLN' => 'Admin', 'EmpMN' => '', 'EmpSuffix' => '',
                           'PosID' => 11, 'EmpStatusID' => 1, 'EmployeeIDNumber' => 'A-1']);
        $ins('empdetails', ['EmpID' => self::ADMIN, 'EmpUN' => 'aadmin', 'EmpRoleID' => 1, 'EmpISID' => 'N/A', 'EmpdepID' => 2,
                            'EmpCompID' => 'TC', 'EmpStatID' => 2, 'AgencyID' => 1, 'HMO_ID' => 1,
                            'EmpDateHired' => '2020-01-06', 'EmpDateResigned' => '0000-00-00']);
        $ins('accessrights', ['EmpID' => self::ADMIN, 'updte' => 2, 'srch' => 2, 'e201' => 2, 'idcard' => 2]);

        // the employee being edited
        $ins('employees', ['EmpID' => self::EMP, 'EmpFN' => 'Juan', 'EmpLN' => 'Dela Cruz', 'EmpMN' => 'Santos', 'EmpSuffix' => 'Jr',
                           'PosID' => 10, 'EmpStatusID' => 1, 'EmployeeIDNumber' => 'E-1']);
        $ins('empdetails', ['EmpID' => self::EMP, 'EmpUN' => 'jdelacruz', 'EmpRoleID' => 3, 'EmpISID' => self::ADMIN, 'EmpdepID' => 1,
                            'EmpCompID' => 'TC', 'EmpStatID' => 1, 'AgencyID' => 2, 'HMO_ID' => 2,
                            'EmpDateHired' => '2021-02-01', 'EmpDOR' => '2021-08-01', 'EmpDateResigned' => '0000-00-00']);
        // a regular employee: may open the 201 page, but has no "Update 201 Files"
        $ins('accessrights', ['EmpID' => self::EMP, 'e201' => 2, 'updte' => 1, 'srch' => 1]);
        $ins('empdetails2', ['EmpID' => self::EMP, 'EmpBasic' => 25000, 'EmpAllowance' => 2000, 'EmpHRate' => 150]);
        $ins('empprofiles', [
            'EmpID' => self::EMP, 'EmpAddress1' => 'B1 L2 Sampaguita St', 'EmpAddDis' => 'San Isidro', 'EmpAddCity' => 'Quezon City',
            'EmpAddProv' => 'Metro Manila', 'EmpAddZip' => '1100', 'EmpAddCountry' => 'Philippines',
            'EmpPhone' => '09170000001',   // mobile (form: pempn0)        — same mapping as registration
            'EmpMobile' => '0281234567',   // home   (form: emphomenumber)
            'EmpDOB' => '1990-05-15', 'EmpGender' => 'Male', 'EmpCS' => 'Single', 'EmpEmail' => 'juan@example.com',
            'EmpPPNo' => 'P1234567', 'EmpPPED' => '2030-01-31', 'EmpPPIA' => 'DFA Manila', 'EmpSSS' => '34-1234567-8',
            'EmpTIN' => '123-456-789', 'EmpHMONumber' => 'HMO-77', 'EmpPP' => 'OldCo', 'EmpPPSD' => '2019-01-07',
            'EmpPPDept' => 'IT', 'EmpPPPos' => 'Intern', 'EmpPINo' => '1212-3434', 'EmpPHNo' => '01-0202', 'EmpUMIDNo' => 'U-99',
            'EmpPPath' => 'assets/images/profiles/' . self::EMP . '.jpg', 'EmpCitezen' => 'Filipino', 'EmpReligion' => 'Catholic',
        ]);
        foreach ([['Primary', 'Pasig Elementary', 1996, 2002, 'Pasig'],
                  ['Secondary', 'Rizal High School', 2002, 2006, 'Pasig'],
                  ['Tertiary', 'PUP Manila', 2006, 2010, 'Sta. Mesa']] as [$prog, $school, $from, $to, $addr]) {
            $ins('empeducationalbackground', ['EmpID' => self::EMP, 'Program' => $prog, 'Name_of_School' => $school,
                                              'Year_Started' => $from, 'Year_End' => $to, 'School_Address' => $addr]);
        }
        $ins('fdetails', ['FDetID' => self::EMP, 'FName' => 'Maria Dela Cruz', 'FAdd' => 'Pasig', 'FRel' => 'Mother',
                          'FContact' => '09171112222', 'FICE' => 'Yes']);
        foreach (['Monday' => 5, 'Tuesday' => 5, 'Wednesday' => 5, 'Thursday' => 5, 'Friday' => 6, 'Saturday' => 0, 'Sunday' => 0] as $day => $sched) {
            $ins('workdays', ['empid' => self::EMP, 'Day_s' => $day, 'SchedTime' => $sched]);
        }
    }

    // ------------------------------------------------------------------ requests

    /** Session of the seeded HR user (has "Update 201 Files"). */
    protected function asAdmin(): array
    {
        return ['id' => self::ADMIN, 'UserType' => 1, 'CompanyName' => 'TestCo', 'CompanyColor' => '#f93627', 'CompID' => 'TC'];
    }

    /** Session of the seeded regular employee (no "Update 201 Files"). */
    protected function asEmployee(): array
    {
        return ['id' => self::EMP, 'UserType' => 3, 'CompanyName' => 'TestCo', 'CompanyColor' => '#f93627', 'CompID' => 'TC'];
    }

    /**
     * Run an app script (path relative to the repo root) in a child PHP process.
     * $session = null means "not logged in" (no session cookie).
     *
     * @return array{status:int, body:string}
     */
    protected function request(string $script, array $get = [], array $post = [], ?array $session = null): array
    {
        $root = dirname(__DIR__, 2);
        $tmp  = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'wedo-tests';
        if (!is_dir($tmp)) { mkdir($tmp, 0777, true); }

        $sid = null;
        if ($session !== null) {
            $sid = 'wedotest' . bin2hex(random_bytes(8));
            $data = '';
            foreach ($session as $k => $v) { $data .= $k . '|' . serialize($v); }   // session.serialize_handler=php
            file_put_contents($tmp . DIRECTORY_SEPARATOR . 'sess_' . $sid, $data);
        }

        $spec = $tmp . DIRECTORY_SEPARATOR . 'spec-' . bin2hex(random_bytes(6)) . '.json';
        file_put_contents($spec, json_encode([
            'script' => $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $script),
            'get' => $get, 'post' => $post, 'session_dir' => $tmp, 'session_id' => $sid,
        ]));

        $c   = $GLOBALS['WEDO_TEST_DB'];
        $env = array_merge(getenv(), ['DB_HOST' => $c['host'], 'DB_USER' => $c['user'], 'DB_PASS' => $c['pass'], 'DB_NAME' => $c['name']]);
        $cmd = [PHP_BINARY, '-d', 'display_errors=1', '-d', 'html_errors=0', '-d', 'error_reporting=-1',
                '-d', 'log_errors=0', '-d', 'display_startup_errors=1',
                $root . '/tests/Support/run-script.php', $spec];

        $proc = proc_open($cmd, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, $root, $env);
        $body = stream_get_contents($pipes[1]);
        $err  = stream_get_contents($pipes[2]);
        fclose($pipes[1]); fclose($pipes[2]);
        proc_close($proc);
        @unlink($spec);
        if ($sid !== null) { @unlink($tmp . DIRECTORY_SEPARATOR . 'sess_' . $sid); }

        $status = preg_match('/__HTTP_STATUS__=(\d+)/', $err, $m) ? (int)$m[1] : 0;
        $this->assertNotSame(0, $status, "Script $script did not finish:\n" . $err . $body);
        return ['status' => $status, 'body' => $body];
    }

    /** Fail if the output contains a PHP warning/notice/error (the browser or AJAX caller would see it). */
    protected function assertNoPhpErrors(string $body, string $context = ''): void
    {
        $this->assertDoesNotMatchRegularExpression(
            '/(Warning|Notice|Deprecated|Fatal error|Parse error|Uncaught)\b.*? in .*? on line \d+/i',
            $body, "PHP error in output$context:\n" . substr($body, 0, 2000));
    }

    // ------------------------------------------------------------------ HTML helpers

    protected function dom(string $html): DOMXPath
    {
        $doc = new DOMDocument();
        libxml_use_internal_errors(true);
        $doc->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_NOERROR | LIBXML_NOWARNING);
        libxml_clear_errors();
        return new DOMXPath($doc);
    }

    /**
     * What jQuery's $("#fdata").serialize() would send for the rendered form:
     * named, enabled controls; checked boxes only; selects → selected option (or
     * the first); <input type=date> sanitized like a browser (invalid → '').
     * Later duplicates win, as in PHP's $_POST.
     */
    protected function formData(string $html, string $formId = 'fdata'): array
    {
        $x = $this->dom($html);
        $form = $x->query("//form[@id='$formId']")->item(0);
        $this->assertNotNull($form, "form #$formId not found");

        $data = [];
        foreach ($x->query('.//input|.//select|.//textarea', $form) as $el) {
            $name = $el->getAttribute('name');
            if ($name === '' || $el->hasAttribute('disabled')) { continue; }
            $tag = strtolower($el->nodeName);
            if ($tag === 'select') {
                $opts = $x->query('.//option', $el);
                $pick = null;
                foreach ($opts as $o) { if ($o->hasAttribute('selected')) { $pick = $o; } }
                $pick = $pick ?? $opts->item(0);
                $data[$name] = $pick ? ($pick->hasAttribute('value') ? $pick->getAttribute('value') : trim($pick->textContent)) : '';
                continue;
            }
            if ($tag === 'textarea') { $data[$name] = $el->textContent; continue; }
            $type = strtolower($el->getAttribute('type') ?: 'text');
            if (in_array($type, ['file', 'submit', 'button', 'image', 'reset'], true)) { continue; }
            if (in_array($type, ['checkbox', 'radio'], true) && !$el->hasAttribute('checked')) { continue; }
            $value = $el->getAttribute('value');
            if ($type === 'date' && !preg_match('/^(?!0000)\d{4}-(0[1-9]|1[0-2])-(0[1-9]|[12]\d|3[01])$/', $value)) { $value = ''; }
            $data[$name] = $value;
        }
        return $data;
    }

    protected function selectedOf(string $html, string $selectId): ?string
    {
        $x = $this->dom($html);
        $sel = $x->query("//select[@id='$selectId']")->item(0);
        $this->assertNotNull($sel, "select #$selectId not found");
        foreach ($x->query('.//option', $sel) as $o) {
            if ($o->hasAttribute('selected')) { return $o->hasAttribute('value') ? $o->getAttribute('value') : trim($o->textContent); }
        }
        return null;
    }

    protected function inputValue(string $html, string $name): ?string
    {
        $el = $this->dom($html)->query("//input[@name='$name']")->item(0);
        return $el ? $el->getAttribute('value') : null;
    }

    // ------------------------------------------------------------------ DB helpers

    protected function row(string $sql, array $params = []): ?array
    {
        $st = self::db()->prepare($sql);
        $st->execute($params);
        $r = $st->fetch();
        return $r === false ? null : $r;
    }

    protected function rows(string $sql, array $params = []): array
    {
        $st = self::db()->prepare($sql);
        $st->execute($params);
        return $st->fetchAll();
    }

    /** Everything the edit form can change for one employee (dars/activity excluded). */
    protected function employeeSnapshot(string $empId): array
    {
        return [
            'employees'   => $this->row('SELECT * FROM employees WHERE EmpID=?', [$empId]),
            'empdetails'  => $this->row('SELECT * FROM empdetails WHERE EmpID=?', [$empId]),
            'empdetails2' => $this->row('SELECT * FROM empdetails2 WHERE EmpID=?', [$empId]),
            'empprofiles' => $this->row('SELECT * FROM empprofiles WHERE EmpID=?', [$empId]),
            'education'   => $this->rows('SELECT * FROM empeducationalbackground WHERE EmpID=? ORDER BY EB_ID', [$empId]),
            'workdays'    => $this->rows('SELECT * FROM workdays WHERE empid=? ORDER BY WID', [$empId]),
            'family'      => $this->rows('SELECT FName,FAdd,FRel,FContact,FICE FROM fdetails WHERE FDetID=? ORDER BY FSID', [$empId]),
        ];
    }
}
