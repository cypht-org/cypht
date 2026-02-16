<?php

/**
 * SpamCop email adapter: message/rfc822 attachment must be base64-encoded.
 * SpamCop rejects 7bit/no_encoding; this adapter uses Hm_MIME_Msg with base64.
 *
 * @package modules
 * @subpackage spam_reporting
 */

if (!defined('DEBUG_MODE')) { die(); }

class Hm_Spam_Report_SpamCop_Email_Target extends Hm_Spam_Report_Email_Target {

    protected $id = 'spamcop_email';
    protected $label = 'SpamCop';
    protected $platform_id = 'spamcop';

    public function id() {
        return $this->id;
    }

    public function label() {
        return $this->label;
    }

    public function platform_id() {
        return $this->platform_id;
    }

    /**
     * SpamCop-specific schema: submission_email (required), subject_prefix (optional).
     * @return array<string, array{type: string, label: string, required: bool}>
     */
    public function get_configuration_schema() {
        return array(
            'submission_email' => array('type' => 'email', 'label' => 'SpamCop submission email', 'required' => true),
            'label' => array('type' => 'string', 'label' => 'Label', 'required' => true),
            'subject_prefix' => array('type' => 'string', 'label' => 'Subject prefix', 'required' => false),
        );
    }

    /**
     * Resolve destination and subject prefix from instance config (SpamCop uses submission_email).
     * @param array $instance_config
     * @return array{to: string, label: string, subject_prefix: string}
     */
    private function resolve_spamcop_values(array $instance_config = array()) {
        $to = '';
        $label = $this->label;
        $prefix = 'Spam report';
        if (!empty($instance_config)) {
            if (isset($instance_config['submission_email']) && is_string($instance_config['submission_email'])) {
                $to = trim($instance_config['submission_email']);
            }
            if (isset($instance_config['label']) && is_string($instance_config['label'])) {
                $label = trim($instance_config['label']);
            }
            if (isset($instance_config['subject_prefix']) && is_string($instance_config['subject_prefix'])) {
                $prefix = trim($instance_config['subject_prefix']);
            }
        }
        return array('to' => $to, 'label' => $label, 'subject_prefix' => $prefix);
    }

    public function is_available(Hm_Spam_Report $report, $user_config, array $instance_config = array()) {
        $resolved = $this->resolve_spamcop_values($instance_config);
        if (!trim((string) $resolved['to'])) {
            return false;
        }
        $parsed = process_address_fld($resolved['to']);
        if (count($parsed) !== 1) {
            return false;
        }
        $destination = isset($parsed[0]['email']) && is_string($parsed[0]['email'])
            ? strtolower(trim($parsed[0]['email'])) : '';
        if ($destination === '') {
            return false;
        }
        $from_reply_to = function_exists('spam_reporting_message_from_reply_to_emails')
            ? spam_reporting_message_from_reply_to_emails($report) : array();
        if (in_array($destination, $from_reply_to, true)) {
            return false;
        }
        return true;
    }

    /**
     * Build payload: destination from submission_email, subject prefix, minimal body, attach original as base64 later.
     */
    public function build_payload(Hm_Spam_Report $report, array $user_input = array(), array $instance_config = array()) {
        $resolved = $this->resolve_spamcop_values($instance_config);
        $message = $report->get_parsed_message();
        $message_id = $message ? $message->getHeaderValue('Message-ID', '') : '';
        $subject = $message_id ? ($resolved['subject_prefix'] . ': ' . $message_id) : $resolved['subject_prefix'];
        $notes = '';
        if (array_key_exists('user_notes', $user_input) && trim((string) $user_input['user_notes'])) {
            $notes = "\r\n\r\nUser notes:\r\n" . trim((string) $user_input['user_notes']);
        }
        $body = "Spam report";
        if ($message_id) {
            $body .= "\r\n\r\nMessage-ID: " . $message_id;
        }
        $body .= "\r\n\r\nAttached: original message (message/rfc822)";
        $body .= $notes;
        return new Hm_Spam_Report_Payload($resolved['to'], $subject, $body, 'text/plain', array(), $report->raw_message);
    }

    /**
     * Deliver using SMTP; attach message/rfc822 with Content-Transfer-Encoding: base64 (no no_encoding).
     * Reuses parent flow but passes force_base64 so SpamCop accepts the attachment.
     */
    public function deliver($payload, $context = null) {
        if (!($payload instanceof Hm_Spam_Report_Payload)) {
            return new Hm_Spam_Report_Result(false, 'Invalid payload');
        }
        if (!($context instanceof Hm_Spam_Report_Delivery_Context)) {
            return new Hm_Spam_Report_Result(false, 'Missing delivery context');
        }
        $instance_config = ($context instanceof Hm_Spam_Report_Delivery_Context)
            ? $context->get_instance_config()
            : array();
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

        $msg = new Hm_MIME_Msg($payload->to, $payload->subject, '', $from, false, '', '', '', $from_name, $reply_to);
        $msg->set_primary_message_rfc822((string) $payload->raw_message, true);
        $msg_content = $msg->get_mime_msg();
        $err = $mailbox->send_message($from, array($payload->to), $msg_content);
        if ($server_id !== false) {
            Hm_SMTP_List::del($server_id);
        }
        if ($err) {
            return new Hm_Spam_Report_Result(false, 'Send failed');
        }
        return new Hm_Spam_Report_Result(true, 'Report sent');
    }
}
