<?php

class Hm_Test_Output extends PHPUnit_Framework_TestCase {

    private $http;

    public function setUp() {
        $this->http = new Hm_Output_HTTP();
    }

    /* tests for Hm_Output_HTTP */
    public function test_output_content() {
    }
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

    /* tests for Hm_Msgs */
    public function test_add() {
        Hm_Msgs::add('test msg');
        $msgs = Hm_Msgs::get();
        $this->assertTrue(in_array('test msg', $msgs, true));
    }
    public function test_get() {
        $this->assertTrue(in_array('test msg', Hm_Msgs::get(), true));
        Hm_Msgs::add('msg two');
        $this->assertTrue(in_array('msg two', Hm_Msgs::get(), true));
    }
    public function test_str() {
        $this->assertEquals('string: test', Hm_Msgs::str('test'));
        $this->assertEquals('integer: 0', Hm_Msgs::str(0));
        $this->assertEquals('double: 0.1', Hm_Msgs::str(.1));
        $this->assertEquals('array()', flatten(Hm_Msgs::str(array())));
        $this->assertEquals('stdclassobject()', flatten(Hm_Msgs::str(new stdClass)));
    }
    public function test_show() {
        $this->assertTrue(strstr(flatten(Hm_Msgs::show('return')), 'msgtwo') !== false);
        $this->assertNull(Hm_Msgs::show('log'));
        ob_start();
        Hm_Msgs::show();
        $output = ob_get_contents();
        ob_end_clean();
        $this->assertTrue(strlen($output) > 0);
    }
    public function test_elog() {
        $this->assertNull(elog('test'));
    }

    /* tests for Hm_Debug */
    public function test_load_page_stats() {
        Hm_Debug::load_page_stats();
        $this->assertTrue(count(Hm_Debug::get()) > 4);
    }
}

?>
