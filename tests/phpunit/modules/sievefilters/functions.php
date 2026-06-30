<?php

use PHPUnit\Framework\TestCase;

class Hm_Test_Sieve_Failing_Factory {
    public function init($user_config = null, $imap_account = null, $is_nux_supported = false) {
        throw new Exception('Connection refused');
    }
}

class Hm_Test_Failing_Sieve_Site_Config {
    public function get($name) {
        return 'Hm_Test_Sieve_Failing_Factory';
    }
    public function get_modules($include_setup = false) {
        return array();
    }
}

class Hm_Test_Mock_Sieve_Module {
    public $config;
    public $user_config;

    public function __construct($site_config, $user_config) {
        $this->config = $site_config;
        $this->user_config = $user_config;
    }

    public function module_is_supported($name) {
        return false;
    }
}

/**
 * Helper class to manage mock scripts
 */
class MockSieveClientStorage {
    private static $scripts = array();
    
    /**
     * Set scripts in the mock storage
     */
    public static function setScripts($scripts) {
        self::$scripts = $scripts;
    }
    
    /**
     * Get scripts from mock storage
     */
    public static function getScripts() {
        return self::$scripts;
    }
}

/**
 * Mock factory for testing that returns a mock client with predefined scripts
 */
class Hm_Test_Mock_Sieve_Client_Factory {
    private static $mockCreator;
    
    public static function setMockCreator($creator) {
        self::$mockCreator = $creator;
    }
    
    public function init($user_config = null, $imap_account = null, $is_nux_supported = false) {
        if (!self::$mockCreator) {
            throw new Exception('Mock creator not set');
        }
        
        return call_user_func(self::$mockCreator);
    }
}

/**
 * Mock site config for testing that returns the mock factory
 */
class Hm_Test_Mock_Sieve_Site_Config {
    public function get($name) {
        if ($name === 'sieve_client_factory') {
            return 'Hm_Test_Mock_Sieve_Client_Factory';
        }
        return null;
    }

    public function get_modules($include_setup = false) {
        return array();
    }
}

class Hm_Test_Sievefilters_Functions extends TestCase {

    public function setUp(): void {
        require_once APP_PATH.'modules/sievefilters/functions.php';
        MockSieveClientStorage::setScripts(array());
        Hm_Msgs::flush();
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_parse_sieve_config_host_reads_url_parts() {
        $result = parse_sieve_config_host(array(
            'sieve_config_host' => 'tls://sieve.example.com:4190',
        ));

        $this->assertEquals(array('sieve.example.com', 4190, true), $result);
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_parse_sieve_config_host_uses_tls_flag_without_scheme() {
        $result = parse_sieve_config_host(array(
            'sieve_config_host' => 'sieve.example.com',
            'tls' => false,
        ));

        $this->assertEquals(array('sieve.example.com', '4190', false), $result);
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_generate_main_script_orders_cypht_scripts_by_priority() {
        $result = generate_main_script(array(
            'manual_script-30-cypht',
            'main_script',
            'from_message-20-cyphtfilter',
            'not-managed-script',
            'archive_now-5-cypht',
            'blocked_senders',
        ));

        $this->assertStringStartsWith("require [\"include\"];", $result);
        $this->assertStringContainsString("include :personal \"blocked_senders\";\ninclude :personal \"archive_now-5-cypht\";\ninclude :personal \"from_message-20-cyphtfilter\";\ninclude :personal \"manual_script-30-cypht\";", $result);
        $this->assertStringNotContainsString('not-managed-script', $result);
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_format_main_script_merges_requirements_and_strips_comments() {
        $script = implode("\n", array(
            '# CYPHT CONFIG HEADER - DON\'T REMOVE',
            'require ["fileinto"];',
            'keep;',
            'require ["reject","fileinto"];',
            '# encoded metadata',
            'discard;',
        ));

        $result = format_main_script($script);

        $this->assertStringStartsWith('require ["fileinto","reject"];', $result);
        $this->assertStringContainsString("keep;\ndiscard;", $result);
        $this->assertStringNotContainsString('# CYPHT CONFIG HEADER', $result);
        $this->assertStringNotContainsString('# encoded metadata', $result);
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_prepare_sieve_script_decodes_metadata_list() {
        $script = implode("\n", array(
            '# CYPHT CONFIG HEADER - DON\'T REMOVE',
            '# '.base64_encode(json_encode(array('sender@example.com', '*@example.org'))),
            '',
            'discard;',
        ));

        $result = prepare_sieve_script($script);

        $this->assertEquals(array('sender@example.com', '@example.org'), $result);
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_generate_script_and_filter_names_normalize_case_and_spaces() {
        $this->assertEquals('manual_script-12-cypht', generate_script_name('Manual Script', 12));
        $this->assertEquals('important_sender-4-cyphtfilter', generate_filter_name('Important Sender', 4));
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_get_domain_returns_root_domain_from_sender() {
        $this->assertEquals('example.co.uk', get_domain('person@mail.sub.example.co.uk'));
        $this->assertFalse(get_domain('invalid-address'));
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_default_reject_message_returns_configured_message() {
        $user_config = new Hm_Mock_Config();
        $user_config->set('sieve_block_default_reject_message', array(
            0 => 'Mailbox does not accept this sender',
        ));

        $this->assertEquals('Mailbox does not accept this sender', default_reject_message($user_config, 0));
        $this->assertEquals('', default_reject_message($user_config, 1));
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_block_filter_reject_with_message_returns_metadata_and_script() {
        $user_config = new Hm_Mock_Config();
        $filter = \PhpSieveManager\Filters\FilterFactory::create('blocked_senders');

        $result = block_filter($filter, $user_config, 'reject_with_message', 0, 'sender@example.com', 'Rejected by test');
        $script = $filter->toScript();

        $this->assertEquals('reject_with_message', $result['action']);
        $this->assertEquals('Rejected by test', $result['reject_message']);
        $this->assertStringContainsString('reject', $script);
        $this->assertStringContainsString('sender@example.com', $script);
        $this->assertStringContainsString('stop;', $script);
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_block_filter_uses_default_move_behaviour_and_regex_for_domain_scope() {
        $user_config = new Hm_Mock_Config();
        $user_config->set('sieve_block_default_behaviour', array(
            0 => 'Move',
        ));
        $filter = \PhpSieveManager\Filters\FilterFactory::create('blocked_senders');

        $result = block_filter($filter, $user_config, 'default', 0, '*@example.com');
        $script = $filter->toScript();

        $this->assertEquals('default', $result['action']);
        $this->assertStringContainsString('require ["regex","fileinto"]', $script);
        $this->assertStringContainsString('fileinto "Blocked";', $script);
        $this->assertStringContainsString('From', $script);
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_block_filter_dropdown_contains_scope_actions_and_submit_button() {
        $mod = new Hm_Output_Test(array(), array());

        $result = block_filter_dropdown($mod, 0, true, 'block_sender', 'Block');

        $this->assertStringContainsString('blockSenderScope', $result);
        $this->assertStringContainsString('This Sender', $result);
        $this->assertStringContainsString('Whole domain', $result);
        $this->assertStringContainsString('reject_with_message', $result);
        $this->assertStringContainsString('Move To Blocked Folder', $result);
        $this->assertStringContainsString('id="block_sender"', $result);
        $this->assertStringContainsString('>Block<', $result);
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_save_main_script_falls_back_to_formatted_inline_script_when_include_fails() {
        $scripts = array(
            'broken-10-cypht' => implode("\n", array(
                'failed to include',
                '# CYPHT CONFIG HEADER - DON\'T REMOVE',
                '# '.base64_encode(json_encode(array('sender@example.com'))),
                'require ["fileinto"];',
                'keep;',
            )),
            'manual-20-cypht' => implode("\n", array(
                'require ["reject"];',
                'discard;',
            )),
        );
        
        MockSieveClientStorage::setScripts($scripts);
        
        // Create the mock client directly with simulated putScript failure
        $client = $this->createMock(PhpSieveManager\ManageSieve\Client::class);
        
        $putAttempts = 0;
        $client->method('connect')->willReturn(true);
        
        // Mock putScript to fail on first call with 'main_script', succeed on retry
        $client->method('putScript')
            ->will($this->returnCallback(function($name, $script) use (&$putAttempts) {
                if ($name === 'main_script') {
                    $putAttempts++;
                    if ($putAttempts === 1) {
                        return false;  // Simulate failure on first attempt
                    }
                }
                $scripts = MockSieveClientStorage::getScripts();
                $scripts[$name] = $script;
                MockSieveClientStorage::setScripts($scripts);
                return true;
            }));
        
        $client->method('getScript')
            ->will($this->returnCallback(function($name) {
                $scripts = MockSieveClientStorage::getScripts();
                return $scripts[$name] ?? '';
            }));
        
        $client->method('listScripts')
            ->will($this->returnCallback(function() {
                return array_keys(MockSieveClientStorage::getScripts());
            }));
        
        $client->method('removeScripts')
            ->will($this->returnCallback(function($name) {
                $scripts = MockSieveClientStorage::getScripts();
                unset($scripts[$name]);
                MockSieveClientStorage::setScripts($scripts);
                return true;
            }));
        
        $client->method('getErrorMessage')
            ->willReturn('failed to include');

        save_main_script($client, 'require ["include"];', array('main_script', 'broken-10-cypht', 'manual-20-cypht'));

        $mockScripts = MockSieveClientStorage::getScripts();
        $this->assertStringContainsString('require ["fileinto","reject"]', $mockScripts['main_script']);
        $this->assertStringContainsString('keep;', $mockScripts['main_script']);
        $this->assertStringContainsString('discard;', $mockScripts['main_script']);
        $this->assertStringNotContainsString('# CYPHT CONFIG HEADER', $mockScripts['main_script']);
    }

    private function makeMockSieveClient(array $extraMethods = []) {
        $client = $this->createMock(PhpSieveManager\ManageSieve\Client::class);
        $client->method('connect')->willReturn(true);
        $client->method('listScripts')->willReturnCallback(function () {
            return array_keys(MockSieveClientStorage::getScripts());
        });
        $client->method('getScript')->willReturnCallback(function ($name) {
            return MockSieveClientStorage::getScripts()[$name] ?? '';
        });
        $client->method('close')->willReturn(true);
        return $client;
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_split_script_lines_handles_crlf_line_endings() {
        $script = "first line\r\nsecond line\r\nthird line";
        $result = split_script_lines($script);
        $this->assertCount(3, $result);
        $this->assertEquals('first line', $result[0]);
        $this->assertEquals('second line', $result[1]);
        $this->assertEquals('third line', $result[2]);
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_get_sieve_client_factory_uses_class_from_site_config() {
        $factory = get_sieve_client_factory(new Hm_Test_Mock_Sieve_Site_Config());
        $this->assertInstanceOf('Hm_Test_Mock_Sieve_Client_Factory', $factory);
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_initialize_sieve_client_factory_returns_null_and_adds_message_on_exception() {
        require_once APP_PATH.'modules/sievefilters/hm-sieve.php';
        $result = initialize_sieve_client_factory(
            new Hm_Test_Failing_Sieve_Site_Config(),
            new Hm_Mock_Config(),
            array('sieve_config_host' => 'sieve.example.com')
        );
        $this->assertNull($result);
        $messages = Hm_Msgs::get();
        $this->assertNotEmpty($messages);
        $this->assertStringContainsString('Connection refused', $messages[0]);
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_get_blocked_senders_array_returns_empty_when_no_blocked_senders_script() {
        MockSieveClientStorage::setScripts(array('other_script' => 'discard;'));
        Hm_Test_Mock_Sieve_Client_Factory::setMockCreator(function () {
            return $this->makeMockSieveClient();
        });
        $result = get_blocked_senders_array(
            array('name' => 'Test', 'sieve_config_host' => 'tls://sieve.example.com:4190'),
            new Hm_Test_Mock_Sieve_Site_Config(),
            new Hm_Mock_Config()
        );
        $this->assertEquals(array(), $result);
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_get_blocked_senders_array_returns_senders_and_prepends_wildcard_for_domain_entries() {
        $senders = array('spam@example.com', '@baddomain.com');
        MockSieveClientStorage::setScripts(array(
            'blocked_senders' => implode("\n", array(
                "# CYPHT CONFIG HEADER - DON'T REMOVE",
                '# ' . base64_encode(json_encode($senders)),
                '',
                'discard; stop;',
            )),
        ));
        Hm_Test_Mock_Sieve_Client_Factory::setMockCreator(function () {
            return $this->makeMockSieveClient();
        });
        $result = get_blocked_senders_array(
            array('name' => 'Test', 'sieve_config_host' => 'tls://sieve.example.com:4190'),
            new Hm_Test_Mock_Sieve_Site_Config(),
            new Hm_Mock_Config()
        );
        $this->assertCount(2, $result);
        $this->assertContains('spam@example.com', $result);
        $this->assertContains('*@baddomain.com', $result);
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_get_blocked_senders_array_returns_empty_and_adds_message_on_exception() {
        Hm_Test_Mock_Sieve_Client_Factory::setMockCreator(function () {
            throw new Exception('Client connection failed');
        });
        $result = get_blocked_senders_array(
            array('name' => 'Test', 'sieve_config_host' => 'tls://sieve.example.com:4190'),
            new Hm_Test_Mock_Sieve_Site_Config(),
            new Hm_Mock_Config()
        );
        $this->assertEquals(array(), $result);
        $this->assertStringContainsString('Client connection failed', Hm_Msgs::get()[0]);
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_get_blocked_senders_returns_empty_string_when_script_not_present() {
        MockSieveClientStorage::setScripts(array());
        Hm_Test_Mock_Sieve_Client_Factory::setMockCreator(function () {
            return $this->makeMockSieveClient();
        });
        $mod = new Hm_Output_Test(array(), array());
        $result = get_blocked_senders(
            array('name' => 'Test', 'sieve_config_host' => 'tls://sieve.example.com:4190', 'sieve_extensions' => array()),
            'serverA', 'x-circle', 'globe',
            new Hm_Test_Mock_Sieve_Site_Config(), new Hm_Mock_Config(), $mod
        );
        $this->assertEquals('', $result);
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_get_blocked_senders_returns_html_rows_with_sender_and_unblock_button() {
        $senders = array('spam@example.com');
        $actions = array('spam@example.com' => array('action' => 'discard', 'reject_message' => ''));
        MockSieveClientStorage::setScripts(array(
            'blocked_senders' => implode("\n", array(
                "# CYPHT CONFIG HEADER - DON'T REMOVE",
                '# ' . base64_encode(json_encode($senders)),
                '# ' . base64_encode(json_encode($actions)),
                '',
                'discard; stop;',
            )),
        ));
        Hm_Test_Mock_Sieve_Client_Factory::setMockCreator(function () {
            return $this->makeMockSieveClient();
        });
        $mod = new Hm_Output_Test(array(), array());
        $result = get_blocked_senders(
            array('name' => 'Test', 'sieve_config_host' => 'tls://sieve.example.com:4190', 'sieve_extensions' => array()),
            'serverA', 'x-circle', 'globe',
            new Hm_Test_Mock_Sieve_Site_Config(), new Hm_Mock_Config(), $mod
        );
        $this->assertStringContainsString('spam@example.com', $result);
        $this->assertStringContainsString('unblock_button', $result);
        $this->assertStringContainsString('block_domain_button', $result);
        $this->assertStringContainsString('Discard', $result);
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_get_sieve_linked_mailbox_returns_folder_map_for_move_action_scripts() {
        $actions = array(array('action' => 'move', 'value' => 'Archive'));
        MockSieveClientStorage::setScripts(array(
            'archive_filter-10-cyphtfilter' => implode("\n", array(
                "# CYPHT CONFIG HEADER - DON'T REMOVE",
                '# ' . base64_encode(json_encode(array(array('condition' => 'from', 'value' => 'test@example.com')))),
                '# ' . base64_encode(json_encode($actions)),
                '# ' . base64_encode('message_list'),
                '',
                'if anyof (header :contains "From" ["test@example.com"]) {',
                '    # CYPHT GENERATED CONDITION',
                '    fileinto "Archive";',
                '}',
            )),
        ));
        Hm_Test_Mock_Sieve_Client_Factory::setMockCreator(function () {
            return $this->makeMockSieveClient();
        });
        $user_config = new Hm_Mock_Config();
        $module = new Hm_Test_Mock_Sieve_Module(new Hm_Test_Mock_Sieve_Site_Config(), $user_config);
        $result = get_sieve_linked_mailbox(
            array('name' => 'Primary Account', 'sieve_config_host' => 'tls://sieve.example.com:4190'),
            $module
        );
        $this->assertArrayHasKey('archive_filter-10-cyphtfilter', $result);
        $this->assertEquals('Archive', $result['archive_filter-10-cyphtfilter']);
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_is_mailbox_linked_with_filters_returns_false_when_no_sieve_config_on_account() {
        $user_config = new Hm_Mock_Config();
        $user_config->set('imap_servers', array(
            'serverA' => array('name' => 'Test', 'server' => 'imap.example.com'),
        ));
        $module = new Hm_Test_Mock_Sieve_Module(new Hm_Test_Mock_Sieve_Site_Config(), $user_config);
        $this->assertFalse(is_mailbox_linked_with_filters('Archive', 'serverA', $module));
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_is_mailbox_linked_with_filters_returns_true_when_folder_is_linked() {
        $actions = array(array('action' => 'move', 'value' => 'Archive'));
        MockSieveClientStorage::setScripts(array(
            'arch-10-cyphtfilter' => implode("\n", array(
                "# CYPHT CONFIG HEADER - DON'T REMOVE",
                '# ' . base64_encode(json_encode(array(array('condition' => 'from', 'value' => 'x@x.com')))),
                '# ' . base64_encode(json_encode($actions)),
                '# ' . base64_encode('message_list'),
                '',
                '# CYPHT GENERATED CONDITION',
                'fileinto "Archive";',
            )),
        ));
        Hm_Test_Mock_Sieve_Client_Factory::setMockCreator(function () {
            return $this->makeMockSieveClient();
        });
        $user_config = new Hm_Mock_Config();
        $user_config->set('imap_servers', array(
            'serverA' => array('name' => 'Test', 'sieve_config_host' => 'tls://sieve.example.com:4190'),
        ));
        $module = new Hm_Test_Mock_Sieve_Module(new Hm_Test_Mock_Sieve_Site_Config(), $user_config);
        $this->assertTrue(is_mailbox_linked_with_filters('Archive', 'serverA', $module));
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_block_filter_discard_action_generates_discard_script() {
        $filter = \PhpSieveManager\Filters\FilterFactory::create('blocked_senders');
        $result = block_filter($filter, new Hm_Mock_Config(), 'discard', 0, 'discard@example.com');
        $this->assertEquals('discard', $result['action']);
        $this->assertStringContainsString('discard@example.com', $filter->toScript());
        $this->assertStringContainsString('discard;', $filter->toScript());
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_block_filter_reject_default_uses_configured_message() {
        $user_config = new Hm_Mock_Config();
        $user_config->set('sieve_block_default_reject_message', array(0 => 'No thanks'));
        $filter = \PhpSieveManager\Filters\FilterFactory::create('blocked_senders');
        $result = block_filter($filter, $user_config, 'reject_default', 0, 'reject@example.com');
        $this->assertEquals('reject_default', $result['action']);
        $this->assertEquals('No thanks', $result['reject_message']);
        $this->assertStringContainsString('reject', $filter->toScript());
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_block_filter_blocked_action_moves_to_blocked_folder() {
        $filter = \PhpSieveManager\Filters\FilterFactory::create('blocked_senders');
        $result = block_filter($filter, new Hm_Mock_Config(), 'blocked', 0, 'junk@example.com');
        $this->assertEquals('blocked', $result['action']);
        $this->assertStringContainsString('fileinto "Blocked"', $filter->toScript());
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_format_main_script_handles_script_with_no_require_statements() {
        $result = format_main_script("keep;\ndiscard;");
        $this->assertStringContainsString('keep;', $result);
        $this->assertStringContainsString('discard;', $result);
        $this->assertStringNotContainsString('require', $result);
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_get_mailbox_filters_lists_only_cypht_scripts() {
        $scripts = array(
            'main_script' => 'require ["include"];',
            'project_archive-20-cypht' => 'require ["fileinto"];',
            'from_message-10-cyphtfilter' => 'require ["fileinto"];',
            'external-script' => 'discard;',
        );
        
        MockSieveClientStorage::setScripts($scripts);
        
        // Create a mock creator that produces mock clients with the test data
        $mockCreator = function() {
            $client = $this->createMock(PhpSieveManager\ManageSieve\Client::class);
            
            $client->method('connect')
                ->willReturn(true);
            
            $client->method('listScripts')
                ->willReturnCallback(function() {
                    return array_keys(MockSieveClientStorage::getScripts());
                });
            
            $client->method('getScript')
                ->willReturnCallback(function($name) {
                    $scripts = MockSieveClientStorage::getScripts();
                    return $scripts[$name] ?? '';
                });
            
            return $client;
        };
        
        Hm_Test_Mock_Sieve_Client_Factory::setMockCreator($mockCreator);

        $mailbox = array(
            'name' => 'Primary Account',
            'sieve_config_host' => 'tls://sieve.example.com:4190',
            'sieve_extensions' => array('fileinto'),
        );
        $site_config = new Hm_Test_Mock_Sieve_Site_Config();
        $user_config = new Hm_Mock_Config();

        $result = get_mailbox_filters($mailbox, $site_config, $user_config);

        $this->assertEquals(2, $result['count']);
        $this->assertStringContainsString('from message', $result['list']);
        $this->assertStringContainsString('project archive', $result['list']);
        $this->assertStringNotContainsString('external-script', $result['list']);
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_client_factory_returns_cached_client_for_same_account() {
        require_once APP_PATH.'modules/sievefilters/hm-sieve.php';

        $imap_account = array(
            'sieve_config_host' => 'tls://sieve.example.com:4190',
            'user' => 'alice@example.com',
        );
        $cache_key = md5($imap_account['sieve_config_host'].'|'.$imap_account['user']);
        $cached_client = $this->createMock(PhpSieveManager\ManageSieve\Client::class);

        $instances = (new ReflectionClass(Hm_Sieve_Client_Factory::class))->getProperty('instances');
        $instances->setAccessible(true);
        $instances->setValue(null, array($cache_key => $cached_client));

        $client = (new Hm_Sieve_Client_Factory())->init(null, $imap_account, false);

        $this->assertSame($cached_client, $client);
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_client_factory_cache_is_keyed_per_account() {
        require_once APP_PATH.'modules/sievefilters/hm-sieve.php';

        $alice_account = array('sieve_config_host' => 'tls://sieve.example.com:4190', 'user' => 'alice@example.com');
        $bob_account = array('sieve_config_host' => 'tls://sieve.example.com:4190', 'user' => 'bob@example.com');
        $alice_key = md5($alice_account['sieve_config_host'].'|'.$alice_account['user']);
        $bob_key = md5($bob_account['sieve_config_host'].'|'.$bob_account['user']);
        $alice_client = $this->createMock(PhpSieveManager\ManageSieve\Client::class);
        $bob_client = $this->createMock(PhpSieveManager\ManageSieve\Client::class);

        $instances = (new ReflectionClass(Hm_Sieve_Client_Factory::class))->getProperty('instances');
        $instances->setAccessible(true);
        $instances->setValue(null, array($alice_key => $alice_client, $bob_key => $bob_client));

        $factory = new Hm_Sieve_Client_Factory();

        $this->assertSame($alice_client, $factory->init(null, $alice_account, false));
        $this->assertSame($bob_client, $factory->init(null, $bob_account, false));
    }
}
