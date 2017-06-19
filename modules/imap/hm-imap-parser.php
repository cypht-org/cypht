<?php

/**
 * IMAP modules
 * @package modules
 * @subpackage imap
 */

/**
 * IMAP specific parsing routines
 * @subpackage imap/lib
 */
class Hm_IMAP_Parser extends Hm_IMAP_Base {

    /**
     * helper method to grab values from the SELECT response
     * @param array $vals low level parsed select response segment
     * @param int $offset offset in the list to search for a value
     * @param string $key value in the array to start from
     * @return int the adjacent value
     */
    protected function get_adjacent_response_value($vals, $offset, $key) {
        foreach ($vals as $i => $v) {
            $i += $offset;
            if (isset($vals[$i]) && $vals[$i] == $key) {
                return $v;
            }
        }
        return 0;
    }

    /**
     * helper function to cllect flags from the SELECT response
     * @param array $vals low level parsed select response segment
     * @return array list of flags
     */
    protected function get_flag_values($vals) {
        $collect_flags = false;
        $res = array();
        foreach ($vals as $i => $v) {
            if ($v == ')') {
                $collect_flags = false;
            }
            if ($collect_flags) {
                $res[] = $v;
            }
            if ($v == '(') {
                $collect_flags = true;
            }
        }
        return $res;
    }

    /**
     * compare filter keywords against message flags
     * @param string $filter message type to filter by
     * @param string $flags IMAP flag value string
     * @return bool true if the message matches the filter
     */ 
    protected function flag_match($filter, $flags) {
        $res = false;
        switch($filter) {
            case 'ANSWERED':
            case 'SEEN':
            case 'DRAFT':
            case 'DELETED':
            case 'FLAGGED':
                $res = stristr($flags, $filter);
                break;
            case 'UNSEEN':
            case 'UNDRAFT':
            case 'UNDELETED':
            case 'UNFLAGGED':
            case 'UNANSWERED':
                $res = !stristr($flags, str_replace('UN', '', $filter));
                break;
        }
        return $res;
    }

    /**
     * build a list of IMAP extensions from the capability response
     * @return void
     */
    protected function parse_extensions_from_capability() {
        $extensions = array();
        foreach (explode(' ', $this->capability) as $word) {
            if (!in_array(strtolower($word), array('*', 'ok', 'completed', 'imap4rev1', 'capability'), true)) {
                $extensions[] = strtolower($word);
            }
        }
        $this->supported_extensions = $extensions;
    }

    /**
     * build a QRESYNC IMAP extension paramater for a SELECT statement
     * @return string param to use
     */
    protected function build_qresync_params() {
        $param = '';
        if (isset($this->selected_mailbox['detail'])) {
            $box = $this->selected_mailbox['detail'];
            if (isset($box['uidvalidity']) && $box['uidvalidity'] && isset($box['modseq']) && $box['modseq']) {
                if ($this->is_clean($box['uidvalidity'], 'uid') && $this->is_clean($box['modseq'], 'uid')) {
                    $param = sprintf(' (QRESYNC (%s %s))', $box['uidvalidity'], $box['modseq']);
                }
            }
        }
        return $param;
    }

    /**
     * parse GETQUOTAROOT and GETQUOTA responses
     * @param array $data low level parsed IMAP response segment
     * @return array list of properties
     */
    protected function parse_quota_response($vals) {
        $current = 0;
        $max = 0;
        $name = '';
        if (in_array('QUOTA', $vals)) {
            $name = $this->get_adjacent_response_value($vals, -1, 'QUOTA');
            if ($name == '(') {
                $name = '';
            }
        }
        if (in_array('STORAGE', $vals)) {
            $current = $this->get_adjacent_response_value($vals, -1, 'STORAGE');
            $max = $this->get_adjacent_response_value($vals, -2, 'STORAGE');
        }
        return array($name, $max, $current);
    }

    /**
     * collect useful untagged responses about mailbox state from certain command responses
     * @param array $data low level parsed IMAP response segment
     * @return array list of properties
     */
    protected function parse_untagged_responses($data) {
        $cache_updates = 0;
        $cache_updated = 0;
        $qresync = false;
        $attributes = array(
            'uidnext' => false,
            'unseen' => false,
            'uidvalidity' => false,
            'exists' => false,
            'pflags' => false,
            'recent' => false,
            'modseq' => false,
            'flags' => false,
            'nomodseq' => false
        );
        foreach($data as $vals) {
            if (in_array('NOMODSEQ', $vals)) {
                $attributes['nomodseq'] = true;
            }
            if (in_array('MODSEQ', $vals)) {
                $attributes['modseq'] = $this->get_adjacent_response_value($vals, -2, 'MODSEQ');
            }
            if (in_array('HIGHESTMODSEQ', $vals)) {
                $attributes['modseq'] = $this->get_adjacent_response_value($vals, -1, 'HIGHESTMODSEQ');
            }
            if (in_array('UIDNEXT', $vals)) {
                $attributes['uidnext'] = $this->get_adjacent_response_value($vals, -1, 'UIDNEXT');
            }
            if (in_array('UNSEEN', $vals)) {
                $attributes['unseen'] = $this->get_adjacent_response_value($vals, -1, 'UNSEEN');
            }
            if (in_array('UIDVALIDITY', $vals)) {
                $attributes['uidvalidity'] = $this->get_adjacent_response_value($vals, -1, 'UIDVALIDITY');
            }
            if (in_array('EXISTS', $vals)) {
                $attributes['exists'] = $this->get_adjacent_response_value($vals, 1, 'EXISTS');
            }
            if (in_array('RECENT', $vals)) {
                $attributes['recent'] = $this->get_adjacent_response_value($vals, 1, 'RECENT');
            }
            if (in_array('PERMANENTFLAGS', $vals)) {
                $attributes['pflags'] = $this->get_flag_values($vals);
            }
            if (in_array('FLAGS', $vals) && !in_array('MODSEQ', $vals)) {
                $attributes['flags'] = $this->get_flag_values($vals);
            }
            if (in_array('FETCH', $vals) || in_array('VANISHED', $vals)) {
                $cache_updates++;
                $cache_updated += $this->update_cache_data($vals);
            }
        }
        if ($cache_updates && $cache_updates == $cache_updated) {
            $qresync = true;
        }
        return array($qresync, $attributes);
    }

    /**
     * parse untagged ESEARCH/ESORT responses
     * @param array $vals low level parsed IMAP response segment
     * @return array list of ESEARCH response values
     */
    protected function parse_esearch_response($vals) {
        $res = array();
        if (in_array('MIN', $vals)) {
            $res['min'] = $this->get_adjacent_response_value($vals, -1, 'MIN');
        }
        if (in_array('MAX', $vals)) {
            $res['max'] = $this->get_adjacent_response_value($vals, -1, 'MAX');
        }
        if (in_array('COUNT', $vals)) {
            $res['count'] = $this->get_adjacent_response_value($vals, -1, 'COUNT');
        }
        if (in_array('ALL', $vals)) {
            $res['all'] = $this->get_adjacent_response_value($vals, -1, 'ALL');
        }
        return $res;
    }

    /**
     * examine NOOP/SELECT/EXAMINE untagged responses to determine if the mailbox state changed
     * @param array $attributes list of attribute name/value pairs
     * @return void
     */
    protected function check_mailbox_state_change($attributes, $cached_state=false, $mailbox=false) {
        if (!$cached_state) {
            if ($this->selected_mailbox) {
                $cached_state = $this->selected_mailbox;
            }
            if (!$cached_state) {
                return;
            }
        }
        $full_change = false;
        $partial_change = false;

        foreach($attributes as $name => $value) {
            if ($value !== false) {
                if (isset($cached_state[$name]) && $cached_state[$name] != $value) {
                    if ($name == 'uidvalidity') {
                        $full_change = true;
                    }
                    else {
                        $partial_change = true;
                    }
                }
            }
        }
        if ($full_change || (isset($attributes['nomodseq']) && $attributes['nomodseq'])) {
            $this->bust_cache($mailbox);
        }
        elseif ($partial_change) {
            $this->bust_cache($mailbox, false);
        }
    }

    /**
     * helper function to build IMAP LIST commands
     * @param bool $lsub flag to use LSUB
     * @return array IMAP LIST/LSUB commands
     */
    protected function build_list_commands($lsub, $mailbox, $keyword) {
        $commands = array();
        if ($lsub) {
            $imap_command = 'LSUB';
        }
        else {
            $imap_command = 'LIST';
        }
        $namespaces = $this->get_namespaces();
        foreach ($namespaces as $nsvals) {

            /* build IMAP command */
            $namespace = $nsvals['prefix'];
            $delim = $nsvals['delim'];
            $ns_class = $nsvals['class'];
            $mailbox = $this->utf7_encode(str_replace('"', '\"', $mailbox));
            if (strtoupper(substr($namespace, 0, 5)) == 'INBOX') { 
                $namespace = '';
            }

            /* send command to the IMAP server and fetch the response */
            if ($mailbox && $namespace) {
                $namespace .= $delim.$mailbox;
            }
            elseif ($mailbox) {
                $namespace .= $mailbox.$delim;
            }
            if ($this->is_supported('LIST-STATUS')) {
                $status = ' RETURN (';
                if ($this->is_supported('LIST-EXTENDED')) {
                    $status .= 'CHILDREN ';
                }
                if ($this->is_supported('SPECIAL-USE')) {
                    $status .= 'SPECIAL-USE ';
                }
                $status .= 'STATUS (MESSAGES UNSEEN UIDVALIDITY UIDNEXT RECENT))';
            }
            else {
                $status = '';
            }
            $commands[] = array($imap_command.' "'.$namespace."\" \"$keyword\"$status\r\n", $namespace);
        }
        return $commands;
    }

    /**
     * parse an untagged STATUS response
     * @param array $response low level parsed IMAP response segment
     * @return array list of mailbox attributes
     */
    protected function parse_status_response($response) {
        $attributes = array(
            'messages' => false,
            'uidvalidity' => false,
            'uidnext' => false,
            'recent' => false,
            'unseen' => false
        );
        foreach ($response as $vals) {
            if (in_array('MESSAGES', $vals)) {
                $res = $this->get_adjacent_response_value($vals, -1, 'MESSAGES');
                if (ctype_digit((string)$res)) {
                    $attributes['messages'] = $this->get_adjacent_response_value($vals, -1, 'MESSAGES');
                }
            }
            if (in_array('UIDNEXT', $vals)) {
                $res = $this->get_adjacent_response_value($vals, -1, 'UIDNEXT');
                if (ctype_digit((string)$res)) {
                    $attributes['uidnext'] = $this->get_adjacent_response_value($vals, -1, 'UIDNEXT');
                }
            }
            if (in_array('UIDVALIDITY', $vals)) {
                $res = $this->get_adjacent_response_value($vals, -1, 'UIDVALIDITY');
                if (ctype_digit((string)$res)) {
                    $attributes['uidvalidity'] = $this->get_adjacent_response_value($vals, -1, 'UIDVALIDITY');
                }
            }
            if (in_array('RECENT', $vals)) {
                $res = $this->get_adjacent_response_value($vals, -1, 'RECENT');
                if (ctype_digit((string)$res)) {
                    $attributes['recent'] = $this->get_adjacent_response_value($vals, -1, 'RECENT');
                }
            }
            if (in_array('UNSEEN', $vals)) {
                $res = $this->get_adjacent_response_value($vals, -1, 'UNSEEN');
                if (ctype_digit((string)$res)) {
                    $attributes['unseen'] = $this->get_adjacent_response_value($vals, -1, 'UNSEEN');
                }
            }
        }
        return $attributes;
    }
}

