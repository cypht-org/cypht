<?php

/**
 * Generic email spam report target
 * @package modules
 * @subpackage spam_reporting
 */

if (!defined('DEBUG_MODE')) { die(); }

class Hm_Spam_Report_Email_Target extends Hm_Spam_Report_Target_Abstract {
    protected $id = 'email_target';
    protected $label = 'Email Target';
    protected $to = '';
    protected $subject_prefix = 'Spam report';

    public function __construct(array $config = array()) {
        $this->configure($config);
    }

    public function configure(array $config) {
        if (array_key_exists('id', $config) && is_string($config['id'])) {
            $this->id = $config['id'];
        }
        if (array_key_exists('label', $config) && is_string($config['label'])) {
            $this->label = $config['label'];
        }
        if (array_key_exists('to', $config) && is_string($config['to'])) {
            $this->to = $config['to'];
        }
        if (array_key_exists('subject_prefix', $config) && is_string($config['subject_prefix'])) {
            $this->subject_prefix = $config['subject_prefix'];
        }
    }

    public function id() {
        return $this->id;
    }

    public function label() {
        return $this->label;
    }

    public function capabilities() {
        return array('email');
    }

    public function requirements() {
        return array('raw_headers', 'body_text');
    }

    public function is_available(Hm_Spam_Report $report, $user_config) {
        if (!trim((string) $this->to)) {
            return false;
        }
        $parsed = process_address_fld($this->to);
        return count($parsed) === 1;
    }

    public function build_payload(Hm_Spam_Report $report, array $user_input = array()) {
        $message = $report->get_parsed_message();
        $message_id = $message ? $message->getHeaderValue('Message-ID', '') : '';
        $subject = $message_id ? ($this->subject_prefix . ': ' . $message_id) : $this->subject_prefix;
        $notes = '';
        if (array_key_exists('user_notes', $user_input) && trim((string) $user_input['user_notes'])) {
            $notes = "\r\n\r\nUser notes:\r\n" . trim((string) $user_input['user_notes']);
        }
        $body = "Spam report\r\n\r\n";
        if ($message_id) {
            $body .= "Message-ID: " . $message_id . "\r\n\r\n";
        }
        $body .= "Attached: original message (message/rfc822)";
        $body .= $notes;
        return new Hm_Spam_Report_Payload($this->to, $subject, $body, 'text/plain', array(), $report->raw_message);
    }

    public function deliver($payload, $context = null) {
        if (!($payload instanceof Hm_Spam_Report_Payload)) {
            return new Hm_Spam_Report_Result(false, 'Invalid payload');
        }
        if (!($context instanceof Hm_Spam_Report_Delivery_Context)) {
            return new Hm_Spam_Report_Result(false, 'Missing delivery context');
        }
        list($mailbox, $server_id) = spam_reporting_get_smtp_mailbox($context->site_config);
        if (!$mailbox || !$mailbox->authed()) {
            return new Hm_Spam_Report_Result(false, 'SMTP server unavailable');
        }

        $from = $context->site_config->get('spam_reporting_sender_address', '');
        if (!trim((string) $from)) {
            if ($server_id !== false) {
                Hm_SMTP_List::del($server_id);
            }
            return new Hm_Spam_Report_Result(false, 'Missing sender address');
        }
        $from_name = $context->site_config->get('spam_reporting_sender_name', '');
        $reply_to = $context->site_config->get('spam_reporting_reply_to', '');

        $attachment_dir = $context->site_config->get('attachment_dir') ?: sys_get_temp_dir();
        $tmp_file = tempnam($attachment_dir, 'spamreport_');
        if (!$tmp_file) {
            if ($server_id !== false) {
                Hm_SMTP_List::del($server_id);
            }
            return new Hm_Spam_Report_Result(false, 'Unable to create attachment');
        }
        file_put_contents($tmp_file, (string) $payload->raw_message);

        $msg = new Hm_MIME_Msg($payload->to, $payload->subject, $payload->body, $from, false, '', '', '', $from_name, $reply_to);
        $msg->add_attachments(array(array(
            'filename' => $tmp_file,
            'type' => 'message/rfc822',
            'name' => 'original_message.eml',
            'no_encoding' => true
        )));
        $msg_content = $msg->get_mime_msg();
        $err = $mailbox->send_message($from, array($payload->to), $msg_content);
        @unlink($tmp_file);
        if ($server_id !== false) {
            Hm_SMTP_List::del($server_id);
        }
        if ($err) {
            return new Hm_Spam_Report_Result(false, 'Send failed');
        }
        return new Hm_Spam_Report_Result(true, 'Report sent');
    }
}
