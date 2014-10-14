<?php

if (!defined('DEBUG_MODE')) { die(); }

require 'modules/pop3/hm-pop3.php';

class Hm_Handler_pop3_message_list_type extends Hm_Handler_Module {
    public function process($data) {
        if (array_key_exists('list_path', $this->request->get)) {
            $path = $this->request->get['list_path'];
            if (preg_match("/^pop3_\d+$/", $path)) {
                $data['list_path'] = $path;
                $parts = explode('_', $path, 2);
                $details = Hm_POP3_List::dump(intval($parts[1]));
                if (!empty($details)) {
                    if ($details['name'] == 'Default-Auth-Server') {
                        $details['name'] = 'Default';
                    }
                    $data['mailbox_list_title'] = array('POP3', $details['name'], 'INBOX');
                    $data['message_list_since'] = $this->user_config->get('pop3_since', DEFAULT_SINCE);
                    $data['per_source_limit'] = $this->user_config->get('pop3_limit', DEFAULT_SINCE);
                }
            }
        }
        return $data;
    }
}

class Hm_Handler_process_pop3_limit_setting extends Hm_Handler_Module {
    public function process($data) {
        list($success, $form) = $this->process_form(array('save_settings', 'pop3_limit'));
        if ($success) {
            if ($form['pop3_limit'] > MAX_PER_SOURCE || $form['pop3_limit'] < 0) {
                $limit = DEFAULT_PER_SOURCE;
            }
            else {
                $limit = $form['pop3_limit'];
            }
            $data['new_user_settings']['pop3_limit'] = $limit;
        }
        else {
            $data['user_settings']['pop3_limit'] = $this->user_config->get('pop3_limit', DEFAULT_PER_SOURCE);
        }
        return $data;
    }
}

class Hm_Handler_process_pop3_since_setting extends Hm_Handler_Module {
    public function process($data) {
        list($success, $form) = $this->process_form(array('save_settings', 'pop3_since'));
        if ($success) {
            $data['new_user_settings']['pop3_since'] = process_since_argument($form['pop3_since'], true);
        }
        else {
            $data['user_settings']['pop3_since'] = $this->user_config->get('pop3_since', false);
        }
        return $data;
    }
}

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
            $unread_only = false;
            $login_time = $this->session->get('login_time', false);
            if ($login_time) {
                $data['login_time'] = $login_time;
            }
            $terms = false;
            if (array_key_exists('pop3_search', $this->request->post)) {
                $limit = $this->user_config->get('pop3_limit', DEFAULT_PER_SOURCE);
                $terms = $this->session->get('search_terms', false);
                $since = $this->session->get('search_since', DEFAULT_SINCE);
                $fld = $this->session->get('search_fld', 'TEXT');
                $date = process_since_argument($since);
                $cutoff_timestamp = strtotime($date);
            }
            elseif (array_key_exists('list_path', $data) && $data['list_path'] == 'unread') {
                $limit = $this->user_config->get('unread_per_source_setting', DEFAULT_PER_SOURCE);
                $date = process_since_argument($this->user_config->get('unread_since_setting', DEFAULT_SINCE));
                $unread_only = true;
                $cutoff_timestamp = strtotime($date);
                if ($login_time && $login_time > $cutoff_timestamp) {
                    $cutoff_timestamp = $login_time;
                }
            }
            elseif (array_key_exists('list_path', $data) && $data['list_path'] == 'combined_inbox') {
                $limit = $this->user_config->get('all_per_source_setting', DEFAULT_PER_SOURCE);
                $date = process_since_argument($this->user_config->get('all_since_setting', DEFAULT_SINCE));
                $cutoff_timestamp = strtotime($date);
            }
            else {
                $limit = $this->user_config->get('pop3_limit', DEFAULT_PER_SOURCE);
                $date = process_since_argument($this->user_config->get('pop3_since', DEFAULT_SINCE));
                $cutoff_timestamp = strtotime($date);
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
                        if ($terms) {
                            $body = implode('', $pop3->retr_full($id));
                            if (!search_pop3_msg($body, $msg_headers, $terms, $fld)) {
                                continue;
                            }
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

function search_pop3_msg($body, $headers, $terms, $fld) {
    if ($fld == 'TEXT') {
        if (stristr($body, $terms)) {
            return true;
        }
    }
    if ($fld == 'SUBJECT') {
        if (array_key_exists('subject', $headers) && stristr($headers['subject'], $terms)) {
            return true;
        }
    }
    if ($fld == 'FROM') {
        if (array_key_exists('from', $headers) && stristr($headers['from'], $terms)) {
            return true;
        }
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
                    $this->session->record_unsaved('POP3 server saved');
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
                $this->session->record_unsaved('POP3 server credentials forgotten');
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
                    $this->session->record_unsaved('POP3 server deleted');
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
                    $this->session->record_unsaved('POP3 server added');
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
            if (array_key_exists('source_total', $data)) {
                $data['source_total'] += count($servers);
            }
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
        $servers = Hm_POP3_List::dump(false, true);
        $this->user_config->set('pop3_servers', $servers);
        $this->session->set('pop3_read_uids', Hm_POP3_Seen_Cache::dump());
        Hm_POP3_List::clean_up();
        return $data;
    }
}

class Hm_Output_add_pop3_server_dialog extends Hm_Output_Module {
    protected function output($input, $format) {
        if (array_key_exists('pop3_servers', $input)) {
            $count = count($input['pop3_servers']);
        }
        else {
            $count = 0;
        }
        $count = sprintf($this->trans('%d configured'), $count);
        return '<div class="pop3_server_setup"><div onclick="return toggle_page_section(\'.pop3_section\');" class="server_section">'.
            '<img alt="" src="'.Hm_Image_Sources::$env_closed.'" width="16" height="16" />'.
            ' POP3 Servers <div class="server_count">'.$count.'</div></div><div class="pop3_section"><form class="add_server" method="POST">'.
            '<input type="hidden" name="hm_nonce" value="'.$this->html_safe(Hm_Nonce::generate()).'" />'.
            '<div class="subtitle">Add a POP3 Server</div>'.
            '<table><tr><td colspan="2"><input required type="text" name="new_pop3_name" class="txt_fld" value="" placeholder="Account name" /></td></tr>'.
            '<tr><td colspan="2"><input required type="text" name="new_pop3_address" class="txt_fld" placeholder="pop3 server address" value=""/></td></tr>'.
            '<tr><td colspan="2"><input required type="text" name="new_pop3_port" class="port_fld" value="" placeholder="Port"></td></tr>'.
            '<tr><td><input type="checkbox" name="tls" value="1" checked="checked" /> Use TLS</td>'.
            '<td><input type="submit" value="Add" name="submit_pop3_server" /></td></tr>'.
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
                    $no_edit = true;
                }
                $res .= '<div class="configured_server">';
                $res .= sprintf('<div class="server_title">%s</div><div class="server_subtitle">%s/%d %s</div>',
                    $this->html_safe($vals['name']), $this->html_safe($vals['server']), $this->html_safe($vals['port']), $vals['tls'] ? 'TLS' : '' );
                $res .= 
                    '<form class="pop3_connect" method="POST">'.
                    '<input type="hidden" name="hm_nonce" value="'.$this->html_safe(Hm_Nonce::generate()).'" />'.
                    '<input type="hidden" name="pop3_server_id" value="'.$this->html_safe($index).'" /><span> '.
                    '<input '.$disabled.' class="credentials" placeholder="Username" type="text" name="pop3_user" value="'.$user_pc.'"></span>'.
                    '<span> <input '.$disabled.' class="credentials pop3_password" placeholder="'.$pass_pc.'" type="password" name="pop3_pass"></span>';
                if (!$no_edit) {
                    $res .= '<input type="submit" value="Test" class="test_pop3_connect" />';
                    if (!isset($vals['user']) || !$vals['user']) {
                        $res .= '<input type="submit" value="Delete" class="delete_pop3_connection" />';
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
            $res .= '<br class="clear_float" /></div></div>';
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
            $txt .= '<table class="msg_headers">'.
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
                                $txt .= ' <img alt="" class="account_icon" src="'.Hm_Image_Sources::$folder.'" width="16" height="16" /> ';
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
        $input['formatted_message_list'] = array();
        if (isset($input['pop3_mailbox_page'])) {
            $style = isset($input['news_list_style']) ? 'news' : 'email';
            if ($input['is_mobile']) {
                $style = 'news';
            }
            if (isset($input['login_time'])) {
                $login_time = $input['login_time'];
            }
            else {
                $login_time = false;
            }
            $res = format_pop3_message_list($input['pop3_mailbox_page'], $this, $style, $login_time, $input['list_path']);
            $input['formatted_message_list'] = $res;
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

class Hm_Output_start_pop3_settings extends Hm_Output_Module {
    protected function output($input, $format) {
        return '<tr><td onclick="return toggle_page_section(\'.pop3_setting\');" colspan="2" class="settings_subtitle"><img alt="" src="'.Hm_Image_Sources::$env_closed.'" />POP3 Settings</td></tr>';
    }
}

class Hm_Output_pop3_since_setting extends Hm_Output_Module {
    protected function output($input, $format) {
        $since = false;
        if (array_key_exists('user_settings', $input) && array_key_exists('pop3_since', $input['user_settings'])) {
            $since = $input['user_settings']['pop3_since'];
        }
        return '<tr class="pop3_setting"><td>Show messages received since</td><td>'.message_since_dropdown($since, 'pop3_since').'</td></tr>';
    }
}

class Hm_Output_pop3_limit_setting extends Hm_Output_Module {
    protected function output($input, $format) {
        $limit = DEFAULT_PER_SOURCE;
        if (array_key_exists('user_settings', $input) && array_key_exists('pop3_limit', $input['user_settings'])) {
            $limit = $input['user_settings']['pop3_limit'];
        }
        return '<tr class="pop3_setting"><td>Max messages to display</td><td><input type="text" name="pop3_limit" size="2" value="'.$this->html_safe($limit).'" /></td></tr>';
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
        if ($style == 'email' && !$from) {
            $from = '[No From]';
        }
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
