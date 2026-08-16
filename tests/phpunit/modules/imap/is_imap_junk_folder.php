<?php

use PHPUnit\Framework\TestCase;

class Hm_Test_Is_Imap_Junk_Folder extends TestCase {

    public function setUp(): void {
        require_once APP_PATH.'tests/phpunit/helpers.php';
        require_once APP_PATH.'modules/imap/functions.php';
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_is_imap_junk_folder_matches_configured_junk_mailbox() {
        Hm_IMAP_List::add(array('user' => 'testuser', 'nopass' => 1, 'name' => 'test', 'server' => 'test', 'port' => 0, 'tls' => 1, 'id' => 'srv1'));
        $parent = build_parent_mock();
        $parent->user_config->set('special_imap_folders', array(
            'srv1' => array('junk' => 'Spam', 'trash' => 'Trash', 'inbox' => 'INBOX'),
        ));
        $handler = new Hm_Handler_Test($parent, 'home');
        $this->assertTrue(is_imap_junk_folder($handler, 'srv1', 'Spam'));
        $this->assertFalse(is_imap_junk_folder($handler, 'srv1', 'INBOX'));
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_is_imap_junk_folder_false_when_junk_not_set() {
        Hm_IMAP_List::add(array('user' => 'u2', 'nopass' => 1, 'name' => 'n2', 'server' => 's2', 'port' => 0, 'tls' => 1, 'id' => 'srv2'));
        $parent = build_parent_mock();
        $parent->user_config->set('special_imap_folders', array(
            'srv2' => array('trash' => 'Trash'),
        ));
        $handler = new Hm_Handler_Test($parent, 'home');
        $this->assertFalse(is_imap_junk_folder($handler, 'srv2', 'Spam'));
    }
}
