<?php

/**
 * Gmail contact modules
 * @package modules
 * @subpackage gmail_contacts
 */

if (!defined('DEBUG_MODE')) { die(); }

require APP_PATH.'modules/gmail_contacts/hm-gmail-contacts.php';
require_once APP_PATH.'modules/imap/hm-imap.php';

/**
 * @subpackage gmail_contacts/handler
 */
class Hm_Handler_load_gmail_contacts extends Hm_Handler_Module {
    public function process() {
        $contacts = $this->get('contact_store');
        if ($this->module_is_supported('imap')) {
            $settings = $this->get('user_settings', array());
            $max_google_contacts_number = DEFAULT_MAX_GOOGLE_CONTACTS_NUMBER;

            if (array_key_exists('max_google_contacts_number', $settings)) {
                $max_google_contacts_number = $settings['max_google_contacts_number'];
            }

            $contacts = fetch_gmail_contacts($this->config, $contacts, $this->session, $max_google_contacts_number);
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
    $headers = array('Authorization: Bearer '.$token);
    $api = new Hm_API_Curl('json');
    return $api->command($url, $headers);
}}

/**
 * @subpackage gmail_contacts/functions
 */
if (!hm_exists('parse_people_api_contacts')) {
function parse_people_api_contacts($response, $source) {
    $parser = new Hm_Gmail_People_API($response);
    $results = array();
    $exists = array();
    foreach ($parser->parse() as $contact) {
        if (in_array($contact['email_address'], $exists, true)) {
            continue;
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
function fetch_gmail_contacts($config, $contact_store, $session=false, $max_google_contacts_number = 500) {
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
                    Hm_Debug::add(sprintf('Oauth2 token refreshed for IMAP server id %s', $id), 'info');
                    $server = Hm_IMAP_List::dump($id, true);
                }
            }

            $page_size = min((int) $max_google_contacts_number, 1000);
            $page_token = '';
            $collected = 0;
            do {
                $url = 'https://people.googleapis.com/v1/people/me/connections'
                     . '?personFields=names,emailAddresses,phoneNumbers'
                     . '&pageSize=' . $page_size;
                if ($page_token) {
                    $url .= '&pageToken=' . urlencode($page_token);
                }
                $response = gmail_contacts_request($server['pass'], $url);
                Hm_Debug::add(sprintf('Gmail People API request for server id %s, page_token: %s', $id, $page_token ? 'yes' : 'none'), 'info');
                $contacts = parse_people_api_contacts($response, $server['name']);
                Hm_Debug::add(sprintf('Gmail People API returned %d contacts for server id %s', count($contacts), $id), 'info');
                if (count($contacts) > 0) {
                    $contact_store->import($contacts);
                    $all_contacts = array_merge($all_contacts, $contacts);
                    $collected += count($contacts);
                }
                $page_token = (is_array($response) && array_key_exists('nextPageToken', $response)) ? $response['nextPageToken'] : '';
            } while ($page_token && $collected < $max_google_contacts_number);
        }
    }
    if ($session && count($all_contacts) > 0) {
        $session->set('gmail_contacts', $all_contacts);
    }
    return $contact_store;
}}
