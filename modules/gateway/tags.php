<?php

if (!hm_exists('gateway_tags_init')) {
function gateway_tags_init($handler) {
    if (!$handler->module_is_supported('tags')) {
        gateway_json_error('tags capability is unavailable', 501);
    }
    if (!class_exists('Hm_Tags', false)) {
        require_once APP_PATH.'modules/tags/hm-tags.php';
    }
    Hm_Tags::init($handler);
}}

if (!hm_exists('gateway_safe_tag')) {
function gateway_safe_tag($id, $tag) {
    if (!is_array($tag)) return null;
    return array(
        'id' => (string)$id,
        'name' => isset($tag['name']) ? (string)$tag['name'] : '',
        'color' => Hm_Tags::sanitizeColor($tag['color'] ?? null),
        'parent' => isset($tag['parent']) && $tag['parent'] !== '' ? (string)$tag['parent'] : null
    );
}}

if (!hm_exists('gateway_tag_fields')) {
function gateway_tag_fields($payload, $create = false) {
    $result = array();
    if (array_key_exists('name', $payload)) {
        if (!is_string($payload['name'])) gateway_json_error('tag name must be text', 400);
        $name = trim($payload['name']);
        if ($name === '' || strlen($name) > 200 || preg_match('/[<>\r\n]/', $name)) {
            gateway_json_error('tag name is invalid', 400);
        }
        $result['name'] = $name;
    }
    if (array_key_exists('color', $payload)) {
        if (!is_string($payload['color'])) gateway_json_error('tag color must be text', 400);
        if (!in_array($payload['color'], Hm_Tags::colorPalette(), true)) {
            gateway_json_error('tag color is not in the supported palette', 400);
        }
        $result['color'] = $payload['color'];
    }
    if ($create && !isset($result['name'])) gateway_json_error('tag name is required', 400);
    if (!$result) gateway_json_error('tag update is empty', 400);
    return $result;
}}

if (!hm_exists('gateway_remove_tag_from_message')) {
function gateway_remove_tag_from_message($tag_id, $account_id, $folder, $uid) {
    $tag = Hm_Tags::get($tag_id);
    if (!is_array($tag)) return false;
    $messages = $tag['server'][$account_id][$folder] ?? array();
    if (!is_array($messages)) return false;
    $tag['server'][$account_id][$folder] = array_values(array_filter($messages, function($message_id) use ($uid) {
        return (string)$message_id !== (string)$uid;
    }));
    return Hm_Tags::edit($tag_id, array('server' => $tag['server']));
}}
if (!hm_exists('gateway_tags_for_message')) {
function gateway_tags_for_message($account_id, $folder, $uid) {
    $found = array();
    foreach (Hm_Tags::getAll() as $id => $tag) {
        $messages = is_array($tag) ? ($tag['server'][$account_id][$folder] ?? array()) : array();
        if (is_array($messages) && in_array((string)$uid, array_map('strval', $messages), true)) {
            $found[$id] = $tag;
        }
    }
    return $found;
}}
if (!hm_exists('gateway_sync_tag_move')) {
function gateway_sync_tag_move($handler, $account_id, $old_folder, $old_uid, $new_folder, $action_result) {
    if (!$handler->module_is_supported('tags')) return 'disabled';
    gateway_tags_init($handler);
    if (!gateway_tags_for_message($account_id, $old_folder, $old_uid)) return 'none';
    $new_uid = gateway_action_new_uid($action_result, $old_uid);
    if ($new_uid === null) return 'pending';

    $synced = gateway_try_durable_user_config_mutation($handler, 'tags', function() use (
        $handler, $account_id, $old_folder, $old_uid, $new_folder, $new_uid
    ) {
        gateway_tags_init($handler);
        $tagged = gateway_tags_for_message($account_id, $old_folder, $old_uid);
        foreach ($tagged as $id => $tag) {
            $tag['server'][$account_id][$old_folder] = array_values(array_filter(
                $tag['server'][$account_id][$old_folder],
                function($uid) use ($old_uid) { return (string)$uid !== (string)$old_uid; }
            ));
            if (!isset($tag['server'][$account_id][$new_folder]) || !is_array($tag['server'][$account_id][$new_folder])) {
                $tag['server'][$account_id][$new_folder] = array();
            }
            if (!in_array($new_uid, array_map('strval', $tag['server'][$account_id][$new_folder]), true)) {
                $tag['server'][$account_id][$new_folder][] = $new_uid;
            }
            if (!Hm_Tags::edit($id, array('server' => $tag['server']))) return false;
        }
        return true;
    });
    return $synced ? 'synced' : 'pending';
}}
if (!hm_exists('gateway_sync_tag_delete')) {
function gateway_sync_tag_delete($handler, $account_id, $folder, $uid, $trash_folder) {
    if (!$handler->module_is_supported('tags')) return 'disabled';
    gateway_tags_init($handler);
    if (!gateway_tags_for_message($account_id, $folder, $uid)) return 'none';
    if ($trash_folder && $trash_folder !== $folder) return 'pending';

    $synced = gateway_try_durable_user_config_mutation($handler, 'tags', function() use (
        $handler, $account_id, $folder, $uid
    ) {
        gateway_tags_init($handler);
        $tagged = gateway_tags_for_message($account_id, $folder, $uid);
        foreach (array_keys($tagged) as $id) {
            if (!gateway_remove_tag_from_message($id, $account_id, $folder, $uid)) return false;
        }
        return true;
    });
    return $synced ? 'synced' : 'pending';
}}