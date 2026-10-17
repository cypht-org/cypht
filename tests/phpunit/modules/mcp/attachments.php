<?php

use PHPUnit\Framework\TestCase;

/**
 * tests for attachments in Hm_MCP_Mail and Hm_MCP_Files
 */
class Hm_Test_MCP_Attachments extends TestCase {

    private $servers = [
        'acc1' => ['id' => 'acc1', 'name' => 'Work', 'user' => 'work@example.com', 'server' => 'imap.example.com', 'type' => 'imap'],
    ];

    public function setUp(): void {
        require_once __DIR__.'/fakes.php';
        date_default_timezone_set('UTC');
    }

    private function mailbox() {
        $inner = "Subject: Forwarded =?utf-8?q?ma=C3=B1ana?=\r\nFrom: Eve <eve@example.org>\r\nDate: Mon, 14 Sep 2026 10:00:00 +0000\r\n".
            "Content-Type: text/html; charset=utf-8\r\n\r\n<p>Inner <b>body</b></p><div style=\"display:none\">HIDDEN</div>\r\n";
        return new Hm_MCP_Fake_Mailbox(['INBOX' => [
            '5' => hm_mcp_fake_message('Files', 'Bob <bob@example.net>', 'Mon, 14 Sep 2026 10:00:00 +0000', ['attachments' => [
                'notes.txt' => ['type' => 'text/plain', 'content' => "Line one\r\nLine two\x07"],
                'page.html' => ['type' => 'text/html', 'content' => '<p>Visible</p><script>evil()</script><span hidden>secret</span>'],
                'report.pdf' => ['type' => 'application/pdf', 'content' => "%PDF-1.4 binary\x00\x01", 'struct' => ['encoding' => 'base64', 'size' => '40']],
                'fwd.eml' => ['type' => 'message/rfc822', 'content' => $inner],
                'bare.eml' => ['type' => 'message/rfc822', 'content' => 'only the body',
                    'struct' => ['envelope' => ['subject' => 'Envelope subject', 'from' => 'x@example.org', 'date' => false]]],
                'huge.bin' => ['type' => 'application/octet-stream', 'content' => 'x', 'struct' => ['encoding' => 'base64', 'size' => '20000000']],
                'long.txt' => ['type' => 'text/plain', 'content' => str_repeat('a', 3000)],
            ]]),
        ]]);
    }

    private function run_op($name, $args, $principal = null, &$services = null) {
        list($services, $holder) = hm_mcp_fake_services(['acc1' => $this->mailbox()], $this->servers);
        $res = $services->executor($principal ?? hm_mcp_fake_principal(), 'mcp')->run($name, $args);
        if ($res['ok']) {
            $errors = $services->validator()->validateAgainstJsonSchema(json_decode(json_encode(Hm_MCP_Rest::public_result($res['result']))),
                Hm_MCP_Catalog::normalize($services->catalog()->get($name)['output']));
            $this->assertSame([], $errors, $name.' output does not match its schema');
        }
        return $res;
    }

    private function id() {
        return Hm_MCP_Format::message_id('acc1', 'INBOX', '5');
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_get_message_lists_readable_attachments() {
        $data = $this->run_op('get_message', ['message_id' => $this->id()])['result']['data'];
        $readable = array_column($data['attachments'], 'readable', 'filename');
        $this->assertSame(['notes.txt' => true, 'page.html' => true, 'report.pdf' => false, 'fwd.eml' => true,
            'bare.eml' => true, 'huge.bin' => false, 'long.txt' => true], $readable);
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_text_attachment_with_download_link() {
        $res = $this->run_op('get_attachment', ['message_id' => $this->id(), 'part_id' => '2'], null, $services)['result'];
        $data = $res['data'];
        $this->assertTrue($data['readable']);
        $this->assertSame('text', $data['format']);
        $this->assertSame("Line one\nLine two", $data['text']);
        $this->assertSame('notes.txt', $data['filename']);
        $this->assertMatchesRegularExpression('#^https://mail\.example\.com/api/v1/files/cyp_f_[A-Za-z0-9_-]{43}$#', $data['download_url']);
        $this->assertSame('cypht://attachment/'.$this->id().'/2', $data['resource_uri']);
        $this->assertStringContainsString('untrusted', $data['notice']);
        $this->assertSame([['uri' => $data['resource_uri'], 'name' => 'notes.txt', 'mime_type' => 'text/plain', 'size' => 300]],
            $res['_resource_links']);
        $issued = array_values($services->store()->issued)[0];
        $this->assertSame(['connection_id' => 'con_test', 'kind' => 'file', 'ttl' => 900,
            'data' => ['account_id' => 'acc1', 'folder' => 'INBOX', 'uid' => '5', 'part_id' => '2']], $issued);
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_html_and_attached_messages_are_converted() {
        $html = $this->run_op('get_attachment', ['message_id' => $this->id(), 'part_id' => '3'])['result']['data'];
        $this->assertSame('html', $html['format']);
        $this->assertSame('Visible', $html['text']);
        $fwd = $this->run_op('get_attachment', ['message_id' => $this->id(), 'part_id' => '5'])['result']['data'];
        $this->assertSame('message', $fwd['format']);
        $this->assertStringContainsString('Subject: Forwarded mañana', $fwd['text']);
        $this->assertStringContainsString('From: Eve <eve@example.org>', $fwd['text']);
        $this->assertStringContainsString('Inner body', $fwd['text']);
        $this->assertStringNotContainsString('HIDDEN', $fwd['text']);
        $bare = $this->run_op('get_attachment', ['message_id' => $this->id(), 'part_id' => '6'])['result']['data'];
        $this->assertSame("Subject: Envelope subject\nFrom: x@example.org\n\nonly the body", $bare['text']);
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_binary_attachments_only_get_a_link() {
        $res = $this->run_op('get_attachment', ['message_id' => $this->id(), 'part_id' => '4'])['result'];
        $this->assertFalse($res['data']['readable']);
        $this->assertSame('', $res['data']['text']);
        $this->assertStringContainsString('cannot be shown as text', $res['message']);
        $this->assertNotEmpty($res['data']['download_url']);
        $huge = $this->run_op('get_attachment', ['message_id' => $this->id(), 'part_id' => '7'])['result'];
        $this->assertArrayNotHasKey('_resource_links', $huge);
        $long = $this->run_op('get_attachment', ['message_id' => $this->id(), 'part_id' => '8', 'max_chars' => 1000])['result']['data'];
        $this->assertTrue($long['truncated']);
        $this->assertSame(3000, $long['total_chars']);
        $this->assertSame(1000, mb_strlen($long['text']));
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_errors() {
        $missing = $this->run_op('get_attachment', ['message_id' => $this->id(), 'part_id' => '42']);
        $this->assertSame('not_found', $missing['error']->error_code);
        $gone = $this->run_op('get_attachment', ['message_id' => Hm_MCP_Format::message_id('acc1', 'INBOX', '99'), 'part_id' => '2']);
        $this->assertSame('not_found', $gone['error']->error_code);
        $bad = $this->run_op('get_attachment', ['message_id' => $this->id(), 'part_id' => '../2']);
        $this->assertSame('invalid_argument', $bad['error']->error_code);
        $none = array_fill_keys(Hm_MCP_Permissions::keys(), false);
        $denied = $this->run_op('get_attachment', ['message_id' => $this->id(), 'part_id' => '2'], hm_mcp_fake_principal($none));
        $this->assertSame('permission_denied', $denied['error']->error_code);
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_read_attachment_resource() {
        $text = $this->run_op('read_attachment', ['message_id' => $this->id(), 'part_id' => '3'])['result'];
        $this->assertSame(['uri' => 'cypht://attachment/'.$this->id().'/3', 'mime_type' => 'text/plain', 'text' => 'Visible'], $text);
        $blob = $this->run_op('read_attachment', ['message_id' => $this->id(), 'part_id' => '4'])['result'];
        $this->assertSame('application/pdf', $blob['mime_type']);
        $this->assertSame("%PDF-1.4 binary\x00\x01", base64_decode($blob['blob']));
        $big = $this->run_op('read_attachment', ['message_id' => $this->id(), 'part_id' => '7']);
        $this->assertSame('payload_too_large', $big['error']->error_code);
        $this->assertFalse(Hm_MCP_Catalog::exposed((new Hm_MCP_Catalog())->get('read_attachment')));
        $this->assertArrayNotHasKey('read_attachment', (new Hm_MCP_Catalog())->allowed(Hm_MCP_Permissions::defaults()));
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_download_headers() {
        $this->assertSame('application/pdf', Hm_MCP_Files::safe_type('application/pdf'));
        foreach (['text/html', 'image/svg+xml', 'application/xhtml+xml', 'message/rfc822', 'multipart/mixed', 'bad type', 'text/x"y'] as $type) {
            $this->assertSame('application/octet-stream', Hm_MCP_Files::safe_type($type), $type);
        }
        $this->assertSame('attachment; filename="fa_ctura_.pdf"; filename*=UTF-8\'\'fa_ctura_.pdf', Hm_MCP_Files::disposition("fa\"ctura\n.pdf"));
        $this->assertSame('attachment; filename="ma_ana.txt"; filename*=UTF-8\'\'ma%C3%B1ana.txt', Hm_MCP_Files::disposition('mañana.txt'));
        $this->assertSame('attachment; filename="attachment"; filename*=UTF-8\'\'attachment', Hm_MCP_Files::disposition('..'));
        $headers = Hm_MCP_Files::headers(['content_type' => 'text/plain', 'filename' => 'a.txt'], ['attributes' => ['charset' => 'ISO-8859-1']]);
        $this->assertSame('text/plain; charset=iso-8859-1', $headers['Content-Type']);
        $this->assertSame('nosniff', $headers['X-Content-Type-Options']);
        $this->assertStringContainsString('sandbox', $headers['Content-Security-Policy']);
        $this->assertSame('private, no-store, max-age=0', $headers['Cache-Control']);
    }
}
