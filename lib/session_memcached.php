<?php

/**
 * Session handling
 * @package framework
 * @subpackage session
 */

/**
 * This session class uses a memcached to manage session data. It does not
 * use PHP session handlers at all and is a completely indenpendant session system.
 */
class Hm_Memcached_Session extends Hm_DB_Session {

    /* session key */
    public $session_key = '';

    /* memcached connection */
    public $conn;

    /* default session lifetime */
    public $cache_lifetime = 86400;

    /**
     * Start the session. This could be an existing session or a new login
     * @param object $request request details
     * @return void
     */
    public function start($request, $existing_session=False) {
        $this->db_start($request);
    }

    /**
     * save data on session start
     * @return boolean|integer|array
     */
    public function insert_session_row() {
        return $this->save_data();
    }

    /**
     * update memcache with current data
     * @return boolean|integer|array
     */
    public function save_data() {
        $enc_data = $this->ciphertext($this->data);
        return $this->conn->set($this->session_key, $enc_data, $this->cache_lifetime);
    }

    /**
     * End a session after a page request is complete. This only closes the session and
     * does not destroy it
     * @return void
     */
    public function end() {
        if ($this->active && !$this->session_closed) {
            $this->save_data();
            $this->conn->close();
        }
        $this->active = false;
    }

    /**
     * Close a session early, but don't destroy it
     * @return void
     */
    public function close_early() {
        $this->session_closed = true;
        $this->save_data();
        $this->conn->close();
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
        $this->conn->del($this->session_key);
        $this->delete_cookie($request, $this->cname);
        $this->delete_cookie($request, 'hm_id');
        $this->session_closed = true;
        $this->conn->close();
        $this->active = false;
        Hm_Request_Key::load($this, $request, false);
    }

    /**
     * @return boolean
     */
    public function connect() {
        $this->conn = new Hm_Memcached($this->site_config);
        return $this->conn->is_active();
    }

    /**
     * Get session data from the DB
     * @param string $key session key
     * @return mixed array results or false on failure
     */
    public function get_session_data($key) {
        return $this->plaintext($this->conn->get($key));
    }

}
