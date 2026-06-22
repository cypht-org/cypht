<?php

/**
 * Unit tests for ScramAuthenticator
 * @package lib/tests
 */

use PHPUnit\Framework\TestCase;

class Hm_Test_Scram_Authenticator extends TestCase {

    private ScramAuthenticator $scram;

    public function setUp(): void {
        $this->scram = new ScramAuthenticator();
    }


    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_getHashAlgorithm_maps_standard_scram_sha256(): void {
        $scram = new TestableScramAuthenticator();
        $this->assertSame('sha256', $scram->hashAlgorithm('SCRAM-SHA-256'));
        $this->assertSame('sha256', $scram->hashAlgorithm('scram-sha-256'));
        $this->assertSame('sha256', $scram->hashAlgorithm('scram-sha256'));
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_getHashAlgorithm_maps_standard_scram_sha1(): void {
        $scram = new TestableScramAuthenticator();
        $this->assertSame('sha1', $scram->hashAlgorithm('SCRAM-SHA-1'));
        $this->assertSame('sha1', $scram->hashAlgorithm('scram-sha-1'));
        $this->assertSame('sha1', $scram->hashAlgorithm('scram-sha1'));
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_getHashAlgorithm_maps_standard_scram_sha512(): void {
        $scram = new TestableScramAuthenticator();
        $this->assertSame('sha512', $scram->hashAlgorithm('SCRAM-SHA-512'));
        $this->assertSame('sha512', $scram->hashAlgorithm('scram-sha-512'));
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_getHashAlgorithm_defaults_to_sha1_for_unknown_spec(): void {
        $scram = new TestableScramAuthenticator();
        $this->assertSame('sha1', $scram->hashAlgorithm('SCRAM-UNKNOWN'));
        $this->assertSame('sha1', $scram->hashAlgorithm('invalid-algo'));
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_generateClientProof_returns_valid_base64_string(): void {
        $proof = $this->scram->generateClientProof('user', 'pass', 'salt', 'cnonce', 'snonce', 'sha256');

        $this->assertIsString($proof);
        $this->assertNotEmpty($proof);
        $this->assertNotFalse(base64_decode($proof, true));
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_generateClientProof_is_deterministic(): void {
        $args = ['user', 'pass', 'salt', 'cnonce', 'snonce', 'sha256'];

        $this->assertSame(
            $this->scram->generateClientProof(...$args),
            $this->scram->generateClientProof(...$args)
        );
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_generateClientProof_differs_across_algorithms(): void {
        $args = ['user', 'pass', 'salt', 'cnonce', 'snonce'];

        $sha1   = $this->scram->generateClientProof(...[...$args, 'sha1']);
        $sha256 = $this->scram->generateClientProof(...[...$args, 'sha256']);
        $sha512 = $this->scram->generateClientProof(...[...$args, 'sha512']);

        $this->assertNotSame($sha1, $sha256);
        $this->assertNotSame($sha256, $sha512);
        $this->assertNotSame($sha1, $sha512);
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_generateClientProof_differs_when_credentials_change(): void {
        $base = $this->scram->generateClientProof('user', 'pass', 'salt', 'cnonce', 'snonce', 'sha256');

        $this->assertNotSame($base,
            $this->scram->generateClientProof('other',  'pass',  'salt',  'cnonce', 'snonce', 'sha256'));
        $this->assertNotSame($base,
            $this->scram->generateClientProof('user',   'other', 'salt',  'cnonce', 'snonce', 'sha256'));
        $this->assertNotSame($base,
            $this->scram->generateClientProof('user',   'pass',  'other', 'cnonce', 'snonce', 'sha256'));
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_authenticateScram_sends_authenticate_command_first(): void {
        $commands = [];
        $sendCommand = function($cmd) use (&$commands) { $commands[] = $cmd; };

        $this->scram->authenticateScram(
            'SCRAM-SHA-256', 'u', 'p',
            fn() => ['- error'],
            $sendCommand
        );

        $this->assertCount(1, $commands);
        $this->assertStringContainsString('AUTHENTICATE SCRAM-SHA-256', $commands[0]);
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_authenticateScram_returns_false_on_invalid_server_challenge(): void {
        $commands = [];

        $result = $this->scram->authenticateScram(
            'SCRAM-SHA-256', 'u', 'p',
            fn() => ['- error'],
            function($cmd) use (&$commands) { $commands[] = $cmd; }
        );

        $this->assertFalse($result);
        $this->assertCount(1, $commands); // no second command sent
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_authenticateScram_returns_false_on_empty_server_response(): void {
        $result = $this->scram->authenticateScram(
            'SCRAM-SHA-256', 'u', 'p',
            fn() => [],
            function($cmd) {}
        );

        $this->assertFalse($result);
    }

    /**
     * Builds a fully correct server challenge and final message so the entire
     * SCRAM flow executes and the signature check passes.
     *
     * The nonce_generator is injected so we know the client nonce and can
     * pre-compute the server signature that authenticateScram will verify.
     *
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_authenticateScram_returns_true_when_server_signature_is_valid(): void {
        $username    = 'testuser';
        $password    = 'testpass';
        $salt        = 'testsalt';
        $clientNonce = 'fixed-client-nonce';
        $serverNonce = 'server-nonce-123';
        $algorithm   = 'sha256';

        // Pre-compute the server signature the same way authenticateScram does
        $passwordBytes  = hash($algorithm, $password, true);
        $keyLen         = strlen(hash($algorithm, '', true));
        $saltedPassword = hash_pbkdf2($algorithm, $passwordBytes, $salt, 4096, $keyLen, true);
        $serverKey      = hash_hmac($algorithm, 'Server Key', $saltedPassword, true);
        $authMessage    = 'n=' . $username
            . ',r=' . $clientNonce
            . ',s=' . base64_encode($salt)
            . ',r=' . $serverNonce;
        $serverSignature = base64_encode(hash_hmac($algorithm, $authMessage, $serverKey, true));

        // Build the two server responses authenticateScram expects
        $challenge    = base64_encode('r=' . base64_encode($serverNonce) . ',s=' . base64_encode($salt));
        $finalMessage = base64_encode('v=' . $serverSignature);

        $responses = [['+ ' . $challenge], ['+ ' . $finalMessage]];
        $idx = 0;
        $getServerResponse = function() use (&$responses, &$idx) {
            return $responses[$idx++] ?? [''];
        };

        $result = $this->scram->authenticateScram(
            'SCRAM-SHA-256', $username, $password,
            $getServerResponse,
            function($cmd) {},
            'imap',
            fn() => $clientNonce   // deterministic nonce so authMessage is predictable
        );

        $this->assertTrue($result);
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_authenticateScram_returns_false_when_server_signature_is_wrong(): void {
        $challenge    = base64_encode('r=' . base64_encode('snonce') . ',s=' . base64_encode('salt'));
        $finalMessage = base64_encode('v=thisisawrongsignature');

        $responses = [['+ ' . $challenge], ['+ ' . $finalMessage]];
        $idx = 0;
        $getServerResponse = function() use (&$responses, &$idx) {
            return $responses[$idx++] ?? [''];
        };

        $result = $this->scram->authenticateScram(
            'SCRAM-SHA-256', 'u', 'p',
            $getServerResponse,
            function($cmd) {},
            fn() => 'cnonce'
        );

        $this->assertFalse($result);
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_authenticateScram_uses_algorithm_from_scram_spec(): void {
        // Build a valid exchange using sha1 (from SCRAM-SHA-1)
        $username    = 'u';
        $password    = 'p';
        $salt        = 's';
        $clientNonce = 'cn';
        $serverNonce = 'sn';
        $algorithm   = 'sha1';

        $passwordBytes  = hash($algorithm, $password, true);
        $keyLen         = strlen(hash($algorithm, '', true));
        $saltedPassword = hash_pbkdf2($algorithm, $passwordBytes, $salt, 4096, $keyLen, true);
        $serverKey      = hash_hmac($algorithm, 'Server Key', $saltedPassword, true);
        $authMessage    = 'n=' . $username
            . ',r=' . $clientNonce
            . ',s=' . base64_encode($salt)
            . ',r=' . $serverNonce;
        $serverSignature = base64_encode(hash_hmac($algorithm, $authMessage, $serverKey, true));

        $challenge    = base64_encode('r=' . base64_encode($serverNonce) . ',s=' . base64_encode($salt));
        $finalMessage = base64_encode('v=' . $serverSignature);

        $responses = [['+ ' . $challenge], ['+ ' . $finalMessage]];
        $idx = 0;

        // SCRAM-SHA-1 must resolve to sha1 for the signature to match
        $result = $this->scram->authenticateScram(
            'SCRAM-SHA-1', $username, $password,
            function() use (&$responses, &$idx) { return $responses[$idx++] ?? ['']; },
            function($cmd) {},
            'imap',
            fn() => $clientNonce
        );

        $this->assertTrue($result);
    }
}
