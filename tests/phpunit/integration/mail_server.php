<?php

use PHPUnit\Framework\TestCase;

/**
 * Real-server integration tests for Hm_Mailbox against a live IMAP/SMTP stack.
 *
 * @package modules
 * @subpackage core/tests
 */
class Hm_Test_Integration_Mail_Server extends TestCase {

    const IMAP_HOST = 'localhost';
    const IMAP_PORT = 143;
    const SMTP_HOST = 'localhost';
    const SMTP_PORT = 25;
    const USERNAME = 'testuser';
    const PASSWORD = 'testuser';
    const RECIPIENT = 'testuser@localhost.org';

    public function setUp(): void {
        if (getenv('CYPHT_REAL_MAIL_SERVER') !== 'true') {
            $this->markTestSkipped('CYPHT_REAL_MAIL_SERVER not set to true; skipping real mail server integration tests.');
        }
        foreach ([[self::IMAP_HOST, self::IMAP_PORT], [self::SMTP_HOST, self::SMTP_PORT]] as [$host, $port]) {
            $conn = @fsockopen($host, $port, $errno, $errstr, 3);
            if (!$conn) {
                $this->markTestSkipped(sprintf('Cannot reach %s:%d (%s); skipping real mail server integration tests.', $host, $port, $errstr));
            }
            fclose($conn);
        }

        require_once APP_PATH.'modules/imap/hm-imap.php';
        require_once APP_PATH.'modules/smtp/hm-smtp.php';
        require_once APP_PATH.'modules/core/hm-mailbox.php';

        $this->assertFalse(
            (new ReflectionClass('Hm_IMAP'))->hasProperty('allow_connection'),
            'Hm_IMAP resolved to the phpunit stub instead of the real class; '
            .'CYPHT_REAL_MAIL_SERVER must be set before bootstrap.php loads tests/phpunit/stubs.php.'
        );
    }

    private function make_mailbox($type, array $extra = []) {
        $config = array_merge([
            'type' => $type,
            'server' => $type === 'imap' ? self::IMAP_HOST : self::SMTP_HOST,
            'port' => $type === 'imap' ? self::IMAP_PORT : self::SMTP_PORT,
            'tls' => false,
            'username' => self::USERNAME,
            'password' => self::PASSWORD,
        ], $extra);
        return new Hm_Mailbox('integration_test_server', new Hm_Mock_Config(), new Hm_Mock_Session(), $config);
    }

    /**
     * Dump the raw protocol exchange for diagnostics. Hm_Mailbox::get_debug()
     * only supports IMAP; for SMTP we reach into Hm_SMTP's private debug/command/
     * response logs via reflection since it has no public accessor.
     */
    private function protocol_log($mailbox) {
        if ($mailbox->is_imap()) {
            return print_r($mailbox->get_debug(), true);
        }
        $connection = $mailbox->get_connection();
        $ref = new ReflectionClass($connection);
        $dump = [];
        foreach (['debug', 'commands', 'responses'] as $prop) {
            if ($ref->hasProperty($prop)) {
                $p = $ref->getProperty($prop);
                $p->setAccessible(true);
                $dump[$prop] = $p->getValue($connection);
            }
        }
        return print_r($dump, true);
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_imap_connects_and_authenticates() {
        $mailbox = $this->make_mailbox('imap');

        $connected = $mailbox->connect();

        $this->assertTrue($connected, 'Hm_Mailbox::connect() should return true for a successful IMAP login. Protocol log: '.$this->protocol_log($mailbox));
        $this->assertTrue($mailbox->authed(), 'Protocol log: '.$this->protocol_log($mailbox));
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_imap_can_select_inbox_and_list_folders() {
        $mailbox = $this->make_mailbox('imap');
        $mailbox->connect();

        $folders = $mailbox->get_folders();

        $this->assertIsArray($folders, 'Protocol log: '.$this->protocol_log($mailbox));
        $this->assertTrue($mailbox->select_folder('INBOX'), 'Protocol log: '.$this->protocol_log($mailbox));
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_smtp_connects_and_authenticates() {
        $mailbox = $this->make_mailbox('smtp');

        $mailbox->connect();

        $this->assertTrue($mailbox->authed(), 'Hm_Mailbox::authed() should be true after a successful SMTP AUTH. Protocol log: '.$this->protocol_log($mailbox));
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_send_message_over_smtp_then_read_it_back_over_imap() {
        $marker = 'cypht-integration-test-'.bin2hex(random_bytes(8));

        $smtp_mailbox = $this->make_mailbox('smtp');
        $smtp_mailbox->connect();
        $this->assertTrue($smtp_mailbox->authed(), 'SMTP auth must succeed before sending the test message. Protocol log: '.$this->protocol_log($smtp_mailbox));

        $raw_message = "From: ".self::RECIPIENT."\r\n"
            ."To: ".self::RECIPIENT."\r\n"
            ."Subject: ".$marker."\r\n"
            ."Date: ".date('r')."\r\n"
            ."\r\n"
            ."Cypht integration test body.\r\n";

        $sent = $smtp_mailbox->send_message(self::RECIPIENT, [self::RECIPIENT], $raw_message);
        $this->assertTrue($sent, 'send_message() should report success. Protocol log: '.$this->protocol_log($smtp_mailbox));

        // local delivery via Postfix -> Dovecot is not instant; poll briefly.
        $found = false;
        $imap_mailbox = $this->make_mailbox('imap');
        $imap_mailbox->connect();
        for ($attempt = 0; $attempt < 10 && !$found; $attempt++) {
            if ($attempt > 0) {
                sleep(1);
            }
            [, $messages] = $imap_mailbox->get_messages('INBOX', 'arrival', true, false, 0, 50);
            foreach ($messages as $msg) {
                if (isset($msg['subject']) && str_contains($msg['subject'], $marker)) {
                    $found = true;
                    break;
                }
            }
        }

        $this->assertTrue($found, "Message with subject marker '$marker' was not found in INBOX via IMAP after sending over SMTP. Protocol log: ".$this->protocol_log($imap_mailbox));
    }
}
