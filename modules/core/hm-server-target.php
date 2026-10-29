<?php

/**
 * Outbound host checks for user-supplied targets (mail servers and feeds).
 * @package modules
 * @subpackage core
 */

if (!hm_exists('hm_server_port_is_valid')) {
/**
 * A TCP port a mail client may dial.
 * @param mixed $port
 * @return bool
 */
function hm_server_port_is_valid($port) {
    if (is_string($port) && preg_match('/^\d+$/', $port)) {
        $port = (int) $port;
    }
    if (!is_int($port)) {
        return false;
    }
    return $port >= 1 && $port <= 65535;
}}

if (!hm_exists('hm_server_target_flag')) {
/**
 * Read one outbound-host setting from a config object or the app config.
 * @param object|null $config site config
 * @param string $name config key
 * @param bool $default value when the key is unset
 * @return bool
 */
function hm_server_target_flag($config, $name, $default) {
    $value = $default;
    if (is_object($config) && method_exists($config, 'get')) {
        $value = $config->get($name, $default);
    } elseif (function_exists('config')) {
        $app = config('app');
        if (is_array($app) && array_key_exists($name, $app)) {
            $value = $app[$name];
        }
    }
    if (is_string($value)) {
        $parsed = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        if ($parsed !== null) {
            return $parsed;
        }
    }
    return (bool) $value;
}}

if (!hm_exists('hm_expand_ipv4_literal')) {
/**
 * Expand an inet_aton IPv4 spelling to dotted decimal.
 * Accepts decimal, short, octal, and hex forms such as 2130706433, 127.1, 0177.0.0.1, and 0x7f.0.0.1.
 * @param string $host
 * @return string|false
 */
function hm_expand_ipv4_literal($host) {
    if (!preg_match('/^(?:0x[0-9a-f]+|\d+)(?:\.(?:0x[0-9a-f]+|\d+)){0,3}$/i', $host)) {
        return false;
    }
    $parts = explode('.', $host);
    $nums = array();
    foreach ($parts as $part) {
        if (preg_match('/^0x([0-9a-f]+)$/i', $part, $match)) {
            if (strlen($match[1]) > 8) {
                return false;
            }
            $nums[] = hexdec($match[1]);
            continue;
        }
        if ($part[0] === '0' && strlen($part) > 1) {
            if (!preg_match('/^[0-7]+$/', $part)) {
                return false;
            }
            $nums[] = octdec($part);
            continue;
        }
        if (strlen($part) > 10) {
            return false;
        }
        $nums[] = (int) $part;
    }
    $count = count($nums);
    if ($count === 1) {
        if ($nums[0] > 0xFFFFFFFF) {
            return false;
        }
        $value = $nums[0];
    } elseif ($count === 2) {
        if ($nums[0] > 0xFF || $nums[1] > 0xFFFFFF) {
            return false;
        }
        $value = ($nums[0] << 24) + $nums[1];
    } elseif ($count === 3) {
        if ($nums[0] > 0xFF || $nums[1] > 0xFF || $nums[2] > 0xFFFF) {
            return false;
        }
        $value = ($nums[0] << 24) + ($nums[1] << 16) + $nums[2];
    } else {
        if ($nums[0] > 0xFF || $nums[1] > 0xFF || $nums[2] > 0xFF || $nums[3] > 0xFF) {
            return false;
        }
        $value = ($nums[0] << 24) + ($nums[1] << 16) + ($nums[2] << 8) + $nums[3];
    }
    return long2ip($value);
}}

if (!hm_exists('hm_unwrap_ipv4_mapped')) {
/**
 * Return the IPv4 address embedded in an IPv4-mapped IPv6 address.
 * @param string $ip canonical or textual IPv6
 * @return string|false
 */
function hm_unwrap_ipv4_mapped($ip) {
    $packed = @inet_pton($ip);
    if ($packed === false || strlen($packed) !== 16) {
        return false;
    }
    if (substr($packed, 0, 12) !== (str_repeat("\x00", 10)."\xff\xff")) {
        return false;
    }
    $ipv4 = inet_ntop(substr($packed, 12, 4));
    return $ipv4 === false ? false : $ipv4;
}}

if (!hm_exists('hm_normalize_server_host')) {
/**
 * Reduce a user-supplied host to a canonical IP or hostname.
 * Rejects transports, userinfo, and whitespace. Unwraps mapped IPv6 and alternate IPv4 spellings.
 * @param mixed $host
 * @return string|false
 */
function hm_normalize_server_host($host) {
    if (!is_string($host)) {
        return false;
    }
    $host = trim($host);
    if ($host === '' || preg_match('/[\s\/\\\\#\?@]/', $host)) {
        return false;
    }
    if (preg_match('/^[a-z][a-z0-9+.-]*:\/\//i', $host)) {
        return false;
    }
    if (str_contains($host, '%')) {
        return false;
    }
    if ($host[0] === '[' && str_ends_with($host, ']')) {
        $host = substr($host, 1, -1);
        if ($host === '') {
            return false;
        }
    }
    $ipv4 = hm_expand_ipv4_literal($host);
    if ($ipv4 !== false) {
        return $ipv4;
    }
    if (str_contains($host, ':')) {
        if (filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) === false) {
            return false;
        }
        $mapped = hm_unwrap_ipv4_mapped($host);
        if ($mapped !== false) {
            return $mapped;
        }
        $packed = inet_pton($host);
        if ($packed === false) {
            return false;
        }
        $canonical = inet_ntop($packed);
        return $canonical === false ? false : $canonical;
    }
    if (str_ends_with($host, '.')) {
        $host = substr($host, 0, -1);
    }
    if ($host === '' || strlen($host) > 253) {
        return false;
    }
    if (!preg_match('/^(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)*[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?$/i', $host)) {
        return false;
    }
    return strtolower($host);
}}

if (!hm_exists('hm_ip_is_link_local')) {
/**
 * Whether an IP is link-local (169.254.0.0/16 or fe80::/10).
 * Always denied for user-supplied targets: cloud instance metadata lives here
 * (see https://owasp.org/www-community/attacks/Server_Side_Request_Forgery).
 * @param string $ip canonical IP
 * @return bool
 */
function hm_ip_is_link_local($ip) {
    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
        $n = unpack('N', pack('N', ip2long($ip)))[1];
        return ($n & 0xFFFF0000) === 0xA9FE0000;
    }
    $packed = @inet_pton($ip);
    if ($packed === false || strlen($packed) !== 16) {
        return false;
    }
    return ord($packed[0]) === 0xFE && (ord($packed[1]) & 0xC0) === 0x80;
}}

if (!hm_exists('hm_classify_ip')) {
/**
 * Classify a canonical IP, after unwrapping mapped and alternate spellings.
 * Public uses PHP FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE.
 * Link-local is separated so it stays denied even when internal servers are allowed.
 * @param string $ip
 * @return string public|internal|link_local
 */
function hm_classify_ip($ip) {
    $ip = hm_normalize_server_host($ip);
    if (!is_string($ip) || filter_var($ip, FILTER_VALIDATE_IP) === false) {
        return 'internal';
    }
    if (hm_ip_is_link_local($ip)) {
        return 'link_local';
    }
    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false) {
        return 'public';
    }
    return 'internal';
}}

if (!hm_exists('hm_server_class_is_allowed')) {
/**
 * Apply the operator policy to an address class.
 * @param string $class result of hm_classify_ip
 * @param object|null $config site config
 * @param bool|null $allow_internal null reads allow_internal_servers; false forces public-only
 * @return bool
 */
function hm_server_class_is_allowed($class, $config = null, $allow_internal = null) {
    if ($class === 'public') {
        return true;
    }
    if ($class === 'link_local') {
        return false;
    }
    if ($class === 'internal') {
        if ($allow_internal === null) {
            $allow_internal = hm_server_target_flag($config, 'allow_internal_servers', false);
        }
        return (bool) $allow_internal;
    }
    return false;
}}

if (!hm_exists('hm_resolve_host_ips')) {
/**
 * Resolve A and AAAA records. Fails closed when nothing resolves.
 * @param string $host
 * @return array
 */
function hm_resolve_host_ips($host) {
    $ips = array();
    if (function_exists('dns_get_record')) {
        $records = @dns_get_record($host, DNS_A + DNS_AAAA);
        if (is_array($records)) {
            foreach ($records as $record) {
                if (!empty($record['ip'])) {
                    $ips[] = $record['ip'];
                }
                if (!empty($record['ipv6'])) {
                    $ips[] = $record['ipv6'];
                }
            }
        }
    }
    if (count($ips) === 0) {
        $resolved = gethostbyname($host);
        if ($resolved !== $host && filter_var($resolved, FILTER_VALIDATE_IP)) {
            $ips[] = $resolved;
        }
    }
    return array_values(array_unique($ips));
}}

if (!hm_exists('hm_server_connect_target')) {
/**
 * Host to store and address to dial for a user-supplied server.
 * IP spellings are rewritten to the canonical address. Hostnames are kept after every resolved address is allowed.
 * @param mixed $host
 * @param object|null $config site config
 * @param bool|null $allow_internal null reads allow_internal_servers; false forces public-only
 * @return array|false keys host and dial
 */
function hm_server_connect_target($host, $config = null, $allow_internal = null) {
    $normalized = hm_normalize_server_host($host);
    if ($normalized === false) {
        return false;
    }
    if (filter_var($normalized, FILTER_VALIDATE_IP)) {
        if (!hm_server_class_is_allowed(hm_classify_ip($normalized), $config, $allow_internal)) {
            return false;
        }
        return array('host' => $normalized, 'dial' => $normalized);
    }
    if ($normalized === 'localhost') {
        if (!hm_server_class_is_allowed('internal', $config, $allow_internal)) {
            return false;
        }
        return array('host' => $normalized, 'dial' => $normalized);
    }
    $ips = hm_resolve_host_ips($normalized);
    if (count($ips) === 0) {
        return false;
    }
    $dial = false;
    foreach ($ips as $ip) {
        $canonical = hm_normalize_server_host($ip);
        if ($canonical === false || !hm_server_class_is_allowed(hm_classify_ip($canonical), $config, $allow_internal)) {
            return false;
        }
        if ($dial === false) {
            $dial = $canonical;
        }
    }
    return array('host' => $normalized, 'dial' => $dial);
}}

if (!hm_exists('hm_server_target_is_allowed')) {
/**
 * Whether a user-supplied host may be dialed.
 * @param mixed $host
 * @param object|null $config site config
 * @param bool|null $allow_internal null reads allow_internal_servers; false forces public-only
 * @return bool
 */
function hm_server_target_is_allowed($host, $config = null, $allow_internal = null) {
    return hm_server_connect_target($host, $config, $allow_internal) !== false;
}}

if (!hm_exists('hm_server_url_is_allowed')) {
/**
 * Whether a user-supplied http(s) URL may be requested.
 * @param mixed $url
 * @param object|null $config site config
 * @param bool|null $allow_internal null reads allow_internal_servers; false forces public-only
 * @return bool
 */
function hm_server_url_is_allowed($url, $config = null, $allow_internal = null) {
    if (!is_string($url)) {
        return false;
    }
    $url = trim($url);
    $parts = parse_url($url);
    if (!is_array($parts) || empty($parts['scheme']) || empty($parts['host'])) {
        return false;
    }
    $scheme = strtolower($parts['scheme']);
    if ($scheme !== 'http' && $scheme !== 'https') {
        return false;
    }
    if (isset($parts['user']) || isset($parts['pass'])) {
        return false;
    }
    if (isset($parts['port']) && !hm_server_port_is_valid($parts['port'])) {
        return false;
    }
    return hm_server_target_is_allowed($parts['host'], $config, $allow_internal);
}}

if (!hm_exists('hm_mailbox_server_is_allowed')) {
/**
 * Whether a saved user mailbox may be dialed. JMAP values are URLs. Other types are hosts.
 * @param array $server server record
 * @return bool
 */
function hm_mailbox_server_is_allowed($server) {
    if (!is_array($server) || empty($server['server']) || !is_string($server['server'])) {
        return false;
    }
    $type = !empty($server['type']) ? $server['type'] : 'imap';
    if ($type === 'jmap') {
        return hm_server_url_is_allowed($server['server'], null);
    }
    if (hm_server_connect_target($server['server'], null) === false) {
        return false;
    }
    if ($type === 'imap' || $type === 'smtp') {
        return hm_server_port_is_valid($server['port'] ?? null);
    }
    return true;
}}

if (!hm_exists('hm_sieve_host_is_allowed')) {
/**
 * Whether a sieve host string (host, host:port, or tls://host:port) may be dialed.
 * @param mixed $value
 * @param object|null $config site config
 * @return bool
 */
function hm_sieve_host_is_allowed($value, $config = null) {
    if (!is_string($value)) {
        return false;
    }
    $value = trim($value);
    if ($value === '') {
        return false;
    }
    if (!str_contains($value, '://')) {
        $value = 'tcp://'.$value;
    }
    $parts = parse_url($value);
    if (!is_array($parts) || empty($parts['host'])) {
        return false;
    }
    $scheme = strtolower($parts['scheme'] ?? '');
    if ($scheme !== 'tcp' && $scheme !== 'tls') {
        return false;
    }
    if (isset($parts['port']) && !hm_server_port_is_valid($parts['port'])) {
        return false;
    }
    return hm_server_target_is_allowed($parts['host'], $config);
}}
