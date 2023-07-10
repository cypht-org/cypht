<?php

use PHPUnit\Framework\TestCase;

/**
 * tests for Hm_User_Config_File
 */
class Hm_Test_User_Config_File extends TestCase {

    public function setUp(): void {
        require 'bootstrap.php'; 
        $mock_config = new Hm_Mock_Config();
        $this->config = new Hm_User_Config_File($mock_config);
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_save() {
        $this->config->reload(array('foo' => 'bar'));
        $this->config->save('testuser', 'testkey');
        $this->config->load('testuser', 'testkey');
        $this->assertEquals(array('foo' => 'bar'), $this->config->dump());
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_restore_servers() {
        $this->config->restore_servers(array(array(array('server' => 'foo'))));
        $this->assertEquals(2, count($this->config->dump()));
        $this->config->restore_servers(array(array('foo')));
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_filter_servers() {
        $this->config->set('imap_servers', array(array()));
        $this->assertEquals(array('imap_servers' => array(array())), $this->config->filter_servers());

        $this->config->set('imap_servers', array(array('default' => 1, 'server' => 'localhost')));
        $this->assertEquals(array('imap_servers' => array(array('default' => 1, 'server' => 'localhost'))), $this->config->filter_servers());

        $this->config->set('imap_servers', array(array('pass' => 'foo', 'server' => 'localhost')));
        $this->config->set('no_password_save_setting', true);
        $this->assertEquals(array('imap_servers' => array(array('pass' => 'foo'))), $this->config->filter_servers());
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_dump() {
        $this->config->load('testuser', 'testkey');
        $this->assertEquals(array('version' => VERSION, 'foo' => 'bar'), $this->config->dump());
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_version() {
        $this->assertEquals(VERSION, $this->config->version());
        $this->config->del('version');
        $this->assertEquals(.1, $this->config->version());
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_set() {
        $this->assertFalse($this->config->get('name', false));
        $this->config->set('name', 'value');
        $this->assertEquals('value', $this->config->get('name'));
        $mock_config = new Hm_Mock_Config();
        $mock_config->set('single_server_mode', true);
        $mock_config->set('auth_type', 'IMAP');
        $config = new Hm_User_Config_File($mock_config);
        $config->load('testuser', 'testkey');
        $config->set('foo', 'bar');
        $this->assertEquals('bar', $config->get('foo'));
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_del() {
        $this->assertFalse($this->config->get('name', false));
        $this->config->set('name', 'value');
        $this->config->del('name', 'value');
        $this->assertFalse($this->config->get('name'));
        $this->assertFalse($this->config->del('asdfasdf'));
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_get() {
        $this->config->reload(array('foo' => 'bar'));
        $this->config->save('testuser', 'testkey');
        $this->config->load('testuser', 'testkey');
        $this->assertEquals('default', $this->config->get('asdf', 'default'));
        $this->assertEquals('bar', $this->config->get('foo', 'default'));
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_load_failed() {
        $this->config->load('testuser', 'blah');
        $this->assertFalse($this->config->get('foo'));
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_load() {
        $this->config->load('testuser', 'testkey');
        $this->assertEquals('bar', $this->config->get('foo', false));
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_decode_failed() {
        $this->assertFalse($this->config->decode('foobar'));
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_get_path() {
        $this->assertEquals('./data/testuser.txt',  $this->config->get_path('testuser'));
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_set_tz() {
        date_default_timezone_set('Europe/London');
        $this->config->set('timezone_setting', 'UTC');
        $this->config->set_tz();
        $this->assertEquals('UTC', date_default_timezone_get());
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_reload() {
        date_default_timezone_set('Europe/London');
        $this->config->reload(array('timezone_setting' => 'UTC', 'bar' => 'foo'));
        $this->assertEquals('UTC', date_default_timezone_get());
        $this->assertEquals('foo', $this->config->get('bar'));
    }
    public function tearDown(): void {
        unset($this->config);
    }
}

/**
 * tests for Hm_Site_Config_File
 */
class Hm_Test_Site_Config_File extends TestCase {

    public function setUp(): void {
        require 'bootstrap.php'; 
        $mock_config = new Hm_Mock_Config();
        $this->config = new Hm_User_Config_File($mock_config);
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_get_modules() {
        $config = new Hm_Site_Config_File('./data/siteconfig.rc');
        $this->assertFalse($config->get_modules());
        $config->set('modules', 'asdf');
        $this->assertEquals(array('asdf'), $config->get_modules());
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_site_load() {
        $config = new Hm_Site_Config_File('./data/siteconfig.rc');
        $this->assertEquals(array('version' => VERSION, 'foo' => 'bar', 'default_setting_foo' => 'bar'), $config->dump());
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_get_user_defaults() {
        $config = new Hm_Site_Config_File('./data/siteconfig.rc');
        $this->assertEquals(array('version' => VERSION, 'foo' => 'bar', 'default_setting_foo' => 'bar'), $config->dump());
    }
    public function tearDown(): void {
        unset($this->config);
    }
}

/**
 * tests for Hm_User_Config_DB
 */
class Hm_Test_User_Config_DB extends TestCase {

    public function setUp(): void {
        require 'bootstrap.php'; 
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

class Hm_Test_User_Config_Functions extends TestCase {

    public function setUp(): void {
        require 'bootstrap.php'; 
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


?>
