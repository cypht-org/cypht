<?php

/* homepage data */
Hm_Handler_Modules::add('home', 'load_imap_servers',  true, 'language', 'after');
Hm_Handler_Modules::add('home', 'imap_setup', true, 'load_imap_servers', 'after');
Hm_Handler_Modules::add('home', 'imap_setup_display', true, 'imap_setup', 'after');
Hm_Handler_Modules::add('home', 'save_imap_servers',  true, 'save_user_data', 'before');

/* homepage output */
Hm_Output_Modules::add('home', 'imap_setup', true, 'date', 'after');
Hm_Output_Modules::add('home', 'imap_setup_display', true, 'imap_setup', 'after');
Hm_Output_Modules::add('home', 'imap_folders', true, 'imap_setup_display', 'after');
Hm_Output_Modules::add('home', 'imap_debug', true, 'imap_folders', 'after');

/* ajax callback data */
Hm_Handler_Modules::add('ajax_imap_debug', 'login', false);
Hm_Handler_Modules::add('ajax_imap_debug', 'load_imap_servers',  true);
Hm_Handler_Modules::add('ajax_imap_debug', 'imap_connect', true);
Hm_Handler_Modules::add('ajax_imap_debug', 'imap_delete', true);
Hm_Handler_Modules::add('ajax_imap_debug', 'save_imap_cache',  true);
Hm_Handler_Modules::add('ajax_imap_debug', 'save_imap_servers',  true);

/* ajax callback output */
Hm_Output_Modules::add('ajax_imap_debug', 'imap_folders',  true);
Hm_Output_Modules::add('ajax_imap_debug', 'imap_debug',  true);

/* allowed input */
return array(
    'allowed_pages' => array(
        'ajax_imap_debug'
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
