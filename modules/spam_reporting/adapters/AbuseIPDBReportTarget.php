<?php

/**
 * AbuseIPDB API spam report target
 * Reports source IP from email headers. Minimal payload: IP, category, optional comment.
 * @package modules
 * @subpackage spam_reporting
 */

if (!defined('DEBUG_MODE')) { die(); }

class Hm_Spam_Report_AbuseIPDB_Target extends Hm_Spam_Report_Target_Api_Abstract {
    protected $id = 'abuseipdb';
    protected $label = 'AbuseIPDB';
    protected $platform_id = 'abuseipdb';

    /** AbuseIPDB category 11 = Email Spam */
    const CATEGORY_EMAIL_SPAM = 11;

    public function id() {
        return $this->id;
    }

    public function label() {
        return $this->label;
    }

    public function platform_id() {
        return $this->platform_id;
    }

    public function capabilities() {
        return array('api');
    }

    public function requirements() {
        return array('ip');
    }

    /**
     * Schema for user-provided instance config. api_key is secret.
     * @return array<string, array{type: string, label: string, required: bool}>
     */
    public function get_configuration_schema() {
        return array(
            'api_key' => array('type' => 'secret', 'label' => 'API Key', 'required' => true),
        );
    }

    /**
     * API key from instance_config when non-empty; else site-level (legacy).
     * @param array $instance_config
     * @return string
     */
    protected function get_api_key(array $instance_config = array()) {
        if (!empty($instance_config) && isset($instance_config['api_key']) && trim((string) $instance_config['api_key']) !== '') {
            return trim((string) $instance_config['api_key']);
        }
        if (!$this->site_config) {
            return '';
        }
        return trim((string) $this->site_config->get('spam_reporting_abuseipdb_api_key', ''));
    }

    protected function get_api_base_url() {
        return 'https://api.abuseipdb.com/api/v2';
    }

    public function get_api_service_name() {
        return 'AbuseIPDB';
    }

    public function is_available(Hm_Spam_Report $report, $user_config, array $instance_config = array()) {
        if ($this->get_api_key($instance_config) === '') {
            return false;
        }
        $ips = $report->get_source_ips();
        return !empty($ips);
    }

    /**
     * Build minimal payload: ip, categories, optional comment only.
     * No headers, body, or user identity.
     */
    public function build_payload(Hm_Spam_Report $report, array $user_input = array(), array $instance_config = array()) {
        $ips = $report->get_source_ips();
        $ip = !empty($ips) ? $ips[0] : '';
        $comment = '';
        if (array_key_exists('user_notes', $user_input) && trim((string) $user_input['user_notes'])) {
            $comment = mb_substr(trim((string) $user_input['user_notes']), 0, 1024);
        }
        return array(
            'ip' => $ip,
            'categories' => array(self::CATEGORY_EMAIL_SPAM),
            'comment' => $comment
        );
    }

    protected function build_api_body($payload_data) {
        $body = array(
            'ip' => $payload_data['ip'],
            'categories' => implode(',', $payload_data['categories'])
        );
        if (!empty($payload_data['comment'])) {
            $body['comment'] = $payload_data['comment'];
        }
        return json_encode($body);
    }

    public function deliver($payload, $context = null) {
        if (!is_array($payload) || empty($payload['ip'])) {
            return new Hm_Spam_Report_Result(false, 'Invalid payload');
        }
        $instance_config = ($context instanceof Hm_Spam_Report_Delivery_Context)
            ? $context->get_instance_config()
            : array();
        $api_key = $this->get_api_key($instance_config);
        if ($api_key === '') {
            return new Hm_Spam_Report_Result(false, 'API key not configured');
        }
        $url = $this->get_api_base_url() . '/report';
        $body = $this->build_api_body($payload);
        $headers = $this->get_request_headers($api_key);
        return $this->http_post($url, $body, $headers);
    }
}
