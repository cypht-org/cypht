<?php

use PHPUnit\Framework\TestCase;

/**
 * tests for Hm_Output_HTTP
 */
class Hm_Test_Output extends TestCase {
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_send_response() {
        ob_start();
        ob_start();

        $http = new Hm_Output_HTTP('test', array('http_headers' => array('test')));

        $http->send_response();
        $output = ob_get_contents();
        ob_end_clean();
        $this->assertEquals('test', $output);

        ob_start();
        ob_start();

        $http = new Hm_Output_HTTP('test', array());

        $http->send_response();
        $output = ob_get_contents();
        ob_end_clean();
        $this->assertEquals('test', $output);
    }
}
