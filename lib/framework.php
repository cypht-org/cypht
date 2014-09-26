<?php

if (!defined('DEBUG_MODE')) { die(); }

/* base configuration */
abstract class Hm_Config {

    protected $source = false;
    protected $config = array();

    abstract public function load($source, $key);

    public function dump() {
        return $this->config;
    }

    public function set($name, $value) {
        $this->config[$name] = $value;
    }

    public function get($name, $default=false) {
        return array_key_exists($name, $this->config) ? $this->config[$name] : $default;
    }
    protected function set_tz() {
        date_default_timezone_set($this->get('timezone_setting', 'UTC'));
    }
}

/* file based user configuration */
class Hm_User_Config_File extends Hm_Config {

    private $site_config = false;

    public function __construct($config) {
        $this->site_config = $config;
    }
    private function get_path($username) {
        $path = $this->site_config->get('user_settings_dir', false);
        return sprintf('%s/%s.txt', $path, $username);
    }

    public function load($username, $key) {
        $source = $this->get_path($username);
        if (is_readable($source)) {
            $str_data = file_get_contents($source);
            if ($str_data) {
                $data = @unserialize(Hm_Crypt::plaintext($str_data, $key));
                if (is_array($data)) {
                    $this->config = array_merge($this->config, $data);
                    $this->set_tz();
                }
            }
        }
    }

    public function reload($data) {
        $this->config = $data;
        $this->set_tz();
    }

    public function save($username, $key) {
        $destination = $this->get_path($username);
        $data = Hm_Crypt::ciphertext(serialize($this->config), $key);
        file_put_contents($destination, $data);
    }
}

/* db based user configuration */
class Hm_User_Config_DB extends Hm_Config {

    private $site_config = false;
    private $dbh = false;

    public function __construct($config) {
        $this->site_config = $config;
    }

    public function load($username, $key) {
        if ($this->connect()) {
            $sql = $this->dbh->prepare("select * from hm_user_settings where username=?");
            if ($sql->execute(array($username))) {
                $data = $sql->fetch();
                if (!$data || !array_key_exists('settings', $data)) {
                    $sql = $this->dbh->prepare("insert into hm_user_settings values(?,?)");
                    if ($sql->execute(array($username, ''))) {
                        Hm_Debug::add(sprintf("created new row in hm_user_settings for %s", $username));
                        $this->config = array();
                    }
                }
                else {
                    $data = @unserialize(Hm_Crypt::plaintext($data['settings'], $key));
                    if (is_array($data)) {
                        $this->config = array_merge($this->config, $data);
                        $this->set_tz();
                    }
                }
            }
        }
    }

    public function reload($data) {
        $this->config = $data;
        $this->set_tz();
    }

    protected function connect() {
        $this->dbh = Hm_DB::connect($this->site_config);
        if ($this->dbh) {
            return true;
        }
        return false;
    }

    public function save($username, $key) {
        $config = Hm_Crypt::ciphertext(serialize($this->config), $key);
        if ($this->connect()) {
            $sql = $this->dbh->prepare("update hm_user_settings set settings=? where username=?");
            if ($sql->execute(array($config, $username))) {
                Hm_Debug::add(sprintf("Saved user data to DB for %s", $username));
            }
        }
    }
}

/* file based site configuration */
class Hm_Site_Config_File extends Hm_Config {

    public function __construct($source) {
        $this->load($source, false);
    }

    public function load($source, $key) {
        if (is_readable($source)) {
            $data = unserialize(file_get_contents($source));
            if ($data) {
                $this->config = array_merge($this->config, $data);
            }
        }
    }
}

/* handle page processing delegation */
class Hm_Router {

    public $type = false;
    public $sapi = false;
    private $page = 'home';

    public function process_request($config) {
        if (DEBUG_MODE) {
            $filters = array();
            $filters = array('allowed_get' => array(), 'allowed_cookie' => array(), 'allowed_post' => array(), 'allowed_server' => array(), 'allowed_pages' => array());
            $modules = explode(',', $config->get('modules', array()));
            foreach ($modules as $name) {
                if (is_readable(sprintf("modules/%s/setup.php", $name))) {
                    $filters = Hm_Router::merge_filters($filters, require sprintf("modules/%s/setup.php", $name));
                }
            }
            $handler_mods = array();
            $output_mods = array();
        }
        else {
            $filters = $config->get('input_filters', array());
            $handler_mods = $config->get('handler_modules', array());
            $output_mods = $config->get('output_modules', array());
        }

        /* process inbound data */
        $request = new Hm_Request($filters);

        /* check for HTTP TLS */
        $this->check_for_tls($config, $request);

        /* initiate a session class */
        $session = $this->setup_session($config);

        /* determine page or ajax request name */
        $this->get_page($request, $filters['allowed_pages']);

        /* load processing modules for this page */
        $this->load_modules($config, $handler_mods, $output_mods);

        /* run all the handler modules for a page and merge in some standard results */
        $result = $this->merge_response($this->process_page($request, $session, $config), $config, $request, $session);

        /* check for a POST redirect */
        $prior_results = $this->forward_redirect_data($session, $request);

        /* merge post redirect data */
        $result = array_merge($result, $prior_results);

        /* see if we should redirect this request */
        $this->check_for_redirect($request, $session, $result);

        /* return processed data */
        return array($result, $session);
    }

    private function check_for_tls($config, $request) {
        if (!$request->tls && !$config->get('disable_tls', false)) {
            $this->redirect('https://'.$request->server['SERVER_NAME'].$request->server['REQUEST_URI']);
        }
    }

    private function setup_session($config) {
        $session_type = $config->get('session_type', false);
        $auth_type = $config->get('auth_type', false);

        switch ($session_type.$auth_type) {
            case 'DBDB':
                Hm_Debug::add('Using DB auth and DB sessions');
                $session = new Hm_DB_Session_DB_Auth($config);
                break;
            case 'PHPDB':
                Hm_Debug::add('Using DB auth and PHP sessions');
                $session = new Hm_PHP_Session_DB_Auth($config);
                break;
            case 'PHPIMAP':
                Hm_Debug::add('Using IMAP auth and PHP sessions');
                $session = new Hm_PHP_Session_IMAP_Auth($config);
                break;
            case 'PHPPOP3':
                Hm_Debug::add('Using POP3 auth and PHP sessions');
                $session = new Hm_PHP_Session_POP3_Auth($config);
                break;
            case 'DBIMAP':
                Hm_Debug::add('Using IMAP auth and DB sessions');
                $session = new Hm_DB_Session_IMAP_Auth($config);
                break;
            case 'DBPOP3':
                Hm_Debug::add('Using POP3 auth and DB sessions');
                $session = new Hm_DB_Session_POP3_Auth($config);
                break;
            default:
                Hm_Debug::add('Using default PHP sessions with no auth');
                $session = new Hm_PHP_Session($config);
                break;
        }
        return $session;
    }
    private function get_active_mods($mod_list) {
        return array_unique(array_values(array_map(function($v) { return $v[0]; }, $mod_list)));
    }

    private function load_modules($config, $handlers=array(), $output=array()) {

        foreach ($handlers as $page => $modlist) {
            foreach ($modlist as $name => $vals) {
                if ($this->page == $page) {
                    Hm_Handler_Modules::add($page, $name, $vals[1], false, 'after', true, $vals[0]);
                }
            }
        }
        Hm_Handler_Modules::try_queued_modules();

        foreach ($output as $page => $modlist) {
            foreach ($modlist as $name => $vals) {
                if ($this->page == $page) {
                    Hm_Output_Modules::add($page, $name, $vals[1], false, 'after', true, $vals[0]);
                }
            }
        }
        Hm_Output_Modules::try_queued_modules();
        $active_mods = array_unique(array_merge($this->get_active_mods(Hm_Output_Modules::get_for_page($this->page)),
            $this->get_active_mods(Hm_Handler_Modules::get_for_page($this->page))));

        $mods = explode(',', $config->get('modules', '')); 
        foreach ($mods as $name) {
            if (in_array($name, $active_mods) && is_readable(sprintf('modules/%s/modules.php', $name))) {
                require sprintf('modules/%s/modules.php', $name);
            }
        }
    }

    private function forward_redirect_data($session, $request) {
        $res = array();
        if (array_key_exists('hm_msgs', $request->cookie) && trim($request->cookie['hm_msgs'])) {
            $msgs = @unserialize(base64_decode($request->cookie['hm_msgs']));
            if (is_array($msgs)) {
                array_walk($msgs, function($v) { Hm_Msgs::add($v); });
            }
            secure_cookie($request, 'hm_msgs', '', 0);
        }
        return $res;
    }

    private function check_for_redirect($request, $session, $result) {
        if (array_key_exists('no_redirect', $result) && $result['no_redirect']) {
            return;
        }
        if (!empty($request->post) && $request->type == 'HTTP') {
            $msgs = Hm_Msgs::get();
            if (!empty($msgs)) {
                secure_cookie($request, 'hm_msgs', base64_encode(serialize($msgs)), 0);
            }
            $session->end();
            $this->redirect($request->server['REQUEST_URI']);
        }
    }

    private function get_page($request, $pages) {
        if ($request->type == 'AJAX' && array_key_exists('hm_ajax_hook', $request->post) && in_array($request->post['hm_ajax_hook'], $pages)) {
            $this->page = $request->post['hm_ajax_hook'];
        }
        elseif ($request->type == 'AJAX' && array_key_exists('hm_ajax_hook', $request->post) && !in_array($request->post['hm_ajax_hook'], $pages)) {
            die(json_encode(array('status' => 'not callable')));;
        }
        elseif (array_key_exists('page', $request->get) && in_array($request->get['page'], $pages)) {
            $this->page = $request->get['page'];
        }
        elseif (!array_key_exists('page', $request->get)) {
            $this->page = 'home';
        }
        else {
            $this->page = 'notfound';
        }
    }

    private function process_page($request, $session, $config) {
        $response = array();
        $handler = new Hm_Request_Handler();
        $response = $handler->process_request($this->page, $request, $session, $config);
        return $response;
    }

    private function build_nonce_base($session, $config, $request) {
        $result = $session->get('username', false);
        if (array_key_exists('hm_id', $request->cookie)) {
            $result .= $request->cookie['hm_id']; 
        }
        elseif ($config->get('enc_key', false)) {
            $result .= $config->get('enc_key', false);
        }
        return $result;
    }

    private function merge_response($response, $config, $request, $session) {
        return array_merge($response, array(
            'router_page_name'    => $this->page,
            'router_nonce_base'   => $this->build_nonce_base($session, $config, $request),
            'router_request_type' => $request->type,
            'router_sapi_name'    => $request->sapi,
            'router_format_name'  => $request->format,
            'router_login_state'  => $session->active,
            'router_url_path'     => $request->path,
            'router_module_list'  => $config->get('modules', '')
        ));
    }

    public function redirect($url) {
        header('HTTP/1.1 303 Found');
        header('Location: '.$url);
        exit;
    }

    static public function merge_filters($existing, $new) {
        foreach (array('allowed_get', 'allowed_cookie', 'allowed_post', 'allowed_server', 'allowed_pages') as $v) {
            if (array_key_exists($v, $new)) {
                if ($v == 'allowed_pages') {
                    $existing[$v] = array_merge($existing[$v], $new[$v]);
                }
                else {
                    $existing[$v] += $new[$v];
                }
            }
        }
        return $existing;
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
    public $tls = false;
    public $mobile = false;
    public $path = '';

    public function __construct($filters) {
        $this->sapi = php_sapi_name();

        $this->server = $this->filter_input(INPUT_SERVER, $filters['allowed_server']);
        $this->post = $this->filter_input(INPUT_POST, $filters['allowed_post']);
        $this->get = $this->filter_input(INPUT_GET, $filters['allowed_get']);
        $this->cookie = $this->filter_input(INPUT_COOKIE, $filters['allowed_cookie']);

        $this->path = $this->get_clean_url_path($this->server['REQUEST_URI']);
        $this->get_request_type();
        $this->is_tls();
        $this->is_mobile();

        unset($_POST);
        unset($_SERVER);
        unset($_GET);
        unset($_COOKIE);
    }

    private function filter_input($type, $filters) {
        $data = filter_input_array($type, $filters, false);
        if (!$data) {
            return array();
        }
        return $data;
    }

    private function is_mobile() {
        if (array_key_exists('HTTP_USER_AGENT', $this->server)) {
            if (preg_match("/(iphone|ipod|ipad|android|blackberry|webos)/i", $this->server['HTTP_USER_AGENT'])) {
                $this->mobile = true;
            }
        }
    }

    private function is_tls() {
        if (array_key_exists('HTTPS', $this->server) && strtolower($this->server['HTTPS']) == 'on') {
            $this->tls = true;
        }
        elseif (array_key_exists('REQUEST_SCHEME', $this->server) && strtolower($this->server['REQUEST_SCHEME']) == 'https') {
            $this->tls = true;
        }
    }

    private function get_request_type() {
        if ($this->is_cli()) {
            die("CLI support not implemented\n");
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
        return array_key_exists('HTTP_X_REQUESTED_WITH', $this->server) && strtolower($this->server['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    }

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

/* data handler module runner */
class Hm_Request_Handler {

    public $page = false;
    public $request = false;
    public $session = false;
    public $config = false;
    public $user_config = false;
    public $response = array();
    private $modules = array();

    public function process_request($page, $request, $session, $config) {
        $this->page = $page;
        $this->request = $request;
        $this->session = $session;
        $this->config = $config;
        $this->modules = Hm_Handler_Modules::get_for_page($page);
        $this->load_user_config_object();
        $this->run_modules();
        $this->default_language();
        return $this->response;
    }

    private function load_user_config_object() {
        $type = $this->config->get('user_config_type', 'file');
        switch ($type) {
            case 'DB':
                $this->user_config = new Hm_User_Config_DB($this->config);
                Hm_Debug::add("Using DB user configuration");
                break;
            case 'file':
            default:
                $this->user_config = new Hm_User_Config_File($this->config);
                Hm_Debug::add("Using file based user configuration");
                break;
        }
    }

    private function default_language() {
        if (!array_key_exists('language', $this->response)) {
            $default_lang = $this->config->get('default_language', false);
            if ($default_lang) {
                $this->response['language'] = $default_lang;
            }
        }
    }

    protected function run_modules() {
        foreach ($this->modules as $name => $args) {
            $input = false;
            $name = "Hm_Handler_$name";
            if (class_exists($name)) {
                if (!$args[1] || ($args[1] && $this->session->active)) {
                    $mod = new $name( $this, $args[1]);
                    $input = $mod->process($this->response);
                }
            }
            else {
                Hm_Debug::add(sprintf('Handler module %s activated but not found', $name));
            }
            if ($input) {
                $this->response = $input;
            }
        }
    }
}

/* base class for output formatting */
abstract class HM_Format {

    protected $modules = false;

    abstract protected function content($input, $lang_str);

    public function format_content($input) {
        $lang_strings = array();
        if (array_key_exists('language', $input)) {
            $lang_strings = $this->get_language($input['language']);
        }
        $this->modules = Hm_Output_Modules::get_for_page($input['router_page_name']);
        $formatted = $this->content($input, $lang_strings);
        return $formatted;
    }

    private function get_language($lang) {
        $strings = array();
        if (file_exists('language/'.$lang.'.php')) {
            $strings = require 'language/'.$lang.'.php';
        }
        return $strings;
    }
    protected function run_modules($input, $format, $lang_str) {
        $mod_output = array();

        foreach ($this->modules as $name => $args) {
            $name = "Hm_Output_$name";
            if (class_exists($name)) {
                if (!$args[1] || ($args[1] && $input['router_login_state'])) {
                    $mod = new $name($input);
                    if ($format == 'JSON') {
                        $mod_output = $mod->output_content($input, $format, $lang_str);
                        if ($mod_output) {
                            $input = $mod_output;
                        }
                    }
                    else {
                        $mod_output[] = $mod->output_content($input, $format, $lang_str);
                    }
                }
            }
            else {
                Hm_Debug::add(sprintf('Output module %s activated but not found', $name));
            }
        }
        if (empty($mod_output)) {
            return $input;
        }
        return $mod_output;
    }
}

/* JSON output format */
class Hm_Format_JSON extends HM_Format {

    public function content($input, $lang_str) {
        $input['router_user_msgs'] = Hm_Msgs::get();
        $output = $this->run_modules($input, 'JSON', $lang_str);
        if (array_key_exists('internal_users', $output)) {
            unset($output['internal_users']);
        }
        return json_encode($output, JSON_FORCE_OBJECT);
    }
}

/* HTML5 output format */
class Hm_Format_HTML5 extends HM_Format {

    public function content($input, $lang_str) {
        $output = $this->run_modules($input, 'HTML5', $lang_str);
        return implode('', $output);
    }
}

/* base output class */
abstract class Hm_Output {

    abstract protected function output_content($content);

    public function send_response($response, $input=array()) {
        if (array_key_exists('http_headers', $input)) {
            $this->output_content($response, $input['http_headers']);
        }
        else {
            $this->output_content($response);
        }
    }
}

/* HTTP output class */
class Hm_Output_HTTP extends Hm_Output {

    protected function output_headers($headers) {
        foreach ($headers as $header) {
            header($header);
        }
    }

    protected function output_content($content, $headers=array()) {
        $this->output_headers($headers);
        ob_end_clean();
        echo $content;
    }
}

/* STDOUT output class */
class Hm_Output_STDOUT extends Hm_Output {

    protected function output_content($content) {
        $stdout = fopen('php://stdout', 'w');
        fwrite($stdout, $content);
        fclose($stdout);
    }
}

/* file output class */
class Hm_Output_File extends Hm_Output {

    public $filename = 'test.out';

    protected function output_content($content) {
        $fh = fopen($this->filename, 'a');
        fwrite($fh, $content);
        fclose($fh);
    }
}

/* output sanitizing */
trait Hm_Sanitize {

    public function html_safe($string) {
        return htmlspecialchars($string, ENT_QUOTES | ENT_HTML5, 'UTF-8');
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

    public static function show($type='print') {
        if ($type == 'log') {
            error_log(str_replace(array("\n", "\t", "  "), array(' '), print_r(self::$msgs, true)));
        }
        elseif ($type == 'return') {
            return print_r(self::$msgs, true);
        }
        else {
            print_r(self::$msgs);
        }
    }
}
class Hm_Msgs { use Hm_List; }
class Hm_Debug { use Hm_List;

    public static function load_page_stats() {
        self::add(sprintf("PHP version %s", phpversion()));
        self::add(sprintf("Zend version %s", zend_version()));
        self::add(sprintf("Peak Memory: %d", (memory_get_peak_usage(true)/1024)));
        self::add(sprintf("PID: %d", getmypid()));
        self::add(sprintf("Included files: %d", count(get_included_files())));
    }
}

/* base handler module */
abstract class Hm_Handler_Module {

    protected $session = false;
    protected $request = false;
    protected $config = false;
    protected $page = false;
    protected $user_data = false;

    public function __construct($parent, $logged_in) {
        $this->session = $parent->session;
        $this->request = $parent->request;
        $this->config = $parent->config;
        $this->user_config = $parent->user_config;
        $this->page = $parent->page;
    }

    protected function process_form($form, $nonce=false) {
        $post = $this->request->post;
        $success = false;
        $new_form = array();
        foreach($form as $name) {
            if (array_key_exists($name, $post) && (trim($post[$name]) || (($post[$name] === '0' ||  $post[$name] === 0 )))) {
                $new_form[$name] = $post[$name];
            }
        }
        if (count($form) == count($new_form)) {
            $success = true;
        }
        if ($nonce && $success) {
            $success = false;
            if (array_key_exists('hm_nonce', $post)) {
                $key = $this->session->get('username', false);
                if (array_key_exists('hm_id', $this->request->cookie)) {
                    $key .= $this->request->cookie['hm_id'];
                }
                elseif ($this->config->get('enc_key', false)) {
                    $key .= $this->config->get('enc_key', false);
                }
                if (hash_hmac('sha256', $nonce, $key) == $post['hm_nonce']) {
                    $success = true;
                }
            }
            else {
                $success = false;
            }
        }
        return array($success, $new_form);
    }

    abstract public function process($data);
}

/* base output module */
abstract class Hm_Output_Module {

    use Hm_Sanitize;

    protected $lstr = array();
    protected $lang = false;
    protected $nonce_base = false;

    function __construct($input) {
        $this->nonce_base = $input['router_nonce_base'];
    }

    abstract protected function output($input, $format);

    protected function build_nonce($name) {
        return hash_hmac('sha256', $name, $this->nonce_base);
    }
    public function trans($string) {
        if (array_key_exists($string, $this->lstr)) {
            if ($this->lstr[$string] === false) {
                return $string;
            }
            else {
                return $this->lstr[$string];
            }
        }
        else {
            Hm_Debug::add(sprintf('No translation found: %s', $string));
        }
        return $string;
    }

    public function output_content($input, $format, $lang_str) {
        $this->lstr = $lang_str;
        if (array_key_exists('interface_lang', $lang_str)) {
            $this->lang = $lang_str['interface_lang'];
        }
        return $this->output($input, $format);
    }
}

/* module managers */
trait Hm_Modules {

    private static $module_list = array();
    private static $source = false;
    private static $module_queue = array();

    public static function load($mod_list) {
        self::$module_list = $mod_list;
    }
    public static function set_source($source) {
        self::$source = $source;
    }
    public static function add($page, $module, $logged_in, $marker=false, $placement='after', $queue=true, $source=false) {
        $inserted = false;
        if (!array_key_exists($page, self::$module_list)) {
            self::$module_list[$page] = array();
        }
        if (array_key_exists($page, self::$module_list) && array_key_exists($module, self::$module_list[$page])) {
            Hm_Debug::add(sprintf("Already registered module re-attempted: %s", $module));
            return;
        }
        if (!$source) {
            $source = self::$source;
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
                    array($module => array($source, $logged_in)),
                    array_slice($list, $index));
                $inserted = true;
            }
        }
        else {
            $inserted = true;
            self::$module_list[$page][$module] = array($source, $logged_in);
        }
        if (!$inserted) {
            if ($queue) {
                Hm_Debug::add(sprintf('queueing module %s', $module));
                self::$module_queue[] = array($page, $module, $logged_in, $marker, $placement);
            }
            else {
                Hm_Debug::add(sprintf('failed to insert module %s on %s', $module, $page));
            }
        }
    }

    public static function replace($target, $replacement, $page=false) {
        if ($page && array_key_exists($page, self::$module_list) && array_key_exists($target, self::$module_list[$page])) {
            self::$module_list[$page] = self::swap_key($target, $replacement, self::$module_list[$page]);
        }
        else {
            foreach (self::$module_list as $page => $modules) {
                if (array_key_exists($target, $modules)) {
                    self::$module_list[$page] = self::swap_key($target, $replacement, self::$module_list[$page]);
                }
            }
        }
    }

    public static function swap_key($target, $replacement, $modules) {
        $keys = array_keys($modules);
        $values = array_values($modules);
        $size = count($modules);
        for ($i = 0; $i < $size; $i++) {
            if ($keys[$i] == $target) {
                $keys[$i] = $replacement;
                $values[$i][0] = self::$source;
                break;
            }
        }
        return array_combine($keys, $values);
    }

    public static function try_queued_modules() {
        foreach (self::$module_queue as $vals) {
            self::add($vals[0], $vals[1], $vals[2], $vals[3], $vals[4], false);
        }
    }

    public static function del($page, $module) {
        if (array_key_exists($page, self::$module_list) && array_key_exists($module, self::$module_list[$page])) {
            unset(self::$module_list[$page][$module]);
        }
    }

    public static function get_for_page($page) {
        $res = array();
        if (array_key_exists($page, self::$module_list)) {
            $res = array_merge($res, self::$module_list[$page]);
        }
        return $res;
    }

    public static function dump() {
        return self::$module_list;
    }
}

class Hm_Handler_Modules { use Hm_Modules; }
class Hm_Output_Modules { use Hm_Modules; }

class Hm_Crypt {

    static private $mode = BLOCK_MODE;
    static private $cipher = CIPHER;
    static private $r_source = RAND_SOURCE;

    public static function plaintext($string, $key) {
        if (!MCRYPT_DATA) {
            return $string;
        }
        $key = substr(md5($key), 0, mcrypt_get_key_size(self::$cipher, self::$mode));
        $string = base64_decode($string);
        $iv_size = self::iv_size();
        $iv_dec = substr($string, 0, $iv_size);
        $string = substr($string, $iv_size);
        return mcrypt_decrypt(self::$cipher, $key, $string, self::$mode, $iv_dec);
    }

    public static function ciphertext($string, $key) {
        if (!MCRYPT_DATA) {
            return $string;
        }
        $key = substr(md5($key), 0, mcrypt_get_key_size(self::$cipher, self::$mode));
        $iv_size = self::iv_size();
        $iv = mcrypt_create_iv($iv_size, self::$r_source);
        return base64_encode($iv.mcrypt_encrypt(self::$cipher, $key, $string, self::$mode, $iv));
    }

    public static function iv_size() {
        return mcrypt_get_iv_size(self::$cipher, self::$mode);
    }
}

class Hm_DB {

    static public $dbh = array();
    static private $required_config = array('db_user', 'db_pass', 'db_name', 'db_host', 'db_driver');
    static private $config;

    static private function parse_config($site_config) {
        self::$config = array(
            'db_driver' => $site_config->get('db_driver', false),
            'db_host' => $site_config->get('db_host', false),
            'db_name' => $site_config->get('db_name', false),
            'db_user' => $site_config->get('db_user', false),
            'db_pass' => $site_config->get('db_pass', false),
        );
        foreach (self::$required_config as $v) {
            if (!self::$config[$v]) {
                Hm_Debug('Missing configuration setting for %s', $v);
            }
        }
    }
    static private function db_key() {
        return md5(self::$config['db_driver'].
            self::$config['db_host'].
            self::$config['db_name'].
            self::$config['db_user'].
            self::$config['db_pass']
        );
    }

    static public function connect($site_config) {

        self::parse_config($site_config);
        $key = self::db_key();

        if (array_key_exists($key, self::$dbh) && self::$dbh[$key]) {
            return self::$dbh[$key];
        }
        $dsn = sprintf('%s:host=%s;dbname=%s', self::$config['db_driver'], self::$config['db_host'], self::$config['db_name']);
        try {
            self::$dbh[$key] = new PDO($dsn, self::$config['db_user'], self::$config['db_pass']);
            Hm_Debug::add(sprintf('Connecting to dsn: %s', $dsn));
            return self::$dbh[$key];
        }
        catch (Exception $oops) {
            Hm_Debug::add($oops->getMessage());
            Hm_Msgs::add("An error occurred communicating with the database");
            self::$dbh[$key] = false;
            return false;
        }
    }
}

trait Hm_Server_List {

    private static $server_list = array();

    public static function connect($id, $cache=false, $user=false, $pass=false, $save_credentials=false) {
        if (array_key_exists($id, self::$server_list)) {
            $server = self::$server_list[$id];
            if ($server['object']) {
                return $server['object'];
            }
            else {
                if ((!$user || !$pass) && (!array_key_exists('user', $server) || !array_key_exists('pass', $server))) {
                    return false;
                }
                elseif (array_key_exists('user', $server) && array_key_exists('pass', $server)) {
                    $user = $server['user'];
                    $pass = $server['pass'];
                }
                if ($user && $pass) {
                    $res = self::service_connect($id, $server, $user, $pass, $cache);
                    if ($res) {
                        self::$server_list[$id]['connected'] = true;
                        if ($save_credentials) {
                            self::$server_list[$id]['user'] = $user;
                            self::$server_list[$id]['pass'] = $pass;
                        }
                    }
                    return self::$server_list[$id]['object'];
                }
            }
        }
        return false;
    }

    public static function reuse($id) {
        if (array_key_exists($id, self::$server_list) && is_object(self::$server_list[$id]['object'])) {
            return self::$server_list[$id]['object'];
        }
        return false;
    }

    public static function forget_credentials($id) {
        if (array_key_exists($id, self::$server_list)) {
            unset(self::$server_list[$id]['user']);
            unset(self::$server_list[$id]['pass']);
        }
    }

    public static function add($atts, $id=false) {
        $atts['object'] = false;
        $atts['connected'] = false;
        if ($id) {
            self::$server_list[$id] = $atts;
        }
        else {
            self::$server_list[] = $atts;
        }
    }

    public static function del($id) {
        if (array_key_exists($id, self::$server_list)) {
            unset(self::$server_list[$id]);
            return true;
        }
        return false;
    }

    public static function dump($id=false, $full=false) {
        $list = array();
        foreach (self::$server_list as $index => $server) {
            if ($id !== false && $index != $id) {
                continue;
            }
            if ($full) {
                $list[$index] = $server;
            }
            else {
                $list[$index] = array(
                    'name' => $server['name'],
                    'server' => $server['server'],
                    'port' => $server['port'],
                    'tls' => $server['tls']
                );
                if (array_key_exists('user', $server)) {
                    $list[$index]['user'] = $server['user'];
                }
            }
            if ($id !== false) {
                return $list[$index];
            }
        }
        return $list;
    }

    public static function clean_up($id=false) {
        foreach (self::$server_list as $index => $server) {
            if ($id !== false && $id != $index) {
                continue;
            }
            if ($server['connected'] && $server['object']) {
                if (method_exists(self::$server_list[$index]['object'], 'disconnect')) {
                    self::$server_list[$index]['object']->disconnect();
                }
                self::$server_list[$index]['connected'] = false;
            }
        }
    }
}

class Hm_Page_Cache {

    private static $pages = array();

    public static function add($key, $page, $save=false) {
        self::$pages[$key] = array($page, $save);
    }
    public static function concat($key, $page, $save = false, $delim=false) {
        if (array_key_exists($key, self::$pages)) {
            if ($delim) {
                self::$pages[$key][0] .= $delim.$page;
            }
            else {
                self::$pages[$key][0] .= $page;
            }
        }
        else {
            self::$pages[$key] = array($page, $save);
        }
    }
    public static function del($key) {
        if (array_key_exists($key, self::$pages)) {
            unset(self::$pages[$key]);
            return true;
        }
        return false;
    }
    public static function get($key) {
        if (array_key_exists($key, self::$pages)) {
            Hm_Debug::add(sprintf("PAGE CACHE: %s", $key));
            return self::$pages[$key][0];
        }
        return false;
    }
    public static function dump() {
        return self::$pages;
    }
    public static function flush($session) {
        self::$pages = array();
        $session->set('page_cache', array());
        $session->set('saved_pages', array());
    }
    public static function load($session) {
        self::$pages = $session->get('page_cache', array());
        self::$pages = array_merge(self::$pages, $session->get('saved_pages', array()));
    }
    public static function save($session) {
        $pages = self::$pages;
        $saved_pages = array();
        foreach (self::$pages as $key => $page) {
            if ($page[1]) {
                $saved_pages[$key] = $pages[$key];
                unset($pages[$key]);
            }
        }
        $session->set('page_cache', $pages);
        $session->set('saved_pages', $saved_pages);
    }
}

trait Hm_Uid_Cache {

    private static $uids;
    
    public static function load($uid_array) {
        if (!empty($uid_array)) {
            self::$uids = array_combine($uid_array, array_fill(0, count($uid_array), 0));
        }
        else {
            self::$uids = array();
        }
    }
    public static function is_present($uid) {
        return array_key_exists($uid, self::$uids);
    }
    public static function dump() {
        return array_keys(self::$uids);
    }
    public static function add($uid) {
        self::$uids[$uid] = 0;
    }
    public static function remove($uid) {
        if (array_key_exists($uid, self::$uids)) {
            unset(self::$uids[$uid]);
            return true;
        }
        return false;
    }
}

class Hm_POP3_Seen_Cache {
    use Hm_Uid_Cache;
}
class Hm_Feed_Seen_Cache {
    use Hm_Uid_Cache;
}

class Hm_Image_Sources {
    public static $power = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABAAAAAQCAYAAAAf8/9hAAAABHNCSVQICAgIfAhkiAAAAAlwSFlzAAALEwAACxMBAJqcGAAAAOlJREFUOI2VkjFuwkAQRV+sNKZEKJh7UCEBl4BwhEhpQpSSFu5CgyjgIECiSIhLBBpoAhT+lker9dqMNPLsnz9f650PxXFTBiMqIzwq8BzghnoAvAI/QF1n+wsN4BcYFg0PgasG3jwCY9X/wMAdbgJHEd4N7j7iROcjkFiBqRpzR9i3hZWwmQV3AjsVBPrCvi14ERhTHrG4lwywayw1DfDkciPgoLpdQSDj7K3AWvVHBYFPfdcWbAEnXSsk8kW+xqbbHJEbaQF0SR+sBvSApXpXAm4cmZv48g+PC91ISE2yBc7KDanZXnwDd6ZsRKAfKovZAAAAAElFTkSuQmCC';
    public static $home = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABAAAAAQCAYAAAAf8/9hAAAABHNCSVQICAgIfAhkiAAAAAlwSFlzAAALEwAACxMBAJqcGAAAAHFJREFUOI3FkMsNgCAQBQfjCUvRlkyMlWlR2IMFcMYLJARxV+OBSebA520ewDMW2KJWuFdlBBwQoi7uvWIBfBZOemCVgqlyGSzdgUGrrHkAk1ZZ8wRmk7UI0vsqGIDuY+hG+wG9cGaKdfWP2j/h94D2XMGCMeGhOf42AAAAAElFTkSuQmCC';
    public static $box = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABAAAAAQCAYAAAAf8/9hAAAABHNCSVQICAgIfAhkiAAAAAlwSFlzAAALEwAACxMBAJqcGAAAADtJREFUOI1jZGBg+M9AAWCiRPPgAf9xYHTwAZs6isOAEYdtDAwMDF/R+NykGkAUoNgLowYMFgMoSQf/AFJDEffBNhqvAAAAAElFTkSuQmCC';
    public static $env_closed = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABAAAAAQCAYAAAAf8/9hAAAABHNCSVQICAgIfAhkiAAAAAlwSFlzAAALEwAACxMBAJqcGAAAAFVJREFUOI3NkkEKACEMA2d92fpzfVn3oHhYqAF7qIFeSpImUMjGA1jEoEQTFKAC/UDbp3bhBRqj0m7a5C78F56Rx5MEdUBHFMlkV09ogN3xB7kG+fgA0tc160Jy09wAAAAASUVORK5CYII=';
    public static $star = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABAAAAAQCAYAAAAf8/9hAAAABHNCSVQICAgIfAhkiAAAAAlwSFlzAAALEwAACxMBAJqcGAAAAKBJREFUOI2dk7ENgDAMBE8U7MAKMAEdS7ACa8AaLMQGrEBKaipoHMmExAReegkl/ij2BbDVALVVULwc0It/axX/UgOc4mQbVgt94jtbq7qB2cYAHKo414dkAWiB7UN4k8xNFbBkhBepjaoEZiM8S40pjS/0A2cMo4UsC6fH56esKb2+Sn/9cMqakvlzTaSn7CmNejGcwQ50gIsc4GRv14sXF4BOBP5lBBYAAAAASUVORK5CYII=';
    public static $globe = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABAAAAAQCAYAAAAf8/9hAAAABHNCSVQICAgIfAhkiAAAAAlwSFlzAAALEwAACxMBAJqcGAAAASNJREFUOI1907FKQ0EQBdBjldhaa28TgmAldnb+gP/gF+gHKAiCYCIGFdv0lhYiYmtriEUsFKysTRASi901j3WfAwPv7d57d2fuLH9jDT28YBJziHO0C/jfWMQVZv/kNIo3S+SHCugU62jEveNM6D4XyU8+zA64icJVzHm15rT4GcmlWvu4xo7Ql2nC9SL5FQfF7rCAES5jaf3IOSN0e4bbGnISeIq4d4zj90C8zgzfWKkR2Fd2ZVwVWMVWgbwUxWsFhvFnO141jwY+agSeCXakhQmOCiLL2MMFHiv4DsGK3OPdml7AhvnAtdJisjLlqIbcwlvEdKsbTWE8k8AXNoUxbgren5jbdyf0Ri7SK5STP6ZuiVyNtjBhg3jiWOh2p1pzih9Ox36Q1K2kawAAAABJRU5ErkJggg==';
    public static $doc = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABAAAAAQCAYAAAAf8/9hAAAABHNCSVQICAgIfAhkiAAAAAlwSFlzAAALEwAACxMBAJqcGAAAAFpJREFUOI3dkEEKACAIBLfo4f28ToJWm0adGghCdFwEgEaeUOHgCZoniQi2kqiAStgAWzBJTgVGkoZmTSJ1Q4407dAJZCNLtJq9T1DUP7rZ8DSBd/Vlwg9u0AEYijfoDYVTdgAAAABJRU5ErkJggg==';
    public static $monitor = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABAAAAAQCAYAAAAf8/9hAAAABHNCSVQICAgIfAhkiAAAAAlwSFlzAAALEwAACxMBAJqcGAAAAGBJREFUOI1jYGBguMTAwPCfTHyCEcogG7AgsRlJ1PufgYGBgYkS20cNGCwGUJyQmBgYGE5RoP8YvtSH7jKsaqkaiEEMDAyvGRA5DZuL/jMwMLxiYGDwx2YYsmZC+CVMEwCAuidyZ/rWAAAAAABJRU5ErkJggg==';
    public static $cog = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABAAAAAQCAYAAAAf8/9hAAAABHNCSVQICAgIfAhkiAAAAAlwSFlzAAALEwAACxMBAJqcGAAAAONJREFUOI2Vk00OAUEQhT+zkiC4gz2CcRpxAJO4gMRJLHAIq7Hxk7iQzGywmG5502qESiqpqX71+lVNNVRbDDydD7/g3tYC+vK9E4KN5AdAwyq+OvAZWAGZENxdzmNSoK4EYwH/4g+gF6q4/UFw9EWREBwkzoEE6AJtF+dynurNMbB3AH/DIpTnSPx5RjHkmAqJHYOgbWEjAwjFkEKrWcAImEoL3mYGdi5xri14W1PuMXGyO8CS8oxWlpoLv//Gc1g8+qPYu659aZVPfK5y5nIXwTRDFd8e01byA6vYMn3OkyrQC5Q1dBrXQiO8AAAAAElFTkSuQmCC';
    public static $people = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABAAAAAQCAYAAAAf8/9hAAAABHNCSVQICAgIfAhkiAAAAAlwSFlzAAALEwAACxMBAJqcGAAAAN1JREFUOI2d0E0uBVEQxfHfk4dBD5lZgYFNmAgDQqyCeAkLYBfW8WIHxEfELsx8znzkGTCpRl/VyY2T3Nx0nar/6brUaQ9PeMSocuZby/gszmZf836k3GE3ag1uC8B5NrydJG2Ed1DUXzPAZQI4DW+xBvCeAF7CmynqZxngLQG0SbM9q3V0kQDapAU8497P4/7RVgJY7+mdxhHmSmOEh0jaSQYbrOEmAsYwj0MMK5KOiz+cwEl8XGElUhqs4vp3EpaSNX0kxfJMAjAsvUFLqdAg7k5/tnctCEz9A9DRFyWUZi0vm1MaAAAAAElFTkSuQmCC';
    public static $caret = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAgAAAAICAYAAADED76LAAAABHNCSVQICAgIfAhkiAAAAAlwSFlzAAALEwAACxMBAJqcGAAAACRJREFUGJVjYECABgYC4D8hRf8JKfqPTRETIXtJtgKnJAMuSQC7gAx6LfypjQAAAABJRU5ErkJggg==';
    public static $folder = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABAAAAAQCAYAAAAf8/9hAAAABHNCSVQICAgIfAhkiAAAAAlwSFlzAAALEwAACxMBAJqcGAAAADBJREFUOI1jZGBg+M+AHTDiEEcBTMQowgcY8biAKECxCwYeDHwYjBowbAw4SYH+YwB6YwSnsuTkoAAAAABJRU5ErkJggg==';
    public static $chevron = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAgAAAAICAYAAADED76LAAAABHNCSVQICAgIfAhkiAAAAAlwSFlzAAALEwAACxMBAJqcGAAAADtJREFUGJVjYCACKDAwMByA0ljFDjAwMPxnYGB4ABVQgLL/Q+VQBB6gseGmIivCkERXhFUSWRFOSawAAEl7E3uv1iMcAAAAAElFTkSuQmCC';
    public static $check = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAgAAAAICAYAAADED76LAAAABHNCSVQICAgIfAhkiAAAAAlwSFlzAAALEwAACxMBAJqcGAAAAFFJREFUGJWdzDsOQEAABNCXiE8vkj2CTuPuJApOgVs4As1KxKcx5bzJ8DMtJlTJBw6okUKDESHihh09chF3rDcszsuAJcIDr6MZ3RueKZHdywPRLxDHyg6J8AAAAABJRU5ErkJggg==';
    public static $refresh = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABgAAAAYCAYAAADgdz34AAAABHNCSVQICAgIfAhkiAAAAAlwSFlzAAALEwAACxMBAJqcGAAAAXtJREFUSIm11b9KHFEUBvBf7BK0VDD4r5E0glhIQDsLJQ/gJiAm+AQSKzsR8SFMG0RLQVA0JPgKgiZPoIVrZbp1/VPMLHN3dnZmFnY/ODDn3nu+795zz9xDPiawiXPcoIZ6/H2KDQznxG+3mxjDAZ7wUmA17GEwxbETz7fgC/7nED63Ga9iKebYDcab8D2D7AgVjKIvtnGs4iK1vo7j1FjTzsOJS8xkHTGFT7jNOTGinIdpOcO7EuQNvMd9nsBBauedkJNcaKbAhKRanpVLS1nyF6I6bzhHHZJviS42z5wHApUOBUrhJhAY7YVALRDo6zZ51wmzBKqBP9ILgevAn++FwO/AX+m2ANHDVZf8aLO9ENmXVNI1+rstMIKHQOQPBjqIH8C3okUVzc3kH+ZKkH/E3zimbYtsYF1rx/qFNXwQvbJvMYmvOEmtfcR0kciy5nSVtSoWS5wY0Z38lFRXUdP/obXpgzcFQmP4jAVMYSiOucOV6CU+FLXMTLwC6Xmw7eTc8o8AAAAASUVORK5CYII=';
    public static $big_cog = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABgAAAAYCAYAAADgdz34AAAABHNCSVQICAgIfAhkiAAAAAlwSFlzAAALEwAACxMBAJqcGAAAAXVJREFUSImtlsEyA0EQhj/hAXgAHORIOEqJgyqiXFURJVUeAHcOXkCpxGu4OgjXRPACDryD2ji62HWYmarJVPfuRPJX9WF7/v57Zrt7ZyEeHSCzdj9CXBSWgNRL8AssxgSWBF8TWAl8Z8BUEHcacFaBo6KEV94ue8AhUAUGnt/ZF7BuRfue/1wTPxFE/mMpsOMf1eG96HiRyIBPbfFtAifo+ILTQYIfYF9J3gfugBf7vKDwLoAP7QSbwo4SYFvg1pGLX5WEy8CtEiCJO+wK/AHQxswOYN5ZKhAz4DlH3OFViU2tdm7BriMS3ORpSJM8UZSAR5tNwkaERk3xD7VsGVMYqcj1HPE9gZ8ALbwi+9gSAgaYbpHEvwX+UJvOBEFzgtAs8ISZcjdkNcyHTsK85YroCjsa1Xqa+NoExJ1VnKjfpuElMw6WtQX/wukCB+gXTmLXGpiJd/7LouxNYQdtIUEr4FSA4yJxDdKlL/b5OPB/Wx5ig/4AeR3UqLNaCmAAAAAASUVORK5CYII=';
    public static $big_caret = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABAAAAAQCAYAAAAf8/9hAAAABHNCSVQICAgIfAhkiAAAAAlwSFlzAAALEwAACxMBAJqcGAAAAEtJREFUOI2lzkEKACAMA8Hgy/15PAnSRjCmx8JOC/SZYmcNU4QpQoQIESIVsBEFWMgNkMhwXks/aNcd4DlWgBVXwI5P4CvewHcMN15ViDhSMdkjzgAAAABJRU5ErkJggg==';
    public static $big_caret_left = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABAAAAAQCAYAAAAf8/9hAAAABHNCSVQICAgIfAhkiAAAAAlwSFlzAAALEwAACxMBAJqcGAAAAElJREFUOI2ljkEKADAIw2Qv9+fbaSB1E2tzFBNqxuHkf5K3Ko8CUaYDKFOBl9wO/ORWoJJTYHUnsVQr5Ii8hAYjI2JkzI1IOB4Oxd84UoiIlQMAAAAASUVORK5CYII=';
    public static $search = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABgAAAAYCAYAAADgdz34AAAABHNCSVQICAgIfAhkiAAAAAlwSFlzAAALEwAACxMBAJqcGAAAAYZJREFUSImtlLtOAkEUhj/UzpAYgom1uI1Kgmh8AyUx8VLZ+EYmYm8jwiuIsbCwokAsBY2FT2AMYAHCqsXOhrOT3WV2wkkmO5P/nP+by85AdBSBMtACuoCrvi3gQulW4QD3wJ9BuwPWkpifAH1Dc7/1gWNT87EoHANV4ABYARaADLAPVICRlnsUZ+5oM38DNqdMKA+0RU0PyEUlyz1/VTM1iQzQEbX1sKQiwaVuGJr7kSe4XQU9oSzESkJzP6rC41wXW0IsWQJKwqOpi10hZi0By8LjSxddJfwCKUvAvAC4UpgDvlU/BaQtAUui39MB72K8YwnYFv2ODngU4zNLwKnoP+jiFpP9+wHWE5o7wJDJOTphSXUBecH8JqcJ/uYfRJxjDu9w/MQ209+iHPAkavzWiIIcEnxNR8AN3iXK4p1XBtgDroBBiLkRpBdTmKRFQlaBW0OTzyl6LQzgRwHv4WriXX9Xre4ZuAR21QwbMYBhHMA04iCDWQDiINezAviQGt62DJT54j8ekcXOOaAJlgAAAABJRU5ErkJggg==';
    public static $info = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABAAAAAQCAYAAAAf8/9hAAAABHNCSVQICAgIfAhkiAAAAAlwSFlzAAALEwAACxMBAJqcGAAAAKtJREFUOI2l0k0OAUEQBeAvIsSSC1g5gI3gDiJxMpKJcAMXcCc/i2Frw0KJCYaZ8ZJKv3TVe13dXfzGDEccMC1Q/4YjrhH712StgME1hxfGNE7eYVLFoDSaSJDijDkaZQwSz0d7xLyMQRqiAYbB07zi+oe9dob3Y72U6SCLbXSwqCIehfiCbhWDTRisq4jhFAa9b0XfRvmR62Dsx298wsqf89DC0v0qqZyJvAEWoivRGHfiuQAAAABJRU5ErkJggg==';
    public static $bug = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABAAAAAQCAYAAAAf8/9hAAAABHNCSVQICAgIfAhkiAAAAAlwSFlzAAALEwAACxMBAJqcGAAAAPpJREFUOI2Vkr9rwlAQxz/RKHXwDyid3Nu5f4K7QwYFESw4OXUt9M8qlNbVTg4qGCh0c7ODQ6HQahx6Lz4v90I9OMjLfX9dXqBYEfAIfAIb4MHAlNY9kKkenyOwNgTeywiR91wF9obAV4hcARbSiZCfDdyTYLtACkz94dRzWkpcnSAFVt75VadIgLlB1D0DOmrtvEb/EBhYxI8A+Bq4DczeHDkGfg3RnXyP2HKU+UlFIuQcnGisnL81scLf3fcDSSznnvDySinuGErgX3fTJbiUF3cc/7jMcN8CQzG8Aupu0OB4rx3gB3jxiBMRbntrX5TteaMANaAVAh8AR6Jp1Y4VfDEAAAAASUVORK5CYII=';
    public static $code = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABAAAAAQCAYAAAAf8/9hAAAABHNCSVQICAgIfAhkiAAAAAlwSFlzAAALEwAACxMBAJqcGAAAAFVJREFUOI3VjjESwCAIBHf8mD5dX6aNRQYhelbJlRzsAn9JATpQbwF1AsqNwXYu7M1gu0Wm2F2oYl/AyTl6LmWgBd9pb+46O4igYWeHkv3IoGRr+FYGqUEz6slPFcwAAAAASUVORK5CYII=';
    public static $loading = 'data:image/gif;base64,R0lGODlhQwAKAOMAAMzOzOzu7Nze3NTW1Pz6/OTm5NTS1PT29OTi5Nza3Pz+/P///wAAAAAAAAAAAAAAACH/C05FVFNDQVBFMi4wAwEAAAAh+QQJCQALACwAAAAAQwAKAAAEvHDJSau9OOvNu/8gQRyEMomjKSkiSbXlCb9jLKGHurA1BSSJQWAiCAInB8MgAdAtg4VJ4TmYKH4Jw2FiTAgmAQMQQEkUCoihBFEIFL6SgzkwIHDP5wn7nLAOzgJbEgJ4CGB7fRN/AQJqC2yQSGYFCXaDbWxSbZQnkwmCC41nhhKMfBRiS45FQIkLSWOWC08JpAuUQFU3WFpcrXALdEAGLyQ5MgfHN8ayCzXKzjXN0jrPsjwuINrb3N3e3xIRACH5BAkJAA0ALAAAAABDAAoAg8TGxOTm5NTW1PT29MzOzNze3Pz+/MzKzOzu7Nza3Pz6/NTS1OTi5P///wAAAAAAAATSsMlJq704693KIt93cGRpVkKRrEkxNooyxJMRz9StGFMsK7kfT+IbDBu2nyQFOBwAgpcqkZoMFoIE4ZilBiaBrqBGWC0GE+qqMEEsVoRl4iAQLBbSQACRsCb2AkASf3pfEgyFfRIGAnoFaBIFhQxtiAGKAgwAdyAvlgGUEgOECYIde4hge5c9pJAdCHqhDQiSrA1ZTQQHCwCRLIoNV3CmXQmzlytjRGUJZ2ksbBIIXQtLDCwpLzqmBjM4Pd9H3DkzO+FKizqDIATtLyfx8vP09SQRACH5BAkJABIALAAAAABDAAoAhJSWlMzOzOzu7KyurNze3Ly6vNTW1Pz6/OTm5MTCxKSipNTS1PT29LS2tOTi5Ly+vNza3Pz+/P///wAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAXUoCSOZGmeaKquUqG8MMDOdF0OT67L0uEzkVHkdyARgyLfgVEcHZ0/pGS4bA4SjUK2wHMYIOARY/ENSL8QA2KEQBuEAfCCMUqDCSPBAhwQ4QoFD4BdCIV4IgwQCAIGTRKKhWsiDpEQQmoIBHQiBJEOeZQIlhJXWYJck4UCoxKJhRCOnQKUbIuiTpAQmxIEAoWfIgKdt6Raxqi8YGGIexBmI2gQwBKiYG9JcRBzdcqHEoxyfjo7SVWOQ0yOPUxAUOlG6VJVTEJEIi4wLzw2/P3+/wBnhAAAIfkECQkAFAAsAAAAAEMACgCElJKUzMrM5Obk3Nrc9Pb0rK6s1NLU7O7spKKk5OLk/P78vLq8lJaUzM7M7Ors3N7c/Pr8tLK01NbU9PL0////AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAABe4gJY5kaZ5oqq7UgrwwMzUGbTtsrq9FX0Q9wMEwKBoTFAUEQoCQlk3FaMl0TqtSEZWQTUKtv4V4EREaAoEGWoIkNIqNrqQoEYwE84FkpHgPDAQjekUPIw55DSI9Y2RCDQ0SBhKQbQ8CAgNWFAOXDkgil5cDfHUCDxMjlpefFAcJoooRjBEMQzSRb0gTloddnKEjrw6YfJwOA4EiD8OehsvEFItjZQePaGttRJGaeQOsmHp7Wm+RyZuDhSIHc5GKPz1AZg96DxIPSApNTU/6XV+aFKpwudJEk5KCIlzAeFHrUY0aDXDsmEixosWLK0IAACH5BAkJABUALAAAAABDAAoAhJyanMzOzOzu7LS2tNze3MTCxKyqrNTW1Pz6/Ly+vOTm5MzKzKSipNTS1PT29Ly6vOTi5MTGxKyurNza3Pz+/P///wAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAXyYCWOZGmeaKquFdEE77soAGPfDavv6zH8P0NhYkgYjYNIhYJAOBCk5pMyajqh1StVZHVsl1Ls4RFZLCKPBMEweLR/SkfjMAl86ZODYqTAH0YUARMTDQ4jeYMEIwINgwEiDQ9nZgNDbEducRMKAgdfmwqhIxChChOAegoEhiIEpRCLpKYiPpNolgNGD5kVDqATWBWbAqR8nLNcv6wtAqGwIgKuyGOTC5UHErtvZL2NdXeDE88VpoN/XIKEy+ETitB4ORU+QANCa0cJBUkVYcH8T16yPPF3BcGXK08AhRFBCMYCGRBq3GAA4ByPixgzatyoIgQAIfkECQkAFQAsAAAAAEMACgCEnJqczM7M7O7svLq83N7crKqs1NbU/Pr8xMbEpKKk5ObktLK0nJ6c1NLU9Pb0xMLE5OLkrK6s3Nrc/P78zMrM////AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAABe9gJY5kaZ5oqq6pAiQwzBhNUN8Gq+9kQFEIysMhYUQiC2QhSJA4JYSG6HBwHCYjavVAolqxUy+4MvFyRTaDmkJkDN7vZcAQBEqkFbVTMYIYnDkiEwFODQ4jEn9QIwINTgEjDY6JbEVwcUEScw0IUSISCqF8IqIKEiMTBqEEhyIEohCMEKGnaHoGlW6XcgZpFHefCgIKsaSitWSgpq0VBMPEjK+mkQ1quEQAlwMJCIR1CMB5iaB9T4EVB4R3zE+LIgKKeBVAPxQDbUdIR0zmnuhWVFCZ6bJlzBYrAreocBFDRq8aNmqc40GxosWLGCuEAAAh+QQJCQAXACwAAAAAQwAKAIScnpzU0tTs6uy8urysrqzc3tz09vTExsSkpqTc2tz08vS0trTk5uT8/vzMzsykoqTU1tTs7uy8vry0srTk4uT8+vzMysz///8AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAF9uAljmRpnmiqrqniBG8sPHT9EGyukwxV+JRKZDEhEgcUgGTJRIgqUENj1IhWSNbpM6q9VCuGq4gRSEAgCUNkcLC0LYukZDFYLCTOCxrNGFHQZlQOCQkBBiNmhAUjAoAOjBAUPgVCC29tcUoDAxKceQkMAgx9IhSjDAlUEKMFCiMFpxQjEaaoI6g/BQkVAmxtmAwAm513n6Gmt6ICqU+gy4ciBQKisiIC0rZjZWe7Q78WcHITdcUihHx+iRCCZoaIiYsiEXsBt0A+QgNFdnRyTEt5sowAA0WMiDBQuhCEQqUgNBQKLMSA4WCGDRoTdmjcyLGjRxIhAAAh+QQJCQAUACwAAAAAQwAKAIScnpzU0tS8urzs7uysqqzc3tzExsT8+vykpqTc2ty0srTk5uTMzsykoqTU1tT09vSsrqzk4uTMysz8/vz///8AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAF9iAljmRpnmiqrqzZvHADRQFT19LR7uhxPIeJKCIxSI4JCoAAgSiaiEQhQZXmKD6gbvTzCUW+35cyyW5FDKpjICoEHPC3EiKoCyDRgrEYkDwoC2oOIxNpDgF/IgkOVAUjAwFUDCQJCwsRbBRuVYxKCnYCCnkScA4MDDoRlguOYJUDCYmaqxGPqoEkDgsDBZk0i4uDAHR2eFZvOKm7uCITuoGyvZa1IryWSSORjJlupZ3DoMZ6RxKnqYyLXJEJiCNVUo/oASRdD18Re0ZJS01P4pwFrpTRQuIBEHtcDp4p04UHiRcIGiAAgGDGDRsMrjjcyLGjxxQhAAAh+QQJCQAZACwAAAAAQwAKAISUlpTMzszs6uy0srTc3tzEwsT09vSsqqzU1tS8urykoqT08vTk5uTMysz8/vycmpzU0tTs7uy0trTk4uTExsT8+vysrqzc2ty8vrz///8AAAAAAAAAAAAAAAAAAAAAAAAF/mAmjmRpnmiqrqwJBW+MUEptA0aro1VlVA7RgjAhTiKZxmEgYVogkgJmOn0sMo7ej6QFjnq+oCjrq5AClwsCmYmkEWpBJmBpUBqNAWSAkST8CVYZE3BqIw5oFxA5ImppBCMChQEkFwwMRyJuE5wXcgEDeHcSAVESUwmBSJeXF4cIlwRXIgSsEyMRE60ksAIEbAsXRAQEnwN3o1AFflMSgpcCDK5jlgIXjBnFDAK3Ir7R0yIQjnJtbwRrScd2DRJ7GKl/gVcTjghfaAiLI2/CuHD6uPwwIGZIEU5IlDRpYqFUgQRUCjxAQmbLl4FiMnQxM6YMxx0iAsAYCWGGjRvYCECqXMmy5YkQACH5BAkJABUALAAAAABDAAoAhJSWlMzOzOzq7LSytNze3MTGxPT29NTW1Ly6vKyurOTm5Pz+/KSipNTS1Ozu7LS2tOTi5MzKzPz6/Nza3Ly+vP///wAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAX6YCWOZGmeaKqubFsVTCwDTtQE9w25rSQZksXI9xOKKJFIQVl5DBKJZwIgiBAm2MlV9PMZK0TDd+EDkgLYg2N0PWBHhcbh0Ig0BwgKAvGgRgJKAQVbC2h0BiMTblojAosBJBMKChBrIhAKAgoEcIsHdg8Jent9VTh0VhNgB5oEiCIEmpQjDpgKqiMHmQSWFbYCnCIFbnOgeHwUfQoBOHKpFQu6AhOvFQSZsyLAmrgiDYoTAmxp3XFzdXcPFHqlf+6Dqgvfh4ngwRUOxA0kXWJDXRJGIEnCJNQTKX7aaNlSAUiRfw9FkHHIgwUMGTFo/MmBY0fFjyBDiqwQAgAh+QQJCQAWACwAAAAAQwAKAISUlpTMzszs6uy0srTc3tzEwsT09vTU1tS8urykoqTk5uTMysz8/vycmpzU0tTs7uy0trTk4uTExsT8+vzc2ty8vrz///8AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAF+6AljmRpnmiqrmxrRYETx8vTJHguue00GRPGyPcTiggRJNJCQUAgAyhC0ahYrxCRD2i0EA1dxnZCClAoh8eIgD6vHWeHgglZSOwVhKDh7BeyE2YUDgYjbRQEIwIHZwEkFAoKEWoiEQoCColHiBEHc00Ldgt5VBBYFVkMkJmFR5GSIw+WChQkngIElC+RuIYUSb90d3YFelVWCAgFA163FK0WBJgCEYrStCRwjAJrZ4xrB4xyTBWiEqQNEH0DqBYMDuLQ3oixjAcOJD9FQ/pkR0kALRxw8gRKhT1WCiRk5gUImCFAfIwQE5EHCxgyZASoASCHDosgQ4ocKSIEACH5BAkJABUALAAAAABDAAoAhJSWlMzOzOzq7LSytNze3PT29MTGxNTW1KyurPTy9Ly6vOTm5Pz+/KSipNTS1Ozu7LS2tOTi5Pz6/MzKzNza3P///wAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAX7YCWOZGmeaKqubJsWQCPPxuEEd+6WklRIjFHPFxQxer+RY8I0GB4JAAIxmA4gDgJlSyEERMNCsXL0SUiB7eEx0h62o4TjHSjOD3iH4AGAKP4KCFgUBhMBNV8VFG9dIwKMiSIUCwsRbCIRCwILBCMFk492jBQTAlEDgAoQgzU3pCIEm5UjD5kLFCQHmgSXFbYCnSIJBJoHoosUAQtRfoCCWRM20YnEmxGOsbckDsgCbWq4IgXcNmcVNngHAXsAqYGsTcmSyMEVD282JGZiQmbmZP6UTCg00JSUKVNWBdCyiACBCWCQjDny49+OixWizKCRLAeOSBhDihx5IgQAIfkECQkAFQAsAAAAAEMACgCEnJ6c1NLU7OrsvLq8rK6s3N7c9Pb0zMrMpKak3NrctLa05Obk/P78pKKk1NbU7O7svL68tLK05OLk/Pr8zM7M////AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAABfFgJY5kaZ5oqq5sm04HFciyRDR43rjlNBkTxsj3E4oYPuBoISk0JQYDJBJRWAcJBGTLBYiIBmMF+ZuQKImE4zEqqNMjQ8CRoBgF9DdQATn4DwpZEAoDA3xeFW8JBSN4aRQkCQtMbCISCwILjCIGknh3ixISDlEDfX+BWoUQhYgFmUwjD5cLCSQOmAWVFbQCmxWdmA53DqJuQKZ+fQoFqqamrrASja+1JAFvAm1pdHHYDgFmFY50pAbJfxCCEYaEiGlpvw/lASRlYUNl4mP6S09OyAgoEChQEJctiO6JSeKDh0N7MWbMKHZDB46HGDNqVBECACH5BAkJABYALAAAAABDAAoAhJyenNTS1Ozq7Ly6vKyqrNze3PT29MTGxKSmpNza3PTy9LSytOTm5Pz+/MzOzKSipNTW1Ozu7KyurOTi5Pz6/MzKzP///wAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAX3oCWOZGmeaKqubOuS0iPPj+AE910pKkUZlMbI9xOKGj4gKRkUCQ6VCjRgQUgkiysBMIEUEuBEIGJB/igkBxhCFn0h4JEiAHcYLfAEhOF0QP4QVAgLA4UDElwJUVEODmQCeQ4kCQwME20WEwwCDAUjBpSQd5SbE31hdBYPEoaHXBB+dDdkEZoMCSR7AgWYtrtyBZsQo5uWfXCAVYSGiLcVgThjFgKakCQBegkCI1/Zn9iBaCLIlMd/qautiF1RBw4V0hHIVENABndM4mVn+hb84hGgLBJ0BUuWRN0SFHC0D0i/FxBTxEDwAAEAigwaacTBI6LHjyBDAAAh+QQJCQAWACwAAAAAQwAKAIScmpzMzszs6uy0trTc3tysqqz09vTExsTU1tS8vrzk5uS0srT8/vykoqTU0tTs7uy8urzk4uSsrqz8+vzMyszc2tz///8AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAF+6AljmRpnmiqrmzrkogTyLTT3DigqNNkTIxRzxcUMXo/EhIoMkQIz8jOIRksrAXKYZDodgsEy9E3IQUqFcRjREijRwYHohIoWubpncXgTgssDgMUWhQLAVsQA4kQBRUWAghzDiQVCgoRayJSAgphTZWQdpUKAhFNCBFPFX8BAweDB4aIEF0DYI9SCo4jCKQEmRa5Ap57oAiipJefUBEIf4GEFBIBhbRdEA1hD5sIJA5ufyJtbnDfMWUic3N6fOrOFq2EW1oDiva3kGmTQj8GdkvoxJAJaGEgOgNQmE2RcKVelgPWvNw68oPgi4spYtCYEQABjhx6MIocOTIEACH5BAkJABUALAAAAABDAAoAhJyanMzOzOzu7Ly6vNze3KyqrNTW1Pz6/MTGxKSipOTm5LSytJyenNTS1PT29Ly+vOTi5KyurNza3Pz+/MzKzP///wAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAX4YCWOZGmeaKqubOuyRiLPgEIFDY4T4nE4h8nI9xOKJj4gKRkcMkcICiX6qCAKkWyWQaAQJGAJwSAKgA2C0dcAHjka7ICxwpYYFCNFnSyiwA0NCFYFAw+GAwwSflEBCGMiEgoKEGkiEAoCCjwiDpECBgcjkZJ4lqQSIzhngggJDwOwiBIIgHCOfHcCBJUVl75uoxKhIgSYl3mYCqh9BgZjghSEsYhdOYBeDSINdhK8X2Fu2xIBw3RhEHlhfBV+zYFWrtMABLRSU48VP0VODg5z+kqGAPHHzweUKVKqXNGypcsZMASyvZhIkc6MGQwg3MjBcVnFjyBRhAAAIfkECQkAFwAsAAAAAEMACgCElJaUzM7M7OrstLK03N7cxMLE9Pb01NbUvLq8rK6s9PL05ObkzMrM/P78pKKk1NLU7O7stLa05OLkxMbE/Pr83NrcvL68////AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAABffgJY5kaZ5oqq5s67JC8MgyowBOriMiRRmUxsj3E4oaPiApGRwyRxJCNHqRIAYRLBYCKFi+34QoUKkcICOCuTxSPA6VgPECNy9Gi/oB/zAfCFURDBODEwhcCBGJEQViFxULCxJoIhILAguAIgaQAgdzkJcSeJELFXgHEhIVgBKCDLATEYgIX7WOB5cElFWRApoXCgSXnyOdkqSYpyJ5qn9VCITSCAIAtRaJjSJ9cAJpZXAjBtwPFCNwcHeVa3uVb2atgtKyNwiJVxaOP0VOQOZH+/718CcQyT4oUhJCi8AwC7UuYLw4ekGx4gIaM2bc0LGjosePLUIAACH5BAkJABIALAAAAABDAAoAhJSSlMzKzOTm5Nza3KyurPT29NTS1KSipOTi5Ly6vPz+/JSWlMzOzOzu7Nze3LSytPz6/NTW1P///wAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAXcoCSOZGmeaKqubOu+5nLMdCJCUAEpI57zIgVORxruesZi7igZBJ5PhARAqD6qBBFjMIg0Ro4ud1QwRAYMoOTcFYwE7MhIsR0YCqKIXs+QUhOACVciAwICCF8iCAINAg5khQ0RECOFhm6KlwNzEYYOeE0GYhGPAA+BCVV5jA6JEouwkIYDlCIOjItvjAKbN5YDoHpcdqWngYMSomeuYcNkomi1a8NSIrxccjd1d4RmegalBKiqEksFakPnPTpE6z9IOtLl8UBOUAF+Vg9XWTD+/ydk0JhhA6DBgyZCAAAh+QQJCQASACwAAAAAQwAKAISsrqzc2tzs7uzMyszk5uT8+vy8urzU0tTk4uT09vS0trTc3tz08vTMzszs6uz8/vzEwsTU1tT///8AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAF6KAkjmRpnmiqrmzrvubQHPO8iEWRFM+Y6z3RI7cjEXm+o1GHlAgQCyhCIIEAFFjsQdQIBCJU0eLrHTEOkUAjKEl/CSOCOzJ6dAOHhMjh9lIhCgMQAwMKdBIBBARTIwgEDgQ3IgmJfGyJjwhxigQBdRGKCwx7AVIBfwaEg4YioA4LYRKOj5ISDAuPEZePi5uQnkKVAXoSDgG4CKdVgYSFhwdkDiNjZCMJ0BEHBSNpaXAiyd0+XdnEfGRgy6oNrBJMCWxH20JM8zg7OXVKPjvwIk9RcI2youAKlkMwEiqM0aAGDWALI0osEQIAIfkECQkAEAAsAAAAAEMACgCEpKak1NbU7O7szM7M5OLk/Pr8tLK03N7c9Pb0vLq83Nrc9PL01NLU5Obk/P78tLa0////AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAABcsgJI5kaZ5oqq5s675wUSCFM8qzLToyTfa1G/A3C4pwCN0CYGg2RwOFIiAYHabS0YIRUAx0kO60MWqIAyNHVMFAjLCKg0jwGNgHiXejQaiKCA0CDXIiCAqBAQV6e2R/jAppAXsHC1aMBHMJAwx2eSKSAgd+EIClWgd7CooiqAKAZYENkEeHg5Wsgq5zD5ycnhAMWKNXUrMQW1IDq2HFmCKyUmhHa21v0c50dwwPNzRJQgjfO97LEEXi5kXl6mDnSkxOBjDz9PX29/giIQA7';
}

function handler_source($source) {
    Hm_Handler_Modules::set_source($source);
}
function output_source($source) {
    Hm_Output_Modules::set_source($source);
}
function replace_module($type, $target, $replacement, $page=false) {
    if ($type == 'handler') {
        Hm_Handler_Modules::replace($target, $replacement, $page);
    }
    elseif ($type == 'output') {
        Hm_Output_Modules::replace($target, $replacement, $page);
    }
}
function add_handler($page, $mod, $logged_in, $source=false, $marker=false, $placement='after', $queue=true) {
    Hm_Handler_Modules::add($page, $mod, $logged_in, $marker, $placement, $queue, $source);
}
function add_output($page, $mod, $logged_in, $source=false, $marker=false, $placement='after', $queue=true) {
    Hm_Output_Modules::add($page, $mod, $logged_in, $marker, $placement, $queue, $source);
}
function secure_cookie($request, $name, $value, $lifetime=0, $path='', $domain='') {
    if ($request->tls) {
        $secure = true;
    }
    else {
        $secure = false;
    }
    setcookie($name, $value, $lifetime, $path, $domain, $secure);
}

?>
