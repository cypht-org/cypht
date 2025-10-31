<?php

use PHPUnit\Framework\TestCase;

/**
 * tests for Hm_Test_User_Config_Functions
 */
class Hm_Test_User_Config_Functions extends TestCase {

    public $config;
    public function setUp(): void {
        $mock_config = new Hm_Mock_Config();
        $this->config = new Hm_User_Config_File($mock_config);
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_load_user_config_object() {
        /* TODO assertions */
        $mock_config = new Hm_Mock_Config();
        load_user_config_object($mock_config);
        $this->assertEquals('Hm_User_Config_File', get_class(load_user_config_object($mock_config)));
        $mock_config->set('user_config_type', 'DB');
        $this->assertEquals('Hm_User_Config_DB', get_class(load_user_config_object($mock_config)));
        $mock_config->set('user_config_type', 'custom:Hm_Mock_Config');
        $this->assertEquals('Hm_Mock_Config', get_class(load_user_config_object($mock_config)));
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_crypt_state() {
        $site_config = new Hm_Mock_Config();
        $this->assertTrue(crypt_state($site_config));
        $site_config->set('auth_type', 'IMAP');
        $site_config->set('single_server_mode', true);
        $this->assertFalse(crypt_state($site_config));
    }

    public function tearDown(): void {
        unset($this->config);
    }
}
