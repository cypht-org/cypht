#!/usr/bin/env php

<?php

define('APP_PATH', dirname(dirname(__FILE__)).'/');
define('VENDOR_PATH', APP_PATH.'vendor/');

require VENDOR_PATH.'autoload.php';
require APP_PATH.'lib/framework.php';

/**
 * Function to check required extensions for a database driver
 */
function checkRequiredExtensions($db_driver, $extensions) {
    $missing_extensions = [];

    foreach ($extensions as $extension) {
        if (!extension_loaded($extension)) {
            $missing_extensions[] = $extension;
        }
    }

    if (!empty($missing_extensions)) {
        error_log(
            "The following required {$db_driver} extensions are missing: " .
            implode(', ', $missing_extensions) .
            ". Please install them.\n"
        );
        exit(1);
    }
}

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
$bad_driver = "Unsupported db driver: {$db_driver}";

// NOTE: these sql commands could be db agnostic if we change the blobs to text

// Check if the required extensions for the configured DB driver are loaded
if ($db_driver == 'mysql') {
    checkRequiredExtensions('MySQL', ['mysqli', 'mysqlnd', 'pdo_mysql']);
} elseif ($db_driver == 'pgsql') {
    checkRequiredExtensions('PostgreSQL', ['pgsql', 'pdo_pgsql']);
} elseif ($db_driver !== 'sqlite') {
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

if (strcasecmp($session_type, 'DB')==0) {
    printf("Creating database table hm_user_session ...\n");

    if ($db_driver == 'mysql') {
        $stmt = "{$create_table} hm_user_session (hm_id varchar(255), data longblob, date timestamp, primary key (hm_id));";
    } elseif($db_driver == 'sqlite') {
        //0 means unlocked, 1 means locked 
        $stmt = "{$create_table} hm_user_session (hm_id varchar(255), data longblob, lock INTEGER DEFAULT 0, date timestamp, primary key (hm_id));";
    } elseif ($db_driver == 'pgsql') {
        $stmt = "{$create_table} hm_user_session (hm_id varchar(255) primary key not null, data text, date timestamp);";
    } else {
        die($bad_driver);
    }

    $conn->exec($stmt);
}
if (strcasecmp($auth_type, 'DB')==0) {

    printf("Creating database table hm_user ...\n");

    if ($db_driver == 'mysql' || $db_driver == 'sqlite') {
        $stmt = "{$create_table} hm_user (username varchar(255), hash varchar(255), primary key (username));";
    } elseif ($db_driver == 'pgsql') {
        $stmt = "{$create_table} hm_user (username varchar(255) primary key not null, hash varchar(255));";
    } else {
        die($bad_driver);
    }

    try {
        $rows = $conn->exec($stmt);
        printf("{$stmt}\n");
    } catch (PDOException $e) {
        print($e);
        exit (1);
    }

}
if (strcasecmp($user_config_type, 'DB')==0) {

    printf("Creating database table hm_user_settings ...\n");

    if ($db_driver == 'mysql' || $db_driver == 'sqlite') {
        $stmt = "{$create_table} hm_user_settings(username varchar(255), settings longblob, primary key (username));";
    } elseif ($db_driver == 'pgsql') {
        $stmt = "{$create_table} hm_user_settings (username varchar(255) primary key not null, settings text);";
    } else {
        die($bad_driver);
    }

    $conn->exec($stmt);
}

print("\nDb setup finished\n");
