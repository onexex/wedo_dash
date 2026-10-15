<?php
namespace Wedo\Tests;

use Wedo\Tests\Support\AppTestCase;

/**
 * Email through Brevo (includes/mail-lib.php). Nothing is ever sent: the
 * bootstrap points WEDO_MAIL_LOG at a file, and each message is written there.
 */
final class MailTest extends AppTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        require_once dirname(__DIR__) . '/includes/mail-lib.php';
    }

    protected function setUp(): void
    {
        parent::setUp();
        @unlink($GLOBALS['WEDO_MAIL_LOG']);
        putenv('BREVO_API_KEY=test-key');
        putenv('MAIL_FROM=hr@example.com');
        putenv('MAIL_FROM_NAME=WeDo Test');
    }

    protected function tearDown(): void
    {
        putenv('BREVO_API_KEY');
        putenv('MAIL_FROM');
        putenv('MAIL_FROM_NAME');
        @unlink($GLOBALS['WEDO_MAIL_LOG']);
        parent::tearDown();
    }

    /** @return array<int, array> the messages "sent" so far */
    private function sent(): array
    {
        $f = $GLOBALS['WEDO_MAIL_LOG'];
        return is_file($f) ? array_map(fn ($l) => json_decode($l, true), file($f, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES)) : [];
    }

    public function testAMessageGoesOutWithTheConfiguredSender(): void
    {
        $this->assertTrue(wd_mail_enabled());
        $r = wd_mail_send('ana@example.com', 'Ana Cruz', 'Hello', '<p>Hi</p>', 'Hi');
        $this->assertTrue($r['ok'], $r['error']);
        $m = $this->sent()[0];
        $this->assertSame(['hr@example.com', 'WeDo Test'], [$m['sender']['email'], $m['sender']['name']]);
        $this->assertSame('ana@example.com', $m['to'][0]['email']);
        $this->assertSame(['Hello', '<p>Hi</p>', 'Hi'], [$m['subject'], $m['htmlContent'], $m['textContent']]);

        wd_mail_send('boss@example.com', '', 'Test', '<p>x</p>');
        $this->assertSame(['email' => 'boss@example.com'], $this->sent()[1]['to'][0], 'no empty name: Brevo refuses "name is missing"');
    }

    public function testEmailIsOffWithoutAValidSender(): void
    {
        putenv('MAIL_FROM=not-an-email');
        $this->assertFalse(wd_mail_enabled());
        $this->assertFalse(wd_mail_send('ana@example.com', 'Ana', 'Hello', '<p>Hi</p>')['ok']);
        $this->assertCount(0, $this->sent(), 'nothing goes out');
    }

    public function testABadRecipientIsRefused(): void
    {
        $this->assertFalse(wd_mail_send('nope', 'Ana', 'Hello', '<p>Hi</p>')['ok']);
        $this->assertCount(0, $this->sent());
    }

    public function testTheSetupCheckNamesWhatIsMissing(): void
    {
        $levels = fn (array $d) => array_column($d['checks'], 'level', 'text');

        $ok = wd_mail_diagnose();
        $this->assertTrue($ok['enabled']);
        $this->assertSame('hr@example.com', $ok['from']);
        $this->assertNotContains('bad', array_slice(array_values($levels($ok)), 1), 'key + sender fine (the settings file itself may be absent in tests)');
        $this->assertStringNotContainsString('test-key', json_encode($ok), 'the key is never shown');

        putenv('BREVO_API_KEY=xsmtpsib-abc123');
        $smtp = wd_mail_diagnose();
        $this->assertStringContainsString('SMTP key', implode(' ', array_keys($levels($smtp))), 'the usual mix-up is named');

        putenv('MAIL_FROM=careers');
        $from = wd_mail_diagnose();
        $this->assertFalse($from['enabled']);
        $this->assertStringContainsString('not a valid email address', implode(' ', array_keys($levels($from))));
    }

    public function testTheLayoutEscapesItsTitle(): void
    {
        $html = wd_mail_layout('Hi <script>x</script>', '<p>body</p>');
        $this->assertStringContainsString('Hi &lt;script&gt;', $html);
        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringContainsString('Data Privacy Act', $html);
        $this->assertSame("a &lt;b&gt;<br>\nc", wd_mail_text("a <b>\nc"));
    }
}
