<?php
namespace Wedo\Tests;

use Wedo\Tests\Support\AppTestCase;

/**
 * Company ID card generator (Management > ID Card Generator).
 *
 *   includes/idcard-lib.php     name format, initials, numbering, issue
 *   query/idcard-action.php     save_crop / issue (gated + token)
 *   idcard.php                  the screen
 */
final class IdCardTest extends AppTestCase
{
    private const TOKEN = 'idc-test-token';

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        require_once dirname(__DIR__) . '/includes/idcard-lib.php';
    }

    /** Most tests print, which needs the card back confirmed and a signature on file; the locks are tested below. */
    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame([], idc_save_back(self::db(), idc_back_defaults(), self::ADMIN));
        // both seeded people get a 201 photo (the fixture gives the admin no profile row)
        self::db()->prepare("INSERT INTO empprofiles (EmpID, EmpPPath) VALUES (?, ?)")
            ->execute([self::ADMIN, 'assets/images/profiles/' . self::ADMIN . '.jpg']);
        foreach ([self::EMP, self::ADMIN] as $id) { $this->makePhoto($id); }
        $this->assertSame('', idc_store_signature(self::EMP, $this->fakeScan()));
        $this->assertSame('', idc_store_signature(self::ADMIN, $this->fakeScan()));
    }

    private function unconfirmBack(): void
    {
        self::db()->exec('DELETE FROM idcard_settings');
    }

    private function hr(): array
    {
        return array_merge($this->asAdmin(), ['idc_csrf' => self::TOKEN]);
    }

    private function action(array $post, ?array $session = null): array
    {
        $res = $this->request('query/idcard-action.php', [], $post + ['token' => self::TOKEN], $session ?? $this->hr());
        $this->assertNoPhpErrors($res['body']);
        $res['json'] = json_decode($res['body'], true);
        return $res;
    }

    private function issue(array $ids, ?array $session = null): array
    {
        return $this->action(['action' => 'issue', 'emp' => $ids], $session);
    }

    // ------------------------------------------------------------ name + number rules

    public function testDisplayNameIsUpperCaseWithMiddleInitialAndSuffix(): void
    {
        $this->assertSame('BERNARD N. FRANCO', idc_display_name('Bernard', 'Nueva', 'Franco'));
        $this->assertSame('JOHN ROBERT A. PLOTADO', idc_display_name('john robert ', 'abanes', ' plotado'));
        $this->assertSame('JUAN S. DELA CRUZ JR.', idc_display_name('Juan', 'Santos', 'Dela Cruz', 'Jr'));
        $this->assertSame('PEDRO SANTOS III', idc_display_name('Pedro', '', 'Santos', 'III'));
        $this->assertSame('JOSHUA PEDE', idc_display_name('Joshua', 'N/A', 'Pede'));
        $this->assertSame('NIÑO B. BULWA', idc_display_name('Niño', 'B', 'Bulwa'));
    }

    public function testInitialsUseFirstLetterOfEachPartAndXForNoMiddleName(): void
    {
        $this->assertSame('BNF', idc_initials('Bernard', 'Nueva', 'Franco'));
        $this->assertSame('ACV', idc_initials('Art Angelo', 'Calim', 'Vicente'));
        $this->assertSame('MDC', idc_initials('Mary Grace', 'Del Monte', 'Corsiga'));
        $this->assertSame('JXP', idc_initials('Joshua', '', 'Pede'));
        $this->assertSame('JXD', idc_initials('Jezrel', '-', 'de Leon'));   // "de Leon" -> D
        $this->assertSame('NEO', idc_initials('Ñino', 'Ébano', 'Ople'));
        $this->assertSame('2026BNF008', idc_format_number(2026, 'BNF', 8));
    }

    // ------------------------------------------------------------ issuing

    public function testFirstPrintAssignsNumberAndWritesItToThe201Record(): void
    {
        $res = $this->issue([self::EMP]);
        $this->assertSame(200, $res['status'], $res['body']);
        $year = date('Y');
        $this->assertSame($year . 'JSD001', $res['json']['cards'][0]['idNumber']);
        $this->assertSame('JUAN S. DELA CRUZ JR.', $res['json']['cards'][0]['name']);

        $this->assertSame($year . 'JSD001', $this->row('SELECT EmployeeIDNumber FROM employees WHERE EmpID=?', [self::EMP])['EmployeeIDNumber']);
        $log = $this->rows('SELECT * FROM idcard_print_log');
        $this->assertCount(1, $log);
        $this->assertSame('issue', $log[0]['action']);
        $this->assertSame('E-1', $log[0]['prev_employee_id_number']);   // old value kept
        $this->assertSame(self::ADMIN, $log[0]['printed_by']);
    }

    public function testReprintKeepsTheNumberAndIsLogged(): void
    {
        $first = $this->issue([self::EMP])['json']['cards'][0]['idNumber'];
        $again = $this->issue([self::EMP]);
        $this->assertSame($first, $again['json']['cards'][0]['idNumber']);
        $this->assertSame(['issue', 'reprint'], array_column($this->rows('SELECT action FROM idcard_print_log ORDER BY id'), 'action'));
        $this->assertSame(1, (int) $this->row('SELECT COUNT(*) n FROM idcard_cards WHERE id_number IS NOT NULL')['n']);
    }

    public function testRunningNumberIncrementsWithinAYearAndRestartsNextYear(): void
    {
        $res = $this->issue([self::EMP, self::ADMIN]);
        $year = date('Y');
        $this->assertEqualsCanonicalizing([$year . 'JSD001', $year . 'AXA002'], array_column($res['json']['cards'], 'idNumber'));
        $this->assertSame(3, $res['json']['nextSeq']);

        // a new year starts again at 001
        self::db()->exec("DELETE FROM idcard_cards WHERE EmpID = '" . self::ADMIN . "'");
        $r = idc_issue(self::db(), self::ADMIN, self::ADMIN, (int) $year + 1);
        $this->assertSame(['id_number' => ($year + 1) . 'AXA001', 'action' => 'issue'], $r);
    }

    public function testPhotoAdjustmentIsSavedClampedAndSurvivesIssuing(): void
    {
        $res = $this->action(['action' => 'save_crop', 'emp' => self::EMP, 'x' => '0.4', 'y' => '-7', 'zoom' => '9']);
        $this->assertSame(200, $res['status'], $res['body']);
        $row = $this->row('SELECT * FROM idcard_cards WHERE EmpID=?', [self::EMP]);
        $this->assertEquals([0.4, -1.0, 3.0], [(float) $row['photo_x'], (float) $row['photo_y'], (float) $row['photo_zoom']]);
        $this->assertNull($row['id_number']);

        $card = $this->issue([self::EMP])['json']['cards'][0];
        $this->assertEquals(['x' => 0.4, 'y' => -1.0, 'zoom' => 3.0], $card['crop']);
    }

    // ------------------------------------------------------------ card back details

    private function backPost(array $overrides = []): array
    {
        return array_merge(['action' => 'save_back'], idc_back_defaults(), $overrides);
    }

    public function testCardBackStartsWithTheOriginalValues(): void
    {
        $this->unconfirmBack();
        $cfg = idc_back_config(self::db());
        $this->assertSame('0967 378 4000', $cfg['phone']);
        $this->assertSame(['UNIT 3004-A WEST TEKTITE TOWER', 'EXCHANGE RD ORTIGAS, PASIG CITY 1605'], $cfg['address']);
    }

    public function testHrCanEditTheCardBackAndEveryCardUsesIt(): void
    {
        $res = $this->action($this->backPost(['phone' => '(02) 8123 4567', 'email' => 'hr@wedoinc.ph',
                                              'address1' => '1901 ANTEL GLOBAL CORPORATE CENTER', 'address2' => '']));
        $this->assertSame(200, $res['status'], $res['body']);
        $this->assertSame('(02) 8123 4567', $res['json']['back']['phone']);
        $this->assertSame(['1901 ANTEL GLOBAL CORPORATE CENTER'], $res['json']['back']['address']);   // blank line 2 dropped

        $page = $this->request('idcard.php', [], [], $this->asAdmin())['body'];
        $this->assertStringContainsString('hr@wedoinc.ph', $page);
        $this->assertStringContainsString('(02) 8123 4567', $page);
    }

    public function testCardBackRejectsBadValuesAndSavesNothing(): void
    {
        $res = $this->action($this->backPost(['email' => 'not-an-email', 'phone' => '', 'signatory' => str_repeat('A', 41)]));
        $this->assertSame(422, $res['status']);
        $this->assertEqualsCanonicalizing(['email', 'phone', 'signatory'], array_keys($res['json']['error']));
        $this->assertSame(idc_back_defaults(), idc_back_values(self::db()));   // nothing changed
    }

    public function testCardBackNeedsTheAccessRight(): void
    {
        $res = $this->action($this->backPost(['phone' => '0000']), array_merge($this->asEmployee(), ['idc_csrf' => self::TOKEN]));
        $this->assertSame(403, $res['status']);
        $this->assertSame('0967 378 4000', idc_back_config(self::db())['phone']);
    }

    public function testPrintingIsLockedUntilTheCardBackIsConfirmed(): void
    {
        $this->unconfirmBack();
        $this->assertFalse(idc_back_is_set(self::db()));

        $res = $this->issue([self::EMP]);
        $this->assertSame(409, $res['status']);
        $this->assertSame('needs_back', $res['json']['status']);
        $this->assertSame(0, (int) $this->row('SELECT COUNT(*) n FROM idcard_print_log')['n']);
        $this->assertNull($this->row('SELECT id_number FROM idcard_cards WHERE EmpID=?', [self::EMP]));
        $this->assertSame('E-1', $this->row('SELECT EmployeeIDNumber FROM employees WHERE EmpID=?', [self::EMP])['EmployeeIDNumber']);

        $page = $this->request('idcard.php', [], [], $this->asAdmin())['body'];
        $this->assertStringContainsString('"backSet":false', $page);

        // confirming (saving) the back unlocks printing
        $this->assertSame(200, $this->action(array_merge(['action' => 'save_back'], idc_back_defaults()))['status']);
        $this->assertSame(200, $this->issue([self::EMP])['status']);
    }

    public function testPrintUsesTheBackAsSavedNowNotAsThePageLoadedIt(): void
    {
        idc_save_back(self::db(), array_merge(idc_back_defaults(), ['phone' => '0917 000 0000']), self::ADMIN);
        $this->assertSame('0917 000 0000', $this->issue([self::EMP])['json']['back']['phone']);
    }

    // ------------------------------------------------------------ employee signature

    protected function tearDown(): void
    {
        foreach ([self::EMP, self::ADMIN] as $id) {
            idc_remove_signature($id);
            @unlink($this->photoPath($id));
        }
        parent::tearDown();
    }

    private function photoPath(string $empId): string
    {
        return dirname(__DIR__) . '/assets/images/profiles/' . $empId . '.jpg';
    }

    private function makePhoto(string $empId): void
    {
        $im = imagecreatetruecolor(600, 700);
        imagefill($im, 0, 0, imagecolorallocate($im, 180, 190, 200));
        imagejpeg($im, $this->photoPath($empId), 80);
        imagedestroy($im);
    }

    /** A "scan": white paper with a dark stroke in the middle, saved as JPG. */
    private function fakeScan(bool $blank = false): string
    {
        $im = imagecreatetruecolor(600, 300);
        imagefill($im, 0, 0, imagecolorallocate($im, 250, 250, 248));
        if (!$blank) {
            imagesetthickness($im, 6);
            imageline($im, 150, 180, 450, 120, imagecolorallocate($im, 20, 20, 30));
        }
        $path = tempnam(sys_get_temp_dir(), 'sig') . '.jpg';
        imagejpeg($im, $path, 92);
        imagedestroy($im);
        return $path;
    }

    public function testSignatureIsCleanedCroppedAndShownOnTheCard(): void
    {
        $this->assertSame('', idc_store_signature(self::EMP, $this->fakeScan()));
        $file = idc_sign_dir() . '/' . idc_sign_file(self::EMP);
        $this->assertFileExists($file);

        [$w, $h, $type] = getimagesize($file);
        $this->assertSame(IMAGETYPE_PNG, $type);
        $this->assertLessThan(600, $w);                     // cropped to the ink
        $this->assertLessThan(300, $h);
        $im = imagecreatefrompng($file);
        $this->assertSame(127, (imagecolorat($im, 0, 0) >> 24) & 0x7F);   // paper is transparent

        $card = idc_employees(self::db(), [self::EMP])[0];
        $this->assertStringStartsWith('assets/images/id-signatures/' . self::EMP . '.png?v=', $card['signature']);
    }

    public function testBlankOrNonImageSignatureIsRejected(): void
    {
        idc_remove_signature(self::EMP);
        $this->assertStringContainsString('blank', idc_store_signature(self::EMP, $this->fakeScan(true)));
        $txt = tempnam(sys_get_temp_dir(), 'sig');
        file_put_contents($txt, '<?php echo 1;');
        $this->assertSame('Upload a PNG or JPG image.', idc_store_signature(self::EMP, $txt));
        $this->assertNull(idc_sign_url(self::EMP));
    }

    public function testSignatureFileNameCannotEscapeTheFolder(): void
    {
        $this->assertSame('WeDoinc-0010.png', idc_sign_file('WeDoinc-0010'));
        $this->assertSame('passwd.png', idc_sign_file('../../etc/passwd'));   // basename only
        $this->assertSame('', idc_sign_file('..'));
    }

    public function testHrCanRemoveASignature(): void
    {
        idc_store_signature(self::EMP, $this->fakeScan());
        $res = $this->action(['action' => 'remove_sign', 'emp' => self::EMP]);
        $this->assertSame(200, $res['status'], $res['body']);
        $this->assertNull($res['json']['card']['signature']);
        $this->assertNull(idc_sign_url(self::EMP));
    }

    public function testPrintingIsLockedForAnyoneWithoutASignature(): void
    {
        idc_remove_signature(self::ADMIN);

        // batch with one unsigned employee: nothing is issued, not even for the signed one
        $res = $this->issue([self::EMP, self::ADMIN]);
        $this->assertSame(409, $res['status'], $res['body']);
        $this->assertSame('not_ready', $res['json']['status']);
        $this->assertSame([['empId' => self::ADMIN, 'name' => 'Admin, Ada', 'needs' => ['signature']]], $res['json']['missing']);
        $this->assertSame(0, (int) $this->row('SELECT COUNT(*) n FROM idcard_print_log')['n']);
        $this->assertSame(0, (int) $this->row('SELECT COUNT(*) n FROM idcard_cards WHERE id_number IS NOT NULL')['n']);
        $this->assertSame('E-1', $this->row('SELECT EmployeeIDNumber FROM employees WHERE EmpID=?', [self::EMP])['EmployeeIDNumber']);

        // the signed employee alone prints fine; reprints are locked too once the signature is removed
        $this->assertSame(200, $this->issue([self::EMP])['status']);
        idc_remove_signature(self::EMP);
        $this->assertSame(409, $this->issue([self::EMP])['status']);
        $this->assertSame(1, (int) $this->row('SELECT COUNT(*) n FROM idcard_print_log')['n']);
    }

    public function testPrintingIsLockedForAnyoneWithoutAPhoto(): void
    {
        @unlink($this->photoPath(self::EMP));
        idc_remove_signature(self::ADMIN);

        $res = $this->issue([self::EMP, self::ADMIN]);
        $this->assertSame(409, $res['status'], $res['body']);
        $this->assertEqualsCanonicalizing([
            ['empId' => self::EMP,   'name' => 'Dela Cruz, Juan', 'needs' => ['photo']],
            ['empId' => self::ADMIN, 'name' => 'Admin, Ada',      'needs' => ['signature']],
        ], $res['json']['missing']);
        $this->assertSame(0, (int) $this->row('SELECT COUNT(*) n FROM idcard_print_log')['n']);

        $this->makePhoto(self::EMP);
        $this->assertSame(200, $this->issue([self::EMP])['status']);
    }

    /** Phone photo: table around the paper, a shadow across it, dust. Only the strokes may survive. */
    public function testSignatureFromAPhonePhotoIsCroppedToTheInk(): void
    {
        $im = imagecreatetruecolor(1600, 1200);
        imagefill($im, 0, 0, imagecolorallocate($im, 92, 64, 40));                       // table
        for ($y = 140; $y < 1060; $y++) {                                                  // paper, shaded toward one corner
            for ($x = 200; $x < 1420; $x++) {
                $v = 236 - (int) (70 * max(0, ($x + $y - 1300) / 1300));
                imagesetpixel($im, $x, $y, ($v << 16) | ($v << 8) | $v);
            }
        }
        imagesetthickness($im, 6);
        imageline($im, 640, 700, 1120, 600, imagecolorallocate($im, 30, 34, 70));         // the "signature"
        imagefilledellipse($im, 300, 300, 3, 3, imagecolorallocate($im, 120, 120, 120)); // dust far away
        $path = tempnam(sys_get_temp_dir(), 'sig') . '.jpg';
        imagejpeg($im, $path, 88);

        $this->assertSame('', idc_store_signature(self::EMP, $path));
        [$w, $h] = getimagesize(idc_sign_dir() . '/' . idc_sign_file(self::EMP));
        // stroke is 480 x 100 px in the photo = 360 x 75 at the 1200 px working size (+ small margin)
        $this->assertLessThan(400, $w, "crop {$w}x{$h} kept table/shadow/dust");
        $this->assertLessThan(110, $h, "crop {$w}x{$h} kept table/shadow/dust");
        $this->assertGreaterThan(340, $w);
    }

    /** A clean scan whose only problem is stray dots and a dash near the page edges (a real sample from HR). */
    public function testStrayDotsOnAScanDoNotShrinkTheSignature(): void
    {
        $im = imagecreatetruecolor(1440, 2000);
        imagefill($im, 0, 0, imagecolorallocate($im, 255, 255, 255));
        imagesetthickness($im, 13);
        imageline($im, 300, 870, 1255, 975, imagecolorallocate($im, 8, 8, 8));           // the signature
        imageellipse($im, 470, 1060, 460, 200, imagecolorallocate($im, 8, 8, 8));
        foreach ([[1340, 210], [20, 490], [147, 1800], [1435, 1825], [1090, 1975]] as [$x, $y]) {
            imagefilledellipse($im, $x, $y, 4, 4, imagecolorallocate($im, 60, 60, 60));   // dots near the edges
        }
        imagesetthickness($im, 3);
        imageline($im, 245, 1975, 290, 1975, imagecolorallocate($im, 8, 8, 8));          // short dash bottom-left
        $path = tempnam(sys_get_temp_dir(), 'sig') . '.jpg';
        imagejpeg($im, $path, 90);

        $this->assertSame('', idc_store_signature(self::EMP, $path));
        [$w, $h] = getimagesize(idc_sign_dir() . '/' . idc_sign_file(self::EMP));
        // strokes span x 240..1260, y 870..1160 on the page = about 612 x 175 at the 0.6 working scale
        $this->assertLessThan(640, $w, "crop {$w}x{$h} kept the stray marks");
        $this->assertLessThan(200, $h, "crop {$w}x{$h} kept the stray marks");
    }

    public function testUploadWithoutAFileIsRejected(): void
    {
        $this->assertSame(422, $this->action(['action' => 'upload_sign', 'emp' => self::EMP])['status']);
        $this->assertSame(403, $this->action(['action' => 'upload_sign', 'emp' => self::EMP],
            array_merge($this->asEmployee(), ['idc_csrf' => self::TOKEN]))['status']);
    }

    // ------------------------------------------------------------ access

    public function testEndpointRequiresTheAccessRightAndToken(): void
    {
        $noRight = $this->issue([self::EMP], array_merge($this->asEmployee(), ['idc_csrf' => self::TOKEN]));
        $this->assertSame(403, $noRight['status']);

        $badToken = $this->request('query/idcard-action.php', [], ['action' => 'issue', 'emp' => [self::EMP], 'token' => 'nope'], $this->hr());
        $this->assertSame(419, $badToken['status']);

        $this->assertSame(0, (int) $this->row('SELECT COUNT(*) n FROM idcard_print_log')['n']);
    }

    public function testUnknownEmployeeIsRejected(): void
    {
        $this->assertSame(404, $this->issue(['NOPE-1'])['status']);
        $this->assertSame(404, $this->action(['action' => 'save_crop', 'emp' => 'NOPE-1'])['status']);
    }

    public function testPageRendersForHrAndRedirectsOthers(): void
    {
        $res = $this->request('idcard.php', [], [], $this->asAdmin());
        $this->assertSame(200, $res['status']);
        $this->assertNoPhpErrors($res['body']);
        $this->assertStringContainsString('ID Card Generator', $res['body']);
        $this->assertStringContainsString('JUAN S. DELA CRUZ JR.', $res['body']);

        $this->assertSame(302, $this->request('idcard.php', [], [], $this->asEmployee())['status']);
    }

    public function testIssuedNumberLocksThe201EmployeeIdField(): void
    {
        $num = $this->issue([self::EMP])['json']['cards'][0]['idNumber'];
        $page = $this->request('UpdateEmployeeInfo.php', ['sid' => self::EMP], [], $this->asAdmin())['body'];
        $this->assertSame($num, $this->inputValue($page, 'empcid'));
        $el = $this->dom($page)->query("//input[@name='empcid']")->item(0);
        $this->assertTrue($el->hasAttribute('readonly'));

        // a direct save that sends a different Employee ID keeps the card number
        $post = array_merge($this->formData($page), ['empcid' => 'TYPED-OVER']);
        $post['family'] = json_encode([]);
        $this->request('query/Query-updateEmployee.php', [], $post, $this->asAdmin());
        $this->assertSame($num, $this->row('SELECT EmployeeIDNumber FROM employees WHERE EmpID=?', [self::EMP])['EmployeeIDNumber']);
    }
}
