<?php

class Hm_Mock_Session {
    public $loaded = true;
    public $data = array();
    public function get($id, $default) {
        if ($id == 'saved_pages') {
            return array('foo' => array('bar', false));
        }
        elseif (array_key_exists($id, $this->data)) {
            return $this->data[$id];
        }
        return $default;
    }
    public function set($name, $value) {
        $this->data[$name] = $value;
    }
    public function build_fingerprint($request, $site_key) {
        return 'fakefingerprint';
    }
    public function is_active() {
        return $this->loaded;
    }
    public function destroy() {
        return true;
    }
    public function end() {
       return true; 
    }
    public function secure_cookie($request, $name, $value, $lifetime) {
        return true;
    }
}

class Hm_Mock_Memcached_No {
    function addServer($server, $port) {
        return false;
    }
}
class Hm_Mock_Memcached {
    private $data = array();
    public static $set_failure = false;
    function addServer($server, $port) {
        return true;
    }
    function set($key, $val, $lifetime) {
        if (self::$set_failure) {
            return false;
        }
        $this->data[$key] = $val;
        return true;
    }
    function get($key) {
        if (array_key_exists($key, $this->data)) {
            return $this->data[$key];
        }
        return false;
    }
    function delete($key) {
        if (array_key_exists($key, $this->data)) {
            unset($this->data[$key]);
            return true;
        }
        return false;
    }
    function quit() {
        return true;
    }
}
class Hm_Mock_Config {
    public $mods = array();
    public $user_defaults = array();
    public $data = array(
        'user_settings_dir' => './data',
        'default_language' => 'es',
    );
    public function get($id, $default=false) {
        if (array_key_exists($id, $this->data)) {
            return $this->data[$id];
        }
        return $default;
    }
    public function get_modules() {
        return $this->mods;
    }
    public function set($name, $value) {
        $this->data[$name] = $value;
    }
}

class Hm_Mock_Request {

    public $invalid_input_detected;
    public $post = array('hm_page_key' => 'asdf', 'fld1' => '0', 'fld2' => '1');
    public $get = array();
    public $cookie = array();
    public $server = array('SERVER_NAME' => 'test', 'REQUEST_URI' => 'test', 'HTTP_USER_AGENT' => 'android', 'REQUEST_METHOD' => 'GET');
    public $tls = false;
    public $type;
    public $sapi = 'test';
    public $format = 'Hm_Format_HTML5';
    public $path = 'asdf';
    public $method = 'GET';
    public function __construct($type) {
        $this->type = $type;
    }
}
class Hm_Functions {
        public static $rand_bytes = 'good';
        public static $memcache = true;
        public static $exists = true;
        public static $exec_res = '{"unit":"test"}';
        public static $filter_failure = false;
        public static function setcookie($name, $value, $lifetime=0, $path='', $domain='', $html_only='') { return true; }
        public static function header($header) { return true; }
        public static function cease() { return true; }
        public static function session_start() { $_SESSION['data'] = 'AT1R5eVsyEauGR/stxOdA7f1OaxFr7p8vhE9j/JfwQwX2Jk7RQh4PoS1t1/baEG9jvuF2Y5UmDPjt6/Hd0ESWfbh4uI80xlvd1+Vt1rXtQU1mIJ+c+W0zRgdXPTTjkoZwSk7CFxCqNbYUviCkpxnNYXlZc9aEl9hgERkStY3u6phskJtoy6+MWo8dB+btO0PulIqXNz6WEBnuWa0/KHrelM2O/6N+9sdANg2CNUYo2ZsOtOZ4jEF9G27qZM2ILlnXwa1HCRDYByzmvk4Teg+PA=='; }
        public static function session_destroy() { return true; }
        public static function error_log($str=true) { return $str; }
        public static function c_init() { return true; }
        public static function c_setopt() { return true; }
        public static function c_exec() { return self::$exec_res; }
        public static function function_exists($func) {
            if ((float) substr(phpversion(), 0, 3) < 5.6) {
                return false;
            }
            return self::$exists;
        }
        public static function class_exists($func) { return self::$exists; }
        public static function memcached() { return self::$memcache ? new Hm_Mock_Memcached() : new Hm_Mock_Memcached_No(); }
        public static function random_bytes($size) {
            if (self::$rand_bytes == 'good') {
                return random_bytes($size);
            }
            else if (self::$rand_bytes == 'bad') {
                throw(new Error());
            }
            else if (self::$rand_bytes == 'ugly') {
                throw(new Exception());
            }
        }
        public static function filter_input_array($type, $filters) {
            if (self::$filter_failure) {
                return false;
            }
            switch ($type) {
            case INPUT_SERVER:
                return filter_var_array($_SERVER, $filters, false);
                break;
            case INPUT_POST:
                return filter_var_array($_POST, $filters, false);
                break;
            case INPUT_GET:
                return filter_var_array($_GET, $filters, false);
                break;
            case INPUT_COOKIE:
                return filter_var_array($_COOKIE, $filters, false);
                break;
            }
        }
}

function setup_db($config) {
    $config->set('db_connection_type', 'host');
    $config->set('db_socket', '/tmp/test.db');
    $config->set('db_driver', 'pgsql');
    $config->set('db_host', '127.0.0.1');
    $config->set('db_name', 'test');
    $config->set('db_user', 'test');
    $config->set('db_pass', '123456');
}
function flatten($str) {
    return strtolower(str_replace(array("\n", "\t", "\r", " "), '', $str));
}
function filters() {
    return array(
        'allowed_pages' => array('test'),
        'allowed_post' => array('bar' => FILTER_VALIDATE_INT),
        'allowed_output' => array(),
        'allowed_server' => array('REQUEST_METHOD' => FILTER_SANITIZE_STRING, 'REQUEST_SCHEME' => FILTER_SANITIZE_STRING, 'HTTP_USER_AGENT' => FILTER_SANITIZE_STRING, 'HTTPS' => FILTER_SANITIZE_STRING, 'HTTP_X_REQUESTED_WITH' => FILTER_SANITIZE_STRING, 'REQUEST_URI' => FILTER_SANITIZE_STRING),
        'allowed_get' => array('foo' => FILTER_UNSAFE_RAW),
        'allowed_cookie' => array()
    );
}
function build_parent_mock($request_type='HTML5') {
    $parent = new stdClass();
    $parent->session = new Hm_Mock_Session();
    $parent->request = new Hm_Mock_Request($request_type);
    $parent->site_config = new Hm_Mock_Config();
    $parent->user_config = new Hm_Mock_Config();
    return $parent;
}
function delete_uploaded_files($obj) {
    return true;
}

?>
