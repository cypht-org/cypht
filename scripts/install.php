#!/usr/bin/env php
<?php

/**
 * Interactive install wizard. Wraps setup_database.php, create_account.php
 * and config_gen.php through Hm_Installer instead of duplicating their logic.
 */

if (mb_strtolower(php_sapi_name()) !== 'cli') {
    die("Must be run from the command line\n");
}

define('APP_PATH', dirname(__DIR__).'/');
require APP_PATH.'lib/installer.php';

$installer = new Hm_Installer();

if ($installer->envExists()) {
    fwrite(STDOUT, "A .env file already exists, Cypht is already configured.\n");
    fwrite(STDOUT, "Delete .env first if you want to run the installer again.\n");
    exit(0);
}

$requirements = $installer->checkRequirements();
$missing = array_keys(array_filter($requirements, fn($ok) => !$ok));
if (!empty($missing)) {
    fwrite(STDERR, "Missing requirements: ".implode(', ', $missing)."\n");
    exit(1);
}

fwrite(STDOUT, "Cypht installer\n\n");

$db_driver = prompt('Database driver (mysql, pgsql, sqlite)', 'mysql');
$db_host = prompt('Database host', '127.0.0.1');
$db_name = prompt('Database name', 'cypht_db');
$db_user = prompt('Database user', 'cypht');
$db_pass = prompt_hidden('Database password');
$settings_dir = prompt('User settings directory', '/var/lib/hm3/users');
$attachment_dir = prompt('Attachment directory', '/var/lib/hm3/attachments');

$installer->writeEnv([
    'DB_DRIVER' => $db_driver,
    'DB_HOST' => $db_host,
    'DB_NAME' => $db_name,
    'DB_USER' => $db_user,
    'DB_PASS' => $db_pass,
    'USER_SETTINGS_DIR' => $settings_dir,
    'ATTACHMENT_DIR' => $attachment_dir,
    'AUTH_TYPE' => 'DB',
    'USER_CONFIG_TYPE' => 'file',
]);

$installer->createDirectories([$settings_dir, $attachment_dir]);

fwrite(STDOUT, "\nSetting up the database...\n");
$result = $installer->setupDatabase();
fwrite(STDOUT, $result['output']);
if (!$result['success']) {
    fwrite(STDERR, $result['error']);
    exit(1);
}

$admin_user = prompt('Admin username (leave blank to skip)', '');
if ($admin_user !== '') {
    $admin_pass = prompt_hidden('Admin password');
    $result = $installer->createAdminAccount($admin_user, $admin_pass);
    fwrite(STDOUT, $result['output']);
    if (!$result['success']) {
        fwrite(STDERR, $result['error']);
    }
}

fwrite(STDOUT, "\nBuilding site configuration...\n");
$result = $installer->buildConfig();
fwrite(STDOUT, $result['output']);
if (!$result['success']) {
    fwrite(STDERR, $result['error']);
    exit(1);
}

fwrite(STDOUT, "\nInstallation complete.\n");

function prompt($label, $default) {
    fwrite(STDOUT, $default !== '' ? "{$label} [{$default}]: " : "{$label}: ");
    $line = trim(fgets(STDIN));
    return $line === '' ? $default : $line;
}

function prompt_hidden($label) {
    fwrite(STDOUT, "{$label}: ");
    if (PHP_OS_FAMILY === 'Windows') {
        $line = trim(fgets(STDIN));
    }
    else {
        system('stty -echo');
        $line = trim(fgets(STDIN));
        system('stty echo');
        fwrite(STDOUT, "\n");
    }
    return $line;
}
