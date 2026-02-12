<?php

/**
 * Spam reporting helpers and models
 * @package modules
 * @subpackage spam_reporting
 */

use ZBateson\MailMimeParser\MailMimeParser;

if (!defined('DEBUG_MODE')) { die(); }

/**
 * Internal spam report model
 * @subpackage spam_reporting/lib
 */
class Hm_Spam_Report {
    public $raw_message;
    public $headers;
    public $body_text;
    public $body_html;
    public $source_ip;
    public $extracted_urls;
    public $metadata;

    private $message;

    public function __construct($raw_message, $message, array $metadata = array()) {
        $this->raw_message = $raw_message;
        $this->message = $message;
        $this->headers = spam_reporting_collect_headers($message);
        $this->body_text = $message->getTextContent();
        $this->body_html = $message->getHtmlContent();
        $this->source_ip = null;
        $this->extracted_urls = array();
        $this->metadata = $metadata;
    }

    public static function from_raw_message($raw_message, array $metadata = array()) {
        $message = spam_reporting_parse_message($raw_message);
        if (!$message) {
            return false;
        }
        return new self($raw_message, $message, $metadata);
    }

    public static function from_mailbox($mailbox, $folder, $uid, array $metadata = array()) {
        $raw_message = $mailbox->get_message_content($folder, $uid, 0);
        if (!$raw_message) {
            return false;
        }
        $metadata = array_merge($metadata, array(
            'folder' => $folder,
            'uid' => $uid
        ));
        return self::from_raw_message($raw_message, $metadata);
    }

    public function get_parsed_message() {
        return $this->message;
    }

    public function get_raw_headers_string() {
        return spam_reporting_format_raw_headers($this->message);
    }

    /**
     * Get source IPs extracted from Received headers (first-hop)
     * @return array of IP strings
     */
    public function get_source_ips() {
        if ($this->message) {
            return spam_reporting_extract_source_ips($this->message);
        }
        return array();
    }
}

/**
 * Spam report payload container
 * @subpackage spam_reporting/lib
 */
class Hm_Spam_Report_Payload {
    public $content_type;
    public $to;
    public $subject;
    public $body;
    public $headers;
    public $raw_message;

    public function __construct($to, $subject, $body, $content_type = 'text/plain', array $headers = array(), $raw_message = '') {
        $this->to = $to;
        $this->subject = $subject;
        $this->body = $body;
        $this->content_type = $content_type;
        $this->headers = $headers;
        $this->raw_message = $raw_message;
    }
}

/**
 * Delivery context for report targets
 * @subpackage spam_reporting/lib
 */
class Hm_Spam_Report_Delivery_Context {
    public $site_config;
    public $user_config;
    public $session;
    /** @var array User-provided instance configuration (Phase B); empty when not using user config */
    public $instance_config = array();

    public function __construct($site_config, $user_config, $session) {
        $this->site_config = $site_config;
        $this->user_config = $user_config;
        $this->session = $session;
    }

    /** @return array */
    public function get_instance_config() {
        return is_array($this->instance_config) ? $this->instance_config : array();
    }
}

/**
 * Delivery result container
 * @subpackage spam_reporting/lib
 */
class Hm_Spam_Report_Result {
    public $ok;
    public $message;
    public $details;

    public function __construct($ok, $message = '', array $details = array()) {
        $this->ok = (bool) $ok;
        $this->message = $message;
        $this->details = $details;
    }
}

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
 * Get normalized email addresses from the message From and Reply-To headers (Phase E).
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
 * Adapter type id → PHP class name (internal; not exposed in config).
 * Admins use only symbolic type IDs via spam_reporting_allowed_target_types.
 * @return array<string, string> type_id => class name
 */
if (!hm_exists('spam_reporting_get_adapter_type_map')) {
function spam_reporting_get_adapter_type_map() {
    return array(
        'abuseipdb' => 'Hm_Spam_Report_AbuseIPDB_Target',
        'email_target' => 'Hm_Spam_Report_Email_Target',
    );
}}

/**
 * Build allowed adapter type IDs: use new config key if set, else derive from legacy targets.
 * @param object $site_config
 * @return array list of adapter type ids
 */
if (!hm_exists('spam_reporting_get_allowed_target_types')) {
function spam_reporting_get_allowed_target_types($site_config) {
    $allowed = $site_config->get('spam_reporting_allowed_target_types', null);
    if (is_array($allowed)) {
        return array_values(array_filter($allowed, 'is_string'));
    }
    $targets = $site_config->get('spam_reporting_targets');
    if (!is_array($targets)) {
        return array();
    }
    $class_to_type = array(
        'Hm_Spam_Report_AbuseIPDB_Target' => 'abuseipdb',
        'Hm_Spam_Report_Email_Target' => 'email_target',
    );
    $types = array();
    foreach ($targets as $entry) {
        $class = null;
        if (is_string($entry) && $entry !== '') {
            $class = $entry;
        } elseif (is_array($entry) && isset($entry['class']) && is_string($entry['class'])) {
            $class = $entry['class'];
        }
        if ($class && isset($class_to_type[$class]) && !in_array($class_to_type[$class], $types, true)) {
            $types[] = $class_to_type[$class];
        }
    }
    return $types;
}}

/**
 * Build a target registry from config.
 * Uses spam_reporting_allowed_target_types when set (Phase A: type-only, no secrets);
 * otherwise falls back to legacy spam_reporting_targets (full config, deprecated).
 * @param object $site_config
 * @return Hm_Spam_Report_Targets_Registry
 */
if (!hm_exists('spam_reporting_build_registry')) {
function spam_reporting_build_registry($site_config) {
    $registry = new Hm_Spam_Report_Targets_Registry();
    $allowed = $site_config->get('spam_reporting_allowed_target_types', null);
    $use_legacy = !is_array($allowed);

    if ($use_legacy) {
        $targets = $site_config->get('spam_reporting_targets');
        if (is_array($targets)) {
            foreach ($targets as $class_name) {
                if (is_string($class_name) && class_exists($class_name)) {
                    $registry->register_target(new $class_name());
                } elseif (is_array($class_name) && array_key_exists('class', $class_name)) {
                    $class = $class_name['class'];
                    if (is_string($class) && class_exists($class)) {
                        $config = array_merge($class_name, array('_site_config' => $site_config));
                        $ref = new ReflectionClass($class);
                        $ctor = $ref->getConstructor();
                        if ($ctor && $ctor->getNumberOfParameters() > 0) {
                            $registry->register_target($ref->newInstance($config));
                        } else {
                            $target = $ref->newInstance();
                            if (method_exists($target, 'configure')) {
                                $target->configure($config);
                            }
                            $registry->register_target($target);
                        }
                    }
                }
            }
        }
        return $registry;
    }

    $type_map = spam_reporting_get_adapter_type_map();
    foreach ($allowed as $type_id) {
        if (!is_string($type_id) || $type_id === '' || !isset($type_map[$type_id])) {
            continue;
        }
        $class = $type_map[$type_id];
        if (!class_exists($class)) {
            continue;
        }
        $ref = new ReflectionClass($class);
        $ctor = $ref->getConstructor();
        if ($ctor && $ctor->getNumberOfParameters() > 0) {
            $target = $ref->newInstance(array('_site_config' => $site_config));
        } else {
            $target = $ref->newInstance();
            if (method_exists($target, 'configure')) {
                $target->configure(array('_site_config' => $site_config));
            }
        }
        if ($target instanceof Hm_Spam_Report_Target_Interface) {
            $registry->register_target($target);
        }
    }
    return $registry;
}}

/**
 * Generate a stable unique instance id (Phase C). 16-char hex.
 * @return string
 */
if (!hm_exists('spam_reporting_generate_instance_id')) {
function spam_reporting_generate_instance_id() {
    return bin2hex(random_bytes(8));
}}

/**
 * Whitelist settings to only keys declared in adapter schema (Phase C).
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
 * Load and normalize user target configurations; whitelist settings by adapter schema (Phase C).
 * @param object $site_config
 * @param object $user_config
 * @return array list of array('id' => string, 'adapter_id' => string, 'label' => string, 'settings' => array)
 */
if (!hm_exists('spam_reporting_load_user_target_configurations')) {
function spam_reporting_load_user_target_configurations($site_config, $user_config) {
    $raw = $user_config->get('spam_reporting_target_configurations', array());
    if (!is_array($raw)) {
        return array();
    }
    $registry = spam_reporting_build_registry($site_config);
    $out = array();
    foreach ($raw as $entry) {
        if (!is_array($entry) || empty($entry['id']) || empty($entry['adapter_id'])) {
            continue;
        }
        $id = trim((string) $entry['id']);
        $adapter_id = trim((string) $entry['adapter_id']);
        $adapter = $registry->get($adapter_id);
        if (!$adapter instanceof Hm_Spam_Report_Target_Interface) {
            continue;
        }
        $label = isset($entry['label']) && is_string($entry['label']) ? trim($entry['label']) : $adapter->label();
        $settings = isset($entry['settings']) && is_array($entry['settings']) ? $entry['settings'] : array();
        $settings = spam_reporting_whitelist_instance_settings($settings, $adapter);
        $out[] = array(
            'id' => $id,
            'adapter_id' => $adapter_id,
            'label' => $label,
            'settings' => $settings
        );
    }
    return $out;
}}

/**
 * Build one effective-target descriptor (public fields only); adapter/instance_config added by caller (Phase C).
 */
if (!hm_exists('spam_reporting_build_effective_descriptor')) {
function spam_reporting_build_effective_descriptor($adapter, $id, $label, array $instance_config = array()) {
    $platform_id = $adapter->platform_id();
    if (($platform_id === '' || $platform_id === null) && isset($instance_config['platform_id']) && is_string($instance_config['platform_id']) && trim($instance_config['platform_id']) !== '') {
        $platform_id = trim($instance_config['platform_id']);
    }
    $t = array(
        'id' => $id,
        'label' => $label,
        'platform_id' => $platform_id,
        'capabilities' => $adapter->capabilities(),
        'requirements' => $adapter->requirements()
    );
    if (method_exists($adapter, 'is_api_target') && $adapter->is_api_target()) {
        $t['is_api_target'] = true;
        $t['api_service_name'] = method_exists($adapter, 'get_api_service_name')
            ? $adapter->get_api_service_name() : '';
    }
    return $t;
}}

/**
 * Build effective targets for the current user (Phase C).
 * Legacy fallback: one virtual instance per allowed type when no user configs.
 * When $report is set, only includes targets where is_available(report, user_config, instance_config).
 * Descriptors include adapter and instance_config (server-side only; do not send to client).
 * @param object $site_config
 * @param object $user_config
 * @param Hm_Spam_Report|null $report
 * @return array list of full descriptors
 */
if (!hm_exists('spam_reporting_get_effective_targets')) {
function spam_reporting_get_effective_targets($site_config, $user_config, $report = null) {
    $registry = spam_reporting_build_registry($site_config);
    $configs = spam_reporting_load_user_target_configurations($site_config, $user_config);
    $list = array();

    if (empty($configs)) {
        foreach ($registry->all_targets() as $adapter) {
            $descriptor = spam_reporting_build_effective_descriptor($adapter, $adapter->id(), $adapter->label(), array());
            $descriptor['adapter'] = $adapter;
            $descriptor['instance_config'] = array();
            if ($report !== null && !$adapter->is_available($report, $user_config, array())) {
                continue;
            }
            $list[] = $descriptor;
        }
        return $list;
    }

    foreach ($configs as $c) {
        $adapter = $registry->get($c['adapter_id']);
        if (!$adapter instanceof Hm_Spam_Report_Target_Interface) {
            continue;
        }
        $instance_config = $c['settings'];
        if ($report !== null && !$adapter->is_available($report, $user_config, $instance_config)) {
            continue;
        }
        $descriptor = spam_reporting_build_effective_descriptor($adapter, $c['id'], $c['label'], $instance_config);
        $descriptor['adapter'] = $adapter;
        $descriptor['instance_config'] = $instance_config;
        $list[] = $descriptor;
    }
    return $list;
}}

/**
 * Strip server-only fields for UI (Phase C). Never send adapter or instance_config to client.
 * @param array $effective_targets
 * @return array public descriptors only
 */
if (!hm_exists('spam_reporting_effective_targets_to_public_descriptors')) {
function spam_reporting_effective_targets_to_public_descriptors(array $effective_targets) {
    $out = array();
    $public_keys = array('id', 'label', 'platform_id', 'capabilities', 'requirements', 'is_api_target', 'api_service_name');
    foreach ($effective_targets as $d) {
        if (!is_array($d)) {
            continue;
        }
        $row = array();
        foreach ($public_keys as $k) {
            if (array_key_exists($k, $d)) {
                $row[$k] = $d[$k];
            }
        }
        $out[] = $row;
    }
    return $out;
}}

/**
 * Resolve target_id to (adapter, instance_config) for send (Phase C).
 * @param object $site_config
 * @param object $user_config
 * @param string $target_id
 * @return array [adapter|null, instance_config]
 */
if (!hm_exists('spam_reporting_resolve_target_id')) {
function spam_reporting_resolve_target_id($site_config, $user_config, $target_id) {
    if (!is_string($target_id) || $target_id === '') {
        return array(null, array());
    }
    $targets = spam_reporting_get_effective_targets($site_config, $user_config, null);
    foreach ($targets as $d) {
        if (isset($d['id']) && $d['id'] === $target_id && isset($d['adapter'], $d['instance_config'])) {
            return array($d['adapter'], $d['instance_config']);
        }
    }
    return array(null, array());
}}

/**
 * Configs for settings UI (Phase D): id, adapter_id, label, adapter_type_label, settings_safe.
 * settings_safe = settings with secret keys removed (never send secrets to client).
 * @param object $site_config
 * @param object $user_config
 * @return array
 */
if (!hm_exists('spam_reporting_settings_configs_for_ui')) {
function spam_reporting_settings_configs_for_ui($site_config, $user_config) {
    $configs = spam_reporting_load_user_target_configurations($site_config, $user_config);
    $registry = spam_reporting_build_registry($site_config);
    $out = array();
    foreach ($configs as $c) {
        $adapter = $registry->get($c['adapter_id']);
        if (!$adapter instanceof Hm_Spam_Report_Target_Interface) {
            continue;
        }
        $schema = $adapter->get_configuration_schema();
        $settings_safe = array();
        $settings_form = array();
        if (is_array($schema)) {
            foreach ($schema as $key => $meta) {
                $is_secret = isset($meta['type']) && $meta['type'] === 'secret';
                if ($is_secret) {
                    $settings_form[$key] = '__KEEP__';
                } else {
                    $v = isset($c['settings'][$key]) ? $c['settings'][$key] : '';
                    $settings_safe[$key] = $v;
                    $settings_form[$key] = $v;
                }
            }
        }
        $out[] = array(
            'id' => $c['id'],
            'adapter_id' => $c['adapter_id'],
            'label' => $c['label'],
            'adapter_type_label' => $adapter->label(),
            'settings_safe' => $settings_safe,
            'settings_form' => $settings_form
        );
    }
    return $out;
}}

/**
 * Adapter types for settings UI (Phase D): adapter_id, label, schema (no secret values).
 * @param object $site_config
 * @return array
 */
if (!hm_exists('spam_reporting_settings_adapter_types')) {
function spam_reporting_settings_adapter_types($site_config) {
    $registry = spam_reporting_build_registry($site_config);
    $out = array();
    foreach ($registry->all_targets() as $adapter) {
        $schema = $adapter->get_configuration_schema();
        if (!is_array($schema) || empty($schema)) {
            continue;
        }
        $out[] = array(
            'adapter_id' => $adapter->id(),
            'label' => $adapter->label(),
            'schema' => $schema
        );
    }
    return $out;
}}

/**
 * Merge __KEEP__ in submitted settings with current stored values (Phase D).
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
 * Validate and normalize one config entry for save (Phase D). Returns [ok, errors, normalized_entry].
 * @param array $entry merged entry (id, adapter_id, label, settings)
 * @param object $site_config
 * @return array [bool, array of strings, array|null]
 */
if (!hm_exists('spam_reporting_validate_config_entry')) {
function spam_reporting_validate_config_entry(array $entry, $site_config) {
    $errors = array();
    $registry = spam_reporting_build_registry($site_config);
    $adapter = $registry->get($entry['adapter_id'] ?? '');
    if (!$adapter instanceof Hm_Spam_Report_Target_Interface) {
        $errors[] = 'Invalid adapter type';
        return array(false, $errors, null);
    }
    $label = $entry['label'] ?? '';
    if (!is_string($label) || trim($label) === '') {
        $errors[] = 'Label is required';
    }
    $schema = $adapter->get_configuration_schema();
    $settings = isset($entry['settings']) && is_array($entry['settings']) ? $entry['settings'] : array();
    $settings = spam_reporting_whitelist_instance_settings($settings, $adapter);
    if (is_array($schema)) {
        foreach ($schema as $key => $meta) {
            if (!empty($meta['required'])) {
                $val = isset($settings[$key]) ? $settings[$key] : '';
                if (!is_string($val)) {
                    $val = '';
                }
                if (trim($val) === '') {
                    $errors[] = ($meta['label'] ?? $key) . ' is required';
                }
            }
        }
    }
    if (!empty($errors)) {
        return array(false, $errors, null);
    }
    return array(true, array(), array(
        'id' => $entry['id'] !== '' ? $entry['id'] : spam_reporting_generate_instance_id(),
        'adapter_id' => $entry['adapter_id'],
        'label' => trim($label),
        'settings' => $settings
    ));
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
 * Get platforms available for user settings (from loaded targets + catalog)
 * Used to build dynamic per-platform toggles in Settings.
 * @param object $site_config
 * @return array [['platform_id' => string, 'name' => string], ...]
 */
if (!hm_exists('spam_reporting_get_available_platforms_for_settings')) {
function spam_reporting_get_available_platforms_for_settings($site_config) {
    $registry = spam_reporting_build_registry($site_config);
    $catalog = spam_reporting_load_platform_catalog($site_config);
    $by_id = array();
    foreach ($catalog as $p) {
        $pid = $p['platform_id'] ?? $p['id'] ?? '';
        if (is_string($pid) && trim($pid)) {
            $by_id[$pid] = $p['name'] ?? $pid;
        }
    }
    $seen = array();
    $out = array();
    foreach ($registry->all_targets() as $target) {
        $pid = $target->platform_id();
        if ($pid && !isset($seen[$pid])) {
            $seen[$pid] = true;
            $out[] = array(
                'platform_id' => $pid,
                'name' => isset($by_id[$pid]) ? $by_id[$pid] : $target->label()
            );
        }
    }
    return $out;
}}

/**
 * Filter targets by user consent (enabled + allowed_platforms)
 * @param array $targets from registry
 * @param object $user_config
 * @return array filtered targets
 */
if (!hm_exists('spam_reporting_filter_targets_by_user_settings')) {
function spam_reporting_filter_targets_by_user_settings(array $targets, $user_config) {
    $enabled = $user_config->get('spam_reporting_enabled_setting', false);
    if (!$enabled) {
        return array();
    }
    $allowed = $user_config->get('spam_reporting_allowed_platforms_setting', array());
    if (!is_array($allowed)) {
        $allowed = array();
    }
    if (empty($allowed)) {
        return array();
    }
    $out = array();
    foreach ($targets as $t) {
        $pid = is_array($t) ? ($t['platform_id'] ?? '') : $t->platform_id();
        // Allow targets with no platform_id (e.g. Email adapter instances) so they appear in the modal
        if ($pid === '' || $pid === null) {
            $out[] = $t;
            continue;
        }
        if (in_array($pid, $allowed, true)) {
            $out[] = $t;
        }
    }
    return $out;
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
