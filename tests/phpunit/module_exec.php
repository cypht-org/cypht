<?php

use PHPUnit\Framework\TestCase;

/**
 * tests for the module runner
 */
class Hm_Test_Module_Exec extends TestCase {

    public $module_exec;
    public function setUp(): void {
        $config = new Hm_Mock_Config();
        $this->module_exec = new Hm_Module_Exec($config);
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_process_module_setup() {
        $this->module_exec->process_module_setup();
        $this->assertEquals(array(), $this->module_exec->filters);
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_default_langauge() {
        $this->module_exec->default_language();
        $this->assertEquals('es', $this->module_exec->handler_response['language']);
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_get_current_language() {
        $this->module_exec->handler_response['language'] = 'en';
        $lang = $this->module_exec->get_current_language();
        $this->assertTrue($lang != false);

    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_run_output_modules() {
        $request = new Hm_Mock_Request('HTTP');
        $session = new Hm_Mock_Session();
        $this->module_exec->run_output_modules($request, $session, 'home', $this->module_exec->handler_response);
        $request = new Hm_Mock_Request('AJAX');
        $this->module_exec->run_output_modules($request, $session, 'ajax_test', $this->module_exec->handler_response);
        Hm_Output_Modules::add('test', 'date', false, false, false, true, 'core');
        Hm_Output_Modules::add('test', 'blah', false, false, false, true, 'core');
        $request = new Hm_Mock_Request('HTTP');
        $this->module_exec->load_module_set_files(array('core'), array('core'));
        $this->module_exec->run_output_modules($request, $session, 'test', $this->module_exec->handler_response);
        $this->assertEquals(array('<div class="date"></div>'), $this->module_exec->output_response);
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_run_output_module() {
        require APP_PATH.'/modules/core/setup.php';
        $request = new Hm_Mock_Request('HTTP');
        $session = new Hm_Mock_Session();
        Hm_Output_Modules::add('test', 'date', false, false, false, true, 'core');
        Hm_Output_Modules::add('test', 'blah', false, false, false, true, 'core');
        $this->assertEquals(array('<div class="date"></div>', array(), 'HTML5'), $this->module_exec->run_output_module(array('test' => 'foo'), array(), 'date', array(false, true), $session, 'HTML5', array()));
        $this->assertEquals(array(array('test' => 'foo'), array(), 'JSON'), $this->module_exec->run_output_module(array('test' => 'foo'), array(), 'blah', array(false, true), $session, 'Hm_Format_JSON', array()));
        $this->assertEquals(array(array('test' => 'foo'), array(), 'JSON'), $this->module_exec->run_output_module(array('test' => 'foo'), array(), 'date', array(false, true), $session, 'Hm_Format_JSON', array()));
		$this->assertEquals(array(array('test' => 'foo'), array(), 'JSON'), $this->module_exec->run_output_module(array('test' => 'foo'), array(), 'date', array(false, true), false, 'Hm_Format_JSON', array()));

    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_run_handler_modules() {
        require APP_PATH.'/modules/core/setup.php';
        $request = new Hm_Mock_Request('HTTP');
        $session = new Hm_Mock_Session();
        Hm_Handler_Modules::add('test', 'date', false, false, false, true, 'core');
        Hm_Handler_Modules::add('test', 'blah', false, false, false, true, 'core');
        $this->module_exec->run_handler_modules($request, $session, 'test');
        $this->assertEquals('asdf', $this->module_exec->handler_response['router_url_path']);
        $this->assertEquals('test', $this->module_exec->handler_response['router_page_name']);
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_run_handler_module() {
        $request = new Hm_Mock_Request('HTTP');
        $session = new Hm_Mock_Session();
        $result = $this->module_exec->run_handler_module(array('test' => 'foo'), array(), 'date', array(false, true), $session);

        // Verify that the original data is preserved and date is added
        $this->assertEquals('foo', $result[0]['test']);
        $this->assertArrayHasKey('date', $result[0]);
        $this->assertMatchesRegularExpression('/^\d{1,2}:\d{2}:\d{2}$/', $result[0]['date']);
        $this->assertEquals(array('date'), $result[1]);
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_merge_response() {
        $request = new Hm_Mock_Request('HTTP');
        $session = new Hm_Mock_Session();
        $this->module_exec->merge_response($request, $session, 'home');
        $this->assertEquals('asdf', $this->module_exec->handler_response['router_url_path']);
        $this->assertEquals('home', $this->module_exec->handler_response['router_page_name']);
        $this->assertEquals('HTTP', $this->module_exec->handler_response['router_request_type']);
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_setup_production_modules() {
        $this->module_exec->setup_production_modules();
        $this->assertEquals(array(), $this->module_exec->filters);
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_merge_filters() {
        $res = $this->module_exec->merge_filters(filters(), array('allowed_get' => array('new' => 'thing')));
        $this->assertEquals('thing', $res['allowed_get']['new']);
        $res = $this->module_exec->merge_filters(filters(), array('allowed_pages' => array('new')));
        $this->assertTrue(in_array('new', $res['allowed_pages'], true));
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_get_active_mods() {
        $this->assertEquals(array('test_mod'), $this->module_exec->get_active_mods(array('test_page' => array('test_mod'))));
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_load_modules() {
        $modules = array('home' => array('test_mod' => array('source', false)));
        $this->module_exec->load_modules('Hm_Handler_Modules', $modules, 'home');
        $mods = Hm_Handler_Modules::get_for_page('home');
        $this->assertTrue(isset($mods['test_mod']));
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_load_module_sets() {
        $this->module_exec->site_config->mods = array('core');
        $this->module_exec->load_module_sets('home');
        $this->module_exec->handlers['home'] = array('date' => array('core', false));
        $this->module_exec->load_module_sets('home');
        $this->assertTrue(class_exists('Hm_Handler_date'));
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_load_module_set_files() {
        $this->module_exec->load_module_set_files(array('core'), array('core'));
        $this->assertTrue(class_exists('Hm_Handler_date'));
    }
}
