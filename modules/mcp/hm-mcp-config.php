<?php

/**
 * Configuration for the MCP server and REST API
 * @package modules
 * @subpackage mcp
 */

if (!defined('DEBUG_MODE')) { die(); }

/**
 * Settings derived from the site configuration
 * @subpackage mcp/lib
 */
class Hm_MCP_Config {

    /* site configuration object */
    public $site_config;

    /* public base URL without a trailing slash */
    private $public_url = '';

    /**
     * @param object $site_config site configuration
     */
    public function __construct($site_config) {
        $this->site_config = $site_config;
        $url = trim((string) $site_config->get('mcp_public_url', ''));
        if ($url !== '' && self::valid_public_url($url)) {
            $this->public_url = rtrim($url, '/');
        }
    }

    /**
     * A public URL must be absolute HTTPS, or HTTP for a loopback host
     * @param string $url candidate URL
     * @return bool
     */
    public static function valid_public_url($url) {
        $parts = parse_url($url);
        if (!is_array($parts) || empty($parts['scheme']) || empty($parts['host'])) {
            return false;
        }
        if (isset($parts['query']) || isset($parts['fragment']) || isset($parts['user']) || isset($parts['pass'])) {
            return false;
        }
        $scheme = strtolower($parts['scheme']);
        if ($scheme === 'https') {
            return true;
        }
        return $scheme === 'http' && self::is_loopback_host($parts['host']);
    }

    /**
     * @param string $host host name
     * @return bool
     */
    public static function is_loopback_host($host) {
        return in_array(strtolower($host), ['localhost', '127.0.0.1', '[::1]', '::1'], true);
    }

    /**
     * @return bool true when the endpoints can be served
     */
    public function is_configured() {
        return $this->public_url !== '';
    }

    /**
     * @return string public base URL (also the OAuth issuer)
     */
    public function public_url() {
        return $this->public_url;
    }

    /**
     * @return string OAuth issuer identifier
     */
    public function issuer() {
        return $this->public_url;
    }

    /**
     * @return string MCP endpoint and protected resource identifier
     */
    public function resource() {
        return $this->public_url.'/mcp';
    }

    /**
     * @return string REST API base URL
     */
    public function api_url() {
        return $this->public_url.'/api/v1';
    }

    /**
     * @param string $path endpoint path starting with '/'
     * @return string absolute URL
     */
    public function url($path) {
        return $this->public_url.$path;
    }

    /**
     * @return string URL of the protected resource metadata for /mcp
     */
    public function resource_metadata_url() {
        return $this->public_url.'/.well-known/oauth-protected-resource/mcp';
    }

    /**
     * Host names accepted in the Host header
     * @return array
     */
    public function allowed_hosts() {
        $hosts = ['localhost', '127.0.0.1', '[::1]'];
        $public_host = parse_url($this->public_url, PHP_URL_HOST);
        if ($public_host) {
            $hosts[] = strtolower($public_host);
        }
        foreach (self::split_list($this->site_config->get('mcp_allowed_hosts', '')) as $host) {
            $hosts[] = strtolower($host);
        }
        return array_values(array_unique($hosts));
    }

    /**
     * @param string $host request host without port
     * @return bool
     */
    public function host_allowed($host) {
        if ($host === '') {
            return false;
        }
        return in_array(strtolower($host), $this->allowed_hosts(), true);
    }

    /**
     * @param string $name setting name without the mcp_ prefix
     * @param int $default fallback value
     * @param int $min smallest accepted value
     * @return int
     */
    public function int($name, $default, $min = 1) {
        $value = $this->site_config->get('mcp_'.$name, $default);
        if (!is_numeric($value) || (int) $value < $min) {
            return $default;
        }
        return (int) $value;
    }

    /**
     * @param string $name setting name without the mcp_ prefix
     * @return array list of values from a comma separated setting
     */
    public function list_setting($name) {
        return self::split_list($this->site_config->get('mcp_'.$name, ''));
    }

    /**
     * @param string $name setting name without the mcp_ prefix
     * @param string $default fallback value
     * @return string
     */
    public function string($name, $default = '') {
        $value = $this->site_config->get('mcp_'.$name, $default);
        return is_string($value) ? trim($value) : $default;
    }

    /**
     * @param mixed $value comma separated string or array
     * @return array
     */
    public static function split_list($value) {
        if (is_array($value)) {
            $items = $value;
        } else {
            $items = explode(',', (string) $value);
        }
        return array_values(array_filter(array_map('trim', $items), 'strlen'));
    }

    /**
     * Check a host name against an allow list that supports "*." wildcards
     * @param string $host host to check
     * @param array $allowed allowed host patterns
     * @return bool
     */
    public static function host_in_list($host, $allowed) {
        $host = strtolower(rtrim($host, '.'));
        foreach ($allowed as $pattern) {
            $pattern = strtolower(trim($pattern));
            if ($pattern === '') {
                continue;
            }
            if (strpos($pattern, '*.') === 0) {
                $suffix = substr($pattern, 1);
                if (strlen($host) > strlen($suffix) && substr($host, -strlen($suffix)) === $suffix) {
                    return true;
                }
            } elseif ($host === $pattern) {
                return true;
            }
        }
        return false;
    }
}
