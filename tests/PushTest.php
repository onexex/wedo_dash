<?php
namespace Wedo\Tests;

use Wedo\Tests\Support\AppTestCase;

/**
 * Mobile push notifications (WeDo app) — server side.
 *
 *   includes/push-lib.php          device registry, FCM sending, request watchers
 *   query/Query-devicetoken.php    the app registers / unregisters its FCM token here
 *
 * Firebase itself is never called: tests run without a service-account key, which
 * must leave every push call a silent no-op.
 */
final class PushTest extends AppTestCase
{
    private const TOKEN  = 'push-test-token';
    private const DEVICE = 'fGx1-abc:APA91bH_test.token-123';

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        require_once dirname(__DIR__) . '/includes/push-lib.php';
    }

    private function as(string $id, int $type = 3): array
    {
        return ['id' => $id, 'UserType' => $type, 'CompanyName' => 'TestCo', 'CompanyColor' => '#f93627', 'CompID' => 'TC', 'msg_csrf' => self::TOKEN];
    }

    private function device(?array $session, array $post): array
    {
        $res = $this->request('query/Query-devicetoken.php', [], $post, $session);
        $this->assertNoPhpErrors($res['body']);
        $res['json'] = json_decode($res['body'], true);
        $this->assertIsArray($res['json'], "not JSON:\n" . $res['body']);
        return $res;
    }

    private function devices(): array
    {
        return $this->rows("SELECT EmpID, token, platform FROM push_devices ORDER BY id");
    }

    public function testRegisterNeedsLoginAndPageToken(): void
    {
        $post = ['action' => 'register', 'device' => self::DEVICE, 'token' => self::TOKEN];

        $this->assertSame(401, $this->device(null, $post)['status']);
        $this->assertSame(419, $this->device($this->as(self::EMP), ['token' => 'wrong'] + $post)['status']);
        $this->assertSame([], $this->devices());
    }

    public function testRegisterRejectsMalformedDeviceToken(): void
    {
        $res = $this->device($this->as(self::EMP), ['action' => 'register', 'device' => "x'); DROP TABLE push_devices; --", 'token' => self::TOKEN]);
        $this->assertSame(422, $res['status']);
        $this->assertSame([], $this->devices());
    }

    public function testRegisterStoresThePhoneForTheSignedInEmployee(): void
    {
        $res = $this->device($this->as(self::EMP), ['action' => 'register', 'device' => self::DEVICE, 'platform' => 'android', 'token' => self::TOKEN]);

        $this->assertSame(200, $res['status']);
        $this->assertFalse($res['json']['push'], 'no Firebase key in tests: push must report as off');
        $this->assertSame([['EmpID' => self::EMP, 'token' => self::DEVICE, 'platform' => 'android']], $this->devices());

        // registering again (every app launch) keeps one row
        $this->device($this->as(self::EMP), ['action' => 'register', 'device' => self::DEVICE, 'token' => self::TOKEN]);
        $this->assertCount(1, $this->devices());
    }

    public function testSigningInAsSomeoneElseOnTheSamePhoneMovesTheDevice(): void
    {
        $this->device($this->as(self::EMP), ['action' => 'register', 'device' => self::DEVICE, 'token' => self::TOKEN]);
        $this->device($this->as(self::ADMIN, 1), ['action' => 'register', 'device' => self::DEVICE, 'platform' => 'ios', 'token' => self::TOKEN]);

        $this->assertSame([['EmpID' => self::ADMIN, 'token' => self::DEVICE, 'platform' => 'ios']], $this->devices());
    }

    public function testUnregisterOnlyRemovesYourOwnDevice(): void
    {
        $this->device($this->as(self::EMP), ['action' => 'register', 'device' => self::DEVICE, 'token' => self::TOKEN]);

        // someone else cannot remove Juan's phone
        $this->device($this->as(self::ADMIN, 1), ['action' => 'unregister', 'device' => self::DEVICE, 'token' => self::TOKEN]);
        $this->assertCount(1, $this->devices());

        $this->device($this->as(self::EMP), ['action' => 'unregister', 'device' => self::DEVICE, 'token' => self::TOKEN]);
        $this->assertSame([], $this->devices());
    }

    public function testSendingWithoutFirebaseKeyIsASilentNoOp(): void
    {
        $db = self::db();
        push_register($db, self::EMP, self::DEVICE);

        $this->assertFalse(push_enabled());
        push_send($db, self::EMP, 'Leave request approved', 'Your leave request was approved by HR.');
        push_request_decided($db, 'HL', self::EMP, 4, self::ADMIN);
        push_chat_message($db, self::ADMIN, self::EMP, 'hello');

        $this->assertCount(1, $this->devices(), 'devices are only dropped when Firebase reports them invalid');
    }

    public function testNameAndLabelHelpers(): void
    {
        $this->assertSame('Juan Dela Cruz', push_name(self::db(), self::EMP));
        $this->assertSame('NOPE-1', push_name(self::db(), 'NOPE-1'));
        $this->assertSame('leave', push_type_label('HL'));
        $this->assertSame('official business', push_type_label('ob'));
        $this->assertSame([self::ADMIN], push_hr_of(self::db(), self::EMP));
    }
}
