<?php

if (!defined('DEBUG_MODE')) { die(); }

handler_source('wordpress');
output_source('wordpress');

add_handler('message', 'wordpress_list_type', true, 'wordpress', 'message_list_type', 'after');

add_handler('message_list', 'wp_load_sources', true, 'wordpress', 'load_user_data', 'after');
add_handler('message_list', 'wordpress_list_type', true, 'wordpress', 'message_list_type', 'after');

add_handler('ajax_hm_folders', 'wordpress_folders_data',  true, 'wordpress', 'load_user_data', 'after');
add_output('ajax_hm_folders', 'wordpress_folders',  true, 'wordpress', 'folder_list_content_start', 'before');

add_handler('servers', 'setup_wordpress_connect', true, 'wordpress', 'load_user_data', 'after');
add_handler('servers', 'wordpress_disconnect', true, 'wordpress', 'setup_wordpress_connect', 'after');
add_output('servers', 'wordpress_connect_section', true, 'wordpress', 'server_content_end', 'before');

add_handler('ajax_message_action', 'wordpress_list_type', true, 'wordpress', 'load_user_data', 'after');
add_handler('ajax_message_action', 'wordpress_msg_action', true, 'wordpress', 'wordpress_list_type', 'after');

add_handler('home', 'process_wordpress_authorization', true, 'wordpress', 'load_user_data', 'after');

add_handler('ajax_wordpess_notifications', 'login', false, 'core');
add_handler('ajax_wordpess_notifications', 'load_user_data', true, 'core');
add_handler('ajax_wordpess_notifications', 'message_list_type', true, 'core');
add_handler('ajax_wordpess_notifications', 'language', true, 'core');
add_handler('ajax_wordpess_notifications', 'wordpress_list_type', true, 'core');
add_handler('ajax_wordpess_notifications', 'wp_notification_data',  true);
add_handler('ajax_wordpess_notifications', 'date', true, 'core');
add_handler('ajax_wordpess_notifications', 'http_headers', true, 'core');
add_output('ajax_wordpess_notifications', 'filter_wp_notification_data', true);

add_handler('ajax_wp_notice_display', 'login', false, 'core');
add_handler('ajax_wp_notice_display', 'load_user_data', true, 'core');
add_handler('ajax_wp_notice_display', 'language', true, 'core');
add_handler('ajax_wp_notice_display', 'get_wp_notice_data', true);
add_handler('ajax_wp_notice_display', 'close_session_early',  true, 'core');
add_handler('ajax_wp_notice_display', 'date', true, 'core');
add_handler('ajax_wp_notice_display', 'http_headers', true, 'core');
add_output('ajax_wp_notice_display', 'filter_wp_notice_data', true);

add_handler('settings', 'process_unread_wp_included', true, 'wordpress', 'save_user_settings', 'before');
add_handler('settings', 'process_wordpress_since_setting', true, 'wordpress', 'save_user_settings', 'before');
add_output('settings', 'unread_wp_included_setting', true, 'wordpress', 'unread_source_max_setting', 'after');
add_output('settings', 'start_wordpress_settings', true, 'wordpress', 'end_settings_form', 'before');
add_output('settings', 'wordpress_since_setting', true, 'wordpress', 'start_wordpress_settings', 'after');

return array(
    'allowed_pages' => array(
        'ajax_wordpess_freshly_pressed',
        'ajax_wordpess_notifications',
        'ajax_wp_notice_display',
    ),
    'allowed_post' => array(
        'wp_disconnect' => FILTER_SANITIZE_STRING,
        'unread_exclude_wordpress' => FILTER_VALIDATE_INT,
        'wp_uid' => FILTER_SANITIZE_STRING,
        'wordpress_limit' => FILTER_VALIDATE_INT,
        'wordpress_since' => FILTER_SANITIZE_STRING,
    ),
    'allowed_output' => array(
        'wp_notice_text' => array(FILTER_UNSAFE_RAW, false),
        'wp_notice_headers' => array(FILTER_UNSAFE_RAW, false),
    ),
);
