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
        return true;
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
    public function set($name, $value) {
        $this->data[$name] = $value;
    }
}

class Hm_Mock_Request {

    public $invalid_input_detected;
    public $post = array('hm_nonce' => 'asdf', 'fld1' => '0', 'fld2' => '1');
    public $get = array();
    public $cookie = array();
    public $server = array('SERVER_NAME' => 'test', 'REQUEST_URI' => 'test', 'HTTP_USER_AGENT' => 'android');
    public $tls = false;
    public $type;

    public function __construct($type) {
        $this->type = $type;
    }
}

class Hm_Functions {
        public static function setcookie($name, $value, $lifetime=0, $path='', $domain='', $html_only='') {
            return true;
        }
        public static function header($header) {
            return true;
        }
        public static function cease() {
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

?>
