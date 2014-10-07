<?php

if (!defined('DEBUG_MODE')) { die(); }

/**
 * Base class for data input processing modules, called "handler modules"
 *
 * All modules that deal with processing input data extend from this class.
 * It provides access to input and state through the following member variables:
 *
 * $session      The session interface object
 * $request      The HTTP request details object
 * $config       The site config object
 * $user_config  The user settings object for the current user
 *
 * Modules that extend this class need to override the process function. It takes a
 * take a single array argument. Modules can pass information to the output modules
 * by adding to the array and returning it. All handler modules should return
 * the the input array even if it's unmodified, otherwise other modules could be lost.
 *
 */
abstract class Hm_Handler_Module {

    /* session object */
    protected $session = false;

    /* request object */
    protected $request = false;

    /* site configuration object */
    protected $config = false;

    /* current request id */
    protected $page = false;

    /* user settings */
    protected $user_config = false;

    /**
     * Assign input and state sources
     *
     * @param $parent object instance of the Hm_Request_Handler class
     * @param $logged_in bool true if currently logged in
     *
     * @return void
     */
    public function __construct($parent, $logged_in) {
        $this->session = $parent->session;
        $this->request = $parent->request;
        $this->config = $parent->config;
        $this->user_config = $parent->user_config;
    }

    /**
     * Process an HTTP POST form
     *
     * @param $form array list of required field names in the form
     * @param $nonce bool if true require a matching nonce value in a form field called hm_nonce
     *
     * @return array tuple with a bool indicating success, and an array of valid form values
     */
    protected function process_form($form, $nonce=false) {
        $post = $this->request->post;
        $success = false;
        $new_form = array();
        foreach($form as $name) {
            if (array_key_exists($name, $post) && (trim($post[$name]) || (($post[$name] === '0' ||  $post[$name] === 0 )))) {
                $new_form[$name] = $post[$name];
            }
        }
        if (count($form) == count($new_form)) {
            $success = true;
        }
        if ($nonce && $success) {
            $success = $this->process_nonce($post);
        }
        return array($success, $new_form);
    }

    /**
     * Validate an HTTP POST form id
     *
     * @param $post array sanitized post data
     *
     * @return bool true on success
     */
    private function process_nonce($post) {
        if (!array_key_exists('hm_nonce', $post)) {
            return false;
        }
        $key = $this->session->get('username', false);
        if (array_key_exists('hm_id', $this->request->cookie)) {
            $key .= $this->request->cookie['hm_id'];
        }
        elseif ($this->config->get('enc_key', false)) {
            $key .= $this->config->get('enc_key', false);
        }
        if (hash_hmac('sha256', $nonce, $key) == $post['hm_nonce']) {
            return true;
        }
        return false;
    }

    /**
     * Handler modules need to override this method to do work
     *
     * @param $data array list of data to send to the output modules
     *
     * @return should return the $data array, modified or otherwise
     */
    abstract public function process($data);
}

/**
 * Base class for output modules
 *
 * All modules that output data to a request must extend this class and define
 * an output() method. It provides a nonce generator for forms, html sanitizing,
 * and string translation services to modules
 */
abstract class Hm_Output_Module {

    /* translated language strings */
    protected $lstr = array();

    /* langauge name */
    protected $lang = false;

    /* nonce based passed up from the router */
    protected $nonce_base = false;

    /**
     * Assign nonce base
     *
     * @param $input array list from the input modules and page router
     *
     * @return void
     */
    function __construct($input) {
        $this->nonce_base = $input['router_nonce_base'];
    }

    /**
     * Build a nonce value for the form
     *
     * @param $name string form id
     *
     * @return string nonce
     */
    protected function build_nonce($name) {
        return hash_hmac('sha256', $name, $this->nonce_base);
    }

    /**
     * Return a translated string if possible
     *
     * @param $string the string to be translated
     * 
     * @return string translated string
     */
    public function trans($string) {
        if (array_key_exists($string, $this->lstr)) {
            if ($this->lstr[$string] === false) {
                return $string;
            }
            else {
                return $this->lstr[$string];
            }
        }
        else {
            Hm_Debug::add(sprintf('No translation found: %s', $string));
        }
        return $string;
    }

    /**
     * Build output by calling module specific output functions
     *
     * @param $input array list of input from the handler modules
     * @param $formt string output type, either HTML5 or AJAX
     * @param $lang_str array list of language translation strings
     *
     * @return mixed module output, a string for HTML5 format,
     * and an array for AJAX
     */
    public function output_content($input, $format, $lang_str) {
        $this->lstr = $lang_str;
        if (array_key_exists('interface_lang', $lang_str)) {
            $this->lang = $lang_str['interface_lang'];
        }
        return $this->output($input, $format);
    }

    /**
     * Sanitize input string
     *
     * @param $string string text to sanitize
     *
     * @return string sanitized value
     */
    public function html_safe($string) {
        return htmlspecialchars($string, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    /**
     * Output modules need to override this method to add to a page or AJAX response
     *
     * @param $input array data from the input processing modules
     * @param $format string either AJAX or HTML5
     *
     * @return mixed should return an array if $format == AJAX, or
     * an HTML5 formatted string if set to HTML5
     */
    abstract protected function output($input, $format);
}

/**
 * Input processing module "runner"
 *
 * This is a wrapper around input or "handler" module execution.
 * It is called by the Hm_Router object to run all the handler modules
 * for the current page
 */
class Hm_Request_Handler {

    /* request details object */
    public $request = false;

    /* session interface object */
    public $session = false;

    /* site config object */
    public $config = false;

    /* user settings object */
    public $user_config = false;

    /* response details array */
    public $response = array();

    /* handler modules to execute */
    private $modules = array();

    /**
     * Process the modules for a given page id
     *
     * @param $page string page id
     * @param $request object request details
     * @param $session object session interface
     * @param $config object site settings
     *
     * @return array combined array of module results
     */
    public function process_request($page, $request, $session, $config) {
        $this->request = $request;
        $this->session = $session;
        $this->config = $config;
        $this->modules = Hm_Handler_Modules::get_for_page($page);
        $this->load_user_config_object();
        $this->run_modules();
        $this->default_language();
        return $this->response;
    }

    /**
     * Load user settings so they can be passed to a module class
     *
     * @return void
     */
    private function load_user_config_object() {
        $type = $this->config->get('user_config_type', 'file');
        switch ($type) {
            case 'DB':
                $this->user_config = new Hm_User_Config_DB($this->config);
                Hm_Debug::add("Using DB user configuration");
                break;
            case 'file':
            default:
                $this->user_config = new Hm_User_Config_File($this->config);
                Hm_Debug::add("Using file based user configuration");
                break;
        }
    }

    /**
     * Setup a default language translation
     *
     * @return void
     */
    private function default_language() {
        if (!array_key_exists('language', $this->response)) {
            $default_lang = $this->config->get('default_language', false);
            if ($default_lang) {
                $this->response['language'] = $default_lang;
            }
        }
    }

    /**
     * Execute input processing, or "handler" modules, and combine the results
     *
     * @return void
     */
    protected function run_modules() {
        foreach ($this->modules as $name => $args) {
            $input = false;
            $name = "Hm_Handler_$name";
            if (class_exists($name)) {
                if (!$args[1] || ($args[1] && $this->session->is_active())) {
                    $mod = new $name( $this, $args[1]);
                    $input = $mod->process($this->response);
                }
            }
            else {
                Hm_Debug::add(sprintf('Handler module %s activated but not found', $name));
            }
            if ($input) {
                $this->response = $input;
            }
        }
    }
}

/**
 * Trait used as the basic logic for module management
 *
 * Two classes use this trait, Hm_Handler_Modules and Hm_Output_Modules.
 * These are the interfaces module sets use (indirectly) to interact with a request
 * and product output to the browser.
 */
trait Hm_Modules {

    /* holds the module to page assignment list */
    private static $module_list = array();

    /* current module set name, used for error tracking and limiting php file inclusion */
    private static $source = false;

    /* a retry queue for modules that fail to insert immediately */
    private static $module_queue = array();

    /* queue for delayed module insertion for all pages */
    private static $all_page_queue = array();

    /**
     * Queue a module to be added to all defined pages
     *
     * @param $module string the module to add
     * @param $logged_in bool true if the module requires the user to be logged in
     * @param $marker string the module to insert before or after
     * @param $placement string "before" or "after" the $marker module
     * @param $source string the module set containing this module
     *
     * return void
     */
    public static function queue_module_for_all_pages($module, $logged_in, $marker, $placement, $source) {
        self::$all_page_queue[] = array($module, $logged_in, $marker, $placement, $source);
    }

    /**
     * Process queued modules and add them to all pages
     *
     * @return void
     */
    public static function process_all_page_queue() {
        foreach (self::$all_page_queue as $mod) {
            self::add_to_all_pages($mod[0], $mod[1], $mod[2], $mod[3], $mod[4]);
        }
    }

    /**
     * Load a complete formatted module list
     *
     * @param $mod_list array list of module assignments
     *
     * @return void
     */
    public static function load($mod_list) {
        self::$module_list = $mod_list;
    }

    /**
     * Assign the module set name
     *
     * @param $source string the name of the module set (imap, pop3, core, etc)
     *
     * @return void
     */
    public static function set_source($source) {
        self::$source = $source;
    }

    /**
     * Add a module to every defined page
     *
     * @param $module string the module to add
     * @param $logged_in bool true if the module requires the user to be logged in
     * @param $marker string the module to insert before or after
     * @param $placement string "before" or "after" the $marker module
     * @param $source string the module set containing this module
     *
     * @return void
     */
    public static function add_to_all_pages($module, $logged_in, $marker, $placement, $source) {
        foreach (self::$module_list as $page => $modules) {
            if (!preg_match("/^ajax_/", $page)) {
                self::add($page, $module, $logged_in, $marker, $placement, true, $source);
            }
        }
    }

    /**
     * Add a module to a single page
     *
     * @param $page string the page to assign the module to
     * @param $module string the module to add
     * @param $logged_in bool true if the module requires the user to be logged in
     * @param $marker string the module to insert before or after
     * @param $placement string "before" or "after" the $marker module
     * @param $queue bool true to attempt to re-insert the module later on failure
     * @param $source string the module set containing this module
     *
     * @return void
     */
    public static function add($page, $module, $logged_in, $marker=false, $placement='after', $queue=true, $source=false) {
        $inserted = false;
        if (!array_key_exists($page, self::$module_list)) {
            self::$module_list[$page] = array();
        }
        if (array_key_exists($page, self::$module_list) && array_key_exists($module, self::$module_list[$page])) {
            Hm_Debug::add(sprintf("Already registered module re-attempted: %s", $module));
            return;
        }
        if (!$source) {
            $source = self::$source;
        }
        if ($marker) {
            $mods = array_keys(self::$module_list[$page]);
            $index = array_search($marker, $mods);
            if ($index !== false) {
                if ($placement == 'after') {
                    $index++;
                }
                $list = self::$module_list[$page];
                self::$module_list[$page] = array_merge(array_slice($list, 0, $index), 
                    array($module => array($source, $logged_in)),
                    array_slice($list, $index));
                $inserted = true;
            }
        }
        else {
            $inserted = true;
            self::$module_list[$page][$module] = array($source, $logged_in);
        }
        if (!$inserted) {
            if ($queue) {
                Hm_Debug::add(sprintf('queueing module %s', $module));
                self::$module_queue[] = array($page, $module, $logged_in, $marker, $placement);
            }
            else {
                Hm_Debug::add(sprintf('failed to insert module %s on %s', $module, $page));
            }
        }
    }

    /**
     * Replace an already assigned module with a different one
     *
     * @param $target string module name to replace
     * @param $replacement string module name to swap in
     * @param $page string page to replace assignment on, try all pages if false
     *
     * @return void
     */
    public static function replace($target, $replacement, $page=false) {
        if ($page && array_key_exists($page, self::$module_list) && array_key_exists($target, self::$module_list[$page])) {
            self::$module_list[$page] = self::swap_key($target, $replacement, self::$module_list[$page]);
        }
        else {
            foreach (self::$module_list as $page => $modules) {
                if (array_key_exists($target, $modules)) {
                    self::$module_list[$page] = self::swap_key($target, $replacement, self::$module_list[$page]);
                }
            }
        }
    }

    /**
     * Helper function to swap the key of an array and maintain it's value
     *
     * @param $target string array key to replace
     * @param $replacement string array key to swap in
     * @param $modules array list of modules
     *
     * @return array new list with the key swapped out
     */
    private static function swap_key($target, $replacement, $modules) {
        $keys = array_keys($modules);
        $values = array_values($modules);
        $size = count($modules);
        for ($i = 0; $i < $size; $i++) {
            if ($keys[$i] == $target) {
                $keys[$i] = $replacement;
                $values[$i][0] = self::$source;
                break;
            }
        }
        return array_combine($keys, $values);
    }

    /**
     * Attempt to insert modules that initially failed
     *
     * @return void
     */
    public static function try_queued_modules() {
        foreach (self::$module_queue as $vals) {
            self::add($vals[0], $vals[1], $vals[2], $vals[3], $vals[4], false);
        }
    }

    /**
     * Delete a module from the internal list
     *
     * @param $page string page to delete from
     * @param $module string module name to delete
     *
     * @return void
     */
    public static function del($page, $module) {
        if (array_key_exists($page, self::$module_list) && array_key_exists($module, self::$module_list[$page])) {
            unset(self::$module_list[$page][$module]);
        }
    }

    /**
     * Return all the modules assigned to a given page
     *
     * @param $page string the request name
     * 
     * @return array list of assigned modules
     */
    public static function get_for_page($page) {
        $res = array();
        if (array_key_exists($page, self::$module_list)) {
            $res = array_merge($res, self::$module_list[$page]);
        }
        return $res;
    }

    /**
     * Return all modules for all pages
     *
     * @return array list of all modules
     */
    public static function dump() {
        return self::$module_list;
    }
}

/**
 * Class to manage all the input processing modules
 */
class Hm_Handler_Modules { use Hm_Modules; }

/**
 * Class to manage all the output modules
 */
class Hm_Output_Modules { use Hm_Modules; }


/**
 * MODULE SET FUNCTIONS
 *
 * This is the functional interface used by module sets to
 * setup data handlers and output modules in their setup.php files.
 * They are easier to use and more succinct than dealing directly
 * with the class instances
 */ 

/**
 * Add a module set name to the input processing manager
 *
 * @param $source string module set name
 *
 * @return void
 */
function handler_source($source) {
    Hm_Handler_Modules::set_source($source);
}

/**
 * Add a module set name to the output module manager
 *
 * @param $source string module set name
 *
 * @return void
 */
function output_source($source) {
    Hm_Output_Modules::set_source($source);
}

/**
 * Replace an already assigned module with a different one
 *
 * @param $type string either output or handler
 * @param $target string module name to replace
 * @param $replacement string module to swap in
 * @param $page request id, otherwise try all pages names
 *
 * $return void/
 */
function replace_module($type, $target, $replacement, $page=false) {
    if ($type == 'handler') {
        Hm_Handler_Modules::replace($target, $replacement, $page);
    }
    elseif ($type == 'output') {
        Hm_Output_Modules::replace($target, $replacement, $page);
    }
}

/**
 * Add an input handler module to a specific page
 *
 * @param $mod string name of the module to add
 * @param $logged_in bool true if the module should only fire when logged in
 * @param $source string the module set containing the module code
 * @param $marker string the module name used to determine where to insert
 * @param $placement string "before" or "after" the $marker module name
 * @param $queue bool true if the module should be queued and retryed on failure
 *
 * @return void
 */
function add_handler($page, $mod, $logged_in, $source=false, $marker=false, $placement='after', $queue=true) {
    Hm_Handler_Modules::add($page, $mod, $logged_in, $marker, $placement, $queue, $source);
}

/**
 * Add an output module to a specific page
 *
 * @param $mod string name of the module to add
 * @param $logged_in bool true if the module should only fire when logged in
 * @param $source string the module set containing the module code
 * @param $marker string the module name used to determine where to insert
 * @param $placement string "before" or "after" the $marker module name
 * @param $queue bool true if the module should be queued and retryed on failure
 *
 * @return void
 */
function add_output($page, $mod, $logged_in, $source=false, $marker=false, $placement='after', $queue=true) {
    Hm_Output_Modules::add($page, $mod, $logged_in, $marker, $placement, $queue, $source);
}

/**
 * Add an input or output module to all possible pages
 *
 * @param $type string either output or handler
 * @param $mod string name of the module to add
 * @param $logged_in bool true if the module should only fire when logged in
 * @param $source string the module set containing the module code
 * @param $marker string the module name used to determine where to insert
 * @param $placement string "before" or "after" the $marker module name
 *
 * @return void
 */
function add_module_to_all_pages($type, $mod, $logged_in, $source, $marker, $placement) {
    if ($type == 'output') {
        Hm_Output_Modules::queue_module_for_all_pages($mod, $logged_in, $marker, $placement, $source);
    }
    elseif ( $type == 'handler') {
        Hm_Handler_Modules::queue_module_for_all_pages($mod, $logged_in, $marker, $placement, $source);
    }
}

/**
 * Setup commonly used modules for a page
 * TODO: This should be moved to the core module set
 *
 * @param $name string the page id
 * @param $source string the module set name
 *
 * @return void
 */
function setup_base_page($name, $source=false) {
    add_handler($name, 'create_user', false, $source);
    add_handler($name, 'login', false, $source);
    add_handler($name, 'load_user_data', true, $source);
    add_handler($name, 'default_page_data', true, $source);
    add_handler($name, 'message_list_type', true);
    add_handler($name, 'language',  true, $source);
    add_handler($name, 'title', true, $source);
    add_handler($name, 'date', true, $source);
    add_handler($name, 'save_user_data', true, $source);
    add_handler($name, 'logout', true, $source);
    add_handler($name, 'http_headers', true, $source);
    add_output($name, 'header_start', false, $source);
    add_output($name, 'js_data', true, $source);
    add_output($name, 'header_css', false, $source);
    add_output($name, 'header_content', false, $source);
    add_output($name, 'header_end', false, $source);
    add_output($name, 'content_start', false, $source);
    add_output($name, 'login', false, $source);
    add_output($name, 'two_col_layout_start', true, $source);
    add_output($name, 'loading_icon', true, $source);
    add_output($name, 'date', true, $source);
    add_output($name, 'msgs', false, $source);
    add_output($name, 'folder_list_start', true, $source);
    add_output($name, 'folder_list_end', true, $source);
    add_output($name, 'content_section_start', true, $source);
    add_output($name, 'content_section_end', true, $source);
    add_output($name, 'two_col_layout_end', true, $source);
    add_output($name, 'page_js', true, $source);
    add_output($name, 'content_end', true, $source);
}

?>
