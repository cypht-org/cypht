<?php

use PHPUnit\Framework\TestCase;

/**
 * tests for Hm_Crypt
 */
class Hm_Test_Crypt extends TestCase {
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
