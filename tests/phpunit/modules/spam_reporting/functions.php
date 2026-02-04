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
        $this->assertEquals('Spam report: <msgid@example.org>', $payload->subject);
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
}
