<?php

/**
 * tests for the Hm_Request class
 */
class Hm_Test_Request extends PHPUnit_Framework_TestCase {

    public function setUp() {
        require 'bootstrap.php';
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_request() {
        
        $_SERVER['REQUEST_URI'] = 'test';
        $_GET['foo'] = 'bar';
        $_POST['bar'] = 1;
        $_POST['blah'] = 'blah';
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
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_request_again() {
        $_SERVER['REQUEST_URI'] = 'test';
        $_SERVER['HTTPS'] = 'on';
        $_SERVER['HTTP_X_REQUESTED_WITH'] = 'xmlhttprequest';
        $_SERVER['HTTP_USER_AGENT'] = 'ipad';
        $_GET['foo'] = 'bar';
        $_GET['blah'] = 'blah';
        $req = new Hm_Request(filters());
        $this->assertEquals('AJAX', $req->type);
        $this->assertEquals('Hm_Format_JSON', $req->format);
        $this->assertTrue($req->tls);
        $this->assertTrue($req->mobile);
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_one_more_time() {
        $_SERVER['REQUEST_URI'] = 'test?hmm=1';
        $_SERVER['REQUEST_SCHEME'] = 'https';
        $req = new Hm_Request(filters());
        $this->assertTrue($req->tls);
    }
}

?>
