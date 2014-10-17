<?php

if (!defined('DEBUG_MODE')) { die(); }

class Hm_Handler_swipe_2fa_check extends Hm_Handler_Module {
    public function process($data) {

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
                $data['2fa_fatal'] = true;
            }

            /* get current 2fa state */
            if (!array_key_exists('2fa_sms_response', $this->request->post)) {
                $state = get_secondfactor_state($api, $api_config, $swipe_username, $swipe_address);
            }
            else {
                $state = RC_SMS_DELIVERED;
            }

            /* pass a nonce and no redirect flag to the output modules */
            $data['no_redirect'] = true;
            $data['2fa_nonce'] = Hm_Nonce::generate();

            $sms_number = false;
            $sms_response = false;

            /* if the user has not registered a phone number yet look for one in POST */
            if ($state == NEED_REGISTER_SMS && array_key_exists('sms_number', $this->request->post)) {

                if (preg_match("/^\d{8,15}$/", $this->request->post['sms_number'])) {
                    $sms_number = $this->request->post['sms_number'];

                    /* set the phone number using the api */
                    $api->setUserSmsNumber($swipe_username, $api_config["com.swipeidentity.api.appcode"], $sms_number);

                    /* refecth the status */
                    $state = get_secondfactor_state($api, $api_config, $swipe_username, $swipe_address);

                    /* number rejected by swipe */
                    if ($state == NEED_REGISTER_SMS) {
                        $data['2fa_error'] = 'Invalid phone number';
                    }
                }
                else {
                    $data['2fa_error'] = 'Invalid phone number format';
                }

            }
            /* the sms was sent, look for a sms code in POST */
            if ($state == RC_SMS_DELIVERED && array_key_exists('2fa_sms_response', $this->request->post)) {

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
                        $data['2fa_error'] = 'Response did not match! A new sms code has been sent';
                    }
                }
                else {
                    $data['2fa_error'] = 'Incorrectly formatted response, please re-enter the sms code';
                }
            }

            /* if required is true we still have not completed the 2fa */
            if ($required) {

                /* pass required flag to modules */
                $this->session->set('2fa_required', true);
                $data['2fa_required'] = true;
                $data['2fa_state'] = $state;

                /* close the session early */
                Hm_Nonce::save($this->session);
                $this->session->close_early();
            }
            else {

                /* unset any previously set required flags */
                $this->session->set('2fa_required', false);
                $data['2fa_required'] = false;
            }
        }
        return $data;
    }
}

class Hm_Output_swipe_2fa_dialog extends Hm_Output_Module {
    protected function output($input, $format) {

        /* intercept normal page output for 2fa dialogs. This is fired just
         * after the http_headers core module */
        if (isset($input['2fa_required']) && $input['2fa_required']) {

            $state = $input['2fa_state'];

            echo '<!DOCTYPE html><html lang=en-us><head><meta charset="utf-8" />'.
                '<link href="site.css" media="all" rel="stylesheet" type="text/css" /></head><body>';

            if (array_key_exists('2fa_error', $input)) {
                $error = '<div class="swipe_error">'.$this->html_safe($input['2fa_error']).'</div>';
            }
            else {
                $error = '';
            }

            /* add a phone number form */
            if ($state == NEED_REGISTER_SMS) {
                echo '<form class="login_form" method="POST">'.
                    '<h1 class="title">'.$this->html_safe($input['router_app_name']).'</h1>'.
                    $error.
                    '<div class="swipe_txt">Register your number for Swipeidentity two factor authentication. '.
                    'The number must include a country code. Only enter numbers and no spaces or delimiters</div>'.
                    '<input type="hidden" name="hm_nonce" value="'.$input['2fa_nonce'].'" />'.
                    '<input autofocus required type="text" name="sms_number" value="" placeholder="Phone number to SMS to" />'.
                    '<input type="submit" name="submit_swipe_number" value="Submit" />'.
                    '</form>';
            }
            /* sms response form */
            elseif ($state == RC_SMS_DELIVERED) {
                echo '<form class="login_form" method="POST">'.
                    '<h1 class="title">'.$this->html_safe($input['router_app_name']).'</h1>'.
                    $error.
                    '<div class="swipe_txt">Enter the 5 digit SMS code you just received</div>'.
                    '<input type="hidden" name="hm_nonce" value="'.$input['2fa_nonce'].'" />'.
                    '<input autofocus required type="text" name="2fa_sms_response" value="" placeholder="Login code" />'.
                    '<input type="submit" name="submit_2fa_response" value="Submit" />'.
                    '</form>';
            }

            /* fatal error */
            elseif ($state == 0) {
                echo '<div class="login_form">'.
                    '<h1 class="title">'.$this->html_safe($input['router_app_name']).'</h1>'.
                    '<div class="swipe_error">A fatal error occurred with the swipeidentity 2fa system</div>'.
                    '</div>';

            }
            echo '</body></html>';
            exit;
        }
    }
}

/**
 * include swipe api code and instantiate an object
 *
 * @return array list containing the api object and api config
 */
function setup_swipe_api($ini_file) {
    require "third_party/swipe_2fa_api/ApiBase.php";
    require "third_party/swipe_2fa_api/Error.php";
    require "third_party/swipe_2fa_api/SpiBaseObject.php";
    require "third_party/swipe_2fa_api/SpiExpressSecondFactor.php";
    require "third_party/swipe_2fa_api/SpiExpressUser.php";
    require "third_party/swipe_2fa_api/SwipeApiList.php";
    require "third_party/swipe_2fa_api/SwipeApiNull.php";
    require "third_party/swipe_2fa_api/SwipeApiStringObject.php";
    require "third_party/swipe_2fa_api/SwipeIdentityExpressApi.php";

    /* TODO: move the ini out of the doc root */
    $api_config = parse_ini_file($ini_file);
    return array(new swipeIdentityExpressApi($api_config["com.swipeidentity.api.server.url"]), $api_config);
}

/**
 * start an api transaction
 *
 * @param $api object swipe api object
 * @param $api_config array api configuration fom the ini file
 *
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
 *
 * @param $api object swipe api object
 * @param $api_config array api configuration fom the ini file
 * @param $username string username used with the api
 * @param $address string ip address from the server REMOTE_ADDR value
 *
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
