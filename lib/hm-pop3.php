<?php

if (!defined('DEBUG_MODE')) { die(); }

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
    var $handle;
   
    /* set defaults */ 
    function pop3() {
        $this->debug = array();
        $this->server = 'localhost';
        $this->port = 110; // ssl @ 995
        $this->ssl = false;
        $this->starttls = false;
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
    function top($id) {
        $this->send_command('TOP '.$id);
        return $this->get_response(true);
    }
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
    function retr_full($id) {
        $this->send_command('RETR '.$id);
        $res = $this->get_response(true);
        return $res;
    }
    function retr_start($id) {
        $this->send_command('RETR '.$id);
        $res = $this->get_response();
        return $this->is_error($res) == false;
    }
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
    function dele($id) {
        $this->send_command('DELE '.$id);
        return $this->is_error($this->get_response()) == false;
    }
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
    function noop() {
        $this->send_command('NOOP');
        return $this->is_error($this->get_response()) == false;
    }
    function rset() {
        $this->send_command('RSET');
        return $this->is_error($this->get_response()) == false;
    }
    function user($user) {
        $this->send_command('USER '.$user);
        return $this->is_error($this->get_response()) == false;
    }
    function pass($pass) {
        $this->send_command('PASS '.$pass);
        return $this->is_error($this->get_response()) == false;
    }
    function auth($user, $pass) {
        $res = false;
        if ($this->starttls) {
            $this->send_command('STLS');
            if ($this->is_error($this->get_response()) == false && is_resource($this->handle)) {
                stream_socket_enable_crypto($this->handle, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
            }
        }
        if (preg_match('/<[0-9.]+@[^>]+>/', $this->banner, $matches)) {
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
    function apop($user, $pass, $challenge) {
        $this->send_command('APOP '.$user.' '.md5($challenge.$pass));
        return $this->is_error($this->get_response()) == false;
    }
}

?>
