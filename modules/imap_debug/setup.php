<?php

/* add link to the home page */
Hm_Output_Modules::add('home', 'servers_link', true, 'logout', 'after');

/* servers page data */
Hm_Handler_Modules::add('servers', 'login', false);
Hm_Handler_Modules::add('servers', 'load_user_data', true);
Hm_Handler_Modules::add('servers', 'language',  true);
Hm_Handler_Modules::add('servers', 'title', true);
Hm_Handler_Modules::add('servers', 'date', true);
Hm_Handler_Modules::add('servers', 'load_imap_servers',  true);
Hm_Handler_Modules::add('servers', 'imap_setup', true);
Hm_Handler_Modules::add('servers', 'imap_setup_display', true);
Hm_Handler_Modules::add('servers', 'save_imap_servers',  true);
Hm_Handler_Modules::add('servers', 'save_user_data', true);
Hm_Handler_Modules::add('servers', 'logout', true);
Hm_Handler_Modules::add('servers', 'http_headers', true);

/* servers page output */
Hm_Output_Modules::add('servers', 'header_start', false);
Hm_Output_Modules::add('servers', 'jquery', false);
Hm_Output_Modules::add('servers', 'header_css', false);
Hm_Output_Modules::add('servers', 'header_js', false);
Hm_Output_Modules::add('servers', 'header_content', false);
Hm_Output_Modules::add('servers', 'header_end', false);
Hm_Output_Modules::add('servers', 'logout', true);
Hm_Output_Modules::add('servers', 'homepage_link', true);
Hm_Output_Modules::add('servers', 'login', false);
Hm_Output_Modules::add('servers', 'title', true);
Hm_Output_Modules::add('servers', 'msgs', false);
Hm_Output_Modules::add('servers', 'date', true);
Hm_Output_Modules::add('servers', 'imap_setup', true);
Hm_Output_Modules::add('servers', 'imap_setup_display', true);
Hm_Output_Modules::add('servers', 'imap_folders', true);
Hm_Output_Modules::add('servers', 'imap_debug', true);
Hm_Output_Modules::add('servers', 'page_js', true);
Hm_Output_Modules::add('servers', 'footer', true);

/* ajax callback data */
Hm_Handler_Modules::add('ajax_imap_debug', 'login', false);
Hm_Handler_Modules::add('ajax_imap_debug', 'load_user_data',  true);
Hm_Handler_Modules::add('ajax_imap_debug', 'load_imap_servers',  true);
Hm_Handler_Modules::add('ajax_imap_debug', 'imap_connect', true);
Hm_Handler_Modules::add('ajax_imap_debug', 'imap_delete', true);
Hm_Handler_Modules::add('ajax_imap_debug', 'save_imap_cache',  true);
Hm_Handler_Modules::add('ajax_imap_debug', 'save_imap_servers',  true);
Hm_Handler_Modules::add('ajax_imap_debug', 'save_user_data',  true);

/* ajax callback output */
Hm_Output_Modules::add('ajax_imap_debug', 'imap_folders',  true);
Hm_Output_Modules::add('ajax_imap_debug', 'imap_debug',  true);

/* allowed input */
return array(
    'allowed_pages' => array(
        'ajax_imap_debug',
        'servers'
    ),

    'allowed_get' => array(
        'imap_server_id' => FILTER_VALIDATE_INT,
    ),

    'allowed_post' => array(
        'tls' => FILTER_VALIDATE_BOOLEAN,
        'server_port' => FILTER_VALIDATE_INT,
        'server' => FILTER_SANITIZE_STRING,
        'username' => FILTER_SANITIZE_STRING,
        'password' => FILTER_SANITIZE_STRING,
        'new_imap_server' => FILTER_SANITIZE_STRING,
        'new_imap_port' => FILTER_VALIDATE_INT,
        'imap_server_id' => FILTER_VALIDATE_INT,
        'imap_user' => FILTER_SANITIZE_STRING,
        'imap_pass' => FILTER_SANITIZE_STRING,
        'imap_delete' => FILTER_SANITIZE_STRING,
        'submit_server' => FILTER_SANITIZE_STRING,
        'imap_connect' => FILTER_SANITIZE_STRING,
        'imap_remember' => FILTER_VALIDATE_INT,
    )
);

?>
