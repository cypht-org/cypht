<?php

use PHPUnit\Framework\TestCase;

/**
 * Reproduction tests for cross-account tag corruption.
 *
 * Tags store messages scoped per server and per folder:
 *
 *     $tag['server'][$serverId][$folder][] = $uid
 *
 * Hm_Tags::addMessage() honours that scoping. Hm_Tags::removeMessage() and
 * Hm_Tags::getTagIdsWithMessage() do not -- they walk every server and every
 * folder and match on the message id alone. IMAP UIDs are only unique within a
 * single mailbox, so two accounts (or two folders of one account) routinely
 * hold different messages under the same UID. Tagging across accounts is the
 * whole point of the Tags module, which makes the collision the common case
 * rather than a corner case.
 *
 * Every test below asserts the behaviour the scoped storage layout implies.
 * They are expected to fail on v2.12.0 / v2.12.1.
 */
class Hm_Test_Tags_Multi_Account extends TestCase {

    public function setUp(): void {
        require __DIR__.'/helpers.php';
        require_once APP_PATH.'modules/tags/hm-tags.php';
        require_once APP_PATH.'modules/tags/handler_modules.php';

        $hmod = new stdClass();
        $hmod->user_config = new Hm_Mock_Config();
        $hmod->session = new Hm_Mock_Session();
        Hm_Tags::init($hmod);
    }

    private function uids($tag_id, $server_id, $folder) {
        $folders = Hm_Tags::getFolders($tag_id, $server_id);
        if (!isset($folders[$folder])) {
            return array();
        }
        return array_values($folders[$folder]);
    }

    /**
     * Runs the untag handler the way the UI does, with a list_path of
     * "{server id}_{uid}_{hex folder}". The handler is the layer that owns the
     * scope, so this is where the cross-account cases have to be asserted:
     * Hm_Tags::removeMessage() cannot infer a mailbox it was never given.
     */
    private function run_untag_handler($tag_id, $server_id, $folder, $uid, callable $seed) {
        $test = new Handler_Test('remove_tag_from_message', 'tags');
        $test->post = array(
            'tag_id' => $tag_id,
            'list_path' => $server_id.'_'.$uid.'_'.bin2hex($folder),
            'untag' => true,
        );
        $test->prep();
        $hmod = new stdClass();
        $hmod->user_config = $test->module_exec->user_config;
        $hmod->session = $test->ses_obj;
        Hm_Tags::init($hmod);

        Hm_Tags::add(array('id' => $tag_id, 'name' => 'Shopping'));
        $seed();

        return $test->run_only();
    }

    /**
     * Control: the single-server case upstream already covers. If this one
     * fails, the harness is broken and the rest of the file means nothing.
     *
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_control_single_server_untag_still_works() {
        $tag_id = Hm_Tags::add(array('name' => 'Shopping'));
        Hm_Tags::addMessage($tag_id, 'srv1', 'INBOX', '101');
        Hm_Tags::addMessage($tag_id, 'srv1', 'INBOX', '102');

        Hm_Tags::removeMessage('101', $tag_id);

        $this->assertEquals(array('102'), $this->uids($tag_id, 'srv1', 'INBOX'));
    }

    /**
     * Two Gmail accounts, same tag, colliding UID. Untagging the message on
     * server 0 must leave the unrelated message on server 1 tagged.
     *
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_untag_does_not_remove_same_uid_on_another_server() {
        $this->run_untag_handler('tag1', '0', 'INBOX', '101', function() {
            Hm_Tags::addMessage('tag1', '0', 'INBOX', '101');
            Hm_Tags::addMessage('tag1', '1', 'INBOX', '101');
        });

        $this->assertEquals(
            array('101'),
            $this->uids('tag1', '1', 'INBOX'),
            'untagging UID 101 on server 0 also dropped the unrelated UID 101 on server 1'
        );
    }

    /**
     * Same collision inside one account. Gmail exposes labels as IMAP folders,
     * and UIDs restart per folder, so INBOX/101 and Archive/101 are different
     * messages of the same account. One account is enough to hit this.
     *
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_untag_does_not_remove_same_uid_in_another_folder() {
        $this->run_untag_handler('tag1', '0', 'INBOX', '101', function() {
            Hm_Tags::addMessage('tag1', '0', 'INBOX', '101');
            Hm_Tags::addMessage('tag1', '0', 'Archive', '101');
        });

        $this->assertEquals(
            array('101'),
            $this->uids('tag1', '0', 'Archive'),
            'untagging INBOX/101 also dropped the unrelated Archive/101'
        );
    }

    /**
     * The scale of the damage: with five accounts holding the same UID under
     * one tag, a single untag must not empty the tag on all of them.
     *
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_untag_on_one_account_does_not_empty_the_tag_everywhere() {
        $servers = array('0', '1', '2', '3', '4');
        $this->run_untag_handler('tag1', '0', 'INBOX', '101', function() use ($servers) {
            foreach ($servers as $server) {
                Hm_Tags::addMessage('tag1', $server, 'INBOX', '101');
            }
        });

        $survivors = 0;
        foreach ($servers as $server) {
            $survivors += count($this->uids('tag1', $server, 'INBOX'));
        }
        $this->assertEquals(
            4,
            $survivors,
            'one untag should remove one message, but it cleared every account'
        );
    }

    /**
     * The scoped library call, exercised directly. Callers that know the
     * mailbox must be able to say so.
     *
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_scoped_remove_message_only_touches_that_mailbox() {
        $tag_id = Hm_Tags::add(array('name' => 'Shopping'));
        Hm_Tags::addMessage($tag_id, 'srv1', 'INBOX', '101');
        Hm_Tags::addMessage($tag_id, 'srv1', 'Archive', '101');
        Hm_Tags::addMessage($tag_id, 'srv2', 'INBOX', '101');

        Hm_Tags::removeMessage('101', $tag_id, 'srv1', 'INBOX');

        $this->assertEquals(array(), $this->uids($tag_id, 'srv1', 'INBOX'));
        $this->assertEquals(array('101'), $this->uids($tag_id, 'srv1', 'Archive'));
        $this->assertEquals(array('101'), $this->uids($tag_id, 'srv2', 'INBOX'));
    }

    /**
     * Characterisation, not an endorsement: with no scope the function still
     * cannot know which mailbox was meant, so it clears every match. Every
     * production call site passes a scope; this test exists so that the
     * ambiguity of the unscoped path stays visible instead of being
     * rediscovered as a bug.
     *
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_unscoped_remove_message_is_still_ambiguous_by_design() {
        $tag_id = Hm_Tags::add(array('name' => 'Shopping'));
        Hm_Tags::addMessage($tag_id, 'srv1', 'INBOX', '101');
        Hm_Tags::addMessage($tag_id, 'srv2', 'INBOX', '101');

        Hm_Tags::removeMessage('101', $tag_id);

        $this->assertEquals(array(), $this->uids($tag_id, 'srv1', 'INBOX'));
        $this->assertEquals(array(), $this->uids($tag_id, 'srv2', 'INBOX'));
    }

    /**
     * The handler already parses the server id and the folder out of
     * list_path ("{server}_{uid}_{hex folder}") and then drops both before
     * calling Hm_Tags::removeMessage(). This is the same defect one layer up,
     * and it shows the scoping information is available at the call site.
     *
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_remove_tag_handler_only_untags_the_requested_server() {
        $folder = bin2hex('INBOX');
        $test = new Handler_Test('remove_tag_from_message', 'tags');
        $test->post = array(
            'tag_id' => 'tag1',
            'list_path' => "0_101_$folder",
            'untag' => true,
        );
        $test->prep();
        $hmod = new stdClass();
        $hmod->user_config = $test->module_exec->user_config;
        $hmod->session = $test->ses_obj;
        Hm_Tags::init($hmod);

        Hm_Tags::add(array('id' => 'tag1', 'name' => 'Shopping'));
        Hm_Tags::addMessage('tag1', '0', 'INBOX', '101');
        Hm_Tags::addMessage('tag1', '1', 'INBOX', '101');

        $test->run_only();

        $this->assertEquals(
            array('101'),
            $this->uids('tag1', '1', 'INBOX'),
            'handler untagged server 0 but also cleared server 1'
        );
    }

    /**
     * Inverse defect. getTagIdsWithMessage() matches on the message id across
     * every server, so moving a message on srv1 reaches into a tag that only
     * ever held the colliding UID on srv2 and grafts the new UID onto it.
     *
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_move_does_not_tag_an_unrelated_message_on_another_server() {
        $tag_id = Hm_Tags::add(array('name' => 'Finance'));
        Hm_Tags::addMessage($tag_id, 'srv2', 'INBOX', '101');

        @Hm_Tags::moveMessageToADifferentFolder(array(
            'oldId' => '101',
            'newId' => '202',
            'oldFolder' => 'INBOX',
            'newFolder' => 'Archive',
            'oldServer' => 'srv1',
        ));

        $this->assertEquals(
            array(),
            Hm_Tags::getFolders($tag_id, 'srv1'),
            'moving a message on srv1 added it to a tag that only covered srv2'
        );
    }

    /**
     * The srv2 entry must also survive that move untouched.
     *
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_move_leaves_the_original_server_entry_intact() {
        $tag_id = Hm_Tags::add(array('name' => 'Finance'));
        Hm_Tags::addMessage($tag_id, 'srv2', 'INBOX', '101');

        @Hm_Tags::moveMessageToADifferentFolder(array(
            'oldId' => '101',
            'newId' => '202',
            'oldFolder' => 'INBOX',
            'newFolder' => 'Archive',
            'oldServer' => 'srv1',
        ));

        $this->assertEquals(array('101'), $this->uids($tag_id, 'srv2', 'INBOX'));
    }
}
