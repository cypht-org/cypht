<?php

/**
 * tests for Hm_DB
 */
class Hm_Test_DB extends PHPUnit_Framework_TestCase {

    public function setUp() {
        require 'bootstrap.php';
        $this->config = new Hm_Mock_Config();
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_build_dsn() {
        setup_db($this->config);
        $this->assertEquals('object', gettype(Hm_DB::connect($this->config)));
        $this->assertEquals('mysql:host=127.0.0.1;dbname=test', Hm_DB::build_dsn());
        $this->config->data['db_driver'] = 'sqlite';
        $this->assertEquals('boolean', gettype(Hm_DB::connect($this->config)));
        $this->assertEquals('sqlite:127.0.0.1', Hm_DB::build_dsn());
        $this->config->data['db_driver'] = 'mysql';
        $this->config->data['db_connection_type'] = 'socket';
        $this->config->data['db_socket'] = '/test';
        $this->assertEquals('boolean', gettype(Hm_DB::connect($this->config)));
        $this->assertEquals('mysql:unix_socket=/test;dbname=test', Hm_DB::build_dsn());
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_connect() {
        $this->assertFalse(Hm_DB::connect($this->config));
        setup_db($this->config);
        $this->assertEquals('object', gettype(Hm_DB::connect($this->config)));
        $this->assertEquals('object', gettype(Hm_DB::connect($this->config)));
    }
    public function tearDown() {
        unset($this->config);
    }
}

?>
