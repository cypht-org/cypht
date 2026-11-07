<?php

if (!class_exists('Hm_Gateway_Response', false)) {
class Hm_Gateway_Response {
    private static $session = null;
    private static $pending_user_config = null;
    private static $pending_session_data = null;
    private static $shutdown_registered = false;

    public static function bind_session($session) {
        self::$session = $session;
    }

    public static function bind_user_config_write($user_config, $session, $snapshot) {
        self::$pending_user_config = $user_config;
        self::$pending_session_data = $snapshot;
        self::$session = $session;
        if (!self::$shutdown_registered) {
            register_shutdown_function(array(__CLASS__, 'abort_user_config_write'));
            self::$shutdown_registered = true;
        }
    }

    public static function clear_user_config_write() {
        self::$pending_user_config = null;
        self::$pending_session_data = null;
    }

    public static function abort_user_config_write() {
        if (!self::$pending_user_config) return;
        try {
            if (method_exists(self::$pending_user_config, 'gateway_abort_write')) {
                self::$pending_user_config->gateway_abort_write();
            }
            if (self::$session && is_array(self::$pending_session_data)) {
                self::$session->set('user_data', self::$pending_session_data);
            }
        } catch (Throwable $error) {
            // Keep the public failure bounded. PHP will release remaining locks at request end.
        }
        self::clear_user_config_write();
    }

    public static function close_session() {
        self::abort_user_config_write();
        if (self::$session && self::$session->is_active()) {
            self::$session->end();
        }
    }
}}
if (!hm_exists('gateway_bridge_version')) {
function gateway_bridge_version() {
    $path = __DIR__.'/VERSION';
    if (!is_readable($path)) return 'unknown';
    return trim((string)file_get_contents($path));
}}

if (!hm_exists('gateway_json_ok')) {
function gateway_json_ok($data) {
    Hm_Gateway_Response::close_session();
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode(array('ok' => true, 'data' => $data), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    Hm_Functions::cease();
}}

if (!hm_exists('gateway_json_error')) {
function gateway_json_error($message, $status = 400) {
    Hm_Gateway_Response::close_session();
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode(array('ok' => false, 'error' => $message), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    Hm_Functions::cease();
}}

if (!hm_exists('gateway_payload')) {
function gateway_payload($request) {
    $raw = isset($request->post['payload']) ? (string)$request->post['payload'] : '';
    if ($raw === '') gateway_json_error('payload is required', 400);
    $payload = json_decode($raw, true);
    if (!is_array($payload)) gateway_json_error('payload must be valid JSON', 400);
    return $payload;
}}

if (!hm_exists('gateway_user_config_storage_supported')) {
function gateway_user_config_storage_supported($handler) {
    if (!isset($handler->user_config) || !is_object($handler->user_config) ||
        !method_exists($handler->user_config, 'gateway_storage_is_safe')) return false;
    try {
        return $handler->user_config->gateway_storage_is_safe() === true;
    } catch (Throwable $error) {
        return false;
    }
}}

if (!hm_exists('gateway_try_durable_user_config_mutation')) {
function gateway_try_durable_user_config_mutation($handler, $section, $mutation) {
    if (!gateway_user_config_storage_supported($handler) ||
        !method_exists($handler->user_config, 'gateway_begin_write') ||
        !method_exists($handler->user_config, 'gateway_commit_write') || !is_callable($mutation)) {
        return false;
    }
    $session = isset($handler->session) ? $handler->session : null;
    $username = $session && method_exists($session, 'get')
        ? (string)$session->get('username', '') : '';
    $key = isset($handler->request->server['HTTP_X_CYPHT_GATEWAY_CONFIG_KEY'])
        ? (string)$handler->request->server['HTTP_X_CYPHT_GATEWAY_CONFIG_KEY'] : '';
    if (!$username || !$key || strlen($key) > 4096 || !$session ||
        !method_exists($session, 'auth')) return false;
    try {
        if (!$session->auth($username, $key)) return false;
        $snapshot = $session->get('user_data', array());
        if (!is_array($snapshot) ||
            !$handler->user_config->gateway_begin_write($username, $key, $section, $snapshot)) return false;
        Hm_Gateway_Response::bind_user_config_write($handler->user_config, $session, $snapshot);
        if ($mutation() !== true) {
            Hm_Gateway_Response::abort_user_config_write();
            return false;
        }
        $values = $handler->user_config->gateway_commit_write($section);
        if (!is_array($values)) {
            Hm_Gateway_Response::abort_user_config_write();
            return false;
        }
        $snapshot[$section] = $values;
        $session->set('user_data', $snapshot);
        Hm_Gateway_Response::clear_user_config_write();
        return true;
    } catch (Throwable $error) {
        Hm_Gateway_Response::abort_user_config_write();
        return false;
    }
}}

if (!hm_exists('gateway_require_durable_user_config')) {
function gateway_require_durable_user_config($handler, $section) {
    $supported = isset($handler->user_config) && is_object($handler->user_config) &&
        method_exists($handler->user_config, 'gateway_storage_is_safe') &&
        method_exists($handler->user_config, 'gateway_begin_write');
    if ($supported) {
        try {
            $supported = $handler->user_config->gateway_storage_is_safe() === true;
        } catch (Throwable $error) {
            $supported = false;
        }
    }
    if (!$supported) gateway_json_error('durable user-config writes are unavailable', 501);
    $session = isset($handler->session) ? $handler->session : null;
    $username = $session && method_exists($session, 'get')
        ? (string)$session->get('username', '') : '';
    $key = isset($handler->request->server['HTTP_X_CYPHT_GATEWAY_CONFIG_KEY'])
        ? (string)$handler->request->server['HTTP_X_CYPHT_GATEWAY_CONFIG_KEY'] : '';
    if (!$username || !$key || strlen($key) > 4096 || !$session ||
        !method_exists($session, 'auth') || !$session->auth($username, $key)) {
        gateway_json_error('user-config authentication failed', 403);
    }
    $snapshot = $session->get('user_data', array());
    if (!is_array($snapshot)) $snapshot = array();
    try {
        if (!$handler->user_config->gateway_begin_write($username, $key, $section, $snapshot)) {
            gateway_json_error('durable user-config writes are unavailable', 501);
        }
    } catch (Throwable $error) {
        if (class_exists('Gateway_User_Config_Conflict', false) &&
            $error instanceof Gateway_User_Config_Conflict) {
            gateway_json_error('user-config write conflict', 409);
        }
        gateway_json_error('durable user-config writes are unavailable', 501);
    }
    Hm_Gateway_Response::bind_user_config_write($handler->user_config, $session, $snapshot);
    return $snapshot;
}}

if (!hm_exists('gateway_commit_durable_user_config')) {
function gateway_commit_durable_user_config($handler, $section, $snapshot) {
    try {
        $values = $handler->user_config->gateway_commit_write($section);
        if (!is_array($values)) {
            throw new RuntimeException('user-config commit verification failed');
        }
        $snapshot[$section] = $values;
        $handler->session->set('user_data', $snapshot);
        Hm_Gateway_Response::clear_user_config_write();
        return true;
    } catch (Throwable $error) {
        Hm_Gateway_Response::abort_user_config_write();
        if (class_exists('Gateway_User_Config_Conflict', false) &&
            $error instanceof Gateway_User_Config_Conflict) {
            gateway_json_error('user-config write conflict', 409);
        }
        gateway_json_error('durable user-config write failed', 502);
    }
}}

if (!hm_exists('gateway_safe_account')) {
function gateway_safe_account($id, $server, $can_send = false) {
    $protocol = strtolower(isset($server['type']) ? $server['type'] : 'imap');
    $email = null;
    if (isset($server['user']) && filter_var($server['user'], FILTER_VALIDATE_EMAIL)) {
        $email = $server['user'];
    }
    return array(
        'id' => (string)$id,
        'name' => isset($server['name']) ? (string)$server['name'] : sprintf('%s %s', strtoupper($protocol), $id),
        'email' => $email,
        'protocol' => $protocol,
        'server' => isset($server['server']) ? (string)$server['server'] : null,
        'can_send' => (bool)$can_send
    );
}}

if (!hm_exists('gateway_action_succeeded')) {
function gateway_action_succeeded($result) {
    if (is_array($result)) return !empty($result['status']);
    return $result === true;
}}

if (!hm_exists('gateway_action_new_uid')) {
function gateway_action_new_uid($result, $old_uid) {
    if (!is_array($result) || !isset($result['responses']) || !is_array($result['responses'])) return null;
    foreach ($result['responses'] as $response) {
        if (!is_array($response) || !isset($response['oldUid'], $response['newUid'])) continue;
        $old = $response['oldUid'];
        $new = $response['newUid'];
        if ((is_string($old) || is_int($old)) && (string)$old === (string)$old_uid &&
            (is_string($new) || is_int($new)) && (string)$new !== '') {
            return (string)$new;
        }
    }
    return null;
}}
if (!hm_exists('gateway_folder_role')) {
function gateway_folder_role($name, $details) {
    if (isset($details['special']) && is_string($details['special']) && $details['special']) {
        return strtolower(ltrim($details['special'], '\\'));
    }
    $lower = strtolower((string)$name);
    foreach (array('inbox', 'sent', 'drafts', 'trash', 'junk', 'archive', 'scheduled') as $role) {
        if ($lower === $role || substr($lower, -strlen('/'.$role)) === '/'.$role) return $role;
    }
    return null;
}}

if (!hm_exists('gateway_normalize_message')) {
function gateway_normalize_message($account_id, $folder, $message) {
    return array(
        'uid' => isset($message['uid']) ? (string)$message['uid'] : (isset($message['id']) ? (string)$message['id'] : ''),
        'account_id' => (string)$account_id,
        'folder' => (string)$folder,
        'subject' => isset($message['subject']) ? (string)$message['subject'] : '',
        'from' => isset($message['from']) ? $message['from'] : array(),
        'to' => isset($message['to']) ? $message['to'] : array(),
        'date' => isset($message['date']) ? (string)$message['date'] : null,
        'timestamp' => isset($message['timestamp']) && is_numeric($message['timestamp']) ? (int)$message['timestamp'] : null,
        'flags' => isset($message['flags']) ? (string)$message['flags'] : '',
        'content_type' => isset($message['content-type']) ? (string)$message['content-type'] : (isset($message['content_type']) ? (string)$message['content_type'] : null),
        'preview' => isset($message['preview']) ? (string)$message['preview'] : null
    );
}}

if (!hm_exists('gateway_extract_attachments')) {
function gateway_extract_attachments($structure) {
    $decoded = json_decode(json_encode($structure), true);
    $result = array();
    gateway_walk_structure($decoded, $result);
    $unique = array();
    foreach ($result as $item) {
        $key = $item['part'].'|'.($item['filename'] ?: '').'|'.($item['content_type'] ?: '');
        $unique[$key] = $item;
    }
    return array_values($unique);
}}

if (!hm_exists('gateway_walk_structure')) {
function gateway_walk_structure($node, &$result, $current_part = "") {
    if (!is_array($node)) return;
    $filename = null;
    if (isset($node["attributes"]) && is_array($node["attributes"])) {
        $filename = $node["attributes"]["filename"] ?? ($node["attributes"]["name"] ?? null);
    }
    if (!$filename && isset($node["disposition"]) && is_array($node["disposition"]) && isset($node["disposition"]["attachment"])) {
        $att = $node["disposition"]["attachment"];
        if (is_array($att) && count($att) >= 2) $filename = $att[1];
    }
    if (!$filename && isset($node["file_attributes"]) && is_array($node["file_attributes"]) && isset($node["file_attributes"]["attachment"])) {
        $att = $node["file_attributes"]["attachment"];
        if (is_array($att) && count($att) >= 2) $filename = $att[1];
    }
    $type = $node["type"] ?? "";
    $subtype = $node["subtype"] ?? "";
    $mime_type = $type && $subtype ? "$type/$subtype" : ($type ?: "application/octet-stream");
    $is_attachment = ($filename !== null && $filename !== "") || strtolower($type) === "image" || isset($node["disposition"]["attachment"]) || isset($node["file_attributes"]["attachment"]);
    if ($is_attachment && $current_part !== "" && $current_part !== "0" && $current_part !== "0.1") {
        $result[] = [
            "part" => (string)$current_part,
            "filename" => $filename ?: "attachment-" . $current_part,
            "content_type" => $mime_type,
            "size" => isset($node["size"]) ? (int)$node["size"] : null,
            "inline" => false
        ];
    }
    foreach ($node as $k => $v) {
        if (is_array($v)) {
            $next_part = (is_string($k) && preg_match("/^[0-9.]+$/", $k)) ? $k : $current_part;
            gateway_walk_structure($v, $result, $next_part);
        }
    }
}}

if (!hm_exists('gateway_header_text')) {
function gateway_header_text($value) {
    return trim(str_replace(array("\r", "\n"), ' ', (string)$value));
}}

if (!hm_exists('gateway_address_list')) {
function gateway_address_list($value) {
    if (is_string($value)) return gateway_header_text($value);
    if (!is_array($value)) return '';
    $result = array();
    foreach ($value as $item) {
        if (is_string($item)) {
            $candidate = gateway_header_text($item);
            if ($candidate !== '') $result[] = $candidate;
            continue;
        }
        if (!is_array($item)) continue;
        $email = isset($item['email']) ? gateway_header_text($item['email']) : '';
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) continue;
        $name = isset($item['name']) ? gateway_header_text($item['name']) : '';
        $result[] = $name !== '' ? sprintf('"%s" <%s>', str_replace('"', '', $name), $email) : $email;
    }
    return implode(', ', $result);
}}

if (!hm_exists('gateway_profile_imap_id')) {
function gateway_profile_imap_id($profile) {
    if (isset($profile['imap_id']) && $profile['imap_id'] !== '') return (string)$profile['imap_id'];
    if (isset($profile['user']) && isset($profile['server'])) {
        $server = Hm_IMAP_List::fetch($profile['user'], $profile['server']);
        if (is_array($server) && isset($server['id'])) return (string)$server['id'];
    }
    return null;
}}

if (!hm_exists('gateway_profiles')) {
function gateway_profiles($handler) {
    Hm_SMTP_List::init($handler->user_config, $handler->session);
    Hm_Profiles::init($handler);
    $profiles = Hm_Profiles::getAll();
    return is_array($profiles) ? $profiles : array();
}}

if (!hm_exists('gateway_safe_profiles')) {
function gateway_safe_profiles($handler) {
    $result = array();
    foreach (gateway_profiles($handler) as $id => $profile) {
        if (!is_array($profile)) continue;
        $internal_id = isset($profile['id']) ? (string)$profile['id'] : (string)$id;
        $result[] = array(
            'id' => $internal_id,
            'name' => isset($profile['name']) ? (string)$profile['name'] : 'Profile',
            'address' => isset($profile['address']) ? (string)$profile['address'] : '',
            'reply_to' => isset($profile['replyto']) ? (string)$profile['replyto'] : '',
            'signature' => isset($profile['sig']) ? (string)$profile['sig'] : '',
            'account_id' => gateway_profile_imap_id($profile),
            'default' => !empty($profile['default'])
        );
    }
    return $result;
}}

if (!hm_exists('gateway_pick_profile')) {
function gateway_pick_profile($handler, $profile_id) {
    $profiles = gateway_profiles($handler);
    if ($profile_id !== null && $profile_id !== '') {
        foreach ($profiles as $id => $profile) {
            $candidate = isset($profile['id']) ? (string)$profile['id'] : (string)$id;
            if (hash_equals($candidate, (string)$profile_id)) { $profile['id'] = $candidate; return $profile; }
        }
        gateway_json_error('unknown profile', 404);
    }
    foreach ($profiles as $id => $profile) { if (!empty($profile['default'])) { $profile['id'] = isset($profile['id']) ? $profile['id'] : (string)$id; return $profile; } }
    $id = array_key_first($profiles);
    $first = reset($profiles);
    if (is_array($first)) { $first['id'] = isset($first['id']) ? $first['id'] : (string)$id; return $first; }
    gateway_json_error('no sending profile configured', 409);
}}

if (!hm_exists('gateway_uploads')) {
function gateway_uploads($handler) {
    $uploads = $handler->session->get('gateway_uploads', array());
    return is_array($uploads) ? $uploads : array();
}}

if (!hm_exists('gateway_resolve_uploads')) {
function gateway_resolve_uploads($handler, $ids) {
    if (!is_array($ids)) return array();
    $known = gateway_uploads($handler);
    $result = array();
    foreach ($ids as $id) {
        $id = (string)$id;
        if (!array_key_exists($id, $known) || !is_array($known[$id])) gateway_json_error('unknown or expired upload', 404);
        $filename = isset($known[$id]['filename']) ? (string)$known[$id]['filename'] : '';
        if ($filename === '' || !is_file($filename) || !is_readable($filename)) {
            unset($known[$id]);
            $handler->session->set('gateway_uploads', $known);
            gateway_json_error('unknown or expired upload', 404);
        }
        $result[] = $known[$id];
    }
    return $result;
}}

if (!hm_exists('gateway_remove_uploads')) {
function gateway_remove_uploads($handler, $ids) {
    if (!is_array($ids) || !$ids) return;
    $uploads = gateway_uploads($handler);
    foreach ($ids as $id) {
        $id = (string)$id;
        if (!isset($uploads[$id])) continue;
        if (isset($uploads[$id]['filename'])) @unlink($uploads[$id]['filename']);
        unset($uploads[$id]);
    }
    $handler->session->set('gateway_uploads', $uploads);
}}

if (!hm_exists('gateway_validate_schedule')) {
function gateway_validate_schedule($value) {
    $value = gateway_header_text($value);
    if ($value === '') return '';
    if (strlen($value) > 100) gateway_json_error('schedule_at is too long', 400);
    try {
        $date = new DateTime($value);
        $now = new DateTime('now', $date->getTimezone());
        if ($date <= $now) gateway_json_error('schedule_at must be in the future', 400);
        return $date->format(DateTime::ATOM);
    } catch (Exception $e) {
        gateway_json_error('schedule_at must be a valid date/time', 400);
    }
}}

if (!hm_exists('gateway_build_mime')) {
function gateway_build_mime($handler, $payload, $profile, $schedule = '') {
    $to = gateway_address_list($payload['to'] ?? array());
    $cc = gateway_address_list($payload['cc'] ?? array());
    $bcc = gateway_address_list($payload['bcc'] ?? array());
    $subject = gateway_header_text($payload['subject'] ?? '');
    $text = isset($payload['body']['text']) ? (string)$payload['body']['text'] : '';
    $html = isset($payload['body']['html']) ? (string)$payload['body']['html'] : '';
    $body = $html !== '' ? $html : $text;
    $body_type = $html !== '';
    $from = isset($profile['address']) && $profile['address'] !== '' ? $profile['address'] : '';
    $reply_to = isset($profile['replyto']) ? $profile['replyto'] : '';
    $from_name = isset($profile['name']) ? $profile['name'] : '';
    $in_reply_to = gateway_header_text($payload['in_reply_to'] ?? '');
    $delivery_receipt = !empty($payload['delivery_receipt']);
    list($from, $reply_to) = outbound_address_check($handler, $from, $reply_to);
    $profile_id = isset($profile['id']) ? (string)$profile['id'] : '';
    $mime = new Hm_MIME_Msg($to, $subject, $body, $from, $body_type, $cc, $bcc, $in_reply_to, $from_name, $reply_to, $delivery_receipt, $schedule, $profile_id);
    $upload_ids = isset($payload['attachment_ids']) && is_array($payload['attachment_ids']) ? $payload['attachment_ids'] : array();
    $mime->add_attachments(gateway_resolve_uploads($handler, $upload_ids));
    return array($mime, $from, $upload_ids);
}}

if (!hm_exists('gateway_safe_stored_uid')) {
function gateway_safe_stored_uid($uid) {
    if (is_int($uid) || is_string($uid)) {
        return (string)$uid !== '' ? (string)$uid : null;
    }
    return null;
}}
if (!hm_exists('gateway_send_now')) {
function gateway_send_now($handler, $payload, $profile) {
    $smtp_id = isset($profile['smtp_id']) ? (string)$profile['smtp_id'] : '';
    if ($smtp_id === '') gateway_json_error('profile has no SMTP server', 409);
    $smtp_details = Hm_SMTP_List::dump($smtp_id, true);
    if (!$smtp_details) gateway_json_error('SMTP server is unavailable', 409);
    smtp_refresh_oauth2_token_on_send($smtp_details, $handler, $smtp_id);
    $smtp = Hm_SMTP_List::connect($smtp_id, false);
    if (!$smtp || !$smtp->authed()) gateway_json_error('SMTP authentication failed', 502);

    list($mime, $from, $upload_ids) = gateway_build_mime($handler, $payload, $profile, '');
    $recipients = $mime->get_recipient_addresses();
    if (!$recipients) gateway_json_error('no valid recipients found', 400);
    $message = $mime->get_mime_msg();
    $error = $smtp->send_message($from, $recipients, $message, !empty($payload['delivery_receipt']));
    if ($error) gateway_json_error((string)$error, 502);

    $imap_id = gateway_profile_imap_id($profile);
    $saved_uid = null;
    $saved_folder = null;
    if ($imap_id !== null) {
        $imap_details = Hm_IMAP_List::dump($imap_id);
        $imap = Hm_IMAP_List::get_connected_mailbox($imap_id, $handler->cache);
        if ($imap && $imap->authed() && $imap_details) {
            $mime->set_original_bcc_header();
            $save_result = save_sent_msg($handler, $imap_id, $imap, $imap_details, $mime->get_mime_msg(), $mime->get_headers()['Message-Id'], false);
            if (is_array($save_result) && count($save_result) >= 2) {
                list($saved_uid, $saved_folder) = $save_result;
                if ($saved_uid !== true && gateway_safe_stored_uid($saved_uid) === null) {
                    $saved_folder = null;
                }
            }
        }
    }
    gateway_remove_uploads($handler, $upload_ids);
    return array(
        'status' => 'sent',
        'message_id_header' => $mime->get_headers()['Message-Id'] ?? null,
        'account_id' => $imap_id,
        'folder' => $saved_folder,
        'uid' => gateway_safe_stored_uid($saved_uid),
        'scheduled' => false
    );
}}

if (!hm_exists('gateway_store_draft')) {
function gateway_store_draft($handler, $payload, $profile, $schedule = '') {
    if ($schedule !== '' && !$handler->module_is_supported('scheduled_sends')) {
        gateway_json_error('scheduled_sends module is not enabled in Cypht', 409);
    }
    $imap_id = gateway_profile_imap_id($profile);
    if ($imap_id === null) gateway_json_error('profile has no associated mailbox', 409);
    $imap = Hm_IMAP_List::get_connected_mailbox($imap_id, $handler->cache);
    if (!$imap || !$imap->authed()) gateway_json_error('mailbox authentication failed', 502);
    list($mime, , $upload_ids) = gateway_build_mime($handler, $payload, $profile, $schedule);
    $folder = $schedule !== '' ? 'Scheduled' : null;
    if ($folder === null) {
        $special = get_special_folders($handler, $imap_id);
        $folder = isset($special['draft']) && $special['draft'] ? $special['draft'] : null;
        if (!$folder) {
            $auto = $imap->get_special_use_mailboxes('drafts');
            $folder = is_array($auto) && isset($auto['drafts']) ? $auto['drafts'] : 'Drafts';
        }
    }
    if (!$imap->folder_exists($folder) && !$imap->create_folder($folder)) gateway_json_error('draft/scheduled folder is unavailable', 409);
    $uid = $imap->store_message($folder, $mime->get_mime_msg(), false, true);
    if (!$uid) gateway_json_error('failed to store draft', 502);
    if (is_array($uid) || is_object($uid)) gateway_json_error('draft save result is uncertain; do not retry with a new key', 502);
    gateway_remove_uploads($handler, $upload_ids);
    return array(
        'status' => $schedule !== '' ? 'scheduled' : 'draft',
        'message_id_header' => $mime->get_headers()['Message-Id'] ?? null,
        'account_id' => $imap_id,
        'folder' => $folder,
        'uid' => gateway_safe_stored_uid($uid),
        'scheduled' => $schedule !== ''
    );
}}
