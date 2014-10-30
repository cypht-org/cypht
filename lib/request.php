<?php

if (!defined('DEBUG_MODE')) { die(); }

/**
 * Data request details
 *
 * This is an interface to HTTP request details. All request data 
 * must be white-listed and sanitized by module set filters.
 */
class Hm_Request {

    /* sanitized $_POST variables */
    public $post = array();

    /* sanitized $_GET variables */
    public $get = array();

    /* sanitized $_COOKIE variables */
    public $cookie = array();

    /* sanitized $_SERVER variables */
    public $server = array(); 

    /* request type. either AJAX or HTTP */
    public $type = false;

    /* PHP sapi method used for the request */
    public $sapi = false;

    /* Output format, either Hm_Format_JSON or Hm_Format_HTML5 */
    public $format = false;

    /* bool indicating if the request was over SSL/TLS */
    public $tls = false;

    /* bool indicating if this looks like a mobile OS request */
    public $mobile = false;

    /* URL path */
    public $path = '';

    /* allowed AJAX output field names defined by module sets */
    public $allowed_output = array();

    /**
     * Process request details
     *
     * @param $filters array list of input filters from module sets
     *
     * @return void
     */
    public function __construct($filters) {
        $this->filter_request_input($filters);
        $this->get_other_request_details($filters);
        $this->remove_super_globals();

        Hm_Debug::add('Using sapi: '.$this->sapi);
        Hm_Debug::add('Request type: '.$this->type);
        Hm_Debug::add('Request path: '.$this->path);
        Hm_Debug::add('TLS request: '.intval($this->tls));
        Hm_Debug::add('Mobile request: '.intval($this->mobile));
    }

    /**
     * Sanitize and filter user and server input
     *
     * @param $filters array list of input filters from module sets
     *
     * @return void
     */
    private function filter_request_input($filters) {
        $this->server = $this->filter_input(INPUT_SERVER, $filters['allowed_server']);
        $this->post = $this->filter_input(INPUT_POST, $filters['allowed_post']);
        $this->get = $this->filter_input(INPUT_GET, $filters['allowed_get']);
        $this->cookie = $this->filter_input(INPUT_COOKIE, $filters['allowed_cookie']);
    }

    /**
     * Collect other useful details about a request
     *
     * @param $filters array list of input filters from module sets
     *
     * @return void
     */
    private function get_other_request_details($filters) {
        $this->sapi = php_sapi_name();
        $this->allowed_output = $filters['allowed_output'];
        if (array_key_exists('REQUEST_URI', $this->server)) {
            $this->path = $this->get_clean_url_path($this->server['REQUEST_URI']);
        }
        $this->get_request_type();
        $this->is_tls();
        $this->is_mobile();
    }

    /**
     * Unset PHP super globals so module sets can't "cheat" and use unsafe user input
     *
     * @return void
     */
    private function remove_super_globals() {
        unset($_POST);
        unset($_SERVER);
        unset($_GET);
        unset($_COOKIE);
        unset($GLOBALS);
        unset($_FILES);
        unset($_REQUEST);
        unset($_ENV);
    }

    /**
     * Filter specified input against module defined filters
     *
     * @param type string the type of input (POST, GET, COOKIE, etc)
     * @param filters array list of input filters from module sets
     *
     * @return array filtered input data
     */
    private function filter_input($type, $filters) {
        $data = filter_input_array($type, $filters, false);
        if (!$data) {
            return array();
        }
        return $data;
    }

    /**
     * Look at the HTTP_USER_AGENT value and set a mobile OS flag
     *
     * @return void
     */
    private function is_mobile() {
        if (array_key_exists('HTTP_USER_AGENT', $this->server)) {
            if (preg_match("/(iphone|ipod|ipad|android|blackberry|webos)/i", $this->server['HTTP_USER_AGENT'])) {
                $this->mobile = true;
            }
        }
    }

    /**
     * Determine if a request was done over TLS
     *
     * @return void
     */
    private function is_tls() {
        if (array_key_exists('HTTPS', $this->server) && strtolower($this->server['HTTPS']) == 'on') {
            $this->tls = true;
        }
        elseif (array_key_exists('REQUEST_SCHEME', $this->server) && strtolower($this->server['REQUEST_SCHEME']) == 'https') {
            $this->tls = true;
        }
    }

    /**
     * Determine the request type, either AJAX or HTTP
     *
     * @return void
     */
    private function get_request_type() {
        if ($this->is_ajax()) {
            $this->type = 'AJAX';
            $this->format = 'Hm_Format_JSON';
        }
        else {
            $this->type = 'HTTP';
            $this->format = 'Hm_Format_HTML5';
        }
    }

    /**
     * Determine if a request is an AJAX call
     *
     * @return bool true if the request is from an AJAX call
     */
    private function is_ajax() {
        return array_key_exists('HTTP_X_REQUESTED_WITH', $this->server) && strtolower($this->server['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    }

    /**
     * Make sure a url path is sane
     *
     * @param $uri string path to check
     *
     * @return string clean url path
     */
    private function get_clean_url_path($uri) {
        if (strpos($uri, '?') !== false) {
            $parts = explode('?', $uri, 2);
            $path = $parts[0];
        }
        else {
            $path = $uri;
        }
        if (substr($path, -1) != '/') {
            $path .= '/';
        }
        return $path;
    }
}

?>
