<?php

use PHPUnit\Framework\TestCase;

class Hm_Test_User_Config_Shared_Store extends TestCase {
    private $directory;

    /** @return void */
    protected function setUp(): void {
        $this->directory = sys_get_temp_dir() . '/cypht-shared-state-store-' . bin2hex(random_bytes(12));
        if (!mkdir($this->directory, 0700)) {
            throw new RuntimeException('Unable to create test directory');
        }
    }

    /** @return void */
    protected function tearDown(): void {
        foreach (glob($this->directory . '/.shared-state-*') as $path) {
            unlink($path);
        }
        rmdir($this->directory);
    }

    /**
     * Create file- and database-backed stores for the storage tests.
     * @return array list of shared-state stores
     */
    private function stores() {
        $db = new PDO('sqlite::memory:');
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $db->exec('CREATE TABLE hm_user_shared_state (user_key TEXT PRIMARY KEY, revision INTEGER NOT NULL, payload BLOB NOT NULL)');
        return [
            new Hm_User_Config_Shared_File_Store($this->directory),
            new Hm_User_Config_Shared_DB_Store($db),
        ];
    }

    /** @return void */
    public function test_revisioned_updates_and_user_isolation() {
        foreach ($this->stores() as $store) {
            $this->assertNull($store->read('alice'));
            $this->assertTrue($store->compare_and_swap('alice', null, 'ciphertext one'));
            $this->assertFalse($store->compare_and_swap('alice', null, 'duplicate'));
            $this->assertSame(['revision' => 1, 'payload' => 'ciphertext one'], $store->read('alice'));
            $this->assertTrue($store->compare_and_swap('alice', 1, 'ciphertext two'));
            $this->assertFalse($store->compare_and_swap('alice', 1, 'stale'));
            $this->assertSame(['revision' => 2, 'payload' => 'ciphertext two'], $store->read('alice'));
            $this->assertNull($store->read('bob'));
            $this->assertTrue($store->compare_and_swap('bob', null, 'other'));
            $this->assertSame(['revision' => 2, 'payload' => 'ciphertext two'], $store->read('alice'));
        }
    }

    /** @return void */
    public function test_delete_only_removes_the_expected_revision() {
        foreach ($this->stores() as $store) {
            $this->assertTrue($store->compare_and_swap('alice', null, 'ciphertext one'));
            $this->assertFalse($store->delete_if_revision('alice', 2));
            $this->assertSame(1, $store->read('alice')['revision']);
            $this->assertTrue($store->delete_if_revision('alice', 1));
            $this->assertNull($store->read('alice'));
        }
    }

    /** @return void */
    public function test_file_rejects_corrupt_record() {
        $store = new Hm_User_Config_Shared_File_Store($this->directory);
        $store->compare_and_swap('alice', null, 'ciphertext');
        file_put_contents($this->directory . '/.shared-state-' . hash('sha256', 'alice'), 'corrupt');
        $this->expectException(RuntimeException::class);
        $store->read('alice');
    }

    /** @return void */
    public function test_empty_payload_is_rejected() {
        $store = new Hm_User_Config_Shared_File_Store($this->directory);
        $this->expectException(InvalidArgumentException::class);
        $store->compare_and_swap('alice', null, '');
    }

    /** @return void */
    public function test_binary_payload_is_preserved() {
        foreach ($this->stores() as $store) {
            $payload = "\x00\xff\x80ciphertext";
            $this->assertTrue($store->compare_and_swap('alice', null, $payload));
            $this->assertSame($payload, $store->read('alice')['payload']);
        }
    }

    /** @return void */
    public function test_invalid_revision_is_rejected() {
        $store = new Hm_User_Config_Shared_File_Store($this->directory);
        $this->expectException(InvalidArgumentException::class);
        $store->compare_and_swap('alice', 0, 'ciphertext');
    }
}
