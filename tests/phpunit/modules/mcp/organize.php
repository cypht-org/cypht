<?php

use PHPUnit\Framework\TestCase;

/**
 * tests for the organize, trash and delete operations with in memory mailboxes
 */
class Hm_Test_MCP_Organize extends TestCase {

    private $servers = [
        'acc1' => ['id' => 'acc1', 'name' => 'Work', 'user' => 'work@example.com', 'server' => 'imap.example.com', 'type' => 'imap'],
        'acc2' => ['id' => 'acc2', 'name' => 'Gmail', 'user' => 'me@gmail.example', 'server' => 'imap.gmail.example', 'type' => 'imap'],
        'acc3' => ['id' => 'acc3', 'name' => 'Old server', 'user' => 'old@example.net', 'server' => 'imap.example.net', 'type' => 'imap'],
    ];

    public function setUp(): void {
        require_once __DIR__.'/fakes.php';
        date_default_timezone_set('UTC');
    }

    private function mailboxes() {
        $acc1 = new Hm_MCP_Fake_Mailbox([
            'INBOX' => [
                '1' => hm_mcp_fake_message('Unread note', 'Bob <bob@example.net>', 'Mon, 01 Sep 2026 10:00:00 +0000'),
                '2' => hm_mcp_fake_message('Read note', 'Carol <carol@example.org>', 'Tue, 02 Sep 2026 10:00:00 +0000', ['flags' => '\\Seen']),
                '3' => hm_mcp_fake_message('Starred', 'Dan <dan@example.org>', 'Wed, 03 Sep 2026 10:00:00 +0000', ['flags' => '\\Flagged']),
            ],
            'Archive' => [],
            'Projects' => [],
            'Junk' => ['20' => hm_mcp_fake_message('Not spam', 'Eve <eve@example.org>', 'Thu, 04 Sep 2026 10:00:00 +0000')],
            'Trash' => [
                '30' => hm_mcp_fake_message('Old', 'Bob <bob@example.net>', 'Fri, 05 Sep 2026 10:00:00 +0000', ['flags' => '\\Seen']),
                '31' => hm_mcp_fake_message('Older', 'Bob <bob@example.net>', 'Fri, 05 Sep 2026 09:00:00 +0000', ['flags' => '\\Seen']),
            ],
        ], ['trash' => 'Trash', 'junk' => 'Junk', 'archive' => 'Archive']);
        $acc1->capabilities = ['MOVE', 'UIDPLUS'];
        $acc2 = new Hm_MCP_Fake_Mailbox([
            'INBOX' => ['10' => hm_mcp_fake_message('Gmail message', 'Fay <fay@example.org>', 'Sat, 06 Sep 2026 10:00:00 +0000')],
            '[Gmail]/All Mail' => ['10' => hm_mcp_fake_message('Gmail message', 'Fay <fay@example.org>', 'Sat, 06 Sep 2026 10:00:00 +0000')],
            '[Gmail]/Trash' => [],
        ], ['all' => '[Gmail]/All Mail', 'trash' => '[Gmail]/Trash']);
        $acc2->capabilities = ['MOVE', 'UIDPLUS', 'X-GM-EXT-1'];
        $acc3 = new Hm_MCP_Fake_Mailbox([
            'INBOX' => [
                '1' => hm_mcp_fake_message('First', 'Gus <gus@example.org>', 'Sun, 07 Sep 2026 10:00:00 +0000'),
                '2' => hm_mcp_fake_message('Second', 'Gus <gus@example.org>', 'Sun, 07 Sep 2026 11:00:00 +0000'),
            ],
            'Saved' => [],
        ]);
        return ['acc1' => $acc1, 'acc2' => $acc2, 'acc3' => $acc3];
    }

    private static function everything() {
        return array_fill_keys(Hm_MCP_Permissions::keys(), true);
    }

    private static function id($account, $folder, $uid) {
        return Hm_MCP_Format::message_id($account, $folder, $uid);
    }

    private function run_op($name, $args, $mailboxes, $options = []) {
        list($services, $holder) = hm_mcp_fake_services($mailboxes, $this->servers, $options['settings'] ?? []);
        if (!empty($options['tags'])) {
            $services->site_config->mods[] = 'tags';
        }
        $principal = $options['principal'] ?? hm_mcp_fake_principal(self::everything());
        $res = $services->executor($principal, 'mcp')->run($name, $args);
        if ($res['ok']) {
            $op = $services->catalog()->get($name);
            $errors = $services->validator()->validateAgainstJsonSchema(json_decode(json_encode($res['result'])),
                Hm_MCP_Catalog::normalize($op['output']));
            $this->assertSame([], $errors, $name.' output does not match its schema');
        }
        $res['context'] = $holder->context;
        $res['logged'] = $services->store()->logged;
        return $res;
    }

    private static function statuses($res) {
        return array_column($res['result']['data']['results'], 'status');
    }

    private static function error_code($res) {
        return $res['ok'] ? null : $res['error']->error_code;
    }

    /* ---------------------------------------------------------------- flags */

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_update_messages_changes_only_what_differs() {
        $boxes = $this->mailboxes();
        $res = $this->run_op('update_messages', ['message_ids' => [self::id('acc1', 'INBOX', '1'), self::id('acc1', 'INBOX', '2'),
            self::id('acc1', 'INBOX', '99')], 'read' => true, 'flagged' => true], $boxes);
        $this->assertTrue($res['ok']);
        $this->assertSame(['ok', 'ok', 'not_found'], self::statuses($res));
        $this->assertSame(2, $res['result']['data']['succeeded']);
        $this->assertSame(1, $res['result']['data']['failed']);
        $this->assertStringContainsString('\\Seen', $boxes['acc1']->folders['INBOX']['1']['flags']);
        $this->assertStringContainsString('\\Flagged', $boxes['acc1']->folders['INBOX']['1']['flags']);
        $this->assertStringContainsString('\\Flagged', $boxes['acc1']->folders['INBOX']['2']['flags']);
        /* only the message that was unread is marked as read, writes use a read-write SELECT */
        $this->assertContains(['INBOX', 'READ', ['1'], false], $boxes['acc1']->actions);
        $this->assertContains(['INBOX', 'FLAG', ['1', '2'], false], $boxes['acc1']->actions);
        $this->assertSame('2 message(s) marked as read and flagged, 1 failed (see results).', $res['result']['message']);
        $this->assertSame(['accounts' => 1, 'messages' => 2, 'failed' => 1], $res['logged'][0]['summary']);

        $res = $this->run_op('update_messages', ['message_ids' => [self::id('acc1', 'INBOX', '3')], 'read' => false], $boxes);
        $this->assertSame(['unchanged'], self::statuses($res));
        $res = $this->run_op('update_messages', ['message_ids' => [self::id('acc1', 'INBOX', '3')]], $boxes);
        $this->assertSame('invalid_argument', self::error_code($res));
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_permissions_are_enforced() {
        $read_only = hm_mcp_fake_principal(['read' => true, 'organize' => false]);
        $organize = hm_mcp_fake_principal(['read' => true, 'organize' => true, 'trash' => false, 'delete_permanent' => false]);
        $id = self::id('acc1', 'INBOX', '1');
        $cases = [
            ['update_messages', ['message_ids' => [$id], 'read' => true], $read_only],
            ['move_messages', ['message_ids' => [$id], 'folder' => 'trash'], $organize],
            ['move_messages', ['message_ids' => [$id], 'folder' => 'Trash'], $organize],
            ['trash_messages', ['message_ids' => [$id]], $organize],
            ['delete_messages_permanently', ['message_ids' => [$id]], $organize],
            ['empty_folder', ['account_id' => 'acc1', 'folder' => 'trash'], $organize],
        ];
        foreach ($cases as list($name, $args, $principal)) {
            $boxes = $this->mailboxes();
            $res = $this->run_op($name, $args, $boxes, ['principal' => $principal]);
            $this->assertSame('permission_denied', self::error_code($res), $name);
            $this->assertArrayHasKey('1', $boxes['acc1']->folders['INBOX'], $name);
            $this->assertCount(2, $boxes['acc1']->folders['Trash'], $name);
        }
        $res = $this->run_op('trash_messages', ['message_ids' => [self::id('acc2', 'INBOX', '10')]], $this->mailboxes(),
            ['principal' => hm_mcp_fake_principal(self::everything(), ['acc1'])]);
        $this->assertSame('account_not_allowed', self::error_code($res));
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_invalid_batches_are_rejected() {
        $boxes = $this->mailboxes();
        $id = self::id('acc1', 'INBOX', '1');
        $too_many = [];
        for ($i = 1; $i <= Hm_MCP_Imap::MAX_BATCH + 1; $i++) {
            $too_many[] = self::id('acc1', 'INBOX', (string) $i);
        }
        $this->assertSame('invalid_argument', self::error_code($this->run_op('archive_messages', ['message_ids' => $too_many], $boxes)));
        $this->assertSame('invalid_argument', self::error_code($this->run_op('archive_messages', ['message_ids' => [$id, $id]], $boxes)));
        $this->assertSame('invalid_argument', self::error_code($this->run_op('archive_messages', ['message_ids' => []], $boxes)));
        $this->assertSame('invalid_argument', self::error_code($this->run_op('archive_messages', ['message_ids' => ['msg_bogus']], $boxes)));
        $this->assertSame('invalid_argument', self::error_code($this->run_op('archive_messages',
            ['message_ids' => [self::id('acc1', 'INBOX', '1:*')]], $boxes)));
        $this->assertSame('invalid_argument', self::error_code($this->run_op('empty_folder', ['account_id' => 'acc1', 'folder' => 'inbox'], $boxes)));
        $this->assertCount(3, $boxes['acc1']->folders['INBOX']);
    }

    /* ---------------------------------------------------------------- moves */

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_move_messages_reports_new_ids() {
        $boxes = $this->mailboxes();
        $res = $this->run_op('move_messages', ['message_ids' => [self::id('acc1', 'INBOX', '1'), self::id('acc1', 'INBOX', '2')],
            'folder' => 'Projects'], $boxes);
        $this->assertTrue($res['ok']);
        $this->assertSame(['ok', 'ok'], self::statuses($res));
        $results = $res['result']['data']['results'];
        $this->assertSame('Projects', $results[0]['folder']);
        $this->assertSame(['acc1', 'Projects', '1'], Hm_MCP_Format::parse_message_id($results[0]['new_message_id']));
        $this->assertSame(['acc1', 'Projects', '2'], Hm_MCP_Format::parse_message_id($results[1]['new_message_id']));
        $this->assertSame(['3'], array_map('strval', array_keys($boxes['acc1']->folders['INBOX'])));
        $this->assertSame('Read note', $boxes['acc1']->folders['Projects']['2']['subject']);
        $this->assertSame('folder', $res['logged'][0]['summary']['destination']);

        $res = $this->run_op('move_messages', ['message_ids' => [self::id('acc1', 'INBOX', '3')], 'folder' => 'archive'], $boxes);
        $this->assertSame(['ok'], self::statuses($res));
        /* looking up the archive role must not reopen the folder read only */
        foreach ($boxes['acc1']->actions as $action) {
            $this->assertFalse($action[3], $action[1].' ran in read only mode');
        }
        $this->assertSame('Archive', $res['result']['data']['results'][0]['folder']);
        $this->assertSame('archive', $res['logged'][0]['summary']['destination']);

        $res = $this->run_op('move_messages', ['message_ids' => [self::id('acc1', 'Archive', '1')], 'folder' => 'Nowhere'], $boxes);
        $this->assertSame(['failed'], self::statuses($res));
        $this->assertStringContainsString('does not exist', $res['result']['data']['results'][0]['reason']);
        $res = $this->run_op('move_messages', ['message_ids' => [self::id('acc1', 'Archive', '1')], 'folder' => 'Archive'], $boxes);
        $this->assertSame(['unchanged'], self::statuses($res));
        $res = $this->run_op('move_messages', ['message_ids' => [self::id('acc3', 'INBOX', '1')], 'folder' => 'archive'], $boxes);
        $this->assertSame('not_found', self::error_code($res));
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_moves_without_the_move_extension_never_remove_other_messages() {
        $boxes = $this->mailboxes();
        /* another mail program marked message 2 as deleted without expunging it */
        $boxes['acc3']->folders['INBOX']['2']['flags'] = '\\Deleted';
        $res = $this->run_op('move_messages', ['message_ids' => [self::id('acc3', 'INBOX', '1')], 'folder' => 'Saved'], $boxes);
        $this->assertSame(['failed'], self::statuses($res));
        $this->assertCount(2, $boxes['acc3']->folders['INBOX']);
        $this->assertSame([], $boxes['acc3']->folders['Saved']);

        $boxes['acc3']->folders['INBOX']['2']['flags'] = '';
        $res = $this->run_op('move_messages', ['message_ids' => [self::id('acc3', 'INBOX', '1')], 'folder' => 'Saved'], $boxes);
        $this->assertSame(['ok'], self::statuses($res));
        /* COPY does not report the new uid without UIDPLUS: it is found by Message-ID */
        $this->assertSame(['acc3', 'Saved', '1'], Hm_MCP_Format::parse_message_id($res['result']['data']['results'][0]['new_message_id']));
        $this->assertSame(['2'], array_map('strval', array_keys($boxes['acc3']->folders['INBOX'])));
        $this->assertCount(1, $boxes['acc3']->folders['Saved']);
        $this->assertContains(['INBOX', 'COPY', ['1'], false], $boxes['acc3']->actions);
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_new_ids_come_from_copyuid_or_the_message_id() {
        $boxes = $this->mailboxes();
        /* COPYUID that Cypht does not parse for several messages: read from the raw answer */
        $boxes['acc1']->raw_copyuid = true;
        $res = $this->run_op('move_messages', ['message_ids' => [self::id('acc1', 'INBOX', '1'), self::id('acc1', 'INBOX', '2'),
            self::id('acc1', 'INBOX', '3')], 'folder' => 'Projects'], $boxes);
        $this->assertSame(['ok', 'ok', 'ok'], self::statuses($res));
        $this->assertSame([['acc1', 'Projects', '1'], ['acc1', 'Projects', '2'], ['acc1', 'Projects', '3']],
            array_map([Hm_MCP_Format::class, 'parse_message_id'], array_column($res['result']['data']['results'], 'new_message_id')));
        /* one MOVE command for the whole folder */
        $this->assertCount(1, array_filter($boxes['acc1']->actions, function ($a) { return $a[1] === 'MOVE'; }));

        /* no COPYUID at all (Gmail when archiving): the message is found by its Message-ID */
        $boxes['acc2']->capabilities = ['MOVE', 'X-GM-EXT-1'];
        /* on Gmail the inbox message and its All Mail copy are the same message */
        unset($boxes['acc2']->folders['[Gmail]/All Mail']['10']);
        $boxes['acc2']->uidnext['[Gmail]/All Mail'] = 11;
        $res = $this->run_op('archive_messages', ['message_ids' => [self::id('acc2', 'INBOX', '10')]], $boxes);
        $this->assertSame(['acc2', '[Gmail]/All Mail', '11'], Hm_MCP_Format::parse_message_id($res['result']['data']['results'][0]['new_message_id']));
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_uid_sets_are_expanded() {
        require_once __DIR__.'/fakes.php';
        $this->assertSame(['3', '4', '5', '9'], Hm_MCP_Imap::expand_set('3:5,9'));
        $this->assertSame(['7'], Hm_MCP_Imap::expand_set('7'));
        $this->assertSame([], Hm_MCP_Imap::expand_set('3:*'));
        $this->assertSame([], Hm_MCP_Imap::expand_set('1:999999'));
        $this->assertSame([], Hm_MCP_Imap::expand_set(''));
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_archive_uses_the_archive_folder_or_gmail_all_mail() {
        $boxes = $this->mailboxes();
        $res = $this->run_op('archive_messages', ['message_ids' => [self::id('acc1', 'INBOX', '1'), self::id('acc2', 'INBOX', '10'),
            self::id('acc3', 'INBOX', '1')]], $boxes);
        $this->assertTrue($res['ok']);
        $this->assertSame(['ok', 'ok', 'failed'], self::statuses($res));
        $this->assertSame('Archive', $res['result']['data']['results'][0]['folder']);
        $this->assertSame('[Gmail]/All Mail', $res['result']['data']['results'][1]['folder']);
        $this->assertStringContainsString('no archive folder', $res['result']['data']['results'][2]['reason']);
        $this->assertArrayNotHasKey('10', $boxes['acc2']->folders['INBOX']);
        $this->assertArrayHasKey('1', $boxes['acc3']->folders['INBOX']);
        $this->assertSame(3, $res['logged'][0]['summary']['accounts']);
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_junk_and_not_junk() {
        $boxes = $this->mailboxes();
        $res = $this->run_op('mark_junk', ['message_ids' => [self::id('acc1', 'INBOX', '1')]], $boxes);
        $this->assertSame(['ok'], self::statuses($res));
        $this->assertSame('Junk', $res['result']['data']['results'][0]['folder']);
        $res = $this->run_op('mark_junk', ['message_ids' => [self::id('acc1', 'Junk', '20'), self::id('acc1', 'INBOX', '2')], 'junk' => false], $boxes);
        $this->assertSame(['ok', 'unchanged'], self::statuses($res));
        $this->assertSame('INBOX', $res['result']['data']['results'][0]['folder']);
        $this->assertSame('Not spam', $boxes['acc1']->folders['INBOX']['4']['subject']);
        $this->assertSame('1 message(s) moved back to the inbox, 1 unchanged.', $res['result']['message']);
    }

    /* ---------------------------------------------------------------- trash */

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_trash_and_restore() {
        $boxes = $this->mailboxes();
        $res = $this->run_op('trash_messages', ['message_ids' => [self::id('acc1', 'INBOX', '1'), self::id('acc1', 'Trash', '30'),
            self::id('acc3', 'INBOX', '1')]], $boxes);
        $this->assertSame(['ok', 'unchanged', 'failed'], self::statuses($res));
        $this->assertStringContainsString('delete_messages_permanently', $res['result']['data']['results'][1]['reason']);
        $this->assertStringContainsString('nothing was deleted', $res['result']['data']['results'][2]['reason']);
        $this->assertArrayHasKey('1', $boxes['acc3']->folders['INBOX']);
        $trashed = $res['result']['data']['results'][0]['new_message_id'];
        $this->assertSame(['acc1', 'Trash', '32'], Hm_MCP_Format::parse_message_id($trashed));

        $res = $this->run_op('restore_messages', ['message_ids' => [$trashed, self::id('acc1', 'INBOX', '2')]], $boxes);
        $this->assertSame(['ok', 'unchanged'], self::statuses($res));
        $this->assertSame('INBOX', $res['result']['data']['results'][0]['folder']);
        $res = $this->run_op('restore_messages', ['message_ids' => [self::id('acc1', 'Trash', '30')], 'folder' => 'Projects'], $boxes);
        $this->assertSame('Projects', $res['result']['data']['results'][0]['folder']);
        $res = $this->run_op('restore_messages', ['message_ids' => [self::id('acc1', 'Trash', '31')], 'folder' => 'trash'], $boxes);
        $this->assertSame('invalid_argument', self::error_code($res));
        $this->assertArrayHasKey('31', $boxes['acc1']->folders['Trash']);
    }

    /* ------------------------------------------------------ permanent delete */

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_permanent_delete_removes_only_the_requested_messages() {
        $boxes = $this->mailboxes();
        $boxes['acc1']->folders['Trash']['31']['flags'] = '\\Seen \\Deleted';
        $res = $this->run_op('delete_messages_permanently', ['message_ids' => [self::id('acc1', 'Trash', '30')]], $boxes);
        $this->assertSame(['ok'], self::statuses($res));
        $this->assertSame(['31'], array_map('strval', array_keys($boxes['acc1']->folders['Trash'])));
        $this->assertSame(['UID EXPUNGE 30'], $boxes['acc1']->get_connection()->commands);
        $this->assertSame('1 message(s) deleted permanently.', $res['result']['message']);
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_permanent_delete_without_uidplus_refuses_to_remove_other_marked_messages() {
        $boxes = $this->mailboxes();
        $boxes['acc3']->folders['INBOX']['2']['flags'] = '\\Deleted';
        $res = $this->run_op('delete_messages_permanently', ['message_ids' => [self::id('acc3', 'INBOX', '1')]], $boxes);
        $this->assertSame(['failed'], self::statuses($res));
        $this->assertStringContainsString('Nothing was deleted', $res['result']['data']['results'][0]['reason']);
        $this->assertCount(2, $boxes['acc3']->folders['INBOX']);
        $this->assertSame('', $boxes['acc3']->folders['INBOX']['1']['flags']);

        $boxes['acc3']->folders['INBOX']['2']['flags'] = '';
        $res = $this->run_op('delete_messages_permanently', ['message_ids' => [self::id('acc3', 'INBOX', '1')]], $boxes);
        $this->assertSame(['ok'], self::statuses($res));
        $this->assertSame(['2'], array_map('strval', array_keys($boxes['acc3']->folders['INBOX'])));
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_permanent_delete_on_gmail_goes_through_the_trash() {
        $boxes = $this->mailboxes();
        $res = $this->run_op('delete_messages_permanently', ['message_ids' => [self::id('acc2', 'INBOX', '10')]], $boxes);
        $this->assertSame(['ok'], self::statuses($res));
        $this->assertSame([], $boxes['acc2']->folders['INBOX']);
        $this->assertSame([], $boxes['acc2']->folders['[Gmail]/Trash']);
        $this->assertContains(['INBOX', 'MOVE', ['10'], false], $boxes['acc2']->actions);
        $this->assertSame(['UID EXPUNGE 1'], $boxes['acc2']->get_connection()->commands);
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_empty_trash_and_junk() {
        $boxes = $this->mailboxes();
        $res = $this->run_op('empty_folder', ['account_id' => 'acc1', 'folder' => 'trash'], $boxes);
        $this->assertTrue($res['ok']);
        $this->assertSame(['account_id' => 'acc1', 'folder' => 'Trash', 'deleted' => 2, 'remaining' => 0], $res['result']['data']);
        $this->assertSame([], $boxes['acc1']->folders['Trash']);
        $this->assertCount(3, $boxes['acc1']->folders['INBOX']);
        $this->assertSame(['account_id' => 'acc1', 'folder' => 'trash', 'messages' => 2], $res['logged'][0]['summary']);
        $res = $this->run_op('empty_folder', ['account_id' => 'acc1', 'folder' => 'trash'], $boxes);
        $this->assertSame('The trash is already empty.', $res['result']['message']);
        $res = $this->run_op('empty_folder', ['account_id' => 'work@example.com', 'folder' => 'junk'], $boxes);
        $this->assertSame(1, $res['result']['data']['deleted']);
        $res = $this->run_op('empty_folder', ['account_id' => 'acc3', 'folder' => 'junk'], $boxes);
        $this->assertSame('not_found', self::error_code($res));
    }

    /* --------------------------------------------------------------- snooze */

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_snooze_needs_the_cypht_setting() {
        $boxes = $this->mailboxes();
        $res = $this->run_op('snooze_messages', ['message_ids' => [self::id('acc1', 'INBOX', '1')], 'until' => 'tomorrow'], $boxes,
            ['settings' => ['enable_snooze_setting' => false]]);
        $this->assertSame('not_supported', self::error_code($res));
        $this->assertArrayHasKey('1', $boxes['acc1']->folders['INBOX']);
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_snooze_and_wake_up() {
        $boxes = $this->mailboxes();
        $options = ['settings' => ['enable_snooze_setting' => true]];
        foreach (['yesterday', '2001-01-01', 'soon', '2999-01-01'] as $until) {
            $res = $this->run_op('snooze_messages', ['message_ids' => [self::id('acc1', 'INBOX', '1')], 'until' => $until], $boxes, $options);
            $this->assertSame('invalid_argument', self::error_code($res), $until);
        }
        $res = $this->run_op('snooze_messages', ['message_ids' => [self::id('acc1', 'INBOX', '1'), self::id('acc1', 'INBOX', '2')],
            'until' => 'tomorrow'], $boxes, $options);
        $this->assertTrue($res['ok'], $res['ok'] ? '' : $res['error']->getMessage());
        $this->assertSame(['ok', 'ok'], self::statuses($res));
        $this->assertSame(date(DATE_ATOM, strtotime('tomorrow 08:00')), $res['result']['data']['until']);
        $this->assertSame(['3'], array_map('strval', array_keys($boxes['acc1']->folders['INBOX'])));
        $snoozed = $boxes['acc1']->folders['Snoozed'];
        $this->assertCount(2, $snoozed);
        $first = $snoozed['1'];
        $this->assertMatchesRegularExpression("/^X-Snoozed: at [^;]+; until [^;]+;\r\n \tfrom INBOX\r\nSubject: Unread note/", $first['raw']);
        /* the read state is kept and the messages were read without marking them as read */
        $this->assertStringNotContainsString('\\Seen', $first['flags']);
        $this->assertStringContainsString('\\Seen', $snoozed['2']['flags']);
        $this->assertSame([['1', 'full', true], ['2', 'full', true]], $boxes['acc1']->content_reads);

        $new_id = $res['result']['data']['results'][1]['new_message_id'];
        $res = $this->run_op('snooze_messages', ['message_ids' => [$new_id, self::id('acc1', 'INBOX', '3')], 'until' => 'now'], $boxes, $options);
        $this->assertSame(['ok', 'unchanged'], self::statuses($res));
        $this->assertSame('INBOX', $res['result']['data']['results'][0]['folder']);
        $woken = $boxes['acc1']->folders['INBOX']['4'];
        $this->assertSame('Read note', $woken['subject']);
        $this->assertStringNotContainsString('X-Snoozed', $woken['raw']);
        /* like Gmail, a message comes back unread */
        $this->assertStringNotContainsString('\\Seen', $woken['flags']);
        $this->assertCount(1, $boxes['acc1']->folders['Snoozed']);
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_snooze_header_round_trip() {
        $raw = "Subject: Hi\r\nX-Snoozed: at Mon, 01 Sep 2026 10:00:00 +0000; until Tue, 02 Sep 2026 08:00 +0000;\r\n \tfrom Projects/2026\r\n".
            "From: a@example.com\r\n\r\nBody line\r\nX-Snoozed: this is body text\r\n";
        list($clean, $header) = Hm_MCP_Imap::take_snooze_header($raw);
        $this->assertSame("Subject: Hi\r\nFrom: a@example.com\r\n\r\nBody line\r\nX-Snoozed: this is body text\r\n", $clean);
        $this->assertSame('Projects/2026', $header['from']);
        $this->assertSame('Tue, 02 Sep 2026 08:00 +0000', $header['until']);
        $again = Hm_MCP_Imap::add_snooze_header($clean, 1800000000, "Inbox;\r\nBcc: x", 1799990000);
        $this->assertStringStartsWith("X-Snoozed: at ", $again);
        $this->assertStringContainsString("\r\n \tfrom InboxBcc: x\r\nSubject: Hi", $again);
        list(, $parsed) = Hm_MCP_Imap::take_snooze_header($again);
        $this->assertSame(1800000000, strtotime($parsed['until']));
        list(, $none) = Hm_MCP_Imap::take_snooze_header("Subject: x\r\n\r\nbody");
        $this->assertNull($none);
    }

    /* ----------------------------------------------------------------- tags */

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_tags_follow_moved_and_deleted_messages() {
        $boxes = $this->mailboxes();
        $tags = ['t1' => ['id' => 't1', 'name' => 'Clients', 'server' => ['acc1' => ['INBOX' => ['1', '2'], 'Trash' => ['30']]]]];
        $options = ['tags' => true, 'settings' => ['tags' => $tags]];
        $res = $this->run_op('move_messages', ['message_ids' => [self::id('acc1', 'INBOX', '1')], 'folder' => 'Projects'], $boxes, $options);
        $this->assertSame(['ok'], self::statuses($res));
        $saved = $res['context']->user_config->get('tags');
        $this->assertSame(['2'], array_values($saved['t1']['server']['acc1']['INBOX']));
        $this->assertSame(['1'], $saved['t1']['server']['acc1']['Projects']);
        $this->assertSame([['tags']], $res['context']->saved_settings);
        $this->assertTrue($res['logged'][0]['summary']['tags_updated']);

        $res = $this->run_op('delete_messages_permanently', ['message_ids' => [self::id('acc1', 'Trash', '30')]], $boxes, $options);
        $this->assertSame([], array_values($res['context']->user_config->get('tags')['t1']['server']['acc1']['Trash']));
        /* nothing is saved when no tag points at the messages */
        $res = $this->run_op('archive_messages', ['message_ids' => [self::id('acc1', 'INBOX', '3')]], $boxes, $options);
        $this->assertSame([], $res['context']->saved_settings);
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_tag_reference_updates() {
        $tags = [
            'a' => ['id' => 'a', 'server' => ['acc1' => ['INBOX' => ['1', '2', 3], 'Done' => ['9']]]],
            'b' => ['id' => 'b', 'server' => ['acc2' => ['INBOX' => ['1']]]],
        ];
        $this->assertTrue(Hm_MCP_Tag_Refs::referenced($tags, 'acc1', 'INBOX', [3]));
        $this->assertFalse(Hm_MCP_Tag_Refs::referenced($tags, 'acc1', 'Done', ['1']));
        list($moved, $changed) = Hm_MCP_Tag_Refs::move($tags, 'acc1', 'INBOX', 'Done', ['1' => '10', '3' => null]);
        $this->assertTrue($changed);
        $this->assertSame(['2'], $moved['a']['server']['acc1']['INBOX']);
        $this->assertSame(['9', '10'], $moved['a']['server']['acc1']['Done']);
        $this->assertSame(['1'], $moved['b']['server']['acc2']['INBOX']);
        list($same, $changed) = Hm_MCP_Tag_Refs::move($tags, 'acc1', 'Other', 'Done', ['1' => '10']);
        $this->assertFalse($changed);
        $this->assertSame($tags, $same);
        list($removed) = Hm_MCP_Tag_Refs::remove($tags, 'acc2', 'INBOX', ['1']);
        $this->assertSame([], $removed['b']['server']['acc2']['INBOX']);
    }

    /* --------------------------------------------------------------- catalog */

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_catalog_marks_destructive_tools() {
        $catalog = new Hm_MCP_Catalog();
        foreach (['trash_messages' => 'trash', 'delete_messages_permanently' => 'delete_permanent', 'empty_folder' => 'delete_permanent'] as $name => $permission) {
            $op = $catalog->get($name);
            $this->assertSame($permission, $op['permission']);
            $this->assertTrue(Hm_MCP_Catalog::ANNOTATIONS[$op['kind']]['destructiveHint'], $name);
        }
        foreach (['update_messages', 'move_messages', 'archive_messages', 'mark_junk', 'snooze_messages'] as $name) {
            $op = $catalog->get($name);
            $this->assertSame('organize', $op['permission'], $name);
            $this->assertFalse(Hm_MCP_Catalog::ANNOTATIONS[$op['kind']]['destructiveHint'], $name);
            $this->assertFalse(Hm_MCP_Catalog::ANNOTATIONS[$op['kind']]['readOnlyHint'], $name);
        }
        $defaults = array_keys($catalog->allowed(Hm_MCP_Permissions::defaults()));
        $this->assertNotContains('trash_messages', $defaults);
        $this->assertNotContains('delete_messages_permanently', $defaults);
        $this->assertContains('archive_messages', $defaults);
    }
}
