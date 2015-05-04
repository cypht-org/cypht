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
abstract class Hm_Contact_Store {

    protected $contacts = array();
    protected $key = false;
    protected $key_validator = false;

    public function __construct($key = 0, $key_validator=false) {
        $this->key = $key;
        $this->key_validator = $key_validator;
    }

    protected function add_contact($data) {
        $contact = new Hm_Contact($data);
        if (!is_int($this->key)) {
            $this->check_key($contact);
            $this->contacts[$contact->value($this->key)] = $contact;
        }
        else {
            $this->contacts[$this->key] = $contact;
            $this->key++;
        }

        return true;
    }

    protected function check_key($contact) {
        if (!$contact->value($this->key)) {
            return false;
        }
        if (array_key_exists($contact->value($this->key), $this->contacts)) {
            return false;
        }
        if (!$this->validate_key($contact)) {
            return false;
        }
    }

    protected function validate_key($contact) {
        if ($this->key_validator !== false) {
            if (!function_exists($this->key_validator) || !call_user_func($this->key_validator, $contact->value($this->key))) {
                return false;
            }
        }
        return true;
    }

    abstract public function load($source);

    public function get($id, $default=false) {
        if (!array_key_exists($id, $this->contacts)) {
            return $default;
        }
        return $this->contacts[$id];
    }

    public function search($flds) {
        $res = array();
        foreach ($flds as $fld => $term) {
            foreach ($this->contacts as $id => $contact) {
                if ($this->search_contact($contact, $fld, $id, $term)) {
                    $res[$contact->value($this->key)] = $contact;
                }
            }
        }
        return $res;
    }

    protected function search_contact($contact, $fld, $id, $term) {
        if (!is_int($this->key) && $fld == $this->key && stristr($id, $term)) {
            return true;
        }
        elseif (stristr($contact->value($fld, ''), $term)) {
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

    public function export() {
        return array_map(function($contact) { return $contact->export(); }, $this->contacts);
    }

    public function import($data) {
        foreach ($data as $contact) {
            $this->add_contact($contact);
        }
    }

    public function page($page, $size) {
        if ($page < 1) {
            return array();
        }
        return array_slice( $this->contacts, (($page - 1)*$size), $size, true);
    }
}

/**
 * @subpackage contacts/lib
 */
class Hm_Contact_Store_DB extends Hm_Contact_Store {

    public function load($source) {
    }
}

/**
 * @subpackage contacts/lib
 */
class Hm_Contact_Store_File extends Hm_Contact_Store {

    public function load($source) {
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
        if (!array_key_exists($fld, $this->data)) {
            return false;
        }
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
 * @todo: move to test suite
 */
class Hm_Contact_Store_Test extends Hm_Contact_Store {

    public function load($source) {
        $this->add_contact(array('email_address' => 'user1@cypht.org', 'display_name' => 'User One'));
        $this->add_contact(array('email_address' => 'user2@cypht.org', 'display_name' => 'User Two'));
        $this->add_contact(array('email_address' => 'user3@cypht.org', 'display_name' => 'User Three'));
        $this->add_contact(array('email_address' => 'user4@cypht.org', 'display_name' => 'User Four'));
        $this->add_contact(array('email_address' => 'user5@cypht.org', 'display_name' => 'User Five'));
        $this->add_contact(array('email_address' => 'user6@cypht.org', 'display_name' => 'User Six'));
        return true;
    }
}

?>
