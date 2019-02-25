<?php

/**
 * Carddav modules
 * @package modules
 * @subpackage carddav_contacts
 */

if (!defined('DEBUG_MODE')) { die(); }

/**
 * @subpackage carddav_contacts/lib
 */
class Hm_Carddav {

    public $addresses = array();
    private $src;
    private $url;
    private $user;
    private $pass;
    private $principal_url;
    private $address_url;
    private $principal_path = '//response/propstat/prop/current-user-principal/href';
    private $addressbook_path = '//response/propstat/prop/addressbook-home-set/href';
    private $addr_list_path = '//response/href';
    private $addr_detail_path = '//response/propstat/prop/address-data';

    public function __construct($src, $url, $user, $pass) {
        $this->user = $user;
        $this->src = $src;
        $this->pass = $pass;
        $this->url = $url;
        $this->api = new Hm_API_Curl('xml');
        if ($this->discover()) {
            $this->get_vcards();
        }
    }

    private function get_vcards() {
        $res = array();
        $parser = new Hm_VCard();
        $count = 0;
        foreach ($this->xml_find($this->list_addressbooks(), $this->addr_list_path, true) as $url) {
            $url = $this->url_concat($url);
            if ($url == $this->address_url) {
                continue;
            }
            $count++;
            Hm_Debug::add(sprintf('CARDDAV: Trying contacts url %s', $url));
            foreach ($this->xml_find($this->report($url), $this->addr_detail_path, true) as $addr) {
                $parser->import($addr);
                foreach ($this->convert_to_contact($parser) as $contact) {
                    $contact['src_url'] = $url;
                    $res[] = $contact;
                }
            }
        }
        Hm_Debug::add(sprintf('CARDDAV: %s contact urls found', $count));
        $this->addresses = $res;
    }

    private function convert_to_contact($parser) {
        $res = array();
        $emails = $parser->fld_val('email', false, array(), true);
        if (count($emails) == 0) {
            return $res;
        }

        $dn = $parser->fld_val('dn');
        if (!$dn) {
            $dn = $parser->fld_val('n');
            if (is_array($dn) && count($dn) > 0) {
                $dn = sprintf('%s %s', $dn['firstname'], $dn['lastname']);
            }
        }
        $phone = $parser->fld_val('tel');
        $all_flds = $this->parse_extr_flds($parser);
        foreach ($emails as $email) {
            $res[] = array(
                'source' => $this->src,
                'type' => 'carddav',
                'display_name' => $dn,
                'phone_number' => $phone,
                'email_address' => $email['values'],
                'all_fields' => $all_flds
            );
        }
        return $res;
    }

    private function parse_extr_flds($parser) {
        $all_flds = array();
        foreach (array_keys($parser->parsed_data()) as $name) {
            if (in_array($name, array('begin', 'end', 'n', 'tel', 'email', 'raw'))) {
                continue;
            }
            $all_flds[$name] = $parser->fld_val($name);
        }
        return $all_flds;
    }

    private function discover() {
        $path = $this->xml_find($this->principal_discover(), $this->principal_path);
        if ($path === false) {
            Hm_Debug::add('CARDDAV: No principal path discovered');
            return false;
        }
        Hm_Debug::add(sprintf('CARDDAV: Found %s principal path', $path));
        $this->principal_url = $this->url_concat($path);
        $address_path = $this->xml_find($this->addressbook_discover(), $this->addressbook_path);
        if ($address_path === false) {
            Hm_Debug::add('CARDDAV: No address path discovered');
            return false;
        }
        Hm_Debug::add(sprintf('CARDDAV: Found %s address path', $address_path));
        $this->address_url = $this->url_concat($address_path);
        return true;
    }

    private function parse_xml($xml) {
        if (substr((string) $this->api->last_status, 0, 1) != '2') {
            Hm_Debug::add(sprintf('ERRUnable to access CardDav server (%d)', $this->api->last_status));
            return false;
        }
        $xml = preg_replace("/<[a-zA-Z]+:/Um", "<", $xml);
        $xml = preg_replace("/<\/[a-zA-Z]+:/Um", "</", $xml);
        $xml = str_replace('xmlns=', 'ns=', $xml);
        try {
            $data = new SimpleXMLElement($xml);
            return $data;
        }
        catch (Exception $oops) {
            Hm_Msgs::add('ERRUnable to access CardDav server');
            Hm_Debug::add(sprintf('CARDDAV: Could not parse XML: %s', $xml));
        }
        return false;
    }

    private function xml_find($xml, $path, $multi=false) {
        $data = $this->parse_xml($xml);
        if (!$data) {
            return false;
        }
        $res = array();
        foreach ($data->xpath($path) as $node) {
            if (!$multi) {
                return (string) $node;
            }
            $res[] = (string) $node;
        }
        if ($multi) {
            if (count($res) == 0) {
                Hm_Debug::add(sprintf('CARDDAV: find for %s failed in xml: %s', $path, $xml));
            }
            return $res;
        }
        Hm_Debug::add(sprintf('CARDDAV: find for %s failed in xml: %s', $path, $xml));
        return false;
    }

    private function url_concat($path) {
        if (substr($this->url, -1) == '/' && substr($path, -1) == '/') {
            return sprintf('%s%s', substr($this->url, 0, -1), $path);
        }
        if (substr($this->url, -1) != '/' && substr($path, -1) != '/') {
            return sprintf('%s/%s', $this->url, $path);
        }
        return sprintf('%s%s', $this->url, $path);
    }

    private function auth_headers() {
        return array('Authorization: Basic '. base64_encode(sprintf('%s:%s', $this->user, $this->pass)));
    }

    private function addressbook_discover() {
        $req_xml = '<d:propfind xmlns:d="DAV:" xmlns:card="urn:ietf:params:xml:ns:carddav"><d:prop>'.
            '<card:addressbook-home-set /></d:prop></d:propfind>';
        return $this->api->command($this->principal_url, $this->auth_headers(), array(), $req_xml, 'PROPFIND');
    }

    private function principal_discover() {
        $req_xml = '<d:propfind xmlns:d="DAV:"><d:prop><d:current-user-principal /></d:prop></d:propfind>';
        Hm_Debug::add(sprintf('CARDDAV: Sending discover XML: %s', $req_xml));
        return $this->api->command($this->url, $this->auth_headers(), array(), $req_xml, 'PROPFIND');
    }

    private function list_addressbooks() {
        $headers = $this->auth_headers();
        $headers[] = 'Depth: 1';
        $req_xml = '<d:propfind xmlns:d="DAV:" xmlns:cs="http://calendarserver.org/ns/"><d:prop>'.
           '<d:resourcetype /><d:displayname /><cs:getctag /></d:prop></d:propfind>';
        Hm_Debug::add(sprintf('CARDDAV: Sending addressbook XML: %s', $req_xml));
        return $this->api->command($this->address_url, $headers, array(), $req_xml, 'PROPFIND');
    }

    private function report($url) {
        $req_xml = '<card:addressbook-query xmlns:d="DAV:" xmlns:card="urn:ietf:params:xml:ns:carddav">'.
            '<d:prop><d:getetag /><card:address-data /></d:prop></card:addressbook-query>';
        Hm_Debug::add(sprintf('CARDDAV: Sending contacts XML: %s', $req_xml));
        return $this->api->command($url, $this->auth_headers(), array(), $req_xml, 'REPORT');
    }
}
