<?php

/**
 * Organize, trash and delete operations for the MCP server and REST API
 * @package modules
 * @subpackage mcp
 */

if (!defined('DEBUG_MODE')) { die(); }

/**
 * Batch operations on messages: flags, moves, archive, junk, snooze, trash,
 * restore, permanent deletion and emptying the trash or junk folder.
 * Every operation reports a result per message and never touches other messages.
 * @subpackage mcp/lib
 */
trait Hm_MCP_Organize {

    /* ---------------------------------------------------------------- flags */

    public function update_messages($args) {
        $read = $args['read'] ?? null;
        $flagged = $args['flagged'] ?? null;
        if ($read === null && $flagged === null) {
            throw new Hm_MCP_Error('invalid_argument', 'Set read, flagged or both.');
        }
        $changes = [];
        if ($read !== null) {
            $changes[] = [$read ? 'READ' : 'UNREAD', 'unread', !$read];
        }
        if ($flagged !== null) {
            $changes[] = [$flagged ? 'FLAG' : 'UNFLAG', 'flagged', (bool) $flagged];
        }
        $results = $this->each_folder($args['message_ids'], function ($mailbox, $account, $folder, $uids) use ($changes) {
            $existing = Hm_MCP_Imap::existing($mailbox, $folder, array_keys($uids));
            $res = [];
            $changed = [];
            $failed = [];
            foreach ($changes as list($action, $flag, $wanted)) {
                $todo = [];
                foreach ($existing as $uid => $info) {
                    if (Hm_MCP_Format::flags($info['flags'])[$flag] !== $wanted) {
                        $todo[] = (string) $uid;
                    }
                }
                if (!$todo) {
                    continue;
                }
                if (Hm_MCP_Imap::set_flags($mailbox, $folder, $todo, $action)) {
                    $changed = array_merge($changed, $todo);
                } else {
                    $failed = array_merge($failed, $todo);
                }
            }
            foreach ($uids as $uid => $id) {
                $uid = (string) $uid;
                if (!isset($existing[$uid])) {
                    $res[$id] = self::not_found_result($id);
                } elseif (in_array($uid, $failed, true)) {
                    $res[$id] = self::result($id, 'failed', ['reason' => 'The server did not change the message.']);
                } else {
                    $res[$id] = self::result($id, in_array($uid, $changed, true) ? 'ok' : 'unchanged', ['folder' => $folder]);
                }
            }
            return $res;
        });
        $what = [];
        if ($read !== null) {
            $what[] = $read ? 'read' : 'unread';
        }
        if ($flagged !== null) {
            $what[] = $flagged ? 'flagged' : 'not flagged';
        }
        return $this->batch_result($results, 'marked as '.implode(' and ', $what));
    }

    /* ---------------------------------------------------------------- moves */

    public function move_messages($args) {
        $target = (string) $args['folder'];
        if (strtolower($target) === 'trash' && !$this->principal->can('trash')) {
            throw new Hm_MCP_Error('permission_denied', 'Moving messages to the trash needs the "Move messages to the trash" permission.');
        }
        $ctx = $this->context();
        $results = $this->each_folder($args['message_ids'], function ($mailbox, $account, $folder, $uids) use ($target, $ctx) {
            $dest = $ctx->resolve_folder($account['id'], $target);
            if ($ctx->folder_role($account['id'], $dest) === 'trash' && !$this->principal->can('trash')) {
                throw new Hm_MCP_Error('permission_denied', 'Moving messages to the trash needs the "Move messages to the trash" permission.');
            }
            if ($dest !== $folder && !Hm_MCP_Imap::folder_exists($mailbox, $dest)) {
                return self::fail_all($uids, 'The destination folder does not exist in this account.');
            }
            return $this->move_group($mailbox, $account, $folder, $uids, $dest);
        });
        $this->audit['destination'] = in_array(strtolower($target), Hm_MCP_Context::ROLES, true) ? strtolower($target) : 'folder';
        return $this->batch_result($results, 'moved');
    }

    public function archive_messages($args) {
        $ctx = $this->context();
        $results = $this->each_folder($args['message_ids'], function ($mailbox, $account, $folder, $uids) use ($ctx) {
            $specials = $ctx->special_folders($account['id'], $mailbox, ['archive']);
            $dest = $specials['archive'] ?? null;
            if (!$dest && !empty($specials['all']) && Hm_MCP_Imap::is_gmail($mailbox)) {
                /* Gmail archives by removing the label: the message stays in All Mail */
                $dest = $specials['all'];
            }
            if (!$dest) {
                return self::fail_all($uids, 'This account has no archive folder. Choose one in Cypht under Settings, Folders.');
            }
            return $this->move_group($mailbox, $account, $folder, $uids, $dest);
        });
        return $this->batch_result($results, 'archived');
    }

    public function mark_junk($args) {
        $junk = (bool) $args['junk'];
        $ctx = $this->context();
        $results = $this->each_folder($args['message_ids'], function ($mailbox, $account, $folder, $uids) use ($ctx, $junk) {
            $junk_folder = $ctx->special_folders($account['id'], $mailbox, ['junk'])['junk'] ?? null;
            if (!$junk_folder) {
                return self::fail_all($uids, 'This account has no junk folder.');
            }
            if ($junk) {
                return $this->move_group($mailbox, $account, $folder, $uids, $junk_folder);
            }
            if ($folder !== $junk_folder) {
                return self::unchanged_all($uids, $folder, 'The message is not in the junk folder.');
            }
            return $this->move_group($mailbox, $account, $folder, $uids, 'INBOX');
        });
        return $this->batch_result($results, $junk ? 'marked as junk' : 'moved back to the inbox');
    }

    /* ---------------------------------------------------------------- trash */

    public function trash_messages($args) {
        $ctx = $this->context();
        $results = $this->each_folder($args['message_ids'], function ($mailbox, $account, $folder, $uids) use ($ctx) {
            $trash = $ctx->special_folders($account['id'], $mailbox, ['trash'])['trash'] ?? null;
            if (!$trash) {
                return self::fail_all($uids, 'This account has no trash folder, so nothing was deleted. Choose one in Cypht under Settings, Folders.');
            }
            if ($folder === $trash) {
                return self::unchanged_all($uids, $folder, 'The message is already in the trash. Use delete_messages_permanently to remove it for good.');
            }
            return $this->move_group($mailbox, $account, $folder, $uids, $trash);
        });
        return $this->batch_result($results, 'moved to the trash');
    }

    public function restore_messages($args) {
        $target = (string) ($args['folder'] ?? '');
        $ctx = $this->context();
        $results = $this->each_folder($args['message_ids'], function ($mailbox, $account, $folder, $uids) use ($ctx, $target) {
            $trash = $ctx->special_folders($account['id'], $mailbox, ['trash'])['trash'] ?? null;
            if (!$trash || $folder !== $trash) {
                return self::unchanged_all($uids, $folder, 'The message is not in the trash.');
            }
            $dest = $target === '' ? 'INBOX' : $ctx->resolve_folder($account['id'], $target);
            if ($dest === $trash) {
                throw new Hm_MCP_Error('invalid_argument', 'Choose a folder other than the trash.');
            }
            if (!Hm_MCP_Imap::folder_exists($mailbox, $dest)) {
                return self::fail_all($uids, 'The destination folder does not exist in this account.');
            }
            return $this->move_group($mailbox, $account, $folder, $uids, $dest);
        });
        return $this->batch_result($results, 'restored');
    }

    /* ------------------------------------------------------ permanent delete */

    public function delete_messages_permanently($args) {
        $results = $this->each_folder($args['message_ids'], function ($mailbox, $account, $folder, $uids) {
            if (!$mailbox->is_imap()) {
                return self::fail_all($uids, 'Permanent deletion is only available for IMAP accounts.');
            }
            $existing = Hm_MCP_Imap::existing($mailbox, $folder, array_keys($uids));
            $res = [];
            foreach ($uids as $uid => $id) {
                if (!isset($existing[(string) $uid])) {
                    $res[$id] = self::not_found_result($id);
                }
            }
            if (!$existing) {
                return $res;
            }
            foreach ($this->erase($mailbox, $account['id'], $folder, $existing) as $uid => $outcome) {
                $id = $uids[$uid];
                $res[$id] = $outcome === true ? self::result($id, 'ok') : self::result($id, 'failed', ['reason' => $outcome]);
            }
            return $res;
        });
        return $this->batch_result($results, 'deleted permanently');
    }

    /**
     * Delete messages for good, and remove them from Cypht tags. On Gmail, deleting a
     * message outside the trash only removes a label, so messages go through the trash.
     * @param object $mailbox mailbox opened for writing
     * @param string $account_id account id
     * @param string $folder folder of the messages
     * @param array $existing uid => details from Hm_MCP_Imap::existing()
     * @return array uid => true, or the reason the message was not deleted
     */
    protected function erase($mailbox, $account_id, $folder, $existing) {
        $found = array_map('strval', array_keys($existing));
        $res = [];
        $where = $folder;
        $targets = array_combine($found, $found);
        $trash = $this->context()->special_folders($account_id, $mailbox, ['trash'])['trash'] ?? null;
        if (Hm_MCP_Imap::is_gmail($mailbox) && $trash && $folder !== $trash) {
            $moved = Hm_MCP_Imap::move($mailbox, $folder, $found, $trash, self::message_id_headers($existing));
            $this->retag($account_id, $folder, $trash, $moved);
            $targets = [];
            foreach ($found as $uid) {
                if (!array_key_exists($uid, $moved)) {
                    $res[$uid] = 'The server did not delete the message.';
                } elseif ($moved[$uid] === null) {
                    $res[$uid] = 'The message was moved to the trash. Delete it from there to remove it for good.';
                } else {
                    $targets[$uid] = (string) $moved[$uid];
                }
            }
            $where = $trash;
        }
        if (!$targets) {
            return $res;
        }
        if (!Hm_MCP_Imap::can_expunge($mailbox, $where, array_values($targets))) {
            foreach (array_keys($targets) as $uid) {
                $res[$uid] = 'Other messages in this folder are marked for deletion by another mail program and this server cannot delete single messages. Nothing was deleted.';
            }
            return $res;
        }
        $deleted = Hm_MCP_Imap::expunge($mailbox, $where, array_values($targets));
        foreach (array_keys($targets) as $uid) {
            $res[$uid] = $deleted ? true : 'The server did not delete the message.';
        }
        if ($deleted) {
            $this->untag($account_id, $where, array_values($targets));
        }
        return $res;
    }

    public function empty_folder($args) {
        $ctx = $this->context();
        $account = $ctx->account($args['account_id']);
        $role = $args['folder'];
        $mailbox = $ctx->mailbox($account['id'], false);
        if (!$mailbox->is_imap()) {
            throw new Hm_MCP_Error('not_supported', 'Emptying folders is only available for IMAP accounts.');
        }
        $folder = $ctx->special_folders($account['id'], $mailbox, [$role])[$role] ?? null;
        if (!$folder) {
            throw new Hm_MCP_Error('not_found', sprintf('This account has no %s folder.', $role));
        }
        $uids = Hm_MCP_Imap::all_uids($mailbox, $folder);
        $batch = array_slice($uids, 0, Hm_MCP_Imap::MAX_EMPTY);
        if ($batch && !Hm_MCP_Imap::expunge($mailbox, $folder, $batch)) {
            throw new Hm_MCP_Error('upstream_error', 'The server did not delete the messages.', ['account_id' => $account['id']]);
        }
        if ($batch) {
            $this->untag($account['id'], $folder, $batch);
        }
        $remaining = count($uids) - count($batch);
        $this->audit = ['account_id' => $account['id'], 'folder' => $role, 'messages' => count($batch)];
        $label = $role === 'trash' ? 'trash' : 'junk folder';
        $message = $batch ? sprintf('%d message(s) deleted permanently from the %s.', count($batch), $label) : sprintf('The %s is already empty.', $label);
        if ($remaining > 0) {
            $message .= sprintf(' %d message(s) remain: run this again to continue.', $remaining);
        }
        return $this->ok($message, ['account_id' => $account['id'], 'folder' => $folder, 'deleted' => count($batch), 'remaining' => $remaining]);
    }

    /* --------------------------------------------------------------- snooze */

    /* preset name => relative time understood by strtotime, in the user's time zone */
    protected static $snooze_presets = [
        'later_today' => 'today 18:00',
        'tomorrow' => 'tomorrow 08:00',
        'next_weekend' => 'next saturday 08:00',
        'next_week' => 'next monday 08:00',
        'next_month' => 'first day of next month 08:00',
    ];

    public function snooze_messages($args) {
        $ctx = $this->context();
        $default = defined('DEFAULT_ENABLE_SNOOZE') ? DEFAULT_ENABLE_SNOOZE : false;
        if (!$ctx->user_config->get('enable_snooze_setting', $default)) {
            throw new Hm_MCP_Error('not_supported', 'Snooze is turned off in Cypht. Turn on "Enable snooze" in Cypht under Settings first.');
        }
        $now = time();
        $wake = strtolower(trim((string) $args['until'])) === 'now';
        $until = $wake ? null : self::snooze_time((string) $args['until'], $now);
        $results = $this->each_folder($args['message_ids'], function ($mailbox, $account, $folder, $uids) use ($ctx, $wake, $until, $now) {
            if (!$mailbox->is_imap()) {
                return self::fail_all($uids, 'Snooze is only available for IMAP accounts.');
            }
            $snoozed = Hm_MCP_Imap::snoozed_folder($mailbox, !$wake);
            if ($wake && $folder !== $snoozed) {
                return self::unchanged_all($uids, $folder, 'The message is not snoozed.');
            }
            if (!$snoozed) {
                return self::fail_all($uids, 'The Snoozed folder could not be created.');
            }
            /* read the messages without marking them as read, then write */
            $reader = $ctx->mailbox($account['id'], true);
            $existing = Hm_MCP_Imap::existing($reader, $folder, array_keys($uids));
            $sources = [];
            $res = [];
            foreach ($uids as $uid => $id) {
                $uid = (string) $uid;
                if (!isset($existing[$uid])) {
                    $res[$id] = self::not_found_result($id);
                } elseif ($existing[$uid]['size'] > Hm_MCP_Imap::MAX_SNOOZE_BYTES) {
                    $res[$id] = self::result($id, 'failed', ['reason' => 'The message is too large to snooze.']);
                } else {
                    $raw = Hm_MCP_Imap::quietly(function () use ($reader, $folder, $uid) { return $reader->get_message_content($folder, $uid); });
                    if (!is_string($raw) || trim($raw) === '') {
                        $res[$id] = self::result($id, 'failed', ['reason' => 'The message could not be read.']);
                    } else {
                        $sources[$uid] = $raw;
                    }
                }
            }
            $writer = $ctx->mailbox($account['id'], false);
            foreach ($sources as $uid => $raw) {
                $uid = (string) $uid;
                $id = $uids[$uid];
                list($clean, $header) = Hm_MCP_Imap::take_snooze_header($raw);
                $from = is_array($header) && !empty($header['from']) && is_string($header['from']) ? $header['from'] : $folder;
                if ($wake) {
                    $dest = Hm_MCP_Imap::folder_exists($writer, $from) ? $from : 'INBOX';
                    $source = $clean;
                    $seen = false;
                } else {
                    $dest = $snoozed;
                    $source = Hm_MCP_Imap::add_snooze_header($clean, $until, $from, $now);
                    $seen = !Hm_MCP_Format::flags($existing[$uid]['flags'])['unread'];
                }
                $new = Hm_MCP_Imap::resave($writer, $folder, $uid, $dest, $source, $seen);
                if ($new === false) {
                    $res[$id] = self::result($id, 'failed', ['reason' => 'The server did not save the message.']);
                    continue;
                }
                $this->retag($account['id'], $folder, $dest, [$uid => $new]);
                $res[$id] = self::result($id, 'ok', ['folder' => $dest,
                    'new_message_id' => $new === null ? null : Hm_MCP_Format::message_id($account['id'], $dest, $new)]);
            }
            return $res;
        });
        if ($wake) {
            return $this->batch_result($results, 'returned from snooze');
        }
        $result = $this->batch_result($results, 'snoozed until '.date('D, d M Y H:i', $until));
        $result['data']['until'] = date(DATE_ATOM, $until);
        return $result;
    }

    /**
     * @param string $value preset, date or ISO 8601 date-time
     * @param int $now current time
     * @return int wake up time
     * @throws Hm_MCP_Error
     */
    protected static function snooze_time($value, $now) {
        $value = strtolower(trim($value));
        if (isset(self::$snooze_presets[$value])) {
            $time = strtotime(self::$snooze_presets[$value], $now);
        } elseif (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            $time = strtotime($value.' 08:00');
        } else {
            $time = Hm_MCP_Format::parse_date_arg($value, 'until');
        }
        if ($time === false || $time < $now + 60) {
            throw new Hm_MCP_Error('invalid_argument', 'The snooze time must be in the future.');
        }
        if ($time > $now + 366 * 86400) {
            throw new Hm_MCP_Error('invalid_argument', 'Messages can be snoozed for one year at most.');
        }
        return $time;
    }

    /* -------------------------------------------------------------- helpers */

    /**
     * Run an action for each account and folder of a list of message ids
     * @param array $ids message ids
     * @param callable $action function($mailbox, $account, $folder, $uids) with
     *                         $uids uid => message id, returning message id => result
     * @return array results in the order of the ids
     */
    protected function each_folder($ids, $action) {
        $ctx = $this->context();
        $ids = array_values(array_unique(array_map('strval', $ids)));
        $groups = [];
        foreach ($ids as $id) {
            list($account_ref, $folder, $uid) = Hm_MCP_Format::parse_message_id($id);
            if (!ctype_digit($uid)) {
                throw new Hm_MCP_Error('invalid_argument', 'The message id is not valid. Use an id returned by list_messages or search_messages.');
            }
            $account = $ctx->account($account_ref);
            $groups[$account['id']][$folder][$uid] = $id;
        }
        $results = [];
        foreach ($groups as $account_id => $folders) {
            $account = $ctx->account($account_id);
            try {
                $mailbox = $ctx->mailbox($account_id, false);
            } catch (Hm_MCP_Error $e) {
                if (count($groups) === 1) {
                    throw $e;
                }
                foreach ($folders as $uids) {
                    $results += self::fail_all($uids, $e->getMessage());
                }
                continue;
            }
            foreach ($folders as $folder => $uids) {
                if ($ctx->time_left() < self::RESERVE_SECONDS) {
                    $results += self::fail_all($uids, 'Skipped to answer in time. Run the operation again for these messages.');
                    continue;
                }
                $results += $action($ctx->mailbox($account_id, false), $account, (string) $folder, $uids);
            }
        }
        $ordered = [];
        foreach ($ids as $id) {
            $ordered[] = $results[$id] ?? self::result($id, 'failed', ['reason' => 'The message was not processed.']);
        }
        $this->audit = array_merge($this->audit, ['accounts' => count($groups)]);
        return $ordered;
    }

    /**
     * Move the existing messages of one folder and report each one
     * @return array message id => result
     */
    protected function move_group($mailbox, $account, $folder, $uids, $dest) {
        if ($dest === $folder) {
            return self::unchanged_all($uids, $folder, 'The message is already in this folder.');
        }
        $existing = Hm_MCP_Imap::existing($mailbox, $folder, array_keys($uids));
        $found = array_map('strval', array_keys($existing));
        $moved = Hm_MCP_Imap::move($mailbox, $folder, $found, $dest, self::message_id_headers($existing));
        $res = [];
        foreach ($uids as $uid => $id) {
            $uid = (string) $uid;
            if (!in_array($uid, $found, true)) {
                $res[$id] = self::not_found_result($id);
            } elseif (!array_key_exists($uid, $moved)) {
                $res[$id] = self::result($id, 'failed', ['reason' => 'The server did not move the message.']);
            } else {
                $new = $moved[$uid];
                $res[$id] = self::result($id, 'ok', ['folder' => $dest,
                    'new_message_id' => $new === null ? null : Hm_MCP_Format::message_id($account['id'], $dest, $new)]);
            }
        }
        $this->retag($account['id'], $folder, $dest, $moved);
        return $res;
    }

    /**
     * Keep Cypht tags on messages that moved
     * @param string $account_id account id
     * @param string $folder old folder
     * @param string $dest new folder
     * @param array $moved old uid => new uid or null
     * @return void
     */
    protected function retag($account_id, $folder, $dest, $moved) {
        $ctx = $this->context();
        if (!$moved || !$ctx->module_is_supported('tags') ||
            !Hm_MCP_Tag_Refs::referenced($ctx->user_config->get('tags', []), $account_id, $folder, array_keys($moved))) {
            return;
        }
        try {
            $ctx->update_user_settings(function ($config) use ($account_id, $folder, $dest, $moved) {
                list($tags, $changed) = Hm_MCP_Tag_Refs::move($config->get('tags', []), $account_id, $folder, $dest, $moved);
                if (!$changed) {
                    return [];
                }
                $config->set('tags', $tags);
                return ['tags'];
            });
            $this->audit['tags_updated'] = true;
        } catch (Exception $e) {
            Hm_Debug::add('MCP could not update tags: '.$e->getMessage(), 'warning');
            $this->audit['tags_updated'] = false;
        }
    }

    /**
     * Remove Cypht tags from deleted messages
     * @return void
     */
    protected function untag($account_id, $folder, $uids) {
        $this->retag($account_id, $folder, $folder, array_fill_keys(array_map('strval', $uids), null));
    }

    /**
     * @param array $existing uid => message details from Hm_MCP_Imap::existing()
     * @return array uid => Message-ID header
     */
    protected static function message_id_headers($existing) {
        $res = [];
        foreach ($existing as $uid => $info) {
            $res[(string) $uid] = $info['message_id'] ?? '';
        }
        return $res;
    }

    /**
     * @param string $id message id
     * @param string $status ok, unchanged, not_found or failed
     * @param array $extra folder, new_message_id, reason
     * @return array
     */
    protected static function result($id, $status, $extra = []) {
        return array_merge(['message_id' => $id, 'status' => $status], $extra);
    }

    protected static function not_found_result($id) {
        return self::result($id, 'not_found', ['reason' => 'Message not found. It may have been moved or deleted.']);
    }

    protected static function fail_all($uids, $reason) {
        $res = [];
        foreach ($uids as $id) {
            $res[$id] = self::result($id, 'failed', ['reason' => $reason]);
        }
        return $res;
    }

    protected static function unchanged_all($uids, $folder, $reason) {
        $res = [];
        foreach ($uids as $id) {
            $res[$id] = self::result($id, 'unchanged', ['folder' => $folder, 'reason' => $reason]);
        }
        return $res;
    }

    /**
     * Standard batch result with counts for the activity log
     * @param array $results results in input order
     * @param string $verb past participle for the summary, like "moved"
     * @return array
     */
    protected function batch_result($results, $verb) {
        $counts = array_count_values(array_column($results, 'status'));
        $ok = $counts['ok'] ?? 0;
        $failed = ($counts['failed'] ?? 0) + ($counts['not_found'] ?? 0);
        $unchanged = $counts['unchanged'] ?? 0;
        $this->audit = array_merge($this->audit, ['messages' => $ok, 'failed' => $failed]);
        $parts = [sprintf('%d message(s) %s', $ok, $verb)];
        if ($unchanged) {
            $parts[] = sprintf('%d unchanged', $unchanged);
        }
        if ($failed) {
            $parts[] = sprintf('%d failed (see results)', $failed);
        }
        return $this->ok(implode(', ', $parts).'.', ['results' => $results, 'succeeded' => $ok, 'failed' => $failed]);
    }
}
