<?php

/**
 * Mailbox bridge class
 * @package modules
 * @subpackage imap
 *
 * This class hides the implementation details of IMAP, JMAP and EWS connections and provides
 * a common interface to work with a mail account/mailbox. It acts as a bridge to more than one
 * underlying connections/protocols.
 */
class Hm_Mailbox {
    const TYPE_IMAP = 1;
    const TYPE_JMAP = 2;
    const TYPE_EWS = 3;

    protected $type;
    protected $connection;

    public function connect(array $config) {
        if (array_key_exists('type', $config) && $config['type'] == 'jmap') {
            $this->connection = new Hm_JMAP();
            $this->type = TYPE_JMAP;
        }
        elseif (array_key_exists('type', $server) && $server['type'] == 'ews') {
            $this->connection = new Hm_EWS();
            $this->type = TYPE_EWS;
        }
        else {
            $this->connection = new Hm_IMAP();
            $this->type = TYPE_IMAP;
        }
        return $this->connection->connect($config);
    }

    public function is_imap() {
        return $this->type !== TYPE_EWS;
    }

    public function server_type() {
        switch ($this->type) {
            case TYPE_IMAP:
                return 'IMAP';
            case TYPE_JMAP:
                return 'JMAP';
            case TYPE_EWS:
                return 'EWS';
        }
    }

    public function authed() {
        if ($this->is_imap()) {
            return $this->connection->get_state() == 'authenticated' || $this->connection->get_state() == 'selected';
        } else {
            // TODO: EWS
        }
    }

    public function get_folder_status($folder) {
        if (! $this->authed()) {
            return;
        }
        if ($this->is_imap()) {
            return $this->connection->get_mailbox_status($folder);
        } else {
            // TODO: EWS
        }
    }

    public function create_folder($folder) {
        if (! $this->authed()) {
            return;
        }
        if ($this->is_imap()) {
            return $this->connection->create_mailbox($folder);
        } else {
            // TODO: EWS
        }
    }

    public function get_folder_state() {
        if ($this->is_imap()) {
            return $this->connection->folder_state;
        } else {
            // TODO: check EWS
            return true;
        }
    }

    public function get_selected_folder() {
        if ($this->is_imap()) {
            return $this->connection->selected_mailbox;
        } else {
            // TODO: EWS
        }
    }

    public function get_special_use_mailboxes($folder) {
        if (! $this->authed()) {
            return;
        }
        if ($this->is_imap()) {
            return $this->connection->get_special_use_mailboxes($folder);
        } else {
            // TODO: EWS
        }
    }
    
    /**
     * Get messages in a folder applying filters, sorting and pagination
     * @return array - [total results found, results for a single page]
     */
    public function get_messages($folder, $sort, $reverse, $flag_filter, $offset=0, $limit=0, $keyword=false, $trusted_senders=[]) {
        if ($this->is_imap()) {
            return $this->connection->get_mailbox_page($folder, $sort, $reverse, $flag_filter, $offset, $limit, $keyword, $trusted_senders);
        } else {
            // TODO: EWS
        }
    }

    public function get_message_headers($msg_id) {
        return $this->connection->get_message_headers($msg_id);
    }

    public function get_message_content($folder, $msg_id) {
        if (! $this->authed()) {
            return;
        }
        if ($this->is_imap()) {
            if (! $this->connection->select_mailbox($folder)) {
                return;
            }
            return $this->connection->get_message_content($msg_id, 0);
        } else {
            // TODO: EWS
        }
    }

    public function get_structured_message($folder, $msg_id, $part, $text_only) {
        if ($this->is_imap()) {
            if (! $this->connection->select_mailbox($folder)) {
                return;
            }
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
            // TODO: EWS
        }
    }

    public function store_message($folder, $msg) {
        if (! $this->authed()) {
            return false;
        }
        if ($this->is_imap()) {
            if ($this->connection->append_start($folder, mb_strlen($msg), true)) {
                $this->connection->append_feed($msg."\r\n");
                if (! $this->connection->append_end()) {
                    return true;
                }
            }
        } else {
            // TODO: EWS
        }
        return false;
    }

    public function delete_message($folder, $msg_id, $trash_folder) {
        if ($this->is_imap()) {
            if (! $this->connection->select_mailbox($folder)) {
                return false;
            }
            if ($trash_folder && $trash_folder != $folder) {
                if ($this->connection->message_action('MOVE', [$msg_id], $trash_folder)) {
                    return true;
                }
            }
            else {
                if ($this->connection->message_action('DELETE', array($msg_id))) {
                    $this->connection->message_action('EXPUNGE', array($msg_id));
                    return true;
                }
            }
        } else {
            // TODO: EWS
        }
        return false;
    }

    public function message_action($folder, $action, $uids, $mailbox=false, $keyword=false) {
        if ($this->is_imap()) {
            $this->connection->select_mailbox($folder);
        }
        return $this->connection->message_action($action, $uids, $mailbox, $keyword);
    }

    public function stream_message_part($msg_id, $part_id, $start_cb) {
        if ($this->is_imap()) {
            if (! $this->connection->select_mailbox($folder)) {
                return;
            }
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
            // TODO: EWS
        }
    }

    public function remove_attachment($folder, $msg_id, $part_id) {
        if ($this->is_imap()) {
            if (! $this->connection->select_mailbox($folder)) {
                return false;
            }
            $msg = $this->connection->get_message_content($msg_id, 0, false, false);
            if ($msg) {
                $attachment_id = get_attachment_id_for_mail_parser($this->connection, $msg_id, $part_id);
                if ($attachment_id !== false) {
                    $msg = remove_attachment($attachment_id, $msg);
                    if ($this->connection->append_start($folder, mb_strlen($msg))) {
                        $this->connection->append_feed($msg."\r\n");
                        if ($this->connection->append_end()) {
                            if ($this->connection->message_action('DELETE', array($uid))) {
                                $this->connection->message_action('EXPUNGE', array($uid));
                                return true;
                            }
                        }
                    }
                }
            }
        } else {
            // TODO: EWS
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
            // TODO: EWS
        }
    }

    public function get_debug() {
        if ($this->is_imap()) {
            return $this->connection->show_debug(true, true, true);
        } else {
            // TODO: EWS
        }
    }

    public function get_state() {
        return $this->connection->get_state();
    }

    public function get_capability() {
        return $this->connection->get_capability();
    }

    public function set_read_only($read_only) {
        $this->connection->read_only = $read_only;
    }
}
