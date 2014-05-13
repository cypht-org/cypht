<?php

require 'lib/hm-pop3.php';

class Hm_Handler_pop3_folder_page extends Hm_Handler_Module {
    public function process($data) {

        $sort = 'ARRIVAL';
        $rev = true;
        $filter = 'ALL';
        $offset = 0;
        $limit = 20;
        $headers = array('subject', 'from', 'date');
        $msgs = array();
        $list_page = 1;

        list($success, $form) = $this->process_form(array('pop3_server_id'));
        if ($success) {
            $pop3 = Hm_POP3_List::connect($form['pop3_server_id'], false);
            $details = Hm_POP3_List::dump($form['pop3_server_id']);
            $path = sprintf("pop3_%d", $form['pop3_server_id']);
            if ($pop3->state = 'authed') {
                $data['pop3_mailbox_page_path'] = $path;
                foreach (array_reverse(array_unique($pop3->mlist())) as $id => $size) {
                    $path = sprintf("pop3_%d", $form['pop3_server_id']);
                    $msg_headers = $pop3->msg_headers($id);
                    if (!empty($msg_headers)) {
                        $msg_headers['server_name'] = $details['name'];
                        $msg_headers['server_id'] = $form['pop3_server_id'];
                        $msgs[$id] = $msg_headers;
                    }
                }
                $data['pop3_mailbox_page'] = $msgs;
                $data['pop3_server_id'] = $form['pop3_server_id'];
            }
        }
        return $data;
    }
}

class Hm_Handler_pop3_save extends Hm_Handler_Module {
    public function process($data) {
        $data['just_saved_credentials'] = false;
        if (isset($this->request->post['pop3_save'])) {
            list($success, $form) = $this->process_form(array('pop3_user', 'pop3_pass', 'pop3_server_id'));
            if (!$success) {
                Hm_Msgs::add('ERRUsername and Password are required to save a connection');
            }
            else {
                $pop3 = Hm_POP3_List::connect($form['pop3_server_id'], false, $form['pop3_user'], $form['pop3_pass'], true);
                if ($pop3->state == 'authed') {
                    $data['just_saved_credentials'] = true;
                    Hm_Msgs::add("Server saved");
                }
                else {
                    Hm_Msgs::add("ERRUnable to save this server, are the username and password correct?");
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
                Hm_Msgs::add("ERRFailed to authenticate to the POP3 server");
            }
        }
        return $data;
    }
}

class Hm_Handler_load_pop3_cache extends Hm_Handler_Module {
    public function process($data) {
        $servers = Hm_POP3_List::dump();
        $cache = $this->session->get('pop3_cache', array()); 
        foreach ($servers as $index => $server) {
            if (isset($cache[$index])) {
            }
        }
        return $data;
    }
}

class Hm_Handler_save_pop3_cache extends Hm_Handler_Module {
    public function process($data) {
        return $data;
    }
}

class Hm_Handler_load_pop3_servers_from_config extends Hm_Handler_Module {
    public function process($data) {
        $servers = $this->user_config->get('pop3_servers', array());
        $added = false;
        foreach ($servers as $index => $server) {
            Hm_POP3_List::add( $server, $index );
            if ($server['name'] == 'Default-Auth-Server') {
                $added = true;
            }
        }
        if (!$added) {
            $auth_server = $this->session->get('pop3_auth_server_settings', array());
            if (!empty($auth_server)) {
                Hm_POP3_List::add(array( 
                    'name' => 'Default-Auth-Server',
                    'server' => $auth_server['server'],
                    'port' => $auth_server['port'],
                    'tls' => $auth_server['tls'],
                    'user' => $auth_server['username'],
                    'pass' => $auth_server['password']),
                count($servers));
                $this->session->del('pop3_auth_server_settings');
            }
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
                Hm_Msgs::add('ERRYou must supply a name, a server and a port');
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
                    Hm_Msgs::add(sprintf('ERRCound not add server: %s', $errstr));
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
            $data['folder_sources'][] = 'pop3_folders';
        }
        return $data;
    }
}

class Hm_Handler_load_pop3_folders extends Hm_Handler_Module {
    public function process($data) {
        $servers = Hm_POP3_List::dump();
        $folders = array();
        if (!empty($servers)) {
            foreach ($servers as $id => $server) {
                if ($server['name'] == 'Default-Auth-Server') {
                    $server['name'] = 'Default';
                }
                $folders[$id] = $server['name'];
            }
        }
        $data['pop3_folders'] = $folders;
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
                '<div class="subtitle">Add a POP3 Server</div>'.
                '<input type="hidden" name="hm_nonce" value="'.$this->build_nonce( 'add_pop3_server' ).'" />'.
                '<table>'.
                '<tr><td colspan="2"><input type="text" name="new_pop3_name" class="txt_fld" value="" placeholder="Account name" /></td></tr>'.
                '<tr><td colspan="2"><input type="text" name="new_pop3_address" class="txt_fld" placeholder="pop3 server address" value=""/></td></tr>'.
                '<tr><td colspan="2"><input type="text" name="new_pop3_port" class="port_fld" value="" placeholder="Port"></td></tr>'.
                '<tr><td><input type="checkbox" name="tls" value="1" checked="checked" /> Use TLS</td>'.
                '<td align="right"><input type="submit" value="Add" name="submit_pop3_server" /></td></tr>'.
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
                        if (stristr($input['session_type'], 'pop3')) {
                            $no_edit = true;
                        }
                    }
                    $res .= '<div class="configured_server">';
                    $res .= sprintf('<div class="server_title">%s</div><div class="server_subtitle">%s/%d %s</div>',
                        $this->html_safe($vals['name']), $this->html_safe($vals['server']), $this->html_safe($vals['port']), $vals['tls'] ? 'TLS' : '' );
                    $res .= 
                        '<form class="pop3_connect" method="POST">'.
                        '<input type="hidden" name="pop3_server_id" value="'.$this->html_safe($index).'" /><span> '.
                        '<input '.$disabled.' class="credentials" placeholder="Username" type="text" name="pop3_user" value="'.$user_pc.'"></span>'.
                        '<span> <input '.$disabled.' class="credentials pop3_password" placeholder="'.$pass_pc.'" type="password" name="pop3_pass"></span>';
                    if (!$no_edit) {
                        $res .= '<input type="submit" value="Test" class="test_pop3_connect" />';
                        if (!isset($vals['user']) || !$vals['user']) {
                            $res .= '<input type="submit" value="Delete" class="pop3_delete" />';
                            $res .= '<input type="submit" value="Save" class="save_pop3_connection" />';
                        }
                        else {
                            $res .= '<input type="submit" value="Delete" class="delete_pop3_connection" />';
                            $res .= '<input type="submit" value="Forget" class="forget_pop3_connection" />';
                        }
                        $res .= '<input type="hidden" value="ajax_pop3_debug" name="hm_ajax_hook" />';
                    }
                    $res .= '</form></div>';
                }
            }
        return $res;
        }
    }
}

class Hm_Output_display_pop3_summary extends Hm_Output_Module {
    protected function output($input, $format) {
        if ($format == 'HTML5') {
            $res = '';
            if (isset($input['pop3_servers']) && !empty($input['pop3_servers'])) {
                foreach ($input['pop3_servers'] as $index => $vals) {
                    if ($vals['name'] == 'Default-Auth-Server') {
                        $vals['name'] = 'Default';
                    }
                    $res .= '<tr><td>POP3</td><td>'.$vals['name'].'</td>'.
                        '<td>'.$vals['server'].'</td><td>'.$vals['port'].'</td>'.
                        '<td>'.$vals['tls'].'</td></tr>';
                }
            }
            return $res;
        }
    }
}

class Hm_Output_filter_pop3_folders extends Hm_Output_Module {
    protected function output($input, $format) {
        $res = '<ul class="folders">';
        if (isset($input['pop3_folders'])) {
            foreach ($input['pop3_folders'] as $id => $folder) {
                $res .= '<li><a href="?page=message_list&list_path=pop3_'.$this->html_safe($id).'">'.$this->html_safe($folder).'</a></li>';
            }
        }
        $res .= '</ul>';
        Hm_Page_Cache::add('pop3_folders', $res, true);
        return '';
    }
}
class Hm_Output_pop3_message_list extends Hm_Output_Module {
    protected function output($input, $format) {
        if (isset($input['list_path']) && preg_match("/^pop3_/", $input['list_path'])) {
            return pop3_message_list($input, $this);
        }
        else {
            // TODO: default
        }
    }
}
class Hm_Output_filter_pop3_message_list extends Hm_Output_Module {
    protected function output($input, $format) {
        $input['formatted_mailbox_page'] = array();
        if (isset($input['pop3_mailbox_page'])) {
            $res = format_pop3_message_list($input['pop3_mailbox_page'], $this);
            $input['formatted_mailbox_page'] = $res;
            Hm_Page_Cache::add('formatted_mailbox_page_'.$input['pop3_mailbox_page_path'], $res);
            unset($input['pop3_mailbox_page']);
        }
        return $input;
    }
}


function pop3_message_list($input, $output_module) {
    $page_cache = Hm_Page_Cache::get('formatted_mailbox_page_'.$input['list_path']);
    $rows = '';
    $links = '';
    if ($page_cache) {
        $rows = implode(array_map(function($v) { return $v[0]; }, $page_cache));
    }
    return '<div class="message_list"><div class="msg_text"></div><div class="content_title">'.$output_module->html_safe($input['mailbox_list_title']).'</div>'.
        '<a class="update_unread" href="#"  onclick="return select_pop3_folder(\''.$output_module->html_safe($input['list_path']).'\', true)">Update</a>'.
        '<table class="message_table" cellpadding="0" cellspacing="0"><colgroup><col class="source_col">'.
        '<col class="subject_col"><col class="from_col"><col class="date_col"></colgroup>'.
        '<thead><tr><th>Source</th><th>Subject</th><th>From</th><th>Date</th></tr></thead>'.
        '<tbody>'.$rows.'</tbody></table><div class="pop3_page_links">'.$links.'</div></div>';
}

function format_pop3_message_list($msg_list, $output_module) {
    $res = array();
    foreach($msg_list as $msg_id => $msg) {
        if ($msg['server_name'] == 'Default-Auth-Server') {
            $msg['server_name'] = 'Default';
        }
        $id = sprintf("pop3_%s_%s", $output_module->html_safe($msg['server_id']), $output_module->html_safe($msg_id));
        $subject = preg_replace("/(\[.+\])/U", '<span class="hl">$1</span>', $output_module->html_safe($msg['subject']));
        $from = preg_replace("/(\&lt;.+\&gt;)/U", '<span class="dl">$1</span>', $output_module->html_safe($msg['from']));
        $from = str_replace("&quot;", '', $from);
        $date = date('Y-m-d G:i:s', strtotime($output_module->html_safe($msg['date'])));
        $res[$id] = array('<tr style="display: none;" class="'.$id.'"><td class="source">'.$output_module->html_safe($msg['server_name']).'</td>'.
            '<td onclick="return msg_preview('.$output_module->html_safe($msg_id).', '.
            $output_module->html_safe($msg['server_id']).')" class="subject">'.$subject.
            '</td><td class="from">'.$from.'</div></td>'.
            '<td class="msg_date">'.$date.'</td></tr>', $id);
    }
    return $res;
}

?>
