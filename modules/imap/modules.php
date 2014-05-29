<?php

if (!defined('DEBUG_MODE')) { die(); }
require 'lib/hm-imap.php';

class Hm_Handler_imap_folder_expand extends Hm_Handler_Module {
    public function process($data) {
        $data['imap_expanded_folder_data'] = array();
        list($success, $form) = $this->process_form(array('imap_server_id'));
        if ($success) {
            $folder = '';
            if (isset($this->request->post['folder'])) {
                $folder = $this->request->post['folder'];
            }
            $path = sprintf("imap_%d_%s", $form['imap_server_id'], $folder);
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
                $msgs = $imap->get_folder_list_by_level($folder);
                if (isset($msgs[$folder])) {
                    unset($msgs[$folder]);
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
            $rows = array_map(function($v) { return '<tr'.$v; }, array_filter(explode('<tr', $form['formatted_unread_data'])));
            Hm_Page_Cache::add('formatted_unread_data', $rows);
        }
        else {
            Hm_Page_Cache::add('formatted_unread_data', array());
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
        $servers = Hm_IMAP_List::dump();
        $folders = array();
        if (!empty($servers)) {
            foreach ($servers as $id => $server) {
                if ($server['name'] == 'Default-Auth-Server') {
                    $server['name'] = 'Default';
                }
                $folders[$id] = $server['name'];
            }
        }
        $data['imap_folders'] = $folders;
        return $data;
    }
}

class Hm_Handler_imap_message_action extends Hm_Handler_Module {
    public function process($data) {
        list($success, $form) = $this->process_form(array('imap_action_type', 'imap_message_ids'));
        if ($success) {
            if (in_array($form['imap_action_type'], array('delete', 'read', 'unread'))) {
                $ids = process_imap_message_ids($form['imap_message_ids']);
                $errs = 0;
                $msgs = 0;
                foreach ($ids as $server => $folders) {
                    $cache = Hm_IMAP_List::get_cache($this->session, $server);
                    $imap = Hm_IMAP_List::connect($server, $cache);
                    if (is_object($imap) && $imap->get_state() == 'authenticated') {
                        foreach ($folders as $folder => $uids) {
                            if ($imap->select_mailbox($folder)) {
                                if (!$imap->message_action(strtoupper($form['imap_action_type']), $uids)) {
                                    $errs++;
                                }
                                else {
                                    $msgs += count($uids);
                                    if ($form['imap_action_type'] == 'delete') {
                                        $imap->message_action('EXPUNGE', $uids);
                                    }
                                }
                            }
                        }
                    }
                }
                if ($errs > 0) {
                    Hm_Msgs::add(sprintf('ERRAn error occured trying to %s some messages!', $form['imap_action_type'], $server));
                }
            }
        }
    }
}

class Hm_Handler_imap_flagged extends Hm_Handler_Module {
    public function process($data) {
        list($success, $form) = $this->process_form(array('imap_server_ids'));
        if ($success) {
            $ids = explode(',', $form['imap_server_ids']);
            $msg_list = merge_imap_search_results($ids, 'FLAGGED', $this->session);
            $data['imap_flagged_data'] = $msg_list;
            $data['flagged_server_ids'] = $form['imap_server_ids'];
        }
        return $data;
    }
}

class Hm_Handler_imap_unread extends Hm_Handler_Module {
    public function process($data) {
        list($success, $form) = $this->process_form(array('imap_unread_since', 'imap_server_ids'));
        if ($success) {
            $ids = explode(',', $form['imap_server_ids']);
            $msg_list = array();
            $date = false;
            if (in_array($form['imap_unread_since'], array('-1 week', '-2 weeks', '-4 weeks', '-6 weeks', '-6 months', '-1 year'))) {
                $date = date('j-M-Y', strtotime($form['imap_unread_since']));
                $this->user_config->set('imap_unread_since', $form['imap_unread_since']);
            }
            elseif ($form['imap_unread_since'] == 'today') {
                $date = date('j-M-Y');
                $this->user_config->set('imap_unread_since', $form['imap_unread_since']);
            }
            $msg_list = merge_imap_search_results($ids, 'UNSEEN', $this->session, $date);
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
        $data['imap_unread_since'] = $this->user_config->get('imap_unread_since', false);
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
                Hm_Page_Cache::flush($this->session);
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
                    Hm_Page_Cache::flush($this->session);
                }
                else {
                    Hm_Msgs::add("ERRUnable to save this server, are the username and password correct?");
                }
            }
        }
        return $data;
    }
}

class Hm_Handler_imap_message_content extends Hm_Handler_Module {
    public function process($data) {
        list($success, $form) = $this->process_form(array('imap_server_id', 'imap_msg_uid', 'folder'));
        if ($success) {
            $data['msg_text_uid'] = $form['imap_msg_uid'];
            $page_cache = Hm_Page_Cache::get('imap_msg_text_'.$form['imap_server_id'].'_'.$form['imap_msg_uid']);
            if ($page_cache) {
                $data['msg_text'] = $page_cache;
            }
            $part = false;
            if (isset($this->request->post['imap_msg_part']) && preg_match("/[0-9\.]+/", $this->request->post['imap_msg_part'])) {
                $part = $this->request->post['imap_msg_part'];
            }
            $cache = Hm_IMAP_List::get_cache($this->session, $form['imap_server_id']);
            $imap = Hm_IMAP_List::connect($form['imap_server_id'], $cache);
            if ($imap) {
                if ($imap->select_mailbox($form['folder'])) {
                    $data['msg_struct'] = $imap->get_message_structure($form['imap_msg_uid']);
                    if ($part !== false) {
                        if ($part == 0) {
                            $max = 50000;
                        }
                        else {
                            $max = false;
                        }
                        $struct = $imap->search_bodystructure( $data['msg_struct'], array('imap_part_number' => $part));
                        $data['msg_struct_current'] = array_shift($struct);
                        $data['msg_text'] = $imap->get_message_content($form['imap_msg_uid'], $part, $max, $data['msg_struct_current']);
                    }
                    else {
                        list($part, $data['msg_text']) = $imap->get_first_message_part($form['imap_msg_uid'], 'text', false, $data['msg_struct']);
                        $struct = $imap->search_bodystructure( $data['msg_struct'], array('imap_part_number' => $part));
                        $data['msg_struct_current'] = array_shift($struct);
                    }
                    $data['msg_headers'] = $imap->get_message_headers($form['imap_msg_uid']);
                    $data['imap_msg_part'] = $part;
                    $data['msg_cache_suffix'] = 'imap_'.$form['imap_server_id'].'_'.$form['folder'].'_'.$form['imap_msg_uid'];
                    update_unread_cache($form['imap_server_id'], $form['imap_msg_uid'], 'INBOX');

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
                    Hm_Page_Cache::flush($this->session);
                }
            }
            else {
                $data['old_form'] = $form;
            }
        }
        return $data;
    }
}

class Hm_Output_filter_message_body extends Hm_Output_Module {
    protected function output($input, $format) {
        $txt = '<div class="msg_text_inner">';
        if (isset($input['msg_text'])) {
            if (isset($input['msg_struct_current']) && isset($input['msg_struct_current']['subtype']) && $input['msg_struct_current']['subtype'] == 'html') {
                $txt .= format_msg_html($input['msg_text']);
            }
            elseif (isset($input['msg_struct_current']['type']) && $input['msg_struct_current']['type'] == 'image') {
                $txt .= format_msg_image($input['msg_text'], $input['msg_struct_current']['subtype']);
            }
            else {
                $txt .= format_msg_text($input['msg_text'], $this);
            }
        }
        $txt .= '</div>';
        $input['msg_text'] = $txt;
        Hm_Page_Cache::add($input['msg_cache_suffix'].'_text', $txt);
        return $input;
    }
}

class Hm_Output_filter_message_struct extends Hm_Output_Module {
    protected function output($input, $format) {
        $res = '<table class="msg_parts" cellpadding="0" cellspacing="0">';
        if (isset($input['msg_struct'])) {
            $part = 1;
            if (isset($input['imap_msg_part'])) {
                $part = $input['imap_msg_part'];
            }
            $res .=  format_msg_part_section($input['msg_struct'], $this, $part);
        }
        $res .= '</table>';
        $input['msg_parts'] = $res;
        Hm_Page_Cache::add($input['msg_cache_suffix'].'_parts', $res);
        return $input;
    }
}

class Hm_Output_filter_message_headers extends Hm_Output_Module {
    protected function output($input, $format) {
        if (isset($input['msg_headers'])) {
            $txt = '';
            $from = '';
            $small_headers = array('subject', 'date', 'from');
            $headers = $input['msg_headers'];
            $txt .= '<table class="msg_headers" cellspacing="0" cellpadding="0">';
            foreach ($small_headers as $fld) {
                foreach ($headers as $name => $value) {
                    if ($fld == strtolower($name)) {
                        if ($fld == 'from') {
                            $from = $value;
                        }
                        if ($fld == 'subject') {
                            $txt .= '<tr class="header_'.$fld.'"><td colspan="2"><div class="content_title">'.$this->html_safe($value).'</div></td></tr>';
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
            $input['msg_gravatar'] =  build_msg_gravatar($from);
            Hm_Page_Cache::add($input['msg_cache_suffix'].'_headers', $txt);
            Hm_Page_Cache::add($input['msg_cache_suffix'].'_gravatar', $input['msg_gravatar']);
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
            $res .= '<br class="clear_float" /></div>';
        }
        return $res;
    }
}

class Hm_Output_add_imap_server_dialog extends Hm_Output_Module {
    protected function output($input, $format) {
        if ($format == 'HTML5') {
            return '<div class="imap_server_setup"><div class="content_title">IMAP Servers</div><form class="add_server" method="POST">'.
                '<input type="hidden" name="hm_nonce" value="'.$this->build_nonce('add_imap_server').'"/>'.
                '<div class="subtitle">Add an IMAP Server</div><table>'.
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
            return '<input type="hidden" class="imap_server_ids" value="'.$this->html_safe(implode(',', array_keys($input['imap_servers']))).'" />';
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
        $cache = Hm_Page_Cache::get('imap_folders');
        if (!$cache) {
            $res = '<ul class="folders">';
            if (isset($input['imap_folders'])) {
                foreach ($input['imap_folders'] as $id => $folder) {
                    $res .= '<li class="imap_'.intval($id).'_"><a href="#" onclick="return expand_imap_folders(\'imap_'.intval($id).'_\')"><img class="account_icon" src="images/open_iconic/folder-2x.png" /> '.
                        $this->html_safe($folder).'</a></li>';
                }
            }
            $res .= '</ul>';
            Hm_Page_Cache::add('imap_folders', $res, true);
        }
        return '';
    }
}

class Hm_Output_filter_flagged_data extends Hm_Output_Module {
    protected function output($input, $format) {
        if (isset($input['imap_flagged_data'])) {
            $res = format_imap_message_list($input['imap_flagged_data'], $this);
            $input['formatted_flagged_data'] = $res;
            unset($input['imap_flagged_data']);
        }
        elseif (!isset($input['formatted_flagged_data'])) {
            $input['formatted_flagged_data'] = array();
        }
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
                return imap_message_list_unread($input);     
            }
            elseif ($input['list_path'] == 'flagged') {
                return imap_flagged_list();
            }
            elseif (preg_match("/^imap_/", $input['list_path'])) {
                return imap_message_list_folder($input, $this);
            }
        }
        else {
            // TODO: default/not found message list type
        }
    }
}

class Hm_Output_imap_msg_from_cache extends Hm_Output_Module {
    protected function output($input, $format) {
        $key = $input['list_path'].'_'.$input['uid'];
        $body_cache = Hm_Page_Cache::get($key.'_text');
        $header_cache = Hm_Page_Cache::get($key.'_headers');
        $grav_cache = Hm_Page_Cache::get($key.'_gravatar');
        $parts_cache = Hm_Page_Cache::get($key.'_parts');

        if ($body_cache && $grav_cache && $header_cache && $parts_cache) {
            return $header_cache.$grav_cache.$body_cache.$parts_cache;
        }
        return '';
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
            $results .= '<a href="#" class="expand_link" onclick="return expand_imap_folders(\'imap_'.intval($id).'_'.$output_mod->html_safe($folder_name).'\')">+</a>';
        }
        else {
            $results .= ' <img class="folder_icon" src="images/open_iconic/folder.png" alt="" />';
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
    $results .= '</ul>';
    return $results;
}

function format_imap_message_list($msg_list, $output_module) {
    $res = array();
    foreach($msg_list as $msg) {
        if ($msg['server_name'] == 'Default-Auth-Server') {
            $msg['server_name'] = 'Default';
        }
        $id = $output_module->html_safe(sprintf("imap_%s_%s_%s", $msg['server_id'], $msg['uid'], $msg['folder']));
        if (!trim($msg['subject'])) {
            $msg['subject'] = '[No Subject]';
        }
        $subject = preg_replace("/(\[.+\])/U", '<span class="hl">$1</span>', $output_module->html_safe($msg['subject']));
        $from = preg_replace("/(\&lt;.+\&gt;)/U", '', $output_module->html_safe($msg['from']));
        $from = str_replace("&quot;", '', $from);
        $timestamp = $output_module->html_safe(strtotime($msg['internal_date']));
        $date = $output_module->html_safe(human_readable_interval($msg['internal_date']));
        $res[$id] = array('<tr style="display: none;" class="'.$id.'">'.
            '</td><td class="checkbox_row"><input type="checkbox" value="'.$output_module->html_safe($id).'" /></td>'.
            '<td class="source">'.$output_module->html_safe($msg['server_name']).'</td>'.
            '<td class="from">'.$from.'</div></td>'.
            '<td class="subject'.(!stristr($msg['flags'], 'seen') ? ' unseen' : '').
            (stristr($msg['flags'], 'deleted') ? ' deleted' : '').'"><a href="?page=message&amp;uid='.$output_module->html_safe($msg['uid']).
            '&amp;list_path='.$output_module->html_safe(sprintf('imap_%d_%s', $msg['server_id'], $msg['folder'])).'">'.$subject.'</a></td>'.
            '<td class="msg_date">'.$date.'<input type="hidden" class="msg_timestamp" value="'.$timestamp.'" /></td></tr>', $id);
    }
    return $res;
}

function imap_message_controls() {
    return '<div class="msg_controls">'.
        '<a href="#" onclick="return imap_message_action(\'read\');" class="disabled_link">Read</a>'.
        '<a href="#" onclick="return imap_message_action(\'unread\');" class="disabled_link">Unread</a>'.
        '<a href="#" onclick="return imap_message_action(\'flag\');" class="disabled_link">Flag</a>'.
        '<a href="#" onclick="return imap_message_action(\'delete\');" class="disabled_link">Delete</a>'.
        '<a href="#" onclick="return imap_message_action(\'expunge\');" class="disabled_link">Expunge</a>'.
        '<a href="#" onclick="return imap_message_action(\'move\');" class="disabled_link">Move</a>'.
        '<a href="#" onclick="return imap_message_action(\'copy\');" class="disabled_link">Copy</a>'.
        '</div>';
}

function process_imap_message_ids($ids) {
    $res = array();
    foreach (explode(',', $ids) as $id) {
        if (preg_match("/imap_(\d+)_(\d+)_(\S+)$/", $id, $matches)) {
            $server = $matches[1];
            $uid = $matches[2];
            $folder = $matches[3];
            if (!isset($res[$server])) {
                $res[$server] = array();
            }
            if (!isset($res[$server][$folder])) {
                $res[$server][$folder] = array();
            }
            $res[$server][$folder][] = $uid;
        }
    }
    return $res;
}

function imap_message_list_headers() {
    return '<table class="message_table" cellpadding="0" cellspacing="0">'.
        '<colgroup><col class="chkbox_col"><col class="source_col">'.
        '<col class="from_col"><col class="subject_col"><col class="date_col"></colgroup>';
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
    $title = implode('<img class="path_delim" src="images/open_iconic/caret-right.png" alt="&gt;" />', $input['mailbox_list_title']);
    return '<div class="message_list"><div class="content_title">'.$title.
        '<a class="update_unread" href="#"  onclick="return select_imap_folder(\''.
        $output_module->html_safe($input['list_path']).'\', true)">[update]</a>'.
        imap_message_controls().'</div>'.imap_message_list_headers().
        '<tbody>'.$rows.'</tbody></table><div class="imap_page_links">'.$links.'</div></div>';
}

function imap_flagged_list() {
    return '<div class="message_list"><div class="content_title">Flagged'.
        '<a class="update_unread" href="#"">[update]</a>'.imap_message_controls().'</div>'.
        imap_message_list_headers().'<tbody></tbody></table></div>';
}

function imap_message_list_unread($input) {
    $cache = Hm_Page_Cache::get('formatted_unread_data');
    $empty_list = '';
    if ($cache === false) {
        $cache = array();
    }
    elseif (!$cache) {
        $empty_list = '<div class="empty_list">No unread messages found!</div>';
    }
    $cache = implode('', $cache);
    if (isset($input['imap_unread_since'])) {
        $since = $input['imap_unread_since'];
    }
    else {
        $since = '-1 week';
    }
    $times = array(
        'today' => 'Today',
        '-1 week' => 'Last 7 days',
        '-2 weeks' => 'Last 2 weeks',
        '-4 weeks' => 'Last 4 weeks',
        '-6 weeks' => 'Last 6 weeks',
        '-6 months' => 'Last 6 months',
        '-1 year' => 'Last year'
    );

    $res = '<div class="message_list"><div class="content_title">Unread<select class="unread_since">';
    foreach ($times as $val => $label) {
        $res .= '<option';
        if ($val == $since) {
            $res .= ' selected="selected"';
        }
        $res .= ' value="'.$val.'">'.$label.'</option>';
    }
    $res .= '</select><a class="update_unread" href="#" onclick="return imap_unread_update(false, true);">[update]</a>'.
        imap_message_controls().'</div>'.imap_message_list_headers().'<tbody>'.$cache.'</tbody></table>'.$empty_list.'</div>';
    return $res;
}

function build_page_links($detail, $path) {
    $links = '';
    $first = '';
    $last = '';
    $display_links = 10;
    $page_size = $detail['limit'];
    $max_pages = ceil($detail['detail']['exists']/$page_size);
    if ($max_pages == 1) {
        return '';
    }
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

function format_msg_part_row($id, $vals, $output_mod, $level, $part) {
    $allowed = array(
        'textplain',
        'texthtml',
        'messagedisposition-notification',
        'messagedelivery-status',
        'messagerfc822-headers',
        'textcsv',
        'textunknown',
        'textx-vcard',
        'textcalendar',
        'textx-vCalendar',
        'textx-sql',
        'textx-comma-separated-values',
        'textenriched',
        'textrfc822-headers',
        'textx-diff',
        'textx-patch',
        'applicationpgp-signature',
        'applicationx-httpd-php',
        'imagepng',
        'imagejpg',
        'imagejpeg',
        'imagepjpeg',
        'imagegif',
    );
    if ($level > 6) {
        $class = 'row_indent_max';
    }
    else {
        $class = 'row_indent_'.$level;
    }
    if (isset($vals['description']) && trim($vals['description'])) {
        $desc = $vals['description'];
    }
    elseif (isset($vals['name']) && trim($vals['name'])) {
        $desc = $vals['name'];
    }
    elseif (isset($vals['filename']) && trim($vals['filename'])) {
        $desc = $vals['filename'];
    }
    else {
        $desc = '';
    }
    $res = '<tr';
    if ($id == $part) {
        $res .= ' class="selected_part"';
    }
    $res .= '><td><div class="'.$class.'">';
    if (in_array($vals['type'].$vals['subtype'], $allowed)) {
        $res .= '<a href="#" onclick="return get_message_content(\''.$output_mod->html_safe($id).'\');">'.$output_mod->html_safe($vals['type']).
            ' / '.$output_mod->html_safe($vals['subtype']).'</a>';
    }
    else {
        $res .= $output_mod->html_safe($vals['type']).' / '.$output_mod->html_safe($vals['subtype']);
    }
    $res .= '</td><td>'.$output_mod->html_safe($vals['encoding']).
        '</td><td>'.(isset($vals['charset']) && trim($vals['charset']) ? $output_mod->html_safe($vals['charset']) : '-').
        '</td><td>'.$output_mod->html_safe($desc).'</td></tr>';
    return $res;
}

function format_msg_part_section($struct, $output_mod, $part, $level=0) {
    $res = '';
    foreach ($struct as $id => $vals) {
        if (is_array($vals) && isset($vals['type'])) {
            $res .= format_msg_part_row($id, $vals, $output_mod, $level, $part);
            if (isset($vals['subs'])) {
                $res .= format_msg_part_section($vals['subs'], $output_mod, $part, ($level + 1));
            }
        }
        else {
            if (count($vals) == 1 && isset($vals['subs'])) {
                $res .= format_msg_part_section($vals['subs'], $output_mod, $part, $level);
            }
        }
    }
    return $res;
}

function format_msg_html($str) {
    require 'lib/HTMLPurifier.standalone.php';
    $config = HTMLPurifier_Config::createDefault();
    $config->set('Cache.DefinitionImpl', null);
    $config->set('URI.DisableResources', true);
    $config->set('URI.DisableExternalResources', true);
    $config->set('URI.DisableExternal', true);
    $config->set('HTML.TargetBlank', true);
    $config->set('Filter.ExtractStyleBlocks.TidyImpl', true);
    $purifier = new HTMLPurifier($config);
    $res = $purifier->purify($str);
    return $res;
}

function format_msg_image($str, $mime_type) {
    return '<img src="data:image/'.$mime_type.';base64,'.chunk_split(base64_encode($str)).'" />';
}

function format_msg_text($str, $output_mod) {
    $str = nl2br(str_replace(' ', '&#160;&#8203;', ($output_mod->html_safe($str))));
    return $str;
}

function build_msg_gravatar( $from ) {
    if (preg_match("/[\S]+\@[\S]+/", $from, $matches)) {
        $hash = md5(strtolower(trim($matches[0], " \"><'\t\n\r\0\x0B")));
        return '<img class="gravatar" src="http://www.gravatar.com/avatar/'.$hash.'?d=mm" />';
    }
}

function merge_imap_search_results($ids, $search_type, $session, $since = false, $folders = array('INBOX')) {
    $msg_list = array();
    foreach($ids as $id) {
        $id = intval($id);
        $cache = Hm_IMAP_List::get_cache($session, $id);
        $imap = Hm_IMAP_List::connect($id, $cache);
        if (is_object($imap) && $imap->get_state() == 'authenticated') {
            $server_details = Hm_IMAP_List::dump($id);
            foreach ($folders as $folder) {
                if ($imap->select_mailbox($folder)) {
                    if ($since) {
                        $msgs = $imap->search($search_type, false, 'SINCE', $since);
                    }
                    else {
                        $msgs = $imap->search($search_type);
                    }
                    if ($msgs) {
                        foreach ($imap->get_message_list($msgs) as $msg) {
                            $msg['server_id'] = $id;
                            $msg['folder'] = $folder;
                            $msg['server_name'] = $server_details['name'];
                            $msg_list[] = $msg;
                        }
                    }
                }
            }
        }
    }
    usort($msg_list, function($a, $b) {
        if ($a['internal_date'] == $b['internal_date']) return 0;
        return (strtotime($a['internal_date']) < strtotime($b['internal_date']))? -1 : 1;
    });
    return $msg_list;
}

function update_unread_cache($server_id, $msg_uid, $folder) {
    $unread_cache = Hm_Page_Cache::get('formatted_unread_data');
    $new_cache = array();
    if (is_array($unread_cache) && !empty($unread_cache)) {
        foreach ($unread_cache as $line) {
            if (!strstr($line, 'imap_'.$server_id.'_'.$msg_uid.'_'.$folder)) {
                $new_cache[] = $line;
            }
        }
    }
    Hm_Page_Cache::add('formatted_unread_data', $new_cache);
}

?>
