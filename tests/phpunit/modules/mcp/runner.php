<?php

use PHPUnit\Framework\TestCase;

/**
 * tests for POST /api/v1/scheduled/run with the real store
 */
class Hm_Test_MCP_Runner extends TestCase {

    private $site;
    private $services;
    private $store;
    private $router;
    private $runner;
    private $runner_id;
    private $personal;
    private $smtp;

    public function setUp(): void {
        require_once __DIR__.'/fakes.php';
        date_default_timezone_set('UTC');
        $this->site = new Hm_Mock_Config();
        setup_db($this->site);
        $this->site->set('mcp_public_url', 'https://mail.example.com');
        $this->site->set('mcp_scheduled_grace', 0);
        $this->site->mods = ['core', 'imap', 'smtp', 'mcp'];
        $this->services = new Hm_MCP_Services($this->site);
        $this->store = $this->services->store();
        if (!$this->store->available()) {
            $this->markTestSkipped('MCP tables are not available in the test database');
        }
        $dbh = Hm_DB::connect($this->site);
        foreach (['hm_mcp_settings', 'hm_mcp_connections', 'hm_mcp_tokens', 'hm_mcp_sessions', 'hm_mcp_activity', 'hm_mcp_rate_limits'] as $table) {
            $dbh->exec('delete from '.$table);
        }
        $this->store->save_settings('alice', ['enabled' => true, 'permissions' => Hm_MCP_Permissions::defaults()]);
        list($this->runner_id, $key) = $this->store->create_connection('alice', 'runner', 'Server', 'pw');
        $this->runner = $this->store->issue_token($this->runner_id, 'runner', $key, 0);
        list($id, $key) = $this->store->create_connection('alice', 'personal', 'Script', 'pw');
        $this->personal = $this->store->issue_token($id, 'personal', $key, 3600);
        $this->smtp = new Hm_MCP_Fake_Smtp();
        $smtp = $this->smtp;
        $services = $this->services;
        $mailbox = new Hm_MCP_Fake_Mailbox(['INBOX' => [], 'Sent' => [], 'Scheduled' => []], ['sent' => 'Sent']);
        $mailbox->capabilities = ['MOVE', 'UIDPLUS'];
        $mailbox->store_message('Scheduled', 'X-Schedule: '.date('D, d M Y H:i O', time() - 300)."\r\n".
            "From: work@example.com\r\nTo: bob@example.net\r\nSubject: Due\r\nMessage-ID: <due@example.com>\r\n\r\nbody\r\n", false, true);
        $this->services->context_factory = function ($principal) use ($services, $smtp, $mailbox) {
            $context = new Hm_MCP_Fake_Context($services->site_config, $services->config, $services->store(), $principal,
                new Hm_Mock_Config(), new Hm_MCP_Session(),
                ['acc1' => ['id' => 'acc1', 'name' => 'Work', 'user' => 'work@example.com', 'server' => 'imap.example.com', 'type' => 'imap']]);
            $context->mailbox_objects = ['acc1' => $mailbox];
            $context->smtp_objects = ['s1' => $smtp];
            $context->smtp_accounts = ['acc1' => 's1'];
            return $context;
        };
        $this->router = new Hm_MCP_Router($this->site, $this->services);
    }

    private function run_request($method = 'POST', $token = null) {
        $headers = ['Host' => 'mail.example.com'];
        if ($token) {
            $headers['Authorization'] = 'Bearer '.$token;
        }
        return $this->router->handle(new Hm_MCP_Http_Request($method, '/api/v1/scheduled/run', [], $headers, '', ['REMOTE_ADDR' => '127.0.0.1']));
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_runner_endpoint() {
        $this->assertSame(401, $this->run_request('POST')->status);
        $this->assertSame(401, $this->run_request('POST', $this->personal)->status);
        $this->assertSame(405, $this->run_request('GET', $this->runner)->status);
        /* runner tokens do not work on the rest of the API or on /mcp */
        $res = $this->router->handle(new Hm_MCP_Http_Request('GET', '/api/v1/accounts', [], ['Host' => 'mail.example.com',
            'Authorization' => 'Bearer '.$this->runner], '', ['REMOTE_ADDR' => '127.0.0.1']));
        $this->assertSame(401, $res->status);
        $res = $this->router->handle(new Hm_MCP_Http_Request('POST', '/mcp', [], ['Host' => 'mail.example.com',
            'Authorization' => 'Bearer '.$this->runner, 'Content-Type' => 'application/json'], '{}', ['REMOTE_ADDR' => '127.0.0.1']));
        $this->assertSame(401, $res->status);

        $res = $this->run_request('POST', $this->runner);
        $this->assertSame(200, $res->status, $res->body);
        $this->assertSame(['sent' => 1, 'failed' => 0, 'waiting' => 0, 'failures' => [], 'errors' => []], $res->decoded());
        $this->assertCount(1, $this->smtp->sent);
        $activity = $this->store->activity('alice');
        $this->assertSame(['runner', 'send_scheduled', 'ok'], [$activity[0]['channel'], $activity[0]['operation'], $activity[0]['outcome']]);
        $this->assertSame(['account_id' => 'acc1', 'recipients' => 1, 'domains' => 'example.net'], $activity[0]['summary']);
        $res = $this->run_request('POST', $this->runner);
        $this->assertSame(0, $res->decoded()['sent']);

        $this->store->save_settings('alice', ['enabled' => false]);
        $this->assertSame(403, $this->run_request('POST', $this->runner)->status);
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_claims_are_exclusive_and_expire() {
        $now = 1800000000;
        $this->store->clock = function () use (&$now) { return $now; };
        $this->assertTrue($this->store->claim('k', 100));
        $this->assertFalse($this->store->claim('k', 100));
        $now += 101;
        $this->assertTrue($this->store->claim('k', 100));
        $this->store->release('k', 100, 30);
        $now += 20;
        $this->assertFalse($this->store->claim('k', 100));
        $now += 11;
        $this->assertTrue($this->store->claim('k', 100));
        $this->assertTrue($this->store->claim('other', 100));
    }
}
