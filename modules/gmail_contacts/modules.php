<?php

/**
 * Gmail contact modules
 * @package modules
 * @subpackage gmail_contacts
 */

if (!defined('DEBUG_MODE')) { die(); }

require APP_PATH.'modules/gmail_contacts/hm-gmail-contacts.php';

/**
 * @subpackage gmail_contacts/handler
 */
class Hm_Handler_load_gmail_contacts extends Hm_Handler_Module {
    public function process() {
        $contacts = $this->get('contact_store');
        if ($this->module_is_supported('imap')) {
            $contacts = fetch_gmail_contacts($this->config, $contacts, $this->session);
        }
        $this->append('contact_sources', 'gmail');
        $this->out('contact_store', $contacts, false);
    }
}

/**
 * @subpackage gmail_contacts/functions
 */
if (!hm_exists('gmail_contacts_request')) {
function gmail_contacts_request($token, $url) {
    $headers = array('Authorization: OAuth '.$token, 'GData-Version: 3.0');
    $api = new Hm_API_Curl('xml');
    return $api->command($url, $headers);
}}

/**
 * @subpackage gmail_contacts/functions
 */
if (!hm_exists('parse_contact_xml')) {
function parse_contact_xml($xml, $source) {
    $parser = new Hm_Gmail_Contact_XML($xml);
    $results = array();
    $exists = array();
    foreach ($parser->parse() as $contact) {
        if (!array_key_exists('email_address', $contact)) {
            continue;
        }
        if (in_array($contact['email_address'], $exists, true)) {
            continue;
        }
        if (!array_key_exists('display_name', $contact)) {
            $contact['display_name'] = '';
        }
        $exists[] = $contact['email_address'];
        $contact['source'] = $source;
        $contact['type'] = 'gmail';
        $results[] = $contact;
    }
    return $results;
}}

/**
 * @subpackage gmail_contacts/functions
 */
if (!hm_exists('fetch_gmail_contacts')) {
function fetch_gmail_contacts($config, $contact_store, $session=false) {
    if ($session && $session->get('gmail_contacts') && is_array($session->get('gmail_contacts')) && count($session->get('gmail_contacts')) > 0) {
        $contact_store->import($session->get('gmail_contacts'));
        return $contact_store;
    }
    $all_contacts = array();
    foreach(Hm_IMAP_List::dump(false, true) as $id => $server) {
        if ($server['server'] == 'imap.gmail.com' && array_key_exists('auth', $server) && $server['auth'] == 'xoauth2') {
            $results = imap_refresh_oauth2_token($server, $config);
            if (!empty($results)) {
                if (Hm_IMAP_List::update_oauth2_token($id, $results[1], $results[0])) {
                    Hm_Debug::add(sprintf('Oauth2 token refreshed for IMAP server id %d', $id));
                    $server = Hm_IMAP_List::dump($id, true);
                }
            }
            $url = 'https://www.google.com/m8/feeds/contacts/'.$server['user'].'/full?max-results=500';
            $contacts = parse_contact_xml(gmail_contacts_request($server['pass'], $url), $server['name']);
            if (count($contacts) > 0) {
                $contact_store->import($contacts);
                $all_contacts = array_merge($all_contacts, $contacts);
            }
        }
        if ($session && count($all_contacts) > 0) {
            $session->set('gmail_contacts', $all_contacts);
        }
    }
    return $contact_store;
}}
