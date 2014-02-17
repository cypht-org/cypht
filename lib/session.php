<?php

/* persistant storage between pages, abstract interface */
abstract class Hm_Session {

    public $active = false;
    public $loaded = false;

    public function __construct($request) {
        $this->check($request);
    }

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

/* persistant storage with vanilla PHP sessions */
class Hm_Session_PHP extends Hm_Session{

    public function check($request) {
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
?>
