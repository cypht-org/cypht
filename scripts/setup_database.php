#!/usr/bin/env php

<?php

define('APP_PATH', dirname(dirname(__FILE__)).'/');
require APP_PATH.'lib/define_vendor_path.php';

require VENDOR_PATH.'autoload.php';
require APP_PATH.'lib/framework.php';
require APP_PATH.'lib/database_setup.php';

// Allow specifying environment file via --env argument
// Usage: php setup_database.php --env=.env.test
$envFile = '.env';
$options = getopt('', ['env:']);
if (isset($options['env'])) {
    $envFile = $options['env'];
}

if (!file_exists(APP_PATH . $envFile)) {
    echo "Environment file {$envFile} not found. Please create it from the example file.\n";
    exit(1);
}

$environment = Hm_Environment::getInstance();
$environment->load($envFile);

/* get config object */
$config = new Hm_Site_Config_File();
$environment->define_default_constants($config);

try {
    run_database_setup($config, APP_PATH.'database/migrations');
    print("\nDb setup finished\n");
}
catch (Throwable $e) {
    error_log($e->getMessage());
    exit(1);
}
