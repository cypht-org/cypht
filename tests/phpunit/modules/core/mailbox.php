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
 */

class Hm_Test_Mailbox extends TestCase {

    private $mock_user_config;
    private $mock_session;
    private $server_id = 'test_server_1';

    public function setUp(): void {
        // Mock user config et session seront créés dans loadRequiredFiles
        $this->mock_user_config = null;
        $this->mock_session = null;
    }

    private function loadRequiredFiles() {
        if (!defined('APP_PATH')) {
            require __DIR__.'/../../bootstrap.php';
        }
        
        // Load mocks first
        if (!class_exists('Hm_Mock_Session', false)) {
            require_once APP_PATH.'tests/phpunit/mocks.php';
        }
        
        if (!class_exists('Hm_IMAP', false)) {
            require_once APP_PATH.'modules/imap/hm-imap.php';
        }
        if (!class_exists('Hm_JMAP', false)) {
            require_once APP_PATH.'modules/imap/hm-jmap.php';
        }
        if (!class_exists('Hm_EWS', false)) {
            require_once APP_PATH.'modules/imap/hm-ews.php';
        }
        if (!class_exists('Hm_SMTP', false)) {
            require_once APP_PATH.'modules/smtp/hm-smtp.php';
        }
        if (!class_exists('Hm_Mailbox', false)) {
            require_once APP_PATH.'modules/core/hm-mailbox.php';
        }
        
        // Create mocks using the real mock classes
        if (!$this->mock_user_config) {
            $this->mock_user_config = new Hm_Mock_Config();
            $this->mock_user_config->set('unsubscribed_folders', ['test_server_1' => ['Junk', 'Spam']]);
        }
        
        if (!$this->mock_session) {
            $this->mock_session = new Hm_Mock_Session();
        }
    }

    /**
     * Test IMAP mailbox construction
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_imap_mailbox_construction() {
        $this->loadRequiredFiles();
        
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
        $this->loadRequiredFiles();
        
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
        $this->loadRequiredFiles();
        
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
        $this->loadRequiredFiles();
        
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
        $this->loadRequiredFiles();
        
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
        $this->loadRequiredFiles();
        
        $config = ['type' => 'unknown'];
        $mailbox = new Hm_Mailbox($this->server_id, $this->mock_user_config, $this->mock_session, $config);
        
        $this->assertFalse($mailbox->connect());
    }

    /**
     * Test IMAP authentication status
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_imap_authed_status() {
        $this->loadRequiredFiles();
        
        $config = ['type' => 'imap'];
        $mailbox = new Hm_Mailbox($this->server_id, $this->mock_user_config, $this->mock_session, $config);
        
        $mock_imap = new Hm_Mock_IMAP();
        $mock_imap->get_state_values = ['disconnected', 'authenticated', 'authenticated'];
        
        $reflection = new ReflectionClass($mailbox);
        $connection_property = $reflection->getProperty('connection');
        $connection_property->setAccessible(true);
        $connection_property->setValue($mailbox, $mock_imap);
        
        $this->assertFalse($mailbox->authed()); // disconnected
        $this->assertTrue($mailbox->authed());  // authenticated
        $this->assertTrue($mailbox->authed());  // authenticated
    }

    /**
     * Test SMTP authentication status
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_smtp_authed_status() {
        $this->loadRequiredFiles();
        
        $config = [
            'type' => 'smtp',
            'username' => 'testuser',
            'password' => 'testpass'
        ];
        $mailbox = new Hm_Mailbox($this->server_id, $this->mock_user_config, $this->mock_session, $config);
        
        $mock_smtp = new Hm_Mock_SMTP();
        $mock_smtp->state = 'disconnected';
        
        $reflection = new ReflectionClass($mailbox);
        $connection_property = $reflection->getProperty('connection');
        $connection_property->setAccessible(true);
        $connection_property->setValue($mailbox, $mock_smtp);
        
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
        $this->loadRequiredFiles();
        
        $config = ['type' => 'imap'];
        $mailbox = new Hm_Mailbox($this->server_id, $this->mock_user_config, $this->mock_session, $config);
        
        $mock_imap = new Hm_Mock_IMAP();
        
        $reflection = new ReflectionClass($mailbox);
        $connection_property = $reflection->getProperty('connection');
        $connection_property->setAccessible(true);
        $connection_property->setValue($mailbox, $mock_imap);
        
        $this->assertTrue($mailbox->folder_exists('INBOX'));
        $this->assertFalse($mailbox->folder_exists('NonExistent'));
    }

    /**
     * Test get folder status when not authenticated
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_get_folder_status_not_authed() {
        $this->loadRequiredFiles();
        
        $config = ['type' => 'imap'];
        $mailbox = new Hm_Mailbox($this->server_id, $this->mock_user_config, $this->mock_session, $config);
        
        $mock_imap = $this->createMock(Hm_IMAP::class);
        $mock_imap->method('get_state')->willReturn('disconnected');
        
        $reflection = new ReflectionClass($mailbox);
        $connection_property = $reflection->getProperty('connection');
        $connection_property->setAccessible(true);
        $connection_property->setValue($mailbox, $mock_imap);
        
        $this->assertNull($mailbox->get_folder_status('INBOX'));
    }

    /**
     * Test message retrieval with pagination
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_get_messages_with_pagination() {
        $this->loadRequiredFiles();
        
        $config = ['type' => 'imap'];
        $mailbox = new Hm_Mailbox($this->server_id, $this->mock_user_config, $this->mock_session, $config);
        
        $mock_imap = new Hm_Mock_IMAP();
        
        $reflection = new ReflectionClass($mailbox);
        $connection_property = $reflection->getProperty('connection');
        $connection_property->setAccessible(true);
        $connection_property->setValue($mailbox, $mock_imap);
        
        $result = $mailbox->get_messages('INBOX', 'arrival', false, 'ALL', 0, 20);
        
        $this->assertEquals(100, $result[0]);
        $this->assertEquals(2, count($result[1]));
        $this->assertEquals('Test 1', $result[1][0]['subject']);
        
        foreach ($result[1] as $msg) {
            $this->assertArrayHasKey('folder', $msg);
        }
    }

    /**
     * Test message retrieval when folder selection fails
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_get_messages_folder_selection_fails() {
        $this->loadRequiredFiles();
        
        $config = ['type' => 'imap'];
        $mailbox = new Hm_Mailbox($this->server_id, $this->mock_user_config, $this->mock_session, $config);
        
        $mock_imap = new Hm_Mock_IMAP();
        $mock_imap->state = 'authenticated';
        $mock_imap->select_mailbox_result = false; // Simulate failure
        
        $reflection = new ReflectionClass($mailbox);
        $connection_property = $reflection->getProperty('connection');
        $connection_property->setAccessible(true);
        $connection_property->setValue($mailbox, $mock_imap);
        
        $result = $mailbox->get_messages('NonExistent', 'arrival', false, 'ALL');
        
        $this->assertEquals([0, []], $result);
    }

    /**
     * Test folder subscription for IMAP
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_imap_folder_subscription() {
        $this->loadRequiredFiles();
        
        $config = ['type' => 'imap'];
        $mailbox = new Hm_Mailbox($this->server_id, $this->mock_user_config, $this->mock_session, $config);
        
        $mock_imap = new Hm_Mock_IMAP();
        $mock_imap->state = 'authenticated';
        $mock_imap->subscription_result = true;
        
        $reflection = new ReflectionClass($mailbox);
        $connection_property = $reflection->getProperty('connection');
        $connection_property->setAccessible(true);
        $connection_property->setValue($mailbox, $mock_imap);
        
        $this->assertTrue($mailbox->folder_subscription('INBOX', true));
    }

    /**
     * Test folder subscription for non-IMAP (EWS)
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_non_imap_folder_subscription() {
        $this->loadRequiredFiles();
        
        $config = ['type' => 'ews'];
        $mailbox = new Hm_Mailbox($this->server_id, $this->mock_user_config, $this->mock_session, $config);
        
        $mock_ews = $this->createMock(Hm_EWS::class);
        $mock_ews->method('authed')->willReturn(true);
        
        $reflection = new ReflectionClass($mailbox);
        $connection_property = $reflection->getProperty('connection');
        $connection_property->setAccessible(true);
        $connection_property->setValue($mailbox, $mock_ews);
        
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
        $this->loadRequiredFiles();
        
        $config = ['type' => 'imap'];
        $mailbox = new Hm_Mailbox($this->server_id, $this->mock_user_config, $this->mock_session, $config);
        
        $mock_imap = new Hm_Mock_IMAP();
        $mock_imap->state = 'authenticated';
        $mock_imap->select_mailbox_result = true;
        $mock_imap->sort_support = true;
        $mock_imap->search_result = [1, 2, 3];
        
        $reflection = new ReflectionClass($mailbox);
        $connection_property = $reflection->getProperty('connection');
        $connection_property->setAccessible(true);
        $connection_property->setValue($mailbox, $mock_imap);
        
        $result = $mailbox->search('INBOX', 'ALL', [], 'date', true);
        
        $this->assertEquals([1, 2, 3], $result);
    }

    /**
     * Test search without sort support
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_search_without_sort_support() {
        $this->loadRequiredFiles();
        
        $config = ['type' => 'imap'];
        $mailbox = new Hm_Mailbox($this->server_id, $this->mock_user_config, $this->mock_session, $config);
        
        $mock_imap = new Hm_Mock_IMAP();
        $mock_imap->state = 'authenticated';
        $mock_imap->select_mailbox_result = true;
        $mock_imap->sort_support = false;
        $mock_imap->search_result = [1, 2, 3];
        
        $reflection = new ReflectionClass($mailbox);
        $connection_property = $reflection->getProperty('connection');
        $connection_property->setAccessible(true);
        $connection_property->setValue($mailbox, $mock_imap);
        
        $result = $mailbox->search('INBOX', 'ALL', [], 'date', false);
        
        $this->assertEquals([1, 2, 3], $result);
    }

    /**
     * Test JMAP search functionality
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_jmap_search_functionality() {
        $this->loadRequiredFiles();
        
        $config = ['type' => 'jmap'];
        $mailbox = new Hm_Mailbox($this->server_id, $this->mock_user_config, $this->mock_session, $config);
        
        $mock_jmap = new Hm_Mock_JMAP();
        $mock_jmap->state = 'authenticated';
        $mock_jmap->select_mailbox_result = true;
        $mock_jmap->sort_support = true;
        $mock_jmap->search_result = [4, 5, 6];
        
        $reflection = new ReflectionClass($mailbox);
        $connection_property = $reflection->getProperty('connection');
        $connection_property->setAccessible(true);
        $connection_property->setValue($mailbox, $mock_jmap);
        
        $result = $mailbox->search('INBOX', 'ALL', [], 'date', true);
        
        $this->assertEquals([4, 5, 6], $result);
    }

    /**
     * Test EWS search functionality
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_ews_search_functionality() {
        $this->loadRequiredFiles();
        
        $config = ['type' => 'ews'];
        $mailbox = new Hm_Mailbox($this->server_id, $this->mock_user_config, $this->mock_session, $config);
        
        $mock_ews = new Hm_Mock_EWS();
        $mock_ews->authed_result = true;
        $mock_ews->select_mailbox_result = true;
        $mock_ews->sort_support = true;
        $mock_ews->search_result = [7, 8, 9];
        
        $reflection = new ReflectionClass($mailbox);
        $connection_property = $reflection->getProperty('connection');
        $connection_property->setAccessible(true);
        $connection_property->setValue($mailbox, $mock_ews);
        
        $result = $mailbox->search('INBOX', 'ALL', [], 'date', true);
        
        $this->assertEquals([7, 8, 9], $result);
    }

    /**
     * Test JMAP search without sort support
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_jmap_search_without_sort_support() {
        $this->loadRequiredFiles();
        
        $config = ['type' => 'jmap'];
        $mailbox = new Hm_Mailbox($this->server_id, $this->mock_user_config, $this->mock_session, $config);
        
        $mock_jmap = new Hm_Mock_JMAP();
        $mock_jmap->state = 'authenticated';
        $mock_jmap->select_mailbox_result = true;
        $mock_jmap->sort_support = false;
        $mock_jmap->search_result = [10, 11, 12];
        
        $reflection = new ReflectionClass($mailbox);
        $connection_property = $reflection->getProperty('connection');
        $connection_property->setAccessible(true);
        $connection_property->setValue($mailbox, $mock_jmap);
        
        $result = $mailbox->search('INBOX', 'ALL', [], 'date', false);
        
        $this->assertEquals([10, 11, 12], $result);
    }

    /**
     * Test EWS search without sort support
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_ews_search_without_sort_support() {
        $this->loadRequiredFiles();
        
        $config = ['type' => 'ews'];
        $mailbox = new Hm_Mailbox($this->server_id, $this->mock_user_config, $this->mock_session, $config);
        
        $mock_ews = new Hm_Mock_EWS();
        $mock_ews->authed_result = true;
        $mock_ews->select_mailbox_result = true;
        $mock_ews->sort_support = false;
        $mock_ews->search_result = [13, 14, 15];
        
        $reflection = new ReflectionClass($mailbox);
        $connection_property = $reflection->getProperty('connection');
        $connection_property->setAccessible(true);
        $connection_property->setValue($mailbox, $mock_ews);
        
        $result = $mailbox->search('INBOX', 'ALL', [], 'date', false);
        
        $this->assertEquals([13, 14, 15], $result);
    }

    /**
     * Test JMAP search when folder selection fails
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_jmap_search_folder_selection_fails() {
        $this->loadRequiredFiles();
        
        $config = ['type' => 'jmap'];
        $mailbox = new Hm_Mailbox($this->server_id, $this->mock_user_config, $this->mock_session, $config);
        
        $mock_jmap = new Hm_Mock_JMAP();
        $mock_jmap->state = 'authenticated';
        $mock_jmap->select_mailbox_result = false; // Simulate failure
        
        $reflection = new ReflectionClass($mailbox);
        $connection_property = $reflection->getProperty('connection');
        $connection_property->setAccessible(true);
        $connection_property->setValue($mailbox, $mock_jmap);
        
        $result = $mailbox->search('NonExistent', 'ALL', [], 'date', true);
        
        $this->assertEquals([], $result);
    }

    /**
     * Test EWS search when folder selection fails
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_ews_search_folder_selection_fails() {
        $this->loadRequiredFiles();
        
        $config = ['type' => 'ews'];
        $mailbox = new Hm_Mailbox($this->server_id, $this->mock_user_config, $this->mock_session, $config);
        
        $mock_ews = new Hm_Mock_EWS();
        $mock_ews->authed_result = true;
        $mock_ews->select_mailbox_result = false; // Simulate failure
        
        $reflection = new ReflectionClass($mailbox);
        $connection_property = $reflection->getProperty('connection');
        $connection_property->setAccessible(true);
        $connection_property->setValue($mailbox, $mock_ews);
        
        $result = $mailbox->search('NonExistent', 'ALL', [], 'date', true);
        
        $this->assertEquals([], $result);
    }

    /**
     * Test message deletion with trash folder
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_delete_message_with_trash() {
        $this->loadRequiredFiles();
        
        $config = ['type' => 'imap'];
        $mailbox = new Hm_Mailbox($this->server_id, $this->mock_user_config, $this->mock_session, $config);
        
        $mock_imap = new Hm_Mock_IMAP();
        $mock_imap->state = 'authenticated';
        $mock_imap->select_mailbox_result = true;
        
        $reflection = new ReflectionClass($mailbox);
        $connection_property = $reflection->getProperty('connection');
        $connection_property->setAccessible(true);
        $connection_property->setValue($mailbox, $mock_imap);
        
        $this->assertTrue($mailbox->delete_message('INBOX', 123, 'Trash'));
    }

    /**
     * Test message deletion without trash folder
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_delete_message_without_trash() {
        $this->loadRequiredFiles();
        
        $config = ['type' => 'imap'];
        $mailbox = new Hm_Mailbox($this->server_id, $this->mock_user_config, $this->mock_session, $config);
        
        $mock_imap = new Hm_Mock_IMAP();
        $mock_imap->state = 'authenticated';
        $mock_imap->select_mailbox_result = true;
        
        $reflection = new ReflectionClass($mailbox);
        $connection_property = $reflection->getProperty('connection');
        $connection_property->setAccessible(true);
        $connection_property->setValue($mailbox, $mock_imap);
        
        $this->assertTrue($mailbox->delete_message('INBOX', 123, null));
    }

    /**
     * Test SMTP message sending
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_smtp_send_message() {
        $this->loadRequiredFiles();
        
        $config = [
            'type' => 'smtp',
            'username' => 'testuser',
            'password' => 'testpass'
        ];
        $mailbox = new Hm_Mailbox($this->server_id, $this->mock_user_config, $this->mock_session, $config);
        
        $mock_smtp = new Hm_Mock_SMTP();
        
        $reflection = new ReflectionClass($mailbox);
        $connection_property = $reflection->getProperty('connection');
        $connection_property->setAccessible(true);
        $connection_property->setValue($mailbox, $mock_smtp);
        
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
        $this->loadRequiredFiles();
        
        $config = [
            'type' => 'smtp',
            'username' => 'testuser',
            'password' => 'testpass'
        ];
        $mailbox = new Hm_Mailbox($this->server_id, $this->mock_user_config, $this->mock_session, $config);
        
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
        
        $reflection = new ReflectionClass($mailbox);
        $connection_property = $reflection->getProperty('connection');
        $connection_property->setAccessible(true);
        $connection_property->setValue($mailbox, $mock_smtp);
        
        $this->assertTrue($mailbox->send_message(
            'test@example.com',
            ['recipient@example.com'],
            'Test message',
            true // delivery receipt
        ));
    }
}
