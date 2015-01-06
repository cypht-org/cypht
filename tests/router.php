<?php

class Hm_Test_Router extends PHPUnit_Framework_TestCase {

    private $router;

    public function setUp() {
        $this->router = new Hm_Router();
    }

    /* tests for Hm_Router */
    public function test_process_request() {
        $mock_config = new Hm_Mock_Config();
        setup_db($mock_config);
        ob_start();
        ob_start();
        $this->router->process_request($mock_config);
        $output = ob_get_contents();
        $this->assertTrue(strlen($output) > 0);
        ob_end_clean();
    }
    public function test_merge_filters() {
        $res = $this->router->merge_filters(filters(), array('allowed_get' => array('new' => 'thing')));
        $this->assertEquals('thing', $res['allowed_get']['new']);
        $res = $this->router->merge_filters(filters(), array('allowed_pages' => array('new')));
        $this->assertTrue(in_array('new', $res['allowed_pages'], true));
    }
}

?>
