<?php

if (!defined('DEBUG_MODE')) { die(); }

require 'modules/smtp/hm-smtp.php';

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
                    Hm_Msgs::add('Added server!');
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

class Hm_Output_compose_form extends Hm_Output_Module {
    protected function output($input, $format) {
        return '<div class="compose_page"><div class="content_title">Compose</div>'.
            '<form class="compose_form" method="post" action="?page=compose">'.
            '<input class="compose_to" type="text" placeholder="To" />'.
            '<input class="compose_subject" type="text" placeholder="Subject" />'.
            '<textarea class="compose_body"></textarea>'.
            '<input class="smtp_send" type="submit" value="'.$this->trans('Send').'" name="smtp_send" /></form></div>';
    }
}

class Hm_Output_add_smtp_server_dialog extends Hm_Output_Module {
    protected function output($input, $format) {
        return '<div class="smtp_server_setup"><div onclick="return toggle_server_section(\'.smtp_section\')" class="content_title"><img alt="" class="section_caret" src="'.Hm_Image_Sources::$chevron.'" width="8" height="8" /> SMTP Servers</div><div class="smtp_section"><form class="add_server" method="POST">'.
            '<div class="subtitle">Add an SMTP Server</div><input type="hidden" name="hm_nonce" value="'.$this->build_nonce( 'add_smtp_server' ).'" />'.
            '<table><tr><td colspan="2"><input type="text" name="new_smtp_name" class="txt_fld" value="" placeholder="Account name" /></td></tr>'.
            '<tr><td colspan="2"><input type="text" name="new_smtp_address" class="txt_fld" placeholder="smtp server address" value=""/></td></tr>'.
            '<tr><td colspan="2"><input type="text" name="new_smtp_port" class="port_fld" value="" placeholder="Port"></td></tr>'.
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


?>
