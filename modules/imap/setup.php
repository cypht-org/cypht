<?php

handler_source('imap');
output_source('imap');

/* add stuff to the home page */
add_handler('home', 'load_imap_servers_from_config', true, 'imap', 'load_user_data', 'after');
add_handler('home', 'add_imap_servers_to_page_data',  true, 'imap', 'load_imap_servers_from_config', 'after');
add_output('home', 'display_imap_summary', true, 'imap', 'server_summary_start', 'after');
add_output('home', 'imap_server_ids', true, 'imap', 'page_js', 'before');

/* servers page data */
add_handler('servers', 'load_imap_servers_from_config',  true, 'imap', 'date', 'after');
add_handler('servers', 'process_add_imap_server', true, 'imap', 'load_imap_servers_from_config', 'after');
add_handler('servers', 'add_imap_servers_to_page_data', 'imap', true, 'process_add_imap_server', 'after');
add_handler('servers', 'save_imap_servers',  true, 'imap', 'add_imap_servers_to_page_data', 'after');
add_output('servers', 'add_imap_server_dialog', true, 'imap', 'loading_icon', 'after');
add_output('servers', 'display_configured_imap_servers', true, 'imap', 'add_imap_server_dialog', 'after');


add_handler('message_list', 'load_imap_servers_from_config', true, 'imap', 'load_user_data', 'after');
add_handler('message_list', 'add_imap_servers_to_page_data',  true, 'imap', 'load_imap_servers_from_config', 'after');
add_handler('message_list', 'imap_message_list_type',  true, 'imap', 'add_imap_servers_to_page_data', 'after');
add_output('message_list', 'imap_message_list', true, 'imap', 'folder_list_end', 'before');

/* ajax server setup callback data */
add_handler('ajax_imap_debug', 'login', false, 'core');
add_handler('ajax_imap_debug', 'load_user_data',  true, 'core');
add_handler('ajax_imap_debug', 'load_imap_servers_from_config',  true);
add_handler('ajax_imap_debug', 'imap_connect', true);
add_handler('ajax_imap_debug', 'imap_delete', true);
add_handler('ajax_imap_debug', 'imap_forget', true);
add_handler('ajax_imap_debug', 'imap_save', true);
add_handler('ajax_imap_debug', 'save_imap_cache',  true);
add_handler('ajax_imap_debug', 'save_imap_servers',  true);
add_handler('ajax_imap_debug', 'save_user_data',  true, 'core');
add_handler('ajax_imap_debug', 'date', true, 'core');

/* ajax unread callback data */
add_handler('ajax_imap_unread', 'login', false, 'core');
add_handler('ajax_imap_unread', 'load_user_data', true, 'core');
add_handler('ajax_imap_unread', 'load_imap_servers_from_config',  true);
add_handler('ajax_imap_unread', 'imap_unread',  true);
add_handler('ajax_imap_unread', 'save_imap_cache',  true);
add_handler('ajax_imap_unread', 'save_imap_servers',  true);
add_handler('ajax_imap_unread', 'date', true, 'core');
add_output('ajax_imap_unread', 'filter_unread_data', true);

/* save unread page state */
add_handler('ajax_imap_save_unread_state', 'login', false, 'core');
add_handler('ajax_imap_save_unread_state', 'load_user_data', true, 'core');
add_handler('ajax_imap_save_unread_state', 'save_unread_state', true);
add_handler('ajax_imap_save_unread_state', 'date', true, 'core');


/* msg preview */
add_handler('ajax_imap_msg_text', 'login', false, 'core');
add_handler('ajax_imap_msg_text', 'load_user_data', true, 'core');
add_handler('ajax_imap_msg_text', 'load_imap_servers_from_config',  true);
add_handler('ajax_imap_msg_text', 'imap_message_text',  true);
add_handler('ajax_imap_msg_text', 'save_imap_cache',  true);
add_handler('ajax_imap_msg_text', 'save_imap_servers',  true);
add_handler('ajax_imap_msg_text', 'date', true, 'core');
add_output('ajax_imap_msg_text', 'filter_message_text', true);

/* ajax folder callback data */
add_handler('ajax_hm_folders', 'load_imap_servers_from_config',  true, 'imap', 'load_user_data', 'after');
add_handler('ajax_hm_folders', 'add_imap_servers_to_page_data',  true, 'imap', 'load_imap_servers_from_config', 'after');
add_handler('ajax_hm_folders', 'load_imap_folders',  true, 'imap', 'add_imap_servers_to_page_data', 'after');
add_handler('ajax_hm_folders', 'save_imap_cache',  true, 'imap', 'load_imap_folders', 'after');
add_handler('ajax_hm_folders', 'save_imap_servers',  true, 'imap', 'save_imap_cache', 'after');
add_output('ajax_hm_folders', 'filter_imap_folders', true);

/* expand folder */
add_handler('ajax_imap_folder_expand', 'login', false, 'core');
add_handler('ajax_imap_folder_expand', 'load_user_data', true, 'core');
add_handler('ajax_imap_folder_expand', 'load_imap_servers_from_config',  true);
add_handler('ajax_imap_folder_expand', 'imap_folder_expand',  true);
add_handler('ajax_imap_folder_expand', 'save_imap_cache',  true);
add_handler('ajax_imap_folder_expand', 'save_imap_servers',  true);
add_handler('ajax_imap_folder_expand', 'date', true, 'core');
add_output('ajax_imap_folder_expand', 'filter_expanded_folder_data', true);

/* select folder */
add_handler('ajax_imap_folder_display', 'login', false, 'core');
add_handler('ajax_imap_folder_display', 'load_user_data', true, 'core');
add_handler('ajax_imap_folder_display', 'load_imap_servers_from_config',  true);
add_handler('ajax_imap_folder_display', 'imap_folder_page',  true);
add_handler('ajax_imap_folder_display', 'save_imap_cache',  true);
add_handler('ajax_imap_folder_display', 'save_imap_servers',  true);
add_handler('ajax_imap_folder_display', 'date', true, 'core');
add_output('ajax_imap_folder_display', 'filter_folder_page', true);

/* allowed input */
return array(
    'allowed_pages' => array(
        'ajax_imap_debug',
        'ajax_imap_summary',
        'ajax_imap_unread',
        'ajax_imap_msg_text',
        'ajax_imap_folder_expand',
        'ajax_imap_folder_display',
        'ajax_imap_save_unread_state'
    ),

    'allowed_get' => array(
        'imap_server_id' => FILTER_VALIDATE_INT,
    ),

    'allowed_post' => array(
        'server_port' => FILTER_VALIDATE_INT,
        'server' => FILTER_SANITIZE_STRING,
        'username' => FILTER_SANITIZE_STRING,
        'password' => FILTER_SANITIZE_STRING,
        'imap_server_id' => FILTER_VALIDATE_INT,
        'imap_server_ids' => FILTER_SANITIZE_STRING,
        'imap_user' => FILTER_SANITIZE_STRING,
        'imap_pass' => FILTER_SANITIZE_STRING,
        'imap_delete' => FILTER_SANITIZE_STRING,
        'imap_connect' => FILTER_SANITIZE_STRING,
        'imap_remember' => FILTER_VALIDATE_INT,
        'summary_ids' => FILTER_SANITIZE_STRING,
        'imap_folder_ids' => FILTER_SANITIZE_STRING,
        'imap_forget' => FILTER_SANITIZE_STRING,
        'imap_save' => FILTER_SANITIZE_STRING,
        'imap_msg_uid' => FILTER_VALIDATE_INT,
        'submit_imap_server' => FILTER_SANITIZE_STRING,
        'new_imap_address' => FILTER_SANITIZE_STRING,
        'new_imap_port' => FILTER_VALIDATE_INT,
        'new_imap_name' => FILTER_SANITIZE_STRING,
        'tls' => FILTER_VALIDATE_BOOLEAN,
        'folder' => FILTER_SANITIZE_STRING,
        'force_update' => FILTER_VALIDATE_BOOLEAN,
        'formatted_unread_data' => FILTER_UNSAFE_RAW
    )
);

?>
