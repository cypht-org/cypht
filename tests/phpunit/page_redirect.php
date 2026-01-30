<?php

use PHPUnit\Framework\TestCase;

class Hm_Test_Debug_Page_Redirect extends TestCase {

    public static function setUpBeforeClass(): void {
        putenv('CYPHT_TEST_DEBUG_MODE=true');
        $_ENV['CYPHT_TEST_DEBUG_MODE'] = 'true';
    }

    public static function tearDownAfterClass(): void {
        putenv('CYPHT_TEST_DEBUG_MODE');
        unset($_ENV['CYPHT_TEST_DEBUG_MODE']);
    }

    public function setUp(): void {
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
