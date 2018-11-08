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
    private $principal_path = '//a:response/a:propstat/a:prop/a:current-user-principal/a:href';
    private $addressbook_path = '//a:response/a:propstat/a:prop/CR:addressbook-home-set/a:href';
    private $addr_list_path = '//a:response/a:href';
    private $addr_detail_path = '//a:response/a:propstat/a:prop/CR:address-data';

    public function __construct($src, $url, $user, $pass) {
        $this->user = $user;
        $this->src = $src;
        $this->pass = $pass;
        $this->url = $url;
        if ($this->discover()) {
            $this->get_vcards();
        }
    }

    private function get_vcards() {
        $res = array();
        $parser = new Hm_VCard();
        foreach ($this->xml_find($this->list_addressbooks(), $this->addr_list_path, true) as $url) {
            $url = $this->url_concat($url);
            if ($url == $this->address_url) {
                continue;
            }
            foreach ($this->xml_find($this->report($url), $this->addr_detail_path, true) as $addr) {
                $parser->import($addr);
                foreach ($this->convert_to_contact($parser->parsed_data()) as $contact) {
                    $res[] = $contact;
                }
            }
        }
        $this->addresses = $res;
    }

    private function convert_to_contact($data) {
        $res = array();
        if (!array_key_exists('email', $data) || count($data['email']) == 0) {
            return $res;
        }

        $dn = '';
        $phone = '';
        $dn = array_key_exists('n', $data) ? sprintf('%s %s', $data['n']['firstname'], $data['n']['lastname']) : '';
        $phone = array_key_exists('tel', $data) && count($data['tel']) > 0 ? $data['tel'][0]['value']: '';

        foreach ($data['email'] as $email) {
            $res[] = array(
                'source' => $this->src,
                'display_name' => $dn,
                'phone_number' => $phone,
                'email_address' => $email['value']
            );
        }
        return $res;
    }

    private function discover() {
        $path = $this->xml_find($this->principal_discover(), $this->principal_path);
        if ($path === false) {
            return false;
        }
        $this->principal_url = $this->url_concat($path);
        $address_path = $this->xml_find($this->addressbook_discover(), $this->addressbook_path);
        if ($address_path === false) {
            return false;
        }
        $this->address_url = $this->url_concat($address_path);
        return true;
    }

    private function xml_find($xml, $path, $multi=false) {
        $data = new SimpleXMLElement($xml);
        foreach ($data->getDocNamespaces() as $pre => $ns) {
            if (!$pre) {
                $pre = 'a';
            }
            $data->registerXPathNamespace($pre, $ns);
        }
        $res = array();
        foreach ($data->xpath($path) as $node) {
            if (!$multi) {
                return (string) $node;
            }
            $res[] = (string) $node;
        }
        if ($multi) {
            return $res;
        }
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
        $api = new Hm_API_Curl('xml');
        return $api->command($this->principal_url, $this->auth_headers(), array(), $req_xml, 'PROPFIND');
    }

    private function principal_discover() {
        $req_xml = '<d:propfind xmlns:d="DAV:"><d:prop><d:current-user-principal /></d:prop></d:propfind>';
        $api = new Hm_API_Curl('xml');
        return $api->command($this->url, $this->auth_headers(), array(), $req_xml, 'PROPFIND');
    }

    private function list_addressbooks() {
        $headers = $this->auth_headers();
        $headers[] = 'Depth: 1';
        $req_xml = '<d:propfind xmlns:d="DAV:" xmlns:cs="http://calendarserver.org/ns/"><d:prop>'.
           '<d:resourcetype /><d:displayname /><cs:getctag /></d:prop></d:propfind>';
        $api = new Hm_API_Curl('xml');
        return $api->command($this->address_url, $headers, array(), $req_xml, 'PROPFIND');
    }

    private function report($url) {
        $req_xml = '<card:addressbook-query xmlns:d="DAV:" xmlns:card="urn:ietf:params:xml:ns:carddav">'.
            '<d:prop><d:getetag /><card:address-data /></d:prop></card:addressbook-query>';
        $api = new Hm_API_Curl('xml');
        return $api->command($url, $this->auth_headers(), array(), $req_xml, 'REPORT');
    }
}
