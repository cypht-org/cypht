<?php

class Hm_Mock_Session {
    public $enc_key = 'asdf';
    public $loaded = true;
    public $auth_state = true;
    public $cookie_set = false;
    public $data = array();
    public function get($id, $default=false) {
        if ($id == 'saved_pages') {
            return array('foo' => array('bar', false));
        }
        elseif (array_key_exists($id, $this->data)) {
            return $this->data[$id];
        }
        return $default;
    }
    public function del($name) {
        if (array_key_exists($name, $this->data)) {
            unset($this->data[$name]);
            return true;
        }
        return false;
    }
    public function set($name, $value) {
        $this->data[$name] = $value;
    }
    public function build_fingerprint($request, $site_key) {
        return 'fakefingerprint';
    }
    public function record_unsaved() {
        return true;
    }
    public function is_active() {
        return $this->loaded;
    }
    public function destroy() {
        return true;
    }
    public function close_early() {
        $this->loaded = false;
        return true;
    }
    public function end() {
       return true; 
    }
    public function secure_cookie($request, $name, $value, $path='', $domain='') {
        $this->cookie_set = true;
        return true;
    }
    public function auth($user, $pass) {
        return $this->auth_state;
    }
    public function check() {
        return true;
    }
}
class Hm_Mock_Redis_No {
    public static $fail_type = 'exception';
    function connect($server, $port) {
        if (self::$fail_type == 'exception') {
            throw new Exception();
        }
        else {
            return false;
        }
    }
}
class Hm_Mock_Memcached_No {
    function addServer($server, $port) {
        return false;
    }
}
class Hm_Mock_Memcached {
    protected $data = array();
    public static $set_failure = false;
    const RES_NOTFOUND = 1;
    const OPT_BINARY_PROTOCOL = false;
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
    function setOption($val) {
        return true;
    }
    function setSaslAuthData($u, $p) {
        return true;
    }
    function getResultCode() {
        return 16;
    }
}

class Hm_Mock_Redis extends Hm_Mock_Memcached {
    function connect($server, $port) {
        return true;
    }
    function select($index) {
        return true;
    }
    function auth($pass) {
        return true;
    }
    function close() {
        return true;
    }
    function del($key) {
        if (array_key_exists($key, $this->data)) {
            unset($this->data[$key]);
            return true;
        }
        return false;
    }
}
if (!class_exists('Memcached')) {
    class Memcached extends Hm_Mock_Memcached {}
}
if (!class_exists('Redis')) {
    class Redis extends Hm_Mock_Redis {}
}
class Hm_Mock_Config {
    public $mods = array();
    public $user_defaults = array();
    public $data = array(
        'user_settings_dir' => './data',
        'default_language' => 'es',
        'default_setting_inline_message' => true
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
    public function dump() {
        return $this->data;
    }
    public function save() {
    }
    public function load() {
    }
    public function reload() {
    }
}
class Hm_Mock_Request {
    public $invalid_input_detected;
    public $post = array('hm_page_key' => 'asdf', 'fld1' => '0', 'fld2' => '1', 'fld3' => 0, 'fld4' => NULL);
    public $get = array();
    public $cookie = array();
    public $server = array('SERVER_NAME' => 'test', 'REQUEST_URI' => 'test', 'HTTP_USER_AGENT' => 'android', 'REQUEST_METHOD' => 'GET');
    public $mobile = false;
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
class Fake_Server {
    protected $position;
    protected $response = '';
    public $command_responses = array();
    function stream_open($path, $mode, $options, &$opened) {
        $this->position = 0;
        return true;
    }
    function stream_read($count) {
        $this->position += strlen($this->response);
        return $this->response;
    }
    function stream_write($data) {
        //print_r($data);
        $data = trim($data);
        if (array_key_exists($data, $this->command_responses)) {
            $this->response =  $this->command_responses[$data];
        }
        else {
            $this->response = $this->error_resp($data);
        }
        //print_r($this->response);
        rewind(Hm_Functions::$resource);
        return (strlen($data)+2);
    }
    function stream_tell() {
        return $this->position;
    }
    function stream_seek($pos, $whence) {
        $this->position = 0;
        return true;
    }
    function stream_eof() {
        $res = $this->position >= strlen($this->response);
        return $res;
    }
    function error_resp($data) {
        return "ERROR\r\n";
    }
}
class Fake_IMAP_Server extends Fake_Server {
    public $command_responses;
    public static $custom_responses = array();
    function __construct() {
        $this->command_responses = require 'imap_commands.php';
        $this->command_responses = array_merge($this->command_responses, self::$custom_responses);
    }
    function error_resp($data) {
        $bits = explode(' ', $data);
        $pre = $bits[0];
        return $pre." BAD Error in IMAP command received by server.\r\n";
    }
}
class Hm_Functions {
    public static $resource = false;
    public static $rand_bytes = 'good';
    public static $memcache = true;
    public static $redis_on = true;
    public static $exists = true;
    public static $exec_res = '{"unit":"test"}';
    public static $filter_failure = false;
    public static $no_stream = false;
    public static function setcookie($name, $value, $lifetime=0, $path='', $domain='', $html_only='') { return true; }
    public static function header($header) { return true; }
    public static function cease() { return true; }
    public static function session_start() { $_SESSION['data'] = 'AT1R5eVsyEauGR/stxOdA7f1OaxFr7p8vhE9j/JfwQwX2Jk7RQh4PoS1t1'.
        '/baEG9jvuF2Y5UmDPjt6/Hd0ESWfbh4uI80xlvd1+Vt1rXtQU1mIJ+c+W0zRgdXPTTjkoZwSk7CFxCqNbYUviCkpxnNYXlZc9aEl9hgERkStY3u6phsk'.
        'Jtoy6+MWo8dB+btO0PulIqXNz6WEBnuWa0/KHrelM2O/6N+9sdANg2CNUYo2ZsOtOZ4jEF9G27qZM2ILlnXwa1HCRDYByzmvk4Teg+PA=='; }
    public static function session_destroy() { return true; }
    public static function error_log($str=true) { return $str; }
    public static function c_init() { return true; }
    public static function c_setopt() { return true; }
    public static function c_status() { return 200; }
    public static function c_exec() { return self::$exec_res; }
    public static function function_exists($func) {
        if ((float) substr(phpversion(), 0, 3) < 5.6) {
            return false;
        }
        return self::$exists;
    }
    public static function class_exists($func) { return self::$exists; }
    public static function memcached() { return self::$memcache ? new Hm_Mock_Memcached() : new Hm_Mock_Memcached_No(); }
    public static function redis() { return self::$redis_on ? new Hm_Mock_Redis() : new Hm_Mock_Redis_No(); }
    public static function random_bytes($size) {
        if (self::$rand_bytes == 'good') {
            return random_bytes($size);
        $imap = new Hm_IMAP();
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
    public static function stream_socket_client($server, $port, &$errno, &$errstr, $timeout, $mode, $ctx) {
        if (self::$no_stream) {
            return false;
        }
        if (!in_array('foo', stream_get_wrappers(), true)) {
            stream_wrapper_register('foo', 'Fake_IMAP_Server');
        }
        $res = fopen('foo://', 'w+');
        self::$resource = $res;
        return $res;
    }
    public static function stream_ended($resource) {
        if (!is_resource($resource) || feof($resource)) {
            return true;
        }
        return false;
    }
    public static function stream_socket_enable_crypto($socket, $type) {
        return true;
    }
}
function setup_db($config) {
    $config->set('db_connection_type', 'host');
    $config->set('db_socket', '/tmp/test.db');
    $config->set('db_driver', 'mysql');
    $config->set('db_host', '127.0.0.1');
    $config->set('db_name', 'cypht_test');
    $config->set('db_user', 'cypht_test');
    $config->set('db_pass', 'cypht_test');
}
function flatten($str) {
    return strtolower(str_replace(array("\n", "\t", "\r", " "), '', $str));
}
function filters() {
    return array(
        'allowed_pages' => array('test'),
        'allowed_post' => array('bar' => FILTER_VALIDATE_INT),
        'allowed_output' => array(),
        'allowed_server' => array('REQUEST_METHOD' => FILTER_SANITIZE_FULL_SPECIAL_CHARS, 'REQUEST_SCHEME' => FILTER_SANITIZE_FULL_SPECIAL_CHARS,
            'HTTP_USER_AGENT' => FILTER_SANITIZE_FULL_SPECIAL_CHARS, 'HTTPS' => FILTER_SANITIZE_FULL_SPECIAL_CHARS,
            'HTTP_X_REQUESTED_WITH' => FILTER_SANITIZE_FULL_SPECIAL_CHARS, 'REQUEST_URI' => FILTER_SANITIZE_FULL_SPECIAL_CHARS),
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
    $parent->cache = new Hm_Cache($parent->session, $parent->site_config);
    return $parent;
}
function delete_uploaded_files($obj) {
    return true;
}

?>
