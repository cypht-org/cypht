<?php

/**
 * POP3 modules
 * @package modules
 * @subpackage pop3
 */

if (!defined('DEBUG_MODE')) { die(); }

/**
 * POP3 connection manager
 * @subpackage pop3/lib
 */
class Hm_POP3_List {
    
    use Hm_Server_List;

    public static function service_connect($id, $server, $user, $pass, $cache=false) {
        self::$server_list[$id]['object'] = new Hm_POP3();
        self::$server_list[$id]['object']->server = $server['server'];
        self::$server_list[$id]['object']->port = $server['port'];
        self::$server_list[$id]['object']->ssl = $server['tls'];

        if (self::$server_list[$id]['object']->connect()) {
            self::$server_list[$id]['object']->auth($user, $pass);
            return self::$server_list[$id]['object'];
        }
        return false;
    }
    public static function get_cache($session, $id) {
        return false;
    }
}

/**
 * Authenticate against a POP3 server
 * @subpackage pop3/lib
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
 * Used to mark messages as "read"
 * @subpackage pop3/lib
 */
class Hm_POP3_Seen_Cache {
    use Hm_Uid_Cache;
}

/**
 * Connect to and interact with POP3 servers
 * @subpackage pop3/lib
 */
class Hm_POP3 {
    var $server;
    var $starttls;
    var $port;
    var $ssl;
    var $debug;
    var $command_count;
    var $commands;
    var $responses;
    var $connected;
    var $banner;
    var $state;
    var $no_apop;
    var $handle;
   
    /* set defaults */ 
    function __construct() {
        $this->debug = array();
        $this->server = 'localhost';
        $this->port = 110; // ssl @ 995
        $this->ssl = false;
        $this->starttls = false;
        $this->no_apop = true;
        $this->command_count = 0;
        $this->commands = array();
        $this->responses = array();
        $this->connected = false;
        $this->state = 'started';
        $this->handle = false;
    }

    /* get server response */
    function get_response($multi_line=false) {
        if ($multi_line) {
            $res = $this->get_multi_line_response();
        }
        else {
            $res = array($this->get_single_line_response());
        }
        return $res;
    }

    /* read in a multi-line response */
    function get_multi_line_response($line_length=8192) {
        $n = -1;
        $result = array();
        if (!is_resource($this->handle)) {
            return $result;
        }
        do {
            if ($n > 0 && $result[$n] == "..\r\n") {
                $result[$n] = ".\r\n";
            }
            $n++;
            $result[$n] = fgets($this->handle, $line_length);
            while(substr($result[$n], -2) != "\r\n") {
                if (!is_resource($this->handle)) {
                    break;
                }
                $result[$n] .= fgets($this->handle, $line_length);
            }
            if ($this->is_error($result)) {
                break;
            }
            if ($n == 0) {
            }
        } while ($result[$n] != ".\r\n");
        return $result;
    }

    /* read in a single line response */
    function get_single_line_response($line_length=512) {
        if (!is_resource($this->handle)) {
            $res = '';
        }
        else {
            $res = fgets($this->handle, $line_length);
        }
        $this->responses[trim($res)] = microtime(true);
        return $res;
    }

    /* send a command string to the server */
    function send_command($command) {
        if (is_resource($this->handle)) {
            fputs($this->handle, $command."\r\n");
        }
        if (preg_match("/^PASS/", $command)) {
            $this->commands['PASS'] = microtime(true);
        }
        else {
            $this->commands[trim($command)] = microtime(true);
        }
    }

    /* establish a connection to the server. */
    function connect() {
        if ($this->ssl) {
            $this->server = 'tls://'.$this->server;
        } 
        $this->debug[] = 'Connecting to '.$this->server.' on port '.$this->port;
        $this->handle = @fsockopen($this->server, $this->port, $errorno, $errorstr, 30);
        if (is_resource($this->handle)) {
            $this->debug[] = 'Successfully opened port to the POP3 server';
            $this->connected = true;
            $this->state = 'connected';
            $res = $this->get_response();
            if (!empty($res)) {
                $this->banner = $res[0];
            }
            $this->commands['Connected'] = microtime(true);
        }
        else {
            $this->debug[] = 'Could not connect to the POP3 server';
            $this->debug[] = 'fsockopen errors #'.$errorno.'. '.$errorstr;
        }
        return $this->connected;
    }

    /* output debug */
    function puke() {
        return print_r(array_merge($this->debug, $this->commands), true);
    }

    /* check the POP3 response code for errors */
    function is_error($response) {
        $index = count($response);
        $error = false;
        if ($index && substr($response[($index - 1)], 0, 3) == '+OK') {
            return false;
        }
        elseif ($index && substr($response[($index - 1)], 0, 4) == '-ERR') {
            $error = substr($response[($index - 1)], 5);
        }
        else {
            if (empty($response)) {
                $error = 'Empty response';
            }
            else {
                $errors = 'Unknown response: '.$response[($index - 1)];
            }
        }
        return $error;
    }

    /* quit an active pop3 session */
    function quit() {
        $this->send_command('QUIT');
        return $this->is_error($this->get_response());
    }

    /* stat a mailbox */
    function mstat() {
        $cnt = 0;
        $size = 0;
        $this->send_command('STAT');
        $res = $this->get_response();
        if ($this->is_error($res) == false) {
            if (preg_match('/^\+OK (\d+) (\d+)/', $res[0], $matches)) {
                $cnt = $matches[1]; 
                $size = $matches[2];
            }
        }
        return array('count' => $cnt, 'size' => $size);
    }

    /* list message ids in a pop3 account */
    function mlist($id=false) {
        $command = 'LIST';
        $multi = true;
        $mlist = array();
        $regex = '/^(\d+) (\d+)/';
        if ($id) {
            $command .= ' '.$id;
            $multi = false;
            $regex = '/^\+OK (\d+) (\d+)/';
        }
        $this->send_command($command);
        $res = $this->get_response($multi);
        if ($this->is_error($res) == false) {
            foreach ($res as $row) {
                if (preg_match($regex, $row, $matches)) {
                    $mlist[$matches[1]] = $matches[2];
                }
            }
        }
        return $mlist;
    }

    /* send the top command for the given message id */
    function top($id) {
        $this->send_command('TOP '.$id.' 0');
        return $this->get_response(true);
    }

    /* parse message headers */
    function msg_headers($id) {
        $lines = $this->top($id);
        $msg_headers = array();
        $current_header = false;
        foreach ($lines as $line) {
            if ($line{0} == "\t" && $current_header) {
                $msg_headers[$current_header] .= ' '.trim($line);
            }
            else {
                $parts = explode(":", $line, 2);
                if (count($parts) == 2) {
                    $msg_headers[strtolower($parts[0])] = trim($parts[1]);
                    $current_header = strtolower($parts[0]);
                }
                else {
                    $current_header = false;
                }
            }
        }
        return $msg_headers;
    }

    /* fetch an entire retr command response */
    function retr_full($id) {
        $this->send_command('RETR '.$id);
        $res = $this->get_response(true);
        return $res;
    }

    /* start a retr command */
    function retr_start($id) {
        $this->send_command('RETR '.$id);
        $res = $this->get_response();
        return $this->is_error($res) == false;
    }

    /* feed results from a retr command */
    function retr_feed() {
        $result = '';
        $line_length = 8192;
        $continue = true;
        $result = fgets($this->handle, $line_length);
        while(substr($result, -2) != "\r\n") {
            if (!is_resource($this->handle)) {
                break;
            }
            $result .= fgets($this->handle, $line_length);
        }
        if ($result == ".\r\n") {
            $continue = false;
            $result = false;
        }
        elseif ($result == "..\r\n") {
            $result = ".\r\n";
        }
        return array($result, $continue);
    }

    /* delete a message */
    function dele($id) {
        $this->send_command('DELE '.$id);
        return $this->is_error($this->get_response()) == false;
    }

    /* send the uidl command */
    function uidl($id=false) {
        $command = 'UIDL';
        $multi = true;
        $uidlist = array();
        $regex = '/^(\d+) (.+)/';
        if ($id) {
            $command .= ' '.$id;
            $multi = false;
            $regex = '/^\+OK (\d+) (.+)/';
        }
        $this->send_command($command);
        $res = $this->get_response($multi);
        if ($this->is_error($res) == false) {
            foreach ($res as $row) {
                if (preg_match($regex, $row, $matches)) {
                    $uidlist[$matches[1]] = trim($matches[2]);
                }
            }
        }
        return $uidlist;
    }

    /* send a noop command */
    function noop() {
        $this->send_command('NOOP');
        return $this->is_error($this->get_response()) == false;
    }

    /* send the rset command */
    function rset() {
        $this->send_command('RSET');
        return $this->is_error($this->get_response()) == false;
    }

    /* send the user command */
    function user($user) {
        $this->send_command('USER '.$user);
        return $this->is_error($this->get_response()) == false;
    }

    /* send the password command */
    function pass($pass) {
        $this->send_command('PASS '.$pass);
        return $this->is_error($this->get_response()) == false;
    }

    /* authenticate to the pop3 server */
    function auth($user, $pass) {
        $res = false;
        if ($this->starttls) {
            $this->send_command('STLS');
            if ($this->is_error($this->get_response()) == false && is_resource($this->handle)) {
                stream_socket_enable_crypto($this->handle, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
            }
        }
        if (!$this->no_apop && preg_match('/<[0-9.]+@[^>]+>/', $this->banner, $matches)) {
            $res = $this->apop($user, $pass, $matches[0]);
        }
        else {
            if ($this->user($user)) {
                $res = $this->pass($pass);
            }
        }
        if ($res) {
            $this->state = 'authed';
        }
        return $res;
    }

    /* send apop command to avoid sending clear text passwords */
    function apop($user, $pass, $challenge) {
        $this->send_command('APOP '.$user.' '.md5($challenge.$pass));
        return $this->is_error($this->get_response()) == false;
    }
}

?>
