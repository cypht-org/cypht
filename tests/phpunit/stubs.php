<?php

class Test_Uid_Cache {
    use Hm_Uid_Cache;
}

class Hm_Auth_None extends Hm_Auth {
    /**
     * This is the method new auth mechs need to override.
     * @param string $user username
     * @param string $pass password
     * @return bool true if the user is authenticated, false otherwise
     */
    public function check_credentials($user, $pass) {
        return true;
    }

    /*
     * Create a new user
     * @param string $user username
     * @param string $pass password
     * @return bool
     */
    public function create($user, $pass) {
        return true;
    }
}

class Hm_Handler_test_mod extends Hm_Handler_Module {
    public function process() {
        $this->out('test', 'foo');
    }
}

class Hm_Handler_Test extends Hm_Handler_Module {
    public function process() {
        return true;
    }
}

class Hm_Output_Test extends Hm_Output_Module {
    public function output() {
        return '';
    }
}

class Hm_Test_Module_List {
    use Hm_Modules;
}
class Hm_Test_Module_List_Functions {
    use Hm_Modules;
}

class Hm_Test_Server {
    public function disconnect() {
        return true;
    }
}

class Hm_Server_Wrapper {
    use Hm_Server_List;
    static public $connected = true;
    public static function service_connect($id, $server, $user, $pass, $cache) {
        self::$server_list[$id]['object'] = new Hm_Test_Server();
        self::$server_list[$id]['connected'] = true;
        return self::$connected;
    }
}

class Hm_IMAP {
    static public $allow_connection = true;
    static public $allow_auth = true;
    private $connected = false;
    public function get_state() { if (self::$allow_auth) { return $this->connected ? 'authenticated' : false; } return 'connected'; }
    public function connect() { if (self::$allow_connection) { $this->connected = true; return true; } return false; }
    public function show_debug() {}
}

class Hm_POP3 {
    static public $allow_connection = true;
    static public $allow_auth = true;
    public function connect() { if (self::$allow_connection) { return true; } return false; }
    public function auth() { if (self::$allow_auth) { return true; } return false; }
    public function puke() {}
}


?>
