<?php
require_once APP_PATH.'modules/report_spam/functions.php';
require_once APP_PATH.'modules/imap/functions.php';
require_once APP_PATH.'modules/imap/hm-imap.php';
/**
 * Report Spam modules
 * @package modules
 * @subpackage report_spam
 */

/**
 * Process spam reporting settings from the Report Spam section
 * @subpackage report_spam/handler
 */
class Hm_Handler_process_spam_report_settings extends Hm_Handler_Module {
    public function process() {
        list($success, $form) = $this->process_form(array('save_settings'));
        if (!$success) {
            return;
        }

        $new_settings = $this->get('new_user_settings', array());

        $set_setting = function($key, $value, $validator = null) use (&$new_settings) {
            if ($validator && !$validator($value)) {
                $new_settings[$key] = '';
            } else {
                $new_settings[$key] = $value;
            }
        };

        // Process SpamCop settings
        if (array_key_exists('spamcop_settings', $this->request->post)) {
            $spamcop = $this->request->post['spamcop_settings'];
            $new_settings['spamcop_enabled_setting'] = isset($spamcop['enabled']);
            $set_setting('spamcop_submission_email_setting', $spamcop['submission_email'] ?? '', function($v) {
                return filter_var($v, FILTER_VALIDATE_EMAIL);
            });
            $set_setting('spamcop_from_email_setting', $spamcop['from_email'] ?? '', function($v) {
                return filter_var($v, FILTER_VALIDATE_EMAIL);
            });
        }

        // Process APWG settings
        if (array_key_exists('apwg_settings', $this->request->post)) {
            $apwg = $this->request->post['apwg_settings'];
            $new_settings['apwg_enabled_setting'] = isset($apwg['enabled']);
            $set_setting('apwg_from_email_setting', $apwg['from_email'] ?? '', function($v) {
                return filter_var($v, FILTER_VALIDATE_EMAIL);
            });
        }

        // Process AbuseIPDB settings
        if (array_key_exists('abuseipdb_settings', $this->request->post)) {
            $abuseipdb = $this->request->post['abuseipdb_settings'];
            $new_settings['abuseipdb_enabled_setting'] = isset($abuseipdb['enabled']);
            
            // Handle API key
            $api_key = $abuseipdb['api_key'] ?? '';
            $api_key_was_set = isset($abuseipdb['api_key_set']) && $abuseipdb['api_key_set'] == '1';
            
            if (empty($api_key) && $api_key_was_set) {
                $original_key = $this->user_config->get('abuseipdb_api_key_setting', '');
                if (!empty($original_key)) {
                    $new_settings['abuseipdb_api_key_setting'] = $original_key;
                } else {
                    $new_settings['abuseipdb_api_key_setting'] = '';
                }
            } else {
                $set_setting('abuseipdb_api_key_setting', $api_key, function($v) {
                    return !empty($v) && strlen($v) >= 10 && strlen($v) <= 200;
                });
            }
        }
        
        $this->out('new_user_settings', $new_settings, false);
    }
}

/**
 * Report spam messages to external services
 * @subpackage report_spam/handler
 */
class Hm_Handler_report_spam extends Hm_Handler_Module {
    public function process() {
        list($success, $form) = $this->process_form(array('message_ids', 'spam_reasons'));
        if (!$success) {
            Hm_Msgs::add('Missing required parameters for spam reporting', 'warning');
            $this->out('spam_report_error', true);
            $this->out('spam_report_message', 'Missing required parameters');
            return;
        }

        $message_ids = $form['message_ids'];
        $reasons = is_array($form['spam_reasons']) ? $form['spam_reasons'] : array($form['spam_reasons']);

        $services_to_report = array();
        $service_names = array(
            'spamcop' => 'SpamCop',
            'apwg' => 'APWG',
            'abuseipdb' => 'AbuseIPDB'
        );

        if ($this->user_config->get('spamcop_enabled_setting', false)) {
            $services_to_report[] = 'spamcop';
        }

        if ($this->user_config->get('apwg_enabled_setting', false)) {
            $services_to_report[] = 'apwg';
        }

        if ($this->user_config->get('abuseipdb_enabled_setting', false)) {
            $services_to_report[] = 'abuseipdb';
        }

        if (empty($services_to_report)) {
            Hm_Msgs::add('No spam reporting services are enabled. Please enable at least one service in Settings.', 'warning');
            $this->out('spam_report_error', true);
            $this->out('spam_report_message', 'No spam reporting services are enabled');
            return;
        }

        $ids = process_imap_message_ids($message_ids);
        
        $total_messages = 0;
        foreach ($ids as $server_id => $folders) {
            foreach ($folders as $folder => $uids) {
                $total_messages += count($uids);
            }
        }
        
        $service_results = array();
        foreach ($services_to_report as $service) {
            $service_results[$service] = array(
                'success_count' => 0,
                'error_count' => 0,
                'errors' => array()
            );
        }
        
        $total_reported = 0;
        $total_errors = 0;
        $all_errors = array();

        // Ensure Hm_IMAP_List is initialized
        Hm_IMAP_List::init($this->user_config, $this->session);

        foreach ($ids as $server_id => $folders) {
            // Verify the server exists in the IMAP list
            $server_details = Hm_IMAP_List::dump($server_id, true);
            if (!$server_details) {
                $error_msg = sprintf('Server %s not found in configured servers', $server_id);
                $all_errors[] = $error_msg;
                $total_errors++;
                continue;
            }

            $mailbox = Hm_IMAP_List::get_connected_mailbox($server_id, $this->cache);
            if (!$mailbox || !$mailbox->authed()) {
                $error_msg = sprintf('Could not connect to server %s', $server_id);
                $all_errors[] = $error_msg;
                $total_errors++;
                continue;
            }

            foreach ($folders as $folder => $uids) {
                $folder_name = hex2bin($folder);
                foreach ($uids as $uid) {
                    $msg_source = $mailbox->get_message_content($folder_name, $uid);
                    if (!$msg_source) {
                        $error_msg = sprintf('Could not retrieve message %s from folder %s', $uid, $folder_name);
                        $all_errors[] = $error_msg;
                        $total_errors++;
                        continue;
                    }

                    // Report to each enabled service
                    $message_success_count = 0;
                    $message_error_count = 0;
                    $message_errors = array();

                    foreach ($services_to_report as $service) {
                        $function_name = 'report_spam_to_' . $service;
                        if (!function_exists($function_name)) {
                            $error_msg = sprintf('Reporting function for %s not found', $service_names[$service]);
                            $service_results[$service]['errors'][] = $error_msg;
                            $service_results[$service]['error_count']++;
                            $message_errors[] = sprintf('%s: %s', $service_names[$service], $error_msg);
                            $message_error_count++;
                            continue;
                        }

                        $imap_server_email = '';
                        $imap_server_details = Hm_IMAP_List::dump($server_id, true);
                        if ($imap_server_details && isset($imap_server_details['user'])) {
                            $imap_server_email = $imap_server_details['user'];
                        }

                        $result = call_user_func($function_name, $msg_source, $reasons, $this->user_config, $this->session, $imap_server_email);
                        if ($result['success']) {
                            $service_results[$service]['success_count']++;
                            $message_success_count++;
                        } else {
                            $error_msg = normalize_spam_report_error($result['error']);
                            $service_results[$service]['errors'][] = sprintf('Message %s: %s', $uid, $error_msg);
                            $service_results[$service]['error_count']++;
                            $message_errors[] = sprintf('%s: %s', $service_names[$service], $error_msg);
                            $message_error_count++;
                        }
                    }

                    if ($message_success_count > 0) {
                        $total_reported++;
                    }
                    if ($message_error_count > 0) {
                        $total_errors++;
                        if (!empty($message_errors)) {
                            $all_errors[] = sprintf('Message %s: %s', $uid, implode('; ', $message_errors));
                        }
                    }
                }
            }
        }

        $build_error_summary = function($errors, $max_show) {
            $summary = implode('; ', array_slice($errors, 0, $max_show));
            $remaining = count($errors) - $max_show;
            if ($remaining > 0) {
                $summary .= sprintf(' (%d more errors)', $remaining);
            }
            return $summary;
        };

        // Build service status summary
        $successful_services = array();
        $failed_services = array();
        foreach ($services_to_report as $service) {
            $service_name = $service_names[$service];
            if ($service_results[$service]['success_count'] > 0) {
                $successful_services[] = $service_name;
            }
            if ($service_results[$service]['error_count'] > 0) {
                $failed_services[$service_name] = $service_results[$service]['errors'];
            }
        }

        // Generate appropriate message based on results
        if ($total_errors > 0 && $total_reported == 0) {
            // All failed
            $error_summary = $build_error_summary($all_errors, 3);
            $msg = sprintf('Failed to report %d message(s) as spam. %s', $total_messages, $error_summary);
            Hm_Msgs::add($msg, 'danger');
            $this->out('spam_report_error', true);
            $this->out('spam_report_message', sprintf('Failed to report %d message(s)', $total_messages));
        } elseif ($total_errors > 0) {
            // Partial success - build service status message
            $error_summary = $build_error_summary($all_errors, 2);
            $service_status_parts = array();
            
            if (!empty($successful_services)) {
                $service_status_parts[] = implode(' and ', $successful_services);
            }
            
            if (!empty($failed_services)) {
                $failed_list = array_keys($failed_services);
                if (!empty($successful_services)) {
                    $service_status_parts[] = 'but ' . implode(' and ', $failed_list) . ' failed';
                } else {
                    $service_status_parts[] = implode(' and ', $failed_list) . ' failed';
                }
            }
            
            $service_status = implode(', ', $service_status_parts);
            $msg = sprintf('Reported %d message(s) successfully to %s. %s', $total_reported, $service_status, $error_summary);
            Hm_Msgs::add($msg, 'warning');
            $this->out('spam_report_error', false);
            $this->out('spam_report_message', sprintf('Reported %d message(s) successfully. %d failed.', $total_reported, $total_errors));
        } else {
            // All successful
            $services_list = implode(' and ', array_map(function($s) use ($service_names) { return $service_names[$s]; }, $services_to_report));
            $msg = sprintf('Successfully reported %d message(s) as spam to %s.', $total_reported, $services_list);
            
            //SpamCop-specific verification reminder
            if (in_array('spamcop', $services_to_report)) {
                $msg .= ' Please check your email for a SpamCop verification link and click it to complete the submission.';
            }
            
            Hm_Msgs::add($msg, 'success');
            $this->out('spam_report_error', false);
            $this->out('spam_report_message', sprintf('Successfully reported %d message(s) as spam.', $total_reported));
        }
        $this->out('spam_report_count', $total_reported);
    }
}

/**
 * Starts the Report Spam section on the settings page
 * @subpackage report_spam/output
 */
class Hm_Output_start_report_spam_settings extends Hm_Output_Module {
    protected function output() {
        return '<tr><td data-target=".report_spam_setting" colspan="2" class="settings_subtitle cursor-pointer border-bottom p-2">'.
            '<i class="bi bi-shield-exclamation fs-5 me-2"></i>'.
            $this->trans('Report Spam').'</td></tr>';
    }
}

/**
 * Option to enable/disable SpamCop reporting
 * @subpackage report_spam/output
 */
class Hm_Output_spamcop_enabled_setting extends Hm_Output_Module {
    protected function output() {
        $settings = $this->get('user_settings', array());
        $enabled = get_setting_value($settings, 'spamcop_enabled', false);
        $checked = $enabled ? ' checked="checked"' : '';
        $reset = $enabled ? '<span class="tooltip_restore" restore_aria_label="Restore default value"><i class="bi bi-arrow-counterclockwise refresh_list reset_default_value_checkbox"></i></span>' : '';
        
        return '<tr class="report_spam_setting"><td><label class="form-check-label" for="spamcop_enabled">'.
            '<strong>'.$this->trans('Enable SpamCop reporting').'</strong></label></td>'.
            '<td><input class="form-check-input" type="checkbox" '.$checked.' id="spamcop_enabled" name="spamcop_settings[enabled]" data-default-value="false" value="1" />'.$reset.'</td></tr>';
    }
}

/**
 * Option for SpamCop submission email address
 * @subpackage report_spam/output
 */
class Hm_Output_spamcop_submission_email_setting extends Hm_Output_Module {
    protected function output() {
        $settings = $this->get('user_settings', array());
        $email = get_setting_value($settings, 'spamcop_submission_email', '');
        $reset = !empty($email) ? '<span class="tooltip_restore" restore_aria_label="Restore default value"><i class="bi bi-arrow-counterclockwise refresh_list reset_default_value_input"></i></span>' : '';
        
        return '<tr class="report_spam_setting"><td><label for="spamcop_submission_email">'.
            $this->trans('SpamCop submission email').'</label></td>'.
            '<td class="d-flex"><input class="form-control form-control-sm" type="email" id="spamcop_submission_email" name="spamcop_settings[submission_email]" value="'.$this->html_safe($email).'" placeholder="submit.xxxxx@spam.spamcop.net" />'.$reset.'</td></tr>';
    }
}

/**
 * Option for SpamCop from email address
 * @subpackage report_spam/output
 */
class Hm_Output_spamcop_from_email_setting extends Hm_Output_Module {
    protected function output() {
        $settings = $this->get('user_settings', array());
        $email = get_setting_value($settings, 'spamcop_from_email', '');
        $reset = !empty($email) ? '<span class="tooltip_restore" restore_aria_label="Restore default value"><i class="bi bi-arrow-counterclockwise refresh_list reset_default_value_input"></i></span>' : '';
        
        return '<tr class="report_spam_setting"><td><label for="spamcop_from_email">'.
            $this->trans('From email address (optional)').'</label></td>'.
            '<td class="d-flex"><input class="form-control form-control-sm" type="email" id="spamcop_from_email" name="spamcop_settings[from_email]" value="'.$this->html_safe($email).'" placeholder="'.$this->trans('Uses your IMAP email if not set').'" />'.$reset.'</td></tr>';
    }
}

/**
 * Option to enable/disable APWG phishing reporting
 * @subpackage report_spam/output
 */
class Hm_Output_apwg_enabled_setting extends Hm_Output_Module {
    protected function output() {
        $settings = $this->get('user_settings', array());
        $enabled = get_setting_value($settings, 'apwg_enabled', false);
        $checked = $enabled ? ' checked="checked"' : '';
        $reset = $enabled ? '<span class="tooltip_restore" restore_aria_label="Restore default value"><i class="bi bi-arrow-counterclockwise refresh_list reset_default_value_checkbox"></i></span>' : '';
        
        return '<tr class="report_spam_setting"><td><label class="form-check-label" for="apwg_enabled">'.
            '<strong>'.$this->trans('Enable APWG phishing reporting').'</strong></label></td>'.
            '<td><input class="form-check-input" type="checkbox" '.$checked.' id="apwg_enabled" name="apwg_settings[enabled]" data-default-value="false" value="1" />'.$reset.'</td></tr>';
    }
}

/**
 * Option for APWG from email address
 * @subpackage report_spam/output
 */
class Hm_Output_apwg_from_email_setting extends Hm_Output_Module {
    protected function output() {
        $settings = $this->get('user_settings', array());
        $email = get_setting_value($settings, 'apwg_from_email', '');
        $reset = !empty($email) ? '<span class="tooltip_restore" restore_aria_label="Restore default value"><i class="bi bi-arrow-counterclockwise refresh_list reset_default_value_input"></i></span>' : '';
        
        return '<tr class="report_spam_setting"><td><label for="apwg_from_email">'.
            $this->trans('From email address (optional)').'</label></td>'.
            '<td class="d-flex"><input class="form-control form-control-sm" type="email" id="apwg_from_email" name="apwg_settings[from_email]" value="'.$this->html_safe($email).'" placeholder="'.$this->trans('Uses your IMAP email if not set').'" />'.$reset.'</td></tr>';
    }
}

/**
 * Option to enable/disable AbuseIPDB reporting
 * @subpackage report_spam/output
 */
class Hm_Output_abuseipdb_enabled_setting extends Hm_Output_Module {
    protected function output() {
        $settings = $this->get('user_settings', array());
        $enabled = get_setting_value($settings, 'abuseipdb_enabled', false);
        $checked = $enabled ? ' checked="checked"' : '';
        $reset = $enabled ? '<span class="tooltip_restore" restore_aria_label="Restore default value"><i class="bi bi-arrow-counterclockwise refresh_list reset_default_value_checkbox"></i></span>' : '';
        
        return '<tr class="report_spam_setting"><td><label class="form-check-label" for="abuseipdb_enabled">'.
            '<strong>'.$this->trans('Enable AbuseIPDB reporting').'</strong></label></td>'.
            '<td><input class="form-check-input" type="checkbox" '.$checked.' id="abuseipdb_enabled" name="abuseipdb_settings[enabled]" data-default-value="false" value="1" />'.$reset.'</td></tr>';
    }
}

/**
 * Option for AbuseIPDB API key
 * @subpackage report_spam/output
 */
class Hm_Output_abuseipdb_api_key_setting extends Hm_Output_Module {
    protected function output() {
        $settings = $this->get('user_settings', array());
        $api_key = get_setting_value($settings, 'abuseipdb_api_key', '');
        
        // Mask API key if it exists - show empty field with indicator
        // The handler will preserve the original key if empty value is submitted
        $display_value = '';
        $placeholder = $this->trans('Your AbuseIPDB API key');
        
        if (!empty($api_key)) {
            $placeholder = $this->trans('API key is set (••••••••) - enter new value to change');
        }
        
        $reset = !empty($api_key) ? '<span class="tooltip_restore" restore_aria_label="Restore default value"><i class="bi bi-arrow-counterclockwise refresh_list reset_default_value_input"></i></span>' : '';
        
        // Add a hidden field to track if API key was originally set
        // This helps the handler know to preserve the key if field is left empty
        $hidden_field = !empty($api_key) ? '<input type="hidden" name="abuseipdb_settings[api_key_set]" value="1" />' : '';
        
        return '<tr class="report_spam_setting"><td><label for="abuseipdb_api_key">'.
            $this->trans('AbuseIPDB API Key').'</label></td>'.
            '<td class="d-flex">'.$hidden_field.
            '<input class="form-control form-control-sm" type="password" id="abuseipdb_api_key" name="abuseipdb_settings[api_key]" value="'.$this->html_safe($display_value).'" placeholder="'.$placeholder.'" autocomplete="off" />'.$reset.'</td></tr>';
    }
}

/**
 * Report Spam modal output
 * Outputs after the core modals
 * @subpackage report_spam/output
 */
// class Hm_Output_report_spam_modal extends Hm_Output_Module {
//     /**
//      * Outputs the Report Spam modal
//      */
//     protected function output() {
//         // Report Spam Modal
//         $report_spam_modal = '<div class="modal fade" id="reportSpamModal" tabindex="-1" aria-labelledby="reportSpamModalLabel" aria-hidden="true">';
//         $report_spam_modal .= '<div class="modal-dialog">';
//         $report_spam_modal .= '<div class="modal-content">';
//         $report_spam_modal .= '<div class="modal-header">';
//         $report_spam_modal .= '<h5 class="modal-title" id="reportSpamModalLabel">'.$this->trans('Report Spam').'</h5>';
//         $report_spam_modal .= '<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>';
//         $report_spam_modal .= '</div>';
//         $report_spam_modal .= '<div class="modal-body">';
//         $report_spam_modal .= '<p>'.$this->trans('Please tell us why you\'re reporting this email:').'</p>';
//         $report_spam_modal .= '<form id="reportSpamForm">';
//         $report_spam_modal .= '<div class="mb-3">';
//         $report_spam_modal .= '<label for="spam_reason_select" class="form-label">'.$this->trans('Select one or more reasons:').'</label>';
//         $report_spam_modal .= '<select class="form-select" id="spam_reason_select" name="spam_reason[]" multiple size="7">';
//         $report_spam_modal .= '<option value="unsolicited">'.$this->trans('Unsolicited / Spam').'</option>';
//         $report_spam_modal .= '<option value="phishing">'.$this->trans('Phishing or scam attempt').'</option>';
//         $report_spam_modal .= '<option value="malicious">'.$this->trans('Malicious or harmful content').'</option>';
//         $report_spam_modal .= '<option value="advertising">'.$this->trans('Advertising / Promotional').'</option>';
//         $report_spam_modal .= '<option value="offensive">'.$this->trans('Offensive or inappropriate').'</option>';
//         $report_spam_modal .= '<option value="wrong_recipient">'.$this->trans('Sent to the wrong recipient').'</option>';
//         $report_spam_modal .= '<option value="other">'.$this->trans('Other – please specify').'</option>';
//         $report_spam_modal .= '</select>';
//         $report_spam_modal .= '<small class="form-text text-muted">'.$this->trans('Hold Ctrl (or Cmd on Mac) to select multiple options.').'</small>';
//         $report_spam_modal .= '</div>';
//         $report_spam_modal .= '<div class="mb-3" id="spam_reason_other_input" style="display: none;">';
//         $report_spam_modal .= '<label for="spam_reason_other_text" class="form-label">'.$this->trans('Please specify:').'</label>';
//         $report_spam_modal .= '<input type="text" class="form-control" id="spam_reason_other_text" placeholder="'.$this->trans('Please specify').'">';
//         $report_spam_modal .= '</div>';
//         $report_spam_modal .= '</form>';
//         $report_spam_modal .= '</div>';
//         $report_spam_modal .= '<div class="modal-footer">';
//         $report_spam_modal .= '<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">'.$this->trans('Cancel').'</button>';
//         $report_spam_modal .= '<button type="button" class="btn btn-warning" id="confirm_report_spam">'.$this->trans('Report as Spam').'</button>';
//         $report_spam_modal .= '</div>';
//         $report_spam_modal .= '</div>';
//         $report_spam_modal .= '</div>';
//         $report_spam_modal .= '</div>';

//         if ($this->exists('modals')) {
//             $this->concat('modals', $report_spam_modal);
//             return '';
//         }

//         return $report_spam_modal;
//     }
// }

