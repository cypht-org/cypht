<?php

/**
 * IMAP modules
 * @package modules
 * @subpackage imap
 */

if (!defined('DEBUG_MODE')) { die(); }

/**
 * Check for attachments when forwarding a message
 * @subpackage imap/handler
 */
class Hm_Handler_imap_forward_attachments extends Hm_Handler_Module {
    public function process() {
        if (!array_key_exists('forward', $this->request->get)) {
            return;
        }
        if (!array_key_exists('list_path', $this->request->get)) {
            return;
        }
        if (!array_key_exists('uid', $this->request->get)) {
            return;
        }
        $uid = $this->request->get['uid'];
        $list_path = $this->request->get['list_path'];
        $path = explode('_', $list_path);
        if (count($path) != 3) {
            return;
        }
        if ($path[0] != 'imap') {
            return;
        }
        $filepath = $this->config->get('attachment_dir');
        if (!$filepath) {
            return;
        }
        $cache = Hm_IMAP_List::get_cache($this->cache, $path[1]);
        $imap = Hm_IMAP_List::connect($path[1], $cache);
        if (!imap_authed($imap)) {
            return;
        }
        if (!$imap->select_mailbox(hex2bin($path[2]))) {
            return;
        }
        $content = $imap->get_message_content($uid, 0);
        if (!$content) {
            return;
        }
        $file = array(
            'name' => 'mail.mime',
            'type' => 'message/rfc822',
            'no_encoding' => true,
            'size' => strlen($content)
        );
        $draft_id = count($this->session->get('compose_drafts', array()));
        attach_file($content, $file, $filepath, $draft_id, $this);
    }
}

/**
 * Get the status of an IMAP folder
 * @subpackage imap/handler
 */
class Hm_Handler_imap_folder_status extends Hm_Handler_Module {
    public function process() {
        list($success, $form) = $this->process_form(array('imap_server_id', 'folder'));
        if ($success) {
            $cache = Hm_IMAP_List::get_cache($this->cache, $form['imap_server_id']);
            $imap = Hm_IMAP_List::connect($form['imap_server_id'], $cache);
            if (imap_authed($imap)) {
                $this->out('folder_status', array('imap_'.$form['imap_server_id'].'_'.$form['folder'] => $imap->get_mailbox_status(hex2bin($form['folder']))));
            }
        }
    }
}

/**
 * Process input from the per page count setting
 * @subpackage imap/handler
 */
class Hm_Handler_process_imap_per_page_setting extends Hm_Handler_Module {
    /**
     * Allowed values are greater than zero and less than MAX_PER_SOURCE
     */
    public function process() {
        process_site_setting('imap_per_page', $this, 'max_source_setting_callback', DEFAULT_PER_SOURCE);
    }
}

/**
 * Process input from the max per source setting for the Sent E-mail page in the settings page
 * @subpackage imap/handler
 */
class Hm_Handler_process_sent_source_max_setting extends Hm_Handler_Module {
    /**
     * Allowed values are greater than zero and less than MAX_PER_SOURCE
     */
    public function process() {
        process_site_setting('sent_per_source', $this, 'max_source_setting_callback', DEFAULT_PER_SOURCE);
    }
}

/**
 * Process "simple message parts" setting for the message view page in the settings page
 * @subpackage imap/handler
 */
class Hm_Handler_process_simple_msg_parts extends Hm_Handler_Module {
    /**
     * valid values are true or false
     */
    public function process() {
        function simple_msg_view_callback($val) {
            return $val;
        }
        process_site_setting('simple_msg_parts', $this, 'simple_msg_view_callback', false, true);
    }
}

/**
 * Process "message part icons" setting for the message view page in the settings page
 * @subpackage imap/handler
 */
class Hm_Handler_process_msg_part_icons extends Hm_Handler_Module {
    /**
     * valid values are true or false
     */
    public function process() {
        function msg_part_icons_callback($val) {
            return $val;
        }
        process_site_setting('msg_part_icons', $this, 'msg_part_icons_callback', false, true);
    }
}

/**
 * Process "text only" setting for the message view page in the settings page
 * @subpackage imap/handler
 */
class Hm_Handler_process_text_only_setting extends Hm_Handler_Module {
    /**
     * valid values are true or false
     */
    public function process() {
        function text_only_callback($val) {
            return $val;
        }
        process_site_setting('text_only', $this, 'text_only_callback', false, true);
    }
}

/**
 * Process "since" setting for the Sent page in the settings page
 * @subpackage imap/handler
 */
class Hm_Handler_process_sent_since_setting extends Hm_Handler_Module {
    /**
     * valid values are defined in the process_since_argument function
     */
    public function process() {
        process_site_setting('sent_since', $this, 'since_setting_callback');
    }
}

 /**
 * Process an IMAP move/copy action
 * @subpackage imap/handler
 */
class Hm_Handler_imap_process_move extends Hm_Handler_Module {
    public function process() {
        list($success, $form) = $this->process_form(array('imap_move_to', 'imap_move_page', 'imap_move_action', 'imap_move_ids'));
        if ($success) {
            list($msg_ids, $dest_path, $same_server_ids, $other_server_ids) = process_move_to_arguments($form);
            $moved = array();
            if (count($same_server_ids) > 0) {
                $moved = array_merge($moved, imap_move_same_server($same_server_ids, $form['imap_move_action'], $this->cache, $dest_path));
            }
            if (count($other_server_ids) > 0) {
                $moved = array_merge($moved, imap_move_different_server($other_server_ids, $form['imap_move_action'], $dest_path, $this->cache));

            }
            if (count($moved) > 0 && count($moved) == count($msg_ids)) {
                if ($form['imap_move_action'] == 'move') {
                    Hm_Msgs::add('Messages moved');
                }
                else {
                    Hm_Msgs::add('Messages copied');
                }
            }
            elseif (count($moved) > 0) {
                if ($form['imap_move_action'] == 'move') {
                    Hm_Msgs::add('Some messages moved (only IMAP message types can be moved)');
                }
                else {
                    Hm_Msgs::add('Some messages copied (only IMAP message types can be copied)');
                }
            }
            elseif (count($moved) == 0) {
                Hm_Msgs::add('ERRUnable to move/copy selected messages');
            }
            if ($form['imap_move_action'] == 'move' && $form['imap_move_page'] == 'message') {
                $msgs = Hm_Msgs::get();
                Hm_Msgs::flush();
                $this->session->secure_cookie($this->request, 'hm_msgs', base64_encode(json_encode($msgs)));
            }
            $this->out('move_count', $moved);
        }
    }
}

 /**
 * Save a sent message
 * @subpackage imap/handler
 */
class Hm_Handler_imap_save_sent extends Hm_Handler_Module {
    public function process() {
        if (!$this->get('save_sent_msg')) {
            return;
        }
        $imap_id = $this->get('save_sent_server');
        $mime = $this->get('save_sent_msg');

        if ($imap_id === false) {
            return;
        }
        $msg = $mime->get_mime_msg();
        $msg = str_replace("\r\n", "\n", $msg);
        $msg = str_replace("\n", "\r\n", $msg);
        $msg = rtrim($msg)."\r\n";
        $cache = Hm_IMAP_List::get_cache($this->cache, $imap_id);
        $imap = Hm_IMAP_List::connect($imap_id, $cache);
        $imap_details = Hm_IMAP_List::dump($imap_id);
        $sent_folder = false;
        if (imap_authed($imap)) {
            $specials = $this->user_config->get('special_imap_folders', array());
            if (array_key_exists($imap_id, $specials)) {
                if (array_key_exists('sent', $specials[$imap_id])) {
                    if ($specials[$imap_id]['sent']) {
                        $sent_folder = $specials[$imap_id]['sent'];
                    }
                }
            }

            if (!$sent_folder) {
                $auto_sent = $imap->get_special_use_mailboxes('sent');
                if (!array_key_exists('sent', $auto_sent)) {
                    return;
                }
                $sent_folder = $auto_sent['sent'];
            }
            if (!$sent_folder) {
                Hm_Debug::add(sprintf("Unable to save sent message, no sent folder for IMAP %s", $imap_details['server']));
            }
            if ($sent_folder) {
                Hm_Debug::add(sprintf("Attempting to save sent message for IMAP server %s in folder %s", $imap_details['server'], $sent_folder));
                if ($imap->append_start($sent_folder, strlen($msg), true)) {
                    $imap->append_feed($msg."\r\n");
                    if (!$imap->append_end()) {
                        Hm_Msgs::add('ERRAn error occurred saving the sent message');
                    }
                }
            }
        }
    }
}

 /**
 * Unflag a message after replying to it
 * @subpackage imap/handler
 */
class Hm_Handler_imap_unflag_on_send extends Hm_Handler_Module {
    public function process() {
        if ($this->get('msg_sent')) {
            list($success, $form) = $this->process_form(array('compose_unflag_send', 'compose_msg_uid', 'compose_msg_path'));
            if ($success) {
                $path = explode('_', $form['compose_msg_path']);
                if (count($path) == 3 && $path[0] == 'imap') {
                    $cache = Hm_IMAP_List::get_cache($this->cache, $path[1]);
                    $imap = Hm_IMAP_List::connect($path[1], $cache);
                    if (imap_authed($imap) && $imap->select_mailbox(hex2bin($path[2]))) {
                        $imap->message_action('UNFLAG', array($form['compose_msg_uid']));
                    }
                }
            }
        }
    }
}

 /**
 * Flag a message as answered
 * @subpackage imap/handler
 */
class Hm_Handler_imap_mark_as_answered extends Hm_Handler_Module {
    public function process() {
        if ($this->get('msg_sent')) {
            list($success, $form) = $this->process_form(array('compose_msg_uid', 'compose_msg_path'));
            if ($success) {
                $path = explode('_', $form['compose_msg_path']);
                if (count($path) == 3 && $path[0] == 'imap') {
                    $cache = Hm_IMAP_List::get_cache($this->cache, $path[1]);
                    $imap = Hm_IMAP_List::connect($path[1], $cache);
                    if (imap_authed($imap) && $imap->select_mailbox(hex2bin($path[2]))) {
                        $this->out('folder_status', array('imap_'.$path[1].'_'.$path[2] => $imap->folder_state));
                        $imap->message_action('ANSWERED', array($form['compose_msg_uid']));
                    }
                }
            }
        }
    }
}

 /**
 * Flag a message as read
 * @subpackage imap/handler
 */
class Hm_Handler_imap_mark_as_read extends Hm_Handler_Module {
    public function process() {
        list($success, $form) = $this->process_form(array('imap_server_id', 'imap_msg_uid', 'folder'));
        if ($success) {
            $cache = Hm_IMAP_List::get_cache($this->cache, $form['imap_server_id']);
            $imap = Hm_IMAP_List::connect($form['imap_server_id'], $cache);
            if (imap_authed($imap) && $imap->select_mailbox(hex2bin($form['folder']))) {
                $this->out('folder_status', array('imap_'.$form['imap_server_id'].'_'.$form['folder'] => $imap->folder_state));
                $imap->message_action('READ', array($form['imap_msg_uid']));
            }
        }
    }
}

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
                if (is_array($sources) && array_key_exists($form['list_path'], $sources)) {
                    unset($sources[$form['list_path']]);
                }
                else {
                    $sources[$form['list_path']] = 'remove';
                }
                Hm_Msgs::add('Folder removed from combined pages');
                $this->session->record_unsaved('Removed folder from combined pages');
            }
            $this->session->set('custom_imap_sources', $sources, true);
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

            if (array_key_exists('uid', $this->request->get) && $this->request->get['uid']) {
                $uid = $this->request->get['uid'];
            }
            if (array_key_exists('list_path', $this->request->get) && preg_match("/^imap_(\d+)_(.+)/", $this->request->get['list_path'], $matches)) {
                $server_id = $matches[1];
                $folder = hex2bin($matches[2]);
            }
            if (array_key_exists('imap_msg_part', $this->request->get) && preg_match("/^[0-9\.]+$/", $this->request->get['imap_msg_part'])) {
                $msg_id = preg_replace("/^0.{1}/", '', $this->request->get['imap_msg_part']);
            }
            if ($server_id !== NULL && $uid !== NULL && $folder !== NULL && $msg_id !== NULL) {
                $cache = Hm_IMAP_List::get_cache($this->cache, $server_id);
                $imap = Hm_IMAP_List::connect($server_id, $cache);
                if (imap_authed($imap)) {
                    if ($imap->select_mailbox($folder)) {
                        $msg_struct = $imap->get_message_structure($uid);
                        $struct = $imap->search_bodystructure($msg_struct, array('imap_part_number' => $msg_id));
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
                                $charset = '';
                                if (array_key_exists('attributes', $part_struct)) {
                                    if (is_array($part_struct['attributes']) && array_key_exists('charset', $part_struct['attributes'])) {
                                        $charset = '; charset='.$part_struct['attributes']['charset'];
                                    }
                                }
                                header('Content-Type: '.$part_struct['type'].'/'.$part_struct['subtype'].$charset);
                                header('Content-Transfer-Encoding: binary');
                                ob_end_clean();
                                $output_line = '';
                                while($line = $imap->read_stream_line()) {
                                    if ($encoding == 'quoted-printable') {
                                        $line = quoted_printable_decode($line);
                                    }
                                    elseif ($encoding == 'base64') {
                                        $line = base64_decode($line);
                                    }
                                    echo $output_line;
                                    $output_line = $line;
                                }
                                if ($part_struct['type'] == 'text') {
                                    $output_line = preg_replace("/\)(\r\n)$/m", '$1', $output_line);
                                }
                                echo $output_line;
                                Hm_Functions::cease();
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
                $this->out('list_path', $path, false);
                $this->out('move_copy_controls', true);
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
                if (array_key_exists('keyword', $this->request->get)) {
                    $this->out('list_keyword', $this->request->get['keyword']);
                }
                if (array_key_exists('filter', $this->request->get)) {
                    if (in_array($this->request->get['filter'], array('all', 'unseen', 'seen',
                        'answered', 'unanswered', 'flagged', 'unflagged'), true)) {
                        $this->out('list_filter', $this->request->get['filter']);
                    }
                }
                if (!empty($details)) {
                    if (array_key_exists('folder_label', $this->request->get)) {
                        $folder = $this->request->get['folder_label'];
                        $this->out('folder_label', $folder);
                    }
                    else {
                        $folder = hex2bin($parts[2]);
                    }
                    $title = array('IMAP', $details['name'], $folder);
                    if ($this->get('list_page', 0)) {
                        $title[] = sprintf('Page %d', $this->get('list_page', 0));
                    }
                    $this->out('mailbox_list_title', $title);
                }
            }
            elseif ($path == 'sent') {
                $this->out('mailbox_list_title', array('Sent'));
                $this->out('per_source_limit', $this->user_config->get('sent_per_source_setting', DEFAULT_PER_SOURCE));
                $this->out('message_list_since', $this->user_config->get('sent_since_setting', DEFAULT_SINCE));
            }
            if (array_key_exists('sort', $this->request->get)) {
                if (in_array($this->request->get['sort'], array('arrival', 'from', 'subject',
                    'date', 'to', '-arrival', '-from', '-subject', '-date', '-to'), true)) {
                    $this->out('list_sort', $this->request->get['sort']);
                }
            } elseif ($default_sort_order = $this->user_config->get('default_sort_order_setting', false)) {
                $this->out('list_sort', $default_sort_order);
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
            $page_cache = $this->cache->get('imap_folders_'.$path);
            if (array_key_exists('imap_prefetch', $this->request->post)) {
                $prefetched = $this->session->get('imap_prefetched_ids', array());
                $prefetched[] = $form['imap_server_id'];
                $this->session->set('imap_prefetched_ids', array_unique($prefetched, SORT_NUMERIC));
            }
            if ($page_cache) {
                $this->out('imap_expanded_folder_data', $page_cache);
                $this->out('imap_expanded_folder_id', $form['imap_server_id']);
                $this->out('imap_expanded_folder_path', $path);
                return;
            }
            $cache = Hm_IMAP_List::get_cache($this->cache, $form['imap_server_id']);
            $imap = Hm_IMAP_List::connect($form['imap_server_id'], $cache);
            if (imap_authed($imap)) {
                $msgs = $imap->get_folder_list_by_level(hex2bin($folder));
                if (isset($msgs[$folder])) {
                    unset($msgs[$folder]);
                }
                $this->cache->set('imap_folders_'.$path, $msgs);
                $this->out('imap_expanded_folder_data', $msgs);
                $this->out('imap_expanded_folder_id', $form['imap_server_id']);
                $this->out('imap_expanded_folder_path', $path);
            }
            else {
                Hm_Msgs::add(sprintf('ERRCould not authenticate to the selected %s server', $imap->server_type));
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

        $filter = 'ALL';
        if ($this->get('list_filter')) {
            $filter = strtoupper($this->get('list_filter'));
        }
        $keyword = $this->get('list_keyword', '');
        list($sort, $rev) = process_sort_arg($this->get('list_sort'), $this->user_config->get('default_sort_order_setting', 'arrival'));
        $limit = $this->user_config->get('imap_per_page_setting', DEFAULT_PER_SOURCE);
        $offset = 0;
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
            $cache = Hm_IMAP_List::get_cache($this->cache, $form['imap_server_id']);
            $imap = Hm_IMAP_List::connect($form['imap_server_id'], $cache);
            if (imap_authed($imap)) {
                $this->out('imap_mailbox_page_path', $path);
                list($total, $results) = $imap->get_mailbox_page(hex2bin($form['folder']), $sort, $rev, $filter, $offset, $limit, $keyword);
                foreach ($results as $msg) {
                    $msg['server_id'] = $form['imap_server_id'];
                    $msg['server_name'] = $details['name'];
                    $msg['folder'] = $form['folder'];
                    $msgs[] = $msg;
                }
                if ($imap->selected_mailbox) {
                    $imap->selected_mailbox['detail']['exists'] = $total;
                    $this->out('imap_folder_detail', array_merge($imap->selected_mailbox, array('offset' => $offset, 'limit' => $limit)));
                }
                $this->out('folder_status', array('imap_'.$form['imap_server_id'].'_'.$form['folder'] => $imap->folder_state));
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
                $folders[$id] = $server['name'];
            }
        }
        $this->out('imap_folders', $folders);
    }
}

/**
 * Delete a message
 * @subpackage imap/handler
 */
class Hm_Handler_imap_delete_message extends Hm_Handler_Module {
    /**
     * Use IMAP to delete the selected message uid
     */
    public function process() {
        list($success, $form) = $this->process_form(array('imap_msg_uid', 'imap_server_id', 'folder'));
        if ($success) {
            $del_result = false;
            $cache = Hm_IMAP_List::get_cache($this->cache, $form['imap_server_id']);
            $imap = Hm_IMAP_List::connect($form['imap_server_id'], $cache);
            $trash_folder = false;
            $specials = $this->user_config->get('special_imap_folders', array());
            if (array_key_exists($form['imap_server_id'], $specials)) {
                if (array_key_exists('trash', $specials[$form['imap_server_id']])) {
                    if ($specials[$form['imap_server_id']]['trash']) {
                        $trash_folder = $specials[$form['imap_server_id']]['trash'];
                    }
                }
            }
            if (imap_authed($imap)) {
                if ($imap->select_mailbox(hex2bin($form['folder']))) {
                    $this->out('folder_status', array('imap_'.$form['imap_server_id'].'_'.$form['folder'] => $imap->folder_state));
                    if ($trash_folder && $trash_folder != hex2bin($form['folder'])) {
                        if ($imap->message_action('MOVE', array($form['imap_msg_uid']), $trash_folder)) {
                            $del_result = true;
                        }
                    }
                    else {
                        if ($imap->message_action('DELETE', array($form['imap_msg_uid']))) {
                            $del_result = true;
                            $imap->message_action('EXPUNGE', array($form['imap_msg_uid']));
                        }
                    }
                }
            }
            if (!$del_result) {
                Hm_Msgs::add('ERRAn error occurred trying to delete this message');
                $this->out('imap_delete_error', true);
            }
            else {
                Hm_Msgs::add('Message deleted');
                $this->out('imap_delete_error', false);
            }
            $msgs = Hm_Msgs::get();
            Hm_Msgs::flush();
            $this->session->secure_cookie($this->request, 'hm_msgs', base64_encode(json_encode($msgs)));
        }
    }
}


/**
 * Archive a message
 * @subpackage imap/handler
 */
class Hm_Handler_imap_archive_message extends Hm_Handler_Module {
    /**
     * Use IMAP to archive the selected message uid
     */
    public function process() {
        list($success, $form) = $this->process_form(array('imap_msg_uid', 'imap_server_id', 'folder'));

        if (!$success) {
            return;
        }
        $cache = Hm_IMAP_List::get_cache($this->cache, $form['imap_server_id']);
        $imap = Hm_IMAP_List::connect($form['imap_server_id'], $cache);
        $archive_folder = false;
        $errors = 0;

        $specials = $this->user_config->get('special_imap_folders', array());
        if (array_key_exists($form['imap_server_id'], $specials)) {
            if (array_key_exists('archive', $specials[$form['imap_server_id']])) {
                if ($specials[$form['imap_server_id']]['archive']) {
                    $archive_folder = $specials[$form['imap_server_id']]['archive'];
                }
            }
        }
        if (!$archive_folder) {
            Hm_Msgs::add('No archive folder configured for this IMAP server');
            $errors++;
        }

        if (!$errors && imap_authed($imap)) {
            $archive_exists = count($imap->get_mailbox_status($archive_folder));
            if (!$archive_exists) {
                Hm_Msgs::add('Configured archive folder for this IMAP server does not exist');
                $errors++;
            }

            /* select source folder */
            if ($errors || !$imap->select_mailbox(hex2bin($form['folder']))) {
                Hm_Msgs::add('ERRAn error occurred archiving the message');
                $errors++;
            }

            /* try to move the message */
            if (!$errors && $imap->message_action('MOVE', array($form['imap_msg_uid']), $archive_folder)) {
                Hm_Msgs::add("Message archived");
            }
            else {
                Hm_Msgs::add('ERRAn error occurred archiving the message');
            }
        }
        $msgs = Hm_Msgs::get();
        Hm_Msgs::flush();
        $this->session->secure_cookie($this->request, 'hm_msgs', base64_encode(json_encode($msgs)));
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
            $cache = Hm_IMAP_List::get_cache($this->cache, $form['imap_server_id']);
            $imap = Hm_IMAP_List::connect($form['imap_server_id'], $cache);
            if (imap_authed($imap)) {
                if ($imap->select_mailbox(hex2bin($form['folder']))) {
                    $this->out('folder_status', array('imap_'.$form['imap_server_id'].'_'.$form['folder'] => $imap->folder_state));
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
            if (in_array($form['action_type'], array('delete', 'read', 'unread', 'flag', 'unflag', 'archive'))) {
                $ids = process_imap_message_ids($form['message_ids']);
                $errs = 0;
                $msgs = 0;
                $moved = array();
                $status = array();
                $specials = $this->user_config->get('special_imap_folders', array());
                foreach ($ids as $server => $folders) {
                    $trash_folder = false;
                    $archive_folder = false;
                    if ($form['action_type'] == 'delete') {
                        if (array_key_exists($server, $specials)) {
                            if (array_key_exists('trash', $specials[$server])) {
                                if ($specials[$server]['trash']) {
                                    $trash_folder = $specials[$server]['trash'];
                                }
                            }
                        }
                    }
                    if ($form['action_type'] == 'archive') {
                        if (array_key_exists($server, $specials)) {
                            if (array_key_exists('archive', $specials[$server])) {
                                if ($specials[$server]['archive']) {
                                    $archive_folder = $specials[$server]['archive'];
                                }
                            }
                        }
                    }
                    $cache = Hm_IMAP_List::get_cache($this->cache, $server);
                    $imap = Hm_IMAP_List::connect($server, $cache);
                    if (imap_authed($imap)) {
                        foreach ($folders as $folder => $uids) {
                            if ($imap->select_mailbox(hex2bin($folder))) {
                                $status['imap_'.$server.'_'.$folder] = $imap->folder_state;

                                if ($form['action_type'] == 'delete' && $trash_folder && $trash_folder != hex2bin($folder)) {
                                    if (!$imap->message_action('MOVE', $uids, $trash_folder)) {
                                        $errs++;
                                    }
                                    else {
                                        foreach ($uids as $uid) {
                                            $moved[] = sprintf("imap_%d_%s_%s", $server, $uid, $folder);
                                        }
                                    }
                                }
                                elseif ($form['action_type'] == 'archive' && $archive_folder && $archive_folder != hex2bin($folder)) {
                                    if (!$imap->message_action('MOVE', $uids, $archive_folder)) {
                                        $errs++;
                                    }
                                    else {
                                        foreach ($uids as $uid) {
                                            $moved[] = sprintf("imap_%d_%s_%s", $server, $uid, $folder);
                                        }
                                    }
                                }
                                else {
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
                }
                if ($errs > 0) {
                    Hm_Msgs::add(sprintf('ERRAn error occurred trying to %s some messages!', $form['action_type'], $server));
                }
                $this->out('move_count', $moved);
                if (count($status) > 0) {
                    $this->out('folder_state', $status);
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
            $folder = bin2hex('INBOX');
            if (array_key_exists('folder', $this->request->post)) {
                $folder = $this->request->post['folder'];
            }
            list($status, $msg_list) = merge_imap_search_results($ids, 'ALL', $this->session, $this->cache, array(hex2bin($folder)), MAX_PER_SOURCE, array(array('SINCE', $date), array($fld, $terms)));
            $this->out('imap_search_results', $msg_list);
            $this->out('folder_status', $status);
            $this->out('imap_server_ids', $form['imap_server_ids']);
        }
    }
}

/**
 * Get message headers for the Sent page
 * @subpackage imap/handler
 */
class Hm_Handler_imap_sent extends Hm_Handler_Module {
    /**
     * Returns list of message data for the Everthing page
     */
    public function process() {
        list($success, $form) = $this->process_form(array('imap_server_ids'));
        if ($success) {
            $limit = $this->user_config->get('sent_per_source_setting', DEFAULT_PER_SOURCE);
            $date = process_since_argument($this->user_config->get('sent_since_setting', DEFAULT_SINCE));
            $ids = explode(',', $form['imap_server_ids']);
            $folder = bin2hex('INBOX');
            if (array_key_exists('folder', $this->request->post)) {
                $folder = $this->request->post['folder'];
            }
            if (hex2bin($folder) == 'SPECIAL_USE_CHECK' || hex2bin($folder) == 'INBOX') {
                list($status, $msg_list) = merge_imap_search_results($ids, 'ALL', $this->session, $this->cache, array(hex2bin($folder)), $limit, array(array('SINCE', $date)), true);
            }
            else {
                list($status, $msg_list) = merge_imap_search_results($ids, 'ALL', $this->session, $this->cache, array(hex2bin($folder)), $limit, array(array('SINCE', $date)), false);
            }
            $folders = array();
            foreach ($msg_list as $msg) {
                if (hex2bin($msg['folder']) != hex2bin($folder)) {
                    $folders[] = hex2bin($msg['folder']);
                }
            }
            if (count($folders) > 0) {
                $auto_folder = $folders[0];
                $this->out('auto_sent_folder', $msg_list[0]['server_name'].' '.$auto_folder);
            }
            $this->out('folder_status', $status);
            $this->out('imap_sent_data', $msg_list);
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
            $folder = bin2hex('INBOX');
            if (array_key_exists('folder', $this->request->post)) {
                $folder = $this->request->post['folder'];
            }
            list($status, $msg_list) = merge_imap_search_results($ids, 'ALL', $this->session, $this->cache, array(hex2bin($folder)), $limit, array(array('SINCE', $date)));
            $this->out('folder_status', $status);
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
            $folder = bin2hex('INBOX');
            if (array_key_exists('folder', $this->request->post)) {
                $folder = $this->request->post['folder'];
            }
            list($status, $msg_list) = merge_imap_search_results($ids, 'FLAGGED', $this->session, $this->cache, array(hex2bin($folder)), $limit, array(array('SINCE', $date)));
            $this->out('folder_status', $status);
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
                $cache = Hm_IMAP_List::get_cache($this->cache, $id);
                $start_time = microtime(true);
                $imap = Hm_IMAP_List::connect($id, $cache);
                $this->out('imap_connect_time', microtime(true) - $start_time);
                if (imap_authed($imap)) {
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
            $folder = bin2hex('INBOX');
            if (array_key_exists('folder', $this->request->post)) {
                $folder = $this->request->post['folder'];
            }
            list($status, $msg_list) = merge_imap_search_results($ids, 'UNSEEN', $this->session, $this->cache, array(hex2bin($folder)), $limit, array(array('SINCE', $date)));
            $this->out('folder_status', $status);
            $this->out('imap_unread_data', $msg_list);
            $this->out('imap_server_ids', $form['imap_server_ids']);
        }
    }
}

/**
 * Add a new JMAP server
 * @subpackage imap/handler
 */
class Hm_Handler_process_add_jmap_server extends Hm_Handler_Module {
    public function process() {
        /**
         * Used on the servers page to add a new JMAP server
         */
        if (isset($this->request->post['submit_jmap_server'])) {
            list($success, $form) = $this->process_form(array('new_jmap_name', 'new_jmap_address'));
            if (!$success) {
                $this->out('old_form', $form);
                Hm_Msgs::add('ERRYou must supply a name and a JMAP server URL');
                return;
            }
            $hidden = false;
            if (isset($this->request->post['new_jmap_hidden'])) {
                $hidden = true;
            }
            $parsed = parse_url($form['new_jmap_address']);
            if (array_key_exists('host', $parsed) && @get_headers($form['new_jmap_address'])) {

                Hm_IMAP_List::add(array(
                    'name' => $form['new_jmap_name'],
                    'server' => $form['new_jmap_address'],
                    'hide' => $hidden,
                    'type' => 'jmap',
                    'port' => false,
                    'tls' => false));
                Hm_Msgs::add('Added server!');
                $this->session->record_unsaved('JMAP server added');
            }
            else {
                Hm_Msgs::add('ERRCound not access supplied URL');
            }
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
                if (array_key_exists('tls', $this->request->post) && $this->request->post['tls']) {
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
        $cache = array();
        foreach ($servers as $index => $server) {
            if (isset($server['object']) && is_object($server['object'])) {
                if ($server['object']->use_cache) {
                    $cache[$index] = $server['object']->dump_cache('array');
                }
            }
        }
        if (count($cache) > 0) {
            foreach ($cache as $id => $data) {
                $this->cache->set('imap'.$id, $cache[$id]);
            }
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
            case 'sent':
                $callback = 'imap_sent_content';
                break;
            default:
                $callback = 'imap_background_unread_content';
                break;
        }
        if ($callback) {
            if ($callback != 'imap_background_unread_content') {
                $this->out('move_copy_controls', true);
            }
            if ($path == 'sent') {
                foreach (imap_sent_sources($callback, $this->user_config->get('special_imap_folders', array()),
                    $this->user_config->get('smtp_auto_bcc_setting', false)) as $vals) {
                    $this->append('data_sources', $vals);
                }
            }
            else {
                foreach (imap_data_sources($callback, $this->user_config->get('custom_imap_sources', array())) as $vals) {
                    if ($callback == 'imap_background_unread_content') {
                        $vals['group'] = 'background';
                    }
                    $this->append('data_sources', $vals);
                }
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
        $max = 0;
        foreach ($servers as $index => $server) {
            if ($this->session->loaded) {
                if (array_key_exists('expiration', $server)) {
                    $updated = true;
                    $server['expiration'] = 1;
                }
            }
            $new_servers[] = $server;
            $max = $index;
            Hm_IMAP_List::add($server, $index);
            if (array_key_exists('default', $server) && $server['default']) {
                $added = true;
            }
        }
        $max++;
        if ($updated) {
            $this->user_config->set('imap_servers', $new_servers);
        }
        if (!$added) {
            $auth_server = $this->session->get('imap_auth_server_settings', array());
            if (!empty($auth_server)) {
                if (array_key_exists('name', $auth_server)) {
                    $name = $auth_server['name'];
                }
                else {
                    $name = $this->config->get('imap_auth_name', 'Default');
                }
                Hm_IMAP_List::add(array( 
                    'name' => $name,
                    'default' => true,
                    'server' => $auth_server['server'],
                    'port' => $auth_server['port'],
                    'tls' => $auth_server['tls'],
                    'user' => $auth_server['username'],
                    'pass' => $auth_server['password']),
                $max);
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
 * Set IMAP server ids to prefetch on login
 * @subpackage imap/handler
 */
class Hm_Handler_prefetch_imap_folders extends Hm_Handler_Module {
    /**
     * Check for imap servers to prefetch
     */
    public function process() {

        $servers = array();
        foreach ($this->get('imap_servers', array()) as $index => $vals) {
            if (array_key_exists('user', $vals)) {
                $servers[$index] = $vals;
            }
        }
        if (count($servers) == 0) {
            return;
        }
        $fetched = $this->session->get('imap_prefetched_ids', array());
        $ids = array_keys($servers);
        if (count($fetched) > 0) {
            $ids = array_diff($ids, $fetched);
        }
        if (count($ids) > 0) {
            $this->out('prefetch_folder_ids', $ids);
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
        list($success, $form) = $this->process_form(array('imap_server_id'));
        if (!$success) {
            return;
        }
        $this->cache->del('imap'.$form['imap_server_id']);
        Hm_Debug::add(sprintf('Busted cache for IMAP server %s', $form['imap_server_id']));
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
            $cache = Hm_IMAP_List::get_cache($this->cache, $form['imap_server_id']);
            if ($success) {
                $imap = Hm_IMAP_List::connect($form['imap_server_id'], $cache, $form['imap_user'], $form['imap_pass']);
            }
            elseif (isset($form['imap_server_id'])) {
                $imap = Hm_IMAP_List::connect($form['imap_server_id'], $cache);
            }
            if ($imap) {
                if ($imap->get_state() == 'authenticated') {
                    Hm_Msgs::add(sprintf("Successfully authenticated to the %s server", $imap->server_type));
                }
                else {
                    Hm_Msgs::add(sprintf("ERRFailed to authenticate to the %s server", $imap->server_type));
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
                if (in_server_list('Hm_IMAP_List', $form['imap_server_id'], $form['imap_user'])) {
                    Hm_Msgs::add('ERRThis server and username are already configured');
                    return;
                }
                $cache = Hm_IMAP_List::get_cache($this->cache, $form['imap_server_id']);
                $imap = Hm_IMAP_List::connect($form['imap_server_id'], $cache, $form['imap_user'], $form['imap_pass'], true);
                if (imap_authed($imap)) {
                    $just_saved_credentials = true;
                    Hm_Msgs::add("Server saved");
                    $this->session->record_unsaved(sprintf('%s server saved', $imap->server_type));
                }
                else {
                    Hm_Msgs::add("ERRUnable to save this server, are the username and password correct?");
                    Hm_IMAP_List::forget_credentials($form['imap_server_id']);
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
            if (array_key_exists('imap_allow_images', $this->request->post) && $this->request->post['imap_allow_images']) {
                $this->out('imap_allow_images', true);
            }
            $this->out('header_allow_images', $this->config->get('allow_external_image_sources'));

            $cache = Hm_IMAP_List::get_cache($this->cache, $form['imap_server_id']);
            $imap = Hm_IMAP_List::connect($form['imap_server_id'], $cache);
            if (imap_authed($imap)) {
                $imap->read_only = $prefetch;
                if ($imap->select_mailbox(hex2bin($form['folder']))) {
                    $this->out('folder_status', array('imap_'.$form['imap_server_id'].'_'.$form['folder'] => $imap->folder_state));
                    $msg_struct = $imap->get_message_structure($form['imap_msg_uid']);
                    $this->out('msg_struct', $msg_struct);
                    if ($part !== false) {
                        if ($part == 0) {
                            $max = 500000;
                        }
                        else {
                            $max = false;
                        }
                        $struct = $imap->search_bodystructure($msg_struct, array('imap_part_number' => $part));
                        $msg_struct_current = array_shift($struct);
                        $msg_text = $imap->get_message_content($form['imap_msg_uid'], $part, $max, $msg_struct_current);
                    }
                    else {
                        if (!$this->user_config->get('text_only_setting', false)) {
                            list($part, $msg_text) = $imap->get_first_message_part($form['imap_msg_uid'], 'text', 'html', $msg_struct);
                            if (!$part) {
                                list($part, $msg_text) = $imap->get_first_message_part($form['imap_msg_uid'], 'text', false, $msg_struct);
                            }
                        }
                        else {
                            list($part, $msg_text) = $imap->get_first_message_part($form['imap_msg_uid'], 'text', false, $msg_struct);
                        }
                        $struct = $imap->search_bodystructure( $msg_struct, array('imap_part_number' => $part));
                        $msg_struct_current = array_shift($struct);
                        if (!trim($msg_text)) {
                            if (is_array($msg_struct_current) && array_key_exists('subtype', $msg_struct_current)) {
                                if ($msg_struct_current['subtype'] == 'plain') {
                                    $subtype = 'html';
                                }
                                else {
                                    $subtype = 'plain';
                                }
                                list($part, $msg_text) = $imap->get_first_message_part($form['imap_msg_uid'], 'text', $subtype, $msg_struct);
                                $struct = $imap->search_bodystructure($msg_struct, array('imap_part_number' => $part));
                                $msg_struct_current = array_shift($struct);
                            }
                        }
                    }
                    if (isset($msg_struct_current['subtype']) && strtolower($msg_struct_current['subtype'] == 'html')) {
                        $msg_text = add_attached_images($msg_text, $form['imap_msg_uid'], $msg_struct, $imap);
                    }
                    $save_reply_text = false;
                    if ($part == 0 || (isset($msg_struct_current['type']) && strtolower($msg_struct_current['type'] == 'text'))) {
                        $save_reply_text = true;
                    }
                    $msg_headers = $imap->get_message_headers($form['imap_msg_uid']);
                    $this->out('list_headers', get_list_headers($msg_headers));
                    $this->out('msg_headers', $msg_headers);
                    $this->out('imap_prefecth', $prefetch);
                    $this->out('imap_msg_part', "$part");
                    $this->out('use_message_part_icons', $this->user_config->get('msg_part_icons_setting', false));
                    $this->out('simple_msg_part_view', $this->user_config->get('simple_msg_parts_setting', false));
                    if ($msg_struct_current) {
                        $this->out('msg_struct_current', $msg_struct_current);
                    }
                    $this->out('msg_text', $msg_text);
                    $this->out('msg_download_args', sprintf("page=message&amp;uid=%s&amp;list_path=imap_%d_%s&amp;imap_download_message=1", $form['imap_msg_uid'], $form['imap_server_id'], $form['folder']));
                    if (!$prefetch) {
                        clear_existing_reply_details($this->session);
                        if ($part == 0) {
                            $msg_struct_current['type'] = 'text';
                            $msg_struct_current['subtype'] = 'plain';
                        }
                        $this->session->set(sprintf('reply_details_imap_%d_%s_%s', $form['imap_server_id'], $form['folder'], $form['imap_msg_uid']),
                            array('msg_struct' => $msg_struct_current, 'msg_text' => ($save_reply_text ? $msg_text : ''), 'msg_headers' => $msg_headers));
                    }
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
                $this->session->record_unsaved(sprintf('%s server hidden status updated', imap_server_type($form['imap_server_id'])));
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
                    $this->session->record_unsaved(sprintf('%s server deleted', imap_server_type($form['imap_server_id'])));
                }
            }
            else {
                $this->out('old_form', $form);
            }
        }
    }
}
