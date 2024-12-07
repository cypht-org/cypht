#!/usr/bin/env php

<?php

define('APP_PATH', dirname(dirname(__FILE__)).'/');
define('VENDOR_PATH', APP_PATH.'vendor/');
define('MIGRATIONS_PATH', APP_PATH.'database/migrations');

require VENDOR_PATH.'autoload.php';
require APP_PATH.'lib/framework.php';

$environment = Hm_Environment::getInstance();
$environment->load();

/* get config object */
$config = new Hm_Site_Config_File();
/* set the default since and per_source values */
$environment->define_default_constants($config);

$session_type = $config->get('session_type');
$auth_type = $config->get('auth_type');
$user_config_type = $config->get('user_config_type');
$db_driver = $config->get('db_driver');

$connected = false;
$create_table = "CREATE TABLE IF NOT EXISTS";
$alter_table = "ALTER TABLE";
$bad_driver = "Unsupported db driver: {$db_driver}";

// NOTE: these sql commands could be db agnostic if we change the blobs to text

// Check if the required extensions for the configured DB driver are loaded
if ($db_driver == 'mysql') {
    $required_extensions = ['mysqli', 'mysqlnd', 'pdo_mysql'];
    $missing_extensions = [];

    foreach ($required_extensions as $extension) {
        if (!extension_loaded($extension)) {
            $missing_extensions[] = $extension;
        }
    }

    if (!empty($missing_extensions)) {
        error_log('The following required MySQL extensions are missing: ' . implode(', ', $missing_extensions) . ". Please install them.\n");
        exit(1);
    }
} elseif ($db_driver == 'pgsql') {
    $required_extensions = ['pgsql', 'pdo_pgsql'];
    $missing_extensions = [];

    foreach ($required_extensions as $extension) {
        if (!extension_loaded($extension)) {
            $missing_extensions[] = $extension;
        }
    }

    if (!empty($missing_extensions)) {
        error_log('The following required PostgreSQL extensions are missing: ' . implode(', ', $missing_extensions) . ". Please install them.\n");
        exit(1);
    }
} elseif ($db_driver == 'sqlite') {
    $required_extensions = ['sqlite3', 'pdo_sqlite'];
    $missing_extensions = [];

    foreach ($required_extensions as $extension) {
        if (!extension_loaded($extension)) {
            $missing_extensions[] = $extension;
        }
    }

    if (!empty($missing_extensions)) {
        error_log('The following required SQLite extensions are missing: ' . implode(', ', $missing_extensions) . ". Please install them.\n");
        exit(1);
    }
}else {
    error_log("Unsupported DB driver: {$db_driver}");
    exit(1);
}

$connection_tries=0;
$max_tries=10;

while (!$connected) {
    $connection_tries = $connection_tries + 1;

    $conn = Hm_DB::connect($config);

    if ($conn !== false) {
        printf("Database connection successful ...\n");
        $connected = true;
    } else {
        printf("Attempting to connect to database ... ({$connection_tries}/{$max_tries})\n");
        sleep(1);
    }

    if ($connection_tries >= $max_tries) {
        error_log('Unable to connect to database');
        exit(1);
    }
}

$action = $argv[1] ?? 'migrate';
if (!in_array($action, ['migrate', 'rollback'])) {
    echo "Invalid argument. Use 'migrate' or 'rollback'.\n";
    exit(1);
}

$migrationRunner = new \Database\Core\MigrationRunner($conn);
$migrationRunner->run($action);

print("\nDb setup and migration finished\n");
