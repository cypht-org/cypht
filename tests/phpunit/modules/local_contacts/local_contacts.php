<?php

use PHPUnit\Framework\TestCase;

class Hm_Test_Local_Contacts extends TestCase {

    private $store;

    public function setUp(): void {
        require_once APP_PATH.'modules/contacts/hm-contacts.php';
        require_once APP_PATH.'modules/contacts/functions.php';
        require_once APP_PATH.'modules/local_contacts/modules.php';

        $this->store = new Hm_Contact_Store();
        $this->store->init(new Hm_Mock_Config(), new Hm_Mock_Session());
    }

    // --- Hm_Contact ---

    public function test_contact_value() {
        $contact = new Hm_Contact(['display_name' => 'John Doe', 'email_address' => 'john@example.com']);
        $this->assertEquals('John Doe', $contact->value('display_name'));
        $this->assertFalse($contact->value('missing'));
        $this->assertEquals('fallback', $contact->value('missing', 'fallback'));
    }

    public function test_contact_update() {
        $contact = new Hm_Contact(['display_name' => 'Jane']);
        $contact->update('display_name', 'Jane Updated');
        $this->assertEquals('Jane Updated', $contact->value('display_name'));
    }

    public function test_contact_export() {
        $data = ['display_name' => 'John', 'email_address' => 'john@example.com'];
        $this->assertEquals($data, (new Hm_Contact($data))->export());
    }

    public function test_store_add_and_dump() {
        $this->store->add_contact(['display_name' => 'Alice', 'email_address' => 'alice@example.com', 'source' => 'local']);
        $contacts = $this->store->dump();
        $this->assertCount(1, $contacts);
        $this->assertEquals('Alice', array_values($contacts)[0]->value('display_name'));
    }

    public function test_store_get_by_id() {
        $this->store->add_contact(['display_name' => 'Bob', 'email_address' => 'bob@example.com', 'source' => 'local']);
        $id = array_keys($this->store->dump())[0];
        $this->assertEquals('Bob', $this->store->get($id)->value('display_name'));
    }

    public function test_store_get_by_email() {
        $this->store->add_contact(['display_name' => 'Carol', 'email_address' => 'carol@example.com', 'source' => 'local']);
        $contact = $this->store->get('nonexistent', false, 'carol@example.com');
        $this->assertNotFalse($contact);
        $this->assertEquals('Carol', $contact->value('display_name'));
    }

    public function test_store_get_returns_default_for_missing() {
        $this->assertEquals('fallback', $this->store->get('no_such_id', 'fallback'));
    }

    public function test_store_search_by_name() {
        $this->store->add_contact(['display_name' => 'Alice Smith', 'email_address' => 'alice@example.com', 'source' => 'local']);
        $this->store->add_contact(['display_name' => 'Bob Jones', 'email_address' => 'bob@example.com', 'source' => 'local']);
        $this->assertCount(1, $this->store->search(['display_name' => 'Alice']));
        $this->assertCount(2, $this->store->search(['email_address' => 'example.com']));
    }

    public function test_store_update_contact() {
        $this->store->add_contact(['display_name' => 'Dave', 'email_address' => 'dave@example.com', 'source' => 'local']);
        $id = array_keys($this->store->dump())[0];
        $this->assertTrue($this->store->update_contact($id, ['display_name' => 'Dave Updated']));
        $this->assertEquals('Dave Updated', $this->store->get($id)->value('display_name'));
    }

    public function test_store_update_contact_missing_returns_false() {
        $this->assertFalse($this->store->update_contact('no_such_id', ['display_name' => 'X']));
    }

    public function test_store_delete() {
        $this->store->add_contact(['display_name' => 'Eve', 'email_address' => 'eve@example.com', 'source' => 'local']);
        $id = array_keys($this->store->dump())[0];
        $this->assertTrue($this->store->delete($id));
        $this->assertCount(0, $this->store->dump());
    }

    public function test_store_delete_missing_returns_false() {
        $this->assertFalse($this->store->delete('no_such_id'));
    }

    public function test_store_export_by_source() {
        $this->store->add_contact(['display_name' => 'Local', 'email_address' => 'local@example.com', 'source' => 'local']);
        $this->store->import([['display_name' => 'Ldap', 'email_address' => 'ldap@example.com', 'source' => 'ldap']]);
        $this->assertCount(1, $this->store->export('local'));
        $this->assertCount(1, $this->store->export('ldap'));
    }

    public function test_store_page() {
        for ($i = 1; $i <= 5; $i++) {
            $this->store->add_contact(['display_name' => "Contact $i", 'email_address' => "c$i@example.com", 'source' => 'local']);
        }
        $this->assertCount(3, $this->store->page(1, 3));
        $this->assertCount(2, $this->store->page(2, 3));
        $this->assertEmpty($this->store->page(0, 3));
    }

    public function test_store_group_by() {
        $this->store->add_contact(['display_name' => 'A', 'email_address' => 'a@example.com', 'source' => 'local', 'group' => 'Personal Addresses']);
        $this->store->add_contact(['display_name' => 'B', 'email_address' => 'b@example.com', 'source' => 'local', 'group' => 'Trusted Senders']);
        $this->store->add_contact(['display_name' => 'C', 'email_address' => 'c@example.com', 'source' => 'local', 'group' => 'Personal Addresses']);
        $grouped = $this->store->group_by('group');
        $this->assertCount(2, $grouped['Personal Addresses']);
        $this->assertCount(1, $grouped['Trusted Senders']);
    }

    public function test_store_sort() {
        $this->store->add_contact(['display_name' => 'Zara', 'email_address' => 'z@example.com', 'source' => 'local']);
        $this->store->add_contact(['display_name' => 'Aaron', 'email_address' => 'a@example.com', 'source' => 'local']);
        $this->store->sort('display_name');
        $names = array_values(array_map(fn($c) => $c->value('display_name'), $this->store->dump()));
        $this->assertEquals(['Aaron', 'Zara'], $names);
    }

    public function test_address_field_parse_simple_email() {
        $results = Hm_Address_Field::parse('user@example.com');
        $this->assertCount(1, $results);
        $this->assertEquals('user@example.com', $results[0]['email']);
    }

    public function test_address_field_parse_named_address() {
        $results = Hm_Address_Field::parse('John Doe <john@example.com>');
        $this->assertCount(1, $results);
        $this->assertEquals('john@example.com', $results[0]['email']);
        $this->assertEquals('John Doe', $results[0]['name']);
    }

    public function test_address_field_parse_multiple() {
        $results = Hm_Address_Field::parse('alice@example.com, bob@example.com');
        $this->assertCount(2, $results);
    }

    public function test_address_field_parse_invalid_returns_empty() {
        $this->assertEmpty(Hm_Address_Field::parse('not-an-email'));
    }

    public function test_get_initials_full_name() {
        $this->assertEquals('JD', get_initials('John Doe'));
    }

    public function test_get_initials_dotted_name() {
        $this->assertEquals('JD', get_initials('john.doe'));
    }

    public function test_get_initials_email() {
        $this->assertEquals('JE', get_initials('john@example.com'));
    }

    public function test_get_initials_single_word() {
        $this->assertEquals('J', get_initials('John'));
    }

    public function test_get_avatar_color_returns_gradient_string() {
        $this->assertStringContainsString('linear-gradient', get_avatar_color('test'));
    }

    public function test_get_avatar_color_is_deterministic() {
        $this->assertEquals(get_avatar_color('abc'), get_avatar_color('abc'));
    }

    public function test_get_avatar_color_numeric_id() {
        $this->assertIsString(get_avatar_color('42'));
    }
}
