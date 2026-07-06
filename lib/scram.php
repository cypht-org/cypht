<?php

/**
 * Authenticator
 * @package framework
 * @subpackage crypt
 */

class ScramAuthenticator {

private $hashes = array(
    'sha-1'   => 'sha1',
    'sha1'    => 'sha1',
    'sha-224' => 'sha224',
    'sha224'  => 'sha224',
    'sha-256' => 'sha256',
    'sha256'  => 'sha256',
    'sha-384' => 'sha384',
    'sha384'  => 'sha384',
    'sha-512' => 'sha512',
    'sha512'  => 'sha512'
);

protected function getHashAlgorithm($scramAlgorithm) {
    $parts = explode('-', mb_strtolower($scramAlgorithm));
    if (count($parts) > 2) {
        $hashAlgorithm = implode('-', array_slice($parts, 1));
    } else {
        $hashAlgorithm = $parts[1] ?? '';
    }
    return $this->hashes[$hashAlgorithm] ?? 'sha1'; // Default to sha1 if the algorithm is not found
}
private function log($message) {
    // Use Hm_Debug to add the debug message
    Hm_Debug::add(sprintf($message));
}
public function generateClientProof($username, $password, $salt, $clientNonce, $serverNonce, $algorithm) {
    $iterations = 4096;
    $keyLength = strlen(hash($algorithm, '', true)); // Dynamically determine key length based on algorithm

    $passwordBytes = hash($algorithm, $password, true);
    $saltedPassword = hash_pbkdf2($algorithm, $passwordBytes, $salt, $iterations, $keyLength, true);
    $clientKey = hash_hmac($algorithm, "Client Key", $saltedPassword, true);
    $storedKey = hash($algorithm, $clientKey, true);
    $authMessage = 'n=' . $username . ',r=' . $clientNonce . ',s=' . base64_encode($salt) . ',r=' . $serverNonce;
    $clientSignature = hash_hmac($algorithm, $authMessage, $storedKey, true);
    $clientProof = base64_encode($clientKey ^ $clientSignature);
    $this->log("Client proof generated successfully");
    return $clientProof;
}

public function authenticateScram($scramAlgorithm, $username, $password, $getServerResponse, $sendCommand, $protocol = 'imap', ?callable $nonce_generator = null) {
    $algorithm = $this->getHashAlgorithm($scramAlgorithm);
    $nonce_generator = $nonce_generator ?? static fn() => base64_encode(random_bytes(32));

    // SMTP uses "AUTH <mech>"; IMAP uses "AUTHENTICATE <mech>".
    // SMTP's send_command() appends \r\n internally, so we must not include it here.
    // IMAP's send_command() sends exactly what it receives, so \r\n must be included.
    if ($protocol === 'smtp') {
        $sendCommand('AUTH ' . $scramAlgorithm);
    } else {
        $sendCommand('AUTHENTICATE ' . $scramAlgorithm . "\r\n");
    }
    $response = $getServerResponse();

    $challenge = $this->extractChallenge($response, $protocol);
    if ($challenge !== null) {
        $this->log("Received server challenge: " . $challenge);
        // Extract salt and server nonce from the server's challenge
        $serverChallenge = base64_decode($challenge);
        $parts = explode(',', $serverChallenge);
        $serverNonce = base64_decode(substr($parts[0], strpos($parts[0], "=") + 1));
        $salt = base64_decode(substr($parts[1], strpos($parts[1], "=") + 1));

        // Generate client nonce
        $clientNonce = $nonce_generator();
        $this->log("Generated client nonce: " . $clientNonce);

        // Calculate client proof
        $clientProof = $this->generateClientProof($username, $password, $salt, $clientNonce, $serverNonce, $algorithm);

        // Construct client final message
        $channelBindingData = (mb_stripos($scramAlgorithm, 'plus') !== false) ? 'c=' . base64_encode('tls-unique') . ',' : 'c=biws,';
        $clientFinalMessage = $channelBindingData . 'r=' . $serverNonce . $clientNonce . ',p=' . $clientProof;
        $clientFinalMessageEncoded = base64_encode($clientFinalMessage);
        $this->log("Sending client final message: " . $clientFinalMessageEncoded);

        // Send client final message to server (same \r\n handling as the initial command)
        if ($protocol === 'smtp') {
            $sendCommand($clientFinalMessageEncoded);
        } else {
            $sendCommand($clientFinalMessageEncoded . "\r\n");
        }

        // Verify server's response
        $response = $getServerResponse();
        $challenge2 = $this->extractChallenge($response, $protocol);
        if ($challenge2 !== null) {
            $serverFinalMessage = base64_decode($challenge2);
            $parts = explode(',', $serverFinalMessage);
            $serverProof = substr($parts[0], strpos($parts[0], "=") + 1);

            // Generate server key
            $passwordBytes = hash($algorithm, $password, true);
            $saltedPassword = hash_pbkdf2($algorithm, $passwordBytes, $salt, 4096, strlen(hash($algorithm, '', true)), true);
            $serverKey = hash_hmac($algorithm, "Server Key", $saltedPassword, true);

            // Calculate server signature
            $authMessage = 'n=' . $username . ',r=' . $clientNonce . ',s=' . base64_encode($salt) . ',r=' . $serverNonce;
            $serverSignature = base64_encode(hash_hmac($algorithm, $authMessage, $serverKey, true));

            // Compare server signature with server proof
            if ($serverSignature === $serverProof) {
                $this->log("SCRAM authentication successful");
                return true; // Authentication successful if they match
            } else {
                $this->log("SCRAM authentication failed: Server signature mismatch");
            }
        } else {
            $this->log("SCRAM authentication failed: Invalid server final response");
        }
    } else {
        $this->log("SCRAM authentication failed: Invalid server challenge");
    }
    return false; // Authentication failed
}

private function extractChallenge($response, $protocol) {
    if ($protocol === 'smtp') {
        // SMTP get_response() returns a chunked array: [['334', ['base64data']], ...]
        if (!empty($response) && isset($response[0][0]) && $response[0][0] === '334') {
            return $response[0][1][0] ?? '';
        }
        return null;
    }
    // IMAP get_response() returns raw strings; continuation lines start with '+ '
    if (!empty($response) && mb_substr($response[0], 0, 2) === '+ ') {
        return mb_substr($response[0], 2);
    }
    return null;
}
}