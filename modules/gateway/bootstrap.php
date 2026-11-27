<?php

if (!class_exists('Gateway_User_Config_Conflict', false)) {
    class Gateway_User_Config_Conflict extends RuntimeException {}
}

if (!function_exists('gateway_load_user_config_class')) {
    function gateway_load_user_config_class($class) {
        if ($class === 'Gateway_User_Config_File') {
            require_once __DIR__.'/user_config.php';
        }
    }
}

spl_autoload_register('gateway_load_user_config_class', true, true);