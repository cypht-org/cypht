<?php

/**
 * Sending and scheduled sending for the MCP server and REST API
 * @package modules
 * @subpackage mcp
 */

if (!defined('DEBUG_MODE')) { die(); }

/**
 * Send messages and drafts, schedule them, and run the scheduled sends.
 * Scheduled messages use the Cypht format: they wait in the Scheduled folder with
 * an X-Schedule header, so Cypht shows and sends them too.
 * @subpackage mcp/lib
 */
trait Hm_MCP_Send {

    /* scheduled messages handled by one manage_scheduled call */
    protected static $max_manage = 20;

    /* scheduled messages listed per account */
    protected static $max_scheduled_listed = 200;

    public function send_message($args) {
        $this->check_send_limit();
        list($account, $sender, $spec, $original, $mode) = $this->compose($args);
        self::require_recipients($spec['to'], $spec['cc'], $spec['bcc']);
        $raw = Hm_MCP_Mime::build($spec);
        $audit = ['account_id' => $account['id'], 'mode' => $mode] + self::recipient_audit(array_merge($spec['to'], $spec['cc'], $spec['bcc']))
            + ['attachments' => count($spec['attachments'])];
        if (($args['send_at'] ?? '') !== '') {
            $when = self::future_time($args['send_at'], time(), 'send_at');
            list($folder, $uid) = $this->store_scheduled($account['id'], self::scheduled_copy($raw, $when, $sender), $spec['message_id']);
            $this->audit = $audit + ['scheduled' => true];
            return $this->ok(sprintf('Message "%s" scheduled for %s.', mb_substr($spec['subject'], 0, 80), date('D, d M Y H:i', $when)),
                $this->send_data('scheduled', $account['id'], $spec, ['scheduled_id' => $uid === null ? null
                    : Hm_MCP_Format::message_id($account['id'], $folder, $uid), 'send_at' => date(DATE_ATOM, $when)]));
        }
        $answered = $original && $mode !== 'forward' ? [$original['account_id'], $original['folder'], $original['uid']] : null;
        $sent = $this->deliver($account['id'], $sender, $raw, self::envelope($spec['to'], $spec['cc'], $spec['bcc']), $answered, []);
        $this->audit = $audit + ['scheduled' => false];
        return $this->ok(sprintf('Message "%s" sent to %d recipient(s).', mb_substr($spec['subject'], 0, 80), $audit['recipients']),
            $this->send_data('sent', $account['id'], $spec, $sent));
    }

    public function send_draft($args) {
        $this->check_send_limit();
        list($account, $folder, $uid) = $this->draft_location($args['draft_id']);
        $raw = $this->read_raw($account['id'], $folder, $uid, 'Draft not found. It may have been sent or deleted, or saved again with a new id.');
        $parsed = Hm_MCP_Mime::parse($raw);
        $sender = $this->message_sender($account['id'], $parsed, '');
        self::require_recipients($parsed['to'], $parsed['cc'], $parsed['bcc']);
        $spec = self::parsed_spec($parsed, $sender);
        $audit = ['account_id' => $account['id']] + self::recipient_audit(array_merge($parsed['to'], $parsed['cc'], $parsed['bcc']))
            + ['attachments' => count($parsed['attachments'])];
        if (($args['send_at'] ?? '') !== '') {
            $when = self::future_time($args['send_at'], time(), 'send_at');
            $message_id = (string) (Hm_MCP_Mime::clean_ids(Hm_MCP_Imap::header_value($raw, 'Message-ID'))[0] ?? '');
            list($scheduled_folder, $scheduled_uid) = $this->store_scheduled($account['id'], self::scheduled_copy($raw, $when, $sender), $message_id);
            $removed = $this->remove_message($account['id'], $folder, $uid);
            $this->audit = $audit + ['scheduled' => true];
            $extra = ['scheduled_id' => $scheduled_uid === null ? null : Hm_MCP_Format::message_id($account['id'], $scheduled_folder, $scheduled_uid),
                'send_at' => date(DATE_ATOM, $when)];
            if (!$removed) {
                $extra['warnings'] = ['The draft could not be removed from the drafts folder.'];
            }
            return $this->ok(sprintf('Draft "%s" scheduled for %s.', mb_substr($spec['subject'], 0, 80), date('D, d M Y H:i', $when)),
                $this->send_data('scheduled', $account['id'], $spec, $extra));
        }
        $sent = $this->deliver($account['id'], $sender, $raw, self::envelope($parsed['to'], $parsed['cc'], $parsed['bcc']), null, $parsed['in_reply_to']);
        if (!$this->remove_message($account['id'], $folder, $uid)) {
            $sent['warnings'][] = 'The message was sent, but the draft could not be removed from the drafts folder.';
        }
        $this->audit = $audit + ['scheduled' => false];
        return $this->ok(sprintf('Draft "%s" sent to %d recipient(s).', mb_substr($spec['subject'], 0, 80), $audit['recipients']),
            $this->send_data('sent', $account['id'], $spec, $sent));
    }

    public function list_scheduled($args) {
        $ctx = $this->context();
        $accounts = ($args['account_id'] ?? '') !== '' ? [$ctx->account($args['account_id'])] : $ctx->accounts(true);
        $messages = [];
        $errors = [];
        foreach ($accounts as $account) {
            if ($ctx->time_left() < self::RESERVE_SECONDS) {
                $errors[] = self::time_error($account['id']);
                continue;
            }
            try {
                $mailbox = $ctx->mailbox($account['id'], true);
                $folder = $mailbox->is_imap() ? Hm_MCP_Imap::scheduled_folder($mailbox, false) : false;
                if (!$folder) {
                    continue;
                }
                foreach (Hm_MCP_Imap::scheduled($mailbox, $folder, self::$max_scheduled_listed) as $uid => $entry) {
                    $time = Hm_MCP_Format::timestamp($entry['x_schedule']);
                    $messages[] = [
                        'id' => Hm_MCP_Format::message_id($account['id'], $folder, $uid),
                        'account_id' => $account['id'],
                        'subject' => Hm_MCP_Format::text($entry['subject']),
                        'from' => Hm_MCP_Format::address($entry['from']),
                        'to' => Hm_MCP_Format::addresses($entry['to']),
                        'send_at' => $time === false ? null : date(DATE_ATOM, $time),
                        '_ts' => $time === false ? PHP_INT_MAX : $time,
                    ];
                }
            } catch (Hm_MCP_Error $e) {
                $errors[] = ['account_id' => $account['id'], 'code' => $e->error_code, 'message' => $e->getMessage()];
            }
        }
        usort($messages, function ($a, $b) { return $a['_ts'] <=> $b['_ts']; });
        foreach ($messages as &$message) {
            unset($message['_ts']);
        }
        unset($message);
        $this->audit = ['accounts' => count($accounts), 'messages' => count($messages)];
        return $this->ok(sprintf('%d scheduled message(s).', count($messages)), ['messages' => $messages, 'errors' => $errors]);
    }

    public function manage_scheduled($args) {
        $action = (string) $args['action'];
        if (count($args['message_ids']) > self::$max_manage) {
            throw new Hm_MCP_Error('invalid_argument', sprintf('At most %d scheduled messages can be changed at once.', self::$max_manage));
        }
        $when = null;
        if ($action === 'reschedule') {
            if (($args['send_at'] ?? '') === '') {
                throw new Hm_MCP_Error('invalid_argument', 'send_at is required to reschedule.');
            }
            $when = self::future_time($args['send_at'], time(), 'send_at');
        }
        $ctx = $this->context();
        $results = $this->each_folder($args['message_ids'], function ($mailbox, $account, $folder, $uids) use ($ctx, $action, $when) {
            $scheduled = $mailbox->is_imap() ? Hm_MCP_Imap::scheduled_folder($mailbox, false) : false;
            if (!$scheduled || $folder !== $scheduled) {
                return self::fail_all($uids, 'This is not a scheduled message. Use an id from list_scheduled.');
            }
            $res = [];
            foreach ($uids as $uid => $id) {
                $uid = (string) $uid;
                try {
                    $raw = $this->read_raw($account['id'], $folder, $uid, 'Message not found. It may have been sent already.');
                } catch (Hm_MCP_Error $e) {
                    $res[$id] = $e->error_code === 'not_found' ? self::not_found_result($id) : self::result($id, 'failed', ['reason' => $e->getMessage()]);
                    continue;
                }
                $message_id = (string) (Hm_MCP_Mime::clean_ids(Hm_MCP_Imap::header_value($raw, 'Message-ID'))[0] ?? '');
                try {
                    if ($action === 'cancel') {
                        list($drafts, $new) = $this->store_draft($account['id'], Hm_MCP_Imap::strip_headers($raw, ['X-Schedule', 'X-Profile-ID']), $message_id);
                        $this->remove_message($account['id'], $folder, $uid);
                        $res[$id] = self::result($id, 'ok', ['folder' => $drafts,
                            'new_message_id' => $new === null ? null : Hm_MCP_Format::message_id($account['id'], $drafts, $new)]);
                    } elseif ($action === 'reschedule') {
                        $sender = $this->message_sender($account['id'], Hm_MCP_Mime::parse($raw), (string) Hm_MCP_Imap::header_value($raw, 'X-Profile-ID'));
                        list($target, $new) = $this->store_scheduled($account['id'], self::scheduled_copy($raw, $when, $sender), $message_id);
                        if ($new === null) {
                            throw new Hm_MCP_Error('upstream_error', 'The replacement was saved, but its message id could not be confirmed. The original scheduled message was left unchanged; check list_scheduled for duplicates.');
                        }
                        if (!$this->remove_message($account['id'], $folder, $uid)) {
                            $rolled_back = $this->remove_message($account['id'], $target, $new);
                            $reason = 'The previous scheduled message could not be removed, so rescheduling was not completed.';
                            if (!$rolled_back) {
                                $reason .= ' The replacement may also remain scheduled; check list_scheduled before sending.';
                            }
                            throw new Hm_MCP_Error('upstream_error', $reason);
                        }
                        $res[$id] = self::result($id, 'ok', ['folder' => $target,
                            'new_message_id' => Hm_MCP_Format::message_id($account['id'], $target, $new)]);
                    } else {
                        $this->check_send_limit();
                        $this->send_scheduled_copy($account['id'], $folder, $uid, $raw);
                        $res[$id] = self::result($id, 'ok');
                    }
                } catch (Hm_MCP_Error $e) {
                    $res[$id] = self::result($id, 'failed', ['reason' => $e->getMessage()]);
                }
            }
            return $res;
        });
        $verbs = ['cancel' => 'moved back to the drafts', 'reschedule' => 'rescheduled', 'send_now' => 'sent'];
        return $this->batch_result($results, $verbs[$action]);
    }

    /**
     * Send the scheduled messages that are due, for the runner endpoint
     * @param int $grace seconds a message must be overdue, leaving Cypht in an open browser time to send it first
     * @param int $max most messages sent in one run
     * @return array ['sent' => list, 'failed' => list, 'waiting' => int, 'errors' => list]
     */
    public function run_scheduled($grace, $max) {
        $ctx = $this->context();
        $store = $this->services->store();
        $now = time();
        $report = ['sent' => [], 'failed' => [], 'waiting' => 0, 'errors' => []];
        foreach ($ctx->accounts(true) as $account) {
            if ($ctx->time_left() < self::RESERVE_SECONDS || count($report['sent']) + count($report['failed']) >= $max) {
                $report['errors'][] = self::time_error($account['id']);
                continue;
            }
            try {
                $mailbox = $ctx->mailbox($account['id'], true);
                $folder = $mailbox->is_imap() ? Hm_MCP_Imap::scheduled_folder($mailbox, false) : false;
                if (!$folder) {
                    continue;
                }
                $entries = Hm_MCP_Imap::scheduled($mailbox, $folder, self::$max_scheduled_listed);
            } catch (Hm_MCP_Error $e) {
                $report['errors'][] = ['account_id' => $account['id'], 'code' => $e->error_code, 'message' => $e->getMessage()];
                continue;
            }
            foreach ($entries as $uid => $entry) {
                $time = Hm_MCP_Format::timestamp($entry['x_schedule']);
                if ($time === false) {
                    continue;
                }
                if ($time > $now - $grace) {
                    $report['waiting']++;
                    continue;
                }
                if ($ctx->time_left() < self::RESERVE_SECONDS || count($report['sent']) + count($report['failed']) >= $max) {
                    $report['waiting']++;
                    continue;
                }
                /* one sender per message, even with several runners or retries */
                $key = implode('|', ['scheduled', $this->principal->username, $account['id'],
                    $entry['message_id'] !== '' ? $entry['message_id'] : $folder.'/'.$uid.'/'.$entry['x_schedule']]);
                if (!$store->claim($key, 86400)) {
                    continue;
                }
                try {
                    $raw = $this->read_raw($account['id'], $folder, (string) $uid, 'Message not found. It may have been sent already.');
                    $info = $this->send_scheduled_copy($account['id'], $folder, (string) $uid, $raw);
                } catch (Hm_MCP_Error $e) {
                    /* try again in ten minutes */
                    $store->release($key, 86400, 600);
                    $report['failed'][] = ['account_id' => $account['id'], 'code' => $e->error_code, 'message' => $e->getMessage()];
                    continue;
                }
                $report['sent'][] = ['account_id' => $account['id']] + $info;
            }
        }
        return $report;
    }

    /* -------------------------------------------------------------- helpers */

    /**
     * Send a message from the Scheduled folder and remove it
     * @param string $account_id account id
     * @param string $folder scheduled folder
     * @param string $uid message uid
     * @param string $raw message source
     * @return array recipients and domains for the activity log
     * @throws Hm_MCP_Error
     */
    protected function send_scheduled_copy($account_id, $folder, $uid, $raw) {
        $parsed = Hm_MCP_Mime::parse($raw);
        $sender = $this->message_sender($account_id, $parsed, (string) Hm_MCP_Imap::header_value($raw, 'X-Profile-ID'));
        self::require_recipients($parsed['to'], $parsed['cc'], $parsed['bcc']);
        $this->deliver($account_id, $sender, $raw, self::envelope($parsed['to'], $parsed['cc'], $parsed['bcc']), null, $parsed['in_reply_to']);
        if (!$this->remove_message($account_id, $folder, $uid)) {
            /* never send it again: hide it from the next runs */
            Hm_MCP_Imap::set_flags($this->context()->mailbox($account_id, false), $folder, [$uid], 'DELETE');
        }
        return self::recipient_audit(array_merge($parsed['to'], $parsed['cc'], $parsed['bcc']));
    }

    /**
     * Send a message with the SMTP server of the sender, then save a copy in the sent
     * folder and mark the message it answers
     * @param string $account_id account id
     * @param array $sender sender from Hm_MCP_Context::senders()
     * @param string $raw message source, which may contain Bcc headers
     * @param array $recipients envelope recipients
     * @param array|null $answered [account id, folder, uid] of the message answered
     * @param array $in_reply_to Message-ID values the message answers, used when $answered is null
     * @return array ['sent_message_id', 'warnings']
     * @throws Hm_MCP_Error
     */
    protected function deliver($account_id, $sender, $raw, $recipients, $answered, $in_reply_to) {
        $ctx = $this->context();
        $smtp_id = $sender['smtp_id'] !== '' && $ctx->smtp_exists($sender['smtp_id']) ? $sender['smtp_id'] : $ctx->smtp_for($account_id);
        if ($smtp_id === false) {
            throw new Hm_MCP_Error('not_supported', 'This account cannot send mail: it has no SMTP server. Add one in Cypht under Settings, Servers.');
        }
        $smtp = $ctx->smtp($smtp_id);
        $now = time();
        $outgoing = Hm_MCP_Imap::outgoing($raw, $now);
        $error = Hm_MCP_Imap::quietly(function () use ($smtp, $sender, $recipients, $outgoing) {
            return $smtp->send_message($sender['email'], $recipients, $outgoing);
        });
        if ($error) {
            throw new Hm_MCP_Error('upstream_error', 'The SMTP server did not accept the message: '.Hm_MCP_Format::text(is_string($error) ? $error : 'unknown error'));
        }
        $warnings = [];
        $default = defined('DEFAULT_SMTP_AUTO_BCC') ? DEFAULT_SMTP_AUTO_BCC : false;
        if ($ctx->user_config->get('smtp_auto_bcc_setting', $default)) {
            /* a copy for the sender, hidden from message lists by Cypht */
            Hm_MCP_Imap::quietly(function () use ($smtp, $sender, $outgoing) {
                return $smtp->send_message($sender['email'], [$sender['email']], "X-Auto-Bcc: cypht\r\n".$outgoing);
            });
        }
        unset($outgoing);
        $sent_id = null;
        try {
            $mailbox = $ctx->mailbox($account_id, false);
            if ($mailbox->is_imap() && !Hm_MCP_Imap::is_gmail($mailbox)) {
                /* Gmail saves a copy of messages sent with its SMTP server by itself */
                $sent = $ctx->special_folders($account_id, $mailbox, ['sent'])['sent'] ?? null;
                if ($sent) {
                    $copy = Hm_MCP_Imap::set_header(Hm_MCP_Imap::strip_headers($raw, ['X-Schedule', 'X-Profile-ID']), 'Date', date('r', $now));
                    $stored = Hm_MCP_Imap::quietly(function () use ($mailbox, $sent, $copy) { return $mailbox->store_message($sent, $copy, true); });
                    if (!$stored) {
                        $warnings[] = 'The message was sent, but the copy could not be saved in the sent folder.';
                    } elseif ($stored !== true && Hm_MCP_Imap::new_uid($stored) !== null) {
                        $sent_id = Hm_MCP_Format::message_id($account_id, $sent, Hm_MCP_Imap::new_uid($stored));
                    }
                } else {
                    $warnings[] = 'The message was sent. This account has no sent folder, so no copy was saved.';
                }
            }
            $this->mark_answered($account_id, $answered, $in_reply_to);
        } catch (Hm_MCP_Error $e) {
            $warnings[] = 'The message was sent, but: '.$e->getMessage();
        }
        return ['sent_message_id' => $sent_id, 'warnings' => $warnings];
    }

    /**
     * Mark the message a reply answers
     * @return void
     */
    protected function mark_answered($account_id, $answered, $in_reply_to) {
        $ctx = $this->context();
        if ($answered) {
            list($answered_account, $folder, $uid) = $answered;
            Hm_MCP_Imap::set_flags($ctx->mailbox($answered_account, false), $folder, [(string) $uid], 'ANSWERED');
            return;
        }
        $ids = Hm_MCP_Mime::clean_ids($in_reply_to);
        if (!$ids) {
            return;
        }
        $mailbox = $ctx->mailbox($account_id, false);
        $found = Hm_MCP_Imap::quietly(function () use ($mailbox, $ids) {
            return $mailbox->search('INBOX', 'ALL', [['HEADER Message-ID', $ids[0]]]);
        });
        if (is_array($found) && count($found) === 1) {
            Hm_MCP_Imap::set_flags($mailbox, 'INBOX', [(string) reset($found)], 'ANSWERED');
        }
    }

    /**
     * Save a message in the Scheduled folder
     * @return array [folder, uid or null when unknown]
     * @throws Hm_MCP_Error
     */
    protected function store_scheduled($account_id, $raw, $message_id) {
        $mailbox = $this->context()->mailbox($account_id, false);
        if (!$mailbox->is_imap()) {
            throw new Hm_MCP_Error('not_supported', 'Scheduled sending is only available for IMAP accounts.');
        }
        $folder = Hm_MCP_Imap::scheduled_folder($mailbox, true);
        if (!$folder) {
            throw new Hm_MCP_Error('upstream_error', 'The Scheduled folder could not be created.');
        }
        $stored = Hm_MCP_Imap::quietly(function () use ($mailbox, $folder, $raw) { return $mailbox->store_message($folder, $raw, false, true); });
        if (!$stored) {
            throw new Hm_MCP_Error('upstream_error', 'The mail server did not save the scheduled message.');
        }
        $uid = $stored === true ? null : Hm_MCP_Imap::new_uid($stored);
        if ($uid === null && $message_id !== '') {
            $found = Hm_MCP_Imap::quietly(function () use ($mailbox, $folder, $message_id) {
                return $mailbox->search($folder, 'ALL', [['HEADER Message-ID', $message_id]]);
            });
            if (is_array($found) && count($found) === 1) {
                $uid = Hm_MCP_Imap::new_uid(reset($found));
            }
        }
        return [$folder, $uid];
    }

    /**
     * Copy of a message for the Scheduled folder, in the format Cypht reads
     * @param string $raw message source
     * @param int $when send time
     * @param array $sender sending profile
     * @return string
     */
    protected static function scheduled_copy($raw, $when, $sender) {
        $raw = Hm_MCP_Imap::strip_headers($raw, ['X-Schedule', 'X-Profile-ID']);
        if (($sender['profile_id'] ?? '') !== '' && preg_match('/^[A-Za-z0-9._-]{1,64}$/', $sender['profile_id'])) {
            $raw = Hm_MCP_Imap::set_header($raw, 'X-Profile-ID', $sender['profile_id']);
        }
        return Hm_MCP_Imap::set_header($raw, 'X-Schedule', date('D, d M Y H:i O', $when));
    }

    /**
     * Full source of a message, read without marking it as read
     * @return string
     * @throws Hm_MCP_Error
     */
    protected function read_raw($account_id, $folder, $uid, $not_found) {
        $reader = $this->context()->mailbox($account_id, true);
        $existing = Hm_MCP_Imap::existing($reader, $folder, [(string) $uid]);
        if (!isset($existing[(string) $uid])) {
            throw new Hm_MCP_Error('not_found', $not_found);
        }
        if ($existing[(string) $uid]['size'] > self::$max_draft_bytes) {
            throw new Hm_MCP_Error('payload_too_large', 'This message is too large to send from here. Open it in Cypht.');
        }
        $raw = Hm_MCP_Imap::quietly(function () use ($reader, $folder, $uid) { return $reader->get_message_content($folder, $uid); });
        if (!is_string($raw) || trim($raw) === '') {
            throw new Hm_MCP_Error('upstream_error', 'The message could not be read.');
        }
        return $raw;
    }

    /**
     * Delete one message for good
     * @return bool
     */
    protected function remove_message($account_id, $folder, $uid) {
        $mailbox = $this->context()->mailbox($account_id, false);
        $existing = Hm_MCP_Imap::existing($mailbox, $folder, [(string) $uid]);
        if (!$existing) {
            return true;
        }
        return ($this->erase($mailbox, $account_id, $folder, $existing)[(string) $uid] ?? false) === true;
    }

    /**
     * The sending profile of a stored message
     * @param string $account_id account id
     * @param array $parsed message from Hm_MCP_Mime::parse()
     * @param string $profile_id X-Profile-ID header written by Cypht
     * @return array sender
     * @throws Hm_MCP_Error
     */
    protected function message_sender($account_id, $parsed, $profile_id) {
        $senders = $this->context()->senders($account_id);
        $email = $parsed['from']['email'] ?? '';
        foreach ($senders as $sender) {
            if ($profile_id !== '' && $sender['profile_id'] === trim($profile_id) && ($email === '' || strcasecmp($sender['email'], $email) === 0)) {
                return $sender;
            }
        }
        if ($email === '') {
            return $this->sender($account_id, '');
        }
        $sender = $this->find_sender($account_id, $email);
        if (!$sender) {
            throw new Hm_MCP_Error('invalid_argument', sprintf('The message is written as %s, which is not an address this account sends as (%s).',
                $email, $senders ? implode(', ', array_column($senders, 'email')) : 'none'));
        }
        return $sender;
    }

    /**
     * Spec values of a parsed draft, for the result data
     * @return array
     */
    protected static function parsed_spec($parsed, $sender) {
        return [
            'from' => $parsed['from'] ?: ['name' => $sender['name'], 'email' => $sender['email']],
            'to' => $parsed['to'], 'cc' => $parsed['cc'], 'bcc' => $parsed['bcc'],
            'subject' => $parsed['subject'],
            'attachments' => $parsed['attachments'],
        ];
    }

    /**
     * @param array ...$lists lists of addresses
     * @return void
     * @throws Hm_MCP_Error
     */
    protected static function require_recipients(...$lists) {
        if (!self::envelope(...$lists)) {
            throw new Hm_MCP_Error('invalid_argument', 'The message has no recipients.');
        }
    }

    /**
     * @param array ...$lists lists of addresses
     * @return array unique recipient addresses
     */
    protected static function envelope(...$lists) {
        return array_values(array_unique(array_map('strtolower', array_column(Hm_MCP_Mime::unique(array_merge(...$lists)), 'email'))));
    }

    /**
     * Recipient count and domains, the only recipient details kept in the activity log
     * @param array $addresses recipients
     * @return array
     */
    protected static function recipient_audit($addresses) {
        $emails = self::envelope($addresses);
        $domains = array_values(array_unique(array_map(function ($email) { return substr((string) strrchr($email, '@'), 1); }, $emails)));
        return ['recipients' => count($emails), 'domains' => implode(', ', array_slice($domains, 0, 10))];
    }

    /**
     * Limit how much a connection and a user can send
     * @return void
     * @throws Hm_MCP_Error
     */
    protected function check_send_limit() {
        $store = $this->services->store();
        $config = $this->services->config;
        if (!$store->rate_limit('send:'.$this->principal->connection_id(), $config->int('send_per_hour', 30), 3600)
            || !$store->rate_limit('send_day:'.$this->principal->username, $config->int('send_per_day', 200), 86400)) {
            throw new Hm_MCP_Error('rate_limited', 'The limit of messages sent through the API was reached. Try again later.');
        }
    }

    /**
     * Result data of a sent or scheduled message
     * @return array
     */
    protected function send_data($status, $account_id, $spec, $extra) {
        return array_merge([
            'status' => $status,
            'account_id' => (string) $account_id,
            'from' => $spec['from'],
            'to' => $spec['to'],
            'cc' => $spec['cc'],
            'bcc' => $spec['bcc'],
            'subject' => $spec['subject'],
            'attachments' => array_map(function ($attachment) {
                return ['filename' => Hm_MCP_Mime::filename($attachment['filename']),
                    'content_type' => Hm_MCP_Mime::content_type($attachment['content_type']), 'size' => strlen($attachment['data'])];
            }, $spec['attachments']),
            'sent_message_id' => null,
            'scheduled_id' => null,
            'send_at' => null,
            'warnings' => [],
        ], $extra);
    }
}

/**
 * Runs the scheduled sends of one user: POST /api/v1/scheduled/run with a runner token.
 * Cypht only sends scheduled messages while it is open in a browser; a scheduled task
 * calling this endpoint every minute sends them when Cypht is closed.
 * @subpackage mcp/lib
 */
class Hm_MCP_Scheduler {

    /* runs accepted per runner token and hour */
    const RUNS_PER_HOUR = 180;

    /* messages sent in one run */
    const MAX_PER_RUN = 25;

    private $services;

    /**
     * @param Hm_MCP_Services $services services
     */
    public function __construct($services) {
        $this->services = $services;
    }

    /**
     * @param Hm_MCP_Http_Request $request request details
     * @return Hm_MCP_Http_Response
     */
    public function run($request) {
        if ($request->method !== 'POST') {
            return Hm_MCP_Http_Response::error(405, 'method_not_allowed', 'Method not allowed', ['Allow' => 'POST, OPTIONS']);
        }
        $token = $request->bearer_token();
        if ($token === false) {
            return Hm_MCP_Http_Response::error(401, 'unauthorized', 'A runner token is required. Create one in Cypht under Settings, API and MCP.',
                ['WWW-Authenticate' => 'Bearer realm="cypht"']);
        }
        $auth = $this->services->auth()->authenticate($token, ['runner']);
        if (!$auth['ok']) {
            $headers = $auth['status'] === 401 ? ['WWW-Authenticate' => 'Bearer realm="cypht", error="invalid_token"'] : [];
            return Hm_MCP_Http_Response::error($auth['status'], $auth['error'], $auth['message'], $headers);
        }
        $principal = $auth['principal'];
        $store = $this->services->store();
        if (!$store->rate_limit('runner:'.$principal->connection_id(), self::RUNS_PER_HOUR, 3600)) {
            return Hm_MCP_Http_Response::error(429, 'rate_limited', 'Too many runs. Call this endpoint at most once a minute.');
        }
        $mail = $this->services->mail($principal);
        try {
            $report = $mail->run_scheduled($this->services->config->int('scheduled_grace', 120, 0), self::MAX_PER_RUN);
        } catch (Hm_MCP_Error $e) {
            $this->log($principal, 'error', ['error' => $e->error_code]);
            return Hm_MCP_Http_Response::json(['error' => $e->to_array()], $e->http_status());
        } catch (Throwable $e) {
            Hm_Debug::add('MCP scheduled run failed: '.$e->getMessage(), 'danger');
            $this->log($principal, 'error', ['error' => 'internal_error']);
            return Hm_MCP_Http_Response::error(500, 'internal_error', 'The run failed because of a server error.');
        } finally {
            $mail->finish();
        }
        foreach ($report['sent'] as $sent) {
            $this->log($principal, 'ok', $sent);
        }
        foreach ($report['failed'] as $failed) {
            $this->log($principal, 'error', ['account_id' => $failed['account_id'], 'error' => $failed['code']]);
        }
        return Hm_MCP_Http_Response::json([
            'sent' => count($report['sent']),
            'failed' => count($report['failed']),
            'waiting' => $report['waiting'],
            'failures' => $report['failed'],
            'errors' => $report['errors'],
        ]);
    }

    /**
     * @return void
     */
    private function log($principal, $outcome, $summary) {
        $this->services->store()->log_activity([
            'username' => $principal->username,
            'connection_id' => $principal->connection_id(),
            'connection_name' => $principal->connection_name(),
            'channel' => 'runner',
            'operation' => 'send_scheduled',
            'permission' => null,
            'outcome' => $outcome,
            'summary' => $summary,
        ]);
    }
}
