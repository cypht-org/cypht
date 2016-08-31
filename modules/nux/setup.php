<?php

if (!defined('DEBUG_MODE')) { die(); }

handler_source('nux');
output_source('nux');

/* servers page */
add_output('servers', 'quick_add_section', true, 'nux', 'server_content_start', 'after');
add_output('servers', 'quick_add_dialog', true, 'nux', 'quick_add_section', 'after');

add_handler('ajax_nux_service_select', 'login', false, 'core');
add_handler('ajax_nux_service_select', 'load_user_data', true, 'core');
add_handler('ajax_nux_service_select', 'setup_nux', true);
add_handler('ajax_nux_service_select', 'process_nux_service', true);
add_handler('ajax_nux_service_select', 'language',  true, 'core');
add_handler('ajax_nux_service_select', 'date', true, 'core');
add_handler('ajax_nux_service_select', 'http_headers', true, 'core');
add_output('ajax_nux_service_select', 'filter_service_select', true);

add_handler('ajax_nux_add_service', 'login', false, 'core');
add_handler('ajax_nux_add_service', 'load_user_data', true, 'core');
add_handler('ajax_nux_add_service', 'setup_nux', true);
add_handler('ajax_nux_add_service', 'load_smtp_servers_from_config',  true, 'smtp');
add_handler('ajax_nux_add_service', 'load_imap_servers_from_config',  true, 'imap');
add_handler('ajax_nux_add_service', 'process_nux_add_service', true, 'nux');
add_handler('ajax_nux_add_service', 'save_user_data',  true, 'core');
add_handler('ajax_nux_add_service', 'language',  true, 'core');
add_handler('ajax_nux_add_service', 'date', true, 'core');
add_handler('ajax_nux_add_service', 'http_headers', true, 'core');

add_handler('home', 'load_smtp_servers_from_config', true, 'smtp', 'load_user_data', 'after');
add_handler('home', 'load_feeds_from_config', true, 'feeds', 'load_user_data', 'after');

add_handler('home', 'process_oauth2_authorization', true, 'nux', 'message_list_type', 'after');
add_handler('home', 'nux_homepage_data', true, 'nux', 'process_oauth2_authorization', 'after');
add_handler('home', 'nux_dev_news', true, 'nux', 'nux_homepage_data', 'after');

add_output('home', 'welcome_dialog', true, 'nux', 'home_password_dialogs', 'after');
add_output('home', 'nux_help', true, 'nux', 'welcome_dialog', 'after');
add_output('home', 'nux_dev_news', true, 'nux', 'nux_help', 'after');

add_output('message_list', 'nux_message_list_notice', true, 'nux', 'message_list_start', 'before');

return array(
    'allowed_pages' => array(
        'ajax_nux_service_select',
        'ajax_nux_add_service',
    ),
    'allowed_get' => array(
        'code' => FILTER_SANITIZE_STRING,
        'state' => FILTER_SANITIZE_STRING,
        'error' => FILTER_SANITIZE_STRING,
        'security_token' => FILTER_SANITIZE_STRING
    ),
    'allowed_output' => array(
        'nux_service_step_two' => array(FILTER_UNSAFE_RAW, false),
        'nux_account_added' => array(FILTER_VALIDATE_BOOLEAN, false)
    ),
    'allowed_post' => array(
        'nux_service' => FILTER_SANITIZE_STRING,
        'nux_email' => FILTER_SANITIZE_STRING,
        'nux_name' => FILTER_SANITIZE_STRING,
        'nux_pass' => FILTER_UNSAFE_RAW,
        'nux_account_name' => FILTER_SANITIZE_STRING
    )
);


