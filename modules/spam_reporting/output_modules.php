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
 * Dynamic platform list from targets + catalog.
 * @subpackage spam_reporting/output
 */
class Hm_Output_spam_report_settings_section extends Hm_Output_Module {
    protected function output() {
        $adapter_types = $this->get('spam_reporting_adapter_types', array());
        if (empty($adapter_types)) {
            return '';
        }
        $configs_for_ui = $this->get('spam_reporting_configs_for_ui', array());
        $configs_json = str_replace('</script>', '<\/script>', json_encode($configs_for_ui));
        $adapter_types_json = str_replace('</script>', '<\/script>', json_encode($adapter_types));

        $res = '<tr><td data-target=".spam_reporting_setting" colspan="2" class="settings_subtitle cursor-pointer border-bottom p-2">'.
            '<i class="bi bi-shield-exclamation fs-5 me-2"></i>'.
            $this->trans('Spam Reporting').'</td></tr>';
        $res .= '<tr class="spam_reporting_setting"><td class="d-block d-md-table-cell" colspan="2">';

        $res .= '<div class="spam-reporting-activation mb-3">';
        $settings = $this->get('user_settings', array());
        $enabled = !empty($settings['spam_reporting_enabled_setting']);
        $res .= '<div class="d-flex align-items-center">';
        $res .= '<input type="checkbox" class="form-check-input me-2" id="spam_reporting_enabled" name="spam_reporting_enabled" value="1" '.($enabled ? 'checked' : '').'>';
        $res .= '<label for="spam_reporting_enabled">'.$this->trans('Enable external spam reporting').'</label>';
        $res .= '</div></div>';

        $res .= '<div class="spam-reporting-destinations mt-3">';
        $res .= '<input type="hidden" name="spam_reporting_target_configurations" id="spam_reporting_target_configurations" value="" />';
        $res .= '<script type="application/json" id="spam_reporting_configs_data">'.$configs_json.'</script>';
        $res .= '<script type="application/json" id="spam_reporting_adapter_types_data">'.$adapter_types_json.'</script>';
        $res .= '<div id="spam_reporting_service_cards" class="spam-reporting-service-cards"></div>';
        $res .= '</div>';

        $res .= '<div class="modal fade" id="spam_reporting_config_modal" tabindex="-1" aria-hidden="true">';
        $res .= '<div class="modal-dialog"><div class="modal-content">';
        $res .= '<div class="modal-header"><h5 class="modal-title" id="spam_reporting_config_modal_title">'.$this->trans('Configure').'</h5>';
        $res .= '<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="'.$this->trans('Close').'"></button></div>';
        $res .= '<div class="modal-body" id="spam_reporting_config_modal_body"></div>';
        $res .= '<div class="modal-footer">';
        $res .= '<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">'.$this->trans('Cancel').'</button>';
        $res .= '<button type="button" class="btn btn-primary" id="spam_reporting_config_modal_save">'.$this->trans('Save').'</button>';
        $res .= '</div></div></div></div>';

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
