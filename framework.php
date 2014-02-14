<?php

/* handle page processing delegation */
class Hm_Router {

    private $page = 'home';
    private $pages = array('home' => 'Hm_Home', 'notfound' => 'Hm_Notfound');

    public $type = false;
    public $sapi = false;

    public function process_request() {
        $request = new Hm_Request();
        $session = new Hm_Session_PHP($request);
        $this->get_page($request);
        $result = $this->merge_response($this->process_page($request, $session), $request);
        $session->end();
        return $result;
    }

    private function get_page($request) {
        if (isset($request->get['page'])) {
            if (isset($this->pages[$request->get['page']])) {
                $this->page = $request->get['page'];
            }
            else {
                $this->page = 'notfound';
            }
        }
    }

    private function process_page($request, $session) {
        $response = array();
        $handler_name = $this->pages[$this->page];
        if (class_exists($handler_name)) {
            $handler = new $handler_name();
            $response = $handler->process_request($request, $session);
        }
        else {
            die(sprintf("Page handler for page %s not found", $this->page));
        }
        return $response;
    }

    private function merge_response($response, $request) {
        return array_merge($response, array(
            'type' => $request->type,
            'sapi' => $request->sapi,
            'format' => $request->format
        ));
    }
}

/* data request details */
class Hm_Request {

    public $post = array();
    public $get = array();
    public $cookie = array();
    public $uri = false;
    public $server = false;
    public $server_port = false;
    public $script = false;
    public $agent = false;
    public $type = false;
    public $sapi = false;
    public $format = false;

    private $server_vars = array(
        'uri'         => 'REQUEST_URI',
        'server_addr' => 'SERVER_ADDR',
        'server_port' => 'SERVER_PORT',
        'script'      => 'PHP_SELF',
        'agent'       => 'HTTP_USER_AGENT',
        'server'      => 'SERVER_NAME'
    );

    public function __construct() {
        $this->sapi = php_sapi_name();
        $this->get_request_type();

        if ($this->type == 'HTTP') {
            $this->get_server_vars();
            $this->fetch_post_vars();
            $this->fetch_get_vars();
            $this->fetch_cookie_vars();
        }
        if ($this->type == 'CLI') {
            $this->fetch_cli_vars();
        }
    }

    private function get_server_vars() {
        foreach ($this->server_vars as $type => $name) {
            if (isset($_SERVER[$name])) {
                if ($value = $this->check($type, $_SERVER[$name])) {
                    $this->{$type} = $value;
                }
            }
        }
    }

    private function fetch_post_vars() {
        foreach ($_POST as $name => $value) {
            if ($res = $this->check($name, $value)) {
                $this->post[$name] = $value;
            }
        }
    }

    private function fetch_get_vars() {
        foreach ($_GET as $name => $value) {
            if ($res = $this->check($name, $value)) {
                $this->get[$name] = $value;
            }
        }
    }

    private function fetch_cookie_vars() {
        foreach ($_COOKIE as $name => $value) {
            if ($res = $this->check($name, $value)) {
                $this->cookie[$name] = $value;
            }
        }
    }

    private function fetch_cli_vars() {
        global $argv;
        if (empty($this->get) && empty($this->post)) {
            if (isset($argv) && !empty($argv)) {
                foreach($argv as $val) {
                    if (strstr($val, '=')) {
                        $arg_parts = explode('=', $val, 2);
                        $this->get[$arg_parts[0]] = $arg_parts[1];
                    }
                }
            }
        }
    }

    private function check($name, $value) {
        return Hm_Validator::whitelist($name, $value);
    }

    private function get_request_type() {
        if ($this->is_cli()) {
            $this->type = 'CLI';
            $this->format = 'Hm_Format_Terminal';
        }
        elseif ($this->is_ajax()) {
            $this->type = 'AJAX';
            $this->format = 'Hm_Format_JSON';
        }
        else {
            $this->type = 'HTTP';
            $this->format = 'Hm_Format_HTML5';
        }
    }

    private function is_cli() {
        return strtolower(php_sapi_name()) == 'cli';
    }

    private function is_ajax() {
        return isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    }
}

/* validate input values */
class Hm_Validator {

    private static $allowed_input = array(
        'page'        => 'string',
        'uri'         => 'string',
        'server_addr' => 'string',
        'server_port' => 'int',
        'script'      => 'string',
        'server'      => 'string',
        'agent'       => 'string'
    );

    public static function whitelist($name, $value) {
        if (!isset(self::$allowed_input[$name])) {
            return false;
        }
        if (!self::is_valid($name, $value)) {
            return false;
        }
        return $value;
    }

    /* TODO: not ascii? */
    public static function is_valid($name, $value) {
        $type = self::$allowed_input[$name];
        return self::{'validate_'.$type}($value);
    }

    public static function validate_string($value) {
        return str_replace(array("\r", "\n"), array(''), $value) == $value;
    }

    public static function validate_int($value) {
        return ctype_digit((string)$value);
    }
}

/* debug output */
class Hm_Debug {

    private static $debug = array();

    public static function add($string) {
        self::$debug[] = $string;
    }

    public static function show() {
        print_r(self::$debug);
    }
}

?>
