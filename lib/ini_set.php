<?php

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

?>
