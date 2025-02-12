<?php

/**
 * Initial setup
 * @package framework
 * @subpackage setup
 */

const VERSION = .1;

/* load the framework */
require APP_PATH.'lib/repository.php';
require APP_PATH.'lib/module.php';
require APP_PATH.'lib/modules.php';
require APP_PATH.'lib/modules_exec.php';
require APP_PATH.'lib/config.php';
require APP_PATH.'lib/auth.php';
require APP_PATH.'lib/oauth2.php';
require APP_PATH.'lib/session_base.php';
require APP_PATH.'lib/session_php.php';
require APP_PATH.'lib/session_db.php';
require APP_PATH.'lib/session_memcached.php';
require APP_PATH.'lib/session_redis.php';
require APP_PATH.'lib/format.php';
require APP_PATH.'lib/dispatch.php';
require APP_PATH.'lib/request.php';
require APP_PATH.'lib/cache.php';
require APP_PATH.'lib/output.php';
require APP_PATH.'lib/crypt.php';
require APP_PATH.'lib/crypt_sodium.php';
require APP_PATH.'lib/sodium_compat.php';
require APP_PATH.'lib/scram.php';
require APP_PATH.'lib/environment.php';
require APP_PATH.'lib/db.php';
require APP_PATH.'lib/servers.php';
require APP_PATH.'lib/api.php';
require APP_PATH.'lib/webdav_formats.php';
require APP_PATH.'lib/js_libs.php';

require_once APP_PATH.'modules/core/functions.php';

/* load random bytes polyfill if needed */
if (!function_exists('random_bytes')) {
    require VENDOR_PATH.'paragonie/random_compat/lib/random.php';
}

/* check for and load the correct libsodium interface */
if (!defined('LIBSODIUM')) {
    if (extension_loaded('libsodium') && function_exists('\Sodium\crypto_pwhash_str_verify')) {
        define('LIBSODIUM', true);
        class Hm_Sodium_Compat extends Hm_Sodium_PECL {}
    } elseif (extension_loaded('sodium') && function_exists('sodium_crypto_pwhash_str_verify')) {
        define('LIBSODIUM', true);
        class Hm_Sodium_Compat extends Hm_Sodium_PHP {}
    } else {
        define('LIBSODIUM', false);
    }
}

if (!class_exists('Hm_Functions')) {
    /**
     * Used to override built in functions that break unit tests
     * @package framework
     * @subpackage setup
     */
    class Hm_Functions {

        /**
         * @param string $name
         * @param string $value
         * @return boolean
         */
        public static function setcookie($name, $value, $lifetime = 0, $path = '', $domain = '', $secure = false, $html_only = false, $same_site = 'Strict') {
            $prefix = ($lifetime != 0 && $lifetime < time()) ? 'Deleting' : 'Setting';
            Hm_Debug::add(sprintf('%s cookie: name: %s, lifetime: %s, path: %s, domain: %s, secure: %s, html_only %s',$prefix, $name, $lifetime, $path, $domain, $secure, $html_only), 'info');
            return setcookie($name, $value, [
                'expires' => $lifetime,
                'path' => $path,
                'domain' => $domain,
                'secure' => $secure,
                'httponly' => $html_only,
                'samesite' => $same_site
            ]);
        }

        /**
         * @param string $header
         * @return void
         */
        public static function header($header) {
            header($header);
        }

        /**
         * @param string $msg
         * @return null
         */
        public static function cease($msg = '') {
            die($msg);
        }

        /**
         * @return boolean
         */
        public static function session_destroy() {
            if (session_status() === PHP_SESSION_ACTIVE) {
                return session_destroy();
            }
            return false;
        }

        /**
         * @return boolean
         */
        public static function session_start() {
            return session_start();
        }

        /**
         * @return boolean
         */
        public static function error_log($str) {
            return error_log($str);
        }

        /**
         * @param resource|false $handle
         * @param integer $name
         */
        public static function c_setopt($handle, $name, $value) {
            if ($handle !== false) {
                curl_setopt($handle, $name, $value);
            }
        }

        /**
         * @return resource|false
         */
        public static function c_init() {
            if (extension_loaded('curl')) {
                return curl_init();
            } else {
                Hm_Msgs::add('Please enable the cURL extension.', 'warning');
                return false;
            }
        }

        /**
         * @param resource $handle
         */
        public static function c_exec($handle) {
            $response = curl_exec($handle);
            if ($response === false) {
                $error = curl_error($handle);
                Hm_Msgs::add('cURL error: '.$error, 'danger');
            }
            return $response;
        }

        /**
         * @param resource $handle
         */
        public static function c_status($ch) {
            return curl_getinfo($ch, CURLINFO_HTTP_CODE);
        }

        /**
         * @param string $func
         */
        public static function function_exists($func) {
            return function_exists($func);
        }

        /**
         * @param string $class
         */
        public static function class_exists($class) {
            return class_exists($class, false);
        }

        /**
         * @param integer $size
         */
        public static function random_bytes($size) {
            return random_bytes($size);
        }

        /**
         * @return Memcached
         */
        public static function memcached() {
            return new Memcached();
        }

        /**
         * @return Redis
         */
        public static function redis() {
            return new Redis();
        }

        /**
         * @param integer $type input type
         * @param array $filters filter list
         * @return array filtered list
         */
        public static function filter_input_array($type, $filters) {
            /*
            In FastCGI, filter_input_array does not work as intended with INPUT_SERVER because its stored copy of data doesn't get modified during the request.
            So we need to explicitly access the $_SERVER.
            */
            if ($type === INPUT_SERVER) {
                $value = array();
                foreach ($filters as $var => $flag) {
                    if (isset($_SERVER[$var])) {
                        $value[$var] = filter_var($_SERVER[$var], $flag);
                    } else {
                        $value[$var] = null;
                    }
                }
                return $value;
            }
            return filter_input_array($type, $filters, false);
        }

        /**
         * @param string $server host to connect to
         * @param integer $port port to connect to
         * @param integer $errno error number
         * @param string $errstr error string
         * @param integer $mode connection mode
         * @param resource $ctx context
         */
        public static function stream_socket_client($server, $port, &$errno, &$errstr, $timeout, $mode, $ctx) {
            return @stream_socket_client($server.':'.$port, $errno, $errstr, $timeout, $mode, $ctx);
        }

        /**
         * @param resource $resource socket connection
         * @return boolean
         */
        public static function stream_ended($resource) {
            if (!is_resource($resource) || feof($resource)) {
                return true;
            }
            return false;
        }
        /**
         * @param resource $socket socket connection to flip to TLS
         * @return boolean
         */
        public static function stream_socket_enable_crypto($socket, $type) {
            return stream_socket_enable_crypto($socket, true, $type);
        }
    }
}

/**
 * See if a function already exists
 * @param string $name function name to check
 * @return boolean
 */
function hm_exists($name) {
    $bt = debug_backtrace();
    $caller = array_shift($bt);
    $module = hm_get_module_from_path($caller['file']);
    if (function_exists($name)) {
        Hm_Debug::add(sprintf('Function in %s replaced: %s', $module, $name), 'warning');
        return true;
    }
    return false;
}

/**
 * Return the module set name from a file path
 * @param string $path file path
 * @return string
 */
function hm_get_module_from_path($path) {
    $data = pathinfo($path);
    if (!is_array($data) || !array_key_exists('dirname', $data)) {
        return 'unknown';
    }
    $parts = array_reverse(explode(DIRECTORY_SEPARATOR, $data['dirname']));
    foreach ($parts as $i => $v) {
        if ($v == 'modules') {
            return $parts[($i - 1)];
        }
    }
    return 'unknown';
}
