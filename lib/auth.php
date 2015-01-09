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

    /* bool flag defining if users are internal */
    static public $internal_users = false;

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
     * This is the method new auth mechs need to override.
     *
     * @param $user string username
     * @param $pass string password
     *
     * @return bool true if the user is authenticated, false otherwise
     */
    abstract public function check_credentials($user, $pass);
}

/**
 * Used for testing
 */
class Hm_Auth_None extends Hm_Auth {
    public function check_credentials($user, $pass) {
        return true;
    }
    public function create($user, $pass) {
        return true;
    }
}

/**
 * Authenticate against an included DB
 */
class Hm_Auth_DB extends Hm_Auth {

    /* bool flag indicating this is an internal user setup */
    static public $internal_users = true;

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
        sleep(2);
        return false;
    }

    /**
     * Delete a user account from the db
     *
     * @param $user string username
     *
     * @return bool true if successful
     */
    public function delete($user) {
        if ($this->connect()) {
            $sql = $this->dbh->prepare("delete from hm_user where username = ?");
            if ($sql->execute(array($user)) && $sql->rowCount() == 1) {
                return true;
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
        if ($sql->execute(array($hash, $user)) && $sql->rowCount() == 1) {
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
        $created = false;
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
                    $created = true;
                }
            }
        }
        return $created;
    }
}

?>
