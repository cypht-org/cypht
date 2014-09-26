<?php

if (!defined('DEBUG_MODE')) { die(); }

handler_source('smtp');
output_source('smtp');

add_handler('compose', 'load_smtp_servers_from_config', true, 'smtp', 'load_user_data', 'after');
add_handler('compose', 'add_smtp_servers_to_page_data', true, 'smtp', 'load_smtp_servers_from_config', 'after');
add_output('compose', 'compose_form', true, 'smtp', 'content_section_start', 'after');

/* servers page */
add_handler('servers', 'load_smtp_servers_from_config', true, 'smtp', 'language', 'after');
add_handler('servers', 'process_add_smtp_server', true, 'smtp', 'load_smtp_servers_from_config', 'after');
add_handler('servers', 'add_smtp_servers_to_page_data', true, 'smtp', 'process_add_smtp_server', 'after');
add_handler('servers', 'save_smtp_servers', true, 'smtp', 'add_smtp_servers_to_page_data', 'after');
add_output('servers', 'add_smtp_server_dialog', true, 'smtp', 'content_section_end', 'before');
add_output('servers', 'display_configured_smtp_servers', true, 'smtp', 'add_smtp_server_dialog', 'after');

/* ajax server setup callback data */
add_handler('ajax_smtp_debug', 'login', false, 'core');
add_handler('ajax_smtp_debug', 'load_user_data',  true, 'core');
add_handler('ajax_smtp_debug', 'load_smtp_servers_from_config',  true);
add_handler('ajax_smtp_debug', 'add_smtp_servers_to_page_data',  true);
add_handler('ajax_smtp_debug', 'smtp_connect', true);
add_handler('ajax_smtp_debug', 'smtp_delete', true);
add_handler('ajax_smtp_debug', 'smtp_forget', true);
add_handler('ajax_smtp_debug', 'smtp_save', true);
add_handler('ajax_smtp_debug', 'save_smtp_servers', true);
add_handler('ajax_smtp_debug', 'save_user_data',  true, 'core');
add_handler('ajax_smtp_debug', 'date', true, 'core');

return array(
    'allowed_pages' => array(
        'ajax_smtp_debug'
    ),
    'allowed_post' => array(
        'new_smtp_name' => FILTER_SANITIZE_STRING,
        'new_smtp_address' => FILTER_SANITIZE_STRING,
        'new_smtp_port' => FILTER_SANITIZE_STRING,
        'smtp_connect' => FILTER_VALIDATE_INT,
        'smtp_forget' => FILTER_VALIDATE_INT,
        'smtp_save' => FILTER_VALIDATE_INT,
        'smtp_delete' => FILTER_VALIDATE_INT,
        'submit_smtp_server' => FILTER_SANITIZE_STRING,
        'smtp_server_id' => FILTER_VALIDATE_INT,
        'smtp_user' => FILTER_SANITIZE_STRING,
        'smtp_pass' => FILTER_SANITIZE_STRING,
    )
);
?>
