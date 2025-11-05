<?php

/**
 * MTA-STS module
 * @package modules
 * @subpackage mta_sts
 */

if (!defined('DEBUG_MODE')) { die(); }

require APP_PATH.'lib/mta_sts.php';

/**
 * Check MTA-STS status for compose recipients
 * @subpackage mta_sts/handler
 */
class Hm_Handler_check_mta_sts_status extends Hm_Handler_Module {
    /**
     * Check if compose_to parameter contains email addresses and check their MTA-STS status
     */
    public function process() {
        // Only process on compose page or when there's a compose_to parameter
        $compose_to = $this->get('compose_to', '');

        if (empty($compose_to)) {
            // Check if there are draft recipients
            $draft = $this->get('compose_draft', array());
            if (!empty($draft) && isset($draft['draft_to'])) {
                $compose_to = $draft['draft_to'];
            }
        }

        if (empty($compose_to)) {
            // Check reply details
            $reply = $this->get('reply_details', array());
            if (!empty($reply) && isset($reply['msg_headers']['From'])) {
                $from = $reply['msg_headers']['From'];
                if (is_array($from)) {
                    $from = implode(', ', $from);
                }
                $compose_to = $from;
            }
        }

        // Check if MTA-STS checking is enabled in user settings
        $settings = $this->user_config->get('enable_mta_sts_check', false);
        if (!$settings) {
            return;
        }

        if (!empty($compose_to)) {
            $recipients = $this->parse_recipients($compose_to);
            $mta_sts_status = array();

            foreach ($recipients as $email) {
                $domain = Hm_MTA_STS::extract_domain($email);
                if ($domain) {
                    // Create an instance for this domain
                    $mta_sts = new Hm_MTA_STS($domain);
                    $result = $mta_sts->check_domain();
                    $tls_rpt = $mta_sts->check_tls_rpt();

                    $mta_sts_status[$email] = array(
                        'domain' => $domain,
                        'mta_sts' => $result,
                        'tls_rpt' => $tls_rpt,
                        'status_message' => Hm_MTA_STS::get_status_message($result),
                        'status_class' => Hm_MTA_STS::get_status_class($result)
                    );
                }
            }

            $this->out('mta_sts_status', $mta_sts_status);
        }
    }

    /**
     * Parse recipient string into individual email addresses
     * @param string $recipients Comma-separated email addresses
     * @return array Array of email addresses
     */
    private function parse_recipients($recipients) {
        $emails = array();

        // Split by comma
        $parts = explode(',', $recipients);

        foreach ($parts as $part) {
            $part = trim($part);
            if (empty($part)) {
                continue;
            }

            // Extract email from "Name <email>" format
            if (preg_match('/<([^>]+)>/', $part, $matches)) {
                $emails[] = trim($matches[1]);
            } else {
                // Assume it's a plain email address
                if (filter_var($part, FILTER_VALIDATE_EMAIL)) {
                    $emails[] = $part;
                }
            }
        }

        return array_unique($emails);
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

        if (empty($mta_sts_status)) {
            return '';
        }

        $output = '<div class="mta-sts-status-container mt-3 mb-3 p-3 border rounded">';
        $output .= '<h6 class="mta-sts-status-title mb-2">';
        $output .= $this->html_safe($this->trans('Email Security Status'));
        $output .= '</h6>';

        foreach ($mta_sts_status as $email => $status) {
            $domain = $status['domain'];
            $mta_sts = $status['mta_sts'];
            $tls_rpt = $status['tls_rpt'];
            $status_message = $status['status_message'];
            $status_class = $status['status_class'];

            $output .= '<div class="mta-sts-recipient mb-2">';
            $output .= '<div class="mta-sts-email fw-bold">' . $this->html_safe($email) . '</div>';
            $output .= '<div class="mta-sts-domain text-muted small">Domain: ' . $this->html_safe($domain) . '</div>';

            // MTA-STS status
            $output .= '<div class="mta-sts-indicator ' . $status_class . ' mt-1">';
            if ($mta_sts['enabled']) {
                $mode = $mta_sts['policy']['mode'];
                switch ($mode) {
                    case 'enforce':
                        $output .= '<span class="badge bg-success">🔒 MTA-STS: Enforce Mode</span>';
                        $output .= '<span class="text-success small ms-2">TLS encryption required</span>';
                        break;
                    case 'testing':
                        $output .= '<span class="badge bg-info">🔓 MTA-STS: Testing Mode</span>';
                        $output .= '<span class="text-info small ms-2">TLS encryption preferred</span>';
                        break;
                    case 'none':
                        $output .= '<span class="badge bg-secondary">MTA-STS: Disabled</span>';
                        break;
                }
            } else {
                $output .= '<span class="badge bg-warning">⚠️ MTA-STS: Not Configured</span>';
                $output .= '<span class="text-muted small ms-2">TLS security not enforced</span>';
            }
            $output .= '</div>';

            // TLS-RPT status
            if ($tls_rpt['enabled']) {
                $output .= '<div class="tls-rpt-indicator mt-1">';
                $output .= '<span class="badge bg-primary">📊 TLS-RPT: Enabled</span>';
                $output .= '<span class="text-muted small ms-2">Reports to: ' . $this->html_safe($tls_rpt['rua']) . '</span>';
                $output .= '</div>';
            }

            $output .= '</div>';
        }

        $output .= '</div>';

        return $output;
    }
}

/**
 * Add CSS for MTA-STS indicators
 * @subpackage mta_sts/output
 */
class Hm_Output_mta_sts_styles extends Hm_Output_Module {
    /**
     * Add custom CSS for MTA-STS status indicators
     */
    protected function output() {
        return '<style>
.mta-sts-status-container {
    background-color: #f8f9fa;
}

.mta-sts-status-title {
    font-weight: 600;
    color: #495057;
}

.mta-sts-recipient {
    padding: 0.5rem;
    background-color: white;
    border-radius: 0.25rem;
}

.mta-sts-email {
    font-size: 0.95rem;
}

.mta-sts-domain {
    font-size: 0.85rem;
}

.mta-sts-indicator,
.tls-rpt-indicator {
    font-size: 0.9rem;
}

.mta-sts-enforce {
    border-left: 3px solid #28a745;
    padding-left: 0.5rem;
}

.mta-sts-testing {
    border-left: 3px solid #17a2b8;
    padding-left: 0.5rem;
}

.mta-sts-disabled {
    border-left: 3px solid #ffc107;
    padding-left: 0.5rem;
}
</style>';
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
        if (array_key_exists('enable_mta_sts_check', $settings) && $settings['enable_mta_sts_check']) {
            $checked = ' checked="checked"';
        }
        return '<tr class="general_setting"><td><label for="enable_mta_sts_check">'.
            $this->trans('Enable MTA-STS security status checking').'</label></td>'.
            '<td><input type="checkbox" id="enable_mta_sts_check" name="enable_mta_sts_check" value="1"'.$checked.' />'.
            '</td></tr>';
    }
}
