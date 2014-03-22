<?php

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
            if (isset($input['pop3_servers'])) {
            }
        }
    }
}

?>
