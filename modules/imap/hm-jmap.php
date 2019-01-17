<?php

/**
 * JMAP lib
 * @package modules
 * @subpackage imap
 */

// jason [ ~/cypht ]$ egrep -o '\$imap->[a-z_]+' modules/*/modules.php | cut -f 2 -d '>' | sort | uniq -c | sort -n
//      1 append_end
//      1 append_feed
//      1 append_start
//      1 create_mailbox
//      1 delete_mailbox
//      1 get_folder_list_by_level
//      1 get_mailbox_page
//      1 get_mailbox_status
//      1 get_message_headers
//      1 get_namespaces
//      1 read_only
//      1 read_stream_line
//      1 rename_mailbox
//      1 start_message_stream
//      1 struct_object
//      2 get_message_structure
//      2 get_special_use_mailboxes
//      2 search_charset
//      3 get_message_list
//      3 selected_mailbox
//      4 get_first_message_part
//      4 get_message_content
//      4 search
//      6 search_bodystructure
//      9 folder_state
//      9 get_state
//     14 message_action
//     14 select_mailbox

/**
 * public interface to JMAP commands
 * @subpackage imap/lib
 */ 
class Hm_JMAP {

    private $api;
    private $session;
    private $api_url;
    private $account_id;
    private $headers;
    private $state = 'disconnected';
    private $requests = array();
    private $responses = array();
    private $folder_list = array();
    private $sorts = array(
        'ARRIVAL' => 'receivedAt',
        'FROM' => 'from',
        'TO' => 'to',
        'SUBJECT' => 'subject',
        'DATE' => 'sentAt'
    );
    private $default_caps = array(
        'urn:ietf:params:jmap:core',
        'urn:ietf:params:jmap:mail'
    );

    public $selected_mailbox;
    public $folder_state;
    public $use_cache = true;

    public function __construct() {
        $this->api = new Hm_API_Curl();
    }
    
    /* ------------------ CACHE METHODS ------------------------ */

    public function dump_cache() {
        return array(
            $this->session,
            $this->folder_list
        );
    }

    public function load_cache($data) {
        $this->session = $data[0];
        $this->folder_list = $data[1];
    }

    /* ------------------ CONNECT AND AUTH ------------------------ */

    public function connect($cfg) {
        $this->build_headers($cfg['username'], $cfg['password']);
        return $this->authenticate(
            $cfg['username'],
            $cfg['password'],
            $cfg['server'],
            $cfg['port']
        );
    }

    public function disconnect() {
        return true;
    }

    public function authenticate($username, $password, $url, $port) {
        if (is_array($this->session)) {
            $res = $this->session;
        }
        else {
            $auth_url = $this->prep_url($url, $port);
            $res = $this->send_command($auth_url, array(), 'GET');
        }
        if (is_array($res) &&
            array_key_exists('apiUrl', $res) &&
            array_key_exists('accounts', $res)) {

            $this->init_session($res, $url, $port);
            return true;
        }
        return false;
    }

    /* ------------------ UNSELECTED STATE COMMANDS ------------------------ */

    public function get_special_use_mailboxes($type=false) {
    }

    public function get_namespaces() {
    }

    public function select_mailbox($mailbox) {
        $this->setup_selected_mailbox($mailbox, 0);
        return true;
    }

    public function get_mailbox_status($mailbox, $args=array('UNSEEN', 'UIDVALIDITY', 'UIDNEXT', 'MESSAGES', 'RECENT')) {
    }

    /* ------------------ SELECTED STATE COMMANDS -------------------------- */

    public function get_message_list($uids) {
        $result = array();
        $body = array('size');
        $flds = array('receivedAt', 'sender', 'replyTo', 'sentAt',
            'hasAttachment', 'size', 'keywords', 'id', 'subject', 'from', 'to', 'messageId');
        $methods = array(array(
            'Email/get',
            array(
                'accountId' => $this->account_id,
                'ids' => $uids,
                'properties' => $flds,
                'bodyProperties' => $body
            ),
            'gml' 
        ));
        $res = $this->send_command($this->api_url, $methods);
        foreach ($this->search_response($res, array(0, 1, 'list')) as $msg) {
            $result[] = $this->normalize_headers($msg);
        }
        return $result;
    }

    public function get_message_structure($uid) {
        $struct = $this->get_raw_bodystructure($uid);
        $converted = $this->parse_bodystructure_response($struct);
        return $converted;
    }

    public function get_message_content($uid, $message_part, $max=false, $struct=true) {
        $methods = array(array(
            'Email/get',
            array(
                'accountId' => $this->account_id,
                'ids' => array($uid),
                'fetchAllBodyValues' => true,
                'properties' => array('bodyValues')
            ),
            'gmc' 
        ));
        $res = $this->send_command($this->api_url, $methods);
        return $this->search_response($res, array(0, 1, 'list', 0, 'bodyValues', $message_part, 'value'));
    }

    public function search($target='ALL', $uids=false, $terms=array(), $esearch=array(), $exclude_deleted=true, $exclude_auto_bcc=true, $only_auto_bcc=false) {
        // callers
        //$msgs = $imap->search($search_type, false, $terms, array(), true, false, true);
        //$msgs = $imap->search($search_type, false, $terms);
        //$msgs = $imap->search($search_type);
    }

    public function get_message_headers($uid, $message_part=false, $raw=false) {
        $flds = array('receivedAt', 'sender', 'replyTo', 'sentAt',
            'hasAttachment', 'size', 'keywords', 'id', 'subject', 'from', 'to', 'messageId');
        $methods = array(array(
            'Email/get',
            array(
                'accountId' => $this->account_id,
                'ids' => array($uid),
                'properties' => $flds,
                'bodyProperties' => array()
            ),
            'gmh' 
        ));
        $res = $this->send_command($this->api_url, $methods);
        $headers = $this->search_response($res, array(0, 1, 'list', 0), array());
        return $this->normalize_headers($headers);
    }

    public function start_message_stream($uid, $message_part) {
    }

    public function read_stream_line($size=1024) {
    }

    public function sort_by_fetch($sort, $reverse, $filter, $uid_str=false) {
    }

    /* ------------------ WRITE COMMANDS ----------------------------------- */

    public function delete_mailbox($mailbox) {
    }

    public function rename_mailbox($mailbox, $new_mailbox) {
    } 

    public function create_mailbox($mailbox) {
    }

    public function message_action($action, $uids, $mailbox=false, $keyword=false) {
    }

    public function append_start($mailbox, $size, $seen=true) {
    }

    public function append_feed($string) {
    }

    public function append_end() {
    }

    /* ------------------ HELPERS ------------------------------------------ */

    private function get_raw_bodystructure($uid) {
        $methods = array(array(
            'Email/get',
            array(
                'accountId' => $this->account_id,
                'ids' => array($uid),
                'properties' => array('bodyStructure')
            ),
            'gbs' 
        ));
        $res = $this->send_command($this->api_url, $methods);
        return $this->search_response($res, array(0, 1, 'list', 0, 'bodyStructure'), array());
    }

    private function parse_bodystructure_response($data) {
        $top = $this->translate_struct_keys($data);
        if (array_key_exists('subParts', $data)) {
            $top['subs'] = $this->parse_subs($data['subParts']);
        }
        return array($top);
    }
    
    private function parse_subs($data) {
        $res = array();
        foreach ($data as $sub) {
            $res[$sub['partId']] = $this->translate_struct_keys($sub);
            if (array_key_exists('subParts', $sub)) {
                $res['subs'] = $this->parse_subs($sub['subParts']);
            }
        }
        return $res;
    }

    private function translate_struct_keys($part) {
        return array(
            'type' => explode('/', $part['type'])[0],
            'subtype' => explode('/', $part['type'])[1],
            'attributes' => array('charset' => $part['charset'])
        );
    }

    private function normalize_headers($msg) {
        return array(
            'uid' => $msg['id'],
            'flags' => $this->keywords_to_flags($msg['keywords']),
            'internal_date' => $msg['receivedAt'],
            'size' => $msg['size'],
            'date' => $msg['sentAt'],
            'from' => $this->combine_addresses($msg['from']),
            'to' => $this->combine_addresses($msg['to']),
            'subject' => $msg['subject'],
            'content-type' => '',
            'timestamp' => strtotime($msg['receivedAt']),
            'charset' => '',
            'x-priority' => '',
            'type' => 'jmap',
            'references' => '',
            'message_id' => implode(' ', $msg['messageId']),
            'x_auto_bcc' => ''
        );
    }

    private function init_session($data, $url, $port) {
        $this->state = 'authenticated';
        $this->session = $data;
        $this->api_url = sprintf(
            '%s:%s%s',
            preg_replace("/\/$/", '', $url),
            $port,
            $data['apiUrl']
        );
        $this->account_id = array_keys($data['accounts'])[0];
        $this->folder_list = $this->build_imap_folders($this->get_folder_list());
    }

    private function keywords_to_flags($keywords) {
        $flags = array();
        if (array_key_exists('$seen', $keywords) && $keywords['$seen']) {
            $flags[] = '\Seen';
        }
        return implode(' ', $flags);
    }

    private function combine_addresses($addrs) {
        $res = array();
        foreach ($addrs as $addr) {
            $res[] = implode(' ', $addr);
        }
        return implode(', ', $res);
    }
    private function send_command($url, $methods=array(), $method='POST', $post=array()) {
        $body = '';
        if (count($methods) > 0) {
            $body = $this->format_request($methods);
        }
        $this->requests[] = array($url, $this->headers, $body, $method, $post);
        $res = $this->api->command($url, $this->headers, $post, $body, $method);
        $this->responses[] = $res;
        return $res;
    }

    private function format_request($methods, $caps=array()) {
        return json_encode(array(
            'using' => count($caps) == 0 ? $this->default_caps : $caps,
            'methodCalls' => $methods
        ));
    }

    private function build_headers($user, $pass) {
        $this->headers = array(
            'Authorization: Basic '. base64_encode(sprintf('%s:%s', $user, $pass)),
            'Cache-Control: no-cache, no-store, must-revalidate',
            'Content-Type: application/json',
            'Accept: application/json'
        );
    }

    private function prep_url($url, $port) {
        $url = preg_replace("/\/$/", '', $url);
        if ($port == 80 || $port == 443) {
            return sprintf('%s/.well-known/jmap/', $url);
        }
        return sprintf('%s:%s/.well-known/jmap/', $url, $port);
    }

    private function search_response($data, $key_path, $default=false) {
        array_unshift($key_path, 'methodResponses');
        foreach ($key_path as $key) {
            if (is_array($data) && array_key_exists($key, $data)) {
                $data = $data[$key];
            }
            else {
                return $default;
            }
        }
        return $data;
    }

    public function is_supported($extension) {
    }

    public function get_state() {
        return $this->state;
    }

    public function show_debug() {
        return array(
            'commands' => $this->requests,
            'responses' => $htis->responses
        );
    }

    private function setup_selected_mailbox($mailbox, $total) {
        $this->selected_mailbox = array('detail' => array(
            'selected' => 1,
            'name' => $mailbox,
            'exists' => $total
        ));
    }

    public function search_bodystructure($struct, $search_flds, $all=true, $res=array()) {
        $this->struct_object = new Hm_IMAP_Struct(array(), $this);
        $res = $this->struct_object->recursive_search($struct, $search_flds, $all, $res);
        return $res;
    }

    private function parse_folder_list_by_level($level) {
        $result = array();
        foreach ($this->folder_list as $name => $folder) {
            if ($folder['level'] == $level) {
                $result[$name] = $folder;
            }
        }
        return $result;
    }

    private function build_imap_folders($data) {
        $lookup = array();
        $result = array();
        $delim = '.';
        foreach ($data as $vals) {
            $vals['children'] = false;
            $lookup[$vals['id']] = $vals;
        }
        foreach ($data as $vals) {
            if ($vals['parentId']) {
                $parents = $this->get_parent_recursive($vals, $lookup, $parents=array());
                $level = implode($delim, $parents);
                $parents[] = $vals['name'];
                $lookup[$vals['parentId']]['children'] = true;
            }
            else {
                $parents = array($vals['name']);
                $level = '';
            }
            $lookup[$vals['id']]['basename'] = $vals['name'];
            $lookup[$vals['id']]['level'] = $level;
            $lookup[$vals['id']]['name_parts'] = $parents;
            $lookup[$vals['id']]['name'] = implode($delim, $parents);
        }
        foreach ($lookup as $vals) {
            $result[$vals['name']] = array(
                    'delim' => $delim,
                    'level' => $vals['level'],
                    'basename' => $vals['basename'],
                    'children' => $vals['children'],
                    'name' => $vals['name'],
                    'type' => 'jmap',
                    'noselect' => false,
                    'id' => $vals['id'],
                    'name_parts' => $vals['name_parts']
                );
        }
        return $result;
    }

    private function get_parent_recursive($vals, $lookup, $parents) {
        $vals = $lookup[$vals['parentId']];
        $parents[] = $vals['name'];
        if ($vals['parentId']) {
            $parents = $this->get_parent_recursive($vals, $lookup, $parents);
        }
        return $parents;
    }

    private function folder_name_to_id($name) {
        if (count($this->folder_list) == 0) {
            $this->folder_list = $this->build_imap_folders($this->get_folder_list());
        }
        if (array_key_exists($name, $this->folder_list)) {
            return $this->folder_list[$name]['id'];
        }
        return false;
    }

    /* ------------------ HIGH LEVEL --------------------------------------- */

    public function get_first_message_part($uid, $type, $subtype=false, $struct=false) {
        if (!$subtype) {
            $flds = array('type' => $type);
        }
        else {
            $flds = array('type' => $type, 'subtype' => $subtype);
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
        return array(false, false);
    }

    public function get_mailbox_page($mailbox, $sort, $rev, $filter, $offset=0, $limit=0, $keyword=false) {
        $mailbox = $this->folder_name_to_id($mailbox);
        $filter = array('inMailbox' => $mailbox);
        if ($keyword) {
            $filter['hasKeyword'] = $keyword;
        }
        $methods = array(
            array(
                'Email/query',
                array(
                    'accountId' => $this->account_id,
                    'filter' => $filter,
                    'sort' => array(array(
                        'property' => $this->sorts[$sort],
                        'isAscending' => $rev ? false : true
                    )),
                    'position' => $offset,
                    'limit' => $limit,
                    'calculateTotal' => true
                ),
                'gmp' 
            )
        );
        $res = $this->send_command($this->api_url, $methods);
        $total = $this->search_response($res, array(0, 1, 'total'), 0);
        $msgs = $this->get_message_list($this->search_response($res, array(0, 1, 'ids'), array()));
        $this->setup_selected_mailbox($mailbox, $total);
        return array($total, $msgs);
    }

    public function get_folder_list() {
        $methods = array(array(
            'Mailbox/get',
            array(
                'accountId' => $this->account_id,
                'ids' => NULL
            ),
            'fl' 
        ));
        $res = $this->send_command($this->api_url, $methods);
        return $this->search_response($res, array(0, 1, 'list'), array());
    }

    public function get_folder_list_by_level($level=false) {
        if (!$level) {
            $level = '';
        }
        if (count($this->folder_list) == 0) {
            $this->folder_list = $this->build_imap_folders($this->get_folder_list());
        }
        return $this->parse_folder_list_by_level($level);
    }
}
