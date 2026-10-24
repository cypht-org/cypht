<?php

if (!hm_exists('gateway_contact_store')) {
function gateway_contact_store($handler) {
    if (!$handler->module_is_supported('contacts') || !$handler->module_is_supported('local_contacts')) {
        gateway_json_error('local contacts capability is unavailable', 501);
    }
    if (!class_exists('Hm_Contact_Store', false)) {
        require_once APP_PATH.'modules/contacts/hm-contacts.php';
    }
    $store = new Hm_Contact_Store();
    $store->init($handler->user_config, $handler->session);
    return $store;
}}

if (!hm_exists('gateway_safe_contact')) {
function gateway_safe_contact($id, $contact) {
    if (!is_object($contact) || $contact->value('source') !== 'local' || $contact->value('external')) {
        return null;
    }
    return array(
        'id' => (string)$id,
        'source' => 'local',
        'name' => (string)$contact->value('display_name', ''),
        'email' => (string)$contact->value('email_address', ''),
        'phone' => (string)$contact->value('phone_number', ''),
        'group' => (string)$contact->value('group', '')
    );
}}

if (!hm_exists('gateway_contact_fields')) {
function gateway_contact_fields($payload, $create = false) {
    $fields = array();
    foreach (array('name', 'email', 'phone', 'group') as $name) {
        if (!array_key_exists($name, $payload)) continue;
        if (!is_string($payload[$name])) gateway_json_error('contact field must be text', 400);
        $value = trim($payload[$name]);
        if (strlen($value) > 500) gateway_json_error('contact field is too long', 400);
        $fields[$name] = $value;
    }
    if ($create && (!isset($fields['name']) || !isset($fields['email']))) {
        gateway_json_error('contact name and email are required', 400);
    }
    if (isset($fields['name']) && $fields['name'] === '') gateway_json_error('contact name is required', 400);
    if (isset($fields['email']) && !filter_var($fields['email'], FILTER_VALIDATE_EMAIL)) {
        gateway_json_error('contact email is invalid', 400);
    }
    if (!$fields) gateway_json_error('contact update is empty', 400);
    $mapped = array();
    foreach (array('name' => 'display_name', 'email' => 'email_address', 'phone' => 'phone_number', 'group' => 'group') as $key => $field) {
        if (array_key_exists($key, $fields)) $mapped[$field] = $fields[$key];
    }
    return $mapped;
}}