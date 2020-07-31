<?php

/**
 * Session handling
 * @package framework
 * @subpackage session
 */

trait Hm_Session_Auth {

    /**
     * Lazy loader for the auth mech so modules can define their own
     * overrides
     * @return void
     */
    abstract protected function load_auth_mech();

    /**
     * Call the configured authentication method to check user credentials
     * @param string $user username
     * @param string $pass password
     * @return bool true if the authentication was successful
     */
    public function auth($user, $pass) {
        $this->load_auth_mech();
        return $this->auth_mech->check_credentials($user, $pass);
    }

    /**
     * Save auth detail if i'ts needed (mech specific)
     * @return void
     */
    public function save_auth_detail() {
        $this->auth_mech->save_auth_detail($this);
    }

    /**
     * Call the configuration authentication method to change the user password
     * @param string $user username
     * @param string $pass password
     * @return bool true if the password was changed
     */
    public function change_pass($user, $pass) {
        $this->load_auth_mech();
        return $this->auth_mech->change_pass($user, $pass);
    }

    /**
     * Call the configuration authentication method to create an account
     * @param string $user username
     * @param string $pass password
     * @return bool true if the account was created
     */
    public function create($user, $pass) {
        $this->load_auth_mech();
        return $this->auth_mech->create($user, $pass);
    }
}

/**
 * PHP session data methods
 * @package framework
 * @subpackage session
 */
abstract class Hm_PHP_Session_Data extends Hm_Session {

    /**
     * @param Hm_Request $request request details
     * @return void
     */
    protected function validate_session_data($request) {
        if ($this->existing && count($this->data) == 0) {
            $this->destroy($request);
        }
        else {
            Hm_Debug::add('LOGGED IN');
            $this->active = true;
        }
    }

    /**
     * @param Hm_Request $request request details
     * @return void
     */
    protected function start_session_data($request) {
        if (array_key_exists('data', $_SESSION)) {
            $data = $this->plaintext($_SESSION['data']);
            if (is_array($data)) {
                $this->data = $data;
            }
            elseif (!$this->loaded) {
                $this->destroy($request);
                Hm_Debug::add('Mismatched session level encryption key');
            }
        }
    }

    /**
     * Return a session value, or a user settings value stored in the session
     * @param string $name session value name to return
     * @param mixed $default value to return if $name is not found
     * @param bool $user if true, only search the user_data section of the session
     * @return mixed the value if found, otherwise $default
     */
    public function get($name, $default=false, $user=false) {
        if ($user) {
            return array_key_exists('user_data', $this->data) && array_key_exists($name, $this->data['user_data']) ? $this->data['user_data'][$name] : $default;
        }
        else {
            return array_key_exists($name, $this->data) ? $this->data[$name] : $default;
        }
    }

    /**
     * Save a value in the session
     * @param string $name the name to save
     * @param string $value the value to save
     * @param bool $user if true, save in the user_data section of the session
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
     * @param string $name name of value to delete
     * @return void
     */
    public function del($name) {
        if (array_key_exists($name, $this->data)) {
            unset($this->data[$name]);
            return true;
        }
        return false;
    }

    /**
     * Save session data
     * @return void
     */
    public function save_data() {
        $enc_data = $this->ciphertext($this->data);
        $_SESSION = array('data' => $enc_data);
        session_write_close();
        $_SESSION = array();
    }
}

/**
 * PHP Sessions that extend the base session class
 * @package framework
 * @subpackage session
 */
class Hm_PHP_Session extends Hm_PHP_Session_Data {

    use Hm_Session_Auth;

    /* data store connection used by classes that extend this */
    public $conn;

    /* used to indicate failed auth */
    public $auth_failed = false;

    /* flag to indicate an existing session */
    protected $existing = false;

    /**
     * Setup newly authenticated session
     * @param Hm_Request $request
     * @param boolean $fingerprint
     * @return null
     */
    protected function authed($request, $fingerprint) {
        $this->set_key($request);
        $this->loaded = true;
        $this->start($request);
        if ($fingerprint) {
            $this->set_fingerprint($request);
        }
        else {
            $this->set('fingerprint', '');
        }
        $this->save_auth_detail();
        $this->just_started();
    }

    /**
     * Check for an existing session or a new user/pass login request
     * @param object $request request details
     * @param string $user username
     * @param string $pass password
     * @return bool
     */
    public function check($request, $user=false, $pass=false, $fingerprint=true) {
        if ($user !== false && $pass !== false) {
            if ($this->auth($user, $pass)) {
                $this->authed($request, $fingerprint);
            }
            else {
                $this->auth_failed = true;
            }
        }
        elseif (array_key_exists($this->cname, $request->cookie)) {
            $this->get_key($request);
            $this->existing = true;
            $this->start($request);
            $this->check_fingerprint($request);
        }
        return $this->is_active();
    }

    /**
     * Start the session. This could be an existing session or a new login
     * @param Hm_Request $request request details
     * @return void
     */
    public function start($request) {
        if (array_key_exists($this->cname, $request->cookie)) {
            session_id($request->cookie[$this->cname]);
        }
        list($secure, $path, $domain) = $this->set_session_params($request);
        session_set_cookie_params($this->lifetime, $path, $domain, $secure);
        Hm_Functions::session_start();
        $this->session_key = session_id();
        $this->start_session_data($request);
        $this->validate_session_data($request);
    }

    /**
     * Setup the cookie params for a session cookie
     * @param Hm_Request $request request details
     * @return array list of cookie fields
     */
    public function set_session_params($request) {
        $path = false;
        if ($request->tls) {
            $secure = true;
        }
        else {
            $secure = false;
        }
        if (isset($request->path)) {
            $path = $request->path;
        }
        $domain = $this->site_config->get('cookie_domain', false);
        if (!$domain && array_key_exists('HTTP_HOST', $request->server)) {
            $host = parse_url($request->server['HTTP_HOST'],  PHP_URL_HOST);
            if (trim($host)) {
                $domain = $host;
            }
            else {
                $domain = $request->server['HTTP_HOST'];
            }
        }
        if ($domain == 'none') {
            $domain = '';
        }
        return array($secure, $path, $domain);
    }

    /**
     * Write session data to avoid locking, keep session active, but don't allow writing
     * @return void
     */
    public function close_early() {
        $this->session_closed = true;
        $this->save_data();
    }

    /**
     * Destroy a session for good
     * @param Hm_Request $request request details
     * @return void
     */
    public function destroy($request) {
        if (function_exists('delete_uploaded_files')) {
            delete_uploaded_files($this);
        }
        session_unset();
        Hm_Functions::session_destroy();
        $params = session_get_cookie_params();
        $this->delete_cookie($request, $this->cname, $params['path'], $params['domain']);
        $this->delete_cookie($request, 'hm_id');
        $this->delete_cookie($request, 'hm_reload_folders');
        $this->delete_cookie($request, 'hm_msgs');
        $this->active = false;
    }

    /**
     * End a session after a page request is complete. This only closes the session and
     * does not destroy it
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
}
