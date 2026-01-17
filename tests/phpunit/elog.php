<?php

use PHPUnit\Framework\TestCase;

/**
 * tests for elog
 */
class Hm_Test_Elog extends TestCase {
    public static function setUpBeforeClass(): void {
        putenv('CYPHT_TEST_DEBUG_MODE=true');
        $_ENV['CYPHT_TEST_DEBUG_MODE'] = 'true';
    }

    public static function tearDownAfterClass(): void {
        putenv('CYPHT_TEST_DEBUG_MODE');
        unset($_ENV['CYPHT_TEST_DEBUG_MODE']);
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_elog() {
        $this->assertEquals('string: test', elog('test'));
    }
}
