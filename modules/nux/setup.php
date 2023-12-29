<?php

if (!defined('DEBUG_MODE')) { die(); }

handler_source('nux');
output_source('nux');

/* servers page */
add_output('servers', 'server_config_stepper', true, 'smtp', 'server_content_start', 'after');
add_output('servers', 'quick_add_section', true, 'nux', 'display_configured_imap_servers', 'after');
add_output('servers', 'quick_add_dialog', true, 'nux', 'quick_add_section', 'after');

add_handler('ajax_nux_service_select', 'login', false, 'core');
add_handler('ajax_nux_service_select', 'load_user_data', true, 'core');
add_handler('ajax_nux_service_select', 'setup_nux', true);
add_handler('ajax_nux_service_select', 'process_nux_service', true);
add_handler('ajax_nux_service_select', 'language',  true, 'core');
add_handler('ajax_nux_service_select', 'date', true, 'core');
add_handler('ajax_nux_service_select', 'http_headers', true, 'core');
add_output('ajax_nux_service_select', 'filter_service_select', true);

add_handler('ajax_get_nux_service_details', 'login', false, 'core');
add_handler('ajax_get_nux_service_details', 'load_user_data', true, 'core');
add_handler('ajax_get_nux_service_details', 'get_nux_service_details', true);
add_handler('ajax_get_nux_service_details', 'language',  true, 'core');
add_handler('ajax_get_nux_service_details', 'date', true, 'core');
add_handler('ajax_get_nux_service_details', 'http_headers', true, 'core');
add_output('ajax_get_nux_service_details', 'service_details', true);

add_handler('ajax_quick_server_setup_nux', 'login', false, 'core');
add_handler('ajax_quick_server_setup_nux', 'load_user_data', true, 'core');
add_handler('ajax_quick_server_setup_nux', 'setup_nux', true);
add_handler('ajax_quick_server_setup_nux', 'load_smtp_servers_from_config',  true, 'smtp');
add_handler('ajax_quick_server_setup_nux', 'load_imap_servers_from_config',  true, 'imap');
add_handler('ajax_quick_server_setup_nux', 'quick_server_setup_nux', true);
add_handler('ajax_quick_server_setup_nux', 'save_user_data',  true, 'core');
add_handler('ajax_quick_server_setup_nux', 'language',  true, 'core');
add_handler('ajax_quick_server_setup_nux', 'date', true, 'core');
add_handler('ajax_quick_server_setup_nux', 'http_headers', true, 'core');

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
        'ajax_get_nux_service_details',
        'ajax_nux_add_service',
        'ajax_quick_server_setup_nux',
    ),
    'allowed_get' => array(
        'code' => FILTER_SANITIZE_FULL_SPECIAL_CHARS,
        'state' => FILTER_SANITIZE_FULL_SPECIAL_CHARS,
        'error' => FILTER_SANITIZE_FULL_SPECIAL_CHARS,
        'security_token' => FILTER_SANITIZE_FULL_SPECIAL_CHARS
    ),
    'allowed_output' => array(
        'nux_service_step_two' => array(FILTER_UNSAFE_RAW, false),
        'service_details' => array(FILTER_UNSAFE_RAW, false),
        'nux_account_added' => array(FILTER_VALIDATE_BOOLEAN, false),
        'nux_server_id' => array(FILTER_VALIDATE_INT, false),
        'nux_service_name' => array(FILTER_SANITIZE_FULL_SPECIAL_CHARS, false)
    ),
    'allowed_post' => array(
        'nux_service' => FILTER_SANITIZE_FULL_SPECIAL_CHARS,
        'nux_email' => FILTER_SANITIZE_FULL_SPECIAL_CHARS,
        'nux_name' => FILTER_SANITIZE_FULL_SPECIAL_CHARS,
        'nux_pass' => FILTER_UNSAFE_RAW,
        'nux_account_name' => FILTER_SANITIZE_FULL_SPECIAL_CHARS,
        'nux_all_inkl_login' => FILTER_SANITIZE_FULL_SPECIAL_CHARS,
        'nux_config_profile_name'  => FILTER_SANITIZE_FULL_SPECIAL_CHARS,
        'nux_config_email' => FILTER_SANITIZE_FULL_SPECIAL_CHARS,
        'nux_config_password' => FILTER_UNSAFE_RAW,
        'nux_config_provider' => FILTER_SANITIZE_FULL_SPECIAL_CHARS,
        'nux_config_is_sender' => FILTER_VALIDATE_BOOLEAN,
        'nux_config_is_receiver' => FILTER_VALIDATE_BOOLEAN,
        'nux_config_smtp_address' => FILTER_SANITIZE_FULL_SPECIAL_CHARS,
        'nux_config_smtp_port' => FILTER_SANITIZE_FULL_SPECIAL_CHARS,
        'nux_config_smtp_tls' => FILTER_VALIDATE_BOOLEAN,
        'nux_config_imap_address' => FILTER_SANITIZE_FULL_SPECIAL_CHARS,
        'nux_config_imap_port' => FILTER_SANITIZE_FULL_SPECIAL_CHARS,
        'nux_config_imap_tls' => FILTER_VALIDATE_BOOLEAN,
        'nux_enable_sieve' => FILTER_VALIDATE_BOOLEAN,
        'nux_create_profile' => FILTER_VALIDATE_BOOLEAN,
        'nux_profile_is_default' => FILTER_VALIDATE_BOOLEAN,
        'nux_profile_signature' => FILTER_SANITIZE_FULL_SPECIAL_CHARS,
        'nux_profile_reply_to' => FILTER_SANITIZE_FULL_SPECIAL_CHARS,
        'nux_imap_sieve_host' => FILTER_SANITIZE_FULL_SPECIAL_CHARS,
    )
);


