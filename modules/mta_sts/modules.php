<?php

/**
 * MTA-STS module
 * @package modules
 * @subpackage mta_sts
 */

if (!defined('DEBUG_MODE')) { die(); }

require_once APP_PATH.'lib/mta_sts.php';

/**
 * Parse recipient strings into individual email addresses
 * @subpackage mta_sts/functions
 * @param string|array $recipients Recipient field values
 * @return array Array of email addresses
 */
if (!hm_exists('mta_sts_parse_recipients')) {
function mta_sts_parse_recipients($recipients) {
    $emails = array();

    if (!is_array($recipients)) {
        $recipients = array($recipients);
    }

    foreach ($recipients as $recipient) {
        if (!is_string($recipient) || trim($recipient) === '') {
            continue;
        }
        foreach (process_address_fld($recipient) as $address) {
            if (!empty($address['email'])) {
                $emails[] = $address['email'];
            }
        }
    }

    return array_values(array_unique($emails));
}}

/**
 * Build MTA-STS/TLS-RPT status details for recipient addresses
 * @subpackage mta_sts/functions
 * @param array $recipients Recipient email addresses
 * @return array Status details keyed by email
 */
if (!hm_exists('mta_sts_check_recipients')) {
function mta_sts_check_recipients($recipients) {
    $mta_sts_status = array();
    $domain_status = array();
    $mta_sts_helper = new Hm_MTA_STS();

    foreach ($recipients as $email) {
        $domain = $mta_sts_helper->extract_domain($email);
        if (!$domain) {
            continue;
        }

        if (!array_key_exists($domain, $domain_status)) {
            $mta_sts = new Hm_MTA_STS($domain);
            $result = $mta_sts->check_domain();
            $domain_status[$domain] = array(
                'domain' => $domain,
                'mta_sts' => $result,
                'tls_rpt' => $mta_sts->check_tls_rpt(),
                'status_class' => $mta_sts->get_status_class($result)
            );
        }

        $mta_sts_status[$email] = $domain_status[$domain];
    }

    return $mta_sts_status;
}}

/**
 * Render MTA-STS/TLS-RPT status HTML
 * @subpackage mta_sts/functions
 * @param array $mta_sts_status Status details keyed by email
 * @param object $output_mod Output module
 * @param bool $render_empty Render an empty client-side placeholder
 * @return string
 */
if (!hm_exists('mta_sts_status_indicator_html')) {
function mta_sts_status_indicator_html($mta_sts_status, $output_mod, $render_empty=false) {
    $container_class = 'mta-sts-status-container mt-3 mb-3 p-3 border rounded';

    if (empty($mta_sts_status)) {
        if (!$render_empty) {
            return '';
        }
        return '<div class="'.$container_class.' d-none" data-mta-sts-enabled="1"></div>';
    }

    $output = '<div class="'.$container_class.'" data-mta-sts-enabled="1">';
    $output .= '<h6 class="mta-sts-status-title mb-2">';
    $output .= $output_mod->html_safe($output_mod->trans('Recipient MTA-STS Policy Status'));
    $output .= '</h6>';

    foreach ($mta_sts_status as $email => $status) {
        $domain = $status['domain'];
        $mta_sts = $status['mta_sts'];
        $tls_rpt = $status['tls_rpt'];
        $status_class = $status['status_class'];

        $output .= '<div class="mta-sts-recipient mb-2">';
        $output .= '<div class="mta-sts-email fw-bold">' . $output_mod->html_safe($email) . '</div>';
        $output .= '<div class="mta-sts-domain text-muted small">' . $output_mod->trans('Domain') . ': ' . $output_mod->html_safe($domain) . '</div>';

        $output .= '<div class="mta-sts-indicator ' . $output_mod->html_safe($status_class) . ' mt-1">';
        if ($mta_sts['enabled']) {
            $mode = $mta_sts['policy']['mode'];
            switch ($mode) {
                case 'enforce':
                    $output .= '<span class="badge bg-success"><i class="bi bi-shield-lock-fill"></i> MTA-STS: ' . $output_mod->trans('Enforce Mode') . '</span>';
                    $output .= '<span class="text-success small ms-2">' . $output_mod->trans('Recipient domain publishes an enforce policy') . '</span>';
                    break;
                case 'testing':
                    $output .= '<span class="badge bg-info"><i class="bi bi-shield-check"></i> MTA-STS: ' . $output_mod->trans('Testing Mode') . '</span>';
                    $output .= '<span class="text-info small ms-2">' . $output_mod->trans('Recipient domain is testing a policy') . '</span>';
                    break;
                case 'none':
                    $output .= '<span class="badge bg-secondary">MTA-STS: ' . $output_mod->trans('Disabled') . '</span>';
                    break;
            }
        } else {
            $output .= '<span class="badge bg-warning"><i class="bi bi-shield-exclamation"></i> MTA-STS: ' . $output_mod->trans('Not Configured') . '</span>';
            $output .= '<span class="text-muted small ms-2">' . $output_mod->trans('No recipient policy was found') . '</span>';
        }
        $output .= '</div>';

        if ($tls_rpt['enabled']) {
            $output .= '<div class="tls-rpt-indicator mt-1">';
            $output .= '<span class="badge bg-primary"><i class="bi bi-bar-chart-line-fill"></i> TLS-RPT: ' . $output_mod->trans('Enabled') . '</span>';
            $output .= '<span class="text-muted small ms-2">' . $output_mod->trans('Reports to') . ': ' . $output_mod->html_safe($tls_rpt['rua']) . '</span>';
            $output .= '</div>';
        }

        $output .= '</div>';
    }

    $output .= '</div>';

    return $output;
}}

/**
 * Check MTA-STS status for compose recipients
 * @subpackage mta_sts/handler
 */
class Hm_Handler_check_mta_sts_status extends Hm_Handler_Module {
    /**
     * Check if recipient parameters contain email addresses and check their MTA-STS status
     */
    public function process() {
        $settings = $this->user_config->get('enable_mta_sts_check_setting', false);
        if (!$settings) {
            return;
        }

        $this->out('mta_sts_status_enabled', true);

        $recipients = mta_sts_parse_recipients($this->get_recipient_values());
        if (!empty($recipients)) {
            $this->out('mta_sts_status', mta_sts_check_recipients($recipients));
        }
    }

    /**
     * Get recipient field values from the current request or composed message state
     * @return array
     */
    private function get_recipient_values() {
        $values = array();
        $post_fields = array('compose_to', 'compose_cc', 'compose_bcc');
        $has_post_recipients = false;

        foreach ($post_fields as $field) {
            if (array_key_exists($field, $this->request->post)) {
                $values[] = $this->request->post[$field];
                $has_post_recipients = true;
            }
        }

        if ($has_post_recipients) {
            return $values;
        }

        $draft = $this->get('compose_draft', array());
        foreach (array('draft_to', 'draft_cc', 'draft_bcc') as $field) {
            if (!empty($draft[$field])) {
                $values[] = $draft[$field];
            }
        }

        $imap_draft = $this->get('imap_draft', array());
        foreach (array('To', 'Cc', 'Bcc') as $field) {
            if (!empty($imap_draft[$field])) {
                $values[] = $imap_draft[$field];
            }
        }

        $reply = $this->get('reply_details', array());
        $reply_type = $this->get('reply_type', function_exists('get_reply_type') ? get_reply_type($this->request->get) : '');
        if (!empty($reply) && !empty($reply['msg_headers']) && $reply_type) {
            list($reply_to, $reply_cc) = reply_to_address($reply['msg_headers'], $reply_type);
            if ($reply_to) {
                $values[] = $reply_to;
            }
            if ($reply_cc) {
                $values[] = $reply_cc;
            }
            if ($reply_type == 'reply_all' && !empty($reply['msg_headers']['From'])) {
                $from = $reply['msg_headers']['From'];
                if (is_array($from)) {
                    $from = implode(', ', $from);
                }
                $values[] = $from;
            }
        }

        return $values;
    }
}

/**
 * Output MTA-STS status indicator in compose form
 * @subpackage mta_sts/output
 */
class Hm_Output_mta_sts_status_indicator extends Hm_Output_Module {
    /**
     * Display MTA-STS status for recipients
     */
    protected function output() {
        $mta_sts_status = $this->get('mta_sts_status', array());

        if (!$this->get('mta_sts_status_enabled', false)) {
            return '';
        }

        return mta_sts_status_indicator_html($mta_sts_status, $this, true);
    }
}

/**
 * Output MTA-STS status indicator for AJAX responses
 * @subpackage mta_sts/output
 */
class Hm_Output_filter_mta_sts_status extends Hm_Output_Module {
    /**
     * Return rendered MTA-STS status HTML
     */
    protected function output() {
        if (!$this->get('mta_sts_status_enabled', false)) {
            return;
        }
        $this->out(
            'mta_sts_status_display',
            mta_sts_status_indicator_html($this->get('mta_sts_status', array()), $this, true)
        );
    }
}

/**
 * Process MTA-STS enable/disable setting
 * @subpackage mta_sts/handler
 */
class Hm_Handler_process_enable_mta_sts_setting extends Hm_Handler_Module {
    /**
     * Process enable_mta_sts_check setting from the settings page
     */
    public function process() {
        process_site_setting('enable_mta_sts_check', $this, 'enable_mta_sts_check_callback', false, true);
    }
}

/**
 * @subpackage mta_sts/functions
 */
if (!hm_exists('enable_mta_sts_check_callback')) {
    function enable_mta_sts_check_callback($val) {
        return $val;
    }
}

/**
 * Output MTA-STS setting on the settings page
 * @subpackage mta_sts/output
 */
class Hm_Output_enable_mta_sts_check_setting extends Hm_Output_Module {
    /**
     * Output the enable_mta_sts_check checkbox on the settings page
     */
    protected function output() {
        $settings = $this->get('user_settings', array());
        $checked = '';
        if (array_key_exists('enable_mta_sts_check_setting', $settings) && $settings['enable_mta_sts_check_setting']) {
            $checked = ' checked="checked"';
        }
        return '<tr class="general_setting"><td><label for="enable_mta_sts_check">'.
            $this->trans('Show recipient MTA-STS policy status when composing').'</label></td>'.
            '<td><input type="checkbox" id="enable_mta_sts_check" name="enable_mta_sts_check" value="1"'.$checked.' />'.
            '</td></tr>';
    }
}
