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

private function getHashAlgorithm($scramAlgorithm) {
    $parts = explode('-', mb_strtolower($scramAlgorithm));
    return $this->hashes[$parts[1]] ?? 'sha1'; // Default to sha1 if the algorithm is not found
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

public function authenticateScram($scramAlgorithm, $username, $password, $getServerResponse, $sendCommand) {
    $algorithm = $this->getHashAlgorithm($scramAlgorithm);

    // Send initial SCRAM command
    $scramCommand = 'AUTHENTICATE ' . $scramAlgorithm . "\r\n";
    $sendCommand($scramCommand);
    $response = $getServerResponse();
    if (!empty($response) && mb_substr($response[0], 0, 2) == '+ ') {
        $this->log("Received server challenge: " . $response[0]);
        // Extract salt and server nonce from the server's challenge
        $serverChallenge = base64_decode(mb_substr($response[0], 2));
        $parts = explode(',', $serverChallenge);
        $serverNonce = base64_decode(substr($parts[0], strpos($parts[0], "=") + 1));
        $salt = base64_decode(substr($parts[1], strpos($parts[1], "=") + 1));

        // Generate client nonce
        $clientNonce = base64_encode(random_bytes(32));
        $this->log("Generated client nonce: " . $clientNonce);

        // Calculate client proof
        $clientProof = $this->generateClientProof($username, $password, $salt, $clientNonce, $serverNonce, $algorithm);

        // Construct client final message
        $channelBindingData = (mb_stripos($scramAlgorithm, 'plus') !== false) ? 'c=' . base64_encode('tls-unique') . ',' : 'c=biws,';
        $clientFinalMessage = $channelBindingData . 'r=' . $serverNonce . $clientNonce . ',p=' . $clientProof;
        $clientFinalMessageEncoded = base64_encode($clientFinalMessage);
        $this->log("Sending client final message: " . $clientFinalMessageEncoded);
        // Send client final message to server
        $sendCommand($clientFinalMessageEncoded . "\r\n");

        // Verify server's response
        $response = $getServerResponse();
        if (!empty($response) && mb_substr($response[0], 0, 2) == '+ ') {
            $serverFinalMessage = base64_decode(mb_substr($response[0], 2));
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
}