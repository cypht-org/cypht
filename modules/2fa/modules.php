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
class Hm_Handler_process_enable_2fa extends Hm_Handler_Module {
    public function process() {
        function enable_2fa_callback($val) { return $val; }
        function backup_2fa_callback($val) { return $val; }
        process_site_setting('2fa_enable', $this, 'enable_2fa_callback', false, true);
        if (array_key_exists('2fa_enable', $this->request->post) && $this->request->post['2fa_enable']) {
            process_site_setting('2fa_backup_codes', $this, 'backup_2fa_callback', false, false);
            $this->session->set('2fa_confirmed', true);
        }
        $secret = get_2fa_key($this->config);
        if ($secret) {
            $username = $this->session->get('username', false);
            $secret = base32_encode_str(create_secret($secret, $username));
            $app_name = $this->config->get('app_name', 'Cypht');
            $uri = sprintf('otpauth://totp/%s:%s?secret=%s&issuer=%s', $app_name, $username, $secret, $app_name);
            $this->out('2fa_png_path', generate_qr_code($this->config, $uri));
            $this->out('2fa_backup_codes', backup_codes($this->user_config));
        }
    }
}

/**
 * @subpackage 2fa/handler
 */
class Hm_Handler_2fa_check extends Hm_Handler_Module {
    public function process() {

        $enabled = $this->user_config->get('2fa_enable_setting', 0);
        if (!$enabled) {
            return;
        }

        $secret = get_2fa_key($this->config);
        if (!$secret) {
            Hm_Debug::add('2FA module set enabled, but no shared secret configured');
            return;
        }

        $confirmed = $this->session->get('2fa_confirmed', false);
        if ($confirmed) {
            return;
        }

        $old_setting = $this->user_config->get('enable_2fa_setting', 0);
        if ($old_setting && $this->session->loaded) {
            Hm_Msgs::add('ERR2FA disabled because of a security issue. Go to "Settings" -> "Site" to re-enable');
        }
        $passed = false;
        $backup_codes = $this->user_config->get('2fa_backup_codes_setting', array());
        if (array_key_exists('2fa_code', $this->request->post)) {
            $username = $this->session->get('username', false);
            $secret = create_secret($secret, $username);
            if (check_2fa_pin($this->request->post['2fa_code'], $secret)) {
                $passed = true;
            }
            elseif (in_array(intval($this->request->post['2fa_code']), $backup_codes, true)) {
                $passed = true;
            }
            else {
                $this->out('2fa_error', '2 factor authentication code does not match');
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
        }
    }
}

/**
 * @subpackage 2fa/output
 */
class Hm_Output_enable_2fa_setting extends Hm_Output_Module {
    protected function output() {
        $enabled = false;
        $backup_codes = $this->get('2fa_backup_codes', array());
        $settings = $this->get('user_settings', array());
        if (array_key_exists('2fa_enable', $settings)) {
            $enabled = $settings['2fa_enable'];
        }
        $res = '<tr><td colspan="2" data-target=".tfa_setting" class="settings_subtitle">'.
            '<img alt="" src="'.Hm_Image_Sources::$unlocked.'" width="16" height="16" />'.$this->trans('2 Factor Authentication').'</td></tr>';

        $res .= '<tr class="tfa_setting"><td>'.$this->trans('Enable 2 factor authentication').
            '</td><td><input value="1" type="checkbox" name="2fa_enable"';
        if ($enabled) {
            $res .= ' checked="checked"';
        }
        $res .= '></td></tr>';
        $path = $this->get('2fa_png_path');
        if ($path && is_readable($path)) {
            $png = file_get_contents($path);
            $qr_code = '<tr class="tfa_setting"><td></td><td>';
            if (!$enabled) {
                $qr_code .= '<div class="err settings_wrap_text">'.
                    $this->trans('Configure your authentication app using the barcode below BEFORE enabling 2 factor authentication.').'</div>';
            }
            else {
                $qr_code .= '<div>'.$this->trans('Update your settings with the code below').'</div>';
            }

            $qr_code .= '<img alt="" width="128" height="128" src="data:image/png;base64,'.base64_encode($png).'" />';
            $qr_code .= '</td></tr>';
        }
        else {
            $qr_code .= '<tr class="tfa_setting"><td></td><td class="err">'.$this->trans('Unable to generate 2 factor authentication QR code').'</td></tr>';
        }
        $res .= $qr_code;
        $res .= '<tr class="tfa_setting"><td></td><td>'.$this->trans('The following backup codes can be used to access your account if you lose your device').'<br /><br />';
        foreach ($backup_codes as $val) {
            $res .= ' '.$val.'<input type="hidden" name="2fa_backup_codes[]" value="'.$val.'" /></br >';
        }
        $res .= '</td></tr>';
        return $res;
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
                $error = '<div class="tfa_error">'.$this->trans($this->get('2fa_error')).'</div>';
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
            Hm_Functions::cease();
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
    $settings = get_ini($config, '2fa.ini');
    if (array_key_exists('2fa_secret', $settings)) {
        return $settings['2fa_secret'];
    }
    return false;
}

/**
 * @subpackage 2fa/functions
 */
function base32_encode_str($str) {
    require_once APP_PATH.'third_party/Base32.php';
    return Base32\Base32::encode($str);
}

/**
 * @subpackage 2fa/functions
 */
function generate_qr_code($config, $str) {
    $qr_code = rtrim($config->get('app_data_dir', ''), '/').'/2fa.png';
    require_once APP_PATH.'third_party/phpqrcode.php';
    QRcode::png($str, $qr_code);
    return $qr_code;
}

/**
 * @subpackage 2fa/functions
 */
function create_secret($key, $user) {
    return Hm_Crypt::pbkdf2($key, $user, 64, 256, 'sha512');
}

/**
 * @subpackage 2fa/functions
 */
function backup_codes($config) {
    $codes = $config->get('2fa_backup_codes_setting', array());
    if (is_array($codes) && count($codes) == 3) {
        return $codes;
    }
    return array(random_int(100000000, 999999999), random_int(100000000, 999999999), random_int(100000000, 999999999));
}
