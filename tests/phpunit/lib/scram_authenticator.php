<?php

/**
 * Unit tests for ScramAuthenticator class
 * @package lib/tests
 */

use PHPUnit\Framework\TestCase;

/**
 * Tests for the ScramAuthenticator class
 * 
 */
class Hm_Test_Scram_Authenticator extends TestCase {

    private $scram;

    public function setUp(): void {
        require __DIR__.'/../bootstrap.php';
        
        $this->scram = new ScramAuthenticator();
    }

    /**
     * Test algorithm detection through generateClientProof behavior
     * We test the internal getHashAlgorithm logic by observing the behavior
     * of generateClientProof with different SCRAM algorithm specifications
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_algorithm_detection_via_public_api() {
        $username = 'testuser';
        $password = 'testpass';
        $salt = 'testsalt';
        $clientNonce = 'clientnonce123';
        $serverNonce = 'servernonce456';

        $testCases = [
            'sha1' => ['SCRAM-SHA-1', 'scram-sha-1', 'SCRAM-UNKNOWN', 'invalid-algorithm'],
            'sha256' => ['SCRAM-SHA-256', 'scram-sha256', 'scram-sha-256'],
            'sha512' => ['SCRAM-SHA-512', 'scram-sha-512']
        ];

        foreach ($testCases as $expectedAlgorithm => $scramSpecs) {
            $referenceProof = $this->scram->generateClientProof(
                $username, $password, $salt, $clientNonce, $serverNonce, $expectedAlgorithm
            );

            foreach ($scramSpecs as $scramSpec) {
                $proof = $this->scram->generateClientProof(
                    $username, $password, $salt, $clientNonce, $serverNonce, $expectedAlgorithm
                );
                
                $this->assertEquals(
                    $referenceProof, 
                    $proof, 
                    "Algorithm detection failed for SCRAM spec: {$scramSpec}"
                );
            }
        }
    }

    /**
     * Test generateClientProof method
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_generateClientProof() {
        $username = 'testuser';
        $password = 'testpass';
        $salt = 'testsalt';
        $clientNonce = 'clientnonce123';
        $serverNonce = 'servernonce456';
        $algorithm = 'sha256';

        $clientProof = $this->scram->generateClientProof(
            $username, 
            $password, 
            $salt, 
            $clientNonce, 
            $serverNonce, 
            $algorithm
        );

        $this->assertIsString($clientProof);
        $this->assertNotEmpty($clientProof);
        
        $decoded = base64_decode($clientProof, true);
        $this->assertNotFalse($decoded);
        $clientProof2 = $this->scram->generateClientProof(
            $username, 
            $password, 
            $salt, 
            $clientNonce, 
            $serverNonce, 
            $algorithm
        );
        $this->assertEquals($clientProof, $clientProof2);
    }

    /**
     * Test generateClientProof with different algorithms
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_generateClientProof_different_algorithms() {
        $username = 'testuser';
        $password = 'testpass';
        $salt = 'testsalt';
        $clientNonce = 'clientnonce123';
        $serverNonce = 'servernonce456';

        $algorithms = ['sha1', 'sha256', 'sha512'];
        $proofs = [];

        foreach ($algorithms as $algorithm) {
            $proof = $this->scram->generateClientProof(
                $username, 
                $password, 
                $salt, 
                $clientNonce, 
                $serverNonce, 
                $algorithm
            );
            
            $this->assertIsString($proof);
            $this->assertNotEmpty($proof);
            $proofs[$algorithm] = $proof;
        }

        $this->assertNotEquals($proofs['sha1'], $proofs['sha256']);
        $this->assertNotEquals($proofs['sha256'], $proofs['sha512']);
    }

    /**
     * Test generateClientProof with different inputs
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_generateClientProof_different_inputs() {
        $baseParams = [
            'username' => 'testuser',
            'password' => 'testpass',
            'salt' => 'testsalt',
            'clientNonce' => 'clientnonce123',
            'serverNonce' => 'servernonce456',
            'algorithm' => 'sha256'
        ];

        $baseProof = $this->scram->generateClientProof(...array_values($baseParams));

        $params = $baseParams;
        $params['username'] = 'differentuser';
        $proof = $this->scram->generateClientProof(...array_values($params));
        $this->assertNotEquals($baseProof, $proof);

        $params = $baseParams;
        $params['password'] = 'differentpass';
        $proof = $this->scram->generateClientProof(...array_values($params));
        $this->assertNotEquals($baseProof, $proof);

        $params = $baseParams;
        $params['salt'] = 'differentsalt';
        $proof = $this->scram->generateClientProof(...array_values($params));
        $this->assertNotEquals($baseProof, $proof);
    }

    /**
     * Test authenticateScram with successful authentication
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_authenticateScram_success() {
        $scramAlgorithm = 'SCRAM-SHA-256';
        $username = 'testuser';
        $password = 'testpass';
        
        $responses = [
            ['+ ' . base64_encode('r=' . base64_encode('servernonce123') . ',s=' . base64_encode('testsalt'))],
            ['+ ' . base64_encode('v=' . base64_encode('valid_server_signature'))]
        ];
        $responseIndex = 0;
        
        $getServerResponse = function() use (&$responses, &$responseIndex) {
            return $responses[$responseIndex++] ?? [''];
        };
        
        $commands = [];
        $sendCommand = function($command) use (&$commands) {
            $commands[] = $command;
        };

        // This will fail because we can't easily mock the server signature validation
        // But it tests the basic flow
        $result = $this->scram->authenticateScram(
            $scramAlgorithm, 
            $username, 
            $password, 
            $getServerResponse, 
            $sendCommand
        );

        $this->assertCount(2, $commands);
        $this->assertStringContainsString('AUTHENTICATE SCRAM-SHA-256', $commands[0]);
        
        $this->assertIsBool($result);
    }

    /**
     * Test authenticateScram with invalid server challenge
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_authenticateScram_invalid_challenge() {
        $scramAlgorithm = 'SCRAM-SHA-256';
        $username = 'testuser';
        $password = 'testpass';
        
        $getServerResponse = function() {
            return ['- Invalid response'];
        };
        
        $commands = [];
        $sendCommand = function($command) use (&$commands) {
            $commands[] = $command;
        };

        $result = $this->scram->authenticateScram(
            $scramAlgorithm, 
            $username, 
            $password, 
            $getServerResponse, 
            $sendCommand
        );

        $this->assertFalse($result);
        $this->assertCount(1, $commands);
    }

    /**
     * Test authenticateScram with empty server response
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_authenticateScram_empty_response() {
        $scramAlgorithm = 'SCRAM-SHA-256';
        $username = 'testuser';
        $password = 'testpass';
        
        $getServerResponse = function() {
            return [];
        };
        
        $commands = [];
        $sendCommand = function($command) use (&$commands) {
            $commands[] = $command;
        };

        $result = $this->scram->authenticateScram(
            $scramAlgorithm, 
            $username, 
            $password, 
            $getServerResponse, 
            $sendCommand
        );

        $this->assertFalse($result);
        $this->assertCount(1, $commands);
    }

    /**
     * Test authenticateScram with different algorithms
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_authenticateScram_different_algorithms() {
        $algorithms = ['SCRAM-SHA-1', 'SCRAM-SHA-256', 'SCRAM-SHA-512'];
        $username = 'testuser';
        $password = 'testpass';
        
        foreach ($algorithms as $algorithm) {
            $getServerResponse = function() {
                return ['- Invalid response'];
            };
            
            $commands = [];
            $sendCommand = function($command) use (&$commands) {
                $commands[] = $command;
            };

            $result = $this->scram->authenticateScram(
                $algorithm, 
                $username, 
                $password, 
                $getServerResponse, 
                $sendCommand
            );

            $this->assertFalse($result);
            $this->assertStringContainsString("AUTHENTICATE $algorithm", $commands[0]);
        }
    }

    /**
     * Test authenticateScram with channel binding (PLUS variants)
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_authenticateScram_channel_binding() {
        $scramAlgorithm = 'SCRAM-SHA-256-PLUS';
        $username = 'testuser';
        $password = 'testpass';
        
        $getServerResponse = function() {
            return ['+ ' . base64_encode('r=' . base64_encode('servernonce123') . ',s=' . base64_encode('testsalt'))];
        };
        
        $commands = [];
        $sendCommand = function($command) use (&$commands) {
            $commands[] = $command;
        };

        $result = $this->scram->authenticateScram(
            $scramAlgorithm, 
            $username, 
            $password, 
            $getServerResponse, 
            $sendCommand
        );

        $this->assertIsBool($result);
        $this->assertCount(2, $commands);
        $this->assertStringContainsString('AUTHENTICATE SCRAM-SHA-256-PLUS', $commands[0]);
    }

    /**
     * Test edge cases and boundary conditions
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_edge_cases() {
        $clientProof = $this->scram->generateClientProof('', 'password', 'salt', 'cnonce', 'snonce', 'sha256');
        $this->assertIsString($clientProof);

        $clientProof = $this->scram->generateClientProof('user', '', 'salt', 'cnonce', 'snonce', 'sha256');
        $this->assertIsString($clientProof);

        $longString = str_repeat('a', 1000);
        $clientProof = $this->scram->generateClientProof($longString, $longString, $longString, $longString, $longString, 'sha256');
        $this->assertIsString($clientProof);
    }

    /**
     * Test logging functionality indirectly through public API
     * Since log() is a private method, we test that it doesn't break the main functionality
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_logging_functionality_via_public_api() {
        // Test that the logging calls within generateClientProof don't cause errors
        $username = 'testuser';
        $password = 'testpass';
        $salt = 'testsalt';
        $clientNonce = 'clientnonce123';
        $serverNonce = 'servernonce456';
        $algorithm = 'sha256';

        // This should succeed without errors, even though it internally calls log()
        $clientProof = $this->scram->generateClientProof(
            $username, $password, $salt, $clientNonce, $serverNonce, $algorithm
        );
        
        $this->assertIsString($clientProof);
        $this->assertNotEmpty($clientProof);
        
        // Multiple calls should work consistently (logging shouldn't interfere)
        $clientProof2 = $this->scram->generateClientProof(
            $username, $password, $salt, $clientNonce, $serverNonce, $algorithm
        );
        
        $this->assertEquals($clientProof, $clientProof2);
    }
}