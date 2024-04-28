<?php

// TODO: this file should probably start with something like #!/usr/bin/env php

$connected = false;
$session_type = CYPHT_SESSION_TYPE;
$auth_type = CYPHT_AUTH_TYPE;
$user_config_type = CYPHT_USER_CONFIG_TYPE;
$db_driver = CYPHT_DB_DRIVER;

// TODO: move this into the scripts/ dir. its not docker specific

while(!$connected) {
    try{
    $conn = new pdo('CYPHT_DB_DRIVER:host=CYPHT_DB_HOST;dbname=CYPHT_DB_NAME', 'CYPHT_DB_USER', 'CYPHT_DB_PASS');
        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    printf("Database connection successful ...\n");
        $connected = true;
    } catch(PDOException $e){
        error_log('Waiting for database connection ... (' . $e->getMessage() . ')');
        sleep(1);
    }
}
if ($session_type == 'DB')  {
    if ($db_driver == 'mysql') {
        $stmt = "CREATE TABLE IF NOT EXISTS hm_user_session (hm_id varchar(250), data longblob, date timestamp, primary key (hm_id));";
    } elseif ($db_driver == 'pgsql') {
        $stmt = "CREATE TABLE IF NOT EXISTS hm_user_session (hm_id varchar(250) primary key not null, data text, date timestamp);";
    }
    // TODO: sqlite command
    printf("Creating database table hm_user_session ...\n");
    $conn->exec($stmt);
}
if ($auth_type == 'DB')  {
    if ($db_driver == 'mysql') {
        $stmt = "CREATE TABLE IF NOT EXISTS hm_user (username varchar(250), hash varchar(250), primary key (username));";
    } elseif ($db_driver == 'pgsql') {
        $stmt = "CREATE TABLE IF NOT EXISTS hm_user (username varchar(255) primary key not null, hash varchar(255));";
    }
    // TODO: sqlite command
    printf("Creating database table hm_user ...\n");
    $conn->exec($stmt);
}
if ($user_config_type == 'DB')  {
    if ($db_driver == 'mysql') {
        $stmt = "CREATE TABLE IF NOT EXISTS hm_user_settings(username varchar(250), settings longblob, primary key (username));";
    } elseif ($db_driver == 'pgsql') {
        $stmt = "CREATE TABLE IF NOT EXISTS hm_user_settings (username varchar(250) primary key not null, settings text);";
    }
    // TODO: sqlite command
    printf("Creating database table hm_user_settings ...\n");
    $conn->exec($stmt);
}
