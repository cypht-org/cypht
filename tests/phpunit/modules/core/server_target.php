<?php

use PHPUnit\Framework\TestCase;

class Hm_Test_Server_Target_Config {
    private $data;

    public function __construct($data) {
        $this->data = is_array($data) ? $data : array();
    }

    public function get($id, $default = false) {
        if (array_key_exists($id, $this->data)) {
            return $this->data[$id];
        }
        return $default;
    }
}

class Hm_Test_Core_Server_Target extends TestCase {
    /**
     * @dataProvider user_added_server_host_cases
     */
    public function test_user_added_server_host_policy($case) {
        if (array_key_exists('connect_calls', $case['expected'])) {
            $this->assertSavedMailboxIsNotDialed($case);
            return;
        }
        $config = new Hm_Test_Server_Target_Config($case['input']['flags']);
        if (array_key_exists('url', $case['input'])) {
            $this->assertSame(
                $case['expected']['allowed'],
                hm_server_url_is_allowed($case['input']['url'], $config)
            );
            return;
        }
        $host = $case['input']['host'];
        if (array_key_exists('canonical', $case['expected'])) {
            $this->assertSame($case['expected']['canonical'], hm_normalize_server_host($host));
        }
        if (array_key_exists('class', $case['expected'])) {
            $this->assertSame($case['expected']['class'], hm_classify_ip($host));
        }
        $this->assertSame($case['expected']['allowed'], hm_server_target_is_allowed($host, $config));
    }

    public static function user_added_server_host_cases() {
        return PolicyCases::forPolicy('user-added-server-hosts');
    }

    private function assertSavedMailboxIsNotDialed($case) {
        require_once APP_PATH.'modules/imap/hm-imap.php';
        require_once APP_PATH.'modules/smtp/hm-smtp.php';
        Hm_IMAP::$connect_calls = 0;
        $server = array(
            'name' => 'Saved',
            'server' => $case['input']['host'],
            'port' => $case['input']['port'],
            'tls' => false,
            'user' => 'user',
            'pass' => 'secret',
        );
        Hm_IMAP_List::init(new Hm_Mock_Config(), new Hm_Mock_Session());
        $imap_id = Hm_IMAP_List::add($server, false);
        Hm_IMAP_List::connect($imap_id, false);
        $this->assertSame($case['expected']['connect_calls'], Hm_IMAP::$connect_calls);
        Hm_IMAP_List::del($imap_id);

        Hm_SMTP_List::init(new Hm_Mock_Config(), new Hm_Mock_Session());
        $smtp = $server;
        $smtp['type'] = 'smtp';
        $smtp_id = Hm_SMTP_List::add($smtp, false);
        $this->assertFalse(Hm_SMTP_List::connect($smtp_id, false));
        Hm_SMTP_List::del($smtp_id);
    }

    public function test_three_part_private_address_expands() {
        $this->assertSame('192.168.0.1', hm_normalize_server_host('192.168.1'));
        $this->assertSame('internal', hm_classify_ip('192.168.1'));
    }

    public function test_feed_url_rejects_metadata_via_shared_helper() {
        require_once APP_PATH.'modules/feeds/hm-feed.php';
        $this->assertFalse(feed_url_is_allowed('http://169.254.169.254/latest/meta-data/'));
        $this->assertFalse(hm_server_url_is_allowed('http://169.254.169.254/latest/meta-data/', null, false));
        $this->assertTrue(hm_server_url_is_allowed('https://8.8.8.8/feed', null, false));
    }

    public function test_imap_add_does_not_reveal_the_os_error() {
        require_once APP_PATH.'modules/imap/hm-imap.php';
        require_once APP_PATH.'modules/imap/handler_modules.php';
        Hm_Msgs::flush();
        Hm_Debug::flush();

        $config = new Hm_Mock_Config();
        $config->set('allow_internal_servers', true);
        $test = new Hm_Server_Target_Handler_Test('process_add_imap_server', 'imap');
        $test->post = array(
            'submit_imap_server' => '1',
            'new_imap_name' => 'Loopback',
            'new_imap_address' => '127.0.0.1',
            'new_imap_port' => 1,
        );
        $test->run($config);

        $messages = implode(' ', Hm_Msgs::get());
        $this->assertStringContainsString('Could not add server', $messages);
        $this->assertStringNotContainsString('Connection refused', $messages);
        $this->assertStringNotContainsString('127.0.0.1', $messages);
        $this->assertStringContainsString('Connection refused', implode(' ', Hm_Debug::get()));
    }

    public function test_imap_add_does_not_dial_a_link_local_address() {
        require_once APP_PATH.'modules/imap/hm-imap.php';
        require_once APP_PATH.'modules/imap/handler_modules.php';
        Hm_Msgs::flush();
        Hm_Debug::flush();

        $test = new Hm_Server_Target_Handler_Test('process_add_imap_server', 'imap');
        $test->post = array(
            'submit_imap_server' => '1',
            'new_imap_name' => 'Metadata',
            'new_imap_address' => '169.254.169.254',
            'new_imap_port' => 80,
        );
        $test->run();

        $messages = implode(' ', Hm_Msgs::get());
        $debug = implode(' ', Hm_Debug::get());
        $this->assertSame('Could not add server', $messages);
        $this->assertStringNotContainsString('Could not add IMAP server', $debug);
        $this->assertStringNotContainsString('169.254.169.254', $debug);
        foreach (Hm_IMAP_List::dump() as $server) {
            $this->assertNotSame('169.254.169.254', $server['server'] ?? '');
        }
    }
}

class Hm_Server_Target_Handler_Test {
    public $post = array();
    public $mod;
    public $set;

    public function __construct($name, $set) {
        $this->mod = $name;
        $this->set = $set;
    }

    public function run($site_config = null) {
        $config = $site_config ?: new Hm_Mock_Config();
        $config->mods = array('imap');
        $module_exec = new Hm_Module_Exec($config);
        $module_exec->user_config = new Hm_Mock_Config();
        $request = new Hm_Mock_Request('HTTP');
        $request->post = $this->post;
        $session = new Hm_Mock_Session();
        Hm_IMAP_List::init($module_exec->user_config, $session);
        Hm_Handler_Modules::add('test', $this->mod, false, false, false, true, $this->set);
        $module_exec->handler_response = array();
        $module_exec->run_handler_modules($request, $session, 'test');
        return $module_exec;
    }
}
