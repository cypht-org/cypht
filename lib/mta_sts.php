<?php

/**
 * MTA-STS (Mail Transfer Agent Strict Transport Security) Support Library
 * @package lib
 * @subpackage mta_sts
 */

if (!defined('DEBUG_MODE')) { die(); }

/**
 * Class for MTA-STS policy checking and validation
 */
class Hm_MTA_STS {

    /**
     * Cache for MTA-STS policy lookups
     * @var array
     */
    private static $policy_cache = array();

    /**
     * DNS cache TTL in seconds
     * @var int
     */
    private static $cache_ttl = 3600;

    /**
     * Check if a domain has MTA-STS enabled
     *
     * @param string $domain The domain to check
     * @return array Array with 'enabled' (bool), 'policy' (array|null), and 'error' (string|null)
     */
    public static function check_domain($domain) {
        $domain = strtolower(trim($domain));

        // Check cache first
        if (isset(self::$policy_cache[$domain])) {
            $cached = self::$policy_cache[$domain];
            if (time() - $cached['timestamp'] < self::$cache_ttl) {
                return $cached['result'];
            }
        }

        $result = array(
            'enabled' => false,
            'policy' => null,
            'error' => null,
            'dns_record' => null
        );

        // Step 1: Check for MTA-STS DNS TXT record
        $dns_record = self::get_mta_sts_dns_record($domain);
        if ($dns_record === false) {
            $result['error'] = 'No MTA-STS DNS record found';
            self::cache_result($domain, $result);
            return $result;
        }

        $result['dns_record'] = $dns_record;

        // Step 2: Parse DNS record to get policy ID
        $policy_id = self::parse_policy_id($dns_record);
        if (!$policy_id) {
            $result['error'] = 'Invalid MTA-STS DNS record format';
            self::cache_result($domain, $result);
            return $result;
        }

        // Step 3: Fetch and parse the policy file
        $policy = self::fetch_policy($domain);
        if (!$policy) {
            $result['error'] = 'Could not fetch MTA-STS policy file';
            self::cache_result($domain, $result);
            return $result;
        }

        $result['enabled'] = true;
        $result['policy'] = $policy;

        self::cache_result($domain, $result);
        return $result;
    }

    /**
     * Get MTA-STS DNS TXT record for a domain
     *
     * @param string $domain The domain to query
     * @return string|false The DNS record or false if not found
     */
    private static function get_mta_sts_dns_record($domain) {
        $record_name = "_mta-sts.{$domain}";

        // Try to get DNS TXT records
        $records = @dns_get_record($record_name, DNS_TXT);

        if ($records === false || empty($records)) {
            return false;
        }

        // Look for the MTA-STS record (v=STSv1)
        foreach ($records as $record) {
            if (isset($record['txt']) && strpos($record['txt'], 'v=STSv1') !== false) {
                return $record['txt'];
            }
        }

        return false;
    }

    /**
     * Parse policy ID from MTA-STS DNS record
     *
     * @param string $dns_record The DNS TXT record
     * @return string|false The policy ID or false if not found
     */
    private static function parse_policy_id($dns_record) {
        // Expected format: "v=STSv1; id=20190429T010101;"
        if (preg_match('/id=([^;]+);?/i', $dns_record, $matches)) {
            return trim($matches[1]);
        }
        return false;
    }

    /**
     * Fetch MTA-STS policy file from domain
     *
     * @param string $domain The domain to fetch policy from
     * @return array|false Parsed policy or false on failure
     */
    private static function fetch_policy($domain) {
        $policy_url = "https://mta-sts.{$domain}/.well-known/mta-sts.txt";

        // Use cURL if available, otherwise use file_get_contents with SSL context
        if (function_exists('curl_init')) {
            $policy_content = self::fetch_with_curl($policy_url);
        } else {
            $policy_content = self::fetch_with_fopen($policy_url);
        }

        if (!$policy_content) {
            return false;
        }

        return self::parse_policy($policy_content);
    }

    /**
     * Fetch policy using cURL
     *
     * @param string $url The URL to fetch
     * @return string|false The content or false on failure
     */
    private static function fetch_with_curl($url) {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);

        $content = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($http_code === 200 && $content !== false) {
            return $content;
        }

        return false;
    }

    /**
     * Fetch policy using file_get_contents
     *
     * @param string $url The URL to fetch
     * @return string|false The content or false on failure
     */
    private static function fetch_with_fopen($url) {
        $context = stream_context_create(array(
            'http' => array(
                'timeout' => 10,
                'follow_location' => 1
            ),
            'ssl' => array(
                'verify_peer' => true,
                'verify_peer_name' => true
            )
        ));

        $content = @file_get_contents($url, false, $context);

        return ($content !== false) ? $content : false;
    }

    /**
     * Parse MTA-STS policy content
     *
     * @param string $content The policy file content
     * @return array|false Parsed policy or false on failure
     */
    private static function parse_policy($content) {
        $policy = array(
            'version' => null,
            'mode' => null,
            'mx' => array(),
            'max_age' => null
        );

        $lines = explode("\n", $content);

        foreach ($lines as $line) {
            $line = trim($line);

            // Skip empty lines and comments
            if (empty($line) || $line[0] === '#') {
                continue;
            }

            // Parse key: value format
            $parts = explode(':', $line, 2);
            if (count($parts) !== 2) {
                continue;
            }

            $key = trim($parts[0]);
            $value = trim($parts[1]);

            switch ($key) {
                case 'version':
                    $policy['version'] = $value;
                    break;
                case 'mode':
                    $policy['mode'] = $value;
                    break;
                case 'mx':
                    $policy['mx'][] = $value;
                    break;
                case 'max_age':
                    $policy['max_age'] = intval($value);
                    break;
            }
        }

        // Validate required fields
        if ($policy['version'] !== 'STSv1' || empty($policy['mode']) || empty($policy['mx'])) {
            return false;
        }

        return $policy;
    }

    /**
     * Cache a lookup result
     *
     * @param string $domain The domain
     * @param array $result The result to cache
     */
    private static function cache_result($domain, $result) {
        self::$policy_cache[$domain] = array(
            'timestamp' => time(),
            'result' => $result
        );
    }

    /**
     * Check if a domain has TLS-RPT enabled
     *
     * @param string $domain The domain to check
     * @return array Array with 'enabled' (bool), 'rua' (string|null), and 'error' (string|null)
     */
    public static function check_tls_rpt($domain) {
        $domain = strtolower(trim($domain));
        $record_name = "_smtp._tls.{$domain}";

        // Try to get DNS TXT records
        $records = @dns_get_record($record_name, DNS_TXT);

        if ($records === false || empty($records)) {
            return array(
                'enabled' => false,
                'rua' => null,
                'error' => 'No TLS-RPT DNS record found'
            );
        }

        // Look for the TLS-RPT record (v=TLSRPTv1)
        foreach ($records as $record) {
            if (isset($record['txt']) && strpos($record['txt'], 'v=TLSRPTv1') !== false) {
                $rua = self::parse_tls_rpt_rua($record['txt']);
                return array(
                    'enabled' => true,
                    'rua' => $rua,
                    'error' => null,
                    'dns_record' => $record['txt']
                );
            }
        }

        return array(
            'enabled' => false,
            'rua' => null,
            'error' => 'No valid TLS-RPT record found'
        );
    }

    /**
     * Parse RUA (Reporting URI of Aggregate reports) from TLS-RPT DNS record
     *
     * @param string $dns_record The DNS TXT record
     * @return string|null The RUA or null if not found
     */
    private static function parse_tls_rpt_rua($dns_record) {
        // Expected format: "v=TLSRPTv1; rua=mailto:reports@example.com"
        if (preg_match('/rua=([^;]+);?/i', $dns_record, $matches)) {
            return trim($matches[1]);
        }
        return null;
    }

    /**
     * Extract domain from email address
     *
     * @param string $email The email address
     * @return string|false The domain or false if invalid
     */
    public static function extract_domain($email) {
        $email = trim($email);

        // Remove display name if present (e.g., "John Doe <john@example.com>")
        if (preg_match('/<([^>]+)>/', $email, $matches)) {
            $email = $matches[1];
        }

        // Extract domain part
        $parts = explode('@', $email);
        if (count($parts) === 2) {
            return strtolower(trim($parts[1]));
        }

        return false;
    }

    /**
     * Get a human-readable status message for MTA-STS check
     *
     * @param array $mta_sts_result Result from check_domain()
     * @return string Status message
     */
    public static function get_status_message($mta_sts_result) {
        if ($mta_sts_result['enabled']) {
            $mode = isset($mta_sts_result['policy']['mode']) ? $mta_sts_result['policy']['mode'] : 'unknown';

            switch ($mode) {
                case 'enforce':
                    return 'MTA-STS enabled (enforce mode) - TLS required';
                case 'testing':
                    return 'MTA-STS enabled (testing mode) - TLS preferred';
                case 'none':
                    return 'MTA-STS disabled';
                default:
                    return 'MTA-STS enabled (unknown mode)';
            }
        }

        return 'MTA-STS not configured';
    }

    /**
     * Get CSS class for status indicator
     *
     * @param array $mta_sts_result Result from check_domain()
     * @return string CSS class name
     */
    public static function get_status_class($mta_sts_result) {
        if (!$mta_sts_result['enabled']) {
            return 'mta-sts-disabled';
        }

        $mode = isset($mta_sts_result['policy']['mode']) ? $mta_sts_result['policy']['mode'] : 'none';

        switch ($mode) {
            case 'enforce':
                return 'mta-sts-enforce';
            case 'testing':
                return 'mta-sts-testing';
            default:
                return 'mta-sts-disabled';
        }
    }

    /**
     * Clear the policy cache
     */
    public static function clear_cache() {
        self::$policy_cache = array();
    }
}
