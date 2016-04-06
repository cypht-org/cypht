<?php

if (!defined('DEBUG_MODE')) { die(); }
handler_source('dynamic_login');
output_source('dynamic_login');

replace_module('handler', 'login', 'process_dynamic_login');
replace_module('output', 'login', 'dynamic_login');

/* allowed input */
return array(
    'allowed_pages' => array(),
    'allowed_cookie' => array(),
    'allowed_server' => array(),
    'allowed_get' => array(),
    'allowed_post' => array('email_provider' => FILTER_SANITIZE_STRING)
);


