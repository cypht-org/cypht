<?php

class Hm_Handler_gateway_contacts extends Hm_Handler_Module {
    public function process() {
        $store = gateway_contact_store($this);
        $query = trim((string)($this->request->get['query'] ?? ''));
        if (strlen($query) > 200) gateway_json_error('contact query is too long', 400);
        $contacts = array();
        foreach ($store->getAll() as $id => $contact) {
            $safe = gateway_safe_contact($id, $contact);
            if (!$safe) continue;
            if ($query !== '' && stripos($safe['name'].' '.$safe['email'].' '.$safe['group'], $query) === false) continue;
            $contacts[] = $safe;
        }
        gateway_json_ok($contacts);
    }
}

class Hm_Handler_gateway_contact extends Hm_Handler_Module {
    public function process() {
        $store = gateway_contact_store($this);
        $id = (string)($this->request->get['contact_id'] ?? '');
        $contact = $id !== '' ? gateway_safe_contact($id, $store->get($id)) : null;
        if (!$contact) gateway_json_error('contact not found', 404);
        gateway_json_ok($contact);
    }
}

class Hm_Handler_gateway_contact_create extends Hm_Handler_Module {
    public function process() {
        $sessionSnapshot = gateway_require_durable_user_config($this, 'contacts');
        $store = gateway_contact_store($this);
        $fields = gateway_contact_fields(gateway_payload($this->request), true);
        $fields['id'] = bin2hex(random_bytes(16));
        $fields['source'] = 'local';
        if (!isset($fields['group'])) $fields['group'] = 'Personal Addresses';
        $store->add_contact($fields);
        gateway_commit_durable_user_config($this, 'contacts', $sessionSnapshot);
        gateway_json_ok(gateway_safe_contact($fields['id'], $store->get($fields['id'])));
    }
}

class Hm_Handler_gateway_contact_update extends Hm_Handler_Module {
    public function process() {
        $sessionSnapshot = gateway_require_durable_user_config($this, 'contacts');
        $store = gateway_contact_store($this);
        $payload = gateway_payload($this->request);
        $id = (string)($payload['contact_id'] ?? '');
        if ($id === '' || !gateway_safe_contact($id, $store->get($id))) gateway_json_error('contact not found', 404);
        if (!$store->update_contact($id, gateway_contact_fields($payload))) gateway_json_error('contact update failed', 502);
        gateway_commit_durable_user_config($this, 'contacts', $sessionSnapshot);
        gateway_json_ok(gateway_safe_contact($id, $store->get($id)));
    }
}

class Hm_Handler_gateway_contact_delete extends Hm_Handler_Module {
    public function process() {
        $sessionSnapshot = gateway_require_durable_user_config($this, 'contacts');
        $store = gateway_contact_store($this);
        $payload = gateway_payload($this->request);
        if (($payload['confirm'] ?? false) !== true) gateway_json_error('confirm=true is required', 400);
        $id = (string)($payload['contact_id'] ?? '');
        if ($id === '' || !gateway_safe_contact($id, $store->get($id))) gateway_json_error('contact not found', 404);
        if (!$store->delete($id)) gateway_json_error('contact deletion failed', 502);
        gateway_commit_durable_user_config($this, 'contacts', $sessionSnapshot);
        gateway_json_ok(array('status' => 'deleted'));
    }
}