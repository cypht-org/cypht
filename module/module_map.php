<?php

/*
 * This is the default mapping for core data and output handlers in HM3.
 * both types of handlers are executed in the order they are defined, so 
 * be careful about mixing them up!. The syntax is for adding modules is as follows:
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
 * The required add module arguments in order are page, module name, and logged
 * in flag:
 *
 *  page          string valid page name or ajax callback with at least one 
 *                handler module assignment
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

/* Homepage data handler modules */
Hm_Handler_Modules::add('home', 'load_imap_servers',  true);
Hm_Handler_Modules::add('home', 'language',  true);
Hm_Handler_Modules::add('home', 'title', true);
Hm_Handler_Modules::add('home', 'date', true);
Hm_Handler_Modules::add('home', 'logout', true);
Hm_Handler_Modules::add('home', 'imap_setup', true);
Hm_Handler_Modules::add('home', 'imap_setup_display', true);
Hm_Handler_Modules::add('home', 'imap_connect', true);
Hm_Handler_Modules::add('home', 'http_headers', true);
Hm_Handler_Modules::add('home', 'save_imap_servers',  true);

/* Homepage output modules */
Hm_Output_Modules::add('home', 'header', false);
Hm_Output_Modules::add('home', 'css', false);
Hm_Output_Modules::add('home', 'jquery', true);
Hm_Output_Modules::add('home', 'logout', true);
Hm_Output_Modules::add('home', 'login', false);
Hm_Output_Modules::add('home', 'title', true);
Hm_Output_Modules::add('home', 'msgs', true);
Hm_Output_Modules::add('home', 'date', true);
Hm_Output_Modules::add('home', 'imap_setup', true);
Hm_Output_Modules::add('home', 'imap_setup_display', true);
Hm_Output_Modules::add('home', 'imap_debug', true);
Hm_Output_Modules::add('home', 'js', true);
Hm_Output_Modules::add('home', 'footer', true);

/* ajax callbacks */
Hm_Handler_Modules::add('ajax_imap_debug', 'load_imap_servers',  true);
Hm_Handler_Modules::add('ajax_imap_debug', 'imap_connect', true);
Hm_Handler_Modules::add('ajax_imap_debug', 'imap_delete', true);
Hm_Handler_Modules::add('ajax_imap_debug', 'save_imap_servers',  true);

/* Not found page modules */
Hm_Handler_Modules::add('notfound', 'title', true);
Hm_Output_Modules::add('notfound', 'title', true);

?>
