<?php

if (!defined('DEBUG_MODE')) { die(); }

/**
 * Page request router. This class ties together everything needed to process
 * a request, initiate a session, run handler modules assigned to the request,
 * and possibly do an HTTP redirect
 */
class Hm_Router {

    /* request id */
    private $page = 'home';

    /**
     * Main entry point to the router. All the work of processing input and sending output to and from
     * the browser happens here.
     *
     * @param $config object site configuration object
     *
     * @return array list of the response array, the session object, and the allowed output filters
     *               for ajax responses
     */
    public function process_request($config) {

        /* get module assignments and input whitelists */
        list($filters, $handler_mods, $output_mods) = $this->process_module_setup($config);

        /* process inbound data */
        $request = new Hm_Request($filters);

        /* check for HTTP TLS */
        $this->check_for_tls($config, $request);

        /* initiate a session class */
        $session = $this->setup_session($config);

        /* determine page or ajax request name */
        $this->get_page($request, $filters['allowed_pages']);

        /* load processing modules for this page */
        $this->load_module_sets($config, $handler_mods, $output_mods);

        /* run all the handler modules for a page and merge in some standard results */
        $response_data = $this->merge_response($this->process_page($request, $session, $config), $config, $request, $session);

        /* check for POST redirect messages */
        $this->check_for_redirected_msgs($session, $request);

        /* see if we should redirect this request */
        $this->check_for_redirect($request, $session, $response_data);

        /* format and output the response data */
        $this->render_output($this->format_response_content($response_data, $request->allowed_output), $response_data);

        /* save any cached stuff */
        Hm_Page_Cache::save($session);

        /* save nonce set */
        hm_nonce::save($session);

        /* close down the session */
        $session->end();
    }

    /**
     * Format result of output modules
     *
     * @param $response_data mixed data from output modules
     * @param $allowed_output array filters applied to JSON formatted output
     *
     * @return mixed formatted content
     */
    private function format_response_content($response_data, $allowed_output) {
        $formatter = new $response_data['router_format_name']();
        return $formatter->format_content($response_data, $allowed_output);
    }

    /**
     * Send the formatted content to the user
     *
     * @param $output mixed data to send to the user
     * @param $response_data mixed router details
     *
     * @return void
     */
    private function render_output($output, $response_data) {
        $renderer = new Hm_Output_HTTP();
        $renderer->send_response($output, $response_data);
    }

    /**
     * Build a list of module properties
     *
     * @param $config object site config object
     *
     * @return array list of filters, input, and output modules
     */
    private function process_module_setup($config) {
        if (DEBUG_MODE) {
            return $this->get_debug_modules($config);
        }
        else {
            return $this->get_production_modules($config);
        }
    }

    /**
     * Get module data when in debug mode
     *
     * @param $config object site config object
     *
     * @return array list of filters, input, and output modules
     */
    private function get_debug_modules($config) {
        $filters = array();
        $filters = array('allowed_output' => array(), 'allowed_get' => array(), 'allowed_cookie' => array(),
            'allowed_post' => array(), 'allowed_server' => array(), 'allowed_pages' => array());
        $modules = explode(',', $config->get('modules', array()));
        foreach ($modules as $name) {
            if (is_readable(sprintf("modules/%s/setup.php", $name))) {
                $filters = Hm_Router::merge_filters($filters, require sprintf("modules/%s/setup.php", $name));
            }
        }
        $handler_mods = array();
        $output_mods = array();
        return array($filters, $handler_mods, $output_mods);
    }

    /**
     * Get module data when in production mode
     *
     * @param $config object site config object
     *
     * @return array list of filters, input, and output modules
     */
    private function get_production_modules($config) {
        $filters = $config->get('input_filters', array());
        $handler_mods = $config->get('handler_modules', array());
        $output_mods = $config->get('output_modules', array());
        return array($filters, $handler_mods, $output_mods);
    }

    /**
     * Force TLS connections unless the site config has it disabled
     *
     * @param $config object site config object
     * @param $request object request object
     *
     * @return void
     */
    private function check_for_tls($config, $request) {
        if (!$request->tls && !$config->get('disable_tls', false)) {
            page_redirect('https://'.$request->server['SERVER_NAME'].$request->server['REQUEST_URI']);
        }
    }

    /**
     * Start the correct session and auth objects. This only initiates the objects
     * and does not process any session values yet
     *
     * @param $config object site config object
     *
     * @return object new session object
     */
    private function setup_session($config) {

        $session_type = $config->get('session_type', false);
        $auth_type = $config->get('auth_type', false);
        if ($auth_type) {
            if ($auth_type == 'DB') {
                require 'third_party/pbkdf2.php';
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
     * Return the subset of active modules from a supplied list
     *
     * @param $mod_list array list of modules
     *
     * @return array filter list
     */
    private function get_active_mods($mod_list) {
        return array_unique(array_values(array_map(function($v) { return $v[0]; }, $mod_list)));
    }

    /**
     * Load modules into a module manager
     *
     * @param $class string name of the module manager to use
     * @param $module_sets array list of modules by page
     *
     * @return void
     */
    private function load_modules($class, $module_sets) {
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
     *
     * @param $config object site config object
     * @param $handlers array list of handler modules
     * @param $output array list of output modules
     *
     * @return void
     */
    private function load_module_sets($config, $handlers=array(), $output=array()) {

        $this->load_modules('Hm_Handler_Modules', $handlers);
        $this->load_modules('Hm_Output_Modules', $output);
        $active_mods = array_unique(array_merge($this->get_active_mods(Hm_Output_Modules::get_for_page($this->page)),
            $this->get_active_mods(Hm_Handler_Modules::get_for_page($this->page))));

        $mods = explode(',', $config->get('modules', '')); 
        foreach ($mods as $name) {
            if (in_array($name, $active_mods, true) && is_readable(sprintf('modules/%s/modules.php', $name))) {
                require sprintf('modules/%s/modules.php', $name);
            }
        }
    }

    /**
     * Collect pending user notices from a cookie after a redirect
     *
     * @param $session object session interface
     * @param $request object request details
     *
     * @return void
     */
    private function check_for_redirected_msgs($session, $request) {
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
     *
     * @param $request object request details
     * @param $session object session interface
     *
     * @return void
     */
    private function check_for_redirect($request, $session, $result) {
        if (array_key_exists('no_redirect', $result) && $result['no_redirect']) {
            return;
        }
        if (!empty($request->post) && $request->type == 'HTTP') {
            $msgs = Hm_Msgs::get();
            if (!empty($msgs)) {
                $session->secure_cookie($request, 'hm_msgs', base64_encode(serialize($msgs)), 0);
            }
            $session->end();
            page_redirect($request->server['REQUEST_URI']);
        }
    }

    /**
     * Determine the page id
     *
     * @param $request object request details
     * @param $pages array list of allowed page ids from the modules
     *
     * @return void
     */
    private function get_page($request, $pages) {
        if ($request->type == 'AJAX' && array_key_exists('hm_ajax_hook', $request->post) && in_array($request->post['hm_ajax_hook'], $pages, true)) {
            $this->page = $request->post['hm_ajax_hook'];
        }
        elseif ($request->type == 'AJAX' && array_key_exists('hm_ajax_hook', $request->post) && !in_array($request->post['hm_ajax_hook'], $pages, true)) {
            die(json_encode(array('status' => 'not callable')));;
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
     * Process all the data handler modules for this page
     *
     * @param $request object request details
     * @param $session object session interface
     * @param $config object site config
     *
     * @return array combined handler module output
     */
    private function process_page($request, $session, $config) {
        $response = array();
        $handler = new Hm_Request_Handler();
        $response = $handler->process_request($this->page, $request, $session, $config);
        return $response;
    }

    /**
     * Merge the combined response from the handler modules with some default values
     *
     * @param $response array combined result of the handler modules
     * @param $config object site config
     * @param $request object request details
     * @param $session object session interface
     *
     * @return void
     */
    private function merge_response($response, $config, $request, $session) {
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
     *
     * @param $existing array already collected filters
     * @param $new array new filters to merge
     *
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
 * Perform an HTTP redirect
 *
 * @param $url string url to redirect to
 *
 * @return void
 */
function page_redirect($url) {
    if (DEBUG_MODE) {
        Hm_Debug::add(sprintf('Redirecting to %s', $url));
        Hm_Debug::load_page_stats();
        Hm_Debug::show('log');
    }
    header('HTTP/1.1 303 Found');
    header('Location: '.$url);
    exit;
}

?>
