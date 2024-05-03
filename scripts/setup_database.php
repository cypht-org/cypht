#!/usr/bin/env php

<?php

// can we source config/app.php instead of getting each var?
// do this once env vs getenv gets sorted out
// define('APP_PATH', dirname(dirname(__FILE__)).'/');
// require APP_PATH.'config/app.php';
// $config('app');

$session_type = getenv('SESSION_TYPE') ?: 'PHP';
$auth_type = getenv('AUTH_TYPE') ?: 'DB';
$user_config_type = getenv('USER_CONFIG_TYPE') ?: 'file';
$db_driver = getenv('DB_DRIVER') ?: 'mysql';
$db_name = getenv('DB_NAME') ?: 'cypht_db';
$db_user = getenv('DB_USER');
$db_pass = getenv('DB_PASS');
$db_driver = getenv('DB_DRIVER') ?: 'mysql';
$db_host = getenv('DB_HOST') ?: '127.0.0.1';

$connected = false;
$create_table = "CREATE TABLE IF NOT EXISTS";
$bad_driver = "Unsupported db driver: {$db_driver}";

// NOTE: these sql commands could be db agnostic if we change the blobs to text


print("session_type={$session_type}  auth_type={$auth_type}  user_config_type={$user_config_type}  db_driver={$db_driver}\n");

while (!$connected) {
    print("Not connected\n");
    try {
        $conn = new pdo("{$db_driver}:host={$db_host};dbname={$db_name}", $db_user, $db_pass);
        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        printf("Database connection successful ...\n");
        printf("{$db_driver}:host={$db_host};dbname={$db_name}\n");
        $connected = true;
    } catch(PDOException $e){
        error_log('Waiting for database connection ... (' . $e->getMessage() . ')');
        sleep(1);
    }
}
print("Connected\n");
if (strcasecmp($session_type,'DB')==0) {
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
        // TODO: figure out why this is not working for sqlite
        $rows = $conn->exec($stmt);
        printf($stmt);
        printf("\nrows updated: {$rows}\n");
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

print("Db setup finished");
