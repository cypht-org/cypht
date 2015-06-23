<?php

/**
 * Hello World example module set.
 * @package modules
 * @subpackage helloworld
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
 * If you need to include additional code this is a good place to do so. Use the APP_PATH constant
 * which is an absolute path to the top level directory of the installation:
 *
 *     require APP_PATH.'modules/hello_world/your_file_name.php';
 */

/**
 * This is an example of a handler module. This one is assigned to the hello_world page identifier
 * and will run after user data is loaded (this is done in the setup.php file). It sends a string
 * to the output modules for this page called "hello_world_data"
 * @subpackage helloworld/handler
 */
class Hm_Handler_hello_world_page_handler extends Hm_Handler_Module {
    public function process() {
        /**
         * This is one of the ways handler modules send data to output modules. By default
         * this value is immutable and cannot be overridden by other modules
         */
        $this->out('hello_world_data', 'Hello World!');
    }
}

/**
 * This is an output module assigned to the hello_world page identifier (this is done in the setup.php
 * file). This looks for the data sent from the Hm_Handler_hello_world_page_handler module and outputs
 * it after the content_section_start module
 * @subpackage helloworld/output
 */
class Hm_Output_hello_world_page_content extends Hm_Output_Module {
    protected function output() {
        /**
         * $this->format is either HTML5 or AJAX. $this->get() attempts to fetch data sent to the output
         * modules by handler modules $this->get has an optional second argument to set a default
         * return value if the name is not found.
         */
        if ($this->format == 'HTML5' && $this->get('hello_world_data')) {
            /**
             * $this->trans() will try to find a translation in the user's current langauge for
             * the supplied string. It also sanitizes output. If you don't want to translate you
             * and just sanitize, use $this->html_safe().
             */
            return '<div class="hwpage">'.$this->trans($this->get('hello_world_data')).
                '<br /><a class="hw_ajax_link">AJAX Example</a></div>';
        }
    }
}

/**
 * This is an output modules that was assigned to the "home" page id. It outputs after
 * the content_section_start core module, and outputs a div with "hello world" as a link
 * to the hello world page
 * @subpackage helloworld/output
 */
class Hm_Output_hello_world_home_page extends Hm_Output_Module {
    protected function output() {
        /**
         * $this->trans() will try to find a translation in the user's current langauge for
         * the supplied string. It also sanitizes output. If you don't want to translate you
         * and just sanitize, use $this->html_safe().
         */
        $output = '<div class="hw"><a href="?page=hello_world">'.$this->trans('hello world').'</a></div>';
        return $output;
    }
}

/**
 * Another output module, this one is called from an AJAX request in site.js. AJAX requests need
 * to use $this->out('name', 'value') to add to the JSON response. They name used must be whitelisted
 * in the setup.php file under 'allowed_output'.
 * @subpackage helloworld/output
 */
class Hm_Output_hello_world_ajax_content extends Hm_Output_Module {
    protected function output() {
        $this->out('hello_world_ajax_result', $this->trans('Hello World Again!'));
    }
}

