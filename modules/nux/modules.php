<?php

/**
 * NUX modules
 * @package modules
 * @subpackage nux
 * @todo filter/disable features depending on imap module sets
 */

if (!defined('DEBUG_MODE')) { die(); }

require_once APP_PATH.'modules/nux/services.php';
require_once APP_PATH.'modules/profiles/hm-profiles.php';

/**
 * @subpackage nux/handler
 */
class Hm_Handler_nux_dev_news extends Hm_Handler_Module {
    public function process() {
        $cache = $this->cache->get('nux_dev_news', array());
        if ($cache) {
            $this->out('nux_dev_news', $cache);
            return;
        }
        $res = array();
        $ch = Hm_Functions::c_init();
        if ($ch) {
            Hm_Functions::c_setopt($ch, CURLOPT_URL, 'https://api.github.com/repos/cypht-org/cypht/commits');
            Hm_Functions::c_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            Hm_Functions::c_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
            Hm_Functions::c_setopt($ch, CURLOPT_USERAGENT, $this->request->server["HTTP_USER_AGENT"]);
            $curl_result = Hm_Functions::c_exec($ch);
            if (trim($curl_result)) {
                if (strstr($curl_result, 'API rate limit exceeded')) {
                    return;
                }
                $json_commits = json_decode($curl_result);
                foreach($json_commits as $c) {
                    $msg = trim($c->commit->message);
                    $res[] = array(
                    'hash' => $c->sha,
                    'shash' => substr($c->sha, 0, 8),
                    'name' => $c->commit->author->name,
                    'age' => date('D, M d', strtotime($c->commit->author->date)),
                    'note' => (strlen($msg) > 80 ? substr($msg, 0, 80) . "..." : $msg)
                    );
                }
            }
        }
        $this->cache->set('nux_dev_news', $res);
        $this->out('nux_dev_news', $res);
    }
}

/**
 * @subpackage nux/handler
 */
class Hm_Handler_nux_homepage_data extends Hm_Handler_Module {
    public function process() {

        $imap_servers = NULL;
        $smtp_servers = NULL;
        $feed_servers = NULL;
        $profiles = NULL;

        $modules = $this->config->get_modules();

        if (data_source_available($modules, 'imap')) {
            $imap_servers = count(Hm_IMAP_List::dump(false));
        }
        if (data_source_available($modules, 'feeds')) {
            $feed_servers = count(Hm_Feed_List::dump(false));
        }
        if (data_source_available($modules, 'smtp')) {
            $smtp_servers = count(Hm_SMTP_List::dump(false));
        }
        if (data_source_available($modules, 'profiles')) {
            Hm_Profiles::init($this);
            $profiles = Hm_Profiles::count();
        }

        $this->out('nux_server_setup', array(
            'imap' => $imap_servers,
            'feeds' => $feed_servers,
            'smtp' => $smtp_servers,
            'profiles' => $profiles
        ));
        $this->out('tzone', $this->user_config->get('timezone_setting'));
    }
}
/**
 * @subpackage nux/handler
 */
class Hm_Handler_process_oauth2_authorization extends Hm_Handler_Module {
    public function process() {
        if (array_key_exists('state', $this->request->get) && $this->request->get['state'] == 'nux_authorization') {
            if (array_key_exists('code', $this->request->get)) {
                $details = $this->session->get('nux_add_service_details');
                $oauth2 = new Hm_Oauth2($details['client_id'], $details['client_secret'], $details['redirect_uri']);
                $result = $oauth2->request_token($details['token_uri'], $this->request->get['code']);
                if (!empty($result) && array_key_exists('access_token', $result)) {
                    Hm_IMAP_List::add(array(
                        'name' => $details['name'],
                        'server' => $details['server'],
                        'port' => $details['port'],
                        'tls' => $details['tls'],
                        'user' => $details['email'],
                        'pass' => $result['access_token'],
                        'expiration' => strtotime(sprintf("+%d seconds", $result['expires_in'])),
                        'refresh_token' => $result['refresh_token'],
                        'auth' => 'xoauth2'
                    ));
                    if (isset($details['smtp'])) {
                        Hm_SMTP_List::add(array(
                            'name' => $details['name'],
                            'server' => $details['smtp']['server'],
                            'port' => $details['smtp']['port'],
                            'tls' => $details['smtp']['tls'],
                            'auth' => 'xoauth2',
                            'user' => $details['email'],
                            'pass' => $result['access_token'],
                            'expiration' => strtotime(sprintf("+%d seconds", $result['expires_in'])),
                            'refresh_token' => $result['refresh_token']
                        ));
                        $this->session->record_unsaved('SMTP server added');
                    }
                    Hm_Msgs::add('E-mail account successfully added');
                    Hm_IMAP_List::clean_up();
                    $this->session->del('nux_add_service_details');
                    $this->session->record_unsaved('IMAP server added');
                    $this->session->secure_cookie($this->request, 'hm_reload_folders', '1');
                    $this->session->close_early();
                }
                else {
                    Hm_Msgs::add('ERRAn Error Occurred');
                }
            }
            elseif (array_key_exists('error', $this->request->get)) {
                Hm_Msgs::add('ERR'.ucwords(str_replace('_', ' ', $this->request->get['error'])));
            }
            else {
                Hm_Msgs::add('ERRAn Error Occurred');
            }
            $this->save_hm_msgs();
            Hm_Dispatch::page_redirect('?page=servers');
        }
    }
}

/**
 * @subpackage nux/handler
 */
class Hm_Handler_process_nux_add_service extends Hm_Handler_Module {
    public function process() {
        list($success, $form) = $this->process_form(array('nux_pass', 'nux_service', 'nux_email', 'nux_name'));
        if ($success) {
            if (Nux_Quick_Services::exists($form['nux_service'])) {
                $details = Nux_Quick_Services::details($form['nux_service']);
                $details['name'] = $form['nux_name'];
                if ($form['nux_service'] == 'all-inkl') {
                    $details['server'] = $this->request->post['nux_all_inkl_login'].$details['server'];
                    $details['smtp']['server'] = $this->request->post['nux_all_inkl_login'].$details['smtp']['server'] ;
                }
                $imap_list = array(
                    'name' => $details['name'],
                    'server' => $details['server'],
                    'port' => $details['port'],
                    'tls' => $details['tls'],
                    'user' => $form['nux_email'],
                    'pass' => $form['nux_pass'],
                );
                if ($details['sieve'] && $this->module_is_supported('sievefilters') && $this->user_config->get('enable_sieve_filter_setting', true)) {
                    $imap_list['sieve_config_host'] = $details['sieve']['host'].':'.$details['sieve']['port'];
                }
                Hm_IMAP_List::add($imap_list);
                $servers = Hm_IMAP_List::dump(false, true);
                $ids = array_keys($servers);
                $new_id = array_pop($ids);
                if (in_server_list('Hm_IMAP_List', $new_id, $form['nux_email'])) {
                    Hm_IMAP_List::del($new_id);
                    Hm_Msgs::add('ERRThis IMAP server and username are already configured');
                    return;
                }
                $imap = Hm_IMAP_List::connect($new_id, false);
                if ($imap && $imap->get_state() == 'authenticated') {
                    if (isset($details['smtp'])) {
                        Hm_SMTP_List::add(array(
                            'name' => $details['name'],
                            'server' => $details['smtp']['server'],
                            'port' => $details['smtp']['port'],
                            'tls' => $details['smtp']['tls'],
                            'user' => $form['nux_email'],
                            'pass' => $form['nux_pass']
                        ));
                        $this->session->record_unsaved('SMTP server added');
                        $smtp_servers = Hm_SMTP_List::dump(false, true);
                        $ids = array_keys($servers);
                        $new_smtp_id = array_pop($ids);
                        if (in_server_list('Hm_SMTP_List', $new_smtp_id, $form['nux_email'])) {
                            Hm_SMTP_List::del($new_smtp_id);
                            Hm_Msgs::add('ERRThis SMTP server and username are already configured');
                        }
                    }
                    Hm_IMAP_List::clean_up();
                    $this->session->record_unsaved('IMAP server added');
                    $this->session->record_unsaved('SMTP server added');
                    $this->session->secure_cookie($this->request, 'hm_reload_folders', '1');
                    Hm_Msgs::add('E-mail account successfully added');
                    $this->save_hm_msgs();
                    $this->session->close_early();
                    $this->out('nux_account_added', true);
                    $this->out('nux_server_id', $new_id);
                    $this->out('nux_service_name', $form['nux_service']);
                }
                else {
                    Hm_IMAP_List::del($new_id);
                    Hm_Msgs::add('ERRAuthentication failed');
                }
            }
        }
    }
}

/**
 * @subpackage nux/handler
 */
class Hm_Handler_setup_nux extends Hm_Handler_Module {
    public function process() {
        Nux_Quick_Services::oauth2_setup($this->config);
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
                if (array_key_exists('nux_account_name', $this->request->post) && trim($this->request->post['nux_account_name'])) {
                    $details['name'] = $this->request->post['nux_account_name'];
                }
                $this->out('nux_add_service_details', $details);
                $this->session->set('nux_add_service_details', $details);
            }
        }
    }
}

/**
 * @subpackage nux/handler
 */
class Hm_Handler_get_nux_service_details extends Hm_Handler_Module {
    public function process() {
        list($success, $form) = $this->process_form(array('nux_service'));
        if ($success) {
            if (Nux_Quick_Services::exists($form['nux_service'])) {
                $details = Nux_Quick_Services::details($form['nux_service']);

                $this->out('nux_add_service_details', $details);
                $this->session->set('nux_add_service_details', $details);
            }
        }
    }
}

/**
 * @subpackage nux/output
 */
class Hm_Output_quick_add_dialog extends Hm_Output_Module {
    protected function output() {
        if ($this->get('single_server_mode')) {
            return '';
        }
        return '<div class="quick_add_section">'.
            '<div class="nux_step_one px-4 pt-">'.
            '<p class="py-3">'.$this->trans('Quickly add an account from popular E-mail providers. To manually configure an account, use the IMAP/SMTP sections below.').'</p>'.
            '<div class="row"><div class="col col-lg-4"><div class="form-floating mb-3">'.
            ' <select id="service_select" name="service_select" class="form-select">'.
            '<option value="">'.$this->trans('Select an E-mail provider').'</option>'.
            Nux_Quick_Services::option_list(false, $this).'</select>'.
            '<label for="service_select">'.$this->trans('Select an E-mail provider').'</label></div>'.

            '<div class="form-floating mb-3">'.
            '<input type="email" id="nux_username" class="form-control nux_username" placeholder="'.$this->trans('Your E-mail address').'">'.
            '<label for="nux_username">'.$this->trans('Username').'</label></div>'.

            '<div class="form-floating mb-3">'.
            '<input type="text" id="nux_account_name" class="form-control nux_account_name" placeholder="'.$this->trans('Account Name [optional]').'">'.
            '<label for="nux_account_name">'.$this->trans('Account name').'</label></div>'.

            '<input type="button" class="nux_next_button btn btn-primary btn-md px-5" value="'.$this->trans('Next').'">'.
            '</div></div></div><div class="nux_step_two px-4 pt-3"></div></div>';
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
class Hm_Output_service_details extends Hm_Output_Module {
    protected function output() {
        $details = $this->get('nux_add_service_details', array());
        $this->out('service_details',  json_encode($details));
    }
}

/**
 * @subpackage nux/output
 */
class Hm_Output_nux_dev_news extends Hm_Output_Module {
    protected function output() {
        $res = '<div class="nux_dev_news mt-3 col-12"><div class="card"><div class="card-body"><div class="card_title"><h4>'.$this->trans('Development Updates').'</h4></div><table>';
        foreach ($this->get('nux_dev_news', array()) as $vals) {
            $res .= sprintf('<tr><td><a href="https://github.com/cypht-org/cypht/commit/%s" target="_blank" rel="noopener">%s</a>'.
                '</td><td class="msg_date">%s</td><td>%s</td><td>%s</td></tr>',
                $this->html_safe($vals['hash']),
                $this->html_safe($vals['shash']),
                $this->html_safe($vals['name']),
                $this->html_safe($vals['age']),
                $this->html_safe($vals['note'])
            );
        }
        $res .= '</table></div></div></div>';
        return $res;
    }
}

/**
 * @subpackage nux/output
 */
class Hm_Output_nux_help extends Hm_Output_Module {
    protected function output() {
        return '<div class="nux_help mt-3 col-lg-6 col-md-12 col-sm-12"><div class="card"><div class="card-body"><div class="card_title"><h4>'.$this->trans('Help').'</h4></div>'.
            $this->trans('Cypht is a webmail program. You can use it to access your E-mail accounts from any service that offers IMAP, or SMTP access - which most do.').' '.
        '</div></div></div>';
    }
}

/**
 * @subpackage nux/output
 */
class Hm_Output_welcome_dialog extends Hm_Output_Module {
    protected function output() {
        if ($this->get('single_server_mode')) {
            return '';
        }
        $server_data = $this->get('nux_server_setup', array());
        $tz = $this->get('tzone');
        $protos = array('imap', 'smtp', 'feeds', 'profiles');

        $res = '<div class="nux_welcome mt-3 col-lg-6 col-md-5 col-sm-12"><div class="card"><div class="card-body"><div class="card-title"><h4>'.$this->trans('Welcome to Cypht').'</h4></div>';
        $res .= '<div class="mb-3"><p>'.$this->trans('Add a popular E-mail source quickly and easily').'</p>';
        $res .= '<a class="mt-3 btn btn-light" href="?page=servers#quick_add_section"><i class="bi bi-person-plus me-3"></i>'.$this->trans('Add an E-mail Account').'</a>';
        $res .= '</div><ul class="mt-4">';

        foreach ($protos as $proto) {
            $proto_dsp = $proto;
            if ($proto == 'feeds') {
                $proto_dsp = 'RSS/ATOM';
            }
            $res .= '<li class="nux_'.$proto.' mt-3">';

            // Check if user have profiles configured
            if ($proto == 'profiles') {
                if ($server_data[$proto] === 0) {
                    $res .= sprintf($this->trans('You don\'t have any profile(s)'));
                    $res .= sprintf(' <a href="?page=profiles">%s</a>', $proto, $this->trans('Add'));
                }
                if ($server_data[$proto] > 0) {
                    $res .= sprintf($this->trans('You have %s profile(s)'), $server_data[$proto]);
                    $res .= sprintf(' <a href="?page=profiles">%s</a>', $this->trans('Manage'));
                }
                $res .= '</li>';
                continue;
            }

            if ($server_data[$proto] === NULL) {
                $res .= sprintf($this->trans('%s services are not enabled for this site. Sorry about that!'), strtoupper($proto_dsp));
            }
            elseif ($server_data[$proto] === 0) {
                $res .= sprintf($this->trans('You don\'t have any %s sources'), strtoupper($proto_dsp));
                $res .= sprintf(' <a href="?page=servers#%s_section">%s</a>', $proto, $this->trans('Add'));
            }
            else {
                if ($server_data[$proto] > 1) {
                    $res .= sprintf($this->trans('You have %d %s sources'), $server_data[$proto], strtoupper($proto_dsp));
                }
                else {
                    $res .= sprintf($this->trans('You have %d %s source'), $server_data[$proto], strtoupper($proto_dsp));
                }
                $res .= sprintf(' <a href="?page=servers#%s_section">%s</a>', $proto, $this->trans('Manage'));
            }
            $res .= '</li>';
        }
        $res .= '</ul>';
        $res .= '<div class="nux_tz">';
        if (!$tz) {
            $res .= $this->trans('Your timezone is NOT set');
        }
        else {
            $res .= sprintf($this->trans('Your timezone is set to %s'), $this->html_safe($tz));
        }
        $res .= ' <a href="?page=settings#general_setting">'.$this->trans('Update').'</a></div></div></div></div>';
        return $res;
    }
}

/**
 * @subpackage nux/output
 */
class Hm_Output_nux_message_list_notice extends Hm_Output_Module {
    protected function output() {
        $msg = '<div class="nux_empty_combined_view">';
        $msg .= $this->trans('You don\'t have any data sources assigned to this page.');
        $msg .= '<br /><a href="?page=servers">'.$this->trans('Add some').'</a>';
        $msg .= '</div>';
        return $msg;
    }
}

/**
 * @subpackage nux/output
 */
class Hm_Output_quick_add_section extends Hm_Output_Module {
    protected function output() {
        if ($this->get('single_server_mode')) {
            return '';
        }
        return '<div class="nux_add_account"><div data-target=".quick_add_section" class="server_section border-bottom cursor-pointer px-1 py-3 pe-auto"><a href="#" class="pe-auto">'.
            '<i class="bi bi-check-circle-fill me-3"></i>'.
            '<b>'.$this->trans('Add an E-mail Account').'</b></a></div>';
    }
}

/**
 * @subpackage nux/functions
 */
if (!hm_exists('oauth2_form')) {
function oauth2_form($details, $mod) {
    $oauth2 = new Hm_Oauth2($details['client_id'], $details['client_secret'], $details['redirect_uri']);
    $url = $oauth2->request_authorization_url($details['auth_uri'], $details['scope'], 'nux_authorization', $details['email']);
    $res = '<input type="hidden" name="nux_service" value="'.$mod->html_safe($details['id']).'" />';
    $res .= '<div class="nux_step_two_title fw-bold">'.$mod->html_safe($details['name']).'</div><div class="mb-3">';
    $res .= $mod->trans('This provider supports Oauth2 access to your account.');
    $res .= $mod->trans(' This is the most secure way to access your E-mail. Click "Enable" to be redirected to the provider site to allow access.');
    $res .= '</div><div class="mb-3"><a class="enable_auth2 btn btn-sm btn-success me-2" href="'.$url.'">'.$mod->trans('Enable').'</a>';
    $res .= '<a href="" class="reset_nux_form btn btn-sm btn-secondary">Reset</a></div>';
    return $res;
}}

/**
 * @subpackage nux/functions
 */
if (!hm_exists('credentials_form')) {
    function credentials_form($details, $mod) {
        $res = '<input type="hidden" id="nux_service" name="nux_service" value="'.$mod->html_safe($details['id']).'" />';
        $res .= '<input type="hidden" name="nux_name" class="nux_name" value="'.$mod->html_safe($details['name']).'" />';
        $res .= '<div class="nux_step_two_title"><b>'.$mod->html_safe($details['name']).'</b></div>';
        $res .= $mod->trans('Enter your password for this E-mail provider to complete the connection process');

        $res .= '<div class="row"><div class="col col-lg-4">';
        // E-mail Address Field
        $res .= '<div class="form-floating mb-3 mt-3">';
        $res .= '<input type="email" class="form-control" id="nux_email" name="nux_email" placeholder="'.$mod->trans('E-mail Address').'" value="'.$mod->html_safe($details['email']).'">';
        $res .= '<label for="nux_email">'.$mod->trans('E-mail Address').'</label></div>';

        // E-mail Password Field
        $res .= '<div class="form-floating mb-3">';
        $res .= '<input type="password" class="form-control nux_password" id="nux_password" name="nux_password" placeholder="'.$mod->trans('E-Mail Password').'">';
        $res .= '<label for="nux_password">'.$mod->trans('E-mail Password').'</label></div>';

        // Connect Button
        $res .= '<input type="button" class="nux_submit px-5 btn btn-primary me-3" value="'.$mod->trans('Connect').'">';

        // Reset Link
        $res .= '<a href="" class="reset_nux_form px-5 btn btn-secondary">Reset</a>';

        $res .= '</div></div>';

        return $res;
    }
}

/**
 * @subpackage nux/functions
 */
if (!hm_exists('data_source_available')) {
function data_source_available($mods, $types) {
    if (!is_array($types)) {
        $types = array($types);
    }
    return count( array_intersect($types, $mods) ) == count( $types );
}}

/**
 * @subpackage nux/lib
 */
class Nux_Quick_Services {

    static private $services = array();
    static private $oauth2 = array();

    static public function add($id, $details) {
        self::$services[$id] = $details;
    }
    static public function oauth2_setup($config) {
        $services = array_keys(config('oauth2'));
        foreach ($services as $service) {
            $vals = $config->get($service, []);
            if (!empty($vals)) {
                self::$services[$service]['auth'] = 'oauth2';
                self::$services[$service]['client_id'] = $vals['client_id'];
                self::$services[$service]['client_secret'] = $vals['client_secret'];
                self::$services[$service]['redirect_uri'] = $vals['client_uri'];
                self::$services[$service]['auth_uri'] = $vals['auth_uri'];
                self::$services[$service]['token_uri'] = $vals['token_uri'];
                self::$services[$service]['refresh_uri'] = $vals['refresh_uri'];
            }
        }
        self::$oauth2 = config('oauth2');
    }

    static public function option_list($current, $mod) {
        $res = '';
        uasort(self::$services, function($a, $b) { return strcasecmp($a['name'], $b['name']); });
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
