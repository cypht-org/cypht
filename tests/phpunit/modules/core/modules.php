<?php

use PHPUnit\Framework\TestCase;

class Hm_Test_Core_Modules extends TestCase {

    public function setUp(): void {
        require_once APP_PATH.'modules/core/modules.php';
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_max_per_source_constant_is_defined_with_expected_value() {
        $this->assertTrue(defined('MAX_PER_SOURCE'));
        $this->assertEquals(1000, MAX_PER_SOURCE);
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_default_per_source_constant_is_defined_with_expected_value() {
        $this->assertTrue(defined('DEFAULT_PER_SOURCE'));
        $this->assertEquals(20, DEFAULT_PER_SOURCE);
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_default_since_constant_is_one_week() {
        $this->assertTrue(defined('DEFAULT_SINCE'));
        $this->assertEquals('-1 week', DEFAULT_SINCE);
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_default_search_fld_is_text() {
        $this->assertTrue(defined('DEFAULT_SEARCH_FLD'));
        $this->assertEquals('TEXT', DEFAULT_SEARCH_FLD);
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_default_max_google_contacts_number_is_defined() {
        $this->assertTrue(defined('DEFAULT_MAX_GOOGLE_CONTACTS_NUMBER'));
        $this->assertEquals(500, DEFAULT_MAX_GOOGLE_CONTACTS_NUMBER);
    }
}
