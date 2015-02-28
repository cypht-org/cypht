<?php

/**
 * Request router
 * @package framework
 * @subpackage router
 */
if (!defined('DEBUG_MODE')) { die(); }

/**
 * Module management for the page request router
 */
trait Hm_Router_Modules {

    /**
     * Build a list of module properties
     * @param object $config site config object
     * @return array list of filters, input, and output modules
     */
    protected function process_module_setup($config, $debug_mode) {
        if ($debug_mode) {
            return $this->get_debug_modules($config);
        }
        else {
            return $this->get_production_modules($config);
        }
    }

    /**
     * Get module data when in debug mode
     * @param object $config site config object
     * @return array list of filters, input, and output modules
     */
    protected function get_debug_modules($config) {
        $filters = array();
        $filters = array('allowed_output' => array(), 'allowed_get' => array(), 'allowed_cookie' => array(),
            'allowed_post' => array(), 'allowed_server' => array(), 'allowed_pages' => array());
        $modules = explode(',', $config->get('modules', ''));
        foreach ($modules as $name) {
            if (is_readable(sprintf(APP_PATH."modules/%s/setup.php", $name))) {
                $filters = Hm_Router::merge_filters($filters, require sprintf(APP_PATH."modules/%s/setup.php", $name));
            }
        }
        $handler_mods = array();
        $output_mods = array();
        return array($filters, $handler_mods, $output_mods);
    }

    /**
     * Get module data when in production mode
     * @param object $config site config object
     * @return array list of filters, input, and output modules
     */
    public function get_production_modules($config) {
        $filters = $config->get('input_filters', array());
        $handler_mods = $config->get('handler_modules', array());
        $output_mods = $config->get('output_modules', array());
        return array($filters, $handler_mods, $output_mods);
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
     * @return void
     */
    public function load_modules($class, $module_sets) {
        foreach ($module_sets as $page => $modlist) {
            foreach ($modlist as $name => $vals) {
                if ($this->page == $page) {
                    $class::add($page, $name, $vals[1], false, 'after', true, $vals[0]);
                }
            }
        }
        $class::try_queued_modules();
        $class::process_all_page_queue();
    }

    /**
     * Load all module sets and include required modules.php files
     * @param object $config site config object
     * @param array $handlers list of handler modules
     * @param array $output list of output modules
     * @return void
     */
    protected function load_module_sets($config, $handlers=array(), $output=array()) {

        $this->load_modules('Hm_Handler_Modules', $handlers);
        $this->load_modules('Hm_Output_Modules', $output);
        $active_mods = array_unique(array_merge($this->get_active_mods(Hm_Output_Modules::get_for_page($this->page)),
            $this->get_active_mods(Hm_Handler_Modules::get_for_page($this->page))));
        if (!count($active_mods)) {
            Hm_Functions::cease('No module assignments found');
        }
        $mods = explode(',', $config->get('modules', '')); 
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

    /**
     * Process all the data handler modules for this page
     * @param object $request request details
     * @param object $session session interface
     * @param object $config site config
     * @return array combined handler module output
     */
    protected function process_page($request, $session, $config) {
        $response = array();
        $handler = new Hm_Request_Handler();
        $modules = Hm_Handler_Modules::get_for_page($this->page);
        $response = $handler->process_request($this->page, $request, $session, $config, $modules);
        return $response;
    }

    /**
     * Merge the combined response from the handler modules with some default values
     * @param array $response combined result of the handler modules
     * @param object $config site config
     * @param object $request request details
     * @param object $session session interface
     * @return void
     */
    protected function merge_response($response, $config, $request, $session) {
        return array_merge($response, array(
            'router_page_name'    => $this->page,
            'router_request_type' => $request->type,
            'router_sapi_name'    => $request->sapi,
            'router_format_name'  => $request->format,
            'router_login_state'  => $session->is_active(),
            'router_url_path'     => $request->path,
            'router_module_list'  => $config->get('modules', ''),
            'router_app_name'     => $config->get('app_name', 'HM3')
        ));
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

}

/**
 * Page request router. This class ties together everything needed to process
 * a request, initiate a session, run handler modules assigned to the request,
 * and possibly do an HTTP redirect
 */
class Hm_Router {

    /* module management */
    use Hm_Router_Modules;

    /* request id */
    public $page = 'home';

    /**
     * Main entry point to the router. All the work of processing input and sending output to and from
     * the browser happens here.
     * @param object $config site configuration object
     * @param bool $debug_mode true to use debug modules
     * @return array list of the response array, the session object, and the allowed output filters
     *               for ajax responses
     */
    public function process_request($config, $debug_mode) {

        /* get module assignments and input whitelists */
        list($filters, $handler_mods, $output_mods) = $this->process_module_setup($config, $debug_mode);

        /* process inbound data */
        $request = new Hm_Request($filters);

        /* check for HTTP TLS */
        $this->check_for_tls($config, $request);

        /* initiate a session class */
        $session = $this->setup_session($config);

        /* determine page or ajax request name */
        $this->get_page($request, $filters);

        /* load processing modules for this page */
        $this->load_module_sets($config, $handler_mods, $output_mods);

        /* run all the handler modules for a page and merge in some standard results */
        $response_data = $this->merge_response($this->process_page($request, $session, $config), $config, $request, $session);

        /* check for POST redirect messages */
        $this->check_for_redirected_msgs($session, $request);

        /* see if we should redirect this request */
        $this->check_for_redirect($request, $session, $response_data);

        /* format and output the response data */
        $formatter = new $response_data['router_format_name']();

        $this->render_output($formatter->format_content($response_data, $request->allowed_output), $response_data);

        /* save any cached stuff */
        Hm_Page_Cache::save($session);

        /* save nonce set */
        hm_nonce::save($session);

        /* close down the session */
        $session->end();
    }

    /**
     * Send the formatted content to the user
     * @param mixed $output data to send to the user
     * @param mixed $response_data router details
     * @return void
     */
    private function render_output($output, $response_data) {
        $renderer = new Hm_Output_HTTP();
        $renderer->send_response($output, $response_data);
    }

    /**
     * Force TLS connections unless the site config has it disabled
     * @param object $config site config object
     * @param object $request request object
     * @return void
     */
    public function check_for_tls($config, $request) {
        if (!$request->tls && !$config->get('disable_tls', false)) {
            if (array_key_exists('SERVER_NAME', $request->server) && array_key_exists('REQUEST_URI', $request->server)) {
                Hm_Router::page_redirect('https://'.$request->server['SERVER_NAME'].$request->server['REQUEST_URI']);
            }
        }
    }

    /**
     * Start the correct session and auth objects. This only initiates the objects
     * and does not process any session values yet
     * @param object $config site config object
     * @return object new session object
     */
    private function setup_session($config) {

        $session_type = $config->get('session_type', false);
        $auth_type = $config->get('auth_type', false);
        if ($auth_type) {
            if ($auth_type == 'DB') {
                require_once APP_PATH.'third_party/pbkdf2.php';
            }
            $auth_class = sprintf('Hm_Auth_%s', $auth_type);
        }
        else {
            $auth_class = 'Hm_Auth_None';
        }
        if ($session_type == 'DB') {
            $session_class = 'Hm_DB_Session';
        }
        else {
            $session_class = 'Hm_PHP_Session';
        }
        Hm_Debug::add(sprintf('Using %s with %s', $session_class, $auth_class));
        $session = new $session_class($config, $auth_class);
        return $session;
    }

    /**
     * Collect pending user notices from a cookie after a redirect
     * @param object $session session interface
     * @param object $request request details
     * @return void
     */
    public function check_for_redirected_msgs($session, $request) {
        if (array_key_exists('hm_msgs', $request->cookie) && trim($request->cookie['hm_msgs'])) {
            $msgs = @unserialize(base64_decode($request->cookie['hm_msgs']));
            if (is_array($msgs)) {
                array_walk($msgs, function($v) { Hm_Msgs::add($v); });
            }
            $session->secure_cookie($request, 'hm_msgs', '', 0);
        }
    }

    /**
     * Redirect the page after a POST form is submitted and forward any user notices
     * @param object $request request details
     * @param object $session session interface
     * @return void
     */
    public function check_for_redirect($request, $session, $result) {
        if (array_key_exists('no_redirect', $result) && $result['no_redirect']) {
            return;
        }
        if (!empty($request->post) && $request->type == 'HTTP') {
            $msgs = Hm_Msgs::get();
            if (!empty($msgs)) {
                $session->secure_cookie($request, 'hm_msgs', base64_encode(serialize($msgs)), 0);
            }
            $session->end();
            if (array_key_exists('REQUEST_URI', $request->server)) {
                Hm_Router::page_redirect($request->server['REQUEST_URI']);
            }
        }
    }

    /**
     * Determine the page id
     * @param object $request request details
     * @param array $filters list of filters
     * @return void
     */
    public function get_page($request, $filters) {
        if (array_key_exists('allowed_pages', $filters)) {
            $pages = $filters['allowed_pages'];
        }
        else {
            $pages = array();
        }
        if ($request->type == 'AJAX' && array_key_exists('hm_ajax_hook', $request->post) && in_array($request->post['hm_ajax_hook'], $pages, true)) {
            $this->page = $request->post['hm_ajax_hook'];
        }
        elseif ($request->type == 'AJAX' && array_key_exists('hm_ajax_hook', $request->post) && !in_array($request->post['hm_ajax_hook'], $pages, true)) {
            Hm_Functions::cease(json_encode(array('status' => 'not callable')));;
        }
        elseif (array_key_exists('page', $request->get) && in_array($request->get['page'], $pages, true)) {
            $this->page = $request->get['page'];
        }
        elseif (!array_key_exists('page', $request->get)) {
            $this->page = 'home';
        }
        else {
            $this->page = 'notfound';
        }
    }

    /**
     * Perform an HTTP redirect
     * @param string $url url to redirect to
     * @return void
     */
    static public function page_redirect($url, $status=false) {
        if (DEBUG_MODE) {
            Hm_Debug::add(sprintf('Redirecting to %s', $url));
            Hm_Debug::load_page_stats();
            Hm_Debug::show('log');
        }
        if ($status == 303) {
            Hm_Debug::add('Redirect loop found');
            Hm_Functions::cease('Redirect loop discovered');
        }
        Hm_Functions::header('HTTP/1.1 303 Found');
        Hm_Functions::header('Location: '.$url);
        Hm_Functions::cease();
    }
}

?>
