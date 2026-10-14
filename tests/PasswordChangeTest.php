<?php
namespace Wedo\Tests;

use Wedo\Tests\Support\AppTestCase;

/**
 * Temporary / default passwords must be replaced at the next sign-in
 * (empdetails.must_change_pw): includes/account-gate.php wd_password_gate,
 * change-password.php, query/force-password.php.
 */
final class PasswordChangeTest extends AppTestCase
{
    private const TOKEN = 'pwc-test-token';

    protected function setUp(): void
    {
        parent::setUp();
        self::db()->prepare("UPDATE empdetails SET EmpPW = ?, must_change_pw = 1 WHERE EmpID = ?")
            ->execute([password_hash('Temp1234xy', PASSWORD_DEFAULT), self::EMP]);
    }

    private function emp(): array { return $this->asEmployee() + ['pwc_csrf' => self::TOKEN]; }

    private function change(array $post, ?array $session = null): array
    {
        $res = $this->request('query/force-password.php', [], $post + ['token' => self::TOKEN], $session ?? $this->emp());
        $this->assertNoPhpErrors($res['body']);
        $res['json'] = json_decode($res['body'], true);
        return $res;
    }

    private function flag(): int
    {
        return (int) $this->row("SELECT must_change_pw FROM empdetails WHERE EmpID = ?", [self::EMP])['must_change_pw'];
    }

    public function testEveryPageSendsTheAccountToChangeItsPassword(): void
    {
        $res = $this->request('coe.php', [], [], $this->asEmployee());
        $this->assertTrue($res['status'] === 302 || str_contains($res['body'], 'change-password.php'), 'sent to change-password.php');
        $this->assertStringNotContainsString('coeRequestForm', $res['body'], 'the page itself is not shown');

        $page = $this->request('change-password.php', [], [], $this->asEmployee());
        $this->assertSame(200, $page['status']);
        $this->assertNoPhpErrors($page['body']);
        $this->assertStringContainsString('Set your own password', $page['body']);

        self::db()->exec("UPDATE empdetails SET must_change_pw = 0 WHERE EmpID = '" . self::EMP . "'");
        $this->assertStringContainsString('coeRequestForm', $this->request('coe.php', [], [], $this->asEmployee())['body'], 'own password: no detour');
    }

    public function testThePasswordRules(): void
    {
        $cases = [
            ['current' => 'wrong', 'new' => 'Abcdef123', 'confirm' => 'Abcdef123'],          // current password
            ['current' => 'Temp1234xy', 'new' => 'Abcdef123', 'confirm' => 'Abcdef124'],     // confirmation
            ['current' => 'Temp1234xy', 'new' => 'short1', 'confirm' => 'short1'],           // length
            ['current' => 'Temp1234xy', 'new' => 'onlyletters', 'confirm' => 'onlyletters'], // digits
            ['current' => 'Temp1234xy', 'new' => 'wedoinc2023', 'confirm' => 'wedoinc2023'], // known default
            ['current' => 'Temp1234xy', 'new' => 'jdelacruz99', 'confirm' => 'jdelacruz99'], // username
            ['current' => 'Temp1234xy', 'new' => 'Temp1234xy', 'confirm' => 'Temp1234xy'],   // unchanged
        ];
        foreach ($cases as $i => $post) {
            $this->assertSame(422, $this->change($post)['status'], "case $i");
        }
        $this->assertSame(1, $this->flag(), 'still forced');
        $this->assertSame(419, $this->request('query/force-password.php', [], ['current' => 'Temp1234xy', 'new' => 'Good-pass-42',
            'confirm' => 'Good-pass-42', 'token' => 'forged'], $this->emp())['status']);
    }

    public function testChangingClearsTheFlag(): void
    {
        $res = $this->change(['current' => 'Temp1234xy', 'new' => 'Good-pass-42', 'confirm' => 'Good-pass-42']);
        $this->assertSame(200, $res['status'], $res['body']);
        $this->assertSame(0, $this->flag());
        $this->assertTrue(password_verify('Good-pass-42', $this->row("SELECT EmpPW FROM empdetails WHERE EmpID = ?", [self::EMP])['EmpPW']));
        $this->assertStringNotContainsString('Good-pass-42', json_encode($this->rows("SELECT * FROM dars")), 'never logged');
    }

    public function testFiveWrongTriesSignTheSessionOut(): void
    {
        $session = $this->emp() + ['pwc_fail' => 4];
        $res = $this->change(['current' => 'nope', 'new' => 'Good-pass-42', 'confirm' => 'Good-pass-42'], $session);
        $this->assertSame(401, $res['status']);
        $this->assertTrue($res['json']['signedOut']);
    }

    public function testEnrollSetsTheFlag(): void
    {
        self::db()->exec("UPDATE accessrights SET eemployee = 2 WHERE EmpID = '" . self::ADMIN . "'");
        $post = ['empidn' => 'TC-0077', 'empfn' => 'New', 'empln' => 'Hire', 'empmn' => '', 'empsuff' => '', 'empposition' => '10',
                 'EmployeeIDNumber' => '', 'uname' => 'nhire', 'empis' => 'N/A', 'empdep' => '1', 'empcompany' => 'TC',
                 'empdatehired' => '2026-10-14', 'empdateresigned' => '', 'empclassification' => '1', 'empagency' => '1', 'emphmoprovider' => '1'];
        foreach (['p', 's', 't'] as $x) { foreach (['_nameofschool', '_yearstarted', '_yeargraduated', '_schooladdress'] as $f) { $post[$x . $f] = ''; } }
        foreach (['pempstreetno', 'pempdistrict', 'pempcity', 'pempprovince', 'pempzipcode', 'pempcountry', 'pempn0', 'pempdob', 'pempgender',
                  'pempcs', 'emphomenumber', 'pempeadd', 'pempppno', 'pemppped', 'pempppia', 'pempsss', 'pemptin', 'emphmo', 'pemppp', 'pemppsd',
                  'pemppdept', 'pempppd', 'pemppagibig', 'pempphno', 'pempumid', 'empcitizen', 'empreligion', 'empbasic', 'empallowance', 'emphourlyrate'] as $f) {
            $post[$f] = $f === 'pempdob' ? '1990-01-01' : ($f === 'empbasic' || $f === 'empallowance' || $f === 'emphourlyrate' ? '1' : '');
        }
        $this->request('query/Query-InsertEmployeeInfo.php', [], $post, $this->asAdmin());
        $this->assertSame('1', (string) $this->row("SELECT must_change_pw FROM empdetails WHERE EmpID = 'TC-0077'")['must_change_pw']);
    }
}
