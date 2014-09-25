<?php

if (!defined('DEBUG_MODE')) { die(); }
require 'modules/imap/hm-imap.php';

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
        list($success, $form) = $this->process_form(array('action_type', 'message_ids'));
        if ($success) {
            if (in_array($form['action_type'], array('delete', 'read', 'unread', 'flag', 'unflag'))) {
                $ids = process_imap_message_ids($form['message_ids']);
                $errs = 0;
                $msgs = 0;
                foreach ($ids as $server => $folders) {
                    $cache = Hm_IMAP_List::get_cache($this->session, $server);
                    $imap = Hm_IMAP_List::connect($server, $cache);
                    if (is_object($imap) && $imap->get_state() == 'authenticated') {
                        foreach ($folders as $folder => $uids) {
                            if ($imap->select_mailbox($folder)) {
                                if (!$imap->message_action(strtoupper($form['action_type']), $uids)) {
                                    $errs++;
                                }
                                else {
                                    $msgs += count($uids);
                                    if ($form['action_type'] == 'delete') {
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
        return $data;
    }
}

class Hm_Handler_imap_search extends Hm_Handler_Module {
    public function process($data) {
        list($success, $form) = $this->process_form(array('imap_server_ids'));
        if ($success) {
            $terms = $this->session->get('search_terms', false);
            $since = $this->session->get('search_since', DEFAULT_SINCE);
            $fld = $this->session->get('search_fld', 'TEXT');
            $ids = explode(',', $form['imap_server_ids']);
            $date = process_since_argument($since);
            $msg_list = merge_imap_search_results($ids, 'ALL', $this->session, array('INBOX'), MAX_PER_SOURCE, array('SINCE' => $date, $fld => $terms));
            $data['imap_search_results'] = $msg_list;
            $data['imap_search_ids'] = $form['imap_server_ids'];
        }
        return $data;
    }
}

class Hm_Handler_imap_combined_inbox extends Hm_Handler_Module {
    public function process($data) {
        list($success, $form) = $this->process_form(array('imap_server_ids'));
        if ($success) {
            $limit = $this->user_config->get('all_per_source_setting', DEFAULT_PER_SOURCE);
            $date = process_since_argument($this->user_config->get('all_since_setting', DEFAULT_SINCE));
            $ids = explode(',', $form['imap_server_ids']);
            $msg_list = merge_imap_search_results($ids, 'ALL', $this->session, array('INBOX'), $limit, array('SINCE' => $date));
            $data['imap_combined_inbox_data'] = $msg_list;
            $data['combined_inbox_server_ids'] = $form['imap_server_ids'];
        }
        return $data;
    }
}

class Hm_Handler_imap_flagged extends Hm_Handler_Module {
    public function process($data) {
        list($success, $form) = $this->process_form(array('imap_server_ids'));
        if ($success) {
            $limit = $this->user_config->get('flagged_per_source_setting', DEFAULT_PER_SOURCE);
            $ids = explode(',', $form['imap_server_ids']);
            $date = process_since_argument($this->user_config->get('flagged_since_setting', DEFAULT_SINCE));
            $msg_list = merge_imap_search_results($ids, 'FLAGGED', $this->session, array('INBOX'), $limit, array('SINCE' => $date));
            $data['imap_flagged_data'] = $msg_list;
            $data['flagged_server_ids'] = $form['imap_server_ids'];
        }
        return $data;
    }
}

class Hm_Handler_imap_unread_total extends Hm_Handler_Module {
    public function process($data) {
        $total = 0;
        list($success, $form) = $this->process_form(array('imap_server_ids'));
        if ($success) {
            $ids = explode(',', $form['imap_server_ids']);
            foreach ($ids as $id) {
                $cache = Hm_IMAP_List::get_cache($this->session, $id);
                $imap = Hm_IMAP_List::connect($id, $cache);
                if ($imap && $imap->get_state() == 'authenticated') {
                    $status = $imap->get_mailbox_status('INBOX', array('UNSEEN'));
                    if (isset($status['unseen'])) {
                        $total += $status['unseen'];
                    }
                }
            }
        }
        $data['unseen_total'] = $total;
        return $data;
    }
}

class Hm_Handler_imap_status extends Hm_Handler_Module {
    public function process($data) {
        list($success, $form) = $this->process_form(array('imap_server_ids'));
        if ($success) {
            $ids = explode(',', $form['imap_server_ids']);
            foreach ($ids as $id) {
                $cache = Hm_IMAP_List::get_cache($this->session, $id);
                $start_time = microtime(true);
                $imap = Hm_IMAP_List::connect($id, $cache);
                $data['imap_connect_time'] = microtime(true) - $start_time;
                if ($imap) {
                    $data['imap_connect_status'] = $imap->get_state();
                    $data['imap_status_inbox'] = $imap->select_mailbox('INBOX');
                    $data['imap_status_server_id'] = $id;
                }
                else {
                    $data['imap_connect_status'] = 'disconnected';
                    $data['imap_status_inbox'] = false;
                    $data['imap_status_server_id'] = $id;
                }
            }
        }
        return $data;
    }
}

class Hm_Handler_imap_unread extends Hm_Handler_Module {
    public function process($data) {
        list($success, $form) = $this->process_form(array('imap_server_ids'));
        if ($success) {
            $limit = $this->user_config->get('unread_per_source_setting', DEFAULT_PER_SOURCE);
            $date = process_since_argument($this->user_config->get('unread_since_setting', DEFAULT_SINCE));
            $ids = explode(',', $form['imap_server_ids']);
            $msg_list = array();
            $msg_list = merge_imap_search_results($ids, 'UNSEEN', $this->session, array('INBOX'), $limit, array('SINCE' => $date));
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
                    $this->session->record_unsaved('IMAP server added');
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
        $servers = Hm_IMAP_List::dump(false, true);
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
            $data['folder_sources'][] = 'email_folders';
        }
        return $data;
    }
}

class Hm_Handler_imap_bust_cache extends Hm_Handler_Module {
    public function process($data) {
        $this->session->set('imap_cache', array());
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
                $this->session->record_unsaved('IMAP server credentials forgotten');
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
                    $this->session->record_unsaved('IMAP server saved');
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
                    $this->session->record_unsaved('IMAP server deleted');
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
        return $input;
    }
}

class Hm_Output_filter_message_struct extends Hm_Output_Module {
    protected function output($input, $format) {
        $res = '<table class="msg_parts">';
        if (isset($input['msg_struct'])) {
            $part = 1;
            if (isset($input['imap_msg_part'])) {
                $part = $input['imap_msg_part'];
            }
            $res .=  format_msg_part_section($input['msg_struct'], $this, $part);
        }
        $res .= '</table>';
        $input['msg_parts'] = $res;
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
                                $txt .= ' <img alt="" class="account_icon" src="'.Hm_Image_Sources::$star.'" width="16" height="16" /> ';
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
        return $input;
    }
}

class Hm_Output_display_configured_imap_servers extends Hm_Output_Module {
    protected function output($input, $format) {
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
                $no_edit = true;
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
        return $res;
    }
}

class Hm_Output_add_imap_server_dialog extends Hm_Output_Module {
    protected function output($input, $format) {
        return '<div class="imap_server_setup"><div class="content_title">IMAP Servers</div><form class="add_server" method="POST">'.
            '<input type="hidden" name="hm_nonce" value="'.$this->build_nonce('add_imap_server').'"/>'.
            '<div class="subtitle">Add an IMAP Server</div><table>'.
            '<tr><td colspan="2"><input type="text" name="new_imap_name" class="txt_fld" value="" placeholder="Account name" /></td></tr>'.
            '<tr><td colspan="2"><input type="text" name="new_imap_address" class="txt_fld" placeholder="IMAP server address" value=""/></td></tr>'.
            '<tr><td colspan="2"><input type="text" name="new_imap_port" class="port_fld" value="" placeholder="Port"></td></tr>'.
            '<tr><td><input type="checkbox" name="tls" value="1" checked="checked" /> Use TLS</td>'.
            '<td><input type="submit" value="Add" name="submit_imap_server" /></td></tr>'.
            '</table></form>';
    }
}

class Hm_Output_display_imap_status extends Hm_Output_Module {
    protected function output($input, $format) {
        $res = '';
        if (isset($input['imap_servers']) && !empty($input['imap_servers'])) {
            foreach ($input['imap_servers'] as $index => $vals) {
                if ($vals['name'] == 'Default-Auth-Server') {
                    $vals['name'] = 'Default';
                }
                $res .= '<tr><td>IMAP</td><td>'.$vals['name'].'</td><td class="imap_status_'.$index.'"></td>'.
                    '<td class="imap_detail_'.$index.'"></td></tr>';
            }
        }
        return $res;
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

class Hm_Output_filter_imap_status_data extends Hm_Output_Module {
    protected function output($input, $format) {
        if (isset($input['imap_connect_status']) && $input['imap_connect_status'] != 'disconnected') {
            $input['imap_status_display'] = '<span class="online">'.
                $this->html_safe(ucwords($input['imap_connect_status'])).'</span> in '.round($input['imap_connect_time'],3);
            $input['imap_detail_display'] = '';
        }
        else {
            $input['imap_status_display'] = '<span class="down">Down</span>';
            $input['imap_detail_display'] = '';
        }
        return $input;
    }
}

class Hm_Output_filter_imap_folders extends Hm_Output_Module {
    protected function output($input, $format) {
        $res = '';
        if (isset($input['imap_folders'])) {
            foreach ($input['imap_folders'] as $id => $folder) {
                $res .= '<li class="imap_'.intval($id).'_"><a href="#" onclick="return expand_imap_folders(\'imap_'.intval($id).'_\')"><img alt="" class="account_icon" alt="Toggle folder" src="'.Hm_Image_Sources::$folder.'" width="16" height="16" /> '.
                    $this->html_safe($folder).'</a></li>';
            }
        }
        Hm_Page_Cache::concat('email_folders', $res);
        return '';
    }
}

class Hm_Output_filter_imap_search extends Hm_Output_Module {
    protected function output($input, $format) {
        if (isset($input['imap_search_results'])) {
            $style = isset($input['news_list_style']) ? 'news' : 'email';
            if ($input['is_mobile']) {
                $style = 'news';
            }
            $res = format_imap_message_list($input['imap_search_results'], $this, 'search', $style);
            $input['formatted_search_results'] = $res;
            unset($input['imap_search_results']);
        }
        elseif (!isset($input['formatted_search_results'])) {
            $input['formatted_search_results'] = array();
        }
        return $input;
    }
}

class Hm_Output_filter_flagged_data extends Hm_Output_Module {
    protected function output($input, $format) {
        if (isset($input['imap_flagged_data'])) {
            $style = isset($input['news_list_style']) ? 'news' : 'email';
            if ($input['is_mobile']) {
                $style = 'news';
            }
            $res = format_imap_message_list($input['imap_flagged_data'], $this, 'flagged', $style);
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
            $style = isset($input['news_list_style']) ? 'news' : 'email';
            if ($input['is_mobile']) {
                $style = 'news';
            }
            $res = format_imap_message_list($input['imap_unread_data'], $this, 'unread', $style);
            $input['formatted_unread_data'] = $res;
            unset($input['imap_unread_data']);
        }
        elseif (!isset($input['formatted_unread_data'])) {
            $input['formatted_unread_data'] = array();
        }
        return $input;
    }
}

class Hm_Output_filter_combined_inbox extends Hm_Output_Module {
    protected function output($input, $format) {
        if (isset($input['imap_combined_inbox_data']) && !empty($input['imap_combined_inbox_data'])) {
            $style = isset($input['news_list_style']) ? 'news' : 'email';
            if ($input['is_mobile']) {
                $style = 'news';
            }
            $res = format_imap_message_list($input['imap_combined_inbox_data'], $this, 'combined_inbox', $style);
            $input['formatted_combined_inbox'] = $res;
            unset($input['imap_combined_inbox_data']);
        }
        else {
            $input['formatted_combined_inbox'] = array();
        }
        return $input;
    }
}

class Hm_Output_filter_folder_page extends Hm_Output_Module {
    protected function output($input, $format) {
        $res = array();
        if (isset($input['imap_mailbox_page']) && !empty($input['imap_mailbox_page'])) {
            $style = isset($input['news_list_style']) ? 'news' : 'email';
            if ($input['is_mobile']) {
                $style = 'news';
            }
            $res = format_imap_message_list($input['imap_mailbox_page'], $this, false, $style);
            $input['formatted_mailbox_page'] = $res;
            Hm_Page_Cache::add('formatted_mailbox_page_'.$input['imap_mailbox_page_path'].'_'.$input['list_page'], $res);
            $input['page_links'] = build_page_links($input['imap_folder_detail'], $input['imap_mailbox_page_path']);
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
            $results .= ' <img class="folder_icon" src="'.Hm_Image_Sources::$folder.'" alt="" width="16" height="16" />';
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

function format_imap_message_list($msg_list, $output_module, $parent_list=false, $style='email') {
    $res = array();
    foreach($msg_list as $msg) {
        if (!$parent_list) {
            $parent_value = sprintf('imap_%d_%s', $msg['server_id'], $msg['folder']);
        }
        else {
            $parent_value = $parent_list;
        }
        if ($msg['server_name'] == 'Default-Auth-Server') {
            $msg['server_name'] = 'Default';
        }
        $id = sprintf("imap_%s_%s_%s", $msg['server_id'], $msg['uid'], $msg['folder']);
        if (!trim($msg['subject'])) {
            $msg['subject'] = '[No Subject]';
        }
        $subject = $msg['subject'];
        $from = preg_replace("/(\<.+\>)/U", '', $msg['from']);
        $from = str_replace('"', '', $from);
        $timestamp = strtotime($msg['internal_date']);
        $date = human_readable_interval($msg['internal_date']);
        $flags = array();
        if (!stristr($msg['flags'], 'seen')) {
           $flags[] = 'unseen';
        }
        if (stristr($msg['flags'], 'deleted')) {
            $flags[] = 'deleted';
        }
        if (stristr($msg['flags'], 'flagged')) {
            $flags[] = 'flagged';
        }
        $url = '?page=message&uid='.$msg['uid'].'&list_path='.sprintf('imap_%d_%s', $msg['server_id'], $msg['folder']).'&list_parent='.$parent_value;
        $res[$id] = message_list_row($subject, $date, $timestamp, $from, $msg['server_name'], $id, $flags, $style, $url, $output_module);
    }
    return $res;
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

function sort_by_internal_date($a, $b) {
    if ($a['internal_date'] == $b['internal_date']) return 0;
    return (strtotime($a['internal_date']) < strtotime($b['internal_date']))? -1 : 1;
}

function merge_imap_search_results($ids, $search_type, $session, $folders = array('INBOX'), $limit=0, $terms=array()) {
    $msg_list = array();
    foreach($ids as $id) {
        $id = intval($id);
        $cache = Hm_IMAP_List::get_cache($session, $id);
        $imap = Hm_IMAP_List::connect($id, $cache);
        if (is_object($imap) && $imap->get_state() == 'authenticated') {
            $server_details = Hm_IMAP_List::dump($id);
            foreach ($folders as $folder) {
                if ($imap->select_mailbox($folder)) {
                    if (!empty($terms)) {
                        $msgs = $imap->search($search_type, false, $terms);
                    }
                    else {
                        $msgs = $imap->search($search_type);
                    }
                    if ($msgs) {
                        if ($limit) {
                            rsort($msgs);
                            $msgs = array_slice($msgs, 0, $limit);
                        }
                        foreach ($imap->get_message_list($msgs) as $msg) {
                            if (stristr($msg['flags'], 'deleted')) {
                                continue;
                            }
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
    usort($msg_list, 'sort_by_internal_date');
    return $msg_list;
}

?>
