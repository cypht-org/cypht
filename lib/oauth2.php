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
        if ($login_hint) {
            $res .= '&amp;login_hint='.$login_hint;
        }
        return $res;
    }

    /**
     * Use curl to exchange an authorization code for a token
     * @param string $url url to post to
     * @param string $authorization_code oauth2 auth code
     * @return array
     */
    public function request_token($url, $authorization_code) {
        $flds = sprintf('code=%s&client_id=%s&client_secret=%s&redirect_uri=%s&grant_type=authorization_code',
            urlencode($authorization_code), urlencode($this->client_id), urlencode($this->client_secret),
            urlencode($this->redirect_uri));

        return $this->curl_post($url, $flds);
    }

    /**
     * Use curl to refresh an access token
     * @param string $url url to to post to
     * @param string $refresh_token oauth2 refresh token
     * @return array
     */
    public function refresh_token($url, $refresh_token) {
        $flds = sprintf('client_id=%s&client_secret=%s&refresh_token=%s&grant_type=refresh_token',
            urlencode($this->client_id), urlencode($this->client_secret), urlencode($refresh_token));

        return $this->curl_post($url, $flds);
    }

    /**
     * post to an oauth2 endpoint
     * @param string $url url to post to
     * @param array $flds post data
     * @return array
     */
    private function curl_post($url, $flds) {
        $result = array();
        $ch = Hm_Functions::c_init();
        Hm_Functions::c_setopt($ch, CURLOPT_URL, $url);
        Hm_Functions::c_setopt($ch, CURLOPT_POST, 5);
        Hm_Functions::c_setopt($ch, CURLOPT_POSTFIELDS, $flds);
        Hm_Functions::c_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        Hm_Functions::c_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Accept: application/json'
        ));

        $curl_result = Hm_Functions::c_exec($ch);
        if (substr($curl_result, 0, 1) == '{') {
            $result = @json_decode($curl_result, true);
        }
        return $result;
    }
}

?>
