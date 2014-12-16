<?php

if (!defined('DEBUG_MODE')) { die(); }

require APP_PATH.'modules/imap/hm-imap.php';

class Hm_Handler_imap_process_reply_fields extends Hm_Handler_Module {
    public function process() {
        if (array_key_exists('reply_source', $this->request->get) && preg_match("/^imap_(\d+)_(.+)$/", $this->request->get['reply_source'])) {
            if (array_key_exists('reply_uid', $this->request->get)) {
                $this->out('imap_reply_source', $this->request->get['reply_source']);
                $this->out('imap_reply_uid', $this->request->get['reply_uid']);
            }
        }
    }
}

class Hm_Handler_imap_message_list_type extends Hm_Handler_Module {
    public function process() {
        if (array_key_exists('list_path', $this->request->get)) {
            $path = $this->request->get['list_path'];
            if (preg_match("/^imap_\d+_.+$/", $path)) {
                $this->out('list_meta', false, false);
                $this->out('list_path', $path);
                $parts = explode('_', $path, 3);
                $details = Hm_IMAP_List::dump(intval($parts[1]));
                if (!empty($details)) {
                    $this->out('mailbox_list_title', array('IMAP', $details['name'], $parts[2]));
                }
            }
        }
    }
}

class Hm_Handler_imap_folder_expand extends Hm_Handler_Module {
    public function process() {

        list($success, $form) = $this->process_form(array('imap_server_id'));
        if ($success) {
            $folder = '';
            if (isset($this->request->post['folder'])) {
                $folder = $this->request->post['folder'];
            }
            $path = sprintf("imap_%d_%s", $form['imap_server_id'], $folder);
            $page_cache =  Hm_Page_Cache::get('imap_folders_'.$path);
            if ($page_cache) {
                $this->out('imap_expanded_folder_path', $path);
                $this->out('imap_expanded_folder_formatted', $page_cache);
                return;
            }
            $details = Hm_IMAP_List::dump($form['imap_server_id']);
            $cache = Hm_IMAP_List::get_cache($this->session, $form['imap_server_id']);
            $imap = Hm_IMAP_List::connect($form['imap_server_id'], $cache);
            if (is_object($imap) && $imap->get_state() == 'authenticated') {
                $msgs = $imap->get_folder_list_by_level($folder);
                if (isset($msgs[$folder])) {
                    unset($msgs[$folder]);
                }
                $this->out('imap_expanded_folder_data', $msgs);
                $this->out('imap_expanded_folder_id', $form['imap_server_id']);
                $this->out('imap_expanded_folder_path', $path);
            }
        }
    }
}

class Hm_Handler_imap_folder_page extends Hm_Handler_Module {
    public function process() {

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
                $this->out('imap_mailbox_page_path', $path);
                foreach ($imap->get_mailbox_page($form['folder'], $sort, $rev, $filter, $offset, $limit) as $msg) {
                    $msg['server_id'] = $form['imap_server_id'];
                    $msg['server_name'] = $details['name'];
                    $msg['folder'] = $form['folder'];
                    $msgs[] = $msg;
                }
                $this->out('imap_folder_detail', array_merge($imap->selected_mailbox, array('offset' => $offset, 'limit' => $limit)));
            }
            $this->out('imap_mailbox_page', $msgs);
            $this->out('list_page', $list_page);
            $this->out('imap_server_id', $form['imap_server_id']);
        }
    }
}

class Hm_Handler_load_imap_folders extends Hm_Handler_Module {
    public function process() {
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
        $this->out('imap_folders', $folders);
    }
}

class Hm_Handler_flag_imap_message extends Hm_Handler_Module {
    public function process() {
        list($success, $form) = $this->process_form(array('imap_flag_state', 'imap_msg_uid', 'imap_server_id', 'folder'));
        if ($success) {
            $flag_result = false;
            $cache = Hm_IMAP_List::get_cache($this->session, $form['imap_server_id']);
            $imap = Hm_IMAP_List::connect($form['imap_server_id'], $cache);
            if (is_object($imap) && $imap->get_state() == 'authenticated') {
                if ($imap->select_mailbox($form['folder'])) {
                    if ($form['imap_flag_state'] == 'flagged') {
                        $cmd = 'UNFLAG';
                    }
                    else {
                        $cmd = 'FLAG';
                    }
                    if ($imap->message_action($cmd, array($form['imap_msg_uid']))) {
                        $flag_result = true;
                    }
                }
            }
            if (!$flag_result) {
                Hm_Msgs::add('ERRAn error occured trying to flag this message');
            }
        }
    }
}

class Hm_Handler_imap_message_action extends Hm_Handler_Module {
    public function process() {
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
    }
}

class Hm_Handler_imap_search extends Hm_Handler_Module {
    public function process() {
        list($success, $form) = $this->process_form(array('imap_server_ids'));
        if ($success) {
            $terms = $this->session->get('search_terms', false);
            $since = $this->session->get('search_since', DEFAULT_SINCE);
            $fld = $this->session->get('search_fld', 'TEXT');
            $ids = explode(',', $form['imap_server_ids']);
            $date = process_since_argument($since);
            $msg_list = merge_imap_search_results($ids, 'ALL', $this->session, array('INBOX'), MAX_PER_SOURCE, array('SINCE' => $date, $fld => $terms));
            $this->out('imap_search_results', $msg_list);
            $this->out('imap_server_ids', $form['imap_server_ids']);
        }
    }
}

class Hm_Handler_imap_combined_inbox extends Hm_Handler_Module {
    public function process() {
        list($success, $form) = $this->process_form(array('imap_server_ids'));
        if ($success) {
            if (array_key_exists('list_path', $this->request->get) && $this->request->get['list_path'] == 'email') {
                /* TODO: add settings for these */
                $limit = DEFAULT_PER_SOURCE;
                $date = process_since_argument(DEFAULT_SINCE);
            }
            else {
                $limit = $this->user_config->get('all_per_source_setting', DEFAULT_PER_SOURCE);
                $date = process_since_argument($this->user_config->get('all_since_setting', DEFAULT_SINCE));
            }
            $ids = explode(',', $form['imap_server_ids']);
            $msg_list = merge_imap_search_results($ids, 'ALL', $this->session, array('INBOX'), $limit, array('SINCE' => $date));
            $this->out('imap_combined_inbox_data', $msg_list);
            $this->out('imap_server_ids', $form['imap_server_ids']);
        }
    }
}

class Hm_Handler_imap_flagged extends Hm_Handler_Module {
    public function process() {
        list($success, $form) = $this->process_form(array('imap_server_ids'));
        if ($success) {
            $limit = $this->user_config->get('flagged_per_source_setting', DEFAULT_PER_SOURCE);
            $ids = explode(',', $form['imap_server_ids']);
            $date = process_since_argument($this->user_config->get('flagged_since_setting', DEFAULT_SINCE));
            $msg_list = merge_imap_search_results($ids, 'FLAGGED', $this->session, array('INBOX'), $limit, array('SINCE' => $date));
            $this->out('imap_flagged_data', $msg_list);
            $this->out('imap_server_ids', $form['imap_server_ids']);
        }
    }
}

class Hm_Handler_imap_status extends Hm_Handler_Module {
    public function process() {
        list($success, $form) = $this->process_form(array('imap_server_ids'));
        if ($success) {
            $ids = explode(',', $form['imap_server_ids']);
            foreach ($ids as $id) {
                $cache = Hm_IMAP_List::get_cache($this->session, $id);
                $start_time = microtime(true);
                $imap = Hm_IMAP_List::connect($id, $cache);
                $this->out('imap_connect_time', microtime(true) - $start_time);
                if ($imap) {
                    $this->out('imap_connect_status', $imap->get_state());
                    $this->out('imap_status_server_id', $id);
                }
                else {
                    $this->out('imap_connect_status', 'disconnected');
                    $this->out('imap_status_server_id', $id);
                }
            }
        }
    }
}

class Hm_Handler_imap_unread extends Hm_Handler_Module {
    public function process() {
        list($success, $form) = $this->process_form(array('imap_server_ids'));
        if ($success) {
            $limit = $this->user_config->get('unread_per_source_setting', DEFAULT_PER_SOURCE);
            $date = process_since_argument($this->user_config->get('unread_since_setting', DEFAULT_SINCE));
            $ids = explode(',', $form['imap_server_ids']);
            $msg_list = array();
            $msg_list = merge_imap_search_results($ids, 'UNSEEN', $this->session, array('INBOX'), $limit, array('SINCE' => $date));
            $this->out('imap_unread_data', $msg_list);
            $this->out('imap_server_ids', $form['imap_server_ids']);
        }
    }
}

class Hm_Handler_process_add_imap_server extends Hm_Handler_Module {
    public function process() {
        if (isset($this->request->post['submit_imap_server'])) {
            list($success, $form) = $this->process_form(array('new_imap_name', 'new_imap_address', 'new_imap_port'));
            if (!$success) {
                $this->out('old_form', $form);
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
    }
}

class Hm_Handler_save_imap_cache extends Hm_Handler_Module {
    public function process() {
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
    }
}

class Hm_Handler_save_imap_servers extends Hm_Handler_Module {
    public function process() {
        $servers = Hm_IMAP_List::dump(false, true);
        $this->user_config->set('imap_servers', $servers);
        Hm_IMAP_List::clean_up();
    }
}

class Hm_Handler_load_imap_servers_for_search extends Hm_Handler_Module {
    public function process() {
        foreach (Hm_IMAP_List::dump() as $index => $vals) {
            $this->append('data_sources', array('callback' => 'imap_search_page_content', 'type' => 'imap', 'name' => $vals['name'], 'id' => $index));
        }
    }
}

class Hm_Handler_load_imap_servers_for_message_list extends Hm_Handler_Module {
    public function process() {
        if (array_key_exists('list_path', $this->request->get)) {
            $path = $this->request->get['list_path'];
        }
        else {
            $path = '';
        }
        switch ($path) {
            case 'unread':
                $callback = 'imap_combined_unread_content';
                break;
            case 'flagged':
                $callback = 'imap_combined_flagged_content';
                break;
            case 'combined_inbox':
                $callback = 'imap_combined_inbox_content';
                break;
            case 'email':
                $callback = 'imap_all_mail_content';
                break;
            default:
                $callback = false;
                break;
        }
        if ($callback) {
            foreach (Hm_IMAP_List::dump() as $index => $vals) {
                $this->append('data_sources', array('callback' => $callback, 'type' => 'imap', 'name' => $vals['name'], 'id' => $index));
            }
        }
    }
}

class Hm_Handler_load_imap_servers_from_config extends Hm_Handler_Module {
    public function process() {
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
    }
}

class Hm_Handler_add_imap_servers_to_page_data extends Hm_Handler_Module {
    public function process() {
        $servers = Hm_IMAP_List::dump();
        if (!empty($servers)) {
            $this->out('imap_servers', $servers);
            $this->append('folder_sources', 'email_folders');
        }
    }
}

class Hm_Handler_imap_bust_cache extends Hm_Handler_Module {
    public function process() {
        $this->session->set('imap_cache', array());
    }
}

class Hm_Handler_imap_connect extends Hm_Handler_Module {
    public function process() {
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
                $this->out('old_form', $form);
            }
        }
    }
}

class Hm_Handler_imap_forget extends Hm_Handler_Module {
    public function process() {
        $just_forgot_credentials = false;
        if (isset($this->request->post['imap_forget'])) {
            list($success, $form) = $this->process_form(array('imap_server_id'));
            if ($success) {
                Hm_IMAP_List::forget_credentials($form['imap_server_id']);
                $just_forgot_credentials = true;
                Hm_Msgs::add('Server credentials forgotten');
                $this->session->record_unsaved('IMAP server credentials forgotten');
                Hm_Page_Cache::flush($this->session);
            }
            else {
                $this->out('old_form', $form);
            }
        }
        $this->out('just_forgot_credentials', $just_forgot_credentials);
    }
}

class Hm_Handler_imap_save extends Hm_Handler_Module {
    public function process() {
        $just_saved_credentials = false;
        if (isset($this->request->post['imap_save'])) {
            list($success, $form) = $this->process_form(array('imap_user', 'imap_pass', 'imap_server_id'));
            if (!$success) {
                Hm_Msgs::add('ERRUsername and Password are required to save a connection');
            }
            else {
                $cache = Hm_IMAP_List::get_cache($this->session, $form['imap_server_id']);
                $imap = Hm_IMAP_List::connect($form['imap_server_id'], $cache, $form['imap_user'], $form['imap_pass'], true);
                if ($imap->get_state() == 'authenticated') {
                    $just_saved_credentials = true;
                    Hm_Msgs::add("Server saved");
                    $this->session->record_unsaved('IMAP server saved');
                    Hm_Page_Cache::flush($this->session);
                }
                else {
                    Hm_Msgs::add("ERRUnable to save this server, are the username and password correct?");
                }
            }
        }
        $this->out('just_saved_credentials', $just_saved_credentials);
    }
}

class Hm_Handler_imap_message_content extends Hm_Handler_Module {
    public function process() {
        list($success, $form) = $this->process_form(array('imap_server_id', 'imap_msg_uid', 'folder'));
        if ($success) {
            $this->out('msg_text_uid', $form['imap_msg_uid']);
            $this->out('msg_server_id', $form['imap_server_id']);
            $this->out('msg_folder', $form['folder']);
            $part = false;
            if (isset($this->request->post['imap_msg_part']) && preg_match("/[0-9\.]+/", $this->request->post['imap_msg_part'])) {
                $part = $this->request->post['imap_msg_part'];
            }
            if (array_key_exists('reply_format', $this->request->post) && $this->request->post['reply_format']) {
                $this->out('reply_format', true);
            }
            $cache = Hm_IMAP_List::get_cache($this->session, $form['imap_server_id']);
            $imap = Hm_IMAP_List::connect($form['imap_server_id'], $cache);
            if ($imap) {
                if ($imap->select_mailbox($form['folder'])) {
                    $msg_struct = $imap->get_message_structure($form['imap_msg_uid']);
                    $this->out('msg_struct', $msg_struct);
                    if ($part !== false) {
                        if ($part == 0) {
                            $max = 50000;
                        }
                        else {
                            $max = false;
                        }
                        $struct = $imap->search_bodystructure( $msg_struct, array('imap_part_number' => $part));
                        $msg_struct_current = array_shift($struct);
                        $msg_text = $imap->get_message_content($form['imap_msg_uid'], $part, $max, $msg_struct_current);
                    }
                    else {
                        list($part, $msg_text) = $imap->get_first_message_part($form['imap_msg_uid'], 'text', false, $msg_struct);
                        $struct = $imap->search_bodystructure( $msg_struct, array('imap_part_number' => $part));
                        $msg_struct_current = array_shift($struct);
                    }
                    $this->out('msg_headers', $imap->get_message_headers($form['imap_msg_uid']));
                    $this->out('imap_msg_part', $part);
                    $this->out('msg_struct_current', $msg_struct_current);
                    $this->out('msg_text', $msg_text);
                }
            }
        }
    }
}

class Hm_Handler_imap_delete extends Hm_Handler_Module {
    public function process() {
        if (isset($this->request->post['imap_delete'])) {
            list($success, $form) = $this->process_form(array('imap_server_id'));
            if ($success) {
                $res = Hm_IMAP_List::del($form['imap_server_id']);
                if ($res) {
                    $this->out('deleted_server_id', $form['imap_server_id']);
                    Hm_Msgs::add('Server deleted');
                    $this->session->record_unsaved('IMAP server deleted');
                    Hm_Page_Cache::flush($this->session);
                }
            }
            else {
                $this->out('old_form', $form);
            }
        }
    }
}

class Hm_Output_filter_message_body extends Hm_Output_Module {
    protected function output($format) {
        $txt = '<div class="msg_text_inner">';
        if ($this->get('msg_text')) {
            $struct = $this->get('msg_struct_current', array());
            if (isset($struct['subtype']) && $struct['subtype'] == 'html') {
                $txt .= format_msg_html($this->get('msg_text'));
            }
            elseif (isset($struct['type']) && $struct['type'] == 'image') {
                $txt .= format_msg_image($this->get('msg_text'), $struct['subtype']);
            }
            else {
                $txt .= format_msg_text($this->get('msg_text'), $this);
            }
            $txt .= '</div>';
            $this->out('msg_text', $txt);
        }
    }
}

class Hm_Output_filter_message_struct extends Hm_Output_Module {
    protected function output($format) {
        if ($this->get('msg_struct')) {
            $res = '<table class="msg_parts">';
            $part = $this->get('imap_msg_part', 1);
            $res .=  format_msg_part_section($this->get('msg_struct'), $this, $part);
            $res .= '</table>';
            $this->out('msg_parts', $res);
        }
    }
}

class Hm_Output_filter_message_headers extends Hm_Output_Module {
    protected function output($format) {
        if ($this->get('msg_headers')) {
            $txt = '';
            $from = '';
            $small_headers = array('subject', 'date', 'from');
            $headers = $this->get('msg_headers', array());
            $txt .= '<table class="msg_headers"><col class="header_name_col"><col class="header_val_col"></colgroup>';
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
                            $txt .= '<tr class="header_'.$fld.'"><th>'.$this->trans($name).'</th><td>'.$this->html_safe($value).'</td></tr>';
                        }
                        break;
                    }
                }
            }
            foreach ($headers as $name => $value) {
                if (!in_array(strtolower($name), $small_headers)) {
                    $txt .= '<tr style="display: none;" class="long_header"><th>'.$this->trans($name).'</th><td>'.$this->html_safe($value).'</td></tr>';
                }
            }
            $txt .= '<tr><th colspan="2" class="header_links">'.
                '<a href="#" class="header_toggle">'.$this->trans('all').'</a>'.
                '<a class="header_toggle" style="display: none;" href="#">'.$this->trans('small').'</a>'.
                ' | <a href="?page=compose&amp;reply_uid='.$this->html_safe($this->get('msg_text_uid', 0)).
                '&amp;reply_source='.$this->html_safe(sprintf('imap_%d_%s', $this->get('msg_server_id'), $this->get('msg_folder'))).'">reply</a>'.
                ' | <a href="?page=compose">'.$this->trans('forward').'</a>'.
                ' | <a href="?page=compose">'.$this->trans('attach').'</a>'.
                ' | <a class="msg_part_link" data-message-part="0" href="#">'.$this->trans('raw').'</a>';
            if (isset($headers['Flags']) && stristr($headers['Flags'], 'flagged')) {
                $txt .= ' | <a style="display: none;" id="flag_msg" data-state="unflagged" href="#">'.$this->trans('flag').'</a>';
                $txt .= '<a id="unflag_msg" data-state="flagged" href="#">'.$this->trans('unflag').'</a>';
            }
            else {
                $txt .= ' | <a id="flag_msg" data-state="unflagged" href="#">'.$this->trans('flag').'</a>';
                $txt .= '<a style="display: none;" id="unflag_msg" data-state="flagged" href="#">'.$this->trans('unflag').'</a>';
            }
            $txt .= '</th></tr></table>';

            $this->out('msg_headers', $txt);
        }
    }
}

class Hm_Output_display_configured_imap_servers extends Hm_Output_Module {
    protected function output($format) {
        $res = '';
        foreach ($this->get('imap_servers', array()) as $index => $vals) {

            $no_edit = false;

            if (isset($vals['user'])) {
                $disabled = 'disabled="disabled"';
                $user_pc = $vals['user'];
                $pass_pc = $this->trans('[saved]');
            }
            else {
                $user_pc = '';
                $pass_pc = $this->trans('Password');
                $disabled = '';
            }
            if ($vals['name'] == 'Default-Auth-Server') {
                $vals['name'] = $this->trans('Default');
                $no_edit = true;
            }
            $res .= '<div class="configured_server">';
            $res .= sprintf('<div class="server_title">%s</div><div class="server_subtitle">%s/%d %s</div>',
                $this->html_safe($vals['name']), $this->html_safe($vals['server']), $this->html_safe($vals['port']),
                $vals['tls'] ? 'TLS' : '' );
            $res .= 
                '<form class="imap_connect" method="POST">'.
                '<input type="hidden" name="hm_nonce" value="'.$this->html_safe(Hm_Nonce::generate()).'" />'.
                '<input type="hidden" name="imap_server_id" value="'.$this->html_safe($index).'" /><span> '.
                '<label class="screen_reader" for="imap_user_'.$index.'">'.$this->trans('IMAP username').'</label>'.
                '<input '.$disabled.' id="imap_user_'.$index.'" class="credentials" placeholder="'.$this->trans('Username').'" type="text" name="imap_user" value="'.$user_pc.'"></span>'.
                '<span><label class="screen_reader" for="imap_pass_'.$index.'">'.$this->trans('IMAP password').'</label>'.
                '<input '.$disabled.' id="imap_pass_'.$index.'" class="credentials imap_password" placeholder="'.$pass_pc.'" type="password" name="imap_pass"></span>';
            if (!$no_edit) {
                $res .= '<input type="submit" value="'.$this->trans('Test').'" class="test_imap_connect" />';
                if (!isset($vals['user']) || !$vals['user']) {
                    $res .= '<input type="submit" value="'.$this->trans('Delete').'" class="imap_delete" />';
                    $res .= '<input type="submit" value="'.$this->trans('Save').'" class="save_imap_connection" />';
                }
                else {
                    $res .= '<input type="submit" value="'.$this->trans('Delete').'" class="imap_delete" />';
                    $res .= '<input type="submit" value="'.$this->trans('Forget').'" class="forget_imap_connection" />';
                }
                $res .= '<input type="hidden" value="ajax_imap_debug" name="hm_ajax_hook" />';
            }
            $res .= '</form></div>';
        }
        $res .= '<br class="clear_float" /></div></div>';
        return $res;
    }
}

class Hm_Output_add_imap_server_dialog extends Hm_Output_Module {
    protected function output($format) {
        $count = count($this->get('imap_servers', array()));
        $count = sprintf($this->trans('%d configured'), $count);
        return '<div class="imap_server_setup"><div data-target=".imap_section" class="server_section">'.
            '<img alt="" src="'.Hm_Image_Sources::$env_closed.'" width="16" height="16" />'.
            ' '.$this->trans('IMAP Servers').'<div class="server_count">'.$count.'</div></div><div class="imap_section"><form class="add_server" method="POST">'.
            '<input type="hidden" name="hm_nonce" value="'.$this->html_safe(Hm_Nonce::generate()).'" />'.
            '<div class="subtitle">'.$this->trans('Add an IMAP Server').'</div><table>'.
            '<tr><td colspan="2"><label class="screen_reader" for="new_imap_name">'.$this->trans('Account name').'</label>'.
            '<input id="new_imap_name" required type="text" name="new_imap_name" class="txt_fld" value="" placeholder="'.$this->trans('Account name').'" /></td></tr>'.
            '<tr><td colspan="2"><label class="screen_reader" for="new_imap_address">'.$this->trans('Server address').'</label>'.
            '<input required type="text" id="new_imap_address" name="new_imap_address" class="txt_fld" placeholder="'.$this->trans('IMAP server address').'" value=""/></td></tr>'.
            '<tr><td colspan="2"><label class="screen_reader" for="new_imap_port">'.$this->trans('IMAP port').'</label>'.
            '<input required type="number" id="new_imap_port" name="new_imap_port" class="port_fld" value="" placeholder="'.$this->trans('Port').'"></td></tr>'.
            '<tr><td><input type="checkbox" name="tls" value="1" id="imap_tls" checked="checked" /> <label for="imap_tls">'.$this->trans('Use TLS').'</label></td>'.
            '<td><input type="submit" value="'.$this->trans('Add').'" name="submit_imap_server" /></td></tr>'.
            '</table></form>';
    }
}

class Hm_Output_display_imap_status extends Hm_Output_Module {
    protected function output($format) {
        $res = '';
        foreach ($this->get('imap_servers', array()) as $index => $vals) {
            if ($vals['name'] == 'Default-Auth-Server') {
                $vals['name'] = $this->trans('Default');
            }
            $res .= '<tr><td>IMAP</td><td>'.$vals['name'].'</td><td class="imap_status_'.$index.'"></td>'.
                '<td class="imap_detail_'.$index.'"></td></tr>';
        }
        return $res;
    }
}

class Hm_Output_imap_reply_details extends Hm_Output_Module {
    protected function output($format) {
        $res = '';
        if ($this->get('imap_reply_source')) {
            $res .= '<input type="hidden" class="imap_reply_source" value="'.$this->html_safe($this->get('imap_reply_source')).'" />';
        }
        if ($this->get('imap_reply_uid')) {
            $res .= '<input type="hidden" class="imap_reply_uid" value="'.$this->html_safe($this->get('imap_reply_uid')).'" />';
        }
        return $res;
    }
}

class Hm_Output_imap_server_ids extends Hm_Output_Module {
    protected function output($format) {
        return '<input type="hidden" class="imap_server_ids" value="'.$this->html_safe(implode(',', array_keys($this->get('imap_servers', array ())))).'" />';
    }
}

class Hm_Output_filter_expanded_folder_data extends Hm_Output_Module {
    protected function output($format) {
        $res = '';
        $folder_data = $this->get('imap_expanded_folder_data', array());
        if (!empty($folder_data)) {
            ksort($folder_data);
            $res .= format_imap_folder_section($folder_data, $this->get('imap_expanded_folder_id'), $this);
            $this->out('imap_expanded_folder_formatted', $res);
            Hm_Page_Cache::add('imap_folders_'.$this->get('imap_expanded_folder_path'), $res);
        }
    }
}

class Hm_Output_filter_imap_status_data extends Hm_Output_Module {
    protected function output($format) {
        $res = '';
        if ($this->get('imap_connect_status') != 'disconnected') {
            $res .= '<span class="online">'.$this->trans(ucwords($this->get('imap_connect_status'))).
                '</span> in '.round($this->get('imap_connect_time', 0.0), 3);
        }
        else {
            $res .= '<span class="down">'.$this->trans('Down').'</span>';
        }
        $this->out('imap_status_display', $res);
    }
}

class Hm_Output_filter_imap_folders extends Hm_Output_Module {
    protected function output($format) {
        $res = '';
        if ($this->get('imap_folders')) {
            foreach ($this->get('imap_folders', array()) as $id => $folder) {
                $res .= '<li class="imap_'.intval($id).'_"><a href="#" class="imap_folder_link" data-target="imap_'.intval($id).'_">'.
                    '<img class="account_icon" alt="'.$this->trans('Toggle folder').'" src="'.Hm_Image_Sources::$folder.'" width="16" height="16" /> '.
                    $this->html_safe($folder).'</a></li>';
            }
        }
        Hm_Page_Cache::concat('email_folders', $res);
        return '';
    }
}

class Hm_Output_filter_imap_search extends Hm_Output_Module {
    protected function output($format) {
        if ($this->get('imap_search_results')) {
            prepare_imap_message_list($this->get('imap_search_results'), $this, 'search');
        }
        elseif (!$this->get('formatted_message_list')) {
            $this->out('formatted_message_list', array());
        }
    }
}

class Hm_Output_filter_flagged_data extends Hm_Output_Module {
    protected function output($format) {
        if ($this->get('imap_flagged_data')) {
            prepare_imap_message_list($this->get('imap_flagged_data'), $this, 'flagged');
        }
        elseif (!$this->get('formatted_message_list')) {
            $this->out('formatted_message_list', array());
        }
    }
}

class Hm_Output_filter_unread_data extends Hm_Output_Module {
    protected function output($format) {
        if ($this->get('imap_unread_data')) {
            prepare_imap_message_list($this->get('imap_unread_data'), $this, 'unread');
        }
        elseif (!$this->get('formatted_message_list')) {
            $this->out('formatted_message_list', array());
        }
    }
}

class Hm_Output_filter_all_email extends Hm_Output_Module {
    protected function output($format) {
        if ($this->get('imap_combined_inbox_data')) {
            prepare_imap_message_list($this->get('imap_combined_inbox_data'), $this, 'email');
        }
        else {
            $this->out('formatted_message_list', array());
        }
    }
}

class Hm_Output_filter_combined_inbox extends Hm_Output_Module {
    protected function output($format) {
        if ($this->get('imap_combined_inbox_data')) {
            prepare_imap_message_list($this->get('imap_combined_inbox_data'), $this, 'combined_inbox');
        }
        else {
            $this->out('formatted_message_list', array());
        }
    }
}

class Hm_Output_filter_folder_page extends Hm_Output_Module {
    protected function output($format) {
        $res = array();
        if ($this->get('imap_mailbox_page')) {
            prepare_imap_message_list($this->get('imap_mailbox_page'), $this, false);
            $this->out('page_links', build_page_links($this->get('imap_folder_detail'), $this->get('imap_mailbox_page_path')));
        }
        elseif (!$this->get('formatted_message_list')) {
            $this->out('formatted_message_list', array());
        }
    }
}

class Hm_Output_filter_reply_content extends Hm_Output_Module {
    protected function output($format) {
        $reply_subject = 'Re: [No Subject]';
        $reply_to = '';
        $reply_body = '';
        $lead_in = '';
        if ($this->get('reply_format')) {
            if (is_array($this->get('msg_headers'))) {
                $hdrs = $this->get('msg_headers');
                if (array_key_exists('Subject', $hdrs)) {
                    $reply_subject = sprintf("Re: %s", $hdrs['Subject']);
                }
                if (array_key_exists('From', $hdrs)) {
                    $reply_to = $hdrs['From'];
                }
                elseif (array_key_exists('Sender', $hdrs)) {
                    $reply_to = $hdrs['Sender'];
                }
                elseif (array_key_exists('Return-path', $hdrs)) {
                    $reply_to = $hdrs['Return-path'];
                }
                if (array_key_exists('Date', $hdrs)) {
                    if ($reply_to) {
                        $lead_in = sprintf($this->trans('On %s %s said')."\n", $hdrs['Date'], $reply_to);
                    }
                    else {
                        $lead_in = sprintf($this->trans('On %s, somebody said')."\n", $hdrs['Date']);
                    }
                }
            }
            if ($this->get('msg_text')) {
                $reply_body = $lead_in.format_reply_text($this->get('msg_text'));
            }
            $this->out('reply_to', $reply_to);
            $this->out('reply_body', $reply_body);
            $this->out('reply_subject', $reply_subject);
        }
    }
}

function prepare_imap_message_list($msgs, $mod, $type) {
    $style = $mod->get('news_list_style') ? 'news' : 'email';
    if ($mod->get('is_mobile')) {
        $style = 'news';
    }
    $res = format_imap_message_list($msgs, $mod, $type, $style);
    $mod->out('formatted_message_list', $res);
}

function format_imap_folder_section($folders, $id, $output_mod) {
    $results = '<ul class="inner_list">';
    foreach ($folders as $folder_name => $folder) {
        $results .= '<li class="imap_'.$id.'_'.$output_mod->html_safe(str_replace(' ', '-', $folder_name)).'">';
        if ($folder['children']) {
            $results .= '<a href="#" class="imap_folder_link expand_link" data-target="imap_'.intval($id).'_'.$output_mod->html_safe($folder_name).'">+</a>';
        }
        else {
            $results .= ' <img class="folder_icon" src="'.Hm_Image_Sources::$folder.'" alt="" width="16" height="16" />';
        }
        if (!$folder['noselect']) {
            $results .= '<a data-id="imap_'.intval($id).'_'.$output_mod->html_safe($folder_name).
                '" href="?page=message_list&amp;list_path='.
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
        if (!trim($from) && $style == 'email') {
            $from = '[No From]';
        }
        $timestamp = strtotime($msg['internal_date']);
        $date = translate_time_str(human_readable_interval($msg['internal_date']), $output_module);
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
    $prev = '<a class="disabled_link"><img src="'.Hm_Image_Sources::$caret_left.'" alt="&larr;" /></a>';
    $next = '<a class="disabled_link"><img src="'.Hm_Image_Sources::$caret_right.'" alt="&rarr;" /></a>';

    if ($floor > 1 ) {
        $first = '<a href="?page=message_list&amp;list_path='.urlencode($path).'&amp;list_page=1">1</a> ... ';
    }
    if ($ceil < $max_pages) {
        $last = ' ... <a href="?page=message_list&amp;list_path='.urlencode($path).'&amp;list_page='.$max_pages.'">'.$max_pages.'</a>';
    }
    if ($current_page > 1) {
        $prev = '<a href="?page=message_list&amp;list_path='.urlencode($path).'&amp;list_page='.($current_page - 1).'"><img src="'.Hm_Image_Sources::$caret_left.'" alt="&larr;" /></a>';
    }
    if ($max_pages > 1 && $current_page < $max_pages) {
        $next = '<a href="?page=message_list&amp;list_path='.urlencode($path).'&amp;list_page='.($current_page + 1).'"><img src="'.Hm_Image_Sources::$caret_right.'" alt="&rarr;" /></a>';
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
    if (isset($vals['description']) && trim($vals['description']) && trim(strtolower($vals['description'])) != 'nil') {
        $desc = $vals['description'];
    }
    elseif (isset($vals['name']) && trim($vals['name']) && trim(strtolower($vals['name'])) != 'nil') {
        $desc = $vals['name'];
    }
    elseif (isset($vals['filename']) && trim($vals['filename']) && trim(strtolower($vals['filename'])) != 'nil') {
        $desc = $vals['filename'];
    }
    elseif (isset($vals['subject']) && trim($vals['subject']) && trim(strtolower($vals['subject'])) != 'nil') {
        $desc = $vals['subject'];
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
        $res .= '<a href="#" class="msg_part_link" data-message-part="'.$output_mod->html_safe($id).'">'.$output_mod->html_safe($vals['type']).
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
