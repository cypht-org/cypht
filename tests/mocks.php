<?php

class Hm_Mock_Session {
    public $data = array();

    public function get($id, $default) {
        if ($id == 'saved_pages') {
            return array('foo' => array('bar', false));
        }
        elseif (array_key_exists($id, $this->data)) {
            return $this->data[$id];
        }
        return $default;
    }
    public function set($name, $value) {
        $this->data[$name] = $value;
    }
    public function build_fingerprint($request, $site_key) {
        return 'fakefingerprint';
    }
}
class Hm_Mock_Config {

    public $data = array();

    public function get($id, $default) {
        if ($id == 'user_settings_dir') {
            return './data';
        }
        elseif (array_key_exists($id, $this->data)) {
            return $this->data[$id];
        }
        return $default;
    }
    public function set($name, $value) {
        $this->data[$name] = $value;
    }
}

function setup_db($config) {
    $config->set('db_driver', 'mysql');
    $config->set('db_host', '127.0.0.1');
    $config->set('db_name', 'test');
    $config->set('db_user', 'test');
    $config->set('db_pass', '123456');
}

?>
