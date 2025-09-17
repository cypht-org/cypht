<?php

/**
 * Unit tests for Hm_Mailbox class
 * @package modules
 * @subpackage core/tests
 */

use PHPUnit\Framework\TestCase;

/**
 * Hm_Mailbox unit tests
 * 
 * Tests for the Hm_Mailbox bridge class that provides a unified interface
 * for different mail server types (IMAP, JMAP, EWS, SMTP).
 * 
 */

class Hm_Test_Mailbox extends TestCase {

    private $mock_user_config;
    private $mock_session;
    private $server_id = 'test_server_1';

    public function setUp(): void {
        define('IMAP_TEST', true);
        require_once __DIR__.'/../../bootstrap.php';
        require_once APP_PATH.'modules/imap/hm-imap.php';
        require_once APP_PATH.'modules/imap/hm-jmap.php';
        require_once APP_PATH.'modules/imap/hm-ews.php';
        require_once APP_PATH.'modules/smtp/hm-smtp.php';
        require_once APP_PATH.'modules/core/hm-mailbox.php';
        
        $this->mock_user_config = new Hm_Mock_Config();
        $this->mock_user_config->set('unsubscribed_folders', ['test_server_1' => ['Junk', 'Spam']]);
        
        $this->mock_session = new Hm_Mock_Session();
    }

    /**
     * Helper method to create a mailbox with injected mock connection
     * This eliminates the need for ReflectionClass in our tests
     */
    private function createMailboxWithMockConnection($type, $mock_connection , $config = []) {
        $config = array_merge(['type' => $type], $config);
        $mailbox = new Hm_Mailbox($this->server_id, $this->mock_user_config, $this->mock_session, $config);
        $mailbox->set_connection($mock_connection);
        return $mailbox;
    }

    /**
     * Test IMAP mailbox construction
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_imap_mailbox_construction() {
        $config = [
            'type' => 'imap',
            'server' => 'imap.example.com',
            'port' => 993,
            'tls' => true
        ];

        $mailbox = new Hm_Mailbox($this->server_id, $this->mock_user_config, $this->mock_session, $config);
        
        $this->assertTrue($mailbox->is_imap());
        $this->assertFalse($mailbox->is_smtp());
        $this->assertEquals('IMAP', $mailbox->server_type());
        $this->assertEquals($config, $mailbox->get_config());
    }

    /**
     * Test JMAP mailbox construction
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_jmap_mailbox_construction() {
        $config = [
            'type' => 'jmap',
            'server' => 'jmap.example.com',
            'port' => 443
        ];

        $mailbox = new Hm_Mailbox($this->server_id, $this->mock_user_config, $this->mock_session, $config);
        
        $this->assertTrue($mailbox->is_imap()); // JMAP is considered IMAP-like
        $this->assertFalse($mailbox->is_smtp());
        $this->assertEquals('JMAP', $mailbox->server_type());
    }

    /**
     * Test SMTP mailbox construction
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_smtp_mailbox_construction() {
        $config = [
            'type' => 'smtp',
            'server' => 'smtp.example.com',
            'port' => 587,
            'tls' => true,
            'username' => 'testuser',
            'password' => 'testpass'
        ];

        $mailbox = new Hm_Mailbox($this->server_id, $this->mock_user_config, $this->mock_session, $config);
        
        $this->assertFalse($mailbox->is_imap());
        $this->assertTrue($mailbox->is_smtp());
        $this->assertNull($mailbox->server_type()); // SMTP doesn't return server type
    }

    /**
     * Test EWS mailbox construction
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_ews_mailbox_construction() {
        $config = [
            'type' => 'ews',
            'server' => 'ews.example.com',
            'username' => 'test@example.com'
        ];

        $mailbox = new Hm_Mailbox($this->server_id, $this->mock_user_config, $this->mock_session, $config);
        
        $this->assertFalse($mailbox->is_imap());
        $this->assertFalse($mailbox->is_smtp());
        $this->assertEquals('EWS', $mailbox->server_type());
    }

    /**
     * Test unknown type construction
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_unknown_type_construction() {
        $config = [
            'type' => 'unknown',
            'server' => 'unknown.example.com'
        ];

        $mailbox = new Hm_Mailbox($this->server_id, $this->mock_user_config, $this->mock_session, $config);
        
        $this->assertFalse($mailbox->is_imap());
        $this->assertFalse($mailbox->is_smtp());
        $this->assertNull($mailbox->server_type());
        $this->assertNull($mailbox->get_connection());
    }

    /**
     * Test connection when no connection object exists
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_connect_without_connection() {
        $config = ['type' => 'unknown'];
        $mailbox = new Hm_Mailbox($this->server_id, $this->mock_user_config, $this->mock_session, $config);
        
        $this->assertFalse($mailbox->is_imap());
        $this->assertFalse($mailbox->is_smtp());
        $this->assertNull($mailbox->server_type());
        $this->assertNull($mailbox->get_connection());
        $this->assertFalse($mailbox->authed());
        
        $this->assertFalse($mailbox->connect());
        
        $this->assertNull($mailbox->get_connection());
        $this->assertFalse($mailbox->authed());
    }

    /**
     * Test IMAP authentication status
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_imap_authed_status() {
        $this->assertEquals('1', Hm_Mailbox::TYPE_IMAP);
        
        $mock_imap = $this->createMock(Hm_IMAP::class);
        $mock_imap->method('get_state')
            ->willReturnCallback(function() {
                static $call_count = 0;
                $call_count++;
                return $call_count === 1 ? 'disconnected' : 'authenticated';
            });
        
        $mailbox = $this->createMailboxWithMockConnection('imap', $mock_imap, [
            'username' => 'testuser',
            'password' => 'testpass'
        ]);
        
        $this->assertTrue($mailbox->is_imap());
        $this->assertFalse($mailbox->is_smtp());
        
        $this->assertFalse($mailbox->authed());
        $this->assertTrue($mailbox->authed());
    }

    /**
     * Test SMTP authentication status
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_smtp_authed_status() {
        $this->assertEquals('4', Hm_Mailbox::TYPE_SMTP);
        
        $mock_smtp = $this->createMock(Hm_SMTP::class);
        
        $mock_smtp->state = 'disconnected';
        
        $mailbox = $this->createMailboxWithMockConnection('smtp', $mock_smtp, [
            'username' => 'testuser',
            'password' => 'testpass'
        ]);
        
        $this->assertFalse($mailbox->is_imap());
        $this->assertTrue($mailbox->is_smtp());
        
        $this->assertFalse($mailbox->authed());
        
        $mock_smtp->state = 'authed';
        $this->assertTrue($mailbox->authed());
    }

    /**
     * Test folder existence check
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_folder_exists() {
        $mock_imap = $this->createMock(Hm_IMAP::class);
        $mock_imap->method('get_state')->willReturn('authenticated');
        
        $mock_imap->method('get_mailbox_status')
            ->willReturnCallback(function($folder) {
                if ($folder === 'INBOX') {
                    return [
                        'messages' => 42,
                        'recent' => 3,
                        'uidnext' => 1000,
                        'uidvalidity' => 12345,
                        'unseen' => 5
                    ];
                }
                return [];
            });
        
        $mock_imap->method('get_mailbox_list')
            ->willReturn(['INBOX' => []]);
        
        $mailbox = $this->createMailboxWithMockConnection('imap', $mock_imap);
        
        $this->assertArrayHasKey(
            'INBOX',
            $mailbox->get_connection()->get_mailbox_list()
        );
        $this->assertArrayNotHasKey(
            'Sent',
            $mailbox->get_connection()->get_mailbox_list()
        );
        
        $this->assertTrue($mailbox->folder_exists('INBOX'));
        $this->assertFalse($mailbox->folder_exists('NonExistentFolder'));
    }

    /**
     * Test get folder status when not authenticated
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_get_folder_status_not_authed() {
        $mock_imap = $this->createMock(Hm_IMAP::class);
        $mock_imap->method('get_state')->willReturn('disconnected');
        
        $mailbox = $this->createMailboxWithMockConnection('imap', $mock_imap);
        
        $this->assertNull($mailbox->get_folder_status('INBOX'));
    }

    /**
     * Test message retrieval with pagination
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_get_messages_with_pagination() {
        $mock_imap = $this->createMock(Hm_IMAP::class);
        $mock_imap->method('get_state')->willReturn('authenticated');
        
        $mock_imap->method('select_mailbox')
            ->with('INBOX')
            ->willReturn(true);
        
        $mock_imap->method('get_mailbox_page')
            ->with('INBOX', 'arrival', false, 'ALL', 5, 5, false, [], false)
            ->willReturn([
                15,
                [
                    [
                        'uid' => 10, 'flags' => [], 'internal_date' => '2023-10-15 10:30:00',
                        'from' => 'sender1@example.com', 'subject' => 'Test Message 10'
                    ],
                    [
                        'uid' => 9, 'flags' => ['\\Seen'], 'internal_date' => '2023-10-15 09:15:00',
                        'from' => 'sender2@example.com', 'subject' => 'Test Message 9'
                    ],
                    [
                        'uid' => 8, 'flags' => [], 'internal_date' => '2023-10-15 08:45:00',
                        'from' => 'sender3@example.com', 'subject' => 'Test Message 8'
                    ],
                    [
                        'uid' => 7, 'flags' => ['\\Seen', '\\Flagged'], 'internal_date' => '2023-10-14 16:20:00',
                        'from' => 'sender4@example.com', 'subject' => 'Important Message 7'
                    ],
                    [
                        'uid' => 6, 'flags' => [], 'internal_date' => '2023-10-14 14:10:00',
                        'from' => 'sender5@example.com', 'subject' => 'Test Message 6'
                    ]
                ]
            ]);
        
        $mailbox = $this->createMailboxWithMockConnection('imap', $mock_imap);
        
        $result = $mailbox->get_messages('INBOX', 'arrival', false, 'ALL', 5, 5);
        
        $this->assertEquals(15, $result[0]);
        $this->assertCount(5, $result[1]);
        
        $messages = $result[1];
        $this->assertEquals(10, $messages[0]['uid']);
        $this->assertEquals('Test Message 10', $messages[0]['subject']);
        $this->assertEquals('sender1@example.com', $messages[0]['from']);
        
        $this->assertEquals(9, $messages[1]['uid']);
        $this->assertContains('\\Seen', $messages[1]['flags']);
        
        $this->assertEquals(8, $messages[2]['uid']);
        $this->assertEquals(7, $messages[3]['uid']);
        $this->assertContains('\\Flagged', $messages[3]['flags']);
        
        $this->assertEquals(6, $messages[4]['uid']);
        
        foreach ($messages as $message) {
            $this->assertArrayHasKey('folder', $message);
            $this->assertEquals(bin2hex('INBOX'), $message['folder']);
        }
    }

    /**
     * Test folder subscription for IMAP
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_imap_folder_subscription() {
        $config = ['type' => 'imap'];
        $mailbox = new Hm_Mailbox($this->server_id, $this->mock_user_config, $this->mock_session, $config);
        
        $this->assertNull($mailbox->folder_subscription('INBOX', true));
        
        $mock_imap = $this->createMock(Hm_IMAP::class);
        $mock_imap->method('get_state')->willReturn('authenticated');
        $mock_imap->method('mailbox_subscription')->willReturn(true);
        
        $mailbox = $this->createMailboxWithMockConnection('imap', $mock_imap);
        
        $this->assertTrue($mailbox->folder_subscription('TestFolder', true));
        $this->assertTrue($mailbox->folder_subscription('TestFolder', false));
    }

    /**
     * Test folder subscription for non-IMAP (EWS)
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_non_imap_folder_subscription() {
        $mock_ews = $this->createMock(Hm_EWS::class);
        $mock_ews->method('authed')->willReturn(true);
        
        $mailbox = $this->createMailboxWithMockConnection('ews', $mock_ews);
        
        $this->mock_user_config->set('unsubscribed_folders', ['test_server_1' => ['TestFolder']]);
        $this->mock_session->set('user_data', []);
        
        $this->assertTrue($mailbox->folder_subscription('TestFolder', false));
    }

    /**
     * Test search functionality
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_search_functionality() {
        $mock_imap = $this->createMock(Hm_IMAP::class);
        $mock_imap->method('get_state')->willReturn('authenticated');
        
        $mock_imap->selected_mailbox = ['name' => 'INBOX'];
        $mock_imap->method('select_mailbox')->willReturn(true);
        
        $mock_imap->method('is_supported')->with('SORT')->willReturn(true);
        
        $mock_imap->method('get_message_sort_order')
            ->with('date', true, 'ALL', [], true, true, false)
            ->willReturn([1, 2, 3]);
        
        $mailbox = $this->createMailboxWithMockConnection('imap', $mock_imap);
        
        $result = $mailbox->search('INBOX', 'ALL', [], 'date', true);
        
        $this->assertEquals([1, 2, 3], $result);
    }

    /**
     * Test search without sort support
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_search_without_sort_support() {
        $config = ['type' => 'imap'];
        $mailbox = new Hm_Mailbox($this->server_id, $this->mock_user_config, $this->mock_session, $config);
        
        $mock_imap = $this->createMock(Hm_IMAP::class);
        $mock_imap->method('get_state')->willReturn('authenticated');
        
        $mock_imap->selected_mailbox = ['name' => 'INBOX'];
        $mock_imap->method('select_mailbox')->willReturn(true);
        
        $mock_imap->method('is_supported')->with('SORT')->willReturn(false);
        
        $mock_imap->method('search')
            ->with('ALL', false, [], [], true, true, false)
            ->willReturn([3, 1, 2]);
        
        $mock_imap->method('sort_by_fetch')
            ->with('date', false, 'ALL', '3,1,2')
            ->willReturn([1, 2, 3]);
        
        $mailbox = $this->createMailboxWithMockConnection('imap', $mock_imap);
        
        $result = $mailbox->search('INBOX', 'ALL', [], 'date', false);
        
        $this->assertEquals([1, 2, 3], $result);
    }

    /**
     * Test search without sorting
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_search_without_sorting() {
        $config = ['type' => 'imap'];
        $mailbox = new Hm_Mailbox($this->server_id, $this->mock_user_config, $this->mock_session, $config);
        
        $mock_imap = $this->createMock(Hm_IMAP::class);
        $mock_imap->method('get_state')->willReturn('authenticated');
        
        $mock_imap->selected_mailbox = ['name' => 'INBOX'];
        $mock_imap->method('select_mailbox')->willReturn(true);
        
        $mock_imap->method('search')
            ->with('ALL', false, [], [], true, true, false)
            ->willReturn([4, 5, 6]);
        
        $mailbox = $this->createMailboxWithMockConnection('imap', $mock_imap);
        
        $result = $mailbox->search('INBOX', 'ALL', [], null, null);
        
        $this->assertEquals([4, 5, 6], $result);
    }

    /**
     * Test search when folder selection fails
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_search_folder_selection_fails() {
        $mock_imap = $this->createMock(Hm_IMAP::class);
        $mock_imap->method('get_state')->willReturn('authenticated');
        
        $mock_imap->selected_mailbox = ['name' => 'INBOX'];
        $mock_imap->method('select_mailbox')->with('NonExistent')->willReturn(false);
    
        $mailbox = $this->createMailboxWithMockConnection('imap', $mock_imap);
        
        $result = $mailbox->search('NonExistent', 'ALL', [], 'date', true);
        
        $this->assertEquals([], $result);
    }

    /**
     * Test JMAP search functionality
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_jmap_search_functionality() {
        $config = ['type' => 'jmap'];
        $mailbox = new Hm_Mailbox($this->server_id, $this->mock_user_config, $this->mock_session, $config);
        
        $mock_jmap = $this->createMock(Hm_JMAP::class);
        
        $mock_jmap->method('select_mailbox')
            ->with('INBOX')
            ->willReturn(true);
        
        $mock_jmap->method('search')
            ->with('ALL', false, [], [], true, true, false)
            ->willReturn(['uid1', 'uid2', 'uid3']);
        
        $mailbox = $this->createMailboxWithMockConnection('jmap', $mock_jmap);
        
        $result = $mailbox->search('INBOX');
        
        $this->assertEquals(['uid1', 'uid2', 'uid3'], $result);
    }

    /**
     * Test EWS search functionality
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_ews_search_functionality() {        
        $mock_ews = $this->createMock(Hm_EWS::class);
        $mock_ews->method('authed')->willReturn(true);
        
        $mock_ews->method('search')
            ->with('INBOX', 'date', true, 'ALL', 0, 9999, [], [])
            ->willReturn([200, [7, 8, 9]]);
        
        $mock_ews->method('get_folder_status')
            ->with('INBOX')
            ->willReturn([
                'id' => 'inbox-folder-id',
                'name' => 'INBOX',
                'messages' => 200,
                'uidvalidity' => false,
                'uidnext' => false,
                'recent' => false,
                'unseen' => 15
            ]);
        
        $mailbox = $this->createMailboxWithMockConnection('ews', $mock_ews);
        
        $result = $mailbox->search('INBOX', 'ALL', [], 'date', true);
        
        $this->assertEquals([7, 8, 9], $result);
    }

    /**
     * Test JMAP search without sort support
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_jmap_search_without_sort_support() {        
        $mock_jmap = $this->createMock(Hm_JMAP::class);
        
        $mock_jmap->method('select_mailbox')
            ->with('INBOX')
            ->willReturn(true);
        
        $mock_jmap->method('search')
            ->with('ALL', false, [], [], true, true, false)
            ->willReturn(['uid4', 'uid5', 'uid6']);
        
        $mailbox = $this->createMailboxWithMockConnection('jmap', $mock_jmap);
        
        $result = $mailbox->search('INBOX', 'ALL', [], 'date', false);
        
        $this->assertEquals(['uid4', 'uid5', 'uid6'], $result);
    }

    /**
     * Test EWS search without sort support
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_ews_search_without_sort_support() {        
        $mock_ews = $this->createMock(Hm_EWS::class);
        $mock_ews->method('authed')->willReturn(true);
        
        $mock_ews->method('search')
            ->with('INBOX', 'date', false, 'ALL', 0, 9999, [], [])
            ->willReturn([250, [13, 14, 15]]);
        
        $mock_ews->method('get_folder_status')
            ->with('INBOX')
            ->willReturn([
                'id' => 'inbox-folder-id',
                'name' => 'INBOX',
                'messages' => 250,
                'uidvalidity' => false,
                'uidnext' => false,
                'recent' => false,
                'unseen' => 20
            ]);

        $mailbox = $this->createMailboxWithMockConnection('ews', $mock_ews);
        
        $result = $mailbox->search('INBOX', 'ALL', [], 'date', false);
        
        $this->assertEquals([13, 14, 15], $result);
    }

    /**
     * Test JMAP search when folder selection fails
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_jmap_search_folder_selection_fails() {
        $mock_jmap = $this->createMock(Hm_JMAP::class);
        $mock_jmap->method('get_state')->willReturn('authenticated');
        
        $mock_jmap->selected_mailbox = ['name' => 'INBOX'];
        $mock_jmap->method('select_mailbox')->with('NonExistent')->willReturn(false);
        
        $mailbox = $this->createMailboxWithMockConnection('jmap', $mock_jmap);
        
        $result = $mailbox->search('NonExistent', 'ALL', [], 'date', true);
        
        $this->assertEquals([], $result);
    }

    /**
     * Test EWS search when folder selection fails
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_ews_search_folder_selection_fails() {
        $mock_ews = $this->createMock(Hm_EWS::class);
        $mock_ews->method('authed')->willReturn(true);
        
        $mock_ews->method('get_folder_status')
            ->with('NonExistent')
            ->willReturn([]);

        $mailbox = $this->createMailboxWithMockConnection('ews', $mock_ews);
        
        $result = $mailbox->search('NonExistent', 'ALL', [], 'date', true);
        
        $this->assertEquals([], $result);
    }

    /**
     * Test message deletion with trash folder
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_delete_message_with_trash() {        
        $mock_imap = $this->createMock(Hm_IMAP::class);
        $mock_imap->method('get_state')->willReturn('authenticated');
        
        $mock_imap->method('select_mailbox')
            ->with('INBOX')
            ->willReturn(true);
        
        $mock_imap->method('message_action')
            ->with('MOVE', [123], 'Trash')
            ->willReturn(['status' => true]);
        
        $mailbox = $this->createMailboxWithMockConnection('imap', $mock_imap);
        
        $result = $mailbox->delete_message('INBOX', 123, 'Trash');
        
        $this->assertTrue($result);
    }

    /**
     * Test message deletion without trash folder
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_delete_message_without_trash() {
        $mock_imap = $this->createMock(Hm_IMAP::class);
        $mock_imap->method('get_state')->willReturn('authenticated');
        
        $mock_imap->method('select_mailbox')
            ->with('INBOX')
            ->willReturn(true);
        
        $mock_imap->method('message_action')
            ->willReturnCallback(function($action, $ids, $mailbox = null) {
                if ($action === 'DELETE' && $ids === [123]) {
                    return ['status' => true];
                }
                if ($action === 'EXPUNGE' && $ids === [123]) {
                    return ['status' => true];
                }
                return ['status' => false];
            });

        $mailbox = $this->createMailboxWithMockConnection('imap', $mock_imap);
        
        $result = $mailbox->delete_message('INBOX', 123, null);
        
        $this->assertTrue($result);
    }

    /**
     * Test delete message when folder selection fails
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_delete_message_folder_selection_fails() {
        $mock_imap = $this->createMock(Hm_IMAP::class);
        $mock_imap->method('get_state')->willReturn('authenticated');
        
        $mock_imap->method('select_mailbox')
            ->with('NonExistentFolder')
            ->willReturn(false);
        
        $mailbox = $this->createMailboxWithMockConnection('imap', $mock_imap);
        
        $result = $mailbox->delete_message('NonExistentFolder', 123, 'Trash');
        
        $this->assertNull($result);
    }

    /**
     * Test delete message when MOVE action fails
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_delete_message_move_fails() {
        $mock_imap = $this->createMock(Hm_IMAP::class);
        $mock_imap->method('get_state')->willReturn('authenticated');
        
        $mock_imap->method('select_mailbox')
            ->with('INBOX')
            ->willReturn(true);
        
        $mock_imap->method('message_action')
            ->with('MOVE', [123], 'Trash')
            ->willReturn(['status' => false]);
        
        $mailbox = $this->createMailboxWithMockConnection('imap', $mock_imap);
        
        $result = $mailbox->delete_message('INBOX', 123, 'Trash');
        
        $this->assertFalse($result);
    }

    /**
     * Test EWS delete message without trash folder
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_ews_delete_message_without_trash() {
        $mock_ews = $this->createMock(Hm_EWS::class);
        $mock_ews->method('authed')->willReturn(true);
        
        $mock_ews->method('get_folder_status')
            ->with('INBOX')
            ->willReturn([
                'id' => 'inbox-folder-id',
                'name' => 'INBOX',
                'messages' => 100
            ]);
        
        $mock_ews->method('message_action')
            ->willReturnCallback(function($action, $ids) {
                if ($action === 'DELETE' && $ids === [123]) {
                    return ['status' => true];
                }
                if ($action === 'EXPUNGE' && $ids === [123]) {
                    return ['status' => true];
                }
                return ['status' => false];
            });
        
        $mailbox = $this->createMailboxWithMockConnection('ews', $mock_ews);
        
        $result = $mailbox->delete_message('INBOX', 123, null);
        
        $this->assertTrue($result);
    }

    /**
     * Test SMTP message sending
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_smtp_send_message() {
        $mock_smtp = $this->createMock(Hm_SMTP::class);
        
        $mock_smtp->expects($this->once())
            ->method('send_message')
            ->with(
                'test@example.com',
                ['recipient@example.com'],
                'Test message content',
                '',
                ''
            )
            ->willReturn(true);

        $mailbox = $this->createMailboxWithMockConnection('smtp', $mock_smtp, ['username' => 'testuser', 'password' => 'testpass']);

        $from = 'test@example.com';
        $recipients = ['recipient@example.com'];
        $message = 'Test message content';
        
        $this->assertTrue($mailbox->send_message($from, $recipients, $message));
    }

    /**
     * Test SMTP message sending with delivery receipt
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_smtp_send_message_with_delivery_receipt() {
        $mock_smtp = $this->createMock(Hm_SMTP::class);
        $mock_smtp->expects($this->once())
            ->method('send_message')
            ->with(
                'test@example.com',
                ['recipient@example.com'],
                'Test message',
                'RET=HDRS',
                'NOTIFY=SUCCESS,FAILURE'
            )
            ->willReturn(true);
        
        $mailbox = $this->createMailboxWithMockConnection('smtp', $mock_smtp, ['username' => 'testuser', 'password' => 'testpass']);
        
        $this->assertTrue($mailbox->send_message(
            'test@example.com',
            ['recipient@example.com'],
            'Test message',
            true // delivery receipt
        ));
    }

    /**
     * Test SMTP message sending failure
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_smtp_send_message_failure() {
        $mock_smtp = $this->createMock(Hm_SMTP::class);
        $mock_smtp->expects($this->once())
            ->method('send_message')
            ->with(
                'test@example.com',
                ['recipient@example.com'],
                'Test message',
                '',
                ''
            )
            ->willReturn(false);
        
        $mailbox = $this->createMailboxWithMockConnection('smtp', $mock_smtp, ['username' => 'testuser', 'password' => 'testpass']);
        
        $this->assertFalse($mailbox->send_message(
            'test@example.com',
            ['recipient@example.com'],
            'Test message'
        ));
    }

    /**
     * Test EWS message sending (non-SMTP protocol that supports send_message)
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_non_smtp_send_message() {
        $mock_ews = $this->createMock(Hm_EWS::class);
        $mock_ews->expects($this->once())
            ->method('send_message')
            ->with(
                'test@example.com',
                ['recipient@example.com'],
                'Test message',
                false
            )
            ->willReturn(true);
        
        $mailbox = $this->createMailboxWithMockConnection('ews', $mock_ews, ['username' => 'testuser', 'password' => 'testpass']);
        
        $this->assertTrue($mailbox->send_message(
            'test@example.com',
            ['recipient@example.com'],
            'Test message',
            false
        ));
    }

    /**
     * Test message sending with protocol that doesn't support send_message (IMAP)
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_unsupported_send_message() {
        $mock_imap = $this->createMock(Hm_IMAP::class);
        $mock_imap->method('get_state')->willReturn('authenticated');
        
        $mailbox = $this->createMailboxWithMockConnection('imap', $mock_imap);
        
        $this->expectException(Error::class);
        
        $mailbox->send_message(
            'test@example.com',
            ['recipient@example.com'],
            'Test message'
        );
    }
}
