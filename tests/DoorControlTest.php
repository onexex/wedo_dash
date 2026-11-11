<?php
namespace Wedo\Tests;

use Wedo\Tests\Support\AppTestCase;

/**
 * Management > Door Control: holders of the `doorctl` access right see whether
 * the biometric device is online and release the door's lock for a few seconds.
 *
 *   door.php                 the page (redirects away without the right)
 *   query/door-action.php    status + unlock (401 / 405 / 419 / 403 / JSON)
 *   includes/door-lib.php    the ZK protocol, who may unlock, the log
 *
 * No test ever talks to a real device: every test points WEDO_DOOR_HOST at
 * tests/Support/fake-zk-device.php on 127.0.0.1 (or at nothing at all).
 */
final class DoorControlTest extends AppTestCase
{
    private const TOKEN = 'door-test-token';
    private const KEY   = 4321;

    /** @var resource|null */
    private $device = null;
    private string $seen = '';

    public static function setUpBeforeClass(): void
    {
        require_once dirname(__DIR__) . '/includes/door-lib.php';
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->seen = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'wedo-test-door-' . bin2hex(random_bytes(4)) . '.log';
        putenv('WEDO_DOOR_HOST=-');   // not an address: "no device" until a test starts the fake one
        putenv('WEDO_DOOR_PORT');
        putenv('WEDO_DOOR_KEY');
        putenv('WEDO_DOOR_SECONDS=5');
    }

    protected function tearDown(): void
    {
        if ($this->device) { proc_terminate($this->device); proc_close($this->device); $this->device = null; }
        @unlink($this->seen);
        foreach (['WEDO_DOOR_HOST', 'WEDO_DOOR_PORT', 'WEDO_DOOR_KEY', 'WEDO_DOOR_SECONDS'] as $v) { putenv($v); }
        parent::tearDown();
    }

    /** Start the pretend device and point WeDo at it; $ourKey is the Comm Key WeDo will use. */
    private function device(int $ourKey = self::KEY, string $mode = ''): void
    {
        $cmd = [PHP_BINARY, dirname(__DIR__) . '/tests/Support/fake-zk-device.php', (string) self::KEY, $this->seen, $mode];
        $this->device = proc_open($cmd, [1 => ['pipe', 'w'], 2 => ['file', $this->seen . '.err', 'a']], $pipes);
        $port = (int) fgets($pipes[1]);
        $this->assertGreaterThan(0, $port, 'the fake device started');
        putenv('WEDO_DOOR_HOST=127.0.0.1');
        putenv('WEDO_DOOR_PORT=' . $port);
        putenv('WEDO_DOOR_KEY=' . $ourKey);
        @unlink($this->seen . '.err');
    }

    /** What the pretend door was told, one line per lock release. */
    private function released(): array
    {
        return is_file($this->seen) ? file($this->seen, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) : [];
    }

    private function as(string $id): array
    {
        return ['id' => $id, 'UserType' => $id === self::ADMIN ? 1 : 3, 'CompanyName' => 'TestCo', 'CompanyColor' => '#f93627',
                'CompID' => 'TC', 'door_csrf' => self::TOKEN];
    }

    private function grant(string $emp): void
    {
        self::db()->prepare("UPDATE accessrights SET doorctl = 2 WHERE EmpID = ?")->execute([$emp]);
    }

    /** @return array{0:int, 1:array} status code + decoded JSON */
    private function act(?array $session, array $post, string $token = self::TOKEN): array
    {
        $res = $this->request('query/door-action.php', [], $post + ['token' => $token], $session, [], ['REMOTE_ADDR' => '198.51.100.7']);
        $this->assertNoPhpErrors($res['body']);
        return [$res['status'], json_decode($res['body'], true) ?? []];
    }

    private function log(): array
    {
        return $this->rows("SELECT EmpID, Result, Detail, Note, Seconds, IP FROM door_log ORDER BY id");
    }

    // ------------------------------------------------------------------ who may

    public function testRefusedWhenNotSignedIn(): void
    {
        $this->device();
        $this->assertSame(401, $this->act(null, ['action' => 'unlock'])[0]);
        $this->assertSame(302, $this->request('door.php')['status']);
        $this->assertSame([], $this->released());
        $this->assertSame([], $this->log());
    }

    public function testRefusedWithoutTheRight(): void
    {
        $this->device();
        // a super user without the right: still no
        foreach ([self::ADMIN, self::EMP] as $who) {
            $this->assertSame(403, $this->act($this->as($who), ['action' => 'unlock'])[0], $who);
            $this->assertSame(403, $this->act($this->as($who), ['action' => 'status'])[0], $who);
            $this->assertSame(302, $this->request('door.php', [], [], $this->as($who))['status'], $who);
        }
        $this->assertSame([], $this->released());
        $this->assertSame([], $this->log());

        // and the menu doesn't offer it
        $menu = $this->request('idcard.php', [], [], $this->as(self::ADMIN))['body'];
        $this->assertNull($this->dom($menu)->query("//a[@href='door']")->item(0));
    }

    public function testRefusesAForgedRequest(): void
    {
        $this->device();
        $this->grant(self::ADMIN);
        $this->assertSame(419, $this->act($this->as(self::ADMIN), ['action' => 'unlock'], 'not-the-token')[0]);
        $get = $this->request('query/door-action.php', ['action' => 'unlock', 'token' => self::TOKEN], [], $this->as(self::ADMIN));
        $this->assertSame(405, $get['status']);
        $this->assertSame([], $this->released());
    }

    public function testThePageOpensWithTheRightAndIsInTheManagementMenu(): void
    {
        $this->grant(self::ADMIN);
        $res = $this->request('door.php', [], [], $this->as(self::ADMIN));
        $this->assertSame(200, $res['status']);
        $this->assertNoPhpErrors($res['body']);
        $x = $this->dom($res['body']);
        $this->assertNotNull($x->query("//div[@data-sec='management']//a[@href='door' and contains(@class,'is-active')]")->item(0));
        $this->assertNotNull($x->query("//button[@id='doorUnlock' and @disabled]")->item(0), 'Unlock starts disabled until the device answers');
        $this->assertStringContainsString(self::TOKEN, $res['body']);
        // the Comm Key never reaches the browser
        putenv('WEDO_DOOR_KEY=987654');
        $this->assertStringNotContainsString('987654', $this->request('door.php', [], [], $this->as(self::ADMIN))['body']);
    }

    /** It is switched on like any other right (Access Rights > Management), and asks first: it opens a door. */
    public function testGrantedFromAccessRightsAsASensitiveRight(): void
    {
        $body = $this->request('searchaccessrights.php', ['q' => self::EMP], [], $this->asAdmin())['body'];
        $this->assertNoPhpErrors($body);
        $switch = $this->dom($body)->query("//*[@id='doorctl']")->item(0);
        $this->assertNotNull($switch, 'Door Control switch');
        $this->assertNotNull($this->dom($body)->query("//*[@data-sensitive='1' and (@id='doorctl' or .//*[@id='doorctl'])]")->item(0), 'marked sensitive');

        $this->assertSame(200, $this->request('updateaccess.php', ['empid' => self::EMP, 'mponoff' => 'ON', 'term' => 'doorctl'], [], $this->asAdmin())['status']);
        $this->assertSame(2, (int) $this->row("SELECT doorctl FROM accessrights WHERE EmpID = ?", [self::EMP])['doorctl']);
        $this->device();
        $this->assertSame(200, $this->act($this->as(self::EMP), ['action' => 'status'])[0]);
    }

    // ------------------------------------------------------------------ status

    public function testStatusOnlineWithTheRightCommKey(): void
    {
        $this->device();
        $this->grant(self::ADMIN);
        [$code, $j] = $this->act($this->as(self::ADMIN), ['action' => 'status']);
        $this->assertSame(200, $code);
        $this->assertSame('online', $j['device']['state']);
        $this->assertSame('FAKE0001', $j['device']['serial']);
        $this->assertSame('F04', $j['device']['model']);
        $this->assertSame('2026-11-11 08:30:15', $j['device']['clock']);
        $this->assertSame([], $j['log']);
        $this->assertSame([], $this->released(), 'checking never opens the door');
        $this->assertStringNotContainsString((string) self::KEY, json_encode($j));
    }

    public function testStatusSaysWhenTheCommKeyIsWrong(): void
    {
        $this->device(1111);
        $this->grant(self::ADMIN);
        [$code, $j] = $this->act($this->as(self::ADMIN), ['action' => 'status']);
        $this->assertSame(200, $code);
        $this->assertSame('auth', $j['device']['state']);
    }

    public function testStatusOfflineAndNotSetUp(): void
    {
        $this->grant(self::ADMIN);
        [, $j] = $this->act($this->as(self::ADMIN), ['action' => 'status']);
        $this->assertSame('unset', $j['device']['state']);

        // a local port nothing listens on
        $free = stream_socket_server('tcp://127.0.0.1:0');
        $port = (int) substr(strrchr(stream_socket_get_name($free, false), ':'), 1);
        fclose($free);
        putenv('WEDO_DOOR_HOST=127.0.0.1');
        putenv('WEDO_DOOR_PORT=' . $port);
        [$code, $j] = $this->act($this->as(self::ADMIN), ['action' => 'status']);
        $this->assertSame(200, $code);
        $this->assertSame('offline', $j['device']['state']);
    }

    // ------------------------------------------------------------------ enrolled people

    public function testEnrolledPeopleAreListedWithoutTheirDevicePasswords(): void
    {
        $this->device();
        $this->grant(self::ADMIN);
        [$code, $j] = $this->act($this->as(self::ADMIN), ['action' => 'enrolled']);
        $this->assertSame(200, $code);
        $this->assertSame(5, $j['fingers']);
        // sorted by name; the one without a name sorts first
        $this->assertSame([
            ['uid' => 3, 'userId' => '1003', 'name' => '', 'role' => 'User'],
            ['uid' => 1, 'userId' => '1001', 'name' => 'Ada Admin', 'role' => 'Admin'],
            ['uid' => 2, 'userId' => '1002', 'name' => 'Juan Dela Cruz', 'role' => 'User'],
        ], $j['users']);
        $raw = json_encode($j);
        $this->assertStringNotContainsString('SECRET', $raw);
        $this->assertStringNotContainsString('5550001', $raw, 'card numbers stay on the device');
        $this->assertSame([], $this->released(), 'looking never opens the door');
    }

    public function testEnrolledPeopleFromAnOldFirmwareDevice(): void
    {
        $this->device(self::KEY, 'old');
        $this->grant(self::ADMIN);
        [$code, $j] = $this->act($this->as(self::ADMIN), ['action' => 'enrolled']);
        $this->assertSame(200, $code);
        $this->assertSame(['1003', '1001', '1002'], array_column($j['users'], 'userId'));
        $this->assertSame(['', 'Ada Admi', 'Juan Del'], array_column($j['users'], 'name'), 'old devices keep 8 letters');
        $this->assertStringNotContainsString('SECRE', json_encode($j));
    }

    public function testEnrolledPeopleNeedTheRightAndAWorkingDevice(): void
    {
        $this->device(1111);   // wrong Comm Key
        $this->assertSame(403, $this->act($this->as(self::ADMIN), ['action' => 'enrolled'])[0]);
        $this->grant(self::ADMIN);
        [$code, $j] = $this->act($this->as(self::ADMIN), ['action' => 'enrolled']);
        $this->assertSame(422, $code);
        $this->assertStringContainsString('Comm Key', $j['msg']);
        $this->assertArrayNotHasKey('users', $j);
    }

    // ------------------------------------------------------------------ correcting a name

    public function testRenameChangesOnlyTheNameOnTheDevice(): void
    {
        $this->device();
        $this->grant(self::ADMIN);
        [$code, $j] = $this->act($this->as(self::ADMIN), ['action' => 'rename', 'uid' => 1, 'userId' => '1001', 'name' => '  Ada   Lovelace ']);
        $this->assertSame(200, $code);
        $this->assertSame('Ada Lovelace', $j['name']);

        // the record written back is the device's own, with just the name replaced
        $written = $this->released();
        $this->assertCount(1, $written);
        $rec = hex2bin(substr($written[0], strlen('write ')));
        $this->assertSame(72, strlen($rec));
        $this->assertSame(['uid' => 1, 'priv' => 14], unpack('vuid/Cpriv', $rec), 'still a device admin');
        $this->assertSame('SECRETPW', substr($rec, 3, 8), 'device password untouched');
        $this->assertSame(str_pad('Ada Lovelace', 24, "\0"), substr($rec, 11, 24));
        $this->assertSame(5550001, unpack('V', substr($rec, 35, 4))[1], 'card untouched');
        $this->assertSame('1001', rtrim(substr($rec, 48, 24), "\0"), 'ID untouched');

        // the device now lists the new name, and the change is on record
        [, $list] = $this->act($this->as(self::ADMIN), ['action' => 'enrolled']);
        $this->assertSame(['', 'Ada Lovelace', 'Juan Dela Cruz'], array_column($list['users'], 'name'));
        $log = $this->rows("SELECT EmpID, Action, Result, Detail FROM door_log");
        $this->assertSame([['EmpID' => self::ADMIN, 'Action' => 'rename', 'Result' => 'ok', 'Detail' => 'ID 1001: Ada Admin → Ada Lovelace']], $log);
        $this->assertSame('rename', $j['log'][0]['action']);
        $this->assertStringNotContainsString('SECRET', json_encode($j));

        // a name change is not an unlock: the door can still be opened straight after
        $this->assertSame(200, $this->act($this->as(self::ADMIN), ['action' => 'unlock'])[0]);
    }

    public function testRenameRefusesNamesTheDeviceCannotHold(): void
    {
        $this->device();
        $this->grant(self::ADMIN);
        foreach (['' => 'Type the name', '   ' => 'Type the name', 'Niño Peña' => 'plain letters', '<b>x</b>' => 'plain letters',
                  str_repeat('A', 24) => '23 characters'] as $name => $why) {
            [$code, $j] = $this->act($this->as(self::ADMIN), ['action' => 'rename', 'uid' => 2, 'userId' => '1002', 'name' => $name]);
            $this->assertSame(422, $code, "'$name'");
            $this->assertStringContainsString($why, $j['msg'], "'$name'");
        }
        $this->assertSame([], $this->released(), 'nothing was written');
        $this->assertSame(200, $this->act($this->as(self::ADMIN), ['action' => 'rename', 'uid' => 2, 'userId' => '1002', 'name' => str_repeat('A', 23)])[0]);
    }

    public function testRenameRefusesAStaleRowAndPeopleWithoutTheRight(): void
    {
        $this->device();
        $post = ['action' => 'rename', 'uid' => 2, 'userId' => '1002', 'name' => 'Juan D. Cruz'];
        $this->assertSame(403, $this->act($this->as(self::EMP), $post)[0]);

        $this->grant(self::EMP);
        // uid 2 is no longer ID 1001 / uid 9 is not on the device
        foreach ([['uid' => 2, 'userId' => '1001'], ['uid' => 9, 'userId' => '1002']] as $stale) {
            [$code, $j] = $this->act($this->as(self::EMP), $stale + $post);
            $this->assertSame(422, $code);
            $this->assertStringContainsString('NOT changed', $j['msg']);
        }
        $this->assertSame([], $this->released());
        $this->assertSame(['failed', 'failed'], array_column($this->rows("SELECT Result FROM door_log WHERE Action = 'rename'"), 'Result'));
    }

    public function testRenameOnAnOldFirmwareDeviceKeepsEightLetters(): void
    {
        $this->device(self::KEY, 'old');
        $this->grant(self::ADMIN);
        [$code, $j] = $this->act($this->as(self::ADMIN), ['action' => 'rename', 'uid' => 2, 'userId' => '1002', 'name' => 'Juan Dela Cruz']);
        $this->assertSame(422, $code);
        $this->assertStringContainsString('too long for this device', $j['msg']);

        $this->assertSame(200, $this->act($this->as(self::ADMIN), ['action' => 'rename', 'uid' => 2, 'userId' => '1002', 'name' => 'Juan DC'])[0]);
        $rec = hex2bin(substr($this->released()[0], strlen('write ')));
        $this->assertSame(28, strlen($rec));
        $this->assertSame("Juan DC\0", substr($rec, 8, 8));
        $this->assertSame(1002, unpack('V', substr($rec, 24, 4))[1], 'ID untouched');
    }

    // ------------------------------------------------------------------ unlock

    public function testUnlockReleasesTheLockAndLogsWho(): void
    {
        $this->device();
        $this->grant(self::EMP);
        [$code, $j] = $this->act($this->as(self::EMP), ['action' => 'unlock', 'note' => "  fingerprint   not reading \n"]);
        $this->assertSame(200, $code);
        $this->assertSame('ok', $j['status']);
        $this->assertSame(['unlock 50'], $this->released(), '5 seconds, in the device\'s tenths');
        $this->assertSame([['EmpID' => self::EMP, 'Result' => 'ok', 'Detail' => null, 'Note' => 'fingerprint not reading', 'Seconds' => 5, 'IP' => '198.51.100.7']],
                          $this->log());
        $this->assertSame('Juan Dela Cruz', $j['log'][0]['who']);
        $this->assertTrue($j['log'][0]['ok']);

        // a double tap doesn't fire the lock again
        [$code, $j] = $this->act($this->as(self::EMP), ['action' => 'unlock']);
        $this->assertSame(422, $code);
        $this->assertStringContainsString('just unlocked', $j['msg']);
        $this->assertCount(1, $this->released());
    }

    public function testUnlockSecondsComeFromConfigAndAreCapped(): void
    {
        $this->device();
        $this->grant(self::ADMIN);
        putenv('WEDO_DOOR_SECONDS=600');
        $this->assertSame(200, $this->act($this->as(self::ADMIN), ['action' => 'unlock'])[0]);
        $this->assertSame(['unlock 150'], $this->released());
    }

    public function testAFailedUnlockIsLoggedAndSaysTheDoorStayedLocked(): void
    {
        $this->grant(self::ADMIN);

        $this->device(1111);   // wrong Comm Key
        [$code, $j] = $this->act($this->as(self::ADMIN), ['action' => 'unlock']);
        $this->assertSame(422, $code);
        $this->assertStringContainsString('NOT unlocked', $j['msg']);
        $this->assertSame([], $this->released());
        proc_terminate($this->device); proc_close($this->device); $this->device = null;

        $this->device(self::KEY, 'refuse');   // the device itself says no
        [$code] = $this->act($this->as(self::ADMIN), ['action' => 'unlock']);
        $this->assertSame(422, $code);
        $this->assertSame([], $this->released());

        $log = $this->log();
        $this->assertSame(['failed', 'failed'], array_column($log, 'Result'));
        $this->assertStringContainsString('Comm Key', $log[0]['Detail']);
        $this->assertStringContainsString('refused', $log[1]['Detail']);
    }

    // ------------------------------------------------------------------ protocol

    public function testCommKeyAndChecksumMatchTheReferenceImplementation(): void
    {
        // values from the open-source pyzk implementation of the same protocol
        $this->assertSame('617d3279', bin2hex(zk_commkey(0, 0)));
        $this->assertSame('267f32eb', bin2hex(zk_commkey(123456, 4660)));
        $this->assertSame('22813296', bin2hex(zk_commkey(999999, 65535)));
        $this->assertSame('61fd32ad', bin2hex(zk_commkey(1, 54321)));
        $this->assertSame(64535, zk_checksum(pack('v4', 1000, 0, 0, 65534)));
        $this->assertSame(42169, zk_checksum(pack('v4', 11, 0, 4660, 3) . "~SerialNumber\0"));
        $this->assertSame(60786, zk_checksum(pack('v4', 31, 0, 4660, 7) . pack('V', 50)));
    }

    public function testConfigRefusesAnythingThatIsNotAnAddress(): void
    {
        foreach (['-', '', '10.0.0.1/../x', 'host name', 'a:b', 'tcp://x'] as $bad) {
            putenv('WEDO_DOOR_HOST=' . $bad);
            if ($bad === '' && is_file(dirname(__DIR__) . '/config.local.php')) { continue; }   // falls back to the developer's own file
            $this->assertNull(door_config(), "'$bad'");
        }
        putenv('WEDO_DOOR_HOST=door.example.net');
        putenv('WEDO_DOOR_PORT=99999');
        $c = door_config();
        $this->assertSame(['door.example.net', 4370, 5], [$c['host'], $c['port'], $c['seconds']]);
    }
}
