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
class Hm_Memcached_Session extends Hm_PHP_Session {

    /* session key */
    public $session_key = '';

    /* memcached connection */
    public $conn = false;

    /* default session lifetime */
    public $lifetime = 86400;

    /**
     * Start the session. This could be an existing session or a new login
     * @param object $request request details
     * @return void
     */
    public function start($request) {
        $this->connect();
        if ($this->conn->active()) {
            if ($this->loaded) {
                $this->start_new($request);
            }
            elseif (!array_key_exists($this->cname, $request->cookie)) {
                $this->destroy($request);
            }
            else {
                $this->start_existing($request, $request->cookie[$this->cname]);
            }
        }
    }

    /**
     * Start a new session
     * @param object $request request details
     * @return void
     */
    public function start_new($request) {
        $this->session_key = Hm_Crypt::unique_id(); 
        $this->secure_cookie($request, $this->cname, $this->session_key, 0);
        if ($this->save_data()) {
            $this->active = true;
            return;
        }
        Hm_Debug::add('MEM SESSION failed to start a new session');
    }

    /**
     * Continue an existing session
     * @param object $request request details
     * @param string $key session key
     * @return void
     */
    public function start_existing($request, $key) {
        $this->session_key = $key;
        $data = $this->plaintext($this->conn->get($key));
        if (is_array($data)) {
            $this->active = true;
            $this->data = $data;
        }
    }

    /**
     * update memcache with current data
     */
    public function save_data() {
        $enc_data = $this->ciphertext($this->data);
        return $this->conn->set($this->session_key, $enc_data, $this->lifetime);
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
        $this->secure_cookie($request, $this->cname, '', time()-3600);
        $this->secure_cookie($request, 'hm_id', '', time()-3600);
        $this->session_closed = true;
        $this->conn->close();
        $this->active = false;
        Hm_Request_Key::load($this, $request, false);
    }

    public function connect() {
        $this->conn = new Hm_Memcached($this->site_config);
    }
}
