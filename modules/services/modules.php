<?php

/**
 * Services module set.
 * @package modules
 * @subpackage services
 *
 * This module set is intented to give developers an overview of
 * the module system. Most of what it does is silly and consists of printing "Hello
 * World" in different ways. You can enable this module by adding it to the modules
 * value in the config/app.php file.
 */

/**
 * All requests should flow through the main index.php file of the program. The following
 * line insures this file is not loaded directly in the browser. All PHP files should
 * start with it. If someone tries to load the file directly it just quits immediately.
 */
if (!defined('DEBUG_MODE')) { die(); }

/**
 * This is an example of a handler module. This one is assigned to the hello_world page identifier
 * and will run after user data is loaded (this is done in the setup.php file). It sends a string
 * to the output modules for this page called "hello_world_data"
 * @subpackage services/handler
 */
class Hm_Handler_hello_world_page_handler extends Hm_Handler_Module {
    public function process() {
        
    }
}

/**
 * This is an output modules that was assigned to the "home" page id. It outputs after
 * the content_section_start core module, and outputs a div with "hello world" as a link
 * to the hello world page
 * @subpackage services/output
 */
class Hm_Output_hello_world_home_page extends Hm_Output_Module {
    protected function output() {
        /**
         * $this->trans() will try to find a translation in the user's current langauge for
         * the supplied string. It also sanitizes output. If you don't want to translate you
         * and just sanitize, use $this->html_safe().
         */
        $output = '<input type="submit" value="Add to queue" class="queue_imap_server btn btn-outline-primary btn-sm me-2 mt-3">';
        return $output;
    }
}
