<?php

use PHPUnit\Framework\TestCase;

class Hm_Test_Ldap_Contacts extends TestCase {
    private static $ldapAvailable = false;
    private static $ldapConfig = [];

    public function setUp(): void {
        require_once __DIR__.'/../../helpers.php';
        require_once APP_PATH.'modules/contacts/hm-contacts.php';
        require_once APP_PATH.'modules/ldap_contacts/hm-ldap-contacts.php';
        require_once APP_PATH.'modules/ldap_contacts/modules.php';
        
        // Check if LDAP extension is available
        if (!Hm_Functions::function_exists('ldap_connect')) {
            $this->markTestSkipped('LDAP extension not available');
        }
        
        // Configure LDAP connection for tests
        self::$ldapConfig = [
            'name' => 'test_ldap',
            'server' => getenv('LDAP_HOST') ?: 'localhost',
            'port' => getenv('LDAP_PORT') ?: 389,
            'enable_tls' => false,
            'base_dn' => 'dc=cypht,dc=test',
            'search_term' => 'objectclass=inetOrgPerson',
            'auth' => true,
            'user' => 'admin',
            'pass' => 'cypht_test',
            'ldap_uid_attr' => 'cn',
            'objectclass' => ['top', 'inetOrgPerson']
        ];
        
        // Test if LDAP server is available
        $ldap = new Hm_LDAP_Contacts(self::$ldapConfig);
        if ($ldap->connect()) {
            self::$ldapAvailable = true;
        } else {
            $this->markTestSkipped('LDAP server not available - skipping integration tests');
        }
    }

    /**
     * Test LDAP connection using Cypht's class
     */
    public function test_ldap_connection() {
        $ldap = new Hm_LDAP_Contacts(self::$ldapConfig);
        $connected = $ldap->connect();
        
        $this->assertTrue($connected, 'Should successfully connect to LDAP server');
    }

    /**
     * Test fetching LDAP contacts using Cypht's fetch method
     */
    public function test_ldap_fetch_contacts() {
        $ldap = new Hm_LDAP_Contacts(self::$ldapConfig);
        $this->assertTrue($ldap->connect(), 'Should connect to LDAP');
        
        $contacts = $ldap->fetch();
        
        $this->assertIsArray($contacts, 'fetch() should return an array');
        $this->assertGreaterThan(0, count($contacts), 'Should find at least one contact');
        
        // Verify contact structure
        $contact = $contacts[0];
        $this->assertArrayHasKey('display_name', $contact, 'Contact should have display_name');
        $this->assertArrayHasKey('email_address', $contact, 'Contact should have email_address');
        $this->assertArrayHasKey('source', $contact, 'Contact should have source');
        $this->assertArrayHasKey('type', $contact, 'Contact should have type');
        $this->assertEquals('ldap', $contact['type'], 'Contact type should be ldap');
    }

    /**
     * Test LDAP contact with all fields
     */
    public function test_ldap_contact_all_fields() {
        $ldap = new Hm_LDAP_Contacts(self::$ldapConfig);
        $this->assertTrue($ldap->connect());
        
        $contacts = $ldap->fetch();
        $this->assertGreaterThan(0, count($contacts));
        
        // Find John Doe contact
        $johnDoe = null;
        foreach ($contacts as $contact) {
            if (isset($contact['display_name']) && $contact['display_name'] === 'John Doe') {
                $johnDoe = $contact;
                break;
            }
        }
        
        $this->assertNotNull($johnDoe, 'Should find John Doe contact');
        $this->assertEquals('john.doe@cypht.test', $johnDoe['email_address']);
        $this->assertArrayHasKey('all_fields', $johnDoe, 'Should have all_fields');
        $this->assertArrayHasKey('dn', $johnDoe['all_fields'], 'Should have DN in all_fields');
    }

    /**
     * Test Hm_LDAP_Contact class methods using real LDAP data
     */
    public function test_ldap_contact_class() {
        // Fetch real contacts from LDAP server
        $ldap = new Hm_LDAP_Contacts(self::$ldapConfig);
        $this->assertTrue($ldap->connect(), 'Should connect to LDAP');
        
        $contacts = $ldap->fetch();
        $this->assertGreaterThan(0, count($contacts), 'Should have at least one contact to test with');
        
        // Use the first real contact
        $contactData = $contacts[0];
        $contact = new Hm_LDAP_Contact($contactData);
        
        // Test getDN method - should return DN from all_fields
        $dn = $contact->getDN();
        $this->assertNotEmpty($dn, 'getDN() should return a non-empty DN');
        $this->assertStringContainsString('ou=contacts,dc=cypht,dc=test', $dn, 'DN should contain base DN');
        
        // Test isLdapContact static method
        $this->assertTrue(Hm_LDAP_Contact::isLdapContact($contact), 'Should identify as LDAP contact');
        
        // Test that contact has required LDAP fields
        $this->assertArrayHasKey('all_fields', $contactData, 'Real LDAP contact should have all_fields');
        $this->assertArrayHasKey('dn', $contactData['all_fields'], 'all_fields should contain DN');
    }

    /**
     * Test LDAP DN construction
     */
    public function test_ldap_dn_construction() {
        $baseDn = 'ou=contacts,dc=cypht,dc=test';
        $cn = 'Test User';
        
        $expectedDn = "cn=Test User,ou=contacts,dc=cypht,dc=test";
        $constructedDn = sprintf('cn=%s,%s', $cn, $baseDn);
        
        $this->assertEquals($expectedDn, $constructedDn);
    }

    /**
     * Test LDAP error handling
     */
    public function test_ldap_connection_error() {
        $badConfig = self::$ldapConfig;
        $badConfig['pass'] = 'wrong_password';
        
        $ldap = new Hm_LDAP_Contacts($badConfig);
        $connected = $ldap->connect();
        
        $this->assertFalse($connected, 'Connection should fail with wrong password');
    }

    /**
     * Test LDAP error method
     */
    public function test_ldap_error_method() {
        $ldap = new Hm_LDAP_Contacts(self::$ldapConfig);
        $this->assertTrue($ldap->connect());
        
        // The error method should be callable
        $error = $ldap->error();
        $this->assertIsString($error, 'error() should return a string');
    }

    /**
     * Test LDAP config validation
     */
    public function test_ldap_config_handling() {
        // Test with minimal config
        $minimalConfig = [
            'name' => 'test',
            'server' => 'localhost',
            'port' => 1389,
            'base_dn' => 'dc=test'
        ];
        
        $ldap = new Hm_LDAP_Contacts($minimalConfig);
        $this->assertInstanceOf(Hm_LDAP_Contacts::class, $ldap);
    }

    /**
     * Test LDAP contact DN encoding/decoding
     */
    public function test_ldap_dn_encoding() {
        $originalDn = 'cn=Test User,ou=contacts,dc=cypht,dc=test';
        $encoded = urlencode($originalDn);
        $decoded = Hm_LDAP_Contact::decodeDN($encoded);
        
        $this->assertEquals($originalDn, $decoded, 'DN should decode correctly');
    }

    /**
     * Test LDAP add contact functionality
     */
    public function test_ldap_add_contact() {
        $ldap = new Hm_LDAP_Contacts(self::$ldapConfig);
        $this->assertTrue($ldap->connect(), 'Should connect to LDAP');
        
        // Create test contact entry
        $testCn = 'PHPUnit Test Contact';
        $testDn = sprintf('cn=%s,%s', $testCn, self::$ldapConfig['base_dn']);
        
        $entry = [
            'objectClass' => ['top', 'inetOrgPerson'],
            'cn' => $testCn,
            'sn' => 'Contact',
            'mail' => 'phpunit.test@cypht.test',
            'telephoneNumber' => '+1234567890'
        ];
        
        // Add the contact
        $added = $ldap->add($entry, $testDn);
        $this->assertTrue($added, 'Should successfully add contact. Error: ' . $ldap->error());
        
        // Verify contact was added by fetching it
        $contacts = $ldap->fetch();
        $found = false;
        foreach ($contacts as $contact) {
            if ($contact['display_name'] === $testCn) {
                $found = true;
                $this->assertEquals('phpunit.test@cypht.test', $contact['email_address']);
                break;
            }
        }
        $this->assertTrue($found, 'Should find the newly added contact');
        
        // Clean up - delete the test contact
        $ldap->delete($testDn);
    }

    /**
     * Test LDAP modify contact functionality
     */
    public function test_ldap_modify_contact() {
        $ldap = new Hm_LDAP_Contacts(self::$ldapConfig);
        $this->assertTrue($ldap->connect(), 'Should connect to LDAP');
        
        // First add a contact to modify
        $testCn = 'PHPUnit Modify Test';
        $testDn = sprintf('cn=%s,%s', $testCn, self::$ldapConfig['base_dn']);
        
        $entry = [
            'objectClass' => ['top', 'inetOrgPerson'],
            'cn' => $testCn,
            'sn' => 'Test',
            'mail' => 'modify.test@cypht.test',
            'telephoneNumber' => '+1111111111'
        ];
        
        $ldap->add($entry, $testDn);
        
        // Modify the contact - update phone and email
        $modifications = [
            'telephoneNumber' => '+9999999999',
            'mail' => 'modified.email@cypht.test'
        ];
        
        $modified = $ldap->modify($modifications, $testDn);
        $this->assertTrue($modified, 'Should successfully modify contact. Error: ' . $ldap->error());
        
        // Verify modifications by fetching the contact
        $contacts = $ldap->fetch();
        $found = false;
        foreach ($contacts as $contact) {
            if ($contact['display_name'] === $testCn) {
                $found = true;
                $this->assertEquals('modified.email@cypht.test', $contact['email_address'], 'Email should be updated');
                $this->assertEquals('+9999999999', $contact['phone_number'], 'Phone should be updated');
                break;
            }
        }
        $this->assertTrue($found, 'Should find the modified contact');
        
        // Clean up
        $ldap->delete($testDn);
    }

    /**
     * Test LDAP delete contact functionality
     */
    public function test_ldap_delete_contact() {
        $ldap = new Hm_LDAP_Contacts(self::$ldapConfig);
        $this->assertTrue($ldap->connect(), 'Should connect to LDAP');
        
        // First add a contact to delete
        $testCn = 'PHPUnit Delete Test';
        $testDn = sprintf('cn=%s,%s', $testCn, self::$ldapConfig['base_dn']);
        
        $entry = [
            'objectClass' => ['top', 'inetOrgPerson'],
            'cn' => $testCn,
            'sn' => 'Test',
            'mail' => 'delete.test@cypht.test'
        ];
        
        $ldap->add($entry, $testDn);
        
        // Verify contact exists
        $contacts = $ldap->fetch();
        $found = false;
        foreach ($contacts as $contact) {
            if ($contact['display_name'] === $testCn) {
                $found = true;
                break;
            }
        }
        $this->assertTrue($found, 'Contact should exist before deletion');
        
        // Delete the contact
        $deleted = $ldap->delete($testDn);
        $this->assertTrue($deleted, 'Should successfully delete contact. Error: ' . $ldap->error());
        
        // Verify contact was deleted
        $contacts = $ldap->fetch();
        $stillExists = false;
        foreach ($contacts as $contact) {
            if ($contact['display_name'] === $testCn) {
                $stillExists = true;
                break;
            }
        }
        $this->assertFalse($stillExists, 'Contact should not exist after deletion');
    }

    /**
     * Test LDAP rename contact functionality
     */
    public function test_ldap_rename_contact() {
        $ldap = new Hm_LDAP_Contacts(self::$ldapConfig);
        $this->assertTrue($ldap->connect(), 'Should connect to LDAP');
        
        // First add a contact to rename
        $oldCn = 'PHPUnit Rename Test Old';
        $oldDn = sprintf('cn=%s,%s', $oldCn, self::$ldapConfig['base_dn']);
        
        $entry = [
            'objectClass' => ['top', 'inetOrgPerson'],
            'cn' => $oldCn,
            'sn' => 'Test',
            'mail' => 'rename.test@cypht.test'
        ];
        
        $ldap->add($entry, $oldDn);
        
        // Rename the contact
        $newCn = 'PHPUnit Rename Test New';
        $newRdn = sprintf('cn=%s', $newCn);
        $parentDn = self::$ldapConfig['base_dn'];
        
        $renamed = $ldap->rename($oldDn, $newRdn, $parentDn);
        $this->assertTrue($renamed, 'Should successfully rename contact. Error: ' . $ldap->error());
        
        // Verify old name doesn't exist
        $contacts = $ldap->fetch();
        $oldExists = false;
        $newExists = false;
        
        foreach ($contacts as $contact) {
            if ($contact['display_name'] === $oldCn) {
                $oldExists = true;
            }
            if ($contact['display_name'] === $newCn) {
                $newExists = true;
                $this->assertEquals('rename.test@cypht.test', $contact['email_address'], 'Email should remain the same');
            }
        }
        
        $this->assertFalse($oldExists, 'Old contact name should not exist after rename');
        $this->assertTrue($newExists, 'New contact name should exist after rename');
        
        // Clean up - delete with new DN
        $newDn = sprintf('cn=%s,%s', $newCn, self::$ldapConfig['base_dn']);
        $ldap->delete($newDn);
    }
}
