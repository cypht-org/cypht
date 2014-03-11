<?php

/* persistant storage between pages, abstract interface */
abstract class Hm_Session {

    public $active = false;
    public $loaded = false;
    protected $enc_key = '';
    protected $data = array();

    abstract protected function check($request, $config);
    abstract protected function start($request);
    abstract protected function auth($user, $pass);
    abstract protected function get($name, $default=false);
    abstract protected function set($name, $value);
    abstract protected function del($name);
    abstract protected function is_active();
    abstract protected function end();
    abstract protected function destroy();
}

/* session persistant storage with vanilla PHP sessions and no local authentication */
class Hm_PHP_Session extends Hm_Session {

    protected $cname = 'PHPSESSID';

    protected function ciphertext($data) {
        return Hm_Crypt::ciphertext(serialize($data), $this->enc_key);
    }
    protected function plaintext($data) {
        return @unserialize(Hm_Crypt::plaintext($data, $this->enc_key));
    }
    protected function set_key($request) {
        if (isset($request->cookie['hm_id'])) {
            $this->enc_key = base64_decode($request->cookie['hm_id']);
        }
        else {
            $this->enc_key = base64_encode(openssl_random_pseudo_bytes(128));
            setcookie('hm_id', $this->enc_key, 0);
        }
    }

    public function check($request, $config) {
        $this->set_key($request);
        $this->start($request);
    }

    public function auth($user, $pass) {
        return true;
    }

    public function start($request) {
        session_start();
        if (isset($_SESSION['data'])) {
            $data = $this->plaintext($_SESSION['data']);
            if (is_array($data)) {
                $this->data = $data;
            }
        }
        $this->active = true;
    }

    public function get($name, $default=false, $user=false) {
        if ($user) {
            return isset($this->data['user_data'][$name]) ? $this->data['user_data'][$name] : $default;
        }
        else {
            return isset($this->data[$name]) ? $this->data[$name] : $default;
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
        if (isset($this->data[$name])) {
            unset($this->data[$name]);
        }
    }
    public function is_active() {
        return $this->active;
    }

    public function destroy() {
        $this->end();
        session_unset();
        @session_destroy();
        $params = session_get_cookie_params();
        setcookie($this->cname, '', 0, $params['path'], $params['domain'], $params['secure'], isset($params['httponly']));
        setcookie('hm_id', '', 0);
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

    protected $dbh = false;
    protected $required_config = array('db_user', 'db_pass', 'db_name', 'db_host', 'db_driver');
    protected $config = false;

    private function parse_config($config) {
        $res = array();
        foreach ($this->required_config as $v) {
            if (isset($config[$v])) {
                $res[$v] = $config[$v];
            }
        }
        if (count($res) == count($this->required_config)) {
            $this->config = $res;
        }
    }

    public function check($request, $config, $user=false, $pass=false) {
        $this->set_key($request);
        $this->parse_config($config->dump());
        if ($this->config) {
            if ($user && $pass) {
                if ($this->auth($user, $pass)) {
                    Hm_Msgs::add('login accepted, starting PHP session');
                    $this->loaded = true;
                    $this->start($request);
                }
            }
            elseif (isset($request->cookie[$this->cname])) {
                $this->start($request);
            }
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
        Hm_Msgs::add("Invalid username or password");
        return false;
    }

    protected function connect() {
        $dsn = sprintf('%s:host=%s;dbname=%s', $this->config['db_driver'], $this->config['db_host'], $this->config['db_name']);
        try {
            $this->dbh = new PDO($dsn, $this->config['db_user'], $this->config['db_pass']);
        }
        catch (Exception $oops) {
            Hm_Debug::add($oops->getMessage());
            Hm_Msgs::add("An error occurred communicating with the database");
            $this->dbh = false;
            return false;
        }
        return true;
    }

    private function create() {
    }
}

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
                setcookie($this->cname, $this->session_key, 0);
                if ($this->insert_session_row()) {
                    $this->active = true;
                }
            }
            else {
                if (!isset($request->cookie[$this->cname])) {
                    $this->destroy();
                }
                else {
                    $this->session_key = $request->cookie[$this->cname];
                    $sql = $this->dbh->prepare('select data from hm_user_session where hm_id=?');
                    if ($sql->execute(array($this->session_key))) {
                        $results = $sql->fetch();
                        if (isset($results['data'])) {
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
        $sql = $this->dbh->prepare("update hm_user_session set data=? where hm_id=?");
        $enc_data = $this->ciphertext($this->data);
        $sql->execute(array($enc_data, $this->session_key));
        $this->active = false;
    }

    public function destroy() {
        $this->end();
        $sql = $this->dbh->prepare("delete from hm_user_session where hm_id=?");
        $sql->execute(array($this->session_key));
        setcookie($this->cname, '', 0);
        setcookie('hm_id', '', 0);
    }
}
?>
