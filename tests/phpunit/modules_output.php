<?php

use PHPUnit\Framework\TestCase;

/**
 * tests for the Hm_Module_Output trait
 */
class Hm_Test_Modules_Output extends TestCase {

    public $parent;
    public $handler_mod;
    public function setUp(): void {
        $this->parent = build_parent_mock();
        $this->handler_mod = new Hm_Handler_Test($this->parent, 'home');
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_out() {
        $this->assertTrue($this->handler_mod->out('foo', 'bar'));
        $this->assertFalse($this->handler_mod->out('foo', 'foo'));
        $this->assertTrue($this->handler_mod->append('name', 'value'));
        $this->assertFalse($this->handler_mod->out('name', 'value2'));
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_get() {
        $this->handler_mod->out('foo', 'bar');
        $this->assertEquals('bar', $this->handler_mod->get('foo'));
        $this->assertEquals('bar', $this->handler_mod->get('foo', ''));
        $this->assertEquals(0, $this->handler_mod->get('foo', 3));
        $this->assertEquals(array('bar'), $this->handler_mod->get('foo', array()));
        $this->assertEquals('default', $this->handler_mod->get('bar', 'default'));
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_append() {
        $this->assertTrue($this->handler_mod->append('test', 'value'));
        $this->assertTrue($this->handler_mod->append('test', 'value'));
        $this->assertEquals(array('value', 'value'), $this->handler_mod->get('test'));
        $this->assertTrue($this->handler_mod->out('no_append', 'blah', true));
        $this->assertFalse($this->handler_mod->append('no_append', 'blah'));
        $this->assertTrue($this->handler_mod->out('scaler', 'blah', false));
        $this->assertFalse($this->handler_mod->append('scaler', 'blah'));
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_concat() {
        $this->assertTrue($this->handler_mod->out('concat_test', array()));
        $this->assertFalse($this->handler_mod->concat('concat_test', 'test'));
        $this->assertTrue($this->handler_mod->concat('concat', 'start'));
        $this->assertTrue($this->handler_mod->concat('concat', 'start'));
        $this->assertEquals('startstart', $this->handler_mod->get('concat'));
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_module_output() {
        $this->handler_mod->out('foo', 'bar');
        $this->assertEquals(array('foo' => 'bar'), $this->handler_mod->module_output());
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_output_protected() {
        $this->handler_mod->out('foo', 'bar', true);
        $this->handler_mod->out('bar', 'foo', false);
        $this->assertEquals(array('foo'), $this->handler_mod->output_protected());
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_exists() {
        $this->handler_mod->out('foo', 'bar');
        $this->assertTrue($this->handler_mod->exists('foo'));
        $this->assertFalse($this->handler_mod->exists('blah'));
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_in() {
        $this->handler_mod->out('foo', 'bar');
        $this->assertTrue($this->handler_mod->in('foo', array('bar', 'baz')));
        $this->assertFalse($this->handler_mod->in('foo', array('baz', 'blah')));
    }
}
