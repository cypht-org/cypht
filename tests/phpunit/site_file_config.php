<?php

use PHPUnit\Framework\TestCase;

/**
 * tests for Hm_Test_Site_File_Config
 */
class Hm_Test_Site_File_Config extends TestCase {

    public $config;
    public function setUp(): void {
        $mock_config = new Hm_Mock_Config();
        $this->config = new Hm_User_Config_File($mock_config);
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_get_modules() {
        $config = new Hm_Site_Config_File(merge_config_files(APP_PATH.'tests/phpunit/data'));
        $this->assertFalse($config->get_modules());
        $config->set('modules', 'asdf');
        $this->assertEquals(array('asdf'), $config->get_modules());
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_site_load() {
        $config = new Hm_Site_Config_File(merge_config_files(APP_PATH.'tests/phpunit/data'));
        $this->assertEquals(array('version' => VERSION, 'foo' => 'bar', 'default_setting_foo' => 'bar'), $config->dump());
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_get_user_defaults() {
        $config = new Hm_Site_Config_File(merge_config_files(APP_PATH.'tests/phpunit/data'));
        $this->assertEquals(array('version' => VERSION, 'foo' => 'bar', 'default_setting_foo' => 'bar'), $config->dump());
    }
    public function tearDown(): void {
        unset($this->config);
    }
}
