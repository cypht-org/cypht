<?php


/**
 * tests for the Hm_Module_Output trait
 */
class Hm_Test_Modules_Output extends PHPUnit_Framework_TestCase {

    public function setUp() {
        require 'bootstrap.php';
        $this->parent = build_parent_mock();
        $this->handler_mod = new Hm_Handler_Test($this->parent, false);
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_out() {
        $this->assertTrue($this->handler_mod->out('foo', 'bar'));
        $this->assertFalse($this->handler_mod->out('foo', 'foo'));
        $this->assertTrue($this->handler_mod->append('name', 'value'));
        $this->assertFalse($this->handler_mod->out('name', 'value2'));
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_get() {
        $this->handler_mod->out('foo', 'bar');
        $this->assertEquals('bar', $this->handler_mod->get('foo'));
        $this->assertEquals('bar', $this->handler_mod->get('foo', ''));
        $this->assertEquals(0, $this->handler_mod->get('foo', 3));
        $this->assertEquals(array('bar'), $this->handler_mod->get('foo', array()));
        $this->assertEquals('default', $this->handler_mod->get('bar', 'default'));
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_append() {
        $this->assertTrue($this->handler_mod->append('test', 'value'));
        $this->assertTrue($this->handler_mod->append('test', 'value'));
        $this->assertEquals(array('value', 'value'), $this->handler_mod->get('test'));
        $this->assertTrue($this->handler_mod->out('no_append', 'blah', true));
        $this->assertFalse($this->handler_mod->append('no_append', 'blah'));
        $this->assertTrue($this->handler_mod->out('scaler', 'blah', false));
        $this->assertFalse($this->handler_mod->append('scaler', 'blah'));
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_concat() {
        $this->assertTrue($this->handler_mod->out('concat_test', array()));
        $this->assertFalse($this->handler_mod->concat('concat_test', 'test'));
        $this->assertTrue($this->handler_mod->concat('concat', 'start'));
        $this->assertTrue($this->handler_mod->concat('concat', 'start'));
        $this->assertEquals('startstart', $this->handler_mod->get('concat'));
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_module_output() {
        $this->handler_mod->out('foo', 'bar');
        $this->assertEquals(array('foo' => 'bar'), $this->handler_mod->module_output());
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_output_protected() {
        $this->handler_mod->out('foo', 'bar', true);
        $this->handler_mod->out('bar', 'foo', false);
        $this->assertEquals(array('foo'), $this->handler_mod->output_protected());
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_exists() {
        $this->handler_mod->out('foo', 'bar');
        $this->assertTrue($this->handler_mod->exists('foo'));
        $this->assertFalse($this->handler_mod->exists('blah'));
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_in() {
        $this->handler_mod->out('foo', 'bar');
        $this->assertTrue($this->handler_mod->in('foo', array('bar', 'baz')));
        $this->assertFalse($this->handler_mod->in('foo', array('baz', 'blah')));
    }
}

/**
 * tests for the Hm_Handler_Module class
 */
class Hm_Test_Handler_Module extends PHPUnit_Framework_TestCase {

    public function setUp() {
        require 'bootstrap.php';
        $this->parent = build_parent_mock();
        $this->handler_mod = new Hm_Handler_Test($this->parent, false);
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_process_nonce() {
        /* TODO: fix assertions */
        $this->assertNull($this->handler_mod->process_nonce());
        $this->handler_mod->session->set('nonce_list', array('asdf'));
        $this->handler_mod->session->loaded = false;
        $this->handler_mod->session->set('nonce_list', array('sdfg'));
        $this->assertNull($this->handler_mod->process_nonce());
        $this->handler_mod->request->type = 'AJAX';
        $this->assertNull($this->handler_mod->process_nonce());
        $this->handler_mod->session->set('nonce_list', array('asdf'));
        $this->parent->request->post = array();
        $handler_mod = new Hm_Handler_Test($this->parent, false);
        $this->assertNull($handler_mod->process_nonce());
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_process_form() {
        list($success, $form) = $this->handler_mod->process_form(array('fld1', 'fld2'));
        $this->assertTrue($success);
        $this->assertEquals(array('fld1' => '0', 'fld2' => '1'), $form);
        list($success, $form) = $this->handler_mod->process_form(array('blah'));
        $this->assertFalse($success);
        $this->assertEquals(array(), $form);
    }
}

/**
 * tests for the Hm_Output_Module class
 */
class Hm_Test_Output_Module extends PHPUnit_Framework_TestCase {

    public function setUp() {
        require 'bootstrap.php';
        $this->output_mod = new Hm_Output_Test(array('foo' => 'bar', 'bar' => 'foo'), array('bar'));
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_output_content() {
        $this->output_mod->output_content('HTML5', array('Main' => false, 'Test' => 'Translated', 'interface_lang' => 'en', 'interface_direction' => 'ltr'), array());
        $this->assertEquals('Main', $this->output_mod->trans('Main'));
        $this->assertEquals('Translated', $this->output_mod->trans('Test'));
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_trans() {
        $this->assertEquals('inbox', $this->output_mod->trans('inbox'));
        $this->assertEquals('Main', $this->output_mod->trans('Main'));
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_html_safe() {
        $this->assertEquals('&lt;script&gt;', $this->output_mod->html_safe('<script>'));
        $this->assertEquals('nohtml', $this->output_mod->html_safe('nohtml'));
    }
    public function tearDown() {
        unset($this->output_mod);
    }
}

/**
 * tests for the Hm_Request_Handler class
 */
class Hm_Test_Request_Handler extends PHPUnit_Framework_TestCase {

    public function setUp() {
        require 'bootstrap.php';
        $this->parent = build_parent_mock();
        $this->handler_mod = new Hm_Handler_Test($this->parent, false);
        $this->output_mod = new Hm_Output_Test(array('foo' => 'bar', 'bar' => 'foo'), array('bar'));
        $this->request_handler = new Hm_Request_Handler();
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_process_request() {
        $res = $this->request_handler->process_request('test', $this->parent->request, $this->parent->session, $this->parent->config);
        $this->assertEquals('es', $res['language']);
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_load_user_config_object() {
        /* TODO assertions */
        $this->parent->config->set('user_config_type', 'DB');
        $res = $this->request_handler->process_request('test', $this->parent->request, $this->parent->session, $this->parent->config);
        $this->request_handler->load_user_config_object();

        $this->parent->config->set('user_config_type', 'file');
        $res = $this->request_handler->process_request('test', $this->parent->request, $this->parent->session, $this->parent->config);
        $this->request_handler->load_user_config_object();
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_default_language() {
        $res = $this->request_handler->process_request('test', $this->parent->request, $this->parent->session, $this->parent->config);
        $this->request_handler->default_language();
        $this->assertEquals('es', $this->request_handler->response['language']);
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_run_modules() {
        $res = $this->request_handler->process_request('test', $this->parent->request, $this->parent->session, $this->parent->config);
        $this->request_handler->run_modules();
        $this->assertEquals(array('language' => 'es'), $this->request_handler->response);
    }
}

/**
 * tests for the Hm_Modules trait
 */
class Hm_Test_Modules extends PHPUnit_Framework_TestCase {

    public function setUp() {
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
        Hm_Test_Module_List::add('test', 'queued', false, 'never_added', 'after', false, 'core');
        Hm_Test_Module_List::try_queued_modules();
        $this->assertEquals(3, count(Hm_Test_Module_List::get_for_page('test')));
    }
}

/**
 * tests for the functional interface to modules
 */
class Hm_Test_Module_Functions extends PHPUnit_Framework_TestCase {

    public function setUp() {
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
