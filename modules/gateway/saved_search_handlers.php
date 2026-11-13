<?php

function gateway_require_saved_search_module($handler) {
    if (!$handler->module_is_supported('saved_searches')) {
        gateway_json_error('saved searches capability is unavailable', 501);
    }
}

function gateway_saved_search_repo($handler) {
    gateway_require_saved_search_module($handler);    if (!class_exists('Hm_Saved_Searches', false)) {
        require_once APP_PATH.'modules/saved_searches/modules.php';
    }
    if (!class_exists('Hm_Saved_Searches', false)) {
        gateway_json_error('saved searches capability is unavailable', 501);
    }
    $data = $handler->user_config->get('saved_searches', array());
    return new Hm_Saved_Searches(is_array($data) ? $data : array());
}

function gateway_saved_search_name($value) {
    if (!is_string($value)) gateway_json_error('saved search name is invalid', 400);
    $name = trim($value);
    if ($name === '' || strlen($name) > 100 || preg_match('/[\x00-\x1f\x7f]/', $name)) {
        gateway_json_error('saved search name is invalid', 400);
    }
    return $name;
}

function gateway_saved_search_validate_advanced($handler, $data) {
    $encoded = is_array($data) ? json_encode($data) : false;
    if (!is_string($encoded) || strlen($encoded) > 65536) {
        gateway_json_error('advanced saved search data is invalid', 400);
    }
    foreach (array('terms', 'targets', 'sources', 'times', 'other') as $key) {
        if (!isset($data[$key]) || !is_array($data[$key])) {
            gateway_json_error('advanced saved search data is incomplete', 400);
        }
    }
    if (count($data['terms']) > 10 || count($data['targets']) > 10 ||
        count($data['sources']) > 50 || count($data['times']) > 10) {
        gateway_json_error('advanced saved search data exceeds limits', 413);
    }
    if (!$data['sources']) gateway_json_error('advanced saved search requires a source', 400);
    if (!$handler->module_is_supported('imap') || !class_exists('Hm_IMAP_List', false)) {
        gateway_json_error('advanced saved search capability is unavailable', 501);
    }
    $account_ids = array_map('strval', array_keys(Hm_IMAP_List::dump()));
    foreach ($data['sources'] as &$source) {
        if (!is_array($source) || !isset($source['source']) || !is_string($source['source'])) {
            gateway_json_error('advanced saved search source is invalid', 400);
        }
        $valid = false;
        foreach ($account_ids as $account_id) {
            $prefix = 'imap_'.$account_id;
            if ($source['source'] === $prefix || $source['source'] === $prefix.'_') {
                $valid = true;
                break;
            }
            if (strpos($source['source'], $prefix.'_') === 0) {
                $hex = substr($source['source'], strlen($prefix) + 1);
                if ($hex !== '' && strlen($hex) % 2 === 0 && ctype_xdigit($hex)) {
                    $valid = true;
                    break;
                }
            }
        }
        if (!$valid) gateway_json_error('advanced saved search source is invalid', 400);
    }
    unset($source);
    return $data;
}

function gateway_saved_search_value($name, $value) {
    if (!is_array($value)) gateway_json_error('saved search data is invalid', 502);
    if (isset($value['type']) && $value['type'] === 'advanced') {
        if (!isset($value['data']) || !is_array($value['data'])) {
            gateway_json_error('advanced saved search data is invalid', 502);
        }
        return array(
            'name' => $name,
            'type' => 'advanced',
            'advanced' => $value['data']
        );
    }
    if (count($value) < 3) gateway_json_error('simple saved search data is invalid', 502);
    return array(
        'name' => $name,
        'type' => 'simple',
        'query' => (string)$value[0],
        'since' => (string)$value[1],
        'field' => (string)$value[2]
    );
}

function gateway_saved_search_mutation($handler, $payload, $current = null, $new_name = null) {
    $name = $new_name !== null ? $new_name : ($current['name'] ?? '');
    $kind = $current['type'] ?? ($payload['type'] ?? '');
    if ($kind === 'simple') {
        $query = array_key_exists('query', $payload) ? $payload['query'] : ($current['query'] ?? '');
        $since = array_key_exists('since', $payload) ? $payload['since'] : ($current['since'] ?? DEFAULT_SEARCH_SINCE);
        $field = array_key_exists('field', $payload) ? $payload['field'] : ($current['field'] ?? DEFAULT_SEARCH_FLD);
        $allowed = array('TEXT', 'BODY', 'FROM', 'SUBJECT', 'TO', 'CC');
        if (!is_string($query) || trim($query) === '' || strlen($query) > 1000 ||
            !is_string($since) || strlen($since) > 100 || !is_string($field) || !in_array($field, $allowed, true)) {
            gateway_json_error('simple saved search data is invalid', 400);
        }
        return array($query, $since, $field, $name);
    }
    if ($kind === 'advanced') {
        $advanced = array_key_exists('advanced', $payload)
            ? gateway_saved_search_validate_advanced($handler, $payload['advanced'])
            : ($current['advanced'] ?? null);
        if (!is_array($advanced)) gateway_json_error('advanced saved search data is invalid', 400);
        $advanced['name'] = $name;
        return array('type' => 'advanced', 'data' => $advanced, 'name' => $name);
    }
    gateway_json_error('saved search type is invalid', 400);
}

class Hm_Handler_gateway_saved_searches extends Hm_Handler_Module {
    public function process() {
        $searches = gateway_saved_search_repo($this);
        $rows = array();
        foreach ($searches->dump() as $name => $value) {
            $rows[] = gateway_saved_search_value((string)$name, $value);
        }
        gateway_json_ok($rows);
    }
}

class Hm_Handler_gateway_saved_search_create extends Hm_Handler_Module {
    public function process() {
        gateway_require_saved_search_module($this);
        $payload = gateway_payload($this->request);
        $name = gateway_saved_search_name($payload['name'] ?? null);
        if (!isset($payload['type']) || !in_array($payload['type'], array('simple', 'advanced'), true)) {
            gateway_json_error('saved search type is invalid', 400);
        }
        $value = gateway_saved_search_mutation($this, $payload, null, $name);
        $snapshot = gateway_require_durable_user_config($this, 'saved_searches');
        $searches = gateway_saved_search_repo($this);
        if (array_key_exists($name, $searches->dump())) gateway_json_error('saved search name already exists', 409);
        if (!$searches->add($name, $value)) gateway_json_error('saved search name already exists', 409);
        $this->user_config->set('saved_searches', $searches->dump());
        gateway_commit_durable_user_config($this, 'saved_searches', $snapshot);
        $fresh = new Hm_Saved_Searches($this->user_config->get('saved_searches', array()));
        gateway_json_ok(gateway_saved_search_value($name, $fresh->get($name)));
    }
}
class Hm_Handler_gateway_saved_search_update extends Hm_Handler_Module {
    public function process() {
        gateway_require_saved_search_module($this);
        $payload = gateway_payload($this->request);
        $old_name = gateway_saved_search_name($payload['search_name'] ?? null);
        $new_name = array_key_exists('name', $payload)
            ? gateway_saved_search_name($payload['name']) : $old_name;
        $snapshot = gateway_require_durable_user_config($this, 'saved_searches');
        $searches = gateway_saved_search_repo($this);
        $current_raw = $searches->get($old_name, null);
        if (!is_array($current_raw)) gateway_json_error('saved search not found', 404);
        $current = gateway_saved_search_value($old_name, $current_raw);
        if ($new_name !== $old_name && array_key_exists($new_name, $searches->dump())) {
            gateway_json_error('saved search name already exists', 409);
        }
        $value = gateway_saved_search_mutation($this, $payload, $current, $new_name);
        if ($new_name !== $old_name) {
            if (!$searches->delete($old_name)) gateway_json_error('saved search not found', 404);
            if (!$searches->add($new_name, $value)) {
                $searches->add($old_name, $current_raw);
                gateway_json_error('saved search name already exists', 409);
            }
        } elseif (!$searches->update($old_name, $value)) {
            gateway_json_error('saved search update failed', 502);
        }
        $this->user_config->set('saved_searches', $searches->dump());
        gateway_commit_durable_user_config($this, 'saved_searches', $snapshot);
        $fresh = new Hm_Saved_Searches($this->user_config->get('saved_searches', array()));
        gateway_json_ok(gateway_saved_search_value($new_name, $fresh->get($new_name)));
    }
}
class Hm_Handler_gateway_saved_search_delete extends Hm_Handler_Module {
    public function process() {
        gateway_require_saved_search_module($this);
        $payload = gateway_payload($this->request);
        if (($payload['confirm'] ?? false) !== true) gateway_json_error('confirm=true is required', 400);
        $name = gateway_saved_search_name($payload['search_name'] ?? null);
        $snapshot = gateway_require_durable_user_config($this, 'saved_searches');
        $searches = gateway_saved_search_repo($this);
        if (!is_array($searches->get($name, null))) gateway_json_error('saved search not found', 404);
        if (!$searches->delete($name)) gateway_json_error('saved search deletion failed', 502);
        $this->user_config->set('saved_searches', $searches->dump());
        gateway_commit_durable_user_config($this, 'saved_searches', $snapshot);
        gateway_json_ok(array('status' => 'deleted'));
    }
}
