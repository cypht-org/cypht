<?php

/**
 * IMAP modules
 * @package modules
 * @subpackage imap
 */

/**
 * Represent a message structure by parsing the results from the IMAP
 * BODYSTRUCTURE command
 * @subpackage imap/lib
 */
class Hm_IMAP_Struct {

    /* Holds the completed structure */
    private $struct = array();

    /* Holds the current part number */
    private $part_number = '-1';

    /* Holds the parent container type, if any */
    private $parent_type = false;

    /* Valid top level MIME types */
    private $mime_types = array('application', 'audio', 'binary', 'image', 'message', 'model', 'multipart', 'text', 'video');

    /* These are "readable" MESSAGE subtypes that should be treated as text */
    private $readable_message_types = array('delivery-status', 'external-body', 'disposition-notification', 'rfc822-headers');

    /* Field order of a single non-text message part */
    private $single_format = array( 'type' => 0, 'subtype' => 1, 'attributes' => 2, 'id' => 3, 'description' => 4,
        'encoding' => 5, 'size' => 6, 'md5' => 7, 'file_attributes' => 8, 'langauge' => 9, 'location' => 10,);

    /* Field order of a single text message part */
    private $text_format = array( 'type' => 0, 'subtype' => 1, 'attributes' => 2, 'id' => 3, 'description' => 4,
        'encoding' => 5, 'size' => 6, 'lines' => 7, 'md5' => 8, 'disposition' => 9, 'file_attributes' => 10,
        'langauge' => 11, 'location' => 12,);

    /* Field order of an RFC822 container part */
    private $rfc822_format = array( 'type' => 0, 'subtype' => 1, 'attributes' => 2, 'id' => 3, 'description' => 4,
        'encoding' => 5, 'size' => 6, 'envelope' => 7, 'body_lines' => 9, 'body_attributes' => 10, 'disposition' => 11,
        'language' => 12, 'location' => 13);

    /* Fields in a multipart container part */
    private $multipart_format = array( 'subtype', 'attributes', 'disposition', 'language', 'location');

    /* Address fields in an RFC822 ENVELOPE */
    private $envelope_addresses = array( 'from', 'sender', 'reply-to', 'to', 'cc', 'bcc', 'in-reply-to');

    /* Fields order of an ENVELOPE address */
    private $address_format = array( 'name' => 0, 'route' => 1, 'mailbox' => 2, 'domain' => 3,);

    /* Fields order of an ENVELOPE */
    private $envelope_format = array( 'date' => 0, 'subject' => 1, 'from' => 2, 'sender' => 3, 'reply-to' => 4,
        'to' => 5, 'cc' => 6, 'bcc' => 7, 'in-reply-to' => 8, 'message_id' => 9);

    /* Hm_IMAP object */
    private $imap = false;

    /**
     * Constructor. Takes the BODYSTRUCTURE response and builds a data representation
     * @param array $struct_response low-level parsed IMAP response
     * @return void
     */
    public function __construct($struct_response, $imap)  {
        $this->imap = $imap;
        list($struct, $_) = $this->build($struct_response);
        $this->struct = $this->id_parts($struct);
    }

    /**
     * Builds a nested array based on parens in the input
     * @param array $array low-level parsed IMAP response
     * @param int $index position in the list
     * @return array tuple of the parsed result and index
     */
    private function build($array, $index=0) {
        $res = array();
        $len = count($array);
        for ($i = $index; $i < $len; $i++ ) {
            $val = $array[$i];
            if ($val == '(') {
                list($result, $new_index) = $this->build($array, ($i+1));
                $res[] = $result;
                $i = $new_index;
            }
            elseif ($val == ')') {
                return array($res, $i);
            }
            else {
                $res[] = $val;
            }
        }
        return array($res, $i);
    }
    /**
     * Create a name => value attribute set
     * @param array $vals set of attributes
     * 
     * @return array
     */
    private function attribute_set($vals) {
        $res = array();
        $len = count($vals);
        for ($i = 0; $i < $len; $i++) {
            if (isset($vals[$i + 1])) {
                $res[strtolower($vals[$i])] = $this->set_value($vals[$i + 1]);
                $i++;
            }
        }
        return $res;
    }

    /**
     * Parse an ENVELOPE address
     * @param array $vals parts of an address
     * @return string
     */
    private function envelope_address($vals) {
        $res = array();
        foreach ($vals as $addy) {
            $parts = array();
            $result = '';
            foreach ($this->address_format as $name => $pos) {
                if (isset($addy[$pos])) {
                    $parts[$name] = $this->set_value($addy[$pos]);
                }
                else {
                    $parts[$name] = false;
                }
            }
            if ($parts['name']) {
                $result = '"'.$parts['name'].'" ';
            }
            if ($parts['mailbox'] && $parts['domain']) {
                $result .= $parts['mailbox'].'@'.$parts['domain'];
            }
            $res[] = $result;
        }
        return implode(',', $res);
    }

    /**
     * Prepare a value to be added to the structure
     * @param mixed $val value to add
     * @param string $type optional value type
     * @return prepared value
     */
    private function set_value($val, $type=false) {
        if ($type == 'envelope') {
            return $this->envelope($val);
        }
        elseif (is_array($val) && in_array($type, array('attributes', 'body_attributes', 'disposition', 'file_attributes'), true)) {
            return $this->attribute_set($val);
        }
        elseif (is_array($val) && in_array($type, $this->envelope_addresses, true)) {
            return $this->envelope_address($val);
        }
        elseif ($val === 'NIL') {
            return false;
        }
        elseif (!is_array($val)) {
            if ($type == 'type' || $type == 'subtype') {
                $val = strtolower($val);
            }
            return $this->imap->decode_fld($val);
        }
        else {
            return $val;
        }
    }

    /**
     * Parse an RFC822 ENVELOPE section
     * @param array $vals low-level IMAP response
     * @return array
     */
    private function envelope($vals) {
        $res = array();
        foreach ($this->envelope_format as $name => $pos) {
            if (isset($vals[$pos])) {
                $res[$name] = $this->set_value($vals[$pos], $name);
            }
        }
        return $res;
    }

    /**
     * Determine if this is a multipart, rfc822 message, or single part type
     * @param array $vals low-level IMAP response
     * @return string
     */
    private function get_part_type($vals) {
        if (count($vals) > 1 && is_string($vals[0]) && is_string($vals[1])) {
            $type = strtolower($vals[0]);
            $subtype = strtolower($vals[1]);
            if ($type == 'message' && !in_array($subtype, $this->readable_message_types, true)) {
                return 'message';
            }
            elseif (in_array($type, $this->mime_types, true) || preg_match("/^x-.+$/", $type)) {
                return 'single';
            }
        }
        elseif (is_array($vals[0])) {
            return 'multi';
        }
        return false;
    }

    /**
     * Parse an RFC822 message part
     * @param array $vals low-level IMAP response
     * @return array
     */
    private function id_rfc822_part($vals) {
        $res = array();
        foreach($this->rfc822_format as $name => $pos) {
            if (isset($vals[$pos])) {
                $res[$name] = $this->set_value($vals[$pos], $name);
            }
            else {
                $res[$name] = false;
            }
        }
        $part_number = $this->part_number;
        $len = count($vals);
        $this->part_number .= '.0';
        $this->parent_type = 'message';
        $subs = array();
        if (isset($vals[8]) && is_array($vals[8])) {
            $subs = array_merge($subs, $this->id_parts(array($vals[8])));
        }
        if (!empty($subs)) {
            $res['subs'] = $subs;
        }
        $this->part_number = $part_number;
        $this->parent_type = false;
        return $res;
    }

    /**
     * Parse multipart message part
     * @param array $vals low-level IMAP response
     * @param bool $increment flag to control part ids
     * @return array
     */
    private function id_multi_part($vals, $increment=false) {
        if ($increment) {
            $part_number = $this->part_number;
            $this->part_number .= '.0';
        }
        list($index, $subs) = $this->parse_multi_part_subs($vals);
        $res = $this->parse_multi_part_flds($index, $vals);
        $res['subs'] = $subs;
        if ($increment) {
            $this->part_number = $part_number;
        }
        return $res;
    }

    /**
     * Parse multipart message parts
     * @param array $vals low-level IMAP response
     * @return array last index and subs array
     */
    private function parse_multi_part_subs($vals) {
        $index = 0;
        $subs = array();
        $this->parent_type = 'multi';
        foreach($vals as $index => $val) {
            if (!is_array($val)) {
                break;
            }
            else {
                $subs = array_merge($subs, $this->id_parts(array($val)));
            }
        }
        return array($index, $subs);
    }

    /**
     * Parse multipart message fields
     * @param int $index position in the array
     * @param array $vals low-level parsed IMAP response
     * @return array
     */
    private function parse_multi_part_flds($index, $vals) {
        $res = array('type' => 'multipart');
        if ($index) {
            foreach ($this->multipart_format as $fld) {
                if (isset($vals[$index])) {
                    $res[$fld] = $this->set_value($vals[$index], $fld);
                }
                else {
                    $res[$fld] = false;
                }
                $index++;
            }
        }
        return $res;
    }

    /**
     * Parse single message part
     * @param array $vals low-level IMAP response
     * @return array
     */
    private function id_single_part($vals) {
        $res = array();
        if (isset($vals[0]) && strtolower($vals[0]) == 'text') {
            $flds = $this->text_format;
        }
        else {
            $flds = $this->single_format;
        }
        foreach($flds as $name => $pos) {
            if (isset($vals[$pos])) {
                $res[$name] = $this->set_value($vals[$pos], $name);
            }
            else {
                $res[$name] = false;
            }
        }
        return $res;
    }

    /**
     * parse the message parts at the current "level"
     * @param array $struct low-level IMAP response
     * @return array
     */
    private function id_parts($struct) {
        $res = array();
        foreach ($struct as $val) {
            if (is_array($val)) {
                $part_type = $this->get_part_type($val);
                if ($part_type == 'message') {
                    $res[$this->increment_part_number()] = $this->id_rfc822_part($val);
                }
                elseif ( $part_type == 'single' ) {
                    if ($this->part_number == '-1') {
                        $this->part_number = '0';
                    }
                    $res[$this->increment_part_number()] = $this->id_single_part($val);
                }
                elseif ( $part_type == 'multi' ) {
                    if ($this->parent_type == 'message') {
                        $res[$this->part_number] = $this->id_multi_part($val);
                    }
                    else {
                        $res[$this->increment_part_number()] = $this->id_multi_part($val, true);
                    }
                }
            }
        }
        return $res;
    }

    /**
     * Increment the message part number in the weird way IMAP does.
     * @return string
     */
    private function increment_part_number() {
        $part = $this->part_number;
        if (!strstr($part, '.')) {
            $part++;
        }
        else {
            $parts = explode('.', $part);
            $parts[(count($parts) - 1)]++;
            $part = implode('.', $parts);
        }
        $part = (string) $part;
        $this->part_number = $part;
        return $part;
    }

    /**
     * Search a parsed BODYSTRUCTURE response
     * @param array $struct the response to search
     * @param array $flds key => value list of fields and values to search for
     * @param bool $all true to return all matching parts
     * @param array $res holds results during recursive iterations
     * @return array list of matching parts
     */
    public function recursive_search($struct, $flds, $all, $res, $parent=false) {
        foreach ($struct as $msg_id => $vals) {
            $matches = 0;
            if (isset($flds['imap_part_number'])) {
                if ($msg_id === $flds['imap_part_number'] || (string) preg_replace("/^0\.{1}/", '', $msg_id) === (string) $flds['imap_part_number']) {
                    $matches++;
                }
            }
            foreach ($flds as $name => $fld_val) {
                if (isset($vals[$name]) && stristr($vals[$name], $fld_val)) {
                    $matches++;
                }
            }
            if (array_key_exists('envelope', $vals)) {
                $parent = $vals;
            }
            if ($matches === count($flds)) {
                $part = $vals;
                if (isset($part['subs'])) {
                    $part['subs'] = count($part['subs']);
                }
                if (is_array($parent) && array_key_exists('envelope', $parent)) {
                    $part['envelope'] = $parent['envelope'];
                }
                $res[preg_replace("/^0\.{1}/", '', $msg_id)] = $part;
                if (!$all) {
                    return $res;
                }
                
            }
            if (isset($vals['subs'])) {
                $res = $this->recursive_search($vals['subs'], $flds, $all, $res, $parent);
            }
        }
        return $res;
    }

    /**
     * Return structure
     * @return array
     */
    public function data () {
        return $this->struct;
    } 

    /**
     * Public search function, returns a list of matching parts
     * @param array $flds key => value pairs of fields and values to search on
     * @param bool $all true to return all matches
     * @return array
     */
    public function search($flds, $all=true) {
        return $this->recursive_search($this->struct, $flds, $all, $res=array());
    }
}
