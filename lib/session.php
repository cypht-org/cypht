<?php

/* persistant storage between pages, abstract interface */
abstract class Hm_Session {

    public $active = false;
    public $loaded = false;
    protected $enc_key = '';
    protected $data = array();
    protected $cname = false;
    protected $site_config = false;

    abstract protected function check($request);
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

    public function __construct($config) {
        $this->site_config = $config;
    }

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

    public function check($request) {
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

    public function check($request, $user=false, $pass=false) {
        $this->set_key($request);
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
        $this->dbh = Hm_DB::connect($this->site_config);
        if ($this->dbh) {
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
                Hm_Msgs::add("That username is already in use");
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
        if ($this->dbh) {
            $sql = $this->dbh->prepare("update hm_user_session set data=? where hm_id=?");
            $enc_data = $this->ciphertext($this->data);
            $sql->execute(array($enc_data, $this->session_key));
        }
        $this->active = false;
    }

    public function destroy() {
        $this->end();
        if ($this->dbh) {
            $sql = $this->dbh->prepare("delete from hm_user_session where hm_id=?");
            $sql->execute(array($this->session_key));
        }
        setcookie($this->cname, '', 0);
        setcookie('hm_id', '', 0);
    }
}

trait Hm_POP3_Auth {
    public function auth($user, $pass) {
        if (!class_exists('Hm_POP3')) {
            require 'lib/hm-pop3.php';
        }
        $pop3 = new Hm_POP3();
        $authed = false;
        list($server, $port, $tls) = $this->get_pop3_config();
        if ($user && $pass && $server && $port) {
            $pop3->server = $server;
            $pop3->port = $port;
            $pop3->tls = $tls;
            if ($pop3->connect()) {
                $authed = $pop3->auth($user, $pass);
            }
        }
        if ($authed) {
            return true;
        }
        Hm_Msgs::add("Invalid username or password");
        return false;
    }
    private function get_pop3_config() {
        $server = $this->site_config->get('pop3_auth_server', false);
        $port = $this->site_config->get('pop3_auth_port', false);
        $tls = $this->site_config->get('pop3_auth_tls', false);
        return array($server, $port, $tls);
    }

}

trait Hm_IMAP_Auth {

    public function auth($user, $pass) {
        if (!class_exists('Hm_IMAP')) {
            require 'lib/hm-imap.php';
        }
        $imap = new Hm_IMAP();
        list($server, $port, $tls) = $this->get_imap_config();
        if ($user && $pass && $server && $port) {
            $imap->connect(array(
                'server' => $server,
                'port' => $port,
                'tls' => $tls,
                'username' => $user,
                'password' => $pass,
                'no_caps' => true,
                'blacklisted_extensions' => array('enable')
            ));
        }
        if ($imap->get_state() == 'authenticated') {
            return true;
        }
        else {
            Hm_Msgs::add("Invalid username or password");
        }
        return false;
    }
    private function get_imap_config() {
        $server = $this->site_config->get('imap_auth_server', false);
        $port = $this->site_config->get('imap_auth_port', false);
        $tls = $this->site_config->get('imap_auth_tls', false);
        return array($server, $port, $tls);
    }
}

/* persistant storage with vanilla PHP sessions and IMAP based authentication */
class Hm_PHP_Session_IMAP_Auth extends Hm_PHP_Session_DB_Auth {
    use Hm_IMAP_Auth;
}

/* persistant storage with vanilla PHP sessions and POP3 based authentication */
class Hm_PHP_Session_POP3_Auth extends Hm_PHP_Session_DB_Auth {
    use Hm_POP3_Auth;
}

/* persistant storage with custom DB sessions and IMAP based authentication */
class Hm_DB_Session_IMAP_Auth extends Hm_DB_Session_DB_Auth {
    use Hm_IMAP_Auth;
}

/* persistant storage with custom DB sessions and POP3 based authentication */
class Hm_DB_Session_POP3_Auth extends Hm_DB_Session_DB_Auth {
    use Hm_POP3_Auth;
}

?>
