<?php

use PHPUnit\Framework\TestCase;


class Hm_Test_Oauth2 extends TestCase {

    public function setUp(): void {
        require 'bootstrap.php';
        $this->oauth2 = new Hm_Oauth2('client_id', 'secret', 'uri');
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_request_authorization_url() {
        $res = $this->oauth2->request_authorization_url('url', 'scope', 'state', 'hint');
        $this->assertEquals('url?response_type=code&amp;scope=scope&amp;state=state&amp;approval_prompt=force&amp;access_type=offline&amp;client_id=client_id&amp;redirect_uri=uri&amp;login_hint=hint', $res);
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_refresh_token() {
        $res = $this->oauth2->refresh_token('url', 'refresh_token');
        $this->assertEquals(array('unit' => 'test'), $res);
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_request_token() {
        $res = $this->oauth2->request_token('url', 'auth_code');
        $this->assertEquals(array('unit' => 'test'), $res);
    }
    public function tearDown(): void {
        unset($this->oauth2);
    }
}

?>
