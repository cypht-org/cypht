<?php
/**
 * Custom session class. To use this, you must set the SESSION_TYPE environment variable to 'custom'.
 */
class Hm_Custom_Session extends Hm_Session {

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

/**
 * Custom cache class. To use this, you must set the ENABLE_CUSTOM_CACHE environment variable to true.
 */
class Hm_Custom_Cache extends Hm_Noop_Cache {

    public function get($key, $default = false)
    {
        return $default;
    }

    public function set($key, $val, $lifetime, $crypt_key)
    {
        return parent::set($key, $val, $lifetime, $crypt_key);
    }

    public function del($key)
    {
        return parent::del($key);
    }
}

/**
 * Custom site configuration class. To use this, you must set the SITE_CONFIG_TYPE environment variable to 'custom'.
 */
class Hm_Custom_Site_Config extends Hm_Site_Config_File {
    public function get($name, $default = false)
    {
        return parent::get($name, $default);
    }

    public function set($name, $value)
    {
        return parent::set($name, $value);
    }

    public function load($all_configs, $key)
    {
        return parent::load($all_configs, $key);
    }
}

/**
 * Custom user configuration class. To use this, you must set the USER_CONFIG_TYPE environment variable to 'custom'.
 */
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
