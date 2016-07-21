<?php

if (!defined('DEBUG_MODE')) { die(); }

handler_source('api_login');
output_source('api_login');

replace_module('handler', 'login', 'process_api_login');

/* allowed input */
return array(
    'allowed_post' => array('api_login_key' => FILTER_SANITIZE_STRING)
);


