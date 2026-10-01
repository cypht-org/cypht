<?php

use PHPUnit\Framework\TestCase;

/**
 * tests for folder, tag and contact management with in memory mailboxes
 */
class Hm_Test_MCP_Manage extends TestCase {

    private $servers = [
        'acc1' => ['id' => 'acc1', 'name' => 'Work', 'user' => 'work@example.com', 'server' => 'imap.example.com', 'type' => 'imap'],
        'acc2' => ['id' => 'acc2', 'name' => 'Prefixed', 'user' => 'dots@example.net', 'server' => 'imap.example.net', 'type' => 'imap'],
    ];

    public function setUp(): void {
        require_once __DIR__.'/fakes.php';
        date_default_timezone_set('UTC');
    }

    private function mailboxes() {
        $acc1 = new Hm_MCP_Fake_Mailbox([
            'INBOX' => [
                '1' => hm_mcp_fake_message('Hello', 'Bob <bob@example.net>', 'Mon, 01 Sep 2026 10:00:00 +0000'),
                '2' => hm_mcp_fake_message('Report', 'Carol <carol@example.org>', 'Tue, 02 Sep 2026 10:00:00 +0000'),
            ],
            'Sent' => [], 'Trash' => [], 'Snoozed' => [],
            'Projects' => ['5' => hm_mcp_fake_message('Plan', 'Dan <dan@example.org>', 'Wed, 03 Sep 2026 10:00:00 +0000')],
            'Projects/2026' => [],
            'Old' => [],
            'Old/Archive' => [],
            'Empty' => [],
        ], ['sent' => 'Sent', 'trash' => 'Trash', 'archive' => 'Old/Archive']);
        $acc1->capabilities = ['MOVE', 'UIDPLUS'];
        /* a server that keeps personal folders below INBOX. */
        $acc2 = new Hm_MCP_Fake_Mailbox(['INBOX' => [], 'INBOX.Notes' => []]);
        $acc2->delim = '.';
        $acc2->ns_prefix = 'INBOX.';
        return ['acc1' => $acc1, 'acc2' => $acc2];
    }

    private static function everything() {
        return array_fill_keys(Hm_MCP_Permissions::keys(), true);
    }

    private static function without($key) {
        return array_merge(self::everything(), [$key => false]);
    }

    private static function id($account, $folder, $uid) {
        return Hm_MCP_Format::message_id($account, $folder, $uid);
    }

    private function run_op($name, $args, $mailboxes, $options = []) {
        list($services, $holder) = hm_mcp_fake_services($mailboxes, $this->servers, $options['settings'] ?? []);
        foreach ($options['mods'] ?? [] as $mod) {
            $services->site_config->mods[] = $mod;
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

    private static function error_code($res) {
        return $res['ok'] ? null : $res['error']->error_code;
    }

    private static function names($mailbox) {
        $names = array_map('strval', array_keys($mailbox->folders));
        sort($names, SORT_STRING);
        return $names;
    }

    /* ------------------------------------------------------------ folders */

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_create_folders() {
        $boxes = $this->mailboxes();
        $res = $this->run_op('create_folder', ['account_id' => 'acc1', 'name' => ' Clientes '], $boxes);
        $this->assertTrue($res['ok'], $res['ok'] ? '' : $res['error']->getMessage());
        $this->assertSame(['account_id' => 'acc1', 'folder' => 'Clientes', 'name' => 'Clientes', 'parent' => ''], $res['result']['data']);
        $this->assertSame(['account_id' => 'acc1', 'folder' => 'Clientes'], $res['logged'][0]['summary']);
        $res = $this->run_op('create_folder', ['account_id' => 'acc1', 'name' => 'Q3', 'parent' => 'Projects'], $boxes);
        $this->assertSame('Projects/Q3', $res['result']['data']['folder']);
        $this->assertArrayHasKey('Projects/Q3', $boxes['acc1']->folders);
        $this->assertSame(['CREATE Clientes', 'CREATE Projects/Q3'], $boxes['acc1']->folder_commands);

        /* the namespace prefix and the delimiter of the server are used */
        $res = $this->run_op('create_folder', ['account_id' => 'acc2', 'name' => 'Ideas'], $boxes);
        $this->assertSame('INBOX.Ideas', $res['result']['data']['folder']);
        $res = $this->run_op('create_folder', ['account_id' => 'acc2', 'name' => 'Sub', 'parent' => 'INBOX.Notes'], $boxes);
        $this->assertSame('INBOX.Notes.Sub', $res['result']['data']['folder']);
        $this->assertSame('invalid_argument', self::error_code($this->run_op('create_folder', ['account_id' => 'acc2', 'name' => 'a.b'], $boxes)));

        $cases = [
            [['name' => 'a/b'], 'invalid_argument'],
            [['name' => 'a*'], 'invalid_argument'],
            [['name' => 'say "hi"'], 'invalid_argument'],
            [['name' => "tab\tname"], 'invalid_argument'],
            [['name' => '   '], 'invalid_argument'],
            [['name' => ''], 'invalid_argument'],
            [['name' => str_repeat('x', 201)], 'invalid_argument'],
            [['name' => 'Projects'], 'conflict'],
            [['name' => 'inbox'], 'conflict'],
            [['name' => 'x', 'parent' => 'Nowhere'], 'not_found'],
        ];
        foreach ($cases as list($args, $code)) {
            $res = $this->run_op('create_folder', array_merge(['account_id' => 'acc1'], $args), $boxes);
            $this->assertSame($code, self::error_code($res), json_encode($args));
        }
        $res = $this->run_op('create_folder', ['account_id' => 'acc1', 'name' => 'x'], $boxes, ['principal' => hm_mcp_fake_principal()]);
        $this->assertSame('permission_denied', self::error_code($res));
        $this->assertCount(2, $boxes['acc1']->folder_commands);
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_rename_and_move_folders() {
        $boxes = $this->mailboxes();
        $res = $this->run_op('rename_folder', ['account_id' => 'acc1', 'folder' => 'Projects', 'name' => 'Trabajo'], $boxes);
        $this->assertTrue($res['ok'], $res['ok'] ? '' : $res['error']->getMessage());
        $this->assertSame(['account_id' => 'acc1', 'folder' => 'Trabajo', 'previous_folder' => 'Projects', 'name' => 'Trabajo', 'parent' => ''],
            $res['result']['data']);
        $this->assertSame('Plan', $boxes['acc1']->folders['Trabajo']['5']['subject']);
        $this->assertArrayHasKey('Trabajo/2026', $boxes['acc1']->folders);
        $this->assertSame(['account_id' => 'acc1', 'folder' => 'Projects', 'destination' => 'Trabajo'], $res['logged'][0]['summary']);

        $res = $this->run_op('rename_folder', ['account_id' => 'acc1', 'folder' => 'Trabajo/2026', 'name' => '2026', 'parent' => ''], $boxes);
        $this->assertSame('2026', $res['result']['data']['folder']);
        $res = $this->run_op('rename_folder', ['account_id' => 'acc1', 'folder' => 'Empty', 'name' => 'Empty', 'parent' => 'Trabajo'], $boxes);
        $this->assertSame('Trabajo/Empty', $res['result']['data']['folder']);
        $res = $this->run_op('rename_folder', ['account_id' => 'acc1', 'folder' => '2026', 'name' => '2026'], $boxes);
        $this->assertSame('The folder already has this name.', $res['result']['message']);
        $res = $this->run_op('rename_folder', ['account_id' => 'acc2', 'folder' => 'INBOX.Notes', 'name' => 'Ideas'], $boxes);
        $this->assertSame('INBOX.Ideas', $res['result']['data']['folder']);

        $cases = [
            [['folder' => 'Trabajo', 'name' => 'Inside', 'parent' => 'Trabajo/Empty'], 'invalid_argument'],
            [['folder' => 'Trabajo', 'name' => 'Trabajo', 'parent' => 'Trabajo'], 'invalid_argument'],
            [['folder' => 'Sent', 'name' => 'Enviados'], 'invalid_argument'],
            [['folder' => 'sent', 'name' => 'Enviados'], 'invalid_argument'],
            [['folder' => 'INBOX', 'name' => 'Entrada'], 'invalid_argument'],
            [['folder' => 'Snoozed', 'name' => 'Later'], 'invalid_argument'],
            [['folder' => 'Old', 'name' => 'Older'], 'invalid_argument'],
            [['folder' => 'Trabajo/Empty', 'name' => '2026', 'parent' => ''], 'conflict'],
            [['folder' => 'Nowhere', 'name' => 'x'], 'not_found'],
            [['folder' => 'Trabajo', 'name' => 'x', 'parent' => 'Nowhere'], 'not_found'],
        ];
        foreach ($cases as list($args, $code)) {
            $res = $this->run_op('rename_folder', array_merge(['account_id' => 'acc1'], $args), $boxes);
            $this->assertSame($code, self::error_code($res), json_encode($args));
        }
        $this->assertSame(['2026', 'INBOX', 'Old', 'Old/Archive', 'Sent', 'Snoozed', 'Trabajo', 'Trabajo/Empty', 'Trash'], self::names($boxes['acc1']));
        $this->assertStringContainsString('archive folder', $this->run_op('rename_folder', ['account_id' => 'acc1', 'folder' => 'Old', 'name' => 'x'],
            $boxes)['error']->getMessage());
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_delete_folders() {
        $boxes = $this->mailboxes();
        $res = $this->run_op('delete_folder', ['account_id' => 'acc1', 'folder' => 'Empty'], $boxes, ['principal' => hm_mcp_fake_principal(self::without('delete_permanent'))]);
        $this->assertTrue($res['ok'], $res['ok'] ? '' : $res['error']->getMessage());
        $this->assertSame(['account_id' => 'acc1', 'folder' => 'Empty', 'deleted' => true, 'messages' => 0], $res['result']['data']);
        $this->assertArrayNotHasKey('Empty', $boxes['acc1']->folders);

        $res = $this->run_op('delete_folder', ['account_id' => 'acc1', 'folder' => 'Projects'], $boxes);
        $this->assertSame('invalid_argument', self::error_code($res));
        $this->assertStringContainsString('folders inside it', $res['error']->getMessage());
        $this->assertTrue($this->run_op('delete_folder', ['account_id' => 'acc1', 'folder' => 'Projects/2026'], $boxes)['ok']);

        /* a folder with messages needs the permanent delete permission too */
        $res = $this->run_op('delete_folder', ['account_id' => 'acc1', 'folder' => 'Projects'], $boxes, ['principal' => hm_mcp_fake_principal(self::without('delete_permanent'))]);
        $this->assertSame('permission_denied', self::error_code($res));
        $this->assertStringContainsString('1 message(s)', $res['error']->getMessage());
        $this->assertSame('denied', $res['logged'][0]['outcome']);
        $this->assertArrayHasKey('Projects', $boxes['acc1']->folders);
        $res = $this->run_op('delete_folder', ['account_id' => 'acc1', 'folder' => 'Projects'], $boxes);
        $this->assertSame(1, $res['result']['data']['messages']);
        $this->assertSame('Folder "Projects" deleted with its 1 message(s).', $res['result']['message']);
        $this->assertArrayNotHasKey('Projects', $boxes['acc1']->folders);

        foreach (['Sent', 'Trash', 'INBOX', 'Snoozed', 'Old', 'Old/Archive', 'archive'] as $folder) {
            $res = $this->run_op('delete_folder', ['account_id' => 'acc1', 'folder' => $folder], $boxes);
            $this->assertSame('invalid_argument', self::error_code($res), $folder);
        }
        $this->assertSame('not_found', self::error_code($this->run_op('delete_folder', ['account_id' => 'acc1', 'folder' => 'Projects'], $boxes)));
        $res = $this->run_op('delete_folder', ['account_id' => 'acc1', 'folder' => 'Old'], $boxes, ['principal' => hm_mcp_fake_principal()]);
        $this->assertSame('permission_denied', self::error_code($res));
        $this->assertSame(['DELETE Empty', 'DELETE Projects/2026', 'DELETE Projects'], $boxes['acc1']->folder_commands);
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_tags_follow_renamed_and_deleted_folders() {
        $boxes = $this->mailboxes();
        $tags = ['t1' => ['id' => 't1', 'name' => 'Clients', 'server' => ['acc1' => ['Projects' => ['5'], 'Projects/2026' => [], 'INBOX' => ['1']]]]];
        $options = ['mods' => ['tags'], 'settings' => ['tags' => $tags]];
        $res = $this->run_op('rename_folder', ['account_id' => 'acc1', 'folder' => 'Projects', 'name' => 'Trabajo'], $boxes, $options);
        $this->assertSame([['tags']], $res['context']->saved_settings);
        $this->assertSame(['Trabajo' => ['5'], 'Trabajo/2026' => [], 'INBOX' => ['1']], $res['context']->user_config->get('tags')['t1']['server']['acc1']);
        $this->assertTrue($res['logged'][0]['summary']['tags_updated']);

        $options['settings']['tags'] = $res['context']->user_config->get('tags');
        $res = $this->run_op('delete_folder', ['account_id' => 'acc1', 'folder' => 'Trabajo/2026'], $boxes, $options);
        $this->assertSame(['Trabajo' => ['5'], 'INBOX' => ['1']], $res['context']->user_config->get('tags')['t1']['server']['acc1']);
        /* nothing is saved when no tag refers to the folder */
        $res = $this->run_op('rename_folder', ['account_id' => 'acc1', 'folder' => 'Empty', 'name' => 'Vacía'], $boxes, $options);
        $this->assertTrue($res['ok']);
        $this->assertSame([], $res['context']->saved_settings);
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_folders_below_the_top_level_are_found() {
        $boxes = $this->mailboxes();
        $boxes['acc1']->top_level_only = true;
        $res = $this->run_op('list_folders', ['account_id' => 'acc1'], $boxes);
        $folders = array_column($res['result']['data']['folders'], null, 'folder');
        $this->assertSame(['Projects', '2026', false], [$folders['Projects/2026']['parent'], $folders['Projects/2026']['name'], $folders['Projects/2026']['has_children']]);
        $this->assertTrue($folders['Projects']['has_children']);
        $this->assertSame([false, '', '*', true], $boxes['acc1']->list_calls[0]);
        $res = $this->run_op('delete_folder', ['account_id' => 'acc1', 'folder' => 'Projects/2026'], $boxes);
        $this->assertTrue($res['ok'], $res['ok'] ? '' : $res['error']->getMessage());
        $this->assertSame('invalid_argument', self::error_code($this->run_op('delete_folder', ['account_id' => 'acc1', 'folder' => 'Old'], $boxes)));
    }

    /* --------------------------------------------------------------- tags */

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_tag_messages() {
        $boxes = $this->mailboxes();
        $tags = [
            't1' => ['id' => 't1', 'name' => 'Clients', 'color' => '#188038', 'server' => ['acc1' => ['INBOX' => ['2']]]],
            't2' => ['id' => 't2', 'name' => 'Dup', 'server' => []],
            't3' => ['id' => 't3', 'name' => 'dup', 'server' => []],
        ];
        $options = ['mods' => ['tags'], 'settings' => ['tags' => $tags]];
        $ids = [self::id('acc1', 'INBOX', '1'), self::id('acc1', 'INBOX', '2'), self::id('acc1', 'INBOX', '99')];
        $res = $this->run_op('tag_messages', ['message_ids' => $ids, 'add' => ['clients', 'Nuevo tag']], $boxes, $options);
        $this->assertTrue($res['ok'], $res['ok'] ? '' : $res['error']->getMessage());
        $this->assertSame(['ok', 'ok', 'not_found'], array_column($res['result']['data']['results'], 'status'));
        $involved = $res['result']['data']['tags'];
        $this->assertSame(['t1', false], [$involved[0]['id'], $involved[0]['created']]);
        $this->assertSame(['Nuevo tag', true], [$involved[1]['name'], $involved[1]['created']]);
        $this->assertMatchesRegularExpression('/^[0-9a-f]{13}$/', $involved[1]['id']);
        $saved = $res['context']->user_config->get('tags');
        $this->assertSame(['2', '1'], $saved['t1']['server']['acc1']['INBOX']);
        $this->assertSame(['1', '2'], $saved[$involved[1]['id']]['server']['acc1']['INBOX']);
        $this->assertSame('#188038', $saved['t1']['color']);
        /* messages are only read, never changed */
        $this->assertTrue($boxes['acc1']->read_only);
        $this->assertSame([], $boxes['acc1']->actions);
        $this->assertSame(['accounts' => 1, 'messages' => 2, 'failed' => 1, 'tags' => 2, 'tags_created' => 1], $res['logged'][0]['summary']);

        $options['settings']['tags'] = $saved;
        $res = $this->run_op('tag_messages', ['message_ids' => array_slice($ids, 0, 2), 'add' => ['Clients']], $boxes, $options);
        $this->assertSame(['unchanged', 'unchanged'], array_column($res['result']['data']['results'], 'status'));
        $this->assertSame([], $res['context']->saved_settings);
        $res = $this->run_op('tag_messages', ['message_ids' => [$ids[1]], 'remove' => ['t1']], $boxes, $options);
        $this->assertSame(['ok'], array_column($res['result']['data']['results'], 'status'));
        $this->assertSame(['1'], $res['context']->user_config->get('tags')['t1']['server']['acc1']['INBOX']);

        $cases = [
            [['add' => ['DUP']], 'invalid_argument'],
            [['remove' => ['Missing']], 'not_found'],
            [['add' => ['Clients'], 'remove' => ['t1']], 'invalid_argument'],
            [[], 'invalid_argument'],
            [['add' => ['  ']], 'invalid_argument'],
        ];
        foreach ($cases as list($args, $code)) {
            $res = $this->run_op('tag_messages', array_merge(['message_ids' => [$ids[0]]], $args), $boxes, $options);
            $this->assertSame($code, self::error_code($res), json_encode($args));
        }
        $this->assertStringContainsString('t2, t3', $this->run_op('tag_messages', ['message_ids' => [$ids[0]], 'add' => ['dup']], $boxes, $options)['error']->getMessage());
        $res = $this->run_op('tag_messages', ['message_ids' => [$ids[0]], 'add' => ['x']], $boxes, ['settings' => ['tags' => $tags]]);
        $this->assertSame('not_supported', self::error_code($res));
        $res = $this->run_op('tag_messages', ['message_ids' => [$ids[0]], 'add' => ['x']], $boxes, $options + ['principal' => hm_mcp_fake_principal()]);
        $this->assertSame('permission_denied', self::error_code($res));
    }

    /* ----------------------------------------------------------- contacts */

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_save_contacts() {
        $boxes = $this->mailboxes();
        $contacts = ['c1' => ['id' => 'c1', 'source' => 'local', 'type' => 'local', 'email_address' => 'Bob@Example.net',
            'display_name' => 'Bob', 'group' => 'Work']];
        $options = ['mods' => ['contacts', 'local_contacts'], 'settings' => ['contacts' => $contacts]];
        $res = $this->run_op('save_contact', ['email' => 'carol@example.org', 'name' => 'Carol Díaz'], $boxes, $options);
        $this->assertTrue($res['ok'], $res['ok'] ? '' : $res['error']->getMessage());
        $data = $res['result']['data'];
        $this->assertTrue($data['created']);
        $this->assertSame(['Carol Díaz', 'carol@example.org', '', 'Personal Addresses'],
            [$data['contact']['name'], $data['contact']['email'], $data['contact']['phone'], $data['contact']['group']]);
        $saved = $res['context']->user_config->get('contacts');
        $this->assertSame(['id' => $data['contact']['id'], 'source' => 'local', 'type' => 'local', 'email_address' => 'carol@example.org',
            'display_name' => 'Carol Díaz', 'group' => 'Personal Addresses'], $saved[$data['contact']['id']]);
        $this->assertSame(['contacts' => 1, 'new_contact' => true], $res['logged'][0]['summary']);
        $this->assertStringNotContainsString('carol', json_encode($res['logged']));

        /* the same address updates the contact, and only the given fields change */
        $res = $this->run_op('save_contact', ['email' => 'bob@example.net', 'phone' => '+56 9 1234-5678'], $boxes, $options);
        $this->assertFalse($res['result']['data']['created']);
        $this->assertSame(['c1', 'Bob', 'bob@example.net', '+56 9 1234-5678', 'Work'], array_values($res['result']['data']['contact']));
        $this->assertCount(1, $res['context']->user_config->get('contacts'));
        $res = $this->run_op('save_contact', ['contact_id' => 'c1', 'name' => 'Robert', 'group' => ''], $boxes, $options);
        $this->assertSame(['Robert', 'Bob@Example.net', 'Personal Addresses'],
            [$res['result']['data']['contact']['name'], $res['result']['data']['contact']['email'], $res['result']['data']['contact']['group']]);

        $cases = [
            [['contact_id' => 'zzz', 'name' => 'x'], 'not_found'],
            [['email' => 'not an address'], 'invalid_argument'],
            [['email' => 'a@example.com', 'phone' => 'call me'], 'invalid_argument'],
            [['name' => 'Nobody'], 'invalid_argument'],
            [['email' => 'a@example.com', 'extra' => 1], 'invalid_argument'],
        ];
        foreach ($cases as list($args, $code)) {
            $this->assertSame($code, self::error_code($this->run_op('save_contact', $args, $boxes, $options)), json_encode($args));
        }
        $this->assertSame('not_supported', self::error_code($this->run_op('save_contact', ['email' => 'a@example.com'], $boxes)));
        $res = $this->run_op('save_contact', ['email' => 'a@example.com'], $boxes, $options + ['principal' => hm_mcp_fake_principal()]);
        $this->assertSame('permission_denied', self::error_code($res));
    }

    /* ------------------------------------------------------------ catalog */

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_catalog_and_rest_routes() {
        $catalog = new Hm_MCP_Catalog();
        $expected = ['create_folder' => ['folders', false], 'rename_folder' => ['folders', false], 'delete_folder' => ['folders', true],
            'tag_messages' => ['tags', false], 'save_contact' => ['contacts_write', false]];
        $defaults = array_keys($catalog->allowed(Hm_MCP_Permissions::defaults()));
        foreach ($expected as $name => list($permission, $destructive)) {
            $op = $catalog->get($name);
            $this->assertSame($permission, $op['permission'], $name);
            $this->assertSame($destructive, Hm_MCP_Catalog::ANNOTATIONS[$op['kind']]['destructiveHint'], $name);
            $this->assertNotContains($name, $defaults, $name);
        }
        list($services) = hm_mcp_fake_services([], $this->servers);
        $paths = (new Hm_MCP_Rest($services))->openapi()['paths'];
        $this->assertSame(['get', 'post'], array_keys($paths['/accounts/{account_id}/folders']));
        $this->assertSame(['get', 'post'], array_keys($paths['/contacts']));
        $this->assertArrayHasKey('post', $paths['/accounts/{account_id}/folders/rename']);
        $this->assertArrayHasKey('post', $paths['/accounts/{account_id}/folders/delete']);
        $this->assertArrayHasKey('post', $paths['/messages/tag']);
    }
}
