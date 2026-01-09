<?php

use PHPUnit\Framework\TestCase;

/**
 * tests for the Hm_Modules trait
 */
class Hm_Test_Modules extends TestCase {

    public function setUp(): void {
        Hm_Test_Module_List::add('test', 'date', false, false, 'after', true, 'core');
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_load() {
        Hm_Test_Module_List::load(array('test' => array('date' => array('core', false))));
        $this->assertEquals(array('test' => array('date' => array('core', false))), Hm_Test_Module_List::dump());
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_add() {
        Hm_Test_Module_List::add('test', 'date', false, false, false, true, 'core');
        $this->assertEquals(array('test' => array('date' => array('core', false))), Hm_Test_Module_List::dump());
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_dump() {
        $this->assertEquals(array('test' => array('date' => array('core', false))), Hm_Test_Module_List::dump());
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_add_to_all_pages() {
        Hm_Test_Module_List::add_to_all_pages('test', false, false, false, 'core');
        $mods = Hm_Test_Module_List::dump();
        $this->assertEquals(array('core', false), $mods['test']['test']);
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_set_source() {
        Hm_Test_Module_List::set_source('test');
        Hm_Test_Module_List::add('test', 'new', false);
        $mods = Hm_Test_Module_List::dump();
        $this->assertEquals('test', $mods['test']['new'][0]);
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_replace() {
        Hm_Test_Module_List::set_source('test');
        Hm_Test_Module_List::add('test', 'new', false);
        Hm_Test_Module_List::replace('new', 'more_new');
        $mods = Hm_Test_Module_List::dump();
        $this->assertEquals('test', $mods['test']['more_new'][0]);
        $this->assertFalse(isset($mods['test']['new']));
        Hm_Test_Module_List::replace('more_new', 'even_newer', 'test');
        $mods = Hm_Test_Module_List::dump();
        $this->assertEquals('test', $mods['test']['even_newer'][0]);
        $this->assertFalse(isset($mods['test']['more_new']));
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_del() {
        Hm_Test_Module_List::set_source('test');
        Hm_Test_Module_List::add('test', 'new', false);

        $mods = Hm_Test_Module_List::dump();
        $this->assertEquals('test', $mods['test']['new'][0]);
        Hm_Test_Module_List::del('test', 'new');
        $mods = Hm_Test_Module_List::dump();
        $this->assertFalse(isset($mods['test']['new']));
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_get_for_page() {
        $this->assertEquals(array('date' => array('core', false)), Hm_Test_Module_List::get_for_page('test'));
        Hm_Test_Module_List::set_source('core');
        Hm_Test_Module_List::add('test', 'new', false);
        $this->assertEquals(array('new' => array('core', false), 'date' => array('core', false)), Hm_Test_Module_List::get_for_page('test'));
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_queue_module_for_all_pages() {
        Hm_Test_Module_List::queue_module_for_all_pages('testqueue', false, 'date', 'after', 'core');
        Hm_Test_Module_List::process_all_page_queue();
        $mods = Hm_Test_Module_List::dump();
        $this->assertEquals(array('core', false), $mods['test']['testqueue']);
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_try_queued_modules() {
        Hm_Test_Module_List::add('test', 'queued', false, 'not_added_yet', 'after', true, 'core');
        $this->assertEquals(1, count(Hm_Test_Module_List::get_for_page('test')));
        Hm_Test_Module_List::add('test', 'not_added_yet', false, 'date', 'after', false, 'core');
        Hm_Test_Module_List::add('test', 'queued', false, 'never_added', 'after', true, 'core');
        Hm_Test_Module_List::add('test', 'notqueued', false, 'never_added', 'after', true, 'core');
        Hm_Test_Module_List::try_queued_modules();
        Hm_Test_Module_List::try_queued_modules();
        Hm_Test_Module_List::try_queued_modules();
        $this->assertEquals(3, count(Hm_Test_Module_List::get_for_page('test')));
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_module_replace() {
        Hm_Test_Module_List::replace('foo', false, 'test');
        Hm_Test_Module_List::add('test', 'foo', false);
        Hm_Test_Module_List::process_replace_queue();
        $this->assertTrue(array_key_exists('', Hm_Test_Module_List::get_for_page('test')));
    }
}
