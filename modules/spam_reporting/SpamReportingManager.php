<?php

/**
 * Central manager for spam reporting: adapters, targets, settings UI, send.
 * Single entry point; no registry or descriptor layers.
 * @package modules
 * @subpackage spam_reporting
 */

if (!defined('DEBUG_MODE')) { die(); }

class Hm_Spam_Reporting_Manager {

    /** @var object */
    private $site_config;

    /** @var object */
    private $user_config;

    /** @var array adapter_id => Hm_Spam_Report_Target_Interface (lazy-built) */
    private $adapters = null;

    /** Adapter type id => PHP class name */
    private static $adapter_type_map = array(
        'abuseipdb' => Hm_Spam_Report_AbuseIPDB_Target::class,
        'email_target' => Hm_Spam_Report_Email_Target::class,
        'spamcop_email' => Hm_Spam_Report_SpamCop_Email_Target::class,
    );

    public function __construct($site_config, $user_config) {
        $this->site_config = $site_config;
        $this->user_config = $user_config;
    }

    /**
     * Build adapters from spam_reporting_allowed_target_types only.
     * Legacy configuration (spam_reporting_targets) has been removed.
     */
    private function buildAdapters() {
        if ($this->adapters !== null) {
            return;
        }
        $this->adapters = array();
        $allowed = $this->site_config->get('spam_reporting_allowed_target_types', array());
        if (!is_array($allowed)) {
            return;
        }
        foreach ($allowed as $type_id) {
            if (!is_string($type_id) || $type_id === '' || !isset(self::$adapter_type_map[$type_id])) {
                continue;
            }
            $class = self::$adapter_type_map[$type_id];
            if (!class_exists($class)) {
                continue;
            }
            $config = array('_site_config' => $this->site_config);
            $target = $this->instantiateAdapter($class, $config);
            if ($target instanceof Hm_Spam_Report_Target_Interface) {
                $this->adapters[$target->id()] = $target;
            }
        }
    }

    /**
     * @param string $class
     * @param array $config
     * @return object|null
     */
    private function instantiateAdapter($class, array $config) {
        $ref = new ReflectionClass($class);
        $ctor = $ref->getConstructor();
        if ($ctor && $ctor->getNumberOfParameters() > 0) {
            return $ref->newInstance($config);
        }
        $target = $ref->newInstance();
        if (method_exists($target, 'configure')) {
            $target->configure($config);
        }
        return $target;
    }

    /**
     * Get adapter by adapter_id (e.g. abuseipdb, email_target).
     * @param string $adapter_id
     * @return Hm_Spam_Report_Target_Interface|null
     */
    private function getAdapter($adapter_id) {
        $this->buildAdapters();
        if (isset($this->adapters[$adapter_id]) && $this->adapters[$adapter_id] instanceof Hm_Spam_Report_Target_Interface) {
            return $this->adapters[$adapter_id];
        }
        return null;
    }

    /**
     * @return array list of Hm_Spam_Report_Target_Interface
     */
    private function getAllAdapters() {
        $this->buildAdapters();
        return array_values($this->adapters);
    }

    /**
     * Load and normalize user target configurations; whitelist settings by adapter schema.
     * @return array list of array('id' => string, 'adapter_id' => string, 'label' => string, 'settings' => array)
     */
    public function loadUserTargetConfigurations() {
        $raw = $this->user_config->get('spam_reporting_target_configurations', array());
        if (!is_array($raw)) {
            return array();
        }
        $out = array();
        foreach ($raw as $entry) {
            if (!is_array($entry) || empty($entry['id']) || empty($entry['adapter_id'])) {
                continue;
            }
            $id = trim((string) $entry['id']);
            $adapter_id = trim((string) $entry['adapter_id']);
            $adapter = $this->getAdapter($adapter_id);
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
    }

    /**
     * Build one target row (public fields + adapter and instance_config for internal use).
     */
    private function buildTargetRow($adapter, $id, $label, array $instance_config = array()) {
        $platform_id = $adapter->platform_id();
        if (($platform_id === '' || $platform_id === null) && isset($instance_config['platform_id']) && is_string($instance_config['platform_id']) && trim($instance_config['platform_id']) !== '') {
            $platform_id = trim($instance_config['platform_id']);
        }
        $row = array(
            'id' => $id,
            'label' => $label,
            'platform_id' => $platform_id,
            'capabilities' => $adapter->capabilities(),
            'requirements' => $adapter->requirements(),
            'adapter' => $adapter,
            'instance_config' => $instance_config
        );
        if (method_exists($adapter, 'is_api_target') && $adapter->is_api_target()) {
            $row['is_api_target'] = true;
            $row['api_service_name'] = method_exists($adapter, 'get_api_service_name')
                ? $adapter->get_api_service_name() : '';
        }
        return $row;
    }

    /**
     * Get effective targets (with adapter + instance_config), then filter by user settings and optionally by is_available($report).
     * Returns only public fields for UI (id, label, platform_id, capabilities, requirements, is_api_target, api_service_name).
     * @param Hm_Spam_Report|null $report
     * @return array list of public target descriptors
     */
    public function getTargets(Hm_Spam_Report $report = null) {
        $configs = $this->loadUserTargetConfigurations();
        $list = array();

        if (empty($configs)) {
            foreach ($this->getAllAdapters() as $adapter) {
                $row = $this->buildTargetRow($adapter, $adapter->id(), $adapter->label(), array());
                if ($report !== null && !$adapter->is_available($report, $this->user_config, array())) {
                    continue;
                }
                $list[] = $row;
            }
        } else {
            foreach ($configs as $c) {
                $adapter = $this->getAdapter($c['adapter_id']);
                if (!$adapter instanceof Hm_Spam_Report_Target_Interface) {
                    continue;
                }
                $instance_config = $c['settings'];
                if ($report !== null && !$adapter->is_available($report, $this->user_config, $instance_config)) {
                    continue;
                }
                $list[] = $this->buildTargetRow($adapter, $c['id'], $c['label'], $instance_config);
            }
        }

        $list = $this->filterTargetsByUserSettings($list);
        return $this->toPublicDescriptors($list);
    }

    /**
     * Filter by enabled_setting and allowed_platforms_setting.
     * @param array $targets rows with 'platform_id'
     * @return array
     */
    private function filterTargetsByUserSettings(array $targets) {
        $enabled = $this->user_config->get('spam_reporting_enabled_setting', false);
        if (!$enabled) {
            return array();
        }
        $allowed = $this->user_config->get('spam_reporting_allowed_platforms_setting', array());
        if (!is_array($allowed)) {
            $allowed = array();
        }
        if (empty($allowed)) {
            return array();
        }
        $out = array();
        foreach ($targets as $t) {
            $pid = isset($t['platform_id']) ? $t['platform_id'] : '';
            if ($pid === '' || $pid === null) {
                $out[] = $t;
                continue;
            }
            if (in_array($pid, $allowed, true)) {
                $out[] = $t;
            }
        }
        return $out;
    }

    /**
     * Strip adapter and instance_config; return only public keys for client.
     */
    private function toPublicDescriptors(array $targets) {
        $public_keys = array('id', 'label', 'platform_id', 'capabilities', 'requirements', 'is_api_target', 'api_service_name');
        $out = array();
        foreach ($targets as $d) {
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
    }

    /**
     * Resolve target_id to (adapter, instance_config) for send.
     * @param string $target_id
     * @return array [adapter|null, instance_config]
     */
    public function getTargetById($target_id) {
        if (!is_string($target_id) || $target_id === '') {
            return array(null, array());
        }
        $configs = $this->loadUserTargetConfigurations();
        if (empty($configs)) {
            foreach ($this->getAllAdapters() as $adapter) {
                if ($adapter->id() === $target_id) {
                    return array($adapter, array());
                }
            }
            return array(null, array());
        }
        foreach ($configs as $c) {
            if ($c['id'] !== $target_id) {
                continue;
            }
            $adapter = $this->getAdapter($c['adapter_id']);
            if ($adapter instanceof Hm_Spam_Report_Target_Interface) {
                return array($adapter, $c['settings']);
            }
        }
        return array(null, array());
    }

    /**
     * Configs for settings UI: id, adapter_id, label, adapter_type_label, settings_safe, settings_form.
     * Same shape as before for site.js compatibility.
     * @return array
     */
    public function getConfigsForUi() {
        $configs = $this->loadUserTargetConfigurations();
        $out = array();
        foreach ($configs as $c) {
            $adapter = $this->getAdapter($c['adapter_id']);
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
    }

    /**
     * Adapter types for settings UI: adapter_id, label, schema (only adapters with non-empty schema).
     * @return array
     */
    public function getAdapterTypes() {
        $out = array();
        foreach ($this->getAllAdapters() as $adapter) {
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
    }

    /**
     * Validate and normalize one config entry for save.
     * @param array $entry merged entry (id, adapter_id, label, settings)
     * @return array [bool ok, array errors, array|null normalized_entry]
     */
    public function validateConfigEntry(array $entry) {
        $errors = array();
        $adapter_id = isset($entry['adapter_id']) ? trim((string) $entry['adapter_id']) : '';
        $adapter = $this->getAdapter($adapter_id);
        if (!$adapter instanceof Hm_Spam_Report_Target_Interface) {
            $errors[] = 'Invalid adapter type';
            return array(false, $errors, null);
        }
        $label = isset($entry['label']) && is_string($entry['label']) ? trim($entry['label']) : '';
        if ($label === '') {
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
                        $errors[] = (isset($meta['label']) ? $meta['label'] : $key) . ' is required';
                    }
                }
            }
        }
        if (!empty($errors)) {
            return array(false, $errors, null);
        }
        $id = isset($entry['id']) && trim((string) $entry['id']) !== '' ? trim((string) $entry['id']) : spam_reporting_generate_instance_id();
        return array(true, array(), array(
            'id' => $id,
            'adapter_id' => $adapter_id,
            'label' => $label,
            'settings' => $settings
        ));
    }

    /**
     * Derive allowed_platforms_setting from validated target configs.
     * @param array $configs validated entries (id, adapter_id, label, settings)
     * @return array list of platform_id
     */
    public function deriveAllowedPlatformsFromConfigs(array $configs) {
        $allowed = array();
        foreach ($configs as $c) {
            $adapter_id = isset($c['adapter_id']) ? trim((string) $c['adapter_id']) : '';
            if ($adapter_id === 'abuseipdb') {
                $allowed[] = 'abuseipdb';
            } elseif ($adapter_id === 'spamcop_email') {
                $allowed[] = 'spamcop';
            } elseif ($adapter_id === 'email_target') {
                $allowed[] = '';
            }
        }
        return array_values(array_unique($allowed));
    }
}
