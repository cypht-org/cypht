<?php

use PHPUnit\Framework\TestCase;

class Hm_Test_Logging extends TestCase {

    public function setUp(): void {
        require_once APP_PATH.'lib/logging.php';
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_get_instance_returns_singleton() {
        $instance1 = Hm_Logger::getInstance();
        $instance2 = Hm_Logger::getInstance();
        $this->assertSame($instance1, $instance2);
        $this->assertInstanceOf('Hm_Logger', $instance1);
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_get_logger_returns_monolog_logger() {
        $logger = Hm_Logger::getLogger();
        $this->assertInstanceOf(\Monolog\Logger::class, $logger);
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_error_static_method_does_not_throw() {
        $this->expectNotToPerformAssertions();
        Hm_Logger::error('Test error message', array('key' => 'value'));
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_warning_static_method_does_not_throw() {
        $this->expectNotToPerformAssertions();
        Hm_Logger::warning('Test warning');
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_info_static_method_does_not_throw() {
        $this->expectNotToPerformAssertions();
        Hm_Logger::info('Test info');
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_debug_static_method_does_not_throw() {
        $this->expectNotToPerformAssertions();
        Hm_Logger::debug('Test debug');
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_log_adds_type_to_context_when_missing() {
        $test_handler = new \Monolog\Handler\TestHandler(\Monolog\Level::Debug);
        Hm_Logger::getLogger()->pushHandler($test_handler);
        Hm_Logger::getInstance()->log('Test message', 'warning', array());
        $records = $test_handler->getRecords();
        $this->assertNotEmpty($records);
        $this->assertEquals('warning', $records[0]['context']['type']);
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_log_preserves_existing_type_in_context() {
        $test_handler = new \Monolog\Handler\TestHandler(\Monolog\Level::Debug);
        Hm_Logger::getLogger()->pushHandler($test_handler);
        Hm_Logger::getInstance()->log('Msg', 'danger', array('type' => 'custom_type'));
        $records = $test_handler->getRecords();
        $this->assertEquals('custom_type', $records[0]['context']['type']);
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_error_logs_at_error_level() {
        $test_handler = new \Monolog\Handler\TestHandler(\Monolog\Level::Debug);
        Hm_Logger::getLogger()->pushHandler($test_handler);
        Hm_Logger::error('An error');
        $this->assertTrue($test_handler->hasErrorRecords());
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_warning_logs_at_warning_level() {
        $test_handler = new \Monolog\Handler\TestHandler(\Monolog\Level::Debug);
        Hm_Logger::getLogger()->pushHandler($test_handler);
        Hm_Logger::warning('A warning');
        $this->assertTrue($test_handler->hasWarningRecords());
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_info_logs_at_info_level() {
        $test_handler = new \Monolog\Handler\TestHandler(\Monolog\Level::Debug);
        Hm_Logger::getLogger()->pushHandler($test_handler);
        Hm_Logger::info('An info');
        $this->assertTrue($test_handler->hasInfoRecords());
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_debug_logs_at_debug_level() {
        $test_handler = new \Monolog\Handler\TestHandler(\Monolog\Level::Debug);
        Hm_Logger::getLogger()->pushHandler($test_handler);
        Hm_Logger::debug('A debug');
        $this->assertTrue($test_handler->hasDebugRecords());
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_log_with_unknown_type_maps_to_error_level() {
        $test_handler = new \Monolog\Handler\TestHandler(\Monolog\Level::Debug);
        Hm_Logger::getLogger()->pushHandler($test_handler);
        Hm_Logger::getInstance()->log('Test unknown type', 'unknown_type', array());
        $this->assertTrue($test_handler->hasErrorRecords());
    }
}
