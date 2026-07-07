<?php

use PHPUnit\Framework\TestCase;

/**
 * tests for Hm_Output_HTTP
 */
class Hm_Test_Output extends TestCase {

    public $http;
    public function setUp(): void {
        $this->http = new Hm_Output_HTTP();
        Hm_Msgs::flush();
        Hm_Debug::flush();
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_send_response() {
        ob_start();
        ob_start();
        $this->http->send_response('test', array('http_headers' => array('test')));
        $output = ob_get_contents();
        ob_end_clean();
        $this->assertEquals('test', $output);
        ob_start();
        ob_start();
        $this->http->send_response('test', array());
        $output = ob_get_contents();
        ob_end_clean();
        $this->assertEquals('test', $output);
    }
    public function tearDown(): void {
        unset($this->http);
        Hm_Msgs::flush();
        Hm_Debug::flush();
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_hm_msgs_add_deduplicates_messages() {
        Hm_Msgs::add('duplicate message');
        Hm_Msgs::add('duplicate message');
        $this->assertCount(1, Hm_Msgs::get());
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_hm_msgs_add_stores_type_with_message() {
        Hm_Msgs::add('test warning', 'warning');
        $raw = Hm_Msgs::getRaw();
        $this->assertEquals('warning', $raw[0]['type']);
        $this->assertEquals('test warning', $raw[0]['text']);
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_hm_msgs_get_returns_text_only() {
        Hm_Msgs::add('msg one');
        Hm_Msgs::add('msg two');
        $texts = Hm_Msgs::get();
        $this->assertEquals(array('msg one', 'msg two'), $texts);
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_hm_msgs_flush_clears_all_messages() {
        Hm_Msgs::add('will be flushed');
        Hm_Msgs::flush();
        $this->assertEmpty(Hm_Msgs::get());
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_hm_list_str_with_array_returns_print_r_output() {
        $result = Hm_Msgs::str(array('a' => 1));
        $this->assertStringContainsString('Array', $result);
        $this->assertStringContainsString('[a]', $result);
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_hm_list_str_with_object_returns_print_r_output() {
        $obj = new stdClass();
        $obj->key = 'value';
        $result = Hm_Msgs::str($obj);
        $this->assertStringContainsString('stdClass', $result);
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_hm_list_str_with_return_type_true_includes_type_prefix() {
        $result = Hm_Msgs::str(42);
        $this->assertEquals('integer: 42', $result);
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_hm_list_str_with_return_type_false_returns_string_only() {
        $result = Hm_Msgs::str(42, false);
        $this->assertEquals('42', $result);
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_hm_msgs_show_returns_true_when_hm_logger_exists() {
        // Hm_Logger is loaded by the bootstrap environment
        if (!class_exists('Hm_Logger', false)) {
            $this->markTestSkipped('Hm_Logger not loaded');
        }
        Hm_Msgs::add('test message');
        $result = Hm_Msgs::show();
        $this->assertTrue($result);
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_hm_debug_add_stores_message() {
        Hm_Debug::add('debug entry', 'info');
        $raw = Hm_Debug::getRaw();
        $this->assertNotEmpty($raw);
        $this->assertEquals('debug entry', $raw[0]['text']);
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_hm_debug_load_page_stats_adds_system_info() {
        Hm_Debug::load_page_stats();
        $texts = Hm_Debug::get();
        $this->assertTrue(count($texts) >= 5);
        $found_php = false;
        foreach ($texts as $text) {
            if (strpos($text, 'PHP version') !== false) {
                $found_php = true;
                break;
            }
        }
        $this->assertTrue($found_php);
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_elog_returns_null_when_debug_mode_is_false() {
        $this->assertNull(elog('test value'));
    }
}
