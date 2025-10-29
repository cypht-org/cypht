<?php
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;

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
        list($secret, $simple) = get_2fa_key($this->config);
        if ($secret) {
            if ($simple) {
                $len = 15;
            }
            else {
                $len = 64;
            }
            $username = $this->session->get('username', false);
            $secret = base32_encode_str(create_secret($secret, $username, $len));
            $app_name = $this->config->get('app_name', 'Cypht');
            $uri = sprintf('otpauth://totp/%s:%s?secret=%s&issuer=%s', $app_name, $username, $secret, $app_name);
            $this->out('2fa_svg', generate_qr_code($this->config, $username, $uri));
            $this->out('2fa_backup_codes', backup_codes($this->user_config));
            $this->out('2fa_secret', $secret);
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

        /*if(!extension_loaded('imagick')){
            Hm_Msgs::add('2FA The imagick extension is required to use 2fa feature, please contact your administrator for fixing this', 'warning');
            return;
        }*/

        list($secret, $simple) = get_2fa_key($this->config);
        if (!$secret) {
            Hm_Debug::add('2FA module set enabled, but no shared secret configured', 'warning');
            return;
        }

        $confirmed = $this->session->get('2fa_confirmed', false);
        if ($confirmed) {
            return;
        }

        $old_setting = $this->user_config->get('enable_2fa_setting', 0);
        if ($old_setting && $this->session->loaded) {
            Hm_Msgs::add('2FA disabled because of a security issue. Go to "Settings" -> "Site" to re-enable', 'warning');
        }
        $passed = false;
        $backup_codes = $this->user_config->get('2fa_backup_codes_setting', array());
        if (array_key_exists('2fa_code', $this->request->post)) {
            if ($simple) {
                $len = 15;
            }
            else {
                $len = 64;
            }
            $username = $this->session->get('username', false);
            $secret = create_secret($secret, $username, $len);
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
 * Verify 2fa code is paired with Authenticator app before enabling 2fa
 * @subpackage 2fa/handler
 */
class Hm_Handler_2fa_setup_check extends Hm_Handler_Module {
    public function process() {

        list($secret, $simple) = get_2fa_key($this->config);
        if (!$secret) {
            Hm_Debug::add('2FA module set enabled, but no shared secret configured', 'warning');
            return;
        }

        $verified = false;
        $len = $simple ? 15 : 64;

        $username = $this->session->get('username', false);
        $secret = create_secret($secret, $username, $len);

        if (check_2fa_pin($this->request->post['2fa_code'], $secret)) {
            $verified = true;
        }

        $this->out('ajax_2fa_verified', $verified);
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
        $res = '<tr><td colspan="2" data-target=".tfa_setting" class="settings_subtitle cursor-pointer border-bottom p-2">'.
            '<i class="bi bi-unlock-fill fs-5 me-2"></i>'.$this->trans('2 Factor Authentication').'</td></tr>';

        $res .= '<tr class="tfa_setting"><td><label class="form-check-label">'.$this->trans('Enable 2 factor authentication').'</label>'.
            '<input class="form-check-input ms-3" value="1" type="checkbox" name="2fa_enable"';
        if ($enabled) {
            $res .= ' checked="checked"';
        }
        $res .= '>';
        $svg = $this->get('2fa_svg');

        if ($svg) {
            $qr_code = '';
            if (!$enabled) {
                $qr_code .= '<div class="err settings_wrap_text tfa_mt_1">'.$this->trans('Configure your authentication app using the barcode below BEFORE enabling 2 factor authentication.').'</div>';
            }
            else {
                $qr_code .= '<div>'.$this->trans('Update your settings with the code below').'</div>';
            }

            $qr_code .= $svg;
            $qr_code .= '<div class="tfa_mb_1">'.$this->trans('If you can\'t use the QR code, you can enter the code below manually (no line breaks)').'</div>';
            $qr_code .= wordwrap($this->html_safe($this->get('2fa_secret', '')), 60, '<br />', true);
        }
        else {
            $qr_code = '<div class="tfa_mt_1">'.$this->trans('Unable to generate 2 factor authentication QR code').'</div>';
        }
        $res .= $qr_code;

        $res .= '<div class="tfa_mb_1">'.$this->trans('The following backup codes can be used to access your account if you lose your device'). '</div>';

        foreach ($backup_codes as $val) {
            $res .= ' '.$val.'<input type="hidden" name="2fa_backup_codes[]" value="'.$val.'" /></br >';
        }
        $res .= '<div class="tfa_mt_1">
                    <fieldset class="tfa_confirmation_fieldset p-3">
                        <legend>Enter the confirmation code</legend>
                        <div class="tfa_confirmation_wrapper">
                            <div class="tfa_confirmation_form">
                                <div class="tfa_confirmation_input_digits">
                                    <input class="tfa_confirmation_input_digit" type="number" aria-label="Digit 0" aria-required="true">
                                    <input class="tfa_confirmation_input_digit" type="number" aria-label="Digit 1" aria-required="true">
                                    <input class="tfa_confirmation_input_digit" type="number" aria-label="Digit 2" aria-required="true">
                                    <input class="tfa_confirmation_input_digit" type="number" aria-label="Digit 3" aria-required="true">
                                    <input class="tfa_confirmation_input_digit" type="number" aria-label="Digit 4" aria-required="true">
                                    <input class="tfa_confirmation_input_digit" type="number" aria-label="Digit 5" aria-required="true">
                                </div>
                                <button id="tfaConfirmationBtn" type="submit" class="tfa_confirmation_input_button btn btn-light border-1">'.$this->trans('Verify code').'</button>
                            </div>
                            <div class="tfa_confirmation_hint"> '.$this->trans('Enter the 6 digit code from your Authenticator application').'</div>
                        </div>
                    </fieldset>
                </div>
            </td>
        </tr>';
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
                $lang = mb_strtolower(str_replace('_', '-', $this->lang));
            }
            if ($this->dir) {
                $dir = $this->dir;
            }
            $class = $dir."_page";

            if ($this->get('2fa_error')) {
                $error = '<div class="tfa_error"><div class="alert alert-danger alert-dismissible fade show" role="alert">'.$this->trans($this->get('2fa_error')).'</div></div>';
            }
            else {
                $error = '';
            }

            if(!$this->get('fancy_login_allowed')){
                echo '<!DOCTYPE html>
                <html lang="'.$this->html_safe($lang).'" class="'.$this->html_safe($class).'" dir="'.$this->html_safe($dir).'">
                <head>
                    <meta charset="utf-8" />
                    <link href="site.css" media="all" rel="stylesheet" type="text/css" />
                    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0">
                    <link href="modules/themes/assets/default/css/default.css?v=' . CACHE_ID . '" media="all" rel="stylesheet" type="text/css" />
                    <link href="vendor/twbs/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet" type="text/css" />
                </head>
                <body>
                    <div class="bg-light">
                        <div class="d-flex align-items-center justify-content-center vh-100 p-3">
                                <div class="card col-12 col-md-6 col-lg-4 p-3">
                                    <div class="card-body">
                                        <form class="mt-5" method="POST">
                                            <p class="text-center"><img class="w-50" src="modules/core/assets/images/logo_dark.svg"></p>
                                            <p class="text-center">'.$this->trans('Enter the 6 digit code from your Authenticator application').'</p>
                                            '.$error.'
                                            <div class="form-floating mb-3">
                                                <input autofocus required id="2fa_code" type="number" name="2fa_code" class="form-control" value="" placeholder="'.$this->trans('Login code').'">
                                                <label for="2fa_code">'.$this->trans('Login code').'</label>
                                            </div>
                                            <div class="d-grid">
                                                <input type="submit" class="btn btn-primary btn-lg" value="'.$this->trans('Submit').'">
                                            </div>
                                            <input type="hidden" name="hm_page_key" value="'.$this->get('2fa_key').'">
                                        </form>
                                    </div>
                                </div>
                        </div>
                    </div>
                </body>
                </html>';
            }
            else{
                $style = '<style type="text/css">body,html{max-width:100vw !important; max-height:100vh !important; overflow:hidden !important;}.form-container{background-color:#f1f1f1;'.
                    'background: linear-gradient( rgba(4, 26, 0, 0.85), rgba(4, 26, 0, 0.85)), url('.WEB_ROOT.'modules/core/assets/images/cloud.jpg);'.
                    'background-attachment: fixed;background-position: center;background-repeat: no-repeat;background-size: cover;'.
                    'display:grid; place-items:center; height:100vh; width:100vw;} .logged_out{display:block !important;}.sys_messages'.
                    '{position:fixed;right:20px;top:15px;min-height:30px;display:none;background-color:#fff;color:teal;'.
                    'margin-top:0px;padding:15px;padding-bottom:5px;white-space:nowrap;border:solid 1px #999;border-radius:'.
                    '5px;filter:drop-shadow(4px 4px 4px #ccc);z-index:101;}.g-recaptcha{margin:0px 10px 10px 10px;}.mobile .g-recaptcha{'.
                    'margin:0px 10px 5px 10px;}.title{font-weight:normal;padding:0px;margin:0px;margin-left:20px;'.
                    'margin-bottom:20px;letter-spacing:-1px;color:#999;}html,body{min-width:100px !important;'.
                    'background-color:#fff;}body{background:linear-gradient(180deg,#faf6f5,#faf6f5,#faf6f5,#faf6f5,'.
                    '#fff);font-size:1em;color:#333;font-family:Arial;padding:0px;margin:0px;min-width:700px;'.
                    'font-size:100%;}input,option,select{font-size:100%;padding:3px;}textarea,select,input{border:outset '.
                    '1px #ddd;background-color:#fff;color:#333;border-radius:6px;}.screen_reader{position:absolute'.
                    ';top:auto;width:1px;height:1px;overflow:hidden;}.login_form{display:flex; justify-content:space-evenly; align-items:center; flex-direction:column;font-size:90%;'.
                    'padding-top:60px;height:360px;border-radius:20px 20px 20px 20px;margin:0px;background-color:rgba(0,0,0,.6);'.
                    'min-width:300px;}.login_form input{clear:both;float:left;padding:4px;'.
                    'margin-top:10px;margin-bottom:10px;} .err{color:red !important;}.long_session'.
                    '{float:left;}.long_session input{padding:0px;float:none;font-size:18px;}.mobile .long_session{float:left;clear:both;} @media screen and (min-width:400px){.login_form{min-width:400px;}}'.
                    '.user-icon_signin{display:block; background-color:white; border-radius:100%; padding:10px; height:40px; margin-top:-75px; box-shadow: #6eb549 .4px 2.4px 6.2px; }'.
                    '.label_signin{width:210px; margin:0px 0px -18px 0px;color:#fff;opacity:0.7;}'.
                    '.login_form {float : none; padding-left : 0px; padding : 8px; }@media (max-height : 500px){ .user-icon_signin{display:none;}}'.
                    '.tfa_error{margin-left:0 !important; margin-right:0 !important; color:#f93838 !important;} .tfa_input{margin-left:0px;}'.
                    '</style>';
                echo '<!DOCTYPE html><html lang='.$this->html_safe($lang).' class="'.$this->html_safe($class).
                '" dir="'.$this->html_safe($dir).'"><head><meta charset="utf-8" />'.
                '<link href="site.css" media="all" rel="stylesheet" type="text/css" />'.
                '<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0">'.$style.
                '</head><body><div class="form-container">
                <form class="login_form" method="POST">
                <svg class="user-icon_signin" viewBox="0 0 20 20"><path d="M12.075,10.812c1.358-0.853,2.242-2.507,2.242-4.037c0-2.181-1.795-4.618-4.198-4.618S5.921,4.594,5.921,6.775c0,1.53,0.884,3.185,2.242,4.037c-3.222,0.865-5.6,3.807-5.6,7.298c0,0.23,0.189,0.42,0.42,0.42h14.273c0.23,0,0.42-0.189,0.42-0.42C17.676,14.619,15.297,11.677,12.075,10.812 M6.761,6.775c0-2.162,1.773-3.778,3.358-3.778s3.359,1.616,3.359,3.778c0,2.162-1.774,3.778-3.359,3.778S6.761,8.937,6.761,6.775 M3.415,17.69c0.218-3.51,3.142-6.297,6.704-6.297c3.562,0,6.486,2.787,6.705,6.297H3.415z"></path></svg>
                <img src="modules/core/assets/images/logo.svg" style="height:90px;"><!--h1 class="title">'.
                $this->html_safe($this->get('router_app_name')).'</h1-->'. $error.'<div class="tfa_input">'.
                '<label class="label_signin" for="2fa_code">'.$this->trans('Enter the 6 digit code from your Authenticator application').
                '</label></div><input type="hidden" name="hm_page_key" value="'.$this->get('2fa_key').'" />'.
                '<input autofocus required id="2fa_code" style="width:200px; height:25px;" type="number" name="2fa_code" value="" placeholder="'.
                $this->trans('Login code').'" /><input style="cursor:pointer; display:block; width: 210px; background-color:#6eb549; color:white; height:40px;" type="submit" value="'.$this->trans('Submit').
                '" /></form></div></body></html>';
            }
            Hm_Functions::cease();
        }
    }
}

/**
 * @subpackage 2fa/functions
 */
if (!hm_exists('check_2fa_pin')) {
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
}}

/**
 * @subpackage 2fa/functions
 */
if (!hm_exists('get_2fa_key')) {
function get_2fa_key($config) {
    $secret = $config->get('2fa_secret', false);
    $simple = $config->get('2fa_simple', false);
    return array($secret, $simple);
}}

/**
 * @subpackage 2fa/functions
 */
if (!hm_exists('base32_encode_str')) {
function base32_encode_str($str) {
    return Base32\Base32::encode($str);
}}

/**
 * @subpackage 2fa/functions
 */
if (!hm_exists('generate_qr_code')) {
function generate_qr_code($config, $username, $str) {
    $renderer = new ImageRenderer(
        new RendererStyle(200),
        new SvgImageBackEnd()
    );
    $writer = new Writer($renderer);
    return $writer->writeString($str);
}}

/**
 * @subpackage 2fa/functions
 */
if (!hm_exists('create_secret')) {
function create_secret($key, $user, $len) {
    return Hm_Crypt::pbkdf2($key, $user, $len, 256, 'sha512');
}}

/**
 * @subpackage 2fa/functions
 */
if (!hm_exists('backup_codes')) {
function backup_codes($config) {
    $codes = $config->get('2fa_backup_codes_setting', array());
    if (is_array($codes) && count($codes) == 3) {
        return $codes;
    }
    return array(random_int(100000000, 999999999), random_int(100000000, 999999999), random_int(100000000, 999999999));
}}
