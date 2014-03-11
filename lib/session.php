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
            $data = unserialize(Hm_Crypt::plaintext($_SESSION['data'], $this->enc_key));
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
        setcookie(session_name(), '', 0, $params['path'], $params['domain'], $params['secure'], isset($params['httponly']));
        $this->active = false;
    }

    public function end() {
        if ($this->active) {
            $enc_data = @Hm_Crypt::ciphertext(serialize($this->data), $this->enc_key);
            $_SESSION = array('data' => $enc_data);
            session_write_close();
            $this->active = false;
        }
    }
}

/* persistant storage with vanilla PHP sessions and DB based authentication */
class Hm_PHP_Session_DB_Auth extends Hm_PHP_Session {

    private $dbh = false;
    private $required_config = array('db_user', 'db_pass', 'db_name', 'db_host', 'db_driver');
    private $config = false;

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
        if ($user && $pass) {
            $this->parse_config($config->dump());
            if ($this->config) {
                if ($this->auth($user, $pass)) {
                    Hm_Msgs::add('login accepted, starting PHP session');
                    $this->loaded = true;
                    $this->start($request);
                }
            }
            else {
                Hm_Debug::add('incomplete DB configuration');
            }
        }
        elseif (!empty($request->cookie) && isset($request->cookie['PHPSESSID'])) {
            $this->start($request);
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

    private function connect() {
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
    public function start($request) {
        if (isset($request->cookie['hm_session'])) {
        }
        else {
        }
        if ($this->connect()) {
            //$sql = $this->dbh->prepare("select hash from hm_user where username = ?");
        }
    }
    public function end() {
    }
}
?>
