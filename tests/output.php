<?php

/**
 * tests for Hm_Output_HTTP
 */
class Hm_Test_Output extends PHPUnit_Framework_TestCase {

    public function setUp() {
        require 'bootstrap.php';
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
    public function tearDown() {
        unset($this->http);
    }
}
class Hm_Test_Msgs extends PHPUnit_Framework_TestCase {

    public function setUp() {
        require 'bootstrap.php';
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_add() {
        Hm_Msgs::add('test msg');
        $msgs = Hm_Msgs::get();
        $this->assertTrue(in_array('test msg', $msgs, true));
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_get() {
        Hm_Msgs::add('test msg');
        $this->assertTrue(in_array('test msg', Hm_Msgs::get(), true));
        Hm_Msgs::add('msg two');
        $this->assertTrue(in_array('msg two', Hm_Msgs::get(), true));
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_str() {
        $this->assertEquals('string: test', Hm_Msgs::str('test'));
        $this->assertEquals('integer: 0', Hm_Msgs::str(0));
        $this->assertEquals('double: 0.1', Hm_Msgs::str(.1));
        $this->assertEquals('array()', flatten(Hm_Msgs::str(array())));
        $this->assertEquals('stdclassobject()', flatten(Hm_Msgs::str(new stdClass)));
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_show() {
        Hm_Msgs::add('msg two');
        $this->assertTrue(strstr(flatten(join('', Hm_Msgs::show('return'))), 'msgtwo') !== false);
        ob_start();
        Hm_Msgs::show();
        $output = ob_get_contents();
        ob_end_clean();
        $this->assertTrue(strlen($output) > 0);
        Hm_Msgs::show('log');
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_elog() {
        $this->assertNull(elog('test'));
    }
}

/**
 * tests for Hm_Debug
 */
class Hm_Test_Debug extends PHPUnit_Framework_TestCase {

    public function setUp() {
        require 'bootstrap.php';
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_load_page_stats() {
        Hm_Debug::load_page_stats();
        $this->assertTrue(count(Hm_Debug::get()) > 4);
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
}

/**
 * tests for elog
 */
class Hm_Test_Elog extends PHPUnit_Framework_TestCase {
    public function setUp() {
        define( 'DEBUG_MODE', true);
        require 'bootstrap.php';
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_elog() {
        /* TODO: assertions */
        elog('test');
    }
}

?>
