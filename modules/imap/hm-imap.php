<?php

if (!defined('DEBUG_MODE')) { die(); }

/**
 * Authenticate against an IMAP server
 */
class Hm_Auth_IMAP extends Hm_Auth {

    /* IMAP authentication server settings */
    private $imap_settings = array();

    /**
     * Send the username and password to the configured IMAP server for authentication
     *
     * @param $user string username
     * @param $pass string password
     *
     * @return bool true if authentication worked
     */
    public function check_credentials($user, $pass) {
        $imap = new Hm_IMAP();
        list($server, $port, $tls) = $this->get_imap_config();
        if ($user && $pass && $server && $port) {
            $this->imap_settings = array(
                'server' => $server,
                'port' => $port,
                'tls' => $tls,
                'username' => $user,
                'password' => $pass,
                'no_caps' => true,
                'blacklisted_extensions' => array('enable')
            );
            $imap->connect($this->imap_settings);
        }
        if ($imap->get_state() == 'authenticated') {
            return true;
        }
        else {
            Hm_Msgs::add("Invalid username or password");
        }
        return false;
    }

    /**
     * Get IMAP server details from the site config
     *
     * @return array list of required details
     */
    private function get_imap_config() {
        $server = $this->site_config->get('imap_auth_server', false);
        $port = $this->site_config->get('imap_auth_port', false);
        $tls = $this->site_config->get('imap_auth_tls', false);
        return array($server, $port, $tls);
    }
}

/* imap connection manager */
class Hm_IMAP_List {
    
    use Hm_Server_List;

    public static function service_connect($id, $server, $user, $pass, $cache=false) {
        self::$server_list[$id]['object'] = new Hm_IMAP();
        if ($cache) {
            self::$server_list[$id]['object']->load_cache($cache, 'string');
        }
        return self::$server_list[$id]['object']->connect(array(
            'server'    => $server['server'],
            'port'      => $server['port'],
            'tls'       => $server['tls'],
            'username'  => $user,
            'password'  => $pass
        ));
    }
    public static function get_cache($session, $id) {
        $server_cache = $session->get('imap_cache', array());
        if (array_key_exists($id, $server_cache)) {
            return $server_cache[$id];
        }
        return false;
    }
}

/* IMAP base classes */
require_once('hm-imap-base.php');

/* public interface to IMAP commands */
class Hm_IMAP extends Hm_IMAP_Cache {

    /* config */

    /* maximum characters to read in from a request */
    public $max_read = false;

    /* SSL connection knobs */
    public $verify_peer_name = false;
    public $verify_peer = false;

    /* IMAP server IP address or hostname */
    public $server = '127.0.0.1';

    /* IP port to connect to. Standard port is 143, TLS is 993 */
    public $port = 143;

    /* enable TLS when connecting to the IMAP server */
    public $tls = false;

    /* don't change the account state in any way */
    public $read_only = false;

    /* convert folder names to utf7 */
    public $utf7_folders = false;

    /* defaults to LOGIN, CRAM-MD5 also supported but experimental */
    public $auth = false;

    /* search character set to use. can be US-ASCII, UTF-8, or '' */
    public $search_charset = '';

    /* sort responses can _probably_ be parsed quickly. This is non-conformant however */
    public $sort_speedup = true;

    /* use built in caching. strongly recommended */
    public $use_cache = true;

    /* limit LIST/LSUB responses to this many characters */
    public $folder_max = 50000;

    /* number of commands and responses to keep in memory. */
    public $max_history = 1000;

    /* default IMAP folder delimiter. Only used if NAMESPACE is not supported */
    public $default_delimiter = '/';

    /* defailt IMAP mailbox prefix. Only used if NAMESPACE is not supported */
    public $default_prefix = '';

    /* list of supported IMAP extensions to ignore */
    public $blacklisted_extensions = array();

    /* maximum number of IMAP commands to cache */
    public $cache_limit = 100;

    /* query the server for it's CAPABILITY response */
    public $no_caps = false;

    /* IMAP ID client information */
    public $app_name = 'Hm_IMAP';
    public $app_version = '3.0';
    public $app_vendor = 'Hastymail Development Group';
    public $app_support_url = 'http://hastymail.org/contact_us/';

    /* connect error info */
    public $con_error_msg = '';
    public $con_error_num = 0;

    /* holds information about the currently selected mailbox */
    public $selected_mailbox = false;

    /* special folders defined by the IMAP SPECIAL-USE extension */
    public $special_use_mailboxes = array(
        '\All' => false,
        '\Archive' => false,
        '\Drafts' => false,
        '\Flagged' => false,
        '\Junk' => false,
        '\Sent' => false,
        '\Trash' => false
    );

    /* holds the current IMAP connection state */
    private $state = 'disconnected';

    /* used for message part content streaming */
    private $stream_size = 0;

    /**
     * constructor
     */
    public function __construct() {
    }

    /* ------------------ CONNECT/AUTH ------------------------------------- */

    /**
     * connect to the imap server
     *
     * @param $config array list of configuration options for this connections
     *
     * @return bool true on connection sucess
     */
    public function connect( $config ) {
        if (isset($config['username']) && isset($config['password'])) {
            $this->commands = array();
            $this->debug = array();
            $this->capability = false;
            $this->responses = array();
            $this->current_command = false;
            $this->apply_config($config);
            if ($this->tls) {
                $this->server = 'tls://'.$this->server;
            } 
            else {
                $this->server = 'tcp://'.$this->server;
            }
            $this->debug[] = 'Connecting to '.$this->server.' on port '.$this->port;
            $ctx = stream_context_create();

            /* TODO: make this optional */
            stream_context_set_option($ctx, 'ssl', 'verify_peer_name', $this->verify_peer_name);
            stream_context_set_option($ctx, 'ssl', 'verify_peer', $this->verify_peer);

            $timeout = 10;
            $this->handle = stream_socket_client($this->server.':'.$this->port, $errorno, $errorstr, $timeout, STREAM_CLIENT_CONNECT, $ctx);
            if (is_resource($this->handle)) {
                $this->debug[] = 'Successfully opened port to the IMAP server';
                $this->state = 'connected';
                return $this->authenticate($config['username'], $config['password']);
            }
            else {
                $this->debug[] = 'Could not connect to the IMAP server';
                $this->debug[] = 'fsockopen errors #'.$errorno.'. '.$errorstr;
                $this->con_error_msg = $errorstr;
                $this->con_error_num = $errorno;
                return false;
            }
        }
        else {
            $this->debug[] = 'username and password must be set in the connect() config argument';
            return false;
        }
    }

    /**
     * close the IMAP connection
     *
     * @return void
     */
    public function disconnect() {
        $command = "LOGOUT\r\n";
        $this->state = 'disconnected';
        $this->selected_mailbox = false;
        $this->send_command($command);
        $result = $this->get_response();
        if (is_resource($this->handle)) {
            fclose($this->handle);
        }
    }

    /**
     * authenticate the username/password
     *
     * @param $username IMAP login name
     * @param $password IMAP password
     *
     * @return bool true on sucessful login
     */
    public function authenticate($username, $password) {
        if (!$this->tls) {
            $this->starttls();
        }
        switch (strtolower($this->auth)) {

            case 'cram-md5':

                $this->banner = $this->fgets(1024);
                $cram1 = 'AUTHENTICATE CRAM-MD5'."\r\n";
                $this->send_command($cram1);
                $response = $this->get_response();

                $challenge = base64_decode(substr(trim($response), 1));
                $pass .= str_repeat(chr(0x00), (64-strlen($password)));
                $ipad = str_repeat(chr(0x36), 64);
                $opad = str_repeat(chr(0x5c), 64);
                $digest = bin2hex(pack("H*", md5(($pass ^ $opad).pack("H*", md5(($pass ^ $ipad).$challenge)))));
                $challenge_response = base64_encode($username.' '.$digest);
                fputs($this->handle, $challenge_response."\r\n");
                break;
            default:
                $login = 'LOGIN "'.str_replace('"', '\"', $username).'" "'.str_replace('"', '\"', $password). "\"\r\n";
                $this->send_command($login);
                break;
        }
        $res = $this->get_response();
        $authed = false;
        if (is_array($res) && !empty($res)) {
            $response = array_pop($res);
            if (!$this->auth) {
                if (isset($res[1])) {
                    $this->banner = $res[1];
                }
                if (isset($res[0])) {
                    $this->banner = $res[0];
                }
            }
            if (stristr($response, 'A'.$this->command_count.' OK')) {
                $authed = true;
                $this->state = 'authenticated';
            }
        }
        if ( $authed ) {
            $this->debug[] = 'Logged in successfully as '.$username;
            $this->get_capability();
            //$this->enable();
            //$this->enable_compression();
        }
        else {
            $this->debug[] = 'Log in for '.$username.' FAILED';
        }
        return $authed;
    }

    /**
     * attempt starttls
     *
     * @return void
     */
    public function starttls() {
        if ($this->is_supported('STARTTLS')) {
            $command = "STARTTLS\r\n";
            $this->send_command($command);
            $response = $this->get_response();
            if (!empty($response)) {
                $end = array_pop($response);
                if (substr($end, 0, strlen('A'.$this->command_count.' OK')) == 'A'.$this->command_count.' OK') {
                    stream_socket_enable_crypto($this->handle, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
                }
                else {
                    $this->debug[] = 'Unexpected results from STARTTLS: '.implode(' ', $response);
                }
            }
            else {
                $this->debug[] = 'No response from STARTTLS command';
            }
        }
    }

    /* ------------------ UNSELECTED STATE COMMANDS ------------------------ */

    /**
     * fetch IMAP server capability response
     *
     * @return string capability response
     */
    public function get_capability() {
        if ( $this->capability ) {
            return $this->capability;
        }
        else {
            if (!$this->no_caps) {
                $command = "CAPABILITY\r\n";
                $this->send_command($command);
                $response = $this->get_response();
                $this->capability = $response[0];
                $this->debug['CAPS'] = $this->capability;
                $this->parse_extensions_from_capability();
                return $this->capability;
            }
        }
    }

    /**
     * get a list of mailbox folders
     *
     * @param $lsub bool flag to limit results to subscribed folders only
     *
     * @return array associative array of folder details
     */
    public function get_mailbox_list($lsub=false, $mailbox='', $keyword='*') {
        /* defaults */
        $folders = array();
        $excluded = array();
        $parents = array();
        $delim = false;
        $commands = $this->build_list_commands($lsub, $mailbox, $keyword);
        $cache_command = implode('', array_map(function($v) { return $v[0]; }, $commands)).(string)$mailbox.(string)$keyword;
        $cache = $this->check_cache($cache_command);
        if ($cache !== false) {
            return $cache;
        }

        foreach($commands as $vals) {
            $command = $vals[0];
            $namespace = $vals[1];

            $this->send_command($command);
            $result = $this->get_response($this->folder_max, true);

            /* loop through the "parsed" response. Each iteration is one folder */
            foreach ($result as $vals) {

                if (in_array('STATUS', $vals)) {
                    $status_values = $this->parse_status_response(array($vals));
                    $this->check_mailbox_state_change($status_values);
                    continue;
                }
                /* break at the end of the list */
                if (!isset($vals[0]) || $vals[0] == 'A'.$this->command_count) {
                    continue;
                }

                /* defaults */
                $flags = false;
                $flag = false;
                $delim_flag = false;
                $parent = '';
                $base_name = '';
                $folder_parts = array();
                $no_select = false;
                $can_have_kids = true;
                $has_kids = false;
                $marked = false;
                $folder_sort_by = 'ARRIVAL';
                $check_for_new = false;

                /* full folder name, includes an absolute path of parent folders */
                $folder = $this->utf7_decode($vals[(count($vals) - 1)]);

                /* sometimes LIST responses have dupes */
                if (isset($folders[$folder]) || !$folder) {
                    continue;
                }

                /* folder flags */
                foreach ($vals as $v) {
                    if ($v == '(') {
                        $flag = true;
                    }
                    elseif ($v == ')') {
                        $flag = false;
                        $delim_flag = true;
                    }
                    else {
                        if ($flag) {
                            $flags .= ' '.$v;
                        }
                        if ($delim_flag && !$delim) {
                            $delim = $v;
                            $delim_flag = false;
                        }
                    }
                }

                /* get each folder name part of the complete hierarchy */
                $folder_parts = array();
                if ($delim && strstr($folder, $delim)) {
                    $temp_parts = explode($delim, $folder);
                    foreach ($temp_parts as $g) {
                        if (trim($g)) {
                            $folder_parts[] = $g;
                        }
                    }
                }
                else {
                    $folder_parts[] = $folder;
                }

                /* get the basename part of the folder name. For a folder named "inbox.sent.march"
                 * with a delimiter of "." the basename would be "march" */
                if (isset($folder_parts[(count($folder_parts) - 1)])) {
                    $base_name = $folder_parts[(count($folder_parts) - 1)];
                }
                else {
                    $base_name = $folder;
                }

                /* determine the parent folder basename if it exists */
                if (isset($folder_parts[(count($folder_parts) - 2)])) {
                    $parent = implode($delim, array_slice($folder_parts, 0, -1));
                    if ($parent.$delim == $namespace) {
                        $parent = '';
                    }
                }

                /* special use mailbox extension */
                if ($this->is_supported('SPECIAL-USE')) {
                    $special = false;
                    foreach ($this->special_use_mailboxes as $name => $value) {
                        if (stristr($flags, $name)) {
                            $special = $name;
                        }
                    }
                    if ($special) {
                        $this->special_use_mailboxes[$special] = $folder;
                    }
                }

                /* build properties from the flags string */
                if (stristr($flags, 'marked')) { 
                    $marked = true;
                }
                if (stristr($flags, 'noinferiors')) { 
                    $can_have_kids = false;
                }
                if (($folder == $namespace && $namespace) || stristr($flags, 'haschildren')) { 
                    $has_kids = true;
                }
                if ($folder != 'INBOX' && $folder != $namespace && stristr($flags, 'noselect')) { 
                    $no_select = true;
                }

                /* store the results in the big folder list struct */
                $folders[$folder] = array('parent' => $parent, 'delim' => $delim, 'name' => $folder,
                                        'name_parts' => $folder_parts, 'basename' => $base_name,
                                        'realname' => $folder, 'namespace' => $namespace, 'marked' => $marked,
                                        'noselect' => $no_select, 'can_have_kids' => $can_have_kids,
                                        'has_kids' => $has_kids);

                /* store a parent list used below */
                if ($parent && !in_array($parent, $parents)) {
                    $parents[$parent][] = $folders[$folder];
                }
            }
        }

        /* attempt to fix broken hierarchy issues. If a parent folder was not found fabricate
         * it in the folder list */
        $place_holders = array();
        foreach ($parents as $val => $parent_list) {
            foreach ($parent_list as $parent) {
                $found = false;
                foreach ($folders as $i => $vals) {
                    if ($vals['name'] == $val) {
                        $folders[$i]['has_kids'] = 1;
                        $found = true;
                        break;
                    }
                }
                if (!$found) {
                    if (count($parent['name_parts']) > 1) {
                        foreach ($parent['name_parts'] as $i => $v) {
                            $fname = implode($delim, array_slice($parent['name_parts'], 0, ($i + 1)));
                            $name_parts = array_slice($parent['name_parts'], 0, ($i + 1));
                            if (!isset($folders[$fname])) {
                                $freal = $v;
                                if ($i > 0) {
                                    $fparent = implode($delim, array_slice($parent['name_parts'], 0, $i));
                                }
                                else {
                                    $fparent = false;
                                }
                                $place_holders[] = $fname;
                                $folders[$fname] = array('parent' => $fparent, 'delim' => $delim, 'name' => $freal,
                                    'name_parts' => $name_parts, 'basename' => $freal, 'realname' => $fname,
                                    'namespace' => $namespace, 'marked' => false, 'noselect' => true,
                                    'can_have_kids' => true, 'has_kids' => true);
                            }
                        }
                    }
                }
            }
        }

        /* ALL account need an inbox. If we did not find one manually add it to the results */
        if (!isset($folders['INBOX']) && !$mailbox ) {
            $folders = array_merge(array('INBOX' => array(
                    'name' => 'INBOX', 'basename' => 'INBOX', 'realname' => 'INBOX', 'noselect' => false,
                    'parent' => false, 'has_kids' => false, 'name_parts' => array(), 'delim' => $delim)), $folders);
        }

        /* sort and return the list */
        ksort($folders);
        return $this->cache_return_val($folders, $cache_command);
    }

    /**
     * get IMAP folder namespaces
     *
     * @return array list of available namespace details
     */
    public function get_namespaces() {
        if (!$this->is_supported('NAMESPACE')) {
            return array(array(
                'prefix' => $this->default_prefix,
                'delim' => $this->default_delimiter,
                'class' => 'personal'
            ));
        }
        $data = array();
        $command = "NAMESPACE\r\n";
        $cache = $this->check_cache($command);
        if ($cache !== false) {
            return $cache;
        }
        $this->send_command("NAMESPACE\r\n");
        $res = $this->get_response();
        $this->namespace_count = 0;
        $status = $this->check_response($res);
        if ($status) {
            if (preg_match("/\* namespace (\(.+\)|NIL) (\(.+\)|NIL) (\(.+\)|NIL)/i", $res[0], $matches)) {
                $classes = array(1 => 'personal', 2 => 'other_users', 3 => 'shared');
                foreach ($classes as $i => $v) {
                    if (trim(strtoupper($matches[$i])) == 'NIL') {
                        continue;
                    }
                    $list = str_replace(') (', '),(', substr($matches[$i], 1, -1));
                    $prefix = '';
                    $delim = '';
                    foreach (explode(',', $list) as $val) {
                        $val = trim($val, ")(\r\n ");
                        if (strlen($val) == 1) {
                            $delim = $val;
                            $prefix = '';
                        }
                        else {
                            $delim = substr($val, -1);
                            $prefix = trim(substr($val, 0, -1));
                        }
                        $this->namespace_count++;
                        $data[] = array('delim' => $delim, 'prefix' => $prefix, 'class' => $v);
                    }
                }
            }
            return $this->cache_return_val($data, $command);
        }
        return $data;
    }

    /**
     * select a mailbox
     *
     * @param $mailbox string the mailbox to attempt to select
     *
     * @return array list of information about the selected mailbox
     */
    public function select_mailbox($mailbox) {
        if (isset($this->selected_mailbox['name']) && $this->selected_mailbox['name'] == $mailbox) {
            return $this->poll();
        }
        $box = $this->utf7_encode(str_replace('"', '\"', $mailbox));
        if (!$this->is_clean($box, 'mailbox')) {
            return false;
        }
        if (!$this->read_only) {
            $command = "SELECT \"$box\"";
        }
        else {
            $command = "EXAMINE \"$box\"";
        }
        if ($this->is_supported('QRESYNC')) {
            $command .= $this->build_qresync_params();
        }
        elseif ($this->is_supported('CONDSTORE')) {
            $command .= ' (CONDSTORE)';
        }
        $cached_state = $this->check_cache($command);
        $this->send_command($command."\r\n");
        $res = $this->get_response(false, true);
        $status = $this->check_response($res, true);
        $result = array();
        if ($status) {
            list($qresync, $attributes) = $this->parse_untagged_responses($res);
            if (!$qresync) {
                $this->check_mailbox_state_change($attributes, $cached_state, $mailbox);
            }
            else {
                $this->debug[] = sprintf('Cache bust avoided on %s with QRESYNC!', $this->selected_mailbox['name']);
            }
            $result = array(
                'selected' => $status,
                'uidvalidity' => $attributes['uidvalidity'],
                'exists' => $attributes['exists'],
                'first_unseen' => $attributes['unseen'],
                'uidnext' => $attributes['uidnext'],
                'flags' => $attributes['flags'],
                'permanentflags' => $attributes['pflags'],
                'recent' => $attributes['recent'],
                'nomodseq' => $attributes['nomodseq'],
                'modseq' => $attributes['modseq'],
            );
            $this->state = 'selected';
            $this->selected_mailbox = array('name' => $box, 'detail' => $result);
            return $this->cache_return_val($result, $command);

        }
        return $result;
    }

    /**
     * issue IMAP status command on a mailbox
     *
     * @param $mailbox string IMAP mailbox to check
     * @param $args array list of properties to fetch
     *
     * @return array list of attribute values discovered
     */
    public function get_mailbox_status($mailbox, $args=array('UNSEEN', 'UIDVALIDITY', 'UIDNEXT', 'MESSAGES', 'RECENT')) {
        $command = 'STATUS "'.$this->utf7_encode($mailbox).'" ('.implode(' ', $args).")\r\n";
        $this->send_command($command);
        $attributes = array();
        $response = $this->get_response(false, true);
        if ($this->check_response($response, true)) {
            $attributes = $this->parse_status_response($response);
            $this->check_mailbox_state_change($attributes);
        }
        return $attributes;
    }

    /* ------------------ SELECTED STATE COMMANDS -------------------------- */

    /**
     * use IMAP NOOP to poll for untagged server messages
     *
     * @return array list of properties that have changed since SELECT
     */
    public function poll() {
        $result = array();
        $command = "NOOP\r\n";
        $this->send_command($command);
        $res = $this->get_response(false, true);
        if ($this->check_response($res, true)) {
            list($qresync, $attributes) = $this->parse_untagged_responses($res);
            if (!$qresync) {
                $this->check_mailbox_state_change($attributes);
            }
            else {
                $this->debug[] = sprintf('Cache bust avoided on %s with QRESYNC!', $this->selected_mailbox['name']);
            }
        }
        return $result;
    }

    /**
     * return a header list for the supplied message uids
     *
     * TODO: refactor. abstract header line continuation parsing for re-use
     *
     * @param $uids array/string an array of uids or a valid IMAP sequence set as a string
     * @param $raw bool flag to disable decoding header values
     *
     * @return array list of headers and values for the specified uids
     */
    public function get_message_list($uids, $raw=false) {
        if (is_array($uids)) {
            sort($uids);
            $sorted_string = implode(',', $uids);
        }
        else {
            $sorted_string = $uids;
        }
        if (!$this->is_clean($sorted_string, 'uid_list')) {
            return array();
        }
        $command = 'UID FETCH '.$sorted_string.' (FLAGS INTERNALDATE RFC822.SIZE ';
        if ($this->is_supported( 'X-GM-EXT-1' )) {
            $command .= 'X-GM-MSGID X-GM-THRID X-GM-LABELS ';
        }
        $command .= "BODY.PEEK[HEADER.FIELDS (SUBJECT FROM DATE CONTENT-TYPE X-PRIORITY TO LIST-ARCHIVE REFERENCES MESSAGE-ID)])\r\n";
        $cache_command = $command.(string)$raw;
        $cache = $this->check_cache($cache_command);
        if ($cache !== false) {
            return $cache;
        }
        $this->send_command($command);
        $res = $this->get_response(false, true);
        $status = $this->check_response($res, true);
        $tags = array('X-GM-MSGID' => 'google_msg_id', 'X-GM-THRID' => 'google_thread_id', 'X-GM-LABELS' => 'google_labels', 'UID' => 'uid', 'FLAGS' => 'flags', 'RFC822.SIZE' => 'size', 'INTERNALDATE' => 'internal_date');
        $junk = array('MESSAGE-ID', 'REFERENCES', 'LIST-ARCHIVE', 'SUBJECT', 'FROM', 'CONTENT-TYPE', 'TO', '(', ')', ']', 'X-PRIORITY', 'DATE');
        $flds = array('message-id' => 'message_id', 'references' => 'references', 'list-archive' => 'list_archive', 'date' => 'date', 'from' => 'from', 'to' => 'to', 'subject' => 'subject', 'content-type' => 'content_type', 'x-priority' => 'x_priority');
        $headers = array();
        foreach ($res as $n => $vals) {
            if (isset($vals[0]) && $vals[0] == '*') {
                $uid = 0;
                $size = 0;
                $subject = '';
                $list_archive = '';
                $from = '';
                $references = '';
                $date = '';
                $message_id = '';
                $x_priority = 0;
                $content_type = '';
                $to = '';
                $flags = '';
                $internal_date = '';
                $google_msg_id = '';
                $google_thread_id = '';
                $google_labels = '';
                $count = count($vals);
                for ($i=0;$i<$count;$i++) {
                    if ($vals[$i] == 'BODY[HEADER.FIELDS') {
                        $i++;
                        while(isset($vals[$i]) && in_array(strtoupper($vals[$i]), $junk)) {
                            $i++;
                        }
                        $last_header = false;
                        $lines = explode("\r\n", $vals[$i]);
                        foreach ($lines as $line) {
                            $header = strtolower(substr($line, 0, strpos($line, ':')));
                            if (!$header || (!isset($flds[$header]) && $last_header)) {
                                ${$flds[$last_header]} .= ' '.trim($line);
                            }
                            elseif (isset($flds[$header])) {
                                ${$flds[$header]} = substr($line, (strpos($line, ':') + 1));
                                $last_header = $header;
                            }
                        }
                    }
                    elseif (isset($tags[strtoupper($vals[$i])])) {
                        if (isset($vals[($i + 1)])) {
                            if (($tags[strtoupper($vals[$i])] == 'flags' || $tags[strtoupper($vals[$i])] == 'google_labels' ) && $vals[$i + 1] == '(') {
                                $n = 2;
                                while (isset($vals[$i + $n]) && $vals[$i + $n] != ')') {
                                    $$tags[strtoupper($vals[$i])] .= ' '.$vals[$i + $n];
                                    $n++;
                                }
                                $i += $n;
                            }
                            else {
                                $$tags[strtoupper($vals[$i])] = $vals[($i + 1)];
                                $i++;
                            }
                        }
                    }
                }
                if ($uid) {
                    $cset = '';
                    if (stristr($content_type, 'charset=')) {
                        if (preg_match("/charset\=([^\s;]+)/", $content_type, $matches)) {
                            $cset = trim(strtolower(str_replace(array('"', "'"), '', $matches[1])));
                        }
                    }
                    $headers[(string) $uid] = array('uid' => $uid, 'flags' => $flags, 'internal_date' => $internal_date, 'size' => $size,
                                     'date' => $date, 'from' => $from, 'to' => $to, 'subject' => $subject, 'content-type' => $content_type,
                                     'timestamp' => time(), 'charset' => $cset, 'x-priority' => $x_priority, 'google_msg_id' => $google_msg_id,
                                     'google_thread_id' => $google_thread_id, 'google_labels' => $google_labels, 'list_archive' => $list_archive,
                                     'references' => $references, 'message_id' => $message_id);

                    if ($raw) {
                        $headers[$uid] = array_map('trim', $headers[$uid]);
                    }
                    else {
                        $headers[$uid] = array_map(array($this, 'decode_fld'), $headers[$uid]);
                    }

                }
            }
        }
        if ($status) {
            return $this->cache_return_val($headers, $cache_command);
        }
        else {
            return $headers;
        }
    }

    /**
     * get the IMAP BODYSTRUCTURE of a message
     *
     * @param $uid int IMAP UID of the message
     *
     * @return array message structure represented as a nested array
     */
    public function get_message_structure($uid) {
        $result = $this->get_raw_bodystructure($uid);
        $struct = $this->parse_bodystructure_response($result);
        return $struct;
    }

    /**
     * get the raw IMAP BODYSTRUCTURE response
     *
     * @param $uid int IMAP UID of the message
     *
     * @return array low-level parsed message structure 
     */
    private function get_raw_bodystructure($uid) {
        if (!$this->is_clean($uid, 'uid')) {
            return array();
        }
        $part_num = 1;
        $struct = array();
        $command = "UID FETCH $uid BODYSTRUCTURE\r\n";
        $cache = $this->check_cache($command);
        if ($cache !== false) {
            return $cache;
        }
        $this->send_command($command);
        $result = $this->get_response(false, true);
        while (isset($result[0][0]) && isset($result[0][1]) && $result[0][0] == '*' && strtoupper($result[0][1]) == 'OK') {
            array_shift($result);
        }
        $status = $this->check_response($result, true);
        if (!isset($result[0][4])) {
            $status = false;
        }
        if ($status) {
            return $this->cache_return_val($result, $command);
        }
        return $result;
    }

    /**
     * New BODYSTRUCTURE parsing routine
     *
     * @param $result array low-level IMAP response
     *
     * @return array
     */
    private function parse_bodystructure_response($result) {
        $response = array();
        if (strtoupper($result[0][6]) == 'MODSEQ')  {
            $response = array_slice($result[0], 11, -1);
        }
        elseif (strtoupper($result[0][4]) == 'UID')  {
            $response = array_slice($result[0], 7, -1);
        }
        else {
            $response = array_slice($result[0], 5, -1);
        }

        $this->struct_object = new Hm_IMAP_Struct($response);
        $struct = $this->struct_object->data();
        return $struct;
    }

    /**
     * get content for a message part
     *
     * @param $uid int a single IMAP message UID
     * @param $message_part string the IMAP message part number
     * @param $raw bool flag to enabled fetching the entire message as text
     * @param $max int maximum read length to allow.
     * @param $struct mixed a message part structure array for decoding and
     *                      charset conversion. bool true for auto discovery
     *
     * @return string message content
     */
    public function get_message_content($uid, $message_part, $max=false, $struct=true) {
        $message_part = preg_replace("/^0\.{1}/", '', $message_part);
        if (!$this->is_clean($uid, 'uid')) {
            return '';
        }
        if ($message_part == 0) {
            $command = "UID FETCH $uid BODY[]\r\n";
        }
        else {
            if (!$this->is_clean($message_part, 'msg_part')) {
                return '';
            }
            $command = "UID FETCH $uid BODY[$message_part]\r\n";
        }
        $cache_command = $command.(string)$max;
        if ($struct) {
            $cache_command .= '1';
        }
        $cache = $this->check_cache($cache_command);
        if ($cache !== false) {
            return $cache;
        }
        $this->send_command($command);
        $result = $this->get_response($max, true);
        $status = $this->check_response($result, true);
        $res = '';
        foreach ($result as $vals) {
            if ($vals[0] != '*') {
                continue;
            }
            $search = true;
            foreach ($vals as $v) {
                if ($v != ']' && !$search) {
                    if ($v == 'NIL') {
                        $res = '';
                        break 2;
                    }
                    $res = trim(preg_replace("/\s*\)$/", '', $v));
                    break 2;
                }
                if (stristr(strtoupper($v), 'BODY')) {
                    $search = false;
                }
            }
        }
        if ($struct === true) {
            $full_struct = $this->get_message_structure( $uid );
            $part_struct = $this->search_bodystructure( $full_struct, array('imap_part_number' => $message_part));
            if (isset($part_struct[$message_part])) {
                $struct = $part_struct[$message_part];
            }
        }
        if (is_array($struct)) {
            if (isset($struct['encoding']) && $struct['encoding']) {
                if (strtolower($struct['encoding']) == 'quoted-printable') {
                    $res = quoted_printable_decode($res);
                }
                if (strtolower($struct['encoding']) == 'base64') {
                    $res = base64_decode($res);
                }
            }
            if (isset($struct['charset']) && $struct['charset']) {
                $res = mb_convert_encoding($res, 'UTF-8', $struct['charset']);
            }
        }
        if ($status) {
            return $this->cache_return_val($res, $cache_command);
        }
        return $res;
    }

    /**
     * use IMAP SEARCH or ESEARCH
    /**
     * search a field for a keyword
     *
     * @param $target string message types to search. can be ALL, UNSEEN, ANSWERED, etc
     * @param $uids array/string an array of uids or a valid IMAP sequence set as a string (or false for ALL)
     * @param $fld string optional field to search
     * @param $term string optional search term
     *
     * @return array list of IMAP message UIDs that match the search
     */
    public function search($target='ALL', $uids=false, $terms=array(), $esearch=array()) {
        if (!$this->is_clean($this->search_charset, 'charset') || !$this->is_clean($target, 'keyword')) {
            return array();
        }
        if (!empty($terms)) {
            foreach ($terms as $fld => $term) {
                if (!$this->is_clean($fld, 'search_str') || !$this->is_clean($term, 'search_str')) {
                    return array();
                }
            }
        }
        if (!empty($uids)) {
            if (is_array($uids)) {
                $uids = implode(',', $uids);
            }
            if (!$this->is_clean($uids, 'uid_list')) {
                return array();
            }
            $uids = 'UID '.$uids;
        }
        else {
            $uids = 'ALL';
        }
        if ($this->search_charset) {
            $charset = 'CHARSET '.strtoupper($this->search_charset).' ';
        }
        else {
            $charset = ' ';
        }
        if (!empty($terms)) {
            $flds = array();
            foreach ($terms as $fld => $term) {
                $flds[] = $fld.' "'.str_replace('"', '\"', $term).'"';
            }
            $fld = ' '.implode(' ', $flds);
        }
        else {
            $fld = '';
        }
        $esearch_enabled = false;
        $command = 'UID SEARCH ';
        if (!empty($esearch) && $this->is_supported('ESEARCH')) {
            $valid = array_filter($esearch, function($v) { return in_array($v, array('MIN', 'MAX', 'COUNT', 'ALL')); });
            if (!empty($valid)) {
                $esearch_enabled = true;
                $command .= 'RETURN ('.implode(' ', $valid).') ';
            }
        }
        $command .= '('.$target.')'.$charset.$uids.$fld."\r\n";
        $cache = $this->check_cache($command);
        if ($cache !== false) {
            return $cache;
        }
        $this->send_command($command);
        $result = $this->get_response(false, true);
        $status = $this->check_response($result, true);
        $res = array();
        $esearch_res = array();
        if ($status) {
            array_pop($result);
            foreach ($result as $vals) {
                if (in_array('ESEARCH', $vals)) {
                    $esearch_res = $this->parse_esearch_response($vals);
                    continue;
                }
                elseif (in_array('SEARCH', $vals)) {
                    foreach ($vals as $v) {
                        if (ctype_digit((string) $v)) {
                            $res[] = $v;
                        }
                    }
                }
            }
            if ($esearch_enabled) {
                $res = $esearch_res;
            }
            return $this->cache_return_val($res, $command);
        }
        return $res;
    }

    /**
     * get the headers for the selected message
     *
     * @param $uid int IMAP message UID
     * @param $message_part string IMAP message part number
     *
     * @return array associate array of message headers
     */
    public function get_message_headers($uid, $message_part=false, $raw=false) {
        if (!$this->is_clean($uid, 'uid')) {
            return array();
        }
        if ($message_part == 1 || !$message_part) {
            $command = "UID FETCH $uid (FLAGS BODY[HEADER])\r\n";
        }
        else {
            if (!$this->is_clean($message_part, 'msg_part')) {
                return array();
            }
            $command = "UID FETCH $uid (FLAGS BODY[$message_part.HEADER])\r\n";
        }
        $cache_command = $command.(string)$raw;
        $cache = $this->check_cache($cache_command);
        if ($cache !== false) {
            return $cache;
        }
        $this->send_command($command);
        $result = $this->get_response(false, true);
        $status = $this->check_response($result, true);
        $headers = array();
        $flags = array();
        if ($status) {
            foreach ($result as $vals) {
                if ($vals[0] != '*') {
                    continue;
                }
                $search = true;
                $flag_search = false;
                foreach ($vals as $v) {
                    if ($flag_search) {
                        if ($v == ')') {
                            $flag_search = false;
                        }
                        elseif ($v == '(') {
                            continue;
                        }
                        else {
                            $flags[] = $v;
                        }
                    }
                    elseif ($v != ']' && !$search) {
                        $v = preg_replace("/(?!\r)\n/", "\r\n", $v);
                        $parts = explode("\r\n", $v);
                        if (is_array($parts) && !empty($parts)) {
                            $i = 0;
                            foreach ($parts as $line) {
                                $split = strpos($line, ':');
                                if (preg_match("/^from /i", $line)) {
                                    continue;
                                }
                                if (isset($headers[$i]) && trim($line) && ($line{0} == "\t" || $line{0} == ' ')) {
                                    $headers[$i][1] .= ' '.trim($line);
                                }
                                elseif ($split) {
                                    $i++;
                                    $last = substr($line, 0, $split);
                                    $headers[$i] = array($last, trim(substr($line, ($split + 1))));
                                }
                            }
                        }
                        break;
                    }
                    if (stristr(strtoupper($v), 'BODY')) {
                        $search = false;
                    }
                    elseif (stristr(strtoupper($v), 'FLAGS')) {
                        $flag_search = true;
                    }
                }
            }
            if (!empty($flags)) {
                $headers[] = array('Flags', implode(' ', $flags));
            }
        }
        $results = array();
        foreach ($headers as $vals) {
            if (!$raw) {
                $vals[1] = $this->decode_fld($vals[1]);
            }
            $results[$vals[0]] = $vals[1];
        }
        if ($status) {
            return $this->cache_return_val($results, $cache_command);
        }
        return $results;
    }

    /**
     * start streaming a message part. returns the number of characters in the message
     *
     * @param $uid int IMAP message UID
     * @param $message_part string IMAP message part number
     *
     * @return int the size of the message queued up to stream
     */
    public function start_message_stream($uid, $message_part) {
        if (!$this->is_clean($uid, 'uid')) {
            return false;
        }
        if ($message_part == 0) {
            $command = "UID FETCH $uid BODY[]\r\n";
        }
        else {
            if (!$this->is_clean($message_part, 'msg_part')) {
                return false;
            }
            $command = "UID FETCH $uid BODY[$message_part]\r\n";
        }
        $this->send_command($command);
        $result = $this->fgets(1024);
        $size = false;
        if (preg_match("/\{(\d+)\}\r\n/", $result, $matches)) {
            $size = $matches[1];
            $this->stream_size = $size;
            $this->current_stream_size = 0;
        }
        return $size;
    }

    /**
     * read a line from a message stream. Called until it returns
     * false will "stream" a message part content one line at a time.
     * useful for avoiding memory consumption when dealing with large
     * attachments
     *
     * @param $size int chunk size to read using fgets
     *
     * @return string chunk of the streamed message
     */
    public function read_stream_line($size=1024) {
        if ($this->stream_size) {
            $res = $this->fgets(1024);
            while(substr($res, -2) != "\r\n") {
                $res .= $this->fgets($size);
            }
            if ($res && $this->check_response(array($res), false, false)) {
                $res = false;
            }
            if ($res) {
                $this->current_stream_size += strlen($res);
            }
            if ($this->current_stream_size >= $this->stream_size) {
                $this->stream_size = 0;
                $res = '';
            }
        }
        else {
            $res = false;
        }
        return $res;
    }

    /**
     * use FETCH to sort a list of uids when SORT is not available
     *
     * @param $sort string the sort field
     * @param $reverse bool flag to reverse the results
     * @param $filter string IMAP message type (UNSEEN, ANSWERED, DELETED, etc)
     * @param $uid_str string IMAP sequence set string or false
     *
     * @return array list of UIDs in the sort order
     */
    public function sort_by_fetch($sort, $reverse, $filter, $uid_str=false) {
        if (!$this->is_clean($sort, 'keyword')) {
            return false;
        }
        if ($uid_str) {
            $command1 = 'UID FETCH '.$uid_str.' (FLAGS ';
        }
        else {
            $command1 = 'UID FETCH 1:* (FLAGS ';
        }
        switch ($sort) {
            case 'DATE':
                $command2 = "BODY.PEEK[HEADER.FIELDS (DATE)])\r\n";
                $key = "BODY[HEADER.FIELDS";
                break;
            case 'SIZE':
                $command2 = "RFC822.SIZE)\r\n";
                $key = "RFC822.SIZE";
                break;
            case 'TO':
                $command2 = "BODY.PEEK[HEADER.FIELDS (TO)])\r\n";
                $key = "BODY[HEADER.FIELDS";
                break;
            case 'CC':
                $command2 = "BODY.PEEK[HEADER.FIELDS (CC)])\r\n";
                $key = "BODY[HEADER.FIELDS";
                break;
            case 'FROM':
                $command2 = "BODY.PEEK[HEADER.FIELDS (FROM)])\r\n";
                $key = "BODY[HEADER.FIELDS";
                break;
            case 'SUBJECT':
                $command2 = "BODY.PEEK[HEADER.FIELDS (SUBJECT)])\r\n";
                $key = "BODY[HEADER.FIELDS";
                break;
            case 'ARRIVAL':
            default:
                $command2 = "INTERNALDATE)\r\n";
                $key = "INTERNALDATE";
                break;
        }
        $command = $command1.$command2;
        $cache_command = $command.(string)$reverse;
        $cache = $this->check_cache($cache_command);
        if ($cache !== false) {
            return $cache;
        }
        $this->send_command($command);
        $res = $this->get_response(false, true);
        $status = $this->check_response($res, true);
        $uids = array();
        $sort_keys = array();
        foreach ($res as $vals) {
            if (!isset($vals[0]) || $vals[0] != '*') {
                continue;
            }
            $uid = 0;
            $sort_key = 0;
            $body = false;
            foreach ($vals as $i => $v) {
                if ($body) {
                    if ($v == ']' && isset($vals[$i + 1])) {
                        if ($command2 == "BODY.PEEK[HEADER.FIELDS (DATE)]\r\n") {
                            $sort_key = strtotime(trim(substr($vals[$i + 1], 5)));
                        }
                        else {
                            $sort_key = $vals[$i + 1];
                        }
                        $body = false;
                    }
                }
                if (strtoupper($v) == 'FLAGS') {
                    $index = $i + 2;
                    $flag_string = '';
                    while (isset($vals[$index]) && $vals[$index] != ')') {
                        $flag_string .= $vals[$index];
                        $index++;
                    }
                    if ($filter && $filter != 'ALL' && !$this->flag_match($filter, $flag_string)) {
                        continue 2;
                    }
                }
                if (strtoupper($v) == 'UID') {
                    if (isset($vals[($i + 1)])) {
                        $uid = $vals[$i + 1];
                    }
                }
                if ($key == strtoupper($v)) {
                    if (substr($key, 0, 4) == 'BODY') {
                        $body = 1;
                    }
                    elseif (isset($vals[($i + 1)])) {
                        if ($key == "INTERNALDATE") {
                            $sort_key = strtotime($vals[$i + 1]);
                        }
                        else {
                            $sort_key = $vals[$i + 1];
                        }
                    }
                }
            }
            if ($sort_key && $uid) {
                $sort_keys[$uid] = $sort_key;
                $uids[] = $uid;
            }
        }
        if (count($sort_keys) != count($uids)) {
            if (count($sort_keys) < count($uids)) {
                foreach ($uids as $v) {
                    if (!isset($sort_keys[$v])) {
                        $sort_keys[$v] = false;
                    }
                }
            }
        }
        natcasesort($sort_keys);
        $uids = array_keys($sort_keys);
        if ($reverse) {
            $uids = array_reverse($uids);
        }
        if ($status) {
            return $this->cache_return_val($uids, $cache_command);
        }
        return $uids;
    }

    /* ------------------ WRITE COMMANDS ----------------------------------- */

    /**
     * delete an existing mailbox
     *
     * @param $mailbox string IMAP mailbox name to delete
     * 
     * @return bool tru if the mailbox was deleted
     */
    public function delete_mailbox($mailbox) {
        if (!$this->is_clean($mailbox, 'mailbox')) {
            return false;
        }
        if ($this->read_only) {
            $this->debug[] = 'Delete mailbox not permitted in read only mode';
            return false;
        }
        $command = 'DELETE "'.str_replace('"', '\"', $this->utf7_encode($mailbox))."\"\r\n";
        $this->send_command($command);
        $result = $this->get_response(false);
        $status = $this->check_response($result, false);
        if ($status) {
            return true;
        }
        else {
            $this->debug[] = str_replace('A'.$this->command_count, '', $result[0]);
            return false;
        }
    }

    /**
     * rename and existing mailbox
     *
     * @param $mailbox string IMAP mailbox to rename
     * @param $new_mailbox string new name for the mailbox
     *
     * @return bool true if the rename operation worked
     */
    public function rename_mailbox($mailbox, $new_mailbox) {
        if (!$this->is_clean($mailbox, 'mailbox') || !$this->is_clean($new_mailbox, 'mailbox')) {
            return false;
        }
        if ($this->read_only) {
            $this->debug[] = 'Rename mailbox not permitted in read only mode';
            return false;
        }
        $command = 'RENAME "'.$this->utf7_encode($mailbox).'" "'.$this->utf7_encode($new_mailbox).'"'."\r\n";
        $this->send_command($command);
        $result = $this->get_response(false);
        $status = $this->check_response($result, false);
        if ($status) {
            return true;
        }
        else {
            $this->debug[] = str_replace('A'.$this->command_count, '', $result[0]);
            return false;
        }
    } 

    /**
     * create a new mailbox
     *
     * @param $mailbox string IMAP mailbox name
     *
     * @return bool true if the mailbox was created
     */
    public function create_mailbox($mailbox) {
        if (!$this->is_clean($mailbox, 'mailbox')) {
            return false;
        }
        if ($this->read_only) {
            $this->debug[] = 'Create mailbox not permitted in read only mode';
            return false;
        }
        $command = 'CREATE "'.$this->utf7_encode($mailbox).'"'."\r\n";
        $this->send_command($command);
        $result = $this->get_response(false);
        $status = $this->check_response($result, false);
        if ($status) {
            return true;
        }
        else {
            $this->debug[] =  str_replace('A'.$this->command_count, '', $result[0]);
            return false;
        }
    }

    /**
     * perform an IMAP action on a message
     *
     * @param $action string action to perform, can be one of READ, UNREAD, FLAG,
     *                       UNFLAG, ANSWERED, DELETE, UNDELETE, EXPUNGE, or COPY
     * @param $uids array/string an array of uids or a valid IMAP sequence set as a string
     * @param $mailbox string destination IMAP mailbox name for operations the require one
     */
    public function message_action($action, $uids, $mailbox=false) {
        $status = false;
        $uid_strings = array();
        if (is_array($uids)) {
            if (count($uids) > 1000) {
                while (count($uids) > 1000) { 
                    $uid_strings[] = implode(',', array_splice($uids, 0, 1000));
                }
                if (count($uids)) {
                    $uid_strings[] = implode(',', $uids);
                }
            }
            else {
                $uid_strings[] = implode(',', $uids);
            }
        }
        else {
            $uid_strings[] = $uids;
        }
        foreach ($uid_strings as $uid_string) {
            if ($uid_string) {
                if (!$this->is_clean($uid_string, 'uid_list')) {
                    return false;
                }
            }
            switch ($action) {
                case 'READ':
                    $command = "UID STORE $uid_string +FLAGS (\Seen)\r\n";
                    break;
                case 'FLAG':
                    $command = "UID STORE $uid_string +FLAGS (\Flagged)\r\n";
                    break;
                case 'UNFLAG':
                    $command = "UID STORE $uid_string -FLAGS (\Flagged)\r\n";
                    break;
                case 'ANSWERED':
                    $command = "UID STORE $uid_string +FLAGS (\Answered)\r\n";
                    break;
                case 'UNREAD':
                    $command = "UID STORE $uid_string -FLAGS (\Seen)\r\n";
                    break;
                case 'DELETE':
                    $command = "UID STORE $uid_string +FLAGS (\Deleted)\r\n";
                    break;
                case 'UNDELETE':
                    $command = "UID STORE $uid_string -FLAGS (\Deleted)\r\n";
                    break;
                case 'EXPUNGE':
                    $command = "EXPUNGE\r\n";
                    break;
                case 'COPY':
                    if (!$this->is_clean($mailbox, 'mailbox')) {
                        return false;
                    }
                    $command = "UID COPY $uid_string \"".$this->utf7_encode($mailbox)."\"\r\n";
                    break;
                case 'MOVE':
                    if ($this->is_supported('MOVE')) {
                        if (!$this->is_clean($mailbox, 'mailbox')) {
                            return false;
                        }
                        $command = "UID MOVE $uid_string \"".$this->utf7_encode($mailbox)."\"\r\n";
                    }
                    break;
            }
            if ($command) {
                $this->send_command($command);
                $res = $this->get_response();
                $status = $this->check_response($res);
            }
            if ($status) {
                $this->bust_cache( $this->selected_mailbox['name'] );
                if ($mailbox) {
                    $this->bust_cache($mailbox);
                }
            }
        }
        return $status;
    }

    /**
     * start writing a message to a folder with IMAP APPEND
     *
     * @param $mailbox string IMAP mailbox name
     * @param $size int size of the message to be written
     * @param $seen bool flag to mark the message seen
     *
     * $return bool true on success
     */
    public function append_start($mailbox, $size, $seen=true) {
        if (!$this->clean($mailbox, 'mailbox') || !$this->clean($size, 'uid')) {
            return false;
        }
        if ($seen) {
            $command = 'APPEND "'.$this->utf7_encode($mailbox).'" (\Seen) {'.$size."}\r\n";
        }
        else {
            $command = 'APPEND "'.$this->utf7_encode($mailbox).'" () {'.$size."}\r\n";
        }
        $this->send_command($command);
        $result = $this->fgets();
        if (substr($result, 0, 1) == '+') {
            return true;
        }
        else {
            return false;
        }
    }

    /**
     * write a line to an active IMAP APPEND operation
     *
     * @param $string string line to write
     *
     * @return int length written
     */
    public function append_feed($string) {
        fwrite($this->handle, $string);
        $res = strlen($string);
        return $res;
    }

    /**
     * finish an IMAP APPEND operation
     *
     * @return bool true on success
     */
    public function append_end() {
        $result = $this->get_response(false, true);
        return $this->check_response($result, true);
    }

    /* ------------------ HELPERS ------------------------------------------ */

    /**
     * convert a sequence string to an array
     *
     * @param $sequence string an IMAP sequence string
     * 
     * @return $array list of ids
     */
    public function convert_sequence_to_array($sequence) {
        $res = array();
        foreach (explode(',', $sequence) as $atom) {
            if (strstr($atom, ':')) {
                $markers = explode(':', $atom);
                if (ctype_digit($markers[0]) && ctype_digit($markers[1])) {
                    $res = array_merge($res, range($markers[0], $markers[1]));
                }
            }
            elseif (ctype_digit($atom)) {
                $res[] = $atom;
            }
        }
        return array_unique($res);
    }

    /**
     * convert an array into a sequence string
     *
     * @param $array array list of ids
     * 
     * @return string an IMAP sequence string
     */
    public function convert_array_to_sequence($array) {
        $res = '';
        $seq = false;
        $max = count($array) - 1;
        foreach ($array as $index => $value) {
            if (!isset($array[$index - 1])) {
                $res .= $value;
            }
            elseif ($seq) {
                $last_val = $array[$index - 1];
                if ($index == $max) {
                    $res .= $value;
                    break;
                }
                elseif ($last_val == $value - 1) {
                    continue;
                }
                else {
                    $res .= $last_val.','.$value;
                    $seq = false;
                }

            }
            else {
                $last_val = $array[$index - 1];
                if ($last_val == $value - 1) {
                    $seq = true;
                    $res .= ':';
                }
                else {
                    $res .= ','.$value;
                }
            }
        }
        return $res;
    }

    /**
     * decode mail fields to human readable text
     *
     * @param $string string field to decode
     *
     * @return string decoded field
     */
    public function decode_fld($string) {
        if (preg_match_all("/(=\?[^\?]+\?(q|b)\?[^\?]+\?=)/i", $string, $matches)) {
            foreach ($matches[1] as $v) {
                $fld = substr($v, 2, -2);
                $charset = strtolower(substr($fld, 0, strpos($fld, '?')));
                $fld = substr($fld, (strlen($charset) + 1));
                $encoding = $fld{0};
                $fld = substr($fld, (strpos($fld, '?') + 1));
                if (strtoupper($encoding) == 'B') {
                    $fld = mb_convert_encoding(base64_decode($fld), 'UTF-8', $charset);
                }
                elseif (strtoupper($encoding) == 'Q') {
                    $fld = mb_convert_encoding(quoted_printable_decode(str_replace('_', ' ', $fld)), 'UTF-8', $charset);
                }
                $string = str_replace($v, $fld, $string);
            }
        }
        return trim($string);
    } 

    /**
     * check if an IMAP extension is supported by the server
     *
     * @param $extension string name of an extension
     * 
     * @return bool true if the extension is supported
     */
    public function is_supported( $extension ) {
        return in_array(strtolower($extension), array_diff($this->supported_extensions, $this->blacklisted_extensions));
    }

    /**
     * returns current IMAP state
     *
     * @return string one of:
     *                disconnected  = no IMAP server TCP connection
     *                connected     = an IMAP server TCP connection exists
     *                authenticated = successfully authenticated to the IMAP server
     *                selected      = a mailbox has been selected
     */
    public function get_state() {
        return $this->state;
    }

    /**
     * output IMAP session debug info
     *
     * @param $full bool flag to enable full IMAP response display
     * @param $return bool flag to return the debug results instead of printing them
     *
     * @return void/string 
     */
    public function show_debug($full=false, $return=false) {
        $res = sprintf("\nDebug %s\n", print_r(array_merge($this->debug, $this->commands), true));
        if ($full) {
            $res .= sprintf("Response %s", print_r($this->responses, true));
        }
        if (!$return) {
            echo $res;
        }
        return $res;
    }

    /**
     * search a nested BODYSTRUCTURE response for a specific part
     *
     * @param $struct array the structure to search
     * @param $search_term string the search term
     * @param $search_flds array list of fields to search for the term
     *
     * @return array array of all matching parts from the message
     */
    public function search_bodystructure($struct, $search_flds, $all=true, $res=array()) {
        return $this->struct_object->recursive_search($struct, $search_flds, $all, $res);
    }

    /* ------------------ EXTENSIONS --------------------------------------- */

    /**
     * use the IMAP GETQUOTA command to fetch quota information
     *
     * @param $quota_root string named quota root to fetch
     * 
     * @return array list of quota details
     */
    public function get_quota($quota_root='') {
        $quotas = array();
        if ($this->is_supported('QUOTA')) {
            $command = 'GETQUOTA "'.$quota_root."\"\r\n";
            $this->send_command($command);
            $res = $this->get_response(false, true);
            if ($this->check_response($res, true)) {
                foreach($res as $vals) {
                    list($name, $max, $current) = $this->parse_quota_response($vals);
                    if ($max) {
                        $quotas[] = array('name' => $name, 'max' => $max, 'current' => $current);
                    }
                }
            }
        }
        return $quotas;
    }

    /**
     * use the IMAP GETQUOTAROOT command to fetch quota information about a mailbox
     *
     * @param $mailbox string IMAP folder to check
     * 
     * @return array list of quota details
     */
    public function get_quota_root($mailbox) {
        $quotas = array();
        if ($this->is_supported('QUOTA') && $this->is_clean($mailbox, 'mailbox')) {
            $command = 'GETQUOTAROOT "'. $this->utf7_encode($mailbox).'"'."\r\n";
            $this->send_command($command);
            $res = $this->get_response(false, true);
            if ($this->check_response($res, true)) {
                foreach($res as $vals) {
                    list($name, $max, $current) = $this->parse_quota_response($vals);
                    if ($max) {
                        $quotas[] = array('name' => $name, 'max' => $max, 'current' => $current);
                    }
                }
            }
        }
        return $quotas;
    }

    /**
     * use the ENABLE extension to tell the IMAP server what extensions we support
     *
     * @return array list of supported extensions that can be enabled
     */
    public function enable() {
        $extensions = array();
        if ($this->is_supported('ENABLE')) {
            $supported = array_diff($this->declared_extensions, $this->blacklisted_extensions);
            if ($this->is_supported('QRESYNC')) {
                $extension_string = implode(' ', array_filter($supported, function($val) { return $val != 'CONDSTORE'; }));
            }
            else {
                $extension_string = implode(' ', $supported);
            }
            if (!$extension_string) {
                return array();
            }
            $command = 'ENABLE '.$extension_string."\r\n";
            $this->send_command($command);
            $res = $this->get_response(false, true);
            if ($this->check_response($res, true)) {
                foreach($res as $vals) {
                    if (in_array('ENABLED', $vals)) {
                        $extensions[] = $this->get_adjacent_response_value($vals, -1, 'ENABLED');
                    }
                }
            }
            $this->enabled_extensions = $extensions;
            $this->debug[] = sprintf("Enabled extensions: ".implode(', ', $extensions));
        }
        return $extensions;
    }

    /**
     * unselect the selected mailbox
     *
     * @return bool true on success
     */
    public function unselect_mailbox() {
        $this->send_command("UNSELECT\r\n");
        $res = $this->get_response(false, true);
        $status = $this->check_response($res, true);
        if ($status) {
            $this->selected_mailbox = false;
        }
        return $status;
    }

    /**
     * use the ID extension
     *
     * @return array list of server properties on success
     */
    public function id() {
        $server_id = array();
        if ($this->is_supported('ID')) {
            $params = array(
                'name' => $this->app_name,
                'version' => $this->app_version,
                'vendor' => $this->app_vendor,
                'support-url' => $this->app_support_url,
            );
            $param_parts = array();
            foreach ($params as $name => $value) {
                $param_parts[] = '"'.$name.'" "'.$value.'"';
            }
            if (!empty($param_parts)) {
                $command = 'ID ('.implode(' ', $param_parts).")\r\n";
                $this->send_command($command);
                $result = $this->get_response(false, true);
                if ($this->check_response($result, true)) {
                    foreach ($result as $vals) {
                        if (in_array('name', $vals)) {
                            $server_id['name'] = $this->get_adjacent_response_value($vals, -1, 'name');
                        }
                        if (in_array('vendor', $vals)) {
                            $server_id['vendor'] = $this->get_adjacent_response_value($vals, -1, 'vendor');
                        }
                        if (in_array('version', $vals)) {
                            $server_id['version'] = $this->get_adjacent_response_value($vals, -1, 'version');
                        }
                        if (in_array('support-url', $vals)) {
                            $server_id['support-url'] = $this->get_adjacent_response_value($vals, -1, 'support-url');
                        }
                        if (in_array('remote-host', $vals)) {
                            $server_id['remote-host'] = $this->get_adjacent_response_value($vals, -1, 'remote-host');
                        }
                    }
                    $this->server_id = $server_id;
                    $res = true;
                }
            }
        }
        return $server_id;
    }

    /**
     * use the SORT extension to get a sorted UID list
     *
     * @param $sort string sort order. can be one of ARRIVAL, DATE, CC, TO, SUBJECT, FROM, or SIZE
     * @param $reverse bool flag to reverse the sort order
     * @param $filter string can be one of ALL, SEEN, UNSEEN ANSWERED, UNANSWERED, DELETED, UNDELETED, FLAGGED, or UNFLAGGED
     *
     * @return array list of IMAP message UIDs
     */
    public function get_message_sort_order($sort='ARRIVAL', $reverse=true, $filter='ALL', $esort=array()) {
        if (!$this->is_clean($sort, 'keyword') || !$this->is_clean($filter, 'keyword') || !$this->is_supported('SORT')) {
            return false;
        }
        $esort_enabled = false;
        $esort_res = array();
        $command = 'UID SORT ';
        if (!empty($esort) && $this->is_supported('ESORT')) {
            $valid = array_filter($esort, function($v) { return in_array($v, array('MIN', 'MAX', 'COUNT', 'ALL')); });
            if (!empty($valid)) {
                $esort_enabled = true;
                $command .= 'RETURN ('.implode(' ', $valid).') ';
            }
        }
        $command .= '('.$sort.') US-ASCII '.$filter."\r\n";
        $cache_command = $command.(string)$reverse;
        $cache = $this->check_cache($cache_command);
        if ($cache !== false) {
            return $cache;
        }
        $this->send_command($command);
        if ($this->sort_speedup) {
            $speedup = true;
        }
        else {
            $speedup = false;
        }
        $res = $this->get_response(false, true, 8192, $speedup);
        $status = $this->check_response($res, true);
        $uids = array();
        foreach ($res as $vals) {
            if ($vals[0] == '*' && strtoupper($vals[1]) == 'ESEARCH') {
                $esort_res = $this->parse_esearch_response($vals);
            }
            if ($vals[0] == '*' && strtoupper($vals[1]) == 'SORT') {
                array_shift($vals);
                array_shift($vals);
                $uids = array_merge($uids, $vals);
            }
            else {
                if (ctype_digit((string) $vals[0])) {
                    $uids = array_merge($uids, $vals);
                }
            }
        }
        if ($reverse) {
            $uids = array_reverse($uids);
        }
        if ($esort_enabled) {
            $uids = $esort_res;
        }
        if ($status) {
            return $this->cache_return_val($uids, $cache_command);
        }
        return $uids;
    }

    /**
     * search using the Google X-GM-RAW IMAP extension
     *
     * @param $start_str string formatted search string like "has:attachment in:unread"
     * 
     * @return array list of IMAP UIDs that match the search
     */
    public function google_search($search_str) {
        $uids = array();
        if ($this->is_supported('X-GM-EXT-1')) {
            $search_str = str_replace('"', '', $search_str);
            if ($this->is_clean($search_str, 'search_str')) {
                $command = "UID SEARCH X-GM-RAW \"".$search_str."\"\r\n";
                $this->send_command($command);
                $res = $this->get_response(false, true);
                $uids = array();
                foreach ($res as $vals) {
                    foreach ($vals as $v) {
                        if (ctype_digit((string) $v)) {
                            $uids[] = $v;
                        }
                    }
                }
            }
        }
        return $uids;
    }

    /**
     * attempt enable IMAP COMPRESS extension
     * TODO: currently does not work ...
     *
     * @return void
     */
    public function enable_compression() {
        if ($this->is_supported('COMPRESS=DEFLATE')) {
            $this->send_command("COMPRESS DEFLATE\r\n");
            $res = $this->get_response(false, true);
            if ($this->check_response($res, true)) {
                stream_filter_prepend($this->handle, 'zlib.inflate', STREAM_FILTER_READ);
                stream_filter_append($this->handle, 'zlib.deflate', STREAM_FILTER_WRITE, 6);
                $this->debug[] = 'DEFLATE compression extension activated';
            }
        }
    }

    /* ------------------ HIGH LEVEL --------------------------------------- */

    /**
     * return the formatted message content of the first part that matches the supplied MIME type
     *
     * @param $uid int IMAP UID value for the message
     * @param $type string Primary MIME type like "text"
     * @param $subtype string Secondary MIME type like "plain"
     *
     * @return string formatted message content, bool false if no matching part is found
     */
    public function get_first_message_part($uid, $type, $subtype=false, $struct=false) {
        if (!$subtype) {
            $flds = array('type' => $type);
        }
        else {
            $flds = array('type' => $type, 'subtype' => $subtype);
        }
        if (!$struct) {
            $struct = $this->get_message_structure($uid);
        }
        $matches = $this->search_bodystructure($struct, $flds, false);
        if (!empty($matches)) {

            $subset = array_slice(array_keys($matches), 0, 1);
            $msg_part_num = $subset[0];
            $struct = array_slice($matches, 0, 1);

            if (isset($struct[$msg_part_num])) {
                $struct = $struct[$msg_part_num];
            }
            elseif (isset($struct[0])) {
                $struct = $struct[0];
            }

            return array($msg_part_num, $this->get_message_content($uid, $msg_part_num, false, $struct));
        } 
        return false;
    }

    /**
     * return a list of headers and UIDs for a page of a mailbox
     *
     * @param $mailbox string the mailbox to access
     * @param $sort string sort order. can be one of ARRIVAL, DATE, CC, TO, SUBJECT, FROM, or SIZE
     * @param $filter string type of messages to include (UNSEEN, ANSWERED, ALL, etc)
     * @param $limit int max number of messages to return
     * @param $offset int offset from the first message in the list
     *
     * @return array list of headers
     */

    public function get_mailbox_page($mailbox, $sort, $rev, $filter, $offset=0, $limit=0) {
        $result = array();

        /* select the mailbox if need be */
        if (!$this->selected_mailbox || $this->selected_mailbox['name'] != $mailbox) {
            $this->select_mailbox($mailbox);
        }
 
        /* use the SORT extension if we can */
        if ($this->is_supported( 'SORT' )) {
            $uids = $this->get_message_sort_order($sort, $rev, $filter);
        }

        /* fall back to using FETCH and manually sorting */
        else {
            $uids = $this->sort_by_fetch($sort, $rev, $filter);
        }

        /* reduce to one page */
        if ($limit) {
            $uids = array_slice($uids, $offset, $limit, true);
        }

        /* get the headers and build a result array by UID */
        if (!empty($uids)) {
            $headers = $this->get_message_list($uids);
            foreach($uids as $uid) {
                if (isset($headers[$uid])) {
                    $result[$uid] = $headers[$uid];
                }
            }
        }
        return $result;
    }

    /**
     * return all the folders contained at a hierarchy level, and if possible, if they have sub-folders
     *
     * @param $level string mailbox name or empty string for the top level
     *
     * @return array list of matching folders
     */
    public function get_folder_list_by_level($level='') {
        $result = array();
        $folders = $this->get_mailbox_list(false, $level, '%');
        foreach ($folders as $name => $folder) {
            $result[$name] = array(
                'delim' => $folder['delim'],
                'basename' => $folder['basename'],
                'children' => $folder['has_kids'],
                'noselect' => $folder['noselect'],
                'name_parts' => $folder['name_parts'],
            );
        }
        return $result;
    }
}

?>
