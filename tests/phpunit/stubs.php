<?php

class Test_Uid_Cache {
    use Hm_Uid_Cache;
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
    public static function service_connect($id, $server, $user, $pass, $cache) {
        self::$server_list[$id]['object'] = new Hm_Test_Server();
        self::$server_list[$id]['connected'] = true;
        return true;
    }
}

class Hm_IMAP {
    private $connected = false;
    public function get_state() { return $this->connected ? 'authenticated' : false; }
    public function connect() { $this->connected = true; return true; }
}

class Hm_POP3 {
    public function connect() { return true; }
    public function auth() { return true; }
}


?>
