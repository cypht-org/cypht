<?php

if (!class_exists('Gateway_User_Config_Conflict', false)) {
    class Gateway_User_Config_Conflict extends RuntimeException {}
}

if (!class_exists('Gateway_User_Config_File', false)) {
class Gateway_User_Config_File extends Hm_User_Config_File {
    private $gateway_site_config;
    private $gateway_lock_handle;
    private $gateway_revision;
    private $gateway_revision_loaded = false;
    private $gateway_revision_username;
    private $gateway_write_username;
    private $gateway_write_key;
    private $gateway_write_section;
    private $gateway_write_active = false;

    public function __construct($config) {
        $this->gateway_site_config = $config;
        parent::__construct($config);
    }

    public function gateway_storage_is_safe() {
        return true;
    }

    public function load($username, $key, $lock_held = false) {
        $lock = $lock_held ? null : $this->gateway_acquire_lock($username, LOCK_SH);
        try {
            $this->decrypt_failed = false;
            $result = parent::load($username, $key);
            $this->gateway_revision = $this->gateway_revision_for_user($username);
            $this->gateway_revision_username = $username;
            $this->gateway_revision_loaded = true;
            return $result;
        } finally {
            if (!$lock_held) $this->gateway_release_lock($lock);
        }
    }

    public function reload($data, $username = false) {
        if ($username !== false && !crypt_state($this->gateway_site_config)) {
            $this->config = array_merge(array('version' => VERSION), $this->gateway_site_config->user_defaults);
            return $this->load($username, false);
        }
        parent::reload($data, $username);
        $this->gateway_revision = null;
        $this->gateway_revision_username = $username;
        $this->gateway_revision_loaded = false;
    }

    public function gateway_begin_write($username, $key, $section, $session_snapshot) {
        if ($this->gateway_write_active || !is_string($username) || $username === '' ||
            !is_string($key) || $key === '' || !is_string($section) || $section === '' ||
            !is_array($session_snapshot)) {
            return false;
        }
        $this->gateway_lock_handle = $this->gateway_acquire_lock($username, LOCK_EX);
        try {
            $fresh = new self($this->gateway_site_config);
            $fresh->load($username, $key, true);
            if ($fresh->decrypt_failed || !$fresh->gateway_revision_loaded) {
                throw new RuntimeException('user-config could not be loaded');
            }
            $stored = $fresh->get($section, array());
            $session_value = array_key_exists($section, $session_snapshot)
                ? $session_snapshot[$section] : array();
            if (!$this->gateway_values_equal($stored, $session_value)) {
                throw new Gateway_User_Config_Conflict('stale user-config snapshot');
            }
            parent::reload($fresh->dump(), $username);
            $this->gateway_revision = $fresh->gateway_revision;
            $this->gateway_revision_username = $username;
            $this->gateway_revision_loaded = $fresh->gateway_revision_loaded;
            $this->gateway_write_username = $username;
            $this->gateway_write_key = $key;
            $this->gateway_write_section = $section;
            $this->gateway_write_active = true;
            return true;
        } catch (Throwable $error) {
            $this->gateway_release_write();
            throw $error;
        }
    }

    public function gateway_commit_write($section) {
        if (!$this->gateway_write_active || $section !== $this->gateway_write_section) {
            return false;
        }
        try {
            $this->save($this->gateway_write_username, $this->gateway_write_key);
            $fresh = new self($this->gateway_site_config);
            $fresh->load($this->gateway_write_username, $this->gateway_write_key, true);
            $expected = $this->get($section, array());
            $actual = $fresh->get($section, array());
            if ($fresh->decrypt_failed || !$this->gateway_values_equal($expected, $actual)) {
                throw new Gateway_User_Config_Conflict('user-config readback failed');
            }
            parent::reload($fresh->dump(), $this->gateway_write_username);
            $this->gateway_revision = $fresh->gateway_revision;
            $this->gateway_revision_username = $this->gateway_write_username;
            $this->gateway_revision_loaded = $fresh->gateway_revision_loaded;
            return $actual;
        } finally {
            $this->gateway_release_write();
        }
    }

    public function gateway_abort_write() {
        $this->gateway_release_write();
    }

    public function save($username, $key) {
        $active = $this->gateway_write_active && $username === $this->gateway_write_username;
        $lock = $active ? null : $this->gateway_acquire_lock($username, LOCK_EX);
        try {
            if (!$this->gateway_revision_loaded || $this->gateway_revision_username !== $username || $this->decrypt_failed) {
                throw new Gateway_User_Config_Conflict('user-config must be freshly loaded before save');
            }
            $current_revision = $this->gateway_revision_for_user($username);
            if ($current_revision !== $this->gateway_revision) {
                throw new Gateway_User_Config_Conflict('user-config changed during write');
            }
            $this->shuffle();
            $removed = $this->filter_servers();
            try {
                $crypt = crypt_state($this->gateway_site_config);
                if ($crypt && (!is_string($key) || $key === '')) {
                    throw new RuntimeException('user-config key is required');
                }
                $json = json_encode($this->config);
                if (!is_string($json)) {
                    throw new RuntimeException('user-config encoding failed');
                }
                $data = $crypt ? Hm_Crypt::ciphertext($json, $key) : $json;
                if (!is_string($data)) {
                    throw new RuntimeException('user-config encryption failed');
                }
                $this->gateway_atomic_write($this->get_path($username), $data);
            } finally {
                $this->restore_servers($removed);
            }
            $this->gateway_revision = hash('sha256', $data);
            $this->gateway_revision_username = $username;
            $this->gateway_revision_loaded = true;
            return true;
        } finally {
            if (!$active) $this->gateway_release_lock($lock);
        }
    }

    private function gateway_acquire_lock($username, $mode = LOCK_EX) {
        $path = $this->get_path($username).'.gateway.lock';
        $handle = @fopen($path, 'c');
        if (!$handle || !flock($handle, $mode)) {
            if (is_resource($handle)) fclose($handle);
            throw new RuntimeException('user-config lock unavailable');
        }
        @chmod($path, 0600);
        return $handle;
    }

    private function gateway_release_lock($handle) {
        if (is_resource($handle)) {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }

    private function gateway_release_write() {
        $this->gateway_release_lock($this->gateway_lock_handle);
        $this->gateway_lock_handle = null;
        $this->gateway_write_username = null;
        $this->gateway_write_key = null;
        $this->gateway_write_section = null;
        $this->gateway_write_active = false;
    }

    private function gateway_revision_for_user($username) {
        $path = $this->get_path($username);
        if (!file_exists($path)) return null;
        if (is_link($path) || !is_file($path)) throw new RuntimeException('user-config path unavailable');
        $hash = @hash_file('sha256', $path);
        if (!is_string($hash)) throw new RuntimeException('user-config read failed');
        return $hash;
    }

    private function gateway_atomic_write($destination, $data) {
        $folder = dirname($destination);
        if (!is_dir($folder) || is_link($destination)) {
            throw new RuntimeException('user-config destination unavailable');
        }
        $temporary = @tempnam($folder, '.gateway-config-');
        if (!$temporary) throw new RuntimeException('user-config temporary file unavailable');
        try {
            if (!@chmod($temporary, 0600)) throw new RuntimeException('user-config permissions failed');
            $written = @file_put_contents($temporary, $data, LOCK_EX);
            if ($written === false || $written !== strlen($data)) {
                throw new RuntimeException('user-config write failed');
            }
            if (!@rename($temporary, $destination)) {
                throw new RuntimeException('user-config atomic replacement failed');
            }
            $temporary = null;
        } finally {
            if ($temporary && is_file($temporary)) @unlink($temporary);
        }
    }

    private function gateway_values_equal($left, $right) {
        $left_json = json_encode($this->gateway_sort_values($left), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $right_json = json_encode($this->gateway_sort_values($right), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        return is_string($left_json) && is_string($right_json) && hash_equals($left_json, $right_json);
    }

    private function gateway_sort_values($value) {
        if (!is_array($value)) return $value;
        if (!array_is_list($value)) ksort($value);
        foreach ($value as $key => $child) {
            $value[$key] = $this->gateway_sort_values($child);
        }
        return $value;
    }
}
}