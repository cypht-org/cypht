<?php

if (!defined('DEBUG_MODE')) { die(); }

handler_source('pop3');
output_source('pop3');

add_module_to_all_pages('handler', 'load_pop3_servers_from_config', true, 'pop3', 'load_user_data', 'after');
add_module_to_all_pages('handler', 'load_pop3_servers_for_message_list', true, 'pop3', 'load_pop3_servers_from_config', 'after');

/* add stuff to the info page */
add_handler('info', 'add_pop3_servers_to_page_data', true, 'pop3', 'load_pop3_servers_from_config', 'after');
add_output('info', 'display_pop3_status', true, 'pop3', 'server_status_start', 'after');
add_output('info', 'pop3_server_ids', true, 'pop3', 'page_js', 'before');

/* profile page */

/* message list page */
add_handler('message_list', 'pop3_message_list_type', true, 'pop3', 'message_list_type', 'after');

/* message view page */
add_handler('message', 'pop3_message_list_type', true, 'pop3', 'message_list_type', 'after');
add_handler('message', 'add_pop3_servers_to_page_data', true, 'pop3', 'load_pop3_servers_from_config', 'after');

/* servers page */
add_handler('servers', 'process_add_pop3_server', true, 'pop3', 'load_pop3_servers_from_config', 'after');
add_handler('servers', 'add_pop3_servers_to_page_data', true, 'pop3', 'process_add_pop3_server', 'after');
add_handler('servers', 'save_pop3_servers', true, 'pop3', 'add_pop3_servers_to_page_data', 'after');
add_output('servers', 'add_pop3_server_dialog', true, 'pop3', 'server_content_start', 'after');
add_output('servers', 'display_configured_pop3_servers', true, 'pop3', 'add_pop3_server_dialog', 'after');

/* compose page */
add_handler('compose', 'add_pop3_servers_to_page_data', true, 'pop3', 'load_pop3_servers_from_config', 'after');

/* search page */
add_handler('search', 'load_pop3_servers_for_search', true, 'pop3', 'load_pop3_servers_from_config', 'after');
add_handler('search', 'pop3_message_list_type', true, 'pop3', 'message_list_type', 'after');

/* not found */
add_handler('notfound', 'add_pop3_servers_to_page_data', true, 'pop3', 'load_pop3_servers_from_config', 'after');

/* folder list */
add_handler('ajax_hm_folders', 'load_pop3_servers_from_config', true, 'pop3', 'load_user_data', 'after');
add_handler('ajax_hm_folders', 'load_pop3_folders', true, 'pop3', 'load_pop3_servers_from_config', 'after');
add_handler('ajax_hm_folders', 'add_pop3_servers_to_page_data', true, 'pop3', 'load_pop3_servers_from_config', 'after');
add_output('ajax_hm_folders', 'filter_pop3_folders', true, 'pop3', 'folder_list_content_start', 'before');

/* pop3 message list messages */
add_handler('ajax_pop3_folder_display', 'login', false, 'core');
add_handler('ajax_pop3_folder_display', 'load_user_data', true, 'core');
add_handler('ajax_pop3_folder_display', 'load_pop3_servers_from_config', true, 'pop3');
add_handler('ajax_pop3_folder_display', 'language', true, 'core');
add_handler('ajax_pop3_folder_display', 'message_list_type', true, 'core');
add_handler('ajax_pop3_folder_display', 'pop3_message_list_type', true);
add_handler('ajax_pop3_folder_display', 'close_session_early', true, 'core');
add_handler('ajax_pop3_folder_display', 'pop3_folder_page', true);
add_handler('ajax_pop3_folder_display', 'save_pop3_cache',  true);
add_handler('ajax_pop3_folder_display', 'date', true, 'core');
add_handler('ajax_pop3_folder_display', 'http_headers', true, 'core');
add_output('ajax_pop3_folder_display', 'filter_pop3_message_list', true);

/* view pop3 message */
add_handler('ajax_pop3_message_display', 'login', false, 'core');
add_handler('ajax_pop3_message_display', 'load_user_data', true, 'core');
add_handler('ajax_pop3_message_display', 'language', true, 'core');
add_handler('ajax_pop3_message_display', 'load_pop3_servers_from_config', true);
add_handler('ajax_pop3_message_display', 'pop3_message_content', true);
add_handler('ajax_pop3_message_display', 'save_pop3_servers', true);
add_handler('ajax_pop3_message_display', 'save_pop3_cache', true);
add_handler('ajax_pop3_message_display', 'close_session_early', true, 'core');
add_handler('ajax_pop3_message_display', 'date', true, 'core');
add_handler('ajax_pop3_message_display', 'http_headers', true, 'core');
add_output('ajax_pop3_message_display', 'filter_pop3_message_content', true);

/* ajax status callback data */
add_handler('ajax_pop3_status', 'login', false, 'core');
add_handler('ajax_pop3_status', 'load_user_data', true, 'core');
add_handler('ajax_pop3_status', 'language', true, 'core');
add_handler('ajax_pop3_status', 'load_pop3_servers_from_config',  true);
add_handler('ajax_pop3_status', 'close_session_early', true, 'core');
add_handler('ajax_pop3_status', 'pop3_status',  true);
add_handler('ajax_pop3_status', 'date', true, 'core');
add_handler('ajax_pop3_status', 'http_headers', true, 'core');
add_output('ajax_pop3_status', 'filter_pop3_status_data', true);

/* message action callback */
add_handler('ajax_message_action', 'load_pop3_servers_from_config', true, 'pop3', 'load_user_data', 'after');
add_handler('ajax_message_action', 'pop3_message_action', true, 'pop3', 'load_pop3_servers_from_config', 'after');
add_handler('ajax_message_action', 'save_pop3_servers', true, 'pop3', 'pop3_message_action', 'after');

/* ajax server setup callback data */
add_handler('ajax_pop3_debug', 'login', false, 'core');
add_handler('ajax_pop3_debug', 'load_user_data',  true, 'core');
add_handler('ajax_pop3_debug', 'language',  true, 'core');
add_handler('ajax_pop3_debug', 'load_pop3_servers_from_config',  true);
add_handler('ajax_pop3_debug', 'add_pop3_servers_to_page_data',  true);
add_handler('ajax_pop3_debug', 'pop3_connect', true);
add_handler('ajax_pop3_debug', 'pop3_delete', true);
add_handler('ajax_pop3_debug', 'pop3_forget', true);
add_handler('ajax_pop3_debug', 'pop3_save', true);
add_handler('ajax_pop3_debug', 'save_pop3_servers', true);
add_handler('ajax_pop3_debug', 'save_user_data',  true, 'core');
add_handler('ajax_pop3_debug', 'date', true, 'core');
add_handler('ajax_pop3_debug', 'http_headers', true, 'core');

add_handler('ajax_update_server_pw', 'load_pop3_servers_from_config', true, 'pop3', 'load_user_data', 'after');

return array(
    'allowed_pages' => array(
        'ajax_pop3_debug',
        'ajax_pop3_message_display',
        'ajax_pop3_folder_display',
        'ajax_pop3_unread',
        'ajax_pop3_status'
    ),
    'allowed_output' => array(
        'pop3_connect_status' => array(FILTER_SANITIZE_STRING, false),
        'pop3_connect_time' => array(FILTER_SANITIZE_STRING, false),
        'pop3_detail_display' => array(FILTER_SANITIZE_STRING, false),
        'pop3_status_display' => array(FILTER_UNSAFE_RAW, false),
        'pop3_status_server_id' => array(FILTER_VALIDATE_INT, false),
        'pop3_server_id' => array(FILTER_VALIDATE_INT, false),
    ),
    'allowed_post' => array(
        'new_pop3_name' => FILTER_SANITIZE_STRING,
        'new_pop3_address' => FILTER_SANITIZE_STRING,
        'new_pop3_port' => FILTER_SANITIZE_STRING,
        'pop3_connect' => FILTER_VALIDATE_INT,
        'pop3_forget' => FILTER_VALIDATE_INT,
        'pop3_save' => FILTER_VALIDATE_INT,
        'pop3_delete' => FILTER_VALIDATE_INT,
        'submit_pop3_server' => FILTER_SANITIZE_STRING,
        'pop3_server_id' => FILTER_VALIDATE_INT,
        'pop3_server_ids' => FILTER_SANITIZE_STRING,
        'pop3_user' => FILTER_SANITIZE_STRING,
        'pop3_pass' => FILTER_UNSAFE_RAW,
        'pop3_list_path' => FILTER_SANITIZE_STRING,
        'pop3_uid' => FILTER_VALIDATE_INT,
        'pop3_limit' => FILTER_VALIDATE_INT,
        'pop3_since' => FILTER_SANITIZE_STRING,
        'pop3_unread_only' => FILTER_VALIDATE_BOOLEAN,
        'pop3_search' => FILTER_VALIDATE_INT,
    )
);


