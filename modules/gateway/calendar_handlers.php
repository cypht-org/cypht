<?php

function gateway_calendar_require_module($handler) {
    if (!$handler->module_is_supported('calendar')) {
        gateway_json_error('calendar capability is unavailable', 501);
    }
    if (!class_exists('Hm_Cal_Event_Store', false)) {
        require_once APP_PATH.'modules/calendar/modules.php';
    }
    if (!class_exists('Hm_Cal_Event_Store', false)) {
        gateway_json_error('calendar capability is unavailable', 501);
    }
}

function gateway_calendar_title($value) {
    if (!is_string($value)) gateway_json_error('calendar title is invalid', 400);
    $value = trim($value);
    if ($value === '' || strlen($value) > 500 || preg_match('/[\x00-\x08\x0b\x0c\x0e-\x1f\x7f]/', $value)) {
        gateway_json_error('calendar title is invalid', 400);
    }
    return $value;
}

function gateway_calendar_description($value) {
    if ($value === null) return '';
    if (!is_string($value) || strlen($value) > 20000 || preg_match('/[\x00-\x08\x0b\x0c\x0e-\x1f\x7f]/', $value)) {
        gateway_json_error('calendar description is invalid', 400);
    }
    return $value;
}

function gateway_calendar_timestamp($value, $field) {
    if (is_int($value) || (is_string($value) && ctype_digit($value))) {
        $timestamp = (int)$value;
    } else {
        gateway_json_error($field.' must be a Unix timestamp', 400);
    }
    if ($timestamp < 0 || $timestamp > 4102444800) {
        gateway_json_error($field.' is outside the supported range', 400);
    }
    return $timestamp;
}

function gateway_calendar_repeat($value) {
    if ($value === null || $value === '') return '';
    if (!is_string($value) || !in_array($value, array('day', 'week', 'month', 'year'), true)) {
        gateway_json_error('calendar repeat_interval is invalid', 400);
    }
    return $value;
}

function gateway_calendar_store($handler) {
    gateway_calendar_require_module($handler);
    $store = new Hm_Cal_Event_Store();
    $events = $handler->user_config->get('calendar_events', array());
    if (is_array($events)) $store->load($events);
    return $store;
}

function gateway_calendar_event($event) {
    if (!is_array($event) || !isset($event['id'], $event['date'])) {
        gateway_json_error('calendar event data is invalid', 502);
    }
    return array(
        'id' => (string)$event['id'],
        'title' => isset($event['title']) ? (string)$event['title'] : '',
        'description' => isset($event['description']) ? (string)$event['description'] : '',
        'starts_at' => (int)$event['date'],
        'occurrence_at' => isset($event['ts']) ? (int)$event['ts'] : (int)$event['date'],
        'repeat_interval' => isset($event['repeat_interval']) ? (string)$event['repeat_interval'] : ''
    );
}

function gateway_calendar_payload($payload, $current = array()) {
    $title = array_key_exists('title', $payload)
        ? gateway_calendar_title($payload['title'])
        : gateway_calendar_title($current['title'] ?? '');
    $description = array_key_exists('description', $payload)
        ? gateway_calendar_description($payload['description'])
        : gateway_calendar_description($current['description'] ?? '');
    $starts_at = array_key_exists('starts_at', $payload)
        ? gateway_calendar_timestamp($payload['starts_at'], 'starts_at')
        : gateway_calendar_timestamp($current['starts_at'] ?? 0, 'starts_at');
    $repeat = array_key_exists('repeat_interval', $payload)
        ? gateway_calendar_repeat($payload['repeat_interval'])
        : gateway_calendar_repeat($current['repeat_interval'] ?? '');
    return array(
        'title' => $title,
        'description' => $description,
        'date' => $starts_at,
        'repeat_interval' => $repeat
    );
}

class Hm_Handler_gateway_calendar_events extends Hm_Handler_Module {
    public function process() {
        gateway_calendar_require_module($this);
        $start = isset($this->request->get['start_at'])
            ? gateway_calendar_timestamp($this->request->get['start_at'], 'start_at')
            : time() - 2592000;
        $end = isset($this->request->get['end_at'])
            ? gateway_calendar_timestamp($this->request->get['end_at'], 'end_at')
            : time() + 31536000;
        if ($end <= $start || ($end - $start) > 366 * 86400) {
            gateway_json_error('calendar range is invalid', 400);
        }
        $events = gateway_calendar_store($this)->in_date_range(date('c', $start - 1), date('c', $end));
        $rows = array();
        foreach ($events as $day) {
            foreach ($day as $event) {
                $mapped = gateway_calendar_event($event);
                if ($mapped['occurrence_at'] >= $start && $mapped['occurrence_at'] < $end) $rows[] = $mapped;
            }
        }
        gateway_json_ok($rows);
    }
}

class Hm_Handler_gateway_calendar_create extends Hm_Handler_Module {
    public function process() {
        $snapshot = gateway_require_durable_user_config($this, 'calendar_events');
        $payload = gateway_calendar_payload(gateway_payload($this->request));
        $store = gateway_calendar_store($this);
        if (!$store->add($payload)) gateway_json_error('calendar event creation failed', 502);
        $events = $store->dump();
        $event = end($events);
        $this->user_config->set('calendar_events', $events);
        gateway_commit_durable_user_config($this, 'calendar_events', $snapshot);
        gateway_json_ok(gateway_calendar_event($event));
    }
}

class Hm_Handler_gateway_calendar_delete extends Hm_Handler_Module {
    public function process() {
        $payload = gateway_payload($this->request);
        if (($payload['confirm'] ?? false) !== true) {
            gateway_json_error('confirm=true is required', 400);
        }
        $event_id = (string)($payload['event_id'] ?? '');
        if ($event_id === '') gateway_json_error('event_id is required', 400);
        $snapshot = gateway_require_durable_user_config($this, 'calendar_events');
        $store = gateway_calendar_store($this);
        $matches = 0;
        foreach ($store->dump() as $event) {
            if ((string)($event['id'] ?? '') === $event_id) $matches++;
        }
        if ($matches === 0) gateway_json_error('calendar event not found', 404);
        if ($matches > 1) gateway_json_error('calendar event identity is ambiguous', 409);
        $store->delete($event_id);
        $this->user_config->set('calendar_events', $store->dump());
        gateway_commit_durable_user_config($this, 'calendar_events', $snapshot);
        gateway_json_ok(array('status' => 'deleted'));
    }
}
