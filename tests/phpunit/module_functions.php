<?php

use PHPUnit\Framework\TestCase;

/**
 * tests for the functional interface to modules
 */
class Hm_Test_Module_Functions extends TestCase {
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_add_handler() {
        add_handler('test', 'test_mod', false);
        add_handler('test', 'test_mod2', false, 'core', 'test_mod', 'after', false);
        add_handler('test', 'test_mod3', false, 'core', 'test_mod', 'before', false);
        $mods = Hm_Handler_Modules::get_for_page('test');
        $keys = array_keys($mods);
        $this->assertEquals('test_mod3', $keys[0]);
        $this->assertEquals('test_mod', $keys[1]);
        $this->assertEquals('test_mod2', $keys[2]);
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_output_source() {
        output_source('test');
        add_output('test', 'source_test', false);
        $this->assertEquals(array('source_test' => array('test', false)), Hm_Output_Modules::get_for_page('test'));
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_handler_source() {
        handler_source('test');
        add_handler('newtest', 'source_test', false);
        $this->assertEquals(array('source_test' => array('test', false)), Hm_Handler_Modules::get_for_page('newtest'));
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_replace_module() {
        add_handler('test', 'new_handler', false);
        add_output('test', 'new_output', false);

        replace_module('handler', 'new_handler', 'replace_test');
        replace_module('output', 'new_output', 'replace_test');
        $this->assertEquals(array('replace_test' => array(false, false)), Hm_Handler_Modules::get_for_page('test'));
        $this->assertEquals(array('replace_test' => array(false, false)), Hm_Output_Modules::get_for_page('test'));
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_add_output() {
        add_output('test', 'add_output', false);
        $keys = array_keys(Hm_Output_Modules::get_for_page('test'));
        $this->assertEquals('add_output', $keys[0]);
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_add_module_to_all_pages() {
        add_handler('test', 'test_mod', false);
        add_output('test', 'test_mod', false);
        add_module_to_all_pages('output', 'all_pages', false, 'test', false, false);
        add_module_to_all_pages('handler', 'all_pages', false, 'test', false, false);
        Hm_Output_Modules::process_all_page_queue();
        Hm_Handler_Modules::process_all_page_queue();
        $mods = Hm_Output_Modules::dump();
        foreach ($mods as $name => $vals) {
            if (!preg_match("/^ajax_/", $name)) {
                $this->assertTrue(array_key_exists('all_pages', $vals));
            }
        }
        $mods = Hm_Handler_Modules::dump();
        foreach ($mods as $name => $vals) {
            if (!preg_match("/^ajax_/", $name)) {
                $this->assertTrue(array_key_exists('all_pages', $vals));
            }
        }
    }
}
