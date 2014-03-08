<?php

/*
 * This is the default mapping for core data and output handlers in HM3.
 * both types of handlers are executed in the order they are defined, so 
 * be careful about mixing them up!.
 *
 * There are two handler assignment methods, both take the same arguments. The
 * two methods are:
 *
 * Hm_Handler_Modules::add  add a data handler process to a page or AJAX request. These
 *                          methods fetch data based on user input.
 *
 * Hm_Output_Modules::add   add an output module to a page. These methods output parts
 *                          of the response "page". This could be HTML, JSON, or even
 *                          terminal formatted content.
 *
 * The required arguments in order are "page", "module name", "logged in":
 *
 *  page          string valid page name or ajax callback
 *  module        string module name of the module associated with the request 
 *                page/ajax hook
 *  logged in     bool flag indicating if the user needs to be logged in for the 
 *                module to be active
 *  
 *  There are three optional arguments:
 *
 *  marker        string name of another module to insert this one before or 
 *                after
 *  placement     string either before or after. only matters if marker is set
 *  module_args   array list of arguments to make available to the module
 *
 */



/* Homepage */

/* data modules */
Hm_Handler_Modules::add('home', 'login', false);
Hm_Handler_Modules::add('home', 'load_imap_servers',  true);
Hm_Handler_Modules::add('home', 'language',  true);
Hm_Handler_Modules::add('home', 'title', true);
Hm_Handler_Modules::add('home', 'date', true);
Hm_Handler_Modules::add('home', 'logout', true);
Hm_Handler_Modules::add('home', 'imap_setup', true);
Hm_Handler_Modules::add('home', 'imap_setup_display', true);
Hm_Handler_Modules::add('home', 'http_headers', true);
Hm_Handler_Modules::add('home', 'save_imap_servers',  true);

/* output modules */
Hm_Output_Modules::add('home', 'header', false);
Hm_Output_Modules::add('home', 'css', false);
Hm_Output_Modules::add('home', 'jquery', true);
Hm_Output_Modules::add('home', 'logout', true);
Hm_Output_Modules::add('home', 'login', false);
Hm_Output_Modules::add('home', 'title', true);
Hm_Output_Modules::add('home', 'msgs', false);
Hm_Output_Modules::add('home', 'date', true);
Hm_Output_Modules::add('home', 'imap_setup', true);
Hm_Output_Modules::add('home', 'imap_setup_display', true);
Hm_Output_Modules::add('home', 'imap_folders', true);
Hm_Output_Modules::add('home', 'imap_debug', true);
Hm_Output_Modules::add('home', 'js', true);
Hm_Output_Modules::add('home', 'footer', true);


/* Ajax debug callback */

/* data modules */
Hm_Handler_Modules::add('ajax_imap_debug', 'login', false);
Hm_Handler_Modules::add('ajax_imap_debug', 'load_imap_servers',  true);
Hm_Handler_Modules::add('ajax_imap_debug', 'imap_connect', true);
Hm_Handler_Modules::add('ajax_imap_debug', 'imap_delete', true);
Hm_Handler_Modules::add('ajax_imap_debug', 'save_imap_cache',  true);
Hm_Handler_Modules::add('ajax_imap_debug', 'save_imap_servers',  true);

/* output modules */
Hm_Output_Modules::add('ajax_imap_debug', 'imap_folders',  true);
Hm_Output_Modules::add('ajax_imap_debug', 'imap_debug',  true);

/* Not found page modules */
Hm_Handler_Modules::add('notfound', 'title', true);
Hm_Output_Modules::add('notfound', 'title', true);

/**
 * Core input definitions. Other module sets can add to these but cannot overwrite them.
 */
return array(
    'allowed_pages' => array(
        'home',
        'notfound'
    ),
    'allowed_cookie' => array(
        'PHPSESSID' => FILTER_SANITIZE_STRING
    ),
    'allowed_server' => array(
        'REQUEST_URI' => FILTER_SANITIZE_STRING,
        'SERVER_ADDR' => FILTER_VALIDATE_IP,
        'SERVER_PORT' => FILTER_VALIDATE_INT,
        'PHP_SELF' => FILTER_SANITIZE_STRING,
        'HTTP_USER_AGENT' => FILTER_SANITIZE_STRING,
        'SERVER_NAME' => FILTER_SANITIZE_STRING
    ),

    'allowed_get' => array(
        'page' => FILTER_SANITIZE_STRING,
        'imap_server_id' => FILTER_VALIDATE_INT,
    ),

    'allowed_post' => array(
        'logout' => FILTER_VALIDATE_BOOLEAN,
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
        'hm_ajax_hook' => FILTER_SANITIZE_STRING,
        'imap_remember' => FILTER_VALIDATE_INT,
    )
);
?>
