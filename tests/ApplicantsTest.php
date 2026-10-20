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
 *   includes/applicants-mail.php    emails to the applicant (written to WEDO_MAIL_LOG, never sent)
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
        require_once dirname(__DIR__) . '/includes/applicants-mail.php';
        require_once dirname(__DIR__) . '/includes/applicants-contract.php';
    }

    protected function setUp(): void
    {
        parent::setUp();
        $db = self::db();
        $db->exec("INSERT IGNORE INTO empstatus (EmpStatID, EmpCODE, EmpStatDesc) VALUES (3, 'TR', 'Trainee')");
        $db->exec("UPDATE empstatus SET EmpCODE = 'IC', EmpStatDesc = 'Independent Contractor' WHERE EmpStatID = 2");   // as on the servers
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
        foreach (self::db()->query("SELECT FileKey FROM applicant_files")->fetchAll(\PDO::FETCH_COLUMN) as $k) {
            $p = apl_photo_path($k);
            if ($p) { @unlink($p); }
        }
        foreach ($this->files as $f) { @unlink($f); }
        putenv('BREVO_API_KEY');
        putenv('MAIL_FROM');
        @unlink($GLOBALS['WEDO_MAIL_LOG']);
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

    /** Brevo "set up" with a fake key; messages land in WEDO_MAIL_LOG. */
    private function mailOn(bool $on = true): void
    {
        @unlink($GLOBALS['WEDO_MAIL_LOG']);
        putenv('BREVO_API_KEY=test-key');
        putenv('MAIL_FROM=' . ($on ? 'hr@example.com' : 'not-an-email'));
    }

    /** @return array<int, array> the emails "sent" so far */
    private function sent(): array
    {
        $f = $GLOBALS['WEDO_MAIL_LOG'];
        return is_file($f) ? array_map(fn ($l) => json_decode($l, true), file($f, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES)) : [];
    }

    /** Walk an application forward through the stages, in order, up to $to. */
    private function advance(int $id, string $to): void
    {
        $now = $this->row("SELECT Stage FROM applicants WHERE id = ?", [$id])['Stage'];
        foreach (array_slice(APL_PIPELINE, array_search($now, APL_PIPELINE, true) + 1) as $s) {
            $this->assertSame(200, $this->act('stage', ['id' => $id, 'stage' => $s, 'notes' => ''])['status'], $s);
            if ($s === $to) { return; }
        }
    }

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
        return $over + ['company' => 'TC', 'department' => '1', 'position' => '10', 'superior' => self::ADMIN,
                        'classification' => '3', 'hired' => '2026-10-20',
                        'basic' => '18000', 'allowance' => '1500', 'hourly' => '103.45', 'idNumber' => ''];
    }

    // ------------------------------------------------------------ public registration

    public function testRegistrationStoresTheFullProfile(): void
    {
        $r = $this->submit();
        $this->assertTrue($r['ok'], $r['msg'] ?? '');
        $a = $this->row("SELECT * FROM applicants WHERE id = ?", [$r['id']]);
        $this->assertSame(['Maria', 'Santos', '1998-03-14', 'Single', '09171234567', 'San Roque', '34-5678901-2', 'screening'],
            [$a['FirstName'], $a['LastName'], $a['BirthDate'], $a['CivilStatus'], $a['Mobile'], $a['Barangay'], $a['SSS'], $a['Stage']]);
        $this->assertNotNull($a['ConsentAt']);
        $ev = $this->row("SELECT FromStage, ToStage, Notes FROM applicant_events WHERE ApplicantID = ?", [$r['id']]);
        $this->assertSame([null, 'screening', 'Registered online'], [$ev['FromStage'], $ev['ToStage'], $ev['Notes']], 'Screening is the first step');
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

    public function testThePhotoIsOptionalAndRedrawn(): void
    {
        $none = $this->submit(['email' => 'nophoto@example.com', 'mobile' => '0917 555 0000'], null, '198.51.100.30', ['error' => UPLOAD_ERR_NO_FILE]);
        $this->assertTrue($none['ok'], 'no photo is fine: ' . ($none['msg'] ?? ''));
        $this->assertNull($this->row("SELECT PhotoKey FROM applicants WHERE id = ?", [$none['id']])['PhotoKey']);
        $this->assertFalse($this->act('detail', ['id' => $none['id']])['json']['applicant']['hasPhoto']);

        $files = function () { return count(glob(dirname(__DIR__) . '/uploads/applicants/*/*/*') ?: []); };
        $before = $files();
        $tmp = tempnam(sys_get_temp_dir(), 'apl');
        file_put_contents($tmp, "<html><body>not a picture</body></html>");
        $this->files[] = $tmp;
        $fake = $this->submit([], null, '203.0.113.5', ['name' => 'photo.jpg', 'tmp_name' => $tmp, 'size' => filesize($tmp), 'error' => UPLOAD_ERR_OK]);
        $this->assertStringContainsString('photo', $fake['msg'], 'a renamed file is refused');
        $tiny = $this->submit([], null, '203.0.113.5', $this->jpeg(120, 90));
        $this->assertFalse($tiny['ok'], 'too small to be a camera photo');
        $this->assertCount(1, $this->rows("SELECT id FROM applicants"), 'a bad photo is refused, not dropped silently');
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

        $this->advance($id, 'exam');
        $this->assertSame(200, $this->act('stage', ['id' => $id, 'stage' => 'interview', 'notes' => 'Initial call OK'])['status']);
        $this->assertSame(422, $this->act('stage', ['id' => $id, 'stage' => 'hired'])['status'], 'hired only through Hire');
        $this->assertSame('interview', $this->row("SELECT Stage FROM applicants WHERE id = ?", [$id])['Stage']);
        $this->assertSame(422, $this->act('delete', ['id' => $id])['status'], 'active applications are kept');
    }

    public function testStagesGoInOrder(): void
    {
        $id = $this->submit()['id'];
        $this->assertSame(['screening', 'exam', 'interview', 'final', 'offer'], APL_PIPELINE, 'Screening, Exam, Initial + Final interview, Job offer');
        $this->assertSame(422, $this->act('stage', ['id' => $id, 'stage' => 'offer', 'notes' => ''])['status'], 'no jumping to the offer');
        $this->assertSame(422, $this->act('stage', ['id' => $id, 'stage' => 'new', 'notes' => ''])['status'], 'New is no longer a stage');
        $this->assertSame(['screening', 'exam', 'rejected', 'withdrawn'], $this->act('detail', ['id' => $id])['json']['applicant']['allowedStages']);

        $exam = $this->act('stage', ['id' => $id, 'stage' => 'interview', 'notes' => '']);
        $this->assertSame(422, $exam['status'], 'the exam comes before the initial interview');
        $this->assertStringContainsString('Exam', $exam['json']['msg']);
        $this->advance($id, 'interview');
        $this->assertSame(200, $this->act('stage', ['id' => $id, 'stage' => 'screening', 'notes' => 'Wrong stage'])['status'], 'back is fine');
        $this->advance($id, 'final');
        $this->assertSame(200, $this->act('stage', ['id' => $id, 'stage' => 'rejected', 'notes' => 'Not a fit'])['status'], 'not selected at any point');

        // reopened where it was (Final interview) or earlier, not further
        $this->assertSame(['screening', 'exam', 'interview', 'final', 'rejected', 'withdrawn'], $this->act('detail', ['id' => $id])['json']['applicant']['allowedStages']);
        $far = $this->act('stage', ['id' => $id, 'stage' => 'offer', 'notes' => '']);
        $this->assertSame(422, $far['status']);
        $this->assertStringContainsString('Final interview', $far['json']['msg']);
        $this->assertSame(200, $this->act('stage', ['id' => $id, 'stage' => 'final', 'notes' => 'Second look'])['status']);
        $this->assertSame('final', $this->row("SELECT Stage FROM applicants WHERE id = ?", [$id])['Stage']);
    }

    public function testTheApplicantIsEmailedAtTheRightMoments(): void
    {
        $this->mailOn();
        $id = $this->submit()['id'];
        $this->assertTrue(apl_mail_after_submit(self::db(), $id, 'APL-000777'));
        $got = $this->sent();
        $this->assertCount(1, $got);
        $this->assertSame('maria@example.com', $got[0]['to'][0]['email']);
        $this->assertStringContainsString('APL-000777', $got[0]['subject']);

        // ticking "email" on a stage that doesn't send one does nothing
        $res = $this->act('stage', ['id' => $id, 'stage' => 'exam', 'notes' => '', 'email' => '1', 'emailMessage' => 'x']);
        $this->assertSame(200, $res['status'], $res['body']);
        $this->assertNull($res['json']['mailed']);
        $this->assertCount(1, $this->sent());

        $this->advance($id, 'final');
        $res = $this->act('stage', ['id' => $id, 'stage' => 'offer', 'notes' => 'Offer approved by the manager',
                                    'email' => '1', 'emailMessage' => "Start Monday 8 AM\nBring 2 IDs <b>"]);
        $this->assertTrue($res['json']['mailed'], $res['body']);
        $offer = $this->sent()[1];
        $this->assertStringContainsString('Job offer', $offer['subject']);
        $this->assertStringContainsString('Start Monday 8 AM<br>', $offer['htmlContent']);
        $this->assertStringContainsString('&lt;b&gt;', $offer['htmlContent'], 'HR text is escaped');
        $this->assertStringNotContainsString('approved by the manager', $offer['htmlContent'], 'the internal note is never emailed');
        $notes = array_column($res['json']['applicant']['events'], 'notes');
        $this->assertStringContainsString('Applicant emailed (Job offer)', implode(' | ', $notes), 'shows in Activity');

        $hire = $this->act('hire', ['id' => $id, 'welcome' => '1'] + $this->hireData());
        $this->assertSame(200, $hire['status'], $hire['body']);
        $this->assertTrue($hire['json']['mailed']);
        $welcome = $this->sent()[2];
        $this->assertStringContainsString($hire['json']['username'], $welcome['htmlContent']);
        $this->assertStringContainsString($hire['json']['empId'], $welcome['htmlContent']);
        $this->assertStringNotContainsString($hire['json']['password'], json_encode($welcome), 'the password is never emailed');
    }

    public function testEmailSetupCheckAndTestEmail(): void
    {
        $this->mailOn(false);
        $st = $this->act('mail_status', [])['json']['mail'];
        $this->assertFalse($st['enabled']);
        $this->assertContains('bad', array_column($st['checks'], 'level'));
        $off = $this->act('mail_test', ['to' => 'hr@example.com'])['json'];
        $this->assertFalse($off['sent']);
        $this->assertCount(0, $this->sent());

        $this->mailOn();
        $this->assertTrue($this->act('mail_status', [])['json']['mail']['enabled']);
        $this->assertSame(422, $this->act('mail_test', ['to' => 'nope'])['status']);
        $on = $this->act('mail_test', ['to' => 'boss@example.com'])['json'];
        $this->assertTrue($on['sent'], $on['error'] ?? '');
        $this->assertSame('boss@example.com', $this->sent()[0]['to'][0]['email']);
        $this->assertSame(403, $this->act('mail_test', ['to' => 'x@example.com'], $this->asEmployee() + ['apl_csrf' => self::TOKEN])['status'], 'HR only');
        $this->assertStringContainsString('id="aplMailBtn"', $this->request('applicants.php', [], [], $this->asAdmin())['body']);
    }

    public function testNotSelectedEmailAndNoEmailWhenNotSetUp(): void
    {
        $this->mailOn(false);   // no valid sender: email is off
        $id = $this->submit()['id'];
        $this->assertFalse(apl_mail_after_submit(self::db(), $id, 'APL-1'));
        $res = $this->act('stage', ['id' => $id, 'stage' => 'rejected', 'notes' => '', 'email' => '1']);
        $this->assertSame(200, $res['status']);
        $this->assertNull($res['json']['mailed']);
        $this->assertCount(0, $this->sent());
        $this->assertStringNotContainsString('"canMail":true', $this->request('applicants.php', [], [], $this->asAdmin())['body']);

        $this->mailOn();
        $this->assertStringContainsString('"canMail":true', $this->request('applicants.php', [], [], $this->asAdmin())['body']);
        $this->act('stage', ['id' => $id, 'stage' => 'screening', 'notes' => 'Reopened']);
        $res = $this->act('stage', ['id' => $id, 'stage' => 'rejected', 'notes' => '', 'email' => '1', 'emailMessage' => '']);
        $this->assertTrue($res['json']['mailed']);
        $this->assertStringContainsString('not to move forward', $this->sent()[0]['htmlContent']);
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
        $this->advance($id, 'offer');

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
        $this->assertSame(['', ''], [$d['AgencyID'], $d['HMO_ID']], 'agency and HMO are not asked on hire');
        $this->assertTrue(password_verify($res['json']['password'], $d['EmpPW']), 'temporary password works');
        $this->assertSame('1', (string) $d['must_change_pw'], 'must set their own password at first sign-in');
        $this->assertStringNotContainsString($res['json']['password'], json_encode($this->rows("SELECT * FROM dars")), 'never logged');
        $p = $this->row("SELECT * FROM empprofiles WHERE EmpID = ?", [$empId]);
        $this->assertSame(['12 Mabini St', 'San Roque', 'Pasig', '09171234567', '1998-03-14', 'Single', 'maria@example.com', '34-5678901-2', 'Acme Call Center', ''],
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
        $early = $this->act('hire', ['id' => $id] + $this->hireData());
        $this->assertSame(422, $early['status'], 'no hiring before the job offer');
        $this->assertStringContainsString('Job offer', $early['json']['msg']);
        $this->advance($id, 'final');
        $this->assertSame(422, $this->act('hire', ['id' => $id] + $this->hireData())['status'], 'still not at the job offer');
        $this->advance($id, 'offer');

        $this->assertSame(422, $this->act('hire', ['id' => $id] + $this->hireData(['position' => '11']))['status'], 'position of another department');
        $this->assertSame(422, $this->act('hire', ['id' => $id] + $this->hireData(['basic' => 'lots']))['status']);
        $this->assertSame(422, $this->act('hire', ['id' => $id] + $this->hireData(['superior' => self::EMP]))['status'], 'a rank-and-file superior');
        $reg = $this->act('hire', ['id' => $id] + $this->hireData(['classification' => '1']));
        $this->assertSame(422, $reg['status'], 'only Trainee or Independent Contractor');
        $this->assertStringContainsString('Independent Contractor', $reg['json']['msg']);
        $this->assertCount(2, $this->rows("SELECT EmpID FROM employees"), 'nothing created');

        self::db()->exec("UPDATE accessrights SET eemployee = 1 WHERE EmpID = '" . self::ADMIN . "'");
        $this->assertSame(403, $this->act('hire', ['id' => $id] + $this->hireData())['status']);
        $this->assertSame('offer', $this->row("SELECT Stage FROM applicants WHERE id = ?", [$id])['Stage']);
    }

    public function testHireOffersOnlyTraineeOrIndependentContractor(): void
    {
        $o = $this->act('hire_options', [])['json']['options'];
        $this->assertSame([['id' => 3, 'name' => 'Trainee'], ['id' => 2, 'name' => 'Independent Contractor']],
            array_map(fn ($c) => ['id' => (int) $c['id'], 'name' => $c['name']], $o['classes']), 'Trainee first (the default)');
        $this->assertArrayNotHasKey('agencies', $o);
        $this->assertArrayNotHasKey('hmos', $o);
        $this->assertSame(['trainee', 'independent-contractor'], [$o['contracts'][3]['kind'], $o['contracts'][2]['kind']]);
        $page = $this->request('applicants.php', [], [], $this->asAdmin())['body'];
        $this->assertStringNotContainsString('aplHAgency', $page);
        $this->assertStringNotContainsString('aplHHmo', $page);
        $this->assertStringContainsString('"pipeline":["screening","exam","interview","final","offer"]', $page);
    }

    public function testTheContractIsFilledInAndEmailedOnHire(): void
    {
        $this->mailOn();
        $id = $this->submit()['id'];
        $this->advance($id, 'offer');

        // the preview: the Independent Contractor agreement with the dialog's values, nothing saved
        $pre = $this->act('contract_preview', ['id' => $id] + $this->hireData(['classification' => '2']));
        $this->assertSame(200, $pre['status'], $pre['body']);
        $this->assertSame('Independent Contractor Agreement', $pre['json']['title']);
        foreach (['Maria Lopez Santos', '12 Mabini St, San Roque, Pasig', 'October 20, 2026', '₱18,000.00', '₱1,500.00', '₱103.45', '(given on hire)'] as $want) {
            $this->assertStringContainsString($want, $pre['json']['page'], $want);
        }
        $this->assertStringNotContainsString('{{', $pre['json']['page'], 'every placeholder filled in');
        $this->assertStringNotContainsString('<!--', $pre['json']['page'], 'the template notes stay out');
        $this->assertSame('offer', $this->row("SELECT Stage FROM applicants WHERE id = ?", [$id])['Stage'], 'a preview hires nobody');
        $this->assertSame(422, $this->act('contract_preview', ['id' => $id] + $this->hireData(['basic' => '']))['status'], 'needs the pay');
        $this->assertCount(0, $this->sent());

        $hire = $this->act('hire', ['id' => $id, 'contract' => '1'] + $this->hireData(['classification' => '2']));
        $this->assertSame(200, $hire['status'], $hire['body']);
        $this->files[] = dirname(__DIR__) . '/assets/images/profiles/' . $hire['json']['empId'] . '.jpg';
        $this->assertTrue($hire['json']['contractMailed']);
        $this->assertNull($hire['json']['mailed'], 'no welcome email unless ticked');
        $this->assertSame('2', (string) $this->row("SELECT EmpStatID FROM empdetails WHERE EmpID = ?", [$hire['json']['empId']])['EmpStatID']);
        $got = $this->sent();
        $this->assertCount(1, $got);
        $this->assertSame('maria@example.com', $got[0]['to'][0]['email']);
        $this->assertStringContainsString('Independent Contractor Agreement', $got[0]['subject']);
        $this->assertStringContainsString($hire['json']['empId'], $got[0]['htmlContent'], 'the new employee no. is on it');
        $this->assertStringNotContainsString($hire['json']['password'], json_encode($got[0]), 'never the password');
        $this->assertSame('Independent-Contractor-Agreement-Maria-Lopez-Santos.html', $got[0]['attachment'][0]['name']);
        $this->assertStringContainsString('<!doctype html>', base64_decode($got[0]['attachment'][0]['content']), 'a printable copy');
        $notes = implode(' | ', array_column($hire['json']['applicant']['events'], 'notes'));
        $this->assertStringContainsString('Contract emailed (Independent Contractor Agreement)', $notes, 'shows in Activity');
    }

    public function testTraineeGetsTheTrainingAgreementOnlyWhenTicked(): void
    {
        $this->mailOn();
        $c = apl_contract_render(['FirstName' => 'Ana', 'MiddleName' => '', 'LastName' => 'Cruz <b>', 'Suffix' => '', 'Street' => '1 A St',
                                  'Barangay' => '', 'City' => 'Pasig', 'Province' => '', 'Zip' => '', 'Country' => 'Philippines'],
            ['company' => 'Test & Co', 'department' => 'Ops', 'position' => 'Bookkeeper', 'superiorName' => '', 'className' => 'Trainee',
             'contract' => 'trainee', 'hired' => '2026-11-03', 'money' => ['basic' => '15000', 'allowance' => '0', 'hourly' => '86.21']], 'TC-0009');
        $this->assertTrue($c['ok']);
        $this->assertSame('Training Agreement', $c['title']);
        $this->assertStringContainsString('Cruz &lt;b&gt;', $c['html'], 'values are escaped');
        $this->assertStringContainsString('Test &amp; Co', $c['html']);
        $this->assertStringContainsString('November 3, 2026', $c['html']);
        $this->assertStringContainsString('the department head', $c['html'], 'no superior chosen');
        $this->assertFalse(apl_contract_render([], ['contract' => 'regular'])['ok'], 'no contract for other classifications');

        $id = $this->submit()['id'];
        $this->advance($id, 'offer');
        $hire = $this->act('hire', ['id' => $id] + $this->hireData());
        $this->assertSame(200, $hire['status'], $hire['body']);
        $this->files[] = dirname(__DIR__) . '/assets/images/profiles/' . $hire['json']['empId'] . '.jpg';
        $this->assertNull($hire['json']['contractMailed'], 'not ticked: not sent');
        $this->assertCount(0, $this->sent());
    }

    public function testOlderHistoryStillNamesTheNewStage(): void
    {
        $id = $this->submit()['id'];
        self::db()->exec("UPDATE applicant_events SET ToStage = 'new' WHERE ApplicantID = $id");
        self::db()->prepare("INSERT INTO applicant_events (ApplicantID, FromStage, ToStage, Notes, ByEmpID) VALUES (?, 'new', 'screening', NULL, ?)")
            ->execute([$id, self::ADMIN]);
        $ev = $this->act('detail', ['id' => $id])['json']['applicant']['events'];
        $this->assertSame(['New', 'New', 'Screening'], [$ev[0]['toLabel'], $ev[1]['fromLabel'], $ev[1]['toLabel']]);
    }

    public function testApplicantTimesAreManilaTime(): void
    {
        $c = $GLOBALS['WEDO_TEST_DB'];
        $pdo = new \PDO('mysql:host=' . $c['host'] . ';dbname=' . $c['name'] . ';charset=utf8mb4', $c['user'], $c['pass']);
        $pdo->exec("SET time_zone = '+00:00'");   // a server whose MySQL runs on UTC
        apl_db_clock($pdo);
        $db = new \DateTime((string) $pdo->query("SELECT NOW()")->fetchColumn(), new \DateTimeZone('Asia/Manila'));
        $this->assertLessThan(120, abs($db->getTimestamp() - time()), 'NOW() is Manila wall-clock time');
        $this->assertStringContainsString("+ '+08:00'", file_get_contents(dirname(__DIR__) . '/assets/js/applicants.js'), '"x h ago" reads them as Manila time');
        foreach (['applicants.php', 'query/applicants-action.php', 'query/apply-action.php'] as $f) {
            $this->assertStringContainsString('apl_db_clock(', file_get_contents(dirname(__DIR__) . '/' . $f), $f);
        }
    }

    /** A picture of an exam sheet: $type png / jpeg, or junk with an image name. */
    private function examImage(string $type = 'png', int $w = 1200, int $h = 900): array
    {
        $tmp = tempnam(sys_get_temp_dir(), 'apl');
        if ($type === 'junk') {
            file_put_contents($tmp, 'just some text, not a picture');
        } else {
            $img = imagecreatetruecolor($w, $h);
            imagefilledrectangle($img, 0, 0, $w, $h, imagecolorallocate($img, 250, 250, 245));
            $type === 'png' ? imagepng($img, $tmp) : imagejpeg($img, $tmp, 90);
        }
        $this->files[] = $tmp;
        return ['name' => 'exam.' . ($type === 'jpeg' ? 'jpg' : 'png'), 'tmp_name' => $tmp, 'size' => filesize($tmp), 'error' => UPLOAD_ERR_OK];
    }

    public function testExamResultPicturesAreKeptAndViewable(): void
    {
        $db = self::db();
        $id = $this->submit()['id'];
        $early = apl_add_exam_files($db, $id, [$this->examImage()], '', self::ADMIN, false);
        $this->assertFalse($early['ok'], 'not before the exam stage');
        $this->assertStringContainsString('Exam', $early['msg']);
        $this->assertFalse($this->act('detail', ['id' => $id])['json']['applicant']['examOpen']);

        $this->advance($id, 'exam');
        $this->assertTrue($this->act('detail', ['id' => $id])['json']['applicant']['examOpen']);
        $bad = apl_add_exam_files($db, $id, [$this->examImage(), $this->examImage('junk')], '', self::ADMIN, false);
        $this->assertFalse($bad['ok'], 'not a picture');
        $this->assertCount(0, $this->rows("SELECT id FROM applicant_files"), 'one bad file adds none');
        $this->assertSame(['ok' => false, 'msg' => 'Choose the picture(s) of the exam result.'],
            apl_add_exam_files($db, $id, [['error' => UPLOAD_ERR_NO_FILE]], '', self::ADMIN, false));

        $ok = apl_add_exam_files($db, $id, [$this->examImage('png', 3600, 1800), $this->examImage('jpeg')], 'Typing test <45 wpm>', self::ADMIN, false);
        $this->assertTrue($ok['ok'], $ok['msg'] ?? '');
        $files = $this->act('detail', ['id' => $id])['json']['applicant']['examFiles'];
        $this->assertCount(2, $files);
        $this->assertSame([2400, 1200], [$files[0]['w'], $files[0]['h']], 'stored at most 2400 px on the long side');
        $this->assertSame('Typing test <45 wpm>', $files[0]['caption'], 'kept as text; the page escapes it');
        $path = apl_exam_file_path($db, $files[0]['id']);
        $this->assertNotNull($path);
        $this->assertSame(IMAGETYPE_JPEG, getimagesize($path)[2], 're-drawn as a JPEG');
        $notes = implode(' | ', array_column($this->act('detail', ['id' => $id])['json']['applicant']['events'], 'notes'));
        $this->assertStringContainsString('Exam result uploaded (2 images): Typing test', $notes, 'shows in Activity');

        // served to HR only
        $img = $this->request('query/applicant-file.php', ['id' => $files[0]['id']], [], $this->asAdmin());
        $this->assertSame(200, $img['status']);
        $this->assertSame(file_get_contents($path), $img['body']);
        $this->assertSame(403, $this->request('query/applicant-file.php', ['id' => $files[0]['id']], [], $this->asEmployee())['status']);
        $this->assertSame(404, $this->request('query/applicant-file.php', ['id' => 99999], [], $this->asAdmin())['status']);

        // still there at later stages; removable one at a time
        $this->advance($id, 'interview');
        $del = $this->act('exam_delete', ['id' => $id, 'file' => $files[1]['id']]);
        $this->assertSame(200, $del['status'], $del['body']);
        $this->assertCount(1, $del['json']['applicant']['examFiles']);
        $this->assertSame(422, $this->act('exam_delete', ['id' => $id + 1, 'file' => $files[0]['id']])['status'], 'only from its own application');
        $this->assertSame(422, $this->act('exam_upload', ['id' => $id])['status'], 'no file chosen');

        // deleting the application removes the pictures too
        $this->act('stage', ['id' => $id, 'stage' => 'rejected', 'notes' => '']);
        $this->assertSame(200, $this->act('delete', ['id' => $id])['status']);
        $this->assertCount(0, $this->rows("SELECT id FROM applicant_files"));
        $this->assertFileDoesNotExist($path);
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
