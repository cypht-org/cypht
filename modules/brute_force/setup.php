<?php

/**
 * Brute force login protection module set
 *
 * Tracks failed login attempts per IP address and per username.
 * After a configurable number of consecutive failures (default: 5) the IP
 * and/or username are locked out for a configurable duration (default: 15 min).
 *
 * Configuration options in config/app.php (all optional):
 *   'brute_force_max_attempts'    => 5     // failures before lockout
 *   'brute_force_lockout_duration'=> 900   // lockout length in seconds
 */
if (!defined('DEBUG_MODE')) { die(); }

handler_source('brute_force');

// brute_force_check must run BEFORE the core login handler so it can block
// the request before credentials are evaluated.
add_module_to_all_pages('handler', 'brute_force_check', false, 'brute_force', 'login', 'before');

// brute_force_track must run AFTER the core login handler so it can observe
// whether the session became active (= login succeeded).
add_module_to_all_pages('handler', 'brute_force_track', false, 'brute_force', 'login', 'after');

return [];
