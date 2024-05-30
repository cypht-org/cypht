<?php

use PHPUnit\Framework\TestCase;

class Hm_Test_Module_Exec_Debug extends TestCase {

    public $module_exec;
    public function setUp(): void {
        define('DEBUG_MODE', true);
        require 'bootstrap.php';
        $config = new Hm_Mock_Config();
        $this->module_exec = new Hm_Module_Exec($config);
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_process_module_setup() {
        $this->module_exec->process_module_setup();
        $this->assertEquals(array('allowed_output' => array(), 'allowed_post' => array(), 'allowed_get' => array(), 'allowed_cookie' => array(), 'allowed_server' => array(), 'allowed_pages' => array ()), $this->module_exec->filters);
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_setup_debug_modules() {
        $this->module_exec->site_config->mods = array('core');
        $this->module_exec->setup_debug_modules();
        $this->assertTrue(!empty($this->module_exec->filters['allowed_pages']));
    }
}
