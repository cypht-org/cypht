<?php

/* persistant storage between pages, abstract interface */
abstract class Hm_Session {

    public $active = false;
    public $loaded = false;

    public function __construct($request, $config) {
        $this->check($request, $config);
    }

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
class Hm_Session_PHP extends Hm_Session {

    public function check($request, $config) {
        if (!isset($request->cookie['PHPSESSID'])) {
            Hm_Msgs::add('starting new PHP session');
        }
        $this->start($request);
    }

    public function auth($user, $pass) {
        return true;
    }

    public function start($request) {
        session_start();
        $this->active = true;
    }

    public function get($name, $default=false) {
        return isset($_SESSION[$name]) ? $_SESSION[$name] : $default;
    }

    public function set($name, $value) {
        $_SESSION[$name] = $value;
    }

    public function del($name) {
        if (isset($_SESSION[$name])) {
            unset($_SESSION[$name]);
        }
    }
    public function is_active() {
        return $this->active;
    }

    public function destroy() {
        session_unset();
        @session_destroy();
        $params = session_get_cookie_params();
        setcookie(session_name(), '', 0, $params['path'], $params['domain'], $params['secure'], isset($params['httponly']));
        $this->active = false;
    }

    public function end() {
        session_write_close();
        $this->active = false;
    }
}

/* persistant storage with vanilla PHP sessions and DB based authentication
 *
 * Postgresql:
 * create table hm_user (username varchar(255) primary key not null, hash varchar(255));
 *
 * Mysql:
 * create table hm_user (username varchar(250), hash varchar(250), PRIMARY KEY (username));
 *
 */
class Hm_Session_PHP_DB_Auth extends Hm_Session_PHP {

    private $db_user = 'test';
    private $db_pass = '123456';
    private $db_name = 'test';
    private $db_host = '127.0.0.1';
    private $db_driver = 'mysql';
    private $dbh = false;

    public function check($request, $config) {
        if (isset($request->post['username']) && $request->post['username'] &&
            isset($request->post['password']) && $request->post['password']) {
            if ($this->auth($request->post['username'], $request->post['password'])) {
                Hm_Msgs::add('login accepted, starting PHP session');
                $this->loaded = true;
                $this->start($request);
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
        $dsn = sprintf('%s:host=%s;dbname=%s', $this->db_driver, $this->db_host, $this->db_name);
        try {
            $this->dbh = new PDO($dsn, $this->db_user, $this->db_pass);
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
?>
