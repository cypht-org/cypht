<?php

/**
 * Spam reporting outputs
 * @package modules
 * @subpackage spam_reporting
 */

if (!defined('DEBUG_MODE')) { die(); }

/**
 * Adds a "Report spam" action to message view controls
 * Hidden when spam_reporting_enabled_setting is false.
 * @subpackage spam_reporting/output
 */
class Hm_Output_spam_report_action extends Hm_Output_Module {
    protected function output() {
        $settings = $this->get('user_settings', array());
        if (empty($settings['spam_reporting_enabled_setting'])) {
            return;
        }
        $attrs = 'class="spam_report_action hlink text-decoration-none btn btn-sm btn-outline-danger" href="#"';
        $uid = $this->get('msg_text_uid');
        $listPath = $this->get('msg_list_path');
        if ($uid !== null && $listPath !== null) {
            $attrs .= ' data-uid="' . $this->html_safe($uid) . '" data-list-path="' . $this->html_safe($listPath) . '"';
        }
        $button = '<a ' . $attrs . '>' . $this->trans('Report spam') . '</a>';
        $this->concat('message_actions_extra', $button);
    }
}

/**
 * Modal UI for spam reporting
 * @subpackage spam_reporting/output
 */
class Hm_Output_spam_report_modal extends Hm_Output_Module {
    protected function output() {
        if ($this->format !== 'HTML5') {
            return;
        }
        return spam_reporting_modal_markup([$this, 'trans']);
    }
}

/**
 * Inline modal injection for AJAX message content
 * @subpackage spam_reporting/output
 */
class Hm_Output_spam_report_modal_inline extends Hm_Output_Module {
    protected function output() {
        if ($this->format !== 'JSON') {
            return;
        }
        $this->concat('msg_headers', spam_reporting_modal_markup([$this, 'trans']));
    }
}

/**
 * Output spam report preview JSON
 * @subpackage spam_reporting/output
 */
class Hm_Output_spam_report_preview extends Hm_Output_Module {
    protected function output() {
        $this->out('spam_report_targets', $this->get('spam_report_targets', array()));
        $this->out('spam_report_suggestion', $this->get('spam_report_suggestion', array()));
        $this->out('spam_report_platforms', $this->get('spam_report_platforms', array()));
        $this->out('spam_report_preview', $this->get('spam_report_preview', array()));
        if ($this->get('spam_report_error')) {
            $this->out('spam_report_error', $this->get('spam_report_error'));
        }
        if ($this->get('spam_report_debug')) {
            $this->out('spam_report_debug', $this->get('spam_report_debug'));
        }
    }
}

/**
 * Spam Reporting section in General Settings
 * Simple form: enable checkbox + one label/input per service (AbuseIPDB API key, SpamCop fields, custom email textarea).
 * @subpackage spam_reporting/output
 */
class Hm_Output_spam_report_settings_section extends Hm_Output_Module {
    protected function output() {
        $adapter_types = $this->get('spam_reporting_adapter_types', array());
        if (empty($adapter_types)) {
            return '';
        }
        $configs_for_ui = $this->get('spam_reporting_configs_for_ui', array());
        $settings = $this->get('user_settings', array());
        $enabled = !empty($settings['spam_reporting_enabled_setting']);

        $adapter_ids_available = array();
        foreach ($adapter_types as $at) {
            $aid = isset($at['adapter_id']) ? $at['adapter_id'] : '';
            if ($aid !== '') {
                $adapter_ids_available[$aid] = true;
            }
        }

        $res = '<tr><td data-target=".spam_reporting_setting" colspan="2" class="settings_subtitle cursor-pointer border-bottom p-2">'.
            '<i class="bi bi-shield-exclamation fs-5 me-2"></i>'.
            $this->trans('Spam Reporting').'</td></tr>';
        $res .= '<tr class="spam_reporting_setting"><td class="d-block d-md-table-cell" colspan="2">';

        $res .= '<div class="spam-reporting-activation mb-3">';
        $res .= '<div class="d-flex align-items-center">';
        $res .= '<input type="checkbox" class="form-check-input me-2" id="spam_reporting_enabled" name="spam_reporting_enabled" value="1" '.($enabled ? 'checked' : '').'>';
        $res .= '<label for="spam_reporting_enabled">'.$this->trans('Enable external spam reporting').'</label>';
        $res .= '</div></div>';

        $res .= '<div class="spam-reporting-destinations mt-3">';

        if (isset($adapter_ids_available['abuseipdb'])) {
            $abuseipdb_config = null;
            foreach ($configs_for_ui as $c) {
                if (isset($c['adapter_id']) && $c['adapter_id'] === 'abuseipdb') {
                    $abuseipdb_config = $c;
                    break;
                }
            }
            $placeholder = $abuseipdb_config ? $this->trans('Leave blank to keep current') : '';
            $res .= '<div class="mb-3">';
            $res .= '<label for="spam_reporting_abuseipdb_api_key" class="form-label">'.$this->trans('AbuseIPDB API Key').'</label>';
            $res .= '<input type="password" class="form-control form-control-sm" id="spam_reporting_abuseipdb_api_key" name="spam_reporting_abuseipdb_api_key" value="" placeholder="'.$this->html_safe($placeholder).'" autocomplete="new-password" />';
            $res .= '<small class="text-muted">'.$this->trans('Report IP addresses of spam senders. Leave empty to disable.').'</small>';
            $res .= '</div>';
        }

        if (isset($adapter_ids_available['spamcop_email'])) {
            $spamcop_config = null;
            foreach ($configs_for_ui as $c) {
                if (isset($c['adapter_id']) && $c['adapter_id'] === 'spamcop_email') {
                    $spamcop_config = $c;
                    break;
                }
            }
            $label_val = $spamcop_config && isset($spamcop_config['label']) ? $spamcop_config['label'] : 'SpamCop';
            $to_val = '';
            if ($spamcop_config) {
                if (isset($spamcop_config['settings_form']['submission_email'])) {
                    $to_val = $spamcop_config['settings_form']['submission_email'];
                } elseif (isset($spamcop_config['settings_safe']['submission_email'])) {
                    $to_val = $spamcop_config['settings_safe']['submission_email'];
                }
            }
            $res .= '<div class="mb-3">';
            $res .= '<label for="spam_reporting_spamcop_label" class="form-label">'.$this->trans('SpamCop label').'</label>';
            $res .= '<input type="text" class="form-control form-control-sm" id="spam_reporting_spamcop_label" name="spam_reporting_spamcop_label" value="'.$this->html_safe($label_val).'" />';
            $res .= '<label for="spam_reporting_spamcop_submission_email" class="form-label mt-2">'.$this->trans('SpamCop submission email').'</label>';
            $res .= '<input type="email" class="form-control form-control-sm" id="spam_reporting_spamcop_submission_email" name="spam_reporting_spamcop_submission_email" value="'.$this->html_safe($to_val).'" />';
            $res .= '<small class="text-muted">'.$this->trans('Send full message reports to SpamCop. Leave submission email empty to disable.').'</small>';
            $res .= '</div>';
        }

        if (isset($adapter_ids_available['email_target'])) {
            $custom_lines = array();
            foreach ($configs_for_ui as $c) {
                if (isset($c['adapter_id']) && $c['adapter_id'] === 'email_target') {
                    $label = isset($c['label']) ? $c['label'] : '';
                    $to = '';
                    if (isset($c['settings_form']['to'])) {
                        $to = $c['settings_form']['to'];
                    } elseif (isset($c['settings_safe']['to'])) {
                        $to = $c['settings_safe']['to'];
                    }
                    if ($label !== '' || $to !== '') {
                        $custom_lines[] = $label . ':' . $to;
                    }
                }
            }
            $custom_textarea_val = implode("\n", $custom_lines);
            $res .= '<div class="mb-3">';
            $res .= '<label for="spam_reporting_custom_emails" class="form-label">'.$this->trans('Custom email destinations').'</label>';
            $res .= '<textarea class="form-control form-control-sm" id="spam_reporting_custom_emails" name="spam_reporting_custom_emails" rows="4" placeholder="'.$this->html_safe($this->trans('One per line: Label: email@example.com')).'">'.$this->html_safe($custom_textarea_val).'</textarea>';
            $res .= '<small class="text-muted">'.$this->trans('One line per destination. Format: Label: email@example.com').'</small>';
            $res .= '</div>';
        }

        $res .= '</div>';
        $res .= '</td></tr>';
        return $res;
    }
}

/**
 * Output spam report send JSON
 * @subpackage spam_reporting/output
 */
class Hm_Output_spam_report_send extends Hm_Output_Module {
    protected function output() {
        $this->out('spam_report_send_ok', $this->get('spam_report_send_ok', false));
        $this->out('spam_report_send_message', $this->get('spam_report_send_message', ''));
    }
}
