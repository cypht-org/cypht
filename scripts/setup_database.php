#!/usr/bin/env php

<?php

define('APP_PATH', dirname(dirname(__FILE__)).'/');
define('VENDOR_PATH', APP_PATH.'vendor/');

require VENDOR_PATH.'autoload.php';
require APP_PATH.'lib/framework.php';

$config = new Hm_Site_Config_File();

$session_type = $config->get('session_type');
$auth_type = $config->get('auth_type');
$user_config_type = $config->get('user_config_type');
$db_driver = $config->get('db_driver');
$db_name = $config->get('db_name');
$db_user = $config->get('db_user');
$db_pass = $config->get('db_pass');
$db_host = $config->get('db_host');
$db_socket = $config->get('db_socket');

$connected = false;
$create_table = "CREATE TABLE IF NOT EXISTS";
$bad_driver = "Unsupported db driver: {$db_driver}";

// NOTE: these sql commands could be db agnostic if we change the blobs to text


print("session_type={$session_type}  auth_type={$auth_type}  user_config_type={$user_config_type}  db_driver={$db_driver}\n");

$connection_tries=0;
$max_tries=10;

while (!$connected) {
    $connection_tries = $connection_tries + 1;

    $conn = Hm_DB::connect($config);
    // $conn = new pdo("{$db_driver}:host={$db_host};dbname={$db_name}", $db_user, $db_pass);

    // if ($db_driver == 'sqlite') {
    //     // TODO: sqlite should be handled by connect(). not manually done here.
    //     $conn = new pdo("{$db_driver}:{$db_socket}");
    // $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    // }

    if ($conn !== false) {
        printf("Database connection successful ...\n");
        $connected = true;
    } else {
        printf("Attempting to connect to database ... ({$connection_tries}/{$max_tries})\n");
        sleep(2);
    }
        
    if ($connection_tries >= $max_tries) {
        error_log('Unable to connect to database');
        exit(1);
    }
}

if (strcasecmp($session_type, 'DB')==0) {
    printf("Creating database table hm_user_session ...\n");

    if ($db_driver == 'mysql' || $db_driver == 'sqlite') {
        $stmt = "{$create_table} hm_user_session (hm_id varchar(255), data longblob, date timestamp, primary key (hm_id));";
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
