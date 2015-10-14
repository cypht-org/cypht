<?php

/**
 * Initial setup
 * @package framework
 * @subpackage setup
 */

require APP_PATH.'lib/module.php';
require APP_PATH.'lib/modules.php';
require APP_PATH.'lib/modules_exec.php';
require APP_PATH.'lib/config.php';
require APP_PATH.'lib/auth.php';
require APP_PATH.'lib/oauth2.php';
require APP_PATH.'lib/session_base.php';
require APP_PATH.'lib/session.php';
require APP_PATH.'lib/format.php';
require APP_PATH.'lib/dispatch.php';
require APP_PATH.'lib/request.php';
require APP_PATH.'lib/cache.php';
require APP_PATH.'lib/output.php';
require APP_PATH.'lib/crypt.php';
require APP_PATH.'lib/db.php';
require APP_PATH.'lib/servers.php';
require APP_PATH.'lib/api.php';

if (!class_exists('Hm_Functions')) {
    /**
     * Used to override built in functions that break unit tests
     * @package framework
     * @subpackage setup
     */
    class Hm_Functions {
        public static function setcookie($name, $value, $lifetime=0, $path='', $domain='', $secure=false, $html_only='') {
            return setcookie($name, $value, $lifetime, $path, $domain, $secure, $html_only);
        }
        public static function header($header) {
            return header($header);
        }
        public static function cease($msg=false) {
            die($msg);
        }
        public static function session_start() {
            return session_start();
        }
        public static function error_log($str) {
            error_log($str);
        }
        public static function c_setopt($handle, $name, $value) {
            curl_setopt($handle, $name, $value);
        }
        public static function c_init() {
            return curl_init();
        }
        public static function c_exec($handle) {
            return curl_exec($handle);
        }
        public static function function_exists($func) {
            return function_exists($func);
        }
    }
}
