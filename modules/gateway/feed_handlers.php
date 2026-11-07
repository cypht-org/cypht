<?php

function gateway_feed_require_module($handler) {
    if (!$handler->module_is_supported('feeds')) {
        gateway_json_error('feeds capability is unavailable', 501);
    }
    if (!class_exists('Hm_Feed_List', false)) {
        require_once APP_PATH.'modules/feeds/modules.php';
    }
    if (!class_exists('Hm_Feed_List', false)) {
        gateway_json_error('feeds capability is unavailable', 501);
    }
    Hm_Feed_List::init($handler->user_config, $handler->session);
}

function gateway_safe_feed($id, $feed) {
    if (!is_array($feed)) gateway_json_error('feed data is invalid', 502);
    $url = isset($feed['url']) ? trim((string)$feed['url']) : '';
    $name = isset($feed['name']) ? trim((string)$feed['name']) : '';
    if ($url === '' || $name === '') gateway_json_error('feed data is incomplete', 502);
    return array(
        'id' => (string)$id,
        'name' => $name,
        'url' => $url
    );
}

class Hm_Handler_gateway_feeds extends Hm_Handler_Module {
    public function process() {
        gateway_feed_require_module($this);
        $rows = array();
        foreach (Hm_Feed_List::dump() as $id => $feed) {
            $rows[] = gateway_safe_feed($id, $feed);
        }
        gateway_json_ok($rows);
    }
}

class Hm_Handler_gateway_feed extends Hm_Handler_Module {
    public function process() {
        gateway_feed_require_module($this);
        $id = isset($this->request->get['feed_id']) ? (string)$this->request->get['feed_id'] : '';
        $feed = $id === '' ? false : Hm_Feed_List::dump($id);
        if (!is_array($feed)) gateway_json_error('feed not found', 404);
        gateway_json_ok(gateway_safe_feed($id, $feed));
    }
}
