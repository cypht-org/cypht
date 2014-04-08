<?php
handler_source('core');
output_source('core');

/* homepage data */
add_handler('home', 'create_user', false);
add_handler('home', 'login', false);
add_handler('home', 'load_user_data', true);
add_handler('home', 'language',  true);
add_handler('home', 'title', true);
add_handler('home', 'date', true);
add_handler('home', 'save_user_data', true);
add_handler('home', 'logout', true);
add_handler('home', 'http_headers', true);

/* homepage output */
add_output('home', 'header_start', false);
add_output('home', 'js_data', true);
add_output('home', 'header_css', false);
add_output('home', 'jquery', false);
add_output('home', 'header_content', false);
add_output('home', 'header_end', false);
add_output('home', 'content_start', false);
add_output('home', 'toolbar_start', true);
add_output('home', 'logout', true);
add_output('home', 'settings_link', true);
add_output('home', 'servers_link', true);
add_output('home', 'unread_link', true);
add_output('home', 'homepage_link', true);
add_output('home', 'date', true);
add_output('home', 'login', false);
add_output('home', 'msgs', false);
add_output('home', 'title', true);
add_output('home', 'loading_icon', true);
add_output('home', 'toolbar_end', true);
add_output('home', 'page_js', true);
add_output('home', 'content_end', true);

/* servers page data */
add_handler('servers', 'login', false);
add_handler('servers', 'load_user_data', true);
add_handler('servers', 'language',  true);
add_handler('servers', 'title', true);
add_handler('servers', 'date', true);
add_handler('servers', 'save_user_data', true);
add_handler('servers', 'logout', true);
add_handler('servers', 'http_headers', true);

/* servers page output */
add_output('servers', 'header_start', false);
add_output('servers', 'js_data', true);
add_output('servers', 'header_css', false);
add_output('servers', 'jquery', false);
add_output('servers', 'header_content', false);
add_output('servers', 'header_end', false);
add_output('servers', 'content_start', false);
add_output('servers', 'toolbar_start', true);
add_output('servers', 'logout', true);
add_output('servers', 'settings_link', true);
add_output('servers', 'servers_link', true);
add_output('servers', 'unread_link', true);
add_output('servers', 'homepage_link', true);
add_output('servers', 'date', true);
add_output('servers', 'login', false);
add_output('servers', 'title', true);
add_output('servers', 'msgs', false);
add_output('servers', 'loading_icon', false);
add_output('servers', 'toolbar_end', true);
add_output('servers', 'page_js', true);
add_output('servers', 'content_end', true);


/* settings data */
add_handler('settings', 'create_user', false);
add_handler('settings', 'login', false);
add_handler('settings', 'load_user_data', true);
add_handler('settings', 'language',  true);
add_handler('settings', 'title', true);
add_handler('settings', 'date', true);
add_handler('settings', 'process_language_setting', true);
add_handler('settings', 'process_timezone_setting', true);
add_handler('settings', 'save_user_settings', true);
add_handler('settings', 'save_user_data', true);
add_handler('settings', 'logout', true);
add_handler('settings', 'http_headers', true);

/* settings output */
add_output('settings', 'header_start', false);
add_output('settings', 'js_data', true);
add_output('settings', 'header_css', false);
add_output('settings', 'jquery', false);
add_output('settings', 'header_content', false);
add_output('settings', 'header_end', false);
add_output('settings', 'content_start', false);
add_output('settings', 'toolbar_start', true);
add_output('settings', 'logout', true);
add_output('settings', 'settings_link', true);
add_output('settings', 'servers_link', true);
add_output('settings', 'unread_link', true);
add_output('settings', 'homepage_link', true);
add_output('settings', 'date', true);
add_output('settings', 'login', false);
add_output('settings', 'msgs', false);
add_output('settings', 'title', true);
add_output('settings', 'loading_icon', true);
add_output('settings', 'toolbar_end', true);
add_output('settings', 'start_settings_form', true);
add_output('settings', 'language_setting', true);
add_output('settings', 'timezone_setting', true);
add_output('settings', 'end_settings_form', true);
add_output('settings', 'page_js', true);
add_output('settings', 'content_end', true);

/* not-found page data and output */
add_handler('notfound', 'title', true);
add_output('notfound', 'title', true);


/* allowed input */
return array(
    'allowed_pages' => array(
        'home',
        'notfound',
        'settings',
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
        'SERVER_PORT' => FILTER_VALIDATE_INT,
        'PHP_SELF' => FILTER_SANITIZE_STRING,
        'REQUEST_SCHEME' => FILTER_SANITIZE_STRING,
        'HTTP_USER_AGENT' => FILTER_SANITIZE_STRING,
        'HTTPS' => FILTER_SANITIZE_STRING,
        'SERVER_NAME' => FILTER_SANITIZE_STRING
    ),

    'allowed_get' => array(
        'page' => FILTER_SANITIZE_STRING,
        'msgs' => FILTER_SANITIZE_STRING
    ),

    'allowed_post' => array(
        'logout' => FILTER_VALIDATE_BOOLEAN,
        'username' => FILTER_SANITIZE_STRING,
        'create_hm_user' => FILTER_SANITIZE_STRING,
        'password' => FILTER_SANITIZE_STRING,
        'hm_ajax_hook' => FILTER_SANITIZE_STRING,
        'save_settings' => FILTER_SANITIZE_STRING,
        'language_setting' => FILTER_SANITIZE_STRING,
        'timezone_setting' => FILTER_SANITIZE_STRING
    )
);

?>
