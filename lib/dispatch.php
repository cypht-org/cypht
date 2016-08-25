<?php

/**
 * Process a page request
 * @package framework
 * @subpackage dispatch
 */

/**
 * Page request router that ties all the framework peices together
 */
class Hm_Dispatch {

    public $site_config;
    public $request;
    public $session;
    public $module_exec;
    public $page;

    /**
     * Setup object needed to process a request
     * @param object $config site configuration
     * @return object
     */
    public function __construct($config) {

        /* get the site config defined in the hm3.rc file */
        $this->site_config = $config;

        /* setup a session handler, but don't actually start a session yet */
        $this->session = setup_session($this->site_config); 

        /* instantiate the module runner */
        $this->module_exec = new Hm_Module_Exec($this->site_config);

        /* process request input using the white-lists defined in modules */
        $this->request = new Hm_Request($this->module_exec->filters);

        /* do it */
        $this->process_request();
    }

    /**
     * Process a request
     * @return void
     */
    private function process_request() {

        /* get the request identifier */
        $this->get_page($this->module_exec->filters, $this->request);

        /* load up the module requirements */
        $this->module_exec->load_module_sets($this->page);

        /* check for TLS connections */
        $this->check_for_tls_redirect();

        /* run handler modules to process input and perform actions */
        $this->module_exec->run_handler_modules($this->request, $this->session, $this->page);

        /* check to see if a handler module told us to redirect the browser */
        $this->check_for_redirect();

        /* save and close a session if one is open */
        $active_session = $this->save_session();

        /* run the output formatting modules */
        $this->module_exec->run_output_modules($this->request, $active_session, $this->page);

        /* output content to the browser */
        $this->render_output();
    }

    /**
     * Force TLS connections unless the site config has it disabled
     * @return bool
     */
    public function check_for_tls_redirect() {
        if (!$this->request->tls && !$this->site_config->get('disable_tls', false) &&array_key_exists('SERVER_NAME', $this->request->server) && array_key_exists('REQUEST_URI', $this->request->server)) {
            return Hm_Dispatch::page_redirect('https://'.$this->request->server['SERVER_NAME'].$this->request->server['REQUEST_URI']);
        }
        return false;
    }

    /**
     * Redirect the page after a POST form is submitted and forward any user notices
     * @return mixed
     */
    public function check_for_redirect() {
        if (array_key_exists('no_redirect', $this->module_exec->handler_response) && $this->module_exec->handler_response['no_redirect']) {
            return 'noredirect';
        }
        if (!empty($this->request->post) && $this->request->type == 'HTTP') {
            $msgs = Hm_Msgs::get();
            if (!empty($msgs)) {
                $this->session->secure_cookie($this->request, 'hm_msgs', base64_encode(json_encode($msgs)), 0);
            }
            $this->session->end();
            if (array_key_exists('REQUEST_URI', $this->request->server)) {
                Hm_Dispatch::page_redirect($this->request->server['REQUEST_URI']);
            }
            return 'redirect';
        }
        elseif (array_key_exists('hm_msgs', $this->request->cookie) && trim($this->request->cookie['hm_msgs'])) {
            $msgs = @json_decode(base64_decode($this->request->cookie['hm_msgs']), true);
            if (is_array($msgs)) {
                array_walk($msgs, function($v) { Hm_Msgs::add($v); });
            }
            $this->session->secure_cookie($this->request, 'hm_msgs', '', 0);
            return 'msg_forward';
        }
        return false;
    }

    /**
     * Close and save the session
     * @return bool true if the session is active
     */
    private function save_session() {
        $res = $this->session->is_active();
        Hm_Page_Cache::save($this->session);
        $this->session->end();
        return $res;
    }

    /**
     * Format and send the request output to the browser
     * @return void
     */
    private function render_output() {
        $formatter = new $this->request->format($this->site_config);
        $renderer = new Hm_Output_HTTP();
        $content = $formatter->content($this->module_exec->output_response, $this->request->allowed_output);
        $renderer->send_response($content, $this->module_exec->output_data);
    }

    /**
     * Determine the page id
     * @param array $filters list of filters
     * @param object $request request details
     * @return void
     */
    public function get_page($filters, $request) {
        $this->page = 'notfound';
        $pages = array();
        if (array_key_exists('allowed_pages', $filters)) {
            $pages = $filters['allowed_pages'];
        }
        if ($request->type == 'AJAX' && array_key_exists('hm_ajax_hook', $request->post)) {
            if (in_array($request->post['hm_ajax_hook'], $pages, true)) {
                $this->page = $request->post['hm_ajax_hook'];
            }
            else {
                Hm_Functions::cease(json_encode(array('status' => 'not callable')));;
            }
        }
        elseif (array_key_exists('page', $request->get) && in_array($request->get['page'], $pages, true)) {
            $this->page = $request->get['page'];
        }
        elseif (!array_key_exists('page', $request->get)) {
            $this->page = 'home';
        }
        $this->module_exec->page = $this->page;
        Hm_Debug::add('Page ID: '.$this->page);
    }

    /**
     * Perform an HTTP redirect
     * @param string $url url to redirect to
     * @param int $status current HTTP status
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
        return Hm_Functions::cease();
    }

    /**
     * Check to see if PHP is configured properly
     * @return bool
     */
    static public function is_php_setup() {
        return
            (float) substr(phpversion(), 0, 3) >= 5.4 &&
            Hm_Functions::function_exists('mb_strpos') &&
            Hm_Functions::function_exists('curl_exec') &&
            Hm_Functions::function_exists('openssl_random_pseudo_bytes') &&
            Hm_Functions::class_exists('PDO');

    }
}
