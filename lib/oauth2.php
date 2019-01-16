<?php

/**
 * Oauth2 manager
 * @package framework
 * @subpackage oauth2
 */

/**
 * Class for dealing with Oauth2
 */
class Hm_Oauth2 {

    private $client_id;
    private $client_secret;
    private $redirect_uri;
    private $api;

    /**
     * Load default settings
     * @param string $id Oath2 client id
     * @param string $secret Oath2 client secret
     * @param string $uri URI to redirect to from the remote site
     */
    public function __construct($id, $secret, $uri) {
        $this->client_id = $id;
        $this->client_secret = $secret;
        $this->redirect_uri = $uri;
        $this->api = new Hm_API_Curl();
    }

    /**
     * Build a URL to request an authorization
     * @param string $url host to request authorization from
     * @param string $scope oauth2 scope
     * @param string $state current state of the oauth2 flow
     * @param string $login_hint optional username
     * @return string
     */
    public function request_authorization_url($url, $scope, $state, $login_hint=false) {
        $res = sprintf('%s?response_type=code&amp;scope=%s&amp;state=%s&amp;'.
            'approval_prompt=force&amp;access_type=offline&amp;client_id=%s&amp;redirect_uri=%s',
            $url, $scope, $state, $this->client_id, $this->redirect_uri);
        if ($login_hint !== false) {
            $res .= '&amp;login_hint='.$login_hint;
        }
        return $res;
    }

    /**
     * Use curl to exchange an authorization code for a token
     * @param string $url url to post to
     * @param string $authorization_code oauth2 auth code
     * @param array $headers HTTP headers to add to the request
     * @return array
     */
    public function request_token($url, $authorization_code, $headers=array()) {
        return $this->api->command($url, $headers, array('code' => $authorization_code, 'client_id' => $this->client_id,
            'client_secret' => $this->client_secret, 'redirect_uri' => $this->redirect_uri, 'grant_type' => 'authorization_code'));
    }

    /**
     * Use curl to refresh an access token
     * @param string $url url to to post to
     * @param string $refresh_token oauth2 refresh token
     * @return array
     */
    public function refresh_token($url, $refresh_token) {
        return $this->api->command($url, array(), array('client_id' => $this->client_id, 'client_secret' => $this->client_secret,
            'refresh_token' => $refresh_token, 'grant_type' => 'refresh_token'));
    }
}
