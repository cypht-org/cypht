<?php

/**
 * Hello World example module set.
 *
 * This module set is intented to give developers an overview of
 * the module system. Most of what it does is silly and consists of printing "Hello
 * World" in different ways. You can enable this module by adding it to the modules
 * value in the hm3.ini file, and rebuilding the site config.
 */

/**
 * All requests should flow through the main index.php file of the program. The following
 * line insures this file is not loaded directly in the browser. All PHP files should
 * start with it. If someone tries to load the file directly it just quits immediately.
 */
if (!defined('DEBUG_MODE')) { die(); }

/**
 * These set the default sources for modules assigned in this file. It can be overridden
 * by the module assignment itself. The module system uses the source to know which PHP
 * files to include when servicing a given request. It should match the name of the module
 * set as used in the hm3.ini file, as well as the current directory name.
 */
handler_source('hello_world');
output_source('hello_world');

/**
 * Add an output module to the home page. The arguments to this function are:
 *
 * 'home'                   : The page identifier we want to attach a module to
 * 'hello_world_home_page'  : The module we want to attach
 * true                     : boolean to limit this module to logged in requests
 * 'hello_world'            : the module set of the module we want to attach
 * 'content_section_start'  : the module used as a basis to insert this one
 * 'after'                  : Either 'before' or 'after' the module in the prior argument
 *
 * This assignment will attach a module called "hello_world_home_page" to the output
 * processing of the home page. It will only trigger if the user is logged in, and is from
 * the module set "hello_world". It will be inserted into the home page processing after the
 * content_section_start module. See the setup.php file in the core module set for the default
 * modules included in a standard page. A corresponding output module for this needs to be
 * created in the modules.php file for this set. It should be a class called
 * Hm_Output_hello_world_home_page. See the modules.php file in this example plugin for
 * more detail.
 */
add_output('home', 'hello_world_home_page', true, 'hello_world', 'content_section_start', 'after');

/**
 * Creates a new page in the program for the hello_world page identifier (accesed by the
 * page=hello_world URL argument). All this does is assign the core set of modules to this
 * identifier. It's defined in the setup.php file of the core module set.
 */
setup_base_page('hello_world', 'core');

/**
 * Add our own custom input and output modules to this new page. The first will execute
 * after the load_user_data handler module, and the second will execute after the
 * content_section_start output module.
 */
add_handler('hello_world', 'hello_world_page_handler', true, 'hello_world', 'load_user_data', 'after');
add_output('hello_world', 'hello_world_page_content', true, 'hello_world', 'content_section_start', 'after');

/**
 * These assign the basic core modules needed to process an ajax request.
 */
add_handler('ajax_hello_world', 'login', false, 'core');
add_handler('ajax_hello_world', 'load_user_data', true, 'core');
add_handler('ajax_hello_world', 'language', true, 'core');
add_handler('ajax_hello_world', 'date', true, 'core');
add_handler('ajax_hello_world', 'http_headers', true, 'core');

/**
 * Add our own custom output module to the ajax request. See the site.js file in this
 * module set for further explination of how to trigger and process ajax requests.
 */
add_output('ajax_hello_world', 'hello_world_ajax_content', true);

/**
 * Module setup files must return an array of values they want access to. They also must
 * define any fields they want included in a JSON formatted response. All of these must
 * have associated PHP input filter types associated with them. You should try to use the
 * most restrictive filter type you can. This is also where a module can define it's own
 * pages within the program.
 */
return array(
    /**
     * The first value adds "hello_world" to the list of valid page identifiers. Modules
     * sets can then assign modules to process this request. The page is accessed with
     * the page=hello_world URL argument. The second line creates an ajax request that
     * we can also module assignments to. If any of the above assignments are associated
     * with identifiers not defined by at least one module set, they will be ignored.
     */
    'allowed_pages' => array(
        'hello_world',
        'ajax_hello_world'
    ),

    /**
     * This defines the fields that are allowed in an ajax response. Any output
     * value not defined here will be filtered out of an ajax response.
     */
    'allowed_output' => array(
        'hello_world_ajax_result' => array(FILTER_SANITIZE_STRING, false)
    ),

    /**
     * This defines the allowed URL arguments. If the value is not defined in this list
     * it will not be available to module code.
     */
    'allowed_get' => array(
    ),

    /**
     * This defines allowed post form arguments. If the value is not defined in this list
     * it will not be available to module code.
     */
    'allowed_post' => array(
    )
);

/**
 * For more examples check out the setup.php file for the other module sets. They don't have
 * a lot of comments, but they show how this sytem works in practice for real module sets. 
 * Also see the examples in the site module set for more information about how to configure
 * modules.
 */


