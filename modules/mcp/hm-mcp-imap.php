<?php

/**
 * Mailbox write helpers for the MCP server and REST API
 * @package modules
 * @subpackage mcp
 */

if (!defined('DEBUG_MODE')) { die(); }

/**
 * Message moves, flag changes and deletions on top of Hm_Mailbox that only ever
 * touch the requested messages
 * @subpackage mcp/lib
 */
class Hm_MCP_Imap {

    /* message ids accepted by one call */
    const MAX_BATCH = 100;

    /* moved messages looked up by Message-ID when the server does not report their new uid */
    const FIND_MOVED_LIMIT = 25;

    /* messages removed by one empty_folder call */
    const MAX_EMPTY = 10000;

    /* UIDs per UID EXPUNGE command */
    const EXPUNGE_CHUNK = 500;

    /* largest message saved again when snoozing */
    const MAX_SNOOZE_BYTES = 26214400;

    /* folder Cypht keeps snoozed messages in */
    const SNOOZED_FOLDER = 'Snoozed';

    /**
     * Run Cypht library code that may raise PHP warnings on unexpected server answers
     * @param callable $callback code to run
     * @return mixed callback result
     */
    public static function quietly($callback) {
        set_error_handler(function () { return true; }, E_WARNING | E_NOTICE | E_DEPRECATED | E_USER_WARNING | E_USER_NOTICE | E_USER_DEPRECATED);
        try {
            return $callback();
        } finally {
            restore_error_handler();
        }
    }

    /**
     * @param object $mailbox Hm_Mailbox
     * @return object|null IMAP connection
     */
    public static function connection($mailbox) {
        if (!$mailbox->is_imap()) {
            return null;
        }
        $connection = $mailbox->get_connection();
        return is_object($connection) ? $connection : null;
    }

    /**
     * @param object $mailbox Hm_Mailbox
     * @param string $extension IMAP extension
     * @return bool
     */
    public static function supports($mailbox, $extension) {
        $connection = self::connection($mailbox);
        return $connection && method_exists($connection, 'is_supported') && $connection->is_supported($extension);
    }

    /**
     * @param object $mailbox Hm_Mailbox
     * @return bool true for Gmail, where deleting outside the trash only removes a label
     */
    public static function is_gmail($mailbox) {
        return self::supports($mailbox, 'X-GM-EXT-1');
    }

    /**
     * Messages of a folder that exist, with their flags and size
     * @param object $mailbox Hm_Mailbox
     * @param string $folder folder
     * @param array $uids message uids
     * @return array uid => ['flags' => string, 'size' => int]
     */
    public static function existing($mailbox, $folder, $uids) {
        if (!$uids) {
            return [];
        }
        $list = self::quietly(function () use ($mailbox, $folder, $uids) {
            return $mailbox->get_message_list($folder, $uids, false);
        });
        $wanted = array_map('strval', $uids);
        $res = [];
        foreach ((is_array($list) ? $list : []) as $key => $message) {
            $uid = (string) ($message['uid'] ?? $key);
            if (in_array($uid, $wanted, true)) {
                $res[$uid] = ['flags' => (string) ($message['flags'] ?? ''), 'size' => (int) ($message['size'] ?? 0),
                    'message_id' => trim((string) ($message['message_id'] ?? ''))];
            }
        }
        return $res;
    }

    /**
     * @param object $mailbox Hm_Mailbox
     * @param string $folder folder
     * @return bool
     */
    public static function folder_exists($mailbox, $folder) {
        $status = self::quietly(function () use ($mailbox, $folder) {
            return $mailbox->get_folder_status($folder, false);
        });
        return is_array($status) && count($status) > 0;
    }

    /**
     * Change flags with a Cypht message action (READ, UNREAD, FLAG, UNFLAG, DELETE, UNDELETE)
     * @param object $mailbox Hm_Mailbox
     * @param string $folder folder
     * @param array $uids message uids
     * @param string $action message action
     * @return bool
     */
    public static function set_flags($mailbox, $folder, $uids, $action) {
        if (!$uids) {
            return true;
        }
        $res = self::quietly(function () use ($mailbox, $folder, $uids, $action) {
            return $mailbox->message_action($folder, $action, $uids);
        });
        return !empty($res['status']);
    }

    /**
     * Move messages to another folder of the same account
     * @param object $mailbox Hm_Mailbox
     * @param string $folder source folder
     * @param array $uids existing message uids
     * @param string $dest destination folder
     * @param array $message_ids uid => Message-ID header, used to find the new uid when
     *                           the server does not report it (Gmail when archiving)
     * @return array uid => new uid, or null when it is unknown, for each moved message
     */
    public static function move($mailbox, $folder, $uids, $dest, $message_ids = []) {
        $moved = self::move_messages($mailbox, $folder, $uids, $dest);
        $unknown = array_keys(array_filter($moved, 'is_null'));
        if (!$unknown || count($unknown) > self::FIND_MOVED_LIMIT) {
            return $moved;
        }
        foreach ($unknown as $uid) {
            $header = $message_ids[(string) $uid] ?? '';
            if ($header === '' || !preg_match('/^<[^<>\s"]+>$/', $header)) {
                continue;
            }
            $found = self::quietly(function () use ($mailbox, $dest, $header) {
                return $mailbox->search($dest, 'ALL', [['HEADER Message-ID', $header]]);
            });
            if (is_array($found) && count($found) === 1) {
                $moved[$uid] = self::new_uid(reset($found));
            }
        }
        return $moved;
    }

    /**
     * @return array uid => new uid or null for each moved message
     */
    protected static function move_messages($mailbox, $folder, $uids, $dest) {
        if (!$uids) {
            return [];
        }
        if (self::connection($mailbox) && !self::supports($mailbox, 'MOVE')) {
            return self::copy_and_remove($mailbox, $folder, $uids, $dest);
        }
        $res = self::quietly(function () use ($mailbox, $folder, $uids, $dest) {
            return $mailbox->message_action($folder, 'MOVE', $uids, $dest);
        });
        if (empty($res['status'])) {
            return [];
        }
        $moved = array_fill_keys(array_map('strval', $uids), null);
        $map = self::copyuid_map(self::connection($mailbox)) ?? self::reported_map($res['responses'] ?? []);
        foreach ($map as $old => $new) {
            if (array_key_exists((string) $old, $moved)) {
                $moved[(string) $old] = $new;
            }
        }
        return $moved;
    }

    /**
     * Old uid => new uid from the COPYUID code (RFC 4315) of the last server answer.
     * Cypht only parses simple COPYUID values, this handles any uid set.
     * @param object|null $connection IMAP connection
     * @return array|null null when the answer has no usable COPYUID code
     */
    protected static function copyuid_map($connection) {
        if (!$connection || !method_exists($connection, 'show_debug')) {
            return null;
        }
        $debug = self::quietly(function () use ($connection) { return $connection->show_debug(true, true, true); });
        $responses = is_array($debug['responses'] ?? null) ? $debug['responses'] : [];
        $last = $responses ? end($responses) : [];
        foreach ((is_array($last) ? $last : []) as $line) {
            if (is_string($line) && preg_match('/\[COPYUID \d+ ([0-9:,]+) ([0-9:,]+)\]/i', $line, $matches)) {
                $old = self::expand_set($matches[1]);
                $new = self::expand_set($matches[2]);
                if ($old && count($old) === count($new)) {
                    return array_combine($old, $new);
                }
            }
        }
        return null;
    }

    /**
     * Old uid => new uid from the moves reported by Hm_Mailbox
     * @param array $responses list of ['oldUid', 'newUid']
     * @return array
     */
    protected static function reported_map($responses) {
        $map = [];
        foreach ((is_array($responses) ? $responses : []) as $response) {
            $old = (string) ($response['oldUid'] ?? '');
            $new = self::new_uid($response['newUid'] ?? null);
            if ($old !== '' && ctype_digit($old) && $new !== null) {
                $map[$old] = $new;
            }
        }
        return $map;
    }

    /**
     * Expand an IMAP uid set like 3:5,9
     * @param string $set uid set
     * @return array uids as strings, empty when the set is not valid
     */
    public static function expand_set($set) {
        $res = [];
        foreach (explode(',', (string) $set) as $part) {
            if (preg_match('/^(\d+):(\d+)$/', $part, $matches)) {
                if (abs((int) $matches[2] - (int) $matches[1]) > self::MAX_EMPTY) {
                    return [];
                }
                foreach (range((int) $matches[1], (int) $matches[2]) as $uid) {
                    $res[] = (string) $uid;
                }
            } elseif ($part !== '' && ctype_digit($part)) {
                $res[] = $part;
            } else {
                return [];
            }
        }
        return $res;
    }

    /**
     * MOVE for servers without the MOVE extension: copy, then remove only these messages
     * @return array uid => null for each moved message
     */
    protected static function copy_and_remove($mailbox, $folder, $uids, $dest) {
        if (!self::can_expunge($mailbox, $folder, $uids)) {
            return [];
        }
        $res = self::quietly(function () use ($mailbox, $folder, $uids, $dest) {
            return $mailbox->message_action($folder, 'COPY', $uids, $dest);
        });
        if (empty($res['status']) || !self::expunge($mailbox, $folder, $uids)) {
            return [];
        }
        return array_fill_keys($uids, null);
    }

    /**
     * @param mixed $value uid reported by the server
     * @return string|null
     */
    protected static function new_uid($value) {
        if (is_int($value) || (is_string($value) && $value !== '' && strlen($value) < 200 && !preg_match('/[\s\x00-\x1F]/', $value))) {
            return (string) $value;
        }
        return null;
    }

    /**
     * A plain EXPUNGE removes every message marked as deleted, also by other mail
     * clients. Without UIDPLUS it is only safe when no other message is marked.
     * @param object $mailbox Hm_Mailbox
     * @param string $folder folder
     * @param array $uids messages to remove
     * @return bool
     */
    public static function can_expunge($mailbox, $folder, $uids) {
        if (!self::connection($mailbox) || self::supports($mailbox, 'UIDPLUS')) {
            return true;
        }
        $marked = self::quietly(function () use ($mailbox, $folder) {
            return $mailbox->search($folder, 'DELETED', [], null, null, false);
        });
        return !array_diff(array_map('strval', is_array($marked) ? $marked : []), array_map('strval', $uids));
    }

    /**
     * Permanently remove messages from a folder
     * @param object $mailbox Hm_Mailbox
     * @param string $folder folder
     * @param array $uids existing message uids
     * @return bool
     */
    public static function expunge($mailbox, $folder, $uids) {
        $uids = array_values(array_filter(array_map('strval', $uids), 'ctype_digit'));
        if (!$uids) {
            return true;
        }
        if (!self::can_expunge($mailbox, $folder, $uids) || !self::set_flags($mailbox, $folder, $uids, 'DELETE')) {
            return false;
        }
        $connection = self::connection($mailbox);
        if ($connection && self::supports($mailbox, 'UIDPLUS') && method_exists($connection, 'send_command')) {
            foreach (array_chunk($uids, self::EXPUNGE_CHUNK) as $chunk) {
                $lines = self::quietly(function () use ($connection, $chunk) {
                    $connection->send_command('UID EXPUNGE '.implode(',', $chunk)."\r\n");
                    return $connection->get_response();
                });
                $last = is_array($lines) && $lines ? trim((string) end($lines)) : '';
                if (!preg_match('/^A\d+ OK\b/i', $last)) {
                    return false;
                }
            }
            if (method_exists($connection, 'bust_cache')) {
                self::quietly(function () use ($connection, $folder) { $connection->bust_cache($folder); });
            }
            return true;
        }
        return self::set_flags($mailbox, $folder, $uids, 'EXPUNGE');
    }

    /**
     * All message uids of a folder
     * @param object $mailbox Hm_Mailbox
     * @param string $folder folder
     * @return array
     */
    public static function all_uids($mailbox, $folder) {
        $uids = self::quietly(function () use ($mailbox, $folder) {
            return $mailbox->search($folder, 'ALL', [], null, null, false);
        });
        $uids = array_values(array_filter(array_map('strval', is_array($uids) ? $uids : []), 'ctype_digit'));
        usort($uids, function ($a, $b) { return (int) $a <=> (int) $b; });
        return $uids;
    }

    /* --------------------------------------------------------------- snooze */

    /**
     * Name of the Snoozed folder, created when asked
     * @param object $mailbox Hm_Mailbox
     * @param bool $create create the folder when it is missing
     * @return string|false
     */
    public static function snoozed_folder($mailbox, $create) {
        if (self::folder_exists($mailbox, self::SNOOZED_FOLDER)) {
            return self::SNOOZED_FOLDER;
        }
        if (!$create) {
            return false;
        }
        $created = self::quietly(function () use ($mailbox) {
            return $mailbox->create_folder(self::SNOOZED_FOLDER);
        });
        return is_string($created) && $created !== '' ? $created : false;
    }

    /**
     * Split a raw message into its header block and body
     * @param string $raw message source
     * @return array [header block without the blank line, body]
     */
    protected static function split($raw) {
        $raw = str_replace("\r\n", "\n", (string) $raw);
        $pos = strpos($raw, "\n\n");
        if ($pos === false) {
            return [$raw, ''];
        }
        return [substr($raw, 0, $pos), substr($raw, $pos + 2)];
    }

    /**
     * Read and remove the X-Snoozed header written by Cypht
     * @param string $raw message source
     * @return array [message source without the header, header values or null]
     */
    public static function take_snooze_header($raw) {
        list($head, $body) = self::split($raw);
        $values = null;
        $lines = explode("\n", $head);
        $kept = [];
        $in_header = false;
        foreach ($lines as $line) {
            if ($in_header && $line !== '' && ($line[0] === ' ' || $line[0] === "\t")) {
                $values .= ' '.trim($line);
                continue;
            }
            $in_header = false;
            if (stripos($line, 'X-Snoozed:') === 0) {
                $in_header = true;
                $values = trim(substr($line, strlen('X-Snoozed:')));
                continue;
            }
            $kept[] = $line;
        }
        $parsed = null;
        if ($values !== null) {
            $parsed = [];
            foreach (explode(';', $values) as $pair) {
                $pair = trim($pair);
                if ($pair === '') {
                    continue;
                }
                $space = strpos($pair, ' ');
                if ($space === false) {
                    $parsed[strtolower($pair)] = true;
                } else {
                    $parsed[strtolower(rtrim(substr($pair, 0, $space), ':'))] = trim(substr($pair, $space + 1));
                }
            }
        }
        return [self::crlf(implode("\n", $kept)."\n\n".$body), $parsed];
    }

    /**
     * Add the X-Snoozed header Cypht uses to wake messages
     * @param string $raw message source without an X-Snoozed header
     * @param int $until wake up time
     * @param string $from folder to return to
     * @param int $now current time
     * @return string message source
     */
    public static function add_snooze_header($raw, $until, $from, $now) {
        $from = str_replace(["\r", "\n", ';'], '', (string) $from);
        $header = sprintf("X-Snoozed: at %s; until %s;\n \tfrom %s", date('D, d M Y H:i:s O', $now), date('D, d M Y H:i O', $until), $from);
        return self::crlf($header."\n".str_replace("\r\n", "\n", (string) $raw));
    }

    /**
     * @param string $text text with any line endings
     * @return string text with CRLF line endings and a final CRLF
     */
    protected static function crlf($text) {
        return rtrim(str_replace("\n", "\r\n", str_replace("\r\n", "\n", $text)))."\r\n";
    }

    /**
     * Save a message in another folder and remove the old copy
     * @param object $mailbox Hm_Mailbox opened for writing
     * @param string $folder current folder
     * @param string $uid current uid
     * @param string $dest destination folder
     * @param string $raw message source to save
     * @param bool $seen save the message as read
     * @return string|null|false new uid, null when unknown, false on failure
     */
    public static function resave($mailbox, $folder, $uid, $dest, $raw, $seen) {
        $new = self::quietly(function () use ($mailbox, $dest, $raw, $seen) {
            return $mailbox->store_message($dest, $raw, $seen);
        });
        if (!$new) {
            return false;
        }
        $new = $new === true ? null : self::new_uid($new);
        if (!self::expunge($mailbox, $folder, [$uid])) {
            if ($new !== null) {
                /* do not leave a duplicate behind */
                self::expunge($mailbox, $dest, [$new]);
            }
            return false;
        }
        return $new;
    }
}

/**
 * Keeps Cypht tags pointing at messages after they move or are deleted. Tags store
 * message uids per account and folder: tag['server'][account][folder] = [uid, ...]
 * @subpackage mcp/lib
 */
class Hm_MCP_Tag_Refs {

    /**
     * @param mixed $tags value of the tags user setting
     * @param string $account account id
     * @param string $folder folder
     * @param array $uids message uids
     * @return bool true when a tag references one of the messages
     */
    public static function referenced($tags, $account, $folder, $uids) {
        foreach ((is_array($tags) ? $tags : []) as $tag) {
            $messages = $tag['server'][$account][$folder] ?? null;
            if (is_array($messages) && array_intersect(array_map('strval', $messages), array_map('strval', $uids))) {
                return true;
            }
        }
        return false;
    }

    /**
     * Point tags at the new location of moved messages
     * @param array $tags tags
     * @param string $account account id
     * @param string $folder old folder
     * @param string $dest new folder
     * @param array $moved old uid => new uid (null when unknown: the reference is dropped)
     * @return array [tags, changed]
     */
    public static function move($tags, $account, $folder, $dest, $moved) {
        $changed = false;
        foreach ((is_array($tags) ? $tags : []) as $id => $tag) {
            $messages = $tag['server'][$account][$folder] ?? null;
            if (!is_array($messages)) {
                continue;
            }
            $kept = [];
            $added = [];
            foreach ($messages as $uid) {
                if (array_key_exists((string) $uid, $moved)) {
                    if ($moved[(string) $uid] !== null) {
                        $added[] = $moved[(string) $uid];
                    }
                    continue;
                }
                $kept[] = $uid;
            }
            if (count($kept) === count($messages)) {
                continue;
            }
            $tags[$id]['server'][$account][$folder] = $kept;
            if ($added) {
                $existing = $tags[$id]['server'][$account][$dest] ?? [];
                $tags[$id]['server'][$account][$dest] = array_values(array_unique(array_merge(is_array($existing) ? $existing : [], $added)));
            }
            $changed = true;
        }
        return [$tags, $changed];
    }

    /**
     * Remove deleted messages from tags
     * @param array $tags tags
     * @param string $account account id
     * @param string $folder folder
     * @param array $uids deleted uids
     * @return array [tags, changed]
     */
    public static function remove($tags, $account, $folder, $uids) {
        return self::move($tags, $account, $folder, $folder, array_fill_keys(array_map('strval', $uids), null));
    }
}
