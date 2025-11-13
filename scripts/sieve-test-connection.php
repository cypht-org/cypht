#!/usr/bin/env php
<?php

define('APP_PATH', dirname(__DIR__) . '/');
define('VENDOR_PATH', APP_PATH . 'vendor/');
define('DEBUG_MODE', true);

require VENDOR_PATH . 'autoload.php';
require APP_PATH . 'lib/framework.php';
require APP_PATH . 'modules/imap/hm-imap.php';

$site_config = new Hm_Site_Config_File();
$user_config = new Hm_User_Config_File($site_config);

class SieveCliSession {
    public function get($key, $default = null) {
        return $default;
    }
    public function set($key, $value) {
        return null;
    }
}

$session = new SieveCliSession();

if ($argc < 3) {
    fwrite(STDERR, "Usage: php scripts/sieve-test-connection.php <user_id> <imap_server_id>\n");
    exit(1);
}

$user_id = $argv[1];
$server_id = $argv[2];

$user_config->load($user_id, null);

$result = SieveSync::testConnection($user_id, $server_id, $user_config, $session);

if ($result['success']) {
    echo "✅ Connection successful\n";
    if (!empty($result['capabilities'])) {
        echo "Capabilities:\n";
        foreach ($result['capabilities'] as $key => $value) {
            if (is_array($value)) {
                echo "  {$key}: " . implode(', ', $value) . "\n";
            } else {
                echo "  {$key}: {$value}\n";
            }
        }
    }
    exit(0);
}

echo "❌ Connection failed: {$result['message']}\n";
if (!empty($result['details']['suggestion'])) {
    echo "Suggestion: {$result['details']['suggestion']}\n";
}
exit(1);

