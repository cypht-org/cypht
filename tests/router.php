<?php

class Hm_Test_Router extends PHPUnit_Framework_TestCase {

    private $router;

    public function setUp() {
        $this->router = new Hm_Router();
    }

    /* tests for Hm_Router */
    public function test_process_request() {
        ob_start();
        $mock_config = new Hm_Mock_Config();
        $mock_config->data['disable_tls'] = true;
        $mock_config->data['auth_type'] = "DB";
        $mock_config->data['session_type'] = "DB";
        setup_db($mock_config);
        $mock_config->data['modules'] = 'imap';
        $this->router->process_request($mock_config, true);
        $this->assertTrue(ob_get_length() > 0);
    }
    public function test_process_request_again() {
        ob_start();
        $mock_config = new Hm_Mock_Config();
        $mock_config->data['disable_tls'] = false;
        $mock_config->data['auth_type'] = "None";
        $mock_config->data['session_type'] = "PHP";
        setup_db($mock_config);
        $mock_config->data['modules'] = 'pop3';
        $this->router->process_request($mock_config, false);
        $this->assertTrue(ob_get_length() > 0);
    }
    public function test_process_request_again_again() {
        ob_start();
        $mock_config = new Hm_Mock_Config();
        $mock_config->data['disable_tls'] = false;
        $mock_config->data['auth_type'] = false;
        $mock_config->data['session_type'] = "DB";
        setup_db($mock_config);
        $mock_config->data['modules'] = 'pop3';
        $this->router->process_request($mock_config, false);
        $this->assertTrue(ob_get_length() > 0);
    }
    public function test_get_production_modules() {
        $mock_config = new Hm_Mock_Config();
        $mock_config->data['modules'] = 'imap,pop3';
        $this->assertEquals(array(array(), array(), array()), $this->router->get_production_modules($mock_config));
    }
    public function test_merge_filters() {
        $res = $this->router->merge_filters(filters(), array('allowed_get' => array('new' => 'thing')));
        $this->assertEquals('thing', $res['allowed_get']['new']);
        $res = $this->router->merge_filters(filters(), array('allowed_pages' => array('new')));
        $this->assertTrue(in_array('new', $res['allowed_pages'], true));
    }
    public function test_for_tls() {
        /* TODO assertions */
        $mock_config = new Hm_Mock_Config();
        $request = new Hm_Mock_Request('HTML5');
        $this->router->check_for_tls($mock_config, $request);
    }
    public function test_load_modules() {
        $modules = array('home' => array('test_mod' => array('source', false)));
        $this->router->load_modules('Hm_Handler_Modules', $modules);
        $mods = Hm_Handler_Modules::get_for_page('home');
        $this->assertTrue(isset($mods['test_mod']));
    }
    public function test_get_page() {
        $request = new Hm_Mock_Request('AJAX');
        $request->post['hm_ajax_hook'] = 'test';
        $this->router->get_page($request, array('allowed_pages' => array()));
        $this->assertEquals('home', $this->router->page);
        $this->router->get_page($request, array('allowed_pages' => array('test')));
        $this->assertEquals('test', $this->router->page);
        $request = new Hm_Mock_Request('HTML5');
        $request->get['page'] = 'test';
        $this->router->get_page($request, array('allowed_pages' => array('test')));
        $this->assertEquals('test', $this->router->page);
        $this->router->get_page($request, array('allowed_pages' => array()));
        $this->assertEquals('notfound', $this->router->page);
    }
    public function test_check_for_redirect_msgs() {
        /* TODO assertions */
        $request = new Hm_Mock_Request('AJAX');
        $request->post['hm_ajax_hook'] = 'test';
        $request->cookie['hm_msgs'] = base64_encode(serialize(array('test message')));
        $session = new Hm_Mock_Session();
        $this->router->check_for_redirected_msgs($session, $request);
    }
    public function test_check_for_redirect() {
        /* TODO assertions */
        $request = new Hm_Mock_Request('HTTP');
        $request->post['hm_ajax_hook'] = 'test';
        $session = new Hm_Mock_Session();
        $this->router->check_for_redirect($request, $session, array());
        $this->router->check_for_redirect($request, $session, array('no_redirect' => 1));
    }
    public function test_page_redirect() {
        /* TODO assertions */
        Hm_Router::page_redirect('test', 303);
    }
    public function test_get_active_mods() {
        $this->assertEquals(array('test_mod'), $this->router->get_active_mods(array('test_page' => array('test_mod'))));
    }
}

?>
