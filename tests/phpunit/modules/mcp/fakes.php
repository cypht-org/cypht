<?php

/**
 * Test doubles for the mcp module tests (not a test file)
 */

require_once APP_PATH.'modules/core/modules.php';
require_once APP_PATH.'modules/mcp/hm-mcp.php';

/**
 * Minimal IMAP connection used by Hm_MCP_Mail to read structures and parts
 */
class Hm_MCP_Fake_Connection {
    public $selected_mailbox = false;
    public $mailbox;

    public function __construct($mailbox) {
        $this->mailbox = $mailbox;
    }

    public function get_message_structure($uid) {
        return $this->mailbox->message($uid)['struct'];
    }

    public function get_message_content($uid, $part, $max = false, $struct = false) {
        $this->mailbox->content_reads[] = [$uid, (string) $part];
        return $this->mailbox->message($uid)['parts'][(string) $part] ?? '';
    }

    public function is_supported($extension) {
        return false;
    }
}

/**
 * In memory mailbox implementing the Hm_Mailbox methods the API uses
 */
class Hm_MCP_Fake_Mailbox {
    public $folders = [];
    public $special_use = [];
    public $read_only = null;
    public $charset = '';
    public $selected = null;
    public $actions = [];
    public $searches = [];
    public $content_reads = [];
    private $connection;

    /**
     * @param array $folders folder => [uid => message]
     */
    public function __construct($folders, $special_use = []) {
        $this->folders = $folders;
        $this->special_use = $special_use;
        $this->connection = new Hm_MCP_Fake_Connection($this);
    }

    public function authed() { return true; }
    public function is_imap() { return true; }
    public function get_connection() { return $this->connection; }
    public function set_read_only($value) { $this->read_only = $value; }
    public function set_search_charset($charset) { $this->charset = $charset; }

    public function select_folder($folder) {
        if (!array_key_exists($folder, $this->folders)) {
            return false;
        }
        $this->selected = $folder;
        return true;
    }

    public function message($uid) {
        return $this->folders[$this->selected][(string) $uid] ?? ['struct' => [], 'parts' => []];
    }

    public function get_folders($only_subscribed = false) {
        $res = [];
        foreach (array_keys($this->folders) as $name) {
            $parts = explode('/', $name);
            $res[$name] = ['name' => $name, 'basename' => end($parts), 'parent' => count($parts) > 1 ? $parts[0] : '',
                'noselect' => false, 'has_kids' => false, 'special' => $name === 'INBOX'];
        }
        return $res;
    }

    public function get_special_use_mailboxes($folder = false) {
        return $this->special_use;
    }

    public function get_folder_status($folder, $report_error = true) {
        $messages = $this->folders[$folder] ?? [];
        $unseen = count(array_filter($messages, function ($m) { return stripos($m['flags'], '\\Seen') === false; }));
        return ['messages' => count($messages), 'unseen' => $unseen];
    }

    public function search($folder, $target = 'ALL', $terms = [], $sort = null, $reverse = null) {
        $this->searches[] = [$folder, $target, $terms];
        $res = [];
        foreach ($this->folders[$folder] ?? [] as $uid => $m) {
            foreach ((array) $target as $key) {
                if ($key === 'UNSEEN' && stripos($m['flags'], '\\Seen') !== false) {
                    continue 2;
                }
                if ($key === 'FLAGGED' && stripos($m['flags'], '\\Flagged') === false) {
                    continue 2;
                }
            }
            foreach ($terms as list($key, $value)) {
                $time = Hm_MCP_Format::timestamp($m['date']);
                $ok = true;
                switch ($key) {
                    case 'SINCE': $ok = $time >= strtotime($value); break;
                    case 'BEFORE': $ok = $time < strtotime($value); break;
                    case 'SUBJECT': $ok = stripos($m['subject'], $value) !== false; break;
                    case 'FROM': $ok = stripos($m['from'], $value) !== false; break;
                    case 'TEXT': $ok = stripos($m['subject'].' '.implode(' ', $m['parts']), $value) !== false; break;
                    case 'HEADER Message-ID': $ok = $m['message_id'] === $value; break;
                    case 'HEADER References': $ok = strpos($m['headers']['References'] ?? '', $value) !== false; break;
                    case 'HEADER In-Reply-To': $ok = ($m['headers']['In-Reply-To'] ?? '') === $value; break;
                }
                if (!$ok) {
                    continue 2;
                }
            }
            $res[] = (string) $uid;
        }
        return $res;
    }

    public function get_message_list($folder, $uids, $exclude_auto_bcc = true) {
        $res = [];
        foreach ($uids as $uid) {
            if (isset($this->folders[$folder][(string) $uid])) {
                $m = $this->folders[$folder][(string) $uid];
                $res[(string) $uid] = ['uid' => (string) $uid, 'flags' => $m['flags'], 'internal_date' => $m['date'],
                    'size' => '1200', 'date' => $m['date'], 'from' => $m['from'], 'to' => $m['to'], 'subject' => $m['subject'],
                    'content-type' => $m['content_type'], 'message_id' => $m['message_id'], 'preview_msg' => ''];
            }
        }
        return $res;
    }

    public function get_message_headers($folder, $uid) {
        if (!isset($this->folders[$folder][(string) $uid])) {
            return [];
        }
        $m = $this->folders[$folder][(string) $uid];
        return array_merge(['Subject' => $m['subject'], 'From' => $m['from'], 'To' => $m['to'], 'Date' => $m['date'],
            'Message-ID' => $m['message_id'], 'Flags' => $m['flags']], $m['headers']);
    }

    public function stream_message_part($folder, $uid, $part_id, $start_cb) {
        $this->select_folder($folder);
        $start_cb('application/octet-stream', 'file');
        echo $this->message($uid)['parts'][(string) $part_id] ?? '';
    }

    public function message_action($folder, $action, $uids, $mailbox = false, $keyword = false) {
        $this->actions[] = [$folder, $action, $uids, $this->read_only];
        if ($action === 'READ') {
            foreach ($uids as $uid) {
                $this->folders[$folder][(string) $uid]['flags'] .= ' \\Seen';
            }
        }
        return ['status' => true, 'responses' => []];
    }
}

/**
 * Context with fake mailboxes instead of IMAP connections
 */
class Hm_MCP_Fake_Context extends Hm_MCP_Context {
    public $mailbox_objects = [];
    public $failing = [];

    protected function connect($id) {
        if (in_array($id, $this->failing, true)) {
            return false;
        }
        return $this->mailbox_objects[$id];
    }

    public function finish() {
        $this->mailboxes = [];
    }
}

/**
 * Fake store for code paths that only need settings and logging
 */
class Hm_MCP_Fake_Store {
    public $logged = [];
    public $profile = 'prf_test';
    public function settings($username, $create = false) {
        return array_merge(Hm_MCP_Store::default_settings(), ['enabled' => true, 'profile_id' => $this->profile]);
    }
    public $issued = [];
    public function log_activity($entry) { $this->logged[] = $entry; return true; }
    public function rate_limit($key, $max, $window) { return true; }
    public function available() { return true; }
    public function now() { return time(); }
    public function issue_token($connection_id, $kind, $key, $ttl, $data = null) {
        $token = 'cyp_f_'.str_pad((string) count($this->issued), 43, 'a', STR_PAD_LEFT);
        $this->issued[$token] = compact('connection_id', 'kind', 'ttl', 'data');
        return $token;
    }
}

/**
 * Build a fake message
 */
function hm_mcp_fake_message($subject, $from, $date, $options = []) {
    $html = $options['html'] ?? null;
    $text = $options['text'] ?? 'Plain body of '.$subject;
    if ($html !== null) {
        $struct = [0 => ['type' => 'multipart', 'subtype' => 'alternative', 'subs' => [
            '0.1' => ['type' => 'text', 'subtype' => 'plain', 'attributes' => ['charset' => 'utf-8'], 'disposition' => false, 'size' => '50'],
            '0.2' => ['type' => 'text', 'subtype' => 'html', 'attributes' => ['charset' => 'utf-8'], 'disposition' => false, 'size' => '90'],
        ]]];
        $parts = ['1' => $text, '2' => $html];
    } else {
        $struct = ['1' => ['type' => 'text', 'subtype' => 'plain', 'attributes' => ['charset' => 'utf-8'], 'disposition' => false, 'size' => '40']];
        $parts = ['1' => $text];
    }
    if (!empty($options['attachments'])) {
        $subs = ['0.1' => $html !== null ? $struct[0] : $struct['1']];
        $parts = $html !== null ? ['1.1' => $text, '1.2' => $html] : ['1' => $text];
        if ($html !== null) {
            $subs['0.1']['subs'] = ['0.1.1' => $struct[0]['subs']['0.1'], '0.1.2' => $struct[0]['subs']['0.2']];
        }
        $n = 2;
        foreach ($options['attachments'] as $name => $spec) {
            $spec = is_array($spec) ? $spec : ['type' => $spec];
            list($t, $s) = explode('/', $spec['type']);
            $subs['0.'.$n] = array_merge(['type' => $t, 'subtype' => $s, 'attributes' => false, 'size' => '300',
                'disposition' => ['attachment' => ['filename', $name]]], $spec['struct'] ?? []);
            $parts[(string) $n] = $spec['content'] ?? 'content of '.$name;
            $n++;
        }
        $struct = [0 => ['type' => 'multipart', 'subtype' => 'mixed', 'subs' => $subs]];
    }
    return [
        'subject' => $subject, 'from' => $from, 'to' => 'Alice <alice@example.com>', 'date' => $date,
        'flags' => $options['flags'] ?? '', 'message_id' => $options['message_id'] ?? '<'.md5($subject.$date).'@example.com>',
        'content_type' => !empty($options['attachments']) ? 'multipart/mixed; boundary=x' : ($html !== null ? 'multipart/alternative' : 'text/plain'),
        'headers' => $options['headers'] ?? [], 'struct' => $struct, 'parts' => $parts,
    ];
}

/**
 * Principal with the given permissions and accounts
 */
function hm_mcp_fake_principal($permissions = null, $connection_accounts = null, $global_accounts = ['mode' => 'all', 'ids' => []]) {
    $settings = array_merge(Hm_MCP_Store::default_settings(), ['enabled' => true, 'profile_id' => 'prf_test',
        'permissions' => $permissions ?? Hm_MCP_Permissions::defaults(), 'accounts' => $global_accounts]);
    $connection = ['id' => 'con_test', 'username' => 'alice', 'kind' => 'personal', 'name' => 'Test', 'client_id' => null,
        'permissions' => null, 'accounts' => $connection_accounts, 'status' => 'active', 'created_at' => 1, 'last_used_at' => 1];
    return new Hm_MCP_Principal($connection, str_repeat('k', 32), 'personal', $settings);
}

/**
 * Services wired to fake mailboxes
 * @return array [Hm_MCP_Services, Hm_MCP_Fake_Context factory holder]
 */
function hm_mcp_fake_services($mailboxes, $servers, $user_settings = []) {
    $site = new Hm_Mock_Config();
    $site->set('mcp_public_url', 'https://mail.example.com');
    $site->set('app_name', 'Test Mail');
    $site->mods = ['core', 'imap', 'smtp', 'mcp'];
    $services = new Hm_MCP_Services($site);
    $services->set_store(new Hm_MCP_Fake_Store());
    $holder = new stdClass();
    $holder->context = null;
    $holder->failing = [];
    $services->context_factory = function ($principal) use ($services, $mailboxes, $servers, $user_settings, $holder) {
        $user_config = new Hm_Mock_Config();
        foreach ($user_settings as $name => $value) {
            $user_config->set($name, $value);
        }
        $context = new Hm_MCP_Fake_Context($services->site_config, $services->config, $services->store(), $principal,
            $user_config, new Hm_MCP_Session(), $servers);
        $context->mailbox_objects = $mailboxes;
        $context->failing = $holder->failing;
        $holder->context = $context;
        return $context;
    };
    return [$services, $holder];
}
