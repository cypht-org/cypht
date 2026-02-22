<?php

/**
 * Generic email spam report target
 * @package modules
 * @subpackage spam_reporting
 */

if (!defined('DEBUG_MODE')) { die(); }

class Hm_Spam_Report_Email_Target extends Hm_Spam_Report_Target_Abstract {
    protected $id = 'email_target';
    protected $label = 'Email (Spamcop, Gmail, etc.)';
    protected $platform_id = '';
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
        if (array_key_exists('platform_id', $config) && is_string($config['platform_id'])) {
            $this->platform_id = $config['platform_id'];
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

    public function platform_id() {
        return $this->platform_id;
    }

    public function capabilities() {
        return array('email');
    }

    public function requirements() {
        return array('raw_headers', 'body_text');
    }

    /**
     * Schema for user-provided instance config.
     * @return array<string, array{type: string, label: string, required: bool}>
     */
    public function get_configuration_schema() {
        return array(
            'to' => array('type' => 'email', 'label' => 'Destination email', 'required' => true),
            'label' => array('type' => 'string', 'label' => 'Label', 'required' => true),
            'subject_prefix' => array('type' => 'string', 'label' => 'Subject prefix', 'required' => false),
        );
    }

    /**
     * Resolve destination/label/prefix: instance_config when non-empty, else configured values.
     * @param array $instance_config
     * @return array{to: string, label: string, subject_prefix: string}
     */
    private function resolve_instance_values(array $instance_config = array()) {
        $to = $this->to;
        $label = $this->label;
        $prefix = $this->subject_prefix;
        if (!empty($instance_config)) {
            if (isset($instance_config['to']) && is_string($instance_config['to'])) {
                $to = trim($instance_config['to']);
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
        $resolved = $this->resolve_instance_values($instance_config);
        if (!trim((string) $resolved['to'])) {
            return false;
        }
        $parsed = process_address_fld($resolved['to']);
        if (count($parsed) !== 1) {
            return false;
        }
        // Forbid using message From/Reply-To as destination
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

    public function build_payload(Hm_Spam_Report $report, array $user_input = array(), array $instance_config = array()) {
        $resolved = $this->resolve_instance_values($instance_config);
        $message = $report->get_parsed_message();
        $message_id = $message ? $message->getHeaderValue('Message-ID', '') : '';
        $subject = $message_id ? ($resolved['subject_prefix'] . ': ' . $message_id) : $resolved['subject_prefix'];
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
        return new Hm_Spam_Report_Payload($resolved['to'], $subject, $body, 'text/plain', array(), $report->raw_message);
    }

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
