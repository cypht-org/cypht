<?php

use PHPUnit\Framework\TestCase;

/**
 * Tests for spam_reporting module helpers
 * @package tests
 * @subpackage spam_reporting
 */
class Hm_Test_Spam_Reporting_Functions extends TestCase {

    public function setUp(): void {
        require __DIR__ . '/../../helpers.php';
        require_once APP_PATH . 'modules/spam_reporting/modules.php';
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_parse_message_empty_returns_false() {
        $this->assertFalse(spam_reporting_parse_message(''));
        $this->assertFalse(spam_reporting_parse_message('   '));
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_generate_instance_id_is_hex_sixteen_chars() {
        $id = spam_reporting_generate_instance_id();
        $this->assertSame(16, strlen($id));
        $this->assertMatchesRegularExpression('/^[0-9a-f]{16}$/', $id);
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_normalize_string_list() {
        $this->assertSame(array(), spam_reporting_normalize_string_list(null));
        $this->assertSame(array(), spam_reporting_normalize_string_list(array()));
        $this->assertSame(array('a', 'b'), spam_reporting_normalize_string_list(array(' a ', 'b', '', '  b  ')));
        $this->assertSame(array('x'), spam_reporting_normalize_string_list(array('x', 'x')));
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_merge_keep_settings_replaces_placeholder() {
        $submitted = array(array(
            'id' => 'cfg1',
            'adapter_id' => 'abuseipdb',
            'label' => 'AbuseIPDB',
            'settings' => array('api_key' => '__KEEP__'),
        ));
        $current = array(array(
            'id' => 'cfg1',
            'adapter_id' => 'abuseipdb',
            'label' => 'AbuseIPDB',
            'settings' => array('api_key' => 'stored-secret'),
        ));
        $merged = spam_reporting_merge_keep_settings($submitted, $current);
        $this->assertSame('stored-secret', $merged[0]['settings']['api_key']);
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_whitelist_instance_settings_uses_adapter_schema() {
        $adapter = new Hm_Spam_Report_AbuseIPDB_Target();
        $raw = array('api_key' => 'k', 'extra' => 'drop');
        $out = spam_reporting_whitelist_instance_settings($raw, $adapter);
        $this->assertSame(array('api_key' => 'k'), $out);
        $this->assertArrayNotHasKey('extra', $out);
    }
}
