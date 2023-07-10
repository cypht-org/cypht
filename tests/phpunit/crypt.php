<?php

use PHPUnit\Framework\TestCase;

/**
 * tests for Hm_Crypt
 */
class Hm_Test_Crypt extends TestCase {

    public function setUp(): void {
        require 'bootstrap.php';
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_ciphertext() {
        $cipher = Hm_Crypt::ciphertext('test', 'testkey');
        $this->assertFalse($cipher == 'test');
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_plaintext() {
        $this->assertFalse(Hm_Crypt::plaintext('asdf', 'testkey'));
        $this->assertFalse(Hm_Crypt::plaintext(base64_encode(str_repeat('a', 201)), 'testkey'));
        $cipher = Hm_Crypt::ciphertext('test', 'testkey');
        $plain = rtrim(Hm_Crypt::plaintext($cipher, 'testkey'), "\0");
        $this->assertEquals('test', $plain);
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_check_password() {
        $this->assertFalse(Hm_Crypt::check_password('test', 'asdf'));
        $hash = Hm_Crypt::hash_password('test');
        $this->assertTrue(Hm_Crypt::check_password('test', $hash));

        $hash = Hm_Crypt::hash_password('test', false, false, 'sha512', 'pbkdf2');
        $this->assertTrue(Hm_Crypt::check_password('test', $hash));
        $this->assertFalse(Hm_Crypt::check_password('asdf', 'sha512asdf'));
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_hash_password() {
        $hash = Hm_Crypt::hash_password('test');
        $this->assertTrue(Hm_Crypt::check_password('test', $hash));
    }
}

/**
 * tests for Hm_Crypt
 */
class Hm_Test_Crypt_Base extends TestCase {

    public function setUp(): void {
        define('LIBSODIUM', false);
        require 'bootstrap.php';
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_random() {
        $bytes = Hm_Crypt::random(10);
        $this->assertTrue(strlen($bytes) == 10);
        Hm_Functions::$rand_bytes = 'bad';
        try {
            Hm_Crypt::random(10);
        }
        catch(Error $e) {
            $this->assertTrue(true);
        }
        Hm_Functions::$rand_bytes = 'ugly';
        try {
            Hm_Crypt::random(10);
        }
        catch(Exception $e) {
            $this->assertTrue(true);
        }
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_ciphertext() {
        $cipher = Hm_Crypt::ciphertext('test', 'testkey');
        $this->assertFalse($cipher == 'test');
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_plaintext() {
        $this->assertFalse(Hm_Crypt::plaintext('asdf', 'testkey'));
        $this->assertFalse(Hm_Crypt::plaintext(base64_encode(str_repeat('a', 201)), 'testkey'));
        $cipher = Hm_Crypt::ciphertext('test', 'testkey');
        $plain = rtrim(Hm_Crypt::plaintext($cipher, 'testkey'), "\0");
        $this->assertEquals('test', $plain);
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_check_password() {
        $this->assertFalse(Hm_Crypt::check_password('test', 'asdf'));
        $hash = Hm_Crypt::hash_password('test');
        $this->assertTrue(Hm_Crypt::check_password('test', $hash));

        $hash = Hm_Crypt::hash_password('test', false, false, 'sha512', 'pbkdf2');
        $this->assertTrue(Hm_Crypt::check_password('test', $hash));
        $this->assertFalse(Hm_Crypt::check_password('asdf', 'sha512asdf'));
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_hash_password() {
        $hash = Hm_Crypt::hash_password('test');
        $this->assertTrue(Hm_Crypt::check_password('test', $hash));
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_hash_compare() {
        $this->assertFalse(Hm_Crypt::hash_compare('asdf', 'xcvb'));
        $this->assertFalse(Hm_Crypt::hash_compare('asdf', false));
        $this->assertFalse(Hm_Crypt::hash_compare('0', false));
        $this->assertTrue(Hm_Crypt::hash_compare('asdf', 'asdf'));
        Hm_Functions::$exists = false;
        $this->assertFalse(Hm_Crypt::hash_compare('asdf', 'xcvb'));
        $this->assertFalse(Hm_Crypt::hash_compare('asdf', false));
        $this->assertFalse(Hm_Crypt::hash_compare('0', false));
        $this->assertTrue(Hm_Crypt::hash_compare('asdf', 'asdf'));
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_pbkdf2() {
        $this->assertEquals(base64_encode(Hm_Crypt::pbkdf2('testkey', 'testsalt', 32, 2, 'sha512')), '8RSGqH63sWwLtwAssCsc01AIweWJW/f8Mf36zDCFN7E=');
        $this->assertNotEquals(base64_encode(Hm_Crypt::pbkdf2('testkey', 'testsalt', 32, 2, 'sha512')), 'asdf');
        Hm_Functions::$exists = false;
        $this->assertEquals(base64_encode(Hm_Crypt::pbkdf2('testkey', 'testsalt', 32, 2, 'sha512')), '8RSGqH63sWwLtwAssCsc01AIweWJW/f8Mf36zDCFN7E=');
        $this->assertNotEquals(base64_encode(Hm_Crypt::pbkdf2('testkey', 'testsalt', 32, 2, 'sha512')), 'asdf');
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_unique_id() {
        $this->assertEquals(24, strlen(base64_decode(Hm_Crypt::unique_id(24))));
        $this->assertEquals(48, strlen(base64_decode(Hm_Crypt::unique_id(48))));
        $this->assertEquals(128, strlen(base64_decode(Hm_Crypt::unique_id())));
    }
}

/**
 * tests for Hm_Request_Key
 */
class Hm_Test_Request_Key extends TestCase {

    public function setUp(): void {
        require 'bootstrap.php';
        $session = new Hm_Mock_Session();
        $request = new Hm_Mock_Request('AJAX');
        Hm_Request_Key::load($session, $request, false);
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_key_load() {
        $this->assertEquals('fakefingerprint', Hm_Request_Key::generate());
        $session = new Hm_Mock_Session();
        $request = new Hm_Mock_Request('AJAX');
        Hm_Request_Key::load($session, $request, false);
        $this->assertEquals('fakefingerprint', Hm_Request_Key::generate());
        Hm_Request_Key::load($session, $request, true);
        $this->assertEquals('fakefingerprint', Hm_Request_Key::generate());
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_key_generate() {
        $this->assertEquals('fakefingerprint', Hm_Request_Key::generate());
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_key_validate() {
        $this->assertTrue(Hm_Request_Key::validate('fakefingerprint'));
    }
    public function tearDown(): void {
    }
}

?>
