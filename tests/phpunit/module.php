<?php

/**
 * tests for the Hm_Module_Output trait
 */
class Hm_Test_Modules_Output extends PHPUnit_Framework_TestCase {

    public function setUp() {
        require 'bootstrap.php';
        $this->parent = build_parent_mock();
        $this->handler_mod = new Hm_Handler_Test($this->parent, false, 'home');
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
        $this->handler_mod = new Hm_Handler_Test($this->parent, false, 'home');
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_process_key() {
        /* TODO: fix assertions */
        $session = new Hm_Mock_Session();
        $request = new Hm_Mock_Request('AJAX');
        Hm_Request_Key::load($session, $request, false);

        $request->post = array();
        $this->handler_mod->request->post = array();
        $this->assertFalse($this->handler_mod->process_key());

        $request->post['hm_page_key'] = 'asdf';
        $this->handler_mod->request->post['hm_page_key'] = 'asdf';
        Hm_Request_Key::load($session, $request, false);
        $this->assertEquals('redirect', $this->handler_mod->process_key());

        $this->handler_mod->request->type = 'AJAX';
        $this->assertEquals('exit', $this->handler_mod->process_key());

        $this->handler_mod->request->post['hm_page_key'] = 'fakefingerprint';
        $this->assertFalse($this->handler_mod->process_key());
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_module_is_supported() {
        $this->assertFalse($this->handler_mod->module_is_supported('core'));
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_validate_origin() {
        $this->assertFalse($this->handler_mod->validate_origin());
        $this->handler_mod->session->loaded = false;
        $this->assertTrue($this->handler_mod->validate_origin());
        $this->handler_mod->session->loaded = true;
        $this->handler_mod->request->server['HTTP_ORIGIN'] = 'asdf';
        $this->handler_mod->request->server['HTTP_HOST'] = 'localhost';
        $this->assertFalse($this->handler_mod->validate_origin());
        $this->handler_mod->request->server['HTTP_ORIGIN'] = 'http://localhost';
        $this->assertTrue($this->handler_mod->validate_origin());
        $this->handler_mod->request->server['HTTP_ORIGIN'] = 'http://otherhost';
        $this->assertFalse($this->handler_mod->validate_origin());
        $this->handler_mod->config->set('cookie_domain', 'none');
        $this->assertFalse($this->handler_mod->validate_origin());
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_validate_method() {
        $this->assertTrue($this->handler_mod->validate_method());
        $this->handler_mod->request->method = 'PUT';
        $this->assertFalse($this->handler_mod->validate_method());
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
 * DEBUG_MODE tests for the Hm_Handler_Module class
 */
class Hm_Test_Handler_Module_Debug extends PHPUnit_Framework_TestCase {

    public function setUp() {
        define('DEBUG_MODE', true);
        require 'bootstrap.php';
        $this->parent = build_parent_mock();
        $this->handler_mod = new Hm_Handler_Test($this->parent, false, 'home');
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

?>
