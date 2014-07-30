<?php

if (!defined('DEBUG_MODE')) { die(); }

require 'lib/hm-pop3.php';

class Hm_Handler_pop3_status extends Hm_Handler_Module {
    public function process($data) {
        list($success, $form) = $this->process_form(array('pop3_server_ids'));
        if ($success) {
            $ids = explode(',', $form['pop3_server_ids']);
            foreach ($ids as $id) {
                $start_time = microtime(true);
                $pop3 = Hm_POP3_List::connect($id, false);
                if ($pop3->state = 'authed') {
                    $data['pop3_connect_time'] = microtime(true) - $start_time;
                    $data['pop3_connect_status'] = 'Authenticated';
                    $data['pop3_status_server_id'] = $id;
                }
            }
        }
        return $data;
    }
}

class Hm_Handler_pop3_message_action extends Hm_Handler_Module {
    public function process($data) {

        list($success, $form) = $this->process_form(array('action_type', 'message_ids'));
        if ($success) {
            $id_list = explode(',', $form['message_ids']);
            foreach ($id_list as $msg_id) {
                if (preg_match("/^pop3_(\d)+_(\d)+$/", $msg_id)) {
                    switch($form['action_type']) {
                        case 'unread':
                            Hm_POP3_Seen_Cache::remove($msg_id);
                            break;
                        case 'read':
                            Hm_POP3_Seen_Cache::add($msg_id);
                            break;
                    }
                }
            }
        }
        return $data;
    }
}

class Hm_Handler_pop3_folder_page extends Hm_Handler_Module {
    public function process($data) {

        $msgs = array();
        list($success, $form) = $this->process_form(array('pop3_server_id'));
        if ($success) {
            $limit = 0;
            if (isset($this->request->post['limit'])) {
                $limit = (int) $this->request->post['limit'];
            }
            if (!$limit) {
                $limit = 20;
            }
            $login_time = $this->session->get('login_time', false);
            if ($login_time) {
                $data['login_time'] = $login_time;
            }
            if (isset($this->request->post['unread_since'])) {
                $unread_only = true;
                $date = process_since_argument($this->request->post['unread_since'], $this->user_config);
                $cutoff_timestamp = strtotime($date);
                if ($login_time && $login_time > $cutoff_timestamp) {
                    $cutoff_timestamp = $login_time;
                }
            }
            else {
                $unread_only = false;
                $cutoff_timestamp = 0;
            }
            $pop3 = Hm_POP3_List::connect($form['pop3_server_id'], false);
            $details = Hm_POP3_List::dump($form['pop3_server_id']);
            $path = sprintf("pop3_%d", $form['pop3_server_id']);
            if ($pop3->state = 'authed') {
                $data['pop3_mailbox_page_path'] = $path;
                $list = array_slice(array_reverse(array_unique(array_keys($pop3->mlist()))), 0, $limit);
                foreach ($list as $id) {
                    $path = sprintf("pop3_%d", $form['pop3_server_id']);
                    $msg_headers = $pop3->msg_headers($id);
                    if (!empty($msg_headers)) {
                        if (isset($msg_headers['date'])) {
                            if (strtotime($msg_headers['date']) < $cutoff_timestamp) {
                                continue;
                            }
                        }
                        if ($unread_only && Hm_POP3_Seen_Cache::is_present(sprintf('pop3_%d_%d', $form['pop3_server_id'], $id))) {
                            continue;
                        }
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

class Hm_Handler_pop3_message_content extends Hm_Handler_Module {
    public function process($data) {

        list($success, $form) = $this->process_form(array('pop3_uid', 'pop3_list_path'));
        if ($success) {
            $id = (int) substr($form['pop3_list_path'], 4);
            $pop3 = Hm_POP3_List::connect($id, false);
            $details = Hm_POP3_List::dump($id);
            if ($pop3->state = 'authed') {
                $msg_lines = $pop3->retr_full($form['pop3_uid']);
                $header_list = array();
                $body = array();
                $headers = true;
                $last_header = false;
                foreach ($msg_lines as $line) {
                    if ($headers) {
                        if (substr($line, 0, 1) == "\t") {
                            $header_list[$last_header] .= ' '.trim($line);
                        }
                        elseif (strstr($line, ':')) {
                            $parts = explode(':', $line, 2);
                            if (count($parts) == 2) {
                                $header_list[$parts[0]] = trim($parts[1]);
                                $last_header = $parts[0];
                            }
                        }
                    }
                    else {
                        $body[] = $line;
                    }
                    if (!trim($line)) {
                        $headers = false;
                    }
                }
                $data['pop3_message_headers'] = $header_list;
                $data['pop3_message_body'] = $body;
                Hm_POP3_Seen_Cache::add(sprintf("pop3_%s_%s", $id, $form['pop3_uid']));
                $data['pop3_mailbox_page_path'] = $form['pop3_list_path'];
                $data['pop3_server_id'] = $id;
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
        Hm_POP3_Seen_Cache::load($this->session->get('pop3_read_uids', array()));
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
            $data['folder_sources'][] = 'email_folders';
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
        $this->session->set('pop3_read_uids', Hm_POP3_Seen_Cache::dump());
        Hm_POP3_List::clean_up();
        return $data;
    }
}

class Hm_Output_add_pop3_server_dialog extends Hm_Output_Module {
    protected function output($input, $format) {
        return '<div class="pop3_server_setup"><div class="content_title">POP3 Servers</div><form class="add_server" method="POST">'.
            '<div class="subtitle">Add a POP3 Server</div><input type="hidden" name="hm_nonce" value="'.$this->build_nonce( 'add_pop3_server' ).'" />'.
            '<table><tr><td colspan="2"><input type="text" name="new_pop3_name" class="txt_fld" value="" placeholder="Account name" /></td></tr>'.
            '<tr><td colspan="2"><input type="text" name="new_pop3_address" class="txt_fld" placeholder="pop3 server address" value=""/></td></tr>'.
            '<tr><td colspan="2"><input type="text" name="new_pop3_port" class="port_fld" value="" placeholder="Port"></td></tr>'.
            '<tr><td><input type="checkbox" name="tls" value="1" checked="checked" /> Use TLS</td>'.
            '<td align="right"><input type="submit" value="Add" name="submit_pop3_server" /></td></tr>'.
            '</table></form>';
    }
}

class Hm_Output_display_configured_pop3_servers extends Hm_Output_Module {
    protected function output($input, $format) {
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
            $res .= '<br class="clear_float" /></div>';
        }
        return $res;
    }
}

class Hm_Output_display_pop3_summary extends Hm_Output_Module {
    protected function output($input, $format) {
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

class Hm_Output_filter_pop3_folders extends Hm_Output_Module {
    protected function output($input, $format) {
        $res = '';
        if (isset($input['pop3_folders'])) {
            foreach ($input['pop3_folders'] as $id => $folder) {
                $res .= '<li class="pop3_'.$this->html_safe($id).'">'.
                    '<a href="?page=message_list&list_path=pop3_'.$this->html_safe($id).'">'.
                    '<img class="account_icon" alt="Toggle folder" src="'.Hm_Image_Sources::$folder.'" width="16" height="16" /> '.
                    $this->html_safe($folder).'</a></li>';
            }
        }
        Hm_Page_Cache::concat('email_folders', $res);
        return '';
    }
}

class Hm_Output_filter_pop3_message_content extends Hm_Output_Module {
    protected function output($input, $format) {
        if (isset($input['pop3_message_headers'])) {
            $txt = '';
            $from = '';
            $small_headers = array('subject', 'date', 'from');
            $headers = $input['pop3_message_headers'];
            $txt .= '<table class="msg_headers" cellspacing="0" cellpadding="0">'.
                '<col class="header_name_col"><col class="header_val_col"></colgroup>';
            foreach ($small_headers as $fld) {
                foreach ($headers as $name => $value) {
                    if ($fld == strtolower($name)) {
                        if ($fld == 'from') {
                            $from = $value;
                        }
                        if ($fld == 'subject') {
                            $txt .= '<tr class="header_'.$fld.'"><th colspan="2">';
                            if (isset($headers['Flags']) && stristr($headers['Flags'], 'flagged')) {
                                $txt .= ' <img class="account_icon" src="'.Hm_Image_Sources::$folder.'" width="16" height="16" /> ';
                            }
                            $txt .= $this->html_safe($value).'</th></tr>';
                        }
                        else {
                            $txt .= '<tr class="header_'.$fld.'"><th>'.$this->html_safe($name).'</th><td>'.$this->html_safe($value).'</td></tr>';
                        }
                        break;
                    }
                }
            }
            foreach ($headers as $name => $value) {
                if (!in_array(strtolower($name), $small_headers)) {
                    $txt .= '<tr style="display: none;" class="long_header"><th>'.$this->html_safe($name).'</th><td>'.$this->html_safe($value).'</td></tr>';
                }
            }
            $txt .= '<tr><th colspan="2" class="header_links">'.
                '<a href="#" class="header_toggle" onclick="return toggle_long_headers();">all</a>'.
                '<a class="header_toggle" style="display: none;" href="#" onclick="return toggle_long_headers();">small</a>'.
                ' | <a href="?page=compose">reply</a>'.
                ' | <a href="?page=compose">forward</a>'.
                ' | <a href="?page=compose">attach</a>'.
                ' | <a onclick="return get_message_content(0);" href="#">raw</a>'.
                ' | <a href="#">flag</a>'.
                '</th></tr></table>';

            $input['msg_headers'] = $txt;
            $input['msg_gravatar'] = build_msg_gravatar($from);
        }
        $txt = '<div class="msg_text_inner">';
        if (isset($input['pop3_message_body'])) {
            $txt .= format_msg_text(implode('', $input['pop3_message_body']), $this);
        }
        $txt .= '</div>';
        $input['msg_text'] = $txt;
        return $input;
    }
}

class Hm_Output_filter_pop3_message_list extends Hm_Output_Module {
    protected function output($input, $format) {
        $input['formatted_mailbox_page'] = array();
        if (isset($input['pop3_mailbox_page'])) {
            $style = isset($input['news_list_style']) ? 'news' : 'email';
            if (isset($input['login_time'])) {
                $login_time = $input['login_time'];
            }
            else {
                $login_time = false;
            }
            $res = format_pop3_message_list($input['pop3_mailbox_page'], $this, $style, $login_time, $input['list_path']);
            $input['formatted_mailbox_page'] = $res;
            Hm_Page_Cache::add('formatted_mailbox_page_'.$input['pop3_mailbox_page_path'], $res);
            unset($input['pop3_mailbox_page']);
        }
        return $input;
    }
}

class Hm_Output_pop3_server_ids extends Hm_Output_Module {
    protected function output($input, $format) {
        if (isset($input['pop3_servers'])) {
            return '<input type="hidden" class="pop3_server_ids" value="'.$this->html_safe(implode(',', array_keys($input['pop3_servers']))).'" />';
        }
    }
}

class Hm_Output_display_pop3_status extends Hm_Output_Module {
    protected function output($input, $format) {
        $res = '';
        if (isset($input['pop3_servers']) && !empty($input['pop3_servers'])) {
            foreach ($input['pop3_servers'] as $index => $vals) {
                if ($vals['name'] == 'Default-Auth-Server') {
                    $vals['name'] = 'Default';
                }
                $res .= '<tr><td>POP3</td><td>'.$vals['name'].'</td><td class="pop3_status_'.$index.'"></td>'.
                    '<td class="pop3_detail_'.$index.'"></td></tr>';
            }
        }
        return $res;
    }
}

class Hm_Output_filter_pop3_status_data extends Hm_Output_Module {
    protected function output($input, $format) {
        if (isset($input['pop3_connect_status']) && $input['pop3_connect_status'] == 'Authenticated') {
            $input['pop3_status_display'] = '<span class="online">'.
                $this->html_safe(ucwords($input['pop3_connect_status'])).'</span> in '.round($input['pop3_connect_time'],3);
            $input['pop3_detail_display'] = '';
        }
        else {
            $input['pop3_status_display'] = '<span class="down">Down</span>';
            $input['pop3_detail_display'] = '';
        }
        return $input;
    }
}


function format_pop3_message_list($msg_list, $output_module, $style, $login_time, $list_parent) {
    $res = array();
    foreach($msg_list as $msg_id => $msg) {
        if ($msg['server_name'] == 'Default-Auth-Server') {
            $msg['server_name'] = 'Default';
        }
        $id = sprintf("pop3_%s_%s", $msg['server_id'], $msg_id);
        $subject = display_value('subject', $msg);;
        $from = display_value('from', $msg);
        $date = display_value('date', $msg);
        $timestamp = display_value('date', $msg, 'time');
        $url = '?page=message&uid='.$msg_id.'&list_path='.sprintf('pop3_%d', $msg['server_id']).'&list_parent='.$list_parent;
        if (Hm_POP3_Seen_Cache::is_present($id)) {
            $flags = array();
        }
        elseif (isset($msg['date']) && $login_time && strtotime($msg['date']) <= $login_time) {
            $flags = array();
        }
        else {
            $flags = array('unseen');
        }
        $res[$id] = message_list_row($subject, $date, $timestamp, $from, $msg['server_name'], $id, $flags, $style, $url, $output_module);
    }
    return $res;
}

?>
