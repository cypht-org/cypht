<?php

use PHPUnit\Framework\TestCase;

/**
 * DEBUG_MODE tests for the Hm_Handler_Module class
 */
class Hm_Test_Handler_Module_Debug extends TestCase {

    public $parent;
    public $handler_mod;
    public function setUp(): void {
        define('DEBUG_MODE', true);
        require 'bootstrap.php';
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
