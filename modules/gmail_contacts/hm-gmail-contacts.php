<?php

/**
 * Gmail contacts modules
 * @package modules
 * @subpackage gmail_contacts
 */

if (!defined('DEBUG_MODE')) { die(); }

/**
 * @subpackage gmail_contacts/lib
 */
class Hm_Gmail_Contact_XML {
    private $collect = false;
    private $results = array();
    private $xml_parser = false;
    private $xml = false;
    private $index = 0;

    public function __construct($xml) {
        $this->xml = $xml;
        $this->xml_parser = xml_parser_create( 'UTF-8');
        xml_set_object($this->xml_parser, $this);
        xml_set_element_handler($this->xml_parser, 'xml_start_element', 'xml_end_element');
        xml_set_character_data_handler($this->xml_parser, 'xml_character_data');
    }
    public function parse() {
        if (xml_parse($this->xml_parser, $this->xml)) {
        }
        return $this->results;
    }
    public function xml_start_element($parser, $tagname, $attrs) {
        if ($tagname == 'ENTRY') {
            if (!array_key_exists($this->index, $this->results)) {
                $this->results[$this->index] = array();
            }
        }
        if ($tagname == 'GD:EMAIL') {
            $this->results[$this->index]['email_address'] = $attrs['ADDRESS'];
        }
        if ($tagname == 'GD:FULLNAME') {
            $this->collect = true;
        }
        if ($tagname == 'GD:PHONENUMBER') {
            if (array_key_exists('URI', $attrs)) {
                $this->results[$this->index]['phone_number'] = substr($attrs['URI'], 5);
            }
        }
    }
    public function xml_end_element($parser, $tagname) {
        if ($tagname == 'ENTRY') {
            $this->index++;
        }
    }
    public function xml_character_data($parser, $data) {
        if ($this->collect) {
            $this->results[$this->index]['display_name'] = $data;
            $this->collect = false;
        }
    }
}

