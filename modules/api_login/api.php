<?php

/**
 * @package modules
 * @subpackage api_login
 */

/* Constants */
define('DEBUG_MODE', false);
define('APP_PATH', dirname(dirname(dirname(__FILE__))).'/');

/* Init the framework */
require_once APP_PATH.'lib/framework.php';
require APP_PATH.'modules/core/functions.php';

/**
 * Start a user session if the user/pass are valid
 * @subpackage api_login/functions
 * @param string $user username
 * @param string $pass password
 * @param string $url location of the Cypht installation
 * @return bool
 */
function cypht_login($user, $pass, $url, $lifetime=0) {
    $parsed = parse_url($url);
    $config = new Hm_Site_Config_File(APP_PATH.'hm3.rc');
    $module_exec = new Hm_Module_Exec($config);
    $request = new Hm_Request($module_exec->filters, $config);
    $session_config = new Hm_Session_Setup($config);
    $session = $session_config->setup_session();
    $session->check($request, $user, $pass, false);
    if ($session->is_active()) {
        $secure = $parsed['scheme'] === 'https' ? true : false;
        $domain = $parsed['host'];
        $path = $parsed['path'];
        Hm_Functions::setcookie('hm_id', stripslashes($session->enc_key), $lifetime, $path, $domain, $secure, true);
        Hm_Functions::setcookie('hm_session', stripslashes($session->session_key), $lifetime, $path, $domain, $secure, true);
        $session->end();
        return true;
    }
    return false;
}
