<?php

/** 
 * Overwrite these paths to the Cypht lib location in your setup.
 */
require_once 'lib/session_php.php';
require_once 'lib/auth.php';
require_once 'lib/cache.php';
require_once 'lib/config.php';
require_once 'lib/environment.php';

class Hm_Custom_Session extends Hm_PHP_Session {

    public function check($request, $user = false, $pass = false, $fingerprint = true)
    {
        return parent::check($request, $user, $pass, $fingerprint);
    }

    public function start($request)
    {
        return parent::start($request);
    }

    public function auth($user, $pass)
    {
        return parent::auth($user, $pass);
    }

    public function get($name, $default = false, $user = false)
    {
        return parent::get($name, $default, $user);
    }

    public function set($name, $value, $user = false)
    {
        return parent::set($name, $value, $user);
    }

    public function del($name)
    {
        return parent::del($name);
    }

    public function end()
    {
        return parent::end();
    }

    public function destroy($request)
    {
        return parent::destroy($request);
    }
}

/**
 * Custom auth class. To use this, you must set the AUTH_TYPE environment variable to 'custom'.
 */
class Hm_Custom_Auth extends Hm_Auth {

    public function check_credentials($user, $pass)
    {
        throw new \Exception('Not implemented');
    }
}

class Hm_Custom_Cache extends Hm_Cache {

    public function get($key, $default = false, $session = false)
    {
        return parent::get($key, $default, $session);
    }

    public function set($key, $val, $lifetime = 600, $session = false)
    {
        return parent::set($key, $val, $lifetime, $session);
    }

    public function del($key, $session = false)
    {
        return parent::del($key, $session);
    }
}

/**
 * Custom site configuration class. To use this, you must set the SITE_CONFIG_TYPE environment variable to 'custom'.
 */
class Hm_Custom_Site_Config extends Hm_Config {
    public function get($name, $default = false)
    {
        return parent::get($name, $default);
    }

    public function set($name, $value)
    {
        return parent::set($name, $value);
    }

    public function load($source, $key)
    {
        throw new \Exception('Not implemented');
    }
}

class Hm_Custom_User_Config extends Hm_Config {
    private $site_config;

    public function __construct($site_config)
    {
        $this->site_config = $site_config;
    }

    public function get($name, $default = false)
    {
        return parent::get($name, $default);
    }

    public function set($name, $value)
    {
        return parent::set($name, $value);
    }

    public function load($source, $key)
    {
        throw new \Exception('Not implemented');
    }
}
