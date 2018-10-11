<?php

/**
 * Carddav contact modules
 * @package modules
 * @subpackage carddav_contacts
 */

if (!defined('DEBUG_MODE')) { die(); }

/**
 * @subpackage carddav_contacts/handler
 */
class Hm_Handler_load_carddav_contacts extends Hm_Handler_Module {
    public function process() {
        $contacts = $this->get('contact_store');
        /* load here */
    }
}

/**
 * @subpackage carddav_contacts/functions
 */
function propfind($url, $headers) {
    
    //$headers = array('Authorization: Basic '. base64_encode(sprintf('%s:%s', $user, $pass)));
    $req_xml = '<d:propfind xmlns:d="DAV:" xmlns:cs="http://calendarserver.org/ns/"><d:prop>'.
        '<cs:getctag /></d:prop></d:propfind>';
    $api = new Hm_API_Curl('xml');
    elog($api->command($url, $headers, array(), $req_xml, 'PROPFIND'));
}

/**
 * @subpackage carddav_contacts/functions
 */
function report($url, $user, $pass) {
    
    $headers = array('Authorization: Basic '. base64_encode(sprintf('%s:%s', $user, $pass)));
    $req_xml = '<card:addressbook-query xmlns:d="DAV:" xmlns:card="urn:ietf:params:xml:ns:carddav">'.
        '<d:prop><d:getetag /><card:address-data /></d:prop></card:addressbook-query>';
    $api = new Hm_API_Curl('xml');
    elog($api->command($url, $headers, array(), $req_xml, 'REPORT'));
}
