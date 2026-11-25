<?php

use PHPUnit\Framework\TestCase;

class Hm_Test_User_Config_Shared_State extends TestCase {
    private Hm_User_Config_Shared_Store $store;
    private $database;

    /** @return void */
    protected function setUp(): void {
        $this->database = new PDO('sqlite::memory:');
        $this->database->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->database->exec('CREATE TABLE hm_user_shared_state (user_key TEXT PRIMARY KEY, revision INTEGER NOT NULL, payload BLOB NOT NULL)');
        $this->store = new Hm_User_Config_Shared_DB_Store($this->database);
    }

    /** @return void */
    public function test_first_open_initializes_state_from_permanent_settings() {
        $this->assertNull($this->store->read('alice'));

        $state = new Hm_User_Config_Shared_State($this->store, 'alice');
        $expected = ['config' => ['theme' => 'dark'], 'changed_settings' => []];
        $this->assertSame($expected, $state->open('password', $expected['config']));
        $this->assertSame(1, $this->store->read('alice')['revision']);

        $next = new Hm_User_Config_Shared_State($this->store, 'alice');
        $this->assertSame($expected, $next->open('password', []));
    }

    /** @return void */
    public function test_invalid_existing_record_is_not_replaced() {
        $this->assertTrue($this->store->compare_and_swap('alice', null, 'invalid json'));

        $state = new Hm_User_Config_Shared_State($this->store, 'alice');
        try {
            $state->open('password', []);
            $this->fail('Invalid shared state should not be replaced');
        } catch (RuntimeException $exception) {
            $this->assertSame('Invalid encrypted shared state', $exception->getMessage());
        }

        $this->assertSame(['revision' => 1, 'payload' => 'invalid json'], $this->store->read('alice'));
    }

    /** @return void */
    public function test_sessions_share_and_merge_concurrent_changes() {
        $first = new Hm_User_Config_Shared_State($this->store, 'alice');
        $initial = ['preferences' => ['a' => 1, 'b' => 1], 'servers' => []];

        $this->assertSame(['config' => $initial, 'changed_settings' => []], $first->open('password', $initial));

        $second = new Hm_User_Config_Shared_State($this->store, 'alice');
        $second->open('password', []);

        $first->update(['config' => ['preferences' => ['a' => 2, 'b' => 1], 'servers' => []], 'changed_settings' => ['first']]);

        $merged = $second->update(['config' => ['preferences' => ['a' => 1, 'b' => 2], 'servers' => []], 'changed_settings' => ['second']]);

        $this->assertSame(['a' => 2, 'b' => 2], $merged['config']['preferences']);
        $this->assertSame(['first', 'second'], $merged['changed_settings']);

        $third = new Hm_User_Config_Shared_State($this->store, 'alice');
        $this->assertSame($merged, $third->resume($first->credentials()));

        // Payload should not contain plaintext data
        $this->assertStringNotContainsString('preferences', $this->store->read('alice')['payload']);
    }

    /** @return void */
    public function test_replacement_discards_inaccessible_state_and_rejects_old_sessions() {
        $old = new Hm_User_Config_Shared_State($this->store, 'alice');
        $old->open('old', ['private' => 'unsaved']);
        $old_record = json_decode($this->store->read('alice')['payload'], true);

        $replacement = new Hm_User_Config_Shared_State($this->store, 'alice');
        $this->assertSame(['config' => ['saved' => true], 'changed_settings' => []], $replacement->open('new', ['saved' => true]));
        $new_record = json_decode($this->store->read('alice')['payload'], true);

        $this->assertNotSame($old_record['data'], $new_record['data']);

        $this->expectExceptionMessage('sign in again');
        $old->update(['config' => ['private' => 'stale'], 'changed_settings' => []]);
    }

    /** @return void */
    public function test_expired_session_leases_reset_the_draft_from_permanent_settings() {
        $old = new Hm_User_Config_Shared_State($this->store, 'alice');
        $old->open('password', ['theme' => 'saved'], 'old-session', 1);
        $old->update(['config' => ['theme' => 'unsaved'], 'changed_settings' => ['Changed theme']]);
        sleep(2);

        $next = new Hm_User_Config_Shared_State($this->store, 'alice');
        $this->assertSame(
            ['config' => ['theme' => 'saved'], 'changed_settings' => []],
            $next->open('password', ['theme' => 'saved'], 'new-session', 3600)
        );
        $this->expectException(Hm_Shared_State_Reauthentication_Required::class);
        $old->update(['config' => ['theme' => 'stale'], 'changed_settings' => []]);
    }

    /** @return void */
    public function test_resuming_the_last_expired_session_deletes_its_draft() {
        $state = new Hm_User_Config_Shared_State($this->store, 'alice');
        $state->open('password', ['theme' => 'saved'], 'expired-session', 1);
        $credentials = $state->credentials();
        sleep(2);

        $next_request = new Hm_User_Config_Shared_State($this->store, 'alice');
        try {
            $next_request->resume($credentials, 'expired-session', 3600);
            $this->fail('An expired session must authenticate again');
        } catch (Hm_Shared_State_Reauthentication_Required $exception) {
            $this->assertSame('The session expired; sign in again', $exception->getMessage());
        }

        $this->assertNull($this->store->read('alice'));
    }

    /** @return void */
    public function test_releasing_one_session_keeps_the_state_for_the_others() {
        $first = new Hm_User_Config_Shared_State($this->store, 'alice');
        $first->open('password', ['theme' => 'saved'], 'first-session');
        $first->update(['config' => ['theme' => 'unsaved'], 'changed_settings' => ['Changed theme']]);

        $second = new Hm_User_Config_Shared_State($this->store, 'alice');
        $second->open('password', [], 'second-session');
        $credentials = $second->credentials();

        $first->release_session('first-session');

        $resumed = new Hm_User_Config_Shared_State($this->store, 'alice');
        $this->assertSame(
            ['config' => ['theme' => 'unsaved'], 'changed_settings' => ['Changed theme']],
            $resumed->resume($credentials, 'second-session')
        );
    }

    /** @return void */
    public function test_released_session_cannot_resume() {
        $first = new Hm_User_Config_Shared_State($this->store, 'alice');
        $first->open('password', ['theme' => 'saved'], 'first-session');
        $credentials = $first->credentials();
        $second = new Hm_User_Config_Shared_State($this->store, 'alice');
        $second->open('password', [], 'second-session');

        $first->release_session('first-session');

        $this->expectException(Hm_Shared_State_Reauthentication_Required::class);
        (new Hm_User_Config_Shared_State($this->store, 'alice'))->resume($credentials, 'first-session');
    }

    /** @return void */
    public function test_releasing_the_last_session_deletes_the_state() {
        $state = new Hm_User_Config_Shared_State($this->store, 'alice');
        $state->open('password', ['theme' => 'saved'], 'only-session');

        $state->release_session('only-session');

        $this->assertNull($this->store->read('alice'));
    }

    /** @return void */
    public function test_release_from_a_replaced_generation_keeps_the_new_state() {
        $old = new Hm_User_Config_Shared_State($this->store, 'alice');
        $old->open('old-password', ['theme' => 'saved'], 'old-session', 1);
        sleep(2);

        $new = new Hm_User_Config_Shared_State($this->store, 'alice');
        $new->open('password', ['theme' => 'saved'], 'new-session');

        $old->release_session('old-session');

        $this->assertNotNull($this->store->read('alice'));
    }

    /** @return void */
    public function test_resume_rejects_invalid_credentials() {
        $state = new Hm_User_Config_Shared_State($this->store, 'alice');
        $state->open('password', []);

        $this->expectException(RuntimeException::class);
        $state->resume(['key' => 'not-a-key', 'generation' => 'x']);
    }

    /** @return void */
    public function test_resume_reports_a_missing_record() {
        $state = new Hm_User_Config_Shared_State($this->store, 'alice');
        $state->open('password', []);
        $credentials = $state->credentials();
        $this->store->delete_if_revision('alice', $this->store->read('alice')['revision']);

        $this->expectException(Hm_Shared_State_Reauthentication_Required::class);
        $state->resume($credentials);
    }

    /** @return void */
    public function test_operations_require_an_opened_state() {
        $state = new Hm_User_Config_Shared_State($this->store, 'alice');
        $data = ['config' => [], 'changed_settings' => []];

        try {
            $state->update($data);
            $this->fail('Updating before opening must fail');
        } catch (LogicException $exception) {
            $this->assertSame('Shared state has not been opened', $exception->getMessage());
        }

        $this->expectException(LogicException::class);
        $state->rebase_local($data);
    }

    /** @return void */
    public function test_password_change_is_rejected_for_a_replaced_generation() {
        $old = new Hm_User_Config_Shared_State($this->store, 'alice');
        $old->open('old', ['saved' => 1], 'old-session', 1);
        sleep(2);
        $new = new Hm_User_Config_Shared_State($this->store, 'alice');
        $new->open('new', ['saved' => 1], 'new-session');

        $this->expectException(Hm_Shared_State_Reauthentication_Required::class);
        $old->change_password('other');
    }

    /** @return void */
    public function test_password_rewrap_keeps_shared_state() {
        $state = new Hm_User_Config_Shared_State($this->store, 'alice');
        $state->open('old', ['saved' => 1]);
        $state->update(['config' => ['saved' => 2], 'changed_settings' => ['changed']]);
        $state->change_password('new');

        $reopened = new Hm_User_Config_Shared_State($this->store, 'alice');
        $this->assertSame(['config' => ['saved' => 2], 'changed_settings' => ['changed']], $reopened->open('new', []));
    }

    /** @return void */
    public function test_save_of_old_snapshot_keeps_later_dirty_changes() {
        $first = new Hm_User_Config_Shared_State($this->store, 'alice');
        $first->open('password', ['a' => 1, 'b' => 1]);
        $first->update(['config' => ['a' => 2, 'b' => 1], 'changed_settings' => ['first']]);

        $second = new Hm_User_Config_Shared_State($this->store, 'alice');
        $second->open('password', []);
        $second->update(['config' => ['a' => 2, 'b' => 2], 'changed_settings' => ['first', 'second']]);

        $result = $first->update(['config' => ['a' => 2, 'b' => 1], 'changed_settings' => []]);
        $this->assertSame(['a' => 2, 'b' => 2], $result['config']);
        $this->assertSame(['second'], $result['changed_settings']);
    }

    /** @return void */
    public function test_concurrent_deletion_is_not_recreated_as_null() {
        $first = new Hm_User_Config_Shared_State($this->store, 'alice');
        $initial = ['preferences' => ['a' => 1, 'b' => 1]];
        $first->open('password', $initial);

        $second = new Hm_User_Config_Shared_State($this->store, 'alice');
        $second->open('password', []);

        $first->update(['config' => ['preferences' => ['b' => 1]], 'changed_settings' => []]);

        $result = $second->update(['config' => ['preferences' => ['a' => 1, 'b' => 2]], 'changed_settings' => []]);
        $this->assertSame(['preferences' => ['b' => 2]], $result['config']);
    }

    /** @return void */
    public function test_concurrent_additions_to_a_new_map_are_merged() {
        $first = new Hm_User_Config_Shared_State($this->store, 'alice');
        $first->open('password', ['version' => 1]);

        $second = new Hm_User_Config_Shared_State($this->store, 'alice');
        $second->open('password', []);

        $first->update(['config' => ['version' => 1, 'servers' => ['first' => ['name' => 'First']]], 'changed_settings' => []]);

        $result = $second->update(['config' => ['version' => 1, 'servers' => ['second' => ['name' => 'Second']]], 'changed_settings' => []]);
        $this->assertSame(['first' => ['name' => 'First'], 'second' => ['name' => 'Second']], $result['config']['servers']);
    }

    /** @return void */
    public function test_explicit_save_uses_latest_merged_snapshot() {
        $first = new Hm_User_Config_Shared_State($this->store, 'alice');
        $first->open('password', ['a' => 1, 'b' => 1]);

        $second = new Hm_User_Config_Shared_State($this->store, 'alice');
        $second->open('password', []);
        $second->update(['config' => ['a' => 1, 'b' => 2], 'changed_settings' => ['second']]);

        $permanent = null;
        $result = $first->save(
            ['config' => ['a' => 2, 'b' => 1], 'changed_settings' => ['first']],
            function ($config) use (&$permanent) { $permanent = $config; }
        );
        $this->assertSame(['a' => 2, 'b' => 2], $permanent);
        $this->assertSame([], $result['changed_settings']);
        $this->assertSame($permanent, $result['config']);
    }

    /** @return void */
    public function test_failed_save_does_not_clear_dirty_state() {
        $state = new Hm_User_Config_Shared_State($this->store, 'alice');
        $state->open('password', ['a' => 1]);
        $state->update(['config' => ['a' => 2], 'changed_settings' => ['changed']]);

        try {
            $state->save(
                ['config' => ['a' => 2], 'changed_settings' => ['changed']],
                function () { throw new RuntimeException('Save failed'); }
            );
            $this->fail('The save should fail');
        } catch (RuntimeException $exception) {
            $this->assertSame('Save failed', $exception->getMessage());
        }

        $other = new Hm_User_Config_Shared_State($this->store, 'alice');
        $this->assertSame(['config' => ['a' => 2], 'changed_settings' => ['changed']], $other->open('password', []));
    }

    /** @return void */
    public function test_conflicting_changes_use_the_last_writer() {
        $first = new Hm_User_Config_Shared_State($this->store, 'alice');
        $first->open('password', ['theme' => 'default']);

        $second = new Hm_User_Config_Shared_State($this->store, 'alice');
        $second->open('password', []);

        $first->update(['config' => ['theme' => 'dark'], 'changed_settings' => ['first']]);

        $result = $second->update(['config' => ['theme' => 'light'], 'changed_settings' => ['second']]);
        $this->assertSame('light', $result['config']['theme']);
        $this->assertSame(['first', 'second'], $result['changed_settings']);
    }

    /** @return void */
    public function test_repeated_flush_does_not_reverse_concurrent_changes() {
        $first = new Hm_User_Config_Shared_State($this->store, 'alice');
        $initial = ['theme' => 'light', 'servers' => ['a' => ['name' => 'A']]];
        $first->open('password', $initial);

        $second = new Hm_User_Config_Shared_State($this->store, 'alice');
        $second->open('password', []);

        $second->update([
            'config' => ['theme' => 'light', 'servers' => ['a' => ['name' => 'A'], 'b' => ['name' => 'B']]],
            'changed_settings' => ['server added'],
        ]);

        $local = ['config' => ['theme' => 'dark', 'servers' => ['a' => ['name' => 'A']]], 'changed_settings' => ['theme changed']];
        $first->update($local);
        $result = $first->update($local);
        $this->assertSame(['a' => ['name' => 'A'], 'b' => ['name' => 'B']], $result['config']['servers']);
        $this->assertSame(['server added', 'theme changed'], $result['changed_settings']);
    }

    /** @return void */
    public function test_save_clears_dirty_changes_added_by_another_session() {
        $first = new Hm_User_Config_Shared_State($this->store, 'alice');
        $initial = ['config' => ['a' => 1], 'changed_settings' => []];
        $first->open('password', $initial['config']);

        $second = new Hm_User_Config_Shared_State($this->store, 'alice');
        $second->open('password', []);
        $second->update(['config' => ['a' => 2], 'changed_settings' => ['changed']]);

        $saved = null;
        $result = $first->save($initial, function ($config) use (&$saved) { $saved = $config; });
        $this->assertSame(['a' => 2], $saved);
        $this->assertSame([], $result['changed_settings']);
    }
}
