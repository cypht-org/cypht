<?php

use PHPUnit\Framework\TestCase;

class Hm_Test_Tls extends TestCase {

    public static function tls_verify_cases(): array {
        return PolicyCases::forPolicy('mail-tls-verification');
    }

    /**
     * @dataProvider tls_verify_cases
     */
    public function test_mail_tls_verification_policy($case): void {
        $verify = hm_server_tls_verify($case['input']['config']);
        $this->assertSame($case['expected']['verify'], $verify);
        $options = stream_context_get_options(hm_tls_stream_context($verify));
        $this->assertSame($verify, $options['ssl']['verify_peer']);
        $this->assertSame($verify, $options['ssl']['verify_peer_name']);
        $this->assertSame(!$verify, $options['ssl']['allow_self_signed']);
    }
}
