<?php

define('APP_PATH', dirname(dirname(dirname(__FILE__))).'/');

require '../../vendor/autoload.php';
require '../../lib/framework.php';
$environment = Hm_Environment::getInstance();
$environment->load();

/* get config object */
$config = new Hm_Site_Config_File();
/* set the default since and per_source values */
$environment->define_default_constants($config);
$config = merge_config_files('../../config');

echo json_encode($config);
