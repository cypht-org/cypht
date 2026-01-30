<?php

use PHPUnit\Framework\TestCase;

/**
 * tests for Hm_Output_HTTP
 */
class Hm_Test_Output extends TestCase {

    public $http;
    public function setUp(): void {
        $this->http = new Hm_Output_HTTP();
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_send_response() {
        ob_start();
        ob_start();
        $this->http->send_response('test', array('http_headers' => array('test')));
        $output = ob_get_contents();
        ob_end_clean();
        $this->assertEquals('test', $output);
        ob_start();
        ob_start();
        $this->http->send_response('test', array());
        $output = ob_get_contents();
        ob_end_clean();
        $this->assertEquals('test', $output);
    }
    public function tearDown(): void {
        unset($this->http);
    }
}
