<?php

/**
 * tests for Hm_Crypt
 */
class Hm_Test_Crypt extends PHPUnit_Framework_TestCase {

    public function setUp() {
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
    public function test_unpad() {
        $this->assertEquals('', Hm_Crypt::unpad(base64_decode('EBAQEBAQEBAQEBAQEBAQEA==')));
        $this->assertEquals('asdfasdfasdfasdfasdfasdfasdfasdfasdfasdf', Hm_Crypt::unpad(base64_decode('YXNkZmFzZGZhc2RmYXNkZmFzZGZhc2RmYXNkZmFzZGZhc2RmYXNkZg==')));
        $this->assertEquals('aaaaaaaaaaaaaaab', Hm_Crypt::unpad('aaaaaaaaaaaaaaab'));
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_pad() {
        $this->assertEquals('EBAQEBAQEBAQEBAQEBAQEA==', base64_encode(Hm_Crypt::pad('')));
        $this->assertEquals('YXNkZmFzZGZhc2RmYXNkZmFzZGZhc2RmYXNkZmFzZGZhc2RmYXNkZg==', base64_encode(Hm_Crypt::pad('asdfasdfasdfasdfasdfasdfasdfasdfasdfasdf')));
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
class Hm_Test_Request_Key extends PHPUnit_Framework_TestCase {

    public function setUp() {
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
    public function tearDown() {
    }
}

?>
