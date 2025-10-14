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
add_output('home', 'home_heading', true, 'core', 'version_upgrade_checker', 'after');
add_output('home', 'home_password_dialogs', true, 'core', 'home_heading', 'after');

/* servers page */
setup_base_page('servers');
add_handler('servers', 'reload_folder_cookie', true, 'core', 'save_user_data', 'after');
add_output('servers', 'server_content_start', true, 'core', 'version_upgrade_checker', 'after');
add_output('servers', 'server_config_stepper', true, 'core', 'server_content_start', 'after');
add_output('servers', 'server_config_stepper_end_part', true, 'core', 'server_config_stepper', 'after');
add_output('servers', 'server_config_stepper_accordion_end_part', true, 'core', 'server_config_stepper_end_part', 'after');
add_output('servers', 'server_content_end', true, 'core', 'content_section_end', 'before');

/* compose */
setup_base_page('compose');

/* save settings */
setup_base_page('save');
add_handler('save', 'process_save_form', true, 'core', 'load_user_data', 'after');
add_output('save', 'save_form', true, 'core', 'version_upgrade_checker', 'after');

/* settings */
setup_base_page('settings');
add_handler('settings', 'process_language_setting', true, 'core', 'date', 'after');
add_handler('settings', 'process_list_style_setting', true, 'core', 'date', 'after');
add_handler('settings', 'process_timezone_setting', true, 'core', 'date', 'after');
add_handler('settings', 'process_warn_for_unsaved_changes_setting', true, 'core', 'date', 'after');
add_handler('settings', 'process_unread_since_setting', true, 'core', 'date', 'after');
add_handler('settings', 'process_flagged_since_setting', true, 'core', 'date', 'after');
add_handler('settings', 'process_flagged_source_max_setting', true, 'core', 'date', 'after');
add_handler('settings', 'process_unread_source_max_setting', true, 'core', 'date', 'after');
add_handler('settings', 'process_all_source_max_setting', true, 'core', 'date', 'after');
add_handler('settings', 'process_all_since_setting', true, 'core', 'date', 'after');
add_handler('settings', 'process_all_email_since_setting', true, 'core', 'date', 'after');
add_handler('settings', 'process_all_email_source_max_setting', true, 'core', 'date', 'after');
add_handler('settings', 'process_junk_since_setting', true, 'core', 'date', 'after');
add_handler('settings', 'process_junk_source_max_setting', true, 'core', 'date', 'after');
add_handler('settings', 'process_snoozed_since_setting', true, 'core', 'date', 'after');
add_handler('settings', 'process_snoozed_source_max_setting', true, 'core', 'date', 'after');
add_handler('settings', 'process_trash_since_setting', true, 'core', 'date', 'after');
add_handler('settings', 'process_trash_source_max_setting', true, 'core', 'date', 'after');
add_handler('settings', 'process_drafts_since_setting', true, 'core', 'date', 'after');
add_handler('settings', 'process_drafts_source_max_setting', true, 'core', 'date', 'after');
add_handler('settings', 'process_hide_folder_icons', true, 'core', 'date', 'after');
add_handler('settings', 'process_delete_prompt_setting', true, 'core', 'date', 'after');
add_handler('settings', 'process_delete_attachment_setting', true, 'core', 'date', 'after');
add_handler('settings', 'process_no_password_setting', true, 'core', 'date', 'after');
add_handler('settings', 'process_start_page_setting', true, 'core', 'date', 'after');
add_handler('settings', 'process_default_sort_order_setting', true, 'core', 'date', 'after');
add_handler('settings', 'process_mailto_handler_setting', true, 'core', 'date', 'after');
add_handler('settings', 'process_show_list_icons', true, 'core', 'date', 'after');
add_handler('settings', 'reset_factory', true, 'core', 'save_user_data', 'before');
add_handler('settings', 'save_user_settings', true, 'core', 'save_user_data', 'before');
add_handler('settings', 'reload_folder_cookie', true, 'core', 'save_user_settings', 'after');
add_handler('settings', 'privacy_settings', true, 'core', 'date', 'after');

add_output('settings', 'start_settings_form', true, 'core', 'version_upgrade_checker', 'after');
add_output('settings', 'start_search_settings', true, 'core', 'start_settings_form', 'after');
add_output('settings', 'start_general_settings', true, 'core', 'start_search_settings', 'after');
add_output('settings', 'language_setting', true, 'core', 'start_general_settings', 'after');
add_output('settings', 'timezone_setting', true, 'core', 'language_setting', 'after');
add_output('settings', 'warn_for_unsaved_changes_setting', true, 'core', 'timezone_setting', 'after');
add_output('settings', 'no_folder_icon_setting', true, 'core', 'warn_for_unsaved_changes_setting', 'after');
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
add_output('settings', 'start_junk_settings', true, 'core', 'flagged_source_max_setting', 'after');
add_output('settings', 'junk_since_setting', true, 'core', 'start_junk_settings', 'after');
add_output('settings', 'junk_source_max_setting', true, 'core', 'junk_since_setting', 'after');
add_output('settings', 'start_snoozed_settings', true, 'core', 'junk_source_max_setting', 'after');
add_output('settings', 'snoozed_since_setting', true, 'core', 'start_snoozed_settings', 'after');
add_output('settings', 'snoozed_source_max_setting', true, 'core', 'snoozed_since_setting', 'after');
add_output('settings', 'start_trash_settings', true, 'core', 'snoozed_source_max_setting', 'after');
add_output('settings', 'trash_since_setting', true, 'core', 'start_trash_settings', 'after');
add_output('settings', 'trash_source_max_setting', true, 'core', 'trash_since_setting', 'after');
add_output('settings', 'start_drafts_settings', true, 'core', 'trash_source_max_setting', 'after');
add_output('settings', 'drafts_since_setting', true, 'core', 'start_drafts_settings', 'after');
add_output('settings', 'drafts_source_max_setting', true, 'core', 'drafts_since_setting', 'after');
add_output('settings', 'start_everything_settings', true, 'core', 'drafts_source_max_setting', 'after');
add_output('settings', 'all_since_setting', true, 'core', 'start_everything_settings', 'after');
add_output('settings', 'all_source_max_setting', true, 'core', 'all_since_setting', 'after');
add_output('settings', 'start_all_email_settings', true, 'core', 'all_source_max_setting', 'after');
add_output('settings', 'all_email_since_setting', true, 'core', 'start_all_email_settings', 'after');
add_output('settings', 'all_email_source_max_setting', true, 'core', 'all_email_since_setting', 'after');
add_output('settings', 'end_settings_form', true, 'core', 'content_section_end', 'before');
add_output('settings', 'privacy_settings', 'true', 'core', 'start_unread_settings', 'before');

/* message list page */
setup_base_page('message_list');
add_handler('message_list', 'default_sort_order_setting', true, 'core', 'load_user_data', 'after');
add_output('message_list', 'message_list_heading', true, 'core', 'version_upgrade_checker', 'after');
add_output('message_list', 'message_list_start', true, 'core', 'message_list_heading', 'after');
add_output('message_list', 'message_list_end', true, 'core', 'message_list_start', 'after');

/* search page */
setup_base_page('search');
add_handler('search', 'default_sort_order_setting', true, 'core', 'load_user_data', 'after');
add_output('search', 'search_content_start', true, 'core', 'version_upgrade_checker', 'after');
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
add_output('message', 'message_start', true, 'core', 'version_upgrade_checker', 'after');
add_output('message', 'message_end', true, 'core', 'message_start', 'after');

/* not-found page data and output */
setup_base_page('notfound');
add_output('notfound', 'notfound_content', true, 'core', 'version_upgrade_checker', 'after');

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

/* Server setup */
add_handler('ajax_quick_servers_setup', 'login', false, 'core');
add_handler('ajax_quick_servers_setup', 'load_user_data', true, 'core');
add_handler('ajax_quick_servers_setup', 'quick_servers_setup', true);
add_handler('ajax_quick_servers_setup', 'load_smtp_servers_from_config',  true, 'smtp', 'quick_servers_setup', 'before');
add_handler('ajax_quick_servers_setup', 'load_imap_servers_from_config',  true, 'imap', 'quick_servers_setup', 'before');
add_handler('ajax_quick_servers_setup', 'profile_data',  true, 'profiles', 'quick_servers_setup', 'before');
add_handler('ajax_quick_servers_setup', 'compose_profile_data',  true, 'profiles');
add_handler('ajax_quick_servers_setup', 'save_user_data',  true, 'core');
add_handler('ajax_quick_servers_setup', 'language',  true, 'core');
add_handler('ajax_quick_servers_setup', 'date', true, 'core');
add_handler('ajax_quick_servers_setup', 'http_headers', true, 'core');

/* privacy settings control */
setup_base_ajax_page('ajax_privacy_settings', 'core');
add_handler('ajax_privacy_settings', 'privacy_settings',  true, 'core');

setup_base_ajax_page('ajax_combined_message_list', 'core');
add_handler('ajax_combined_message_list', 'load_user_data', true, 'core');
add_output('ajax_combined_message_list', 'combined_message_list', true, 'core');

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
        'search',
        'ajax_quick_servers_setup',
        'ajax_privacy_settings',
        'ajax_combined_message_list'
    ),
    'allowed_output' => array(
        'date' => array(FILTER_UNSAFE_RAW, false),
        'formatted_folder_list' => array(FILTER_UNSAFE_RAW, false),
        'router_user_msgs' => array(FILTER_UNSAFE_RAW, FILTER_REQUIRE_ARRAY),
        'router_login_state' => array(FILTER_VALIDATE_BOOLEAN, false),
        'formatted_message_list' => array(FILTER_UNSAFE_RAW, FILTER_REQUIRE_ARRAY),
        'just_saved_credentials' => array(FILTER_VALIDATE_BOOLEAN, false),
        'just_forgot_credentials' => array(FILTER_VALIDATE_BOOLEAN, false),
        'deleted_server_id' => array(FILTER_UNSAFE_RAW, false),
        'msg_headers' => array(FILTER_UNSAFE_RAW, false),
        'msg_text' => array(FILTER_UNSAFE_RAW, false),
        'msg_source' => array(FILTER_UNSAFE_RAW, false),
        'msg_parts' => array(FILTER_UNSAFE_RAW, false),
        'pages' => array(FILTER_VALIDATE_INT, false),
        'folder_status' => array(FILTER_UNSAFE_RAW, FILTER_REQUIRE_ARRAY),
        'imap_server_id' => array(FILTER_UNSAFE_RAW, false),
        'imap_service_name' => array(FILTER_UNSAFE_RAW, false)
    ),
    'allowed_cookie' => array(
        'CYPHTID' => FILTER_UNSAFE_RAW,
        'hm_id' => FILTER_UNSAFE_RAW,
        'hm_session' => FILTER_UNSAFE_RAW,
        'hm_msgs'    => FILTER_UNSAFE_RAW,
        'hm_reload_folders'    => FILTER_VALIDATE_INT
    ),
    'allowed_server' => array(
        'REQUEST_URI' => FILTER_UNSAFE_RAW,
        'REQUEST_METHOD' => FILTER_UNSAFE_RAW,
        'SERVER_ADDR' => FILTER_VALIDATE_IP,
        'REMOTE_ADDR' => FILTER_VALIDATE_IP,
        'SERVER_PORT' => FILTER_VALIDATE_INT,
        'SERVER_PROTOCOL' => FILTER_UNSAFE_RAW,
        'PHP_SELF' => FILTER_UNSAFE_RAW,
        'REQUEST_SCHEME' => FILTER_UNSAFE_RAW,
        'HTTP_HOST' => FILTER_UNSAFE_RAW,
        'HTTP_ORIGIN' => FILTER_VALIDATE_URL,
        'HTTP_REFERER' => FILTER_VALIDATE_URL,
        'HTTP_ACCEPT_LANGUAGE' => FILTER_UNSAFE_RAW,
        'HTTP_ACCEPT_ENCODING' => FILTER_UNSAFE_RAW,
        'HTTP_ACCEPT_CHARSET' => FILTER_UNSAFE_RAW,
        'HTTP_ACCEPT' => FILTER_UNSAFE_RAW,
        'HTTP_USER_AGENT' => FILTER_UNSAFE_RAW,
        'HTTPS' => FILTER_UNSAFE_RAW,
        'SERVER_NAME' => FILTER_UNSAFE_RAW,
        'HTTP_X_REQUESTED_WITH' => FILTER_UNSAFE_RAW,
        'HTTP_X_FORWARDED_HOST' => FILTER_UNSAFE_RAW
    ),

    'allowed_get' => array(
        'page' => FILTER_SANITIZE_FULL_SPECIAL_CHARS,
        'msgs' => FILTER_SANITIZE_FULL_SPECIAL_CHARS,
        'list_path' => FILTER_SANITIZE_FULL_SPECIAL_CHARS,
        'list_parent' => FILTER_SANITIZE_FULL_SPECIAL_CHARS,
        'list_page' => FILTER_VALIDATE_INT,
        'uid' => FILTER_SANITIZE_FULL_SPECIAL_CHARS,
        'search_terms' => FILTER_UNSAFE_RAW,
        'search_since' => FILTER_SANITIZE_FULL_SPECIAL_CHARS,
        'search_fld' => FILTER_SANITIZE_FULL_SPECIAL_CHARS,
        'filter' => FILTER_SANITIZE_FULL_SPECIAL_CHARS,
        'sort' => FILTER_SANITIZE_FULL_SPECIAL_CHARS,
        'keyword' => FILTER_SANITIZE_FULL_SPECIAL_CHARS,
        'screen_emails' => FILTER_SANITIZE_FULL_SPECIAL_CHARS,
    ),

    'allowed_post' => array(
        'payload' => FILTER_UNSAFE_RAW,
        'reset_factory' => FILTER_UNSAFE_RAW,
        'hm_page_key' => FILTER_UNSAFE_RAW,
        'logout' => FILTER_VALIDATE_BOOLEAN,
        'save_and_logout' => FILTER_VALIDATE_BOOLEAN,
        'limit' => FILTER_VALIDATE_INT,
        'username' => FILTER_UNSAFE_RAW,
        'show_list_icons' => FILTER_VALIDATE_BOOLEAN,
        'password' => FILTER_UNSAFE_RAW,
        'hm_ajax_hook' => FILTER_UNSAFE_RAW,
        'save_settings' => FILTER_UNSAFE_RAW,
        'save_settings_permanently' => FILTER_UNSAFE_RAW,
        'save_settings_permanently_then_logout' => FILTER_UNSAFE_RAW,
        'language' => FILTER_UNSAFE_RAW,
        'flagged_per_source' => FILTER_VALIDATE_INT,
        'flagged_since' => FILTER_UNSAFE_RAW,
        'unread_per_source' => FILTER_VALIDATE_INT,
        'unread_since' => FILTER_UNSAFE_RAW,
        'all_email_per_source' => FILTER_VALIDATE_INT,
        'all_email_since' => FILTER_UNSAFE_RAW,
        'all_per_source' => FILTER_VALIDATE_INT,
        'all_since' => FILTER_UNSAFE_RAW,
        'no_folder_icons' => FILTER_VALIDATE_BOOLEAN,
        'mailto_handler' => FILTER_VALIDATE_BOOLEAN,
        'list_style' => FILTER_UNSAFE_RAW,
        'timezone' => FILTER_UNSAFE_RAW,
        'disable_delete_prompt' => FILTER_VALIDATE_INT,
        'allow_delete_attachment' => FILTER_VALIDATE_INT,
        'section_state' => FILTER_UNSAFE_RAW,
        'section_class' => FILTER_UNSAFE_RAW,
        'message_ids' => FILTER_UNSAFE_RAW,
        'action_type' => FILTER_UNSAFE_RAW,
        'server_pw_id' => FILTER_UNSAFE_RAW,
        'message_list_since' => FILTER_UNSAFE_RAW,
        'no_password_save' => FILTER_VALIDATE_BOOLEAN,
        'start_page' => FILTER_SANITIZE_URL,
        'default_sort_order' => FILTER_UNSAFE_RAW,
        'stay_logged_in' => FILTER_VALIDATE_BOOLEAN,
        'junk_per_source' => FILTER_VALIDATE_INT,
        'junk_since' => FILTER_UNSAFE_RAW,
        'snoozed_per_source' => FILTER_VALIDATE_INT,
        'snoozed_since' => FILTER_UNSAFE_RAW,
        'enable_snooze' => FILTER_VALIDATE_BOOLEAN,
        'trash_per_source' => FILTER_VALIDATE_INT,
        'trash_since' => FILTER_UNSAFE_RAW,
        'drafts_per_source' => FILTER_UNSAFE_RAW,
        'drafts_since' => FILTER_UNSAFE_RAW,
        'warn_for_unsaved_changes' => FILTER_VALIDATE_BOOLEAN,
        'srv_setup_stepper_imap_server_id'  => FILTER_UNSAFE_RAW,
        'srv_setup_stepper_smtp_server_id' => FILTER_UNSAFE_RAW,
        'srv_setup_stepper_profile_name'  => FILTER_UNSAFE_RAW,
        'srv_setup_stepper_email' => FILTER_UNSAFE_RAW,
        'srv_setup_stepper_password' => FILTER_UNSAFE_RAW,
        'srv_setup_stepper_provider' => FILTER_UNSAFE_RAW,
        'srv_setup_stepper_is_sender' => FILTER_VALIDATE_BOOLEAN,
        'srv_setup_stepper_is_receiver' => FILTER_VALIDATE_BOOLEAN,
        'srv_setup_stepper_smtp_address' => FILTER_UNSAFE_RAW,
        'srv_setup_stepper_smtp_port' => FILTER_UNSAFE_RAW,
        'srv_setup_stepper_smtp_tls' => FILTER_VALIDATE_BOOLEAN,
        'srv_setup_stepper_imap_address' => FILTER_UNSAFE_RAW,
        'srv_setup_stepper_imap_port' => FILTER_UNSAFE_RAW,
        'srv_setup_stepper_imap_tls' => FILTER_VALIDATE_BOOLEAN,
        'srv_setup_stepper_enable_sieve' => FILTER_VALIDATE_BOOLEAN,
        'srv_setup_stepper_create_profile' => FILTER_VALIDATE_BOOLEAN,
        'srv_setup_stepper_profile_is_default' => FILTER_VALIDATE_BOOLEAN,
        'srv_setup_stepper_profile_signature' => FILTER_UNSAFE_RAW,
        'srv_setup_stepper_profile_reply_to' => FILTER_UNSAFE_RAW,
        'srv_setup_stepper_imap_sieve_host' => FILTER_UNSAFE_RAW,
        'srv_setup_stepper_imap_sieve_mode_tls' => FILTER_VALIDATE_BOOLEAN,
        'srv_setup_stepper_only_jmap' => FILTER_VALIDATE_BOOLEAN,
        'srv_setup_stepper_jmap_hide_from_c_page' => FILTER_VALIDATE_BOOLEAN,
        'srv_setup_stepper_jmap_address' => FILTER_UNSAFE_RAW,
        'srv_setup_stepper_imap_hide_from_c_page' => FILTER_VALIDATE_BOOLEAN,
        'images_whitelist' => FILTER_UNSAFE_RAW,
        'update' => FILTER_VALIDATE_BOOLEAN,
        'images_blacklist' => FILTER_UNSAFE_RAW,
        'pop' => FILTER_VALIDATE_BOOLEAN,
    )
);
