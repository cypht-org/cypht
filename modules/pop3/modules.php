<?php

class Hm_Handler_pop3_connect extends Hm_Handler_Module {
    public function process($data) {
        $pop3 = false;
        error_log(print_r($data, true));
        if (isset($this->request->post['pop3_connect'])) {
            list($success, $form) = $this->process_form(array('pop3_user', 'pop3_pass', 'pop3_server_id'));
            if ($success) {
                $pop3 = Hm_POP3_List::connect($form['pope_server_id'], false, $form['pop3_user'], $form['pop3_pass']);
            }
            elseif (isset($form['pop3_server_id'])) {
                $pop3 = Hm_POP3_List::connect($form['pop3_server_id'], $cache);
            }
            if ($pop3) {
                Hm_Msgs::add("Successfully authenticated to the POP3 server");
                $data['pop3_debug'] = $pop3->puke();
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
                '<tr><td colspan="2"><input type="text" name="new_pop3_name" value="" placeholder="Account name" /></td></tr>'.
                '<tr><td colspan="2"><input type="text" name="new_pop3_address" placeholder="pop3 server address" value=""/></td></tr>'.
                '<tr><td colspan="2"><input type="text" name="new_pop3_port" value="" placeholder="Port"></td></tr>'.
                '<tr><td>Use TLS</td><td><input type="checkbox" name="tls" value="1" checked="checked" /></td></tr>'.
                '<tr><td colspan="2"><input type="submit" value="Add POP3 Server" name="submit_pop3_server" /></td></tr>'.
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
                        $display = 'none';
                    }
                    else {
                        $disabled = '';
                        $display = 'inline';
                    }
                    $res .= '<div class="configured_server">';
                    $res .= sprintf("<div>Type: POP3</div><div>Name: %s</div><div>Server: %s</div>".
                        "<div>Port: %d</div><div>TLS: %s</div>", $this->html_safe($vals['name']), $this->html_safe($vals['server']),
                        $this->html_safe($vals['port']), $vals['tls'] ? 'true' : 'false' );
                    $res .= 
                        ' <form class="pop3_connect" method="POST">'.
                        '<input type="hidden" name="pop3_server_id" value="'.$this->html_safe($index).'" />'.
                        '<span style="display: '.$display.'"> '.
                        '<input '.$disabled.' class="credentials" placeholder="Username" type="text" name="pop3_user" value=""></span>'.
                        '<span style="display: '.$display.'"> '.
                        '<input '.$disabled.' class="credentials" placeholder="Password" type="password" name="pop3_pass"></span>'.
                        '<input type="submit" value="Test Connection" class="test_pop3_connect" />';
                    if (!isset($vals['user']) || !$vals['user']) {
                        $res .= '<input type="submit" value="Delete" class="pop3_delete" />';
                        $res .= '<input type="submit" value="Save" class="save_connection" />';
                    }
                    else {
                        $res .= '<input type="submit" value="Delete" class="pop3_delete" />';
                        $res .= '<input type="submit" value="Forget" class="forget_connection" />';
                    }
                    $res .= '<input type="hidden" value="ajax_pop3_debug" name="hm_ajax_hook" /></form></div>';
                }
            }
        return $res;
        }
    }
}

?>
