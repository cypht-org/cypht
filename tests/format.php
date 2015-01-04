<?php

class Hm_Test_Format extends PHPUnit_Framework_TestCase {

    private $json;
   
    public function setUp() {
       $this->json = new Hm_Format_JSON();
    }

    /* tests for Hm_Format_JSON */
    function test_content() {
        $this->assertEquals('{}', $this->json->content(array(), array(), array()));
    }
    /* tests for Hm_Format_HTML5 */

}

?>
