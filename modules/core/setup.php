<?php

/**
 * Core modules
 * @package modules
 * @subpackage core/functions
 */

require_once APP_PATH.'modules/core/functions.php';

handler_source('core');
output_source('core');

/* homepage */
setup_base_page('home');
add_output('home', 'home_heading', true, 'core', 'content_section_start', 'after');

/* servers page */
setup_base_page('servers');
add_handler('servers', 'reload_folder_cookie', true, 'core', 'save_user_data', 'after');
add_output('servers', 'server_content_start', true, 'core', 'content_section_start', 'after');
add_output('servers', 'server_content_end', true, 'core', 'content_section_end', 'before');

/* compose */
setup_base_page('compose');

/* save settings */
setup_base_page('save');
add_handler('save', 'process_save_form', true, 'core', 'load_user_data', 'after');
add_output('save', 'save_form', true, 'core', 'content_section_start', 'after');

/* settings */
setup_base_page('settings');
add_handler('settings', 'process_language_setting', true, 'core', 'date', 'after');
add_handler('settings', 'process_list_style_setting', true, 'core', 'date', 'after');
add_handler('settings', 'process_timezone_setting', true, 'core', 'date', 'after');
add_handler('settings', 'process_unread_since_setting', true, 'core', 'date', 'after');
add_handler('settings', 'process_flagged_since_setting', true, 'core', 'date', 'after');
add_handler('settings', 'process_flagged_source_max_setting', true, 'core', 'date', 'after');
add_handler('settings', 'process_unread_source_max_setting', true, 'core', 'date', 'after');
add_handler('settings', 'process_all_source_max_setting', true, 'core', 'date', 'after');
add_handler('settings', 'process_all_since_setting', true, 'core', 'date', 'after');
add_handler('settings', 'process_all_email_since_setting', true, 'core', 'date', 'after');
add_handler('settings', 'process_all_email_source_max_setting', true, 'core', 'date', 'after');
add_handler('settings', 'save_user_settings', true, 'core', 'save_user_data', 'before');
add_handler('settings', 'reload_folder_cookie', true, 'core', 'save_user_settings', 'after');

add_output('settings', 'start_settings_form', true, 'core', 'content_section_start', 'after');
add_output('settings', 'start_general_settings', true, 'core', 'start_settings_form', 'after');
add_output('settings', 'language_setting', true, 'core', 'start_general_settings', 'after');
add_output('settings', 'timezone_setting', true, 'core', 'language_setting', 'after');
add_output('settings', 'list_style_setting', true, 'core', 'timezone_setting', 'after');
add_output('settings', 'start_unread_settings', true, 'core', 'list_style_setting', 'after');
add_output('settings', 'unread_since_setting', true, 'core', 'start_unread_settings', 'after');
add_output('settings', 'unread_source_max_setting', true, 'core', 'unread_since_setting', 'after');
add_output('settings', 'start_flagged_settings', true, 'core', 'unread_source_max_setting', 'after');
add_output('settings', 'flagged_since_setting', true, 'core', 'start_flagged_settings', 'after');
add_output('settings', 'flagged_source_max_setting', true, 'core', 'flagged_since_setting', 'after');
add_output('settings', 'start_everything_settings', true, 'core', 'flagged_source_max_setting', 'after');
add_output('settings', 'all_since_setting', true, 'core', 'start_everything_settings', 'after');
add_output('settings', 'all_source_max_setting', true, 'core', 'all_since_setting', 'after');
add_output('settings', 'start_all_email_settings', true, 'core', 'all_source_max_setting', 'after');
add_output('settings', 'all_email_since_setting', true, 'core', 'start_all_email_settings', 'after');
add_output('settings', 'all_email_source_max_setting', true, 'core', 'all_email_since_setting', 'after');
add_output('settings', 'end_settings_form', true, 'core', 'content_section_end', 'before');

/* message list page */
setup_base_page('message_list');
add_output('message_list', 'message_list_heading', true, 'core', 'content_section_start', 'after');
add_output('message_list', 'message_list_start', true, 'core', 'message_list_heading', 'after');
add_output('message_list', 'message_list_end', true, 'core', 'message_list_start', 'after');

/* search page */
setup_base_page('search');
add_output('search', 'search_content_start', true, 'core', 'content_section_start', 'after');
add_output('search', 'search_form_start', true, 'core', 'search_content_start', 'after');
add_output('search', 'search_form_content', true, 'core', 'search_form_start', 'after');
add_output('search', 'search_form_end', true, 'core', 'search_form_content', 'after');
add_output('search', 'message_list_start', true, 'core', 'search_form_end', 'after');
add_output('search', 'search_results_table_end', true, 'core', 'message_list_start', 'after');
add_output('search', 'search_content_end', true, 'core', 'search_results_table_end', 'after');

/* reset search form */
setup_base_ajax_page('ajax_reset_search', 'core');
add_handler('ajax_reset_search', 'reset_search', true, 'core', 'load_user_data', 'after');

/* message view page */
setup_base_page('message');
add_output('message', 'message_start', true, 'core', 'content_section_start', 'after');
add_output('message', 'message_end', true, 'core', 'message_start', 'after');

/* not-found page data and output */
setup_base_page('notfound');
add_output('notfound', 'notfound_content', true, 'core', 'content_section_start', 'after');

/* message action ajax request */
setup_base_ajax_page('ajax_message_action', 'core');

/* folder list update ajax request */
setup_base_ajax_page('ajax_hm_folders', 'core');
add_output('ajax_hm_folders', 'folder_list_content_start', true);
add_output('ajax_hm_folders', 'main_menu_start', true);
add_output('ajax_hm_folders', 'search_from_folder_list', true);
add_output('ajax_hm_folders', 'main_menu_content', true);
add_output('ajax_hm_folders', 'logout_menu_item', true);
add_output('ajax_hm_folders', 'main_menu_end', true);
add_output('ajax_hm_folders', 'email_menu_content', true);
add_output('ajax_hm_folders', 'settings_menu_start', true);
add_output('ajax_hm_folders', 'settings_servers_link', true);
add_output('ajax_hm_folders', 'settings_site_link', true);
add_output('ajax_hm_folders', 'settings_save_link', true);
add_output('ajax_hm_folders', 'settings_menu_end', true);
add_output('ajax_hm_folders', 'folder_list_content_end', true);


/* allowed input */
return array(
    'allowed_pages' => array(
        'save',
        'home',
        'compose',
        'message_list',
        'message',
        'settings',
        'servers',
        'ajax_hm_folders',
        'ajax_message_action',
        'ajax_reset_search',
        'notfound',
        'search'
    ),
    'allowed_output' => array(
        'date' => array(FILTER_SANITIZE_STRING, false),
        'formatted_folder_list' => array(FILTER_UNSAFE_RAW, false),
        'router_user_msgs' => array(FILTER_SANITIZE_STRING, FILTER_REQUIRE_ARRAY),
        'router_login_state' => array(FILTER_VALIDATE_BOOLEAN, false),
        'formatted_message_list' => array(FILTER_UNSAFE_RAW, FILTER_REQUIRE_ARRAY),
        'just_saved_credentials' => array(FILTER_VALIDATE_BOOLEAN, false),
        'just_forgot_credentials' => array(FILTER_VALIDATE_BOOLEAN, false),
        'deleted_server_id' => array(FILTER_VALIDATE_INT, false),
        'msg_headers' => array(FILTER_UNSAFE_RAW, false),
        'msg_text' => array(FILTER_UNSAFE_RAW, false),
        'msg_parts' => array(FILTER_UNSAFE_RAW, false),
        'page_links' => array(FILTER_UNSAFE_RAW, false),
    ),
    'allowed_cookie' => array(
        'PHPSESSID' => FILTER_SANITIZE_STRING,
        'hm_id' => FILTER_SANITIZE_STRING,
        'hm_session' => FILTER_SANITIZE_STRING,
        'hm_msgs'    => FILTER_SANITIZE_STRING
    ),
    'allowed_server' => array(
        'REQUEST_URI' => FILTER_SANITIZE_STRING,
        'SERVER_ADDR' => FILTER_VALIDATE_IP,
        'REMOTE_ADDR' => FILTER_VALIDATE_IP,
        'SERVER_PORT' => FILTER_VALIDATE_INT,
        'SERVER_PROTOCOL' => FILTER_SANITIZE_STRING,
        'PHP_SELF' => FILTER_SANITIZE_STRING,
        'REQUEST_SCHEME' => FILTER_SANITIZE_STRING,
        'HTTP_HOST' => FILTER_SANITIZE_STRING,
        'HTTP_ACCEPT_LANGUAGE' => FILTER_SANITIZE_STRING,
        'HTTP_ACCEPT_ENCODING' => FILTER_SANITIZE_STRING,
        'HTTP_ACCEPT_CHARSET' => FILTER_SANITIZE_STRING,
        'HTTP_ACCEPT' => FILTER_SANITIZE_STRING,
        'HTTP_USER_AGENT' => FILTER_SANITIZE_STRING,
        'HTTPS' => FILTER_SANITIZE_STRING,
        'SERVER_NAME' => FILTER_SANITIZE_STRING,
        'HTTP_X_REQUESTED_WITH' => FILTER_SANITIZE_STRING
    ),

    'allowed_get' => array(
        'page' => FILTER_SANITIZE_STRING,
        'msgs' => FILTER_SANITIZE_STRING,
        'list_path' => FILTER_SANITIZE_STRING,
        'list_parent' => FILTER_SANITIZE_STRING,
        'list_page' => FILTER_VALIDATE_INT,
        'uid' => FILTER_SANITIZE_STRING,
        'search_terms' => FILTER_SANITIZE_STRING,
        'search_since' => FILTER_SANITIZE_STRING,
        'search_fld' => FILTER_SANITIZE_STRING,
    ),

    'allowed_post' => array(
        'payload' => FILTER_SANITIZE_STRING,
        'hm_page_key' => FILTER_SANITIZE_STRING,
        'logout' => FILTER_VALIDATE_BOOLEAN,
        'save_and_logout' => FILTER_VALIDATE_BOOLEAN,
        'limit' => FILTER_VALIDATE_INT,
        'username' => FILTER_SANITIZE_STRING,
        'password' => FILTER_SANITIZE_STRING,
        'hm_ajax_hook' => FILTER_SANITIZE_STRING,
        'save_settings' => FILTER_SANITIZE_STRING,
        'save_settings_permanently' => FILTER_SANITIZE_STRING,
        'save_settings_permanently_then_logout' => FILTER_SANITIZE_STRING,
        'language' => FILTER_SANITIZE_STRING,
        'flagged_per_source' => FILTER_VALIDATE_INT,
        'flagged_since' => FILTER_SANITIZE_STRING,
        'unread_per_source' => FILTER_VALIDATE_INT,
        'unread_since' => FILTER_SANITIZE_STRING,
        'all_email_per_source' => FILTER_VALIDATE_INT,
        'all_email_since' => FILTER_SANITIZE_STRING,
        'all_per_source' => FILTER_VALIDATE_INT,
        'all_since' => FILTER_SANITIZE_STRING,
        'list_style' => FILTER_SANITIZE_STRING,
        'timezone' => FILTER_SANITIZE_STRING,
        'section_state' => FILTER_SANITIZE_STRING,
        'section_class' => FILTER_SANITIZE_STRING,
        'message_ids' => FILTER_SANITIZE_STRING,
        'action_type' => FILTER_SANITIZE_STRING,
        'message_list_since' => FILTER_SANITIZE_STRING,
    )
);


