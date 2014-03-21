<?php

/* add stuff to the home page */
Hm_Handler_Modules::add('home', 'load_imap_servers',  true, 'load_user_data', 'after');
Hm_Handler_Modules::add('home', 'imap_setup_display',  true, 'load_imap_servers', 'after');
Hm_Output_Modules::add('home', 'jquery_table', true, 'jquery', 'after');
Hm_Output_Modules::add('home', 'imap_summary', true, 'title', 'after');

/* servers page data */
Hm_Handler_Modules::add('servers', 'load_imap_servers',  true, 'date', 'after');
Hm_Handler_Modules::add('servers', 'imap_setup', true, 'load_imap_servers', 'after');
Hm_Handler_Modules::add('servers', 'imap_setup_display', true, 'imap_setup', 'after');
Hm_Handler_Modules::add('servers', 'save_imap_servers',  true, 'imap_setup_display', 'after');

/* servers page output */
Hm_Output_Modules::add('servers', 'imap_setup', true, 'loading_icon', 'after');
Hm_Output_Modules::add('servers', 'imap_setup_display', true, 'imap_setup', 'after');

/* unread page data */
Hm_Handler_Modules::add('unread', 'login', false);
Hm_Handler_Modules::add('unread', 'load_user_data', true);
Hm_Handler_Modules::add('unread', 'language',  true);
Hm_Handler_Modules::add('unread', 'title', true);
Hm_Handler_Modules::add('unread', 'date', true);
Hm_Handler_Modules::add('unread', 'load_imap_servers',  true);
Hm_Handler_Modules::add('unread', 'imap_setup', true);
Hm_Handler_Modules::add('unread', 'imap_setup_display', true);
Hm_Handler_Modules::add('unread', 'imap_bust_cache', true);
Hm_Handler_Modules::add('unread', 'save_user_data', true);
Hm_Handler_Modules::add('unread', 'logout', true);
Hm_Handler_Modules::add('unread', 'http_headers', true);

Hm_Output_Modules::add('unread', 'header_start', false);
Hm_Output_Modules::add('unread', 'js_data', true);
Hm_Output_Modules::add('unread', 'header_css', false);
Hm_Output_Modules::add('unread', 'jquery', false);
Hm_Output_Modules::add('unread', 'jquery_table', false);
Hm_Output_Modules::add('unread', 'header_content', false);
Hm_Output_Modules::add('unread', 'header_end', false);
Hm_Output_Modules::add('unread', 'logout', true);
Hm_Output_Modules::add('unread', 'settings_link', true);
Hm_Output_Modules::add('unread', 'servers_link', true);
Hm_Output_Modules::add('unread', 'unread_link', true);
Hm_Output_Modules::add('unread', 'homepage_link', true);
Hm_Output_Modules::add('unread', 'login', false);
Hm_Output_Modules::add('unread', 'date', true);
Hm_Output_Modules::add('unread', 'title', true);
Hm_Output_Modules::add('unread', 'msgs', false);
Hm_Output_Modules::add('unread', 'loading_icon', false);
Hm_Output_Modules::add('unread', 'unread_message_list', true);
Hm_Output_Modules::add('unread', 'page_js', true);
Hm_Output_Modules::add('unread', 'footer', true);

/* ajax server setup callback data */
Hm_Handler_Modules::add('ajax_imap_debug', 'login', false);
Hm_Handler_Modules::add('ajax_imap_debug', 'load_user_data',  true);
Hm_Handler_Modules::add('ajax_imap_debug', 'load_imap_servers',  true);
Hm_Handler_Modules::add('ajax_imap_debug', 'imap_connect', true);
Hm_Handler_Modules::add('ajax_imap_debug', 'imap_delete', true);
Hm_Handler_Modules::add('ajax_imap_debug', 'imap_forget', true);
Hm_Handler_Modules::add('ajax_imap_debug', 'imap_save', true);
Hm_Handler_Modules::add('ajax_imap_debug', 'save_imap_cache',  true);
Hm_Handler_Modules::add('ajax_imap_debug', 'save_imap_servers',  true);
Hm_Handler_Modules::add('ajax_imap_debug', 'save_user_data',  true);
Hm_Handler_Modules::add('ajax_imap_debug', 'date', true);

/* ajax server summary callback data */
Hm_Handler_Modules::add('ajax_imap_summary', 'login', false);
Hm_Handler_Modules::add('ajax_imap_summary', 'load_user_data',  true);
Hm_Handler_Modules::add('ajax_imap_summary', 'load_imap_servers',  true);
Hm_Handler_Modules::add('ajax_imap_summary', 'imap_summary',  true);
Hm_Handler_Modules::add('ajax_imap_summary', 'save_imap_cache',  true);
Hm_Handler_Modules::add('ajax_imap_summary', 'save_imap_servers',  true);
Hm_Handler_Modules::add('ajax_imap_summary', 'date', true);

/* ajax unread callback data */
Hm_Handler_Modules::add('ajax_imap_unread', 'login', false);
Hm_Handler_Modules::add('ajax_imap_unread', 'load_user_data',  true);
Hm_Handler_Modules::add('ajax_imap_unread', 'load_imap_servers',  true);
Hm_Handler_Modules::add('ajax_imap_unread', 'imap_unread',  true);
Hm_Handler_Modules::add('ajax_imap_unread', 'save_imap_cache',  true);
Hm_Handler_Modules::add('ajax_imap_unread', 'save_imap_servers',  true);
Hm_Handler_Modules::add('ajax_imap_unread', 'date', true);
Hm_Output_Modules::add('ajax_imap_unread', 'filter_unread_data', true);

/* allowed input */
return array(
    'allowed_pages' => array(
        'ajax_imap_debug',
        'ajax_imap_summary',
        'ajax_imap_unread',
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
        'imap_forget' => FILTER_SANITIZE_STRING,
        'imap_save' => FILTER_SANITIZE_STRING,
        'submit_server' => FILTER_SANITIZE_STRING,
        'new_imap_address' => FILTER_SANITIZE_STRING,
        'new_imap_port' => FILTER_VALIDATE_INT,
        'new_imap_name' => FILTER_SANITIZE_STRING,
        'tls' => FILTER_VALIDATE_BOOLEAN,
    )
);

?>
