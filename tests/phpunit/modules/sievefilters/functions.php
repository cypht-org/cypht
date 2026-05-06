<?php

use PHPUnit\Framework\TestCase;

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
}
