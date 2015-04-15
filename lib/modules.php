<?php

/**
 * Module management classes
 * @package framework
 * @subpackage modules
 */
if (!defined('DEBUG_MODE')) { die(); }

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
     * @param string $module the module to add
     * @param bool $logged_in true if the module requires the user to be logged in
     * @param string $marker the module to insert before or after
     * @param string $placement "before" or "after" the $marker module
     * @param string $source the module set containing this module
     * return void
     */
    public static function queue_module_for_all_pages($module, $logged_in, $marker, $placement, $source) {
        self::$all_page_queue[] = array($module, $logged_in, $marker, $placement, $source);
    }

    /**
     * Process queued modules and add them to all pages
     * @return void
     */
    public static function process_all_page_queue() {
        foreach (self::$all_page_queue as $mod) {
            self::add_to_all_pages($mod[0], $mod[1], $mod[2], $mod[3], $mod[4]);
        }
    }

    /**
     * Load a complete formatted module list
     * @param array $mod_list list of module assignments
     * @return void
     */
    public static function load($mod_list) {
        self::$module_list = $mod_list;
    }

    /**
     * Assign the module set name
     * @param string $source the name of the module set (imap, pop3, core, etc)
     * @return void
     */
    public static function set_source($source) {
        self::$source = $source;
    }

    /**
     * Add a module to every defined page
     * @param string $module the module to add
     * @param bool $logged_in true if the module requires the user to be logged in
     * @param string $marker the module to insert before or after
     * @param string $placement "before" or "after" the $marker module
     * @param string $source the module set containing this module
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
     * @param string $page the page to assign the module to
     * @param string $module the module to add
     * @param bool $logged_in true if the module requires the user to be logged in
     * @param string $marker the module to insert before or after
     * @param string $placement "before" or "after" the $marker module
     * @param bool $queue true to attempt to re-insert the module later on failure
     * @param string $source the module set containing this module
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
                self::$module_queue[] = array($page, $module, $logged_in, $marker, $placement, $source);
            }
            else {
                Hm_Debug::add(sprintf('failed to insert module %s on %s', $module, $page));
            }
        }
    }

    /**
     * Replace an already assigned module with a different one
     * @param string $target module name to replace
     * @param string $replacement module name to swap in
     * @param string $page page to replace assignment on, try all pages if false
     * @return void
     */
    public static function replace($target, $replacement, $page=false) {
        if ($page) {
            if (array_key_exists($page, self::$module_list) && array_key_exists($target, self::$module_list[$page])) {
                self::$module_list[$page] = self::swap_key($target, $replacement, self::$module_list[$page]);
            }
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
     * @param string $target array key to replace
     * @param string $replacement array key to swap in
     * @param array $modules list of modules
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
     * @return void
     */
    public static function try_queued_modules() {
        foreach (self::$module_queue as $vals) {
            self::add($vals[0], $vals[1], $vals[2], $vals[3], $vals[4], false, $vals[5]);
        }
    }

    /**
     * Delete a module from the internal list
     * @param string $page page to delete from
     * @param string $module module name to delete
     * @return void
     */
    public static function del($page, $module) {
        if (array_key_exists($page, self::$module_list) && array_key_exists($module, self::$module_list[$page])) {
            unset(self::$module_list[$page][$module]);
        }
    }

    /**
     * Return all the modules assigned to a given page
     * @param string $page the request name
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
 * Output module execution methods
 */
trait Hm_Output_Module_Exec {

    /**
     * Run all the handler modules for a page and merge the results
     * @param object $request details about the request
     * @param object $session session interface
     * @return void
     */
    public function run_output_modules($request, $session, $page) {
        $input = $this->handler_response;
        $protected = array();
        $modules = Hm_Output_Modules::get_for_page($page);
        $list_output = array();
        $lang_str = $this->get_current_language();
        foreach ($modules as $name => $args) {
            list($output, $protected, $type) = $this->run_output_module($input, $protected, $name, $args, $session, $request->format, $lang_str);
            if ($type != 'JSON') {
                $list_output[] = $output;
            }
            else {
                $input = $output;
            }
        }
        if (!empty($list_output)) {
            $this->output_response = $list_output;
        }
        else {
            $this->output_response = $input;
        }
    }

    /**
     * Run a single output modules and return the results
     * @param array $input handler output 
     * @param array $protected list of protected output
     * @param string $name handler module name
     * @param array $args module arguments
     * @param object $session session interface
     * @param string $format HTML5 or JSON format
     * @param array $lang_str translation lookup array
     */
    public function run_output_module($input, $protected, $name, $args, $session, $format, $lang_str) {
        $name = "Hm_Output_$name";
        $mod_output = false;
        if (class_exists($name)) {
            if (!$args[1] || ($args[1] && $session->is_active())) {
                $mod = new $name($input, $protected);
                if ($format == 'Hm_Format_JSON') {
                    $mod->output_content($format, $lang_str, $protected);
                    $input = $mod->module_output();
                    $protected = $mod->output_protected();
                }
                else {
                    $mod_output = $mod->output_content($format, $lang_str, array());
                    $input = $mod->module_output();
                }
            }
        }
        else {
            Hm_Debug::add(sprintf('Output module %s activated but not found', $name));
        }
        if (!$mod_output) {
            return array($input, $protected, 'JSON');
        }
        return array($mod_output, $protected, 'HTML5');
    }

}
/**
 * Handler module execution methods
 */
trait Hm_Handler_Module_Exec {

    /**
     * Run all the handler modules for a page and merge the results
     * @param object $request details about the request
     * @param object $session session interface
     * @param string $page page id
     * @return void
     */
    public function run_handler_modules($request, $session, $page) {
        $input = array();
        $protected = array();
        $this->request = $request;
        $this->session = $session;
        $modules = Hm_Handler_Modules::get_for_page($page);
        foreach ($modules as $name => $args) {
            list($input, $protected) = $this->run_handler_module($input, $protected, $name, $args, $session);
        }
        if ($input) {
            $this->handler_response = $input;
        }
        $this->default_language();
        $this->merge_response($request, $session, $page);
    }

    /**
     * Run a single handler and return the results
     * @param array $input handler output so far for this page
     * @param array $protected list of protected output
     * @param string $name handler module name
     * @param array $args module arguments
     * @param object $session session interface
     */
    public function run_handler_module($input, $protected, $name, $args, $session) {
        $name = "Hm_Handler_$name";
        if (class_exists($name)) {
            if (!$args[1] || ($args[1] && $session->is_active())) {
                $mod = new $name($this, $args[1], $this->page, $input, $protected);
                $mod->process($input);
                $input = $mod->module_output();
                $protected = $mod->output_protected();
            }
        }
        else {
            Hm_Debug::add(sprintf('Handler module %s activated but not found', $name));
        }
        return array($input, $protected);
    }

    /**
     * Merge the combined response from the handler modules with some default values
     * @param object $request request details
     * @param object $session session interface
     * @param string $page page id
     * @return void
     */
    public function merge_response($request, $session, $page) {
        $this->handler_response = array_merge($this->handler_response, array(
            'router_page_name'    => $page,
            'router_request_type' => $request->type,
            'router_sapi_name'    => $request->sapi,
            'router_format_name'  => $request->format,
            'router_login_state'  => $session->is_active(),
            'router_url_path'     => $request->path,
            'router_module_list'  => $this->site_config->get('modules', ''),
            'router_app_name'     => $this->site_config->get('app_name', 'HM3')
        ));
    }
}

/**
 * Class to setup, load, and execute module sets
 */
class Hm_Module_Exec {

    use Hm_Output_Module_Exec;
    use Hm_Handler_Module_Exec;

    public $page = false;
    public $site_config = false;
    public $user_config = false;
    public $handler_response = array();
    public $output_response = false;
    public $filters = array();
    public $session = false;
    public $request = false;
    public $handlers = array();
    public $outputs = array();

    public function __construct($config) {
        $this->site_config = $config;
        $this->user_config = load_user_config_object($config);
        $this->process_module_setup();
    }

    /**
     * Build a list of module properties
     * @return void
     */
    public function process_module_setup() {
        if (DEBUG_MODE) {
            $this->setup_debug_modules();
        }
        else {
            $this->setup_production_modules();
        }
    }

    /**
     * Look for translation strings based on the current language setting
     * return array
     */
    public function get_current_language() {
        if (array_key_exists('language', $this->handler_response)) {
            $lang = $this->handler_response['language'];
        }
        else {
            $lang = 'en';
        }
        $strings = array();
        if (file_exists(APP_PATH.'language/'.$lang.'.php')) {
            $strings = require APP_PATH.'language/'.$lang.'.php';
        }
        return $strings;
    }

    /**
     * Setup a default language translation
     * @return void
     */
    public function default_language() {
        if (!array_key_exists('language', $this->handler_response)) {
            $default_lang = $this->site_config->get('default_language', false);
            if ($default_lang) {
                $this->handler_response['language'] = $default_lang;
            }
        }
    }

    /**
     * Get module data when in production mode
     * @return void
     */
    public function setup_production_modules() {
        $this->filters = $this->site_config->get('input_filters', array());
        $this->handlers = $this->site_config->get('handler_modules', array());
        $this->outputs = $this->site_config->get('output_modules', array());
    }

    /**
     * Get module data when in debug mode
     * @return array list of filters, input, and output modules
     */
    public function setup_debug_modules() {
        $filters = array();
        $filters = array('allowed_output' => array(), 'allowed_get' => array(), 'allowed_cookie' => array(),
            'allowed_post' => array(), 'allowed_server' => array(), 'allowed_pages' => array());
        $modules = explode(',', $this->site_config->get('modules', ''));
        foreach ($modules as $name) {
            if (is_readable(sprintf(APP_PATH."modules/%s/setup.php", $name))) {
                $filters = self::merge_filters($filters, require sprintf(APP_PATH."modules/%s/setup.php", $name));
            }
        }
        $this->filters = $filters;
    }

    /**
     * Merge input filters from module sets
     * @param array $existing already collected filters
     * @param array $new new filters to merge
     * @return array merged list
     */
    static public function merge_filters($existing, $new) {
        foreach (array('allowed_output', 'allowed_get', 'allowed_cookie', 'allowed_post', 'allowed_server', 'allowed_pages') as $v) {
            if (array_key_exists($v, $new)) {
                if ($v == 'allowed_pages' || $v == 'allowed_output') {
                    $existing[$v] = array_merge($existing[$v], $new[$v]);
                }
                else {
                    $existing[$v] += $new[$v];
                }
            }
        }
        return $existing;
    }

    /**
     * Return the subset of active modules from a supplied list
     * @param array $mod_list list of modules
     * @return array filter list
     */
    public function get_active_mods($mod_list) {
        return array_unique(array_values(array_map(function($v) { return $v[0]; }, $mod_list)));
    }

    /**
     * Load modules into a module manager
     * @param string $class name of the module manager to use
     * @param array $module_sets list of modules by page
     * @param string $page page id
     * @return void
     */
    public function load_modules($class, $module_sets, $page) {
        foreach ($module_sets as $mod_page => $modlist) {
            foreach ($modlist as $name => $vals) {
                if ($page == $mod_page) {
                    $class::add($mod_page, $name, $vals[1], false, 'after', true, $vals[0]);
                }
            }
        }
        $class::try_queued_modules();
        $class::process_all_page_queue();
    }

    /**
     * Load all module sets and include required modules.php files
     * @param string $page page id
     * @return void
     */
    public function load_module_sets($page) {

        $this->load_modules('Hm_Handler_Modules', $this->handlers, $page);
        $this->load_modules('Hm_Output_Modules', $this->outputs, $page);
        $active_mods = array_unique(array_merge($this->get_active_mods(Hm_Output_Modules::get_for_page($page)),
            $this->get_active_mods(Hm_Handler_Modules::get_for_page($page))));
        if (!count($active_mods)) {
            Hm_Functions::cease('No module assignments found');
        }
        $mods = explode(',', $this->site_config->get('modules', '')); 
        $this->load_module_set_files($mods, $active_mods);
    }

    /**
     * Load module set definition files
     * @param array $mods modules to load
     * @param array $active_mods list of active modules
     * @return void
     */
    public function load_module_set_files($mods, $active_mods) {
        foreach ($mods as $name) {
            if (in_array($name, $active_mods, true) && is_readable(sprintf(APP_PATH.'modules/%s/modules.php', $name))) {
                require sprintf(APP_PATH.'modules/%s/modules.php', $name);
            }
        }
    }
}

/**
 * MODULE SET FUNCTIONS
 *
 * This is the functional interface used by module sets to
 * setup data handlers and output modules in their setup.php files.
 * They are easier to use than dealing directly with the class instances
 */ 

/**
 * Add a module set name to the input processing manager
 * @param string $source module set name
 * @return void
 */
function handler_source($source) {
    Hm_Handler_Modules::set_source($source);
}

/**
 * Add a module set name to the output module manager
 * @param string $source module set name
 * @return void
 */
function output_source($source) {
    Hm_Output_Modules::set_source($source);
}

/**
 * Replace an already assigned module with a different one
 * @param string $type either output or handler
 * @param string $target module name to replace
 * @param string $replacement module to swap in
 * @param string $page request id, otherwise try all page names
 * $return void
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
 * @param string $mod name of the module to add
 * @param bool $logged_in true if the module should only fire when logged in
 * @param string $source the module set containing the module code
 * @param string $marker the module name used to determine where to insert
 * @param string $placement "before" or "after" the $marker module name
 * @param bool $queue true if the module should be queued and retryed on failure
 * @return void
 */
function add_handler($page, $mod, $logged_in, $source=false, $marker=false, $placement='after', $queue=true) {
    Hm_Handler_Modules::add($page, $mod, $logged_in, $marker, $placement, $queue, $source);
}

/**
 * Add an output module to a specific page
 * @param string $mod name of the module to add
 * @param bool $logged_in true if the module should only fire when logged in
 * @param string $source the module set containing the module code
 * @param string $marker the module name used to determine where to insert
 * @param string $placement "before" or "after" the $marker module name
 * @param bool $queue true if the module should be queued and retryed on failure
 * @return void
 */
function add_output($page, $mod, $logged_in, $source=false, $marker=false, $placement='after', $queue=true) {
    Hm_Output_Modules::add($page, $mod, $logged_in, $marker, $placement, $queue, $source);
}

/**
 * Add an input or output module to all possible pages
 * @param string $type either output or handler
 * @param string $mod name of the module to add
 * @param bool $logged_in true if the module should only fire when logged in
 * @param string $source the module set containing the module code
 * @param string $marker the module name used to determine where to insert
 * @param string $placement "before" or "after" the $marker module name
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

?>
