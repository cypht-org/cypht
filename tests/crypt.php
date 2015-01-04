<?php

class Hm_Test_Crypt extends PHPUnit_Framework_TestCase {

    /* tests for Hm_Crypt */
    public function test_ciphertext() {
        $cipher = Hm_Crypt::ciphertext('test', 'testkey');
        $this->assertFalse($cipher == 'test');
    }
    public function test_plaintext() {
        $cipher = Hm_Crypt::ciphertext('test', 'testkey');
        $plain = rtrim(Hm_Crypt::plaintext($cipher, 'testkey'), "\0");
        $this->assertEquals('test', $plain);
    }
    public function test_iv_size() {
        $this->assertEquals(16, Hm_Crypt::iv_size());
    }
    public function test_unique_id() {
        $this->assertEquals(24, strlen(base64_decode(Hm_Crypt::unique_id(24))));
        $this->assertEquals(48, strlen(base64_decode(Hm_Crypt::unique_id(48))));
        $this->assertEquals(128, strlen(base64_decode(Hm_Crypt::unique_id())));
    }

    /* test for Hm_Nonce */
    public function test_nonce_load() {
        $session = new Hm_Mock_Session();
        $config = new Hm_Mock_Config();
        $session->set('nonce_list', array('asdf'));
        Hm_Nonce::load($session, $config, false);
        $this->assertTrue(Hm_Nonce::validate('asdf'));
    }
    public function test_nonce_validate() {
        $this->assertTrue(Hm_Nonce::validate('asdf'));
        $this->assertEquals(false, Hm_Nonce::validate('qwer'));
    }
    public function test_nonce_site_key() {
        $this->assertEquals('fakefingerprint', Hm_Nonce::site_key());
    }
    public function test_nonce_validate_site_key() {
        $this->assertTrue(Hm_Nonce::validate_site_key('fakefingerprint'));
    }
    public function test_nonce_generate() {
        $nonce = Hm_Nonce::generate();
        $this->assertTrue(Hm_Nonce::validate($nonce));
    }
    public function test_nonce_trim_list() {
        $this->assertTrue(Hm_Nonce::validate('asdf'));
        Hm_Nonce::generate();
        Hm_Nonce::generate();
        Hm_Nonce::generate();
        $this->assertEquals(false, Hm_Nonce::validate('asdf'));
    }
    public function test_nonce_save() {
        $session = new Hm_Mock_Session();
        Hm_Nonce::save($session);
        $this->assertEquals(4, count($session->get('nonce_list', array())));
    }
}

?>
