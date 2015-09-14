<?php

/**
 * Tweak ini settings
 * @package framework
 * @subpackage setup
 */

/* compress output if possible */
ini_set('zlib.output_compression', 'On');

/* limit cookie life to the session */
ini_set('session.cookie_lifetime', 0);

/* force cookies only */
ini_set('session.use_cookie', 'On');
ini_set('session.use_only_cookies', 'On');

/* strict session mode */
ini_set('session.use_strict_mode', 'On');

/* limit session cookie to HTTP only */
ini_set('session.cookie_httponly', 'On');

/* HTTPS required for session cookie */
if (!$config->get('disable_tls', false)) {
    ini_set('session.cookie_secure', 'On');
}

/* gc max lifetime */
ini_set('session.gc_maxlifetime', 1440); 

/* disable trans sid */
ini_set('session.use_trans_sid', 'Off');

/* don't allow dynamic page caching */
ini_set('session.cache_limiter', 'nocache');

/* session hash mechanism */
ini_set('session.hash_function', 'sha256');

/* disable remote includes */
ini_set('allow_url_include', 0);

/* don't show errors in production */
if (!DEBUG_MODE) {
    ini_set('display_errors', 0);
    ini_set('display_start_up_errors', 0);
}

/* show everthing in debug mode */
else {
    ini_set('display_errors', 1);
    ini_set('display_start_up_errors', 1);
}

/* open base dir */
$base = dirname(dirname(__FILE__)).PATH_SEPARATOR.'/tmp';
if ($config->get('app_data_dir', false) && is_readable($config->get('app_data_dir', false))) {
    $base .= PATH_SEPARATOR.$config->get('app_data_dir', false);
}
if ($config->get('user_settings_dir', false) && is_readable($config->get('user_settings_dir', false))) {
    $base .= PATH_SEPARATOR.$config->get('user_settings_dir', false);
}
if ($config->get('attachment_dir', false) && is_readable($config->get('attachment_dir', false))) {
    $base .= PATH_SEPARATOR.$config->get('attachment_dir', false);
}
if (!defined('HHVM_VERSION')) {
    ini_set('open_basedir', $base);
}
