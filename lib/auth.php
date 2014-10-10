<?php

if (!defined('DEBUG_MODE')) { die(); }

/**
 * Base class for authentication
 *
 * Creating a new authentication method requires extending this class
 * and overriding the check_credentials method
 */
abstract class Hm_Auth {

    /* site configuration object */
    protected $site_config = false;

    /* bool flag defining if we are responsible for user auth */
    public $internal_users = false;

    /**
     * Assign site config
     *
     * @param $config object site config
     *
     * @return void
     */
    public function __construct($config) {
        $this->site_config = $config;
    }

    /**
     * Method new auth mechs need to override.
     *
     * @param $user string username
     * @param $pass string password
     *
     * @return bool true if the user is authenticated, false otherwise
     */
    abstract public function check_credentials($user, $pass);
}

/**
 * Auth class that always returns true. DANGER - only for testing!
 */
class Hm_Auth_None extends Hm_Auth {

    /**
     * Allow all requests to be authenticated
     *
     * @param $user string username
     * @param $pass string password
     */
    public function check_credentials($user, $pass) {
        return true;
    }
}

/**
 * Authenticate against a POP3 server
 * TODO: move to the POP3 module set
 */
class Hm_Auth_POP3 extends Hm_Auth {

    /* POP3 authentication server settings */
    private $pop3_settings = array();

    /**
     * Send the username and password to the configured POP3 server for authentication
     *
     * @param $user string username
     * @param $pass string password
     *
     * @return bool true if authentication worked
     */
    public function check_credentials($user, $pass) {
        $pop3 = new Hm_POP3();
        $authed = false;
        list($server, $port, $tls) = $this->get_pop3_config();
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
            if ($pop3->connect()) {
                $authed = $pop3->auth($user, $pass);
            }
        }
        if ($authed) {
            return true;
        }
        Hm_Msgs::add("Invalid username or password");
        return false;
    }

    /**
     * Get POP3 server details from the site config
     *
     * @return array list of required details
     */
    private function get_pop3_config() {
        $server = $this->site_config->get('pop3_auth_server', false);
        $port = $this->site_config->get('pop3_auth_port', false);
        $tls = $this->site_config->get('pop3_auth_tls', false);
        return array($server, $port, $tls);
    }
}

/**
 * Authenticate against an IMAP server
 * TODO: move to the IMAP module set
 */
class Hm_Auth_IMAP extends Hm_Auth {

    /* IMAP authentication server settings */
    private $imap_settings = array();

    /**
     * Send the username and password to the configured IMAP server for authentication
     *
     * @param $user string username
     * @param $pass string password
     *
     * @return bool true if authentication worked
     */
    public function check_credentials($user, $pass) {
        $imap = new Hm_IMAP();
        list($server, $port, $tls) = $this->get_imap_config();
        if ($user && $pass && $server && $port) {
            $this->imap_settings = array(
                'server' => $server,
                'port' => $port,
                'tls' => $tls,
                'username' => $user,
                'password' => $pass,
                'no_caps' => true,
                'blacklisted_extensions' => array('enable')
            );
            $imap->connect($this->imap_settings);
        }
        if ($imap->get_state() == 'authenticated') {
            return true;
        }
        else {
            Hm_Msgs::add("Invalid username or password");
        }
        return false;
    }

    /**
     * Get IMAP server details from the site config
     *
     * @return array list of required details
     */
    private function get_imap_config() {
        $server = $this->site_config->get('imap_auth_server', false);
        $port = $this->site_config->get('imap_auth_port', false);
        $tls = $this->site_config->get('imap_auth_tls', false);
        return array($server, $port, $tls);
    }
}

/**
 * Authenticate against an included DB
 */
class Hm_Auth_DB extends Hm_Auth {

    /* bool flag indicating this is an internal user setup */
    public $internal_users = true;

    /**
     * Send the username and password to the configured DB for authentication
     *
     * @param $user string username
     * @param $pass string password
     *
     * @return bool true if authentication worked
     */
    public function check_credentials($user, $pass) {
        if ($this->connect()) {
            $sql = $this->dbh->prepare("select hash from hm_user where username = ?");
            if ($sql->execute(array($user))) {
                $row = $sql->fetch();
                if ($row['hash'] && pbkdf2_validate_password($pass, $row['hash'])) {
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * Create a new or re-use an existing DB connection
     *
     * @return bool true if the connection is available
     */
    protected function connect() {
        $this->dbh = Hm_DB::connect($this->site_config);
        if ($this->dbh) {
            return true;
        }
        return false;
    }

    /**
     * Change the password for a user in the DB
     *
     * @param $user string username
     * @param $pass string password
     *
     * @return bool true on success
     */
    public function change_pass($user, $pass) {
        $this->connect();
        $hash = pbkdf2_create_hash($pass);
        $sql = $this->dbh->prepare("update hm_user set hash=? where username=?");
        if ($sql->execute(array($hash, $user))) {
            Hm_Msgs::add("Password changed");
            return true;
        }
        return false;
    }

    /**
     * Create a new user in the DB
     *
     * @param $request object request details
     * @param $user string username
     * @param $pass string password
     */
    public function create($user, $pass) {
        $this->connect();
        $sql = $this->dbh->prepare("select username from hm_user where username = ?");
        if ($sql->execute(array($user))) {
            $res = $sql->fetch();
            if (!empty($res)) {
                Hm_Msgs::add("ERRThat username is already in use");
            }
            else {
                $sql = $this->dbh->prepare("insert into hm_user values(?,?)");
                $hash = pbkdf2_create_hash($pass);
                if ($sql->execute(array($user, $hash))) {
                    Hm_Msgs::add("Account created");
                }
            }
        }
    }
}

?>
