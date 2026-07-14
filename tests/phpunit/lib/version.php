<?php

use PHPUnit\Framework\TestCase;

class Hm_Test_Version extends TestCase {

    public function setUp(): void {
        require_once APP_PATH.'lib/version.php';
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_cypht_version_constant_is_defined() {
        $this->assertTrue(defined('CYPHT_VERSION'));
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_cypht_version_is_a_non_empty_string() {
        $this->assertIsString(CYPHT_VERSION);
        $this->assertNotEmpty(CYPHT_VERSION);
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_cypht_version_matches_semver_format() {
        $this->assertMatchesRegularExpression('/^\d+\.\d+\.\d+/', CYPHT_VERSION);
    }
}
