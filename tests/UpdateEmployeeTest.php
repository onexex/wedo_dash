<?php
namespace Wedo\Tests;

use Wedo\Tests\Support\AppTestCase;

/**
 * Regression tests for "Edit employee profile":
 *   UpdateEmployeeInfo.php          (the form)
 *   query/Query-updateEmployee.php  (saves the form)
 *   update-fdetails.php             (saves the family / ICE list)
 *
 * The save tests post exactly what the rendered form would send, so a field
 * that the page and the endpoint disagree on shows up as a changed value.
 */
final class UpdateEmployeeTest extends AppTestCase
{
    private function editPage(string $empId = self::EMP): string
    {
        $res = $this->request('UpdateEmployeeInfo.php', ['sid' => $empId], [], $this->asAdmin());
        $this->assertSame(200, $res['status']);
        return $res['body'];
    }

    private function save(array $post, bool $loggedIn = true): array
    {
        return $this->request('query/Query-updateEmployee.php', [], $post, $loggedIn ? $this->asAdmin() : null);
    }

    /** Render the form, apply $changes, post it like the page's Save button does. */
    private function saveForm(array $changes = []): array
    {
        $post = array_merge($this->formData($this->editPage()), $changes);
        $res  = $this->save($post);
        $this->assertSame(200, $res['status']);
        // the page treats ANY output as a failed save, so warnings count as failures too
        $this->assertSame('', trim($res['body']), 'save endpoint must print nothing on success');
        return $res;
    }

    // ================================================================ the form

    public function testEditPageRendersWithoutPhpErrors(): void
    {
        $html = $this->editPage();
        $this->assertNoPhpErrors($html);
        $this->assertStringContainsString('Edit employee profile', $html);
        $this->assertStringContainsString('Juan Santos Dela Cruz Jr', $html);
    }

    public function testEditPagePreselectsTheEmployeesCurrentValues(): void
    {
        $html = $this->editPage();
        $this->assertSame('Male', $this->selectedOf($html, 'empgender'));
        $this->assertSame('Single', $this->selectedOf($html, 'empcs'));
        $this->assertSame('1', $this->selectedOf($html, 'empdepartment1'));   // Engineering
        $this->assertSame('10', $this->selectedOf($html, 'idempposition'));   // Developer
        $this->assertSame('1', $this->selectedOf($html, 'empdesccode'));      // Regular
        $this->assertSame(self::ADMIN, $this->selectedOf($html, 'empis'));
        $this->assertSame('1', $this->selectedOf($html, 'empstatus'));        // Employed
        $this->assertSame('1', $this->selectedOf($html, 'idempjoblevel'));
        $this->assertSame('2', $this->selectedOf($html, 'idempagency'));
        $this->assertSame('2', $this->selectedOf($html, 'idhmoprovider'));
    }

    /** Regression: the two numbers used to be pre-filled into each other's boxes, so every save swapped them. */
    public function testPhoneNumbersArePrefilledInTheirOwnFields(): void
    {
        $html = $this->editPage();
        $this->assertSame('09170000001', $this->inputValue($html, 'pempn0'), 'Mobile Number = empprofiles.EmpPhone');
        $this->assertSame('0281234567', $this->inputValue($html, 'emphomenumber'), 'Home Phone = empprofiles.EmpMobile');
    }

    /** Regression: the regularization date was never pre-filled, so every save wiped it. */
    public function testRegularizationDateIsPrefilled(): void
    {
        $this->assertSame('2021-08-01', $this->inputValue($this->editPage(), 'dorInput'));
    }

    /** Work schedule belongs to the scheduling module, not this form. */
    public function testEditPageHasNoWorkScheduleSection(): void
    {
        $html = $this->editPage();
        $this->assertStringNotContainsString('wrksch', $html);
        $this->assertStringNotContainsString('btn-ws', $html);
    }

    public function testEditPageEscapesStoredValues(): void
    {
        self::db()->prepare('UPDATE empprofiles SET EmpAddress1=? WHERE EmpID=?')
            ->execute(['"><script>alert(1)</script>', self::EMP]);
        $html = $this->editPage();
        $this->assertStringNotContainsString('<script>alert(1)</script>', $html);
        $this->assertSame('"><script>alert(1)</script>', $this->inputValue($html, 'pempstreetno'));
    }

    public function testUnknownEmployeeShowsNotFound(): void
    {
        $html = $this->editPage('NO-SUCH-ID');
        $this->assertNoPhpErrors($html);
        $this->assertStringContainsString('Employee not found', $html);
        $this->assertStringNotContainsString('id="fdata"', $html);
    }

    public function testEditPageRequiresLogin(): void
    {
        $res = $this->request('UpdateEmployeeInfo.php', ['sid' => self::EMP], [], null);
        $this->assertStringNotContainsString('id="fdata"', $res['body']);
        $this->assertStringNotContainsString('Dela Cruz', $res['body']);
    }

    // ================================================================ saving

    /** The core regression check: open → Save with no edits must not change anything. */
    public function testSavingTheUnchangedFormChangesNothing(): void
    {
        $before = $this->employeeSnapshot(self::EMP);
        $this->saveForm();
        $this->assertEquals($before, $this->employeeSnapshot(self::EMP));
    }

    public function testSaveStoresEditedFields(): void
    {
        $this->saveForm([
            'empfn' => 'Juanito', 'pempstreetno' => '99 Acacia Ave', 'pempeadd' => 'juanito@example.com',
            'pempn0' => '09998887777', 'emphomenumber' => '0287654321',
            'empdep' => '2', 'empposition' => '11', 'empbasic' => '30000.00',
            't_nameofschool' => 'UP Diliman',
        ]);

        $this->assertSame('Juanito', $this->row('SELECT EmpFN FROM employees WHERE EmpID=?', [self::EMP])['EmpFN']);
        $this->assertSame('11', (string)$this->row('SELECT PosID FROM employees WHERE EmpID=?', [self::EMP])['PosID']);
        $p = $this->row('SELECT * FROM empprofiles WHERE EmpID=?', [self::EMP]);
        $this->assertSame('99 Acacia Ave', $p['EmpAddress1']);
        $this->assertSame('juanito@example.com', $p['EmpEmail']);
        $this->assertSame('09998887777', $p['EmpPhone']);
        $this->assertSame('0287654321', $p['EmpMobile']);
        $this->assertSame('2', (string)$this->row('SELECT EmpdepID FROM empdetails WHERE EmpID=?', [self::EMP])['EmpdepID']);
        $this->assertEquals(30000, $this->row('SELECT EmpBasic FROM empdetails2 WHERE EmpID=?', [self::EMP])['EmpBasic']);
        $this->assertSame('UP Diliman', $this->row(
            "SELECT Name_of_School FROM empeducationalbackground WHERE EmpID=? AND Program='Tertiary'", [self::EMP])['Name_of_School']);
    }

    /** Regression: values were pasted into the SQL, so an apostrophe broke the whole save. */
    public function testSaveAcceptsApostrophes(): void
    {
        $this->saveForm(['empln' => "O'Brien", 'pempstreetno' => "St. Mary's Road"]);
        $this->assertSame("O'Brien", $this->row('SELECT EmpLN FROM employees WHERE EmpID=?', [self::EMP])['EmpLN']);
        $this->assertSame("St. Mary's Road", $this->row('SELECT EmpAddress1 FROM empprofiles WHERE EmpID=?', [self::EMP])['EmpAddress1']);
    }

    public function testBlankRegularizationDateIsStoredAsNull(): void
    {
        $this->saveForm(['dorInput' => '']);
        // NULL, not 0000-00-00: alas.php only treats NULL as "not regularized yet"
        $this->assertNull($this->row('SELECT EmpDOR FROM empdetails WHERE EmpID=?', [self::EMP])['EmpDOR']);
    }

    /** Regression: without the schedule fields the endpoint used to set every day's shift to NULL. */
    public function testSaveLeavesTheWorkScheduleUntouched(): void
    {
        $before = $this->rows('SELECT * FROM workdays WHERE empid=? ORDER BY WID', [self::EMP]);
        $this->saveForm();
        $this->assertEquals($before, $this->rows('SELECT * FROM workdays WHERE empid=? ORDER BY WID', [self::EMP]));
    }

    /** Callers that still post the schedule keep working, and no duplicate day rows are added. */
    public function testSchedulePostedExplicitlyIsStillApplied(): void
    {
        $sched = [];
        foreach (['mon', 'tues', 'wed', 'thu', 'fri', 'sat', 'sun'] as $d) { $sched['wrksch' . $d] = '6'; }
        $this->saveForm($sched);

        $rows = $this->rows('SELECT Day_s, SchedTime FROM workdays WHERE empid=?', [self::EMP]);
        $this->assertCount(7, $rows);
        foreach ($rows as $r) { $this->assertSame('6', (string)$r['SchedTime'], $r['Day_s']); }
    }

    public function testSaveIsRecordedInTheActivityLog(): void
    {
        $this->saveForm();
        $this->assertNotNull($this->row(
            "SELECT * FROM dars WHERE EMPID=? AND EmpActivity='Maintenance : Updated Employee Information'", [self::EMP]));
    }

    /** Regression: the endpoint had no login check at all. */
    public function testSaveRequiresLogin(): void
    {
        $post   = array_merge($this->formData($this->editPage()), ['empfn' => 'Hacked']);
        $before = $this->employeeSnapshot(self::EMP);

        $res = $this->save($post, false);
        $this->assertSame(401, $res['status']);
        $this->assertStringContainsString('sign in', $res['body']);
        $this->assertEquals($before, $this->employeeSnapshot(self::EMP));
    }

    // ================================================================ family / ICE list

    private function saveFamily(array $rows, bool $loggedIn = true): array
    {
        $col = fn(int $i) => json_encode(array_map(fn($r) => $r[$i], $rows));
        return $this->request('update-fdetails.php', [], [
            'name' => $col(0), 'a' => $col(1), 'rel' => $col(2), 'conno' => $col(3), 'ice' => $col(4), 'empID' => self::EMP,
        ], $loggedIn ? $this->asAdmin() : null);
    }

    private function family(): array
    {
        return $this->rows('SELECT FName,FAdd,FRel,FContact,FICE FROM fdetails WHERE FDetID=? ORDER BY FSID', [self::EMP]);
    }

    public function testFamilyListIsReplacedByWhatIsPosted(): void
    {
        $res = $this->saveFamily([
            ['Maria Dela Cruz', 'Pasig', 'Mother', '09171112222', 'Yes'],
            ['Ana Dela Cruz', 'Makati', 'Spouse', '09173334444', 'No'],
        ]);
        $this->assertNoPhpErrors($res['body']);
        $this->assertSame([
            ['FName' => 'Maria Dela Cruz', 'FAdd' => 'Pasig', 'FRel' => 'Mother', 'FContact' => '09171112222', 'FICE' => 'Yes'],
            ['FName' => 'Ana Dela Cruz', 'FAdd' => 'Makati', 'FRel' => 'Spouse', 'FContact' => '09173334444', 'FICE' => 'No'],
        ], $this->family());
    }

    public function testFamilyListKeepsSpecialCharactersLiterally(): void
    {
        $this->saveFamily([["Tom & Jerry's Mom", 'Brgy. Sto. Niño', 'Mother', '0917', 'Yes']]);
        $this->assertSame("Tom & Jerry's Mom", $this->family()[0]['FName']);
        $this->assertSame('Brgy. Sto. Niño', $this->family()[0]['FAdd']);
    }

    public function testFamilyRowsWithoutANameAreSkipped(): void
    {
        $this->saveFamily([['', 'x', 'Mother', '1', 'No'], ['Ana', 'Makati', 'Spouse', '2', 'No']]);
        $this->assertSame(['Ana'], array_column($this->family(), 'FName'));
    }

    /** Regression: the login check redirected but didn't stop, so the delete/insert still ran. */
    public function testFamilyListRequiresLogin(): void
    {
        $before = $this->family();
        $res = $this->saveFamily([], false);
        $this->assertSame(401, $res['status']);
        $this->assertSame($before, $this->family());
    }
}
