<?php

/**
 * Module management classes
 * @package framework
 * @subpackage modules
 */

/**
 * Output module execution methods
 */
trait Hm_Output_Module_Exec {

    public $output_response;
    public $output_data = array();

    /**
     * Look for translation strings based on the current language setting
     * return array
     */
    abstract public function get_current_language();

    /**
     * Setup a default language translation
     * @return void
     */
    abstract public function default_language();

    /**
     * Run all the handler modules for a page and merge the results
     * @param Hm_Request $request details about the request
     * @param bool $active_session true if the session is active
     * @param string $page current page request
     * @param array $handler_response data from handler modules
     * @return void
     */
    public function run_output_modules($request, $active_session, $page, $handler_response) {
        $input = $handler_response;
        $protected = array();
        $modules = Hm_Output_Modules::get_for_page($page);
        $list_output = array();
        foreach ($modules as $name => $args) {
            list($output, $protected, $type) = $this->run_output_module($input, $protected, $name,
                $args, $active_session, $request->format, $this->get_current_language());
            if ($type != 'JSON') {
                $list_output[] = $output;
            }
            else {
                $input = $output;
            }
        }
        if (!empty($list_output)) {
            $this->output_response = $list_output;
            $this->output_data = $input;
        }
        else {
            $this->output_response = $input;
            $this->output_data = $input;
        }
    }

    /**
     * Run a single output modules and return the results
     * @param array $input handler output 
     * @param array $protected list of protected output
     * @param string $name handler module name
     * @param array $args module arguments
     * @param bool $active_session true if the session is active
     * @param string $format HTML5 or JSON format
     * @param array $lang_str translation lookup array
     * @return array
     */
    public function run_output_module($input, $protected, $name, $args, $active_session, $format, $lang_str) {
        $name = "Hm_Output_$name";
        if (!class_exists($name)) {
            Hm_Debug::add(sprintf('Output module %s activated but not found', $name));
            return $this->output_result($input, $protected, false);
        }
        if ($args[1] && !$active_session) {
            return $this->output_result($input, $protected, false);
        }
        $mod = new $name($input, $protected);
        return $this->process_result($mod, $format, $lang_str, $protected);
    }

    /**
     * @param object $mod output module
     * @param string $format output format type
     * @param array $lang_str translation lookup array
     * @param array protected output
     * @return array
     */
    private function process_result($mod, $format, $lang_str, $protected) {
        $mod_output = false;
        if ($format == 'Hm_Format_JSON') {
            $mod->output_content($format, $lang_str, $protected);
            $input = $mod->module_output();
            $protected = $mod->output_protected();
        }
        else {
            $mod_output = $mod->output_content($format, $lang_str, array());
            $input = $mod->module_output();
        }
        return $this->output_result($input, $protected, $mod_output);
    }

    /**
     * @param array $input module input
     * @param array $protected protected output
     * @param string|false http output module result
     * @return array
     */
    private function output_result($input, $protected, $mod_output) {
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

    public $request;
    public $site_config;
    public $handler_response = array();
    public $page = '';
    public $session;

    /**
     * Setup a default language translation
     * @return void
     */
    abstract public function default_language();

    /**
     * Run all the handler modules for a page and merge the results
     * @param Hm_Request $request details about the request
     * @param object $session session interface
     * @param string $page page id
     * @return void
     */
    public function run_handler_modules($request, $session, $page) {
        $input = $this->handler_response;
        $protected = array();
        $this->request = $request;
        $this->session = $session;
        $this->cache = new Hm_Cache($this->site_config, $session);
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
                $mod = new $name($this, $this->page, $input, $protected);
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
     * @param Hm_Request $request request details
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
            'router_module_list'  => $this->site_config->get_modules(),
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

    public $user_config;
    public $filters = array();
    public $handlers = array();
    public $outputs = array();

    /**
     * @param object $config site config
     */
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
     * @return void
     */
    public function setup_debug_modules() {
        $filters = array('allowed_output' => array(), 'allowed_get' => array(), 'allowed_cookie' => array(),
            'allowed_post' => array(), 'allowed_server' => array(), 'allowed_pages' => array());
        $modules = $this->site_config->get_modules();
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
        $class::try_queued_modules();
        $class::process_replace_queue();
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
        $mods = $this->site_config->get_modules(); 
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

