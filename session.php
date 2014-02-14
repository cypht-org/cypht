<?php

/* persistant storage between pages, abstract interface */
abstract class Hm_Session {

    public function __construct($request) {
        $this->check($request);
    }

    abstract protected function check($request);
    abstract protected function start($request);
    abstract protected function get($name, $default=false);
    abstract protected function set($name, $value);
    abstract protected function del($name);
    abstract protected function end();
    abstract protected function destroy();
}

/* persistant storage with vanilla PHP sessions */
class Hm_Session_PHP extends Hm_Session{

    public function check($request) {
        $this->start($request);
    }

    public function start($request) {
        session_start();
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

    public function destroy() {
        session_unset();
        session_destroy();
    }

    public function end() {
        session_write_close();
    }
}
?>
