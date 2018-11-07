<?php

/**
 * Session handling
 * @package framework
 * @subpackage session
 */

/**
 * This session class uses a Redis to manage session data. It does not
 * use PHP session handlers at all and is a completely indenpendant session system.
 */
class Hm_Redis_Session extends Hm_Memcached_Session {

    /**
     * @return boolean
     */
    public function connect() {
        $this->conn = new Hm_Redis($this->site_config);
        return $this->conn->is_active();
    }
}
