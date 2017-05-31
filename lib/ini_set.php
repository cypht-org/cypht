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
ini_set('session.use_cookie', 1);
ini_set('session.use_only_cookies', 1);

/* strict session mode */
ini_set('session.use_strict_mode', 1);

/* limit session cookie to HTTP only */
ini_set('session.cookie_httponly', 1);

/* HTTPS required for session cookie */
if (!$config->get('disable_tls', false)) {
    ini_set('session.cookie_secure', 1);
}

/* gc max lifetime */
ini_set('session.gc_maxlifetime', 1440); 

/* disable trans sid */
ini_set('session.use_trans_sid', 0);

/* don't allow dynamic page caching */
ini_set('session.cache_limiter', 'nocache');

/* session hash mechanism */
if ((float) substr(phpversion(), 0, 3) === 5.6) {
    ini_set('session.hash_function', 1);
}
else {
    ini_set('session.hash_function', 'sha256');
}

/* session name */
ini_set('session.name', 'hm_session');

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

$base = dirname(dirname(__FILE__)).PATH_SEPARATOR.'/tmp'.PATH_SEPARATOR.'/dev/urandom';
$disabled = $this->config->get('disable_open_basedir', false);
foreach (array('app_data_dir', 'user_settings_dir', 'attachment_dir') as $dir) {
    if ($config->get($dir, false) && is_readable($config->get($dir, false))) {
        $base .= PATH_SEPARATOR.$config->get($dir, false);
    }
}
if (!defined('HHVM_VERSION') && !$disabled) {
    ini_set('open_basedir', $base);
}
