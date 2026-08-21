<?php

if (!defined('DEBUG_MODE')) { die(); }

handler_source('site');
output_source('site');

/* Embedded mode: logout must not exist. These take effect only when
   CYPHT_HIDE_LOGOUT=true; otherwise each handler defers to its parent. */
replace_module('handler', 'logout', 'site_logout');
replace_module('handler', 'idle_time_check', 'site_idle_time_check');

/* Save settings without demanding a password the user does not have (SSO). */
replace_module('handler', 'process_save_form', 'site_process_save_form');

/* replace module just on the home page */
//replace_module('handler', 'http_headers', 'site_http_headers', 'home');

/* replace module on all pages */
//replace_module('handler', 'http_headers', 'site_http_headers');

/* disable the "servers" link in the settings section of the folder list */
//replace_module('output', 'settings_servers_link', false, 'ajax_hm_folders');

/* redirect request to the servers page to the home page instead */
//add_handler('servers', 'disable_servers_page', true, 'site', 'login', 'after');

/* allowed input */
return array(
    'allowed_pages' => array(),
    'allowed_cookie' => array(),
    'allowed_server' => array(),
    'allowed_get' => array(),
    'allowed_post' => array()
);
