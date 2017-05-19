<?php

/**
 * Session handling
 * @package framework
 * @subpackage session
 */

/**
 * This session class uses a PDO compatible DB to manage session data. It does not
 * use PHP session handlers at all and is a completely indenpendant session system.
 */
class Hm_DB_Session extends Hm_PHP_Session {

    /* session key */
    public $session_key = '';

    /* DB handle */
    protected $dbh;

    /**
     * Create a new session
     * @return bool true on success
     */
    public function insert_session_row() {
        if ($this->dbh) {
            $sql = $this->dbh->prepare("insert into hm_user_session values(?, ?, current_date)");
            $enc_data = $this->ciphertext($this->data);
            return $sql->execute(array($this->session_key, $enc_data));
        }
        return false;
    }

    /**
     * Connect to the configured DB
     * @return bool true on success
     */
    public function connect() {
        $this->dbh = Hm_DB::connect($this->site_config);
        if ($this->dbh) {
            return true;
        }
        return false;
    }

    /**
     * Start the session. This could be an existing session or a new login
     * @param object $request request details
     * @return void
     */
    public function start($request) {
        if ($this->connect()) {
            if ($this->loaded) {
                $this->start_new_session($request);
            }
            else if (!array_key_exists($this->cname, $request->cookie)) {
                $this->destroy($request);
            }
            else {
                $this->start_existing($request->cookie[$this->cname]);
            }
        }
    }

    /**
     * Start a new session
     * @param object $request request details
     * @return void
     */
    public function start_new_session($request) {
        $this->session_key = Hm_Crypt::unique_id(); 
        $this->secure_cookie($request, $this->cname, $this->session_key, 0);
        if ($this->insert_session_row()) {
            $this->active = true;
        }
    }

    /**
     * Continue an existing session
     * @param string $key session key
     * @return void
     */
    public function start_existing($key) {
        $this->session_key = $key;
        $data = $this->get_session_data($key);
        if (is_array($data)) {
            $this->active = true;
            $this->data = $data;
        }
    }

    /**
     * Get session data from the DB
     * @param string $key session key
     * @return mixed array results or false on failure
     */
    public function get_session_data($key) {
        $sql = $this->dbh->prepare('select data from hm_user_session where hm_id=?');
        if ($sql->execute(array($key))) {
            $results = $sql->fetch();
            if (is_array($results) && array_key_exists('data', $results)) {
                return $this->plaintext($results['data']);
            }
        }
        Hm_Debug::add('DB SESSION failed to read session data');
        return false;
    }

    /**
     * End a session after a page request is complete. This only closes the session and
     * does not destroy it
     * @return void
     */
    public function end() {
        if (!$this->session_closed && $this->active) {
            $this->save_data();
        }
        $this->active = false;
    }

    /**
     * Write session data to the db
     * @return void
     */
    public function save_data() {
        if ($this->dbh) {
            $sql = $this->dbh->prepare("update hm_user_session set data=? where hm_id=?");
            $enc_data = $this->ciphertext($this->data);
            return $sql->execute(array($enc_data, $this->session_key));
        }
        Hm_Debug::add('DB SESSION failed to write session data');
        return false;
    }

    /**
     * Close a session early, but don't destroy it
     * @return void
     */
    public function close_early() {
        $this->session_closed = true;
        $this->save_data();
    }

    /**
     * Destroy a session for good
     * @param object $request request details
     * @return void
     */
    public function destroy($request) {
        if (Hm_Functions::function_exists('delete_uploaded_files')) {
            delete_uploaded_files($this);
        }
        if ($this->dbh) {
            $sql = $this->dbh->prepare("delete from hm_user_session where hm_id=?");
            $sql->execute(array($this->session_key));
        }
        $this->secure_cookie($request, $this->cname, false, time()-3600);
        $this->secure_cookie($request, 'hm_id', false, time()-3600);
        $this->secure_cookie($request, 'hm_reload_folders', false, time()-3600);
        $this->secure_cookie($request, 'hm_msgs', false, time()-3600);
        $this->active = false;
        Hm_Request_Key::load($this, $request, false);
    }
}
