<?php

/**
 * Tweak ini settings
 * @package framework
 * @subpackage setup
 */

/* compress output if possible */
if (version_compare(PHP_VERSION, 8.0, '<')) {
    ini_set('zlib.output_compression', 'On');
}

/* limit cookie life to the session */
ini_set('session.cookie_lifetime', 0);

/* force cookies only */
ini_set('session.use_cookie', 1);
ini_set('session.use_only_cookies', 1);

/* strict session mode */
ini_set('session.use_strict_mode', 1);

/* limit session cookie to HTTP only */
ini_set('session.cookie_httponly', 1);
if (version_compare(PHP_VERSION, 7.3, '>=')) {
    ini_set('session.cookie_samesite', 'Lax');
}

/* HTTPS required for session cookie */
if (!$config->get('disable_tls', false)) {
    ini_set('session.cookie_secure', 1);
}

/* gc max lifetime */
if ($config->get('allow_long_session', false)) {
    // For long sessions, set gc_maxlifetime to match the session duration
    $long_session_days = intval($config->get('long_session_lifetime', 0));
    if ($long_session_days > 0) {
        $gc_lifetime = 60 * 60 * 24 * $long_session_days;
        ini_set('session.gc_maxlifetime', $gc_lifetime);
    } else {
        // Fallback to default if not configured properly
        ini_set('session.gc_maxlifetime', 1440);
    }
} else {
    // Default: 24 minutes for regular sessions
    ini_set('session.gc_maxlifetime', 1440);
}

/* disable trans sid */
ini_set('session.use_trans_sid', 0);

/* don't allow dynamic page caching */
ini_set('session.cache_limiter', 'nocache');

/* session hash mechanism */
if (version_compare(PHP_VERSION, 8.1, '==')) {
    ini_set('session.hash_function', 1);
} else {
    ini_set('session.hash_function', 'sha256');
}

/* session name */
ini_set('session.name', 'hm_session');

/* disable remote includes */
ini_set('allow_url_include', 0);

/* when display_errors is on PHP returns a 200 when it should be a 500 */
ini_set('display_errors', 0);
ini_set('display_start_up_errors', 0);

$tmp_dir = ini_get('upload_tmp_dir') ? ini_get('upload_tmp_dir') : sys_get_temp_dir();
$base = dirname(dirname(__FILE__)).PATH_SEPARATOR.$tmp_dir.PATH_SEPARATOR.'/dev/urandom';
$disabled = $config->get('disable_open_basedir', false);
foreach (array('user_settings_dir', 'attachment_dir') as $dir) {
    if ($config->get($dir, false) && is_readable($config->get($dir, false))) {
        $base .= PATH_SEPARATOR.$config->get($dir, false);
    }
}
if (!$disabled) {
    ini_set('open_basedir', $base);
}
