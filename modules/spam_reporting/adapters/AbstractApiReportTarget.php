<?php

/**
 * Base adapter for API-based spam report targets
 * Handles HTTP requests, auth headers, JSON encoding, error handling.
 * @package modules
 * @subpackage spam_reporting
 */

if (!defined('DEBUG_MODE')) { die(); }

abstract class Hm_Spam_Report_Target_Api_Abstract extends Hm_Spam_Report_Target_Abstract {
    /** @var object|null site config for API key lookup */
    protected $site_config = null;

    public function configure(array $config) {
        parent::configure($config);
        if (isset($config['_site_config'])) {
            $this->site_config = $config['_site_config'];
        }
    }

    /**
     * Get API key: from instance_config when non-empty, else from site config (legacy).
     * @param array $instance_config user-provided instance config
     * @return string
     */
    abstract protected function get_api_key(array $instance_config = array());

    /**
     * Get API base URL (subclass defines)
     * @return string
     */
    abstract protected function get_api_base_url();

    /**
     * Build JSON payload for API (subclass implements)
     * @param mixed $payload_data from build_payload
     * @return string JSON
     */
    abstract protected function build_api_body($payload_data);

    /**
     * Get HTTP headers for the request
     * @param string $api_key
     * @return array
     */
    protected function get_request_headers($api_key) {
        return array(
            'Accept: application/json',
            'Content-Type: application/json',
            'Key: ' . $api_key
        );
    }

    /**
     * Execute HTTP POST and return result
     * @param string $url
     * @param string $body JSON
     * @param array $headers
     * @return Hm_Spam_Report_Result
     */
    protected function http_post($url, $body, array $headers) {
        $ch = curl_init($url);
        if (!$ch) {
            return new Hm_Spam_Report_Result(false, 'Failed to initialize request');
        }
        curl_setopt_array($ch, array(
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_SSL_VERIFYPEER => true
        ));
        $response = curl_exec($ch);
        $http_code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_err = curl_error($ch);
        curl_close($ch);

        if ($response === false || $curl_err !== '') {
            return new Hm_Spam_Report_Result(false, 'Request failed: ' . ($curl_err ?: 'Unknown error'));
        }

        $decoded = json_decode($response, true);
        if ($http_code >= 200 && $http_code < 300) {
            $msg = isset($decoded['data']['abuseConfidenceScore'])
                ? 'Report submitted (confidence: ' . $decoded['data']['abuseConfidenceScore'] . '%)'
                : 'Report submitted';
            return new Hm_Spam_Report_Result(true, $msg, $decoded);
        }

        $err_msg = 'API error';
        if (is_array($decoded) && isset($decoded['errors'][0]['detail'])) {
            $err_msg = $decoded['errors'][0]['detail'];
        } elseif (is_array($decoded) && isset($decoded['errors'][0]['title'])) {
            $err_msg = $decoded['errors'][0]['title'];
        }
        return new Hm_Spam_Report_Result(false, $err_msg . ' (HTTP ' . $http_code . ')');
    }

    /**
     * Whether this target uses an external API (for consent UI)
     * @return bool
     */
    public function is_api_target() {
        return true;
    }

    /**
     * Human-readable service name for consent message
     * @return string
     */
    abstract public function get_api_service_name();
}
