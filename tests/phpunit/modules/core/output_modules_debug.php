<?php

use PHPUnit\Framework\TestCase;

class Hm_Test_Core_Output_Modules_Debug extends TestCase {
    public function setUp(): void {
        define('DEBUG_MODE', true);
        require __DIR__.'/../../bootstrap.php';
        require __DIR__.'/../../helpers.php';
        require APP_PATH.'modules/core/modules.php';
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_header_css_debug() {
        $test = new Output_Test('header_css', 'core');
        $test->handler_response = array('router_module_list' => array('core'));
        $res = $test->run();
        $this->assertEquals(array('<link href="modules/themes/assets/default/css/default.css?v=asdf" media="all" rel="stylesheet" type="text/css" /><link href="vendor/twbs/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet" type="text/css" /><link href="modules/core/site.css" media="all" rel="stylesheet" type="text/css" /><style type="text/css">@font-face {font-family:"Behdad";src:url("modules/core/assets/fonts/Behdad/Behdad-Regular.woff2") format("woff2"),url("modules/core/assets/fonts/Behdad/Behdad-Regular.woff") format("woff");</style>'), $res->output_response);
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_page_js_debug() {
        $test = new Output_Test('page_js', 'core');
        $test->handler_response = array('encrypt_ajax_requests' => true, 'router_module_list' => array('foo', 'core'));
        $res = $test->run();
        $this->assertEquals(array('<script type="text/javascript" src="vendor/twbs/bootstrap/dist/js/bootstrap.bundle.min.js"></script><script type="text/javascript" src="third_party/cash.min.js"></script><script type="text/javascript" src="third_party/resumable.min.js"></script><script type="text/javascript" src="third_party/ays-beforeunload-shim.js"></script><script type="text/javascript" src="third_party/jquery.are-you-sure.js"></script><script type="text/javascript" src="third_party/sortable.min.js"></script><script type="text/javascript" src="third_party/forge.min.js"></script><script type="text/javascript" src="modules/core/site.js"></script>'), $res->output_response);
        $test->handler_response = array('encrypt_ajax_requests' => true, 'router_module_list' => array('imap'));
        $res = $test->run();
        $this->assertEquals(array('<script type="text/javascript" src="vendor/twbs/bootstrap/dist/js/bootstrap.bundle.min.js"></script><script type="text/javascript" src="third_party/cash.min.js"></script><script type="text/javascript" src="third_party/resumable.min.js"></script><script type="text/javascript" src="third_party/ays-beforeunload-shim.js"></script><script type="text/javascript" src="third_party/jquery.are-you-sure.js"></script><script type="text/javascript" src="third_party/sortable.min.js"></script><script type="text/javascript" src="third_party/forge.min.js"></script><script type="text/javascript" src="modules/core/site.js"></script><script type="text/javascript" src="modules/imap/site.js"></script>'), $res->output_response);
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_main_menu_start_debug() {
        $test = new Output_Test('main_menu_start', 'core');
        $res = $test->run();
        $this->assertEquals(array('<div class="src_name main_menu d-flex justify-content-between pe-2" data-source=".main">Main <span title="Running in debug mode. See https://cypht.org/install.html Section 6 for more detail." class="debug_title">Debug</span><i class="bi bi-chevron-down"></i></div><div class="main"><ul class="folders">'), $res->output_response);
        $test->rtype = 'AJAX';
        $res = $test->run();
        $this->assertEquals(array('formatted_folder_list' => '<div class="src_name main_menu d-flex justify-content-between pe-2" data-source=".main">Main <span title="Running in debug mode. See https://cypht.org/install.html Section 6 for more detail." class="debug_title">Debug</span><i class="bi bi-chevron-down"></i></div><div class="main"><ul class="folders">'), $res->output_response);
    }
}
