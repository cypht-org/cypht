<?php

use PHPUnit\Framework\TestCase;

require_once APP_PATH.'modules/spam_reporting/modules.php';

/**
 * Tests for spam reporting module helpers and adapters
 */
class Hm_Test_Spam_Reporting_Functions extends TestCase {

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_spam_report_from_raw_message() {
        $raw = "From: sender@example.org\r\n".
            "To: victim@example.org\r\n".
            "Subject: Test Subject\r\n".
            "Message-ID: <msgid@example.org>\r\n".
            "MIME-Version: 1.0\r\n".
            "Content-Type: text/plain; charset=UTF-8\r\n".
            "\r\n".
            "Hello world\r\n";

        $report = Hm_Spam_Report::from_raw_message($raw, array('uid' => 1));
        $this->assertInstanceOf(Hm_Spam_Report::class, $report);
        $this->assertStringContainsString('Subject: Test Subject', $report->get_raw_headers_string());
        $this->assertStringContainsString('Hello world', (string) $report->body_text);
        $this->assertSame($raw, $report->raw_message);
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_email_target_payload_subject_and_notes() {
        $raw = "From: sender@example.org\r\n".
            "To: victim@example.org\r\n".
            "Subject: Test Subject\r\n".
            "Message-ID: <msgid@example.org>\r\n".
            "MIME-Version: 1.0\r\n".
            "Content-Type: text/plain; charset=UTF-8\r\n".
            "\r\n".
            "Hello world\r\n";

        $report = Hm_Spam_Report::from_raw_message($raw, array());
        $target = new Hm_Spam_Report_Email_Target(array(
            'to' => 'abuse@example.org',
            'subject_prefix' => 'Spam report'
        ));

        $payload = $target->build_payload($report, array('user_notes' => 'user note'));
        $this->assertInstanceOf(Hm_Spam_Report_Payload::class, $payload);
        $this->assertEquals('Spam report: msgid@example.org', $payload->subject);
        $this->assertStringContainsString('User notes:', $payload->body);
        $this->assertStringContainsString('user note', $payload->body);
        $this->assertSame($raw, $payload->raw_message);
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_email_target_subject_fallback_without_message_id() {
        $raw = "From: sender@example.org\r\n".
            "To: victim@example.org\r\n".
            "Subject: Test Subject\r\n".
            "MIME-Version: 1.0\r\n".
            "Content-Type: text/plain; charset=UTF-8\r\n".
            "\r\n".
            "Hello world\r\n";

        $report = Hm_Spam_Report::from_raw_message($raw, array());
        $target = new Hm_Spam_Report_Email_Target(array(
            'to' => 'abuse@example.org',
            'subject_prefix' => 'Spam report'
        ));

        $payload = $target->build_payload($report, array());
        $this->assertEquals('Spam report', $payload->subject);
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_email_target_availability_single_recipient_only() {
        $raw = "From: sender@example.org\r\n".
            "To: victim@example.org\r\n".
            "Subject: Test Subject\r\n".
            "MIME-Version: 1.0\r\n".
            "Content-Type: text/plain; charset=UTF-8\r\n".
            "\r\n".
            "Hello world\r\n";
        $report = Hm_Spam_Report::from_raw_message($raw, array());

        $single = new Hm_Spam_Report_Email_Target(array('to' => 'abuse@example.org'));
        $multi = new Hm_Spam_Report_Email_Target(array('to' => 'a@example.org, b@example.org'));
        $empty = new Hm_Spam_Report_Email_Target(array('to' => ''));

        $this->assertTrue($single->is_available($report, new Hm_Mock_Config()));
        $this->assertFalse($multi->is_available($report, new Hm_Mock_Config()));
        $this->assertFalse($empty->is_available($report, new Hm_Mock_Config()));
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_platform_catalog_loads_from_json() {
        $config = new Hm_Mock_Config();
        $config->set('spam_reporting_platforms_file', APP_PATH.'data/spam_report_platforms.json');
        $platforms = spam_reporting_load_platform_catalog($config);
        $this->assertNotEmpty($platforms);
        $ids = array_column($platforms, 'id');
        $this->assertContains('spamcop', $ids);
        $this->assertArrayHasKey('description', $platforms[0]);
        $this->assertArrayHasKey('methods', $platforms[0]);
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_provider_mapping_loads() {
        $config = new Hm_Mock_Config();
        $config->set('spam_reporting_provider_mapping_file', APP_PATH.'data/spam_report_provider_mapping.json');
        $mappings = spam_reporting_load_provider_mapping($config);
        $this->assertNotEmpty($mappings);
        $this->assertArrayHasKey('provider_id', $mappings[0]);
        $this->assertArrayHasKey('platform_ids', $mappings[0]);
        $this->assertArrayHasKey('signals', $mappings[0]);
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_provider_detection_from_received_header() {
        $raw = "From: sender@gmail.com\r\n".
            "To: victim@example.org\r\n".
            "Subject: Test\r\n".
            "Received: from smtp.gmail.com (smtp.gmail.com [1.2.3.4])\r\n".
            "MIME-Version: 1.0\r\n".
            "Content-Type: text/plain\r\n".
            "\r\nbody\r\n";
        $report = Hm_Spam_Report::from_raw_message($raw, array());
        $this->assertNotFalse($report);
        $config = new Hm_Mock_Config();
        $config->set('spam_reporting_provider_mapping_file', APP_PATH.'data/spam_report_provider_mapping.json');
        $mappings = spam_reporting_load_provider_mapping($config);
        $detected = spam_reporting_detect_providers($report->get_parsed_message(), $mappings);
        $this->assertNotEmpty($detected);
        $provider_ids = array_column($detected, 'provider_id');
        $this->assertContains('gmail', $provider_ids);
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_suggested_target_ids_resolves_platform_to_target() {
        $detected = array(
            array('provider_id' => 'gmail', 'provider_name' => 'Gmail', 'confidence' => 2, 'platform_ids' => array('google_abuse'))
        );
        $targets = array(
            array('id' => 'target_google', 'platform_id' => 'google_abuse', 'label' => 'Google Abuse'),
            array('id' => 'target_spamcop', 'platform_id' => 'spamcop', 'label' => 'SpamCop')
        );
        $suggested = spam_reporting_suggested_target_ids($detected, $targets);
        $this->assertContains('target_google', $suggested);
        $this->assertNotContains('target_spamcop', $suggested);
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_signal_matches() {
        $this->assertTrue(spam_reporting_signal_matches('smtp.gmail.com', array('gmail.com', 'smtp.gmail.com')));
        $this->assertTrue(spam_reporting_signal_matches('mail.google.com', array('google.com')));
        $this->assertFalse(spam_reporting_signal_matches('example.org', array('gmail.com')));
    }
}
