<?php

use PHPUnit\Framework\TestCase;

/**
 * tests for Hm_Test_User_File_Config
 */
class Hm_Test_User_File_Config extends TestCase {

    public $config;
    public function setUp(): void {
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
        $this->assertEquals(array(), $this->config->filter_servers());

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
        $this->assertEquals(APP_PATH.'tests/phpunit/data/testuser.txt',  $this->config->get_path('testuser'));
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

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_set_tz_falls_back_to_default_setting_timezone() {
        $this->config->set('default_setting_timezone', 'America/New_York');
        $this->config->set_tz();
        $this->assertEquals('America/New_York', date_default_timezone_get());
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_shuffle_preserves_all_keys_in_different_order() {
        $this->config->reload(array('aa' => 1, 'bb' => 2, 'cc' => 3, 'dd' => 4));
        $before = array_keys($this->config->dump());
        $this->config->shuffle();
        $after = array_keys($this->config->dump());
        $this->assertEqualsCanonicalizing($before, $after);
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_reset_factory_retains_only_core_keys() {
        $this->config->reload(array(
            'version' => '1.0',
            'feeds' => array(),
            'imap_servers' => array(),
            'smtp_servers' => array(),
            'extra_key' => 'should_be_gone',
        ));
        $this->config->reset_factory();
        $dump = $this->config->dump();
        $this->assertArrayHasKey('version', $dump);
        $this->assertArrayNotHasKey('extra_key', $dump);
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_filter_servers_removes_entries_without_server_key() {
        $this->config->reload(array(
            'imap_servers' => array(
                0 => array('server' => 'imap.test.com', 'user' => 'u', 'pass' => 'p', 'auth' => 'plain', 'object' => false),
                1 => array('user' => 'u2', 'pass' => 'p2'),
            ),
            'smtp_servers' => array(),
        ));
        $this->config->filter_servers();
        $dump = $this->config->dump();
        $this->assertArrayNotHasKey(1, $dump['imap_servers']);
        $this->assertArrayHasKey(0, $dump['imap_servers']);
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_filter_servers_preserves_password_for_xoauth2_servers() {
        $this->config->reload(array(
            'imap_servers' => array(
                0 => array('server' => 'imap.test.com', 'user' => 'u', 'pass' => 'token123', 'auth' => 'xoauth2', 'object' => false),
            ),
            'smtp_servers' => array(),
            'no_password_save_setting' => true,
        ));
        $this->config->filter_servers();
        $dump = $this->config->dump();
        $this->assertEquals('token123', $dump['imap_servers'][0]['pass']);
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_save_with_crypt_mode_writes_encrypted_data() {
        $mock_config = new Hm_Mock_Config();
        $mock_config->set('single_server_mode', true);
        $config = new Hm_User_Config_File($mock_config);
        $config->reload(array('secret_key' => 'secret_value'), 'testuser');
        $config->save('testuser', 'testkey');
        $path = APP_PATH . 'tests/phpunit/data/testuser.txt';
        $this->assertTrue(is_readable($path));
        $raw = file_get_contents($path);
        $this->assertNotEquals('{"secret_key":"secret_value"}', $raw);
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_load_with_crypt_mode_decrypts_data() {
        $mock_config = new Hm_Mock_Config();
        $mock_config->set('single_server_mode', true);
        $config = new Hm_User_Config_File($mock_config);
        $config->reload(array('crypt_test' => 'crypt_value'), 'testuser');
        $config->save('testuser', 'testkey');
        $config2 = new Hm_User_Config_File($mock_config);
        $config2->load('testuser', 'testkey');
        $this->assertEquals('crypt_value', $config2->get('crypt_test'));
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_restore_servers_restores_removed_password() {
        $this->config->reload(array(
            'imap_servers' => array(
                0 => array('server' => 'imap.test.com', 'user' => 'u', 'pass' => 'secret', 'auth' => 'plain', 'object' => false),
            ),
            'smtp_servers' => array(),
            'no_password_save_setting' => true,
        ));
        $removed = $this->config->filter_servers();
        $this->config->restore_servers($removed);
        $dump = $this->config->dump();
        $this->assertEquals('secret', $dump['imap_servers'][0]['pass']);
    }
}
