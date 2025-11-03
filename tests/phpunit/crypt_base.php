<?php

use PHPUnit\Framework\TestCase;

/**
 * tests for Hm_Crypt
 */
class Hm_Test_Crypt_Base extends TestCase {
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
