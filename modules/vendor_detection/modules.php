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

        $data_request_sender_match = $this->get('data_request_sender_match', array());
        $data_request_platform_match = $this->get('data_request_platform_match', array());
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
        $sender_domain = $data_request_sender_match['sender_domain'] ?? '';
        $sender_slug = $data_request_sender_match['vendor_id'] ?? '';
        $platform_slug = $data_request_platform_match['vendor_id'] ?? '';
        $has_data_request = $data_request_enabled && ($sender_domain || $platform_slug);
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
            $language = $this->get('language', '');
            $base_url = vendor_detection_get_datarequests_base_url($language);
            $sender_label = $data_request_sender_match['vendor_name'] ?? $sender_domain;
            $platform_label = $data_request_platform_match['vendor_name'] ?? $platform_slug;

            $sender_help = $this->trans('The sender is usually the data controller and most likely to respond.');
            $platform_help = $this->trans('Platforms may also process your data. Responses can vary.');
            $sender_missing_help = $this->trans('This sender was not found on datarequests.org.');
            $platform_only_help = $this->trans('The actual data controller may be the organization mentioned in the message content.');

            $target_options = '';
            $checked_sender = $sender_domain ? ' checked' : '';
            $checked_platform = (!$sender_domain && $platform_slug) ? ' checked' : '';

            if ($sender_domain) {
                $target_options .= '<label class="vendor-detection-request-option">'.
                    '<input type="radio" name="data_request_target" class="js-data-request-target" value="sender" data-company-slug="'.
                    $this->html_safe($sender_slug).'"'.$checked_sender.' /> '.
                    $this->trans('The sender').': '.$this->html_safe($sender_label).
                    '<div class="vendor-detection-request-help">'.$this->html_safe($sender_help).'</div>';
                if (!$sender_slug) {
                    $target_options .= '<div class="vendor-detection-request-help">'.$this->html_safe($sender_missing_help).'</div>';
                }
                $target_options .= '</label>';
            }

            if ($platform_slug) {
                $target_options .= '<label class="vendor-detection-request-option">'.
                    '<input type="radio" name="data_request_target" class="js-data-request-target" value="platform" data-company-slug="'.
                    $this->html_safe($platform_slug).'"'.$checked_platform.' /> '.
                    $this->trans('The sending platform').': '.$this->html_safe($platform_label).
                    '<div class="vendor-detection-request-help">'.$this->html_safe($platform_help).'</div>';
                if (!$sender_domain) {
                    $target_options .= '<div class="vendor-detection-request-help">'.$this->html_safe($platform_only_help).'</div>';
                }
                $target_options .= '</label>';
            }

            $request_types = array(
                'access' => $this->trans('Access my data'),
                'delete' => $this->trans('Delete my data'),
                'rectification' => $this->trans('Correct my data'),
                'objection' => $this->trans('Stop direct marketing')
            );
            $type_options = '';
            foreach ($request_types as $value => $label) {
                $checked = $value === 'access' ? ' checked' : '';
                $type_options .= '<label class="vendor-detection-request-type">'.
                    '<input type="radio" name="data_request_type" class="js-data-request-type" value="'.
                    $this->html_safe($value).'"'.$checked.' /> '.$this->html_safe($label).
                    '</label>';
            }

            $selected_slug = $sender_domain ? $sender_slug : $platform_slug;
            $button_disabled_class = $selected_slug ? '' : ' disabled';
            $button_aria = $selected_slug ? 'false' : 'true';
            $data_request_html = '<div class="vendor-detection-requests" data-base-url="'.$this->html_safe($base_url).'">'.
                '<div class="vendor-detection-requests-label">'.$this->trans('Data requests').'</div>'.
                '<div class="vendor-detection-request-question">'.$this->trans('Request my data from').':</div>'.
                '<div class="vendor-detection-request-targets">'.$target_options.'</div>'.
                '<div class="vendor-detection-request-types">'.$type_options.'</div>'.
                '<a class="btn btn-primary btn-sm vendor-detection-request-button js-data-request-button'.$button_disabled_class.'" href="#" '.
                'target="_blank" rel="noopener" aria-disabled="'.$button_aria.'">'.
                $this->trans('Generate data request').'</a>'.
                '</div>';
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
