<?php

use PHPUnit\Framework\TestCase;

/**
 * tests for the Hm_Modules trait
 */
class Hm_Test_Modules extends TestCase {

    public function setUp(): void {
        require 'bootstrap.php';
        Hm_Test_Module_List::add('test', 'date', false, false, 'after', true, 'core');
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_load() {
        Hm_Test_Module_List::load(array('test' => array('date' => array('core', false))));
        $this->assertEquals(array('test' => array('date' => array('core', false))), Hm_Test_Module_List::dump());
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_add() {
        Hm_Test_Module_List::add('test', 'date', false, false, false, true, 'core');
        $this->assertEquals(array('test' => array('date' => array('core', false))), Hm_Test_Module_List::dump());
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_dump() {
        $this->assertEquals(array('test' => array('date' => array('core', false))), Hm_Test_Module_List::dump());
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_add_to_all_pages() {
        Hm_Test_Module_List::add_to_all_pages('test', false, false, false, 'core');
        $mods = Hm_Test_Module_List::dump();
        $this->assertEquals(array('core', false), $mods['test']['test']);
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_set_source() {
        Hm_Test_Module_List::set_source('test');
        Hm_Test_Module_List::add('test', 'new', false);
        $mods = Hm_Test_Module_List::dump();
        $this->assertEquals('test', $mods['test']['new'][0]);
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_replace() {
        Hm_Test_Module_List::set_source('test');
        Hm_Test_Module_List::add('test', 'new', false);
        Hm_Test_Module_List::replace('new', 'more_new');
        $mods = Hm_Test_Module_List::dump();
        $this->assertEquals('test', $mods['test']['more_new'][0]);
        $this->assertFalse(isset($mods['test']['new']));
        Hm_Test_Module_List::replace('more_new', 'even_newer', 'test');
        $mods = Hm_Test_Module_List::dump();
        $this->assertEquals('test', $mods['test']['even_newer'][0]);
        $this->assertFalse(isset($mods['test']['more_new']));
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_del() {
        Hm_Test_Module_List::set_source('test');
        Hm_Test_Module_List::add('test', 'new', false);

        $mods = Hm_Test_Module_List::dump();
        $this->assertEquals('test', $mods['test']['new'][0]);
        Hm_Test_Module_List::del('test', 'new');
        $mods = Hm_Test_Module_List::dump();
        $this->assertFalse(isset($mods['test']['new']));
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_get_for_page() {
        $this->assertEquals(array('date' => array('core', false)), Hm_Test_Module_List::get_for_page('test'));
        Hm_Test_Module_List::set_source('core');
        Hm_Test_Module_List::add('test', 'new', false);
        $this->assertEquals(array('new' => array('core', false), 'date' => array('core', false)), Hm_Test_Module_List::get_for_page('test'));
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_queue_module_for_all_pages() {
        Hm_Test_Module_List::queue_module_for_all_pages('testqueue', false, 'date', 'after', 'core');
        Hm_Test_Module_List::process_all_page_queue();
        $mods = Hm_Test_Module_List::dump();
        $this->assertEquals(array('core', false), $mods['test']['testqueue']);
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_try_queued_modules() {
        Hm_Test_Module_List::add('test', 'queued', false, 'not_added_yet', 'after', true, 'core');
        $this->assertEquals(1, count(Hm_Test_Module_List::get_for_page('test')));
        Hm_Test_Module_List::add('test', 'not_added_yet', false, 'date', 'after', false, 'core');
        Hm_Test_Module_List::add('test', 'queued', false, 'never_added', 'after', true, 'core');
        Hm_Test_Module_List::add('test', 'notqueued', false, 'never_added', 'after', true, 'core');
        Hm_Test_Module_List::try_queued_modules();
        Hm_Test_Module_List::try_queued_modules();
        Hm_Test_Module_List::try_queued_modules();
        $this->assertEquals(3, count(Hm_Test_Module_List::get_for_page('test')));
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_module_replace() {
        Hm_Test_Module_List::replace('foo', false, 'test');
        Hm_Test_Module_List::add('test', 'foo', false);
        Hm_Test_Module_List::process_replace_queue();
        $this->assertTrue(array_key_exists('', Hm_Test_Module_List::get_for_page('test')));
    }
}

/**
 * tests for the module runner
 */
class Hm_Test_Module_Exec extends TestCase {

    public function setUp(): void {
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
        require '../../modules/core/setup.php';
        require '../../modules/core/modules.php';
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
        require '../../modules/core/setup.php';
        require '../../modules/core/modules.php';
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
        $this->assertEquals(array(array('test' => 'foo'), array()), $this->module_exec->run_handler_module(array('test' => 'foo'), array(), 'date', array(false, true), $session));
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

class Hm_Test_Module_Exec_Debug extends TestCase {

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

/**
 * tests for the functional interface to modules
 */
class Hm_Test_Module_Functions extends TestCase {

    public function setUp(): void {
        require 'bootstrap.php';
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_add_handler() {
        add_handler('test', 'test_mod', false);
        add_handler('test', 'test_mod2', false, 'core', 'test_mod', 'after', false);
        add_handler('test', 'test_mod3', false, 'core', 'test_mod', 'before', false);
        $mods = Hm_Handler_Modules::get_for_page('test');
        $keys = array_keys($mods);
        $this->assertEquals('test_mod3', $keys[0]);
        $this->assertEquals('test_mod', $keys[1]);
        $this->assertEquals('test_mod2', $keys[2]);
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_output_source() {
        output_source('test');
        add_output('test', 'source_test', false);
        $this->assertEquals(array('source_test' => array('test', false)), Hm_Output_Modules::get_for_page('test'));
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_handler_source() {
        handler_source('test');
        add_handler('newtest', 'source_test', false);
        $this->assertEquals(array('source_test' => array('test', false)), Hm_Handler_Modules::get_for_page('newtest'));
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_replace_module() {
        add_handler('test', 'new_handler', false);
        add_output('test', 'new_output', false);

        replace_module('handler', 'new_handler', 'replace_test');
        replace_module('output', 'new_output', 'replace_test');
        $this->assertEquals(array('replace_test' => array(false, false)), Hm_Handler_Modules::get_for_page('test'));
        $this->assertEquals(array('replace_test' => array(false, false)), Hm_Output_Modules::get_for_page('test'));
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_add_output() {
        add_output('test', 'add_output', false);
        $keys = array_keys(Hm_Output_Modules::get_for_page('test'));
        $this->assertEquals('add_output', $keys[0]);
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_add_module_to_all_pages() {
        add_handler('test', 'test_mod', false);
        add_output('test', 'test_mod', false);
        add_module_to_all_pages('output', 'all_pages', false, 'test', false, false);
        add_module_to_all_pages('handler', 'all_pages', false, 'test', false, false);
        Hm_Output_Modules::process_all_page_queue();
        Hm_Handler_Modules::process_all_page_queue();
        $mods = Hm_Output_Modules::dump();
        foreach ($mods as $name => $vals) {
            if (!preg_match("/^ajax_/", $name)) {
                $this->assertTrue(array_key_exists('all_pages', $vals));
            }
        }
        $mods = Hm_Handler_Modules::dump();
        foreach ($mods as $name => $vals) {
            if (!preg_match("/^ajax_/", $name)) {
                $this->assertTrue(array_key_exists('all_pages', $vals));
            }
        }
    }
}

?>
