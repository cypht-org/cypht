<?php

if (!defined('DEBUG_MODE')) { die(); }

handler_source('site');
output_source('site');

/* replace module just on the home page */
//replace_module('handler', 'http_headers', 'site_http_headers', 'home');

/* replace module on all pages */
//replace_module('handler', 'http_headers', 'site_http_headers', 'home');

/* allowed input */
return array(
    'allowed_pages' => array(),
    'allowed_cookie' => array(),
    'allowed_server' => array(),
    'allowed_get' => array(),
    'allowed_post' => array()
);


