<?php

handler_source('imap');
output_source('imap');

/* add stuff to the home page */
add_handler('home', 'load_imap_servers_from_config', true, 'imap', 'load_user_data', 'after');
add_handler('home', 'add_imap_servers_to_page_data',  true, 'imap', 'load_imap_servers_from_config', 'after');

add_output('home', 'jquery_table', true, 'imap', 'jquery', 'after'); 
add_output('home', 'display_imap_summary', true, 'imap', 'page_js', 'before');
add_output('home', 'folder_list_start', true, 'imap', 'toolbar_end', 'after');
add_output('home', 'folder_list_end', true, 'imap', 'page_js', 'before');

/* servers page data */
add_handler('servers', 'load_imap_servers_from_config',  true, 'imap', 'date', 'after');
add_handler('servers', 'process_add_imap_server', true, 'imap', 'load_imap_servers_from_config', 'after');
add_handler('servers', 'add_imap_servers_to_page_data', 'imap', true, 'process_add_imap_server', 'after');
add_handler('servers', 'save_imap_servers',  true, 'imap', 'add_imap_servers_to_page_data', 'after');

/* servers page output */
add_output('servers', 'add_imap_server_dialog', true, 'imap', 'loading_icon', 'after');
add_output('servers', 'display_configured_imap_servers', true, 'imap', 'add_imap_server_dialog', 'after');

/* unread page data */
add_handler('unread', 'login', false, 'core');
add_handler('unread', 'load_user_data', true, 'core');
add_handler('unread', 'language',  true, 'core');
add_handler('unread', 'title', true, 'core');
add_handler('unread', 'date', true, 'core');
add_handler('unread', 'load_imap_servers_from_config', true);
add_handler('unread', 'add_imap_servers_to_page_data', true);
add_handler('unread', 'imap_bust_cache', true);
add_handler('unread', 'save_user_data', true, 'core');
add_handler('unread', 'logout', true, 'core');
add_handler('unread', 'http_headers', true, 'core');

add_output('unread', 'header_start', false, 'core');
add_output('unread', 'js_data', true, 'core');
add_output('unread', 'header_css', false, 'core');
add_output('unread', 'jquery', false, 'core');
add_output('unread', 'jquery_table', false, 'core');
add_output('unread', 'header_content', false, 'core');
add_output('unread', 'header_end', false, 'core');
add_output('unread', 'content_start', false, 'core');
add_output('unread', 'toolbar_start', true, 'core');
add_output('unread', 'logout', true, 'core');
add_output('unread', 'settings_link', true, 'core');
add_output('unread', 'servers_link', true, 'core');
add_output('unread', 'unread_link', true, 'core');
add_output('unread', 'homepage_link', true, 'core');
add_output('unread', 'login', false, 'core');
add_output('unread', 'date', true, 'core');
add_output('unread', 'title', true, 'core');
add_output('unread', 'msgs', false, 'core');
add_output('unread', 'loading_icon', false, 'core');
add_output('unread', 'toolbar_end', true, 'core');
add_output('unread', 'folder_list_start', true, 'imap');
add_output('unread', 'unread_message_list', true);
add_output('unread', 'folder_list_end', true, 'imap');
add_output('unread', 'page_js', true, 'core');
add_output('unread', 'content_end', false, 'core');

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

/* ajax folder callback data */
add_handler('ajax_imap_folders', 'login', false, 'core');
add_handler('ajax_imap_folders', 'load_user_data', true, 'core');
add_handler('ajax_imap_folders', 'load_imap_servers_from_config',  true);
add_handler('ajax_imap_folders', 'add_imap_servers_to_page_data',  true);
add_handler('ajax_imap_folders', 'load_imap_folders',  true);
add_handler('ajax_imap_folders', 'save_imap_cache',  true);
add_handler('ajax_imap_folders', 'save_imap_servers',  true);
add_handler('ajax_imap_folders', 'date', true, 'core');
add_output('ajax_imap_folders', 'filter_imap_folders', true);

/* allowed input */
return array(
    'allowed_pages' => array(
        'ajax_imap_debug',
        'ajax_imap_summary',
        'ajax_imap_unread',
        'ajax_imap_folders',
        'servers',
        'unread'
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
        'imap_user' => FILTER_SANITIZE_STRING,
        'imap_pass' => FILTER_SANITIZE_STRING,
        'imap_delete' => FILTER_SANITIZE_STRING,
        'imap_connect' => FILTER_SANITIZE_STRING,
        'imap_remember' => FILTER_VALIDATE_INT,
        'summary_ids' => FILTER_SANITIZE_STRING,
        'imap_unread_ids' => FILTER_SANITIZE_STRING,
        'imap_folder_ids' => FILTER_SANITIZE_STRING,
        'imap_forget' => FILTER_SANITIZE_STRING,
        'imap_save' => FILTER_SANITIZE_STRING,
        'submit_imap_server' => FILTER_SANITIZE_STRING,
        'new_imap_address' => FILTER_SANITIZE_STRING,
        'new_imap_port' => FILTER_VALIDATE_INT,
        'new_imap_name' => FILTER_SANITIZE_STRING,
        'tls' => FILTER_VALIDATE_BOOLEAN,
    )
);

?>
