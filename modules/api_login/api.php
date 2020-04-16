<?php

/**
 * @package modules
 * @subpackage api_login
 */

/* Constants */
define('DEBUG_MODE', false);
define('APP_PATH', dirname(dirname(dirname(__FILE__))).'/');
define('VENDOR_PATH', APP_PATH.'vendor/');
define('WEB_ROOT', '');
define('CONFIG_FILE', APP_PATH.'hm3.rc');

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
    list($session, $request) = session_init();
    $session->check($request, $user, $pass, false);
    if ($session->is_active()) {
        list($domain, $path, $secure) = url_parse($url);
        $config = new Hm_Site_Config_File(CONFIG_FILE);
        $user_config = load_user_config_object($config);
        $user_config->load($user, $pass);
        module_init_functions($user_config, $session, $request, $config, $user, $pass);
        $user_data = $user_config->dump();
        $session->set('user_data', $user_data);
        $session->set('username', rtrim($user));
        Hm_Functions::setcookie('hm_id', stripslashes($session->enc_key), $lifetime, $path, $domain, $secure, true);
        Hm_Functions::setcookie('hm_session', stripslashes($session->session_key), $lifetime, $path, $domain, $secure, true);
        $session->end();
        return true;
    }
    return false;
}

/**
 * Run functions assigned to the "page" functional_api
 * @subpackage api_login/functions
 * @param object $user_config user configuration object
 * @param object $session user session object
 * @param object $request page request object
 * @param object $config site configuration object
 * @param string $user username
 * @param string $pass password
 * @return void
 */
function module_init_functions($user_config, $session, $request, $config, $user, $pass) {
    foreach (Hm_Handler_Modules::get_for_page('functional_api') as $func => $vals) {
        if (is_readable(sprintf("%smodules/%s/modules.php", APP_PATH, $vals[0]))) {
            require_once sprintf("%smodules/%s/modules.php", APP_PATH, $vals[0]);
            $func($user_config, $session, $request, $config, $user, $pass);
        }
    }
}

/**
 * Log a user out of cypht
 * @subpackage api_login/functions
 * @return void
 */
function cypht_logout() {
    list($session, $request) = session_init();
    $session->check($request);
    $session->destroy($request);
}

/**
 * Parse URL
 * @subpackage api_login/functions
 * @param string $url location of the Cypht installation
 * @return array
 */
function url_parse($url) {
    $parsed = parse_url($url);
    $secure = $parsed['scheme'] === 'https' ? true : false;
    return array($parsed['host'], '/', $secure);
}

/**
 * Startup the session and request objects
 * @subpackage api_login/functions
 * @return array
 */
function session_init() {
    $config = new Hm_Site_Config_File(APP_PATH.'hm3.rc');
    $module_exec = new Hm_Module_Exec($config);
    $module_exec->load_module_sets('functional_api');
    $request = new Hm_Request($module_exec->filters, $config);
    if (in_array('site', $config->get_modules(), true)) {
        if (is_readable(APP_PATH.'modules/site/lib.php')) {
            Hm_Debug::add('Including site module set lib.php');
            require APP_PATH.'modules/site/lib.php';
        }
    }
    $session_config = new Hm_Session_Setup($config);
    $session = $session_config->setup_session();
    return array($session, $request);
}
