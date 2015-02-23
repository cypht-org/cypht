<?php

if (!defined('DEBUG_MODE')) { die(); }

handler_source('nux');
output_source('nux');

/* servers page */
add_output('servers', 'quick_add_section', true, 'nux', 'server_content_start', 'after');
add_output('servers', 'quick_add_dialog', true, 'nux', 'quick_add_section', 'after');

add_handler('ajax_nux_service_select', 'login', false, 'core');
add_handler('ajax_nux_service_select', 'load_user_data', true, 'core');
add_handler('ajax_nux_service_select', 'process_nux_service', true);
add_handler('ajax_nux_service_select', 'language',  true, 'core');
add_handler('ajax_nux_service_select', 'date', true, 'core');
add_handler('ajax_nux_service_select', 'http_headers', true, 'core');
add_output('ajax_nux_service_select', 'filter_service_select', true);

add_handler('ajax_nux_add_service', 'login', false, 'core');
add_handler('ajax_nux_add_service', 'load_user_data', true, 'core');
add_handler('ajax_nux_add_service', 'load_imap_servers_from_config',  true);
add_handler('ajax_nux_add_service', 'process_nux_add_service', true, 'core');
add_handler('ajax_nux_add_service', 'save_imap_servers',  true, 'imap');
add_handler('ajax_nux_add_service', 'save_user_data',  true, 'core');
add_handler('ajax_nux_add_service', 'language',  true, 'core');
add_handler('ajax_nux_add_service', 'date', true, 'core');
add_handler('ajax_nux_add_service', 'http_headers', true, 'core');

return array(
    'allowed_pages' => array(
        'ajax_nux_service_select',
        'ajax_nux_add_service',
    ),
    'allowed_output' => array(
        'nux_service_step_two' => array(FILTER_UNSAFE_RAW, false)
    ),
    'allowed_post' => array(
        'nux_service' => FILTER_SANITIZE_STRING,
        'nux_email' => FILTER_SANITIZE_STRING,
        'nux_pass' => FILTER_UNSAFE_RAW
    )
);

?>
