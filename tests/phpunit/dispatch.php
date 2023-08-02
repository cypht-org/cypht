<?php

use PHPUnit\Framework\TestCase;

class Hm_Test_Dispatch extends TestCase {

    public function setUp(): void {
        require 'bootstrap.php';
        require 'helpers.php';
        define('CONFIG_FILE', APP_PATH.'hm3.rc');
        $this->config = new Hm_Mock_Config();
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_is_php_setup() {
        if ((float) substr(phpversion(), 0, 3) >= 5.6) {
            $this->assertTrue(Hm_Dispatch::is_php_setup());
        }
        Hm_Functions::$exists = false;
        $this->assertFalse(Hm_Dispatch::is_php_setup());
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_init_with_site_lib() {
        ob_start();
        ob_start();
        $this->config->mods[] = 'site';
        Hm_Functions::$exists = true;
        $router = new Hm_Dispatch($this->config);
        $this->assertEquals('home', $router->page);
        ob_end_clean();
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_process_request() {
        ob_start();
        ob_start();
        Hm_Functions::$exists = false;
        $router = new Hm_Dispatch($this->config);
        $this->assertEquals('home', $router->page);
        ob_end_clean();
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_get_page() {
        ob_start();
        ob_start();
        Hm_Functions::$exists = false;
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
        $router->get_page(array('allowed_pages' => array('ajax_test')), $request);
        $this->assertEquals('ajax_test', $router->page);
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_validate_request_uri() {
        ob_start();
        ob_start();
        Hm_Functions::$exists = false;
        $router = new Hm_Dispatch($this->config);
        ob_end_clean();
        $this->assertEquals('asdf', $router->validate_request_uri('asdf'));
        $this->assertEquals('/', $router->validate_request_uri('/'));
        $this->assertEquals('/', $router->validate_request_uri('../'));
        $this->assertEquals('/', $router->validate_request_uri(''));
        $this->assertEquals('/', $router->validate_request_uri('http://someothersite'));
        $this->assertEquals('/path/?foo=blah', $router->validate_request_uri('/path/?foo=blah'));
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_check_for_redirect() {
        ob_start();
        ob_start();
        Hm_Functions::$exists = false;
        $router = new Hm_Dispatch($this->config);
        ob_end_clean();
        $this->assertFalse($router->check_for_redirect($router->request, $router->module_exec, $router->session));
        $router->module_exec->handler_response = array('no_redirect' => true);
        $this->assertEquals('noredirect', $router->check_for_redirect($router->request, $router->module_exec, $router->session));

        $router->module_exec->handler_response = array();
        Hm_Msgs::add('just a test');
        $router->request->post = array('test' => 'foo');
        $router->request->type = 'HTTP';
        $router->request->server['REQUEST_URI'] = 'asdf';
        $this->assertEquals('redirect', $router->check_for_redirect($router->request, $router->module_exec, $router->session));

        $router->module_exec->handler_response = array('redirect_url' => 'asdf');
        $this->assertEquals('redirect', $router->check_for_redirect($router->request, $router->module_exec, $router->session));

        $router->request->post = array();
        $router->request->cookie['hm_msgs'] = base64_encode(json_encode(array('test message')));
        $this->assertEquals('redirect', $router->check_for_redirect($router->request, $router->module_exec, $router->session));
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_page_redirect() {
        $this->assertEquals(null, Hm_Dispatch::page_redirect('test', 303));
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_validate_ajax_request() {
        ob_start();
        ob_start();
        Hm_Functions::$exists = false;
        $router = new Hm_Dispatch($this->config);
        ob_end_clean();
        $request = new Hm_Mock_Request('HTTP');
        $this->assertFalse($router->validate_ajax_request($request, array()));
        $request->post['hm_ajax_hook'] = 'asdf';
        $this->assertFalse($router->validate_ajax_request($request, array()));
    }
}

class Hm_Test_Debug_Page_Redirect extends TestCase {

    public function setUp(): void {
        define('DEBUG_MODE', true);
        require 'bootstrap.php';
        define('CONFIG_FILE', APP_PATH.'hm3.rc');
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_debug_page_redirect() {
        $this->assertEquals(null, Hm_Dispatch::page_redirect('test', 200));
    }
}

?>
