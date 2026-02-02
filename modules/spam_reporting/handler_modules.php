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
            return;
        }

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
            'headers' => $report->get_raw_headers_string(),
            'body_text' => $report->body_text ?? '',
            'body_html' => $report->body_html ?? ''
        ));
    }
}
