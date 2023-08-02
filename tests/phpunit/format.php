<?php

use PHPUnit\Framework\TestCase;


/**
 * tests for Hm_Format_JSON and Hm_Format_HTML5
 */
class Hm_Test_Format extends TestCase {

    public function setUp(): void {
        require 'bootstrap.php';
        $config = new Hm_Mock_Config();
        $this->json = new Hm_Format_JSON($config);
        $this->html5 = new Hm_Format_HTML5($config);
        Hm_Output_Modules::add('test', 'date', false, false, 'after', true, 'core');
        Hm_Output_Modules::add('test', 'blah', false, false, 'after', true, 'core');
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_content() {
        $this->assertEquals('{"date":"today"}', $this->json->content(array('router_page_name' => 'test', 'language' => 'en', 'date' => 'today'), array('date' => array(FILTER_UNSAFE_RAW, false))));
        $this->assertEquals('testtoday', $this->html5->content(array('router_module_list' => array(), 'router_page_name' => 'test', 'date' => 'today'), array()));
        $config = new Hm_Mock_Config();
        $config->set('encrypt_ajax_requests', true);
        $this->json = new Hm_Format_JSON($config);
        $res = $this->json->content(array('router_page_name' => 'test', 'language' => 'en', 'date' => 'today'), array('date' => array(FILTER_UNSAFE_RAW, false)));
        $this->assertTrue((bool) preg_match('/^{"payload/', $res));
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_filter_output() {
        $this->assertEquals(array('date' => '&lt;b&gt;today&lt;/b&gt;'), $this->json->filter_all_output(array('date' => '<b>today</b>'), array('date' => array(FILTER_SANITIZE_FULL_SPECIAL_CHARS, false))));
        $this->assertEquals(array(), $this->json->filter_all_output(array('date' => array()), array('date' => array(FILTER_SANITIZE_FULL_SPECIAL_CHARS, false))));
        $this->assertEquals(array('test_array' => array('test')), $this->json->filter_all_output(array('test_array' => array('test')), array('test_array' => array(FILTER_UNSAFE_RAW, FILTER_REQUIRE_ARRAY))));
    }
    public function tearDown(): void {
        Hm_Output_Modules::del('test', 'blah');
    }
}
class Hm_Test_Transform extends TestCase {
    public function setUp(): void {
        require 'bootstrap.php';
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_stringify() {
        $this->assertEquals('{"foo":"YmFy"}', Hm_Transform::stringify(array('foo' => 'bar')));
        $this->assertEquals('', Hm_Transform::stringify(NULL));
        $this->assertEquals('asdf', Hm_Transform::stringify('asdf'));
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_unstringify() {
        $test = Hm_Transform::stringify(array('foo' => 'bar', 'baz' => array('test' => 'asdf')));
        $this->assertEquals(array('foo' => 'bar', 'baz' => array('test' => 'asdf')), Hm_Transform::unstringify($test));
        $this->assertFalse(Hm_Transform::unstringify(array()));
        $this->assertFalse(Hm_Transform::unstringify('asdf'));
        $this->assertEquals('asdf', Hm_Transform::unstringify('asdf', false, true));
        $this->assertEquals(array('foo' => 'bar'), Hm_Transform::unstringify('a:1:{s:3:"foo";s:4:"YmFy";}'));
        $int_test = Hm_Transform::stringify(array('foo' => 1));
        $this->assertEquals(array('foo' => 1), Hm_Transform::unstringify($int_test));
    }
}
?>
