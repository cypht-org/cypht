<?php

use PHPUnit\Framework\TestCase;

/**
 * tests for Hm_DB
 */
class Hm_Test_DB extends TestCase {

    public function setUp(): void {
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
        if ($this->config->data['db_driver'] == 'mysql') {
            $this->assertEquals('mysql:host=127.0.0.1;dbname=cypht_test', Hm_DB::build_dsn());
        }
        if ($this->config->data['db_driver'] == 'pgsql') {
            $this->assertEquals('pgsql:host=127.0.0.1;dbname=cypht_test', Hm_DB::build_dsn());
        }
        $this->config->data['db_driver'] = 'sqlite';
        $type = gettype(Hm_DB::connect($this->config));
        $this->assertTrue($type == 'boolean' || $type == 'object');
        $this->assertEquals('sqlite:/tmp/test.db', Hm_DB::build_dsn());
        $this->config->data['db_driver'] = 'mysql';
        $this->config->data['db_connection_type'] = 'socket';
        $this->config->data['db_socket'] = '/test';
        $this->assertEquals('boolean', gettype(Hm_DB::connect($this->config)));
        $this->assertEquals('mysql:unix_socket=/test;dbname=cypht_test', Hm_DB::build_dsn());
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_execute() {
        $this->assertFalse(Hm_DB::connect($this->config));
        setup_db($this->config);
        $db = Hm_DB::connect($this->config);
        $this->assertEquals(0, Hm_DB::execute($db, "update hm_user set username='foo' where username='bar'", array()));
        $this->assertTrue(count(Hm_DB::execute($db, 'select * from hm_user', array(), false, true)) > 0);
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
    public function tearDown(): void {
        unset($this->config);
    }
}

?>
