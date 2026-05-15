<?php

use PHPUnit\Framework\TestCase;

class Hm_Test_Mock_Mailbox_NotJunk {
    /** @var array<int, array{0: string, 1: string, 2: array, 3: string|false}> */
    public $moves = array();

    public function message_action($folder, $action, $uids, $dest = false) {
        if ($action === 'MOVE') {
            $this->moves[] = array($folder, $action, $uids, $dest);
            return array('status' => true, 'responses' => array());
        }
        return array('status' => false, 'responses' => array());
    }

    public function get_folder_status($f) {
        return array(1);
    }

    public function create_folder($f) {
        return true;
    }
}

/**
 * Backend tests for not_junk / inbox resolution on Hm_Handler_imap_message_action
 */
class HandlerImapNotJunkTest extends TestCase {

    public function setUp(): void {
        require_once APP_PATH.'tests/phpunit/helpers.php';
        require_once APP_PATH.'modules/imap/functions.php';
        require_once APP_PATH.'modules/imap/handler_modules.php';
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_get_special_folder_not_junk_reads_configured_inbox() {
        $parent = build_parent_mock();
        $handler = new Hm_Handler_imap_message_action($parent, 'home');
        $rm = new ReflectionMethod($handler, 'get_special_folder');
        $rm->setAccessible(true);
        $specials = array('inbox' => 'BulkInbox');
        $server_details = array('id' => 's1', 'name' => 'Srv');
        $this->assertSame('BulkInbox', $rm->invoke($handler, 'not_junk', $specials, $server_details));
        $this->assertSame('BulkInbox', $rm->invoke($handler, 'restore', $specials, $server_details));
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_get_special_folder_not_junk_missing_inbox_key() {
        $parent = build_parent_mock();
        $handler = new Hm_Handler_imap_message_action($parent, 'home');
        $rm = new ReflectionMethod($handler, 'get_special_folder');
        $rm->setAccessible(true);
        $specials = array('junk' => 'Spam');
        $server_details = array('id' => 's1', 'name' => 'Srv');
        $this->assertFalse($rm->invoke($handler, 'not_junk', $specials, $server_details));
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_get_special_folder_not_junk_non_array_specials() {
        $parent = build_parent_mock();
        $handler = new Hm_Handler_imap_message_action($parent, 'home');
        $rm = new ReflectionMethod($handler, 'get_special_folder');
        $rm->setAccessible(true);
        $server_details = array('id' => 's1', 'name' => 'Srv');
        $this->assertFalse($rm->invoke($handler, 'not_junk', 'not-an-array', $server_details));
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_perform_action_not_junk_moves_to_inbox_when_inbox_not_configured() {
        $parent = build_parent_mock();
        $parent->user_config->set('original_folder_setting', false);
        $handler = new Hm_Handler_imap_message_action($parent, 'home');
        $pm = new ReflectionMethod($handler, 'perform_action');
        $pm->setAccessible(true);
        $mb = new Hm_Test_Mock_Mailbox_NotJunk();
        $junk_hex = bin2hex('Spam');
        $uids = array('42');
        $specials = array();
        $server_details = array('id' => 'srv', 'name' => 'Test');
        $result = $pm->invoke($handler, $mb, 'not_junk', $uids, $junk_hex, $specials, $server_details);
        $this->assertFalse($result['error']);
        $this->assertCount(1, $mb->moves);
        $this->assertSame('Spam', $mb->moves[0][0]);
        $this->assertSame('MOVE', $mb->moves[0][1]);
        $this->assertSame(array('42'), $mb->moves[0][2]);
        $this->assertSame('INBOX', $mb->moves[0][3]);
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_perform_action_not_junk_moves_to_configured_inbox() {
        $parent = build_parent_mock();
        $parent->user_config->set('original_folder_setting', false);
        $handler = new Hm_Handler_imap_message_action($parent, 'home');
        $pm = new ReflectionMethod($handler, 'perform_action');
        $pm->setAccessible(true);
        $mb = new Hm_Test_Mock_Mailbox_NotJunk();
        $junk_hex = bin2hex('Spam');
        $specials = array('inbox' => 'Bulk');
        $server_details = array('id' => 'srv', 'name' => 'Test');
        $result = $pm->invoke($handler, $mb, 'not_junk', array('7'), $junk_hex, $specials, $server_details);
        $this->assertFalse($result['error']);
        $this->assertSame('Bulk', $mb->moves[0][3]);
    }

    /**
     * not_junk must remain in the ajax_message_action whitelist (regression guard).
     *
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_not_junk_action_type_registered_in_process() {
        $src = file_get_contents(APP_PATH.'modules/imap/handler_modules.php');
        $this->assertStringContainsString(
            "array('delete', 'read', 'unread', 'flag', 'unflag', 'archive', 'junk', 'restore', 'not_junk')",
            $src,
            'not_junk should be listed in Hm_Handler_imap_message_action::process allowed action types'
        );
    }
}
