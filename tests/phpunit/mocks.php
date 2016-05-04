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

class Hm_Mock_Config {
    public $mods = array();
    public $data = array(
        'user_settings_dir' => './data',
        'default_language' => 'es',
    );
    public function get($id, $default) {
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
    public $server = array('SERVER_NAME' => 'test', 'REQUEST_URI' => 'test', 'HTTP_USER_AGENT' => 'android');
    public $tls = false;
    public $type;
    public $sapi = 'test';
    public $format = 'Hm_Format_HTML5';
    public $path = 'asdf';
    public function __construct($type) {
        $this->type = $type;
    }
}
class Hm_Functions {
        public static $rand_bytes = 'good';
        public static $exists = true;
        public static $exec_res = '{"unit":"test"}';
        public static function setcookie($name, $value, $lifetime=0, $path='', $domain='', $html_only='') { return true; }
        public static function header($header) { return true; }
        public static function cease() { return true; }
        public static function session_start() { $_SESSION['data'] = 'RzEp5KFVqtwJeQLsx3+vjNLbvROUT7mGmUoHqzU0i/1P1W4W6qxsmhP+QAFF19wuFM741RSd+afsDxH4tKyIrtn+kxsvIfO/6xFAXQso8rwmLPKw46lAGbQifATo0GRFlUU3cFs215DAZ5BP3tIgc3KsNQP0cPy9ZAaJ+3IM2ivNLoX7JXE69ZyflgVeJI7ihgYQBgVnai0dwGcF1R4kHLjssqbeqDsh7hGnLiCBn4BaTeOGYTUqTIfgm7ZaBCg1CBq98jntG8kdLLdQDgopYw=='; }
        public static function error_log() { return true; }
        public static function c_init() { return true; }
        public static function c_setopt() { return true; }
        public static function c_exec() { return self::$exec_res; }
        public static function function_exists($func) { return self::$exists; }
        public static function class_exists($func) { return self::$exists; }
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
}

function setup_db($config) {
    $config->set('db_driver', 'mysql');
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
        'allowed_server' => array('REQUEST_SCHEME' => FILTER_SANITIZE_STRING, 'HTTP_USER_AGENT' => FILTER_SANITIZE_STRING, 'HTTPS' => FILTER_SANITIZE_STRING, 'HTTP_X_REQUESTED_WITH' => FILTER_SANITIZE_STRING, 'REQUEST_URI' => FILTER_SANITIZE_STRING),
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
