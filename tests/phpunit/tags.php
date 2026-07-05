<?php

use PHPUnit\Framework\TestCase;

/**
 * tests for Hm_Test_Tags
 */
class Hm_Test_Tags extends TestCase {

    public function setUp(): void {
        require __DIR__.'/helpers.php';
        require_once APP_PATH.'modules/tags/hm-tags.php';

        $hmod = new stdClass();
        $hmod->user_config = new Hm_Mock_Config();
        $hmod->session = new Hm_Mock_Session();
        Hm_Tags::init($hmod);
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_get() {
        $tags = Hm_Tags_Wrapper::getAll();
        $this->assertEquals($tags, array());
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_crud() {
        $tag = array(
            'name' => html_entity_decode('Cypht', ENT_QUOTES),
            'parent' => null
        );
        $tag_id = Hm_Tags_Wrapper::add($tag);
        $this->assertNotEmpty($tag_id);

        $tag = Hm_Tags_Wrapper::get($tag_id);
        $this->assertEquals($tag['name'], 'Cypht');

        $tag_with_parent = array(
            'name' => html_entity_decode('Child', ENT_QUOTES),
            'parent' => $tag_id
        );
        $child_id = Hm_Tags_Wrapper::add($tag_with_parent);
        $child = Hm_Tags_Wrapper::get($child_id);
        $this->assertEquals($tag['id'], $child['parent']);

        $child['name'] = 'Child 2';
        Hm_Tags_Wrapper::edit($child_id, $child);
        $child = Hm_Tags_Wrapper::get($child_id);
        $this->assertEquals($child['name'], 'Child 2');

        $this->assertCount(2, Hm_Tags_Wrapper::getAll());

        Hm_Tags_Wrapper::del($child_id);
        $this->assertEmpty(Hm_Tags_Wrapper::get($child_id));

        $this->assertCount(1, Hm_Tags_Wrapper::getAll());
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_add_message_registers_folder_and_message() {
        $tag_id = Hm_Tags::add(array('name' => 'Work'));
        $this->assertTrue(Hm_Tags::addMessage($tag_id, 'srv1', 'INBOX', '101'));
        $this->assertEquals(array('INBOX' => array('101')), Hm_Tags::getFolders($tag_id, 'srv1'));
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_add_message_does_not_duplicate_existing_message() {
        $tag_id = Hm_Tags::add(array('name' => 'Work'));
        Hm_Tags::addMessage($tag_id, 'srv1', 'INBOX', '101');
        Hm_Tags::addMessage($tag_id, 'srv1', 'INBOX', '101');
        $this->assertEquals(array('101'), Hm_Tags::getFolders($tag_id, 'srv1')['INBOX']);
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_get_folders_returns_empty_array_for_unknown_server() {
        $tag_id = Hm_Tags::add(array('name' => 'Work'));
        $this->assertEquals(array(), Hm_Tags::getFolders($tag_id, 'unknown_server'));
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_remove_message_untags_message_from_folder() {
        $tag_id = Hm_Tags::add(array('name' => 'Work'));
        Hm_Tags::addMessage($tag_id, 'srv1', 'INBOX', '101');
        Hm_Tags::addMessage($tag_id, 'srv1', 'INBOX', '102');
        $this->assertTrue(Hm_Tags::removeMessage('101', $tag_id));
        $this->assertEquals(array('102'), array_values(Hm_Tags::getFolders($tag_id, 'srv1')['INBOX']));
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_remove_message_missing_tag_returns_false() {
        $this->assertFalse(Hm_Tags::removeMessage('101', 'no_such_tag'));
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_get_tag_ids_with_message() {
        $tag_id = Hm_Tags::add(array('name' => 'Work'));
        Hm_Tags::addMessage($tag_id, 'srv1', 'INBOX', '101');
        $this->assertEquals(array($tag_id), Hm_Tags::getTagIdsWithMessage('101'));
        $this->assertEquals(array(), Hm_Tags::getTagIdsWithMessage('999'));
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_move_message_to_different_folder_same_server() {
        $tag_id = Hm_Tags::add(array('name' => 'Work'));
        Hm_Tags::addMessage($tag_id, 'srv1', 'INBOX', '101');
        Hm_Tags::moveMessageToADifferentFolder(array(
            'oldId' => '101', 'newId' => '202', 'oldFolder' => 'INBOX',
            'newFolder' => 'Archive', 'oldServer' => 'srv1',
        ));
        $folders = Hm_Tags::getFolders($tag_id, 'srv1');
        $this->assertEquals(array(), $folders['INBOX']);
        $this->assertEquals(array('202'), $folders['Archive']);
    }

    /**
     * Documents the current cross-server move behavior: the message is
     * registered under the new server/folder, but the old server's folder
     * entry is left untouched (moveMessageToADifferentFolder only rewrites
     * $tag['server'][$oldServer] when there is no $newServer).
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_move_message_to_different_server_leaves_old_folder_entry_untouched() {
        $tag_id = Hm_Tags::add(array('name' => 'Work'));
        Hm_Tags::addMessage($tag_id, 'srv1', 'INBOX', '101');
        Hm_Tags::moveMessageToADifferentFolder(array(
            'oldId' => '101', 'newId' => '202', 'oldFolder' => 'INBOX',
            'newFolder' => 'INBOX', 'oldServer' => 'srv1', 'newServer' => 'srv2',
        ));
        $this->assertEquals(array('101'), Hm_Tags::getFolders($tag_id, 'srv1')['INBOX']);
        $this->assertEquals(array('202'), Hm_Tags::getFolders($tag_id, 'srv2')['INBOX']);
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_color_palette_is_a_list_of_hex_colors() {
        $palette = Hm_Tags::colorPalette();
        $this->assertNotEmpty($palette);
        foreach ($palette as $color) {
            $this->assertMatchesRegularExpression('/^#[0-9a-fA-F]{6}$/', $color);
        }
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_sanitize_color_accepts_palette_values() {
        $color = Hm_Tags::colorPalette()[0];
        $this->assertEquals($color, Hm_Tags::sanitizeColor($color));
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_sanitize_color_rejects_values_outside_the_palette() {
        $this->assertEquals(Hm_Tags::defaultColor(), Hm_Tags::sanitizeColor('javascript:alert(1)'));
        $this->assertEquals(Hm_Tags::defaultColor(), Hm_Tags::sanitizeColor(null));
        $this->assertEquals(Hm_Tags::defaultColor(), Hm_Tags::sanitizeColor('#123456'));
    }
}
