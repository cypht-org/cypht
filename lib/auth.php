<?php

/**
 * Authentication classes
 * @package framework
 * @subpackage auth
 */

/**
 * Base class for authentication
 * Creating a new authentication method requires extending this class
 * and overriding the check_credentials method
 * @abstract
 */
abstract class Hm_Auth {

    /* site configuration object */
    protected $site_config;

    /* bool flag defining if users are internal */
    static public $internal_users = false;

    /**
     * Assign site config
     * @param object $config site config
     */
    public function __construct($config) {
        $this->site_config = $config;
    }

    /**
     * This is the method new auth mechs need to override.
     * @param string $user username
     * @param string $pass password
     * @return bool true if the user is authenticated, false otherwise
     */
    abstract public function check_credentials($user, $pass);

    /**
     * Optional method for auth mech to save login details
     * @param object $session session object
     * @return void
     */
    public function save_auth_detail($session) {}
}

/**
 * Stub for dynamic authentication
 */
class Hm_Auth_Dynamic extends Hm_Auth {
    public function check_credentials($user, $pass) {
        return false;
    }
}
/**
 * Authenticate against an included DB
 */
class Hm_Auth_DB extends Hm_Auth {

    /* bool flag indicating this is an internal user setup */
    static public $internal_users = true;

    /* database conneciton handle */
    public $dbh;

    /**
     * Send the username and password to the configured DB for authentication
     * @param string $user username
     * @param string $pass password
     * @return bool true if authentication worked
     */
    public function check_credentials($user, $pass) {
        $this->connect();
        $row = Hm_DB::execute($this->dbh, 'select hash from hm_user where username = ?', array($user));
        if ($row && array_key_exists('hash', $row) && $row['hash'] && Hm_Crypt::check_password($pass, $row['hash'])) {
            return true;
        }
        sleep(2);
        Hm_Debug::add(sprintf('DB AUTH failed for %s', $user));
        return false;
    }

    /**
     * Delete a user account from the db
     * @param string $user username
     * @return bool true if successful
     */
    public function delete($user) {
        $this->connect();
        if (Hm_DB::execute($this->dbh, 'delete from hm_user where username = ?', array($user))) {
            return true;
        }
        return false;
    }

    /**
     * Create a new or re-use an existing DB connection
     * @return bool true if the connection is available
     */
    protected function connect() {
        $this->dbh = Hm_DB::connect($this->site_config);
        if ($this->dbh) {
            return true;
        }
        Hm_Debug::add(sprintf('Unable to connect to the DB auth server %s', $this->site_config->get('db_host')));
        return false;
    }

    /**
     * Change the password for a user in the DB
     * @param string $user username
     * @param string $pass password
     * @return bool true on success
     */
    public function change_pass($user, $pass) {
        $this->connect();
        $hash = Hm_Crypt::hash_password($pass);
        if (Hm_DB::execute($this->dbh, 'update hm_user set hash=? where username=?', array($hash, $user))) {
            return true;
        }
        return false;
    }

    /**
     * Create a new user in the DB
     * @param string $user username
     * @param string $pass password
     * @return integer
     */
    public function create($user, $pass) {
        $this->connect();
        $result = 0;
        $res = Hm_DB::execute($this->dbh, 'select username from hm_user where username = ?', array($user));
        if (!empty($res)) {
            $result = 1;
        }
        else {
            $hash = Hm_Crypt::hash_password($pass);
            if (Hm_DB::execute($this->dbh, 'insert into hm_user values(?,?)', array($user, $hash))) {
                $result = 2;
            }
        }
        return $result;
    }
}

/**
 * Authenticate against an IMAP server
 */
class Hm_Auth_IMAP extends Hm_Auth {

    /**
     * Assign site config, get required libs
     * @param object $config site config
     */
    public function __construct($config) {
        $this->site_config = $config;
        require_once APP_PATH.'modules/imap/hm-imap.php';
    }

    /* IMAP authentication server settings */
    private $imap_settings = array();

    /**
     * @param object $imap imap connection object
     * @return boolean
     */
    private function check_connection($imap) {
        $imap->connect($this->imap_settings);
        if ($imap->get_state() == 'authenticated') {
            return true;
        }
        elseif ($imap->get_state() != 'connected') {
            Hm_Debug::add($imap->show_debug(true));
            Hm_Debug::add(sprintf('Unable to connect to the IMAP auth server %s', $this->imap_settings['server']));
            return false;
        }
        else {
            Hm_Debug::add($imap->show_debug(true));
            Hm_Debug::add(sprintf('IMAP AUTH failed for %s', $this->imap_settings['username']));
            return false;
        }
    }

    /**
     * Send the username and password to the configured IMAP server for authentication
     * @param string $user username
     * @param string $pass password
     * @return bool true if authentication worked
     */
    public function check_credentials($user, $pass) {
        $imap = new Hm_IMAP();
        list($server, $port, $tls) = get_auth_config($this->site_config, 'imap');
        if (!$user || !$pass || !$server || !$port) {
            Hm_Debug::add($imap->show_debug(true));
            Hm_Debug::add('Invalid IMAP auth configuration settings');
            return false;
        }
        $this->imap_settings = array('server' => $server, 'port' => $port,
            'tls' => $tls, 'username' => $user, 'password' => $pass,
            'no_caps' => false, 'blacklisted_extensions' => array('enable')
        );
        return $this->check_connection($imap);
    }

    /**
     * Save IMAP server details
     * @param object $session session object
     * @return void
     */
    public function save_auth_detail($session) {
        $session->set('imap_auth_server_settings', $this->imap_settings);
    }
}

/**
 * Authenticate against a POP3 server
 */
class Hm_Auth_POP3 extends Hm_Auth {

    /* POP3 authentication server settings */
    private $pop3_settings = array();

    /**
     * Assign site config, get required libs
     * @param object $config site config
     */
    public function __construct($config) {
        $this->site_config = $config;
        require_once APP_PATH.'modules/pop3/hm-pop3.php';
    }

    /**
     * @param object $pop3 pop3 connection object
     * @param string $user username to login with
     * @param string $pass password to login with
     * @param string $server server to login to
     * @return boolean
     */
    private function check_connection($pop3, $user, $pass, $server) {
        if (!$pop3->connect()) {
            Hm_Debug::add($pop3->puke());
            Hm_Debug::add(sprintf('Unable to connect to the POP3 auth server %s', $server));
            return false;
        }
        if (!$pop3->auth($user, $pass)) {
            Hm_Debug::add($pop3->puke());
            Hm_Debug::add(sprintf('POP3 AUTH failed for %s', $user));
            return false;
        }
        return true;
    }

    /**
     * Send the username and password to the configured POP3 server for authentication
     * @param string $user username
     * @param string $pass password
     * @return bool true if authentication worked
     */
    public function check_credentials($user, $pass) {
        $pop3 = new Hm_POP3();
        list($server, $port, $tls) = get_auth_config($this->site_config, 'pop3');
        if ($user && $pass && $server && $port) {
            $this->pop3_settings = array(
                'server' => $server,
                'port' => $port,
                'tls' => $tls,
                'username' => $user,
                'password' => $pass,
                'no_caps' => true
            );
            $pop3->server = $server;
            $pop3->port = $port;
            $pop3->tls = $tls;
            return $this->check_connection($pop3, $user, $pass, $server);
        }
        Hm_Debug::add($pop3->puke());
        Hm_Debug::add('Invalid POP3 auth configuration settings');
        return false;
    }

    /**
     * Save POP3 server details
     * @param object $session session object
     * @return void
     */
    public function save_auth_detail($session) {
        $session->set('pop3_auth_server_settings', $this->pop3_settings);
    }
}

/**
 * Authenticate against an LDAP server
 */
class Hm_Auth_LDAP extends Hm_Auth {

    protected $config = array();
    protected $fh;
    protected $source = 'ldap';

    /**
     * Build connect uri
     * @return string
     */
    private function connect_details() {
        $prefix = 'ldaps://';
        $server = $this->apply_config_value('server', 'localhost');
        $port = $this->apply_config_value('port', 389);
        if (array_key_exists('enable_tls', $this->config) && !$this->config['enable_tls']) {
            $prefix = 'ldap://';
        }
        return $prefix.$server.':'.$port;
    }

    /**
     * @param string $name
     * @param mixed $default
     * @return mixed
     */
    private function apply_config_value($name, $default) {
        if (array_key_exists($name, $this->config) && trim($this->config[$name])) {
            return $this->config[$name];
        }
        return $default;
    }

    /**
     * Check a username and password
     * @param string $user username
     * @param string $pass password
     * @return boolean
     */
    public function check_credentials($user, $pass) {
        list($server, $port, $tls) = get_auth_config($this->site_config, 'ldap');
        $base_dn = $this->site_config->get('ldap_auth_base_dn', false);
        if ($server && $port && $base_dn) {
            $user = sprintf('cn=%s,%s', $user, $base_dn);
            $this->config = array(
                'server' => $server,
                'port' => $port,
                'enable_tls' => $tls,
                'base_dn' => $base_dn,
                'user' => $user,
                'pass' => $pass
            );
            return $this->connect();
        }
        Hm_Debug::add('Invalid LDAP auth configuration settings');
        return false;
    }

    /**
     * Connect and auth to the LDAP server
     * @return boolean
     */
    public function connect() {
        if (!Hm_Functions::function_exists('ldap_connect')) {
            return false;
        }
        $uri = $this->connect_details();
        $this->fh = @ldap_connect($uri);
        if ($this->fh) {
            ldap_set_option($this->fh, LDAP_OPT_PROTOCOL_VERSION, 3);
            return $this->auth();
        }
        Hm_Debug::add(sprintf('Unable to connect to the LDAP auth server %s', $this->config['server']));
        return false;
    }

    /**
     * Authenticate to the LDAP server
     * @return boolean
     */
    protected function auth() {
        $result = @ldap_bind($this->fh, $this->config['user'], $this->config['pass']);
        ldap_unbind($this->fh);
        if (!$result) {
            Hm_Debug::add(sprintf('LDAP AUTH failed for %s', $this->config['user']));
        }
        return $result;
    }
}

/*
 * @param object $config site config object
 * @param string $prefix settings prefix
 * @return array
 */
function get_auth_config($config, $prefix) {
    $server = $config->get($prefix.'_auth_server', false);
    $port = $config->get($prefix.'_auth_port', false);
    $tls = $config->get($prefix.'_auth_tls', false);
    return array($server, $port, $tls);
}
