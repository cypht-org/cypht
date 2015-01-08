<?php

class Hm_Test_Router extends PHPUnit_Framework_TestCase {

    public function setUp() {
        require 'bootstrap.php';
    }
    /**
     * @preserveGlobalState disabled
     * @outputBuffering enabled
     * @runInSeparateProcess
     */
    public function test_process_request() {
        $router = new Hm_Router();
        ob_start();
        ob_start();
        $mock_config = new Hm_Mock_Config();
        $mock_config->data['disable_tls'] = true;
        $mock_config->data['auth_type'] = "DB";
        $mock_config->data['session_type'] = "DB";
        setup_db($mock_config);
        $mock_config->data['modules'] = 'imap';
        $router->process_request($mock_config, true);
        $this->assertTrue(ob_get_length() > 0);
        ob_end_clean();
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_process_request_again() {
        $router = new Hm_Router();
        ob_start();
        ob_start();
        $mock_config = new Hm_Mock_Config();
        $mock_config->data['disable_tls'] = false;
        $mock_config->data['auth_type'] = "None";
        $mock_config->data['session_type'] = "PHP";
        setup_db($mock_config);
        $mock_config->data['modules'] = 'pop3';
        $router->process_request($mock_config, false);
        $this->assertTrue(ob_get_length() > 0);
        ob_end_clean();
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_process_request_again_again() {
        $router = new Hm_Router();
        ob_start();
        ob_start();
        $mock_config = new Hm_Mock_Config();
        $mock_config->data['disable_tls'] = false;
        $mock_config->data['auth_type'] = false;
        $mock_config->data['session_type'] = "DB";
        setup_db($mock_config);
        $mock_config->data['modules'] = 'pop3';
        $router->process_request($mock_config, false);
        $this->assertTrue(ob_get_length() > 0);
        ob_end_clean();
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_get_production_modules() {
        $router = new Hm_Router();
        $mock_config = new Hm_Mock_Config();
        $mock_config->data['modules'] = 'imap,pop3';
        $this->assertEquals(array(array(), array(), array()), $router->get_production_modules($mock_config));
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_merge_filters() {
        $router = new Hm_Router();
        $res = $router->merge_filters(filters(), array('allowed_get' => array('new' => 'thing')));
        $this->assertEquals('thing', $res['allowed_get']['new']);
        $res = $router->merge_filters(filters(), array('allowed_pages' => array('new')));
        $this->assertTrue(in_array('new', $res['allowed_pages'], true));
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_for_tls() {
        /* TODO assertions */
        $router = new Hm_Router();
        $mock_config = new Hm_Mock_Config();
        $request = new Hm_Mock_Request('HTML5');
        $router->check_for_tls($mock_config, $request);
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_load_modules() {
        $router = new Hm_Router();
        $modules = array('home' => array('test_mod' => array('source', false)));
        $router->load_modules('Hm_Handler_Modules', $modules);
        $mods = Hm_Handler_Modules::get_for_page('home');
        $this->assertTrue(isset($mods['test_mod']));
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_get_page() {
        $router = new Hm_Router();
        $request = new Hm_Mock_Request('AJAX');
        $request->post['hm_ajax_hook'] = 'test';
        $router->get_page($request, array('allowed_pages' => array()));
        $this->assertEquals('home', $router->page);
        $router->get_page($request, array('allowed_pages' => array('test')));
        $this->assertEquals('test', $router->page);
        $request = new Hm_Mock_Request('HTML5');
        $request->get['page'] = 'test';
        $router->get_page($request, array('allowed_pages' => array('test')));
        $this->assertEquals('test', $router->page);
        $router->get_page($request, array('allowed_pages' => array()));
        $this->assertEquals('notfound', $router->page);
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_check_for_redirect_msgs() {
        $router = new Hm_Router();
        /* TODO assertions */
        $request = new Hm_Mock_Request('AJAX');
        $request->post['hm_ajax_hook'] = 'test';
        $request->cookie['hm_msgs'] = base64_encode(serialize(array('test message')));
        $session = new Hm_Mock_Session();
        $router->check_for_redirected_msgs($session, $request);
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_check_for_redirect() {
        $router = new Hm_Router();
        /* TODO assertions */
        $request = new Hm_Mock_Request('HTTP');
        $request->post['hm_ajax_hook'] = 'test';
        $session = new Hm_Mock_Session();
        $router->check_for_redirect($request, $session, array());
        $router->check_for_redirect($request, $session, array('no_redirect' => 1));
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_page_redirect() {
        /* TODO assertions */
        Hm_Router::page_redirect('test', 303);
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_get_active_mods() {
        $router = new Hm_Router();
        $this->assertEquals(array('test_mod'), $router->get_active_mods(array('test_page' => array('test_mod'))));
    }
}

?>
