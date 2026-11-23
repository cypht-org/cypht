<?php

/**
 * TLS settings for mail server connections
 * @package framework
 * @subpackage tls
 */

/**
 * Whether a saved server should verify its TLS certificate.
 * A missing value means verify. Only an explicit false skips verification.
 * @param mixed $config server or connection config
 * @return bool
 */
function hm_server_tls_verify($config) {
    if (!is_array($config) || !array_key_exists('tls_verify', $config)) {
        return true;
    }
    return (bool) $config['tls_verify'];
}

/**
 * Stream context for a mail-server TLS connection.
 * Peer and hostname checks are always set together.
 * @param bool $verify true to require a valid certificate
 * @return resource
 */
function hm_tls_stream_context($verify = true) {
    $verify = (bool) $verify;
    return stream_context_create(array(
        'ssl' => array(
            'verify_peer' => $verify,
            'verify_peer_name' => $verify,
            'allow_self_signed' => !$verify,
        ),
    ));
}
