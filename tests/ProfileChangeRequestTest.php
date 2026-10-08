<?php
namespace Wedo\Tests;

use Wedo\Tests\Support\AppTestCase;

/**
 * Employees request changes to their own 201 record; users with
 * "Update 201 Files" approve them (includes/profile-change.php).
 *
 *   UpdateEmployeeInfo.php                 request mode for employees
 *   query/Query-requestProfileChange.php   employee submits
 *   query/Query-reviewProfileChange.php    reviewer approves / rejects
 *   profilerequests.php                    review queue
 */
final class ProfileChangeRequestTest extends AppTestCase
{
    // ------------------------------------------------------------ helpers

    private function myEditPage(): string
    {
        $res = $this->request('UpdateEmployeeInfo.php', [], [], $this->asEmployee());
        $this->assertSame(200, $res['status']);
        $this->assertNoPhpErrors($res['body']);
        return $res['body'];
    }

    /** The family list the page's JS would send, starting from the seeded row. */
    private function seededFamily(array $extra = []): array
    {
        return array_merge([['name' => 'Maria Dela Cruz', 'address' => 'Pasig', 'relationship' => 'Mother',
                             'contact' => '09171112222', 'ice' => 'Yes']], $extra);
    }

    /** Submit like the page does: rendered form + edits + family JSON. */
    private function submitRequest(array $changes = [], ?array $family = null, ?array $session = null): array
    {
        $post = array_merge($this->formData($this->myEditPage()), $changes);
        $post['family'] = json_encode($family ?? $this->seededFamily());
        return $this->request('query/Query-requestProfileChange.php', [], $post, $session ?? $this->asEmployee());
    }

    private function review(int $id, string $action, string $remarks = '', bool $asReviewer = true): array
    {
        return $this->request('query/Query-reviewProfileChange.php', [],
            ['id' => $id, 'action' => $action, 'remarks' => $remarks], $asReviewer ? $this->asAdmin() : $this->asEmployee());
    }

    private function requests(): array
    {
        return $this->rows('SELECT * FROM profile_change_requests ORDER BY id');
    }

    private function pendingId(): int
    {
        $r = $this->row("SELECT id FROM profile_change_requests WHERE Status='pending'");
        $this->assertNotNull($r, 'expected a pending request');
        return (int)$r['id'];
    }

    // ------------------------------------------------------------ what employees can see and do

    public function testEmployeeGetsRequestModeWithoutHrOnlySections(): void
    {
        $html = $this->myEditPage();
        $this->assertStringContainsString('Update my information', $html);
        $this->assertStringContainsString('Submit for approval', $html);
        $this->assertStringContainsString('data-mode="request"', $html);
        // employment, salary and photo are HR-only — not rendered at all
        foreach (['id="btn-es"', 'id="btn-prf"', 'name="empbasic"', 'name="empposition"', 'id="file"', 'modaladddep', '25000'] as $hidden) {
            $this->assertStringNotContainsString($hidden, $html);
        }
    }

    public function testEmployeeCannotOpenSomeoneElsesRecord(): void
    {
        $res = $this->request('UpdateEmployeeInfo.php', ['sid' => self::ADMIN], [], $this->asEmployee());
        $this->assertNoPhpErrors($res['body']);
        $this->assertStringContainsString('You can only update your own information', $res['body']);
        $this->assertStringNotContainsString('id="fdata"', $res['body']);
        $this->assertStringNotContainsString('aadmin', $res['body']);
    }

    public function testEmployeeCannotBypassApprovalThroughTheDirectSaveEndpoints(): void
    {
        $before = $this->employeeSnapshot(self::EMP);
        $post   = array_merge($this->formData($this->myEditPage()), ['empidn' => self::EMP, 'empfn' => 'Hacked', 'empbasic' => '999999']);

        $res = $this->request('query/Query-updateEmployee.php', [], $post, $this->asEmployee());
        $this->assertSame(403, $res['status']);

        $res = $this->request('update-fdetails.php', [], ['name' => '[]', 'a' => '[]', 'rel' => '[]', 'conno' => '[]', 'ice' => '[]',
                                                          'empID' => self::EMP], $this->asEmployee());
        $this->assertSame(403, $res['status']);

        $res = $this->request('uploadpicture.php', ['q' => self::EMP], [], $this->asEmployee());
        $this->assertSame(403, $res['status']);

        $this->assertEquals($before, $this->employeeSnapshot(self::EMP));
    }

    private function addFamily(?array $session): array
    {
        return $this->request('insert-fdetails.php', [], ['name' => '["Injected Kin"]', 'a' => '["Somewhere"]', 'rel' => '["Sibling"]',
                                                          'conno' => '["09170000000"]', 'ice' => '["1"]', 'empID' => self::EMP], $session);
    }

    private function injectedKin(): int
    {
        return count($this->rows('SELECT FSID FROM fdetails WHERE FDetID=? AND FName=?', [self::EMP, 'Injected Kin']));
    }

    /** Regression: the registration family endpoint had no rights check and its login redirect didn't stop. */
    public function testEmployeeCannotAddFamilyRowsThroughTheRegistrationEndpoint(): void
    {
        $this->assertSame(403, $this->addFamily($this->asEmployee())['status']);
        $this->assertSame(401, $this->addFamily(null)['status']);
        $this->assertSame(0, $this->injectedKin());
    }

    public function testHrCanStillAddFamilyRowsThroughTheRegistrationEndpoint(): void
    {
        $this->assertSame(200, $this->addFamily($this->asAdmin())['status']);
        $this->assertSame(1, $this->injectedKin());
    }

    public function testEmployee201PageOffersUpdateMyInfo(): void
    {
        $res = $this->request('e201.php', [], [], $this->asEmployee());
        $this->assertStringContainsString('Update my info', $res['body']);
        $this->assertStringNotContainsString('Edit profile', $res['body']);
    }

    // ------------------------------------------------------------ submitting

    public function testSubmittingStoresARequestAndLeavesTheRecordUnchanged(): void
    {
        $before = $this->employeeSnapshot(self::EMP);
        $res = $this->submitRequest(
            ['empln' => "O'Neil", 'pempn0' => '09995554444', 'p_nameofschool' => 'Pasig Central', 'pempsss' => '34-0000000-1'],
            $this->seededFamily([['name' => 'Ana Dela Cruz', 'address' => 'Makati', 'relationship' => 'Spouse', 'contact' => '0917', 'ice' => 'No']])
        );
        $this->assertSame(200, $res['status'], $res['body']);
        $this->assertTrue(json_decode($res['body'], true)['ok']);

        $this->assertEquals($before, $this->employeeSnapshot(self::EMP), 'nothing changes before approval');

        $reqs = $this->requests();
        $this->assertCount(1, $reqs);
        $this->assertSame('pending', $reqs[0]['Status']);
        $this->assertSame(self::EMP, $reqs[0]['EmpID']);
        $changes = json_decode($reqs[0]['Changes'], true);
        $this->assertEqualsCanonicalizing(['empln', 'pempn0', 'p_nameofschool', 'pempsss'], array_column($changes['fields'], 'field'));
        $byField = array_column($changes['fields'], null, 'field');
        $this->assertSame('Dela Cruz', $byField['empln']['old']);
        $this->assertSame("O'Neil", $byField['empln']['new']);
        $this->assertCount(2, $changes['family']['new']);
    }

    public function testRequestsOnlyEverCoverTheEmployeesOwnAllowedFields(): void
    {
        $res = $this->submitRequest(['pempcity' => 'Makati City', 'empbasic' => '999999', 'empposition' => '11',
                                     'empst' => '2', 'empidn' => self::ADMIN, 'empidhidden' => self::ADMIN]);
        $this->assertSame(200, $res['status'], $res['body']);

        $req = $this->requests()[0];
        $this->assertSame(self::EMP, $req['EmpID'], 'always the logged-in employee, never the posted ID');
        $changes = json_decode($req['Changes'], true);
        $this->assertSame(['pempcity'], array_column($changes['fields'], 'field'));
        $this->assertNull($changes['family']);
    }

    public function testSubmittingWithoutChangesIsRefused(): void
    {
        $res = $this->submitRequest();
        $this->assertSame(422, $res['status']);
        $this->assertStringContainsString('no changes', json_decode($res['body'], true)['message']);
        $this->assertSame([], $this->requests());
    }

    public function testRequiredFieldsCannotBeBlanked(): void
    {
        $res = $this->submitRequest(['empfn' => '  ']);
        $this->assertSame(422, $res['status']);
        $this->assertStringContainsString('First name', json_decode($res['body'], true)['message']);
        $this->assertSame([], $this->requests());
    }

    public function testResubmittingReplacesThePendingRequestAndShowsIt(): void
    {
        $this->submitRequest(['pempcity' => 'Makati City']);
        $res = $this->submitRequest(['pempcity' => 'Taguig City']);
        $this->assertSame(200, $res['status'], $res['body']);

        $this->assertSame(['cancelled', 'pending'], array_column($this->requests(), 'Status'));

        $html = $this->myEditPage();
        $this->assertStringContainsString('Waiting for HR approval', $html);
        $this->assertSame('Taguig City', $this->inputValue($html, 'pempcity'), 'form shows the pending values');
    }

    // ------------------------------------------------------------ reviewing

    public function testApprovingWritesTheRequestedChanges(): void
    {
        $this->submitRequest(
            ['empln' => "O'Neil", 'pempn0' => '09995554444', 't_nameofschool' => 'UP Diliman', 'pempdob' => '1991-06-20'],
            [['name' => "Ana D'Souza", 'address' => 'Makati', 'relationship' => 'Spouse', 'contact' => '0917', 'ice' => 'Yes']]
        );
        $res = $this->review($this->pendingId(), 'approve');
        $this->assertSame(200, $res['status'], $res['body']);

        $this->assertSame("O'Neil", $this->row('SELECT EmpLN FROM employees WHERE EmpID=?', [self::EMP])['EmpLN']);
        $p = $this->row('SELECT EmpPhone, EmpDOB FROM empprofiles WHERE EmpID=?', [self::EMP]);
        $this->assertSame('09995554444', $p['EmpPhone']);
        $this->assertSame('1991-06-20', $p['EmpDOB']);
        $this->assertSame('UP Diliman', $this->row(
            "SELECT Name_of_School FROM empeducationalbackground WHERE EmpID=? AND Program='Tertiary'", [self::EMP])['Name_of_School']);
        $this->assertSame(["Ana D'Souza"], array_column(
            $this->rows('SELECT FName FROM fdetails WHERE FDetID=?', [self::EMP]), 'FName'));

        $req = $this->requests()[0];
        $this->assertSame('approved', $req['Status']);
        $this->assertSame(self::ADMIN, $req['ReviewedBy']);
        $this->assertNotNull($req['ReviewedAt']);
        $this->assertNotNull($this->row("SELECT * FROM dars WHERE EMPID=? AND EmpActivity LIKE '%approved%'", [self::EMP]));
        $this->assertStringContainsString('Your last request was approved', $this->myEditPage());
    }

    /** Regression: general info + a family row longer than the old fdetails columns (contact was varchar(12)). */
    public function testApprovingGeneralAndFamilyWithLongValues(): void
    {
        $this->submitRequest(
            ['pempn0' => '09995554444', 'pempcity' => 'Cebu City'],
            $this->seededFamily([['name' => 'Jose Protacio Rizal Mercado y Alonso Realonda Jr.', 'relationship' => 'Grandfather-in-law',
                                  'address' => 'Blk 12 Lot 5, Phase 3, Villa Grande Homes, Brgy. San Isidro, Talisay City, Cebu',
                                  'contact' => '+63 917 123 4567 / 032 888 1234', 'ice' => 'No']])
        );
        $res = $this->review($this->pendingId(), 'approve');
        $this->assertSame(200, $res['status'], $res['body']);
        $fam = $this->rows('SELECT * FROM fdetails WHERE FDetID=? ORDER BY FSID', [self::EMP]);
        $this->assertCount(2, $fam);
        $this->assertSame('+63 917 123 4567 / 032 888 1234', $fam[1]['FContact']);
        $this->assertSame('Cebu City', $this->row('SELECT EmpAddCity FROM empprofiles WHERE EmpID=?', [self::EMP])['EmpAddCity']);
    }

    /** Until the fdetails columns are widened, a value that does not fit is refused with a clear message (never cut off). */
    public function testValuesTooLongForTheRecordAreRefusedClearly(): void
    {
        $long = [['name' => 'Ana Cruz', 'address' => 'Makati', 'relationship' => 'Spouse', 'contact' => '+63 917 123 4567', 'ice' => 'No']];
        $this->submitRequest(['pempcity' => 'Cebu City'], $long);       // fits today's columns
        $id = $this->pendingId();
        self::db()->exec('ALTER TABLE fdetails MODIFY FContact VARCHAR(12) NOT NULL');
        try {
            $res = $this->review($id, 'approve');
            $this->assertSame(422, $res['status'], $res['body']);
            $this->assertStringContainsString('contact number of Ana Cruz (max 12 characters)', $res['body']);
            $this->assertSame('pending', $this->requests()[0]['Status']);
            $this->assertSame([], $this->rows("SELECT * FROM fdetails WHERE FDetID=? AND FName='Ana Cruz'", [self::EMP]));
            $this->assertNotSame('Cebu City', $this->row('SELECT EmpAddCity FROM empprofiles WHERE EmpID=?', [self::EMP])['EmpAddCity']);

            $res = $this->submitRequest([], $long);
            $this->assertSame(422, $res['status'], $res['body']);
            $this->assertStringContainsString('Please shorten', $res['body']);
        } finally {
            self::db()->exec('ALTER TABLE fdetails MODIFY FContact VARCHAR(50) NOT NULL');
        }
    }

    public function testApprovalCreatesAMissingEducationRow(): void
    {
        self::db()->prepare("DELETE FROM empeducationalbackground WHERE EmpID=? AND Program='Primary'")->execute([self::EMP]);
        $this->submitRequest(['p_nameofschool' => 'Pasig Central', 'p_yearstarted' => '1996']);
        $this->review($this->pendingId(), 'approve');

        $row = $this->row("SELECT * FROM empeducationalbackground WHERE EmpID=? AND Program='Primary'", [self::EMP]);
        $this->assertNotNull($row);
        $this->assertSame('Pasig Central', $row['Name_of_School']);
        $this->assertSame('1996', (string)$row['Year_Started']);
    }

    public function testRejectingNeedsRemarksAndLeavesTheRecordAlone(): void
    {
        $this->submitRequest(['pempcity' => 'Makati City']);
        $id = $this->pendingId();
        $before = $this->employeeSnapshot(self::EMP);

        $res = $this->review($id, 'reject');
        $this->assertSame(422, $res['status']);
        $this->assertSame('pending', $this->requests()[0]['Status']);

        $res = $this->review($id, 'reject', 'Please attach proof of address.');
        $this->assertSame(200, $res['status'], $res['body']);
        $this->assertSame('rejected', $this->requests()[0]['Status']);
        $this->assertEquals($before, $this->employeeSnapshot(self::EMP));

        $html = $this->myEditPage();
        $this->assertStringContainsString('Your last request was not approved', $html);
        $this->assertStringContainsString('Please attach proof of address.', $html);
        $this->assertSame('Quezon City', $this->inputValue($html, 'pempcity'), 'rejected values are not shown');
    }

    public function testEmployeesCannotReviewRequests(): void
    {
        $this->submitRequest(['pempcity' => 'Makati City']);
        $res = $this->review($this->pendingId(), 'approve', '', false);
        $this->assertSame(403, $res['status']);
        $this->assertSame('pending', $this->requests()[0]['Status']);
        $this->assertSame('Quezon City', $this->row('SELECT EmpAddCity FROM empprofiles WHERE EmpID=?', [self::EMP])['EmpAddCity']);
    }

    public function testARequestCanOnlyBeReviewedOnce(): void
    {
        $this->submitRequest(['pempcity' => 'Makati City']);
        $id = $this->pendingId();
        $this->assertSame(200, $this->review($id, 'approve')['status']);
        $res = $this->review($id, 'reject', 'too late');
        $this->assertSame(409, $res['status']);
        $this->assertSame('approved', $this->requests()[0]['Status']);
    }

    // ------------------------------------------------------------ review queue

    public function testReviewQueueShowsPendingRequestsToReviewersOnly(): void
    {
        $this->submitRequest(['pempcity' => 'Makati City']);

        $res = $this->request('profilerequests.php', [], [], $this->asAdmin());
        $this->assertNoPhpErrors($res['body']);
        $this->assertStringContainsString('Juan Dela Cruz', $res['body']);
        $this->assertStringContainsString('Makati City', $res['body']);
        $this->assertMatchesRegularExpression('/Profile Change Requests<\/span><span class="wd-nav__badge">1</', $res['body']);
        // same count on the Modules group header, so it shows while the group is collapsed
        $this->assertMatchesRegularExpression('/>Modules<span class="wd-nav__badge"[^>]*>1</', $res['body']);

        $res = $this->request('profilerequests.php', [], [], $this->asEmployee());
        $this->assertNoPhpErrors($res['body']);
        $this->assertStringContainsString('>Modules</span>', $res['body']);
        $this->assertStringContainsString('Not available', $res['body']);
        $this->assertStringNotContainsString('Makati City', $res['body']);
    }
}
