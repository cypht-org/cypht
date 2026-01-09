<?php

use PHPUnit\Framework\TestCase;

/**
 * tests for Hm_Request_Key
 */
class Hm_Test_Request_Key extends TestCase {

    public function setUp(): void {
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
