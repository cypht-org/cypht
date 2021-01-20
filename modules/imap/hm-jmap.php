<?php

/**
 * JMAP lib
 * @package modules
 * @subpackage imap
 *
 * This is intended to be a "drop in" replacement for the hm-imap.php class.
 * It mimics the public interface of that class to minimize changes required
 * to the modules.php code. Because of this a number of methods have unused
 * arguments that must be left in place to maintain compatible behavior. JMAP
 * simply does not need those arguments. They will be denoted with (ignored)
 * in the doc string for that method.
 *
 * There is a lot of room for improvement by "chaining" JMAP methods together
 * into a single API request and using "back reference" suppport. Once this
 * is working solidly it's definitely something we should look into.
 *
 */


/**
 * TODO:
 * - support multiple accounts per JMAP connection.
 * - Update move/copy for multiple mailboxId refs (patch)
 * - mailbox state handling
 * - pipeline where we can with back refs
 * - disable download of multipart types
 * - recurse into nested multipart types for bodystruct
 */

/**
 * public interface to JMAP commands
 * @subpackage imap/lib
 */
class Hm_JMAP {

    private $api;
    private $session;
    private $api_url;
    private $download_url;
    private $upload_url;
    private $account_id;
    private $headers;
    private $delim = '.';
    private $state = 'disconnected';
    private $requests = array();
    private $responses = array();
    private $folder_list = array();
    private $streaming_msg = '';
    private $msg_part_id = 0;
    private $append_mailbox = false;
    private $append_seen = false;
    private $append_result = false;
    private $sorts = array(
        'ARRIVAL' => 'receivedAt',
        'FROM' => 'from',
        'TO' => 'to',
        'SUBJECT' => 'subject',
        'DATE' => 'sentAt'
    );
    private $mod_map = array(
        'READ' => array('$seen', true),
        'UNREAD' => array('$seen', NULL),
        'FLAG' => array('$flagged', true),
        'UNFLAG' => array('$flagged', NULL),
        'ANSWERED' => array('$answered', true),
        'UNANSWERED' => array('$answered', NULL)
    );
    private $default_caps = array(
        'urn:ietf:params:jmap:core',
        'urn:ietf:params:jmap:mail'
    );

    public $selected_mailbox;
    public $folder_state = array();
    public $use_cache = true;
    public $read_only = false;
    public $server_type = 'JMAP';

    /** 
     * PUBLIC INTERFACE
     */

    public function __construct() {
        $this->api = new Hm_API_Curl();
    }

    /**
     * Looks for special use folders like sent or trash
     * @param string $type folder type
     * @return array
     */
    public function get_special_use_mailboxes($type=false) {
        $res = array();
        foreach ($this->folder_list as $name => $vals) {
            if ($type && strtolower($vals['role']) == strtolower($type)) {
                return array($type => $name);
            }
            elseif ($vals['role']) {
                $res[$type] = $name;
            }
        }
        return $res;
    }

    /**
     * Get the status of a mailbox
     * @param string $mailbox mailbox name
     * @param array $args values to check for (ignored)
     * @return
     */
    public function get_mailbox_status($mailbox, $args=array('UNSEEN', 'UIDVALIDITY', 'UIDNEXT', 'MESSAGES', 'RECENT')) {
        $methods = array(array(
            'Mailbox/get',
            array(
                'accountId' => $this->account_id,
                'ids' => array($this->folder_name_to_id($mailbox))
            ),
            'gms'
        ));
        $res = $this->send_and_parse($methods, array(0, 1, 'list', 0), array());
        $this->folder_state = array(
            'messages' => $res['totalEmails'],
            'unseen' => $res['unreadEmails'],
            'uidvalidity' => false,
            'uidnext' => false,
            'recent' => false,
        );
        return $this->folder_state;
    }

    /**
     * Fake start to IMAP APPEND
     * @param string $mailbox the mailbox to add a message to
     * @param integer $size the size of the message (ignored)
     * @param boolean $seen flag to mark the new message as read
     * @return true
     */
    public function append_start($mailbox, $size, $seen=true) {
        $this->append_mailbox = $mailbox;
        $this->append_seen = $seen;
        return true;
    }

    /**
     * Normally this would be a single line of data to feed to an IMAP APPEND
     * command. For JMAP it is the whole message which we first upload to the
     * server then import into the mailbox
     * @param string $string the raw message to append
     * @return boolean
     */
    public function append_feed($string) {
        $blob_id = $this->upload_file($string);
        if (!$blob_id) {
            return false;
        }
        $emails = array(
            'mailboxIds' => array($this->folder_name_to_id($this->append_mailbox) => true),
            'blobId' => $blob_id
        );
        if ($this->append_seen) {
            $emails['keywords'] =  array('$seen' => true);
        }
        $methods = array(array(
            'Email/import',
            array(
                'accountId' => $this->account_id,
                'emails' => array(NULL => $emails)
            ),
            'af'
        ));
        $res = $this->send_and_parse($methods, array(0, 1, 'created', NULL), array());
        $this->append_result = is_array($res) && array_key_exists('id', $res);
    }

    /**
     * Fake end of IMAP APPEND command.
     * @return boolean
     */
    public function append_end() {
        $res = $this->append_result;
        $this->append_result = false;
        $this->append_mailbox = false;
        $this->append_seen = false;
        return $res;
    }

    /**
     * Normally whould start streaming data for an IMAP message part, but with
     * JMAP we donwload the whole thing into $this->streaming_msg
     * @param string $uid message uid
     * @param string $message_part message part id
     * @return integer
     */
    public function start_message_stream($uid, $message_part) {
        list($blob_id, $name) = $this->download_details($uid, $message_part);
        if (!$name || !$blob_id) {
            return 0;
        }
        $this->streaming_msg = $this->get_raw_message_content($blob_id, $name);
        return strlen($this->streaming_msg);
    }

    /**
     * Normally would stream one line from IMAP at a time, with JMAP it's the
     * whole thing
     * @param integer $size line size (ignored)
     * @return string
     */
    public function read_stream_line($size=1024) {
        $res = $this->streaming_msg;
        $this->streaming_msg = false;
        return $res;
    }

    /**
     * Create a new mailbox
     * @param string $mailbox the mailbox name to create
     * @return boolean
     */
    public function create_mailbox($mailbox) {
        list($name, $parent) = $this->split_name_and_parent($mailbox);
        $methods = array(array(
            'Mailbox/set',
            array(
                'accountId' => $this->account_id,
                'create' => array(NULL => array('parentId' => $parent, 'name' => $name))
            ),
            'cm'
        ));
        $created = $this->send_and_parse($methods, array(0, 1, 'created'), array());
        $this->reset_folders();
        return $created && count($created) > 0;
    }

    /**
     * Delete an existing mailbox
     * @param string $mailbox the mailbox name to delete
     * @return boolean
     */
    public function delete_mailbox($mailbox) {
        $ids = array($this->folder_name_to_id($mailbox));
        $methods = array(array(
            'Mailbox/set',
            array(
                'accountId' => $this->account_id,
                'destroy' => $ids
            ),
            'dm'
        ));
        $destroyed = $this->send_and_parse($methods, array(0, 1, 'destroyed'), array());
        $this->reset_folders();
        return $destroyed && count($destroyed) > 0;
    }

    /**
     * Rename a mailbox
     * @param string $mailbox mailbox to rename
     * @param string $new_mailbox new name
     * @return boolean
     */
    public function rename_mailbox($mailbox, $new_mailbox) {
        $id = $this->folder_name_to_id($mailbox);
        list($name, $parent) = $this->split_name_and_parent($new_mailbox);
        $methods = array(array(
            'Mailbox/set',
            array(
                'accountId' => $this->account_id,
                'update' => array($id => array('parentId' => $parent, 'name' => $name))
            ),
            'rm'
        ));
        $updated = $this->send_and_parse($methods, array(0, 1, 'updated'), array());
        $this->reset_folders();
        return $updated && count($updated) > 0;
    }

    /**
     * Return the IMAP connection state (authenticated, connected, etc)
     * @return string
     */
    public function get_state() {
        return $this->state;
    }

    /**
     * Return debug info about the JMAP session requests and responses
     * @return array
     */
    public function show_debug() {
        return array(
            'commands' => $this->requests,
            'responses' => $this->responses
        );
    }

    /**
     * Fetch the first viewable message part of an E-mail
     * @param string $uid message uid
     * @param string $type the primary mime type
     * @param string $subtype the secondary mime type
     * @param array $struct The message structure
     * @return array
     */
    public function get_first_message_part($uid, $type, $subtype=false, $struct=false) {
        if (!$subtype) {
            $flds = array('type' => $type);
        }
        else {
            $flds = array('type' => $type, 'subtype' => $subtype);
        }
        $matches = $this->search_bodystructure($struct, $flds, false);
        if (empty($matches)) {
            return array(false, false);
        }

        $subset = array_slice(array_keys($matches), 0, 1);
        $msg_part_num = $subset[0];
        return array($msg_part_num, $this->get_message_content($uid, $msg_part_num));
    }

    /**
     * Return a list of headers and UIDs for a page of a mailbox
     * @param string $mailbox the mailbox to access
     * @param string $sort sort order. can be one of ARRIVAL, DATE, CC, TO, SUBJECT, FROM, or SIZE
     * @param string $filter type of messages to include (UNSEEN, ANSWERED, ALL, etc)
     * @param int $limit max number of messages to return
     * @param int $offset offset from the first message in the list
     * @param string $keyword optional keyword to filter the results by
     * @return array list of headers
     */
    public function get_mailbox_page($mailbox, $sort, $rev, $filter, $offset=0, $limit=0, $keyword=false) {
        $this->select_mailbox($mailbox);
        $mailbox_id = $this->folder_name_to_id($mailbox);
        $filter = array('inMailbox' => $mailbox_id);
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

    /**
     * Return all the folders contained at a hierarchy level, and if possible, if they have sub-folders
     * @param string $level mailbox name or empty string for the top level
     * @return array list of matching folders
     */
    public function get_folder_list_by_level($level=false) {
        if (!$level) {
            $level = '';
        }
        if (count($this->folder_list) == 0) {
            $this->reset_folders();
        }
        return $this->parse_folder_list_by_level($level);
    }

    /**
     * Return cached data
     * @return array
     */
    public function dump_cache() {
        return array(
            $this->session,
            $this->folder_list
        );
    }

    /**
     * Load cached data
     * @param array $data cache
     * @return void
     */
    public function load_cache($data) {
        $this->session = $data[0];
        $this->folder_list = $data[1];
    }

    /**
     * "connect" to a JMAP server by testing an auth request
     * @param array $cfg JMAP configuration
     * @return boolean
     */
    public function connect($cfg) {
        $this->build_headers($cfg['username'], $cfg['password']);
        return $this->authenticate(
            $cfg['username'],
            $cfg['password'],
            $cfg['server']
        );
    }

    /**
     * Fake a disconnect. JMAP is stateless so there is no disconnect
     * @return true
     */
    public function disconnect() {
        return true;
    }

    /**
     * Attempt an auth to JMAP and record the session detail
     * @param string $username user to login
     * @param string $password user password
     * @param string $url JMAP url
     * @return boolean
     */
    public function authenticate($username, $password, $url) {
        if (is_array($this->session)) {
            $res = $this->session;
        }
        else {
            $auth_url = $this->prep_url($url);
            $res = $this->send_command($auth_url, array(), 'GET');
        }
        if (is_array($res) &&
            array_key_exists('apiUrl', $res) &&
            array_key_exists('accounts', $res)) {

            $this->init_session($res, $url);
            return true;
        }
        return false;
    }

    /**
     * Fetch a list of all folders from JMAP
     * @return array
     */
    public function get_folder_list() {
        $methods = array(array(
            'Mailbox/get',
            array(
                'accountId' => $this->account_id,
                'ids' => NULL
            ),
            'fl'
        ));
        return $this->send_and_parse($methods, array(0, 1, 'list'), array());
    }

    /**
     * Fake IMAP namespace support
     * @return array
     */
    public function get_namespaces() {
        return array(array(
            'class' => 'personal',
            'prefix' => false,
            'delim' => $this->delim
        ));
    }

    /**
     * Fake selected a mailbox by getting it's current state
     * @param string $mailbox mailbox to select
     * @return true
     */
    public function select_mailbox($mailbox) {
        $this->get_mailbox_status($mailbox);
        $this->setup_selected_mailbox($mailbox, 0);
        return true;
    }

    /**
     * Get a list of message headers for a set of uids
     * @param array $uids list of uids
     * @return array
     */
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
        foreach ($this->send_and_parse($methods, array(0, 1, 'list'), array()) as $msg) {
            $result[] = $this->normalize_headers($msg);
        }
        return $result;
    }

    /**
     * Get the bodystructure of a message
     * @param string $uid message uid
     * @return array
     */
    public function get_message_structure($uid) {
        $struct = $this->get_raw_bodystructure($uid);
        $converted = $this->parse_bodystructure_response($struct);
        return $converted;
    }

    /**
     * Get a message part or the raw message if the part is 0
     * @param string $uid message uid
     * @param string $message_part the IMAP messge part "number"
     * @param int $max max size to return (ignored)
     * @param array $struct message structure (ignored)
     * @return string
     */
    public function get_message_content($uid, $message_part, $max=false, $struct=false) {
        if ($message_part == 0) {
            $methods = array(array(
                'Email/get',
                array(
                    'accountId' => $this->account_id,
                    'ids' => array($uid),
                    'properties' => array('blobId')
                ),
                'gmc'
            ));
            $blob_id = $this->send_and_parse($methods, array(0, 1, 'list', 0, 'blobId'), false);
            if (!$blob_id) {
                return '';
            }
            return $this->get_raw_message_content($blob_id, 'message');
        }
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
        if (!$this->read_only) {
            $this->message_action('READ', array($uid));
        }
        return $this->send_and_parse($methods, array(0, 1, 'list', 0, 'bodyValues', $message_part, 'value'));
    }

    /**
     * Search a field for a keyword
     * @param string $target message types to search. can be ALL, UNSEEN, ANSWERED, etc
     * @param mixed $uids an array of uids
     * @param string $fld optional field to search
     * @param string $term optional search term
     * @param bool $exclude_deleted extra argument to exclude messages with the deleted flag (ignored)
     * @param bool $exclude_auto_bcc don't include auto-bcc'ed messages (ignored)
     * @param bool $only_auto_bcc only include auto-bcc'ed messages (ignored)
     * @return array
     */
    public function search($target='ALL', $uids=false, $terms=array(), $esearch=array(), $exclude_deleted=true, $exclude_auto_bcc=true, $only_auto_bcc=false) {
        $mailbox_id = $this->folder_name_to_id($this->selected_mailbox['detail']['name']);
        $filter = array('inMailbox' => $mailbox_id);
        if ($target == 'UNSEEN') {
            $filter['notKeyword'] = '$seen';
        }
        elseif ($target == 'FLAGGED') {
            $filter['hasKeyword'] = '$flagged';
        }
        if ($converted_terms = $this->process_imap_search_terms($terms)) {
            $filter = $converted_terms + $filter;
        }
        $methods = array(
            array(
                'Email/query',
                array(
                    'accountId' => $this->account_id,
                    'filter' => $filter,
                ),
                's'
            )
        );
        return $this->send_and_parse($methods, array(0, 1, 'ids'), array());
    }

    /**
     * Get the full headers for an E-mail
     * @param string $uid message uid
     * @param string $message_part IMAP message part "number":
     * @param boolean $raw (ignored)
     * @return array
     */
    public function get_message_headers($uid, $message_part=false, $raw=false) {
        $methods = array(array(
            'Email/get',
            array(
                'accountId' => $this->account_id,
                'ids' => array($uid),
                'properties' => array('headers'),
                'bodyProperties' => array()
            ),
            'gmh'
        ));
        $headers = $this->send_and_parse($methods, array(0, 1, 'list', 0, 'headers'), array());
        $res = array();
        if (is_array($headers)) {
            foreach ($headers as $vals) {
                if (array_key_exists($vals['name'], $res)) {
                    if (!is_array($res[$vals['name']])) {
                        $res[$vals['name']] = array($res[$vals['name']]);
                    }
                    $res[$vals['name']][] = $vals['value'];
                }
                else {
                    $res[$vals['name']] = $vals['value'];
                }
            }
        }
        return $res;
    }

    /**
     * Move, Copy, delete, or set a keyword on an E-mail
     * @param string $action the actions to perform
     * @param string $uid message uid
     * @param string $mailbox the target mailbox
     * @param string $keyword  ignored)
     * @return boolean
     */
    public function message_action($action, $uids, $mailbox=false, $keyword=false) {
        $methods = array();
        $key =false;
        if (array_key_exists($action, $this->mod_map)) {
            $methods = $this->modify_msg_methods($action, $uids);
            $key = 'updated';
        }
        elseif ($action == 'DELETE') {
            $methods = $this->delete_msg_methods($uids);
            $key = 'destroyed';
        }
        elseif (in_array($action, array('MOVE', 'COPY'), true)) {
            $methods = $this->move_copy_methods($action, $uids, $mailbox);
            $key = 'updated';
        }
        if (!$key) {
            return false;
        }
        $changed_uids = array_keys($this->send_and_parse($methods, array(0, 1, $key), array()));
        return count($changed_uids) == count($uids);
    }

    /**
     * Search a bodystructure for a message part
     * @param array $struct the structure to search
     * @param string $search_term the search term
     * @param array $search_flds list of fields to search for the term
     * @return array
     */
    public function search_bodystructure($struct, $search_flds, $all=true, $res=array()) {
        $this->struct_object = new Hm_IMAP_Struct(array(), $this);
        $res = $this->struct_object->recursive_search($struct, $search_flds, $all, $res);
        return $res;
    }

    /**
     * PRIVATE HELPER METHODS
     */

    /**
     * Build JMAP keyword args to move or copy messages
     * @param string $action move or copy
     * @param array $uids message uids to act on
     * @param string $mailbox target mailbox
     * @return array
     */
    private function move_copy_methods($action, $uids, $mailbox) {
        if ($action == 'MOVE') {
            $mailbox_ids = array('mailboxIds' => array($this->folder_name_to_id($mailbox) => true));
        }
        else {
            $mailbox_ids = array('mailboxIds' => array(
                $this->folder_name_to_id($this->selected_mailbox['detail']['name']) => true,
                $this->folder_name_to_id($mailbox) => true));
        }
        $keywords = array();
        foreach ($uids as $uid) {
            $keywords[$uid] = $mailbox_ids;
        }
        return array(array(
            'Email/set',
            array(
                'accountId' => $this->account_id,
                'update' => $keywords
            ),
            'ma'
        ));
    }

    /**
     * Build JMAP memthod for setting keywords
     * @param string $action the keyword to set
     * @param array $uids message uids
     * @return array
     */
    private function modify_msg_methods($action, $uids) {
        $keywords = array();
        $jmap_keyword = $this->mod_map[$action];
        foreach ($uids as $uid) {
            $keywords[$uid] = array(sprintf('keywords/%s', $jmap_keyword[0]) => $jmap_keyword[1]);
        }
        return array(array(
            'Email/set',
            array(
                'accountId' => $this->account_id,
                'update' => $keywords
            ),
            'ma'
        ));
    }

    /**
     * Build JMAP memthod for deleting an E-mail
     * @param array $uids message uids
     * @return array
     */
    private function delete_msg_methods($uids) {
        return array(array(
            'Email/set',
            array(
                'accountId' => $this->account_id,
                'destroy' => $uids
            ),
            'ma'
        ));
    }

    /**
     * Get the bodystructure from JMAP for a message
     * @param string $uid message uid
     * @return array
     */
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

    /**
     * Parse a bodystructure response and mimic the IMAP lib
     * @param array $data raw bodstructure
     * @return array
     */
    private function parse_bodystructure_response($data) {
        $top = $this->translate_struct_keys($data);
        if (array_key_exists('subParts', $data)) {
            $top['subs'] = $this->parse_subs($data['subParts']);
        }
        if (array_key_exists('partId', $data)) {
            return array($data['partId'] => $top);
        }
        return array($top);
    }

    /**
     * Recursive function to parse bodstructure sub parts
     * @param array $data bodystructure
     * @return array
     */
    private function parse_subs($data) {
        $res = array();
        foreach ($data as $sub) {
            if ($sub['partId']) {
                $this->msg_part_id = $sub['partId'];
            }
            else {
                $sub['partId'] = $this->msg_part_id + 1;
            }
            $res[$sub['partId']] = $this->translate_struct_keys($sub);
            if (array_key_exists('subParts', $sub)) {
                $res[$sub['partId']]['subs'] = $this->parse_subs($sub['subParts']);
            }
        }
        return $res;
    }

    /**
     * Translate bodystructure keys from JMAP to IMAP-ish
     * @param array $part singe message part bodystructure
     * @return array
     */
    private function translate_struct_keys($part) {
        return array(
            'type' => explode('/', $part['type'])[0],
            'name' => $part['name'],
            'subtype' => explode('/', $part['type'])[1],
            'blob_id' => array_key_exists('blobId', $part) ? $part['blobId'] : false,
            'size' => $part['size'],
            'attributes' => array('charset' => $part['charset'])
        );
    }

    /**
     * Convert JMAP headers for a message list to IMAP-ish
     * @param array $msg headers for a message
     * @return array
     */
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

    /**
     * Start a JMAP session
     * @param array $data JMAP auth response
     * @param string $url url to access JMAP
     * @return void
     */
    private function init_session($data, $url) {
        $this->state = 'authenticated';
        $this->session = $data;
        $this->api_url = sprintf(
            '%s%s',
            preg_replace("/\/$/", '', $url),
            $data['apiUrl']
        );
        $this->download_url = sprintf(
            '%s%s',
            preg_replace("/\/$/", '', $url),
            $data['downloadUrl']
        );
        $this->upload_url = sprintf(
            '%s%s',
            preg_replace("/\/$/", '', $url),
            $data['uploadUrl']
        );
        foreach ($data['accounts'] as $account) {
            if (array_key_exists('isPrimary', $account) && $account['isPrimary']) {
                $this->account_id = array_keys($data['accounts'])[0];
                break;
            }
        }
        if ($this->account_id && count($this->folder_list) == 0) {
            $this->reset_folders();
        }
    }

    /**
     * Convert JMAP keywords to an IMAP flag string
     * @param array $keyworkds JMAP keywords
     * @return string
     */
    private function keywords_to_flags($keywords) {
        $flags = array();
        if (array_key_exists('$seen', $keywords) && $keywords['$seen']) {
            $flags[] = '\Seen';
        }
        if (array_key_exists('$flagged', $keywords) && $keywords['$flagged']) {
            $flags[] = '\Flagged';
        }
        if (array_key_exists('$answered', $keywords) && $keywords['$answered']) {
            $flags[] = '\Answered';
        }
        return implode(' ', $flags);
    }

    /**
     * Combine parsed addresses
     * @param array $addr JMAP address field
     * @return string
     */
    private function combine_addresses($addrs) {
        $res = array();
        foreach ($addrs as $addr) {
            $res[] = implode(' ', $addr);
        }
        return implode(', ', $res);
    }

    /**
     * Allow callers to the JMAP API to override a default HTTP header
     * @param array $headers list of headers to override
     * @return array
     */
    private function merge_headers($headers) {
        $req_headers = $this->headers;
        foreach ($headers as $index => $val) {
            $req_headers[$index] = $val;
        }
        return $req_headers;
    }

    /**
     * Send a "command" or a set of methods to JMAP
     * @param string $url the JMAP url
     * @param array $methods the methods to run
     * @param string $method the HTTP method to use
     * @param array $post optional HTTP POST BOdy
     * @param array $headers custom HTTP headers
     * @return array
     */
    private function send_command($url, $methods=array(), $method='POST', $post=array(), $headers=array()) {
        $body = '';
        if (count($methods) > 0) {
            $body = $this->format_request($methods);
        }
        $headers = $this->merge_headers($headers);
        $this->requests[] = array($url, $headers, $body, $method, $post);
        return $this->api->command($url, $headers, $post, $body, $method);
    }

    /**
     * Search a JMAP response for what we care about
     * @param array $data the response
     * @param array $key_path the path to the key we want
     * @param mixed $default what to return if we don't find the key path
     * @return mixed
     */
    private function search_response($data, $key_path, $default=false) {
        array_unshift($key_path, 'methodResponses');
        foreach ($key_path as $key) {
            if (is_array($data) && array_key_exists($key, $data)) {
                $data = $data[$key];
            }
            else {
                Hm_Debug::add('Failed to find key path in response');
                Hm_Debug::add('key path: '.print_r($key_path, true));
                Hm_Debug::add('data: '.print_r($data, true));
                return $default;
            }
        }
        return $data;
    }

    /**
     * Send and parse a set of JMAP methods
     * @param array $methods JMAP methods to execute
     * @param array $key_path path to the response key we want
     * @param mixed $default what to return if we don't find the key path
     * @param string $method the HTTP method to use
     * @param array $post optional HTTP POST BOdy
     * @return mixed
     */
    private function send_and_parse($methods, $key_path, $default=false, $method='POST', $post=array()) {
        $res = $this->send_command($this->api_url, $methods, $method, $post);
        $this->responses[] = $res;
        return $this->search_response($res, $key_path, $default);
    }

    /**
     * Format a set of JMAP methods
     * @param array $methods methods to formamt
     * @param array $caps optional capability override
     * @return array
     */
    private function format_request($methods, $caps=array()) {
        return json_encode(array(
            'using' => count($caps) == 0 ? $this->default_caps : $caps,
            'methodCalls' => $methods
        ));
    }

    /**
     * Build default HTTP headers for a JMAP request
     * @param string $user username
     * @param string $pass password
     * @return void
     */
    private function build_headers($user, $pass) {
        $this->headers = array(
            'Authorization: Basic '. base64_encode(sprintf('%s:%s', $user, $pass)),
            'Cache-Control: no-cache, no-store, must-revalidate',
            'Content-Type: application/json',
            'Accept: application/json'
        );
    }

    /**
     * Prep a URL for JMAP discover
     * @param string $url JMAP url
     * @return string
     */
    private function prep_url($url) {
        $url = preg_replace("/\/$/", '', $url);
        return sprintf('%s/.well-known/jmap/', $url);
    }

    /**
     * Make a mailbox look "selected" like in IMAP
     * @param string $mailbox mailbox name
     * @param integer $total total messages in the mailbox
     * @return void
     */
    private function setup_selected_mailbox($mailbox, $total=0) {
        if ($total == 0 && count($this->folder_state) > 0) {
            $total = $this->folder_state['messages'];
        }
        $this->selected_mailbox = array('detail' => array(
            'selected' => 1,
            'name' => $mailbox,
            'exists' => $total
        ));
    }

    /**
     * Filter a folder list by a parent folder
     * @param string $level parent folder
     * @return array
     */
    private function parse_folder_list_by_level($level) {
        $result = array();
        foreach ($this->folder_list as $name => $folder) {
            if ($folder['level'] == $level) {
                $result[$name] = $folder;
            }
        }
        return $result;
    }

    /**
     * Parse JMAP folders to make them more IMAP-ish
     * @param array $data folder list
     * @return array
     */
    private function parse_imap_folders($data) {
        $lookup = array();
        foreach ($data as $vals) {
            $vals['children'] = false;
            $lookup[$vals['id']] = $vals;
        }
        foreach ($data as $vals) {
            if ($vals['parentId']) {
                $parents = $this->get_parent_recursive($vals, $lookup, $parents=array());
                $level = implode($this->delim, $parents);
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
            $lookup[$vals['id']]['name'] = implode($this->delim, $parents);
        }
        return $this->build_imap_folders($lookup);
    }

    /**
     * Make JMAP folders more IMAP-ish
     * @param array $data modified JMAP folder list
     * @return array
     */
    private function build_imap_folders($data) {
        $result = array();
        foreach ($data as $vals) {
            if (strtolower($vals['name']) == 'inbox') {
                $vals['name'] = 'INBOX';
            }
            $result[$vals['name']] = array(
                    'delim' => $this->delim,
                    'level' => $vals['level'],
                    'basename' => $vals['basename'],
                    'children' => $vals['children'],
                    'name' => $vals['name'],
                    'type' => 'jmap',
                    'noselect' => false,
                    'id' => $vals['id'],
                    'role' => $vals['role'],
                    'name_parts' => $vals['name_parts'],
                    'messages' => $vals['totalEmails'],
                    'unseen' => $vals['unreadEmails']
                );
        }
        return $result;
    }

    /**
     * Recursively get all parents to a folder
     * @param array $vals folders
     * @param array $lookup easy lookup list
     * @param array $parents array of parents
     * @return array
     */
    private function get_parent_recursive($vals, $lookup, $parents) {
        $vals = $lookup[$vals['parentId']];
        $parents[] = $vals['name'];
        if ($vals['parentId']) {
            $parents = $this->get_parent_recursive($vals, $lookup, $parents);
        }
        return $parents;
    }

    /**
     * Convert an IMAP folder name to a JMAP ID
     * @param string $name folder name
     * @return string|false
     */
    private function folder_name_to_id($name) {
        if (count($this->folder_list) == 0 || !array_key_exists($name, $this->folder_list)) {
            $this->reset_folders();
        }
        if (array_key_exists($name, $this->folder_list)) {
            return $this->folder_list[$name]['id'];
        }
        return false;
    }

    /**
     * Re-fetch folders from JMAP
     * @return void
     */
    private function reset_folders() {
        $this->folder_list = $this->parse_imap_folders($this->get_folder_list());
    }

    /**
     * Convert IMAP search terms to JMAP
     * @param array $terms search terms
     * @return array|false
     */
    private function process_imap_search_terms($terms) {
        $converted_terms = array();
        $map = array(
            'SINCE' => 'after',
            'SUBJECT' => 'subject',
            'TO' => 'to',
            'FROM' => 'from',
            'BODY' => 'body',
            'TEXT' => 'text'
        );
        foreach ($terms as $vals) {
            if (array_key_exists($vals[0], $map)) {
                if ($vals[0] == 'SINCE') {
                    $vals[1] = gmdate("Y-m-d\TH:i:s\Z", strtotime($vals[1]));
                }
                $converted_terms[$map[$vals[0]]] = $vals[1];
            }
        }
        return count($converted_terms) > 0 ? $converted_terms : false;
    }

    /**
     * Upload data in memory as a file to JMAP (Used to replicate IMAP APPEND)
     * @param string $string file contents
     * @return string|false
     */
    private function upload_file($string) {
        $upload_url = str_replace('{accountId}', $this->account_id, $this->upload_url);
        $res = $this->send_command($upload_url, array(), 'POST', $string, array(2 => 'Content-Type: message/rfc822'));
        if (!is_array($res) || !array_key_exists('blobId', $res)) {
            return false;
        }
        return $res['blobId'];
    }

    /**
     * Split an IMAP style folder name into the parent and child
     * @param string $mailbox folder name
     * @return array
     */
    private function split_name_and_parent($mailbox) {
        $parent = NULL;
        $parts = explode($this->delim, $mailbox);
        $name = array_pop($parts);
        if (count($parts) > 0) {
            $parent = $this->folder_name_to_id(implode($this->delim, $parts));
        }
        return array($name, $parent);
    }

    /**
     * Get the detail needed to download a message or message part
     * @param string $uid message uid
     * @param string $message_part IMAP message "number"
     * @return array
     */
    private function download_details($uid, $message_part) {
        $blob_id = false;
        $name = false;
        $struct = $this->get_message_structure($uid);
        $part_struct = $this->search_bodystructure($struct, array('imap_part_number' => $message_part));
        if (is_array($part_struct) && array_key_exists($message_part, $part_struct)) {
            if (array_key_exists('blob_id', $part_struct[$message_part])) {
                $blob_id = $part_struct[$message_part]['blob_id'];
            }
            if (array_key_exists('name', $part_struct[$message_part]) && $part_struct[$message_part]['name']) {
                $name = $part_struct[$message_part]['name'];
            }
            else {
                $name = sprintf('message_%s', $message_part);
            }
        }
        return array($blob_id, $name);
    }

    /**
     * Download a raw message or raw message part
     * @param string $blob_id message blob id
     * @param string name for the downloaded file
     * @return string
     */
    private function get_raw_message_content($blob_id, $name) {
        $download_url = str_replace(
            array('{accountId}', '{blobId}', '{name}', '{type}'),
            array(
                urlencode($this->account_id),
                urlencode($blob_id),
                urlencode($name),
                urlencode('application/octet-stream')
            ),
            $this->download_url
        );
        $this->api->format = 'binary';
        $res = $this->send_command($download_url, array(), 'GET');
        $this->api->format = 'json';
        return $res;
    }
}
