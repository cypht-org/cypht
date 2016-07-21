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
class Hm_LDAP_Contacts {

    private $config;
    private $fh;
    private $source = 'ldap';

    public function __construct($config) {
        $this->config = $config;
        if (array_key_exists('name', $config)) {
            $this->source = $config['name'];
        }
    }

    private function connect_details() {
        $prefix = 'ldaps://';
        $server = 'localhost';
        $port = 389;
        if (array_key_exists('server', $this->config)) {
            $server = $this->config['server'];
        }
        if (array_key_exists('port', $this->config)) {
            $port = $this->config['port'];
        }
        if (array_key_exists('enable_tls', $this->config) && !$this->config['enable_tls']) {
            $prefix = 'ldap://';
        }
        return $prefix.$server.':'.$port;
    }

    public function connect() {
        if (!function_exists('ldap_connect')) {
            return false;
        }
        $uri = $this->connect_details(); 
        $this->fh = ldap_connect($uri);
        if ($this->fh) {
            if ($this->auth()) {
                return true;
            }
        }
        return false;
    }

    public function add($entry, $dn) {
        return @ldap_add($this->fh, $dn, $entry);
    }

    public function delete($dn) {
        return @ldap_delete($this->fh, $dn);
    }

    private function auth() {
        if (array_key_exists('auth', $this->config) && $this->config['auth']) {
            if (array_key_exists('user', $this->config) && array_key_exists('pass', $this->config)) {
                return ldap_bind($this->fh, $this->config['user'], $this->config['pass']);
            }
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
            $res = ldap_search($this->fh, $base_dn, $search_term);
            if ($res) {
                $contacts = ldap_get_entries($this->fh, $res);
                return $this->parse($contacts);
            }
        }
        return array();
    }
}
