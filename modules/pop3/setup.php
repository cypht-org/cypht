<?php

if (!defined('DEBUG_MODE')) { die(); }

handler_source('pop3');
output_source('pop3');

/* add stuff to the home page */
add_handler('home', 'load_pop3_servers_from_config', true, 'pop3', 'language', 'after');
add_handler('home', 'add_pop3_servers_to_page_data', true, 'pop3', 'load_pop3_servers_from_config', 'after');
add_output('home', 'display_pop3_summary', true, 'pop3', 'server_summary_start', 'after');

/* message list page */
add_handler('message_list', 'load_pop3_servers_from_config', true, 'pop3', 'language', 'after');
add_handler('message_list', 'add_pop3_servers_to_page_data', true, 'pop3', 'load_pop3_servers_from_config', 'after');
add_output('message_list', 'pop3_server_ids', true, 'pop3', 'page_js', 'before');

/* message view page */
add_handler('message', 'load_pop3_servers_from_config', true, 'pop3', 'language', 'after');
add_handler('message', 'add_pop3_servers_to_page_data', true, 'pop3', 'load_pop3_servers_from_config', 'after');

/* servers page */
add_handler('servers', 'load_pop3_servers_from_config', true, 'pop3', 'language', 'after');
add_handler('servers', 'process_add_pop3_server', true, 'pop3', 'load_pop3_servers_from_config', 'after');
add_handler('servers', 'add_pop3_servers_to_page_data', true, 'pop3', 'process_add_pop3_server', 'after');
add_handler('servers', 'save_pop3_servers', true, 'pop3', 'add_pop3_servers_to_page_data', 'after');
add_output('servers', 'add_pop3_server_dialog', true, 'pop3', 'content_section_start', 'after');
add_output('servers', 'display_configured_pop3_servers', true, 'pop3', 'add_pop3_server_dialog', 'after');

/* settings page */
add_handler('settings', 'load_pop3_servers_from_config', true, 'pop3', 'language', 'after');
add_handler('settings', 'add_pop3_servers_to_page_data', true, 'pop3', 'load_pop3_servers_from_config', 'after');

/* compose page */
add_handler('compose', 'load_pop3_servers_from_config', true, 'pop3', 'language', 'after');
add_handler('compose', 'add_pop3_servers_to_page_data', true, 'pop3', 'load_pop3_servers_from_config', 'after');

/* search page */
add_handler('search', 'load_pop3_servers_from_config', true, 'pop3', 'language', 'after');
add_handler('search', 'add_pop3_servers_to_page_data', true, 'pop3', 'load_pop3_servers_from_config', 'after');

/* profile page */
add_handler('profiles', 'load_pop3_servers_from_config', true, 'pop3', 'language', 'after');
add_handler('profiles', 'add_pop3_servers_to_page_data', true, 'pop3', 'load_pop3_servers_from_config', 'after');

/* not found */
add_handler('notfound', 'load_pop3_servers_from_config', true, 'pop3', 'language', 'after');
add_handler('notfound', 'add_pop3_servers_to_page_data', true, 'pop3', 'load_pop3_servers_from_config', 'after');

add_handler('ajax_hm_folders', 'load_pop3_servers_from_config', true, 'pop3', 'load_user_data', 'after');
add_handler('ajax_hm_folders', 'load_pop3_folders', true, 'pop3', 'load_pop3_servers_from_config', 'after');
add_handler('ajax_hm_folders', 'add_pop3_servers_to_page_data', true, 'pop3', 'load_pop3_servers_from_config', 'after');
add_output('ajax_hm_folders', 'filter_pop3_folders', true, 'pop3', 'folder_list_content', 'before');

/* select pop3 "folder" */
add_handler('ajax_pop3_folder_display', 'login', false, 'core');
add_handler('ajax_pop3_folder_display', 'load_user_data', true, 'core');
add_handler('ajax_pop3_folder_display', 'load_pop3_servers_from_config', true);
add_handler('ajax_pop3_folder_display', 'pop3_folder_page', true);
add_handler('ajax_pop3_folder_display', 'date', true, 'core');
add_output('ajax_pop3_folder_display', 'filter_pop3_message_list', true);

/* combined inbox */
add_handler('ajax_pop3_combined_inbox', 'login', false, 'core');
add_handler('ajax_pop3_combined_inbox', 'load_user_data', true, 'core');
add_handler('ajax_pop3_combined_inbox', 'load_pop3_servers_from_config',  true);
add_handler('ajax_pop3_combined_inbox', 'pop3_folder_page',  true);
add_handler('ajax_pop3_combined_inbox', 'date', true, 'core');
add_output('ajax_pop3_combined_inbox', 'filter_pop3_message_list', true);

/* view pop3 message */
add_handler('ajax_pop3_message_display', 'login', false, 'core');
add_handler('ajax_pop3_message_display', 'load_user_data', true, 'core');
add_handler('ajax_pop3_message_display', 'load_pop3_servers_from_config', true);
add_handler('ajax_pop3_message_display', 'pop3_message_content', true);
add_handler('ajax_pop3_message_display', 'date', true, 'core');
add_output('ajax_pop3_message_display', 'filter_pop3_message_content', true);

/* ajax server setup callback data */
add_handler('ajax_pop3_debug', 'login', false, 'core');
add_handler('ajax_pop3_debug', 'load_user_data',  true, 'core');
add_handler('ajax_pop3_debug', 'load_pop3_servers_from_config',  true);
add_handler('ajax_pop3_debug', 'load_pop3_cache',  true);
add_handler('ajax_pop3_debug', 'save_pop3_cache',  true);
add_handler('ajax_pop3_debug', 'add_pop3_servers_to_page_data',  true);
add_handler('ajax_pop3_debug', 'pop3_connect', true);
add_handler('ajax_pop3_debug', 'pop3_delete', true);
add_handler('ajax_pop3_debug', 'pop3_forget', true);
add_handler('ajax_pop3_debug', 'pop3_save', true);
add_handler('ajax_pop3_debug', 'save_pop3_servers', true);
add_handler('ajax_pop3_debug', 'save_user_data',  true, 'core');
add_handler('ajax_pop3_debug', 'date', true, 'core');

return array(
    'allowed_pages' => array(
        'ajax_pop3_debug',
        'ajax_pop3_summary',
        'ajax_pop3_message_display',
        'ajax_pop3_folder_display',
        'ajax_pop3_combined_inbox',
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
        'pop3_user' => FILTER_SANITIZE_STRING,
        'pop3_pass' => FILTER_SANITIZE_STRING,
        'pop3_list_path' => FILTER_SANITIZE_STRING,
        'pop3_uid' => FILTER_VALIDATE_INT
    )
);

?>
