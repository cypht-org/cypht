<?php

/**
 * Spam reporting helpers and models
 * @package modules
 * @subpackage spam_reporting
 */

use ZBateson\MailMimeParser\MailMimeParser;

if (!defined('DEBUG_MODE')) { die(); }

/**
 * Parse a raw MIME message using the existing parser
 * @param string $raw_message
 * @return object|false
 */
if (!hm_exists('spam_reporting_parse_message')) {
function spam_reporting_parse_message($raw_message) {
    if (!trim($raw_message)) {
        return false;
    }
    $parser = new MailMimeParser();
    return $parser->parse($raw_message, false);
}}

/**
 * Collect headers as parser objects (no manual parsing)
 * @param object $message
 * @return array
 */
if (!hm_exists('spam_reporting_collect_headers')) {
function spam_reporting_collect_headers($message) {
    if (!$message || !method_exists($message, 'getAllHeaders')) {
        return array();
    }
    return $message->getAllHeaders();
}}

/**
 * Format raw headers into a single string
 * @param object $message
 * @return string
 */
if (!hm_exists('spam_reporting_format_raw_headers')) {
function spam_reporting_format_raw_headers($message) {
    if (!$message || !method_exists($message, 'getRawHeaders')) {
        return '';
    }
    $lines = array();
    foreach ($message->getRawHeaders() as $header) {
        if (is_array($header) && count($header) >= 2) {
            $lines[] = $header[0] . ': ' . $header[1];
        }
    }
    return implode("\r\n", $lines);
}}

/**
 * Get normalized email addresses from the message From and Reply-To headers.
 * Used to forbid using the report destination as one of these addresses.
 * @param Hm_Spam_Report $report
 * @return array list of lowercase trimmed emails
 */
if (!hm_exists('spam_reporting_message_from_reply_to_emails')) {
function spam_reporting_message_from_reply_to_emails(Hm_Spam_Report $report) {
    $message = $report->get_parsed_message();
    if (!$message || !function_exists('process_address_fld')) {
        return array();
    }
    $emails = array();
    foreach (array('From', 'Reply-To') as $header) {
        $value = $message->getHeaderValue($header, '');
        if (!is_string($value) || trim($value) === '') {
            continue;
        }
        $parsed = process_address_fld($value);
        foreach ($parsed as $addr) {
            if (!empty($addr['email']) && is_string($addr['email'])) {
                $emails[] = strtolower(trim($addr['email']));
            }
        }
    }
    return array_values(array_unique($emails));
}}

/**
 * Generate a stable unique instance id. 16-char hex.
 * @return string
 */
if (!hm_exists('spam_reporting_generate_instance_id')) {
function spam_reporting_generate_instance_id() {
    return bin2hex(random_bytes(8));
}}

/**
 * Whitelist settings to only keys declared in adapter schema.
 * @param array $settings raw stored settings
 * @param Hm_Spam_Report_Target_Interface $adapter
 * @return array
 */
if (!hm_exists('spam_reporting_whitelist_instance_settings')) {
function spam_reporting_whitelist_instance_settings(array $settings, $adapter) {
    $schema = $adapter->get_configuration_schema();
    if (!is_array($schema)) {
        return array();
    }
    $allowed_keys = array_keys($schema);
    $out = array();
    foreach ($allowed_keys as $key) {
        if (array_key_exists($key, $settings)) {
            $out[$key] = $settings[$key];
        }
    }
    return $out;
}}

/**
 * Merge __KEEP__ in submitted settings with current stored values.
 * @param array $submitted_list each item: id, adapter_id, label, settings
 * @param array $current_configs from load_user_target_configurations (have settings)
 * @return array merged list with settings
 */
if (!hm_exists('spam_reporting_merge_keep_settings')) {
function spam_reporting_merge_keep_settings(array $submitted_list, array $current_configs) {
    $by_id = array();
    foreach ($current_configs as $c) {
        $by_id[$c['id']] = $c;
    }
    $out = array();
    foreach ($submitted_list as $entry) {
        $id = isset($entry['id']) ? trim((string) $entry['id']) : '';
        $settings = isset($entry['settings']) && is_array($entry['settings']) ? $entry['settings'] : array();
        if (isset($by_id[$id]['settings'])) {
            foreach ($by_id[$id]['settings'] as $k => $v) {
                if (array_key_exists($k, $settings) && $settings[$k] === '__KEEP__') {
                    $settings[$k] = $v;
                }
            }
        }
        $out[] = array(
            'id' => $id,
            'adapter_id' => isset($entry['adapter_id']) ? trim((string) $entry['adapter_id']) : '',
            'label' => isset($entry['label']) && is_string($entry['label']) ? trim($entry['label']) : '',
            'settings' => $settings
        );
    }
    return $out;
}}

/**
 * Normalize a list of strings from config/catalog
 * @param mixed $input
 * @return array
 */
if (!hm_exists('spam_reporting_normalize_string_list')) {
function spam_reporting_normalize_string_list($input) {
    if (!is_array($input)) {
        return array();
    }
    $out = array();
    foreach ($input as $item) {
        if (!is_string($item)) {
            continue;
        }
        $trimmed = trim($item);
        if ($trimmed !== '') {
            $out[] = $trimmed;
        }
    }
    return array_values(array_unique($out));
}}

/**
 * Load reporting platform catalog from disk
 * @param object $site_config
 * @return array
 */
if (!hm_exists('spam_reporting_load_platform_catalog')) {
function spam_reporting_load_platform_catalog($site_config) {
    $path = $site_config->get('spam_reporting_platforms_file', APP_PATH.'data/spam_report_platforms.json');
    if (!is_string($path) || !trim($path) || !is_file($path)) {
        return array();
    }
    $raw = @file_get_contents($path);
    if (!is_string($raw) || !trim($raw)) {
        return array();
    }
    $decoded = json_decode($raw, true);
    if (!is_array($decoded)) {
        return array();
    }
    $platforms = $decoded;
    if (array_key_exists('platforms', $decoded)) {
        $platforms = $decoded['platforms'];
    }
    if (!is_array($platforms)) {
        return array();
    }
    $clean = array();
    foreach ($platforms as $platform) {
        if (!is_array($platform)) {
            continue;
        }
        $id = $platform['id'] ?? '';
        if (!is_string($id) || !trim($id)) {
            continue;
        }
        $name = $platform['name'] ?? $id;
        $description = $platform['description'] ?? '';
        $platform_id = $platform['platform_id'] ?? $id;
        $clean[] = array(
            'id' => $id,
            'platform_id' => (is_string($platform_id) && trim($platform_id)) ? $platform_id : $id,
            'name' => (is_string($name) && trim($name)) ? $name : $id,
            'description' => is_string($description) ? $description : '',
            'methods' => spam_reporting_normalize_string_list($platform['methods'] ?? array()),
            'required_data' => spam_reporting_normalize_string_list($platform['required_data'] ?? array()),
            'allowed_data' => spam_reporting_normalize_string_list($platform['allowed_data'] ?? array()),
            'never_send' => spam_reporting_normalize_string_list($platform['never_send'] ?? array())
        );
    }
    return $clean;
}}

/**
 * Load provider-to-platform mapping from disk
 * @param object $site_config
 * @return array
 */
if (!hm_exists('spam_reporting_load_provider_mapping')) {
function spam_reporting_load_provider_mapping($site_config) {
    $path = $site_config->get('spam_reporting_provider_mapping_file', APP_PATH.'data/spam_report_provider_mapping.json');
    if (!is_string($path) || !trim($path) || !is_file($path)) {
        return array();
    }
    $raw = @file_get_contents($path);
    if (!is_string($raw) || !trim($raw)) {
        return array();
    }
    $decoded = json_decode($raw, true);
    if (!is_array($decoded) || !array_key_exists('mappings', $decoded)) {
        return array();
    }
    $mappings = $decoded['mappings'];
    if (!is_array($mappings)) {
        return array();
    }
    $clean = array();
    foreach ($mappings as $m) {
        if (!is_array($m) || empty($m['provider_id']) || empty($m['platform_ids'])) {
            continue;
        }
        $provider_id = trim((string) $m['provider_id']);
        $platform_ids = spam_reporting_normalize_string_list($m['platform_ids'] ?? array());
        if (empty($platform_ids)) {
            continue;
        }
        $signals = $m['signals'] ?? array();
        if (!is_array($signals)) {
            $signals = array();
        }
        $received = spam_reporting_normalize_string_list($signals['received'] ?? array());
        $auth_results = spam_reporting_normalize_string_list($signals['auth_results'] ?? array());
        $return_path = spam_reporting_normalize_string_list($signals['return_path'] ?? array());
        $from_domain = spam_reporting_normalize_string_list($signals['from_domain'] ?? array());
        $clean[] = array(
            'provider_id' => $provider_id,
            'provider_name' => (isset($m['provider_name']) && is_string($m['provider_name'])) ? trim($m['provider_name']) : $provider_id,
            'platform_ids' => $platform_ids,
            'signals' => array(
                'received' => $received,
                'auth_results' => $auth_results,
                'return_path' => $return_path,
                'from_domain' => $from_domain
            )
        );
    }
    return $clean;
}}

/**
 * Extract source IP addresses from Received headers (first-hop, closest to sender)
 * Used for IP-based reporting (e.g. AbuseIPDB).
 * @param object $message parsed MIME message
 * @return array of IPv4/IPv6 strings, empty if none found
 */
if (!hm_exists('spam_reporting_extract_source_ips')) {
function spam_reporting_extract_source_ips($message) {
    $ips = array();
    if (!$message || !method_exists($message, 'getRawHeaders')) {
        return $ips;
    }
    $headers = $message->getRawHeaders();
    $received_vals = array();
    foreach ($headers as $h) {
        if (!is_array($h) || count($h) < 2) {
            continue;
        }
        if (strtolower(trim($h[0])) === 'received') {
            $received_vals[] = $h[1];
        }
    }
    // Process in reverse order: last Received is closest to sender (first hop)
    $received_vals = array_reverse($received_vals);
    foreach ($received_vals as $val) {
        // Match [1.2.3.4] or (1.2.3.4) or bare IPv4
        if (preg_match('/\[([0-9a-f.:]+)\]/i', $val, $m) && filter_var($m[1], FILTER_VALIDATE_IP)) {
            $ips[] = $m[1];
            break;
        }
        if (preg_match('/\(([0-9a-f.:]+)\)/i', $val, $m) && filter_var($m[1], FILTER_VALIDATE_IP)) {
            $ips[] = $m[1];
            break;
        }
        if (preg_match('/\b(\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3})\b/', $val, $m) && filter_var($m[1], FILTER_VALIDATE_IP)) {
            $ips[] = $m[1];
            break;
        }
        // IPv6 in brackets
        if (preg_match('/\[([0-9a-f:]+)\]/i', $val, $m) && filter_var($m[1], FILTER_VALIDATE_IP)) {
            $ips[] = $m[1];
            break;
        }
    }
    return array_values(array_unique($ips));
}}

/**
 * Extract domains/hosts from message headers for provider detection
 * Primary: Received, Authentication-Results
 * Secondary: Return-Path, From
 * @param object $message parsed MIME message
 * @return array ['primary' => [...], 'secondary' => [...]]
 */
if (!hm_exists('spam_reporting_extract_header_signals')) {
function spam_reporting_extract_header_signals($message) {
    $primary = array();
    $secondary = array();
    if (!$message || !method_exists($message, 'getRawHeaders')) {
        return array('primary' => $primary, 'secondary' => $secondary);
    }
    $headers = $message->getRawHeaders();
    $received_vals = array();
    $auth_vals = '';
    $return_path_val = '';
    $from_val = '';
    foreach ($headers as $h) {
        if (!is_array($h) || count($h) < 2) {
            continue;
        }
        $name = strtolower(trim($h[0]));
        $value = $h[1];
        if ($name === 'received') {
            $received_vals[] = $value;
        } elseif ($name === 'authentication-results') {
            $auth_vals .= ' ' . $value;
        } elseif ($name === 'return-path') {
            $return_path_val = $value;
        } elseif ($name === 'from') {
            $from_val = $value;
        }
    }
    foreach ($received_vals as $val) {
        // Received headers are free-form; regex is used here intentionally
        // to extract by/from hostnames in a best-effort manner.
        if (preg_match_all('/\b(?:by|from)\s+([a-zA-Z0-9][-a-zA-Z0-9.]*\.[a-zA-Z]{2,})/i', $val, $m)) {
            foreach ($m[1] as $host) {
                $h = strtolower(trim($host));
                if ($h && !in_array($h, $primary, true)) {
                    $primary[] = $h;
                }
            }
        }
    }
    if ($auth_vals !== '' && preg_match_all('/([a-zA-Z0-9][-a-zA-Z0-9.]*\.[a-zA-Z]{2,})/i', $auth_vals, $m)) {
        foreach ($m[1] as $domain) {
            $d = strtolower(trim($domain));
            if ($d && strlen($d) > 4 && !in_array($d, $primary, true)) {
                $primary[] = $d;
            }
        }
    }
    if ($return_path_val !== '' && preg_match('/@([a-zA-Z0-9][-a-zA-Z0-9.]*\.[a-zA-Z]{2,})/i', $return_path_val, $m)) {
        $d = strtolower(trim($m[1]));
        if ($d && !in_array($d, $secondary, true)) {
            $secondary[] = $d;
        }
    }
    if ($from_val !== '' && preg_match_all('/@([a-zA-Z0-9][-a-zA-Z0-9.]*\.[a-zA-Z]{2,})/i', $from_val, $m)) {
        foreach ($m[1] as $domain) {
            $d = strtolower(trim($domain));
            if ($d && !in_array($d, $secondary, true)) {
                $secondary[] = $d;
            }
        }
    }
    return array('primary' => $primary, 'secondary' => $secondary);
}}

/**
 * Check if a domain/host matches any of the signal patterns (substring or exact)
 * @param string $value
 * @param array $patterns
 * @return bool
 */
if (!hm_exists('spam_reporting_signal_matches')) {
function spam_reporting_signal_matches($value, array $patterns) {
    $v = strtolower(trim($value));
    if ($v === '') {
        return false;
    }
    foreach ($patterns as $p) {
        $p = strtolower(trim($p));
        if ($p === '' || strlen($p) < 3) {
            continue;
        }
        if ($v === $p || strpos($v, $p) !== false || strpos($p, $v) !== false) {
            return true;
        }
    }
    return false;
}}

/**
 * Detect providers from message headers using mapping
 * Returns array of [provider_id, provider_name, confidence, platform_ids]
 * @param object $message parsed MIME message
 * @param array $mappings from spam_reporting_load_provider_mapping
 * @return array
 */
if (!hm_exists('spam_reporting_detect_providers')) {
function spam_reporting_detect_providers($message, array $mappings) {
    $signals = spam_reporting_extract_header_signals($message);
    $primary = $signals['primary'];
    $secondary = $signals['secondary'];
    $detected = array();
    foreach ($mappings as $m) {
        $score = 0;
        $recv = $m['signals']['received'] ?? array();
        $auth = $m['signals']['auth_results'] ?? array();
        $rp = $m['signals']['return_path'] ?? array();
        $fd = $m['signals']['from_domain'] ?? array();
        foreach ($primary as $val) {
            if (spam_reporting_signal_matches($val, $recv) || spam_reporting_signal_matches($val, $auth)) {
                $score += 2;
                break;
            }
        }
        foreach ($secondary as $val) {
            if (spam_reporting_signal_matches($val, $rp) || spam_reporting_signal_matches($val, $fd)) {
                $score += 1;
                break;
            }
        }
        if ($score > 0) {
            $detected[] = array(
                'provider_id' => $m['provider_id'],
                'provider_name' => $m['provider_name'],
                'confidence' => $score,
                'platform_ids' => $m['platform_ids']
            );
        }
    }
    usort($detected, function ($a, $b) {
        return ($b['confidence'] <=> $a['confidence']) ?: strcasecmp($a['provider_name'], $b['provider_name']);
    });
    return $detected;
}}

/**
 * Resolve suggested platform_ids to target_ids from available targets
 * @param array $detected_providers from spam_reporting_detect_providers
 * @param array $available_targets each with id, platform_id
 * @return array of target ids in suggestion order
 */
if (!hm_exists('spam_reporting_suggested_target_ids')) {
function spam_reporting_suggested_target_ids(array $detected_providers, array $available_targets) {
    $platform_to_target = array();
    foreach ($available_targets as $t) {
        $pid = isset($t['platform_id']) ? trim((string) $t['platform_id']) : '';
        if ($pid !== '') {
            $platform_to_target[$pid] = $t['id'];
        }
    }
    $suggested = array();
    $seen = array();
    foreach ($detected_providers as $dp) {
        foreach ($dp['platform_ids'] as $platform_id) {
            if (isset($platform_to_target[$platform_id]) && !isset($seen[$platform_to_target[$platform_id]])) {
                $suggested[] = $platform_to_target[$platform_id];
                $seen[$platform_to_target[$platform_id]] = true;
            }
        }
    }
    return $suggested;
}}

/**
 * Detect user mailbox provider from IMAP server host (best-effort)
 * @param string|null $server_id
 * @param object $site_config
 * @return string|null provider_id or null
 */
if (!hm_exists('spam_reporting_detect_user_mailbox_provider')) {
function spam_reporting_detect_user_mailbox_provider($server_id, $site_config) {
    if ($server_id === null || $server_id === '' || !class_exists('Hm_IMAP_List')) {
        return null;
    }
    $server = Hm_IMAP_List::get($server_id, false);
    if (!is_array($server) || empty($server['server'])) {
        return null;
    }
    $host = strtolower(trim($server['server']));
    if ($host === '') {
        return null;
    }
    $mappings = spam_reporting_load_provider_mapping($site_config);
    foreach ($mappings as $m) {
        $recv = $m['signals']['received'] ?? array();
        $auth = $m['signals']['auth_results'] ?? array();
        if (spam_reporting_signal_matches($host, $recv) || spam_reporting_signal_matches($host, $auth)) {
            return $m['provider_id'];
        }
    }
    return null;
}}

/**
 * Build the spam report modal markup
 * @param callable $trans translation callback
 * @return string
 */
if (!hm_exists('spam_reporting_modal_markup')) {
function spam_reporting_modal_markup($trans) {
    $modal = '<div class="modal fade" id="spamReportModal" tabindex="-1" aria-hidden="true">';
    $modal .= '<div class="modal-dialog modal-lg modal-dialog-scrollable">';
    $modal .= '<div class="modal-content">';
    $modal .= '<div class="modal-header">';
    $modal .= '<h5 class="modal-title">'.$trans('Report spam').'</h5>';
    $modal .= '<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="'.$trans('Close').'"></button>';
    $modal .= '</div>';
    $modal .= '<div class="modal-body">';
    $modal .= '<div class="spam-report-targets mb-3">';
    $modal .= '<div class="fw-bold mb-2">'.$trans('Targets').'</div>';
    $modal .= '<div class="spam-report-suggestion-text text-muted small mb-2"></div>';
    $modal .= '<div class="spam-report-self-report-note text-warning small mb-2"></div>';
    $modal .= '<select class="form-select spam-report-target-select" disabled="disabled"></select>';
    $modal .= '<div class="spam-report-targets-empty text-muted mt-2">'.$trans('No reporting targets are configured by the administrator.').'</div>';
    $modal .= '<div class="spam-report-data-summary mt-2 p-2 bg-light rounded small d-none">';
    $modal .= '<div class="fw-semibold mb-1">'.$trans('What will be sent').'</div>';
    $modal .= '<ul class="spam-report-data-checklist list-unstyled mb-0"></ul>';
    $modal .= '</div>';
    $modal .= '</div>';
    $modal .= '<div class="spam-report-platforms mb-3">';
    $modal .= '<div class="fw-bold mb-2">'.$trans('Reporting platforms').'</div>';
    $modal .= '<div class="spam-report-platforms-empty text-muted mt-2">'.$trans('No platform catalog loaded').'</div>';
    $modal .= '<ul class="list-group spam-report-platforms-list"></ul>';
    $modal .= '</div>';
    $modal .= '<div class="spam-report-preview">';
    $modal .= '<div class="fw-bold mb-2">'.$trans('Preview').'</div>';
    $modal .= '<label class="form-label">'.$trans('Headers').'</label>';
    $modal .= '<textarea class="form-control spam-report-headers" rows="8"></textarea>';
    $modal .= '<label class="form-label mt-3">'.$trans('Plain text body').'</label>';
    $modal .= '<textarea class="form-control spam-report-body" rows="8"></textarea>';
    $modal .= '<label class="form-label mt-3">'.$trans('Notes').'</label>';
    $modal .= '<textarea class="form-control spam-report-notes" rows="3"></textarea>';
    $modal .= '<div class="form-check mt-3">';
    $modal .= '<input class="form-check-input spam-report-toggle-html" type="checkbox" id="spam_report_show_html">';
    $modal .= '<label class="form-check-label" for="spam_report_show_html">'.$trans('Show HTML body').'</label>';
    $modal .= '</div>';
    $modal .= '<div class="spam-report-html mt-2 d-none">';
    $modal .= '<label class="form-label">'.$trans('HTML body').'</label>';
    $modal .= '<textarea class="form-control spam-report-body-html" rows="8"></textarea>';
    $modal .= '</div>';
    $modal .= '</div>';
    $modal .= '</div>';
    $modal .= '<div class="modal-footer">';
    $modal .= '<div class="spam-report-status text-muted me-auto"></div>';
    $modal .= '<button type="button" class="btn btn-outline-secondary spam-report-send" disabled="disabled">'.$trans('Send report').'</button>';
    $modal .= '<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">'.$trans('Close').'</button>';
    $modal .= '</div>';
    $modal .= '</div></div></div>';
    return $modal;
}}

/**
 * Build a system SMTP mailbox for spam reporting
 * @param object $site_config
 * @return array [mailbox, server_id]
 */
if (!hm_exists('spam_reporting_get_smtp_mailbox')) {
function spam_reporting_get_smtp_mailbox($site_config) {
    $server = $site_config->get('spam_reporting_smtp_server', '');
    if (!trim((string) $server)) {
        return array(false, false);
    }
    $smtp = array(
        'name' => $site_config->get('spam_reporting_smtp_name', 'Spam Reporting'),
        'server' => $server,
        'type' => 'smtp',
        'hide' => true,
        'port' => $site_config->get('spam_reporting_smtp_port', 587),
        'user' => $site_config->get('spam_reporting_smtp_user', ''),
        'pass' => $site_config->get('spam_reporting_smtp_pass', ''),
        'tls' => $site_config->get('spam_reporting_smtp_tls', true),
    );
    if ($site_config->get('spam_reporting_smtp_no_auth', false)) {
        $smtp['no_auth'] = true;
    }
    $server_id = Hm_SMTP_List::add($smtp, false);
    $mailbox = Hm_SMTP_List::connect($server_id, false, $smtp['user'], $smtp['pass'], false);
    return array($mailbox, $server_id);
}}

/**
 * Enforce a per-user rate limit
 * @param object $session
 * @param object $site_config
 * @return array [allowed, retry_after]
 */
if (!hm_exists('spam_reporting_rate_limit')) {
function spam_reporting_rate_limit($session, $site_config, $record = false) {
    $limit = (int) $site_config->get('spam_reporting_rate_limit_count', 5);
    $window = (int) $site_config->get('spam_reporting_rate_limit_window', 3600);
    $now = time();
    if ($limit <= 0 || $window <= 0) {
        return array(true, 0);
    }
    $log = $session->get('spam_reporting_rate_log', array());
    $log = array_values(array_filter($log, function($ts) use ($now, $window) {
        return is_int($ts) && ($now - $ts) < $window;
    }));
    if (count($log) >= $limit) {
        $retry_after = $window - ($now - $log[0]);
        return array(false, max(0, $retry_after));
    }
    if ($record) {
        $log[] = $now;
        $session->set('spam_reporting_rate_log', $log);
    }
    return array(true, 0);
}}

/**
 * Build a spam report from a mailbox/UID
 * @param object $mailbox
 * @param string $folder
 * @param string|int $uid
 * @param array $metadata
 * @return Hm_Spam_Report|false
 */
if (!hm_exists('spam_reporting_build_report')) {
function spam_reporting_build_report($mailbox, $folder, $uid, array $metadata = array()) {
    if (!$mailbox) {
        return false;
    }
    return Hm_Spam_Report::from_mailbox($mailbox, $folder, $uid, $metadata);
}}
