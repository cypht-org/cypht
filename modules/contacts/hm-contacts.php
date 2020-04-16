<?php

/**
 * Contacts modules
 * @package modules
 * @subpackage contacts
 */

if (!defined('DEBUG_MODE')) { die(); }

/**
 * @subpackage contacts/lib
 */
class Hm_Contact_Store {

    private $contacts = array();
    private $sort_fld = false;

    public function __construct() {
    }

    public function add_contact($data) {
        $contact = new Hm_Contact($data);
        $this->contacts[] = $contact;
        return true;
    }

    public function get($id, $default=false) {
        if (!array_key_exists($id, $this->contacts)) {
            return $default;
        }
        return $this->contacts[$id];
    }

    public function search($flds) {
        $res = array();
        $found = array();
        foreach ($flds as $fld => $term) {
            foreach ($this->contacts as $id => $contact) {
                if (array_key_exists($contact->value('email_address'), $found)) {
                    continue;
                }
                if ($this->search_contact($contact, $fld, $term)) {
                    $res[$id] = $contact;
                    $found[$contact->value('email_address')] = 1;
                }
            }
        }
        return $res;
    }

    protected function search_contact($contact, $fld, $term) {
        if (stristr($contact->value($fld, ''), $term)) {
            return true;
        }
        return false;
    }

    public function update($id, $contact) {
        if (!array_key_exists($id, $this->contacts)) {
            return false;
        }
        $this->contacts[$id] = $contact;
    }

    public function update_contact_fld($contact, $name, $value)  {
        return $contact->update($name, $value);
    }

    public function update_contact($id, $flds) {
        if (!$contact = $this->get($id)) {
            return false;
        }
        $failures = 0;
        foreach ($flds as $name => $value) {
            $failures += (int) !$this->update_contact_fld($contact, $name, $value);
        }
        if ($failures == 0) {
            $this->update($id, $contact);
        }
        return $failures > 0 ? false : true;
    }

    public function delete($id) {
        if (!array_key_exists($id, $this->contacts)) {
            return false;
        }
        unset($this->contacts[$id]);
        return true;
    }

    public function dump() {
        return $this->contacts;
    }

    public function export($source = 'local') {
        return array_map(function($contact) { return $contact->export(); },
            array_filter($this->contacts, function($contact) use ($source) { return $contact->value('source') == $source; })
        );
    }

    public function import($data) {
        foreach ($data as $contact) {
            $this->add_contact($contact);
        }
    }

    public function reset() {
        $this->contacts = array();
    }

    public function page($page, $size) {
        if ($page < 1) {
            return array();
        }
        return array_slice( $this->contacts, (($page - 1)*$size), $size, true);
    }

    public function sort($fld) {
        $this->sort_fld = $fld;
        uasort($this->contacts, array($this, 'sort_callback'));
    }

    public function sort_callback($a, $b) {
        return strcmp($a->value($this->sort_fld), $b->value($this->sort_fld));
    }
}

/**
 * @subpackage contacts/lib
 */
class Hm_Contact {

    private $data = array();

    function __construct($data) {
        $this->build($data);
    }

    function build($data) {
        foreach ($data as $name => $value) {
            $this->data[$name] = $value;
        }
    }

    function update($fld, $val) {
        $this->data[$fld] = $val;
        return true;
    }

    function value($fld, $default=false) {
        if (array_key_exists($fld, $this->data)) {
            return $this->data[$fld];
        }
        return $default;
    }

    function export() {
        return $this->data;
    }
}

/**
 * @subpackage contacts/lib
 */
class Hm_Address_Field {

    public static function parse($string) {
        $marker = true;
        $string = str_replace(array('<', '>'), array(' <', '> '), $string);
        $string = preg_replace("/\s{2,}/", ' ', $string);
        $results = array();

        while ($marker !== false) {
            list($marker, $token, $string) = self::get_token($string);
            if (is_email_address($token)) {
                list($name, $marker) = self::find_name_field($string);
                if ($marker > -1) {
                    $string = substr($string, 0, $marker);
                }
                else {
                    $marker = false;
                }
                $results[] = array('email' => $token, 'name' => $name);
            }
        }
        return $results;
    }

    private static function get_token($string) {
        $marker = strrpos($string, ' ');
        $token = trim(ltrim(substr($string, $marker)), '<>');
        $string = substr($string, 0, $marker);
        return array($marker, $token, $string);
    }

    private static function is_quote($string, $i, $quote) {
        if (in_array($string[$i], array('"', "'"), true)) {
            if (!self::embeded_quote($string, $i)) {
                $quote = $quote ? false : true;
            }
        }
        return $quote;
    }

    private static function find_name_field($string) {
        $quote = false;
        $result = '';
        for ($i = strlen($string) - 1;$i>-1; $i--) {
            $quote = self::is_quote($string, $i, $quote);
            if (self::delimiter_found($string, $i, $quote)) {
                break;
            }
            $result .= $string[$i];
        }
        return array(strrev(trim(trim($result),'"\'')), $i);
    }

    private static function embeded_quote($string, $i) {
        return $i > 0 && $string[$i -1] == '\\';
    }

    private static function delimiter_found($string, $i, $quote) {
        return !$quote && in_array($string[$i], array(',', ';'), true);
    }
}
