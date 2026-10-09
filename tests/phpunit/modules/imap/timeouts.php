<?php

use PHPUnit\Framework\TestCase;

require_once APP_PATH.'modules/imap/hm-imap.php';

/** Exercise real socket reads rather than the IMAP response stub. */
class Hm_IMAP_Timeout_Test_Client extends Hm_IMAP {
    public function __construct($stream, $timeout = 0.03, $deadline = null) {
        $this->handle = $stream;
        $this->read_timeout = $timeout;
        $this->read_deadline = $deadline;
        $this->command_count = 1;
        $this->state = 'authenticated';
        stream_set_timeout($stream, 0, 30000);
    }

    public function has_stream() { return is_resource($this->handle); }

    public function prepare_authentication() {
        $this->command_count = 0;
        $this->auth = 'cram-md5';
        $this->tls = true;
    }

    public function limited_literal() {
        return $this->parse_line("{50}\r\n", 0, 1, 8192);
    }
}

class Hm_Test_IMAP_Timeouts extends TestCase {
    private $peer;
    private $client;

    protected function setUp(): void {
        list($stream, $this->peer) = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);
        $this->client = new Hm_IMAP_Timeout_Test_Client($stream);
    }

    protected function tearDown(): void {
        if (is_resource($this->peer)) {
            fclose($this->peer);
        }
        $this->client->disconnect();
        Hm_IMAP_List::$request_deadline = null;
    }

    private function assert_read_aborts() {
        $start = microtime(true);
        $this->assertSame([], $this->client->get_response());
        $this->assertLessThan(1, microtime(true) - $start);
        $this->assertFalse($this->client->has_stream());
        $this->assertSame('disconnected', $this->client->get_state());
        /* Cleanup must not send LOGOUT and wait on the failed socket again. */
        $this->client->disconnect();
        $this->assertLessThan(1, microtime(true) - $start);
    }

    public function test_silent_server_does_not_hold_a_worker() {
        $this->assert_read_aborts();
    }

    public function test_authentication_stops_when_the_greeting_times_out() {
        $this->client->prepare_authentication();
        $this->assertFalse($this->client->authenticate('user', 'password'));
        $this->assertFalse($this->client->has_stream());
    }

    public function test_authentication_stops_when_the_challenge_times_out() {
        $this->client->prepare_authentication();
        fwrite($this->peer, "* OK ready\r\n* CAPABILITY IMAP4rev1\r\nA1 OK capability\r\n");
        $this->assertFalse($this->client->authenticate('user', 'password'));
        $this->assertFalse($this->client->has_stream());
    }

    public function test_cram_authentication_does_not_read_a_second_greeting() {
        $this->client->prepare_authentication();
        fwrite($this->peer, "* OK ready\r\n* CAPABILITY IMAP4rev1\r\nA1 OK capability\r\n+ dGVzdA==\r\nA2 OK authenticated\r\n* CAPABILITY IMAP4rev1\r\nA3 OK capability\r\n");
        $this->assertTrue($this->client->authenticate('user', 'password'));
        $this->assertTrue($this->client->has_stream());
        fwrite($this->peer, "* BYE closing\r\nA4 OK logout\r\n");
    }

    public function test_partial_line_does_not_retry_after_timeout() {
        fwrite($this->peer, '* OK unfinished');
        $this->assert_read_aborts();
    }

    public function test_eof_in_a_partial_line_is_not_accepted_as_a_response() {
        fwrite($this->peer, '* OK unfinished');
        fclose($this->peer);
        $this->assert_read_aborts();
    }

    public function test_truncated_literal_does_not_retry_after_timeout() {
        fwrite($this->peer, "* 1 FETCH (BODY[] {50}\r\nshort\n");
        $this->assert_read_aborts();
    }

    public function test_discarding_an_oversized_literal_stops_on_timeout() {
        fwrite($this->peer, "a\nb\n");
        $start = microtime(true);
        $this->client->limited_literal();
        $this->assertLessThan(1, microtime(true) - $start);
        $this->assertFalse($this->client->has_stream());
    }

    public function test_eof_during_literal_does_not_loop_or_return_partial_data() {
        fwrite($this->peer, "* 1 FETCH (BODY[] {50}\r\nshort\n");
        fclose($this->peer);
        $this->assert_read_aborts();
    }

    public function test_timeout_in_a_second_literal_discards_the_incomplete_response() {
        fwrite($this->peer, "* 1 FETCH (BODY[1] {3}\r\nabc BODY[2] {50}\r\nshort\n");
        $this->assert_read_aborts();
    }

    public function test_complete_literal_and_tagged_response_are_preserved() {
        fwrite($this->peer, "* 1 FETCH (BODY[] {5}\r\nhello)\r\nA1 OK done\r\n");
        $response = $this->client->get_response(false, true);
        $this->assertContains('hello', $response[0]);
        $this->assertSame(['A1', 'OK', 'done'], $response[1]);
        $this->assertTrue($this->client->has_stream());
        /* Supply the logout answer so the ordinary cleanup path also succeeds. */
        fwrite($this->peer, "* BYE closing\r\nA2 OK logout\r\n");
    }

    public function test_absolute_deadline_caps_a_longer_socket_timeout() {
        $this->client->read_timeout = 5;
        $this->client->read_deadline = microtime(true) + 0.03;
        $this->assert_read_aborts();
    }

    public function test_expired_deadline_stops_a_reused_connection() {
        $this->client->read_deadline = microtime(true) + 0.03;
        fwrite($this->peer, "A1 OK first\r\n");
        $this->assertSame(['A1 OK first'], $this->client->get_response());
        usleep(40000);
        $this->assert_read_aborts();
    }

    public function test_api_budget_is_forwarded_without_saving_account_settings() {
        $config = new Hm_Mock_Config();
        Hm_IMAP_List::init($config, new Hm_Mock_Session());
        $server = ['server' => 'unused.example', 'port' => 993, 'tls' => true, 'type' => 'imap'];
        $id = Hm_IMAP_List::add($server, false);
        $deadline = microtime(true) + 2;
        Hm_IMAP_List::$request_deadline = $deadline;
        /* Null credentials stop before opening a socket, but still construct the mailbox. */
        $this->assertFalse(Hm_IMAP_List::service_connect($id, $server, null, null));
        $entry = Hm_IMAP_List::dump($id, true);
        $options = $entry['object']->get_config();
        $this->assertSame($deadline, $options['read_deadline']);
        $this->assertGreaterThan(0, $options['read_timeout']);
        $this->assertLessThanOrEqual(2, $options['read_timeout']);
        $this->assertSame($options['timeout'], $options['read_timeout']);
        $this->assertArrayNotHasKey('read_deadline', $entry);
        Hm_IMAP_List::init($config, new Hm_Mock_Session());
        $this->assertNull(Hm_IMAP_List::$request_deadline);
    }

    public function test_expired_api_budget_does_not_start_a_connection() {
        Hm_IMAP_List::$request_deadline = microtime(true) - 1;
        $this->assertFalse(Hm_IMAP_List::service_connect('expired',
            ['server' => 'unused.example', 'port' => 993, 'tls' => true], 'user', 'password'));
    }
}
