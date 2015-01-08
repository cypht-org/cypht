<?php

class Hm_Test_Crypt extends PHPUnit_Framework_TestCase {

    public function setUp() {
        $this->crypt = new Hm_Crypt();
        $this->nonce = new Hm_Nonce();
    }

    /* tests for Hm_Crypt */
    public function test_ciphertext() {
        $cipher = $this->crypt->ciphertext('test', 'testkey');
        $this->assertFalse($cipher == 'test');
    }
    public function test_plaintext() {
        $cipher = $this->crypt->ciphertext('test', 'testkey');
        $plain = rtrim($this->crypt->plaintext($cipher, 'testkey'), "\0");
        $this->assertEquals('test', $plain);
    }
    public function test_iv_size() {
        $this->assertEquals(16, $this->crypt->iv_size());
    }
    public function test_unique_id() {
        $this->assertEquals(24, strlen(base64_decode($this->crypt->unique_id(24))));
        $this->assertEquals(48, strlen(base64_decode($this->crypt->unique_id(48))));
        $this->assertEquals(128, strlen(base64_decode($this->crypt->unique_id())));
    }

    /* test for Hm_Nonce */
    public function test_nonce_load() {
        $session = new Hm_Mock_Session();
        $config = new Hm_Mock_Config();
        $session->set('nonce_list', array('asdf'));
        $this->nonce->load($session, $config, false);
        $this->assertTrue($this->nonce->validate('asdf'));
    }
    public function test_nonce_validate() {
        $this->assertTrue($this->nonce->validate('asdf'));
        $this->assertEquals(false, $this->nonce->validate('qwer'));
    }
    public function test_nonce_site_key() {
        $this->assertEquals('fakefingerprint', $this->nonce->site_key());
    }
    public function test_nonce_validate_site_key() {
        $this->assertTrue($this->nonce->validate_site_key('fakefingerprint'));
    }
    public function test_nonce_generate() {
        $nonce = $this->nonce->generate();
        $this->assertTrue($this->nonce->validate($nonce));
    }
    public function test_nonce_trim_list() {
        $this->assertTrue($this->nonce->validate('asdf'));
        $this->nonce->generate();
        $this->nonce->generate();
        $this->nonce->generate();
        $this->assertEquals(false, $this->nonce->validate('asdf'));
    }
    public function test_nonce_save() {
        $session = new Hm_Mock_Session();
        $this->nonce->save($session);
        $this->assertEquals(4, count($session->get('nonce_list', array())));
    }
    public function tearDown() {
        unset($this->crypt);
        unset($this->nonce);
    }
}

?>
