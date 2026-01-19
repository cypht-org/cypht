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
class Hm_Output_vendor_detection_label extends Hm_Output_Module {
    protected function output() {
        $vendor_detection = $this->get('vendor_detection', array());
        if (!$vendor_detection || empty($vendor_detection['vendor_name'])) {
            return '';
        }

        $user_config = $this->get('user_config');
        $enabled = false;
        if ($user_config && method_exists($user_config, 'get')) {
            $enabled = (bool) $user_config->get('vendor_detection_ui_setting', false);
        }
        $site_config = $this->get('site_config');
        if ($site_config && method_exists($site_config, 'get')) {
            $override = $site_config->get('vendor_detection_ui_enabled', null);
            if ($override !== null) {
                $enabled = (bool) $override;
            }
        }
        if (!$enabled) {
            return '';
        }

        $confidence = $vendor_detection['confidence'] ?? '';
        $confidence_map = array(
            'high' => $this->trans('High confidence'),
            'medium' => $this->trans('Medium confidence'),
            'low' => $this->trans('Low confidence')
        );
        $confidence_label = $confidence_map[$confidence] ?? '';
        $label = $this->trans('Sent via');
        $vendor_name = $this->html_safe($vendor_detection['vendor_name']);

        $uid = preg_replace('/[^a-zA-Z0-9_-]+/', '-', (string) $this->get('msg_text_uid', '0'));
        $panel_id = 'vendor-detection-evidence-'.$uid;

        $evidence_html = '';
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
                $evidence_html = '<div class="vendor-detection-evidence" id="'.$this->html_safe($panel_id).'" '.
                    'aria-hidden="true" hidden>'.
                    '<div class="vendor-detection-evidence-title">'.$this->trans('Why this detection?').'</div>'.
                    implode('', $items).'</div>';
            }
        }

        $toggle = '';
        if ($evidence_html) {
            $toggle = '<button class="vendor-detection-toggle js-vendor-detection-toggle" type="button" '.
                'data-target="'.$this->html_safe($panel_id).'" aria-expanded="false" aria-controls="'.$this->html_safe($panel_id).'">'.
                '<i class="bi bi-info-circle"></i> '.$this->trans('Why this detection?').'</button>';
        }

        $confidence_html = $confidence_label ? ' <span class="vendor-detection-confidence">('.
            $this->html_safe($confidence_label).')</span>' : '';

        $html = '<div class="row g-0 py-0 py-sm-1 small_header d-flex vendor-detection-row">'.
            '<div class="col-md-2 d-none d-sm-block"><span class="text-muted">'.$label.'</span></div>'.
            '<div class="col-md-10">'.
            '<span class="vendor-detection-label">'.$vendor_name.'</span>'.$confidence_html.$toggle.
            $evidence_html.
            '</div></div>';
        $this->concat('msg_headers', $html);
        return '';
    }
}
