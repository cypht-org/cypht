<?php

/**
 * Cryptographic helpers for the MCP server and REST API
 * @package modules
 * @subpackage mcp
 */

if (!defined('DEBUG_MODE')) { die(); }

/**
 * Key handling for connections and tokens
 *
 * Each connection has a random 256 bit key (K). The Cypht login password of the
 * user, which decrypts the user settings, is stored sealed with K. K itself is
 * only stored wrapped with keys derived from the tokens issued to the connection,
 * and tokens are only stored as SHA-256 hashes. Reading the database is therefore
 * not enough to recover the password: a valid token is required.
 * @subpackage mcp/lib
 */
class Hm_MCP_Crypto {

    /* sealed value format version */
    const VERSION = 'm1';

    /* XChaCha20-Poly1305 (libsodium) */
    const ALG_XCHACHA = 1;

    /* AES-256-GCM (OpenSSL), used when libsodium is unavailable */
    const ALG_AES_GCM = 2;

    /* size in bytes of connection keys and token secrets */
    const KEY_BYTES = 32;

    /**
     * @return string random connection key
     */
    public static function random_key() {
        return random_bytes(self::KEY_BYTES);
    }

    /**
     * Create a new random token
     * @param string $prefix readable prefix, for example "cyp_at_"
     * @return string
     */
    public static function new_token($prefix) {
        return $prefix.self::b64url(random_bytes(self::KEY_BYTES));
    }

    /**
     * Lookup hash of a token
     * @param string $token token value
     * @return string hex encoded SHA-256
     */
    public static function token_hash($token) {
        return hash('sha256', (string) $token);
    }

    /**
     * Derive a purpose specific key from a token
     * @param string $token token value
     * @param string $purpose context label
     * @return string 32 byte key
     */
    public static function derive($token, $purpose) {
        return hash_hkdf('sha256', (string) $token, self::KEY_BYTES, 'cypht-mcp:'.$purpose);
    }

    /**
     * @return int algorithm used for new sealed values
     */
    public static function default_alg() {
        if (function_exists('sodium_crypto_aead_xchacha20poly1305_ietf_encrypt')) {
            return self::ALG_XCHACHA;
        }
        return self::ALG_AES_GCM;
    }

    /**
     * Encrypt and authenticate a value
     * @param string $plaintext value to protect
     * @param string $key 32 byte key
     * @param string $aad additional authenticated data that must match when opening
     * @param int|null $alg algorithm, default_alg() when null
     * @return string printable sealed value
     */
    public static function seal($plaintext, $key, $aad = '', $alg = null) {
        if (strlen($key) !== self::KEY_BYTES) {
            throw new InvalidArgumentException('Invalid key length');
        }
        $alg = $alg ?? self::default_alg();
        $header = self::VERSION.chr($alg);
        $aad = $header.$aad;
        if ($alg === self::ALG_XCHACHA) {
            $nonce = random_bytes(SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_NPUBBYTES);
            $cipher = sodium_crypto_aead_xchacha20poly1305_ietf_encrypt((string) $plaintext, $aad, $nonce, $key);
        } elseif ($alg === self::ALG_AES_GCM) {
            $nonce = random_bytes(12);
            $tag = '';
            $cipher = openssl_encrypt((string) $plaintext, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $nonce, $tag, $aad, 16);
            if ($cipher === false) {
                throw new RuntimeException('Encryption failed');
            }
            $cipher .= $tag;
        } else {
            throw new InvalidArgumentException('Unknown algorithm');
        }
        return self::b64url($header.$nonce.$cipher);
    }

    /**
     * Decrypt a sealed value
     * @param string $sealed value from seal()
     * @param string $key 32 byte key
     * @param string $aad additional authenticated data used when sealing
     * @return string|false plaintext, or false if the value is invalid or was modified
     */
    public static function open($sealed, $key, $aad = '') {
        if (!is_string($sealed) || strlen($key) !== self::KEY_BYTES) {
            return false;
        }
        $raw = self::b64url_decode($sealed);
        if ($raw === false || strlen($raw) < 3 || substr($raw, 0, 2) !== self::VERSION) {
            return false;
        }
        $alg = ord($raw[2]);
        $header = substr($raw, 0, 3);
        $aad = $header.$aad;
        $body = substr($raw, 3);
        if ($alg === self::ALG_XCHACHA) {
            if (!function_exists('sodium_crypto_aead_xchacha20poly1305_ietf_decrypt')) {
                return false;
            }
            $nonce_len = SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_NPUBBYTES;
            if (strlen($body) < $nonce_len + SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_ABYTES) {
                return false;
            }
            try {
                $res = sodium_crypto_aead_xchacha20poly1305_ietf_decrypt(substr($body, $nonce_len), $aad, substr($body, 0, $nonce_len), $key);
            } catch (SodiumException $e) {
                return false;
            }
            return $res === false ? false : $res;
        }
        if ($alg === self::ALG_AES_GCM) {
            if (strlen($body) < 12 + 16) {
                return false;
            }
            $nonce = substr($body, 0, 12);
            $tag = substr($body, -16);
            $cipher = substr($body, 12, -16);
            $res = openssl_decrypt($cipher, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $nonce, $tag, $aad);
            return $res === false ? false : $res;
        }
        return false;
    }

    /**
     * Wrap a connection key with a token
     * @param string $connection_key 32 byte key
     * @param string $token token value
     * @param string $aad binding data
     * @return string
     */
    public static function wrap_key($connection_key, $token, $aad) {
        return self::seal($connection_key, self::derive($token, 'wrap'), $aad);
    }

    /**
     * Unwrap a connection key with a token
     * @param string $wrapped value from wrap_key()
     * @param string $token token value
     * @param string $aad binding data
     * @return string|false 32 byte key
     */
    public static function unwrap_key($wrapped, $token, $aad) {
        $key = self::open($wrapped, self::derive($token, 'wrap'), $aad);
        if ($key === false || strlen($key) !== self::KEY_BYTES) {
            return false;
        }
        return $key;
    }

    /**
     * PKCE S256 code challenge for a verifier (RFC 7636)
     * @param string $verifier code verifier
     * @return string
     */
    public static function pkce_s256($verifier) {
        return self::b64url(hash('sha256', (string) $verifier, true));
    }

    /**
     * Constant time string comparison
     * @param string $known expected value
     * @param string $user supplied value
     * @return bool
     */
    public static function equals($known, $user) {
        return is_string($known) && is_string($user) && hash_equals($known, $user);
    }

    /**
     * @param string $bytes raw bytes
     * @return string base64url without padding
     */
    public static function b64url($bytes) {
        return rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');
    }

    /**
     * @param string $value base64url text
     * @return string|false raw bytes
     */
    public static function b64url_decode($value) {
        if (!is_string($value) || !preg_match('/^[A-Za-z0-9_-]*$/', $value)) {
            return false;
        }
        $pad = strlen($value) % 4;
        if ($pad === 1) {
            return false;
        }
        if ($pad) {
            $value .= str_repeat('=', 4 - $pad);
        }
        return base64_decode(strtr($value, '-_', '+/'), true);
    }

    /**
     * Random identifier
     * @param string $prefix readable prefix
     * @param int $bytes random bytes
     * @return string
     */
    public static function random_id($prefix = '', $bytes = 16) {
        return $prefix.bin2hex(random_bytes($bytes));
    }
}
