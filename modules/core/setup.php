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
add_handler('home', 'check_missing_passwords', true, 'core', 'load_user_data', 'after');
add_output('home', 'home_heading', true, 'core', 'content_section_start', 'after');
add_output('home', 'home_password_dialogs', true, 'core', 'home_heading', 'after');

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
add_handler('settings', 'process_hide_folder_icons', true, 'core', 'date', 'after');
add_handler('settings', 'process_delete_prompt_setting', true, 'core', 'date', 'after');
add_handler('settings', 'process_no_password_setting', true, 'core', 'date', 'after');
add_handler('settings', 'process_start_page_setting', true, 'core', 'date', 'after');
add_handler('settings', 'process_default_sort_order_setting', true, 'core', 'date', 'after');
add_handler('settings', 'process_mailto_handler_setting', true, 'core', 'date', 'after');
add_handler('settings', 'process_show_list_icons', true, 'core', 'date', 'after');
add_handler('settings', 'save_user_settings', true, 'core', 'save_user_data', 'before');
add_handler('settings', 'reload_folder_cookie', true, 'core', 'save_user_settings', 'after');

add_output('settings', 'start_settings_form', true, 'core', 'content_section_start', 'after');
add_output('settings', 'start_general_settings', true, 'core', 'start_settings_form', 'after');
add_output('settings', 'language_setting', true, 'core', 'start_general_settings', 'after');
add_output('settings', 'timezone_setting', true, 'core', 'language_setting', 'after');
add_output('settings', 'no_folder_icon_setting', true, 'core', 'timezone_setting', 'after');
add_output('settings', 'mailto_handler_setting', true, 'core', 'no_folder_icon_setting', 'after');
add_output('settings', 'list_style_setting', true, 'core', 'mailto_handler_setting', 'after');
add_output('settings', 'msg_list_icons_setting', true, 'core', 'list_style_setting', 'before');
add_output('settings', 'delete_prompt_setting', true, 'core', 'list_style_setting', 'after');
add_output('settings', 'no_password_setting', true, 'core', 'delete_prompt_setting', 'after');
add_output('settings', 'start_page_setting', true, 'core', 'no_password_setting', 'after');
add_output('settings', 'default_sort_order_setting', true, 'core', 'start_page_setting', 'after');
add_output('settings', 'start_unread_settings', true, 'core', 'default_sort_order_setting', 'after');
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
add_handler('message_list', 'default_sort_order_setting', true, 'core', 'load_user_data', 'after');
add_output('message_list', 'message_list_heading', true, 'core', 'content_section_start', 'after');
add_output('message_list', 'message_list_start', true, 'core', 'message_list_heading', 'after');
add_output('message_list', 'message_list_end', true, 'core', 'message_list_start', 'after');

/* search page */
setup_base_page('search');
add_handler('search', 'default_sort_order_setting', true, 'core', 'load_user_data', 'after');
add_output('search', 'search_content_start', true, 'core', 'content_section_start', 'after');
add_output('search', 'search_form_start', true, 'core', 'search_content_start', 'after');
add_output('search', 'search_form_content', true, 'core', 'search_form_start', 'after');
add_output('search', 'search_form_end', true, 'core', 'search_form_content', 'after');
add_output('search', 'message_list_start', true, 'core', 'search_form_end', 'after');
add_output('search', 'search_results_table_end', true, 'core', 'message_list_start', 'after');
add_output('search', 'search_content_end', true, 'core', 'search_results_table_end', 'after');
add_output('search', 'search_move_copy_controls', true, 'core', 'search_content_start', 'before');

/* advanced search page */
add_handler('advanced_search', 'default_sort_order_setting', true, 'core', 'load_user_data', 'after');
add_output('advanced_search', 'search_move_copy_controls', true, 'core', 'advanced_search_content_start', 'before');

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

/* password udpates when not saving between logins */
setup_base_ajax_page('ajax_update_server_pw', 'core');
add_handler('ajax_update_server_pw', 'save_user_data', true, 'core', 'language', 'before');
add_handler('ajax_update_server_pw', 'check_missing_passwords', true, 'core', 'load_user_data', 'after');
add_handler('ajax_update_server_pw', 'process_pw_update', true, 'core', 'check_missing_passwords', 'after');

/* folder list update ajax request */
setup_base_ajax_page('ajax_hm_folders', 'core');
add_handler('ajax_hm_folders', 'check_folder_icon_setting', true, 'core', 'load_user_data', 'after');
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

/* no-op to test connectivity */
add_handler('ajax_test', 'login', false, 'core');
add_handler('ajax_test', 'load_user_data', true, 'core');
add_handler('ajax_test', 'date', true, 'core');
add_handler('ajax_test', 'http_headers', true, 'core');

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
        'ajax_test',
        'ajax_hm_folders',
        'ajax_message_action',
        'ajax_reset_search',
        'ajax_update_server_pw',
        'ajax_no_op',
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
        'folder_status' => array(FILTER_SANITIZE_STRING, FILTER_REQUIRE_ARRAY),
    ),
    'allowed_cookie' => array(
        'CYPHTID' => FILTER_SANITIZE_STRING,
        'hm_id' => FILTER_SANITIZE_STRING,
        'hm_session' => FILTER_SANITIZE_STRING,
        'hm_msgs'    => FILTER_SANITIZE_STRING
    ),
    'allowed_server' => array(
        'REQUEST_URI' => FILTER_SANITIZE_STRING,
        'REQUEST_METHOD' => FILTER_SANITIZE_STRING,
        'SERVER_ADDR' => FILTER_VALIDATE_IP,
        'REMOTE_ADDR' => FILTER_VALIDATE_IP,
        'SERVER_PORT' => FILTER_VALIDATE_INT,
        'SERVER_PROTOCOL' => FILTER_SANITIZE_STRING,
        'PHP_SELF' => FILTER_SANITIZE_STRING,
        'REQUEST_SCHEME' => FILTER_SANITIZE_STRING,
        'HTTP_HOST' => FILTER_SANITIZE_STRING,
        'HTTP_ORIGIN' => FILTER_VALIDATE_URL,
        'HTTP_REFERER' => FILTER_VALIDATE_URL,
        'HTTP_ACCEPT_LANGUAGE' => FILTER_SANITIZE_STRING,
        'HTTP_ACCEPT_ENCODING' => FILTER_SANITIZE_STRING,
        'HTTP_ACCEPT_CHARSET' => FILTER_SANITIZE_STRING,
        'HTTP_ACCEPT' => FILTER_SANITIZE_STRING,
        'HTTP_USER_AGENT' => FILTER_SANITIZE_STRING,
        'HTTPS' => FILTER_SANITIZE_STRING,
        'SERVER_NAME' => FILTER_SANITIZE_STRING,
        'HTTP_X_REQUESTED_WITH' => FILTER_SANITIZE_STRING,
        'HTTP_X_FORWARDED_HOST' => FILTER_SANITIZE_STRING
    ),

    'allowed_get' => array(
        'page' => FILTER_SANITIZE_STRING,
        'msgs' => FILTER_SANITIZE_STRING,
        'list_path' => FILTER_SANITIZE_STRING,
        'list_parent' => FILTER_SANITIZE_STRING,
        'list_page' => FILTER_VALIDATE_INT,
        'uid' => FILTER_SANITIZE_STRING,
        'search_terms' => FILTER_UNSAFE_RAW,
        'search_since' => FILTER_SANITIZE_STRING,
        'search_fld' => FILTER_SANITIZE_STRING,
        'filter' => FILTER_SANITIZE_STRING,
        'sort' => FILTER_SANITIZE_STRING,
        'keyword' => FILTER_SANITIZE_STRING,
    ),

    'allowed_post' => array(
        'payload' => FILTER_SANITIZE_STRING,
        'hm_page_key' => FILTER_SANITIZE_STRING,
        'logout' => FILTER_VALIDATE_BOOLEAN,
        'save_and_logout' => FILTER_VALIDATE_BOOLEAN,
        'limit' => FILTER_VALIDATE_INT,
        'username' => FILTER_SANITIZE_STRING,
        'show_list_icons' => FILTER_VALIDATE_BOOLEAN,
        'password' => FILTER_UNSAFE_RAW,
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
        'no_folder_icons' => FILTER_VALIDATE_BOOLEAN,
        'mailto_handler' => FILTER_VALIDATE_BOOLEAN,
        'list_style' => FILTER_SANITIZE_STRING,
        'timezone' => FILTER_SANITIZE_STRING,
        'disable_delete_prompt' => FILTER_VALIDATE_INT,
        'section_state' => FILTER_SANITIZE_STRING,
        'section_class' => FILTER_SANITIZE_STRING,
        'message_ids' => FILTER_SANITIZE_STRING,
        'action_type' => FILTER_SANITIZE_STRING,
        'server_pw_id' => FILTER_SANITIZE_STRING,
        'message_list_since' => FILTER_SANITIZE_STRING,
        'no_password_save' => FILTER_VALIDATE_BOOLEAN,
        'start_page' => FILTER_SANITIZE_STRING,
        'default_sort_order' => FILTER_SANITIZE_STRING,
        'stay_logged_in' => FILTER_VALIDATE_BOOLEAN
    )
);


