<?php

/**
 * IMAP modules
 * @package modules
 * @subpackage imap
 */

if (!defined('DEBUG_MODE')) { die(); }

require APP_PATH.'modules/imap/hm-imap.php';

/**
 * Process a request to change a combined page source
 * @subpackage imap/handler
 */
class Hm_Handler_process_imap_source_update extends Hm_Handler_Module {
    /**
     * Add or remove an IMAP folder to the combined view
     */
    public function process() {
        list($success, $form) = $this->process_form(array('combined_source_state', 'list_path'));
        if ($success) {
            $sources = $this->user_config->get('custom_imap_sources');
            if ($form['combined_source_state'] == 1) {
                $sources[$form['list_path']] = 'add';
                Hm_Msgs::add('Folder added to combined pages');
                $this->session->record_unsaved('Added folder to combined pages');
            }
            else {
                if (array_key_exists($form['list_path'], $sources)) {
                    unset($sources[$form['list_path']]);
                }
                else {
                    $sources[$form['list_path']] = 'remove';
                }
                Hm_Msgs::add('Folder removed from combined pages');
                $this->session->record_unsaved('Removed folder from combined pages');
            }
            $this->user_config->set('custom_imap_sources', $sources);
            $this->session->set('user_data', $this->user_config->dump());
        }
    }
}

/**
 * Stream a message from IMAP to the browser
 * @subpackage imap/handler
 */
class Hm_Handler_imap_download_message extends Hm_Handler_Module {
    /**
     * Download a message from the IMAP server
     */
    public function process() {
        if (array_key_exists('imap_download_message', $this->request->get) && $this->request->get['imap_download_message']) {

            $server_id = NULL;
            $uid = NULL;
            $folder = NULL;
            $msg_id = NULL;

            if (array_key_exists('uid', $this->request->get) && is_numeric($this->request->get['uid'])) {
                $uid = $this->request->get['uid'];
            }
            if (array_key_exists('list_path', $this->request->get) && preg_match("/^imap_(\d+)_(.+)/", $this->request->get['list_path'], $matches)) {
                $server_id = $matches[1];
                $folder = $matches[2];
            }
            if (array_key_exists('imap_msg_part', $this->request->get) && preg_match("/^[0-9\.]+$/", $this->request->get['imap_msg_part'])) {
                $msg_id = preg_replace("/^0.{1}/", '', $this->request->get['imap_msg_part']);
            }
            if ($server_id !== NULL && $uid !== NULL && $folder !== NULL && $msg_id !== NULL) {
                $cache = Hm_IMAP_List::get_cache($this->session, $server_id);
                $imap = Hm_IMAP_List::connect($server_id, $cache);
                if ($imap) {
                    if ($imap->select_mailbox($folder)) {
                        $msg_struct = $imap->get_message_structure($uid);
                        $struct = $imap->search_bodystructure( $msg_struct, array('imap_part_number' => $msg_id));
                        if (!empty($struct)) {
                            $part_struct = array_shift($struct);
                            $encoding = false;
                            if (array_key_exists('encoding', $part_struct)) {
                                $encoding = trim(strtolower($part_struct['encoding']));
                            }
                            $stream_size = $imap->start_message_stream($uid, $msg_id);
                            if ($stream_size > 0) {
                                $name = get_imap_part_name($part_struct, $uid, $msg_id);
                                header('Content-Disposition: attachment; filename="'.$name.'"');
                                header('Content-Transfer-Encoding: binary');
                                header('Content-Length: '.$part_struct['size']);
                                ob_end_clean();
                                while($line = $imap->read_stream_line()) {
                                    if ($encoding == 'quoted-printable') {
                                        echo quoted_printable_decode($line);
                                    }
                                    elseif ($encoding == 'base64') {
                                        echo base64_decode($line);
                                    }
                                    else {
                                        echo $line;
                                    }
                                }
                                exit;
                            }
                        }
                    }
                }
            }
            Hm_Msgs::add('ERRAn Error occurred trying to download the message');
        }
    }
}

/**
 * Process reply field values
 * @subpackage imap/handler
 */
class Hm_Handler_imap_process_reply_fields extends Hm_Handler_Module {
    /**
     * Output reply field values if found in the request arguments
     */
    public function process() {
        if (array_key_exists('reply_source', $this->request->get) && preg_match("/^imap_(\d+)_(.+)$/", $this->request->get['reply_source'])) {
            if (array_key_exists('reply_uid', $this->request->get)) {
                $this->out('imap_reply_source', $this->request->get['reply_source']);
                $this->out('imap_reply_uid', $this->request->get['reply_uid']);
            }
        }
    }
}

/**
 * Process the list_path input argument
 * @subpackage imap/handler
 */
class Hm_Handler_imap_message_list_type extends Hm_Handler_Module {
    /**
     * Output a list title
     */
    public function process() {
        if (array_key_exists('list_path', $this->request->get)) {
            $path = $this->request->get['list_path'];
            if (preg_match("/^imap_\d+_.+$/", $path)) {
                $this->out('list_meta', false, false);
                $this->out('list_path', $path);
                $parts = explode('_', $path, 3);
                $details = Hm_IMAP_List::dump(intval($parts[1]));
                $custom_link = 'add';
                foreach (imap_data_sources(false, $this->user_config->get('custom_imap_sources', array())) as $vals) {
                    if ($vals['id'] == $parts[1] && $vals['folder'] == $parts[2]) {
                        $custom_link = 'remove';
                        break;
                    }
                }
                $this->out('custom_list_controls_type', $custom_link);
                if (!empty($details)) {
                    $this->out('mailbox_list_title', array('IMAP', $details['name'], $parts[2]));
                }
            }
        }
    }
}

/**
 * Expand an IMAP folder section
 * @subpackage imap/handler
 */
class Hm_Handler_imap_folder_expand extends Hm_Handler_Module {
    /**
     * Return cached subfolder contents or query the IMAP server for it
     */
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
            else {
                Hm_Msgs::add('ERRCould not authenticate to the selected IMAP server');
            }
        }
    }
}

/**
 * Fetch the message headers for a an IMAP folder page
 * @subpackage imap/handler
 */
class Hm_Handler_imap_folder_page extends Hm_Handler_Module {

    /**
     * Use IMAP FETCH to get a page of headers
     */
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
                if ($imap->selected_mailbox) {
                    $this->out('imap_folder_detail', array_merge($imap->selected_mailbox, array('offset' => $offset, 'limit' => $limit)));
                }
            }
            $this->out('imap_mailbox_page', $msgs);
            $this->out('list_page', $list_page);
            $this->out('imap_server_id', $form['imap_server_id']);
        }
    }
}

/**
 * Build a list of IMAP servers as the top level folders
 * @subpackage imap/handler
 */
class Hm_Handler_load_imap_folders extends Hm_Handler_Module {
    /**
     * Used by the folder list
     */
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

/**
 * Flag a message
 * @subpackage imap/handler
 */
class Hm_Handler_flag_imap_message extends Hm_Handler_Module {
    /**
     * Use IMAP to flag the selected message uid
     */
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
                Hm_Msgs::add('ERRAn error occurred trying to flag this message');
            }
        }
    }
}

/**
 * Perform an IMAP message action 
 * @subpackage imap/handler
 */
class Hm_Handler_imap_message_action extends Hm_Handler_Module {
    /**
     * Read, unread, delete, flag, or unflag a set of message uids
     */
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
                    Hm_Msgs::add(sprintf('ERRAn error occurred trying to %s some messages!', $form['imap_action_type'], $server));
                }
            }
        }
    }
}

/**
 * Search for a message
 * @subpackage imap/handler
 */
class Hm_Handler_imap_search extends Hm_Handler_Module {
    /**
     * Use IMAP SEARCH to find matching messages
     */
    public function process() {
        list($success, $form) = $this->process_form(array('imap_server_ids'));
        if ($success) {
            $terms = $this->session->get('search_terms', false);
            $since = $this->session->get('search_since', DEFAULT_SINCE);
            $fld = $this->session->get('search_fld', 'TEXT');
            $ids = explode(',', $form['imap_server_ids']);
            $date = process_since_argument($since);
            $folder = 'INBOX';
            if (array_key_exists('folder', $this->request->post)) {
                $folder = $this->request->post['folder'];
            }
            $msg_list = merge_imap_search_results($ids, 'ALL', $this->session, array($folder), MAX_PER_SOURCE, array('SINCE' => $date, $fld => $terms));
            $this->out('imap_search_results', $msg_list);
            $this->out('imap_server_ids', $form['imap_server_ids']);
        }
    }
}

/**
 * Get message headers for the Everthing page
 * @subpackage imap/handler
 */
class Hm_Handler_imap_combined_inbox extends Hm_Handler_Module {
    /**
     * Returns list of message data for the Everthing page
     */
    public function process() {
        list($success, $form) = $this->process_form(array('imap_server_ids'));
        if ($success) {
            if (array_key_exists('list_path', $this->request->get) && $this->request->get['list_path'] == 'email') {
                $limit = $this->user_config->get('all_email_per_source_setting', DEFAULT_PER_SOURCE);
                $date = process_since_argument($this->user_config->get('all_email_since_setting', DEFAULT_SINCE));
            }
            else {
                $limit = $this->user_config->get('all_per_source_setting', DEFAULT_PER_SOURCE);
                $date = process_since_argument($this->user_config->get('all_since_setting', DEFAULT_SINCE));
            }
            $ids = explode(',', $form['imap_server_ids']);
            $folder = 'INBOX';
            if (array_key_exists('folder', $this->request->post)) {
                $folder = $this->request->post['folder'];
            }
            $msg_list = merge_imap_search_results($ids, 'ALL', $this->session, array($folder), $limit, array('SINCE' => $date));
            $this->out('imap_combined_inbox_data', $msg_list);
            $this->out('imap_server_ids', $form['imap_server_ids']);
        }
    }
}

/**
 * Get message headers for the Flagged page
 * @subpackage imap/handler
 */
class Hm_Handler_imap_flagged extends Hm_Handler_Module {
    /**
     * Fetch flagged messages from an IMAP server
     */
    public function process() {
        list($success, $form) = $this->process_form(array('imap_server_ids'));
        if ($success) {
            $limit = $this->user_config->get('flagged_per_source_setting', DEFAULT_PER_SOURCE);
            $ids = explode(',', $form['imap_server_ids']);
            $date = process_since_argument($this->user_config->get('flagged_since_setting', DEFAULT_SINCE));
            $folder = 'INBOX';
            if (array_key_exists('folder', $this->request->post)) {
                $folder = $this->request->post['folder'];
            }
            $msg_list = merge_imap_search_results($ids, 'FLAGGED', $this->session, array($folder), $limit, array('SINCE' => $date));
            $this->out('imap_flagged_data', $msg_list);
            $this->out('imap_server_ids', $form['imap_server_ids']);
        }
    }
}

/**
 * Check the status of an IMAP server connection
 * @subpackage imap/handler
 */
class Hm_Handler_imap_status extends Hm_Handler_Module {
    /**
     * Output used on the info page to display the server status
     */
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

/**
 * Fetch messages for the Unread page
 * @subpackage imap/handler
 */
class Hm_Handler_imap_unread extends Hm_Handler_Module {
    /**
     * Returns UNSEEN messages for an IMAP server
     */
    public function process() {
        list($success, $form) = $this->process_form(array('imap_server_ids'));
        if ($success) {
            $limit = $this->user_config->get('unread_per_source_setting', DEFAULT_PER_SOURCE);
            $date = process_since_argument($this->user_config->get('unread_since_setting', DEFAULT_SINCE));
            $ids = explode(',', $form['imap_server_ids']);
            $msg_list = array();
            $folder = 'INBOX';
            if (array_key_exists('folder', $this->request->post)) {
                $folder = $this->request->post['folder'];
            }
            $msg_list = merge_imap_search_results($ids, 'UNSEEN', $this->session, array($folder), $limit, array('SINCE' => $date));
            $this->out('imap_unread_data', $msg_list);
            $this->out('imap_server_ids', $form['imap_server_ids']);
        }
    }
}

/**
 * Add a new IMAP server
 * @subpackage imap/handler
 */
class Hm_Handler_process_add_imap_server extends Hm_Handler_Module {
    public function process() {
        /**
         * Used on the servers page to add a new IMAP server
         */
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
                $hidden = false;
                if (isset($this->request->post['new_imap_hidden'])) {
                    $hidden = true;
                }
                if ($con = fsockopen($form['new_imap_address'], $form['new_imap_port'], $errno, $errstr, 2)) {
                    Hm_IMAP_List::add(array(
                        'name' => $form['new_imap_name'],
                        'server' => $form['new_imap_address'],
                        'hide' => $hidden,
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

/**
 * Save IMAP caches in the session
 * @subpackage imap/handler
 */
class Hm_Handler_save_imap_cache extends Hm_Handler_Module {
    /**
     * Save IMAP cache data for re-use
     */
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

/**
 * Save IMAP servers
 * @subpackage imap/handler
 */
class Hm_Handler_save_imap_servers extends Hm_Handler_Module {
    /**
     * Save IMAP servers in the user config
     */
    public function process() {
        $servers = Hm_IMAP_List::dump(false, true);
        $this->user_config->set('imap_servers', $servers);
        Hm_IMAP_List::clean_up();
    }
}

/**
 * Load IMAP servers for the search page
 * @subpackage imap/handler
 */
class Hm_Handler_load_imap_servers_for_search extends Hm_Handler_Module {
    /**
     * Output IMAP server array used on the search page
     */
    public function process() {
        foreach(imap_data_sources('imap_search_page_content', $this->user_config->get('custom_imap_sources', array())) as $vals) {
            $this->append('data_sources', $vals);
        }
    }
}

/**
 * Load IMAP servers for message list pages
 * @subpackage imap/handler
 */
class Hm_Handler_load_imap_servers_for_message_list extends Hm_Handler_Module {
    /**
     * Used by combined views excluding normal folder view and search pages
     */
    public function process() {
        $callback = false;
        if (array_key_exists('list_path', $this->request->get)) {
            $path = $this->request->get['list_path'];
        }
        else {
            $path = '';
        }
        if (array_key_exists('page', $this->request->get)) {
            $page = $this->request->get['page'];
        }
        else {
            $page = false;
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
                if ($page != 'message_list' && $page != 'search') {
                    $callback = 'imap_background_unread_content';
                }
                break;
        }
        if ($callback) {
            foreach (imap_data_sources($callback, $this->user_config->get('custom_imap_sources', array())) as $vals) {
                $this->append('data_sources', $vals);
            }
        }
    }
}

/**
 * Load IMAP servers for the user config object
 * @subpackage imap/handler
 */
class Hm_Handler_load_imap_servers_from_config extends Hm_Handler_Module {
    /**
     * This list is cached in the session between page loads by Hm_Handler_save_imap_servers
     */
    public function process() {
        $servers = $this->user_config->get('imap_servers', array());
        $added = false;
        $updated = false;
        $new_servers = array();
        foreach ($servers as $index => $server) {
            if ($this->session->loaded) {
                if (array_key_exists('expiration', $server)) {
                    $updated = true;
                    $server['expiration'] = 1;
                }
            }
            $new_servers[] = $server;
            Hm_IMAP_List::add($server, $index);
            if ($server['name'] == 'Default-Auth-Server') {
                $added = true;
            }
        }
        if ($updated) {
            $this->user_config->set('imap_servers', $new_servers);
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

/**
 * Check for IMAP server oauth2 token refresh
 * @subpackage imap/handler
 */
class Hm_Handler_imap_oauth2_token_check extends Hm_Handler_Module {
    public function process() {
        $active = array();
        if (array_key_exists('imap_server_ids', $this->request->post)) {
            $active = explode(',', $this->request->post['imap_server_ids']);
        }
        if (array_key_exists('imap_server_id', $this->request->post)) {
            $active[] = $this->request->post['imap_server_id'];
        }
        $updated = 0;
        foreach ($active as $server_id) {
            $server = Hm_IMAP_List::dump($server_id, true);
            if (array_key_exists('auth', $server) && $server['auth'] == 'xoauth2') {
                $results = imap_refresh_oauth2_token($server, $this->config);
                if (!empty($results)) {
                    if (Hm_IMAP_List::update_oauth2_token($server_id, $results[1], $results[0])) {
                        Hm_Debug::add(sprintf('Oauth2 token refreshed for IMAP server id %d', $server_id));
                        $this->session->record_unsaved('Oauth2 access token updated');
                        $updated++;
                    }
                }
            }
        }
        if ($updated > 0) {
            $servers = Hm_IMAP_List::dump(false, true);
            $this->user_config->set('imap_servers', $servers);
            $this->session->set('user_data', $this->user_config->dump());
        }
    }
}

/**
 * Output IMAP server data for other modules to use
 * @subpackage imap/handler
 */
class Hm_Handler_add_imap_servers_to_page_data extends Hm_Handler_Module {
    /**
     * Creates folder source for the folder list and outputs IMAP server details
     */
    public function process() {
        $servers = Hm_IMAP_List::dump();
        if (!empty($servers)) {
            $this->out('imap_servers', $servers);
            $this->append('folder_sources', 'email_folders');
        }
    }
}

/**
 * Delete IMAP cache
 * @subpackage imap/handler
 */
class Hm_Handler_imap_bust_cache extends Hm_Handler_Module {
    /**
     * Deletes all the saved IMAP cache data
     */
    public function process() {
        $this->session->set('imap_cache', array());
    }
}

/**
 * Test a connection to an IMAP server
 * @subpackage imap/handler
 */
class Hm_Handler_imap_connect extends Hm_Handler_Module {
    /**
     * Used by the servers page to test/authenticate with an IMAP server
     */
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

/**
 * Forget IMAP server credentials
 * @subpackage imap/handler
 */
class Hm_Handler_imap_forget extends Hm_Handler_Module {
    /**
     * Used on the servers page to forget login information for an IMAP server
     */
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

/**
 * Save a user/pass combination for an IMAP server
 * @subpackage imap/handler
 */
class Hm_Handler_imap_save extends Hm_Handler_Module {
    /**
     * Authenticate then save the username and password for an IMAP server
     */
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

/**
 * Get message content from an IMAP server
 * @subpackage imap/handler
 */
class Hm_Handler_imap_message_content extends Hm_Handler_Module {
    /**
     * Fetch the content, message parts, and headers for the supplied message
     */
    public function process() {
        list($success, $form) = $this->process_form(array('imap_server_id', 'imap_msg_uid', 'folder'));
        if ($success) {
            $this->out('msg_text_uid', $form['imap_msg_uid']);
            $this->out('msg_server_id', $form['imap_server_id']);
            $this->out('msg_folder', $form['folder']);
            $part = false;
            $prefetch = false;
            if (isset($this->request->post['imap_msg_part']) && preg_match("/[0-9\.]+/", $this->request->post['imap_msg_part'])) {
                $part = $this->request->post['imap_msg_part'];
            }
            elseif (isset($this->request->post['imap_prefetch']) && $this->request->post['imap_prefetch']) {
                $prefetch = true;
            }
            if (array_key_exists('reply_format', $this->request->post) && $this->request->post['reply_format']) {
                $this->out('reply_format', true);
            }
            $cache = Hm_IMAP_List::get_cache($this->session, $form['imap_server_id']);
            $imap = Hm_IMAP_List::connect($form['imap_server_id'], $cache);
            if ($imap) {
                $imap->read_only = $prefetch;
                if ($imap->select_mailbox($form['folder'])) {
                    $msg_struct = $imap->get_message_structure($form['imap_msg_uid']);
                    $this->out('msg_struct', $msg_struct);
                    if ($part !== false) {
                        if ($part == 0) {
                            $max = 500000;
                        }
                        else {
                            $max = false;
                        }
                        $struct = $imap->search_bodystructure( $msg_struct, array('imap_part_number' => $part));
                        $msg_struct_current = array_shift($struct);
                        $msg_text = $imap->get_message_content($form['imap_msg_uid'], $part, $max, $msg_struct_current);
                        if (isset($msg_struct_current['subtype']) && strtolower($msg_struct_current['subtype'] == 'html')) {
                            $msg_text = add_attached_images($msg_text, $form['imap_msg_uid'], $msg_struct, $imap);
                        }
                    }
                    else {
                        list($part, $msg_text) = $imap->get_first_message_part($form['imap_msg_uid'], 'text', false, $msg_struct);
                        $struct = $imap->search_bodystructure( $msg_struct, array('imap_part_number' => $part));
                        $msg_struct_current = array_shift($struct);
                    }
                    $this->out('msg_headers', $imap->get_message_headers($form['imap_msg_uid']));
                    $this->out('imap_msg_part', "$part");
                    if ($msg_struct_current) {
                        $this->out('msg_struct_current', $msg_struct_current);
                    }
                    $this->out('msg_text', $msg_text);
                    $this->out('msg_download_args', sprintf("page=message&amp;uid=%d&amp;list_path=imap_%d_%s&amp;imap_download_message=1", $form['imap_msg_uid'], $form['imap_server_id'], $form['folder']));
                }
            }
        }
    }
}

/**
 * Hide or unhide an IMAP server
 * @subpackage imap/handler
 */
class Hm_Handler_imap_hide extends Hm_Handler_Module {
    /**
     * Hide or unhide an IMAP server from combined pages and searches
     */
    public function process() {
        if (isset($this->request->post['hide_imap_server'])) {
            list($success, $form) = $this->process_form(array('imap_server_id'));
            if ($success) {
                Hm_IMAP_List::toggle_hidden($form['imap_server_id'], (bool) $this->request->post['hide_imap_server']);
                Hm_Msgs::add('Hidden status updated');
                $this->session->record_unsaved('IMAP server hidden status updated');
                Hm_Page_Cache::flush($this->session);
            }
        }
    }
}

/**
 * Delete an IMAP server
 * @subpackage imap/handler
 */
class Hm_Handler_imap_delete extends Hm_Handler_Module {
    /**
     * Remove an IMAP server completely, used on the servers page
     */
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

/**
 * Format a custom list controls section
 * @subpackage imap/output
 */
class Hm_Output_imap_custom_controls extends Hm_Output_Module {
    /**
     * Adds list controls to the IMAP folder page view
     */
    protected function output() {
        if ($this->get('custom_list_controls_type')) {
            if ($this->get('custom_list_controls_type') == 'remove') {
                $custom = '<a class="remove_source" title="'.$this->trans('Remove this folder from combined pages').'" href=""><img width="20" height="20" class="refresh_list" src="'.Hm_Image_Sources::$circle_x.'" alt="'.$this->trans('Remove').'"/></a>';
                $custom .= '<a style="display: none;" class="add_source" title="'.$this->trans('Add this folder to combined pages').'" href=""><img class="refresh_list" width="20" height="20" alt="'.$this->trans('Add').'" src="'.Hm_Image_Sources::$circle_check.'" /></a>';
            }
            else {
                $custom = '<a style="display: none;" class="remove_source" title="'.$this->trans('Remove this folder from combined pages').'" href=""><img width="20" height="20" class="refresh_list" src="'.Hm_Image_Sources::$circle_x.'" alt="'.$this->trans('Remove').'"/></a>';
                $custom .= '<a class="add_source" title="'.$this->trans('Add this folder to combined pages').'" href=""><img class="refresh_list" width="20" height="20" alt="'.$this->trans('Add').'" src="'.Hm_Image_Sources::$circle_check.'" /></a>';
            }
            $this->out('custom_list_controls', $custom);
        }
    }
}

/**
 * Format a message part body for display
 * @subpackage imap/output
 */
class Hm_Output_filter_message_body extends Hm_Output_Module {
    /**
     * Format html, text, or image content
     */
    protected function output() {
        $txt = '<div class="msg_text_inner">';
        if ($this->get('msg_text')) {
            $struct = $this->get('msg_struct_current', array());
            if (isset($struct['subtype']) && strtolower($struct['subtype']) == 'html') {
                $txt .= format_msg_html($this->get('msg_text'));
            }
            elseif (isset($struct['type']) && strtolower($struct['type']) == 'image') {
                $txt .= format_msg_image($this->get('msg_text'), strtolower($struct['subtype']));
            }
            else {
                $txt .= format_msg_text($this->get('msg_text'), $this);
            }
        }
        $txt .= '</div>';
        $this->out('msg_text', $txt);
    }
}

/**
 * Format the message part section of the message view
 * @subpackage imap/output
 */
class Hm_Output_filter_message_struct extends Hm_Output_Module {
    /**
     * Build message part section HTML
     */
    protected function output() {
        if ($this->get('msg_struct')) {
            $res = '<table class="msg_parts">';
            $part = $this->get('imap_msg_part', '1');
            $args = $this->get('msg_download_args', '');
            $res .=  format_msg_part_section($this->get('msg_struct'), $this, $part, $args);
            $res .= '</table>';
            $this->out('msg_parts', $res);
        }
    }
}

/**
 * Format the message headers section of the message view
 * @subpackage imap/output
 */
class Hm_Output_filter_message_headers extends Hm_Output_Module {
    /**
     * Build message header HTML
     */
    protected function output() {
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
                '&amp;reply_source='.$this->html_safe(sprintf('imap_%d_%s', $this->get('msg_server_id'), $this->get('msg_folder'))).'">'.
                $this->trans('reply').'</a>'.
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

/**
 * Format configured IMAP servers for the servers page
 * @subpackage imap/output
 */
class Hm_Output_display_configured_imap_servers extends Hm_Output_Module {
    /**
     * Build HTML for configured IMAP servers
     */
    protected function output() {
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
                '<input type="hidden" name="hm_page_key" value="'.$this->html_safe(Hm_Request_Key::generate()).'" />'.
                '<input type="hidden" name="imap_server_id" class="imap_server_id" value="'.$this->html_safe($index).'" /><span> '.
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
                $hidden = false;
                if (array_key_exists('hide', $vals) && $vals['hide']) {
                    $hidden = true;
                }
                $res .= '<input type="submit" ';
                if ($hidden) {
                    $res .= 'style="display: none;" ';
                }
                $res .= 'value="'.$this->trans('Hide').'" class="hide_imap_connection" />';
                $res .= '<input type="submit" ';
                if (!$hidden) {
                    $res .= 'style="display: none;" ';
                }
                $res .= 'value="'.$this->trans('Unhide').'" class="unhide_imap_connection" />';
                $res .= '<input type="hidden" value="ajax_imap_debug" name="hm_ajax_hook" />';
            }
            $res .= '</form></div>';
        }
        $res .= '<br class="clear_float" /></div></div>';
        return $res;
    }
}

/**
 * Format the add IMAP server dialog for the servers page
 * @subpackage imap/output
 */
class Hm_Output_add_imap_server_dialog extends Hm_Output_Module {
    /**
     * Build the HTML for the add server dialog
     */
    protected function output() {
        $count = count($this->get('imap_servers', array()));
        $count = sprintf($this->trans('%d configured'), $count);
        return '<div class="imap_server_setup"><div data-target=".imap_section" class="server_section">'.
            '<img alt="" src="'.Hm_Image_Sources::$env_closed.'" width="16" height="16" />'.
            ' '.$this->trans('IMAP Servers').'<div class="server_count">'.$count.'</div></div><div class="imap_section"><form class="add_server" method="POST">'.
            '<input type="hidden" name="hm_page_key" value="'.$this->html_safe(Hm_Request_Key::generate()).'" />'.
            '<div class="subtitle">'.$this->trans('Add an IMAP Server').'</div><table>'.
            '<tr><td colspan="2"><label class="screen_reader" for="new_imap_name">'.$this->trans('Account name').'</label>'.
            '<input id="new_imap_name" required type="text" name="new_imap_name" class="txt_fld" value="" placeholder="'.$this->trans('Account name').'" /></td></tr>'.
            '<tr><td colspan="2"><label class="screen_reader" for="new_imap_address">'.$this->trans('Server address').'</label>'.
            '<input required type="text" id="new_imap_address" name="new_imap_address" class="txt_fld" placeholder="'.$this->trans('IMAP server address').'" value=""/></td></tr>'.
            '<tr><td colspan="2"><label class="screen_reader" for="new_imap_port">'.$this->trans('IMAP port').'</label>'.
            '<input required type="number" id="new_imap_port" name="new_imap_port" class="port_fld" value="" placeholder="'.$this->trans('Port').'"></td></tr>'.
            '<tr><td colspan="2"><input type="checkbox" id="new_imap_hidden" name="new_imap_hidden" class="" value="1">'.
            '<label for="new_imap_hidden">'.$this->trans('Hide From Combined Pages').'</label></td></tr>'.
            '<tr><td><input type="checkbox" name="tls" value="1" id="imap_tls" checked="checked" /> <label for="imap_tls">'.$this->trans('Use TLS').'</label></td>'.
            '<td><input type="submit" value="'.$this->trans('Add').'" name="submit_imap_server" /></td></tr>'.
            '</table></form>';
    }
}

/**
 * Format the IMAP status output on the info page
 * @subpackage imap/output
 */
class Hm_Output_display_imap_status extends Hm_Output_Module {
    /**
     * Build the HTML for the status rows. Will be populated by an ajax call per server
     */
    protected function output() {
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

/**
 * Build HTML for reply to hidden fields
 * @subpackage imap/output
 */
class Hm_Output_imap_reply_details extends Hm_Output_Module {
    /**
     * Used on the message view page to pass reply to information to the compose page
     */
    protected function output() {
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

/**
 * Output a hidden field with all the IMAP server ids
 * @subpackage imap/output
 */
class Hm_Output_imap_server_ids extends Hm_Output_Module {
    /**
     * Build HTML for the IMAP server ids
     */
    protected function output() {
        return '<input type="hidden" class="imap_server_ids" value="'.$this->html_safe(implode(',', array_keys($this->get('imap_servers', array ())))).'" />';
    }
}

/**
 * Format a list of subfolders
 * @subpackage imap/output
 */
class Hm_Output_filter_expanded_folder_data extends Hm_Output_Module {
    /**
     * Build the HTML for a list of subfolders. The page cache is used to pass this to the folder list.
     */
    protected function output() {
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

/**
 * Format the status of an IMAP connection used on the info page
 * @subpackage imap/output
 */
class Hm_Output_filter_imap_status_data extends Hm_Output_Module {
    /**
     * Build AJAX response for an IMAP server status
     */
    protected function output() {
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

/**
 * Format the top level IMAP folders for the folder list
 * @subpackage imap/output
 */
class Hm_Output_filter_imap_folders extends Hm_Output_Module {
    /**
     * Build HTML for the Email section of the folder list
     */
    protected function output() {
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

/**
 * Format search results row
 * @subpackage imap/output
 */
class Hm_Output_filter_imap_search extends Hm_Output_Module {
    /**
     * Build ajax response from an IMAP server for a search
     */
    protected function output() {
        if ($this->get('imap_search_results')) {
            prepare_imap_message_list($this->get('imap_search_results'), $this, 'search');
        }
        elseif (!$this->get('formatted_message_list')) {
            $this->out('formatted_message_list', array());
        }
    }
}

/**
 * Format message headers for the Flagged page
 * @subpackage imap/output
 */
class Hm_Output_filter_flagged_data extends Hm_Output_Module {
    /**
     * Build ajax response for the Flagged message list
     */
    protected function output() {
        if ($this->get('imap_flagged_data')) {
            prepare_imap_message_list($this->get('imap_flagged_data'), $this, 'flagged');
        }
        elseif (!$this->get('formatted_message_list')) {
            $this->out('formatted_message_list', array());
        }
    }
}

/**
 * Format message headers for the Unread page
 * @subpackage imap/output
 */
class Hm_Output_filter_unread_data extends Hm_Output_Module {
    /**
     * Build ajax response for the Unread message list
     */
    protected function output() {
        if ($this->get('imap_unread_data')) {
            prepare_imap_message_list($this->get('imap_unread_data'), $this, 'unread');
        }
        elseif (!$this->get('formatted_message_list')) {
            $this->out('formatted_message_list', array());
        }
    }
}

/**
 * Format message headers for the All E-mail page
 * @subpackage imap/output
 */
class Hm_Output_filter_all_email extends Hm_Output_Module {
    /**
     * Build ajax response for the All E-mail message list
     */
    protected function output() {
        if ($this->get('imap_combined_inbox_data')) {
            prepare_imap_message_list($this->get('imap_combined_inbox_data'), $this, 'email');
        }
        else {
            $this->out('formatted_message_list', array());
        }
    }
}

/**
 * Format message headers for the Everthing page
 * @subpackage imap/output
 */
class Hm_Output_filter_combined_inbox extends Hm_Output_Module {
    /**
     * Build ajax response for the Everthing message list
     */
    protected function output() {
        if ($this->get('imap_combined_inbox_data')) {
            prepare_imap_message_list($this->get('imap_combined_inbox_data'), $this, 'combined_inbox');
        }
        else {
            $this->out('formatted_message_list', array());
        }
    }
}

/**
 * Normal IMAP folder view
 * @subpackage imap/output
 */
class Hm_Output_filter_folder_page extends Hm_Output_Module {
    /**
     * Build ajax response for a folder page
     */
    protected function output() {
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

/**
 * Format reply content for the compose page
 * @subpackage imap/output
 */
class Hm_Output_filter_reply_content extends Hm_Output_Module {
    /**
     * Build data to be used when replying a message
     */
    protected function output() {
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
                if (array_key_exists('Reply-to', $hdrs)) {
                    $reply_to = $hdrs['Reply-to'];
                }
                elseif (array_key_exists('From', $hdrs)) {
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

/**
 * Build a source list
 * @subpackage imap/functions
 * @param string $callback javascript callback function name
 * @param array $custom user specific assignments
 * @return array
 */
function imap_data_sources($callback, $custom=array()) {
    $sources = array();
    foreach (Hm_IMAP_List::dump() as $index => $vals) {
        if (array_key_exists('hide', $vals) && $vals['hide']) {
            continue;
        }
        $sources[] = array('callback' => $callback, 'folder' => 'INBOX', 'type' => 'imap', 'name' => $vals['name'], 'id' => $index);
    }
    foreach ($custom as $path => $type) {
        $parts = explode('_', $path, 3);
        $remove_id = false;

        if ($type == 'add') {
            $details = Hm_IMAP_List::dump($parts[1]);
            if ($details) {
                $sources[] = array('callback' => $callback, 'folder' => $parts[2], 'type' => 'imap', 'name' => $details['name'], 'id' => $parts[1]);
            }
        }
        elseif ($type == 'remove') {
            foreach ($sources as $index => $vals) {
                if ($vals['folder'] == $parts[2] && $vals['id'] == $parts[1]) {
                    $remove_id = $index;
                    break;
                }
            }
            if ($remove_id !== false) {
                unset($sources[$remove_id]);
            }
        }
    }
    return $sources;
}

/**
 * Prepare and format message list data 
 * @subpackage imap/functions
 * @param array $msgs list of message headers to format
 * @param object $mod Hm_Output_Module
 * @return void
 */
function prepare_imap_message_list($msgs, $mod, $type) {
    $style = $mod->get('news_list_style') ? 'news' : 'email';
    if ($mod->get('is_mobile')) {
        $style = 'news';
    }
    $res = format_imap_message_list($msgs, $mod, $type, $style);
    $mod->out('formatted_message_list', $res);
}

/**
 * Build HTML for a list of IMAP folders
 * @subpackage imap/functions
 * @param array $folders list of folder data
 * @param mixed $id IMAP server id
 * @param object $mod Hm_Output_Module
 * @return string
 */
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

/**
 * Format a list of message headers
 * @subpackage imap/functions
 * @param array $msg_list list of message headers
 * @param object $mod Hm_Output_Module
 * @param mixed $parent_list parent list id
 * @param string $style list style (email or news)
 * @return array
 */
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
        $source = $msg['server_name'];
        if ($msg['folder'] && $msg['folder'] != 'INBOX') {
            $source .= '-'.preg_replace("/^INBOX.{1}/", '', $msg['folder']);
        }
        $url = '?page=message&uid='.$msg['uid'].'&list_path='.sprintf('imap_%d_%s', $msg['server_id'], $msg['folder']).'&list_parent='.$parent_value;
        if ($style == 'news') {
            $res[$id] = message_list_row(array(
                    array('checkbox_callback', $id),
                    array('icon_callback', $flags),
                    array('subject_callback', $subject, $url, $flags),
                    array('safe_output_callback', 'source', $source),
                    array('safe_output_callback', 'from', $from),
                    array('date_callback', $date, $timestamp),
                ),
                $id,
                $style,
                $output_module
            );
        }
        else {
            $res[$id] = message_list_row(array(
                    array('checkbox_callback', $id),
                    array('safe_output_callback', 'source', $source),
                    array('safe_output_callback', 'from', $from),
                    array('subject_callback', $subject, $url, $flags),
                    array('date_callback', $date, $timestamp),
                    array('icon_callback', $flags)
                ),
                $id,
                $style,
                $output_module
            );
        }
    }
    return $res;
}

/**
 * Process message ids
 * @subpackage imap/functions
 * @param array $ids list of ids
 * @return array
 */
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

/**
 * Build pagination links for an IMAP folder page
 * @subpackage imap/functions
 * @param array $detail folder details
 * @param string $path list path
 * @return string
 */
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

/**
 * Format a message part row
 * @subpackage imap/functions
 * @param string $id message identifier
 * @param array $vals details of the message
 * @param object $mod Hm_Output_Module
 * @param int $level indention level
 * @param string $part currently selected part
 * @param string $dl_args base arguments for a download link URL
 * @return string
 */
function format_msg_part_row($id, $vals, $output_mod, $level, $part, $dl_args) {
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
    elseif (isset($vals['envelope']['subject']) && trim($vals['envelope']['subject'])) {
        $desc = $vals['envelope']['subject'];
    }
    else {
        $desc = '';
    }
    $res = '<tr';
    if ($id == $part) {
        $res .= ' class="selected_part"';
    }
    $res .= '><td><div class="'.$class.'">';
    if (in_array(strtolower($vals['type']).strtolower($vals['subtype']), $allowed, true)) {
        $res .= '<a href="#" class="msg_part_link" data-message-part="'.$output_mod->html_safe($id).'">'.$output_mod->html_safe(strtolower($vals['type'])).
            ' / '.$output_mod->html_safe(strtolower($vals['subtype'])).'</a>';
    }
    else {
        $res .= $output_mod->html_safe(strtolower($vals['type'])).' / '.$output_mod->html_safe(strtolower($vals['subtype']));
    }
    $res .= '</td><td>'.(isset($vals['encoding']) ? $output_mod->html_safe(strtolower($vals['encoding'])) : '').
        '</td><td>'.(isset($vals['attributes']['charset']) && trim($vals['attributes']['charset']) ? $output_mod->html_safe(strtolower($vals['attributes']['charset'])) : '').
        '</td><td>'.$output_mod->html_safe($desc).'</td>';
    $res .= '<td class="download_link"><a href="?'.$dl_args.'&amp;imap_msg_part='.$output_mod->html_safe($id).'">Download</a></td></tr>';
    return $res;
}

/**
 * Format the message part section of the message view page
 * @subpackage imap/functions
 * @param array $struct message structure
 * @param object $mod Hm_Output_Module
 * @param string $part currently selected message part id
 * @param string $dl_link base arguments for a download link
 * @param int $level indention level
 * @return string
 */
function format_msg_part_section($struct, $output_mod, $part, $dl_link, $level=0) {
    $res = '';
    foreach ($struct as $id => $vals) {
        if (is_array($vals) && isset($vals['type'])) {
            $res .= format_msg_part_row($id, $vals, $output_mod, $level, $part, $dl_link);
            if (isset($vals['subs'])) {
                $res .= format_msg_part_section($vals['subs'], $output_mod, $part, $dl_link, ($level + 1));
            }
        }
        else {
            if (count($vals) == 1 && isset($vals['subs'])) {
                $res .= format_msg_part_section($vals['subs'], $output_mod, $part, $dl_link, $level);
            }
        }
    }
    return $res;
}

/**
 * Sort callback to sort by internal date
 * @subpackage imap/functions
 * @param array $a first message detail
 * @param array $b second message detail
 * @return int
 */
function sort_by_internal_date($a, $b) {
    if ($a['internal_date'] == $b['internal_date']) return 0;
    return (strtotime($a['internal_date']) < strtotime($b['internal_date']))? -1 : 1;
}

/**
 * Merge IMAP search results
 * @subpackage imap/functions
 * @param array $ids IMAP server ids
 * @param string $search_type
 * @param object $session session object
 * @param array $folders list of folders to search
 * @param int $limit max results
 * @param array $terms list of search terms
 * @return array
 */
function merge_imap_search_results($ids, $search_type, $session, $folders = array('INBOX'), $limit=0, $terms=array()) {
    $msg_list = array();
    foreach($ids as $index => $id) {
        $id = intval($id);
        $cache = Hm_IMAP_List::get_cache($session, $id);
        $imap = Hm_IMAP_List::connect($id, $cache);
        if (is_object($imap) && $imap->get_state() == 'authenticated') {
            $server_details = Hm_IMAP_List::dump($id);
            $folder = $folders[$index];
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
    usort($msg_list, 'sort_by_internal_date');
    return $msg_list;
}

/**
 * Replace inline images in an HTML message part
 * @subpackage imap/functions
 * @param string $txt HTML
 * @param string $uid message id
 * @param array $struct message structure array
 * @param object $imap IMAP server object
 */
function add_attached_images($txt, $uid, $struct, $imap) {
    if (preg_match_all("/src=('|\"|)cid:([^@]+@[^\s'\"]+)/", $txt, $matches)) {
        $cids = array_pop($matches);
        foreach ($cids as $id) {
            $part = $imap->search_bodystructure($struct, array('id' => $id, 'type' => 'image'), true);
            $part_ids = array_keys($part);
            $part_id = array_pop($part_ids);
            $img = $imap->get_message_content($uid, $part_id, false, $part[$part_id]);
            $txt = str_replace('cid:'.$id, 'data:image/'.$part[$part_id]['subtype'].';base64,'.chunk_split(base64_encode($img)), $txt);
        }
    }
    return $txt;
}

/**
 * Get Oauth2 server info
 * @subpackage imap/functions
 * @param object $config site config object
 * @return array
 */
function imap_get_oauth2_data($config) {
    $settings = array();
    $ini_file = rtrim($config->get('app_data_dir', ''), '/').'/oauth2.ini';
    if (is_readable($ini_file)) {
        $settings = parse_ini_file($ini_file, true);
    }
    return $settings;
}

/**
 * Check for and do an Oauth2 token reset if needed
 * @param array $server imap server data
 * @param object $config site config object
 * @return mixed
 */
function imap_refresh_oauth2_token($server, $config) {

    if ((int) $server['expiration'] <= time()) {
        $oauth2_data = imap_get_oauth2_data($config);
        $details = array();
        if ($server['server'] == 'imap.gmail.com') {
            $details = $oauth2_data['gmail'];
        }
        elseif ($server['server'] == 'imap-mail.outlook.com') {
            $details = $oauth2_data['outlook'];
        }
        if (!empty($details)) {
            $oauth2 = new Hm_Oauth2($details['client_id'], $details['client_secret'], $details['client_uri']);
            $result = $oauth2->refresh_token($details['refresh_uri'], $server['refresh_token']);
            if (array_key_exists('access_token', $result)) {
                return array(strtotime(sprintf('+%d seconds', $result['expires_in'])), $result['access_token']);
            }
        }
    }
    return array();
}

function get_imap_part_name($struct, $uid, $part_id) {
    $extension = '';
    if (strtolower($struct['type']) == 'message' && strtolower($struct['subtype'] = 'rfc822')) {
        $extension = '.eml';
    }
    if (array_key_exists('file_attributes', $struct) && array_key_exists('attachment', $struct['file_attributes'])) {
        for ($i=0;$i<count($struct['file_attributes']['attachment']);$i++) {
            if (strtolower(trim($struct['file_attributes']['attachment'][$i])) == 'filename') {
                if (array_key_exists(($i+1), $struct['file_attributes']['attachment'])) {
                    return trim($struct['file_attributes']['attachment'][($i+1)]);
                }
            }
        }
    }

    if (array_key_exists('attributes', $struct) && is_array($struct['attributes']) && array_key_exists('name', $struct['attributes'])) {
        return trim($struct['attributes']['name']);
    }
    if (array_key_exists('description', $struct)) {
        return trim(str_replace(' ', '_', $struct['description'])).$extension;
    }
    return 'message_'.$uid.'_part_'.$part_id.$extension;
}

?>
