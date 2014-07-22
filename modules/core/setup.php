<?php

if (!defined('DEBUG_MODE')) { die(); }

handler_source('core');
output_source('core');

/* homepage */
add_handler('home', 'create_user', false);
add_handler('home', 'login', false);
add_handler('home', 'load_user_data', true);
add_handler('home', 'language',  true);
add_handler('home', 'title', true);
add_handler('home', 'date', true);
add_handler('home', 'save_user_data', true);
add_handler('home', 'logout', true);
add_handler('home', 'http_headers', true);
add_output('home', 'header_start', false);
add_output('home', 'js_data', true);
add_output('home', 'header_css', false);
add_output('home', 'jquery', false);
add_output('home', 'header_content', false);
add_output('home', 'header_end', false);
add_output('home', 'content_start', false);
add_output('home', 'toolbar_start', true);
add_output('home', 'logout', true);
add_output('home', 'date', true);
add_output('home', 'login', false);
add_output('home', 'title', true);
add_output('home', 'loading_icon', true);
add_output('home', 'msgs', false);
add_output('home', 'toolbar_end', true);
add_output('home', 'two_col_layout_start', true);
add_output('home', 'folder_list_start', true);
add_output('home', 'folder_list_end', true);
add_output('home', 'content_section_start', true);
add_output('home', 'server_summary_start', true);
add_output('home', 'server_summary_end', true);
add_output('home', 'server_status_start', true);
add_output('home', 'server_status_end', true);
add_output('home', 'content_section_end', true);
add_output('home', 'two_col_layout_end', true);
add_output('home', 'page_js', true);
add_output('home', 'content_end', true);

/* servers page */
add_handler('servers', 'login', false);
add_handler('servers', 'load_user_data', true);
add_handler('servers', 'language',  true);
add_handler('servers', 'title', true);
add_handler('servers', 'date', true);
add_handler('servers', 'save_user_data', true);
add_handler('servers', 'logout', true);
add_handler('servers', 'reload_folder_cookie', true);
add_handler('servers', 'http_headers', true);
add_output('servers', 'header_start', false);
add_output('servers', 'js_data', true);
add_output('servers', 'header_css', false);
add_output('servers', 'jquery', false);
add_output('servers', 'header_content', false);
add_output('servers', 'header_end', false);
add_output('servers', 'content_start', false);
add_output('servers', 'toolbar_start', true);
add_output('servers', 'logout', true);
add_output('servers', 'date', true);
add_output('servers', 'login', false);
add_output('servers', 'title', true);
add_output('servers', 'loading_icon', false);
add_output('servers', 'msgs', false);
add_output('servers', 'toolbar_end', true);
add_output('servers', 'two_col_layout_start', true);
add_output('servers', 'folder_list_start', true);
add_output('servers', 'folder_list_end', true);
add_output('servers', 'content_section_start', true);
add_output('servers', 'content_section_end', true);
add_output('servers', 'two_col_layout_end', true);
add_output('servers', 'page_js', true);
add_output('servers', 'content_end', true);


/* compose */
add_handler('compose', 'create_user', false);
add_handler('compose', 'login', false);
add_handler('compose', 'load_user_data', true);
add_handler('compose', 'language',  true);
add_handler('compose', 'title', true);
add_handler('compose', 'date', true);
add_handler('compose', 'save_user_data', true);
add_handler('compose', 'logout', true);
add_handler('compose', 'http_headers', true);
add_output('compose', 'header_start', false);
add_output('compose', 'js_data', true);
add_output('compose', 'header_css', false);
add_output('compose', 'jquery', false);
add_output('compose', 'header_content', false);
add_output('compose', 'header_end', false);
add_output('compose', 'content_start', false);
add_output('compose', 'toolbar_start', true);
add_output('compose', 'logout', true);
add_output('compose', 'date', true);
add_output('compose', 'login', false);
add_output('compose', 'title', true);
add_output('compose', 'loading_icon', true);
add_output('compose', 'msgs', false);
add_output('compose', 'toolbar_end', true);
add_output('compose', 'two_col_layout_start', true);
add_output('compose', 'folder_list_start', true);
add_output('compose', 'folder_list_end', true);
add_output('compose', 'content_section_start', true);
add_output('compose', 'content_section_end', true);
add_output('compose', 'two_col_layout_end', true);
add_output('compose', 'page_js', true);
add_output('compose', 'content_end', true);

/* settings */
add_handler('settings', 'create_user', false);
add_handler('settings', 'login', false);
add_handler('settings', 'load_user_data', true);
add_handler('settings', 'language',  true);
add_handler('settings', 'title', true);
add_handler('settings', 'date', true);
add_handler('settings', 'process_language_setting', true);
add_handler('settings', 'process_list_style_setting', true);
add_handler('settings', 'process_timezone_setting', true);
add_handler('settings', 'save_user_settings', true);
add_handler('settings', 'save_user_data', true);
add_handler('settings', 'logout', true);
add_handler('settings', 'reload_folder_cookie', true);
add_handler('settings', 'http_headers', true);
add_output('settings', 'header_start', false);
add_output('settings', 'js_data', true);
add_output('settings', 'header_css', false);
add_output('settings', 'jquery', false);
add_output('settings', 'header_content', false);
add_output('settings', 'header_end', false);
add_output('settings', 'content_start', false);
add_output('settings', 'toolbar_start', true);
add_output('settings', 'logout', true);
add_output('settings', 'date', true);
add_output('settings', 'login', false);
add_output('settings', 'title', true);
add_output('settings', 'loading_icon', true);
add_output('settings', 'msgs', false);
add_output('settings', 'toolbar_end', true);
add_output('settings', 'two_col_layout_start', true);
add_output('settings', 'folder_list_start', true);
add_output('settings', 'folder_list_end', true);
add_output('settings', 'content_section_start', true);
add_output('settings', 'start_settings_form', true);
add_output('settings', 'language_setting', true);
add_output('settings', 'timezone_setting', true);
add_output('settings', 'list_style_setting', true);
add_output('settings', 'end_settings_form', true);
add_output('settings', 'content_section_end', true);
add_output('settings', 'two_col_layout_end', true);
add_output('settings', 'page_js', true);
add_output('settings', 'content_end', true);

/* search page */
add_handler('search', 'create_user', false);
add_handler('search', 'login', false);
add_handler('search', 'load_user_data', true);
add_handler('search', 'language',  true);
add_handler('search', 'title', true);
add_handler('search', 'date', true);
add_handler('search', 'save_user_settings', true);
add_handler('search', 'save_user_data', true);
add_handler('search', 'logout', true);
add_handler('search', 'http_headers', true);
add_output('search', 'header_start', false);
add_output('search', 'js_data', true);
add_output('search', 'header_css', false);
add_output('search', 'jquery', false);
add_output('search', 'header_content', false);
add_output('search', 'header_end', false);
add_output('search', 'content_start', false);
add_output('search', 'toolbar_start', true);
add_output('search', 'logout', true);
add_output('search', 'date', true);
add_output('search', 'login', false);
add_output('search', 'title', true);
add_output('search', 'loading_icon', true);
add_output('search', 'msgs', false);
add_output('search', 'toolbar_end', true);
add_output('search', 'two_col_layout_start', true);
add_output('search', 'folder_list_start', true);
add_output('search', 'folder_list_end', true);
add_output('search', 'content_section_start', true);
add_output('search', 'search_content', true);
add_output('search', 'content_section_end', true);
add_output('search', 'two_col_layout_end', true);
add_output('search', 'page_js', true);
add_output('search', 'content_end', true);

/* profile page */
add_handler('profiles', 'create_user', false);
add_handler('profiles', 'login', false);
add_handler('profiles', 'load_user_data', true);
add_handler('profiles', 'language',  true);
add_handler('profiles', 'title', true);
add_handler('profiles', 'date', true);
add_handler('profiles', 'save_user_settings', true);
add_handler('profiles', 'save_user_data', true);
add_handler('profiles', 'logout', true);
add_handler('profiles', 'http_headers', true);
add_output('profiles', 'header_start', false);
add_output('profiles', 'js_data', true);
add_output('profiles', 'header_css', false);
add_output('profiles', 'jquery', false);
add_output('profiles', 'header_content', false);
add_output('profiles', 'header_end', false);
add_output('profiles', 'content_start', false);
add_output('profiles', 'toolbar_start', true);
add_output('profiles', 'logout', true);
add_output('profiles', 'date', true);
add_output('profiles', 'login', false);
add_output('profiles', 'title', true);
add_output('profiles', 'loading_icon', true);
add_output('profiles', 'msgs', false);
add_output('profiles', 'toolbar_end', true);
add_output('profiles', 'two_col_layout_start', true);
add_output('profiles', 'folder_list_start', true);
add_output('profiles', 'folder_list_end', true);
add_output('profiles', 'content_section_start', true);
add_output('profiles', 'profile_content', true);
add_output('profiles', 'content_section_end', true);
add_output('profiles', 'two_col_layout_end', true);
add_output('profiles', 'page_js', true);
add_output('profiles', 'content_end', true);

/* message list page */
add_handler('message_list', 'create_user', false);
add_handler('message_list', 'login', false);
add_handler('message_list', 'load_user_data', true);
add_handler('message_list', 'language',  true);
add_handler('message_list', 'title', true);
add_handler('message_list', 'message_list_type', true);
add_handler('message_list', 'date', true);
add_handler('message_list', 'save_user_data', true);
add_handler('message_list', 'logout', true);
add_handler('message_list', 'http_headers', true);
add_output('message_list', 'header_start', false);
add_output('message_list', 'js_data', true);
add_output('message_list', 'header_css', false);
add_output('message_list', 'jquery', false);
add_output('message_list', 'header_content', false);
add_output('message_list', 'header_end', false);
add_output('message_list', 'content_start', false);
add_output('message_list', 'toolbar_start', true);
add_output('message_list', 'logout', true);
add_output('message_list', 'date', true);
add_output('message_list', 'login', false);
add_output('message_list', 'title', true);
add_output('message_list', 'loading_icon', true);
add_output('message_list', 'msgs', false);
add_output('message_list', 'toolbar_end', true);
add_output('message_list', 'two_col_layout_start', true);
add_output('message_list', 'folder_list_start', true);
add_output('message_list', 'folder_list_end', true);
add_output('message_list', 'content_section_start', true);
add_output('message_list', 'message_list_heading', true);
add_output('message_list', 'message_list_start', true);
add_output('message_list', 'message_list_end', true);
add_output('message_list', 'content_section_end', true);
add_output('message_list', 'two_col_layout_end', true);
add_output('message_list', 'page_js', true);
add_output('message_list', 'content_end', true);

/* message view page */
add_handler('message', 'create_user', false);
add_handler('message', 'login', false);
add_handler('message', 'load_user_data', true);
add_handler('message', 'language',  true);
add_handler('message', 'title', true);
add_handler('message', 'message_list_type', true);
add_handler('message', 'date', true);
add_handler('message', 'save_user_data', true);
add_handler('message', 'logout', true);
add_handler('message', 'http_headers', true);
add_output('message', 'header_start', false);
add_output('message', 'js_data', true);
add_output('message', 'header_css', false);
add_output('message', 'jquery', false);
add_output('message', 'header_content', false);
add_output('message', 'header_end', false);
add_output('message', 'content_start', false);
add_output('message', 'toolbar_start', true);
add_output('message', 'logout', true);
add_output('message', 'date', true);
add_output('message', 'login', false);
add_output('message', 'title', true);
add_output('message', 'loading_icon', true);
add_output('message', 'msgs', false);
add_output('message', 'toolbar_end', true);
add_output('message', 'two_col_layout_start', true);
add_output('message', 'folder_list_start', true);
add_output('message', 'folder_list_end', true);
add_output('message', 'content_section_start', true);
add_output('message', 'message_start', true);
add_output('message', 'message_end', true);
add_output('message', 'content_section_end', true);
add_output('message', 'two_col_layout_end', true);
add_output('message', 'page_js', true);
add_output('message', 'content_end', true);

/* not-found page data and output */
add_handler('notfound', 'login', false);
add_handler('notfound', 'load_user_data', true);
add_handler('notfound', 'language',  true);
add_handler('notfound', 'title', true);
add_handler('notfound', 'date', true);
add_handler('notfound', 'save_user_data', true);
add_handler('notfound', 'logout', true);
add_handler('notfound', 'http_headers', true);
add_output('notfound', 'header_start', false);
add_output('notfound', 'js_data', true);
add_output('notfound', 'header_css', false);
add_output('notfound', 'jquery', false);
add_output('notfound', 'header_content', false);
add_output('notfound', 'header_end', false);
add_output('notfound', 'content_start', false);
add_output('notfound', 'toolbar_start', true);
add_output('notfound', 'logout', true);
add_output('notfound', 'date', true);
add_output('notfound', 'login', false);
add_output('notfound', 'title', true);
add_output('notfound', 'loading_icon', true);
add_output('notfound', 'msgs', false);
add_output('notfound', 'toolbar_end', true);
add_output('notfound', 'two_col_layout_start', true);
add_output('notfound', 'folder_list_start', true);
add_output('notfound', 'folder_list_end', true);
add_output('notfound', 'content_section_start', true);
add_output('notfound', 'notfound_content', true);
add_output('notfound', 'content_section_end', true);
add_output('notfound', 'two_col_layout_end', true);
add_output('notfound', 'page_js', true);
add_output('notfound', 'content_end', true);

add_handler('ajax_message_action', 'login', false);
add_handler('ajax_message_action', 'load_user_data', true);
add_handler('ajax_message_action', 'date', true);

add_handler('ajax_hm_folders', 'login', false);
add_handler('ajax_hm_folders', 'load_user_data', true);
add_handler('ajax_hm_folders', 'date', true);
add_output('ajax_hm_folders', 'folder_list_content', true);

/* allowed input */
return array(
    'allowed_pages' => array(
        'home',
        'compose',
        'message_list',
        'message',
        'settings',
        'servers',
        'ajax_hm_folders',
        'ajax_message_action',
        'notfound',
        'profiles',
        'search',
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
        'SERVER_NAME' => FILTER_SANITIZE_STRING
    ),

    'allowed_get' => array(
        'page' => FILTER_SANITIZE_STRING,
        'msgs' => FILTER_SANITIZE_STRING,
        'list_path' => FILTER_SANITIZE_STRING,
        'list_parent' => FILTER_SANITIZE_STRING,
        'list_page' => FILTER_VALIDATE_INT,
        'uid' => FILTER_SANITIZE_STRING,
    ),

    'allowed_post' => array(
        'hm_nonce' => FILTER_SANITIZE_STRING,
        'logout' => FILTER_VALIDATE_BOOLEAN,
        'save_and_logout' => FILTER_VALIDATE_BOOLEAN,
        'limit' => FILTER_VALIDATE_INT,
        'username' => FILTER_SANITIZE_STRING,
        'create_hm_user' => FILTER_SANITIZE_STRING,
        'password' => FILTER_SANITIZE_STRING,
        'hm_ajax_hook' => FILTER_SANITIZE_STRING,
        'save_settings' => FILTER_SANITIZE_STRING,
        'language_setting' => FILTER_SANITIZE_STRING,
        'list_style' => FILTER_SANITIZE_STRING,
        'timezone_setting' => FILTER_SANITIZE_STRING,
        'section_state' => FILTER_SANITIZE_STRING,
        'section_class' => FILTER_SANITIZE_STRING,
        'message_ids' => FILTER_SANITIZE_STRING,
        'action_type' => FILTER_SANITIZE_STRING,
    )
);

?>
