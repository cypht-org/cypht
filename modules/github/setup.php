<?php

if (!defined('DEBUG_MODE')) { die(); }
handler_source('github');
output_source('github');

add_handler('ajax_hm_folders', 'github_folders_data',  true, 'github', 'load_user_data', 'after');
add_output('ajax_hm_folders', 'github_folders',  true, 'github', 'folder_list_content_start', 'before');

add_module_to_all_pages('handler', 'github_list_type', true, 'github', 'message_list_type', 'after');

add_handler('servers', 'setup_github_connect', true, 'github', 'load_user_data', 'after');
add_handler('servers', 'github_disconnect', true, 'github', 'setup_github_connect', 'after');
add_handler('servers', 'github_process_add_repo', true, 'github', 'github_disconnect', 'after');
add_handler('servers', 'github_process_remove_repo', true, 'github', 'github_process_add_repo', 'after');
add_output('servers', 'github_connect_section', true, 'github', 'server_content_end', 'before');
add_output('servers', 'github_add_repo', true, 'github', 'github_connect_section', 'after');

add_handler('home', 'process_github_authorization', true, 'github', 'load_user_data', 'after');

add_handler('ajax_message_action', 'github_message_action', true, 'github', 'load_user_data', 'after');

add_handler('ajax_github_data', 'login', false, 'core');
add_handler('ajax_github_data', 'load_user_data', true, 'core');
add_handler('ajax_github_data', 'message_list_type', true, 'core');
add_handler('ajax_github_data', 'language', true, 'core');
add_handler('ajax_github_data', 'github_list_data', true);
add_handler('ajax_github_data', 'close_session_early',  true, 'core');
add_handler('ajax_github_data', 'date', true, 'core');
add_handler('ajax_github_data', 'http_headers', true, 'core');
add_output('ajax_github_data', 'filter_github_data', true);

add_handler('info', 'load_github_repos', true, 'github', 'language', 'after');
add_output('info', 'display_github_status', true, 'github', 'server_status_start', 'after');

add_handler('ajax_github_event_detail', 'login', false, 'core');
add_handler('ajax_github_event_detail', 'load_user_data', true, 'core');
add_handler('ajax_github_event_detail', 'language', true, 'core');
add_handler('ajax_github_event_detail', 'github_event_detail',  true);
add_handler('ajax_github_event_detail', 'close_session_early', true, 'core');
add_handler('ajax_github_event_detail', 'date', true, 'core');
add_handler('ajax_github_event_detail', 'http_headers', true, 'core');
add_output('ajax_github_event_detail', 'filter_github_event_detail', true);

add_handler('ajax_github_status', 'login', false, 'core');
add_handler('ajax_github_status', 'load_user_data', true, 'core');
add_handler('ajax_github_status', 'language', true, 'core');
add_handler('ajax_github_status', 'github_status',  true);
add_handler('ajax_github_status', 'close_session_early',  true, 'core');
add_handler('ajax_github_status', 'date', true, 'core');
add_handler('ajax_github_status', 'http_headers', true, 'core');
add_output('ajax_github_status', 'filter_github_status', true);

add_handler('settings', 'process_unread_github_included', true, 'github', 'save_user_settings', 'before');
add_handler('settings', 'process_github_limit_setting', true, 'github', 'save_user_settings', 'before');
add_handler('settings', 'process_github_since_setting', true, 'github', 'save_user_settings', 'before');
add_output('settings', 'unread_github_included_setting', true, 'github', 'unread_source_max_setting', 'after');
add_output('settings', 'start_github_settings', true, 'github', 'end_settings_form', 'before');
add_output('settings', 'github_since_setting', true, 'github', 'start_github_settings', 'after');
add_output('settings', 'github_limit_setting', true, 'github', 'github_since_setting', 'after');

return array(
    'allowed_pages' => array(
        'ajax_github_status',
        'ajax_github_data',
        'ajax_github_event_detail',
    ),
    'allowed_output' => array(
        'github_msg_text' => array(FILTER_UNSAFE_RAW, false),
        'github_server_id' => array(FILTER_VALIDATE_INT, false),
        'github_status_display' => array(FILTER_UNSAFE_RAW, false),
        'github_status_repo' => array(FILTER_SANITIZE_STRING, false),
    ),
    'allowed_post' => array(
        'github_unread' => FILTER_VALIDATE_INT,
        'github_since' => FILTER_SANITIZE_STRING,
        'github_limit' => FILTER_VALIDATE_INT,
        'github_uid' => FILTER_SANITIZE_STRING,
        'github_disconnect' => FILTER_SANITIZE_STRING,
        'new_github_repo_owner' => FILTER_SANITIZE_STRING,
        'new_github_repo' => FILTER_SANITIZE_STRING,
        'github_remove_repo' => FILTER_SANITIZE_STRING,
        'github_add_repo' => FILTER_SANITIZE_STRING,
        'github_repo' => FILTER_SANITIZE_STRING,
        'unread_exclude_github' => FILTER_VALIDATE_INT,
    )
);


