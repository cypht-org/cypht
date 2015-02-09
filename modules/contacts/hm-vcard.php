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
class Hm_Vcard_Reader {

    public function __construct($vcard_data) {
        $cards = $this->find_cards($vcard_data);
        $parsed = $this->parse_cards($cards);
        return $parsed;
    }

    private function find_cards($vcard_data) {
    }

    private function parse_cards($cards) {
    }

}

/**
 * @subpackage contacts/lib
 */
class Hm_Vcard_Store {

    private $cards = array();

    public function __construct() {
    }

    public function load($data) {
        $cards = new Hm_Vcard_Reader($data);
        $this->cards = array_merge($this->cards, $cards);
    }

    public function get($id) {
    }

    public function search($flds) {
    }

    public function update($id, $values) {
    }

    public function delete($id) {
    }

    public function dump() {
    }

    public function page($page, $size) {
    }
}

/**
 * @subpackage contacts/lib
 */
class Hm_Vcard {

    private $data = array();

    function __construct($data) {
        $this->build($data);
    }

    function build($data) {
    }

    function export() {
    }
}

?>
