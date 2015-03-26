<?php

/**
 * tests for Hm_Crypt
 */
class Hm_Test_Crypt extends PHPUnit_Framework_TestCase {

    public function setUp() {
        require 'bootstrap.php';
        $this->crypt = new Hm_Crypt();
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_ciphertext() {
        $cipher = $this->crypt->ciphertext('test', 'testkey');
        $this->assertFalse($cipher == 'test');
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_plaintext() {
        $cipher = $this->crypt->ciphertext('test', 'testkey');
        $plain = rtrim($this->crypt->plaintext($cipher, 'testkey'), "\0");
        $this->assertEquals('test', $plain);
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_iv_size() {
        $this->assertEquals(16, $this->crypt->iv_size());
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_unique_id() {
        $this->assertEquals(24, strlen(base64_decode($this->crypt->unique_id(24))));
        $this->assertEquals(48, strlen(base64_decode($this->crypt->unique_id(48))));
        $this->assertEquals(128, strlen(base64_decode($this->crypt->unique_id())));
    }
    public function tearDown() {
        unset($this->crypt);
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
        Hm_Request_Key::load($session, $request);
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
