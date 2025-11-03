<?php

use PHPUnit\Framework\TestCase;

/**
 * tests for the Hm_Output_Module class
 */
class Hm_Test_Output_Module extends TestCase {

    public $output_mod;
    public function setUp(): void {
        $this->output_mod = new Hm_Output_Test(array('foo' => 'bar', 'bar' => 'foo'), array('bar'));
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_output_content() {
        $this->output_mod->output_content('HTML5', array('Main' => false, 'Test' => 'Translated', 'interface_lang' => 'en', 'interface_direction' => 'ltr'), array());
        $this->assertEquals('Main', $this->output_mod->trans('Main'));
        $this->assertEquals('Translated', $this->output_mod->trans('Test'));
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_trans() {
        $this->assertEquals('inbox', $this->output_mod->trans('inbox'));
        $this->assertEquals('Main', $this->output_mod->trans('Main'));
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_html_safe() {
        $this->assertEquals('&lt;script&gt;', $this->output_mod->html_safe('<script>'));
        $this->assertEquals('nohtml', $this->output_mod->html_safe('nohtml'));
        $this->assertEquals('&lt;script&gt;', $this->output_mod->html_safe('<script>', true));
    }
    public function tearDown(): void {
        unset($this->output_mod);
    }
}
