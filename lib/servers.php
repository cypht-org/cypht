<?php

/**
 * Server list manager
 * @package framework
 * @subpackage servers
 */

/**
 * Struct that makes it easy for a module set to manage a list of server connections
 */
trait Hm_Server_List {

    /* list of server connections */
    private static $server_list = array();

    /**
     * Server lists must override this method to connect
     * @param int $id server id
     * @param string $server server hostname or ip
     * @param string $user username for authentication
     * @param string $pass password for authentication
     * @param mixed $cache cached connection data
     * @return bool true on success
     */
    public abstract function service_connect($id, $server, $user, $pass, $cache);

    /**
     * Connect to a server
     * @param int $id server id
     * @param mixed $cache cached server data
     * @param string $user username
     * @param string $pass password
     * @param bool $save_credentials true to save the username and password
     * @return object|false connection object on success, otherwise false
     */
    public static function connect($id, $cache=false, $user=false, $pass=false, $save_credentials=false) {
        if (!array_key_exists($id, self::$server_list)) {
            return false;
        }
        $server = self::$server_list[$id];
        if ($server['object']) {
            return $server['object'];
        }
        if (($user === false || $pass === false) && (!array_key_exists('user', $server) || !array_key_exists('pass', $server))) {
            return false;
        }
        if (array_key_exists('user', $server) && array_key_exists('pass', $server)) {
            $user = $server['user'];
            $pass = $server['pass'];
        }
        $res = self::service_connect($id, $server, $user, $pass, $cache);
        if ($res) {
            self::$server_list[$id]['connected'] = true;
            if ($save_credentials) {
                self::$server_list[$id]['user'] = $user;
                self::$server_list[$id]['pass'] = $pass;
            }
            return self::$server_list[$id]['object'];
        }
        return false;
    }

    /**
     * Update the oauth2 password and password expiration
     * @param int $id server id
     * @param string $pass new password
     * @param int $expiry new password expiration timestamp
     * @return bool
     */
    public static function update_oauth2_token($id, $pass, $expiry) {
        if (!array_key_exists($id, self::$server_list)) {
            return false;
        }
        if (!array_key_exists('auth', self::$server_list[$id])) {
            return false;
        }
        if (self::$server_list[$id]['auth'] != 'xoauth2') {
            return false;
        }
        self::$server_list[$id]['pass'] = $pass;
        self::$server_list[$id]['expiration'] = $expiry;
        self::$server_list[$id]['object'] = false;
        return true;
    }

    /**
     * Remove the username and password from a connection
     * @param int $id server id
     * @return void
     */
    public static function forget_credentials($id) {
        if (array_key_exists($id, self::$server_list)) {
            unset(self::$server_list[$id]['user']);
            unset(self::$server_list[$id]['pass']);
        }
    }
    /**
     * Toggle the hidden status of a server
     * @param int $id server id
     * @param int $hide bool
     * @return void
     */
    public static function toggle_hidden($id, $hide) {
        if (array_key_exists($id, self::$server_list)) {
            self::$server_list[$id]['hide'] = $hide;
        }
    }

    /**
     * Add a server definition
     * @param array $atts server details
     * @param int $id server id
     * @return void
     */
    public static function add($atts, $id=false) {
        $atts['object'] = false;
        $atts['connected'] = false;
        if ($id !== false) {
            self::$server_list[$id] = $atts;
        }
        else {
            self::$server_list[] = $atts;
        }
    }

    /**
     * Remove a server
     * @param int $id server id
     * @return bool true on success
     */
    public static function del($id) {
        if (array_key_exists($id, self::$server_list)) {
            unset(self::$server_list[$id]);
            return true;
        }
        return false;
    }

    /**
     * Return all server details
     * @param int $id if not false, return details for this server only
     * @param bool $full true to return passwords for server connections. CAREFUL!
     * @return array server details
     */
    public static function dump($id=false, $full=false) {
        $list = array();
        foreach (self::$server_list as $index => $server) {
            if ($id !== false && $index != $id) {
                continue;
            }
            if (!$full) {
                if (!array_key_exists('pass', $server) || !$server['pass']) {
                    $server['nopass'] = true;
                }
                unset($server['pass']);
            }
            $list[$index] = $server;
            if ($id !== false) {
                return $list[$index];
            }
        }
        return $list;
    }

    /**
     * Fetch a server by the username and servername
     * @param string $username the user associated with the server
     * @param string $servername the host associated with the server
     * @return array|false
     */
    public static function fetch($username, $servername) {
        foreach (self::$server_list as $id => $server) {
            if (array_key_exists('user', $server) && array_key_exists('server', $server)) {
                if ($username == $server['user'] && $servername == $server['server']) {
                    if (array_key_exists('pass', $server)) {
                        unset($server['pass']);
                    }
                    $server['id'] = $id;
                    return $server;
                }
            }
        }
        return false;
    }

    /**
     * Try to disconnect cleanly
     * @param int $id server id
     * @return void
     */
    public static function clean_up($id=false) {
        if ($id !== false && array_key_exists($id, self::$server_list)) {
            self::disconnect($id);
        }
        else {
            foreach (self::$server_list as $index => $server) {
                self::disconnect($index);
            }
        }
    }

    /**
     * Disconnect from a server
     * @param int $id the server id to disconnect
     * @return void
     */
    public static function disconnect($id) {
        if (self::$server_list[$id]['connected'] && self::$server_list[$id]['object']) {
            if (method_exists(self::$server_list[$id]['object'], 'disconnect')) {
                self::$server_list[$id]['object']->disconnect();
            }
            self::$server_list[$id]['connected'] = false;
        }
    }
}
