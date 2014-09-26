<?php

if (!defined('DEBUG_MODE')) { die(); }

class smtp {
    var $server;
    var $starttls;
    var $port;
    var $tls;
    var $auth;
    var $handle;
    var $debug;
    var $hostname;
    var $command_count;
    var $commands;
    var $responses;
    var $smtp_err;
    var $banner;
    var $capability;
    var $connected;
    var $state;
    var $crlf;
    var $line_length;
    var $username;
    var $password;
   
    /* set defaults */ 
    function smtp() {
        global $conf;
        global $user;
        $this->hostname = $conf['host_name'];
        /* SMTP servers (exchange at least) don't like port numbers in the EHLO hostname */
        if (preg_match("/:\d+$/", $this->hostname)) {
            $this->hostname = substr($this->hostname, 0, strpos($this->hostname, ':'));
        }
        $this->debug = array();
        if (isset($conf['smtp_server'])) {
            $this->server = $conf['smtp_server'];
        }
        else {
            $this->server = '127.0.0.1';
        }
        if (isset($conf['smtp_port'])) {
            $this->port = $conf['smtp_port'];
        }
        else {
            $this->port = 25;
        }
        if (isset($conf['smtp_starttls'])) {
            $this->starttls = $conf['smtp_starttls'];
        }
        else {
            $this->starttls = false;
        }
        if (isset($conf['smtp_tls']) && $conf['smtp_tls']) {
            $this->tls = true;
        }
        else {
            $this->tls = false;
        }
        $this->smtp_err = '';
        $this->supports_tls = false;
        $this->auth = false;
        $this->supports_auth = array();
        $this->handle = false;
        $this->state = 'started';
        $this->command_count = 0;
        $this->commands = array();
        $this->responses = array();
        $this->banner = '';
        $this->crlf = "\r\n";
        $this->capability = '';
        $this->line_length = 2048;
        $this->connected = false;
        $this->username = false;
        $this->password = false;
        $this->max_message_size = 0; //in bytes; 0 = no limit
        if (isset($conf['smtp_authentication_type'])) {
            switch (strtolower($conf['smtp_authentication_type'])) {
                case 'plain':
                case 'login':
                case 'cram-md5':
                    $pass_bits = $user->string_decrypt($_SESSION['user_data']['pass']);
                    if (is_array($pass_bits) && isset($pass_bits[1])) {
                        $this->password = $pass_bits[1];
                        $this->auth = $conf['smtp_authentication_type'];
                        $this->username = $_SESSION['user_data']['username'];
                    }
                    break;
                case 'user':
                    if (isset($_SESSION['user_settings']['smtp_auth']) && $_SESSION['user_settings']['smtp_auth'] != 'none') {
                        $this->auth = $_SESSION['user_settings']['smtp_auth'];
                        $this->password = $_SESSION['user_settings']['smtp_pass'];
                        $this->username = $_SESSION['user_settings']['smtp_user'];
                    }
                    break;
            }
        }
    }

    /* send command to the server. Append "\r\n" to the end. */
    function send_command($command) {
        if (is_resource($this->handle)) {
            fputs($this->handle, $command.$this->crlf);
        }
        $this->commands[] = trim($command);
    }

    /* loop through "lines" returned from smtp and parse
       them. It can return the lines in a raw format, or 
       parsed into atoms. 
    */
    function get_response($chunked=true) {
        $n = -1;
        $result = array();
        $chunked_result = array();
        do {
            $n++;
            if (!is_resource($this->handle)) {
                break;
            }
            $result[$n] = fgets($this->handle, $this->line_length);
            $chunks = $this->parse_line($result[$n]);
            if ($chunked) {
                $chunked_result[] = $chunks;
            }
            if (!trim($result[$n])) {
                unset($result[$n]);
                break;
            }
            $cont = false;
            if (strlen($result[$n]) > 3 && substr($result[$n], 3, 1) == '-') {
                $cont = true;
            }
        } while ($cont);
        $this->responses[] = $result;
        if ($chunked) {
            $result = $chunked_result;
        }
        return $result;
    }

    /* parse out a line */
    function parse_line($line) {
        $parts = array();

        $code = substr($line, 0, 3);
        $parts[] = $code;

        $remainder = explode(' ',substr($line, 4));
        $parts[] = $remainder;

        return $parts;
        
    }
    /* Checks if the numeric response matches the code in $check. 
       The return value is simalar to strcmp
       Returns <0 if $check is less than the response
       Returns  0 if $check is equal to the response
       Returns >0 if $check is greater than the response
    */
    function compare_response($chunked_response, $check) {
        $size = count($chunked_response);
        if ($size) {
            $last = $chunked_response[$size-1];
            $code = $last[0];
        }
        else {
            $code = false;
        }
        $return_val = strcmp($check,$code);
        if ($return_val) {
            if (isset($chunked_response[0][1])) {
                $this->smtp_err = join(' ', $chunked_response[0][1]);
            }
        }
        return $return_val;
    }

    /* determine what capabilities the server has.
       Pass it the chunked response from EHLO  */
    function capabilities($ehlo_response) {
        foreach($ehlo_response as $line) {
            $feature = trim($line[1][0]);
            switch(strtolower($feature)) {
                case 'starttls': // supports starttls
                    $this->supports_tls = true;
                    break;
                case 'auth.': // supported auth mechanisims
                    $auth_mecs = array_slice($line[1], 1);
                    $this->supports_auth = $auth_mecs;
                    break;
                case 'size': // advisary maximum message size
                    if(isset($line[1][1]) && is_numeric($line[1][1])) {
                        $this->max_message_size = $line[1][1];
                    }
                    break;
            }
        }

    }

    /* establish a connection to the server. */
    function connect($servers_attempted=array()) {
        global $smtp_server_pool;
        global $user;
        global $phpversion;
        $result = 'An error occured connecting to the SMTP server';

        if ($smtp_server_pool) {
            // get the list of available smtp servers to connect to
            $available_servers = array_diff(explode(',', $this->server), $servers_attempted);
            $server = trim($available_servers[array_rand($available_servers)]);
        }
        else {
            $server = $this->server;
        }

        if ($this->tls) {
            $server = 'tls://'.$server;
        } 
        $this->debug[] = 'Connecting to '.$server.' on port '.$this->port;
        $this->handle = @fsockopen($server, $this->port, $errorno, $errorstr, 30);
        if (is_resource($this->handle)) {
            $this->debug[] = 'Successfully opened port to the SMTP server';
            $this->connected = true;
            $this->state = 'connected';
        }
        else {
            $this->debug[] = 'Could not connect to the SMTP server';
            $this->debug[] = 'fsockopen errors #'.$errorno.'. '.$errorstr;
            $result = 'Could not connect to the configured SMTP server';
        }
        $this->banner = $this->get_response();
        $command = 'EHLO '.$this->hostname;
        $this->send_command($command);
        $response = $this->get_response();
        $this->capabilities($response);
        if ($this->starttls && $this->supports_tls) {
            if ($phpversion < 5) {
                $result = 'You must have PHP5 to use STARTTLS';
            }
            else {
                $command = 'STARTTLS';
                $this->send_command($command);
                $response = $this->get_response();
                if ($this->compare_response($response, '220') != 0) {
                    $result = 'An error occured during the STARTTLS command';
                }
            }
            if(isset($user->certfile) && $user->certfile) {
                stream_context_set_option($this->handle, 'tls', 'local_cert', $user->certfile);
                if($user->certpass) {
                    stream_context_set_option($this->handle, 'tls', 'passphrase', $user->certpass);
                }
            }
            stream_socket_enable_crypto($this->handle, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
            $command = 'EHLO '.$this->hostname;
            $this->send_command($command);
            $response = $this->get_response();
            $this->capabilities($response);
        }
        if($this->compare_response($response,'250') != 0) {
            $result = 'An error occured during the EHLO command';
        }
        else {
            if($this->auth) {
                //$mech = $this->choose_auth();
                $mech = $this->auth;
                $result = $this->authenticate($this->username, $this->password, $mech);
            }
            else {
                if ($this->state == 'connected') {
                    $result = false;
                }
            }
        }

        // failed to connect
        if ( $this->state != 'connected' && $this->state != 'authed' ) {

            // recurse if there are more smtp servers to try connecting to
            if ($smtp_server_pool && count($available_servers) > 1 ) {
                array_push($servers_attempted, $server);
                return $this->connect($servers_attempted);
            }

        }

        return $result;
    }


    /* Choose an auth mech to use.  The mech choosen is the most secure
        of the intersection of what we support and what the server supports.
        The user may optionally reduce the list of what we support, to 
        eliminate the use of unwanted mechs (ie, PLAIN).  If there is no
        intersection, the last mech (least preferred) is choosen, since
        this will generally be considered the most comptabile for a last
        ditch effort.
    */ 
    function choose_auth() {
        global $user;
        $requested = array('cram-md5','login','plain');
        if (!empty($user->smtp_mechs)) {
            $requested = array();
            foreach($user->smtp_mechs as $m) {
                $m = strtolower($m);
                if($m == 'external' ||
                   $m == 'cram-md5' ||
                   $m == 'login' ||
                   $m == 'plain') {
                     $requested[] = $m;
                }
            }
        }
        else {
            if($this->tls && $this->cert) {
                array_unshift($requested,'external');
            }
        }
        $intersect = array_intersect($requested,$this->supports_auth);
        if(count($intersect) > 0) {
            return $intersect[0];
        }
        // No common mechs, so choose the last of the requested mechs
        return $requested[ count($requested)-1 ];
    }

    /* authenticate the username and password to the server */
    function authenticate($username, $password, $mech) {
        $result = false;
        switch (strtolower($mech)) {
            case 'external':
                $command = 'AUTH EXTERNAL '.base64_encode($username);
                $this->send_command($command);
                break;
            case 'cram-md5':
                $command = 'AUTH CRAM-MD5';
                $this->send_command($command);
                $response = $this->get_response();
                if (empty($response) || !isset($response[0][1][0]) || $this->compare_response($response,'334') != 0) {
                    $result = 'FATAL: SMTP server does not support AUTH CRAM-MD5';
                }
                else {
                    $challenge = base64_decode(trim($response[0][1][0]));
                    $password .= str_repeat(chr(0x00), (64-strlen($password)));
                    $ipad = str_repeat(chr(0x36), 64);
                    $opad = str_repeat(chr(0x5c), 64);
                    $digest = bin2hex(pack('H*', md5(($password ^ $opad).pack('H*', md5(($password ^ $ipad).$challenge)))));
                    $command = base64_encode($username.' '.$digest);
                    $this->send_command($command);
                }
                break;
            case 'ntlm':
                $command = 'AUTH NTLM '.$this->build_ntlm_type_one();
                $this->send_command($command);
                $response = $this->get_response();
                if (empty($response) || !isset($response[0][1][0]) || $this->compare_response($response,'334') != 0) {
                    $result = 'FATAL: SMTP server does not support AUTH NTLM';
                }
                else {
                    $ntlm_res = $this->parse_ntlm_type_two($response[0][1][0]);
                    $command = $this->build_ntlm_type_three($ntlm_res, $username, $password);
                    $this->send_command($command);
                }
                break;
            case 'login':
                $command = 'AUTH LOGIN';
                $this->send_command($command);
                $response = $this->get_response();
                if (empty($response) || $this->compare_response($response,'334') != 0) {
                    $result =  'FATAL: SMTP server does not support AUTH LOGIN';
                }
                else {
                    $command = base64_encode($username);
                    $this->send_command($command);
                    $response = $this->get_response();
                    if (empty($response) || $this->compare_response($response,'334') != 0) {
                        $result = 'FATAL: SMTP server does not support AUTH LOGIN';
                    }
                    $command = base64_encode($password);
                    $this->send_command($command);
                }
                break;
            case 'plain':
                $command = 'AUTH PLAIN '.base64_encode("\0".$username."\0".$password);
                $this->send_command($command);
                break;
            default:
                $result = 'FATAL: Unknown SMTP AUTH mechanism: '.$mech;
                exit;
        }
        if (!$result) {
            $result = 'An error occured authenticating to the SMTP server';
            $res = $this->get_response();
            if ($this->compare_response($res, '235') == 0) {
                $this->state = 'authed';
                $result = false;
            }
            else {
                $result = 'Authorization failure';
                if (isset($res[0][1])) {
                    $result .= ': '.implode(' ', $res[0][1]);
                }
            }
        }
        return $result;
    }
    /* parse NTLM challenge string */
    function parse_ntlm_type_two($bin_str) {
        $res = array();
        $res['vals'] = unpack('a8prefix/Vtype/vname_len/vname_space/Vname_offset/Vflags/A8challenge/A8context/vtarget_len/vtarget_space/Vtarget_offset', base64_decode($bin_str));
        $res['name'] = unpack('A'.$res['vals']['name_len'].'name', substr(base64_decode($bin_str), $res['vals']['name_offset'], $res['vals']['name_len']));
        $target = substr(base64_decode($bin_str), $res['vals']['target_offset'], $res['vals']['target_len']);
        $flds = array(2 => 'domain', 1 => 'server', 4 => 'dns_domain', 3 => 'dns_server');
        $names = array('domain' => '', 'server' => '', 'dns_domain' => '', 'dns_server' => '');
        while ($target) {
            $atts = unpack('vfld/vlen', $target);
            if ($atts['fld'] == 0) {
                break;
            }
            $fld = unpack('A'.$atts['len'], substr($target, 4));
            if (isset($flds[$atts['fld']])) {
                $names[$flds[$atts['fld']]] = $fld;
            }
            $target = substr($target, (4 + $atts['len']));
        }
        $res['names'] = $names;
        return $res;
    }
    /* build initial NTLM message string */
    function build_ntlm_type_one() {
        $pre = 'NTLMSSP'.chr(0);
        $type = pack('V', 1);
        $flags = pack('V', 0x00000201);
        return base64_encode($pre.$type.$flags);
    }
    /* build NTLM challenge response string */
    function build_ntlm_type_three($msg_data, $username, $password) {
        $username = iconv('UTF-8', 'UTF-16LE', $username);
        $target = $msg_data['name']['name'];
        $host = iconv('UTF-8', 'UTF-16LE', php_uname('n'));
        $pre = 'NTLMSSP'.chr(0);
        $type = pack('V', 3);
        $lm_response = $this->build_lm_response($msg_data, $username, $password);
        $ntlm_response = $this->build_ntlm_response($msg_data, $username, $password);
        $flags = pack('V', 0x00000201);
        $offset = strlen($pre.$type)+52;
        $target_sec = $this->ntlm_security_buffer(strlen($target), $offset);
        $offset += strlen($target);
        $user_sec = $this->ntlm_security_buffer(strlen($username), $offset);
        $offset += strlen($username);
        $host_sec = $this->ntlm_security_buffer(strlen($host), $offset);
        $offset += strlen($host);
        $lm_sec = $this->ntlm_security_buffer(strlen($lm_response), $offset);
        $offset += strlen($lm_response);
        $ntlm_sec = $this->ntlm_security_buffer(strlen($ntlm_response), $offset);
        $offset += strlen($ntlm_response);
        $sess_sec = $this->ntlm_security_buffer(0, $offset);
        return base64_encode($pre.$type.$lm_sec.$ntlm_sec.$target_sec.$user_sec.$host_sec.$sess_sec.$flags.$target.$username.$host.$lm_response.$ntlm_response);
    }
    /* build an NTLM "security buffer" for the type 3 response string */
    function ntlm_security_buffer($len, $offset) {
        return pack('vvV', $len, $len, $offset);
    }
    /* build the NTLM lm hash then ecnrypt the challenge string with it */
    function build_lm_response($msg_data, $username, $password){
        $pass = strtoupper($password);
        while (strlen($pass) < 14) {
            $pass .= chr(0);
        }
        if (strlen($pass) > 14) {
            return str_repeat(chr(0), 16);
        }
        $p1 = substr($pass, 0, 7);
        $p2 = substr($pass, 7);
        $lm_hash = $this->des_encrypt($p1).$this->des_encrypt($p2);
        while (strlen($lm_hash) < 21) {
            $lm_hash .= chr(0);
        }
        return $this->apply_ntlm_hash($msg_data['vals']['challenge'], $lm_hash);

    }
    /* build the NTLM ntlm hash then ecnrypt the challenge string with it */
    function build_ntlm_response($msg_data, $username, $password){
        $password = iconv('UTF-8', 'UTF-16LE', $password);
        $ntlm_hash = hash('md4', $password, true);
        while (strlen($ntlm_hash) < 21) {
            $ntlm_hash .= chr(0);
        }
        return $this->apply_ntlm_hash($msg_data['vals']['challenge'], $ntlm_hash);
    }
    /* encrypt the challenge string with the lm/ntlm hash */
    function apply_ntlm_hash($challenge, $hash) {
        $p1 = substr($hash, 0, 7);
        $p2 = substr($hash, 7, 7);
        $p3 = substr($hash, 14, 7);
        return $this->des_encrypt($p1, $challenge).
            $this->des_encrypt($p2, $challenge).
            $this->des_encrypt($p3, $challenge);
    }
    /* NTLM compatible DES encryption */
    function des_encrypt($string, $challenge='KGS!@#$%') {
        $key = array();
        $tmp = array();
        $len = strlen($string);
        for ($i=0; $i<7; ++$i)
            $tmp[] = $i < $len ? ord($string[$i]) : 0;
        $key[] = $tmp[0] & 254;
        $key[] = ($tmp[0] << 7) | ($tmp[1] >> 1);
        $key[] = ($tmp[1] << 6) | ($tmp[2] >> 2);
        $key[] = ($tmp[2] << 5) | ($tmp[3] >> 3);
        $key[] = ($tmp[3] << 4) | ($tmp[4] >> 4);
        $key[] = ($tmp[4] << 3) | ($tmp[5] >> 5);
        $key[] = ($tmp[5] << 2) | ($tmp[6] >> 6);
        $key[] = $tmp[6] << 1;
        $is = mcrypt_get_iv_size(MCRYPT_DES, MCRYPT_MODE_ECB);
        $iv = mcrypt_create_iv($is, MCRYPT_RAND);
        $key0 = "";
        foreach ($key as $k)
            $key0 .= chr($k);
        $crypt = mcrypt_encrypt(MCRYPT_DES, $key0, $challenge, MCRYPT_MODE_ECB, $iv);
        return $crypt;
    }

    /* Send a message */
    function send_message($from, $recipients, $message) {
        global $conf;
        global $fd;
        global $max_outbound_recipients;
        if ($max_outbound_recipients && count($recipients) >= $max_outbound_recipients) {
            return 'Maximum number of recipients exceeded, sending canceled';
        }
        if (isset($conf['site_throttle_outbound_mail']) && $conf['site_throttle_outbound_mail'] != 0) {
            if (!allow_outbound_msg($conf['site_throttle_outbound_mail'])) {
                return 'Maximum number of outbound messages per minute reached.';
            }
        }
        $this->clean($from);
        $command = 'MAIL FROM:<'.$from.'>';
        $this->send_command($command);
        $res = $this->get_response();
        $bail = false;
        $result = 'An error occured sending the message';
        if(is_array($recipients)) {
            foreach($recipients as $rcpt) {
                $this->clean($rcpt);
                $command = 'RCPT TO:<'.$rcpt.'>';
                $this->send_command($command);
                $res = $this->get_response();
                if ($this->compare_response($res, '250') != 0) {
                    $bail = true;
                    break;
                }
            }
        }
        else {
            $this->clean($recipients);
            $command = 'RCPT TO:<'.$recipients.'>';
            $this->send_command($command);
            $res = $this->get_response();
            if ($this->compare_response($res, '250') != 0) {
                $bail = true;
            }
        }
        if (!$bail) {
            $command = 'DATA';
            $this->send_command($command);
            $res = $this->get_response();
            if ($this->compare_response($res, '354') != 0) {
                $result = 'An error occured during the DATA command';
            }
            else {
                $command = $message->output_smtp_message();
                $this->send_command($command);
                /* TODO: process attachments */
                $command = $this->crlf.'.';
                $this->send_command($command);
                $res = $this->get_response();
                if ($this->compare_response($res, '250') == 0) {
                    $result = false;
                }
                else {
                    $result = 'An error occured sending the message DATA';
                }
            }
        }
        else {
            $result = 'An error occured during the RCPT command';
        }
        return $result;
    }

    function puke($commands_only=false) {
        if ($commands_only) {
            echo_r($this->commands);
            echo_r($this->responses);
        }
        else {
            echo_r($this->debug);
            echo_r($this->commands);
            echo_r($this->responses);
        }
    } 

    /* issue a logout and close the socket to the server */
    function disconnect() {
        $command = 'QUIT';
        $this->send_command($command);
        $this->state = 'disconnected';
        $result = $this->get_response();
        if (is_resource($this->handle)) {
            fclose($this->handle);
        }
    }
    function clean($val) {
        if (!preg_match("/^[^\r\n]+$/", $val)) {
            echo_r("INVALID SMTP INPUT DETECTED: <b>$val</b>");
            exit;
        }
    }
}

?>
