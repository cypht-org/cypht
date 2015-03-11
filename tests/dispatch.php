<?php

class Hm_Test_Dispatch extends PHPUnit_Framework_TestCase {

    public function setUp() {
        require 'bootstrap.php';
        define('CONFIG_FILE', APP_PATH.'hm3.rc');
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_process_request() {
        ob_start();
        ob_start();
        $router = new Hm_Dispatch(CONFIG_FILE);
        ob_end_clean();
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_check_for_tls_redirect() {
        /* TODO assertions */
        ob_start();
        ob_start();
        $router = new Hm_Dispatch();
        ob_end_clean();
        $router->site_config->set('disable_tls', false);
        $router->request->server['SERVER_NAME'] = 'test';
        $router->request->server['REQUEST_URI'] = 'asdf';
        $router->check_for_tls_redirect();
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_get_page() {
        /* TODO assertions */
        ob_start();
        ob_start();
        $router = new Hm_Dispatch();
        ob_end_clean();
        $request = new Hm_Mock_Request('HTTP');
        $router->get_page(array(), $request);
        $request = new Hm_Mock_Request('HTTP');
        $request->get['page'] = 'home';
        $router->get_page(array('allowed_pages' => array('home')), $request);
        $request = new Hm_Mock_Request('AJAX');
        $request->post['hm_ajax_hook'] = 'ajax_test';
        $router->get_page(array(), $request);
        $router->get_page(array('allowed_pages' => array('ajax_test')), $request);
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_check_for_redirect() {
        /* TODO assertions */
        ob_start();
        ob_start();
        $router = new Hm_Dispatch();
        ob_end_clean();
        $router->check_for_redirect();
        $router->module_exec->handler_response = array('no_redirect' => true);
        $router->check_for_redirect();
        $router->module_exec->handler_response = array();
        Hm_Msgs::add('just a test');
        $router->request->post = array('test' => 'foo');
        $router->request->type = 'HTTP';
        $router->request->server['REQUEST_URI'] = 'asdf';
        $router->check_for_redirect();
        $router->request->post = array();
        $router->request->cookie['hm_msgs'] = base64_encode(serialize(array('test message')));
        $router->check_for_redirect();
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_page_redirect() {
        /* TODO assertions */
        Hm_Dispatch::page_redirect('test', 303);
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
        /* TODO assertions */
        Hm_Dispatch::page_redirect('test', 200);
    }
}

?>
