<?php

/**
 * Gmail contacts modules
 * @package modules
 * @subpackage gmail_contacts
 */

if (!defined('DEBUG_MODE')) { die(); }

/**
 * Parses Google People API v1 JSON responses into a flat contact array.
 * @subpackage gmail_contacts/lib
 */
class Hm_Gmail_People_API {
    private $response;

    public function __construct($response) {
        $this->response = $response;
    }

    public function parse() {
        $results = array();
        if (!is_array($this->response)) {
            Hm_Debug::add('Gmail People API: empty or non-JSON response received', 'warning');
            return $results;
        }
        if (array_key_exists('error', $this->response)) {
            $err = $this->response['error'];
            $msg = isset($err['message']) ? $err['message'] : 'unknown error';
            $code = isset($err['code']) ? $err['code'] : '?';
            Hm_Debug::add(sprintf('Gmail People API error %s: %s', $code, $msg), 'warning');
            return $results;
        }
        if (!array_key_exists('connections', $this->response)) {
            Hm_Debug::add('Gmail People API: response has no connections key (empty contacts or missing API scope)', 'info');
            return $results;
        }
        foreach ($this->response['connections'] as $person) {
            if (!array_key_exists('emailAddresses', $person) || empty($person['emailAddresses'])) {
                continue;
            }
            $contact = array(
                'email_address' => $person['emailAddresses'][0]['value'],
                'display_name' => '',
            );
            if (array_key_exists('names', $person) && !empty($person['names'])) {
                $contact['display_name'] = $person['names'][0]['displayName'];
            }
            if (array_key_exists('phoneNumbers', $person) && !empty($person['phoneNumbers'])) {
                $contact['phone_number'] = $person['phoneNumbers'][0]['value'];
            }
            $results[] = $contact;
        }
        return $results;
    }
}
