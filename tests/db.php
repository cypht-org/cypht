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
    public function test_connect() {
        $this->assertFalse(Hm_DB::connect($this->config));
        setup_db($this->config);
        $this->assertEquals('object', gettype(Hm_DB::connect($this->config)));
    }
    public function tearDown() {
        unset($this->config);
    }
}

?>
