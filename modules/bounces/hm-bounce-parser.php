<?php

/**
 * Bounce message parser
 * @package modules
 * @subpackage bounces
 */

use rambomst\PHPBounceHandler\BounceHandler;

/**
 * Turns a raw bounce message (RFC 3464 delivery status notification or a
 * free-form mailer-daemon reply) into one event per bounced recipient.
 */
class Hm_Bounce_Parser {

    /* bounce handler actions that are bounces, with the bounce type; auto-replies are not */
    const ACTION_TYPES = [
        'failed' => Hm_Bounce_Policy::TYPE_HARD,
        'transient' => Hm_Bounce_Policy::TYPE_SOFT,
        'delayed' => Hm_Bounce_Policy::TYPE_SOFT,
    ];

    /**
     * @param string $raw full message source
     * @param array $headers parsed headers of the message, used to find its Message-ID
     * @return array events ['email' => string, 'type' => 'hard'|'soft', 'status' => string, 'message_id' => string]
     */
    public function parse($raw, $headers = []) {
        if (!is_string($raw) || trim($raw) === '') {
            return [];
        }
        $messageId = $this->messageId($raw, $headers);
        $events = [];
        $handler = new BounceHandler();
        $results = $handler->parseEmail($raw);
        if ($handler->looks_like_an_auto_response) {
            // the library reports auto-responses as transient failures
            return [];
        }
        foreach ($results as $result) {
            $action = mb_strtolower(trim((string) ($result['action'] ?? '')));
            if (!array_key_exists($action, self::ACTION_TYPES)) {
                continue;
            }
            $email = $this->extractEmail($result['recipient'] ?? '');
            if ($email === '') {
                continue;
            }
            if (array_key_exists($email, $events) && $events[$email]['type'] === Hm_Bounce_Policy::TYPE_HARD) {
                continue;
            }
            $events[$email] = [
                'email' => $email,
                'type' => self::ACTION_TYPES[$action],
                'status' => trim((string) ($result['status'] ?? '')),
                'message_id' => $messageId,
            ];
        }
        return array_values($events);
    }

    /**
     * Identifier used to record each bounce message once: its Message-ID, or a digest when it has none
     * @param string $raw full message source
     * @param array $headers parsed headers of the message
     * @return string
     */
    public function messageId($raw, $headers = []) {
        foreach ($headers as $name => $value) {
            if (mb_strtolower((string) $name) === 'message-id' && is_string($value) && trim($value) !== '') {
                return $this->normalizeMessageId($value);
            }
        }
        $head = preg_split("/\r?\n\r?\n/", $raw, 2)[0];
        if (preg_match('/^Message-ID:[ \t]*(.+?)[ \t]*$/im', $head, $matches)) {
            return $this->normalizeMessageId($matches[1]);
        }
        return 'sha1-'.sha1($raw);
    }

    private function normalizeMessageId($id) {
        return mb_substr(trim($id, " \t\r\n<>"), 0, 250);
    }

    private function extractEmail($value) {
        $value = trim((string) $value);
        if (preg_match('/<([^<>]+@[^<>]+)>/', $value, $matches)) {
            $value = $matches[1];
        }
        $value = trim($value, " \t\r\n\"'<>;,");
        if (!preg_match('/^[^@\s]+@[^@\s]+\.[^@\s]+$/', $value)) {
            return '';
        }
        return mb_strtolower($value);
    }
}
