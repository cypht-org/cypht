<?php

/**
 * Unit tests for Hm_Repository trait
 * @package lib/tests
 */

use PHPUnit\Framework\TestCase;

class Hm_Test_Repository extends TestCase {

    private $user_config;
    private $session;

    public function setUp(): void {
        require_once __DIR__.'/../bootstrap.php';

        $this->user_config = new Hm_Mock_Config();
        $this->session     = new Hm_Mock_Session();

        Hm_Tags_Wrapper::init($this->user_config, $this->session);
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_count_returns_zero_on_empty_repo(): void {
        $this->assertSame(0, Hm_Tags_Wrapper::count());
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_count_reflects_added_entities(): void {
        Hm_Tags_Wrapper::add(['name' => 'A']);
        Hm_Tags_Wrapper::add(['name' => 'B']);
        $this->assertSame(2, Hm_Tags_Wrapper::count());
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_add_generates_id_when_not_provided(): void {
        $id = Hm_Tags_Wrapper::add(['name' => 'Tag']);
        $this->assertNotEmpty($id);
        $entity = Hm_Tags_Wrapper::get($id);
        $this->assertSame('Tag', $entity['name']);
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_add_preserves_explicit_id(): void {
        Hm_Tags_Wrapper::add(['id' => 'my-id', 'name' => 'Named']);
        $entity = Hm_Tags_Wrapper::get('my-id');
        $this->assertSame('Named', $entity['name']);
        $this->assertSame('my-id', $entity['id']);
    }

    /**
     * add() accepts an object that implements value() / update() instead of an array.
     *
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_add_accepts_entity_object_with_value_method(): void {
        $entity = new class {
            private array $data = ['name' => 'ObjectTag'];
            public function value(string $key) { return $this->data[$key] ?? null; }
            public function update(string $key, $val): void { $this->data[$key] = $val; }
        };

        $id = Hm_Tags_Wrapper::add($entity);
        $this->assertNotEmpty($id);

        $stored = Hm_Tags_Wrapper::get($id);
        $this->assertSame('ObjectTag', $stored->value('name'));
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_add_throws_for_unsupported_entity_type(): void {
        $this->expectException(Exception::class);
        Hm_Tags_Wrapper::add('unsupported string entity');
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_get_returns_false_for_missing_id(): void {
        $this->assertFalse(Hm_Tags_Wrapper::get('nonexistent-id'));
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_edit_merges_fields_for_array_entity(): void {
        $id = Hm_Tags_Wrapper::add(['name' => 'Original', 'color' => 'red']);
        Hm_Tags_Wrapper::edit($id, ['color' => 'blue']);

        $entity = Hm_Tags_Wrapper::get($id);
        $this->assertSame('Original', $entity['name']);
        $this->assertSame('blue', $entity['color']);
    }

    /**
     * edit() replaces an object entity with another object (the non-array branch).
     *
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_edit_replaces_object_entity_with_new_object(): void {
        $original = new class {
            private array $data = ['name' => 'Original'];
            public function value(string $k) { return $this->data[$k] ?? null; }
            public function update(string $k, $v): void { $this->data[$k] = $v; }
        };
        $id = Hm_Tags_Wrapper::add($original);

        $replacement = new class {
            private array $data = ['name' => 'Replaced'];
            public function value(string $k) { return $this->data[$k] ?? null; }
            public function update(string $k, $v): void { $this->data[$k] = $v; }
        };
        Hm_Tags_Wrapper::edit($id, $replacement);

        $stored = Hm_Tags_Wrapper::get($id);
        $this->assertSame('Replaced', $stored->value('name'));
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_edit_returns_false_for_missing_id(): void {
        $this->assertFalse(Hm_Tags_Wrapper::edit('no-such-id', ['name' => 'X']));
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_del_removes_entity_and_returns_true(): void {
        $id = Hm_Tags_Wrapper::add(['name' => 'ToDelete']);
        $this->assertTrue(Hm_Tags_Wrapper::del($id));
        $this->assertFalse(Hm_Tags_Wrapper::get($id));
        $this->assertSame(0, Hm_Tags_Wrapper::count());
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_del_returns_false_for_missing_id(): void {
        $this->assertFalse(Hm_Tags_Wrapper::del('no-such-id'));
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_getAll_returns_all_entities_keyed_by_id(): void {
        $id1 = Hm_Tags_Wrapper::add(['name' => 'First']);
        $id2 = Hm_Tags_Wrapper::add(['name' => 'Second']);

        $all = Hm_Tags_Wrapper::getAll();
        $this->assertArrayHasKey($id1, $all);
        $this->assertArrayHasKey($id2, $all);
        $this->assertCount(2, $all);
    }

    /**
     * Integer-keyed imap_servers entries are migrated to uniqid-based keys
     * when initRepo() is called.
     *
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_migrate_replaces_integer_imap_server_ids(): void {
        $this->user_config->set('imap_servers', [
            0 => ['server' => 'imap.example.com', 'port' => 993],
            1 => ['server' => 'imap2.example.com', 'port' => 993],
        ]);

        Hm_Tags_Wrapper::init($this->user_config, $this->session);

        $servers = $this->user_config->get('imap_servers', []);
        foreach (array_keys($servers) as $key) {
            $this->assertFalse(is_numeric($key), "Key '$key' should not be numeric after migration");
        }
        $this->assertCount(2, $servers);
    }

    /**
     * Integer-keyed smtp_servers entries are migrated similarly.
     *
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_migrate_replaces_integer_smtp_server_ids(): void {
        $this->user_config->set('smtp_servers', [
            0 => ['server' => 'smtp.example.com', 'port' => 587],
        ]);

        Hm_Tags_Wrapper::init($this->user_config, $this->session);

        $servers = $this->user_config->get('smtp_servers', []);
        foreach (array_keys($servers) as $key) {
            $this->assertFalse(is_numeric($key));
        }
    }

    /**
     * When smtp_server ids are migrated, profile smtp_id references are
     * updated to the new non-integer ids.
     *
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_migrate_updates_profile_smtp_id_references(): void {
        $this->user_config->set('smtp_servers', [
            0 => ['server' => 'smtp.example.com', 'port' => 587],
        ]);
        $this->user_config->set('profiles', [
            'p1' => ['name' => 'Work', 'smtp_id' => 0],
        ]);

        Hm_Tags_Wrapper::init($this->user_config, $this->session);

        $profiles = $this->user_config->get('profiles', []);
        $smtpId   = $profiles['p1']['smtp_id'] ?? null;
        $this->assertFalse(is_numeric($smtpId), 'Profile smtp_id should be migrated to a string id');
    }

    /**
     * Non-integer keys are left untouched by the migration.
     *
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_migrate_leaves_string_keyed_servers_unchanged(): void {
        $existingId = 'abc123';
        $this->user_config->set('imap_servers', [
            $existingId => ['server' => 'imap.example.com', 'port' => 993],
        ]);

        Hm_Tags_Wrapper::init($this->user_config, $this->session);

        $servers = $this->user_config->get('imap_servers', []);
        $this->assertArrayHasKey($existingId, $servers);
    }
}
