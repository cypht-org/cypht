<?php

use PHPUnit\Framework\TestCase;

require_once APP_PATH.'modules/mcp/hm-mcp.php';

/**
 * tests for Hm_MCP_Crypto
 */
class Hm_Test_MCP_Crypto extends TestCase {

    public static function algorithms() {
        $algs = [[Hm_MCP_Crypto::ALG_AES_GCM]];
        if (function_exists('sodium_crypto_aead_xchacha20poly1305_ietf_encrypt')) {
            $algs[] = [Hm_MCP_Crypto::ALG_XCHACHA];
        }
        return $algs;
    }

    /**
     * @dataProvider algorithms
     */
    public function test_seal_round_trip($alg) {
        $key = Hm_MCP_Crypto::random_key();
        $sealed = Hm_MCP_Crypto::seal('secret value ñ', $key, 'aad', $alg);
        $this->assertMatchesRegularExpression('/^[A-Za-z0-9_-]+$/', $sealed);
        $this->assertStringNotContainsString('secret', $sealed);
        $this->assertSame('secret value ñ', Hm_MCP_Crypto::open($sealed, $key, 'aad'));
        $this->assertNotSame($sealed, Hm_MCP_Crypto::seal('secret value ñ', $key, 'aad', $alg));
    }

    /**
     * @dataProvider algorithms
     */
    public function test_open_rejects_wrong_key_aad_and_tampering($alg) {
        $key = Hm_MCP_Crypto::random_key();
        $sealed = Hm_MCP_Crypto::seal('payload', $key, 'aad', $alg);
        $this->assertFalse(Hm_MCP_Crypto::open($sealed, Hm_MCP_Crypto::random_key(), 'aad'));
        $this->assertFalse(Hm_MCP_Crypto::open($sealed, $key, 'other'));
        $raw = Hm_MCP_Crypto::b64url_decode($sealed);
        $raw[strlen($raw) - 1] = chr(ord($raw[strlen($raw) - 1]) ^ 1);
        $this->assertFalse(Hm_MCP_Crypto::open(Hm_MCP_Crypto::b64url($raw), $key, 'aad'));
        $this->assertFalse(Hm_MCP_Crypto::open('not sealed!', $key, 'aad'));
        $this->assertFalse(Hm_MCP_Crypto::open('', $key, 'aad'));
        $this->assertFalse(Hm_MCP_Crypto::open($sealed, 'short', 'aad'));
    }

    public function test_seal_requires_a_full_length_key() {
        $this->expectException(InvalidArgumentException::class);
        Hm_MCP_Crypto::seal('x', 'short');
    }

    public function test_tokens_and_hashes() {
        $token = Hm_MCP_Crypto::new_token('cyp_at_');
        $this->assertMatchesRegularExpression('/^cyp_at_[A-Za-z0-9_-]{43}$/', $token);
        $this->assertNotSame($token, Hm_MCP_Crypto::new_token('cyp_at_'));
        $this->assertSame(64, strlen(Hm_MCP_Crypto::token_hash($token)));
        $this->assertNotSame(Hm_MCP_Crypto::derive($token, 'wrap'), Hm_MCP_Crypto::derive($token, 'other'));
        $this->assertNotSame(hash('sha256', $token, true), Hm_MCP_Crypto::derive($token, 'wrap'));
    }

    public function test_wrap_and_unwrap_key() {
        $key = Hm_MCP_Crypto::random_key();
        $token = Hm_MCP_Crypto::new_token('cyp_pat_');
        $wrapped = Hm_MCP_Crypto::wrap_key($key, $token, 'binding');
        $this->assertSame($key, Hm_MCP_Crypto::unwrap_key($wrapped, $token, 'binding'));
        $this->assertFalse(Hm_MCP_Crypto::unwrap_key($wrapped, Hm_MCP_Crypto::new_token('cyp_pat_'), 'binding'));
        $this->assertFalse(Hm_MCP_Crypto::unwrap_key($wrapped, $token, 'other binding'));
    }

    public function test_pkce_s256_matches_rfc7636_example() {
        $this->assertSame('E9Melhoa2OwvFrEMTJguCHaoeK1t8URWbuGJSstw-cM',
            Hm_MCP_Crypto::pkce_s256('dBjftJeZ4CVP-mB92K27uhbUJU1p1r_wW1gFWFOEjXk'));
    }

    public function test_b64url() {
        $bytes = random_bytes(31);
        $this->assertSame($bytes, Hm_MCP_Crypto::b64url_decode(Hm_MCP_Crypto::b64url($bytes)));
        $this->assertFalse(Hm_MCP_Crypto::b64url_decode('a+b/'));
        $this->assertFalse(Hm_MCP_Crypto::b64url_decode('abcde'));
    }
}
