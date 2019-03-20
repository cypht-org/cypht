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
    private $card_flds = array(
        'carddav_email' => 'email',
        'carddav_phone' => 'tel',
        'carddav_fn' => 'fn'
    );

    public function __construct($src, $url, $user, $pass) {
        $this->user = $user;
        $this->src = $src;
        $this->pass = $pass;
        $this->url = $url;
        $this->api = new Hm_API_Curl('xml');
    }

    public function get_vcards() {
        if (!$this->discover()) {
            return;
        }
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

    public function add_contact($form) {
        if (!$this->discover()) {
            return false;
        }
        $filename = sha1(time().json_encode($form));
        $uid = sha1($filename);
        $card = array('BEGIN:VCARD', 'VERSION:3', sprintf('UID:%s', $uid));
        foreach ($this->card_flds as $name => $cname) {
            if (array_key_exists($name, $form) && trim($form[$name])) {
                $card[] = sprintf('%s:%s', strtoupper($cname), $form[$name]);
            }
        }
        $card[] = 'END:VCARD';
        $url = sprintf('%s%s.vcf', $this->address_url, $filename);
        $card = implode("\n", $card);
        return $this->update_server_contact($url, $card);
    }

    public function delete_contact($contact) {
        return $this->delete_server_contact($contact->value('src_url'));
    }

    public function update_contact($contact, $form) {
        $parsed = $contact->value('carddav_parsed');
        $parsed = $this->update_or_add('carddav_email', $form, $parsed);
        $parsed = $this->update_or_add('carddav_fn', $form, $parsed);
        $parsed = $this->update_or_add('carddav_phone', $form, $parsed);
        $new_card = $this->convert_to_card($parsed);
        return $this->update_server_contact($contact->value('src_url'), $new_card);
    }

    private function find_by_id($type, $form, $data) {
        if (!array_key_exists($type, $form) || !trim($form[$type])) {
            return false;
        }
        $id = $form[$type];
        foreach ($data as $name => $entry) {
            if (!is_array($entry)) {
                continue;
            }
            foreach ($entry as $index => $vals) {
                if (array_key_exists('id', $vals) && $id == $vals['id']) {
                    return array($name, $index);
                }
            }
        }
        return false;
    }

    private function update_or_add($type, $form, $parsed) {
        $path = $this->find_by_id($type.'_id', $form, $parsed);
        if ($path === false && trim($form[$type])) {
            $start = array_splice($parsed, 0, 2);
            $start[$this->card_flds[$type]] = array(array('values' => $form[$type]));
            $parsed = array_merge($start, $parsed);
        }
        elseif (trim($form[$type])) {
            $parsed[$path[0]][$path[1]]['values'] = $form[$type];
        }
        return $parsed;
    }

    private function convert_to_card($parsed) {
        $parser = new Hm_VCard();
        $parser->import_parsed($parsed);
        return $parser->build_card();
    }

    private function get_phone($parser) {
        $res = $parser->fld_val('tel', false, false, true);
        if ($res === false) {
            return array('', '');
        }
        return array($res[0]['values'], $res[0]['id']);
    }

    private function get_display_name($parser) {
        $res = $parser->fld_val('fn', false, false, true);
        if ($res === false) {
            return array('', '');
        }
        return array($res[0]['values'], $res[0]['id']);
    }

    private function convert_to_contact($parser) {
        $res = array();
        $emails = $parser->fld_val('email', false, array(), true);
        if (count($emails) == 0) {
            return $res;
        }

        list($fn, $fn_id) = $this->get_display_name($parser);
        list($phone, $phone_id) = $this->get_phone($parser);

        $all_flds = $this->parse_extr_flds($parser);
        foreach ($emails as $email) {
            $res[] = array(
                'source' => $this->src,
                'type' => 'carddav',
                'fn' => $fn,
                'carddav_fn_id' => $fn_id,
                'phone_number' => $phone,
                'email_address' => $email['values'],
                'carddav_phone_id' => $phone_id,
                'carddav_email_id' => $email['id'],
                'carddav_parsed' => $parser->parsed_data(),
                'all_fields' => $all_flds
            );
        }
        return $res;
    }

    private function parse_extr_flds($parser) {
        $all_flds = array();
        foreach (array_keys($parser->parsed_data()) as $name) {
            if (in_array($name, array('begin', 'end', 'n', 'fn', 'tel', 'email', 'raw'))) {
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

    private function delete_server_contact($url) {
        $headers = $this->auth_headers();
        $this->api->command($url, $headers, array(), '', 'DELETE');
        return $this->api->last_status == 200;
    }
    private function update_server_contact($url, $card) {
        $headers = $this->auth_headers();
        $headers[] = 'Content-Type: text/vcard; charset=utf-8';
        $this->api->command($url, $headers, array(), $card, 'PUT');
        return $this->api->last_status == 200 || $this->api->last_status == 201;
    }
}
