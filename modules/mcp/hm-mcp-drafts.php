<?php

/**
 * Draft operations for the MCP server and REST API
 * @package modules
 * @subpackage mcp
 */

if (!defined('DEBUG_MODE')) { die(); }

/**
 * Create, update and delete drafts, including replies and forwards. Drafts are saved
 * in the drafts folder of the account, so they can be finished in Cypht or any other
 * mail program. Nothing here sends mail.
 * @subpackage mcp/lib
 */
trait Hm_MCP_Drafts {

    /* largest draft read back to update it, in bytes */
    protected static $max_draft_bytes = 41943040;

    /* characters of the original message included in a reply or forward */
    protected static $max_quote_chars = 100000;

    public function create_draft($args) {
        $mode = (string) ($args['mode'] ?? 'new');
        $original = null;
        if ($mode === 'new') {
            if (!empty($args['message_id'])) {
                throw new Hm_MCP_Error('invalid_argument', 'Set mode to reply, reply_all or forward to use message_id.');
            }
        } else {
            if (empty($args['message_id'])) {
                throw new Hm_MCP_Error('invalid_argument', 'message_id is required to reply or forward.');
            }
            $original = $this->original_message($args['message_id'], $mode);
        }
        $account = $this->draft_account((string) ($args['account_id'] ?? ''), $original);
        $sender = $this->sender($account['id'], (string) ($args['from'] ?? ''));
        $spec = [
            'from' => ['name' => $sender['name'], 'email' => $sender['email']],
            'reply_to' => $sender['reply_to'] !== '' ? [['name' => '', 'email' => $sender['reply_to']]] : [],
            'to' => array_key_exists('to', $args) ? Hm_MCP_Mime::parse_addresses($args['to'], 'to') : ($original['to'] ?? []),
            'cc' => array_key_exists('cc', $args) ? Hm_MCP_Mime::parse_addresses($args['cc'], 'cc') : ($original['cc'] ?? []),
            'bcc' => array_key_exists('bcc', $args) ? Hm_MCP_Mime::parse_addresses($args['bcc'], 'bcc') : [],
            'subject' => array_key_exists('subject', $args) ? Hm_MCP_Mime::header_text($args['subject']) : ($original['subject'] ?? ''),
            'in_reply_to' => $original['in_reply_to'] ?? [],
            'references' => $original['references'] ?? [],
            'message_id' => Hm_MCP_Mime::message_id($sender['email']),
            'draft' => true,
        ];
        self::check_recipients($spec);
        $text = self::with_signature((string) ($args['body'] ?? ''), $sender, !empty($args['include_signature']));
        $quote = $original && !empty($args['quote_original']) ? $original['quote'] : null;
        list($spec['text'], $spec['html']) = Hm_MCP_Mime::bodies($text, (string) ($args['body_format'] ?? 'text'), $quote);
        $limit = $this->services->uploads()->max_bytes();
        $forwarded = $original && $mode === 'forward' && !empty($args['include_attachments'])
            ? $this->original_attachments($original, $limit) : [];
        $spec['attachments'] = array_merge($forwarded, $this->new_attachments($args, $limit - self::attachments_size($forwarded)));
        list($folder, $uid) = $this->store_draft($account['id'], Hm_MCP_Mime::build($spec), $spec['message_id']);
        $this->audit = ['account_id' => $account['id'], 'mode' => $mode, 'recipients' => self::recipient_count($spec),
            'attachments' => count($spec['attachments'])];
        $message = $uid === null ? 'Draft saved. Its id is not known yet: list the drafts folder to find it.'
            : sprintf('Draft "%s" saved in %s. Nothing was sent.', mb_substr($spec['subject'], 0, 80), $folder);
        return $this->ok($message, $this->draft_data($account['id'], $folder, $uid, $spec));
    }

    public function update_draft($args) {
        list($account, $folder, $uid) = $this->draft_location($args['draft_id']);
        $reader = $this->context()->mailbox($account['id'], true);
        $existing = Hm_MCP_Imap::existing($reader, $folder, [$uid]);
        if (!isset($existing[$uid])) {
            throw new Hm_MCP_Error('not_found', 'Draft not found. It may have been sent or deleted, or saved again with a new id.');
        }
        if ($existing[$uid]['size'] > self::$max_draft_bytes) {
            throw new Hm_MCP_Error('payload_too_large', 'This draft is too large to change here. Open it in Cypht.');
        }
        $raw = Hm_MCP_Imap::quietly(function () use ($reader, $folder, $uid) { return $reader->get_message_content($folder, $uid); });
        if (!is_string($raw) || trim($raw) === '') {
            throw new Hm_MCP_Error('upstream_error', 'The draft could not be read.');
        }
        $draft = Hm_MCP_Mime::parse($raw);
        unset($raw);
        if (array_key_exists('from', $args) || !$draft['from']) {
            $sender = $this->sender($account['id'], (string) ($args['from'] ?? ''));
            $from = ['name' => $sender['name'], 'email' => $sender['email']];
            $reply_to = $sender['reply_to'] !== '' ? [['name' => '', 'email' => $sender['reply_to']]] : [];
        } else {
            $sender = $this->find_sender($account['id'], $draft['from']['email']);
            $from = $draft['from'];
            $reply_to = $draft['reply_to'];
        }
        $spec = [
            'from' => $from,
            'reply_to' => $reply_to,
            'to' => array_key_exists('to', $args) ? Hm_MCP_Mime::parse_addresses($args['to'], 'to') : $draft['to'],
            'cc' => array_key_exists('cc', $args) ? Hm_MCP_Mime::parse_addresses($args['cc'], 'cc') : $draft['cc'],
            'bcc' => array_key_exists('bcc', $args) ? Hm_MCP_Mime::parse_addresses($args['bcc'], 'bcc') : $draft['bcc'],
            'subject' => array_key_exists('subject', $args) ? Hm_MCP_Mime::header_text($args['subject']) : $draft['subject'],
            'in_reply_to' => $draft['in_reply_to'],
            'references' => $draft['references'],
            'message_id' => Hm_MCP_Mime::message_id($from['email']),
            'draft' => true,
        ];
        self::check_recipients($spec);
        if (array_key_exists('body', $args)) {
            $format = $args['body_format'] ?? ($draft['html'] !== null ? 'markdown' : 'text');
            list($spec['text'], $spec['html']) = Hm_MCP_Mime::bodies(
                self::with_signature((string) $args['body'], $sender, !empty($args['include_signature'])), $format);
        } elseif (array_key_exists('body_format', $args)) {
            list($spec['text'], $spec['html']) = Hm_MCP_Mime::bodies($draft['text'], $args['body_format']);
        } else {
            $spec['text'] = $draft['text'];
            $spec['html'] = $draft['html'];
        }
        $remove = array_map('strval', (array) ($args['remove_attachments'] ?? []));
        $known = array_values(array_filter(array_map('strval', array_column($draft['attachments'], 'part_id')), 'strlen'));
        $unknown = array_diff($remove, $known);
        if ($unknown) {
            throw new Hm_MCP_Error('invalid_argument', sprintf('The draft has no attachment with part_id %s. Its attachments are: %s.',
                implode(', ', $unknown), $known ? implode(', ', $known) : 'none'));
        }
        $kept = array_values(array_filter($draft['attachments'], function ($attachment) use ($remove) {
            return !in_array((string) $attachment['part_id'], $remove, true);
        }));
        $limit = $this->services->uploads()->max_bytes();
        $spec['attachments'] = array_merge($kept, $this->new_attachments($args, $limit - self::attachments_size($kept)));
        list($folder, $new_uid, $replaced) = $this->store_draft($account['id'], Hm_MCP_Mime::build($spec), $spec['message_id'], $uid);
        $this->audit = ['account_id' => $account['id'], 'recipients' => self::recipient_count($spec),
            'attachments' => count($spec['attachments'])];
        $message = 'Draft updated. It has a new draft_id: use it from now on.';
        if (!$replaced) {
            $message .= ' The previous version could not be removed and is still in the drafts folder.';
        }
        $data = $this->draft_data($account['id'], $folder, $new_uid, $spec);
        $data['replaced_draft_id'] = (string) $args['draft_id'];
        return $this->ok($message, $data);
    }

    public function delete_draft($args) {
        list($account, $folder, $uid) = $this->draft_location($args['draft_id']);
        $mailbox = $this->context()->mailbox($account['id'], false);
        $existing = Hm_MCP_Imap::existing($mailbox, $folder, [$uid]);
        if (!isset($existing[$uid])) {
            throw new Hm_MCP_Error('not_found', 'Draft not found. It may have been sent or deleted already.');
        }
        $outcome = $this->erase($mailbox, $account['id'], $folder, $existing)[$uid] ?? 'The server did not delete the draft.';
        if ($outcome !== true) {
            throw new Hm_MCP_Error('upstream_error', $outcome);
        }
        $this->audit = ['account_id' => $account['id']];
        return $this->ok('Draft deleted.', ['draft_id' => (string) $args['draft_id'], 'deleted' => true]);
    }

    /* -------------------------------------------------------------- helpers */

    /**
     * Account, folder and uid of a draft id. Only messages in the drafts folder are drafts.
     * @param string $draft_id draft id
     * @return array [account, folder, uid]
     * @throws Hm_MCP_Error
     */
    protected function draft_location($draft_id) {
        list($ref, $folder, $uid) = Hm_MCP_Format::parse_message_id($draft_id);
        if (!ctype_digit($uid)) {
            throw new Hm_MCP_Error('invalid_argument', 'The draft id is not valid.');
        }
        $account = $this->context()->account($ref);
        if ($folder !== $this->drafts_folder($account['id'])) {
            throw new Hm_MCP_Error('invalid_argument', 'This message is not a draft: only messages in the drafts folder can be changed or deleted as drafts.');
        }
        return [$account, $folder, $uid];
    }

    /**
     * @param string $account_id account id
     * @return string drafts folder
     * @throws Hm_MCP_Error
     */
    protected function drafts_folder($account_id) {
        $folder = $this->context()->special_folders($account_id, null, ['drafts'])['drafts'] ?? null;
        if (!$folder) {
            throw new Hm_MCP_Error('not_found', 'This account has no drafts folder. Choose one in Cypht under Settings, Folders.');
        }
        return $folder;
    }

    /**
     * Save a draft, and remove the version it replaces
     * @param string $account_id account id
     * @param string $raw message
     * @param string $message_id Message-ID header, used to find the new uid
     * @param string|null $replace uid of the previous version
     * @return array [folder, new uid or null when unknown, previous version removed]
     * @throws Hm_MCP_Error
     */
    protected function store_draft($account_id, $raw, $message_id, $replace = null) {
        $folder = $this->drafts_folder($account_id);
        $mailbox = $this->context()->mailbox($account_id, false);
        $stored = Hm_MCP_Imap::quietly(function () use ($mailbox, $folder, $raw) {
            return $mailbox->store_message($folder, $raw, false, true);
        });
        unset($raw);
        if (!$stored) {
            throw new Hm_MCP_Error('upstream_error', 'The mail server did not save the draft.', ['account_id' => $account_id]);
        }
        $uid = $stored === true ? null : Hm_MCP_Imap::new_uid($stored);
        if ($uid === null) {
            $found = Hm_MCP_Imap::quietly(function () use ($mailbox, $folder, $message_id) {
                return $mailbox->search($folder, 'ALL', [['HEADER Message-ID', $message_id]]);
            });
            if (is_array($found) && count($found) === 1) {
                $uid = Hm_MCP_Imap::new_uid(reset($found));
            }
        }
        $replaced = true;
        if ($replace !== null) {
            $existing = Hm_MCP_Imap::existing($mailbox, $folder, [(string) $replace]);
            if ($existing) {
                $replaced = ($this->erase($mailbox, $account_id, $folder, $existing)[(string) $replace] ?? false) === true;
            }
        }
        return [$folder, $uid, $replaced];
    }

    /**
     * Read the message being answered or forwarded, without marking it as read
     * @param string $message_id message id
     * @param string $mode reply, reply_all or forward
     * @return array recipients, subject, threading headers, quote and structure
     * @throws Hm_MCP_Error
     */
    protected function original_message($message_id, $mode) {
        $ctx = $this->context();
        list($ref, $folder, $uid) = Hm_MCP_Format::parse_message_id($message_id);
        $account = $ctx->account($ref);
        $mailbox = $ctx->mailbox($account['id'], true);
        $headers = Hm_MCP_Imap::quietly(function () use ($mailbox, $folder, $uid) { return $mailbox->get_message_headers($folder, $uid); });
        if (!is_array($headers) || !$headers) {
            throw new Hm_MCP_Error('not_found', 'The message to reply to or forward was not found. It may have been moved or deleted.');
        }
        list($format, $raw, $struct, $body_part) = $this->read_body($mailbox, $folder, $uid);
        $text = $format === 'html' ? Hm_MCP_Format::html_to_text($raw) : Hm_MCP_Format::plain_text($raw);
        $text = Hm_MCP_Format::truncate($text, self::$max_quote_chars)[0];
        $header = function ($name) use ($headers) { return (string) Hm_MCP_Format::header($headers, $name); };
        $from = Hm_MCP_Mime::unique(Hm_MCP_Format::addresses($header('From')));
        $original_to = Hm_MCP_Mime::unique(Hm_MCP_Format::addresses($header('To')));
        $original_cc = Hm_MCP_Mime::unique(Hm_MCP_Format::addresses($header('Cc')));
        $own = array_map('strtolower', array_column($ctx->senders($account['id']), 'email'));
        $to = [];
        $cc = [];
        if ($mode !== 'forward') {
            if ($from && in_array(strtolower($from[0]['email']), $own, true)) {
                /* answering a message the user sent: write to the same people again */
                $to = $original_to;
                $cc = $mode === 'reply_all' ? Hm_MCP_Mime::without($original_cc, $to) : [];
            } else {
                $to = Hm_MCP_Mime::unique(Hm_MCP_Format::addresses($header('Reply-To'))) ?: $from;
                $cc = $mode === 'reply_all' ? Hm_MCP_Mime::without(array_merge($original_to, $original_cc), $own, $to) : [];
            }
        }
        $subject = Hm_MCP_Mime::header_text($header('Subject'));
        if ($mode === 'forward' && !preg_match('/^(fwd?|rv|wg|tr)\s*:/i', $subject)) {
            $subject = 'Fwd: '.$subject;
        } elseif ($mode !== 'forward' && !preg_match('/^(re|aw|sv)\s*:/i', $subject)) {
            $subject = 'Re: '.$subject;
        }
        $lang = $ctx->language();
        if ($mode === 'forward') {
            $lines = [];
            foreach (['From', 'Date', 'Subject', 'To', 'Cc'] as $name) {
                $value = Hm_MCP_Mime::header_text($header($name));
                if ($value !== '') {
                    $lines[] = $name.': '.$value;
                }
            }
            $lead_in = '----- '.Hm_MCP_Strings::trans($lang, 'begin forwarded message')." -----\n".implode("\n", $lines);
        } else {
            $who = $from ? ($from[0]['name'] !== '' ? $from[0]['name'].' <'.$from[0]['email'].'>' : $from[0]['email']) : '';
            $date = Hm_MCP_Mime::header_text($header('Date'));
            $lead_in = $who !== '' ? sprintf(Hm_MCP_Strings::trans($lang, 'On %s %s said'), $date, $who)
                : sprintf(Hm_MCP_Strings::trans($lang, 'On %s, somebody said'), $date);
        }
        $id_header = Hm_MCP_Mime::clean_ids($header('Message-ID'));
        return [
            'account_id' => $account['id'],
            'folder' => $folder,
            'uid' => $uid,
            'struct' => $struct,
            'body_part' => $body_part,
            'to' => $to,
            'cc' => $cc,
            'subject' => $subject,
            'in_reply_to' => $mode === 'forward' ? [] : $id_header,
            'references' => $mode === 'forward' ? [] : array_merge(Hm_MCP_Mime::clean_ids($header('References')), $id_header),
            'quote' => ['type' => $mode === 'forward' ? 'forward' : 'reply', 'lead_in' => $lead_in, 'text' => $text],
        ];
    }

    /**
     * Attachments of the message being forwarded
     * @param array $original value from original_message()
     * @param int $limit largest total size in bytes
     * @return array attachments for Hm_MCP_Mime::build()
     * @throws Hm_MCP_Error
     */
    protected function original_attachments($original, $limit) {
        $mailbox = $this->context()->mailbox($original['account_id'], true);
        $parts = [];
        foreach (self::flatten($original['struct']) as list($id, $part)) {
            $parts[$id] = $part;
        }
        $res = [];
        $total = 0;
        foreach ($this->attachments($original['struct'], $original['body_part'], $original['uid']) as $info) {
            if (!isset($parts[$info['part_id']])) {
                continue;
            }
            $data = $this->read_part($mailbox, $original['folder'], $original['uid'], $info['part_id'], $parts[$info['part_id']]);
            $total += strlen($data);
            if ($total > $limit) {
                throw new Hm_MCP_Error('payload_too_large', Hm_MCP_Uploads::too_large($limit)->getMessage().
                    ' Set include_attachments to false to forward the message without its attachments.');
            }
            $res[] = ['filename' => $info['filename'], 'content_type' => $info['content_type'], 'data' => $data,
                'disposition' => $info['disposition'], 'content_id' => $info['content_id'] ?? ''];
        }
        return $res;
    }

    /**
     * Files added by the client
     * @param array $args operation arguments with files and attachments
     * @param int $budget bytes still allowed
     * @return array attachments for Hm_MCP_Mime::build()
     * @throws Hm_MCP_Error
     */
    protected function new_attachments($args, $budget) {
        $uploads = $this->services->uploads();
        $ctx = $this->context();
        $budget = max(0, (int) $budget);
        $res = [];
        foreach ((array) ($args['files'] ?? []) as $file) {
            $time = (int) floor($ctx->time_left() - self::RESERVE_SECONDS);
            if ($time < 5) {
                throw new Hm_MCP_Error('unavailable', 'There was not enough time to download every file. Add the remaining files with update_draft.');
            }
            $item = $uploads->from_file($file, $budget, $time);
            $budget -= strlen($item['data']);
            $res[] = $item;
        }
        $inline = min($budget, Hm_MCP_Uploads::MAX_INLINE_BYTES);
        foreach ((array) ($args['attachments'] ?? []) as $content) {
            $item = $uploads->from_base64($content, $inline);
            $inline -= strlen($item['data']);
            $res[] = $item;
        }
        return $res;
    }

    /**
     * Address to send as
     * @param string $account_id account id
     * @param string $from requested address, empty for the default
     * @return array sender from Hm_MCP_Context::senders()
     * @throws Hm_MCP_Error
     */
    protected function sender($account_id, $from) {
        $senders = $this->context()->senders($account_id);
        if (trim($from) !== '') {
            $email = Hm_MCP_Mime::parse_address($from, 'from')['email'];
            $sender = $this->find_sender($account_id, $email);
            if ($sender) {
                return $sender;
            }
            throw new Hm_MCP_Error('invalid_argument', sprintf('from must be an address this account sends as: %s.',
                $senders ? implode(', ', array_column($senders, 'email')) : 'none'));
        }
        if (!$senders) {
            throw new Hm_MCP_Error('not_supported', 'This account has no sending address. Add a profile for it in Cypht under Settings, Profiles.');
        }
        return $senders[0];
    }

    /**
     * @param string $account_id account id
     * @param string $email address
     * @return array|null sender with this address
     */
    protected function find_sender($account_id, $email) {
        foreach ($this->context()->senders($account_id) as $sender) {
            if (strcasecmp($sender['email'], $email) === 0) {
                return $sender;
            }
        }
        return null;
    }

    /**
     * Account a new draft is saved in
     * @param string $account_id requested account
     * @param array|null $original message being answered or forwarded
     * @return array account
     * @throws Hm_MCP_Error
     */
    protected function draft_account($account_id, $original) {
        $ctx = $this->context();
        if (trim($account_id) !== '') {
            return $ctx->account($account_id);
        }
        if ($original) {
            return $ctx->account($original['account_id']);
        }
        $accounts = $ctx->accounts(true);
        if (count($accounts) === 1) {
            return $accounts[0];
        }
        foreach ((array) $ctx->user_config->get('profiles', []) as $profile) {
            if (is_array($profile) && !empty($profile['default'])) {
                foreach ($accounts as $account) {
                    if ($account['id'] === (string) ($profile['imap_id'] ?? '')) {
                        return $account;
                    }
                }
            }
        }
        throw new Hm_MCP_Error('invalid_argument', $accounts ? 'Choose the account to write from: pass account_id from list_accounts.'
            : 'No email account is available to this connection.');
    }

    /**
     * @param string $text message text
     * @param array|null $sender sending profile
     * @param bool $include add the signature
     * @return string
     */
    protected static function with_signature($text, $sender, $include) {
        $signature = trim((string) ($sender['signature'] ?? ''));
        if (!$include || $signature === '') {
            return $text;
        }
        if ($signature !== strip_tags($signature)) {
            $signature = Hm_MCP_Format::html_to_text($signature);
        }
        return rtrim($text)."\n\n".$signature;
    }

    /**
     * @param array $spec message
     * @return void
     * @throws Hm_MCP_Error
     */
    protected static function check_recipients($spec) {
        if (self::recipient_count($spec) > Hm_MCP_Mime::MAX_RECIPIENTS) {
            throw new Hm_MCP_Error('invalid_argument', sprintf('A message can have at most %d recipients.', Hm_MCP_Mime::MAX_RECIPIENTS));
        }
    }

    protected static function recipient_count($spec) {
        return count($spec['to']) + count($spec['cc']) + count($spec['bcc']);
    }

    protected static function attachments_size($attachments) {
        return array_sum(array_map(function ($attachment) { return strlen($attachment['data']); }, $attachments));
    }

    /**
     * Result data describing a saved draft
     * @return array
     */
    protected function draft_data($account_id, $folder, $uid, $spec) {
        return [
            'draft_id' => $uid === null ? null : Hm_MCP_Format::message_id($account_id, $folder, $uid),
            'account_id' => (string) $account_id,
            'folder' => (string) $folder,
            'from' => $spec['from'],
            'to' => $spec['to'],
            'cc' => $spec['cc'],
            'bcc' => $spec['bcc'],
            'subject' => $spec['subject'],
            'format' => $spec['html'] === null ? 'text' : 'html',
            'in_reply_to' => (string) (Hm_MCP_Mime::clean_ids($spec['in_reply_to'])[0] ?? ''),
            'attachments' => array_map(function ($attachment) {
                return ['filename' => Hm_MCP_Mime::filename($attachment['filename']),
                    'content_type' => Hm_MCP_Mime::content_type($attachment['content_type']), 'size' => strlen($attachment['data'])];
            }, $spec['attachments']),
            'url' => $uid === null ? null : $this->context()->draft_url($account_id, $folder, $uid),
        ];
    }
}
