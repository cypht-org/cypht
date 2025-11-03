<?php

use PHPUnit\Framework\TestCase;

/**
 * tests for Hm_User_Config_DB
 */
class Hm_Test_User_Config_DB extends TestCase {

    public $config;
    public function setUp(): void {
        $mock_config = new Hm_Mock_Config();
        $this->config = new Hm_User_Config_File($mock_config);
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_db_connect() {
        $site_config = new Hm_Mock_Config();
        setup_db($site_config);
        $user_config = new Hm_User_Config_DB($site_config);
        $this->assertTrue($user_config->connect());
    }

    /*public function test_db_load() {
        $site_config = new Hm_Mock_Config();
        setup_db($site_config);
        $user_config = new Hm_User_Config_DB($site_config);
        $this->assertEquals(array('version' => VERSION), $user_config->dump());
        $user_config->load('testuser', 'testkey');
        $this->assertEquals(array('version' => VERSION, 'foo' => 'bar'), $user_config->dump());
        $user_config->reload(array());
        $user_config->load('testuser', 'testpass');
        $this->assertEquals(array(), $user_config->dump());
        $user_config->reload(array());
        $user_config->load(uniqid(), 'blah');
        $this->assertEquals(array(), $user_config->dump());
        $site_config->set('auth_type', 'IMAP');
        $site_config->set('single_server_mode', true);
        $user_config = new Hm_User_Config_DB($site_config);
        $user_config->load('testuser', 'testkey');
        $this->assertTrue(array_key_exists('version', $user_config->dump()));
    }*/

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_db_reload() {
        $site_config = new Hm_Mock_Config();
        setup_db($site_config);
        $user_config = new Hm_User_Config_DB($site_config);
        $user_config->reload(array('foo' => 'bar'));
        $this->assertEquals(array('foo' => 'bar'), $user_config->dump());
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_db_save() {
        $site_config = new Hm_Mock_Config();
        $user_config = new Hm_User_Config_DB($site_config);
        $user_config->reload(array('foo' => 'bar'));
        $this->assertFalse($user_config->save('testuser', 'testkey'));

        $site_config = new Hm_Mock_Config();
        setup_db($site_config);
        $user_config = new Hm_User_Config_DB($site_config);
        $user_config->reload(array('foo' => 'bar'));
        $this->assertTrue($user_config->save('testuser', 'testkey'));
        $this->assertEquals(1, $user_config->save(uniqid(), 'testkey'));
        //$this->assertFalse($user_config->save(NULL, 'blah'));
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_db_set() {
        $site_config = new Hm_Mock_Config();
        setup_db($site_config);
        $user_config = new Hm_User_Config_DB($site_config);
        $user_config->set('foo',  'bar');
        $this->assertEquals('bar', $user_config->get('foo'));
        $site_config->set('auth_type', 'IMAP');
        $site_config->set('single_server_mode', true);
        $user_config = new Hm_User_Config_DB($site_config);
        $user_config->load('foo', 'foo');
        $user_config->set('foo',  'bar');
        $this->assertEquals('bar', $user_config->get('foo'));
    }
    public function tearDown(): void {
        unset($this->config);
    }
}
