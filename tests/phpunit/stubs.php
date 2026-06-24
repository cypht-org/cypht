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
    public $state;
    public function disconnect() {
        return true;
    }
}

class Hm_Server_Wrapper {
    use Hm_Server_List;
    static public $connected = true;
    public static function init($user_config, $session) {
        self::initRepo('test', $user_config, $session, self::$server_list);
    }
    public static function service_connect($id, $server, $user, $pass, $cache) {
        self::$server_list[$id]['object'] = new Hm_Test_Server();
        self::$server_list[$id]['connected'] = true;
        return self::$connected;
    }
}

class Hm_Tags_Wrapper {
    use Hm_Repository;

    private static $data = array();

    public static function init($user_config, $session) {
        self::initRepo('tags', $user_config, $session, self::$data);
    }
}

if (!defined("IMAP_TEST")) {
    class Hm_IMAP {
        static public $allow_connection = true;
        static public $allow_auth = true;
        private $connected = false;
        public function get_state() { if (self::$allow_auth) { return $this->connected ? 'authenticated' : false; } return 'connected'; }
        public function connect() { if (self::$allow_connection) { $this->connected = true; return true; } return false; }
        public function show_debug() {}
    }
}

class Searchable_Wrapper {
    use Searchable;
    
    private static $testData = [
        ['id' => 1, 'name' => 'John Doe', 'email' => 'john@example.com', 'status' => 'active', 'age' => 30],
        ['id' => 2, 'name' => 'Jane Smith', 'email' => 'jane@example.com', 'status' => 'inactive', 'age' => 25],
        ['id' => 3, 'name' => 'Bob Johnson', 'email' => 'bob@example.com', 'status' => 'active', 'age' => 35],
        ['id' => 4, 'name' => 'Alice Brown', 'email' => 'alice@example.com', 'status' => 'pending', 'age' => 28],
        ['id' => 5, 'name' => 'Charlie Wilson', 'email' => 'charlie@example.com', 'status' => 'active', 'age' => 30],
    ];
    
    /**
     * Implementation of the abstract method required by Searchable trait
     */
    protected static function getDataset() {
        return self::$testData;
    }
    
    /**
     * Method to reset test data (useful for testing)
     */
    public static function resetTestData() {
        self::$testData = [
            ['id' => 1, 'name' => 'John Doe', 'email' => 'john@example.com', 'status' => 'active', 'age' => 30],
            ['id' => 2, 'name' => 'Jane Smith', 'email' => 'jane@example.com', 'status' => 'inactive', 'age' => 25],
            ['id' => 3, 'name' => 'Bob Johnson', 'email' => 'bob@example.com', 'status' => 'active', 'age' => 35],
            ['id' => 4, 'name' => 'Alice Brown', 'email' => 'alice@example.com', 'status' => 'pending', 'age' => 28],
            ['id' => 5, 'name' => 'Charlie Wilson', 'email' => 'charlie@example.com', 'status' => 'active', 'age' => 30],
        ];
    }
    
    /**
     * Method to set custom test data
     */
    public static function setTestData(array $data) {
        self::$testData = $data;
    }
}

class Empty_Searchable_Wrapper {
    use Searchable;
    
    protected static function getDataset() {
        return [];
    }
}
