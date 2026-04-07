<?php

use PHPUnit\Framework\TestCase;

class Hm_Test_Sieve_Functions_Client {
    public static $scripts = array();

    public function listScripts() {
        return array_keys(self::$scripts);
    }

    public function getScript($name) {
        return self::$scripts[$name] ?? '';
    }

    public function putScript($name, $script) {
        self::$scripts[$name] = $script;
        return true;
    }

    public function getErrorMessage() {
        return '';
    }
}

class Hm_Test_Sieve_Functions_Client_Factory {
    public function init($user_config = null, $imap_account = null, $is_nux_supported = false)
    {
        return new Hm_Test_Sieve_Functions_Client();
    }
}

class Hm_Test_Sieve_Site_Config {
    public function get($name) {
        if ($name === 'sieve_client_factory') {
            return 'Hm_Test_Sieve_Functions_Client_Factory';
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
        Hm_Test_Sieve_Functions_Client::$scripts = array();
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
    public function test_get_mailbox_filters_lists_only_cypht_scripts() {
        Hm_Test_Sieve_Functions_Client::$scripts = array(
            'main_script' => 'require ["include"];',
            'project_archive-20-cypht' => 'require ["fileinto"];',
            'from_message-10-cyphtfilter' => 'require ["fileinto"];',
            'external-script' => 'discard;',
        );

        $mailbox = array(
            'name' => 'Primary Account',
            'sieve_config_host' => 'tls://sieve.example.com:4190',
            'sieve_extensions' => array('fileinto'),
        );
        $site_config = new Hm_Test_Sieve_Site_Config();
        $user_config = new Hm_Mock_Config();

        $result = get_mailbox_filters($mailbox, $site_config, $user_config);

        $this->assertEquals(2, $result['count']);
        $this->assertStringContainsString('from message', $result['list']);
        $this->assertStringContainsString('project archive', $result['list']);
        $this->assertStringNotContainsString('external-script', $result['list']);
    }
}