<?php

if (class_exists('Hm_Handler_login')) {
    class Hm_Handler_gateway_login extends Hm_Handler_login {
        public function process() {
            $this->validate_request = false;
            parent::process();
        }
    }
}

/**
 * All bridge handlers deliberately return JSON (or a bounded binary attachment)
 * and stop dispatch immediately. Secrets from server repositories are never exposed.
 */
class Hm_Handler_gateway_http_headers extends Hm_Handler_Module {
    public function process() {
        $headers = $this->get('http_headers', array());
        if (isset($headers['Content-Security-Policy'])) {
            if (strpos($headers['Content-Security-Policy'], 'frame-src') === false) {
                if (strpos($headers['Content-Security-Policy'], "default-src 'none';") !== false) {
                    $headers['Content-Security-Policy'] = str_replace(
                        "default-src 'none';",
                        "default-src 'none'; frame-src 'self';",
                        $headers['Content-Security-Policy']
                    );
                } else {
                    $headers['Content-Security-Policy'] .= " frame-src 'self';";
                }
            }
            $this->out('http_headers', $headers);
        }
    }
}

class Hm_Handler_gateway_sso_data extends Hm_Handler_Module {
    public function process() {
        $username = $this->session->get('username', '');
        $hm_id = $this->request->cookie['hm_id'] ?? '';
        $hm_session = $this->request->cookie['hm_session'] ?? '';
        $bridge_key = function_exists('gateway_bridge_key') ? gateway_bridge_key() : env('GATEWAY_BRIDGE_KEY', '');
        if (!$bridge_key && file_exists('/var/lib/hm3/app_data/gateway/bridge.key')) {
            $bridge_key = trim((string)@file_get_contents('/var/lib/hm3/app_data/gateway/bridge.key'));
        }
        $token = '';
        if ($username && $hm_id && $hm_session && $bridge_key) {
            $payload = json_encode(array(
                'username' => $username,
                'hm_id' => $hm_id,
                'hm_session' => $hm_session
            ));
            $opts = array(
                'http' => array(
                    'method'  => 'POST',
                    'header'  => "Content-Type: application/json\r\nX-Cypht-Gateway-Key: ".$bridge_key."\r\n",
                    'content' => $payload,
                    'timeout' => 2,
                    'ignore_errors' => true
                )
            );
            $context = @stream_context_create($opts);
            $res = @file_get_contents('http://127.0.0.1:18080/api/v1/auth/sso', false, $context);
            if ($res) {
                $data = @json_decode($res, true);
                if (!empty($data['access_token'])) {
                    $token = $data['access_token'];
                }
            }
        }
        $this->out('gateway_sso_token', $token);
    }
}

class Hm_Handler_gateway_guard extends Hm_Handler_Module {
    public function process() {
        $configured = function_exists('gateway_bridge_key') ? gateway_bridge_key() : env('GATEWAY_BRIDGE_KEY', '');
        if (!$configured && file_exists('/var/lib/hm3/app_data/gateway/bridge.key')) {
            $configured = trim((string)@file_get_contents('/var/lib/hm3/app_data/gateway/bridge.key'));
        }
        $provided = isset($this->request->server['HTTP_X_CYPHT_GATEWAY_KEY'])
            ? $this->request->server['HTTP_X_CYPHT_GATEWAY_KEY'] : '';
        if (!$configured || !$provided || !hash_equals((string)$configured, (string)$provided)) {
            gateway_json_error('bridge authentication failed', 403);
        }
        Hm_Request_Key::load($this->session, $this->request, false);
        Hm_Gateway_Response::bind_session($this->session);
    }
}

class Hm_Handler_gateway_ping extends Hm_Handler_Module {
    public function process() {
        gateway_json_ok(array(
            'status' => 'ok',
            'bridge_version' => gateway_bridge_version(),
            'cypht_version' => defined('CYPHT_VERSION') ? (string)CYPHT_VERSION : 'unknown',
            'durable_user_config' => isset($this->user_config) && is_object($this->user_config) &&
                method_exists($this->user_config, 'gateway_storage_is_safe') &&
                $this->user_config->gateway_storage_is_safe() === true
        ));
    }
}

class Hm_Handler_gateway_accounts extends Hm_Handler_Module {
    public function process() {
        $sendable = array();
        foreach (gateway_safe_profiles($this) as $profile) {
            if (!empty($profile['account_id'])) $sendable[(string)$profile['account_id']] = true;
        }
        $accounts = array();
        foreach (Hm_IMAP_List::dump() as $id => $server) {
            $accounts[] = gateway_safe_account($id, $server, isset($sendable[(string)$id]));
        }
        gateway_json_ok($accounts);
    }
}

class Hm_Handler_gateway_profiles extends Hm_Handler_Module {
    public function process() {
        gateway_json_ok(gateway_safe_profiles($this));
    }
}

class Hm_Handler_gateway_mailboxes extends Hm_Handler_Module {
    public function process() {
        $id = isset($this->request->get['account_id']) ? (string)$this->request->get['account_id'] : '';
        if ($id === '' || !Hm_IMAP_List::dump($id)) gateway_json_error('unknown account', 404);
        $mailbox = Hm_IMAP_List::get_connected_mailbox($id, $this->cache);
        if (!$mailbox || !$mailbox->authed()) gateway_json_error('mail account authentication failed', 502);
        $folders = $mailbox->get_folders(false);
        if (!is_array($folders)) gateway_json_ok(array());
        $result = array();
        foreach ($folders as $name => $details) {
            if (!is_array($details)) $details = array();
            $folder_name = is_string($name) ? $name : (isset($details['name']) ? $details['name'] : '');
            if ($folder_name === '') continue;
            $status = $mailbox->get_folder_status($folder_name, false);
            $result[] = array(
                'name' => (string)$folder_name,
                'display_name' => isset($details['name']) ? (string)$details['name'] : (string)$folder_name,
                'role' => gateway_folder_role($folder_name, $details),
                'total' => is_array($status) && isset($status['messages']) ? (int)$status['messages'] : null,
                'unread' => is_array($status) && isset($status['unseen']) ? (int)$status['unseen'] : null,
                'selectable' => !(isset($details['noselect']) && $details['noselect'])
            );
        }
        gateway_json_ok($result);
    }
}

class Hm_Handler_gateway_messages extends Hm_Handler_Module {
    public function process() {
        $id = isset($this->request->get['account_id']) ? (string)$this->request->get['account_id'] : '';
        $folder = isset($this->request->get['folder']) ? (string)$this->request->get['folder'] : 'INBOX';
        $offset = isset($this->request->get['offset']) ? max(0, (int)$this->request->get['offset']) : 0;
        $limit = isset($this->request->get['limit']) ? min(1100, max(1, (int)$this->request->get['limit'])) : 50;
        if ($id === '' || !Hm_IMAP_List::dump($id)) gateway_json_error('unknown account', 404);
        $mailbox = Hm_IMAP_List::get_connected_mailbox($id, $this->cache);
        if (!$mailbox || !$mailbox->authed()) gateway_json_error('mail account authentication failed', 502);
        list($total, $messages) = $mailbox->get_messages($folder, 'arrival', true, 'ALL', $offset, $limit, false, array(), true);
        $result = array();
        foreach ((array)$messages as $message) $result[] = gateway_normalize_message($id, $folder, $message);
        gateway_json_ok(array('total' => is_numeric($total) ? (int)$total : null, 'messages' => $result));
    }
}

class Hm_Handler_gateway_search extends Hm_Handler_Module {
    public function process() {
        $ids_raw = isset($this->request->get['account_ids']) ? (string)$this->request->get['account_ids'] : '';
        $ids = array_values(array_filter(explode(',', $ids_raw), 'strlen'));
        $folder = isset($this->request->get['folder']) ? (string)$this->request->get['folder'] : 'INBOX';
        $query = isset($this->request->get['query']) ? trim((string)$this->request->get['query']) : '';
        $limit = isset($this->request->get['limit']) ? min(100, max(1, (int)$this->request->get['limit'])) : 50;
        if ($query === '') gateway_json_error('query cannot be empty', 400);
        if (!$ids) $ids = array_keys(Hm_IMAP_List::dump());
        $known = Hm_IMAP_List::dump();
        $result = array();
        foreach ($ids as $id) {
            if (!array_key_exists($id, $known)) continue;
            $mailbox = Hm_IMAP_List::get_connected_mailbox($id, $this->cache);
            if (!$mailbox || !$mailbox->authed()) continue;
            list(, $messages) = $mailbox->get_messages($folder, 'arrival', true, 'ALL', 0, $limit, $query, array(), true);
            foreach ((array)$messages as $message) $result[] = gateway_normalize_message($id, $folder, $message);
        }
        usort($result, function($a, $b) { return (int)($b['timestamp'] ?: 0) <=> (int)($a['timestamp'] ?: 0); });
        if (count($result) > $limit) $result = array_slice($result, 0, $limit);
        gateway_json_ok(array('total' => count($result), 'messages' => $result));
    }
}

class Hm_Handler_gateway_message extends Hm_Handler_Module {
    public function process() {
        $id = isset($this->request->get['account_id']) ? (string)$this->request->get['account_id'] : '';
        $folder = isset($this->request->get['folder']) ? (string)$this->request->get['folder'] : '';
        $uid = isset($this->request->get['uid']) ? (string)$this->request->get['uid'] : '';
        if ($id === '' || $folder === '' || $uid === '') gateway_json_error('account_id, folder and uid are required', 400);
        if (!Hm_IMAP_List::dump($id)) gateway_json_error('unknown account', 404);
        $mailbox = Hm_IMAP_List::get_connected_mailbox($id, $this->cache);
        if (!$mailbox || !$mailbox->authed()) gateway_json_error('mail account authentication failed', 502);
        list($structure, , $text) = $mailbox->get_structured_message($folder, $uid, false, true, true);
        $headers = $mailbox->get_message_headers($folder, $uid);
        gateway_json_ok(array(
            'uid' => $uid,
            'account_id' => $id,
            'folder' => $folder,
            'headers' => is_array($headers) ? $headers : array(),
            'body_text' => is_string($text) ? $text : null,
            'body_html' => null,
            'structure' => $structure,
            'attachments' => gateway_extract_attachments($structure)
        ));
    }
}

class Hm_Handler_gateway_attachment extends Hm_Handler_Module {
    public function process() {
        $id = isset($this->request->get['account_id']) ? (string)$this->request->get['account_id'] : '';
        $folder = isset($this->request->get['folder']) ? (string)$this->request->get['folder'] : '';
        $uid = isset($this->request->get['uid']) ? (string)$this->request->get['uid'] : '';
        $part = isset($this->request->get['part']) ? (string)$this->request->get['part'] : '';
        if ($id === '' || $folder === '' || $uid === '' || $part === '') gateway_json_error('attachment location is incomplete', 400);
        if (!Hm_IMAP_List::dump($id)) gateway_json_error('unknown account', 404);
        $mailbox = Hm_IMAP_List::get_connected_mailbox($id, $this->cache);
        if (!$mailbox || !$mailbox->authed()) gateway_json_error('mail account authentication failed', 502);
        list($structure) = $mailbox->get_structured_message($folder, $uid, false, true, true);
        $meta = null;
        foreach (gateway_extract_attachments($structure) as $candidate) {
            if ((string)$candidate['part'] === $part) { $meta = $candidate; break; }
        }
        if (!$meta) gateway_json_error('unknown attachment', 404);
        $max = (int)env('GATEWAY_MAX_ATTACHMENT_BYTES', 26214400);
        if ($max < 1) $max = 26214400;
        if ($meta['size'] !== null && $meta['size'] > $max) gateway_json_error('attachment exceeds download limit', 413);
        $content = $mailbox->get_message_content($folder, $uid, $part);
        if (!is_string($content)) gateway_json_error('attachment could not be read', 502);
        if (strlen($content) > $max) gateway_json_error('attachment exceeds download limit', 413);
        $filename = basename((string)($meta['filename'] ?: 'attachment.bin'));
        $content_type = (string)($meta['content_type'] ?: 'application/octet-stream');
        Hm_Gateway_Response::close_session();
        header('Content-Type: '.$content_type);
        header('Content-Disposition: attachment; filename="'.str_replace('"', '', $filename).'"');
        header('Content-Length: '.strlen($content));
        header('Cache-Control: no-store');
        echo $content;
        Hm_Functions::cease();
    }
}

class Hm_Handler_gateway_upload extends Hm_Handler_Module {
    public function process() {
        $max = (int)env('GATEWAY_MAX_UPLOAD_BYTES', 20971520);
        if ($max < 1) $max = 20971520;
        $length = isset($this->request->server['CONTENT_LENGTH']) ? (int)$this->request->server['CONTENT_LENGTH'] : 0;
        if ($length > $max) gateway_json_error('attachment exceeds configured size limit', 413);
        $data = file_get_contents('php://input');
        if (!is_string($data) || $data === '') gateway_json_error('attachment body is empty', 400);
        if (strlen($data) > $max) gateway_json_error('attachment exceeds configured size limit', 413);
        $name = isset($this->request->server['HTTP_X_CYPHT_GATEWAY_FILENAME'])
            ? basename(gateway_header_text($this->request->server['HTTP_X_CYPHT_GATEWAY_FILENAME'])) : 'attachment.bin';
        if ($name === '' || strlen($name) > 255) gateway_json_error('invalid attachment filename', 400);
        $type = isset($this->request->server['HTTP_X_CYPHT_GATEWAY_CONTENT_TYPE'])
            ? gateway_header_text($this->request->server['HTTP_X_CYPHT_GATEWAY_CONTENT_TYPE']) : 'application/octet-stream';
        if ($type === '' || strlen($type) > 200) $type = 'application/octet-stream';
        $root = $this->config->get('attachment_dir');
        if (!$root || !is_dir($root) || !is_writable($root)) gateway_json_error('attachment storage unavailable', 503);
        $user_dir = $root.DIRECTORY_SEPARATOR.md5($this->session->get('username', false)).DIRECTORY_SEPARATOR.'gateway';
        if (!is_dir($user_dir) && !mkdir($user_dir, 0700, true)) gateway_json_error('attachment storage unavailable', 503);
        // Remove abandoned Gateway upload files after 24 hours. Files tied to the
        // current session remain referenced by gateway_uploads and are normally
        // deleted immediately after send/draft succeeds.
        foreach (glob($user_dir.DIRECTORY_SEPARATOR.'upl_*.bin') ?: array() as $stale) {
            $mtime = @filemtime($stale);
            if ($mtime !== false && $mtime < time() - 86400) @unlink($stale);
        }
        $upload_id = 'upl_'.bin2hex(random_bytes(16));
        $path = $user_dir.DIRECTORY_SEPARATOR.$upload_id.'.bin';
        // Match Cypht's native attachment cache format. Hm_MIME_Msg decrypts
        // attachment files with the per-session request key immediately before
        // constructing MIME, so Gateway uploads must be encrypted the same way.
        $encrypted = Hm_Crypt::ciphertext($data, Hm_Request_Key::generate());
        if (!is_string($encrypted) || file_put_contents($path, $encrypted, LOCK_EX) === false) {
            gateway_json_error('failed to store attachment', 500);
        }
        @chmod($path, 0600);
        $uploads = gateway_uploads($this);
        foreach ($uploads as $known_id => $known_upload) {
            $known_path = is_array($known_upload) && isset($known_upload['filename']) ? (string)$known_upload['filename'] : '';
            if ($known_path === '' || !is_file($known_path)) unset($uploads[$known_id]);
        }
        $uploads[$upload_id] = array(
            'id' => $upload_id,
            'name' => $name,
            'basename' => $name,
            'type' => $type,
            'size' => strlen($data),
            'tmp_name' => $path,
            'filename' => $path
        );
        $this->session->set('gateway_uploads', $uploads);
        gateway_json_ok(array('id' => $upload_id, 'filename' => $name, 'content_type' => $type, 'size' => strlen($data)));
    }
}

class Hm_Handler_gateway_send extends Hm_Handler_Module {
    public function process() {
        $payload = gateway_payload($this->request);
        $profile = gateway_pick_profile($this, $payload['profile_id'] ?? null);
        $schedule = gateway_validate_schedule($payload['schedule_at'] ?? '');
        if ($schedule !== '') gateway_json_ok(gateway_store_draft($this, $payload, $profile, $schedule));
        gateway_json_ok(gateway_send_now($this, $payload, $profile));
    }
}

class Hm_Handler_gateway_draft extends Hm_Handler_Module {
    public function process() {
        $payload = gateway_payload($this->request);
        $profile = gateway_pick_profile($this, $payload['profile_id'] ?? null);
        gateway_json_ok(gateway_store_draft($this, $payload, $profile, ''));
    }
}

class Hm_Handler_gateway_message_update extends Hm_Handler_Module {
    public function process() {
        $payload = gateway_payload($this->request);
        $id = (string)($payload['account_id'] ?? '');
        $folder = (string)($payload['folder'] ?? '');
        $uid = (string)($payload['uid'] ?? '');
        if ($id === '' || $folder === '' || $uid === '') gateway_json_error('message location is incomplete', 400);
        if (!Hm_IMAP_List::dump($id)) gateway_json_error('unknown account', 404);
        $mailbox = Hm_IMAP_List::get_connected_mailbox($id, $this->cache);
        if (!$mailbox || !$mailbox->authed()) gateway_json_error('mail account authentication failed', 502);
        if (array_key_exists('seen', $payload)) {
            $cmd = $payload['seen'] ? 'READ' : 'UNREAD';
            if (!gateway_action_succeeded($mailbox->message_action($folder, $cmd, array($uid)))) gateway_json_error('failed to update seen state', 502);
        }
        if (array_key_exists('flagged', $payload)) {
            $cmd = $payload['flagged'] ? 'FLAG' : 'UNFLAG';
            if (!gateway_action_succeeded($mailbox->message_action($folder, $cmd, array($uid)))) gateway_json_error('failed to update flagged state', 502);
        }
        gateway_json_ok(array('status' => 'updated'));
    }
}

class Hm_Handler_gateway_message_move extends Hm_Handler_Module {
    public function process() {
        $payload = gateway_payload($this->request);
        $id = (string)($payload['account_id'] ?? '');
        $folder = (string)($payload['folder'] ?? '');
        $uid = (string)($payload['uid'] ?? '');
        $destination = (string)($payload['destination'] ?? '');
        if ($id === '' || $folder === '' || $uid === '' || $destination === '') gateway_json_error('move request is incomplete', 400);
        if (!Hm_IMAP_List::dump($id)) gateway_json_error('unknown account', 404);
        $mailbox = Hm_IMAP_List::get_connected_mailbox($id, $this->cache);
        if (!$mailbox || !$mailbox->authed()) gateway_json_error('mail account authentication failed', 502);
        $action = $mailbox->message_action($folder, 'MOVE', array($uid), $destination);
        if (!gateway_action_succeeded($action)) gateway_json_error('failed to move message', 502);
        $tag_sync = gateway_sync_tag_move($this, $id, $folder, $uid, $destination, $action);
        gateway_json_ok(array('status' => 'moved', 'folder' => $destination, 'tag_sync' => $tag_sync));
    }
}

class Hm_Handler_gateway_message_archive extends Hm_Handler_Module {
    public function process() {
        $payload = gateway_payload($this->request);
        $id = (string)($payload['account_id'] ?? '');
        $folder = (string)($payload['folder'] ?? '');
        $uid = (string)($payload['uid'] ?? '');
        if ($id === '' || $folder === '' || $uid === '') gateway_json_error('archive request is incomplete', 400);
        if (!Hm_IMAP_List::dump($id)) gateway_json_error('unknown account', 404);
        $mailbox = Hm_IMAP_List::get_connected_mailbox($id, $this->cache);
        if (!$mailbox || !$mailbox->authed()) gateway_json_error('mail account authentication failed', 502);
        $special = get_special_folders($this, $id);
        $archive = isset($special['archive']) ? $special['archive'] : null;
        if (!$archive) {
            $auto = $mailbox->get_special_use_mailboxes('archive');
            $archive = is_array($auto) && isset($auto['archive']) ? $auto['archive'] : null;
        }
        if (!$archive) gateway_json_error('archive folder is not configured', 409);
        $action = $mailbox->message_action($folder, 'MOVE', array($uid), $archive);
        if (!gateway_action_succeeded($action)) gateway_json_error('failed to archive message', 502);
        $tag_sync = gateway_sync_tag_move($this, $id, $folder, $uid, $archive, $action);
        gateway_json_ok(array('status' => 'archived', 'folder' => $archive, 'tag_sync' => $tag_sync));
    }
}

class Hm_Handler_gateway_message_delete extends Hm_Handler_Module {
    public function process() {
        $payload = gateway_payload($this->request);
        $id = (string)($payload['account_id'] ?? '');
        $folder = (string)($payload['folder'] ?? '');
        $uid = (string)($payload['uid'] ?? '');
        if ($id === '' || $folder === '' || $uid === '') gateway_json_error('delete request is incomplete', 400);
        if (!Hm_IMAP_List::dump($id)) gateway_json_error('unknown account', 404);
        $mailbox = Hm_IMAP_List::get_connected_mailbox($id, $this->cache);
        if (!$mailbox || !$mailbox->authed()) gateway_json_error('mail account authentication failed', 502);
        $special = get_special_folders($this, $id);
        $trash = isset($special['trash']) ? $special['trash'] : false;
        if (!$mailbox->delete_message($folder, $uid, $trash)) gateway_json_error('failed to delete message', 502);
        $tag_sync = gateway_sync_tag_delete($this, $id, $folder, $uid, $trash);
        gateway_json_ok(array(
            'status' => $trash && $trash !== $folder ? 'trashed' : 'deleted',
            'folder' => $trash && $trash !== $folder ? $trash : null,
            'tag_sync' => $tag_sync
        ));
    }
}
