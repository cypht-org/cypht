<?php

if (!defined('DEBUG_MODE')) { die(); }

/**
 * Base class for session management. All session interaction happens through
 * classes that extend this.
 */
abstract class Hm_Session {

    /* set to true if the session was just loaded on this request */
    public $loaded = false;

    /* set to true if the session is active */
    public $active = false;

    /* set to true if the user authentication is local (DB) */
    public $internal_users = false;

    /* key used to encrypt session data */
    protected $enc_key = '';

    /* session data */
    protected $data = array();

    /* session cookie name */
    protected $cname = false;

    /* site config object */
    protected $site_config = false;

    /* authentication object */
    protected $auth_mech = false;

    /* close early flag */
    protected $session_closed = false;

    /**
     * Methods extended classes need to override
     * TODO: document
     */
    abstract protected function check($request);
    abstract protected function start($request);
    abstract protected function auth($user, $pass);
    abstract protected function get($name, $default=false);
    abstract protected function set($name, $value);
    abstract protected function del($name);
    abstract protected function end();
    abstract protected function destroy($request);

    /**
     * Setup initial data
     *
     * @param $config object site config
     * @param $auth_type string authentication class
     *
     * @return void
     */
    public function __construct($config, $auth_type='Hm_Auth_DB') {
        $this->site_config = $config;
        $this->auth_mech = new $auth_type($config);
        $this->internal_users = $this->auth_mech->internal_users;
    }

    /**
     * Method called on a new login
     *
     * @return void
     */
    protected function just_started() {
        $this->set('login_time', time());
    }

    /**
     * Check HTTP header "fingerprint" against the session value
     *
     * @param $request object request details
     *
     * @return void
     */
    protected function check_fingerprint($request) {
        $id = $this->build_fingerprint($request);
        $fingerprint = $this->get('fingerprint', false);
        if (!$fingerprint || $fingerprint !== $id) {
            $this->destroy($request);
        }
    }

    /**
     * Build HTTP header "fingerprint"
     *
     * @param $request object request details
     *
     * @return string fingerprint value
     */
    protected function build_fingerprint($request) {
        $env = $request->server;
        $id = '';
        $id .= (array_key_exists('REMOTE_ADDR', $env)) ? $env['REMOTE_ADDR'] : '';
        $id .= (array_key_exists('HTTP_USER_AGENT', $env)) ? $env['HTTP_USER_AGENT'] : '';
        $id .= (array_key_exists('REQUEST_SCHEME', $env)) ? $env['REQUEST_SCHEME'] : '';
        $id .= (array_key_exists('HTTP_ACCEPT_LANGUAGE', $env)) ? $env['HTTP_ACCEPT_LANGUAGE'] : '';
        $id .= (array_key_exists('HTTP_ACCEPT_CHARSET', $env)) ? $env['HTTP_ACCEPT_CHARSET'] : '';
        $id .= (array_key_exists('HTTP_HOST', $env)) ? $env['HTTP_HOST'] : '';
        return hash('sha256', $id);
    }

    /**
     * Save a fingerprint in the session
     *
     * @param $request object request details
     *
     * @return void
     */
    protected function set_fingerprint($request) {
        $id = $this->build_fingerprint($request);
        $this->set('fingerprint', $id);
    }

    /**
     * Record session level changes not yet saved in persistant storage
     *
     * @param $vaue string short description of the unsaved value
     *
     * @return void
     */
    public function record_unsaved($value) {
        $this->data['changed_settings'][] = $value;
    }

    /**
     * Returns bool true if the session is active
     *
     * @return bool
     */
    public function is_active() {
        return $this->active;
    }

    /**
     * Encrypt session data
     *
     * @param $data array session data to encrypt
     *
     * @return string encrypted session data
     */
    protected function ciphertext($data) {
        return Hm_Crypt::ciphertext(serialize($data), $this->enc_key);
    }

    /**
     * Decrypt session data
     *
     * @param $data encrypted session data
     *
     * @return array decrpted session data
     */
    protected function plaintext($data) {
        return @unserialize(Hm_Crypt::plaintext($data, $this->enc_key));
    }

    /**
     * Set the session level encryption key
     *
     * @param $request object request details
     *
     * @return void
     */
    protected function set_key($request) {
        $this->enc_key = Hm_Crypt::unique_id();
        $this->secure_cookie($request, 'hm_id', $this->enc_key);
    }
    /**
     * Fetch the current encryption key
     *
     * @param $request object request details
     *
     * @return void
     */
    function get_key($request) {
        if (array_key_exists('hm_id', $request->cookie)) {
            $this->enc_key = $request->cookie['hm_id'];
        }
    }


    /**
     * Set a cookie, secure if possible
     *
     * @param $request object request details
     * @param $name string cookie name
     * @param $value string cookie value
     * @param $lifetime string cookie lifetime
     * @param $path string cookie path
     * @param $domain string cookie domain
     * @param $html_only string set html only cookie flag
     *
     * @return void
     */
    public function secure_cookie($request, $name, $value, $lifetime=0, $path='', $domain='', $html_only=true) {
        if ($request->tls) {
            $secure = true;
        }
        else {
            $secure = false;
        }
        if (!$path && isset($request->path)) {
            $path = $request->path;
        }
        if (!$domain && array_key_exists('SERVER_NAME', $request->server) && strtolower($request->server['SERVER_NAME']) != 'localhost') {
            $domain = $request->server['SERVER_NAME'];
        }
        setcookie($name, $value, $lifetime, $path, $domain, $secure, $html_only);
    }
}

/**
 * PHP Sessions that extend the base session class
 */
class Hm_PHP_Session extends Hm_Session {

    /* cookie name for sessions */
    protected $cname = 'PHPSESSID';

    /**
     * Check for an existing session or a new user/pass login request
     *
     * @param $request object request details
     * @param $user string username
     * @param $pass string password
     *
     * @return void
     */
    public function check($request, $user=false, $pass=false) {
        if ($user && $pass) {
            if ($this->auth($user, $pass)) {
                $this->set_key($request);
                $this->loaded = true;
                $this->start($request);
                $this->set_fingerprint($request);
                $this->just_started();
            }
            else {
                Hm_Msgs::add("ERRInvalid username or password");
            }
        }
        elseif (array_key_exists($this->cname, $request->cookie)) {
            $this->get_key($request);
            $this->start($request);
            $this->check_fingerprint($request);
        }
    }

    /**
     * Call the configured authentication method to check user credentials
     *
     * @param $user string username
     * @param $pass string password
     *
     * @return bool true if the authentication was successful
     */
    public function auth($user, $pass) {
        return $this->auth_mech->check_credentials($user, $pass);
    }

    /**
     * Call the configuration authentication method to change the user password
     *
     * @param $user string username
     * @param $pass string password
     *
     * @return bool true if the password was changed
     */
    public function change_pass($user, $pass) {
        return $this->auth_mech->change_pass($user, $pass);
    }

    /**
     * Call the configuration authentication method to create an account
     *
     * @param $request object request details
     * @param $user string username
     * @param $pass string password
     *
     * @return bool true if the account was created
     */
    public function create($request, $user, $pass) {
        if ($this->auth_mech->create($user, $pass)) {
            return $this->check($request, $user, $pass);
        }
    }

    /**
     * Start the session. This could be an existing session or a new login
     *
     * @param $request object request details
     *
     * @return void
     */
    public function start($request) {
        if (array_key_exists($this->cname, $request->cookie)) {
            session_id($request->cookie[$this->cname]);
        }
        list($secure, $path, $domain) = $this->set_session_params($request);
        session_set_cookie_params(0, $path, $domain, $secure);
        session_start();
        if (array_key_exists('data', $_SESSION)) {
            $data = $this->plaintext($_SESSION['data']);
            if (is_array($data)) {
                $this->data = $data;
            }
        }
        $this->active = true;
    }

    /**
     * Setup the cookie params for a session cookie
     *
     * @param $request object request details
     *
     * @return array list of cookie fields
     */
    public function set_session_params($request) {
        if ($request->tls) {
            $secure = true;
        }
        else {
            $secure = false;
        }
        if (isset($request->path)) {
            $path = $request->path;
        }
        if (array_key_exists('SERVER_NAME', $request->server) && strtolower($request->server['SERVER_NAME']) != 'localhost') {
            $domain = $request->server['SERVER_NAME'];
        }
        return array($secure, $path, $domain);
    }

    /**
     * Return a session value, or a user settings value stored in the session
     *
     * @param $name string session value name to return
     * @param $default mixed value to return if $name is not found
     * @param $user bool if true, only search the user_data section of the session
     *
     * @return mixed the value if found, otherwise $default
     */
    public function get($name, $default=false, $user=false) {
        if ($user) {
            return array_key_exists('user_data', $this->data) && array_key_exists($name, $this->data) ? $this->data['user_data'][$name] : $default;
        }
        else {
            return array_key_exists($name, $this->data) ? $this->data[$name] : $default;
        }
    }

    /**
     * Save a value in the session
     *
     * @param $name string the name to save
     * @param $value string the value to save
     * @param $user bool if true, save in the user_data section of the session
     *
     * @return void
     */
    public function set($name, $value, $user=false) {
        if ($user) {
            $this->data['user_data'][$name] = $value;
        }
        else {
            $this->data[$name] = $value;
        }
    }

    /**
     * Delete a value from the session
     *
     * @param $name string name of value to delete
     *
     * @return void
     */
    public function del($name) {
        if (array_key_exists($name, $this->data)) {
            unset($this->data[$name]);
        }
    }

    /**
     * End a session after a page request is complete. This only closes the session and
     * does not destroy it
     *
     * @return void
     */
    public function end() {
        if ($this->active) {
            if (!$this->session_closed) {
                $this->save_data();
            }
            $this->active = false;
        }
    }

    /**
     * write session data to avoid locking, keep session active, but don't allow writing
     *
     * @return void
     */
    public function close_early() {
        $this->session_closed = true;
        $this->save_data();
    }

    /** save session data
     *
     * @return void
     */
    public function save_data() {
        $enc_data = $this->ciphertext($this->data);
        $_SESSION = array('data' => $enc_data);
        session_write_close();
        $_SESSION = array();
    }

    /**
     * Destroy a session for good
     *
     * @param $request object request details
     *
     * @return void
     */
    public function destroy($request) {
        session_unset();
        @session_destroy();
        $params = session_get_cookie_params();
        $this->secure_cookie($request, $this->cname, '', time()-3600, $params['path'], $params['domain']);
        $this->secure_cookie($request, 'hm_id', '', time()-3600);
        $this->active = false;
    }
}

/**
 * This session class uses a PDO compatible DB to manage session data. It does not
 * use PHP session handlers at all and is a completely indenpendant session system.
 */
class Hm_DB_Session extends Hm_PHP_Session {

    /* session cookie name */
    protected $cname = 'hm_session';

    /* session key */
    private $session_key = '';

    /* DB handle */
    protected $dbh = false;

    /**
     * Create a new session
     *
     * @return bool true on success
     */
    private function insert_session_row() {
        $sql = $this->dbh->prepare("insert into hm_user_session values(?, ?, current_date)");
        $enc_data = $this->ciphertext($this->data);
        if ($sql->execute(array($this->session_key, $enc_data))) {
            return true;
        }
        return false;
    }

    /**
     * Connect to the configured DB
     *
     * @return bool true on success
     */
    protected function connect() {
        $this->dbh = Hm_DB::connect($this->site_config);
        if ($this->dbh) {
            return true;
        }
        return false;
    }

    /**
     * Start the session. This could be an existing session or a new login
     *
     * @param $request object request details
     *
     * @return void
     */
    public function start($request) {
        if ($this->connect()) {
            if ($this->loaded) {
                $this->session_key = Hm_Crypt::unique_id(); 
                $this->secure_cookie($request, $this->cname, $this->session_key, 0);
                if ($this->insert_session_row()) {
                    $this->active = true;
                }
            }
            else {
                if (!array_key_exists($this->cname, $request->cookie)) {
                    $this->destroy($request);
                }
                else {
                    $this->session_key = $request->cookie[$this->cname];
                    $sql = $this->dbh->prepare('select data from hm_user_session where hm_id=?');
                    if ($sql->execute(array($this->session_key))) {
                        $results = $sql->fetch();
                        if (is_array($results) && array_key_exists('data', $results)) {
                            $data = $this->plaintext($results['data']);
                            if (is_array($data)) {
                                $this->active = true;
                                $this->data = $data;
                            }
                        }
                    }
                }
            }
        }
    }

    /**
     * End a session after a page request is complete. This only closes the session and
     * does not destroy it
     *
     * @return void
     */
    public function end() {
        if (!$this->session_closed) {
            $this->save_data();
        }
        $this->active = false;
    }

    public function save_data() {
        if ($this->dbh) {
            $sql = $this->dbh->prepare("update hm_user_session set data=? where hm_id=?");
            $enc_data = $this->ciphertext($this->data);
            $sql->execute(array($enc_data, $this->session_key));
        }
    }

    public function close_early() {
        $this->session_closed = true;
        $this->save_data();
    }

    /**
     * Destroy a session for good
     *
     * @param $request object request details
     *
     * @return void
     */
    public function destroy($request) {
        if ($this->dbh) {
            $sql = $this->dbh->prepare("delete from hm_user_session where hm_id=?");
            $sql->execute(array($this->session_key));
        }
        $this->secure_cookie($request, $this->cname, '', time()-3600);
        $this->secure_cookie($request, 'hm_id', '', time()-3600);
        $this->active = false;
    }
}

?>
