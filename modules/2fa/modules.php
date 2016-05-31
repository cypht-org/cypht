<?php

/**
 * 2FA modules
 * @package modules
 * @subpackage 2fa
 */

if (!defined('DEBUG_MODE')) { die(); }

/**
 * @subpackage 2fa/handler
 */
class Hm_Handler_2fa_check extends Hm_Handler_Module {
    public function process() {

        $secret = get_2fa_key($this->config);
        if (!$secret) {
            $this->out('2fa_required', false);
            Hm_Debug::add('2FA module set enabled, but no shared secret configured');
            return;
        }

        $confirmed = $this->session->get('2fa_confirmed', false);

        if ($confirmed) {
            $this->out('2fa_required', false);
            return;
        }

        $passed = false;
        if (array_key_exists('2fa_code', $this->request->post)) {
            if (check_2fa_pin($this->request->post['2fa_code'], $secret)) {
                $passed = true;
            }
            else {
                $this->out('2fa_error', '2 factor authorization code does not match');
            }
        }
        if (!$passed) {
            $this->out('no_redirect', true);
            Hm_Request_Key::load($this->session, $this->request, false);
            $this->out('2fa_key', Hm_Request_Key::generate());
            $this->out('2fa_required', true);
            $this->session->close_early();
        }
        else {
            $this->session->set('2fa_confirmed', true);
            $this->session->close_early();
            $this->out('2fa_required', false);
        }
    }
}

/**
 * @subpackage 2fa/output
 */
class Hm_Output_2fa_dialog extends Hm_Output_Module {
    protected function output() {

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

            if ($this->get('2fa_error')) {
                $error = '<div class="tfa_error">'.$this->html_safe($this->get('2fa_error')).'</div>';
            }
            else {
                $error = '';
            }

            echo '<!DOCTYPE html><html lang='.$this->html_safe($lang).' class="'.$this->html_safe($class).
                '" dir="'.$this->html_safe($dir).'"><head><meta charset="utf-8" />'.
                '<link href="site.css" media="all" rel="stylesheet" type="text/css" />'.
                '<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0">'.
                '</head><body><form class="login_form" method="POST"><h1 class="title">'.
                $this->html_safe($this->get('router_app_name')).'</h1>'. $error.'<div class="tfa_input">'.
                '<label for="2fa_code">'.$this->trans('Enter the 6 digit code from Google Authenticator').
                '</label></div><input type="hidden" name="hm_page_key" value="'.$this->get('2fa_key').'" />'.
                '<input autofocus required id="2fa_code" type="number" name="2fa_code" value="" placeholder="'.
                $this->trans('Login code').'" /><input type="submit" value="'.$this->trans('Submit').
                '" /></form></body></html>';
            exit;
        }
    }
}

/**
 * @subpackage 2fa/functions
 */
function check_2fa_pin($pin, $secret, $pass_len=6) {
    $pin_mod = pow(10, $pass_len);
    $time = floor(time()/30);
    $time = pack('N', $time);
    $time = str_pad($time, 8, chr(0), STR_PAD_LEFT);
    $hash = hash_hmac('sha1', $time, $secret, true);
    $offset = ord(substr($hash,-1)) & 0xF;
    $input = substr($hash, $offset, strlen($hash) - $offset);
    $input = unpack("N",substr($input, 0, 4));
    $inthash = $input[1] & 0x7FFFFFFF;
    return $pin === str_pad($inthash % $pin_mod, 6, "0", STR_PAD_LEFT);
}

/**
 * @subpackage 2fa/functions
 */
function get_2fa_key($config) {
    $ini_file = rtrim($config->get('app_data_dir', ''), '/').'/2fa.ini';
    if (is_readable($ini_file)) {
        $settings = parse_ini_file($ini_file, true);
        if (array_key_exists('2fa_secret', $settings)) {
            return $settings['2fa_secret'];
        }
    }
    return false;
}
