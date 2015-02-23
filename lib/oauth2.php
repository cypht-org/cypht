<?php

/**
 * Oauth2 manager
 * @package framework
 * @subpackage oauth2
 */

if (!defined('DEBUG_MODE')) { die(); }

/**
 * Class for deailing with Oauth2
 */
class Hm_Oauth2 {

    private $client_id;
    private $client_secret;
    private $redirect_uri;

    /**
     * Load default settings
     * @subpackage oath2/lib
     * @param string $id Oath2 client id
     * @param string $secret Oath2 client secret
     * @param string $redirect_uri URI to redirect to from the remote site
     * @return object
     */
    public function __construct($id, $secret, $uri) {
        $this->client_id = $id;
        $this->client_secret = $secret;
        $this->redirect_uri = $uri;
    }

    public function request_authorization($scope, $state) {
    }

    public function process_authorization() {
    }

    public function request_token($authorization_code) {
    }

    public function process_token() {
    }

}

?>
