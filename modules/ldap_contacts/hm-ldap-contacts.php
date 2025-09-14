<?php

/**
 * LDAP contacts modules
 * @package modules
 * @subpackage contacts
 */

if (!defined('DEBUG_MODE')) { die(); }

/**
 * @subpackage ldap_contacts/lib
 */
class Hm_LDAP_Contacts extends Hm_Auth_LDAP {

    public function __construct($config) {
        if (is_array($config)) {
            $this->config = $config;
        }
        if (is_array($config) && array_key_exists('name', $config)) {
            $this->source = $config['name'];
        }
    }

    public function rename($dn, $new_dn, $parent) {
        return @ldap_rename($this->fh, $dn, $new_dn, $parent, true);
    }

    public function modify($entry, $dn) {
        return @ldap_modify($this->fh, $dn, $entry);
    }

    public function add($entry, $dn) {
        return @ldap_add($this->fh, $dn, $entry);
    }

    public function delete($dn) {
        return @ldap_delete($this->fh, $dn);
    }

    protected function auth() {
        if (array_key_exists('auth', $this->config) && $this->config['auth'] &&
            array_key_exists('user', $this->config) && $this->config['user'] &&
            array_key_exists('pass', $this->config) && $this->config['pass']) {
            $uid_attr = $this->config['ldap_uid_attr'];
            $user_dn = sprintf('%s=%s,%s', $uid_attr, $this->config['user'], $this->config['base_dn']);
            return @ldap_bind($this->fh, $user_dn, $this->config['pass']);
        }
        else {
            return @ldap_bind($this->fh);
        }
        return false;
    }

    private function parse($data) {
        $result = array();
        $flds = array(
            'mail' => 'email_address',
            'cn' => 'display_name',
            'telephonenumber' => 'phone_number'
        );
        foreach ($data as $contact) {
            $res = array();
            if (!is_array($contact)) {
                continue;
            }
            $all = array();
            foreach ($contact as $name => $fld) {
                if (in_array($name, array_keys($flds), true)) {
                    $res[$flds[$name]] = $fld[0];
                }
                elseif (!is_int($name) && $name != 'count' && $name != 'dn') {
                    $all[$name] = $fld[0];
                }
                elseif ($name == 'dn') {
                    $all[$name] = $fld;
                }
            }
            if (array_key_exists('email_address', $res) && $res['email_address'] &&
                array_key_exists('display_name', $res) && $res['display_name']) {
                $res['source'] = $this->source;
                $res['type'] = 'ldap';
                $res['all_fields'] = $all;
                $result[] = $res;
            }
        }
        return $result;
    }

    public function fetch() {
        $base_dn = 'dc=example,dc=com';
        $search_term='objectclass=inetOrgPerson';
        if (array_key_exists('search_term', $this->config)) {
            $search_term = $this->config['search_term'];
        }
        if (array_key_exists('base_dn', $this->config)) {
            $base_dn = $this->config['base_dn'];
            $res = @ldap_search($this->fh, $base_dn, $search_term, array(), 0, 0);
            if ($res) {
                $contacts = ldap_get_entries($this->fh, $res);
                return $this->parse($contacts);
            }
        }
        return array();
    }
}

/**
 * @subpackage ldap_contacts/lib
 */
class Hm_LDAP_Contact extends Hm_Contact {
    
    public function getDN() {
        $all_fields = $this->value('all_fields');
        if ($all_fields && isset($all_fields['dn'])) {
            return $all_fields['dn'];
        }
        return null;
    }
    
    public static function findByDN($contact_store, $target_dn, $contact_source) {
        $all_contacts = $contact_store->dump();
        
        foreach ($all_contacts as $contact_id => $contact_obj) {
            if ($contact_obj->value('source') == $contact_source && 
                $contact_obj->value('type') == 'ldap') {
                
                $all_fields = $contact_obj->value('all_fields');
                
                if (isset($all_fields['dn']) && $all_fields['dn'] === $target_dn) {
                    return $contact_obj;
                }
            }
        }
        return null;
    }
    
    public static function isLdapContact($contact) {
        return $contact instanceof self || $contact->value('type') === 'ldap';
    }

    public static function decodeDN($encoded_dn) {
        return urldecode($encoded_dn);
    }

    public static function generateDeleteAttributes($contact, $html_safe) {
        if (!self::isLdapContact($contact)) {
            return '';
        }
        
        $all_fields = $contact->value('all_fields');
        if ($all_fields && isset($all_fields['dn'])) {
            return ' data-ldap-dn="'.$html_safe($all_fields['dn']).'"';
        }
        
        return '';
    }

    public static function addDNToUrl($contact, $base_url) {
        if (!self::isLdapContact($contact)) {
            return $base_url;
        }
        
        $all_fields = $contact->value('all_fields');
        if ($all_fields && isset($all_fields['dn'])) {
            return $base_url . '&amp;dn='.urlencode($all_fields['dn']);
        }
        
        return $base_url;
    }

    public static function fromContact($contact) {
        if ($contact->value('type') !== 'ldap') {
            return null;
        }

        if ($contact instanceof self) {
            return $contact;
        }
        
        $contact_data = $contact->export();
        $ldap_contact = new self($contact_data);
        
        return $ldap_contact;
    }
}
