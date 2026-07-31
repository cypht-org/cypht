<?php

/**
 * Shared harness and sieve mocks for the sievefilters test files.
 *
 * Not a test file itself - it is require_once'd by each of them. The harness
 * classes mirror Handler_Test / Output_Test in tests/phpunit/helpers.php but
 * are defined here because helpers.php and modules/imap/hm-imap.php both
 * declare Hm_IMAP_List unguarded, and modules/sievefilters/modules.php pulls in
 * the latter, so the two cannot be loaded into one process.
 */

class Sieve_Handler_Test {
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

    public function run() {
        $this->prep();
        $this->module_exec->run_handler_modules($this->req_obj, $this->ses_obj, 'test');
        return $this->module_exec;
    }
}

class Hm_Test_Sieve_Client {
    public static $scripts = array();
    public static $activated = '';
    public static $renamed = array();

    public function listScripts() {
        return array_keys(self::$scripts);
    }

    public function getScript($name) {
        return self::$scripts[$name] ?? '';
    }

    public function putScript($name, $script) {
        self::$scripts[$name] = $script;
        return true;
    }

    public function removeScripts($name) {
        unset(self::$scripts[$name]);
        return true;
    }

    public function activateScript($name) {
        self::$activated = $name;
        return true;
    }

    public function renameScript($name, $prefix) {
        $new_name = $prefix.$name;
        self::$scripts[$new_name] = self::$scripts[$name] ?? '';
        unset(self::$scripts[$name]);
        self::$renamed[] = array($name, $new_name);
        return true;
    }

    public function close() {
        return true;
    }

    public function getErrorMessage() {
        return '';
    }

    public function getExtensions() {
        return array('fileinto', 'reject');
    }

    public function getCapabilities() {
        return array('fileinto', 'reject', 'vacation');
    }
}

class Hm_Test_Sieve_Client_Factory {
    public function init($user_config = null, $imap_account = null, $is_nux_supported = false)
    {
        return new Hm_Test_Sieve_Client();
    }
}

class Hm_Test_Sieve_Client_Failing_Factory {
    public function init($user_config = null, $imap_account = null, $is_nux_supported = false)
    {
        throw new Exception('Test failure');
    }
}

/**
 * Runs a single sievefilters output module against a canned handler response.
 *
 * This mirrors Output_Test in tests/phpunit/helpers.php, but is defined locally
 * for the same reason Sieve_Handler_Test is: helpers.php and
 * modules/imap/hm-imap.php both declare Hm_IMAP_List unguarded, and
 * modules/sievefilters/modules.php pulls in the latter, so the two cannot be
 * loaded into one process.
 */
class Sieve_Output_Test {
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

    public function run() {
        $this->prep();
        $this->module_exec->run_output_modules($this->req_obj, $this->active_session, 'test', $this->handler_response);
        return $this->module_exec;
    }
}

/**
 * Minimal sieve client for the output tests. Named separately from the client in
 * handler_modules.php so both test files can be loaded into the same process.
 */
class Hm_Test_Sieve_Output_Client {
    public static $scripts = array();

    public function listScripts() {
        return array_keys(self::$scripts);
    }

    public function getScript($name) {
        return self::$scripts[$name] ?? '';
    }

    public function getExtensions() {
        return array('fileinto', 'reject');
    }

    public function close() {
        return true;
    }
}

class Hm_Test_Sieve_Output_Client_Factory {
    public function init($user_config = null, $imap_account = null, $is_nux_supported = false) {
        return new Hm_Test_Sieve_Output_Client();
    }
}

/**
 * Stands in for an output module where render_custom_actions_dropdown() only
 * needs trans(). Returning the key verbatim keeps assertions translation-proof.
 */
class Hm_Test_Sieve_Output_Trans_Stub {
    public function trans($string) {
        return $string;
    }
}
