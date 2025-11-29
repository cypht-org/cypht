<?php

use PHPUnit\Framework\TestCase;

/**
 * tests for the Hm_Handler_Module class
 */
class Hm_Test_Handler_Module extends TestCase {

    public $parent;
    public $handler_mod;
    public function setUp(): void {
        $this->parent = build_parent_mock();
        $this->handler_mod = new Hm_Handler_Test($this->parent, 'home');
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
        $this->assertFalse($this->handler_mod->validate_origin($this->handler_mod->session, $this->handler_mod->request, $this->handler_mod->config));
        $this->handler_mod->session->loaded = true;
        $this->handler_mod->request->server['HTTP_ORIGIN'] = 'http://localhost';
        $this->handler_mod->request->server['HTTP_HOST'] = 'localhost';
        $this->assertTrue($this->handler_mod->validate_origin($this->handler_mod->session, $this->handler_mod->request, $this->handler_mod->config));
        $this->handler_mod->session->loaded = false;
        $this->assertTrue($this->handler_mod->validate_origin($this->handler_mod->session, $this->handler_mod->request, $this->handler_mod->config));
        $this->handler_mod->session->loaded = true;
        $this->handler_mod->request->server['HTTP_ORIGIN'] = 'asdf';
        $this->handler_mod->request->server['HTTP_HOST'] = 'localhost';
        $this->assertFalse($this->handler_mod->validate_origin($this->handler_mod->session, $this->handler_mod->request, $this->handler_mod->config));
        $this->handler_mod->request->server['HTTP_ORIGIN'] = 'http://localhost:123';
        $this->assertFalse($this->handler_mod->validate_origin($this->handler_mod->session, $this->handler_mod->request, $this->handler_mod->config));
        $this->handler_mod->request->server['HTTP_ORIGIN'] = 'http://otherhost';
        $this->assertFalse($this->handler_mod->validate_origin($this->handler_mod->session, $this->handler_mod->request, $this->handler_mod->config));
        $this->handler_mod->config->set('cookie_domain', 'none');
        $this->assertFalse($this->handler_mod->validate_origin($this->handler_mod->session, $this->handler_mod->request, $this->handler_mod->config));
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_validate_method() {
        $this->assertTrue($this->handler_mod->validate_method($this->handler_mod->session, $this->handler_mod->request));
        $this->handler_mod->request->method = 'PUT';
        $this->assertFalse($this->handler_mod->validate_method($this->handler_mod->session, $this->handler_mod->request));
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_process_form() {
        list($success, $form) = $this->handler_mod->process_form(array('fld1', 'fld2', 'fld3'));
        $this->assertTrue($success);
        $this->assertEquals(array('fld1' => '0', 'fld2' => '1', 'fld3' => 0), $form);
        list($success, $form) = $this->handler_mod->process_form(array('blah'));
        $this->assertFalse($success);
        $this->assertEquals(array(), $form);
        list($success, $form) = $this->handler_mod->process_form(array('fld4'));
        $this->assertFalse($success);
        $this->assertEquals(array(), $form);
    }
}
