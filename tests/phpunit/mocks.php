<?php

class Hm_Mock_Session {
    public $loaded = true;
    public $auth_state = true;
    public $cookie_set = false;
    public $data = array();
    public $auth_failed = true;
    public function get($id, $default=false) {
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
    function setOption($val) {
        return true;
    }
    function setSaslAuthData($u, $p) {
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
        $data = trim($data);
        if (array_key_exists($data, $this->command_responses)) {
            $this->response =  $this->command_responses[$data];
        }
        else {
            $this->response = $this->error_resp($data);
        }
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
    public $command_responses = array(
        'A1 CAPABILITY' => "* CAPABILITY IMAP4rev1 LITERAL+ SASL-IR LOGIN-REFERRALS ID ENABLE IDLE AUTH=PLAIN AUTH=CRAM-MD5\r\n",
        'A3 CAPABILITY' => "* CAPABILITY IMAP4rev1 LITERAL+ SASL-IR LOGIN-REFERRALS ID ENABLE IDLE SORT SORT=DISPLAY THREAD=".
            "REFERENCES THREAD=REFS THREAD=ORDEREDSUBJECT MULTIAPPEND URL-PARTIAL CATENATE UNSELECT CHILDREN NAMESPACE UIDPLUS ".
            "LIST-EXTENDED I18NLEVEL=1 CONDSTORE QRESYNC ESEARCH ESORT SEARCHRES WITHIN CONTEXT=SEARCH LIST-STATUS BINARY MOVE\r\n".
            "A3 OK Capability completed (0.001 + 0.000 secs).\r\n",
        'A2 LOGIN "testuser" "testpass"' => "* BANNER\r\n* BANNER2\r\nA2 OK [CAPABILITY IMAP4rev1 LITERAL+ SASL-IR LOGIN-REFERRALS ID ENABLE IDLE SORT ".
            "SORT=DISPLAY THREAD=REFERENCES THREAD=REFS THREAD=ORDEREDSUBJECT MULTIAPPEND URL-PARTIAL CATENATE UNSELECT CHILDREN ".
            "NAMESPACE UIDPLUS LIST-EXTENDED I18NLEVEL=1 CONDSTORE QRESYNC ESEARCH ESORT SEARCHRES WITHIN CONTEXT=SEARCH LIST-STATUS ".
            "BINARY MOVE] Logged in\r\n",
        'A4 ENABLE QRESYNC' => "* ENABLED QRESYNC\r\nA4 OK Enabled (0.001 + 0.000 secs).\r\n",
        'A2 AUTHENTICATE CRAM-MD5' => "+ PDBFOTRCMUMwMkY5NDFFEFU2QkM5MjVFMUITFCMjZAbG9naW5wcm94eTZiLLmFsaWNlLml0Pg==\r\n",
        'dGVzdHVzZXIgMGYxMzE5YmIxMzMxOWViOWU4ZDdkM2JiZDJiZDJlOTQ=' => "A2 OK authentication successful\r\n",
        'A2 AUTHENTICATE XOAUTH2 dXNlcj10ZXN0dXNlcgFhdXRoPUJlYXJlciB0ZXN0cGFzcwEB' => "+ V1WTI5dENnPT0BAQ==\r\n",
        'A5 LIST (SPECIAL-USE) "" "*"' => "* LIST (\NoInferiors \UnMarked \Sent) \"/\" Sent\r\nA5 OK List completed (0.003 + 0.000 + 0.002 secs).\r\n",
        'A6 LIST (SPECIAL-USE) "" "*"' => "* LIST (\NoInferiors \UnMarked \Sent) \"/\" Sent\r\nA6 OK List completed (0.003 + 0.000 + 0.002 secs).\r\n",
        'A6 LIST "" "*" RETURN (CHILDREN STATUS (MESSAGES UNSEEN UIDVALIDITY UIDNEXT RECENT))' => "* LIST (\NoInferiors \UnMarked) \"/\" Torture\r\n* ".
            "STATUS Torture (MESSAGES 2 RECENT 0 UIDNEXT 3 UIDVALIDITY 813552407 UNSEEN 0)\r\n* LIST (\NoInferiors \UnMarked) \"/\" Sent\r\n* STATUS ".
            "Sent (MESSAGES 0 RECENT 0 UIDNEXT 1 UIDVALIDITY 1474301542 UNSEEN 0)* LIST (\HasNoChildren) \"/\" INBOX\r\n* STATUS INBOX (MESSAGES 93 ".
            "RECENT 0 UIDNEXT 1736 UIDVALIDITY 1422554786 UNSEEN 0)\r\n* A6 OK List completed (0.007 + 0.000 + 0.006 secs).\r\n",
        'A7 LSUB "" "*" RETURN (CHILDREN STATUS (MESSAGES UNSEEN UIDVALIDITY UIDNEXT RECENT))' => "* LSUB (\NoInferiors \UnMarked \Sent) \"/\" Sent\r\n* ".
            "STATUS Sent (MESSAGES 0 RECENT 0 UIDNEXT 1 UIDVALIDITY 1474301542 UNSEEN 0)\r\n* A7 OK Lsub completed (0.005 + 0.000 + 0.004 secs).\r\n",
        'A5 NAMESPACE' => "* NAMESPACE ((\"\" \"/\")) NIL NIL\r\nA5 OK Namespace completed (0.001 + 0.000 secs).\r\n",
        '' => "A2 Ok Success\r\n",
    );
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
}
function setup_db($config) {
    $config->set('db_connection_type', 'host');
    $config->set('db_socket', '/tmp/test.db');
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
        'allowed_server' => array('REQUEST_METHOD' => FILTER_SANITIZE_STRING, 'REQUEST_SCHEME' => FILTER_SANITIZE_STRING,
            'HTTP_USER_AGENT' => FILTER_SANITIZE_STRING, 'HTTPS' => FILTER_SANITIZE_STRING,
            'HTTP_X_REQUESTED_WITH' => FILTER_SANITIZE_STRING, 'REQUEST_URI' => FILTER_SANITIZE_STRING),
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
