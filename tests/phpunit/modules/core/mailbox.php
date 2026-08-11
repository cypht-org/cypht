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
        require_once APP_PATH.'modules/imap/hm-imap.php';
        require_once APP_PATH.'modules/imap/hm-jmap.php';
        require_once APP_PATH.'modules/imap/hm-ews.php';
        require_once APP_PATH.'modules/smtp/hm-smtp.php';
        require_once APP_PATH.'modules/core/hm-mailbox.php';
        require_once APP_PATH.'modules/imap/functions.php';
        
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
        $this->assertSame(1, Hm_Mailbox::TYPE_IMAP);
        
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
        $this->assertSame(4, Hm_Mailbox::TYPE_SMTP);
        
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
            ->with('date', true, 'ALL', [], true, false)
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
            ->with('ALL', false, [], [], true, false)
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
            ->with('ALL', false, [], [], true, false)
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

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_state_returns_connection_state_for_imap() {
        $mock_imap = $this->createMock(Hm_IMAP::class);
        $mock_imap->method('get_state')->willReturn('authenticated');
        $mailbox = $this->createMailboxWithMockConnection('imap', $mock_imap);
        $this->assertEquals('authenticated', $mailbox->state());
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_state_returns_null_for_ews() {
        $mock_ews = $this->createMock(Hm_EWS::class);
        $mailbox = $this->createMailboxWithMockConnection('ews', $mock_ews);
        $this->assertNull($mailbox->state());
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_get_folder_name_returns_folder_directly_for_imap() {
        $mock_imap = $this->createMock(Hm_IMAP::class);
        $mock_imap->method('get_state')->willReturn('authenticated');
        $mailbox = $this->createMailboxWithMockConnection('imap', $mock_imap);
        $this->assertEquals('INBOX', $mailbox->get_folder_name('INBOX'));
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_get_folder_name_uses_quick_lookup_for_ews() {
        $mock_ews = $this->createMock(Hm_EWS::class);
        $mock_ews->method('get_folder_name_quick')->with('AQMs...')->willReturn('INBOX');
        $mailbox = $this->createMailboxWithMockConnection('ews', $mock_ews);
        $this->assertEquals('INBOX', $mailbox->get_folder_name('AQMs...'));
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_create_folder_returns_null_when_not_authed() {
        $mock_imap = $this->createMock(Hm_IMAP::class);
        $mock_imap->method('get_state')->willReturn('disconnected');
        $mailbox = $this->createMailboxWithMockConnection('imap', $mock_imap);
        $this->assertNull($mailbox->create_folder('NewFolder'));
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_create_folder_delegates_to_connection_for_ews() {
        $mock_ews = $this->createMock(Hm_EWS::class);
        $mock_ews->method('authed')->willReturn(true);
        $mock_ews->method('create_folder')->with('NewFolder', null)->willReturn('folder-id-123');
        $mailbox = $this->createMailboxWithMockConnection('ews', $mock_ews);
        $this->assertEquals('folder-id-123', $mailbox->create_folder('NewFolder'));
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_delete_folder_returns_null_when_not_authed() {
        $mock_imap = $this->createMock(Hm_IMAP::class);
        $mock_imap->method('get_state')->willReturn('disconnected');
        $mailbox = $this->createMailboxWithMockConnection('imap', $mock_imap);
        $this->assertNull($mailbox->delete_folder('OldFolder'));
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_delete_folder_delegates_to_connection_for_ews() {
        $mock_ews = $this->createMock(Hm_EWS::class);
        $mock_ews->method('authed')->willReturn(true);
        $mock_ews->method('delete_folder')->willReturn(true);
        $mailbox = $this->createMailboxWithMockConnection('ews', $mock_ews);
        $this->assertTrue($mailbox->delete_folder('OldFolder'));
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_rename_folder_returns_null_when_not_authed() {
        $mock_imap = $this->createMock(Hm_IMAP::class);
        $mock_imap->method('get_state')->willReturn('disconnected');
        $mailbox = $this->createMailboxWithMockConnection('imap', $mock_imap);
        $this->assertNull($mailbox->rename_folder('OldFolder', 'NewName'));
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_rename_folder_delegates_to_ews_connection() {
        $mock_ews = $this->createMock(Hm_EWS::class);
        $mock_ews->method('authed')->willReturn(true);
        $mock_ews->method('rename_folder')->willReturn(true);
        $mailbox = $this->createMailboxWithMockConnection('ews', $mock_ews);
        $this->assertTrue($mailbox->rename_folder('AQMs...encoded', 'NewName'));
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_get_folders_returns_null_when_not_authed() {
        $mock_imap = $this->createMock(Hm_IMAP::class);
        $mock_imap->method('get_state')->willReturn('disconnected');
        $mailbox = $this->createMailboxWithMockConnection('imap', $mock_imap);
        $this->assertNull($mailbox->get_folders());
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_get_folders_delegates_to_ews_connection() {
        $mock_ews = $this->createMock(Hm_EWS::class);
        $mock_ews->method('authed')->willReturn(true);
        $mock_ews->method('get_folders')->willReturn(array('INBOX' => [], 'Sent' => []));
        $mailbox = $this->createMailboxWithMockConnection('ews', $mock_ews);
        $result = $mailbox->get_folders();
        $this->assertArrayHasKey('INBOX', $result);
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_get_subfolders_returns_null_when_not_authed() {
        $mock_imap = $this->createMock(Hm_IMAP::class);
        $mock_imap->method('get_state')->willReturn('disconnected');
        $mailbox = $this->createMailboxWithMockConnection('imap', $mock_imap);
        $this->assertNull($mailbox->get_subfolders('INBOX'));
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_get_subfolders_delegates_to_ews_connection() {
        $mock_ews = $this->createMock(Hm_EWS::class);
        $mock_ews->method('authed')->willReturn(true);
        $mock_ews->method('get_folders')->willReturn(array('INBOX.Sub' => []));
        $mailbox = $this->createMailboxWithMockConnection('ews', $mock_ews);
        $result = $mailbox->get_subfolders('INBOX');
        $this->assertArrayHasKey('INBOX.Sub', $result);
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_get_folder_state_returns_imap_connection_property() {
        $mock_imap = $this->createMock(Hm_IMAP::class);
        $mock_imap->folder_state = 'selected';
        $mailbox = $this->createMailboxWithMockConnection('imap', $mock_imap);
        $this->assertEquals('selected', $mailbox->get_folder_state());
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_get_selected_folder_returns_imap_connection_property() {
        $mock_imap = $this->createMock(Hm_IMAP::class);
        $mock_imap->selected_mailbox = array('name' => 'INBOX');
        $mailbox = $this->createMailboxWithMockConnection('imap', $mock_imap);
        $this->assertEquals(array('name' => 'INBOX'), $mailbox->get_selected_folder());
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_get_special_use_mailboxes_returns_null_when_not_authed() {
        $mock_imap = $this->createMock(Hm_IMAP::class);
        $mock_imap->method('get_state')->willReturn('disconnected');
        $mailbox = $this->createMailboxWithMockConnection('imap', $mock_imap);
        $this->assertNull($mailbox->get_special_use_mailboxes());
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_get_special_use_mailboxes_delegates_to_ews_connection() {
        $mock_ews = $this->createMock(Hm_EWS::class);
        $mock_ews->method('authed')->willReturn(true);
        $mock_ews->method('get_special_use_folders')->willReturn(array('Trash' => 'Trash', 'Sent' => 'Sent'));
        $mailbox = $this->createMailboxWithMockConnection('ews', $mock_ews);
        $this->assertArrayHasKey('Trash', $mailbox->get_special_use_mailboxes());
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_get_quota_root_delegates_to_imap_connection() {
        $mock_imap = $this->getMockBuilder(Hm_IMAP::class)
            ->addMethods(['get_quota_root', 'get_quota'])
            ->getMock();
        $mock_imap->method('get_quota_root')->with('INBOX')->willReturn(array('usage' => 100, 'limit' => 1000));
        $mailbox = $this->createMailboxWithMockConnection('imap', $mock_imap);
        $result = $mailbox->get_quota('INBOX', true);
        $this->assertEquals(100, $result['usage']);
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_get_quota_returns_empty_for_ews() {
        $mock_ews = $this->createMock(Hm_EWS::class);
        $mailbox = $this->createMailboxWithMockConnection('ews', $mock_ews);
        $this->assertEquals(array(), $mailbox->get_quota('INBOX'));
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_get_debug_delegates_to_imap_connection() {
        $mock_imap = $this->createMock(Hm_IMAP::class);
        $mock_imap->method('show_debug')->with(true, true, true)->willReturn(array('log entry'));
        $mailbox = $this->createMailboxWithMockConnection('imap', $mock_imap);
        $this->assertEquals(array('log entry'), $mailbox->get_debug());
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_get_debug_returns_empty_for_ews() {
        $mock_ews = $this->createMock(Hm_EWS::class);
        $mailbox = $this->createMailboxWithMockConnection('ews', $mock_ews);
        $this->assertEquals(array(), $mailbox->get_debug());
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_use_cache_returns_connection_property_for_imap() {
        $mock_imap = $this->createMock(Hm_IMAP::class);
        $mock_imap->use_cache = true;
        $mailbox = $this->createMailboxWithMockConnection('imap', $mock_imap);
        $this->assertTrue($mailbox->use_cache());
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_use_cache_returns_false_for_ews() {
        $mock_ews = $this->createMock(Hm_EWS::class);
        $mailbox = $this->createMailboxWithMockConnection('ews', $mock_ews);
        $this->assertFalse($mailbox->use_cache());
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_dump_cache_delegates_to_imap_connection() {
        $mock_imap = $this->getMockBuilder(Hm_IMAP::class)
            ->addMethods(['dump_cache'])
            ->getMock();
        $mock_imap->method('dump_cache')->with('string')->willReturn('cache data');
        $mailbox = $this->createMailboxWithMockConnection('imap', $mock_imap);
        $this->assertEquals('cache data', $mailbox->dump_cache());
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_dump_cache_returns_null_for_ews() {
        $mock_ews = $this->createMock(Hm_EWS::class);
        $mailbox = $this->createMailboxWithMockConnection('ews', $mock_ews);
        $this->assertNull($mailbox->dump_cache());
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_get_state_delegates_to_imap_connection() {
        $mock_imap = $this->createMock(Hm_IMAP::class);
        $mock_imap->method('get_state')->willReturn('selected');
        $mailbox = $this->createMailboxWithMockConnection('imap', $mock_imap);
        $this->assertEquals('selected', $mailbox->get_state());
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_get_state_returns_authenticated_for_authed_ews() {
        $mock_ews = $this->createMock(Hm_EWS::class);
        $mock_ews->method('authed')->willReturn(true);
        $mailbox = $this->createMailboxWithMockConnection('ews', $mock_ews);
        $this->assertEquals('authenticated', $mailbox->get_state());
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_get_capability_delegates_to_ews_connection() {
        $mock_ews = $this->createMock(Hm_EWS::class);
        $mock_ews->method('get_capability')->willReturn(array('EWS', 'IDLE'));
        $mailbox = $this->createMailboxWithMockConnection('ews', $mock_ews);
        $this->assertContains('IDLE', $mailbox->get_capability());
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_set_read_only_sets_property_on_imap_connection() {
        $mock_imap = $this->createMock(Hm_IMAP::class);
        $mock_imap->read_only = false;
        $mailbox = $this->createMailboxWithMockConnection('imap', $mock_imap);
        $mailbox->set_read_only(true);
        $this->assertTrue($mock_imap->read_only);
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_set_search_charset_sets_property_on_imap_connection() {
        $mock_imap = $this->createMock(Hm_IMAP::class);
        $mock_imap->search_charset = '';
        $mailbox = $this->createMailboxWithMockConnection('imap', $mock_imap);
        $mailbox->set_search_charset('UTF-8');
        $this->assertEquals('UTF-8', $mock_imap->search_charset);
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_get_message_list_delegates_to_ews_connection() {
        $mock_ews = $this->createMock(Hm_EWS::class);
        $mock_ews->method('authed')->willReturn(true);
        $mock_ews->method('get_folder_status')->willReturn(array('id' => 'inbox-id', 'name' => 'INBOX', 'messages' => 10));
        $mock_ews->method('get_message_list')->willReturn(array(1 => array('subject' => 'Test')));
        $mailbox = $this->createMailboxWithMockConnection('ews', $mock_ews);
        $result = $mailbox->get_message_list('INBOX', array(1));
        $this->assertArrayHasKey(1, $result);
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_is_archive_folder_returns_false_for_ews() {
        $mock_ews = $this->createMock(Hm_EWS::class);
        $mailbox = $this->createMailboxWithMockConnection('ews', $mock_ews);
        $this->assertFalse($mailbox->is_archive_folder('archive', new Hm_Mock_Config(), 'INBOX'));
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_is_inplace_archive_enabled_delegates_to_ews_connection() {
        $mock_ews = $this->createMock(Hm_EWS::class);
        $mock_ews->method('is_inplace_archive_enabled')->willReturn(true);
        $mailbox = $this->createMailboxWithMockConnection('ews', $mock_ews);
        $this->assertTrue($mailbox->is_inplace_archive_enabled());
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_is_inplace_archive_enabled_returns_null_for_imap() {
        $mock_imap = $this->createMock(Hm_IMAP::class);
        $mailbox = $this->createMailboxWithMockConnection('imap', $mock_imap);
        $this->assertNull($mailbox->is_inplace_archive_enabled());
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_prep_folder_name_returns_folder_decoded_for_ews() {
        $mock_ews = $this->createMock(Hm_EWS::class);
        $mailbox = $this->createMailboxWithMockConnection('ews', $mock_ews);
        $this->assertEquals('simpletestfolder', $mailbox->prep_folder_name('simpletestfolder'));
    }
}
