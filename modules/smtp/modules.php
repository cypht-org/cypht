<?php

/**
 * SMTP modules
 * @package modules
 * @subpackage smtp
 */

if (!defined('DEBUG_MODE')) { die(); }

require APP_PATH.'modules/smtp/hm-smtp.php';
require APP_PATH.'modules/smtp/hm-mime-message.php';

/**
 * @subpackage smtp/handler
 */
class Hm_Handler_smtp_default_server extends Hm_Handler_Module {
    public function process() {
        list($success, $form) = $this->process_form(array('username', 'password'));
        if ($success) {
            $smtp_server = $this->config->get('default_smtp_server', false);
            if ($smtp_server) {
                $smtp_port = $this->config->get('default_smtp_port', 465);
                $smtp_tls = $this->config->get('default_smtp_tls', true);
                $servers = $this->user_config->get('smtp_servers', array());
                foreach ($servers as $index => $server) {
                    if ($server['server'] == $smtp_server && $server['tls'] == $smtp_tls && $server['port'] == $smtp_port) {
                        continue;
                    }
                    Hm_SMTP_List::add( $server, $index );
                }
                Hm_SMTP_List::add(array(
                    'name' => 'Default',
                    'server' => $smtp_server,
                    'port' => $smtp_port,
                    'tls' => $smtp_tls,
                    'user' => $form['username'],
                    'pass' => $form['password']
                ));
                $smtp_servers = Hm_SMTP_List::dump(false, true);
                $this->user_config->set('smtp_servers', $smtp_servers);
                $user_data = $this->user_config->dump();
                $this->session->set('user_data', $user_data);
                Hm_Debug::add('Default SMTP server added');
            }
        }
    }
}

/**
 * @subpackage smtp/handler
 * @todo: update this for the new settings system
 */
class Hm_Handler_process_compose_type extends Hm_Handler_Module {
    public function process() {
        list($success, $form) = $this->process_form(array('save_settings', 'smtp_compose_type_setting'));
        $new_settings = $this->get('new_user_settings', array());
        $settings = $this->get('user_settings', array());

        if ($success) {
            if ($form['smtp_compose_type_setting'] == 0 || $form['smtp_compose_type_setting'] == 1) {
                $new_settings['smtp_compose_type'] = $form['smtp_compose_type_setting'];
            }
            else {
                $settings['smtp_compose_type'] = $this->user_config->get('smtp_compose_type', false);
            }
        }
        else {
            $settings['smtp_compose_type'] = $this->user_config->get('smtp_compose_type', false);
        }
        $this->out('new_user_settings', $new_settings, false);
        $this->out('user_settings', $settings, false);
    }
}

/**
 * @subpackage smtp/handler
 */
class Hm_Handler_smtp_save_draft extends Hm_Handler_Module {
    public function process() {
        $to = array_key_exists('draft_to', $this->request->post) ? $this->request->post['draft_to'] : '';
        $body = array_key_exists('draft_body', $this->request->post) ? $this->request->post['draft_body'] : '';
        $subject = array_key_exists('draft_subject', $this->request->post) ? $this->request->post['draft_subject'] : '';
        $this->session->set('compose_draft', array('draft_to' => $to, 'draft_body' => $body, 'draft_subject' => $subject));
    }
}

/**
 * @subpackage smtp/handler
 */
class Hm_Handler_load_smtp_servers_from_config extends Hm_Handler_Module {
    public function process() {
        $servers = $this->user_config->get('smtp_servers', array());
        foreach ($servers as $index => $server) {
            Hm_SMTP_List::add( $server, $index );
        }
        $reply_type = false;
        if (array_key_exists('reply', $this->request->get) && $this->request->get['reply']) {
            $reply_type = 'reply';
        }
        elseif (array_key_exists('forward', $this->request->get) && $this->request->get['forward']) {
            $reply_type = 'forward';
        }
        if ($reply_type) {
            $details = $this->session->get('reply_details', array());
            if (!empty($details)) {
                $this->out('reply_details', $details);
                $this->out('reply_type', $reply_type);
            }
        }
        $this->out('compose_draft', $this->session->get('compose_draft', array()), false);
        $compose_type = $this->user_config->get('smtp_compose_type', 0);
        if ($this->get('is_mobile', false)) {
            $compose_type = 0;
        }
        $this->out('smtp_compose_type', $compose_type);
    }
}

/**
 * @subpackage smtp/handler
 */
class Hm_Handler_process_add_smtp_server extends Hm_Handler_Module {
    public function process() {
        if (isset($this->request->post['submit_smtp_server'])) {
            list($success, $form) = $this->process_form(array('new_smtp_name', 'new_smtp_address', 'new_smtp_port'));
            if (!$success) {
                $this->out('old_form', $form);
                Hm_Msgs::add('ERRYou must supply a name, a server and a port');
            }
            else {
                $tls = false;
                if (isset($this->request->post['tls'])) {
                    $tls = true;
                }
                if ($con = fsockopen($form['new_smtp_address'], $form['new_smtp_port'], $errno, $errstr, 2)) {
                    Hm_SMTP_List::add( array(
                        'name' => $form['new_smtp_name'],
                        'server' => $form['new_smtp_address'],
                        'port' => $form['new_smtp_port'],
                        'tls' => $tls));
                    Hm_Msgs::add('Added SMTP server!');
                    $this->session->record_unsaved('SMTP server added');
                }
                else {
                    Hm_Msgs::add(sprintf('ERRCound not add server: %s', $errstr));
                }
            }
        }
    }
}

/**
 * @subpackage smtp/handler
 */
class Hm_Handler_add_smtp_servers_to_page_data extends Hm_Handler_Module {
    public function process() {
        $servers = Hm_SMTP_List::dump();
        $this->out('smtp_servers', $servers);
    }
}

/**
 * @subpackage smtp/handler
 */
class Hm_Handler_save_smtp_servers extends Hm_Handler_Module {
    public function process() {
        $servers = Hm_SMTP_List::dump(false, true);
        $this->user_config->set('smtp_servers', $servers);
    }
}

/**
 * @subpackage smtp/handler
 */
class Hm_Handler_smtp_save extends Hm_Handler_Module {
    public function process() {
        $just_saved_credentials = false;
        if (isset($this->request->post['smtp_save'])) {
            list($success, $form) = $this->process_form(array('smtp_user', 'smtp_pass', 'smtp_server_id'));
            if (!$success) {
                Hm_Msgs::add('ERRUsername and Password are required to save a connection');
            }
            else {
                $smtp = Hm_SMTP_List::connect($form['smtp_server_id'], false, $form['smtp_user'], $form['smtp_pass'], true);
                if ($smtp->state == 'authed') {
                    $just_saved_credentials = true;
                    Hm_Msgs::add("Server saved");
                    $this->session->record_unsaved('SMTP server saved');
                }
                else {
                    Hm_Msgs::add("ERRUnable to save this server, are the username and password correct?");
                }
            }
        }
        $this->out('just_saved_credentials', $just_saved_credentials);
    }
}

/**
 * @subpackage smtp/handler
 */
class Hm_Handler_smtp_forget extends Hm_Handler_Module {
    public function process() {
        $just_forgot_credentials = false;
        if (isset($this->request->post['smtp_forget'])) {
            list($success, $form) = $this->process_form(array('smtp_server_id'));
            if ($success) {
                Hm_SMTP_List::forget_credentials($form['smtp_server_id']);
                $just_forgot_credentials = true;
                Hm_Msgs::add('Server credentials forgotten');
                $this->session->record_unsaved('SMTP server credentials forgotten');
            }
            else {
                $this->out('old_form', $form);
            }
        }
        $this->out('just_forgot_credentials', $just_forgot_credentials);
    }
}

/**
 * @subpackage smtp/handler
 */
class Hm_Handler_smtp_delete extends Hm_Handler_Module {
    public function process() {
        if (isset($this->request->post['smtp_delete'])) {
            list($success, $form) = $this->process_form(array('smtp_server_id'));
            if ($success) {
                $res = Hm_SMTP_List::del($form['smtp_server_id']);
                if ($res) {
                    $this->out('deleted_server_id', $form['smtp_server_id']);
                    Hm_Msgs::add('Server deleted');
                    $this->session->record_unsaved('SMTP server deleted');
                }
            }
            else {
                $this->out(old_form, $form);
            }
        }
    }
}

/**
 * @subpackage smtp/handler
 */
class Hm_Handler_smtp_connect extends Hm_Handler_Module {
    public function process() {
        $smtp = false;
        if (isset($this->request->post['smtp_connect'])) {
            list($success, $form) = $this->process_form(array('smtp_user', 'smtp_pass', 'smtp_server_id'));
            $smtp_details = Hm_SMTP_List::dump($form['smtp_server_id'], true);
            if ($smtp_details && ($success | array_key_exists('smtp_server_id', $form))) {
                if (array_key_exists('auth', $smtp_details) && $smtp_details['auth'] == 'xoauth2') {
                    $results = smtp_refresh_oauth2_token($smtp_details, $this->config);
                    if (!empty($results)) {
                        if (Hm_SMTP_List::update_oauth2_token($form['smtp_server_id'], $results[1], $results[0])) {
                            Hm_Debug::add(sprintf('Oauth2 token refreshed for SMTP server id %d', $form['smtp_server_id']));
                            $servers = Hm_SMTP_List::dump(false, true);
                            $this->user_config->set('smtp_servers', $servers);
                            $this->session->set('user_data', $this->user_config->dump());
                        }
                    }
                }
            }
            if ($success) {
                $smtp = Hm_SMTP_List::connect($form['smtp_server_id'], false, $form['smtp_user'], $form['smtp_pass']);
            }
            elseif (isset($form['smtp_server_id'])) {
                $smtp = Hm_SMTP_List::connect($form['smtp_server_id'], false);
            }
            if ($smtp && $smtp->state == 'authed') {
                Hm_Msgs::add("Successfully authenticated to the SMTP server");
            }
            elseif ($smtp && $smtp->state == 'connected') {
                Hm_Msgs::add("ERRConnected, but failed to authenticated to the SMTP server");
            }
            else {
                Hm_Msgs::add("ERRFailed to authenticate to the SMTP server");
            }
        }
    }
}

/**
 * @subpackage smtp/handler
 */
class Hm_Handler_process_compose_form_submit extends Hm_Handler_Module {
    public function process() {
        if (array_key_exists('smtp_send', $this->request->post)) {
            list($success, $form) = $this->process_form(array('compose_to', 'compose_subject', 'smtp_server_id'));
            if ($success) {
                $draft = array(
                    'draft_to' => $form['compose_to'],
                    'draft_body' => '',
                    'draft_subject' => $form['compose_subject']
                );
                $to = $form['compose_to'];
                $subject = $form['compose_subject'];
                $body = '';
                $from = '';
                if (array_key_exists('compose_body', $this->request->post)) {
                    $body = $this->request->post['compose_body'];
                    $draft['draft_body'] = $this->request->post['compose_body'];
                }
                $smtp_details = Hm_SMTP_List::dump($form['smtp_server_id'], true);
                if ($smtp_details) {
                    $from = $smtp_details['user'];
                    if (array_key_exists('auth', $smtp_details) && $smtp_details['auth'] == 'xoauth2') {
                        $results = smtp_refresh_oauth2_token($smtp_details, $this->config);
                        if (!empty($results)) {
                            if (Hm_SMTP_List::update_oauth2_token($form['smtp_server_id'], $results[1], $results[0])) {
                                Hm_Debug::add(sprintf('Oauth2 token refreshed for SMTP server id %d', $form['smtp_server_id']));
                                $servers = Hm_SMTP_List::dump(false, true);
                                $this->user_config->set('smtp_servers', $servers);
                                $this->session->set('user_data', $this->user_config->dump());
                            }
                        }
                    }
                    $smtp = Hm_SMTP_List::connect($form['smtp_server_id'], false);
                    if ($smtp && $smtp->state == 'authed') {
                        $mime = new Hm_MIME_Msg($to, $subject, $body, $from, $this->get('smtp_compose_type', 0));
                        $recipients = $mime->get_recipient_addresses();
                        if (empty($recipients)) {
                            Hm_Msgs::add("ERRNo valid receipts found");
                        }
                        else {
                            $err_msg = $smtp->send_message($from, $recipients, $mime->get_mime_msg());
                            if ($err_msg) {
                                Hm_Msgs::add(sprintf("ERR%s", $err_msg));
                            }
                            else {
                                $draft = array();
                                Hm_Msgs::add("Message Sent");
                            }
                        }
                    }
                    else {
                        Hm_Msgs::add("ERRFailed to authenticate to the SMTP server");
                    }
                }
                $this->session->set('compose_draft', $draft);
            }
            else {
                Hm_Msgs::add('ERRRequired field missing');
            }
        }
    }
}

/**
 * @subpackage smtp/output
 */
class Hm_Output_compose_form extends Hm_Output_Module {
    protected function output() {
        $to = '';
        $subject = '';
        $body = '';
        $cc = '';
        $bcc = '';
        $draft = $this->get('compose_draft', array());
        $reply = $this->get('reply_details', array());
        $reply_type = $this->get('reply_type', '');
        $html = $this->get('smtp_compose_type', 0);
        if (!empty($reply)) {
            list($to, $subject, $body) = format_reply_fields($reply['msg_text'], $reply['msg_headers'], $reply['msg_struct'], $html, $this, $reply_type);
        }
        elseif (!empty($draft)) {
            $to = $draft['draft_to'];
            $subject = $draft['draft_subject'];
            $body= $draft['draft_body'];
        }
        $res = '';
        if ($html == 1) {
            $res .= '<script type="text/javascript" src="modules/smtp/assets/kindeditor/kindeditor-all-min.js"></script>'.
                '<link href="modules/smtp/assets/kindeditor/themes/default/default.css" rel="stylesheet" />'.
                '<script type="text/javascript">KindEditor.ready(function(K) { K.create("#compose_body", {items:'.
                "['formatblock', 'fontname', 'fontsize', 'forecolor', 'hilitecolor', 'bold',".
                "'italic', 'underline', 'strikethrough', 'lineheight', 'table', 'hr', 'pagebreak', 'link', 'unlink',".
                "'justifyleft', 'justifycenter', 'justifyright',".
                "'justifyfull', 'insertorderedlist', 'insertunorderedlist', 'indent', 'outdent', '|',".
                "'undo', 'redo', 'preview', 'print', '|', 'selectall', 'cut', 'copy', 'paste',".
                "'plainpaste', 'wordpaste', '|', 'source', 'fullscreen']".
                ",basePath: 'modules/smtp/assets/kindeditor/'".
                '})});;</script>';
        }
        $res .= '<div class="compose_page"><div class="content_title">'.$this->trans('Compose').'</div>'.
            '<form class="compose_form" method="post" action="?page=compose">'.
            '<input type="hidden" name="hm_page_key" value="'.$this->html_safe(Hm_Request_Key::generate()).'" />'.
            '<input value="'.$this->html_safe($to).'" list="to_contacts" required name="compose_to" class="compose_to" type="text" placeholder="'.$this->trans('To').'" />'.
            '<datalist id="to_contacts"></datalist>'.
            '<input value="'.$this->html_safe($cc).'" name="compose_cc" class="compose_cc" type="text" placeholder="'.$this->trans('CC').'" />'.
            '<input value="'.$this->html_safe($bcc).'" name="compose_bcc" class="compose_bcc" type="text" placeholder="'.$this->trans('BCC').'" />'.
            '<input value="'.$this->html_safe($subject).'" required name="compose_subject" class="compose_subject" type="text" placeholder="'.$this->trans('Subject').'" />'.
            '<textarea novalidate id="compose_body" name="compose_body" class="compose_body">'.$this->html_safe($body).'</textarea>'.
            smtp_server_dropdown($this->module_output(), $this).
            '<input class="smtp_send" type="submit" value="'.$this->trans('Send').'" name="smtp_send" />'.
            '<input class="smtp_reset" type="button" value="'.$this->trans('Reset').'" /></form></div>';
        return $res;
    }
}

/**
 * @subpackage smtp/output
 */
class Hm_Output_add_smtp_server_dialog extends Hm_Output_Module {
    protected function output() {
        $count = count($this->get('smtp_servers', array()));
        $count = sprintf($this->trans('%d configured'), $count);
        return '<div class="smtp_server_setup"><div data-target=".smtp_section" class="server_section">'.
            '<img alt="" src="'.Hm_Image_Sources::$doc.'" width="16" height="16" />'.
            ' '.$this->trans('SMTP Servers').' <div class="server_count">'.$count.'</div></div><div class="smtp_section"><form class="add_server" method="POST">'.
            '<input type="hidden" name="hm_page_key" value="'.$this->html_safe(Hm_Request_Key::generate()).'" />'.
            '<div class="subtitle">'.$this->trans('Add an SMTP Server').'</div>'.
            '<table><tr><td colspan="2"><label for="new_smtp_name" class="screen_reader">'.$this->trans('SMTP account name').'</label>'.
            '<input required type="text" id="new_smtp_name" name="new_smtp_name" class="txt_fld" value="" placeholder="'.$this->trans('Account name').'" /></td></tr>'.
            '<tr><td colspan="2"><label for="new_smtp_address" class="screen_reader">'.$this->trans('SMTP server address').'</label>'.
            '<input required type="text" id="new_smtp_address" name="new_smtp_address" class="txt_fld" placeholder="'.$this->trans('SMTP server address').'" value=""/></td></tr>'.
            '<tr><td colspan="2"><label for="new_smtp_port" class="screen_reader">'.$this->trans('SMTP port').'</label>'.
            '<input required type="number" id="new_smtp_port" name="new_smtp_port" class="port_fld" value="" placeholder="'.$this->trans('Port').'"></td></tr>'.
            '<tr><td><input type="checkbox" name="tls" value="1" id="smtp_tls" checked="checked" /> <label for="smtp_tls">'.$this->trans('Use TLS').'</label></td>'.
            '<td><input type="submit" value="'.$this->trans('Add').'" name="submit_smtp_server" /></td></tr>'.
            '</table></form>';
    }
}

/**
 * @subpackage smtp/output
 */
class Hm_Output_compose_type_setting extends Hm_Output_Module {
    protected function output() {
        $selected = 2;
        $settings = $this->get('user_settings', array());
        if (array_key_exists('smtp_compose_type', $settings)) {
            $selected = $settings['smtp_compose_type'];
        }
        $res = '<tr class="general_setting"><td>'.$this->trans('Outbound mail format').'</td><td><select name="smtp_compose_type_setting">';
        $res .= '<option ';
        if ($selected == 0) {
            $res .= 'selected="selected" ';
        }
        $res .= 'value="0">'.$this->trans('Plain text').'</option><option ';
        if ($selected == 1) {
            $res .= 'selected="selected" ';
        }
        $res .= 'value="1">'.$this->trans('HTML').'</option></select></td></tr>';
        return $res;
    }
}

/**
 * @subpackage smtp/output
 */
class Hm_Output_display_configured_smtp_servers extends Hm_Output_Module {
    protected function output() {
        $res = '';
        foreach ($this->get('smtp_servers', array()) as $index => $vals) {

            $no_edit = false;

            if (isset($vals['user'])) {
                $disabled = 'disabled="disabled"';
                $user_pc = $vals['user'];
                $pass_pc = $this->trans('[saved]');
            }
            else {
                $user_pc = '';
                $pass_pc = $this->trans('Password');
                $disabled = '';
            }
            if ($vals['name'] == 'Default-Auth-Server') {
                $vals['name'] = $this->trans('Default');
                $no_edit = true;
            }
            $res .= '<div class="configured_server">';
            $res .= sprintf('<div class="server_title">%s</div><div class="server_subtitle">%s/%d %s</div>',
                $this->html_safe($vals['name']), $this->html_safe($vals['server']), $this->html_safe($vals['port']), $vals['tls'] ? 'TLS' : '' );
            $res .= 
                '<form class="smtp_connect" method="POST">'.
                '<input type="hidden" name="hm_page_key" value="'.$this->html_safe(Hm_Request_Key::generate()).'" />'.
                '<input type="hidden" name="smtp_server_id" value="'.$this->html_safe($index).'" /><span> '.
                '<label class="screen_reader" for="smtp_user_'.$index.'">'.$this->trans('SMTP username').'</label>'.
                '<input '.$disabled.' class="credentials" id="smtp_user_'.$index.'" placeholder="'.$this->trans('Username').'" type="text" name="smtp_user" value="'.$user_pc.'"></span>'.
                '<span> <label class="screen_reader" for="smtp_pass_'.$index.'">'.$this->trans('SMTP password').'</label>'.
                '<input '.$disabled.' class="credentials smtp_password" placeholder="'.$pass_pc.'" type="password" id="smtp_pass_'.$index.'" name="smtp_pass"></span>';
            if (!$no_edit) {
                $res .= '<input type="submit" value="'.$this->trans('Test').'" class="test_smtp_connect" />';
                if (!isset($vals['user']) || !$vals['user']) {
                    $res .= '<input type="submit" value="'.$this->trans('Delete').'" class="delete_smtp_connection" />';
                    $res .= '<input type="submit" value="'.$this->trans('Save').'" class="save_smtp_connection" />';
                }
                else {
                    $res .= '<input type="submit" value="'.$this->trans('Delete').'" class="delete_smtp_connection" />';
                    $res .= '<input type="submit" value="'.$this->trans('Forget').'" class="forget_smtp_connection" />';
                }
                $res .= '<input type="hidden" value="ajax_smtp_debug" name="hm_ajax_hook" />';
            }
            $res .= '</form></div>';
        }
        $res .= '<br class="clear_float" /></div></div>';
        return $res;
    }
}

/**
 * @subpackage smtp/output
 */
class Hm_Output_compose_page_link extends Hm_Output_Module {
    protected function output() {
        $res = '<li class="menu_compose"><a class="unread_link" href="?page=compose">'.
            '<img class="account_icon" src="'.$this->html_safe(Hm_Image_Sources::$doc).'" alt="" width="16" height="16" /> '.$this->trans('Compose').'</a></li>';

        if ($this->format == 'HTML5') {
            return $res;
        }
        $this->concat('formatted_folder_list', $res);
    }
}

/**
 * @subpackage smtp/functions
 */
function smtp_server_dropdown($data, $output_mod) {
    $res = '<select name="smtp_server_id" class="compose_server">';
    if (array_key_exists('smtp_servers', $data)) {
        foreach ($data['smtp_servers'] as $id => $vals) {
            $res .= '<option value="'.$output_mod->html_safe($id).'">'.$output_mod->html_safe(sprintf("%s - %s", $vals['name'], $vals['server'])).'</option>';
        }
    }
    $res .= '</select>';
    return $res;
}

/**
 * @subpackage smtp/functions
 */
function build_mime_msg($to, $subject, $body, $from) {
    $headers = array(
        'from' => $from,
        'to' => $to,
        'subject' => $subject,
        'date' => date('r')
    );
    $body = array(
        1 => array(
            'type' => TYPETEXT,
            'subtype' => 'plain',
            'contents.data' => $body
        )
    );
    return imap_mail_compose($headers, $body);
}

/**
 * Check for and do an Oauth2 token reset if needed
 * @param array $server imap server data
 * @param object $config site config object
 * @return mixed
 */
function smtp_refresh_oauth2_token($server, $config) {

    if (array_key_exists('expiration', $server) && (int) $server['expiration'] <= time()) {
        $oauth2_data = get_oauth2_data($config);
        $details = array();
        if ($server['server'] == 'smtp.gmail.com') {
            $details = $oauth2_data['gmail'];
        }
        if (!empty($details)) {
            $oauth2 = new Hm_Oauth2($details['client_id'], $details['client_secret'], $details['client_uri']);
            $result = $oauth2->refresh_token($details['refresh_uri'], $server['refresh_token']);
            if (array_key_exists('access_token', $result)) {
                return array(strtotime(sprintf('+%d seconds', $result['expires_in'])), $result['access_token']);
            }
        }
    }
    return array();
}



