<?php

/**
 * To use these overrides, you must first enable the "site" module in your
 * hm3.ini file, then rebuild your configuration with the config_gen.php script
 * to activate the module. 
 */

/**
 * Override the session class. These are the methods that must be overriden to
 * create a new session backend. The "session_type" value in your hm3.ini must
 * be set to "custom" to activate this class. There are several other
 * properties and methods that can be modified to create custom sessions:
 *
 *  https://cypht.org/docs/code_docs/class-Hm_Session.html
 *
 * This example extends the standard PHP session class. You can also extend the
 * DB or Memcached classes, or the base session class. In this example we just
 * defer to the PHP session class methods.
 *
 * @package modules
 * @subpackage site
 */
class Custom_Session extends Hm_PHP_Session {

    /**
     * check for an active session or an attempt to start one
     * @param object $request request object
     * @return bool
     */
    public function check($request, $user=false, $pass=false, $fingerprint=true) {
        return parent::check($request, $user, $pass, $fingerprint);
    }

    /**
     * Start the session. This could be an existing session or a new login
     * @param object $request request details
     * @return void
     */
    public function start($request, $existing_session=false) {
        return parent::start($request, $existing_session); 
    }

    /**
     * Call the configured authentication method to check user credentials
     * @param string $user username
     * @param string $pass password
     * @return bool true if the authentication was successful
     */
    public function auth($user, $pass) {
        return parent::auth($user, $pass);
    }

    /**
     * Return a session value, or a user settings value stored in the session
     * @param string $name session value name to return
     * @param mixed $default value to return if $name is not found
     * @return mixed the value if found, otherwise $defaultHm_Auth
     */
    public function get($name, $default=false, $user=false) {
        return parent::get($name, $default, $user);
    }

    /**
     * Save a value in the session
     * @param string $name the name to save
     * @param string $value the value to save
     * @return void
     */
    public function set($name, $value, $user=false) {
        return parent::set($name, $value); 
    }

    /**
     * Delete a value from the session
     * @param string $name name of value to deleteHm_Auth
     * @return void
     */
    public function del($name) {
        return parent::del($name);
    }

    /**
     * End a session after a page request is complete. This only closes the session and
     * does not destroy it
     * @return void
     */
    public function end() {
        return parent::end();
    }

    /**
     * Destroy a session for good
     * @param object $request request details
     * @return void
     */
    public function destroy($request) {
        return parent::destroy($request);
    }

}

/**
 * Override the authentication class. This method needs to be overriden to
 * create a custom authentication backend. You must set the "auth_type" setting
 * in your hm3.ini file to "custom" to activate this class. More information
 * about the base class for authentication is located here:
 *
 * https://cypht.org/docs/code_docs/class-Hm_Auth.html
 *
 * This example extends the auth DB class, and simply defers the parent class
 * method
 * @package modules
 * @subpackage site
 */
class Custom_Auth extends Hm_Auth_DB {

    /**
     * This is the method new auth mechs need to override.
     * @param string $user username
     * @param string $pass password
     * @return bool true if the user is authenticated, false otherwise
     */
    public function check_credentials($user, $pass) {
        return parent::check_credentials($user, $pass);
    }
}

/*function format_msg_html($str, $images=false) {
    return '';
}*/
?>
