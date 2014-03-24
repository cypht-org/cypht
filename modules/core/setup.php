<?php

/* homepage data */
Hm_Handler_Modules::add('home', 'create_user', false);
Hm_Handler_Modules::add('home', 'login', false);
Hm_Handler_Modules::add('home', 'load_user_data', true);
Hm_Handler_Modules::add('home', 'language',  true);
Hm_Handler_Modules::add('home', 'title', true);
Hm_Handler_Modules::add('home', 'date', true);
Hm_Handler_Modules::add('home', 'save_user_data', true);
Hm_Handler_Modules::add('home', 'logout', true);
Hm_Handler_Modules::add('home', 'http_headers', true);

/* homepage output */
Hm_Output_Modules::add('home', 'header_start', false);
Hm_Output_Modules::add('home', 'js_data', true);
Hm_Output_Modules::add('home', 'header_css', false);
Hm_Output_Modules::add('home', 'jquery', false);
Hm_Output_Modules::add('home', 'header_content', false);
Hm_Output_Modules::add('home', 'header_end', false);
Hm_Output_Modules::add('home', 'toolbar_start', true);
Hm_Output_Modules::add('home', 'logout', true);
Hm_Output_Modules::add('home', 'settings_link', true);
Hm_Output_Modules::add('home', 'servers_link', true);
Hm_Output_Modules::add('home', 'unread_link', true);
Hm_Output_Modules::add('home', 'homepage_link', true);
Hm_Output_Modules::add('home', 'date', true);
Hm_Output_Modules::add('home', 'login', false);
Hm_Output_Modules::add('home', 'msgs', false);
Hm_Output_Modules::add('home', 'title', true);
Hm_Output_Modules::add('home', 'loading_icon', true);
Hm_Output_Modules::add('home', 'toolbar_end', true);
Hm_Output_Modules::add('home', 'page_js', true);
Hm_Output_Modules::add('home', 'footer', true);

/* servers page data */
Hm_Handler_Modules::add('servers', 'login', false);
Hm_Handler_Modules::add('servers', 'load_user_data', true);
Hm_Handler_Modules::add('servers', 'language',  true);
Hm_Handler_Modules::add('servers', 'title', true);
Hm_Handler_Modules::add('servers', 'date', true);
Hm_Handler_Modules::add('servers', 'save_user_data', true);
Hm_Handler_Modules::add('servers', 'logout', true);
Hm_Handler_Modules::add('servers', 'http_headers', true);

/* servers page output */
Hm_Output_Modules::add('servers', 'header_start', false);
Hm_Output_Modules::add('servers', 'js_data', true);
Hm_Output_Modules::add('servers', 'header_css', false);
Hm_Output_Modules::add('servers', 'jquery', false);
Hm_Output_Modules::add('servers', 'header_content', false);
Hm_Output_Modules::add('servers', 'header_end', false);
Hm_Output_Modules::add('servers', 'toolbar_start', true);
Hm_Output_Modules::add('servers', 'logout', true);
Hm_Output_Modules::add('servers', 'settings_link', true);
Hm_Output_Modules::add('servers', 'servers_link', true);
Hm_Output_Modules::add('servers', 'unread_link', true);
Hm_Output_Modules::add('servers', 'homepage_link', true);
Hm_Output_Modules::add('servers', 'date', true);
Hm_Output_Modules::add('servers', 'login', false);
Hm_Output_Modules::add('servers', 'title', true);
Hm_Output_Modules::add('servers', 'msgs', false);
Hm_Output_Modules::add('servers', 'loading_icon', false);
Hm_Output_Modules::add('servers', 'toolbar_end', true);
Hm_Output_Modules::add('servers', 'page_js', true);
Hm_Output_Modules::add('servers', 'footer', true);


/* settings data */
Hm_Handler_Modules::add('settings', 'create_user', false);
Hm_Handler_Modules::add('settings', 'login', false);
Hm_Handler_Modules::add('settings', 'load_user_data', true);
Hm_Handler_Modules::add('settings', 'language',  true);
Hm_Handler_Modules::add('settings', 'title', true);
Hm_Handler_Modules::add('settings', 'date', true);
Hm_Handler_Modules::add('settings', 'process_language_setting', true);
Hm_Handler_Modules::add('settings', 'process_timezone_setting', true);
Hm_Handler_Modules::add('settings', 'save_user_settings', true);
Hm_Handler_Modules::add('settings', 'save_user_data', true);
Hm_Handler_Modules::add('settings', 'logout', true);
Hm_Handler_Modules::add('settings', 'http_headers', true);

/* settings output */
Hm_Output_Modules::add('settings', 'header_start', false);
Hm_Output_Modules::add('settings', 'js_data', true);
Hm_Output_Modules::add('settings', 'header_css', false);
Hm_Output_Modules::add('settings', 'jquery', false);
Hm_Output_Modules::add('settings', 'header_content', false);
Hm_Output_Modules::add('settings', 'header_end', false);
Hm_Output_Modules::add('settings', 'toolbar_start', true);
Hm_Output_Modules::add('settings', 'logout', true);
Hm_Output_Modules::add('settings', 'settings_link', true);
Hm_Output_Modules::add('settings', 'servers_link', true);
Hm_Output_Modules::add('settings', 'unread_link', true);
Hm_Output_Modules::add('settings', 'homepage_link', true);
Hm_Output_Modules::add('settings', 'date', true);
Hm_Output_Modules::add('settings', 'login', false);
Hm_Output_Modules::add('settings', 'msgs', false);
Hm_Output_Modules::add('settings', 'title', true);
Hm_Output_Modules::add('settings', 'loading_icon', true);
Hm_Output_Modules::add('settings', 'toolbar_end', true);
Hm_Output_Modules::add('settings', 'start_settings_form', true);
Hm_Output_Modules::add('settings', 'language_setting', true);
Hm_Output_Modules::add('settings', 'timezone_setting', true);
Hm_Output_Modules::add('settings', 'end_settings_form', true);
Hm_Output_Modules::add('settings', 'page_js', true);
Hm_Output_Modules::add('settings', 'footer', true);

/* not-found page data and output */
Hm_Handler_Modules::add('notfound', 'title', true);
Hm_Output_Modules::add('notfound', 'title', true);


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
