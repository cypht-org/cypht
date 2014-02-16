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
            $this->redirect($request->server['REQUEST_URI']);
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
        $handler = new Hm_Request_Handler();
        $response = $handler->process_request($this->page, $request, $session, $config);
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
    public $server = array(); 

    public $type = false;
    public $sapi = false;
    public $format = false;

    private $allowed_cookie = array(
        'PHPSESSID' => FILTER_SANITIZE_STRING
    );

    private $allowed_server = array(
        'REQUEST_URI' => FILTER_SANITIZE_STRING,
        'SERVER_ADDR' => FILTER_VALIDATE_IP,
        'SERVER_PORT' => FILTER_VALIDATE_INT,
        'PHP_SELF' => FILTER_SANITIZE_STRING,
        'HTTP_USER_AGENT' => FILTER_SANITIZE_STRING,
        'SERVER_NAME' => FILTER_SANITIZE_STRING
    );

    private $allowed_get = array(
        'page' => FILTER_SANITIZE_STRING
    );

    private $allowed_post = array(
        'logout' => FILTER_VALIDATE_BOOLEAN,
        'tls' => FILTER_VALIDATE_BOOLEAN,
        'server_port' => FILTER_VALIDATE_INT,
        'server' => FILTER_SANITIZE_STRING,
        'username' => FILTER_SANITIZE_STRING,
        'password' => FILTER_SANITIZE_STRING,
        'new_imap_server' => FILTER_SANITIZE_STRING,
        'new_imap_port' => FILTER_VALIDATE_INT,
        'imap_server_id' => FILTER_VALIDATE_INT,
        'imap_user' => FILTER_SANITIZE_STRING,
        'imap_pass' => FILTER_SANITIZE_STRING,
        'imap_delete' => FILTER_SANITIZE_STRING,
        'submit_server' => FILTER_SANITIZE_STRING,
    );

    public function __construct() {
        $this->sapi = php_sapi_name();
        $this->get_request_type();

        if ($this->type == 'HTTP') {
            $this->server = filter_input_array(INPUT_SERVER, $this->allowed_server, false);
            $this->post = filter_input_array(INPUT_POST, $this->allowed_post, false);
            $this->get = filter_input_array(INPUT_GET, $this->allowed_get, false);
            $this->cookie = filter_input_array(INPUT_COOKIE, $this->allowed_cookie, false);
        }
        if ($this->type == 'CLI') {
            $this->fetch_cli_vars();
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
