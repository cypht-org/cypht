<?php

require '../modules/core/setup.php';
require '../modules/core/modules.php';

class Hm_Test_Format extends PHPUnit_Framework_TestCase {

    private $json;
    private $html5;
   
    public function setUp() {
       $this->json = new Hm_Format_JSON();
       $this->html5 = new Hm_Format_HTML5();
       Hm_Output_Modules::add('test', 'date', false, false, 'after', true, 'core');
    }

    /* tests for Hm_Format_JSON and Hm_Format_HTML5 */
    function test_format_content() {
        $this->assertEquals('{"date":"today"}', $this->json->format_content(array('router_page_name' => 'test', 'date' => 'today'), array('date' => array(FILTER_UNSAFE_RAW, false))));
        $this->assertEquals('<div class="date">today</div>', $this->html5->format_content(array('router_page_name' => 'test', 'date' => 'today'), array()));
    }

    function test_get_language() {
        $this->assertTrue(count($this->html5->get_language('en')) > 0);
        $this->assertEquals(0, count($this->html5->get_language('blah')));
        $this->assertTrue(count($this->json->get_language('en')) > 0);
        $this->assertEquals(0, count($this->json->get_language('blah')));
    }
    function test_filter_output() {
        $this->assertEquals(array('date' => 'today'), $this->json->filter_output(array('date' => '<b>today</b>'), array('date' => array(FILTER_SANITIZE_STRING, false))));
    }
}

?>
