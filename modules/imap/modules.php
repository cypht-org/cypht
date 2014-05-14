<?php

require 'lib/hm-imap.php';

class Hm_Handler_imap_folder_expand extends Hm_Handler_Module {
    public function process($data) {
        $data['imap_expanded_folder_data'] = array();
        list($success, $form) = $this->process_form(array('imap_server_id', 'folder'));
        if ($success) {
            $path = sprintf("imap_%d_%s", $form['imap_server_id'], $form['folder']);
            $page_cache =  Hm_Page_Cache::get('imap_folders_'.$path);
            if ($page_cache) {
                $data['imap_expanded_folder_path'] = $path;
                $data['imap_expanded_folder_formatted'] = $page_cache;
                return $data;
            }
            $details = Hm_IMAP_List::dump($form['imap_server_id']);
            $cache = Hm_IMAP_List::get_cache($this->session, $form['imap_server_id']);
            $imap = Hm_IMAP_List::connect($form['imap_server_id'], $cache);
            if (is_object($imap) && $imap->get_state() == 'authenticated') {
                $msgs = $imap->get_folder_list_by_level($form['folder']);
                if (isset($msgs[$form['folder']])) {
                    unset($msgs[$form['folder']]);
                }
                $data['imap_expanded_folder_data'] = $msgs;
                $data['imap_expanded_folder_id'] = $form['imap_server_id'];
                $data['imap_expanded_folder_path'] = $path;
            }
        }
        return $data;
    }
}

class Hm_Handler_save_folder_state extends Hm_Handler_Module {
    public function process($data) {
        list($success, $form) = $this->process_form(array('imap_folder_state'));
        if ($success) {
            Hm_Page_Cache::add('imap_folders', $form['imap_folder_state'], true);
        }
    }
}

class Hm_Handler_save_unread_state extends Hm_Handler_Module {
    public function process($data) {
        list($success, $form) = $this->process_form(array('formatted_unread_data'));
        if ($success) {
            Hm_Page_Cache::add('formatted_unread_data',
                preg_replace("/<tr style=\"opacity: \d(\.\d+|);\"/", "<tr ", $form['formatted_unread_data']));
        }
    }
}

class Hm_Handler_imap_folder_page extends Hm_Handler_Module {
    public function process($data) {

        $sort = 'ARRIVAL';
        $rev = true;
        $filter = 'ALL';
        $offset = 0;
        $limit = 20;
        $msgs = array();
        $list_page = 1;

        list($success, $form) = $this->process_form(array('imap_server_id', 'folder'));
        if ($success) {
            if (isset($this->request->get['list_page'])) {
                $list_page = (int) $this->request->get['list_page'];
                if ($list_page && $list_page > 1) {
                    $offset = ($list_page - 1)*$limit;
                }
                else {
                    $list_page = 1;
                }
            }
            $path = sprintf("imap_%d_%s", $form['imap_server_id'], $form['folder']);
            $details = Hm_IMAP_List::dump($form['imap_server_id']);
            $cache = Hm_IMAP_List::get_cache($this->session, $form['imap_server_id']);
            $imap = Hm_IMAP_List::connect($form['imap_server_id'], $cache);
            if (is_object($imap) && $imap->get_state() == 'authenticated') {
                $data['imap_mailbox_page_path'] = $path;
                foreach ($imap->get_mailbox_page($form['folder'], $sort, $rev, $filter, $offset, $limit) as $msg) {
                    $msg['server_id'] = $form['imap_server_id'];
                    $msg['server_name'] = $details['name'];
                    $msg['folder'] = $form['folder'];
                    $msgs[] = $msg;
                }
                $data['imap_folder_detail'] = $imap->selected_mailbox;
                $data['imap_folder_detail']['offset'] = $offset;
                $data['imap_folder_detail']['limit'] = $limit;
            }
            $data['imap_mailbox_page'] = $msgs;
            $data['list_page'] = $list_page;
            $data['imap_server_id'] = $form['imap_server_id'];
        }
        return $data;
    }
}

class Hm_Handler_prep_imap_summary_display extends Hm_Handler_Module {
    public function process($data) {
        list($success, $form) = $this->process_form(array('summary_ids'));
        if ($success) {
            $ids = explode(',', $form['summary_ids']);
            foreach($ids as $id) {
                $id = intval($id);
                $details = Hm_IMAP_List::dump($id);
                $cache = Hm_IMAP_List::get_cache($this->session, $id);
                $imap = Hm_IMAP_List::connect($id, $cache);
                if (is_object($imap) && $imap->get_state() == 'authenticated') {
                    $data['imap_summary'][$id] = $imap->get_mailbox_status('INBOX');
                }
                else {
                    if (!$imap) {
                        Hm_Msgs::add(sprintf('ERRCould not access IMAP server "%s" (%s:%d)', $details['name'], $details['server'], $details['port']));
                    }
                    $data['imap_summary'][$id] = array('messages' => '?', 'unseen' => '?');
                }
            }
        }
        return $data;
    }
}

class Hm_Handler_load_imap_folders extends Hm_Handler_Module {
    public function process($data) {
        $folders = array();
        list($success, $form) = $this->process_form(array('imap_folder_ids'));
        if ($success) {
            $ids = explode(',', $form['imap_folder_ids']);
            foreach ($ids as $id) {
                $details = Hm_IMAP_List::dump($id);
                $cache = Hm_IMAP_List::get_cache($this->session, $id);
                $imap = Hm_IMAP_List::connect($id, $cache);
                if (is_object($imap) && $imap->get_state() == 'authenticated') {
                    $folders[$id] = $imap->get_folder_list_by_level();
                }
            }
        }
        $data['imap_folders'] = $folders;
        return $data;
    }
}

class Hm_Handler_imap_unread extends Hm_Handler_Module {
    public function process($data) {
        list($success, $form) = $this->process_form(array('imap_server_ids'));
        if ($success) {
            $ids = explode(',', $form['imap_server_ids']);
            $msg_list = array();
            foreach($ids as $id) {
                $id = intval($id);
                $cache = Hm_IMAP_List::get_cache($this->session, $id);
                $imap = Hm_IMAP_List::connect($id, $cache);
                if (is_object($imap) && $imap->get_state() == 'authenticated') {
                    $imap->read_only = true;
                    $server_details = Hm_IMAP_List::dump($id);
                    if ($imap->select_mailbox('INBOX')) {
                        $unseen = $imap->search('UNSEEN');
                        if ($unseen) {
                            foreach ($imap->get_message_list($unseen) as $msg) {
                                $msg['server_id'] = $id;
                                $msg['folder'] = 'INBOX';
                                $msg['server_name'] = $server_details['name'];
                                $msg_list[] = $msg;
                            }
                        }
                    }
                }
            }
            usort($msg_list, function($a, $b) {
                if ($a['internal_date'] == $b['internal_date']) return 0;
                return (strtotime($a['internal_date']) < strtotime($b['internal_date']))? -1 : 1;
            });
            $data['imap_unread_data'] = $msg_list;
            $data['unread_server_ids'] = $form['imap_server_ids'];
        }
        return $data;
    }
}

class Hm_Handler_process_add_imap_server extends Hm_Handler_Module {
    public function process($data) {
        if (isset($this->request->post['submit_imap_server'])) {
            list($success, $form) = $this->process_form(array('new_imap_name', 'new_imap_address', 'new_imap_port'), 'add_imap_server');
            if (!$success) {
                $data['old_form'] = $form;
                Hm_Msgs::add('ERRYou must supply a name, a server and a port');
            }
            else {
                $tls = false;
                if (isset($this->request->post['tls'])) {
                    $tls = true;
                }
                if ($con = fsockopen($form['new_imap_address'], $form['new_imap_port'], $errno, $errstr, 2)) {
                    Hm_IMAP_List::add(array(
                        'name' => $form['new_imap_name'],
                        'server' => $form['new_imap_address'],
                        'port' => $form['new_imap_port'],
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

class Hm_Handler_save_imap_cache extends Hm_Handler_Module {
    public function process($data) {
        $servers = Hm_IMAP_List::dump(false, true);
        $cache = $this->session->get('imap_cache', array());
        foreach ($servers as $index => $server) {
            if (isset($server['object']) && is_object($server['object'])) {
                $cache[$index] = $server['object']->dump_cache('string');
            }
        }
        if (count($cache) > 0) {
            $this->session->set('imap_cache', $cache);
            Hm_Debug::add(sprintf('Cached data for %d IMAP connections', count($cache)));
        }
        return $data;
    }
}

class Hm_Handler_save_imap_servers extends Hm_Handler_Module {
    public function process($data) {
        $servers = Hm_IMAP_List::dump();
        $this->user_config->set('imap_servers', $servers);
        Hm_IMAP_List::clean_up();
        return $data;
    }
}

class Hm_Handler_load_imap_servers_from_config extends Hm_Handler_Module {
    public function process($data) {
        $servers = $this->user_config->get('imap_servers', array());
        $added = false;
        foreach ($servers as $index => $server) {
            Hm_IMAP_List::add($server, $index);
            if ($server['name'] == 'Default-Auth-Server') {
                $added = true;
            }
        }
        if (!$added) {
            $auth_server = $this->session->get('imap_auth_server_settings', array());
            if (!empty($auth_server)) {
                Hm_IMAP_List::add(array( 
                    'name' => 'Default-Auth-Server',
                    'server' => $auth_server['server'],
                    'port' => $auth_server['port'],
                    'tls' => $auth_server['tls'],
                    'user' => $auth_server['username'],
                    'pass' => $auth_server['password']),
                count($servers));
                $this->session->del('imap_auth_server_settings');
            }
        }
        return $data;
    }
}

class Hm_Handler_add_imap_servers_to_page_data extends Hm_Handler_Module {
    public function process($data) {
        $data['imap_servers'] = array();
        $servers = Hm_IMAP_List::dump();
        if (!empty($servers)) {
            $data['imap_servers'] = $servers;
            $data['folder_sources'][] = 'imap_folders';
        }
        return $data;
    }
}

class Hm_Handler_imap_bust_cache extends Hm_Handler_Module {
    public function process($data) {
        //$this->session->set('imap_cache', array());
        return $data;
    }
}

class Hm_Handler_imap_connect extends Hm_Handler_Module {
    public function process($data) {
        if (isset($this->request->post['imap_connect'])) {
            list($success, $form) = $this->process_form(array('imap_user', 'imap_pass', 'imap_server_id'));
            $imap = false;
            $cache = Hm_IMAP_List::get_cache($this->session, $form['imap_server_id']);
            if ($success) {
                $imap = Hm_IMAP_List::connect($form['imap_server_id'], $cache, $form['imap_user'], $form['imap_pass']);
            }
            elseif (isset($form['imap_server_id'])) {
                $imap = Hm_IMAP_List::connect($form['imap_server_id'], $cache);
            }
            if ($imap) {
                if ($imap->get_state() == 'authenticated') {
                    Hm_Msgs::add("Successfully authenticated to the IMAP server");
                }
                else {
                    Hm_Msgs::add("ERRFailed to authenticate to the IMAP server");
                }
            }
            else {
                Hm_Msgs::add('ERRUsername and password are required');
                $data['old_form'] = $form;
            }
        }
        return $data;
    }
}

class Hm_Handler_imap_forget extends Hm_Handler_Module {
    public function process($data) {
        $data['just_forgot_credentials'] = false;
        if (isset($this->request->post['imap_forget'])) {
            list($success, $form) = $this->process_form(array('imap_server_id'));
            if ($success) {
                Hm_IMAP_List::forget_credentials($form['imap_server_id']);
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

class Hm_Handler_imap_save extends Hm_Handler_Module {
    public function process($data) {
        $data['just_saved_credentials'] = false;
        if (isset($this->request->post['imap_save'])) {
            list($success, $form) = $this->process_form(array('imap_user', 'imap_pass', 'imap_server_id'));
            if (!$success) {
                Hm_Msgs::add('ERRUsername and Password are required to save a connection');
            }
            else {
                $cache = Hm_IMAP_List::get_cache($this->session, $form['imap_server_id']);
                $imap = Hm_IMAP_List::connect($form['imap_server_id'], $cache, $form['imap_user'], $form['imap_pass'], true);
                if ($imap->get_state() == 'authenticated') {
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

class Hm_Handler_imap_message_text extends Hm_Handler_Module {
    public function process($data) {
        list($success, $form) = $this->process_form(array('imap_server_id', 'imap_msg_uid', 'folder'));
        if ($success) {
            $data['msg_text_uid'] = $form['imap_msg_uid'];
            $page_cache = Hm_Page_Cache::get('imap_msg_text_'.$form['imap_server_id'].'_'.$form['imap_msg_uid']);
            if ($page_cache) {
                $data['msg_text'] = $page_cache;
            }
            else {
                $cache = Hm_IMAP_List::get_cache($this->session, $form['imap_server_id']);
                $imap = Hm_IMAP_List::connect($form['imap_server_id'], $cache);
                if ($imap) {
                    $imap->read_only = true;
                    if ($imap->select_mailbox($form['folder'])) {
                        $data['msg_text'] = $imap->get_first_message_part($form['imap_msg_uid'], 'text', 'plain');
                        $data['msg_headers'] = $imap->get_message_headers($form['imap_msg_uid']);
                        $data['msg_cache_path'] = 'imap_msg_text_'.$form['imap_server_id'].'_'.$form['imap_msg_uid'];
                    }
                }
            }
        }
        return $data;
    }
}

class Hm_Handler_imap_delete extends Hm_Handler_Module {
    public function process($data) {
        if (isset($this->request->post['imap_delete'])) {
            list($success, $form) = $this->process_form(array('imap_server_id'));
            if ($success) {
                $res = Hm_IMAP_List::del($form['imap_server_id']);
                if ($res) {
                    $data['deleted_server_id'] = $form['imap_server_id'];
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

class Hm_Output_filter_message_text extends Hm_Output_Module {
    protected function output($input, $format) {
        if (isset($input['msg_text']) && isset($input['msg_headers'])) {
            $txt = '';
            $small_headers = array('subject', 'date', 'from');
            $headers = $input['msg_headers'];
            $txt .= '<table class="msg_headers" cellspacing="0" cellpadding="0">';
            foreach ($small_headers as $fld) {
                foreach ($headers as $name => $value) {
                    if ($fld == strtolower($name)) {
                        $txt .= '<tr class="header_'.$fld.'"><th>'.$this->html_safe($name).'</th><td>'.$this->html_safe($value).'</td></tr>';
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
                '<a href="#" class="header_toggle" onclick="return toggle_long_headers();">All</a>'.
                '<a class="header_toggle" style="display: none;" href="#" onclick="return toggle_long_headers();">Small</a>'.
                '<a class="close_link" href="#" onclick="return close_msg_preview();">Close</a>'.
                '</th></tr></table>'.
                '<div class="msg_text_inner">'.$this->html_safe($input['msg_text']).'</div>'.
                '<a href="#" class="close_link" onclick="return close_msg_preview();">Close</a>';

            $input['msg_text'] = $txt;
            Hm_Page_Cache::add($input['msg_cache_path'], $txt);
        }
        return $input;
    }
}

class Hm_Output_display_configured_imap_servers extends Hm_Output_Module {
    protected function output($input, $format) {
        $res = '';
        if ($format == 'HTML5') {
            $res = '';
            foreach ($input['imap_servers'] as $index => $vals) {

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
                    if (stristr($input['session_type'], 'imap')) {
                        $no_edit = true;
                    }
                }
                $res .= '<div class="configured_server">';
                $res .= sprintf('<div class="server_title">%s</div><div class="server_subtitle">%s/%d %s</div>',
                    $this->html_safe($vals['name']), $this->html_safe($vals['server']), $this->html_safe($vals['port']),
                    $vals['tls'] ? 'TLS' : '' );
                $res .= 
                    '<form class="imap_connect" method="POST">'.
                    '<input type="hidden" name="imap_server_id" value="'.$this->html_safe($index).'" /><span> '.
                    '<input '.$disabled.' class="credentials" placeholder="Username" type="text" name="imap_user" value="'.$user_pc.'"></span>'.
                    '<span> <input '.$disabled.' class="credentials imap_password" placeholder="'.$pass_pc.'" type="password" name="imap_pass"></span>';
                if (!$no_edit) {
                    $res .= '<input type="submit" value="Test" class="test_imap_connect" />';
                    if (!isset($vals['user']) || !$vals['user']) {
                        $res .= '<input type="submit" value="Delete" class="imap_delete" />';
                        $res .= '<input type="submit" value="Save" class="save_imap_connection" />';
                    }
                    else {
                        $res .= '<input type="submit" value="Delete" class="imap_delete" />';
                        $res .= '<input type="submit" value="Forget" class="forget_imap_connection" />';
                    }
                    $res .= '<input type="hidden" value="ajax_imap_debug" name="hm_ajax_hook" />';
                }
                $res .= '</form></div>';
            }
        }
        return $res;
    }
}

class Hm_Output_add_imap_server_dialog extends Hm_Output_Module {
    protected function output($input, $format) {
        if ($format == 'HTML5') {
            return '<form class="add_server" method="POST"><input type="hidden" name="hm_nonce" value="'.$this->build_nonce('add_imap_server').'"/>'.
                '<div class="subtitle">Add an IMAP Server</div>'.
                '<table>'.
                '<tr><td colspan="2"><input type="text" name="new_imap_name" class="txt_fld" value="" placeholder="Account name" /></td></tr>'.
                '<tr><td colspan="2"><input type="text" name="new_imap_address" class="txt_fld" placeholder="IMAP server address" value=""/></td></tr>'.
                '<tr><td colspan="2"><input type="text" name="new_imap_port" class="port_fld" value="" placeholder="Port"></td></tr>'.
                '<tr><td><input type="checkbox" name="tls" value="1" checked="checked" /> Use TLS</td>'.
                '<td align="right"><input type="submit" value="Add" name="submit_imap_server" /></td></tr>'.
                '</table></form>';
        }
    }
}

class Hm_Output_display_imap_summary extends Hm_Output_Module {
    protected function output($input, $format) {
        if ($format == 'HTML5') {
            $res = '';
            if (isset($input['imap_servers']) && !empty($input['imap_servers'])) {
                foreach ($input['imap_servers'] as $index => $vals) {
                    if ($vals['name'] == 'Default-Auth-Server') {
                        $vals['name'] = 'Default';
                    }
                    $res .= '<tr><td>IMAP</td><td>'.$vals['name'].'</td>'.
                        '<td>'.$vals['server'].'</td><td>'.$vals['port'].'</td>'.
                        '<td>'.$vals['tls'].'</td>'.
                        '</tr>';
                }
            }
            return $res;
        }
    }
}

class Hm_Output_imap_server_ids extends Hm_Output_Module {
    protected function output($input, $format) {
        if (isset($input['imap_servers'])) {
            return '<input type="hidden" id="imap_server_ids" value="'.$this->html_safe(implode(',', array_keys($input['imap_servers']))).'" />';
        }
    }
}

class Hm_Output_filter_expanded_folder_data extends Hm_Output_Module {
    protected function output($input, $format) {
        $res = '';
        if (isset($input['imap_expanded_folder_data']) && !empty($input['imap_expanded_folder_data'])) {
            ksort($input['imap_expanded_folder_data']);
            $res .= format_imap_folder_section($input['imap_expanded_folder_data'], $input['imap_expanded_folder_id'], $this);
            $input['imap_expanded_folder_formatted'] = $res;
            unset($input['imap_expanded_folder_data']);
            Hm_Page_Cache::add('imap_folders_'.$input['imap_expanded_folder_path'], $res);
        }
        return $input;
    }
}

class Hm_Output_filter_imap_folders extends Hm_Output_Module {
    protected function output($input, $format) {
        $results = '<ul class="folders">';
        if (isset($input['imap_folders'])) {
            foreach ($input['imap_folders'] as $id => $folders) {
                $details = Hm_IMAP_List::dump($id);
                $results .= '<li>'.$this->html_safe($details['name']).'</li>';
                $results .= '<li>'.format_imap_folder_section($folders, $id, $this).'</li>';
            }
        }
        $results .= '<li class="imap_update"><a href="#" onclick="return imap_folder_update(true); return false;">Update</a></li></ul>';
        $input['imap_folders'] = $results;
        Hm_Page_Cache::add('imap_folders', $results, true);
        return $input;
    }
}

class Hm_Output_filter_unread_data extends Hm_Output_Module {
    protected function output($input, $format) {
        if (isset($input['imap_unread_data'])) {
            $res = format_imap_message_list($input['imap_unread_data'], $this);
            $input['formatted_unread_data'] = $res;
            unset($input['imap_unread_data']);
        }
        elseif (!isset($input['formatted_unread_data'])) {
            $input['formatted_unread_data'] = array();
        }
        return $input;
    }
}

class Hm_Output_imap_message_list extends Hm_Output_Module {
    protected function output($input, $format) {
        if (isset($input['list_path'])) {
            if ($input['list_path'] == 'unread') {
                return imap_message_list_unread();     
            }
            elseif (preg_match("/^imap_/", $input['list_path'])) {
                return imap_message_list_folder($input, $this);
            }
        }
        else {
            // TODO: default
        }
    }
}

class Hm_Output_filter_folder_page extends Hm_Output_Module {
    protected function output($input, $format) {
        $res = array();
        if (isset($input['imap_mailbox_page']) && !empty($input['imap_mailbox_page'])) {
            $res = format_imap_message_list($input['imap_mailbox_page'], $this);
            $input['formatted_mailbox_page'] = $res;
            Hm_Page_Cache::add('formatted_mailbox_page_'.$input['imap_mailbox_page_path'].'_'.$input['list_page'], $res);
            $input['imap_page_links'] = build_page_links($input['imap_folder_detail'], $input['imap_mailbox_page_path']);
            Hm_Page_Cache::add('imap_page_links_'.$input['imap_mailbox_page_path'].'_'.$input['list_page'], $input['imap_page_links']);
            unset($input['imap_mailbox_page']);
            unset($input['imap_folder_detail']);
        }
        elseif (!isset($input['formatted_mailbox_page'])) {
            $input['formatted_mailbox_page'] = array();
        }
        return $input;
    }
}

function format_imap_folder_section($folders, $id, $output_mod) {
    $results = '<ul class="inner_list">';
    foreach ($folders as $folder_name => $folder) {
        $results .= '<li class="imap_'.$id.'_'.$output_mod->html_safe($folder_name).'">';
        if ($folder['children']) {
            $results .= '<a href="#" onclick="return expand_imap_folders(\'imap_'.intval($id).'_'.$output_mod->html_safe($folder_name).'\')">+</a>';
        }
        if (!$folder['noselect']) {
            $results .= '<a href="?page=message_list&amp;list_path='.
            urlencode('imap_'.intval($id).'_'.$output_mod->html_safe($folder_name)).
            '">'.$output_mod->html_safe($folder['basename']).'</a>';
        }
        else {
            $results .= $output_mod->html_safe($folder['basename']);
        }
        $results .= '</li>';
    }
    $results .= '</ul></li>';
    return $results;
}

function format_imap_message_list($msg_list, $output_module) {
    $res = array();
    foreach($msg_list as $msg) {
        if ($msg['server_name'] == 'Default-Auth-Server') {
            $msg['server_name'] = 'Default';
        }
        $id = sprintf("imap_%s_%s", $output_module->html_safe($msg['server_id']), $output_module->html_safe($msg['uid']));
        $subject = preg_replace("/(\[.+\])/U", '<span class="hl">$1</span>', $output_module->html_safe($msg['subject']));
        $from = preg_replace("/(\&lt;.+\&gt;)/U", '<span class="dl">$1</span>', $output_module->html_safe($msg['from']));
        $from = str_replace("&quot;", '', $from);
        $date = date('Y-m-d G:i:s', strtotime($output_module->html_safe($msg['internal_date'])));
        $res[$id] = array('<tr style="display: none;" class="'.$id.'"><td class="source">'.$output_module->html_safe($msg['server_name']).'</td>'.
            '<td onclick="return msg_preview('.$output_module->html_safe($msg['uid']).', '.
            $output_module->html_safe($msg['server_id']).', \''.$output_module->html_safe($msg['folder']).'\')" class="subject">'.$subject.
            '</td><td class="from">'.$from.'</div></td>'.
            '<td class="msg_date">'.$date.'</td></tr>', $id);
    }
    return $res;
}

function imap_message_list_folder($input, $output_module) {
    $page_cache = Hm_Page_Cache::get('formatted_mailbox_page_'.$input['list_path'].'_'.$input['list_page']);
    $rows = '';
    $links = '';
    if ($page_cache) {
        $rows = implode(array_map(function($v) { return $v[0]; }, $page_cache));
    }
    $links_cache = Hm_Page_Cache::get('imap_page_links_'.$input['list_path'].'_'.$input['list_page']);
    if ($links_cache) {
        $links = $links_cache;
    }
    return '<div class="message_list"><div class="msg_text"></div><div class="content_title">'.
        $output_module->html_safe($input['mailbox_list_title']).'</div>'.
        '<a class="update_unread" href="#"  onclick="return select_imap_folder(\''.$output_module->html_safe($input['list_path']).'\', true)">Update</a>'.
        '<table class="message_table" cellpadding="0" cellspacing="0"><colgroup><col class="source_col">'.
        '<col class="subject_col"><col class="from_col"><col class="date_col"></colgroup>'.
        '<thead><tr><th>Source</th><th>Subject</th><th>From</th><th>Date</th></tr></thead>'.
        '<tbody>'.$rows.'</tbody></table><div class="imap_page_links">'.$links.'</div></div>';
}

function imap_message_list_unread() {
    $cache = (string) Hm_Page_Cache::get('formatted_unread_data');
    return '<div class="message_list"><div class="msg_text"></div><div class="content_title">Unread</div>'.
        '<a class="update_unread" href="#" onclick="return imap_unread_update(false, true);">Update</a>'.
        '<table class="message_table" cellpadding="0" cellspacing="0"><colgroup><col class="source_col">'.
        '<col class="subject_col"><col class="from_col"><col class="date_col"></colgroup>'.
        '<thead><tr><th>Source</th><th>Subject</th><th>From</th><th>Date</th></tr></thead>'.
        '<tbody>'.$cache.'</tbody></table></div>';
}

function build_page_links($detail, $path) {
    $links = '';
    $first = '';
    $last = '';
    $display_links = 10;
    $page_size = $detail['limit'];
    $max_pages = ceil($detail['detail']['exists']/$page_size);
    $current_page = $detail['offset']/$page_size + 1;
    $floor = $current_page - intval($display_links/2);
    if ($floor < 0) {
        $floor = 1;
    }
    $ceil = $floor + $display_links;
    if ($ceil > $max_pages) {
        $floor -= ($ceil - $max_pages);
    }
    $prev = '<a class="disabled_link"><img src="images/open_iconic/caret-left-2x.png" alt="&larr;" /></a>';
    $next = '<a class="disabled_link"><img src="images/open_iconic/caret-right-2x.png" alt="&rarr;" /></a>';

    if ($floor > 1 ) {
        $first = '<a href="?page=message_list&amp;list_path='.urlencode($path).'&amp;list_page=1">1</a> ... ';
    }
    if ($ceil < $max_pages) {
        $last = ' ... <a href="?page=message_list&amp;list_path='.urlencode($path).'&amp;list_page='.$max_pages.'">'.$max_pages.'</a>';
    }
    if ($current_page > 1) {
        $prev = '<a href="?page=message_list&amp;list_path='.urlencode($path).'&amp;list_page='.($current_page - 1).'"><img src="images/open_iconic/caret-left-2x.png" alt="&larr;" /></a>';
    }
    if ($max_pages > 1 && $current_page < $max_pages) {
        $next = '<a href="?page=message_list&amp;list_path='.urlencode($path).'&amp;list_page='.($current_page + 1).'"><img src="images/open_iconic/caret-right-2x.png" alt="&rarr;" /></a>';
    }
    for ($i=1;$i<=$max_pages;$i++) {
        if ($i < $floor || $i > $ceil) {
            continue;
        }
        $links .= ' <a ';
        if ($i == $current_page) {
            $links .= 'class="current_page" ';
        }
        $links .= 'href="?page=message_list&amp;list_path='.urlencode($path).'&amp;list_page='.$i.'">'.$i.'</a>';
    }
    return $prev.' '.$first.$links.$last.' '.$next;
}
?>
