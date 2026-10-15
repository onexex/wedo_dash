<?php
namespace Wedo\Tests;

use Wedo\Tests\Support\AppTestCase;

/**
 * Applicants: public registration (full 201 profile, no login), the HR
 * pipeline and hiring (the registration is copied into the employee tables).
 *
 *   includes/applicants-lib.php     rules, spam guards, apl_hire()
 *   query/apply-action.php          public submit
 *   query/applicants-action.php     HR reads/writes (gated + token)
 *   query/applicant-resume.php      resume download (gated)
 *   query/applicant-photo.php       camera photo (gated)
 *   apply.php, applicants.php       the screens
 */
final class ApplicantsTest extends AppTestCase
{
    private const TOKEN = 'apl-test-token';
    private const PUB   = 'apl-public-token';
    private array $files = [];

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        require_once dirname(__DIR__) . '/includes/applicants-lib.php';
    }

    protected function setUp(): void
    {
        parent::setUp();
        $db = self::db();
        $db->exec("INSERT IGNORE INTO empstatus (EmpStatID, EmpCODE, EmpStatDesc) VALUES (3, 'TR', 'Trainee')");
        $db->exec("INSERT IGNORE INTO estatus (ID, StatusEmpDesc) VALUES (4, 'Terminated'), (5, 'AWOL')");
        // the HR user reviews applicants and may enroll employees
        $db->exec("UPDATE accessrights SET applicants = 2, eemployee = 2, separation = 2 WHERE EmpID = '" . self::ADMIN . "'");
        $db->exec("UPDATE empdetails SET EmpRoleID = 1 WHERE EmpID = '" . self::ADMIN . "'");
        $_SESSION = ['id' => self::ADMIN, 'apl_pub' => self::PUB, 'apl_pub_at' => time() - 60];
    }

    protected function tearDown(): void
    {
        foreach (self::db()->query("SELECT ResumeKey, PhotoKey FROM applicants")->fetchAll(\PDO::FETCH_ASSOC) as $k) {
            foreach ([apl_resume_path($k['ResumeKey']), apl_photo_path($k['PhotoKey'])] as $p) {
                if ($p) { @unlink($p); }
            }
        }
        foreach ($this->files as $f) { @unlink($f); }
        $_SESSION = [];
        parent::tearDown();
    }

    // ------------------------------------------------------------ helpers

    /** A small real PDF; unique each time unless $same (the same file twice is refused as a repeat). */
    private function pdf(string $same = ''): array
    {
        $tmp = tempnam(sys_get_temp_dir(), 'apl') ;
        file_put_contents($tmp, "%PDF-1.4\n% " . ($same !== '' ? $same : bin2hex(random_bytes(8)))
            . "\n1 0 obj << /Type /Catalog >> endobj\ntrailer << /Root 1 0 R >>\n%%EOF\n");
        $this->files[] = $tmp;
        return ['name' => 'My Resume.pdf', 'tmp_name' => $tmp, 'size' => filesize($tmp), 'error' => UPLOAD_ERR_OK];
    }

    /** A camera-like photo (a real JPEG, 640 x 480). */
    private function jpeg(int $w = 640, int $h = 480): array
    {
        $img = imagecreatetruecolor($w, $h);
        imagefilledrectangle($img, 0, 0, $w, $h, imagecolorallocate($img, 200, 160, 120));
        $tmp = tempnam(sys_get_temp_dir(), 'apl');
        imagejpeg($img, $tmp, 90);
        $this->files[] = $tmp;
        return ['name' => 'photo.jpg', 'tmp_name' => $tmp, 'size' => filesize($tmp), 'error' => UPLOAD_ERR_OK];
    }

    private function form(array $over = []): array
    {
        return array_replace_recursive([
            'token' => self::PUB, 'website' => '', 'opening' => '0', 'consent' => '1',
            'firstName' => 'Maria', 'middleName' => 'Lopez', 'lastName' => 'Santos', 'suffix' => '',
            'birthDate' => '1998-03-14', 'gender' => 'Female', 'civilStatus' => 'Single', 'citizenship' => 'Filipino', 'religion' => 'Catholic',
            'email' => 'maria@example.com', 'mobile' => '0917 123 4567', 'homePhone' => '',
            'street' => '12 Mabini St', 'barangay' => 'San Roque', 'city' => 'Pasig', 'province' => 'Metro Manila', 'zip' => '1600',
            'country' => 'Philippines', 'sss' => '34-5678901-2', 'tin' => '', 'philhealth' => '', 'pagibig' => '', 'umid' => '',
            'passportNo' => '', 'passportExpiry' => '', 'passportIssuer' => '', 'prevCompany' => 'Acme Call Center',
            'prevPosition' => 'Agent', 'prevDept' => 'Ops', 'prevStart' => '2021-06-01', 'source' => 'Facebook',
            'edu' => ['Primary' => ['school' => '', 'from' => '', 'to' => '', 'address' => ''],
                      'Secondary' => ['school' => 'Rizal High School', 'from' => '2010', 'to' => '2014', 'address' => 'Pasig'],
                      'Tertiary' => ['school' => 'PUP Manila', 'from' => '2014', 'to' => '2018', 'address' => 'Sta. Mesa']],
            'fam' => [['name' => 'Ana Santos', 'relationship' => 'Mother', 'contact' => '09181112222', 'address' => 'Pasig', 'emergency' => '1']],
        ], $over);
    }

    private function submit(array $over = [], ?array $file = null, string $ip = '203.0.113.5', ?array $photo = null): array
    {
        $_SESSION['apl_pub'] = self::PUB;
        $_SESSION['apl_pub_at'] = time() - 60;
        return apl_submit(self::db(), $this->form($over), $file ?? $this->pdf(), $ip, false, $photo ?? $this->jpeg());
    }

    private function hr(): array { return $this->asAdmin() + ['apl_csrf' => self::TOKEN]; }

    private function act(string $action, array $post, ?array $session = null): array
    {
        $res = $this->request('query/applicants-action.php', [], ['action' => $action, 'token' => self::TOKEN] + $post, $session ?? $this->hr());
        $this->assertNoPhpErrors($res['body']);
        $res['json'] = json_decode($res['body'], true);
        $this->assertIsArray($res['json'], 'not JSON: ' . $res['body']);
        return $res;
    }

    private function hireData(array $over = []): array
    {
        return $over + ['company' => 'TC', 'department' => '1', 'position' => '10', 'superior' => self::ADMIN, 'agency' => '1',
                        'hmo' => '1', 'hmoNumber' => 'HMO-9', 'classification' => '3', 'hired' => '2026-10-20',
                        'basic' => '18000', 'allowance' => '1500', 'hourly' => '103.45', 'idNumber' => ''];
    }

    // ------------------------------------------------------------ public registration

    public function testRegistrationStoresTheFullProfile(): void
    {
        $r = $this->submit();
        $this->assertTrue($r['ok'], $r['msg'] ?? '');
        $a = $this->row("SELECT * FROM applicants WHERE id = ?", [$r['id']]);
        $this->assertSame(['Maria', 'Santos', '1998-03-14', 'Single', '09171234567', 'San Roque', '34-5678901-2', 'new'],
            [$a['FirstName'], $a['LastName'], $a['BirthDate'], $a['CivilStatus'], $a['Mobile'], $a['Barangay'], $a['SSS'], $a['Stage']]);
        $this->assertNotNull($a['ConsentAt']);
        $this->assertSame('203.0.113.5', $a['IP']);
        $this->assertNotNull(apl_resume_path($a['ResumeKey']), 'resume stored under a random name');
        $this->assertSame('My Resume.pdf', $a['ResumeName']);
        $this->assertCount(3, $this->rows("SELECT id FROM applicant_education WHERE ApplicantID = ?", [$r['id']]));
        $this->assertSame('Mother', $this->row("SELECT Relationship FROM applicant_family WHERE ApplicantID = ?", [$r['id']])['Relationship']);
        $this->assertArrayNotHasKey('apl_pub', $_SESSION, 'one registration per form load');
    }

    public function testSpamGuards(): void
    {
        $_SESSION['apl_pub'] = self::PUB;
        $_SESSION['apl_pub_at'] = time() - 60;
        $bad = apl_submit(self::db(), $this->form(['token' => 'forged']), $this->pdf(), '203.0.113.5', false);
        $this->assertSame(419, $bad['code'], 'token');

        $_SESSION['apl_pub_at'] = time();
        $fast = apl_submit(self::db(), $this->form(), $this->pdf(), '203.0.113.5', false);
        $this->assertFalse($fast['ok'], 'filled in instantly');

        $hp = $this->submit(['website' => 'http://spam.example']);
        $this->assertTrue($hp['ok']);
        $this->assertSame(0, $hp['id'], 'honeypot: pretend, keep nothing');
        $this->assertCount(0, $this->rows("SELECT id FROM applicants"));

        for ($i = 1; $i <= APL_PER_IP_HOUR; $i++) {
            $this->assertTrue($this->submit(['email' => "p$i@example.com", 'mobile' => "0918000000$i"])['ok']);
        }
        $limited = $this->submit(['email' => 'p9@example.com', 'mobile' => '09180000009']);
        $this->assertSame(429, $limited['code'], 'per-address hourly limit');
        $this->assertTrue($this->submit(['email' => 'p9@example.com', 'mobile' => '09180000009'], null, '198.51.100.7')['ok'], 'another address is fine');
    }

    public function testRegistrationIsValidated(): void
    {
        $cases = [
            'consent'        => [['consent' => ''], 'Data Privacy'],
            'email'          => [['email' => 'not-an-email'], 'email'],
            'birth date'     => [['birthDate' => '2020-01-01'], 'date of birth'],
            'gender'         => [['gender' => 'Robot'], 'gender'],
            'civil status'   => [['civilStatus' => 'Complicated'], 'civil status'],
            'barangay'       => [['barangay' => ''], 'barangay'],
            'id format'      => [['sss' => '12$%^&*'], 'SSS'],
            'school years'   => [['edu' => ['Tertiary' => ['from' => '2018', 'to' => '2014']]], 'school years'],
            'relationship'   => [['fam' => [['relationship' => 'Boss']]], 'relationship'],
            'emergency'      => [['fam' => [['emergency' => '']]], 'emergency contact'],
            'no school'      => [['edu' => ['Secondary' => ['school' => ''], 'Tertiary' => ['school' => '']]], 'school'],
            'markup'         => [['religion' => 'Catholic<img src=x onerror=alert(1)>'], '< and >'],
            'nested markup'  => [['fam' => [['address' => '<script>x</script>']]], '< and >'],
        ];
        foreach ($cases as $what => [$over, $needle]) {
            $r = $this->submit($over);
            $this->assertFalse($r['ok'], $what);
            $this->assertStringContainsStringIgnoringCase($needle, $r['msg'], $what);
        }
        $this->assertCount(0, $this->rows("SELECT id FROM applicants"), 'nothing saved');
    }

    public function testSpamContentIsRefused(): void
    {
        $cases = [
            'link in a field'     => [['religion' => 'see www.cheap-pills.com'], 'web links'],
            'link with scheme'    => [['edu' => ['Tertiary' => ['school' => 'http://spam.example']]], 'web links'],
            'bot name'            => [['firstName' => 'xX_bot42'], 'letters only'],
            'odd suffix'          => [['suffix' => 'Esq123'], 'suffix'],
            'throwaway email'     => [['email' => 'a@mailinator.com'], 'temporary'],
            'subdomain throwaway' => [['email' => 'a@x.yopmail.com'], 'temporary'],
            'not a PH mobile'     => [['mobile' => '12345678'], 'mobile'],
            'family number'       => [['fam' => [['contact' => '435435435']]], '11-digit mobile'],
            'family one name'     => [['fam' => [['name' => 'ert']]], 'first and last name'],
            'family bot name'     => [['fam' => [['name' => 'Ana 123']]], 'letters only'],
        ];
        foreach ($cases as $what => [$over, $needle]) {
            $r = $this->submit($over);
            $this->assertFalse($r['ok'], $what);
            $this->assertStringContainsStringIgnoringCase($needle, $r['msg'], $what);
        }
        $ok = $this->submit(['firstName' => 'José María', 'lastName' => "O'Brien-Dela Peña", 'suffix' => 'Jr.', 'mobile' => '+63 917-123-4567']);
        $this->assertTrue($ok['ok'], 'real names with accents, apostrophes and hyphens; +63 format: ' . ($ok['msg'] ?? ''));
        $this->assertSame('09171234567', $this->row("SELECT Mobile FROM applicants WHERE id = ?", [$ok['id']])['Mobile'], 'stored in one format');
    }

    public function testRepeatsUnderAnotherEmailAreRefused(): void
    {
        $this->assertTrue($this->submit(['email' => 'one@example.com'], $this->pdf('same-file'))['ok']);
        $r = $this->submit(['email' => 'two@example.com', 'mobile' => '0917 999 0000'], $this->pdf('same-file'), '198.51.100.20');
        $this->assertStringContainsString('resume was already sent', $r['msg'], 'the same resume file');
        $r = $this->submit(['email' => 'three@example.com'], null, '198.51.100.21');
        $this->assertStringContainsString('mobile number', $r['msg'], 'the same mobile number');
    }

    public function testFormsExpire(): void
    {
        $_SESSION['apl_pub'] = self::PUB;
        $_SESSION['apl_pub_at'] = time() - APL_FORM_TTL - 5;
        $r = apl_submit(self::db(), $this->form(), $this->pdf(), '203.0.113.5', false);
        $this->assertSame(419, $r['code']);
        $_SESSION['apl_pub_at'] = time() - 10;   // under the 20-second minimum
        $this->assertFalse(apl_submit(self::db(), $this->form(), $this->pdf(), '203.0.113.5', false)['ok']);
    }

    public function testOnlyRealPdfOrWordResumesAreAccepted(): void
    {
        $tmp = tempnam(sys_get_temp_dir(), 'apl');
        file_put_contents($tmp, "<html><body>not really a resume</body></html>");   // (a real web shell trips the antivirus)
        $this->files[] = $tmp;
        $php = $this->submit([], ['name' => 'cv.php', 'tmp_name' => $tmp, 'size' => filesize($tmp), 'error' => UPLOAD_ERR_OK]);
        $this->assertStringContainsString('PDF or Word', $php['msg']);
        $fake = $this->submit([], ['name' => 'cv.pdf', 'tmp_name' => $tmp, 'size' => filesize($tmp), 'error' => UPLOAD_ERR_OK]);
        $this->assertFalse($fake['ok'], 'a renamed script is refused');
        $none = $this->submit([], ['error' => UPLOAD_ERR_NO_FILE]);
        $this->assertStringContainsString('resume', $none['msg']);
        $this->assertFileExists(dirname(__DIR__) . '/uploads/applicants/.htaccess', 'no web access to the folder');
    }

    public function testThePhotoIsRequiredAndRedrawn(): void
    {
        $files = function () { return count(glob(dirname(__DIR__) . '/uploads/applicants/*/*/*') ?: []); };
        $before = $files();
        $none = $this->submit([], null, '203.0.113.5', ['error' => UPLOAD_ERR_NO_FILE]);
        $this->assertStringContainsString('photo', $none['msg']);
        $tmp = tempnam(sys_get_temp_dir(), 'apl');
        file_put_contents($tmp, "<html><body>not a picture</body></html>");
        $this->files[] = $tmp;
        $fake = $this->submit([], null, '203.0.113.5', ['name' => 'photo.jpg', 'tmp_name' => $tmp, 'size' => filesize($tmp), 'error' => UPLOAD_ERR_OK]);
        $this->assertStringContainsString('photo', $fake['msg'], 'a renamed file is refused');
        $tiny = $this->submit([], null, '203.0.113.5', $this->jpeg(120, 90));
        $this->assertFalse($tiny['ok'], 'too small to be a camera photo');
        $this->assertCount(0, $this->rows("SELECT id FROM applicants"), 'nothing saved');
        $this->assertSame($before, $files(), 'no resume or photo kept for a refused application');

        $r = $this->submit();
        $this->assertTrue($r['ok'], $r['msg'] ?? '');
        $path = apl_photo_path($this->row("SELECT PhotoKey FROM applicants WHERE id = ?", [$r['id']])['PhotoKey']);
        $this->assertNotNull($path, 'stored under a random name');
        $info = getimagesize($path);
        $this->assertSame([APL_PHOTO_PX, APL_PHOTO_PX, IMAGETYPE_JPEG], [$info[0], $info[1], $info[2]], 'centre square, re-drawn as JPEG');

        $this->assertTrue($this->act('detail', ['id' => $r['id']])['json']['applicant']['hasPhoto']);
        $this->assertSame(403, $this->request('query/applicant-photo.php', ['id' => $r['id']], [], $this->asEmployee())['status'], 'HR only');
        $this->assertSame(401, $this->request('query/applicant-photo.php', ['id' => $r['id']])['status']);
        $img = $this->request('query/applicant-photo.php', ['id' => $r['id']], [], $this->hr());
        $this->assertSame(200, $img['status']);
        $this->assertSame("\xFF\xD8", substr($img['body'], 0, 2), 'a JPEG');
    }

    public function testDuplicateAndClosedOpenings(): void
    {
        self::db()->exec("INSERT INTO job_openings (id, Title, IsOpen) VALUES (1, 'Agent', 1), (2, 'Old role', 0)");
        $this->assertTrue($this->submit(['opening' => '1'])['ok']);
        $this->assertStringContainsString('already applied', $this->submit(['opening' => '1'], null, '198.51.100.9')['msg']);
        $this->assertStringContainsString('no longer open', $this->submit(['opening' => '2', 'email' => 'x@example.com'])['msg']);
    }

    public function testPublicPageAndEndpoint(): void
    {
        self::db()->exec("INSERT INTO job_openings (Title, Location, IsOpen) VALUES ('Customer Service <Rep>', 'Pasig', 1), ('Hidden', '', 0)");
        $page = $this->request('apply.php');
        $this->assertSame(200, $page['status']);
        $this->assertNoPhpErrors($page['body']);
        $this->assertStringContainsString('Customer Service &lt;Rep&gt;', $page['body'], 'escaped');
        $this->assertStringNotContainsString('Hidden', $page['body'], 'closed openings are not listed');
        $this->assertStringContainsString('name="website"', $page['body'], 'honeypot present');
        $this->assertStringContainsString('name="fam[0][relationship]"', $page['body']);
        $this->assertStringContainsString('id="aplCam"', $page['body'], 'the camera photo step');
        $this->assertStringNotContainsString('name="photo"', $page['body'], 'live capture only: no file picker for the photo');
        $this->assertStringNotContainsString('<script>window', $page['body'], 'no inline script (strict CSP)');
        $this->assertStringNotContainsString('login.php', $page['body'], 'applicants are not pointed to the employee sign-in');

        $res = $this->request('query/apply-action.php', [], ['token' => 'nope', 'firstName' => 'X']);
        $this->assertSame(419, $res['status']);
        $this->assertCount(0, $this->rows("SELECT id FROM applicants"));
    }

    public function testClientAddressTrustsCloudflareOnlyFromItsOwnRanges(): void
    {
        $_SERVER['REMOTE_ADDR'] = '203.0.113.5';
        $_SERVER['HTTP_CF_CONNECTING_IP'] = '1.2.3.4';
        $this->assertSame('203.0.113.5', apl_client_ip(), 'spoofed header ignored');
        $_SERVER['REMOTE_ADDR'] = '172.68.10.20';   // inside 172.64.0.0/13
        $this->assertSame('1.2.3.4', apl_client_ip());
        unset($_SERVER['HTTP_CF_CONNECTING_IP'], $_SERVER['REMOTE_ADDR']);
    }

    // ------------------------------------------------------------ HR pipeline

    public function testPipelineIsGatedAndTokenProtected(): void
    {
        $this->submit();
        $this->assertSame(403, $this->act('list', [], $this->asEmployee() + ['apl_csrf' => self::TOKEN])['status']);
        $res = $this->request('query/applicants-action.php', [], ['action' => 'list', 'token' => 'x'], $this->hr());
        $this->assertSame(419, $res['status']);
        $this->assertSame(401, $this->request('query/applicants-action.php', [], ['action' => 'list'])['status']);
        $this->assertSame(403, $this->request('query/applicant-resume.php', ['id' => 1], [], $this->asEmployee())['status']);
        $page = $this->request('applicants.php', [], [], $this->asEmployee());
        $this->assertStringNotContainsString('applicants.js', $page['body'], 'HR only');
    }

    public function testStagesNotesAndRehireWarning(): void
    {
        // a former employee with the same name + birth date, not eligible for rehire
        $db = self::db();
        $db->exec("INSERT INTO employees (EmpID, EmpFN, EmpLN, EmpStatusID, PosID) VALUES ('TC-0099', 'Maria', 'Santos', 5, 10)");
        $db->exec("INSERT INTO empprofiles (EmpID, EmpDOB) VALUES ('TC-0099', '1998-03-14')");
        $db->exec("INSERT INTO emp_separations (EmpID, Type, LastDay, Reason, RehireEligible) VALUES ('TC-0099', 'awol', '2025-05-01', 'AWOL', 'no')");
        $id = $this->submit()['id'];

        $list = $this->act('list', [])['json']['applicants'];
        $this->assertSame('no', $list[0]['rehire']);
        $d = $this->act('detail', ['id' => $id])['json']['applicant'];
        $this->assertTrue($d['matches'][0]['strong']);
        $this->assertSame('Mother', $d['family'][0]['Relationship']);

        $this->assertSame(200, $this->act('stage', ['id' => $id, 'stage' => 'interview', 'notes' => 'Initial call OK'])['status']);
        $this->assertSame(422, $this->act('stage', ['id' => $id, 'stage' => 'hired'])['status'], 'hired only through Hire');
        $this->assertSame('interview', $this->row("SELECT Stage FROM applicants WHERE id = ?", [$id])['Stage']);
        $this->assertSame(422, $this->act('delete', ['id' => $id])['status'], 'active applications are kept');
    }

    public function testDeletingAClosedApplicationRemovesItsData(): void
    {
        $id = $this->submit()['id'];
        $keys = $this->row("SELECT ResumeKey, PhotoKey FROM applicants WHERE id = ?", [$id]);
        $path = apl_resume_path($keys['ResumeKey']);
        $photo = apl_photo_path($keys['PhotoKey']);
        $this->assertNotNull($photo);
        $this->act('stage', ['id' => $id, 'stage' => 'rejected', 'notes' => 'Not a fit']);
        $this->assertSame(200, $this->act('delete', ['id' => $id])['status']);
        foreach (['applicants' => 'id', 'applicant_family' => 'ApplicantID', 'applicant_education' => 'ApplicantID', 'applicant_events' => 'ApplicantID'] as $t => $c) {
            $this->assertCount(0, $this->rows("SELECT * FROM $t WHERE $c = ?", [$id]), $t);
        }
        $this->assertFileDoesNotExist($path);
        $this->assertFileDoesNotExist($photo);
    }

    // ------------------------------------------------------------ hiring

    public function testHireCopiesTheRegistrationIntoTheEmployeeTables(): void
    {
        $id = $this->submit()['id'];
        $this->act('stage', ['id' => $id, 'stage' => 'offer', 'notes' => 'Offer accepted']);

        $res = $this->act('hire', ['id' => $id] + $this->hireData());
        $this->assertSame(200, $res['status'], $res['body']);
        $empId = $res['json']['empId'];
        $this->assertSame('TC-0002', $empId, 'next number after TC-0001');

        $e = $this->row("SELECT * FROM employees WHERE EmpID = ?", [$empId]);
        $this->assertSame(['Maria', 'Lopez', 'Santos', '10', '1'], [$e['EmpFN'], $e['EmpMN'], $e['EmpLN'], (string) $e['PosID'], (string) $e['EmpStatusID']]);
        $d = $this->row("SELECT * FROM empdetails WHERE EmpID = ?", [$empId]);
        $this->assertSame(['3', '3', self::ADMIN, '1', 'TC', '2026-10-20'],
            [(string) $d['EmpStatID'], (string) $d['EmpRoleID'], $d['EmpISID'], (string) $d['EmpdepID'], $d['EmpCompID'], $d['EmpDateHired']]);
        $this->assertSame('MSantos', $d['EmpUN']);
        $this->assertTrue(password_verify($res['json']['password'], $d['EmpPW']), 'temporary password works');
        $this->assertSame('1', (string) $d['must_change_pw'], 'must set their own password at first sign-in');
        $this->assertStringNotContainsString($res['json']['password'], json_encode($this->rows("SELECT * FROM dars")), 'never logged');
        $p = $this->row("SELECT * FROM empprofiles WHERE EmpID = ?", [$empId]);
        $this->assertSame(['12 Mabini St', 'San Roque', 'Pasig', '09171234567', '1998-03-14', 'Single', 'maria@example.com', '34-5678901-2', 'Acme Call Center', 'HMO-9'],
            [$p['EmpAddress1'], $p['EmpAddDis'], $p['EmpAddCity'], $p['EmpPhone'], $p['EmpDOB'], $p['EmpCS'], $p['EmpEmail'], $p['EmpSSS'], $p['EmpPP'], $p['EmpHMONumber']]);
        $this->assertSame(['Primary', 'Secondary', 'Tertiary'], array_column($this->rows("SELECT Program FROM empeducationalbackground WHERE EmpID = ? ORDER BY EB_ID", [$empId]), 'Program'));
        $f = $this->row("SELECT * FROM fdetails WHERE FDetID = ?", [$empId]);
        $this->assertSame(['Ana Santos', 'Mother', '09181112222', 'Yes'], [$f['FName'], $f['FRel'], $f['FContact'], $f['FICE']]);
        $this->assertSame('18000', (string) (int) $this->row("SELECT EmpBasic FROM empdetails2 WHERE EmpID = ?", [$empId])['EmpBasic']);
        $this->assertNotNull($this->row("SELECT ARID FROM accessrights WHERE EmpID = ?", [$empId]));

        $a = $this->row("SELECT * FROM applicants WHERE id = ?", [$id]);
        $this->assertSame(['hired', $empId], [$a['Stage'], $a['EmpID']]);
        $ev = $this->row("SELECT * FROM emp_lifecycle_events WHERE EmpID = ? AND EventType = 'hired'", [$empId]);
        $this->assertSame('Trainee', $ev['ToVal']);

        $pic = dirname(__DIR__) . '/assets/images/profiles/' . $empId . '.jpg';
        $this->files[] = $pic;
        $this->assertSame('assets/images/profiles/' . $empId . '.jpg', $p['EmpPPath']);
        $this->assertFileEquals(apl_photo_path($a['PhotoKey']), $pic, 'the camera photo becomes the profile picture');

        $this->assertSame(422, $this->act('hire', ['id' => $id] + $this->hireData())['status'], 'only once');
    }

    public function testHireIsValidatedAndNeedsTheEnrollRight(): void
    {
        $id = $this->submit()['id'];
        $this->assertSame(422, $this->act('hire', ['id' => $id] + $this->hireData(['position' => '11']))['status'], 'position of another department');
        $this->assertSame(422, $this->act('hire', ['id' => $id] + $this->hireData(['basic' => 'lots']))['status']);
        $this->assertSame(422, $this->act('hire', ['id' => $id] + $this->hireData(['superior' => self::EMP]))['status'], 'a rank-and-file superior');
        $this->assertCount(2, $this->rows("SELECT EmpID FROM employees"), 'nothing created');

        self::db()->exec("UPDATE accessrights SET eemployee = 1 WHERE EmpID = '" . self::ADMIN . "'");
        $this->assertSame(403, $this->act('hire', ['id' => $id] + $this->hireData())['status']);
        $this->assertSame('new', $this->row("SELECT Stage FROM applicants WHERE id = ?", [$id])['Stage']);
    }

    public function testNextEmployeeNumberNeverReusesOne(): void
    {
        self::db()->exec("INSERT INTO empdetails (EmpID, EmpUN) VALUES ('TC-0041', 'x41'), ('TC-007', 'x7')");
        $this->assertSame('TC-0042', apl_next_empid(self::db(), 'TC'));
    }

    public function testCitizenshipAndReligionSuggestionsAreSafe(): void
    {
        // values in empprofiles can come from the public form (copied on hire); the old autocomplete printed them raw
        self::db()->exec("UPDATE empprofiles SET EmpReligion = 'Cath<img src=x onerror=alert(1)>' WHERE EmpID = '" . self::EMP . "'");
        $res = $this->request('query/query-maintenance.php', ['religion' => ''], ['data' => 'Cath'], $this->asAdmin());
        $this->assertNoPhpErrors($res['body']);
        $this->assertStringContainsString('&lt;img', $res['body']);
        $this->assertStringNotContainsString('<img', $res['body']);

        // the typed text used to be pasted into the SQL
        $inj = $this->request('query/query-maintenance.php', ['citizenship' => ''], ['data' => "zz' OR '1'='1"], $this->asAdmin());
        $this->assertNoPhpErrors($inj['body']);
        $this->assertStringNotContainsString('Filipino', $inj['body']);
        $this->assertStringContainsString('Filipino', $this->request('query/query-maintenance.php', ['citizenship' => ''], ['data' => 'Fil'], $this->asAdmin())['body']);
    }

    public function testHrScreenRenders(): void
    {
        $res = $this->request('applicants.php', [], [], $this->asAdmin());
        $this->assertSame(200, $res['status']);
        $this->assertNoPhpErrors($res['body']);
        $this->assertStringContainsString('applicants.js', $res['body']);
        $this->assertStringContainsString('href="applicants"', $this->request('idcard.php', [], [], $this->asAdmin())['body'], 'menu entry');
    }
}
