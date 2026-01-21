<?php
/**
 * Vendor detection modules
 * @package modules
 * @subpackage vendor_detection
 */
if (!defined('DEBUG_MODE')) { die(); }

require_once APP_PATH.'modules/vendor_detection/functions.php';

/**
 * @subpackage vendor_detection/handler
 */
class Hm_Handler_process_vendor_detection_setting extends Hm_Handler_Module {
    public function process() {
        function vendor_detection_ui_callback($val) {
            return $val ? true : false;
        }
        process_site_setting('vendor_detection_ui', $this, 'vendor_detection_ui_callback', false, true);
    }
}

/**
 * @subpackage vendor_detection/handler
 */
class Hm_Handler_process_data_request_setting extends Hm_Handler_Module {
    public function process() {
        function data_request_ui_callback($val) {
            return $val ? true : false;
        }
        process_site_setting('data_request_ui', $this, 'data_request_ui_callback', false, true);
    }
}

/**
 * @subpackage vendor_detection/output
 */
class Hm_Output_vendor_detection_setting extends Hm_Output_Module {
    protected function output() {
        $settings = $this->get('user_settings', array());
        $enabled = $settings['vendor_detection_ui'] ?? false;
        $checked = $enabled ? ' checked="checked"' : '';
        return '<tr class="general_setting"><td><label class="form-check-label" for="vendor_detection_ui">'.
            $this->trans('Show vendor detection label').'</label></td>'.
            '<td><input class="form-check-input" type="checkbox" id="vendor_detection_ui" name="vendor_detection_ui" '.
            'data-default-value="false" value="1"'.$checked.' /></td></tr>';
    }
}

/**
 * @subpackage vendor_detection/output
 */
class Hm_Output_data_request_setting extends Hm_Output_Module {
    protected function output() {
        $settings = $this->get('user_settings', array());
        $enabled = $settings['data_request_ui'] ?? false;
        $checked = $enabled ? ' checked="checked"' : '';
        return '<tr class="general_setting"><td><label class="form-check-label" for="data_request_ui">'.
            $this->trans('Show data request actions').'</label></td>'.
            '<td><input class="form-check-input" type="checkbox" id="data_request_ui" name="data_request_ui" '.
            'data-default-value="false" value="1"'.$checked.' /></td></tr>';
    }
}

/**
 * @subpackage vendor_detection/output
 */
class Hm_Output_vendor_detection_label extends Hm_Output_Module {
    protected function output() {
        $vendor_detection = $this->get('vendor_detection', array());
        $user_config = $this->get('user_config');
        $vendor_label_enabled = false;
        if ($user_config && method_exists($user_config, 'get')) {
            $vendor_label_enabled = (bool) $user_config->get('vendor_detection_ui_setting', false);
        }
        $site_config = $this->get('site_config');
        if ($site_config && method_exists($site_config, 'get')) {
            $override = $site_config->get('vendor_detection_ui_enabled', null);
            if ($override !== null) {
                $vendor_label_enabled = (bool) $override;
            }
        }
        $vendor_name_raw = $vendor_detection['vendor_name'] ?? '';
        $has_vendor_label = $vendor_label_enabled && $vendor_name_raw;

        $data_request_match = $this->get('data_request_match', array());
        $data_request_enabled = false;
        if ($user_config && method_exists($user_config, 'get')) {
            $data_request_enabled = (bool) $user_config->get('data_request_ui_setting', false);
        }
        if ($site_config && method_exists($site_config, 'get')) {
            $override = $site_config->get('data_request_ui_enabled', null);
            if ($override !== null) {
                $data_request_enabled = (bool) $override;
            }
        }
        $has_data_request = $data_request_enabled && !empty($data_request_match['datarequests_url']);
        if (!$has_vendor_label && !$has_data_request) {
            return '';
        }

        $uid = preg_replace('/[^a-zA-Z0-9_-]+/', '-', (string) $this->get('msg_text_uid', '0'));
        $vendor_label_html = '';
        $evidence_html = '';
        if ($has_vendor_label) {
            $confidence = $vendor_detection['confidence'] ?? '';
            $confidence_map = array(
                'high' => $this->trans('High confidence'),
                'medium' => $this->trans('Medium confidence'),
                'low' => $this->trans('Low confidence')
            );
            $confidence_label = $confidence_map[$confidence] ?? '';
            $label = $this->trans('Sent via');
            $vendor_name = $this->html_safe($vendor_name_raw);
            $panel_id = 'vendor-detection-evidence-'.$uid;

            $evidence = $vendor_detection['evidence'] ?? array();
            if (!empty($evidence)) {
                $label_map = array(
                    'dkim_domain' => $this->trans('DKIM domain'),
                    'header_name' => $this->trans('Header match'),
                    'header_prefix' => $this->trans('Header match'),
                    'return_path_domain' => $this->trans('Return-Path domain'),
                    'received_domain' => $this->trans('Received domain'),
                    'from_domain' => $this->trans('From domain'),
                    'reply_to_domain' => $this->trans('Reply-To domain')
                );
                $items = array();
                foreach ($evidence as $item) {
                    $type = $item['type'] ?? '';
                    $value = $item['value'] ?? '';
                    if (!$type || !$value) {
                        continue;
                    }
                    $label_text = $label_map[$type] ?? $this->trans('Evidence');
                    $items[] = '<div class="vendor-detection-evidence-item"><span class="vendor-detection-evidence-label">'.
                        $this->html_safe($label_text).'</span> <span class="vendor-detection-evidence-value">'.
                        $this->html_safe($value).'</span></div>';
                }
                if (!empty($items)) {
                    $evidence_html = '<div class="vendor-detection-evidence collapse" id="'.$this->html_safe($panel_id).'">'.
                        '<div class="vendor-detection-evidence-title">'.$this->trans('Why this detection?').'</div>'.
                        implode('', $items).'</div>';
                }
            }

            $toggle = '';
            if ($evidence_html) {
                $toggle = '<button class="vendor-detection-toggle" type="button" data-bs-toggle="collapse" '.
                    'data-bs-target="#'.$this->html_safe($panel_id).'" aria-expanded="false" aria-controls="'.$this->html_safe($panel_id).'">'.
                    '<i class="bi bi-info-circle"></i> '.$this->trans('Why this detection?').'</button>';
            }

            $confidence_html = $confidence_label ? ' <span class="vendor-detection-confidence">('.
                $this->html_safe($confidence_label).')</span>' : '';

            $vendor_label_html = '<div class="vendor-detection-content">'.
                '<div class="col-md-2 d-none d-sm-block">'.
                    '<span class="text-muted">'.$label.'</span></div>'.
                '<div class="col-md-10">'.
                    '<span class="vendor-detection-label">'.$vendor_name.'</span>'.
                    $confidence_html.
                    $toggle.
                    $evidence_html.
            '</div></div>';
        }

        $data_request_html = '';
        if ($has_data_request) {
            $base_url = $this->html_safe($data_request_match['datarequests_url']);
            $request_target = $this->html_safe($data_request_match['vendor_name'] ?? '');
            $target_label = $request_target ? $request_target.' ' : '';
            $data_request_html = '<div class="vendor-detection-requests">'.
                '<span class="vendor-detection-requests-label">'.$this->trans('Data requests').':</span> '.
                $target_label.
                '<a href="'.$base_url.'" class="vendor-detection-request-link" target="_blank" rel="noopener">'.
                $this->trans('Get access to data about me').'</a> '.
                '<span class="vendor-detection-request-sep">|</span> '.
                '<a href="'.$base_url.'" class="vendor-detection-request-link" target="_blank" rel="noopener">'.
                $this->trans('Delete data about me').'</a> '.
                '<span class="vendor-detection-request-sep">|</span> '.
                '<a href="'.$base_url.'" class="vendor-detection-request-link" target="_blank" rel="noopener">'.
                $this->trans('Correct data about me').'</a> '.
                '<span class="vendor-detection-request-sep">|</span> '.
                '<a href="'.$base_url.'" class="vendor-detection-request-link" target="_blank" rel="noopener">'.
                $this->trans('Stop direct marketing').'</a>'.
                '</div>';

            $imap_accounts = $this->get('imap_accounts', array());
            $server_id = $this->get('msg_server_id');
            $user_email = vendor_detection_get_user_email($imap_accounts, $server_id);
            $display_name = vendor_detection_get_profile_name();
            $message_id = vendor_detection_get_message_id($this->get('msg_headers', array()));
            $template_name = $vendor_name_raw ?: ($data_request_match['vendor_name'] ?? 'there');
            $template = vendor_detection_build_request_template(
                $template_name,
                $user_email,
                $display_name,
                $message_id
            );
            $template_id = 'vendor-detection-template-'.$uid;
            $data_request_html .= '<div class="vendor-detection-template">'.
                '<button class="vendor-detection-toggle" type="button" data-bs-toggle="collapse" '.
                'data-bs-target="#'.$this->html_safe($template_id).'" aria-expanded="false" aria-controls="'.$this->html_safe($template_id).'">'.
               // '<i class="bi bi-pencil-square"></i> '.$this->trans('Request template').'</button>'.
                '<div class="vendor-detection-template-panel collapse" id="'.$this->html_safe($template_id).'">'.
                '<textarea class="vendor-detection-template-text" rows="8">'.$this->html_safe($template).'</textarea>'.
                '</div></div>';
        }

        $requests_wrapper = $data_request_html ? '<div class="vendor-detection-requests-content">'.$data_request_html.'</div>' : '';
        $html = '<div class="row g-0 py-0 py-sm-1 small_header d-flex vendor-detection-row">'.
            $vendor_label_html.
            $requests_wrapper.
        '</div>';
        $this->concat('msg_headers', $html);
        return '';
    }
}
