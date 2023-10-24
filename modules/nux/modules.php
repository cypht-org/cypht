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
            $profiles = new Hm_Profiles($this);
            $profiles = count($profiles->list_all());
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
                        $smtp_servers = Hm_SMTP_List::dump(false, true);
                        $this->user_config->set('smtp_servers', $smtp_servers);
                    }
                    Hm_Msgs::add('E-mail account successfully added');
                    $servers = Hm_IMAP_List::dump(false, true);
                    $this->user_config->set('imap_servers', $servers);
                    Hm_IMAP_List::clean_up();
                    $user_data = $this->user_config->dump();
                    if (!empty($user_data)) {
                        $this->session->set('user_data', $user_data);
                    }
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
                        } else {
                            $this->user_config->set('smtp_servers', $smtp_servers);
                        }
                    }
                    $this->user_config->set('imap_servers', $servers);
                    Hm_IMAP_List::clean_up();
                    $user_data = $this->user_config->dump();
                    if (!empty($user_data)) {
                        $this->session->set('user_data', $user_data);
                    }
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
            '<div class="nux_step_one">'.
            $this->trans('Quickly add an account from popular E-mail providers. To manually configure an account, use the IMAP/SMTP sections below.').
            '<br /><br /><label class="screen_reader" for="service_select">'.$this->trans('Select an E-mail provider').'</label>'.
            ' <select id="service_select" name="service_select"><option value="">'.$this->trans('Select an E-mail provider').'</option>'.Nux_Quick_Services::option_list(false, $this).'</select>'.
            '<label class="screen_reader" for="nux_username">'.$this->trans('Username').'</label>'.
            '<br /><input type="email" id="nux_username" class="nux_username" placeholder="'.$this->trans('Your E-mail address').'" />'.
            '<label class="screen_reader" for="nux_account_name">'.$this->trans('Account name').'</label>'.
            '<br /><input type="text" id="nux_account_name" class="nux_account_name" placeholder="'.$this->trans('Account Name [optional]').'" />'.
            '<br /><input type="button" class="nux_next_button" value="'.$this->trans('Next').'" />'.
            '</div><div class="nux_step_two"></div></div></div>';
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
        $res = '<div class="nux_dev_news"><div class="nux_title">'.$this->trans('Development Updates').'</div><table>';
        foreach ($this->get('nux_dev_news', array()) as $vals) {
            $res .= sprintf('<tr><td><a href="https://github.com/cypht-org/cypht/commit/%s">%s</a>'.
                '</td><td class="msg_date">%s</td><td>%s</td><td>%s</td></tr>',
                $this->html_safe($vals['hash']),
                $this->html_safe($vals['shash']),
                $this->html_safe($vals['name']),
                $this->html_safe($vals['age']),
                $this->html_safe($vals['note'])
            );
        }
        $res .= '</table></div>';
        return $res;
    }
}

/**
 * @subpackage nux/output
 */
class Hm_Output_nux_help extends Hm_Output_Module {
    protected function output() {
        return '<div class="nux_help"><div class="nux_title">'.$this->trans('Help').'</div>'.
            $this->trans('Cypht is a webmail program. You can use it to access your E-mail accounts from any service that offers IMAP, or SMTP access - which most do.').' '.
        '</div>';
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

        $res = '<div class="nux_welcome"><div class="nux_title">'.$this->trans('Welcome to Cypht').'</div>';
        $res .= '<div class="nux_qa">'.$this->trans('Add a popular E-mail source quickly and easily');
        $res .= ' <a class="nux_try_out" href="?page=servers#quick_add_section">'.$this->trans('Add an E-mail Account').'</a>';
        $res .= '</div><ul>';

        foreach ($protos as $proto) {
            $proto_dsp = $proto;
            if ($proto == 'feeds') {
                $proto_dsp = 'RSS/ATOM';
            }
            $res .= '<li class="nux_'.$proto.'">';

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
        $res .= ' <a href="?page=settings#general_setting">'.$this->trans('Update').'</a></div></div>';
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
        return '<div class="nux_add_account"><div data-target=".quick_add_section" class="server_section">'.
            '<img src="'.Hm_Image_Sources::$circle_check.'" alt="" width="16" height="16" /> '.
            $this->trans('Add an E-mail Account').'</div>';
    }
}



class Hm_Output_server_config_stepper extends Hm_Output_Module {
    protected function output() {

    return '<div class="smtp_imap_server_setup">
         <div data-target=".server_config_section" class="server_section">'.
                '<img alt="" src="'.Hm_Image_Sources::$env_closed.'" width="16" height="16" />'. $this->trans('IMAP & SMTP Servers').'
                 <div class="server_count">10</div>
         </div>
         <div class="server_config_section">
            <div class="stepper">
                <div class="step-container">
                    <div id="step_config_1" class="step step_config">
                        <div class="step_config-title">
                            <h2>Step 1</h2>
                            <span>(Authentication)</span>
                        </div>
                        <div>
                            <form>
                               <div class="step_config-form_item">
                                    <label for="nux_config_profile_name">Profile Name</label>
                                    <br />
                                    <input type="text" class="stepper_input" id="nux_config_profile_name" placeholder="Profile name" />
                                    <span id="nux_config_profile_name-error" class="error-message"></span>
                               </div>
                               <div class="step_config-form_item">
                                    <label for="nux_config_email">Email</label>
                                    <br />
                                    <input type="email"  class="stepper_input" id="nux_config_email" placeholder="email" />
                                    <span id="nux_config_email-error" class="error-message"></span>
                               </div>
                               <div class="step_config-form_item">
                                    <label for="nux_config_password">Password</label>
                                    <br />
                                    <input type="password"  class="stepper_input" id="nux_config_password" placeholder="password" />
                                    <span id="nux_config_password-error" class="error-message"></span>
                               </div>
                            </form>
                        </div>
                        <div class="step_config-actions">
                            <button class="nux_stepper_btn" onclick="display_config_step(2)">Cancel</button>
                            <button class="nux_stepper_btn" onclick="display_config_step(2)">Next</button>
                        </div>
                    </div>
                    <div id="step_config_2" class="step step_config">
                        <div class="step_config-title">
                            <h2>Step 2</h2>
                            <span>(Mail server configuration)</span>
                        </div>
                        <div>
                            <form>
                               <div class="step_config-form_item">
                                    <label for="nux_config_provider">Provider</label>
                                    <br />
                                    <select id="nux_config_provider" class="stepper_input" name="nux_config_provider"><option value="">'.$this->trans('Select an E-mail provider').'</option>'.Nux_Quick_Services::option_list(false, $this).'</select>
                               </div>
                               <div class="step_config-form_item">
                                    <input type="checkbox"  class="step_config-form_item-checkbox" onchange="handleSmtpImapCheckboxChange(this)" id="nux_config_is_sender" checked />
                                    <label for="nux_config_is_sender">Sender account</label>
                               </div>
                               <div class="step_config-form_item">
                                    <input type="checkbox"  class="step_config-form_item-checkbox" onchange="handleSmtpImapCheckboxChange(this)" id="nux_config_is_receiver" checked />
                                    <label for="nux_config_is_receiver">Receiver account</label>
                               </div>

                               <div class="step_config-smtp_imap_bloc">
                                    <div class="step_config-smtp_bloc" id="step_config-smtp_bloc">
                                       <label>SMTP</label>
                                       <div class="step_config-form_item">
                                           <label for="nux_config_smtp_address">Address</label>
                                           <br />
                                           <input type="text" style="height: 20px;"  class="stepper_input" id="nux_config_smtp_address" placeholder="Address" />
                                       </div>
                                       <div class="step_config-smtp_imap_port_bloc">
                                           <input type="number" style="height: 20px;" class="stepper_input" id="nux_config_smtp_port"/>
                                           <div>
                                               <input type="radio" id="smtp_tls" name="nux_config_smtp_con_type" value="tls">
                                               <label for="smtp_tls">Use TLS</label><br>
                                               <input type="radio" id="smtp_start_tls" name="nux_config_smtp_con_type" value="start_tls_or_unencrypted">
                                               <label for="smtp_start_tls">STARTTLS or unencrypted</label><br>
                                           </div>
                                       </div>
                                   </div>
                                   <div class="step_config-smtp_bloc" id="step_config-imap_bloc">
                                      <label>IMAP</label>
                                      <div class="step_config-form_item">
                                          <label for="nux_config_imap_address">Address</label>
                                          <br />
                                          <input type="text" style="height: 20px;"  class="stepper_input" id="nux_config_imap_address" placeholder="Address" />
                                      </div>
                                      <div class="step_config-smtp_imap_port_bloc">
                                         <input type="number" style="height: 20px;" class="stepper_input" id="nux_config_imap_port"/>
                                         <div>
                                             <input type="radio" id="imap_tls" name="nux_config_imap_con_type" value="tls">
                                             <label for="imap_tls">Use TLS</label><br>
                                             <input type="radio" id="imap_start_tls" name="nux_config_imap_con_type" value="start_tls_or_unencrypted">
                                             <label for="imap_start_tls">STARTTLS or unencrypted</label><br>
                                         </div>
                                      </div>
                                  </div>
                               </div>

                               <div class="step_config-form_item">
                                    <input type="checkbox"  class="step_config-form_item-checkbox" id="nux_enable_sieve" />
                                    <label for="nux_enable_sieve">Enable Sieve</label>
                               </div>
                               <div class="step_config-form_item">
                                    <input type="checkbox"  class="step_config-form_item-checkbox" id="nux_create_profile" checked />
                                    <label for="nux_create_profile">Create Profile</label>
                               </div>
                            </form>
                        </div>
                        <div class="step_config-actions">
                            <button class="nux_stepper_btn" onclick="display_config_step(1)">Previous</button>
                            <button class="nux_stepper_btn" onclick="display_config_step(0)">Cancel</button>
                            <button class="nux_stepper_btn" onclick="display_config_step(3)">Finish</button>
                        </div>
                    </div>
                    <button id="step_config_0" class="nux_stepper_btn step_config current_config_step" onclick="display_config_step(1)">Add a new server</button>
                </div>
            </div>
         </div>
    </div>
';
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
    $res .= '<div class="nux_step_two_title">'.$mod->html_safe($details['name']).'</div><div>';
    $res .= $mod->trans('This provider supports Oauth2 access to your account.');
    $res .= $mod->trans(' This is the most secure way to access your E-mail. Click "Enable" to be redirected to the provider site to allow access.');
    $res .= '</div><a class="enable_auth2" href="'.$url.'">'.$mod->trans('Enable').'</a>';
    $res .= '<a href="" class="reset_nux_form">Reset</a>';
    return $res;
}}

/**
 * @subpackage nux/functions
 */
if (!hm_exists('credentials_form')) {
function credentials_form($details, $mod) {
    $res = '<input type="hidden" id="nux_service" name="nux_service" value="'.$mod->html_safe($details['id']).'" />';
    $res .= '<input type="hidden" name="nux_name" class="nux_name" value="'.$mod->html_safe($details['name']).'" />';
    $res .= '<div class="nux_step_two_title">'.$mod->html_safe($details['name']).'</div>';
    $res .= $mod->trans('Enter your password for this E-mail provider to complete the connection process');
    $res .= '<br /><br /><label class="screen_reader" for="nux_email">';
    $res .= $mod->trans('E-mail Address').'</label><input type="email" id="nux_email" name="nux_email" value="'.$mod->html_safe($details['email']).'" />';
    $res .= '<br /><label class="screen_reader" for="nux_password">'.$mod->trans('E-mail Password').'</label>';
    $res .= '<input type="password" id="nux_password" placeholder="'.$mod->trans('E-Mail Password').'" name="nux_password" class="nux_password" />';
    $res .= '<br /><input type="button" class="nux_submit" value="'.$mod->trans('Connect').'" /><br />';
    $res .= '<a href="" class="reset_nux_form">Reset</a>';
    return $res;
}}

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
        $settings = array();
        $settings = get_ini($config, 'oauth2.ini', true);
        if (!empty($settings)) {
            foreach ($settings as $service => $vals) {
                self::$services[$service]['auth'] = 'oauth2';
                self::$services[$service]['client_id'] = $vals['client_id'];
                self::$services[$service]['client_secret'] = $vals['client_secret'];
                self::$services[$service]['redirect_uri'] = $vals['client_uri'];
                self::$services[$service]['auth_uri'] = $vals['auth_uri'];
                self::$services[$service]['token_uri'] = $vals['token_uri'];
                self::$services[$service]['refresh_uri'] = $vals['refresh_uri'];
            }
        }
        self::$oauth2 = $settings;
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


