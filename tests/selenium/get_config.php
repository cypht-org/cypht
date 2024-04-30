<?php

require '../../vendor/autoload.php';
require '../../lib/environment.php';
$config = merge_config_files('../../config');

echo json_encode($config);
