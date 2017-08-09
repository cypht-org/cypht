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
     * @return boolean|integer|array
     */
    public function insert_session_row() {
        return $this->upsert('insert');
    }

    /**
     * Connect to the configured DB
     * @return bool true on success
     */
    public function connect() {
        return ($this->dbh = Hm_DB::connect($this->site_config)) ? true : false;
    }

    /**
     * Start the session. This could be an existing session or a new login
     * @param object $request request details
     * @return void
     */
    public function start($request) {
        $this->db_start($request);
    }

    /**
     * Start a new session
     * @param object $request request details
     * @return void
     */
    public function start_new($request) {
        $this->session_key = Hm_Crypt::unique_id(); 
        $this->secure_cookie($request, $this->cname, $this->session_key);
        if ($this->insert_session_row()) {
            Hm_Debug::add('LOGGED IN');
            $this->active = true;
            return;
        }
        Hm_Debug::add('Failed to start a new session');
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
            Hm_Debug::add('LOGGED IN');
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
        $results = Hm_DB::execute($this->dbh, 'select data from hm_user_session where hm_id=?', array($key));
        if (is_array($results) && array_key_exists('data', $results)) {
            return $this->plaintext($results['data']);
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
     * @return boolean|integer|array
     */
    public function save_data() {
        return $this->upsert('update');
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
     * Update or insert a row
     * @param string $type type of action (insert or update)
     * @return boolean|integer|array
     */
    public function upsert($type) {
        $res = false;
        $params = array(':key' => $this->session_key, ':data' => $this->ciphertext($this->data));
        if ($type == 'update') {
            $res = Hm_DB::execute($this->dbh, 'update hm_user_session set data=:data where hm_id=:key', $params);
        }
        elseif ($type == 'insert') {
            $res = Hm_DB::execute($this->dbh, 'insert into hm_user_session values(:key, :data, current_date)', $params);
        }
        if (!$res) {
            Hm_Debug::add('DB SESSION failed to write session data');
        }
        return $res;
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
        Hm_DB::execute($this->dbh, 'delete from hm_user_session where hm_id=?', array($this->session_key));
        $this->delete_cookie($request, $this->cname);
        $this->delete_cookie($request, 'hm_id');
        $this->delete_cookie($request, 'hm_reload_folders');
        $this->delete_cookie($request, 'hm_msgs');
        $this->active = false;
        Hm_Request_Key::load($this, $request, false);
    }

    /**
     * Start the session. This could be an existing session or a new login
     * @param object $request request details
     * @return void
     */
    public function db_start($request) {
        if ($this->connect()) {
            if ($this->loaded) {
                $this->start_new($request);
            }
            elseif (!array_key_exists($this->cname, $request->cookie)) {
                $this->destroy($request);
            }
            else {
                $this->start_existing($request->cookie[$this->cname]);
            }
        }
    }
}
