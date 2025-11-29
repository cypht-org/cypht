<?php

use PHPUnit\Framework\TestCase;

/**
 * DEBUG_MODE tests for the Hm_Handler_Module class
 */
class Hm_Test_Handler_Module_Debug extends TestCase {

    public $parent;
    public $handler_mod;

    public static function setUpBeforeClass(): void {
        putenv('CYPHT_TEST_DEBUG_MODE=true');
        $_ENV['CYPHT_TEST_DEBUG_MODE'] = 'true';
    }

    public static function tearDownAfterClass(): void {
        putenv('CYPHT_TEST_DEBUG_MODE');
        unset($_ENV['CYPHT_TEST_DEBUG_MODE']);
    }

    public function setUp(): void {
        $this->parent = build_parent_mock();
        $this->handler_mod = new Hm_Handler_Test($this->parent, 'home');
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_process_key_debug() {
        $this->handler_mod->request->type = 'AJAX';
        $this->assertEquals('exit', $this->handler_mod->process_key());
    }
}
