<?php

use PHPUnit\Framework\TestCase;

/**
 * tests for Hm_Output_HTTP
 */
class Hm_Test_Output extends TestCase {

    public function setUp(): void {
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
    public function tearDown(): void {
        unset($this->http);
    }
}

/**
 * tests for Hm_Msgs
 */
class Hm_Test_Msgs extends TestCase {

    public function setUp(): void {
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
    public function test_flush() {
        Hm_Msgs::add('test msg');
        $this->assertTrue(in_array('test msg', Hm_Msgs::get(), true));
        Hm_Msgs::flush();
        $this->assertEquals(array(), Hm_Msgs::get());
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
        Hm_Msgs::add('msg');
        $this->assertEquals("Array\n(\n    [0] => msg\n)\n", Hm_Msgs::show());
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
class Hm_Test_Debug extends TestCase {

    public function setUp(): void {
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
class Hm_Test_Elog extends TestCase {
    public function setUp(): void {
        define( 'DEBUG_MODE', true);
        require 'bootstrap.php';
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_elog() {
        $this->assertEquals('string: test', elog('test'));
    }
}

?>
