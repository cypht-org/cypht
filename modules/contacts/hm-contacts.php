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

    protected $cards = array();
    protected $key = false;
    protected $key_validator =false;

    public function __construct($key, $key_validator=false) {
        $this->key = $key;
        $this->key_validator = $key_validator;
    }

    protected function add_card($data) {
        $contact = new Hm_Contact($data);
        if (!$contact->value($this->key)) {
            return false;
        }
        if (array_key_exists($contact->value($this->key), $this->cards)) {
            return false;
        }
        if ($this->key_validator !== false) {
            if (!function_exists($this->key_validator) || !call_user_func($this->key_validator, $contact->value($this->key))) {
                return false;
            }
        }
        $this->cards[$contact->value($this->key)] = $contact;
        return true;
    }

    abstract public function load($source);

    public function get($id, $default=false) {
        if (!array_key_exists($id, $this->cards)) {
            return $default;
        }
        return $this->cards[$id];
    }

    public function search($flds) {
        $res = array();
        foreach ($flds as $fld => $term) {
            foreach ($this->cards as $id => $card) {
                if ($fld == $this->key && stristr($id, $term)) {
                    $res[$card->value($this->key)] = $card;
                }
                elseif (stristr($card->value($fld, ''), $term)) {
                    $res[$card->value($this->key)] = $card;
                }
            }
        }
        return $res;
    }

    public function update($id, $contact) {
        if (!array_key_exists($id, $this->cards)) {
            return false;
        }
        $this->cards[$id] = $contact;
    }

    public function update_card_fld($contact, $name, $value)  {
        return $contact->update($name, $value);
    }

    public function update_card($id, $flds) {
        if (!$contact = $this->get($id)) {
            return false;
        }
        $failures = 0;
        foreach ($flds as $name => $value) {
            $failures += (int) !$this->update_card_fld($contact, $name, $value);
        }
        if ($failures == 0) {
            $this->update($id, $contact);
        }
        return $failures > 0 ? false : true;
    }

    public function delete($id) {
        if (!array_key_exists($id, $this->cards)) {
            return false;
        }
        unset($this->cards[$id]);
        return true;
    }

    public function dump() {
        return $this->cards;
    }

    public function page($page, $size) {
        if ($page < 1) {
            return array();
        }
        return array_slice( $this->cards, (($page - 1)*$size), $size, true);
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
class Hm_Contact_Store_Test extends Hm_Contact_Store {

    public function load($source) {
        $this->add_card(array('email_address' => 'user1@cypht.org', 'display_name' => 'User One'));
        $this->add_card(array('email_address' => 'user2@cypht.org', 'display_name' => 'User Two'));
        $this->add_card(array('email_address' => 'user3@cypht.org', 'display_name' => 'User Three'));
        $this->add_card(array('email_address' => 'user4@cypht.org', 'display_name' => 'User Four'));
        $this->add_card(array('email_address' => 'user5@cypht.org', 'display_name' => 'User Five'));
        $this->add_card(array('email_address' => 'user6@cypht.org', 'display_name' => 'User Six'));
        return true;
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
    }
}

//$test = new Hm_Contact_Store_Test('email_address', 'is_email');
//$test->load(false);
//elog($test->dump());

?>
