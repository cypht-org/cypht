<?php

/**
 * Spam reporting handlers (Phase 1 stub)
 * @package modules
 * @subpackage spam_reporting
 */

if (!defined('DEBUG_MODE')) { die(); }

/**
 * Build a spam report preview (AJAX)
 * @subpackage spam_reporting/handler
 */
class Hm_Handler_spam_report_preview extends Hm_Handler_Module {
    public function process() {
        list($success, $form) = $this->process_form(array('list_path', 'uid'));
        if (!$success) {
            $this->out('spam_report_error', 'Invalid request');
            $this->out('spam_report_debug', array(
                'post_keys' => array_keys($this->request->post ?? array()),
                'post' => $this->request->post ?? array(),
            ));
            return;
        }

        list($server_id, $uid, $folder, $msg_id) = get_request_params($form);
        if ($server_id === NULL || $folder === NULL || $uid === NULL) {
            $this->out('spam_report_error', 'Unsupported message source');
            return;
        }

        $mailbox = Hm_IMAP_List::get_connected_mailbox($server_id, $this->cache);
        if (!$mailbox || !$mailbox->authed()) {
            $this->out('spam_report_error', 'Mailbox unavailable');
            return;
        }

        $report = spam_reporting_build_report($mailbox, $folder, $uid, array(
            'list_path' => $form['list_path']
        ));
        if (!$report) {
            $this->out('spam_report_error', 'Failed to parse message');
            $this->out('spam_report_debug', array(
                'list_path' => $form['list_path'],
                'uid' => $uid
            ));
            return;
        }
        $raw_headers = $report->get_raw_headers_string();
        $this->out('spam_report_debug', array(
            'raw_len' => is_string($report->raw_message) ? mb_strlen($report->raw_message) : 0,
            'headers_len' => is_string($raw_headers) ? mb_strlen($raw_headers) : 0,
            'body_text_len' => is_string($report->body_text) ? mb_strlen($report->body_text) : 0,
            'body_html_len' => is_string($report->body_html) ? mb_strlen($report->body_html) : 0,
            'message_class' => is_object($report->get_parsed_message()) ? get_class($report->get_parsed_message()) : ''
        ));

        $registry = spam_reporting_build_registry($this->config);
        $targets = array();
        foreach ($registry->all_targets() as $target) {
            if ($target->is_available($report, $this->user_config)) {
                $targets[] = array(
                    'id' => $target->id(),
                    'label' => $target->label(),
                    'capabilities' => $target->capabilities(),
                    'requirements' => $target->requirements()
                );
            }
        }

        $this->out('spam_report_targets', $targets);
        $this->out('spam_report_preview', array(
            'headers' => $raw_headers,
            'body_text' => $report->body_text ?? '',
            'body_html' => $report->body_html ?? ''
        ));
    }
}

/**
 * Send a spam report (AJAX)
 * @subpackage spam_reporting/handler
 */
class Hm_Handler_spam_report_send extends Hm_Handler_Module {
    public function process() {
        list($success, $form) = $this->process_form(array('list_path', 'uid', 'target_id'));
        if (!$success) {
            $this->out('spam_report_send_ok', false);
            $this->out('spam_report_send_message', 'Missing required fields');
            return;
        }
        $notes = '';
        if (array_key_exists('user_notes', $this->request->post)) {
            $notes = $this->request->post['user_notes'];
        }

        list($allowed, $retry_after) = spam_reporting_rate_limit($this->session, $this->config, false);
        if (!$allowed) {
            $this->out('spam_report_send_ok', false);
            $this->out('spam_report_send_message', 'Rate limit exceeded. Please try again later.');
            return;
        }

        list($server_id, $uid, $folder, $msg_id) = get_request_params($form);
        if ($server_id === NULL || $folder === NULL || $uid === NULL) {
            $this->out('spam_report_send_ok', false);
            $this->out('spam_report_send_message', 'Unsupported message source');
            return;
        }

        $mailbox = Hm_IMAP_List::get_connected_mailbox($server_id, $this->cache);
        if (!$mailbox || !$mailbox->authed()) {
            $this->out('spam_report_send_ok', false);
            $this->out('spam_report_send_message', 'Mailbox unavailable');
            return;
        }

        $report = spam_reporting_build_report($mailbox, $folder, $uid, array(
            'list_path' => $form['list_path']
        ));
        if (!$report) {
            $this->out('spam_report_send_ok', false);
            $this->out('spam_report_send_message', 'Failed to parse message');
            return;
        }

        $registry = spam_reporting_build_registry($this->config);
        $target = $registry->get($form['target_id']);
        if (!$target || !$target->is_available($report, $this->user_config)) {
            $this->out('spam_report_send_ok', false);
            $this->out('spam_report_send_message', 'Target unavailable');
            return;
        }

        $payload = $target->build_payload($report, array('user_notes' => $notes));
        $context = new Hm_Spam_Report_Delivery_Context($this->config, $this->user_config, $this->session);
        $result = $target->deliver($payload, $context);
        if (!$result || !($result instanceof Hm_Spam_Report_Result) || !$result->ok) {
            $msg = $result && $result instanceof Hm_Spam_Report_Result ? $result->message : 'Send failed';
            $this->out('spam_report_send_ok', false);
            $this->out('spam_report_send_message', $msg);
            return;
        }

        spam_reporting_rate_limit($this->session, $this->config, true);
        $user = $this->session->get('username', '');
        $msg_id = $form['list_path'] . ':' . $uid;
        Hm_Debug::add(sprintf('SPAM_REPORT user=%s target=%s msg=%s', $user, $form['target_id'], $msg_id), 'info');

        $this->out('spam_report_send_ok', true);
        $this->out('spam_report_send_message', $result->message);
    }
}
