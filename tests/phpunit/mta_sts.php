<?php

use PHPUnit\Framework\TestCase;

require_once 'bootstrap.php';
require_once APP_PATH.'lib/mta_sts.php';
require_once APP_PATH.'modules/mta_sts/modules.php';

/**
 * Tests for MTA-STS functionality
 */
class Hm_Test_MTA_STS extends TestCase {

    public function setUp(): void {
        require_once 'bootstrap.php';
        require_once APP_PATH.'lib/mta_sts.php';
        require_once APP_PATH.'modules/mta_sts/modules.php';
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_extract_domain() {
        $mta_sts = new Hm_MTA_STS();
        $this->assertEquals('example.com', $mta_sts->extract_domain('user@example.com'));
        $this->assertEquals('example.com', $mta_sts->extract_domain('User Name <user@example.com>'));
        $this->assertEquals('example.com', $mta_sts->extract_domain(' user@example.com '));
        $this->assertEquals('sub.example.com', $mta_sts->extract_domain('user@sub.example.com'));
        $this->assertFalse($mta_sts->extract_domain('invalid-email'));
        $this->assertFalse($mta_sts->extract_domain(''));
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
        $mta_sts = new Hm_MTA_STS();
        $this->assertEquals('MTA-STS policy published (enforce mode)', $mta_sts->get_status_message($result));

        // Test testing mode
        $result = array(
            'enabled' => true,
            'policy' => array('mode' => 'testing')
        );
        $this->assertEquals('MTA-STS policy published (testing mode)', $mta_sts->get_status_message($result));

        // Test none mode
        $result = array(
            'enabled' => true,
            'policy' => array('mode' => 'none')
        );
        $this->assertEquals('MTA-STS disabled', $mta_sts->get_status_message($result));

        // Test disabled
        $result = array(
            'enabled' => false,
            'policy' => null
        );
        $this->assertEquals('MTA-STS not configured', $mta_sts->get_status_message($result));
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
        $mta_sts = new Hm_MTA_STS();
        $this->assertEquals('mta-sts-enforce', $mta_sts->get_status_class($result));

        // Test testing mode
        $result = array(
            'enabled' => true,
            'policy' => array('mode' => 'testing')
        );
        $this->assertEquals('mta-sts-testing', $mta_sts->get_status_class($result));

        // Test disabled
        $result = array(
            'enabled' => false,
            'policy' => null
        );
        $this->assertEquals('mta-sts-disabled', $mta_sts->get_status_class($result));
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_parse_policy() {
        $mta_sts = new Hm_MTA_STS();
        $policy = $mta_sts->parse_policy("version: STSv1\nmode: enforce\nmx: mail.example.com\nmax_age: 86400\n");
        $this->assertEquals('STSv1', $policy['version']);
        $this->assertEquals('enforce', $policy['mode']);
        $this->assertEquals(array('mail.example.com'), $policy['mx']);
        $this->assertEquals(86400, $policy['max_age']);
        $this->assertFalse($mta_sts->parse_policy("version: STSv1\nmode: enforce\nmax_age: 86400\n"));
        $this->assertFalse($mta_sts->parse_policy("version: STSv1\nmode: invalid\nmx: mail.example.com\nmax_age: 86400\n"));
        $this->assertNotFalse($mta_sts->parse_policy("version: STSv1\nmode: none\nmax_age: 86400\n"));
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

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_parse_recipients_from_multiple_compose_fields() {
        $recipients = mta_sts_parse_recipients(array(
            '"Doe, Jane" <jane@example.com>, Bob <bob@example.org>',
            'carol@example.net; invalid-address',
            'jane@example.com'
        ));

        $this->assertEquals(array(
            'jane@example.com',
            'bob@example.org',
            'carol@example.net'
        ), $recipients);
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_check_domain_uses_shared_cache() {
        $first = new Hm_Testable_MTA_STS('example.com');
        $second = new Hm_Testable_MTA_STS('example.com');

        $this->assertTrue($first->check_domain()['enabled']);
        $this->assertTrue($second->check_domain()['enabled']);
        $this->assertEquals(1, Hm_Testable_MTA_STS::$dns_lookups);

        $second->clear_cache();
        $this->assertTrue($second->check_domain()['enabled']);
        $this->assertEquals(2, Hm_Testable_MTA_STS::$dns_lookups);
    }
}

class Hm_Testable_MTA_STS extends Hm_MTA_STS {
    public static $dns_lookups = 0;

    protected function get_mta_sts_dns_record() {
        self::$dns_lookups++;
        return 'v=STSv1; id=20260429;';
    }

    protected function fetch_policy() {
        return $this->parse_policy("version: STSv1\nmode: enforce\nmx: mail.example.com\nmax_age: 86400\n");
    }
}
