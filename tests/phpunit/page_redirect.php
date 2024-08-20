<?php

use PHPUnit\Framework\TestCase;

class Hm_Test_Debug_Page_Redirect extends TestCase {

    public function setUp(): void {
        define('DEBUG_MODE', true);
        require 'bootstrap.php';
        define('CONFIG_FILE', merge_config_files(APP_PATH.'config'));
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_debug_page_redirect() {
        $this->assertEquals(null, Hm_Dispatch::page_redirect('test', 200));
    }
}
