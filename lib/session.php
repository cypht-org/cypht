<?php

if (!defined('DEBUG_MODE')) { die(); }

/* persistant storage between pages, abstract interface */
abstract class Hm_Session {

    public $active = false;
    public $loaded = false;
    public $internal_users = false;

    protected $enc_key = '';
    protected $data = array();
    protected $cname = false;
    protected $site_config = false;

    abstract protected function check($request);
    abstract protected function start($request);
    abstract protected function just_started();
    abstract protected function check_fingerprint($request);
    abstract protected function set_fingerprint($request);
    abstract protected function auth($user, $pass);
    abstract protected function get($name, $default=false);
    abstract protected function set($name, $value);
    abstract protected function del($name);
    abstract protected function is_active();
    abstract protected function record_unsaved($value);
    abstract protected function end();
    abstract protected function destroy($request);
}

/* session persistant storage with vanilla PHP sessions and no local authentication */
class Hm_PHP_Session extends Hm_Session {

    protected $cname = 'PHPSESSID';

    public function __construct($config) {
        $this->site_config = $config;
    }
    protected function just_started() {
        $this->set('login_time', time());
    }

    protected function check_fingerprint($request) {
        $id = $this->build_fingerprint($request);
        $fingerprint = $this->get('fingerprint', false);
        if (!$fingerprint || $fingerprint !== $id) {
            $this->end();
        }
    }
    private function build_fingerprint($request) {
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

    protected function set_fingerprint($request) {
        $id = $this->build_fingerprint($request);
        $this->set('fingerprint', $id);
    }

    protected function ciphertext($data) {
        return Hm_Crypt::ciphertext(serialize($data), $this->enc_key);
    }
    protected function plaintext($data) {
        return @unserialize(Hm_Crypt::plaintext($data, $this->enc_key));
    }
    protected function set_key($request) {
        if (array_key_exists('hm_id', $request->cookie)) {
            $this->enc_key = $request->cookie['hm_id'];
        }
        else {
            $this->enc_key = base64_encode(openssl_random_pseudo_bytes(128));
            secure_cookie($request, 'hm_id', $this->enc_key);
        }
    }

    public function check($request) {
        $this->set_key($request);
        if (array_key_exists($this->cname, $request->cookie)) {
            $this->start($request);
            $this->check_fingerprint($request);
        }
        else {
            $this->start($request);
            $this->set_fingerprint($request);
        }
    }

    public function auth($user, $pass) {
        return true;
    }

    public function start($request) {
        session_start();
        if (array_key_exists('data', $_SESSION)) {
            $data = $this->plaintext($_SESSION['data']);
            if (is_array($data)) {
                $this->data = $data;
            }
        }
        $this->active = true;
    }

    public function get($name, $default=false, $user=false) {
        if ($user) {
            return array_key_exists('user_data', $this->data) && array_key_exists($name, $this->data) ? $this->data['user_data'][$name] : $default;
        }
        else {
            return array_key_exists($name, $this->data) ? $this->data[$name] : $default;
        }
    }

    public function set($name, $value, $user=false) {
        if ($user) {
            $this->data['user_data'][$name] = $value;
        }
        else {
            $this->data[$name] = $value;
        }
    }

    public function del($name) {
        if (array_key_exists($name, $this->data)) {
            unset($this->data[$name]);
        }
    }
    public function is_active() {
        return $this->active;
    }
    public function record_unsaved($value) {
        $this->data['changed_settings'][] = $value;
    }

    public function destroy($request) {
        session_unset();
        @session_destroy();
        $params = session_get_cookie_params();
        secure_cookie($request, $this->cname, '', 0, $params['path'], $params['domain']);
        secure_cookie($request, 'hm_id', '', 0);
        $this->active = false;
    }

    public function end() {
        if ($this->active) {
            $enc_data = $this->ciphertext($this->data);
            $_SESSION = array('data' => $enc_data);
            session_write_close();
            $this->active = false;
        }
    }
}

/* persistant storage with vanilla PHP sessions and DB based authentication */
class Hm_PHP_Session_DB_Auth extends Hm_PHP_Session {

    public $internal_users = true;
    protected $dbh = false;

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
            $this->set_key($request);
            $this->start($request);
            $this->check_fingerprint($request);
        }
    }

    public function auth($user, $pass) {
        if ($this->connect()) {
            $sql = $this->dbh->prepare("select hash from hm_user where username = ?");
            if ($sql->execute(array($user))) {
                $row = $sql->fetch();
                if ($row['hash'] && pbkdf2_validate_password($pass, $row['hash'])) {
                    return true;
                }
            }
        }
        return false;
    }

    protected function connect() {
        $this->dbh = Hm_DB::connect($this->site_config);
        if ($this->dbh) {
            return true;
        }
        return false;
    }

    public function change_pass($user, $pass) {
        $this->connect();
        $hash = pbkdf2_create_hash($pass);
        $sql = $this->dbh->prepare("update hm_user set hash=? where username=?");
        if ($sql->execute(array($hash, $user))) {
            Hm_Msgs::add("Password changed");
            return true;
        }
        return false;
    }

    public function create($request, $user, $pass) {
        $this->connect();
        $sql = $this->dbh->prepare("select username from hm_user where username = ?");
        if ($sql->execute(array($user))) {
            $res = $sql->fetch();
            if (!empty($res)) {
                Hm_Msgs::add("ERRThat username is already in use");
            }
            else {
                $sql = $this->dbh->prepare("insert into hm_user values(?,?)");
                $hash = pbkdf2_create_hash($pass);
                if ($sql->execute(array($user, $hash))) {
                    $this->check($request, $user, $pass);
                    Hm_Msgs::add("Account created");
                }
            }
        }
    }
}

/* persistant storage with DB sessions and DB authentication */
class Hm_DB_Session_DB_Auth extends Hm_PHP_Session_DB_Auth {

    protected $cname = 'hm_session';
    private $session_key = '';

    private function insert_session_row() {
        $sql = $this->dbh->prepare("insert into hm_user_session values(?, ?, current_date)");
        $enc_data = $this->ciphertext($this->data);
        if ($sql->execute(array($this->session_key, $enc_data))) {
            return true;
        }
        return false;
    }

    public function start($request) {
        if ($this->connect()) {
            if ($this->loaded) {
                $this->session_key = base64_encode(openssl_random_pseudo_bytes(128));
                secure_cookie($request, $this->cname, $this->session_key, 0);
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
                        if (array_key_exists('data', $results)) {
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

    public function end() {
        if ($this->dbh) {
            $sql = $this->dbh->prepare("update hm_user_session set data=? where hm_id=?");
            $enc_data = $this->ciphertext($this->data);
            $sql->execute(array($enc_data, $this->session_key));
        }
        $this->active = false;
    }

    public function destroy($request) {
        if ($this->dbh) {
            $sql = $this->dbh->prepare("delete from hm_user_session where hm_id=?");
            $sql->execute(array($this->session_key));
        }
        secure_cookie($request, $this->cname, '', 0);
        secure_cookie($request, 'hm_id', '', 0);
        $this->active = false;
    }
}

?>
