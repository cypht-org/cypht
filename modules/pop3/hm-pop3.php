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

    /**
     * Connect to a POP3 server
     * @param int $id server id
     * @param array $server server details
     * @param string $user username
     * @param string $pass password
     * @param array $cache server cache
     * @return bool
     */
    public static function service_connect($id, $server, $user, $pass, $cache=false) {
        self::$server_list[$id]['object'] = new Hm_POP3();
        if ($cache && is_array($cache)) {
            self::$server_list[$id]['object']->load_cache($cache);

        }
        self::$server_list[$id]['object']->server = $server['server'];
        self::$server_list[$id]['object']->port = $server['port'];
        self::$server_list[$id]['object']->ssl = $server['tls'];

        if (self::$server_list[$id]['object']->connect()) {
            self::$server_list[$id]['object']->auth($user, $pass);
            return self::$server_list[$id]['object'];
        }
        return false;
    }

    /**
     * Get a server cache
     * @param object $session session object
     * @param int $id server id
     * @return mixed
     */
    public static function get_cache($hm_cache, $id) {
        $res = $hm_cache->get('pop3'.$id);
        return $res;
    }
}

/**
 * Used to mark messages as "read"
 * @subpackage pop3/lib
 */
class Hm_POP3_Uid_Cache {
    use Hm_Uid_Cache;
}

/* for unit testing */
if (!class_exists('Hm_POP3')) {

/**
 * Connect to and interact with POP3 servers
 * @subpackage pop3/lib
 */
class Hm_POP3_Base {

    /**
     * Get server response
     * @param bool $multi_line multi-line flag
     * @return string
     */
    protected function get_response($multi_line=false) {
        if ($multi_line) {
            $res = $this->get_multi_line_response();
        }
        else {
            $res = array($this->get_single_line_response());
        }
        return $res;
    }

    /**
     * Read in a multi-line response
     * @param int $line_length max read length
     * @return string
     */
    protected function get_multi_line_response($line_length=8192) {
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

    /**
     * Read in a single line response
     * @param int $line_length max read length
     * @return string
     */
    protected function get_single_line_response($line_length=512) {
        if (!is_resource($this->handle)) {
            $res = '';
        }
        else {
            $res = fgets($this->handle, $line_length);
        }
        $this->responses[trim($res)] = microtime(true);
        return $res;
    }

    /**
     * Send a command string to the server
     * @param string $command POP3 command
     * @return void
     */
    protected function send_command($command) {
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

    /**
     * Establish a connection to the server.
     * @return bool
     */
    public function connect() {
        if ($this->ssl) {
            $this->server = 'tls://'.$this->server;
        } 
        $this->debug[] = 'Connecting to '.$this->server.' on port '.$this->port;
        $ctx = stream_context_create();
        $this->handle = Hm_Functions::stream_socket_client($this->server, $this->port, $errorno, $errorstr, 30, STREAM_CLIENT_CONNECT, $ctx);
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

}

class Hm_POP3 extends Hm_POP3_Base {
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
   
    /**
     * Set defaults
     * @return void
     */
    public function __construct() {
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
        $this->cache = array();
        $this->handle = false;
    }

    /**
     * Output debug
     * @return string
     */
    public function puke() {
        return print_r(array_merge($this->debug, $this->commands, $this->responses), true);
    }

    /**
     * Check the POP3 response code for errors
     * @param string $response POP3 response
     * @return bool
     */
    protected function is_error($response) {
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

    /**
     * Check for a cached response
     * @param string $command the POP3 command to check for
     * @return  mixed
     */
    public function check_cache($command) {
        if (array_key_exists($command, $this->cache)) {
            Hm_Debug::add('POP3 cache hit for '.$command);
            return $this->cache[$command];
        }
        return false;
    }

    /**
     * Cache a response
     * @param string $command command to cache data for
     * @param mixed $response data to cache
     * @return mixed
     */
    public function cache_response($command, $response) {
        $this->cache[$command] = $response;
        return $response;
    }

    /**
     * Dump the cache
     * @return array
     */
    public function dump_cache() {
        return $this->cache;
    }

    /**
     * Load the cache
     */
    public function load_cache($data) {
        $this->cache = $data;
    }

    /**
     * Quit an active pop3 session
     * @return bool
     */
    public function quit() {
        $this->send_command('QUIT');
        return $this->is_error($this->get_response());
    }

    /**
     * Stat a mailbox
     * @return array
     */
    public function mstat() {
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

    /**
     * List message ids in a pop3 account
     * @param int $id message id
     * @return array
     */
    public function mlist($id=false) {
        $command = 'LIST';
        $multi = true;
        $mlist = array();
        $regex = '/^(\d+) (\d+)/';
        if ($id) {
            $command .= ' '.$id;
            $multi = false;
            $regex = '/^\+OK (\d+) (\d+)/';
        }
        $cache = $this->check_cache($command);
        if ($cache) {
            return $cache;
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
        return $this->cache_response($command, $mlist);
    }

    /**
     * Send the top command for the given message id 
     * @param int $id message id
     * @return string
     */
    public function top($id) {
        $command = 'TOP '.$id.' 0';
        $cache = $this->check_cache($command);
        if ($cache) {
            return $cache;
        }
        $this->send_command($command);
        return $this->cache_response($command, $this->get_response(true));
    }

    /**
     * Parse message headers
     * @param int $id message id
     * @return array
     */
    public function msg_headers($id) {
        $lines = $this->top($id);
        $msg_headers = array();
        $current_header = false;
        foreach ($lines as $line) {
            if (($line[0] == "\t" || $line[0] == " ") && $current_header) {
                $msg_headers[$current_header] .= decode_fld($line);
            }
            else {
                $parts = explode(":", $line, 2);
                if (count($parts) == 2) {
                    $msg_headers[strtolower($parts[0])] = decode_fld($parts[1]);
                    $current_header = strtolower($parts[0]);
                }
                else {
                    $current_header = false;
                }
            }
        }
        return $msg_headers;
    }

    /**
     * Fetch an entire retr command response
     * @param int $id message id
     * @return string
     */
    public function retr_full($id) {
        $command = 'RETR '.$id;
        $cache = $this->check_cache($command);
        if ($cache) {
            return $cache;
        }
        $this->send_command($command);
        $res = $this->get_response(true);
        return $this->cache_response($command, $res);
    }

    /**
     * Start a retr command
     * @param int $id message id
     * @return bool
     */
    public function retr_start($id) {
        $this->send_command('RETR '.$id);
        $res = $this->get_response();
        return $this->is_error($res) == false;
    }

    /**
     * Feed results from a retr command
     * @return array
     */
    public function retr_feed() {
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

    /**
     * Delete a message
     * @param int $id message id
     * @return bool
     */
    public function dele($id) {
        $this->send_command('DELE '.$id);
        return $this->is_error($this->get_response()) == false;
    }

    /**
     * Send the uidl command
     * @param int $id message id
     * @return array
     */
    public function uidl($id=false) {
        $command = 'UIDL';
        $multi = true;
        $uidlist = array();
        $regex = '/^(\d+) (.+)/';
        if ($id) {
            $command .= ' '.$id;
            $multi = false;
            $regex = '/^\+OK (\d+) (.+)/';
        }
        $cache = $this->check_cache($command);
        if ($cache) {
            return $cache;
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
        return $this->cache_response($command, $uidlist);
    }

    /**
     * Send a noop command
     * @return bool
     */
    public function noop() {
        $this->send_command('NOOP');
        return $this->is_error($this->get_response()) == false;
    }

    /**
     * Send the rset command
     * @return bool
     */
    public function rset() {
        $this->send_command('RSET');
        return $this->is_error($this->get_response()) == false;
    }

    /**
     * Send the user command
     * @param string $user username
     * @return bool
     */
    public function user($user) {
        $this->send_command('USER '.$user);
        return $this->is_error($this->get_response()) == false;
    }

    /**
     * Send the password command
     * @param string $pass password
     * @return bool
     */
    public function pass($pass) {
        $this->send_command('PASS '.$pass);
        return $this->is_error($this->get_response()) == false;
    }

    /**
     * Authenticate to the pop3 server
     * @param string $user username
     * @param string $pass password
     * @return bool
     */
    public function auth($user, $pass) {
        $res = false;
        if ($this->starttls) {
            $this->send_command('STLS');
            if ($this->is_error($this->get_response()) == false && is_resource($this->handle)) {
                Hm_Functions::stream_socket_enable_crypto($this->handle, get_tls_stream_type());
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

    /**
     * Send apop command to avoid sending clear text passwords
     * @param string $user username
     * @param string $pass password
     * @param string $challenge
     * @return bool
     */
    public function apop($user, $pass, $challenge) {
        $this->send_command('APOP '.$user.' '.md5($challenge.$pass));
        return $this->is_error($this->get_response()) == false;
    }
}

}


