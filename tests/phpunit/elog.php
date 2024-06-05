<?php

use PHPUnit\Framework\TestCase;

/**
 * tests for elog
 */
class Hm_Test_Elog extends TestCase {
    public function setUp(): void {
        define( 'DEBUG_MODE', true);
        require 'bootstrap.php';
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_elog() {
        $this->assertEquals('string: test', elog('test'));
    }
}
