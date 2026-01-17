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

    /* database connection handle */
    public $dbh;

    /**
     * Send the username and password to the configured DB for authentication
     * @param string $user username
     * @param string $pass password
     * @return bool true if authentication worked
     */
    public function check_credentials($user, $pass) {
        $this->connect();
        $row = Hm_DB::execute($this->dbh, 'select hash from hm_user where username = ?', [$user]);
        if ($row && !empty($row['hash']) && Hm_Crypt::check_password($pass, $row['hash'])) {
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
        if (Hm_DB::execute($this->dbh, 'delete from hm_user where username = ?', [$user])) {
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
        if (Hm_DB::execute($this->dbh, 'update hm_user set hash=? where username=?', [$hash, $user])) {
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
        $res = Hm_DB::execute($this->dbh, 'select username from hm_user where username = ?', [$user]);
        if (!empty($res)) {
            //this var will prevent showing print in phpuni tests
            if(!defined('CYPHT_PHPUNIT_TEST_MODE')) {
                print("user {$user} already exists\n");
            }
            $result = 1;
        }
        else {
            $hash = Hm_Crypt::hash_password($pass);
            if (Hm_DB::execute($this->dbh, 'insert into hm_user values(?,?)', [$user, $hash])) {
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
        include_once APP_PATH.'modules/sievefilters/hm-sieve.php';
    }

    /* IMAP authentication server settings */
    private $imap_settings = [];

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
        list($server, $port, $tls, $sieve_config, $sieve_tls_mode) = get_auth_config($this->site_config, 'imap');
        if (!$user || !$pass || !$server || !$port) {
            Hm_Debug::add($imap->show_debug(true));
            Hm_Debug::add('Invalid IMAP auth configuration settings');
            return false;
        }
        $this->imap_settings = ['server' => $server, 'port' => $port,
            'tls' => $tls, 'username' => $user, 'password' => $pass,
            'no_caps' => false, 'blacklisted_extensions' => ['enable'],
            'sieve_config_host' => $sieve_config,
            'sieve_tls' => $sieve_tls_mode
        ];
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
 * Authenticate against an LDAP server
 */
class Hm_Auth_LDAP extends Hm_Auth {

    protected $config = [];
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
        if (
            empty($this->config['enable_tls']) ||
            $this->config['enable_tls'] === false ||
            strtolower($this->config['enable_tls']) === "false"
        ) {
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
        return !empty($this->config[$name]) ? $this->config[$name] : $default;
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
        $uid_auth_attr = $this->site_config->get('ldap_auth_uid_attr', false);
        if ($server && $port && $base_dn) {
            if (strpos($user, '@') !== false || strpos($user, '\\') !== false) {
                $bind_dn = $user;
            } else {
                $bind_dn = sprintf('%s=%s,%s', $uid_auth_attr, $user, $base_dn);
            }
            $this->config = [
                'server' => $server,
                'port' => $port,
                'enable_tls' => $tls,
                'base_dn' => $base_dn,
                'user' => $bind_dn,
                'pass' => $pass
            ];
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
            // Disable LDAP referral following: Active Directory may return referrals for searches
            // across domains or special partitions, but for authentication we should keep the check
            // local to the connected server without following referrals to other DCs
            ldap_set_option($this->fh, LDAP_OPT_REFERRALS, 0);
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
    $ret = [$server, $port, $tls];
    if ($prefix == 'imap') {
        $ret[] = $config->get($prefix.'_auth_sieve_conf_host', false);
        $ret[] = $config->get($prefix.'_auth_sieve_tls_mode', false);
    }
    return $ret;
}
