<?php

if (!defined('DEBUG_MODE')) { die(); }

/**
 * Struct that makes it easy for a module set to manage a list of server connections
 */
trait Hm_Server_List {

    /* list of server connections */
    private static $server_list = array();

    /**
     * Server lists must override this method to connect
     *
     * @param $id int server id
     * @param $server string server hostname or ip
     * @param $user string username for authentication
     * @param $pass string password for authentication
     * @param $cache mixed cached connection data
     *
     * @return bool true on success
     */
    abstract public function service_connect($id, $server, $user, $pass, $cache);

    /**
     * Connect to a server
     *
     * @param $id int server id
     * @param $cache mixed cached server data
     * @param $user string username
     * @param $pass string password
     * @param $save_credentials bool true to save the username and password
     *
     * @return mixed connection object on success, otherwise false
     */
    public static function connect($id, $cache=false, $user=false, $pass=false, $save_credentials=false) {
        if (array_key_exists($id, self::$server_list)) {
            $server = self::$server_list[$id];
            if ($server['object']) {
                return $server['object'];
            }
            else {
                if ((!$user || !$pass) && (!array_key_exists('user', $server) || !array_key_exists('pass', $server))) {
                    return false;
                }
                elseif (array_key_exists('user', $server) && array_key_exists('pass', $server)) {
                    $user = $server['user'];
                    $pass = $server['pass'];
                }
                if ($user && $pass) {
                    $res = self::service_connect($id, $server, $user, $pass, $cache);
                    if ($res) {
                        self::$server_list[$id]['connected'] = true;
                        if ($save_credentials) {
                            self::$server_list[$id]['user'] = $user;
                            self::$server_list[$id]['pass'] = $pass;
                        }
                    }
                    return self::$server_list[$id]['object'];
                }
            }
        }
        return false;
    }

    /**
     * Remove the username and password from a connection
     *
     * @param $id int server id
     *
     * @return void
     */
    public static function forget_credentials($id) {
        if (array_key_exists($id, self::$server_list)) {
            unset(self::$server_list[$id]['user']);
            unset(self::$server_list[$id]['pass']);
        }
    }

    /**
     * Add a server definition
     *
     * @param $atts array server details
     * @param $id int server id
     *
     * @return void
     */
    public static function add($atts, $id=false) {
        $atts['object'] = false;
        $atts['connected'] = false;
        if ($id) {
            self::$server_list[$id] = $atts;
        }
        else {
            self::$server_list[] = $atts;
        }
    }

    /**
     * Remove a server
     *
     * @param $id int server id
     *
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
     *
     * @param $id int if not false, return details for this server only
     * @param $full bool true to return passwords for server connections. CAREFUL!
     *
     * @return array server details
     */
    public static function dump($id=false, $full=false) {
        $list = array();
        foreach (self::$server_list as $index => $server) {
            if ($id !== false && $index != $id) {
                continue;
            }
            if ($full) {
                $list[$index] = $server;
            }
            else {
                $list[$index] = array(
                    'name' => $server['name'],
                    'server' => $server['server'],
                    'port' => $server['port'],
                    'tls' => $server['tls']
                );
                if (array_key_exists('user', $server)) {
                    $list[$index]['user'] = $server['user'];
                }
            }
            if ($id !== false) {
                return $list[$index];
            }
        }
        return $list;
    }

    /**
     * Try to disconnect cleanly
     * TODO: this looks suspect. Better look into it
     *
     * @param $id int server id
     *
     * @return void
     */
    public static function clean_up($id=false) {
        foreach (self::$server_list as $index => $server) {
            if ($id !== false && $id != $index) {
                continue;
            }
            if ($server['connected'] && $server['object']) {
                if (method_exists(self::$server_list[$index]['object'], 'disconnect')) {
                    self::$server_list[$index]['object']->disconnect();
                }
                self::$server_list[$index]['connected'] = false;
            }
        }
    }
}

?>
