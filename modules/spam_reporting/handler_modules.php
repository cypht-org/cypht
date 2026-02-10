<?php

/**
 * Spam reporting handlers (Phase 1 stub)
 * @package modules
 * @subpackage spam_reporting
 */

if (!defined('DEBUG_MODE')) { die(); }

/**
 * Process and output spam report user settings (General Settings → Spam Reporting)
 * @subpackage spam_reporting/handler
 */
class Hm_Handler_process_spam_report_settings extends Hm_Handler_Module {
    public function process() {
        $available = spam_reporting_get_available_platforms_for_settings($this->config);
        $this->out('spam_reporting_available_platforms', $available);

        list($success, $form) = $this->process_form(array('save_settings'));
        $new_settings = $this->get('new_user_settings', array());
        $settings = $this->get('user_settings', array());

        if ($success) {
            $enabled = array_key_exists('spam_reporting_enabled', $this->request->post)
                && $this->request->post['spam_reporting_enabled'];
            $allowed = array();
            foreach ($available as $p) {
                $key = 'spam_reporting_platform_' . $p['platform_id'];
                if (array_key_exists($key, $this->request->post) && $this->request->post[$key]) {
                    $allowed[] = $p['platform_id'];
                }
            }
            $new_settings['spam_reporting_enabled_setting'] = (bool) $enabled;
            $new_settings['spam_reporting_allowed_platforms_setting'] = $allowed;
            $settings['spam_reporting_enabled'] = $enabled;
            $settings['spam_reporting_allowed_platforms'] = $allowed;
        } else {
            $settings['spam_reporting_enabled'] = $this->user_config->get('spam_reporting_enabled_setting', false);
            $settings['spam_reporting_allowed_platforms'] = $this->user_config->get('spam_reporting_allowed_platforms_setting', array());
            if (!is_array($settings['spam_reporting_allowed_platforms'])) {
                $settings['spam_reporting_allowed_platforms'] = array();
            }
        }

        $this->out('new_user_settings', $new_settings, false);
        $this->out('user_settings', $settings, false);
    }
}

/**
 * Build a spam report preview (AJAX)
 * @subpackage spam_reporting/handler
 */
class Hm_Handler_spam_report_preview extends Hm_Handler_Module {
    public function process() {
        $post = $this->request->post ?? array();
        $debug = array(
            'step' => 'start',
            'post_keys' => array_keys($post),
            'list_path_in_post' => isset($post['list_path']) ? $post['list_path'] : '(missing)',
            'uid_in_post' => isset($post['uid']) ? $post['uid'] : '(missing)',
        );

        list($success, $form) = $this->process_form(array('list_path', 'uid'));
        if (!$success) {
            $debug['step'] = 'process_form_failed';
            $debug['form_received'] = $form;
            $debug['form_count'] = count($form);
            $this->out('spam_report_error', 'Invalid request');
            $this->out('spam_report_debug', $debug);
            return;
        }

        $debug['step'] = 'process_form_ok';
        $debug['form'] = $form;

        list($server_id, $uid, $folder, $msg_id) = get_request_params($form);
        $debug['get_request_params'] = array(
            'server_id' => $server_id,
            'uid' => $uid,
            'folder' => $folder ? '(set)' : null,
            'msg_id' => $msg_id,
        );

        if ($server_id === NULL || $folder === NULL || $uid === NULL) {
            $debug['step'] = 'get_request_params_failed';
            $this->out('spam_report_error', 'Unsupported message source');
            $this->out('spam_report_debug', $debug);
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
            $debug['step'] = 'build_report_failed';
            $this->out('spam_report_error', 'Failed to parse message');
            $this->out('spam_report_debug', $debug);
            return;
        }
        $raw_headers = $report->get_raw_headers_string();
        $debug['step'] = 'success';
        $debug['raw_len'] = is_string($report->raw_message) ? mb_strlen($report->raw_message) : 0;
        $debug['headers_len'] = is_string($raw_headers) ? mb_strlen($raw_headers) : 0;
        $debug['body_text_len'] = is_string($report->body_text) ? mb_strlen($report->body_text) : 0;
        $debug['body_html_len'] = is_string($report->body_html) ? mb_strlen($report->body_html) : 0;
        $debug['message_class'] = is_object($report->get_parsed_message()) ? get_class($report->get_parsed_message()) : '';

        $registry = spam_reporting_build_registry($this->config);
        $targets = array();
        foreach ($registry->all_targets() as $target) {
            if ($target->is_available($report, $this->user_config)) {
                $t = array(
                    'id' => $target->id(),
                    'label' => $target->label(),
                    'platform_id' => $target->platform_id(),
                    'capabilities' => $target->capabilities(),
                    'requirements' => $target->requirements()
                );
                if (method_exists($target, 'is_api_target') && $target->is_api_target()) {
                    $t['is_api_target'] = true;
                    $t['api_service_name'] = method_exists($target, 'get_api_service_name')
                        ? $target->get_api_service_name() : '';
                }
                $targets[] = $t;
            }
        }
        $targets = spam_reporting_filter_targets_by_user_settings($targets, $this->user_config);

        $message = $report->get_parsed_message();
        $mappings = spam_reporting_load_provider_mapping($this->config);
        $detected = $message ? spam_reporting_detect_providers($message, $mappings) : array();
        $suggested_ids = spam_reporting_suggested_target_ids($detected, $targets);

        $targets_ordered = array();
        $by_id = array();
        foreach ($targets as $t) {
            $by_id[$t['id']] = $t;
        }
        foreach ($suggested_ids as $tid) {
            if (isset($by_id[$tid])) {
                $targets_ordered[] = $by_id[$tid];
                unset($by_id[$tid]);
            }
        }
        foreach ($by_id as $t) {
            $targets_ordered[] = $t;
        }

        $suggestion = array(
            'suggested_target_ids' => $suggested_ids,
            'explanation' => '',
            'self_report_note' => ''
        );
        if (!empty($detected)) {
            $names = array_map(function ($d) {
                return $d['provider_name'];
            }, $detected);
            $suggestion['explanation'] = 'Suggested because the message appears to come from ' . implode(' or ', array_unique($names)) . '.';
        } else {
            $suggestion['explanation'] = 'No specific provider detected; you can choose any reporting platform.';
        }
        list($server_id, , , ) = get_request_params($form);
        $user_provider = spam_reporting_detect_user_mailbox_provider($server_id, $this->config);
        if ($user_provider && !empty($detected)) {
            $match = false;
            foreach ($detected as $d) {
                if ($d['provider_id'] === $user_provider) {
                    $match = true;
                    break;
                }
            }
            if ($match) {
                $suggestion['self_report_note'] = 'This message appears to originate from the same provider as your mailbox.';
            }
        }

        $this->out('spam_report_targets', $targets_ordered);
        $this->out('spam_report_suggestion', $suggestion);
        $this->out('spam_report_platforms', spam_reporting_load_platform_catalog($this->config));
        $preview = array(
            'headers' => $raw_headers,
            'body_text' => $report->body_text ?? '',
            'body_html' => $report->body_html ?? ''
        );
        $debug['preview_keys'] = array_keys($preview);
        $debug['preview_headers_len'] = mb_strlen($preview['headers']);
        $debug['preview_body_text_len'] = mb_strlen($preview['body_text']);
        $this->out('spam_report_debug', $debug);
        $this->out('spam_report_preview', $preview);
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
        $enabled = $this->user_config->get('spam_reporting_enabled_setting', false);
        $allowed = $this->user_config->get('spam_reporting_allowed_platforms_setting', array());
        if (!$enabled || !is_array($allowed) || !in_array($target->platform_id(), $allowed, true)) {
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
