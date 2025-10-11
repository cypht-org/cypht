<?php

use PHPUnit\Framework\TestCase;

/**
 * Tests for MTA-STS functionality
 */
class Hm_Test_MTA_STS extends TestCase {

    public function setUp(): void {
        require 'bootstrap.php';
        require APP_PATH.'lib/mta_sts.php';
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_extract_domain() {
        $this->assertEquals('example.com', Hm_MTA_STS::extract_domain('user@example.com'));
        $this->assertEquals('example.com', Hm_MTA_STS::extract_domain('User Name <user@example.com>'));
        $this->assertEquals('example.com', Hm_MTA_STS::extract_domain(' user@example.com '));
        $this->assertEquals('sub.example.com', Hm_MTA_STS::extract_domain('user@sub.example.com'));
        $this->assertFalse(Hm_MTA_STS::extract_domain('invalid-email'));
        $this->assertFalse(Hm_MTA_STS::extract_domain(''));
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_get_status_message() {
        // Test enforce mode
        $result = array(
            'enabled' => true,
            'policy' => array('mode' => 'enforce')
        );
        $this->assertEquals('MTA-STS enabled (enforce mode) - TLS required', Hm_MTA_STS::get_status_message($result));

        // Test testing mode
        $result = array(
            'enabled' => true,
            'policy' => array('mode' => 'testing')
        );
        $this->assertEquals('MTA-STS enabled (testing mode) - TLS preferred', Hm_MTA_STS::get_status_message($result));

        // Test none mode
        $result = array(
            'enabled' => true,
            'policy' => array('mode' => 'none')
        );
        $this->assertEquals('MTA-STS disabled', Hm_MTA_STS::get_status_message($result));

        // Test disabled
        $result = array(
            'enabled' => false,
            'policy' => null
        );
        $this->assertEquals('MTA-STS not configured', Hm_MTA_STS::get_status_message($result));
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_get_status_class() {
        // Test enforce mode
        $result = array(
            'enabled' => true,
            'policy' => array('mode' => 'enforce')
        );
        $this->assertEquals('mta-sts-enforce', Hm_MTA_STS::get_status_class($result));

        // Test testing mode
        $result = array(
            'enabled' => true,
            'policy' => array('mode' => 'testing')
        );
        $this->assertEquals('mta-sts-testing', Hm_MTA_STS::get_status_class($result));

        // Test disabled
        $result = array(
            'enabled' => false,
            'policy' => null
        );
        $this->assertEquals('mta-sts-disabled', Hm_MTA_STS::get_status_class($result));
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_clear_cache() {
        // Test instance method
        $mta_sts = new Hm_MTA_STS();
        $mta_sts->clear_cache();
        $this->assertTrue(true);
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_instance_creation() {
        // Test creating instance without domain
        $mta_sts = new Hm_MTA_STS();
        $this->assertInstanceOf('Hm_MTA_STS', $mta_sts);

        // Test creating instance with domain
        $mta_sts = new Hm_MTA_STS('example.com');
        $this->assertInstanceOf('Hm_MTA_STS', $mta_sts);
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_set_domain() {
        $mta_sts = new Hm_MTA_STS();
        $result = $mta_sts->set_domain('example.com');
        $this->assertInstanceOf('Hm_MTA_STS', $result);
    }
}
