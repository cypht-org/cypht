<?php

/* base configuration */
abstract class Hm_Config {

    protected $source = false;
    protected $config = array();

    abstract protected function load($source);
    abstract protected function dump();
    abstract protected function set_var($name, $value);
    abstract protected function get_var($name, $default=false);
}

/* file based configuration */
class Hm_Config_File extends Hm_Config {

    public function __construct($source) {
        $this->load($source);
    }

    protected function load($source) {
        $data = unserialize(file_get_contents($source));
        if ($data) {
            $this->config = array_merge($this->config, $data);
        }
    }

    public function dump() {
        return $this->config;
    }

    protected function set_var($name, $value) {
        $this->config[$name] = $value;
    }

    protected function get_var($name, $default=false) {
        return isset($this->config[$name]) ? $this->config[$name] : $default;
    }
}


/* handle page processing delegation */
class Hm_Router {

    private $page = 'home';
    private $pages = array(
        'home'      => 'Hm_Home',
        'notfound'  => 'Hm_Notfound',
    );

    public $type = false;
    public $sapi = false;

    public function process_request($config) {
        $request = new Hm_Request();
        $session = new Hm_Session_PHP($request);
        $this->get_page($request);
        $prior_results = $this->forward_redirect_data($session, $request);
        $result = $this->merge_response($this->process_page($request, $session, $config), $request, $session);
        $result = array_merge($result, $prior_results);
        $this->check_for_redirect($request, $session, $result);
        $session->end();
        return $result;
    }

    private function forward_redirect_data($session, $request) {
        $res = $session->get('redirect_result', array());
        $redirect_msgs = $session->get('redirect_messages', array());
        $session->del('redirect_result');
        if (!empty($redirect_msgs)) {
            array_walk($redirect_msgs, function($v) { Hm_Msgs::add($v); });
            $session->del('redirect_messages');
        }
        return $res;
    }

    private function check_for_redirect($request, $session, $result) {
        if (!empty($request->post)) {
            $msgs = Hm_Msgs::get();
            if (!empty($msgs)) {
                $session->set('redirect_messages', $msgs);
            }
            $session->set('redirect_result', $result);
            $this->redirect($request->uri);
        }
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

    private function process_page($request, $session, $config) {
        $response = array();
        $handler_name = $this->pages[$this->page];
        if (class_exists($handler_name)) {
            $handler = new $handler_name();
            $response = $handler->process_request($this->page, $request, $session, $config);
        }
        else {
            die(sprintf("Page handler for page %s not found", $this->page));
        }
        return $response;
    }

    private function merge_response($response, $request, $session) {
        return array_merge($response, array(
            'router_page_name'    => $this->page,
            'router_request_type' => $request->type,
            'router_sapi_name'    => $request->sapi,
            'router_format_name'  => $request->format,
            'router_login_state'  => $session->active
        ));
    }

    public function redirect($url) {
        header('HTTP/1.1 303 Found');
        header('Location: '.$url);
        exit;
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
                $value = $this->check($type, $_SERVER[$name]);
                if ($value !== false) {
                    $this->{$type} = $value;
                }
            }
        }
    }

    private function fetch_post_vars() {
        foreach ($_POST as $name => $value) {
            $res = $this->check($name, $value);
            if ($res !== false) {
                $this->post[$name] = $value;
            }
        }
    }

    private function fetch_get_vars() {
        foreach ($_GET as $name => $value) {
            $res = $this->check($name, $value);
            if ($res !== false) {
                $this->get[$name] = $value;
            }
        }
    }

    private function fetch_cookie_vars() {
        foreach ($_COOKIE as $name => $value) {
            $res = $this->check($name, $value);
            if ($res !== false) {
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
        'page'            => 'string',
        'username'        => 'string',
        'password'        => 'string',
        'uri'             => 'string',
        'server_addr'     => 'string',
        'script'          => 'string',
        'server'          => 'string',
        'PHPSESSID'       => 'string',
        'agent'           => 'string',
        'logout'          => 'string',
        'new_imap_server' => 'string',
        'submit_server'   => 'string',
        'server_port'     => 'int',
        'new_imap_port'   => 'int',
        'tls'             => 'int',
        'imap_server_id'  => 'int',
        'imap_user'       => 'string',
        'imap_pass'       => 'string'
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

/* interface and debug mssages */
trait Hm_List {

    private static $msgs = array();

    public static function add($string) {
        self::$msgs[] = $string;
    }

    public static function get() {
        return self::$msgs;
    }

    public static function show() {
        print_r(self::$msgs);
    }
}
class Hm_Debug { use Hm_List; }
class Hm_Msgs { use Hm_List; }

/* modules */
trait Hm_Modules {

    private static $module_list = array();

    public static function add($page, $module, $logged_in, $marker=false, $placement='after', $module_args=array()) {
        $inserted = false;
        if (!isset(self::$module_list[$page])) {
            self::$module_list[$page] = array();
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
                    array($module => array('logged_in' => $logged_in, 'args' => $module_args)),
                    array_slice($list, $index));
                $inserted = true;
            }
        }
        else {
            $inserted = true;
            self::$module_list[$page][$module] = array('logged_in' => $logged_in, 'args' => $module_args);
        }
        if (!$inserted) {
            Hm_Msgs::add(sprintf('failed to insert module %s', $module));
        }
    }

    public static function del($page, $module) {
        if (isset(self::$module_list[$page][$module])) {
            unset(self::$module_list[$page][$module]);
        }
    }

    public static function get_for_page($page) {
        $res = array();
        if (isset(self::$module_list[$page])) {
            $res = array_merge($res, self::$module_list[$page]);
        }
        return $res;
    }
}
class Hm_Handler_Modules { use Hm_Modules; }
class Hm_Output_Modules { use Hm_Modules; }

?>
