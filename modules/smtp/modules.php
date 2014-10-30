<?php

if (!defined('DEBUG_MODE')) { die(); }

require APP_PATH.'modules/smtp/hm-smtp.php';

class Hm_Handler_load_smtp_servers_from_config extends Hm_Handler_Module {
    public function process($data) {
        $servers = $this->user_config->get('smtp_servers', array());
        foreach ($servers as $index => $server) {
            Hm_SMTP_List::add( $server, $index );
        }
        return $data;
    }
}

class Hm_Handler_process_add_smtp_server extends Hm_Handler_Module {
    public function process($data) {
        if (isset($this->request->post['submit_smtp_server'])) {
            list($success, $form) = $this->process_form(array('new_smtp_name', 'new_smtp_address', 'new_smtp_port'));
            if (!$success) {
                $data['old_form'] = $form;
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
        return $data;
    }
}

class Hm_Handler_add_smtp_servers_to_page_data extends Hm_Handler_Module {
    public function process($data) {
        $data['smtp_servers'] = array();
        $servers = Hm_SMTP_List::dump();
        if (!empty($servers)) {
            $data['smtp_servers'] = $servers;
        }
        return $data;
    }
}

class Hm_Handler_save_smtp_servers extends Hm_Handler_Module {
    public function process($data) {
        $servers = Hm_SMTP_List::dump(false, true);
        $this->user_config->set('smtp_servers', $servers);
        return $data;
    }
}

class Hm_Handler_smtp_save extends Hm_Handler_Module {
    public function process($data) {
        $data['just_saved_credentials'] = false;
        if (isset($this->request->post['smtp_save'])) {
            list($success, $form) = $this->process_form(array('smtp_user', 'smtp_pass', 'smtp_server_id'));
            if (!$success) {
                Hm_Msgs::add('ERRUsername and Password are required to save a connection');
            }
            else {
                $smtp = Hm_SMTP_List::connect($form['smtp_server_id'], false, $form['smtp_user'], $form['smtp_pass'], true);
                if ($smtp->state == 'authed') {
                    $data['just_saved_credentials'] = true;
                    Hm_Msgs::add("Server saved");
                    $this->session->record_unsaved('SMTP server saved');
                }
                else {
                    Hm_Msgs::add("ERRUnable to save this server, are the username and password correct?");
                }
            }
        }
        return $data;
    }
}

class Hm_Handler_smtp_forget extends Hm_Handler_Module {
    public function process($data) {
        $data['just_forgot_credentials'] = false;
        if (isset($this->request->post['smtp_forget'])) {
            list($success, $form) = $this->process_form(array('smtp_server_id'));
            if ($success) {
                Hm_SMTP_List::forget_credentials($form['smtp_server_id']);
                $data['just_forgot_credentials'] = true;
                Hm_Msgs::add('Server credentials forgotten');
                $this->session->record_unsaved('SMTP server credentials forgotten');
            }
            else {
                $data['old_form'] = $form;
            }
        }
        return $data;
    }
}

class Hm_Handler_smtp_delete extends Hm_Handler_Module {
    public function process($data) {
        if (isset($this->request->post['smtp_delete'])) {
            list($success, $form) = $this->process_form(array('smtp_server_id'));
            if ($success) {
                $res = Hm_SMTP_List::del($form['smtp_server_id']);
                if ($res) {
                    $data['deleted_server_id'] = $form['smtp_server_id'];
                    Hm_Msgs::add('Server deleted');
                    $this->session->record_unsaved('SMTP server deleted');
                }
            }
            else {
                $data['old_form'] = $form;
            }
        }
        return $data;
    }
}

class Hm_Handler_smtp_connect extends Hm_Handler_Module {
    public function process($data) {
        $smtp = false;
        if (isset($this->request->post['smtp_connect'])) {
            list($success, $form) = $this->process_form(array('smtp_user', 'smtp_pass', 'smtp_server_id'));
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
        return $data;
    }
}

class Hm_Handler_process_compose_form_submit extends Hm_Handler_Module {
    public function process($data) {
        if (array_key_exists('smtp_send', $this->request->post)) {
            list($success, $form) = $this->process_form(array('compose_to', 'compose_subject', 'smtp_server_id'));
            if ($success) {
                $to = $form['compose_to'];
                $subject = $form['compose_subject'];
                $body = '';
                $from = '';
                if (array_key_exists('compose_body', $this->request->post)) {
                    $body = $this->request->post['compose_body'];
                }
                $smtp_details = Hm_SMTP_List::dump($form['smtp_server_id']);
                if ($smtp_details) {
                    $from = $smtp_details['user'];
                    $smtp = Hm_SMTP_List::connect($form['smtp_server_id'], false);
                    if ($smtp && $smtp->state == 'authed') {
                        $mime = new Hm_MIME_Msg($to, $subject, $body, $from);
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
                                Hm_Msgs::add("Message Sent");
                            }
                        }
                    }
                    else {
                        Hm_Msgs::add("ERRFailed to authenticate to the SMTP server");
                    }
                }
            }
            else {
                Hm_Msgs::add('ERRRequired field missing');
            }
        }
        return $data;
    }
}

class Hm_Output_compose_form extends Hm_Output_Module {
    protected function output($input, $format) {
        return '<div class="compose_page"><div class="content_title">Compose</div>'.
            '<form class="compose_form" method="post" action="?page=compose">'.
            '<input type="hidden" name="hm_nonce" value="'.$this->html_safe(Hm_Nonce::generate()).'" />'.
            '<input required name="compose_to" class="compose_to" type="text" placeholder="To" />'.
            '<input required name="compose_subject" class="compose_subject" type="text" placeholder="Subject" />'.
            '<textarea required name="compose_body" class="compose_body"></textarea>'.
            smtp_server_dropdown($input, $this).
            '<input class="smtp_send" type="submit" value="'.$this->trans('Send').'" name="smtp_send" /></form></div>';
    }
}

class Hm_Output_add_smtp_server_dialog extends Hm_Output_Module {
    protected function output($input, $format) {
        if (array_key_exists('smtp_servers', $input)) {
            $count = count($input['smtp_servers']);
        }
        else {
            $count = 0;
        }
        $count = sprintf($this->trans('%d configured'), $count);
        return '<div class="smtp_server_setup"><div data-target=".smtp_section" class="server_section">'.
            '<img alt="" src="'.Hm_Image_Sources::$doc.'" width="16" height="16" />'.
            ' SMTP Servers <div class="server_count">'.$count.'</div></div><div class="smtp_section"><form class="add_server" method="POST">'.
            '<input type="hidden" name="hm_nonce" value="'.$this->html_safe(Hm_Nonce::generate()).'" />'.
            '<div class="subtitle">Add an SMTP Server</div>'.
            '<table><tr><td colspan="2"><input required type="text" name="new_smtp_name" class="txt_fld" value="" placeholder="Account name" /></td></tr>'.
            '<tr><td colspan="2"><input required type="text" name="new_smtp_address" class="txt_fld" placeholder="smtp server address" value=""/></td></tr>'.
            '<tr><td colspan="2"><input required type="text" name="new_smtp_port" class="port_fld" value="" placeholder="Port"></td></tr>'.
            '<tr><td><input type="checkbox" name="tls" value="1" checked="checked" /> Use TLS</td>'.
            '<td><input type="submit" value="Add" name="submit_smtp_server" /></td></tr>'.
            '</table></form>';
    }
}

class Hm_Output_display_configured_smtp_servers extends Hm_Output_Module {
    protected function output($input, $format) {
        $res = '';
        if (isset($input['smtp_servers'])) {
            foreach ($input['smtp_servers'] as $index => $vals) {

                $no_edit = false;

                if (isset($vals['user'])) {
                    $disabled = 'disabled="disabled"';
                    $user_pc = $vals['user'];
                    $pass_pc = '[saved]';
                }
                else {
                    $user_pc = '';
                    $pass_pc = 'Password';
                    $disabled = '';
                }
                if ($vals['name'] == 'Default-Auth-Server') {
                    $vals['name'] = 'Default';
                    $no_edit = true;
                }
                $res .= '<div class="configured_server">';
                $res .= sprintf('<div class="server_title">%s</div><div class="server_subtitle">%s/%d %s</div>',
                    $this->html_safe($vals['name']), $this->html_safe($vals['server']), $this->html_safe($vals['port']), $vals['tls'] ? 'TLS' : '' );
                $res .= 
                    '<form class="smtp_connect" method="POST">'.
                    '<input type="hidden" name="hm_nonce" value="'.$this->html_safe(Hm_Nonce::generate()).'" />'.
                    '<input type="hidden" name="smtp_server_id" value="'.$this->html_safe($index).'" /><span> '.
                    '<input '.$disabled.' class="credentials" placeholder="Username" type="text" name="smtp_user" value="'.$user_pc.'"></span>'.
                    '<span> <input '.$disabled.' class="credentials smtp_password" placeholder="'.$pass_pc.'" type="password" name="smtp_pass"></span>';
                if (!$no_edit) {
                    $res .= '<input type="submit" value="Test" class="test_smtp_connect" />';
                    if (!isset($vals['user']) || !$vals['user']) {
                        $res .= '<input type="submit" value="Delete" class="smtp_delete" />';
                        $res .= '<input type="submit" value="Save" class="save_smtp_connection" />';
                    }
                    else {
                        $res .= '<input type="submit" value="Delete" class="delete_smtp_connection" />';
                        $res .= '<input type="submit" value="Forget" class="forget_smtp_connection" />';
                    }
                    $res .= '<input type="hidden" value="ajax_smtp_debug" name="hm_ajax_hook" />';
                }
                $res .= '</form></div>';
            }
            $res .= '<br class="clear_float" /></div></div>';
        }
        return $res;
    }
}

class Hm_Output_compose_page_link extends Hm_Output_Module {
    protected function output($input, $format) {
        $res = '<li class="menu_compose"><a class="unread_link" href="?page=compose">'.
            '<img class="account_icon" src="'.$this->html_safe(Hm_Image_Sources::$doc).'" alt="" width="16" height="16" /> '.$this->trans('Compose').'</a></li>';

        if ($format == 'HTML5') {
            return $res;
        }
        $input['formatted_folder_list'] .= $res;
        return $input;
    }
}

function smtp_server_dropdown($input, $output_mod) {
    $res = '<select name="smtp_server_id" class="compose_server">';
    if (array_key_exists('smtp_servers', $input)) {
        foreach ($input['smtp_servers'] as $id => $vals) {
            $res .= '<option value="'.$output_mod->html_safe($id).'">'.$output_mod->html_safe(sprintf("%s - %s", $vals['name'], $vals['server'])).'</option>';
        }
    }
    $res .= '</select>';
    return $res;
}

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


?>
