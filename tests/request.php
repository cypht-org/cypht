<?php

class Hm_Test_Request extends PHPUnit_Framework_TestCase {

    /* tests for the Hm_Request object */
    public function test_request() {
        $_SERVER['REQUEST_URI'] = 'test';
        $_GET['foo'] = 'bar';
        $_POST['bar'] = 1;
        $req = new Hm_Request(filters());
    }
}

?>
