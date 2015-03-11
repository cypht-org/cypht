<?php

/**
 * Process a page request
 * @package framework
 * @subpackage dispatch
 */
if (!defined('DEBUG_MODE')) { die(); }

class Hm_Dispatch {

    public $site_config;
    public $request;
    public $session;
    public $module_exec;
    private $page;

    /**
     * Setup object needed to process a request
     * @return object
     */
    public function __construct() {

        $this->site_config = new Hm_Site_Config_File(CONFIG_FILE);
        $this->session = setup_session($this->site_config); 
        $this->module_exec = new Hm_Module_Exec($this->site_config);
        $this->request = new Hm_Request($this->module_exec->filters);
        $this->process_request();
    }

    /**
     * Process a request
     * @return void
     */
    private function process_request() {
        $this->get_page($this->module_exec->filters, $this->request);
        $this->module_exec->load_module_sets($this->page);
        $this->check_for_tls_redirect();
        $this->module_exec->run_handler_modules($this->request, $this->session, $this->page);
        $this->check_for_redirect();
        $this->module_exec->run_output_modules($this->request, $this->session, $this->page);
        $this->render_output();
        $this->save_session();
    }

    /**
     * Force TLS connections unless the site config has it disabled
     * @return void
     */
    public function check_for_tls_redirect() {
        if (!$this->request->tls && !$this->site_config->get('disable_tls', false)) {
            if (array_key_exists('SERVER_NAME', $this->request->server) && array_key_exists('REQUEST_URI', $this->request->server)) {
                Hm_Dispatch::page_redirect('https://'.$this->request->server['SERVER_NAME'].$this->request->server['REQUEST_URI']);
            }
        }
    }

    /**
     * Redirect the page after a POST form is submitted and forward any user notices
     * @return void
     */
    public function check_for_redirect() {
        if (array_key_exists('no_redirect', $this->module_exec->handler_response) && $this->module_exec->handler_response['no_redirect']) {
            return;
        }
        if (!empty($this->request->post) && $this->request->type == 'HTTP') {
            $msgs = Hm_Msgs::get();
            if (!empty($msgs)) {
                $this->session->secure_cookie($this->request, 'hm_msgs', base64_encode(serialize($msgs)), 0);
            }
            $this->session->end();
            if (array_key_exists('REQUEST_URI', $this->request->server)) {
                Hm_Dispatch::page_redirect($this->request->server['REQUEST_URI']);
            }
        }
        elseif (array_key_exists('hm_msgs', $this->request->cookie) && trim($this->request->cookie['hm_msgs'])) {
            $msgs = @unserialize(base64_decode($this->request->cookie['hm_msgs']));
            if (is_array($msgs)) {
                array_walk($msgs, function($v) { Hm_Msgs::add($v); });
            }
            $this->session->secure_cookie($this->request, 'hm_msgs', '', 0);
        }
    }

    /**
     * Close and save the session
     * @return void
     */
    private function save_session() {
        Hm_Page_Cache::save($this->session);
        hm_nonce::save($this->session);
        $this->session->end();
    }

    /**
     * Format and send the request output to the browser
     * @return void
     */
    private function render_output() {
        $formatter = new $this->request->format();
        $renderer = new Hm_Output_HTTP();
        $content = $formatter->content($this->module_exec->output_response, $this->request->allowed_output);
        $renderer->send_response($content, $this->module_exec->output_response);
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
