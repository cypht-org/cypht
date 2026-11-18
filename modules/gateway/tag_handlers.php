<?php

class Hm_Handler_gateway_tags extends Hm_Handler_Module {
    public function process() {
        gateway_tags_init($this);
        $rows = array();
        foreach (Hm_Tags::getAll() as $id => $tag) {
            $safe = gateway_safe_tag($id, $tag);
            if ($safe) $rows[] = $safe;
        }
        gateway_json_ok($rows);
    }
}

class Hm_Handler_gateway_tag_create extends Hm_Handler_Module {
    public function process() {
        $sessionSnapshot = gateway_require_durable_user_config($this, 'tags');
        gateway_tags_init($this);
        $fields = gateway_tag_fields(gateway_payload($this->request), true);
        $fields['parent'] = null;
        $fields['server'] = array();
        if (!isset($fields['color'])) $fields['color'] = Hm_Tags::defaultColor();
        $id = Hm_Tags::add($fields);
        gateway_commit_durable_user_config($this, 'tags', $sessionSnapshot);
        gateway_json_ok(gateway_safe_tag($id, Hm_Tags::get($id)));
    }
}

class Hm_Handler_gateway_tag_update extends Hm_Handler_Module {
    public function process() {
        $sessionSnapshot = gateway_require_durable_user_config($this, 'tags');
        gateway_tags_init($this);
        $payload = gateway_payload($this->request);
        $id = (string)($payload['tag_id'] ?? '');
        if ($id === '' || !Hm_Tags::get($id)) gateway_json_error('tag not found', 404);
        if (!Hm_Tags::edit($id, gateway_tag_fields($payload))) gateway_json_error('tag update failed', 502);
        gateway_commit_durable_user_config($this, 'tags', $sessionSnapshot);
        gateway_json_ok(gateway_safe_tag($id, Hm_Tags::get($id)));
    }
}

class Hm_Handler_gateway_tag_delete extends Hm_Handler_Module {
    public function process() {
        $sessionSnapshot = gateway_require_durable_user_config($this, 'tags');
        gateway_tags_init($this);
        $payload = gateway_payload($this->request);
        if (($payload['confirm'] ?? false) !== true) gateway_json_error('confirm=true is required', 400);
        $id = (string)($payload['tag_id'] ?? '');
        if ($id === '' || !Hm_Tags::get($id)) gateway_json_error('tag not found', 404);
        if (!Hm_Tags::del($id)) gateway_json_error('tag deletion failed', 502);
        gateway_commit_durable_user_config($this, 'tags', $sessionSnapshot);
        gateway_json_ok(array('status' => 'deleted'));
    }
}

class Hm_Handler_gateway_message_tag_add extends Hm_Handler_Module {
    public function process() {
        $sessionSnapshot = gateway_require_durable_user_config($this, 'tags');
        gateway_tags_init($this);
        $payload = gateway_payload($this->request);
        $id = (string)($payload['tag_id'] ?? '');
        $account = (string)($payload['account_id'] ?? '');
        $folder = (string)($payload['folder'] ?? '');
        $uid = (string)($payload['uid'] ?? '');
        if ($id === '' || !Hm_Tags::get($id)) gateway_json_error('tag not found', 404);
        if (!$this->module_is_supported('imap')) gateway_json_error('mail account capability is unavailable', 501);
        if ($account === '' || !Hm_IMAP_List::dump($account) || $folder === '' || $uid === '') gateway_json_error('message location is invalid', 404);
        if (!Hm_Tags::addMessage($id, $account, $folder, $uid)) gateway_json_error('tag association failed', 502);
        gateway_commit_durable_user_config($this, 'tags', $sessionSnapshot);
        gateway_json_ok(array('status' => 'added'));
    }
}

class Hm_Handler_gateway_message_tag_remove extends Hm_Handler_Module {
    public function process() {
        $sessionSnapshot = gateway_require_durable_user_config($this, 'tags');
        gateway_tags_init($this);
        $payload = gateway_payload($this->request);
        if (($payload['confirm'] ?? false) !== true) gateway_json_error('confirm=true is required', 400);
        $id = (string)($payload['tag_id'] ?? '');
        $account = (string)($payload['account_id'] ?? '');
        $folder = (string)($payload['folder'] ?? '');
        $uid = (string)($payload['uid'] ?? '');
        if ($id === '' || !Hm_Tags::get($id)) gateway_json_error('tag not found', 404);
        if (!$this->module_is_supported('imap')) gateway_json_error('mail account capability is unavailable', 501);
        if ($account === '' || !Hm_IMAP_List::dump($account) || $folder === '' || $uid === '') gateway_json_error('message location is invalid', 404);
        if (!gateway_remove_tag_from_message($id, $account, $folder, $uid)) gateway_json_error('tag removal failed', 502);
        gateway_commit_durable_user_config($this, 'tags', $sessionSnapshot);
        gateway_json_ok(array('status' => 'removed'));
    }
}