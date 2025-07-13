<?php

class Output_Test {
    public $handler_response = array();
    public $active_session = true;
    public $req_obj = false;
    public $rtype = 'HTTP';
    public $mod;
    public $set;
    public $module_exec;

    public function __construct($name, $set) {
        $this->mod = $name;
        $this->set = $set;
    }
    public function prep() {
        $config = new Hm_Mock_Config();
        $this->req_obj = new Hm_Mock_Request($this->rtype);
        if ($this->rtype == 'AJAX') {
            $this->req_obj->format = 'Hm_Format_JSON';
        }
        $this->module_exec = new Hm_Module_Exec($config);
        Hm_Output_Modules::add('test', $this->mod, false, false, false, true, $this->set);
    }
    public function run_only() {
        $this->module_exec->run_output_modules($this->req_obj, $this->active_session, 'test', $this->handler_response);
        return $this->module_exec;
    }
    public function run() {
        $this->prep();
        return $this->run_only();
    }
}
class Handler_Test {
    public $post = array();
    public $get = array();
    public $user_config = array();
    public $config = array();
    public $input = array();
    public $modules = array();
    public $mod = false;
    public $tls = false;
    public $rtype = 'HTTP';
    public $session = array();
    public $req_obj = false;
    public $ses_obj = false;
    public $set;
    public $module_exec;

    public function __construct($name, $set) {
        $this->mod = $name;
        $this->set = $set;
    }
    public function prep() {
        $config = new Hm_Mock_Config();
        $config->mods = $this->modules;
        foreach ($this->config as $name => $val) {
            $config->set($name, $val);
        }
        $this->module_exec = new Hm_Module_Exec($config);
        $this->module_exec->user_config = new Hm_Mock_Config();
        foreach ($this->user_config as $name => $val) {
            $this->module_exec->user_config->set($name, $val);
        }
        $this->req_obj = new Hm_Mock_Request($this->rtype);
        $this->req_obj->tls = $this->tls;
        $this->req_obj->post = $this->post;
        $this->req_obj->get = $this->get;
        $this->ses_obj = new Hm_Mock_Session();
        foreach ($this->session as $name => $val) {
            $this->ses_obj->set($name, $val);
        }
        Hm_Handler_Modules::add('test', $this->mod, false, false, false, true, $this->set);
        $this->module_exec->handler_response = $this->input;
        Hm_Server_Wrapper::init($this->module_exec->user_config, $this->ses_obj);
    }
    public function run_only() {
        $this->module_exec->run_handler_modules($this->req_obj, $this->ses_obj, 'test');
        return $this->module_exec;
    }
    public function run() {
        $this->prep();
        return $this->run_only();
    }
}
class Hm_SMTP_List extends Hm_Server_Wrapper {
    public static $state = '';
    public static function connect($id, $cache=false, $user=false, $pass=false, $save_credentials=false) {
        self::$server_list[$id]['object'] = new Hm_Test_Server();
        self::$server_list[$id]['connected'] = true;
        self::$server_list[$id]['object']->state = self::$state;
        return self::$server_list[$id]['object'];
    }
    public static function change_state($val) {
        self::$state = $val;
    }
}
class Hm_IMAP_List extends Hm_Server_Wrapper {
    public static $state = false;
    public static function connect($id, $cache=false, $user=false, $pass=false, $save_credentials=false) {
        global $user_config, $session;
        Hm_IMAP::$allow_connection = self::$state;
        Hm_IMAP::$allow_auth = self::$state;
        self::$server_list[$id]['object'] = new Hm_Mailbox($id, $user_config, $session, ['type' => 'imap']);
        self::$server_list[$id]['object']->connect();
        self::$server_list[$id]['connected'] = true;
        return self::$server_list[$id]['object'];
    }
    public static function change_state($val) {
        if ($val == 'authed') {
            self::$state = true;
        }
        else {
            self::$state = false;
        }
    }
}
