<?php

class Hm_Test_DB extends PHPUnit_Framework_TestCase {

    private $config;

    /* set things up */
    public function setUp() {
        $this->config = new Hm_Mock_Config();
        $this->config->set('db_driver', 'test');
    }
    public function test_connect() {
        /* TODO */
    }
}

?>
