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
        return in_array(strtoupper($extension), $this->mailbox->capabilities, true);
    }

    /* raw commands sent by the API, and the answer to the last one */
    public $commands = [];
    private $answer = [];

    public function send_command($command, $no_prefix = false) {
        $this->commands[] = trim($command);
        $this->answer = $this->mailbox->raw_command(trim($command));
    }

    public function get_response($max = false, $chunked = false) {
        return $this->answer;
    }

    public function bust_cache($folder, $full = true) {}

    public function show_debug($full = false, $return = false, $list = false) {
        return ['debug' => [], 'commands' => [], 'responses' => $this->mailbox->raw_responses];
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
    /* IMAP extensions the fake server supports, like MOVE, UIDPLUS or X-GM-EXT-1 */
    public $capabilities = [];
    /* message actions that fail */
    public $failing_actions = [];
    /* folder => next uid */
    public $uidnext = [];
    /* answer COPYUID only in the raw server response, like Cypht sees it for several messages */
    public $raw_copyuid = false;
    public $raw_responses = [];
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
        if (!array_key_exists($folder, $this->folders)) {
            return [];
        }
        $messages = $this->folders[$folder];
        $unseen = count(array_filter($messages, function ($m) { return stripos($m['flags'], '\\Seen') === false; }));
        return ['messages' => count($messages), 'unseen' => $unseen];
    }

    public function search($folder, $target = 'ALL', $terms = [], $sort = null, $reverse = null, $exclude_deleted = true) {
        $this->searches[] = [$folder, $target, $terms];
        $res = [];
        foreach ($this->folders[$folder] ?? [] as $uid => $m) {
            $deleted = stripos($m['flags'], '\\Deleted') !== false;
            if ($exclude_deleted && $deleted) {
                continue;
            }
            foreach ((array) $target as $key) {
                if ($key === 'DELETED' && !$deleted) {
                    continue 2;
                }
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

    /* [folder, uid, read only] of every header read */
    public $header_reads = [];

    public function get_message_headers($folder, $uid) {
        $this->header_reads[] = [$folder, (string) $uid, $this->read_only];
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
        if (!$this->select_folder($folder) || in_array($action, $this->failing_actions, true)) {
            return ['status' => false, 'responses' => []];
        }
        $uids = array_map('strval', (array) $uids);
        $flags = ['READ' => ['+', '\\Seen'], 'UNREAD' => ['-', '\\Seen'], 'FLAG' => ['+', '\\Flagged'],
            'UNFLAG' => ['-', '\\Flagged'], 'DELETE' => ['+', '\\Deleted'], 'UNDELETE' => ['-', '\\Deleted']];
        if (isset($flags[$action])) {
            list($op, $flag) = $flags[$action];
            foreach ($uids as $uid) {
                if (isset($this->folders[$folder][$uid])) {
                    $current = $this->folders[$folder][$uid]['flags'];
                    $current = trim(str_ireplace($flag, '', $current));
                    $this->folders[$folder][$uid]['flags'] = $op === '+' ? trim($current.' '.$flag) : $current;
                }
            }
            return ['status' => true, 'responses' => []];
        }
        if ($action === 'EXPUNGE') {
            foreach ($this->folders[$folder] as $uid => $m) {
                if (stripos($m['flags'], '\\Deleted') !== false) {
                    unset($this->folders[$folder][$uid]);
                }
            }
            return ['status' => true, 'responses' => []];
        }
        if ($action === 'MOVE' || $action === 'COPY') {
            if (!array_key_exists($mailbox, $this->folders)) {
                return ['status' => false, 'responses' => []];
            }
            $responses = [];
            $pairs = [];
            foreach ($uids as $uid) {
                if (!isset($this->folders[$folder][$uid])) {
                    continue;
                }
                $new = $this->next_uid($mailbox);
                $this->folders[$mailbox][$new] = $this->folders[$folder][$uid];
                if ($action === 'MOVE') {
                    unset($this->folders[$folder][$uid]);
                }
                $pairs[$uid] = $new;
                $responses[] = ['oldUid' => $uid, 'newUid' => in_array('UIDPLUS', $this->capabilities, true) ? $new : null];
            }
            $this->raw_responses[] = $this->raw_copyuid && $pairs
                ? [sprintf("* OK [COPYUID 7 %s %s] Moved\r\n", implode(',', array_keys($pairs)), implode(',', $pairs)), "A9 OK Done\r\n"]
                : ["A9 OK Done\r\n"];
            if ($this->raw_copyuid) {
                $responses = [];
            }
            return ['status' => true, 'responses' => $action === 'MOVE' ? $responses : []];
        }
        return ['status' => false, 'responses' => []];
    }

    /**
     * Raw commands of the fake IMAP connection
     */
    public function raw_command($command) {
        if (preg_match('/^UID EXPUNGE ([0-9,]+)$/', $command, $matches) && in_array('UIDPLUS', $this->capabilities, true)) {
            foreach (explode(',', $matches[1]) as $uid) {
                if (isset($this->folders[$this->selected][$uid]) && stripos($this->folders[$this->selected][$uid]['flags'], '\\Deleted') !== false) {
                    unset($this->folders[$this->selected][$uid]);
                }
            }
            return ['* 1 EXPUNGE', 'A7 OK UID EXPUNGE completed'];
        }
        return ['A7 BAD Unknown command'];
    }

    private function next_uid($folder) {
        $keys = array_map('intval', array_keys($this->folders[$folder] ?? []));
        $next = max($this->uidnext[$folder] ?? 1, $keys ? max($keys) + 1 : 1);
        $this->uidnext[$folder] = $next + 1;
        return (string) $next;
    }

    public function create_folder($folder, $parent = null) {
        if (array_key_exists($folder, $this->folders) || in_array('CREATE', $this->failing_actions, true)) {
            return false;
        }
        $this->folders[$folder] = [];
        return $folder;
    }

    /**
     * Full message source, built from the fake fields when the message has none
     */
    public function get_message_content($folder, $uid, $part = 0) {
        if (!$this->select_folder($folder) || !isset($this->folders[$folder][(string) $uid])) {
            return null;
        }
        $this->content_reads[] = [(string) $uid, 'full', $this->read_only];
        $m = $this->folders[$folder][(string) $uid];
        if (isset($m['raw'])) {
            return $m['raw'];
        }
        $head = sprintf("Subject: %s\r\nFrom: %s\r\nTo: %s\r\nDate: %s\r\nMessage-ID: %s\r\n", $m['subject'], $m['from'], $m['to'],
            $m['date'], $m['message_id']);
        foreach ($m['headers'] as $name => $value) {
            $head .= $name.': '.$value."\r\n";
        }
        return $head."\r\n".($m['parts']['1'] ?? '')."\r\n";
    }

    /**
     * Save a raw message (IMAP APPEND)
     */
    public function store_message($folder, $msg, $seen = true, $draft = false) {
        if (!array_key_exists($folder, $this->folders) || in_array('APPEND', $this->failing_actions, true)) {
            return false;
        }
        $this->actions[] = [$folder, 'APPEND', [], $this->read_only];
        list($head, $body) = array_pad(explode("\r\n\r\n", $msg, 2), 2, '');
        $headers = [];
        foreach (explode("\r\n", preg_replace("/\r\n[ \t]+/", ' ', $head)) as $line) {
            if (strpos($line, ':') !== false) {
                list($name, $value) = explode(':', $line, 2);
                $headers[trim($name)] = trim($value);
            }
        }
        $uid = $this->next_uid($folder);
        $known = ['Subject', 'From', 'To', 'Date', 'Message-ID'];
        $this->folders[$folder][$uid] = array_merge(hm_mcp_fake_message($headers['Subject'] ?? '', $headers['From'] ?? '',
            $headers['Date'] ?? '', ['text' => rtrim($body), 'message_id' => $headers['Message-ID'] ?? '',
            'flags' => trim(($seen ? '\\Seen' : '').($draft ? ' \\Draft' : '')),
            'headers' => array_diff_key($headers, array_flip($known))]), ['raw' => $msg]);
        return in_array('UIDPLUS', $this->capabilities, true) ? $uid : true;
    }
}

/**
 * Context with fake mailboxes instead of IMAP connections
 */
class Hm_MCP_Fake_Context extends Hm_MCP_Context {
    public $mailbox_objects = [];
    public $failing = [];
    /* lists of setting names saved by update_user_settings() */
    public $saved_settings = [];

    protected function connect($id) {
        if (in_array($id, $this->failing, true)) {
            return false;
        }
        return $this->mailbox_objects[$id];
    }

    public function update_user_settings($change) {
        $changed = $change($this->user_config);
        if ($changed) {
            $this->saved_settings[] = $changed;
        }
        return (bool) $changed;
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
