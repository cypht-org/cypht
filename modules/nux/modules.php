<?php

/**
 * NUX modules
 * @package modules
 * @subpackage nux
 */

if (!defined('DEBUG_MODE')) { die(); }

/**
 * @subpackage nux/handler
 */
class Hm_Handler_process_nux_add_service extends Hm_Handler_Module {
    public function process() {
        list($success, $form) = $this->process_form(array('nux_pass', 'nux_service', 'nux_email'));
        if ($success) {
            if (Nux_Quick_Services::exists($form['nux_service'])) {
                $details = Nux_Quick_Services::details($form['nux_service']);
            }
        }
    }
}

/**
 * @subpackage nux/handler
 */
class Hm_Handler_process_nux_service extends Hm_Handler_Module {
    public function process() {
        list($success, $form) = $this->process_form(array('nux_service', 'nux_email'));
        if ($success) {
            if (Nux_Quick_Services::exists($form['nux_service'])) {
                $details = Nux_Quick_Services::details($form['nux_service']);
                $details['id'] = $form['nux_service'];
                $details['email'] = $form['nux_email'];
                $this->out('nux_add_service_details', $details);
            }
        }
    }
}

/**
 * @subpackage nux/output
 */
class Hm_Output_quick_add_dialog extends Hm_Output_Module {
    protected function output() {
        return '<div class="quick_add_section">'.
            '<form method="post">'.
            '<label class="screen_reader" for="service_select">'.$this->trans('Select an E-mail provider').'</label>'.
            ' <select id="service_select" name="service_select"><option value="">'.$this->trans('Select an E-mail provider').'</option>'.Nux_Quick_Services::option_list(false, $this).'</select>'.
            '<label class="screen_reader" for="nux_username">'.$this->trans('Username').'</label>'.
            '<br /><input type="email" class="nux_username" placeholder="'.$this->trans('Enter Your E-mail address').'" />'.
            '<br /><input type="button" class="nux_next_button" value="'.$this->trans('Next').'" />'.
            '</form></div><div class="nux_step_two"></div></div>';
    }
}

/**
 * @subpackage nux/output
 */
class Hm_Output_filter_service_select extends Hm_Output_Module {
    protected function output() {
        $details = $this->get('nux_add_service_details', array());
        if (!empty($details)) {
            if (array_key_exists('auth', $details) && $details['auth'] == 'oauth2') {
                $this->out('nux_service_step_two',  oauth2_form($details, $this));
            }
            else {
                $this->out('nux_service_step_two',  credentials_form($details, $this));
            }
        }
    }
}

/**
 * @subpackage nux/output
 */
class Hm_Output_quick_add_section extends Hm_Output_Module {
    protected function output() {
        return '<div class="nux_add_account"><div data-target=".quick_add_section" class="server_section">'.
            '<img src="'.Hm_Image_Sources::$circle_check.'" alt="" width="16" height="16" /> '.
            $this->trans('Quick Add').'</div>';
    }
}

/**
 * @subpackage nux/lib
 */
class Nux_Quick_Services {

    static private $services = array();

    static public function add($id, $details) {
        self::$services[$id] = $details;
    }

    static public function option_list($current, $mod) {
        $res = '';
        foreach(self::$services as $id => $details) {
            $res .= '<option value="'.$mod->html_safe($id).'"';
            if ($id == $current) {
                $res .= ' selected="selected"';
            }
            $res .= '>'.$mod->trans($details['name']);
            $res .= '</option>';
        }
        return $res;
    }

    static public function exists($id) {
        return array_key_exists($id, self::$services);
    }

    static public function details($id) {
        if (array_key_exists($id, self::$services)) {
            return self::$services[$id];
        }
        return array();
    }
}

Nux_Quick_Services::add('gmail', array(
    'server' => 'imap.gmail.com',
    'type' => 'imap',
    'tls' => true,
    'port' => 993,
    'name' => 'Gmail',
    'auth' => 'oauth2',
    '2fa' => 'https://www.google.com/landing/2step/',
    'oauth2_authorization' => 'https://accounts.google.com/o/oauth2/auth',
    'client_id' => '163910415595-atnjhgf4h4sp0fegll6s4jcdn5ssgu55.apps.googleusercontent.com',
    'client_secret' => 'r98hAAYlpm6KGhgsQX00J1z3', 'redirect_uri' => 'http://localhost/hm3/',
    'scope' => ' https://mail.google.com/'
));

Nux_Quick_Services::add('outlook', array(
    'server' => 'imap-mail.outlook.com',
    'type' => 'imap',
    'tls' => true,
    'port' => 993,
    'name' => 'Outlook',
    'auth' => 'oauth2',
    'oauth2_authorization' => ''
));

Nux_Quick_Services::add('yahoo', array(
    'server' => 'imap.mail.yahoo.com',
    'type' => 'imap',
    'tls' => true,
    'port' => 993,
    'name' => 'Yahoo',
    'auth' => 'login'
));

Nux_Quick_Services::add('mailcom', array(
    'server' => 'imap.mail.com',
    'type' => 'imap',
    'tls' => true,
    'port' => 993,
    'name' => 'Mail.com',
    'auth' => 'login'
));

Nux_Quick_Services::add('aol', array(
    'server' => 'imap.aol.com',
    'type' => 'imap',
    'tls' => true,
    'port' => 993,
    'name' => 'AOL',
    'auth' => 'login'
));

Nux_Quick_Services::add('gmx', array(
    'server' => 'imap.gmx.com',
    'type' => 'imap',
    'tls' => true,
    'port' => 143,
    'name' => 'GMX',
    'auth' => 'login'
));

Nux_Quick_Services::add('zoho', array(
    'server' => 'imap.zoho.com',
    'type' => 'imap',
    'tls' => true,
    'port' => 993,
    'name' => 'Zoho',
    'auth' => 'login'
));

/**
 * @subpackage nux/functions
 */
function oauth2_form($details, $mod) {
    $oauth2 = new Hm_Oauth2($details['client_id'], $details['client_secret'], $details['redirect_uri']);
    $url = $oauth2->request_authorization_url($details['oauth2_authorization'], $details['scope'], 'authorization', $details['email']);
    $res = '<form method="post">';
    $res .= '<input type="hidden" name="nux_service" value="'.$mod->html_safe($details['id']).'" />';
    $res .= '<div class="nux_step_two_title">'.$mod->html_safe($details['name']).'</div><div>';
    if (array_key_exists('2fa', $details)) {
        $res .= $mod->trans('This service supports 2 factor authentication. If you have 2 factor authentication enabled, you will need an application password to access your account');
        $res .= '<br /><br /><input type="password" placeholder="'.$mod->trans('Application Password').'" name="application_password" class="app_password" />';
        $res .= '<br /><input type="button" class="nux_submit" value="'.$mod->trans('Connect').'" /><br />';
        $res .= $mod->trans('If you do NOT have 2 factor authentication enabled, follow the link below to allow access to your E-mail account using Oauth2.');
    }
    else {
        $res .= $mod->trans('This provider supports Oauth2 access to your account.');
    }
    $res .= $mod->trans(' This is the most secure way to access your E-mail. Click "Enable" to be reidrected to the provider site to allow access.');
    $res .= '</div><a class="enable_auth2" href="'.$url.'">'.$mod->trans('Enable').'</a>';
    $res .= '<a href="" class="reset_nux_form">Reset</a>';
    $res .= '</form>';
    return $res;
}

/**
 * @subpackage nux/functions
 */
function credentials_form($details, $mod) {
    $res = '<form method="post">';
    $res .= '<input type="hidden" id="nux_service" name="nux_service" value="'.$mod->html_safe($details['id']).'" />';
    $res .= '<input type="hidden" id="nux_email" name="nux_email" value="'.$mod->html_safe($details['email']).'" />';
    $res .= '<div class="nux_step_two_title">'.$mod->html_safe($details['name']).'</div>';
    $res .= $mod->trans('Enter your password for this E-mail provider to complete the connection process');
    $res .= '<br /><br /><input type="password" placeholder="'.$mod->trans('E-Mail Password').'" name="nux_password" class="nux_password" />';
    $res .= '<br /><input type="button" class="nux_submit" value="'.$mod->trans('Connect').'" /><br />';
    $res .= '<a href="" class="reset_nux_form">Reset</a>';
    $res .= '</form>';
    return $res;
}
?>
