<?php

class Hm_Test_DB extends PHPUnit_Framework_TestCase {

    private $config;

    /* set things up */
    public function setUp() {
        $this->config = new Hm_Mock_Config();
    }

    /* test for Hm_DB */
    public function test_connect() {
        $this->assertFalse(Hm_DB::connect($this->config));
        setup_db($this->config);
        $this->assertEquals('object', gettype(Hm_DB::connect($this->config)));
    }
}

?>
