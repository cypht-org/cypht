<?php

use PHPUnit\Framework\TestCase;

class Hm_Test_Output_Debug extends TestCase {

    public static function setUpBeforeClass(): void {
        putenv('CYPHT_TEST_DEBUG_MODE=true');
        $_ENV['CYPHT_TEST_DEBUG_MODE'] = 'true';
    }

    public static function tearDownAfterClass(): void {
        putenv('CYPHT_TEST_DEBUG_MODE');
        unset($_ENV['CYPHT_TEST_DEBUG_MODE']);
    }

    public function setUp(): void {
        Hm_Debug::flush();
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_send_response_injects_debug_panel_when_body_tag_present_and_debug_messages_exist() {
        Hm_Debug::add('Debug entry for panel', 'info');
        $http = new Hm_Output_HTTP();
        ob_start();
        ob_start();
        $http->send_response('<html><body>Page Content</body></html>');
        $output = ob_get_clean();
        $this->assertStringContainsString('cypht-debug-panel', $output);
        $this->assertStringContainsString('Debug Panel', $output);
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_send_response_skips_panel_when_no_debug_messages() {
        $http = new Hm_Output_HTTP();
        ob_start();
        ob_start();
        $http->send_response('<html><body>Page Content</body></html>');
        $output = ob_get_clean();
        $this->assertStringNotContainsString('cypht-debug-panel', $output);
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_elog_logs_message_when_debug_mode_enabled() {
        $result = elog('test log value');
        $debug_msgs = Hm_Debug::get();
        $found = false;
        foreach ($debug_msgs as $msg) {
            if (strpos($msg, 'ELOG called in') !== false) {
                $found = true;
                break;
            }
        }
        $this->assertTrue($found);
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_debug_panel_filters_messages_by_log_level() {
        Hm_Debug::add('Low priority debug', 'debug');
        Hm_Debug::add('High priority error', 'danger');
        putenv('LOG_LEVEL=ERROR');
        $http = new Hm_Output_HTTP();
        ob_start();
        ob_start();
        $http->send_response('<html><body>Content</body></html>');
        $output = ob_get_clean();
        putenv('LOG_LEVEL=WARNING');
        $this->assertStringContainsString('cypht-debug-panel', $output);
        $this->assertStringContainsString('High priority error', $output);
    }
}
