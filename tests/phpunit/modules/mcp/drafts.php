<?php

use PHPUnit\Framework\TestCase;

/**
 * tests for drafts: MIME messages, attachments from clients, replies and forwards
 */
class Hm_Test_MCP_Drafts extends TestCase {

    private $servers = [
        'acc1' => ['id' => 'acc1', 'name' => 'Work', 'user' => 'work@example.com', 'server' => 'imap.example.com', 'type' => 'imap'],
        'acc2' => ['id' => 'acc2', 'name' => 'Gmail', 'user' => 'me@gmail.example', 'server' => 'imap.gmail.example', 'type' => 'imap'],
        'acc3' => ['id' => 'acc3', 'name' => 'No drafts', 'user' => 'old@example.net', 'server' => 'imap.example.net', 'type' => 'imap'],
    ];

    private $settings = [
        'language_setting' => 'en',
        'profiles' => [
            'p1' => ['id' => 'p1', 'name' => 'Alice Work', 'address' => 'work@example.com', 'replyto' => '', 'smtp_id' => 's1',
                'imap_id' => 'acc1', 'sig' => "Alice\nACME Inc.", 'default' => true, 'type' => 'imap', 'user' => 'work@example.com', 'server' => 'imap.example.com'],
            'p2' => ['id' => 'p2', 'name' => 'Sales', 'address' => 'sales@example.com', 'replyto' => 'team@example.com', 'smtp_id' => 's1',
                'imap_id' => 'acc1', 'sig' => '<p>The <b>sales</b> team</p>', 'default' => false, 'type' => 'imap', 'user' => 'work@example.com', 'server' => 'imap.example.com'],
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
                    'message_id' => '<orig1@example.net>', 'text' => "Shall we meet?\nAt noon.",
                    'headers' => ['Reply-To' => 'Bob Replies <bob-replies@example.net>', 'References' => '<root@example.net>',
                        'Cc' => 'Carol <carol@example.org>, work@example.com']]),
                '2' => hm_mcp_fake_message('Invoice', 'Billing <billing@example.org>', 'Tue, 02 Sep 2026 10:00:00 +0000', [
                    'attachments' => ['invoice.pdf' => ['type' => 'application/pdf', 'content' => '%PDF-1.4 invoice'],
                        'notes.txt' => ['type' => 'text/plain', 'content' => 'some notes']]]),
            ],
            'Sent' => [
                '5' => hm_mcp_fake_message('Plans', 'Alice Work <work@example.com>', 'Wed, 03 Sep 2026 10:00:00 +0000', [
                    'flags' => '\\Seen', 'message_id' => '<sent5@example.com>']),
            ],
            'Drafts' => [],
            'Trash' => [],
        ], ['drafts' => 'Drafts', 'trash' => 'Trash', 'sent' => 'Sent']);
        $acc1->capabilities = ['MOVE', 'UIDPLUS'];
        $acc1->folders['INBOX']['1']['to'] = 'Alice Work <work@example.com>, Dan <dan@example.org>';
        $acc1->folders['Sent']['5']['to'] = 'Dan <dan@example.org>';
        $acc2 = new Hm_MCP_Fake_Mailbox(['INBOX' => [], '[Gmail]/Drafts' => [], '[Gmail]/Trash' => []],
            ['drafts' => '[Gmail]/Drafts', 'trash' => '[Gmail]/Trash']);
        $acc2->capabilities = ['MOVE', 'UIDPLUS', 'X-GM-EXT-1'];
        $acc3 = new Hm_MCP_Fake_Mailbox(['INBOX' => []]);
        return ['acc1' => $acc1, 'acc2' => $acc2, 'acc3' => $acc3];
    }

    private static function id($account, $folder, $uid) {
        return Hm_MCP_Format::message_id($account, $folder, $uid);
    }

    private function run_op($name, $args, $mailboxes, $options = []) {
        list($services, $holder) = hm_mcp_fake_services($mailboxes, $this->servers, $options['settings'] ?? $this->settings);
        $services->site_config->set('mcp_upload_allowed_hosts', 'files.oaiusercontent.com, *.blob.core.windows.net');
        foreach ($options['site'] ?? [] as $name_ => $value) {
            $services->site_config->set($name_, $value);
        }
        $services->upload_resolver = $options['resolver'] ?? function ($host) { return ['93.184.216.34']; };
        $services->upload_fetcher = $options['fetcher'] ?? null;
        $principal = $options['principal'] ?? hm_mcp_fake_principal();
        $res = $services->executor($principal, 'mcp')->run($name, $args);
        if ($res['ok']) {
            $op = $services->catalog()->get($name);
            $errors = $services->validator()->validateAgainstJsonSchema(json_decode(json_encode($res['result'])),
                Hm_MCP_Catalog::normalize($op['output']));
            $this->assertSame([], $errors, $name.' output does not match its schema');
        }
        $res['logged'] = $services->store()->logged;
        $res['services'] = $services;
        return $res;
    }

    private static function error_code($res) {
        return $res['ok'] ? null : $res['error']->error_code;
    }

    private static function draft($mailbox, $folder, $uid) {
        return Hm_MCP_Mime::parse($mailbox->folders[$folder][$uid]['raw']);
    }

    /* ------------------------------------------------------------- MIME */

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_addresses_are_parsed_strictly() {
        $this->assertSame(['name' => 'Bob', 'email' => 'bob@example.com'], Hm_MCP_Mime::parse_address('Bob <bob@example.com>'));
        $this->assertSame(['name' => 'Doe, John', 'email' => 'j@example.com'], Hm_MCP_Mime::parse_address('"Doe, John" <j@example.com>'));
        $this->assertSame(['name' => '', 'email' => 'alice@localhost'], Hm_MCP_Mime::parse_address(' alice@localhost '));
        foreach (['bob', 'a@b@c.com', "bob@example.com\r\nBcc: x@example.com", '<>', '', 'Bob <bob@example.com', 'bob@-example.com',
            'Bob <bob@example.com>, eve@example.com', str_repeat('a', 65).'@example.com'] as $bad) {
            try {
                Hm_MCP_Mime::parse_address($bad, 'to');
                $this->fail('accepted '.json_encode($bad));
            } catch (Hm_MCP_Error $e) {
                $this->assertSame('invalid_argument', $e->error_code);
            }
        }
        $this->assertSame([['name' => 'A', 'email' => 'a@example.com']],
            Hm_MCP_Mime::parse_addresses(['A <a@example.com>', 'A@EXAMPLE.com'], 'to'));
        $this->assertSame('"Doe, John" <j@example.com>', Hm_MCP_Mime::format_address(['name' => 'Doe, John', 'email' => 'j@example.com']));
        $this->assertSame('=?UTF-8?B?'.base64_encode('José').'?= <j@example.com>', Hm_MCP_Mime::format_address(['name' => 'José', 'email' => 'j@example.com']));
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_messages_are_built_and_read_back() {
        $text = "Hola José,\n.leading dot\n".str_repeat('largo ', 30)."\ntrailing   \n\nAdiós";
        $spec = [
            'from' => ['name' => 'Alice Work', 'email' => 'work@example.com'],
            'reply_to' => [['name' => '', 'email' => 'team@example.com']],
            'to' => [['name' => 'Bob', 'email' => 'bob@example.net'], ['name' => 'Ñandú', 'email' => 'n@example.org']],
            'cc' => [], 'bcc' => [['name' => 'Secret', 'email' => 'secret@example.com']],
            'subject' => "Reunión de mañana\r\nBcc: evil@example.com",
            'text' => $text, 'html' => null, 'message_id' => '<m1@example.com>',
            'in_reply_to' => ['<orig@example.net>', 'garbage'], 'references' => ['<root@example.net>', '<orig@example.net>', "<bad\r\n>"],
            'attachments' => [], 'draft' => true,
        ];
        $raw = Hm_MCP_Mime::build($spec);
        $this->assertSame(0, preg_match("/(?<!\r)\n/", $raw), 'bare LF in the message');
        list($head) = explode("\r\n\r\n", $raw, 2);
        $this->assertStringNotContainsString('evil@example.com', $head === '' ? '' : preg_replace('/=\?UTF-8\?B\?[^?]+\?=/', '', $head));
        $this->assertSame(0, preg_match('/^Bcc: evil/mi', $raw));
        $this->assertStringContainsString("Bcc: Secret <secret@example.com>\r\n", $raw);
        $this->assertStringContainsString("X-Original-Bcc: Secret <secret@example.com>\r\n", $raw);
        $this->assertStringContainsString("In-Reply-To: <orig@example.net>\r\n", $raw);
        $this->assertStringContainsString("References: <root@example.net>\r\n <orig@example.net>\r\n", $raw);
        $this->assertStringContainsString("Content-Transfer-Encoding: quoted-printable\r\n", $raw);
        $this->assertStringContainsString("\r\n=2Eleading dot\r\n", $raw);
        foreach (explode("\r\n", $raw) as $line) {
            $this->assertLessThanOrEqual(998, strlen($line));
        }
        $parsed = Hm_MCP_Mime::parse($raw);
        $this->assertSame(['name' => 'Alice Work', 'email' => 'work@example.com'], $parsed['from']);
        $this->assertSame($spec['to'], $parsed['to']);
        $this->assertSame([['name' => 'Secret', 'email' => 'secret@example.com']], $parsed['bcc']);
        $this->assertSame([['name' => '', 'email' => 'team@example.com']], $parsed['reply_to']);
        $this->assertSame('Reunión de mañana Bcc: evil@example.com', $parsed['subject']);
        $this->assertSame(Hm_MCP_Mime::normalize_text($text), Hm_MCP_Mime::normalize_text($parsed['text']));
        $this->assertNull($parsed['html']);
        $this->assertSame(['<orig@example.net>'], $parsed['in_reply_to']);
        $this->assertSame(['<root@example.net>', '<orig@example.net>'], $parsed['references']);

        /* sent messages never carry the hidden recipients */
        $spec['draft'] = false;
        $this->assertStringNotContainsString('secret@example.com', Hm_MCP_Mime::build($spec));
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_markdown_bodies_are_safe() {
        list($plain, $html) = Hm_MCP_Mime::bodies("**Hola** equipo\nsegunda línea\n\n<script>alert(1)</script>\n\n[ok](https://example.com) [bad](javascript:alert(1))", 'markdown');
        $this->assertStringContainsString('**Hola**', $plain);
        $this->assertStringContainsString('<strong>Hola</strong> equipo<br>', $html);
        $this->assertStringNotContainsString('<script', $html);
        $this->assertStringNotContainsString('javascript:', $html);
        $this->assertStringContainsString('href="https://example.com"', $html);
        list($plain, $html) = Hm_MCP_Mime::bodies('Gracias', 'markdown', ['type' => 'reply', 'lead_in' => 'On Monday Bob said', 'text' => "Hi <b>there</b>\nBye"]);
        $this->assertSame("Gracias\n\nOn Monday Bob said\n\n> Hi <b>there</b>\n> Bye", $plain);
        $this->assertStringContainsString('<blockquote type="cite"', $html);
        $this->assertStringContainsString('Hi &lt;b&gt;there&lt;/b&gt;<br>', $html);
        $raw = Hm_MCP_Mime::build(['from' => ['name' => '', 'email' => 'a@example.com'], 'to' => [], 'cc' => [], 'bcc' => [], 'subject' => 's',
            'text' => $plain, 'html' => $html, 'message_id' => '<x@example.com>', 'attachments' => []]);
        $this->assertStringContainsString('Content-Type: multipart/alternative; boundary="=_cypht_', $raw);
        $parsed = Hm_MCP_Mime::parse($raw);
        $this->assertStringContainsString('<blockquote', $parsed['html']);
        $this->assertStringContainsString('> Bye', $parsed['text']);
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_attachments_round_trip() {
        $binary = random_bytes(3000);
        $raw = Hm_MCP_Mime::build(['from' => ['name' => '', 'email' => 'a@example.com'], 'to' => [], 'cc' => [], 'bcc' => [], 'subject' => 's',
            'text' => 'see attached', 'html' => null, 'message_id' => '<x@example.com>', 'attachments' => [
                ['filename' => '../../etc/informe año "final".pdf', 'content_type' => 'application/pdf', 'data' => $binary],
                ['filename' => 'note.eml', 'content_type' => 'message/rfc822', 'data' => "Subject: inner\nFrom: x@example.com\n\nbody\n"],
                ['filename' => 'data.csv', 'content_type' => 'multipart/mixed; boundary=x', 'data' => "a,b\n"],
            ]]);
        $this->assertStringContainsString('Content-Type: multipart/mixed; boundary="=_cypht_', $raw);
        $this->assertStringContainsString("Content-Transfer-Encoding: 7bit\r\n\r\nSubject: inner\r\n", $raw);
        $parsed = Hm_MCP_Mime::parse($raw);
        $this->assertSame('see attached', trim($parsed['text']));
        $this->assertCount(3, $parsed['attachments']);
        list($pdf, $eml, $csv) = $parsed['attachments'];
        $this->assertSame(['2', 'informe año _final_.pdf', 'application/pdf'], [$pdf['part_id'], $pdf['filename'], $pdf['content_type']]);
        $this->assertSame($binary, $pdf['data']);
        $this->assertSame(['3', 'message/rfc822'], [$eml['part_id'], $eml['content_type']]);
        $this->assertSame(['4', 'application/octet-stream'], [$csv['part_id'], $csv['content_type']]);
        $this->assertSame('attachment', Hm_MCP_Mime::filename(" ..\r\n"));
        $this->assertSame(Hm_MCP_Mime::MAX_FILENAME, mb_strlen(Hm_MCP_Mime::filename(str_repeat('ñ', 300).'.pdf')));
        $this->assertStringEndsWith('.pdf', Hm_MCP_Mime::filename(str_repeat('ñ', 300).'.pdf'));
    }

    /* ----------------------------------------------------------- uploads */

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_download_links_are_checked() {
        $config = new Hm_Mock_Config();
        $config->set('mcp_upload_allowed_hosts', 'files.oaiusercontent.com, *.blob.core.windows.net');
        $uploads = new Hm_MCP_Uploads(new Hm_MCP_Config($config));
        $ips = ['files.oaiusercontent.com' => ['93.184.216.34'], 'x.blob.core.windows.net' => ['20.60.1.1'],
            'evil.blob.core.windows.net' => ['10.0.0.5'], 'v6.blob.core.windows.net' => ['2001:db8::1', 'fd00::1']];
        $uploads->resolver = function ($host) use ($ips) { return $ips[$host] ?? []; };
        $calls = [];
        $uploads->fetcher = function ($url, $host, $ip, $max, $timeout) use (&$calls) {
            $calls[] = [$url, $host, $ip];
            if (strpos($url, '/redirect-out') !== false) {
                return [302, ['location' => 'https://evil.example.com/x'], ''];
            }
            if (strpos($url, '/redirect-in') !== false) {
                return [302, ['location' => 'https://x.blob.core.windows.net/real/file.bin?sig=1'], ''];
            }
            if (strpos($url, '/expired') !== false) {
                return [403, [], 'no'];
            }
            return [200, ['content-type' => 'application/pdf; charset=binary'], str_repeat('A', 100)];
        };
        $file = $uploads->from_file(['download_url' => 'https://files.oaiusercontent.com/file-1?sig=abc', 'file_id' => 'file-1',
            'file_name' => 'Informe.pdf'], 1000);
        $this->assertSame(['filename' => 'Informe.pdf', 'content_type' => 'application/pdf', 'data' => str_repeat('A', 100)], $file);
        $this->assertSame(['https://files.oaiusercontent.com/file-1?sig=abc', 'files.oaiusercontent.com', '93.184.216.34'], $calls[0]);
        $file = $uploads->from_file(['download_url' => 'https://files.oaiusercontent.com/redirect-in', 'file_id' => 'f'], 1000);
        $this->assertSame('file.bin', $file['filename']);
        $this->assertSame('20.60.1.1', end($calls)[2]);

        $cases = [
            ['http://files.oaiusercontent.com/file', 'invalid_argument'],
            ['https://files.oaiusercontent.com:8443/file', 'invalid_argument'],
            ['https://user:pw@files.oaiusercontent.com/file', 'invalid_argument'],
            ['https://evil.example.com/file', 'invalid_argument'],
            ['https://evil.blob.core.windows.net/file', 'invalid_argument'],
            ['https://v6.blob.core.windows.net/file', 'invalid_argument'],
            ['https://nothing.blob.core.windows.net/file', 'upstream_error'],
            ['https://files.oaiusercontent.com/redirect-out', 'invalid_argument'],
            ['https://files.oaiusercontent.com/expired', 'upstream_error'],
            ['file:///etc/passwd', 'invalid_argument'],
        ];
        foreach ($cases as list($url, $code)) {
            try {
                $uploads->from_file(['download_url' => $url, 'file_id' => 'f'], 1000);
                $this->fail('downloaded '.$url);
            } catch (Hm_MCP_Error $e) {
                $this->assertSame($code, $e->error_code, $url);
            }
        }
        try {
            $uploads->from_file(['download_url' => 'https://files.oaiusercontent.com/big', 'file_id' => 'f'], 99);
            $this->fail('accepted a file over the limit');
        } catch (Hm_MCP_Error $e) {
            $this->assertSame('payload_too_large', $e->error_code);
        }
        foreach (['127.0.0.1', '10.1.2.3', '192.168.1.1', '169.254.169.254', '100.64.0.1', '0.0.0.0', '::1', 'fe80::1', '::ffff:10.0.0.1', 'fd12::1'] as $ip) {
            $this->assertFalse(Hm_MCP_Uploads::public_ip($ip), $ip);
        }
        $this->assertTrue(Hm_MCP_Uploads::public_ip('93.184.216.34'));
        $this->assertTrue(Hm_MCP_Uploads::public_ip('2606:4700::1111'));
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_base64_attachments() {
        $uploads = new Hm_MCP_Uploads(new Hm_MCP_Config(new Hm_Mock_Config()));
        $file = $uploads->from_base64(['filename' => 'a.pdf', 'content_base64' => base64_encode("%PDF-1.4\n%binary\n")], 1000);
        $this->assertSame('application/pdf', $file['content_type']);
        $file = $uploads->from_base64(['filename' => 'b.txt', 'content_type' => 'text/plain', 'content_base64' => chunk_split(base64_encode('hi'))], 1000);
        $this->assertSame(['b.txt', 'text/plain', 'hi'], [$file['filename'], $file['content_type'], $file['data']]);
        foreach ([['not base64!', 1000, 'invalid_argument'], [base64_encode(str_repeat('x', 200)), 100, 'payload_too_large']] as list($content, $budget, $code)) {
            try {
                $uploads->from_base64(['filename' => 'c', 'content_base64' => $content], $budget);
                $this->fail('accepted '.$content);
            } catch (Hm_MCP_Error $e) {
                $this->assertSame($code, $e->error_code);
            }
        }
    }

    /* ------------------------------------------------------------ drafts */

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_create_a_new_draft() {
        $boxes = $this->mailboxes();
        $res = $this->run_op('create_draft', ['account_id' => 'acc1', 'to' => ['Bob <bob@example.net>'], 'bcc' => ['hidden@example.org'],
            'subject' => 'Hola', 'body' => 'Texto del mensaje', 'include_signature' => true], $boxes);
        $this->assertTrue($res['ok'], $res['ok'] ? '' : $res['error']->getMessage());
        $data = $res['result']['data'];
        $this->assertSame(['acc1', 'Drafts', '1'], Hm_MCP_Format::parse_message_id($data['draft_id']));
        $this->assertSame(['name' => 'Alice Work', 'email' => 'work@example.com'], $data['from']);
        $this->assertSame('text', $data['format']);
        $this->assertStringContainsString('page=compose&imap_draft=1', $data['url']);
        $this->assertStringContainsString('Nothing was sent', $res['result']['message']);
        $stored = $boxes['acc1']->folders['Drafts']['1'];
        $this->assertSame('\\Draft', $stored['flags']);
        $draft = Hm_MCP_Mime::parse($stored['raw']);
        $this->assertSame([['name' => 'Bob', 'email' => 'bob@example.net']], $draft['to']);
        $this->assertSame([['name' => '', 'email' => 'hidden@example.org']], $draft['bcc']);
        $this->assertSame("Texto del mensaje\n\nAlice\nACME Inc.", Hm_MCP_Mime::normalize_text($draft['text']));
        $this->assertSame(['account_id' => 'acc1', 'mode' => 'new', 'recipients' => 2, 'attachments' => 0], $res['logged'][0]['summary']);

        /* the draft reads back with its hidden recipients */
        $res = $this->run_op('get_message', ['message_id' => $data['draft_id']], $boxes);
        $this->assertTrue($res['result']['data']['draft']);
        $this->assertSame('hidden@example.org', $res['result']['data']['bcc'][0]['email']);
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_sender_and_account_choice() {
        $boxes = $this->mailboxes();
        $res = $this->run_op('create_draft', ['subject' => 'x', 'from' => 'sales@example.com'], $boxes);
        $this->assertTrue($res['ok']);
        $this->assertSame('acc1', $res['result']['data']['account_id']);
        $draft = self::draft($boxes['acc1'], 'Drafts', '1');
        $this->assertSame(['name' => 'Sales', 'email' => 'sales@example.com'], $draft['from']);
        $this->assertSame([['name' => '', 'email' => 'team@example.com']], $draft['reply_to']);

        $res = $this->run_op('create_draft', ['account_id' => 'acc1', 'from' => 'ceo@example.com'], $boxes);
        $this->assertSame('invalid_argument', self::error_code($res));
        $this->assertStringContainsString('work@example.com', $res['error']->getMessage());
        /* without a default profile the account must be chosen */
        $res = $this->run_op('create_draft', ['subject' => 'x'], $boxes, ['settings' => []]);
        $this->assertSame('invalid_argument', self::error_code($res));
        $this->assertStringContainsString('account_id', $res['error']->getMessage());
        /* the account address is used when there is no profile */
        $res = $this->run_op('create_draft', ['account_id' => 'acc2', 'subject' => 'x'], $boxes, ['settings' => []]);
        $this->assertSame(['name' => '', 'email' => 'me@gmail.example'], $res['result']['data']['from']);
        $this->assertSame('[Gmail]/Drafts', $res['result']['data']['folder']);
        $res = $this->run_op('list_accounts', [], $boxes);
        $this->assertSame(['work@example.com', 'sales@example.com'], array_column($res['result']['data']['accounts'][0]['send_as'], 'email'));

        $res = $this->run_op('create_draft', ['account_id' => 'acc3', 'subject' => 'x'], $boxes);
        $this->assertSame('not_found', self::error_code($res));
        $this->assertStringContainsString('no drafts folder', $res['error']->getMessage());
        $res = $this->run_op('create_draft', ['account_id' => 'acc1', 'to' => ['not an address']], $boxes);
        $this->assertSame('invalid_argument', self::error_code($res));
        $res = $this->run_op('create_draft', ['account_id' => 'acc1', 'message_id' => self::id('acc1', 'INBOX', '1')], $boxes);
        $this->assertSame('invalid_argument', self::error_code($res));
        $res = $this->run_op('create_draft', ['account_id' => 'acc1', 'mode' => 'reply'], $boxes);
        $this->assertSame('invalid_argument', self::error_code($res));
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_reply_and_reply_all() {
        $boxes = $this->mailboxes();
        $res = $this->run_op('create_draft', ['mode' => 'reply', 'message_id' => self::id('acc1', 'INBOX', '1'), 'body' => 'Sure, see you.'], $boxes);
        $this->assertTrue($res['ok'], $res['ok'] ? '' : $res['error']->getMessage());
        $data = $res['result']['data'];
        $this->assertSame([['name' => 'Bob Replies', 'email' => 'bob-replies@example.net']], $data['to']);
        $this->assertSame([], $data['cc']);
        $this->assertSame('Re: Lunch', $data['subject']);
        $this->assertSame('<orig1@example.net>', $data['in_reply_to']);
        $draft = self::draft($boxes['acc1'], 'Drafts', '1');
        $this->assertSame(['<root@example.net>', '<orig1@example.net>'], $draft['references']);
        $text = Hm_MCP_Mime::normalize_text($draft['text']);
        $this->assertStringStartsWith("Sure, see you.\n\nOn Mon, 01 Sep 2026 10:00:00 +0000 Bob <bob@example.net> said\n\n> Shall we meet?\n> At noon.", $text);
        /* the original was read without marking it as read */
        $this->assertSame([['INBOX', '1', true]], $boxes['acc1']->header_reads);
        $this->assertSame([], array_filter($boxes['acc1']->actions, function ($a) { return $a[1] === 'READ'; }));

        $res = $this->run_op('create_draft', ['mode' => 'reply_all', 'message_id' => self::id('acc1', 'INBOX', '1'), 'quote_original' => false], $boxes);
        $this->assertSame(['dan@example.org', 'carol@example.org'], array_column($res['result']['data']['cc'], 'email'));
        $this->assertSame('', Hm_MCP_Mime::normalize_text(self::draft($boxes['acc1'], 'Drafts', '2')['text']));

        /* answering a sent message writes to its recipients again */
        $res = $this->run_op('create_draft', ['mode' => 'reply', 'message_id' => self::id('acc1', 'Sent', '5')], $boxes);
        $this->assertSame([['name' => 'Dan', 'email' => 'dan@example.org']], $res['result']['data']['to']);

        /* Spanish lead-in for a Spanish speaking user */
        $settings = $this->settings;
        $settings['language_setting'] = 'es';
        $res = $this->run_op('create_draft', ['mode' => 'reply', 'message_id' => self::id('acc1', 'INBOX', '1'), 'subject' => 'Re: Almuerzo'], $boxes,
            ['settings' => $settings]);
        $this->assertStringContainsString('El Mon, 01 Sep 2026 10:00:00 +0000, Bob <bob@example.net> dijo:', self::draft($boxes['acc1'], 'Drafts', '4')['text']);
        $this->assertSame('Re: Almuerzo', $res['result']['data']['subject']);

        $res = $this->run_op('create_draft', ['mode' => 'reply', 'message_id' => self::id('acc1', 'INBOX', '99')], $boxes);
        $this->assertSame('not_found', self::error_code($res));
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_forward_with_attachments() {
        $boxes = $this->mailboxes();
        $res = $this->run_op('create_draft', ['mode' => 'forward', 'message_id' => self::id('acc1', 'INBOX', '2'),
            'to' => ['Eve <eve@example.org>'], 'body' => 'FYI', 'body_format' => 'markdown'], $boxes);
        $this->assertTrue($res['ok'], $res['ok'] ? '' : $res['error']->getMessage());
        $data = $res['result']['data'];
        $this->assertSame('Fwd: Invoice', $data['subject']);
        $this->assertSame('', $data['in_reply_to']);
        $this->assertSame('html', $data['format']);
        $this->assertSame(['invoice.pdf', 'notes.txt'], array_column($data['attachments'], 'filename'));
        $draft = self::draft($boxes['acc1'], 'Drafts', '1');
        $this->assertSame(['%PDF-1.4 invoice', 'some notes'], array_column($draft['attachments'], 'data'));
        $this->assertStringContainsString("----- begin forwarded message -----\nFrom: Billing <billing@example.org>", Hm_MCP_Mime::normalize_text($draft['text']));
        $this->assertStringContainsString('<p>FYI</p>', $draft['html']);
        $this->assertSame(2, $res['logged'][0]['summary']['attachments']);

        $res = $this->run_op('create_draft', ['mode' => 'forward', 'message_id' => self::id('acc1', 'INBOX', '2'), 'include_attachments' => false], $boxes);
        $this->assertSame([], $res['result']['data']['attachments']);
        $this->assertSame([], $res['result']['data']['to']);
        $res = $this->run_op('create_draft', ['mode' => 'forward', 'message_id' => self::id('acc1', 'INBOX', '2')], $boxes,
            ['site' => ['mcp_max_upload_bytes' => 1024 * 1024]]);
        $this->assertTrue($res['ok']);
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_files_from_chatgpt_and_base64() {
        $boxes = $this->mailboxes();
        $fetcher = function ($url, $host, $ip, $max, $timeout) {
            return [200, ['content-type' => 'application/pdf'], '%PDF-1.7 report'];
        };
        $res = $this->run_op('create_draft', ['account_id' => 'acc1', 'subject' => 'Report',
            'files' => [['download_url' => 'https://files.oaiusercontent.com/file-xyz?se=1&sig=2', 'file_id' => 'file-xyz', 'file_name' => 'report.pdf']],
            'attachments' => [['filename' => 'notes.txt', 'content_base64' => base64_encode('hello')]]], $boxes, ['fetcher' => $fetcher]);
        $this->assertTrue($res['ok'], $res['ok'] ? '' : $res['error']->getMessage());
        $this->assertSame([['filename' => 'report.pdf', 'content_type' => 'application/pdf', 'size' => 15],
            ['filename' => 'notes.txt', 'content_type' => 'text/plain', 'size' => 5]], $res['result']['data']['attachments']);

        $res = $this->run_op('create_draft', ['account_id' => 'acc1', 'files' => [['download_url' => 'https://example.com/x', 'file_id' => 'f']]],
            $boxes, ['fetcher' => $fetcher]);
        $this->assertSame('invalid_argument', self::error_code($res));
        $res = $this->run_op('create_draft', ['account_id' => 'acc1', 'files' => [['download_url' => 'https://files.oaiusercontent.com/x', 'file_id' => 'f']]],
            $boxes, ['fetcher' => $fetcher, 'site' => ['mcp_max_upload_bytes' => 1024], 'resolver' => function () { return ['10.0.0.1']; }]);
        $this->assertSame('invalid_argument', self::error_code($res));
        $res = $this->run_op('create_draft', ['account_id' => 'acc1', 'attachments' => [['filename' => 'big', 'content_base64' => base64_encode(str_repeat('x', 2000))]]],
            $boxes, ['site' => ['mcp_max_upload_bytes' => 1024]]);
        $this->assertSame('payload_too_large', self::error_code($res));
        $res = $this->run_op('create_draft', ['account_id' => 'acc1', 'files' => [['download_url' => 'https://files.oaiusercontent.com/x', 'file_id' => 'f', 'extra' => 1]]],
            $boxes, ['fetcher' => $fetcher]);
        $this->assertSame('invalid_argument', self::error_code($res));
        /* failed calls saved nothing */
        $this->assertCount(1, $boxes['acc1']->folders['Drafts']);
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_update_a_draft() {
        $boxes = $this->mailboxes();
        $res = $this->run_op('create_draft', ['account_id' => 'acc1', 'to' => ['bob@example.net'], 'subject' => 'Old', 'body' => '**Hi**',
            'body_format' => 'markdown', 'attachments' => [['filename' => 'a.txt', 'content_base64' => base64_encode('AAA')],
                ['filename' => 'b.txt', 'content_base64' => base64_encode('BBB')]]], $boxes);
        $first = $res['result']['data']['draft_id'];
        $res = $this->run_op('update_draft', ['draft_id' => $first, 'subject' => 'New', 'cc' => ['carol@example.org'],
            'remove_attachments' => ['2'], 'attachments' => [['filename' => 'c.txt', 'content_base64' => base64_encode('CCC')]]], $boxes);
        $this->assertTrue($res['ok'], $res['ok'] ? '' : $res['error']->getMessage());
        $data = $res['result']['data'];
        $this->assertSame($first, $data['replaced_draft_id']);
        $this->assertSame(['acc1', 'Drafts', '2'], Hm_MCP_Format::parse_message_id($data['draft_id']));
        $this->assertSame(['2'], array_map('strval', array_keys($boxes['acc1']->folders['Drafts'])));
        $this->assertContains('UID EXPUNGE 1', $boxes['acc1']->get_connection()->commands);
        $draft = self::draft($boxes['acc1'], 'Drafts', '2');
        $this->assertSame('New', $draft['subject']);
        $this->assertSame('bob@example.net', $draft['to'][0]['email']);
        $this->assertSame('carol@example.org', $draft['cc'][0]['email']);
        $this->assertSame(['b.txt', 'c.txt'], array_column($draft['attachments'], 'filename'));
        $this->assertStringContainsString('<strong>Hi</strong>', $draft['html']);

        /* a new body keeps the format of the draft unless told otherwise */
        $res = $this->run_op('update_draft', ['draft_id' => $data['draft_id'], 'body' => 'Plain *now*'], $boxes);
        $draft = self::draft($boxes['acc1'], 'Drafts', '3');
        $this->assertStringContainsString('<em>now</em>', $draft['html']);
        $res = $this->run_op('update_draft', ['draft_id' => $res['result']['data']['draft_id'], 'body_format' => 'text'], $boxes);
        $draft = self::draft($boxes['acc1'], 'Drafts', '4');
        $this->assertNull($draft['html']);
        $this->assertSame('Plain *now*', Hm_MCP_Mime::normalize_text($draft['text']));
        $this->assertSame('text', $res['result']['data']['format']);

        $res = $this->run_op('update_draft', ['draft_id' => $res['result']['data']['draft_id'], 'remove_attachments' => ['9']], $boxes);
        $this->assertSame('invalid_argument', self::error_code($res));
        $this->assertStringContainsString('2, 3', $res['error']->getMessage());
        $res = $this->run_op('update_draft', ['draft_id' => $first, 'subject' => 'x'], $boxes);
        $this->assertSame('not_found', self::error_code($res));
        $res = $this->run_op('update_draft', ['draft_id' => self::id('acc1', 'INBOX', '1'), 'subject' => 'x'], $boxes);
        $this->assertSame('invalid_argument', self::error_code($res));
        $this->assertSame('Lunch', $boxes['acc1']->folders['INBOX']['1']['subject']);
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_delete_a_draft() {
        $boxes = $this->mailboxes();
        $id = $this->run_op('create_draft', ['account_id' => 'acc1', 'subject' => 'Bye'], $boxes)['result']['data']['draft_id'];
        $res = $this->run_op('delete_draft', ['draft_id' => $id], $boxes);
        $this->assertTrue($res['ok']);
        $this->assertSame([], $boxes['acc1']->folders['Drafts']);
        $this->assertSame('not_found', self::error_code($this->run_op('delete_draft', ['draft_id' => $id], $boxes)));
        /* not a way to delete other messages */
        $res = $this->run_op('delete_draft', ['draft_id' => self::id('acc1', 'INBOX', '1')], $boxes);
        $this->assertSame('invalid_argument', self::error_code($res));
        $this->assertArrayHasKey('1', $boxes['acc1']->folders['INBOX']);
        $res = $this->run_op('delete_draft', ['draft_id' => $id], $boxes, ['principal' => hm_mcp_fake_principal(['read' => true, 'drafts' => false])]);
        $this->assertSame('permission_denied', self::error_code($res));
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_gmail_drafts_are_removed_through_the_trash() {
        $boxes = $this->mailboxes();
        $id = $this->run_op('create_draft', ['account_id' => 'acc2', 'subject' => 'v1'], $boxes)['result']['data']['draft_id'];
        $res = $this->run_op('update_draft', ['draft_id' => $id, 'subject' => 'v2'], $boxes);
        $this->assertTrue($res['ok']);
        $this->assertSame(['2'], array_map('strval', array_keys($boxes['acc2']->folders['[Gmail]/Drafts'])));
        $this->assertSame([], $boxes['acc2']->folders['[Gmail]/Trash']);
        $this->assertContains(['[Gmail]/Drafts', 'MOVE', ['1'], false], $boxes['acc2']->actions);
        $res = $this->run_op('delete_draft', ['draft_id' => $res['result']['data']['draft_id']], $boxes);
        $this->assertTrue($res['ok']);
        $this->assertSame([], $boxes['acc2']->folders['[Gmail]/Drafts']);
        $this->assertSame([], $boxes['acc2']->folders['[Gmail]/Trash']);
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_draft_ids_are_found_without_uidplus() {
        $boxes = $this->mailboxes();
        $boxes['acc1']->capabilities = ['MOVE'];
        $res = $this->run_op('create_draft', ['account_id' => 'acc1', 'subject' => 'No uidplus'], $boxes);
        $this->assertSame(['acc1', 'Drafts', '1'], Hm_MCP_Format::parse_message_id($res['result']['data']['draft_id']));
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_catalog_and_rest_routes() {
        $catalog = new Hm_MCP_Catalog();
        $create = $catalog->get('create_draft');
        $this->assertSame(['openai/fileParams' => ['files']], $create['meta']);
        $item = $create['input']['properties']['files']['items'];
        $this->assertSame(['download_url', 'file_id', 'mime_type', 'file_name'], array_keys($item['properties']));
        $this->assertSame(['download_url', 'file_id'], $item['required']);
        $this->assertSame(['openai/fileParams' => ['files']], $catalog->get('update_draft')['meta']);
        $this->assertTrue(Hm_MCP_Catalog::ANNOTATIONS[$catalog->get('delete_draft')['kind']]['destructiveHint']);
        $this->assertArrayHasKey('openai/fileParams', Hm_MCP_Catalog::tool_meta($create));
        $defaults = array_keys($catalog->allowed(Hm_MCP_Permissions::defaults()));
        foreach (['create_draft', 'update_draft', 'delete_draft'] as $name) {
            $this->assertContains($name, $defaults);
        }
        list($services) = hm_mcp_fake_services([], $this->servers);
        $paths = (new Hm_MCP_Rest($services))->openapi()['paths'];
        $this->assertArrayHasKey('post', $paths['/drafts']);
        $this->assertArrayHasKey('patch', $paths['/drafts/{draft_id}']);
        $this->assertArrayNotHasKey('requestBody', $paths['/drafts/{draft_id}']['delete']);
        $this->assertArrayNotHasKey('draft_id', $paths['/drafts/{draft_id}']['patch']['requestBody']['content']['application/json']['schema']['properties']);
    }
}
