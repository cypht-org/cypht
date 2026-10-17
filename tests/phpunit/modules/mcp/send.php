<?php

use PHPUnit\Framework\TestCase;

/**
 * tests for sending, scheduled sending and the scheduled sends runner
 */
class Hm_Test_MCP_Send extends TestCase {

    private $servers = [
        'acc1' => ['id' => 'acc1', 'name' => 'Work', 'user' => 'work@example.com', 'server' => 'imap.example.com', 'type' => 'imap'],
        'acc2' => ['id' => 'acc2', 'name' => 'Gmail', 'user' => 'me@gmail.example', 'server' => 'imap.gmail.example', 'type' => 'imap'],
        'acc3' => ['id' => 'acc3', 'name' => 'No SMTP', 'user' => 'old@example.net', 'server' => 'imap.example.net', 'type' => 'imap'],
    ];

    private $settings = [
        'language_setting' => 'en',
        'profiles' => [
            'p1' => ['id' => 'p1', 'name' => 'Alice Work', 'address' => 'work@example.com', 'replyto' => '', 'smtp_id' => 's1',
                'imap_id' => 'acc1', 'sig' => '', 'default' => true, 'type' => 'imap', 'user' => 'work@example.com', 'server' => 'imap.example.com'],
        ],
    ];

    public function setUp(): void {
        require_once __DIR__.'/fakes.php';
        date_default_timezone_set('UTC');
    }

    private function mailboxes() {
        $acc1 = new Hm_MCP_Fake_Mailbox([
            'INBOX' => [
                '1' => hm_mcp_fake_message('Lunch', 'Bob <bob@example.net>', 'Mon, 01 Sep 2026 10:00:00 +0000', [
                    'message_id' => '<orig1@example.net>', 'text' => 'Shall we meet?']),
            ],
            'Sent' => [], 'Drafts' => [], 'Trash' => [],
        ], ['sent' => 'Sent', 'drafts' => 'Drafts', 'trash' => 'Trash']);
        $acc1->capabilities = ['MOVE', 'UIDPLUS'];
        $acc2 = new Hm_MCP_Fake_Mailbox(['INBOX' => [], '[Gmail]/Sent Mail' => [], '[Gmail]/Drafts' => [], '[Gmail]/Trash' => []],
            ['sent' => '[Gmail]/Sent Mail', 'drafts' => '[Gmail]/Drafts', 'trash' => '[Gmail]/Trash']);
        $acc2->capabilities = ['MOVE', 'UIDPLUS', 'X-GM-EXT-1'];
        $acc3 = new Hm_MCP_Fake_Mailbox(['INBOX' => [], 'Drafts' => []], ['drafts' => 'Drafts']);
        return ['acc1' => $acc1, 'acc2' => $acc2, 'acc3' => $acc3];
    }

    private static function everything() {
        return array_fill_keys(Hm_MCP_Permissions::keys(), true);
    }

    private static function id($account, $folder, $uid) {
        return Hm_MCP_Format::message_id($account, $folder, $uid);
    }

    private function services($mailboxes, $smtp, $settings = null) {
        list($services, $holder) = hm_mcp_fake_services($mailboxes, $this->servers, $settings ?? $this->settings);
        $holder->smtp = $smtp;
        $holder->smtp_accounts = ['acc1' => 's1', 'acc2' => 's2'];
        return [$services, $holder];
    }

    private function run_op($name, $args, $mailboxes, $smtp, $options = []) {
        list($services, $holder) = $this->services($mailboxes, $smtp, $options['settings'] ?? null);
        if (!empty($options['limited'])) {
            $services->store()->limited = $options['limited'];
        }
        $res = $services->executor($options['principal'] ?? hm_mcp_fake_principal(self::everything()), 'mcp')->run($name, $args);
        if ($res['ok']) {
            $op = $services->catalog()->get($name);
            $errors = $services->validator()->validateAgainstJsonSchema(json_decode(json_encode($res['result'])),
                Hm_MCP_Catalog::normalize($op['output']));
            $this->assertSame([], $errors, $name.' output does not match its schema');
        }
        $res['logged'] = $services->store()->logged;
        return $res;
    }

    private static function error_code($res) {
        return $res['ok'] ? null : $res['error']->error_code;
    }

    private static function smtp() {
        return ['s1' => new Hm_MCP_Fake_Smtp(), 's2' => new Hm_MCP_Fake_Smtp()];
    }

    /* ---------------------------------------------------------- headers */

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_outgoing_messages_hide_bcc_and_escape_dots() {
        $raw = "Date: Mon, 01 Sep 2025 10:00:00 +0000\r\nFrom: a@example.com\r\nBcc: Secret\r\n <s@example.com>\r\nX-Original-Bcc: s@example.com\r\n".
            "X-Schedule: Tue, 02 Sep 2025 08:00 +0000\r\nX-Profile-ID: p1\r\nSubject: Hi\r\n\r\nline\r\n.\r\n..two\r\nBcc: body text\n";
        $out = Hm_MCP_Imap::outgoing($raw, 1800000000);
        $this->assertStringStartsWith('Date: '.date('r', 1800000000)."\r\nFrom: a@example.com\r\nSubject: Hi\r\n\r\n", $out);
        $this->assertStringNotContainsString('s@example.com', $out);
        $this->assertStringNotContainsString('X-Schedule', $out);
        $this->assertStringNotContainsString('X-Profile-ID', $out);
        $this->assertStringContainsString("\r\nline\r\n..\r\n...two\r\nBcc: body text\r\n", $out);
        $this->assertSame('Secret <s@example.com>', Hm_MCP_Imap::header_value($raw, 'bcc'));
        $this->assertNull(Hm_MCP_Imap::header_value($raw, 'Cc'));
        $set = Hm_MCP_Imap::set_header($raw, 'X-Schedule', "Wed\r\nBcc: evil@example.com");
        $this->assertStringStartsWith("X-Schedule: Wed Bcc: evil@example.com\r\nDate:", $set);
        $this->assertSame(1, substr_count($set, 'X-Schedule'));
    }

    /* ----------------------------------------------------------- send */

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_send_a_new_message() {
        $boxes = $this->mailboxes();
        $smtp = self::smtp();
        $res = $this->run_op('send_message', ['account_id' => 'acc1', 'to' => ['Bob <Bob@Example.net>'], 'cc' => ['carol@example.org'],
            'bcc' => ['secret@example.com', 'bob@example.net'], 'subject' => 'Plan', 'body' => "Hi\n.\nBye",
            'attachments' => [['filename' => 'a.txt', 'content_base64' => base64_encode('A')]]], $boxes, $smtp);
        $this->assertTrue($res['ok'], $res['ok'] ? '' : $res['error']->getMessage());
        $data = $res['result']['data'];
        $this->assertSame('sent', $data['status']);
        $this->assertSame([], $data['warnings']);
        $this->assertCount(1, $smtp['s1']->sent);
        $sent = $smtp['s1']->sent[0];
        $this->assertSame('work@example.com', $sent['from']);
        $this->assertSame(['bob@example.net', 'carol@example.org', 'secret@example.com'], $sent['recipients']);
        $this->assertStringNotContainsString('secret@example.com', $sent['message']);
        $this->assertStringContainsString("Subject: Plan\r\n", $sent['message']);
        $this->assertSame(0, preg_match('/\r\n\.(?!\.)/', $sent['message']), 'a line starts with a single dot');
        /* the copy in the sent folder keeps the hidden recipients for the user */
        $copy = $boxes['acc1']->folders['Sent']['1'];
        $this->assertSame('\\Seen', $copy['flags']);
        $this->assertStringContainsString('secret@example.com', $copy['raw']);
        $this->assertSame(['acc1', 'Sent', '1'], Hm_MCP_Format::parse_message_id($data['sent_message_id']));
        $this->assertSame([], $smtp['s2']->sent);
        /* the activity log has counts and domains, never addresses */
        $summary = $res['logged'][0]['summary'];
        $this->assertSame(['account_id' => 'acc1', 'mode' => 'new', 'recipients' => 3, 'domains' => 'example.net, example.org, example.com',
            'attachments' => 1, 'scheduled' => false], $summary);
        $this->assertStringNotContainsString('bob', json_encode($res['logged']));
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_replies_mark_the_original_and_gmail_keeps_its_own_copy() {
        $boxes = $this->mailboxes();
        $smtp = self::smtp();
        $res = $this->run_op('send_message', ['mode' => 'reply', 'message_id' => self::id('acc1', 'INBOX', '1'), 'body' => 'Yes'], $boxes, $smtp);
        $this->assertTrue($res['ok'], $res['ok'] ? '' : $res['error']->getMessage());
        $this->assertSame(['bob@example.net'], $smtp['s1']->sent[0]['recipients']);
        $this->assertStringContainsString("In-Reply-To: <orig1@example.net>\r\n", $smtp['s1']->sent[0]['message']);
        $this->assertStringContainsString('\\Answered', $boxes['acc1']->folders['INBOX']['1']['flags']);

        $res = $this->run_op('send_message', ['account_id' => 'acc2', 'to' => ['x@example.com'], 'subject' => 'From Gmail'], $boxes, $smtp,
            ['settings' => []]);
        $this->assertTrue($res['ok']);
        $this->assertCount(1, $smtp['s2']->sent);
        $this->assertSame('me@gmail.example', $smtp['s2']->sent[0]['from']);
        $this->assertSame([], $boxes['acc2']->folders['[Gmail]/Sent Mail']);
        $this->assertNull($res['result']['data']['sent_message_id']);
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_send_checks() {
        $boxes = $this->mailboxes();
        $smtp = self::smtp();
        /* sending is off by default */
        $res = $this->run_op('send_message', ['account_id' => 'acc1', 'to' => ['bob@example.net']], $boxes, $smtp,
            ['principal' => hm_mcp_fake_principal()]);
        $this->assertSame('permission_denied', self::error_code($res));
        $this->assertSame('invalid_argument', self::error_code($this->run_op('send_message', ['account_id' => 'acc1', 'subject' => 'x'], $boxes, $smtp)));
        $res = $this->run_op('send_message', ['account_id' => 'acc3', 'to' => ['bob@example.net']], $boxes, $smtp);
        $this->assertSame('not_supported', self::error_code($res));
        $res = $this->run_op('send_message', ['account_id' => 'acc1', 'to' => ['bob@example.net']], $boxes, $smtp, ['limited' => ['send:']]);
        $this->assertSame('rate_limited', self::error_code($res));
        $res = $this->run_op('send_message', ['account_id' => 'acc1', 'to' => ['bob@example.net']], $boxes, $smtp, ['limited' => ['send_day:']]);
        $this->assertSame('rate_limited', self::error_code($res));
        $smtp['s1']->error = 'Recipient address rejected';
        $res = $this->run_op('send_message', ['account_id' => 'acc1', 'to' => ['bob@example.net']], $boxes, $smtp);
        $this->assertSame('upstream_error', self::error_code($res));
        $this->assertStringContainsString('Recipient address rejected', $res['error']->getMessage());
        $this->assertSame([], $boxes['acc1']->folders['Sent']);
        foreach (['yesterday', 'soon', '2999-01-01'] as $when) {
            $res = $this->run_op('send_message', ['account_id' => 'acc1', 'to' => ['bob@example.net'], 'send_at' => $when], $boxes, self::smtp());
            $this->assertSame('invalid_argument', self::error_code($res), $when);
        }
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_send_at_accepts_iso8601_with_timezone_offset() {
        $boxes = $this->mailboxes();
        $smtp = self::smtp();
        $send_at = (new DateTimeImmutable('+2 days', new DateTimeZone('-03:00')))->format(DATE_ATOM);
        $res = $this->run_op('send_message', ['account_id' => 'acc1', 'to' => ['bob@example.net'],
            'subject' => 'ISO schedule', 'send_at' => $send_at], $boxes, $smtp);

        $this->assertTrue($res['ok'], $res['ok'] ? '' : $res['error']->getMessage());
        $this->assertSame('scheduled', $res['result']['data']['status']);
        $this->assertSame(date(DATE_ATOM, strtotime($send_at)), $res['result']['data']['send_at']);
        $this->assertSame([], $smtp['s1']->sent);
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_auto_bcc_sends_a_copy_to_the_sender() {
        $boxes = $this->mailboxes();
        $smtp = self::smtp();
        $settings = $this->settings + ['smtp_auto_bcc_setting' => true];
        $res = $this->run_op('send_message', ['account_id' => 'acc1', 'to' => ['bob@example.net'], 'subject' => 'x'], $boxes, $smtp, ['settings' => $settings]);
        $this->assertTrue($res['ok']);
        $this->assertCount(2, $smtp['s1']->sent);
        $this->assertSame(['work@example.com'], $smtp['s1']->sent[1]['recipients']);
        $this->assertStringStartsWith("X-Auto-Bcc: cypht\r\n", $smtp['s1']->sent[1]['message']);
        $this->assertStringNotContainsString('X-Auto-Bcc', $smtp['s1']->sent[0]['message']);
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_send_a_draft() {
        $boxes = $this->mailboxes();
        $smtp = self::smtp();
        $draft = $this->run_op('create_draft', ['mode' => 'reply', 'message_id' => self::id('acc1', 'INBOX', '1'), 'bcc' => ['h@example.com']],
            $boxes, $smtp)['result']['data'];
        $res = $this->run_op('send_draft', ['draft_id' => $draft['draft_id']], $boxes, $smtp);
        $this->assertTrue($res['ok'], $res['ok'] ? '' : $res['error']->getMessage());
        $this->assertSame(['bob@example.net', 'h@example.com'], $smtp['s1']->sent[0]['recipients']);
        $this->assertStringNotContainsString('h@example.com', $smtp['s1']->sent[0]['message']);
        $this->assertSame([], $boxes['acc1']->folders['Drafts']);
        $this->assertCount(1, $boxes['acc1']->folders['Sent']);
        $this->assertStringContainsString('\\Answered', $boxes['acc1']->folders['INBOX']['1']['flags']);
        $this->assertSame('not_found', self::error_code($this->run_op('send_draft', ['draft_id' => $draft['draft_id']], $boxes, $smtp)));
        $this->assertSame('invalid_argument', self::error_code($this->run_op('send_draft', ['draft_id' => self::id('acc1', 'INBOX', '1')], $boxes, $smtp)));

        /* a draft written as somebody else is not sent */
        $boxes['acc1']->store_message('Drafts', "From: ceo@example.com\r\nTo: bob@example.net\r\nSubject: Spoof\r\nMessage-ID: <s@x>\r\n\r\nx\r\n", false, true);
        $uid = (string) max(array_keys($boxes['acc1']->folders['Drafts']));
        $res = $this->run_op('send_draft', ['draft_id' => self::id('acc1', 'Drafts', $uid)], $boxes, $smtp);
        $this->assertSame('invalid_argument', self::error_code($res));
        $this->assertStringContainsString('ceo@example.com', $res['error']->getMessage());
        $this->assertCount(1, $smtp['s1']->sent);
    }

    /* ------------------------------------------------------ scheduled */

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_schedule_list_reschedule_cancel_and_send_now() {
        $boxes = $this->mailboxes();
        $smtp = self::smtp();
        $res = $this->run_op('send_message', ['account_id' => 'acc1', 'to' => ['bob@example.net'], 'bcc' => ['h@example.com'],
            'subject' => 'Later', 'send_at' => 'tomorrow'], $boxes, $smtp);
        $this->assertTrue($res['ok'], $res['ok'] ? '' : $res['error']->getMessage());
        $data = $res['result']['data'];
        $this->assertSame('scheduled', $data['status']);
        $this->assertSame(date(DATE_ATOM, strtotime('tomorrow 08:00')), $data['send_at']);
        $this->assertSame([], $smtp['s1']->sent);
        $stored = $boxes['acc1']->folders['Scheduled']['1'];
        $this->assertSame('\\Draft', $stored['flags']);
        $this->assertStringStartsWith('X-Schedule: '.date('D, d M Y H:i O', strtotime('tomorrow 08:00'))."\r\nX-Profile-ID: p1\r\n", $stored['raw']);
        $this->assertTrue($res['logged'][0]['summary']['scheduled']);

        $list = $this->run_op('list_scheduled', [], $boxes, $smtp);
        $this->assertCount(1, $list['result']['data']['messages']);
        $entry = $list['result']['data']['messages'][0];
        $this->assertSame([$data['scheduled_id'], 'Later', $data['send_at']], [$entry['id'], $entry['subject'], $entry['send_at']]);

        $day = date('Y-m-d', strtotime('+30 days'));
        $res = $this->run_op('manage_scheduled', ['action' => 'reschedule', 'message_ids' => [$entry['id']], 'send_at' => $day], $boxes, $smtp);
        $this->assertTrue($res['ok'], $res['ok'] ? '' : $res['error']->getMessage());
        $this->assertSame(['ok'], array_column($res['result']['data']['results'], 'status'));
        $moved = $res['result']['data']['results'][0]['new_message_id'];
        $this->assertSame('X-Schedule: '.date('D, d M Y H:i O', strtotime($day.' 08:00')), explode("\r\n", $boxes['acc1']->folders['Scheduled']['2']['raw'])[0]);
        $this->assertCount(1, $boxes['acc1']->folders['Scheduled']);
        $rescheduled = $this->run_op('list_scheduled', [], $boxes, $smtp)['result']['data']['messages'];
        $this->assertCount(1, $rescheduled);
        $this->assertSame(date(DATE_ATOM, strtotime($day.' 08:00')), $rescheduled[0]['send_at']);
        $this->assertSame('invalid_argument', self::error_code($this->run_op('manage_scheduled', ['action' => 'reschedule', 'message_ids' => [$moved]], $boxes, $smtp)));
        $res = $this->run_op('manage_scheduled', ['action' => 'cancel', 'message_ids' => [self::id('acc1', 'INBOX', '1')]], $boxes, $smtp);
        $this->assertTrue($res['ok'], $res['ok'] ? '' : $res['error']->getMessage());
        $this->assertSame(['failed'], array_column($res['result']['data']['results'], 'status'));

        $res = $this->run_op('manage_scheduled', ['action' => 'cancel', 'message_ids' => [$moved]], $boxes, $smtp);
        $this->assertSame('Drafts', $res['result']['data']['results'][0]['folder']);
        $draft = $boxes['acc1']->folders['Drafts']['1'];
        $this->assertStringNotContainsString('X-Schedule', $draft['raw']);
        $this->assertStringContainsString('h@example.com', $draft['raw']);
        $this->assertSame([], $boxes['acc1']->folders['Scheduled']);

        /* schedule the draft again, then send it right away */
        $res = $this->run_op('send_draft', ['draft_id' => $res['result']['data']['results'][0]['new_message_id'], 'send_at' => 'next_week'], $boxes, $smtp);
        $this->assertSame('scheduled', $res['result']['data']['status']);
        $this->assertSame([], $boxes['acc1']->folders['Drafts']);
        $res = $this->run_op('manage_scheduled', ['action' => 'send_now', 'message_ids' => [$res['result']['data']['scheduled_id']]], $boxes, $smtp);
        $this->assertSame(['ok'], array_column($res['result']['data']['results'], 'status'));
        $this->assertSame(['bob@example.net', 'h@example.com'], $smtp['s1']->sent[0]['recipients']);
        $this->assertStringNotContainsString('X-Schedule', $smtp['s1']->sent[0]['message']);
        $this->assertSame([], $boxes['acc1']->folders['Scheduled']);
        $this->assertCount(1, $boxes['acc1']->folders['Sent']);
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_reschedule_reports_failure_when_the_old_message_cannot_be_removed() {
        $boxes = $this->mailboxes();
        $smtp = self::smtp();
        $scheduled = $this->run_op('send_message', ['account_id' => 'acc1', 'to' => ['bob@example.net'],
            'subject' => 'Cannot move', 'send_at' => 'tomorrow'], $boxes, $smtp)['result']['data'];
        $boxes['acc1']->failing_actions[] = 'DELETE';

        $res = $this->run_op('manage_scheduled', ['action' => 'reschedule', 'message_ids' => [$scheduled['scheduled_id']],
            'send_at' => 'next_week'], $boxes, $smtp);

        $this->assertTrue($res['ok']);
        $this->assertSame(['failed'], array_column($res['result']['data']['results'], 'status'));
        $this->assertStringContainsString('could not be removed', $res['result']['data']['results'][0]['reason']);
        $this->assertCount(2, $this->run_op('list_scheduled', [], $boxes, $smtp)['result']['data']['messages']);
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_runner_sends_due_messages_once() {
        $boxes = $this->mailboxes();
        $smtp = self::smtp();
        $now = time();
        $boxes['acc1']->folders['Scheduled'] = [];
        foreach ([['Due', $now - 600], ['Just due', $now - 30], ['Later', $now + 3600]] as $i => list($subject, $time)) {
            $boxes['acc1']->store_message('Scheduled', 'X-Schedule: '.date('D, d M Y H:i O', $time)."\r\nX-Profile-ID: p1\r\n".
                "From: Alice Work <work@example.com>\r\nTo: bob@example.net\r\nSubject: $subject\r\nMessage-ID: <sched$i@example.com>\r\n\r\nbody\r\n", false, true);
        }
        /* a message written in Cypht with its own date format */
        $boxes['acc1']->store_message('Scheduled', 'X-Schedule: '.date('D, d M Y H:i T', $now - 900)."\r\n".
            "From: work@example.com\r\nTo: carol@example.org\r\nSubject: Cypht format\r\nMessage-ID: <sched9@example.com>\r\n\r\nbody\r\n", false, true);
        list($services, $holder) = $this->services($boxes, $smtp);
        $mail = $services->mail(hm_mcp_fake_principal(['read' => false]));
        $report = $mail->run_scheduled(120, 25);
        $mail->finish();
        $this->assertCount(2, $report['sent']);
        $this->assertSame(2, $report['waiting']);
        $this->assertSame(['Due', 'Cypht format'], array_map(function ($sent) {
            preg_match('/^Subject: (.*)$/m', $sent['message'], $m);
            return trim($m[1]);
        }, $smtp['s1']->sent));
        $this->assertSame(['account_id' => 'acc1', 'recipients' => 1, 'domains' => 'example.net'], $report['sent'][0]);
        $this->assertSame(['Just due', 'Later'], array_values(array_column($boxes['acc1']->folders['Scheduled'], 'subject')));
        $this->assertCount(2, $boxes['acc1']->folders['Sent']);

        /* a second runner at the same time does not send the claimed messages again */
        $boxes['acc1']->store_message('Scheduled', 'X-Schedule: '.date('D, d M Y H:i O', $now - 600)."\r\n".
            "From: work@example.com\r\nTo: bob@example.net\r\nSubject: Due\r\nMessage-ID: <sched0@example.com>\r\n\r\nbody\r\n", false, true);
        $mail = $services->mail(hm_mcp_fake_principal(['read' => false]));
        $report = $mail->run_scheduled(120, 25);
        $this->assertSame([], $report['sent']);
        $this->assertCount(2, $smtp['s1']->sent);

        /* failures are retried later */
        $smtp['s1']->error = 'Try again later';
        $mail = $services->mail(hm_mcp_fake_principal(['read' => false]));
        $report = $mail->run_scheduled(0, 25);
        $this->assertCount(1, $report['failed']);
        $this->assertSame('upstream_error', $report['failed'][0]['code']);
        $this->assertSame(['Just due', 'Later', 'Due'], array_values(array_column($boxes['acc1']->folders['Scheduled'], 'subject')));
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_catalog() {
        $catalog = new Hm_MCP_Catalog();
        foreach (['send_message', 'send_draft', 'manage_scheduled'] as $name) {
            $op = $catalog->get($name);
            $this->assertSame('send', $op['permission'], $name);
            $this->assertTrue(Hm_MCP_Catalog::ANNOTATIONS[$op['kind']]['openWorldHint'], $name);
        }
        $this->assertSame('read', $catalog->get('list_scheduled')['permission']);
        $this->assertSame(['openai/fileParams' => ['files']], $catalog->get('send_message')['meta']);
        $this->assertArrayHasKey('send_at', $catalog->get('send_message')['input']['properties']);
        $this->assertArrayNotHasKey('send_at', $catalog->get('create_draft')['input']['properties']);
        $defaults = array_keys($catalog->allowed(Hm_MCP_Permissions::defaults()));
        $this->assertNotContains('send_message', $defaults);
        $this->assertContains('list_scheduled', $defaults);
    }
}
