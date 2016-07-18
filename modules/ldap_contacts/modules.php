<?php

/**
 * LDAP contact modules
 * @package modules
 * @subpackage ldap_contacts
 */

if (!defined('DEBUG_MODE')) { die(); }

require APP_PATH.'modules/ldap_contacts/hm-ldap-contacts.php';

/**
 * @subpackage ldap_contacts/functions
 */
function fetch_ldap_contacts($config, $contact_store, $session=false) {
    $ldap_config = ldap_config($config);
    if (count($ldap_config) > 0) {
        $ldap = new Hm_LDAP_Contacts($ldap_config);
        if ($ldap->connect()) {
            $contacts = $ldap->fetch();
            if (count($contacts) > 0) {
                $contact_store->import($contacts);
            }
        }
    }
    return $contact_store;
}

/**
 * @subpackage ldap_contacts/functions
 */
function ldap_config($config) {
    $details = array();
    $ini_file = rtrim($config->get('app_data_dir', ''), '/').'/ldap.ini';
    if (is_readable($ini_file)) {
        $settings = parse_ini_file($ini_file);
        if (!empty($settings)) {
            $details = $settings;
        }
    }
    return $details;
}

/**
 * @subpackage ldap_contacts/handler
 */
class Hm_Handler_load_ldap_contacts extends Hm_Handler_Module {
    public function process() {
        $contacts = $this->get('contact_store');
        $contacts = fetch_ldap_contacts($this->config, $contacts);
        $this->out('contact_store', $contacts);
    }
}
