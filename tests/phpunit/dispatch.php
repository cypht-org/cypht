<?php

class Hm_Test_Dispatch extends PHPUnit_Framework_TestCase {

    public function setUp() {
        require 'bootstrap.php';
        define('CONFIG_FILE', APP_PATH.'hm3.rc');
        $this->config = new Hm_Mock_Config();
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_is_php_setup() {
        $this->assertTrue(Hm_Dispatch::is_php_setup());
        Hm_Functions::$exists = false;
        $this->assertFalse(Hm_Dispatch::is_php_setup());
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_process_request() {
        ob_start();
        ob_start();
        $router = new Hm_Dispatch($this->config);
        $this->assertEquals('home', $router->page);
        ob_end_clean();
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_check_for_tls_redirect() {
        ob_start();
        ob_start();
        $router = new Hm_Dispatch($this->config);
        ob_end_clean();
        $router->site_config->set('disable_tls', false);
        $router->request->server['SERVER_NAME'] = 'test';
        $router->request->server['REQUEST_URI'] = 'asdf';
        $this->assertTrue($router->check_for_tls_redirect());
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_get_page() {
        ob_start();
        ob_start();
        $router = new Hm_Dispatch($this->config);
        ob_end_clean();
        $request = new Hm_Mock_Request('HTTP');
        $router->get_page(array(), $request);
        $this->assertEquals('home', $router->page);
        $request = new Hm_Mock_Request('HTTP');
        $request->get['page'] = 'home';
        $router->get_page(array('allowed_pages' => array('home')), $request);
        $this->assertEquals('home', $router->page);
        $request = new Hm_Mock_Request('AJAX');
        $request->post['hm_ajax_hook'] = 'ajax_test';
        $router->get_page(array(), $request);
        $this->assertEquals('notfound', $router->page);
        $router->get_page(array('allowed_pages' => array('ajax_test')), $request);
        $this->assertEquals('ajax_test', $router->page);
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_check_for_redirect() {
        ob_start();
        ob_start();
        $router = new Hm_Dispatch($this->config);
        ob_end_clean();
        $this->assertFalse($router->check_for_redirect());
        $router->module_exec->handler_response = array('no_redirect' => true);
        $this->assertEquals('noredirect', $router->check_for_redirect());

        $router->module_exec->handler_response = array();
        Hm_Msgs::add('just a test');
        $router->request->post = array('test' => 'foo');
        $router->request->type = 'HTTP';
        $router->request->server['REQUEST_URI'] = 'asdf';
        $this->assertEquals('redirect', $router->check_for_redirect());

        $router->module_exec->handler_response = array('redirect_url' => 'asdf');
        $this->assertEquals('redirect', $router->check_for_redirect());

        $router->request->post = array();
        $router->request->cookie['hm_msgs'] = base64_encode(json_encode(array('test message')));
        $this->assertEquals('msg_forward', $router->check_for_redirect());
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_page_redirect() {
        $this->assertTrue(Hm_Dispatch::page_redirect('test', 303));
    }
}

class Hm_Test_Debug_Page_Redirect extends PHPUnit_Framework_TestCase {

    public function setUp() {
        define('DEBUG_MODE', true);
        require 'bootstrap.php';
        define('CONFIG_FILE', APP_PATH.'hm3.rc');
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_debug_page_redirect() {
        $this->assertTrue(Hm_Dispatch::page_redirect('test', 200));
    }
}

?>
