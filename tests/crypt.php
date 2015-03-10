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
 * tests for Hm_Nonce
 */
class Hm_Test_Nonce extends PHPUnit_Framework_TestCase {

    public function setUp() {
        require 'bootstrap.php';
        $this->nonce = new Hm_Nonce();
        $this->session = new Hm_Mock_Session();
        $config = new Hm_Mock_Config();
        $this->session->set('nonce_list', array('asdf'));
        $this->nonce->load($this->session, $config, false);
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_nonce_load() {
        $this->assertTrue($this->nonce->validate('asdf'));
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_nonce_validate() {
        $this->assertTrue($this->nonce->validate('asdf'));
        $this->assertEquals(false, $this->nonce->validate('qwer'));
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_nonce_site_key() {
        $this->assertEquals('fakefingerprint', $this->nonce->site_key());
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_nonce_validate_site_key() {
        $this->assertTrue($this->nonce->validate_site_key('fakefingerprint'));
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_nonce_generate() {
        $nonce = $this->nonce->generate();
        $this->assertTrue($this->nonce->validate($nonce));
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_nonce_trim_list() {
        $this->assertTrue($this->nonce->validate('asdf'));
        for ($i = 0; $i < 24; $i++) {
            $this->nonce->generate();
        }
        $this->assertEquals(false, $this->nonce->validate('asdf'));
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_nonce_save() {
        $this->session = new Hm_Mock_Session();
        $this->nonce->save($this->session);
        $this->assertEquals(1, count($this->session->get('nonce_list', array())));
        $this->nonce->generate();
        $this->nonce->save($this->session);
        $this->assertEquals(2, count($this->session->get('nonce_list', array())));
    }
    public function tearDown() {
        unset($this->nonce);
        unset($this->session);
    }
}

?>
