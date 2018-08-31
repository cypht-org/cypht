<?php


class Hm_Test_API_Curl extends PHPUnit_Framework_TestCase {

    public function setUp() {
        require 'bootstrap.php';
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_command() {
        $api = new Hm_API_Curl();
        $this->assertEquals(array('unit' => 'test'), $api->command('asdf'));
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_curl_setopt_post() {
        $api = new Hm_API_Curl();
        $this->assertEquals(array('unit' => 'test'), $api->command('asdf', array(), array('foo' => 'bar')));
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_curl_result() {
        $api = new Hm_API_Curl();
        $this->assertEquals(array('unit' => 'test'), $api->command('asdf', array(), array('foo' => 'bar')));
        Hm_Functions::$exec_res = NULL;
        $this->assertEquals(array(), $api->command('asdf', array(), array('foo' => 'bar')));
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_curl_custom() {
        $api = new Hm_API_Curl('xml');
        $this->assertEquals('{"unit":"test"}', $api->command('asdf', array(), array(), 'foo', 'FOO'));
    }
}

?>
