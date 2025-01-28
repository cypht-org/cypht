<?php

/**
 * Mailbox bridge class
 * @package modules
 * @subpackage imap
 *
 * This class hides the implementation details of IMAP, JMAP, SMTP and EWS connections and provides
 * a common interface to work with a mail account/mailbox. It acts as a bridge to more than one
 * underlying connections/protocols.
 */
class Hm_Mailbox {
    const TYPE_IMAP = 1;
    const TYPE_JMAP = 2;
    const TYPE_EWS = 3;
    const TYPE_SMTP = 4;

    protected $type;
    protected $connection;
    protected $selected_folder;
    protected $folder_state;

    protected $server_id;
    protected $user_config;
    protected $session;
    protected $config;

    public function __construct($server_id, $user_config, $session, $config) {
        $this->server_id = $server_id;
        $this->user_config = $user_config;
        $this->session = $session;
        $this->config = $config;
        $type = $config['type'] ?? '';
        if ($type == 'imap') {
            $this->type = self::TYPE_IMAP;
            $this->connection = new Hm_IMAP();
        } elseif ($type == 'jmap') {
            $this->type = self::TYPE_JMAP;
            $this->connection = new Hm_JMAP();
        } elseif ($type == 'ews') {
            $this->type = self::TYPE_EWS;
            $this->connection = new Hm_EWS();
        } elseif ($type == 'smtp') {
            $this->type = self::TYPE_SMTP;
            $this->connection = new Hm_SMTP($config);
        }
    }

    public function connect() {
        return $this->connection->connect($this->config);
    }

    public function get_connection() {
        return $this->connection;
    }

    public function is_imap() {
        return $this->type === self::TYPE_IMAP || $this->type === self::TYPE_JMAP;
    }

    public function is_smtp() {
        return $this->type === self::TYPE_SMTP;
    }

    public function server_type() {
        switch ($this->type) {
            case self::TYPE_IMAP:
                return 'IMAP';
            case self::TYPE_JMAP:
                return 'JMAP';
            case self::TYPE_EWS:
                return 'EWS';
        }
    }

    public function authed() {
        if ($this->is_imap()) {
            return $this->connection->get_state() == 'authenticated' || $this->connection->get_state() == 'selected';
        } elseif ($this->is_smtp()) {
            return $this->connection->state == 'authed';
        } else {
            return $this->connection->authed();
        }
    }

    public function state() {
        if ($this->is_imap()) {
            return $this->connection->get_state();
        } elseif ($this->is_smtp()) {
            return $this->connection->state;
        } else {
            return null;
        }
    }

    public function get_folder_status($folder) {
        if (! $this->authed()) {
            return;
        }
        if ($this->is_imap()) {
            return $this->connection->get_mailbox_status($folder);
        } else {
            return $this->connection->get_folder_status($folder);
        }
    }

    public function get_folder_name($folder) {
        if ($this->is_imap()) {
            return $folder;
        } else {
            $result = $this->connection->get_folder_status($folder);
            return $result['name'];
        }
    }

    public function create_folder($folder, $parent = null) {
        if (! $this->authed()) {
            return;
        }
        if ($this->is_imap()) {
            $new_folder = prep_folder_name($this->connection, $folder, false, $parent);
            if ($this->connection->create_mailbox($new_folder)) {
                return $new_folder;
            } else {
                return false;
            }
        } else {
            return $this->connection->create_folder($folder, $parent);
        }
    }

    public function rename_folder($folder, $new_name, $parent = null) {
        if (! $this->authed()) {
            return;
        }
        if ($this->is_imap()) {
            $old_folder = prep_folder_name($this->connection, $folder, true);
            $new_folder = prep_folder_name($this->connection, $new_name, false, $parent);
            return $this->connection->rename_mailbox($old_folder, $new_folder);
        } else {
            $folder = decode_folder_str($folder);
            if ($parent) {
                $parent = decode_folder_str($parent);
            }
            return $this->connection->rename_folder($folder, $new_name, $parent);
        }
    }

    public function delete_folder($folder) {
        if (! $this->authed()) {
            return;
        }
        if ($this->is_imap()) {
            $del_folder = prep_folder_name($this->connection, $folder, true);
            return $this->connection->delete_mailbox($del_folder);
        } else {
            $del_folder = decode_folder_str($folder);
            return $this->connection->delete_folder($del_folder);
        }
    }

    public function prep_folder_name($folder) {
        if ($this->is_imap()) {
            return prep_folder_name($this->connection, $folder, true);
        } else {
            if (substr_count($folder, '_') >= 2) {
                return decode_folder_str($folder);
            } else {
                return $folder;
            }
        }
    }

    public function folder_subscription($folder, $action) {
        if (! $this->authed()) {
            return;
        }
        if ($this->is_imap()) {
            return $this->connection->mailbox_subscription($folder, $action);
        } else {
            // emulate folder subscription via settings
            $config = $this->user_config->get('unsubscribed_folders');
            if (! isset($config[$this->server_id])) {
                $config[$this->server_id] = [];
            }
            if ($action) {
                $index = array_search($folder, $config[$this->server_id]);
                if ($index !== false) {
                    unset($config[$this->server_id][$index]);
                }
            } else {
                if (! in_array($folder, $config[$this->server_id])) {
                    $config[$this->server_id][] = $folder;
                }
            }
            $this->user_config->set('unsubscribed_folders', $config);
            $this->session->set('user_data', $this->user_config->dump());
            $this->session->record_unsaved('Folder subscription updated');
            return true;
        }
    }

    public function get_folders($only_subscribed = false) {
        if (! $this->authed()) {
            return;
        }
        if ($this->is_imap()) {
            return $this->connection->get_mailbox_list($only_subscribed, children_capability: $this->connection->server_support_children_capability());
        } else {
            return $this->connection->get_folders(null, $only_subscribed, $this->user_config->get('unsubscribed_folders')[$this->server_id] ?? []);
        }
    }

    public function get_subfolders($folder, $only_subscribed = false, $with_input = false) {
        if (! $this->authed()) {
            return;
        }
        if ($this->is_imap()) {
            return $this->connection->get_folder_list_by_level($folder, $only_subscribed, $with_input);
        } else {
            return $this->connection->get_folders($folder, $only_subscribed, $this->user_config->get('unsubscribed_folders')[$this->server_id] ?? [], $with_input);
        }
    }

    public function get_folder_state() {
        if ($this->is_imap()) {
            return $this->connection->folder_state;
        } else {
            return $this->folder_state;
        }
    }

    public function get_selected_folder() {
        if ($this->is_imap()) {
            return $this->connection->selected_mailbox;
        } else {
            return $this->selected_folder;
        }
    }

    public function get_special_use_mailboxes($folder = false) {
        if (! $this->authed()) {
            return;
        }
        if ($this->is_imap()) {
            return $this->connection->get_special_use_mailboxes($folder);
        } else {
            return $this->connection->get_special_use_folders($folder);
        }
    }
    
    /**
     * Get messages in a folder applying filters, sorting and pagination
     * @return array - [total results found, results for a single page]
     */
    public function get_messages($folder, $sort, $reverse, $flag_filter, $offset=0, $limit=50, $keyword=false, $trusted_senders=[], $include_preview = false) {
        if (! $this->select_folder($folder)) {
            return;
        }
        if ($this->is_imap()) {
            $messages = $this->connection->get_mailbox_page($folder, $sort, $reverse, $flag_filter, $offset, $limit, $keyword, $trusted_senders, $include_preview);
        } else {
            $messages = $this->connection->get_messages($folder, $sort, $reverse, $flag_filter, $offset, $limit, $keyword, $trusted_senders, $include_preview);
            $folder = $this->selected_folder['id'];
        }
        foreach ($messages[1] as &$msg) {
            $msg['folder'] = bin2hex($folder);
        }
        return $messages;
    }

    public function get_message_headers($folder, $msg_id) {
        if (! $this->select_folder($folder)) {
            return;
        }
        if ($this->is_imap()) {
            return $this->connection->get_message_headers($msg_id);
        } else {
            return $this->connection->get_message_headers($msg_id);
        }
        
    }

    public function get_message_content($folder, $msg_id, $part = 0) {
        if (! $this->authed()) {
            return;
        }
        if (! $this->select_folder($folder)) {
            return;
        }
        if ($this->is_imap()) {
            return $this->connection->get_message_content($msg_id, $part);
        } else {
            return $this->connection->get_message_content($msg_id, $part);
        }
    }

    public function get_structured_message($folder, $msg_id, $part, $text_only) {
        if (! $this->select_folder($folder)) {
            return;
        }
        if ($this->is_imap()) {
            $msg_struct = $this->connection->get_message_structure($msg_id);
            if ($part !== false) {
                if ($part == 0) {
                    $max = 500000;
                }
                else {
                    $max = false;
                }
                $struct = $this->connection->search_bodystructure($msg_struct, array('imap_part_number' => $part));
                $msg_struct_current = array_shift($struct);
                $msg_text = $this->connection->get_message_content($msg_id, $part, $max, $msg_struct_current);
            }
            else {
                if (! $text_only) {
                    list($part, $msg_text) = $this->connection->get_first_message_part($msg_id, 'text', 'html', $msg_struct);
                    if (!$part) {
                        list($part, $msg_text) = $this->connection->get_first_message_part($msg_id, 'text', false, $msg_struct);
                    }
                }
                else {
                    list($part, $msg_text) = $this->connection->get_first_message_part($msg_id, 'text', false, $msg_struct);
                }
                $struct = $this->connection->search_bodystructure($msg_struct, array('imap_part_number' => $part));
                $msg_struct_current = array_shift($struct);
                if (! trim($msg_text)) {
                    if (is_array($msg_struct_current) && array_key_exists('subtype', $msg_struct_current)) {
                        if ($msg_struct_current['subtype'] == 'plain') {
                            $subtype = 'html';
                        }
                        else {
                            $subtype = 'plain';
                        }
                        list($part, $msg_text) = $this->connection->get_first_message_part($msg_id, 'text', $subtype, $msg_struct);
                        $struct = $this->connection->search_bodystructure($msg_struct, array('imap_part_number' => $part));
                        $msg_struct_current = array_shift($struct);
                    }
                }
            }
            if (isset($msg_struct_current['subtype']) && mb_strtolower($msg_struct_current['subtype'] == 'html')) {
                $msg_text = add_attached_images($msg_text, $msg_id, $msg_struct, $this->connection);
            }
            return [$msg_struct, $msg_struct_current, $msg_text, $part];
        } else {
            return $this->connection->get_structured_message($msg_id, $part, $text_only);
        }
    }

    public function store_message($folder, $msg, $seen = true, $draft = false) {
        if (! $this->authed()) {
            return false;
        }
        if ($this->is_imap()) {
            if ($this->connection->append_start($folder, mb_strlen($msg), $seen, $draft)) {
                $this->connection->append_feed($msg."\r\n");
                return $this->connection->append_end();
            }
        } else {
            return $this->connection->store_message($folder, $msg, $seen, $draft);
        }
        return false;
    }

    public function delete_message($folder, $msg_id, $trash_folder) {
        if (! $this->select_folder($folder)) {
            return;
        }
        if ($this->is_imap() && $trash_folder && $trash_folder != $folder) {
            if ($this->connection->message_action('MOVE', [$msg_id], $trash_folder)['status']) {
                return true;
            }
        }
        else {
            if ($this->connection->message_action('DELETE', array($msg_id))['status']) {
                $this->connection->message_action('EXPUNGE', array($msg_id));
                return true;
            }
        }
        return false;
    }

    public function message_action($folder, $action, $uids, $mailbox=false, $keyword=false) {
        if (! $this->select_folder($folder)) {
            return ['status' => false, 'responses' => []];
        }
        return $this->connection->message_action($action, $uids, $mailbox, $keyword);
    }

    public function stream_message_part($folder, $msg_id, $part_id, $start_cb) {
        if (! $this->select_folder($folder)) {
            return;
        }
        if ($this->is_imap()) {
            $msg_struct = $this->connection->get_message_structure($msg_id);
            $struct = $this->connection->search_bodystructure($msg_struct, array('imap_part_number' => $part_id));
            if (! empty($struct)) {
                $part_struct = array_shift($struct);
                $encoding = false;
                if (array_key_exists('encoding', $part_struct)) {
                    $encoding = trim(mb_strtolower($part_struct['encoding']));
                }
                $stream_size = $this->connection->start_message_stream($msg_id, $part_id);
                if ($stream_size > 0) {
                    $part_name = get_imap_part_name($part_struct, $msg_id, $part_id);
                    $charset = '';
                    if (array_key_exists('attributes', $part_struct)) {
                        if (is_array($part_struct['attributes']) && array_key_exists('charset', $part_struct['attributes'])) {
                            $charset = '; charset='.$part_struct['attributes']['charset'];
                        }
                    }
                    $start_cb($part_struct['type'] . '/' . $part_struct['subtype'] . $charset, $part_name);
                    $output_line = '';
                    while($line = $this->connection->read_stream_line()) {
                        if ($encoding == 'quoted-printable') {
                            $line = quoted_printable_decode($line);
                        }
                        elseif ($encoding == 'base64') {
                            $line = base64_decode($line);
                        }
                        echo $output_line;
                        $output_line = $line;
                    }
                    if ($part_struct['type'] == 'text') {
                        $output_line = preg_replace("/\)(\r\n)$/m", '$1', $output_line);
                    }
                    echo $output_line;
                }
            }
        } else {
            return $this->connection->stream_message_part($msg_id, $part_id, $start_cb);
        }
    }

    public function remove_attachment($folder, $msg_id, $part_id) {
        if (! $this->select_folder($folder)) {
            return;
        }
        if ($this->is_imap()) {
            $msg = $this->connection->get_message_content($msg_id, 0, false, false);
            if ($msg) {
                $struct = $this->connection->get_message_structure($msg_id);
                $attachment_id = get_attachment_id_for_mail_parser($struct, $part_id);
                if ($attachment_id !== false) {
                    $msg = remove_attachment($attachment_id, $msg);
                    if ($this->connection->append_start($folder, mb_strlen($msg))) {
                        $this->connection->append_feed($msg."\r\n");
                        if ($this->connection->append_end()) {
                            if ($this->connection->message_action('DELETE', array($uid))['status']) {
                                $this->connection->message_action('EXPUNGE', array($uid));
                                return true;
                            }
                        }
                    }
                }
            }
        } else {
            $message = $this->connection->get_mime_message_by_id($msg_id);
            $result = $this->connection->get_structured_message($msg_id, false, false);
            $struct = $result[0];
            $attachment_id = get_attachment_id_for_mail_parser($struct, $part_id);
            if ($attachment_id !== false) {
                $message->removeAttachmentPart($attachment_id);
                if ($this->connection->store_message($folder, (string) $message)) {
                    $this->connection->message_action('HARDDELETE', [$msg_id]);
                    return true;
                }
            }
        }
    }

    public function get_quota($folder, $root = false) {
        if ($this->is_imap()) {
            if ($root) {
                return $this->connection->get_quota_root($folder);
            } else {
                return $this->connection->get_quota($folder);
            }
        } else {
            // not supported by EWS
            return [];
        }
    }

    public function get_debug() {
        if ($this->is_imap()) {
            return $this->connection->show_debug(true, true, true);
        } else {
            return [];
        }
    }

    public function use_cache() {
        if ($this->is_imap()) {
            return $this->connection->use_cache;
        } else {
            return false;
        }
    }

    public function dump_cache($type = 'string') {
        if ($this->is_imap()) {
            return $this->connection->dump_cache($type);
        } else {
            return null;
        }
    }

    public function get_state() {
        if ($this->is_imap()) {
            return $this->connection->get_state();
        } else {
            return $this->authed() ? 'authenticated' : 'disconnected';
        }
    }

    public function get_capability() {
        return $this->connection->get_capability();
    }

    public function set_read_only($read_only) {
        if ($this->is_imap()) {
            $this->connection->read_only = $read_only;
        }
    }

    public function set_search_charset($charset) {
        if ($this->is_imap()) {
            $this->connection->search_charset = $charset;
        }
    }

    public function search($folder, $target='ALL', $uids=false, $terms=array(), $esearch=array(), $exclude_deleted=true, $exclude_auto_bcc=true, $only_auto_bcc=false) {
        if (! $this->select_folder($folder)) {
            return;
        }
        if ($this->is_imap()) {
            return $this->connection->search($target, $uids, $terms, $esearch, $exclude_deleted, $exclude_auto_bcc, $only_auto_bcc);
        } else {
            // deleted flag, auto-bcc feature - not supported by EWS
            list($total, $itemIds) = $this->connection->search($folder, false, false, $target, 0, 9999, $terms, []);
            return $itemIds;
        }
    }

    public function get_message_list($folder, $msg_ids) {
        if (! $this->select_folder($folder)) {
            return [];
        }
        if ($this->is_imap()) {
            return $this->connection->get_message_list($msg_ids);
        } else {
            return $this->connection->get_message_list($msg_ids);
        }
    }

    public function send_message($from, $recipients, $message, $delivery_receipt = false) {
        if ($this->is_smtp()) {
            if ($delivery_receipt) {
                $from_params = 'RET=HDRS';
                $recipients_params = 'NOTIFY=SUCCESS,FAILURE';
            } else {
                $from_params = '';
                $recipients_params = '';
            }
            return $this->connection->send_message($from, $recipients, $message, $from_params, $recipients_params);
        } else {
            return $this->connection->send_message($from, $recipients, $message, $delivery_receipt);
        }
    }

    public function select_folder($folder) {
        if ($this->is_imap()) {
            if (isset($this->connection->selected_mailbox['name']) && $this->connection->selected_mailbox['name'] == $folder) {
                return true;
            }
            if (! $this->connection->select_mailbox($folder)) {
                return false;
            }
        } else {
            $this->folder_state = $this->get_folder_status($folder);
            if (! $this->folder_state) {
                return false;
            }
            $this->selected_folder = ['id' => $folder, 'name' => $this->folder_state['name'], 'detail' => []];
        }
        return true;
    }

    public function get_config() {
        return $this->config;
    }
}
