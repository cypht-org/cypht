<?php

if (!defined('DEBUG_MODE')) { die(); }

handler_source('api_login');
output_source('api_login');

setup_base_page('process_api_login', 'core');
replace_module('handler', 'login', 'process_api_login');
add_handler('process_api_login', 'api_login_step_two', false, 'api_login', 'process_api_login', 'before');

/* allowed input */
return array(
    'allowed_pages' => array('process_api_login'),
    'allowed_post' => array(
        'hm_session' => FILTER_SANITIZE_STRING,
        'hm_id' => FILTER_SANITIZE_STRING,
        'api_login_key' => FILTER_SANITIZE_STRING
    )
);


