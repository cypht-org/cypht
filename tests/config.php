<?php

class Hm_Test_Config extends PHPUnit_Framework_TestCase {

    /* set things up */
    public function setUp() {
        $mock_config = new Hm_Mock_Config();
        $this->config = new Hm_User_Config_File($mock_config);
    }

    /* tests for Hm_User_Config_File */
    public function test_dump() {
        $this->config->load('testuser', 'testkey');
        $this->assertEquals(array('foo' => 'bar'), $this->config->dump());
    }
    public function test_set() {
        $this->assertFalse($this->config->get('name', false));
        $this->config->set('name', 'value');
        $this->assertEquals('value', $this->config->get('name'));
    }
    public function test_get() {
        $this->config->load('testuser', 'testkey');
        $this->assertEquals('default', $this->config->get('asdf', 'default'));
        $this->assertEquals('bar', $this->config->get('foo', 'default'));
    }
    public function test_load() {
        $this->config->load('testuser', 'testkey');
        $this->assertEquals('bar', $this->config->get('foo', false));
    }
    public function test_get_path() {
        $this->assertEquals('./testuser.txt',  $this->config->get_path('testuser'));
    }
    public function test_set_tz() {
        date_default_timezone_set('Europe/London');
        $this->config->set('timezone_setting', 'UTC');
        $this->config->set_tz();
        $this->assertEquals('UTC', date_default_timezone_get());
    }
    public function test_reload() {
        date_default_timezone_set('Europe/London');
        $this->config->reload(array('timezone_setting' => 'UTC', 'bar' => 'foo'));
        $this->assertEquals('UTC', date_default_timezone_get());
        $this->assertEquals('foo', $this->config->get('bar'));
    }
    public function test_save() {
        $this->config->reload(array('foo' => 'bar'));
        $this->config->save('testuser', 'testkey');
        $this->config->load('testuser', 'testkey');
        $this->assertEquals(array('foo' => 'bar'), $this->config->dump());
    }

    /* tests for Hm_Site_Config_File */
    public function test_site_load() {
        $config = new Hm_Site_Config_File('./siteconfig.rc');
        $this->assertEquals(array('foo' => 'bar'), $config->dump());
    }

    /* TODO: tests for Hm_User_Config_DB */
}

?>
