<?php

use PHPUnit\Framework\TestCase;

class Hm_Test_Mock_Mailbox_Not_Junk {
    /** @var array<int, array{0: string, 1: string, 2: array, 3: string|false}> */
    public $moves = array();

    /** @var array<int, array{0: string, 1: string, 2: array, 3: mixed, 4: mixed}> */
    public $not_junk_flags = array();

    /** @var bool */
    public $imap = true;

    /** @var bool */
    public $not_junk_succeeds = true;

    public function is_imap() {
        return $this->imap;
    }

    public function message_action($folder, $action, $uids, $dest = false, $keyword = false) {
        if ($action === 'NOT_JUNK') {
            $this->not_junk_flags[] = array($folder, $action, $uids, $dest, $keyword);
            return array('status' => $this->not_junk_succeeds, 'responses' => array());
        }
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
 * Tests for not_junk on Hm_Handler_imap_message_action
 */
class Hm_Test_Imap_Message_Action_Not_Junk extends TestCase {

    public function setUp(): void {
        require_once APP_PATH.'tests/phpunit/helpers.php';
        require_once APP_PATH.'modules/imap/functions.php';
        require_once APP_PATH.'modules/imap/handler_modules.php';
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
        $mb = new Hm_Test_Mock_Mailbox_Not_Junk();
        $junk_hex = bin2hex('Spam');
        $uids = array('42');
        $specials = array();
        $server_details = array('id' => 'srv', 'name' => 'Test');
        $result = $pm->invoke($handler, $mb, 'not_junk', $uids, $junk_hex, $specials, $server_details);
        $this->assertFalse($result['error']);
        $this->assertCount(1, $mb->not_junk_flags);
        $this->assertSame('Spam', $mb->not_junk_flags[0][0]);
        $this->assertSame('NOT_JUNK', $mb->not_junk_flags[0][1]);
        $this->assertSame(array('42'), $mb->not_junk_flags[0][2]);
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
        $mb = new Hm_Test_Mock_Mailbox_Not_Junk();
        $junk_hex = bin2hex('Spam');
        $specials = array('inbox' => 'Bulk');
        $server_details = array('id' => 'srv', 'name' => 'Test');
        $result = $pm->invoke($handler, $mb, 'not_junk', array('7'), $junk_hex, $specials, $server_details);
        $this->assertFalse($result['error']);
        $this->assertCount(1, $mb->not_junk_flags);
        $this->assertSame('Bulk', $mb->moves[0][3]);
    }

    /**
     * NOT_JUNK is attempted before MOVE (source-folder UIDs).
     *
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_perform_action_not_junk_clears_junk_flag_before_move() {
        $parent = build_parent_mock();
        $parent->user_config->set('original_folder_setting', false);
        $handler = new Hm_Handler_imap_message_action($parent, 'home');
        $pm = new ReflectionMethod($handler, 'perform_action');
        $pm->setAccessible(true);
        $mb = new Hm_Test_Mock_Mailbox_Not_Junk();
        $junk_hex = bin2hex('Spam');
        $server_details = array('id' => 'srv', 'name' => 'Test');
        $pm->invoke($handler, $mb, 'not_junk', array('9'), $junk_hex, array(), $server_details);
        $this->assertNotEmpty($mb->not_junk_flags);
        $this->assertNotEmpty($mb->moves);
        $this->assertSame('NOT_JUNK', $mb->not_junk_flags[0][1]);
        $this->assertSame('MOVE', $mb->moves[0][1]);
    }

    /**
     * Fail-soft: MOVE still runs when -FLAGS (\Junk) fails.
     *
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_perform_action_not_junk_move_succeeds_when_clear_junk_flag_fails() {
        $parent = build_parent_mock();
        $parent->user_config->set('original_folder_setting', false);
        $handler = new Hm_Handler_imap_message_action($parent, 'home');
        $pm = new ReflectionMethod($handler, 'perform_action');
        $pm->setAccessible(true);
        $mb = new Hm_Test_Mock_Mailbox_Not_Junk();
        $mb->not_junk_succeeds = false;
        $junk_hex = bin2hex('Spam');
        $server_details = array('id' => 'srv', 'name' => 'Test');
        $result = $pm->invoke($handler, $mb, 'not_junk', array('3'), $junk_hex, array(), $server_details);
        $this->assertFalse($result['error']);
        $this->assertCount(1, $mb->not_junk_flags);
        $this->assertCount(1, $mb->moves);
    }

    /**
     * NOT_JUNK is skipped for non-IMAP mailboxes.
     *
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_perform_action_not_junk_skips_flag_clear_for_non_imap() {
        $parent = build_parent_mock();
        $parent->user_config->set('original_folder_setting', false);
        $handler = new Hm_Handler_imap_message_action($parent, 'home');
        $pm = new ReflectionMethod($handler, 'perform_action');
        $pm->setAccessible(true);
        $mb = new Hm_Test_Mock_Mailbox_Not_Junk();
        $mb->imap = false;
        $junk_hex = bin2hex('Spam');
        $server_details = array('id' => 'srv', 'name' => 'Test');
        $result = $pm->invoke($handler, $mb, 'not_junk', array('5'), $junk_hex, array(), $server_details);
        $this->assertFalse($result['error']);
        $this->assertCount(0, $mb->not_junk_flags);
        $this->assertCount(1, $mb->moves);
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
