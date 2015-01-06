<?php

class Hm_Test_Request extends PHPUnit_Framework_TestCase {

    /* tests for the Hm_Request object */
    public function test_request() {
        $_SERVER['REQUEST_URI'] = 'test';
        $_GET['foo'] = 'bar';
        $_POST['bar'] = 1;
        $req = new Hm_Request(filters());
        $this->assertEquals('HTTP', $req->type);
        $this->assertEquals(array('bar' => 1), $req->post);
        $this->assertEquals(array(), $req->cookie);
        $this->assertEquals(array('foo' => 'bar'), $req->get);
        $this->assertEquals(array('REQUEST_URI' => 'test'), $req->server);
        $this->assertEquals('test/', $req->path);
        $this->assertEquals('Hm_Format_HTML5', $req->format);
        $this->assertFalse($req->tls);
        $this->assertFalse($req->mobile);
    }
}

?>
