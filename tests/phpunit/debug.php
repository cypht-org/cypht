<?php

use PHPUnit\Framework\TestCase;

/**
 * tests for Hm_Debug
 */
class Hm_Test_Debug extends TestCase {
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_load_page_stats() {
        Hm_Debug::load_page_stats();
        $this->assertTrue(count(Hm_Debug::get()) > 4);
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
}
