<?php

class Hm_Handler_pop3_save extends Hm_Handler_Module {
    public function process($data) {
        $data['just_saved_credentials'] = false;
        if (isset($this->request->post['pop3_save'])) {
            list($success, $form) = $this->process_form(array('pop3_user', 'pop3_pass', 'pop3_server_id'));
            if (!$success) {
                Hm_Msgs::add('Username and Password are required to save a connection');
            }
            else {
                $pop3 = Hm_POP3_List::connect($form['pop3_server_id'], $cache, $form['pop3_user'], $form['pop3_pass'], true);
                if ($pop3->state == 'authed') {
                    $data['just_saved_credentials'] = true;
                    Hm_Msgs::add("Server saved");
                }
                else {
                    Hm_Msgs::add("Unable to save this server, are the username and password correct?");
                }
            }
        }
        return $data;
    }
}
class Hm_Handler_pop3_forget extends Hm_Handler_Module {
    public function process($data) {
        $data['just_forgot_credentials'] = false;
        if (isset($this->request->post['pop3_forget'])) {
            list($success, $form) = $this->process_form(array('pop3_server_id'));
            if ($success) {
                Hm_POP3_List::forget_credentials($form['pop3_server_id']);
                $data['just_forgot_credentials'] = true;
                Hm_Msgs::add('Server credentials forgotten');
            }
            else {
                $data['old_form'] = $form;
            }
        }
        return $data;
    }
}
class Hm_Handler_pop3_delete extends Hm_Handler_Module {
    public function process($data) {
        if (isset($this->request->post['pop3_delete'])) {
            list($success, $form) = $this->process_form(array('pop3_server_id'));
            if ($success) {
                $res = Hm_POP3_List::del($form['pop3_server_id']);
                if ($res) {
                    $data['deleted_server_id'] = $form['pop3_server_id'];
                    Hm_Msgs::add('Server deleted');
                }
            }
            else {
                $data['old_form'] = $form;
            }
        }
        return $data;
    }
}
class Hm_Handler_pop3_connect extends Hm_Handler_Module {
    public function process($data) {
        $pop3 = false;
        if (isset($this->request->post['pop3_connect'])) {
            list($success, $form) = $this->process_form(array('pop3_user', 'pop3_pass', 'pop3_server_id'));
            if ($success) {
                $pop3 = Hm_POP3_List::connect($form['pop3_server_id'], false, $form['pop3_user'], $form['pop3_pass']);
            }
            elseif (isset($form['pop3_server_id'])) {
                $pop3 = Hm_POP3_List::connect($form['pop3_server_id'], false);
            }
            if ($pop3 && $pop3->state == 'authed') {
                Hm_Msgs::add("Successfully authenticated to the POP3 server");
            }
            else {
                Hm_Msgs::add("Failed to authenticate to the POP3 server");
            }
        }
        return $data;
    }
}
class Hm_Handler_load_pop3_servers_from_config extends Hm_Handler_Module {
    public function process($data) {
        $servers = $this->user_config->get('pop3_servers', array());
        foreach ($servers as $index => $server) {
            Hm_POP3_List::add( $server, $index );
        }
        return $data;
    }
}

class Hm_Handler_process_add_pop3_server extends Hm_Handler_Module {
    public function process($data) {
        if (isset($this->request->post['submit_pop3_server'])) {
            list($success, $form) = $this->process_form(array('new_pop3_name', 'new_pop3_address', 'new_pop3_port'));
            if (!$success) {
                $data['old_form'] = $form;
                Hm_Msgs::add('You must supply a name, a server and a port');
            }
            else {
                $tls = false;
                if (isset($this->request->post['tls'])) {
                    $tls = true;
                }
                if ($con = fsockopen($form['new_pop3_address'], $form['new_pop3_port'], $errno, $errstr, 2)) {
                    Hm_POP3_List::add( array(
                        'name' => $form['new_pop3_name'],
                        'server' => $form['new_pop3_address'],
                        'port' => $form['new_pop3_port'],
                        'tls' => $tls));
                    Hm_Msgs::add('Added server!');
                }
                else {
                    Hm_Msgs::add(sprintf('Cound not add server: %s', $errstr));
                }
            }
        }
        return $data;
    }
}

class Hm_Handler_add_pop3_servers_to_page_data extends Hm_Handler_Module {
    public function process($data) {
        $data['pop3_servers'] = array();
        $servers = Hm_POP3_List::dump();
        if (!empty($servers)) {
            $data['pop3_servers'] = $servers;
        }
        return $data;
    }
}

class Hm_Handler_save_pop3_servers extends Hm_Handler_Module {
    public function process($data) {
        $servers = Hm_POP3_List::dump();
        $this->user_config->set('pop3_servers', $servers);
        Hm_POP3_List::clean_up();
        return $data;
    }
}

class Hm_Output_add_pop3_server_dialog extends Hm_Output_Module {
    protected function output($input, $format) {
        if ($format == 'HTML5') {
            return '<form class="add_server" method="POST">'.
                '<table>'.
                '<tr><td><input type="text" name="new_pop3_name" class="txt_fld" value="" placeholder="Account name" /></td></tr>'.
                '<tr><td><input type="text" name="new_pop3_address" class="txt_fld" placeholder="pop3 server address" value=""/></td></tr>'.
                '<tr><td><input type="text" name="new_pop3_port" class="port_fld" value="" placeholder="Port"></td></tr>'.
                '<tr><td><input type="checkbox" name="tls" value="1" checked="checked" /> Use TLS</td></tr>'.
                '<tr><td><input type="submit" value="Add POP3 Server" name="submit_pop3_server" /></td></tr>'.
                '</table></form>';
        }
    }
}
class Hm_Output_display_configured_pop3_servers extends Hm_Output_Module {
    protected function output($input, $format) {
        if ($format == 'HTML5') {
            $res = '';
            if (isset($input['pop3_servers'])) {
                foreach ($input['pop3_servers'] as $index => $vals) {

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
                    $res .= '<div class="configured_server">';
                    $res .= sprintf('<div class="server_title">POP3 - %s</div><div class="server_subtitle">%s/%d %s</div>',
                        $this->html_safe($vals['name']), $this->html_safe($vals['server']), $this->html_safe($vals['port']), $vals['tls'] ? 'TLS' : '' );
                    $res .= 
                        '<form class="pop3_connect" method="POST">'.
                        '<input type="hidden" name="pop3_server_id" value="'.$this->html_safe($index).'" />'.
                        '<span> '.
                        '<input '.$disabled.' class="credentials" placeholder="Username" type="text" name="pop3_user" value="'.$user_pc.'"></span>'.
                        '<span style="display: '.$display.'"> '.
                        '<input '.$disabled.' class="credentials pop3_password" placeholder="'.$pass_pc.'" type="password" name="pop3_pass"></span>'.
                        '<input type="submit" value="Test" class="test_pop3_connect" />';
                    if (!isset($vals['user']) || !$vals['user']) {
                        $res .= '<input type="submit" value="Delete" class="pop3_delete" />';
                        $res .= '<input type="submit" value="Save" class="save_pop3_connection" />';
                    }
                    else {
                        $res .= '<input type="submit" value="Delete" class="delete_pop3_connection" />';
                        $res .= '<input type="submit" value="Forget" class="forget_pop3_connection" />';
                    }
                    $res .= '<input type="hidden" value="ajax_pop3_debug" name="hm_ajax_hook" /></form></div>';
                }
            }
        return $res;
        }
    }
}

class Hm_Output_display_pop3_summary extends Hm_Output_Module {
    protected function output($input, $format, $lang_str=false) {
        if ($format == 'HTML5') {
            $res = '';
            if (isset($input['pop3_servers']) && !empty($input['pop3_servers'])) {
                $res .= '<input type="hidden" id="pop3_summary_ids" value="'.
                    $this->html_safe(implode(',', array_keys($input['pop3_servers']))).'" />';

                $res .= '<div class="pop3_summary_data">';
                $res .= '<table><thead><tr><th>POP3 Server</th><th>Address</th><th>Port</th>'.
                    '<th>TLS</th></tr></thead><tbody>';
                foreach ($input['pop3_servers'] as $index => $vals) {
                    $res .= '<tr class="pop3_summary_'.$index.'"><td>'.$vals['name'].'</td>'.
                        '<td>'.$vals['server'].'</td><td>'.$vals['port'].'</td>'.
                        '<td>'.$vals['tls'].'</td></tr>';
                }
                $res .= '</table></div>';
            }
            else {
                $res .= '<table class="empty_table"><tr><td>No POP3 servers found<br /><a href="'.$input['router_url_path'].'?page=servers">Add some</a></td></tr></table>';
            }
            return $res;
        }
    }
}

?>
