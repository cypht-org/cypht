<?php

class Hm_Test_Router extends PHPUnit_Framework_TestCase {

    private $router;

    public function setUp() {
        $this->router = new Hm_Router();
    }

    /* tests for Hm_Router */
    public function test_process_request() {
        ob_start();
        $mock_config = new Hm_Mock_Config();
        $mock_config->data['disable_tls'] = true;
        $mock_config->data['auth_type'] = "DB";
        $mock_config->data['session_type'] = "DB";
        setup_db($mock_config);
        $mock_config->data['modules'] = 'imap';
        $this->router->process_request($mock_config, true);
        $this->assertTrue(ob_get_length() > 0);
    }
    public function test_process_request_again() {
        ob_start();
        $mock_config = new Hm_Mock_Config();
        $mock_config->data['disable_tls'] = false;
        $mock_config->data['auth_type'] = "None";
        $mock_config->data['session_type'] = "PHP";
        setup_db($mock_config);
        $mock_config->data['modules'] = 'pop3';
        $this->router->process_request($mock_config, false);
        $this->assertTrue(ob_get_length() > 0);
    }
    public function test_get_production_modules() {
        $mock_config = new Hm_Mock_Config();
        $mock_config->data['modules'] = 'imap,pop3';
        $this->assertEquals(array(array(), array(), array()), $this->router->get_production_modules($mock_config));
    }
    public function test_merge_filters() {
        $res = $this->router->merge_filters(filters(), array('allowed_get' => array('new' => 'thing')));
        $this->assertEquals('thing', $res['allowed_get']['new']);
        $res = $this->router->merge_filters(filters(), array('allowed_pages' => array('new')));
        $this->assertTrue(in_array('new', $res['allowed_pages'], true));
    }
}

?>
