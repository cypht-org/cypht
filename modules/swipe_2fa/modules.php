<?php

/**
 * Swipe Identity modules
 * @package modules
 * @subpackage swipe2fa
 */

if (!defined('DEBUG_MODE')) { die(); }

/**
 * @subpackage swipe2fa/handler
 */
class Hm_Handler_swipe_2fa_check extends Hm_Handler_Module {
    public function process() {

        /* new session or one not passed the second auth */
        if ($this->session->loaded || $this->session->get('2fa_required', false)) {

            /* ini file location */
            $ini_file = rtrim($this->config->get('app_data_dir', ''), '/').'/swipeidentity.ini';

            /* data for the swipe api */
            $swipe_username = $this->session->get('username', false);
            $swipe_address = $this->request->server['REMOTE_ADDR'];
            $required = true;

            /* get api config and object */
            list($api, $api_config) = setup_swipe_api($ini_file);
            $started = start_api($api, $api_config);
            if (!$started) {
                $this->out('2fa_fatal', true);
            }

            /* get current 2fa state */
            if (!array_key_exists('2fa_sms_response', $this->request->post)) {
                $state = get_secondfactor_state($api, $api_config, $swipe_username, $swipe_address);
            }
            else {
                $state = RC_SMS_DELIVERED;
            }

            /* pass a key and no redirect flag to the output modules */
            $this->out('no_redirect', true);
            Hm_Request_Key::load($this->session, $this->request, false);
            $this->out('2fa_key', Hm_Request_Key::generate());

            $sms_number = false;
            $sms_response = false;

            /* if the user has not registered a phone number yet look for one in POST */
            if ($state == NEED_REGISTER_SMS && array_key_exists('sms_number', $this->request->post)) {

                /* remove non numeric delimiters */
                $sms_number = preg_replace("/[^\d]/", "", $this->request->post['sms_number']);

                /* US phone numbers only for now */
                if (preg_match("/^1\d{10}$/", $sms_number)) {
                    $submit_number = $sms_number;

                    /* set the phone number using the api */
                    $api->setUserSmsNumber($swipe_username, $api_config["com.swipeidentity.api.appcode"], $submit_number);

                    /* refecth the status */
                    $state = get_secondfactor_state($api, $api_config, $swipe_username, $swipe_address);

                    /* number rejected by swipe */
                    if ($state == NEED_REGISTER_SMS) {
                        $this->out('2fa_error', 'Invalid phone number');
                    }
                }
                else {
                    $this->out('2fa_error', 'Invalid phone number format');
                }

            }
            /* the sms was sent, look for a sms code in POST */
            elseif ($state == RC_SMS_DELIVERED && array_key_exists('2fa_sms_response', $this->request->post)) {

                if (preg_match("/^\d{5}$/", $this->request->post['2fa_sms_response'])) {

                    $sms_response = $this->request->post['2fa_sms_response'];

                    /* validate the sms response with the api */
                    $resp = $api->answerSMS($swipe_username, $api_config["com.swipeidentity.api.appcode"], $sms_response);

                    /* success! allow the user to login */
                    if ($resp->getReturnCode() == RC_SMS_ANSWER_ACCEPTED) {
                        $required = false;
                    }
                    else {
                        $state = get_secondfactor_state($api, $api_config, $swipe_username, $swipe_address);
                        $this->out('2fa_error', 'Response did not match! A new sms code has been sent');
                    }
                }
                else {
                    $this->out('2fa_error', 'Incorrectly formatted response, please re-enter the sms code');
                }
            }

            /* if required is true we still have not completed the 2fa */
            if ($required) {

                /* pass required flag to modules */
                $this->session->set('2fa_required', true);
                $this->out('2fa_required', true);
                $this->out('2fa_state', $state);

                /* close the session early */
                $this->session->close_early();
            }
            else {

                /* unset any previously set required flags */
                $this->session->set('2fa_required', false);
                $this->out('2fa_required', false);
            }
        }
    }
}

/**
 * @subpackage swipe2fa/output
 */
class Hm_Output_swipe_2fa_dialog extends Hm_Output_Module {
    protected function output() {

        /* intercept normal page output for 2fa dialogs. This is fired just
         * after the http_headers core module */
        if ($this->get('2fa_required')) {

            $lang = 'en-us';
            $dir = 'ltr';
            if ($this->lang) {
                $lang = strtolower(str_replace('_', '-', $this->lang));
            }
            if ($this->dir) {
                $dir = $this->dir;
            }
            $class = $dir."_page";

            echo '<!DOCTYPE html><html lang='.$this->html_safe($lang).' class="'.$this->html_safe($class).
                '" dir="'.$this->html_safe($dir).'"><head><meta charset="utf-8" />'.
                '<link href="site.css" media="all" rel="stylesheet" type="text/css" />'.
                '<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0">'.
                '</head><body>';

            $state = $this->get('2fa_state');

            if ($this->get('2fa_error')) {
                $error = '<div class="swipe_error">'.$this->html_safe($this->get('2fa_error')).'</div>';
            }
            else {
                $error = '';
            }

            /* add a phone number form */
            if ($state == NEED_REGISTER_SMS) {
                echo '<form class="login_form" method="POST">'.
                    '<h1 class="title">'.$this->html_safe($this->get('router_app_name')).'</h1>'.
                    $error.
                    '<div class="swipe_txt">'.$this->trans('Register your phone number for two factor authentication. '.
                    'You will receive an SMS code at this number anytime you try to access your account. '.
                    'The number must be 11 digits including a US country code prefix of 1. This is a service of ').
                    '<br /><a target="_blank" href="https://www.swipeidentity.com/">Swipeidentity.com</a></div>'.
                    '<input type="hidden" name="hm_page_key" value="'.$this->get('2fa_key').'" />'.
                    '<label class="screen_reader" for="sms_number">'.$this->trans('Phone number to send SMS codes to').'</label>'.
                    '<input id="sms_number" autofocus required type="tel" name="sms_number" value="" placeholder="'.$this->trans('1-222-333-4444').'" />'.
                    '<input type="submit" name="submit_swipe_number" value="'.$this->trans('Submit').'" />'.
                    '</form>';
            }
            /* sms response form */
            elseif ($state == RC_SMS_DELIVERED) {
                echo '<form class="login_form" method="POST">'.
                    '<h1 class="title">'.$this->html_safe($this->get('router_app_name')).'</h1>'.
                    $error.
                    '<div class="swipe_txt"><label for="sms_response">'.$this->trans('Enter the 5 digit SMS code you just received below').'</label></div>'.
                    '<input type="hidden" name="hm_page_key" value="'.$this->get('2fa_key').'" />'.
                    '<input autofocus required id="sms_response" type="number" name="2fa_sms_response" value="" placeholder="'.$this->trans('Login code').'" />'.
                    '<input type="submit" value="'.$this->trans('Submit').'" />'.
                    '</form>';
            }

            /* fatal error */
            elseif ($state == 0) {
                echo '<div class="login_form">'.
                    '<h1 class="title">'.$this->html_safe($this->get('router_app_name')).'</h1>'.
                    '<div class="swipe_error">'.$this->trans('A fatal error occurred with the Swipeidentity 2fa system').'</div>'.
                    '</div>';

            }
            echo '</body></html>';
            exit;
        }
    }
}

/**
 * include swipe api code and instantiate an object
 * @subpackage swipe2fa/functions
 * @return array list containing the api object and api config
 */
function setup_swipe_api($ini_file) {
    require APP_PATH."third_party/swipe_2fa_api/ApiBase.php";
    require APP_PATH."third_party/swipe_2fa_api/Error.php";
    require APP_PATH."third_party/swipe_2fa_api/SpiBaseObject.php";
    require APP_PATH."third_party/swipe_2fa_api/SpiExpressSecondFactor.php";
    require APP_PATH."third_party/swipe_2fa_api/SpiExpressUser.php";
    require APP_PATH."third_party/swipe_2fa_api/SwipeApiList.php";
    require APP_PATH."third_party/swipe_2fa_api/SwipeApiNull.php";
    require APP_PATH."third_party/swipe_2fa_api/SwipeApiStringObject.php";
    require APP_PATH."third_party/swipe_2fa_api/SwipeIdentityExpressApi.php";

    $api_config = parse_ini_file($ini_file);
    return array(new swipeIdentityExpressApi($api_config["com.swipeidentity.api.server.url"]), $api_config);
}

/**
 * start an api transaction
 * @subpackage swipe2fa/functions
 * @param $api object swipe api object
 * @param $api_config array api configuration fom the ini file
 * @return void
 */
function start_api($api, $api_config) {
    try {
        $api->startTransaction();
        $api->apiLogin($api_config["com.swipeidentity.api.username"], $api_config["com.swipeidentity.api.password"], $api_config["com.swipeidentity.api.apikey"]);
        return true;
    }
    catch (Exception $e) {
        return false;
    }
}

/**
 * check the 2fa state
 * @subpackage swipe2fa/functions
 * @param $api object swipe api object
 * @param $api_config array api configuration fom the ini file
 * @param $username string username used with the api
 * @param $address string ip address from the server REMOTE_ADDR value
 * @return int api return code
 */
function get_secondfactor_state($api, $api_config, $username, $address) {
    try {
        $resp = $api->doSecondFactor($username, $api_config["com.swipeidentity.api.appcode"], $address);
        $state = ApiBase::dispatchUser($resp);
    }
    catch (Exception $e) {
        $state = 0;
    }
    return $state;
}

?>
