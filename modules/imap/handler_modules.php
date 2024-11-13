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
            'size' => mb_strlen($content)
        );
        $draft_id = next_draft_key($this->session);
        // This needs to be replaced with something that works with the new attachment
        // code.
        //attach_file($content, $file, $filepath, $draft_id, $this);
        $this->out('compose_draft_id', $draft_id);
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
        process_site_setting('imap_per_page', $this, 'max_source_setting_callback', DEFAULT_IMAP_PER_PAGE);
    }
}

/**
 * Process input from the max google contacts input in the settings page
 * @subpackage imap/handler
 */
class Hm_Handler_process_max_google_contacts_number extends Hm_Handler_Module {
    /**
     * Allowed values are greater than zero and less than MAX_PER_SOURCE
     */
    public function process() {
        process_site_setting('max_google_contacts_number', $this, 'max_source_setting_callback', DEFAULT_MAX_GOOGLE_CONTACTS_NUMBER);
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
        process_site_setting('sent_per_source', $this, 'max_source_setting_callback', DEFAULT_SENT_PER_SOURCE);
    }
}

/**
 * Process input from archive to original folder setting archive page in the settings page
 * @subpackage imap/handler
 */
class Hm_Handler_process_original_folder_setting extends Hm_Handler_Module {
    /**
     * Allowed values are true and false
     */
    public function process() {
        function original_folder_callback($val) {
            return $val;
        }
        process_site_setting('original_folder', $this, 'original_folder_callback', false, true);
    }
}

/**
 * Process "unread_on_open" setting for the message view page in the settings page
 * @subpackage imap/handler
 */
class Hm_Handler_process_unread_on_open extends Hm_Handler_Module {
    /**
     * valid values are true or false
     */
    public function process() {
        function unread_on_open_callback($val) {
            return $val;
        }
        process_site_setting('unread_on_open', $this, 'unread_on_open_callback', false, true);
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
        process_site_setting('simple_msg_parts', $this, 'simple_msg_view_callback', DEFAULT_SIMPLE_MSG_PARTS, true);
    }
}

/**
 * Process "pagination links" setting for the message view page in the settings page
 * @subpackage imap/handler
 */
class Hm_Handler_process_pagination_links extends Hm_Handler_Module {
    /**
     * valid values are true or false
     */
    public function process() {
        function pagination_links_callback($val) {
            return $val;
        }
        process_site_setting('pagination_links', $this, 'pagination_links_callback', DEFAULT_PAGINATION_LINKS, true);
    }
}

/**
 * Process "auto_advance_email" setting for loading the next email instead of returning to inbox in the settings page
 * @subpackage imap/handler
 */
class Hm_Handler_process_auto_advance_email_setting extends Hm_Handler_Module {
    /**
     * valid values are true or false
     */
    public function process() {
        function auto_advance_email_callback($val) {
            return $val;
        }
        process_site_setting('auto_advance_email', $this, 'auto_advance_email_callback', true, true);
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
        process_site_setting('msg_part_icons', $this, 'msg_part_icons_callback', DEFAULT_MSG_PART_ICONS, true);
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
        process_site_setting('text_only', $this, 'text_only_callback', DEFAULT_TEXT_ONLY, true);
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
        process_site_setting('sent_since', $this, 'since_setting_callback',DEFAULT_SENT_SINCE);
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
            $screen = false;
            $parts = explode("_", $this->request->get['list_path']);
            $imap_server_id = $parts[1] ?? '';
            $cache = Hm_IMAP_List::get_cache($this->cache, $imap_server_id);
            $imap = Hm_IMAP_List::connect($imap_server_id, $cache);
            if ($form['imap_move_action'] == "screen_mail") {
                $form['imap_move_action'] = "move";
                $screen = true;
                $screen_folder = 'Screen emails';
                if (!count($imap->get_mailbox_status($screen_folder))) {
                    $imap->create_mailbox($screen_folder);
                }
                $form['imap_move_to'] = $parts[0] ."_". $parts[1] ."_".bin2hex($screen_folder);
            }

            list($msg_ids, $dest_path, $same_server_ids, $other_server_ids) = process_move_to_arguments($form);
            $moved = array();
            if (count($same_server_ids) > 0) {
                $moved = array_merge($moved, imap_move_same_server($same_server_ids, $form['imap_move_action'], $this->cache, $dest_path, $screen));
            }
            if (count($other_server_ids) > 0) {
                $moved = array_merge($moved, imap_move_different_server($other_server_ids, $form['imap_move_action'], $dest_path, $this->cache));

            }
            if (count($moved) > 0 && count($moved) == count($msg_ids)) {
                if ($form['imap_move_action'] == 'move') {
                    if ($screen) {
                        Hm_Msgs::add('Emails moved to Screen email folder');
                    } else {
                        Hm_Msgs::add('Messages moved');
                    }
                }
                else {
                    Hm_Msgs::add('Messages copied');
                }
            }
            elseif (count($moved) > 0) {
                if ($form['imap_move_action'] == 'move') {
                    if ($screen) {
                        Hm_Msgs::add('Some Emails moved to Screen email folder');
                    } else {
                        Hm_Msgs::add('Some messages moved (only IMAP message types can be moved)');
                    }
                }
                else {
                    Hm_Msgs::add('Some messages copied (only IMAP message types can be copied)');
                }
            }
            elseif (count($moved) == 0) {
                Hm_Msgs::add('ERRUnable to move/copy selected messages');
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
            $specials = get_special_folders($this, $imap_id);
            if (array_key_exists('sent', $specials) && $specials['sent']) {
                $sent_folder = $specials['sent'];
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
                if ($imap->append_start($sent_folder, mb_strlen($msg), true)) {
                    $imap->append_feed($msg."\r\n");
                    if (!$imap->append_end()) {
                        Hm_Msgs::add('ERRAn error occurred saving the sent message');
                    }
                }
                $uid = null;
                $mailbox_page = $imap->get_mailbox_page($sent_folder, 'ARRIVAL', true, 'ALL', 0, 10);
                foreach ($mailbox_page[1] as $mail) {
                    $msg_header = $imap->get_message_headers($mail['uid']);
                    if ($msg_header['Message-Id'] === $mime->get_headers()['Message-Id']) {
                        $uid = $mail['uid'];
                        break;
                    }
                }
                if ($uid && $this->user_config->get('review_sent_email_setting', false)) {
                    $this->out('redirect_url', '?page=message&uid='.$uid.'&list_path=imap_'.$imap_id.'_'.bin2hex($sent_folder));
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
        if ($this->get('msg_next_link') && !$this->user_config->get('review_sent_email_setting', false)) {
            $this->out('redirect_url', htmlspecialchars_decode($this->get('msg_next_link')));
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
                $sources[$form['list_path']] = 'remove';
                Hm_Msgs::add('Folder removed from combined pages');
                $this->session->record_unsaved('Removed folder from combined pages');
            }
            $this->session->set('custom_imap_sources', $sources, true);
        }
    }
}

/**
 * Stream a message from IMAP to the browser and show it
 * @subpackage imap/handler
 */
class Hm_Handler_imap_show_message extends Hm_Handler_Module {
    public function process() {
        if (array_key_exists('imap_show_message', $this->request->get) && $this->request->get['imap_show_message']) {

            $server_id = NULL;
            $uid = NULL;
            $folder = NULL;
            $msg_id = NULL;

            if (array_key_exists('uid', $this->request->get) && $this->request->get['uid']) {
                $uid = $this->request->get['uid'];
            }
            if (array_key_exists('list_path', $this->request->get) && preg_match("/^imap_(\w+)_(.+)/", $this->request->get['list_path'], $matches)) {
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
                                $encoding = trim(mb_strtolower($part_struct['encoding']));
                            }
                            $stream_size = $imap->start_message_stream($uid, $msg_id);
                            if ($stream_size > 0) {
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
 * Stream a message from IMAP to the browser
 * @subpackage imap/handler
 */
class Hm_Handler_imap_download_message extends Hm_Handler_Module {
    /**
     * Download a message from the IMAP server
     */
    public function process() {
        if (array_key_exists('imap_download_message', $this->request->get) && $this->request->get['imap_download_message']) {

            list($server_id, $uid, $folder, $msg_id) = get_request_params($this->request->get);
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
                                $encoding = trim(mb_strtolower($part_struct['encoding']));
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
            if (preg_match("/^imap_\w+_.+$/", $path)) {
                $this->out('list_meta', false, false);
                $this->out('list_path', $path, false);
                $this->out('move_copy_controls', true);
                $parts = explode('_', $path, 3);
                $details = Hm_IMAP_List::dump($parts[1]);
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
                $folder = hex2bin($parts[2]);
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

                if ($this->module_is_supported("contacts") && $folder == 'INBOX') {
                    $this->out('folder', $folder);
                    $this->out('screen_emails', isset($this->request->get['screen_emails']));
                    $this->out('first_time_screen_emails', $this->user_config->get('first_time_screen_emails_setting', DEFAULT_PER_SOURCE));
                    $this->out('move_messages_in_screen_email', $this->user_config->get('move_messages_in_screen_email_setting', DEFAULT_PER_SOURCE));
                }
            }
            elseif ($path == 'sent') {
                $this->out('mailbox_list_title', array('Sent'));
                $this->out('per_source_limit', $this->user_config->get('sent_per_source_setting', DEFAULT_SENT_PER_SOURCE));
                $this->out('message_list_since', $this->user_config->get('sent_since_setting', DEFAULT_SENT_SINCE));
                $this->out('custom_list_controls_type', 'add');
                if (array_key_exists('keyword', $this->request->get)) {
                    $this->out('list_keyword', $this->request->get['keyword']);
                }
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
 * Delete an attachment on the server
 * @subpackage imap/handler
 */
class Hm_Handler_imap_remove_attachment extends Hm_Handler_Module {
    public function process() {
        if (array_key_exists('imap_remove_attachment', $this->request->get) && $this->request->get['imap_remove_attachment']) {
            list($server_id, $uid, $folder, $msg_id) = get_request_params($this->request->get);
            if ($server_id !== NULL && $uid !== NULL && $folder !== NULL && $msg_id !== NULL) {
                $cache = Hm_IMAP_List::get_cache($this->cache, $server_id);
                $imap = Hm_IMAP_List::connect($server_id, $cache);
                if (imap_authed($imap)) {
                    if ($imap->select_mailbox($folder)) {
                        $msg = $imap->get_message_content($uid, 0, false, false);
                        if ($msg) {
                            $attachment_id = get_attachment_id_for_mail_parser($imap, $uid, $this->request->get['imap_msg_part']);
                            if ($attachment_id !== false) {
                                $msg = remove_attachment($attachment_id, $msg);
                                if ($imap->append_start($folder, mb_strlen($msg))) {
                                    $imap->append_feed($msg."\r\n");
                                    if ($imap->append_end()) {
                                        if ($imap->message_action('DELETE', array($uid))) {
                                            $imap->message_action('EXPUNGE', array($uid));
                                            Hm_Msgs::add('Attachment deleted');
                                            $this->out('redirect_url', '?page=message_list&list_path='.$this->request->get['list_path']);
                                            return;
                                        }
                                    }
                                }
                            }

                        }
                    }
                }
            }
            Hm_Msgs::add('ERRAn Error occurred trying to remove attachment to the message');
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
            $path = sprintf("imap_%s_%s", $form['imap_server_id'], $folder);
            $page_cache = $this->cache->get('imap_folders_'.$path);
            if (array_key_exists('imap_prefetch', $this->request->post)) {
                $prefetched = $this->session->get('imap_prefetched_ids', array());
                $prefetched[] = $form['imap_server_id'];
                $this->session->set('imap_prefetched_ids', array_unique($prefetched, SORT_STRING));
            }
            $with_subscription = isset($this->request->post['subscription_state']) && $this->request->post['subscription_state'];
            $cache = Hm_IMAP_List::get_cache($this->cache, $form['imap_server_id']);
            $imap = Hm_IMAP_List::connect($form['imap_server_id'], $cache);
            $this->out('can_share_folders', stripos($imap->get_capability(), 'ACL') !== false);
            if (imap_authed($imap)) {
                $quota_root = $imap->get_quota_root($folder ? $folder : 'INBOX');
                if ($quota_root && isset($quota_root[0]['name'])) {
                    $quota = $imap->get_quota($quota_root[0]['name']);
                    if ($quota) {
                        $current = floatval($quota[0]['current']);
                        $max = floatval($quota[0]['max']);
                        if ($max > 0) {
                            $this->out('quota', ceil(($current / $max) * 100));
                            $this->out('quota_max', $max / 1024);
                        }
                    }
                }
            }
            if ($page_cache) {
                $this->out('imap_expanded_folder_data', $page_cache);
                $this->out('imap_expanded_folder_id', $form['imap_server_id']);
                $this->out('imap_expanded_folder_path', $path);
                $this->out('with_input', $with_subscription);
                $this->out('folder', $folder);
                return;
            }
            if (imap_authed($imap)) {
                $only_subscribed = $this->user_config->get('only_subscribed_folders_setting', false);
                if ($with_subscription) {
                    $only_subscribed = false;
                }
                $msgs = $imap->get_folder_list_by_level(hex2bin($folder), $only_subscribed, $with_subscription);
                if (isset($msgs[$folder])) {
                    unset($msgs[$folder]);
                }
                $this->cache->set('imap_folders_'.$path, $msgs);
                $this->out('imap_expanded_folder_data', $msgs);
                $this->out('imap_expanded_folder_id', $form['imap_server_id']);
                $this->out('imap_expanded_folder_path', $path);
                $this->out('with_input', $with_subscription);
                $this->out('folder', $folder);
            }
            else {
                Hm_Msgs::add(sprintf('ERRCould not authenticate to the selected %s server (%s)', $imap->server_type, $this->user_config->get('imap_servers')[$form['imap_server_id']]['user']));
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
            $filter = mb_strtoupper($this->get('list_filter'));
        }
        $keyword = $this->get('list_keyword', '');
        list($sort, $rev) = process_sort_arg($this->get('list_sort'), $this->user_config->get('default_sort_order_setting', 'arrival'));
        $limit = $this->user_config->get('imap_per_page_setting', DEFAULT_PER_SOURCE);
        $offset = 0;
        $msgs = array();
        $list_page = 1;
        $include_preview = $this->user_config->get('active_preview_message_setting', false);

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
            $path = sprintf("imap_%s_%s", $form['imap_server_id'], $form['folder']);
            $details = Hm_IMAP_List::dump($form['imap_server_id']);
            $cache = Hm_IMAP_List::get_cache($this->cache, $form['imap_server_id']);
            $imap = Hm_IMAP_List::connect($form['imap_server_id'], $cache);
            if (imap_authed($imap)) {
                $this->out('imap_mailbox_page_path', $path);
                if (isset($this->request->get['screen_emails']) && hex2bin($form['folder']) == 'INBOX' && $this->module_is_supported("contacts")) {
                    $contacts = $this->get('contact_store');
                    $contact_list = $contacts->getAll();

                    $existingEmails = array_map(function($c){
                        return $c->value('email_address');
                    },$contact_list);
                    list($total, $results) = $imap->get_mailbox_page(hex2bin($form['folder']), $sort, $rev, $filter, $offset, $limit, $keyword, $existingEmails, $include_preview);
                } else {
                    list($total, $results) = $imap->get_mailbox_page(hex2bin($form['folder']), $sort, $rev, $filter, $offset, $limit, $keyword, null, $include_preview);
                }
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
            $this->out('do_not_flag_as_read_on_open', $this->user_config->get('unread_on_open_setting', false));
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
            $specials = get_special_folders($this, $form['imap_server_id']);
            if (array_key_exists('trash', $specials) && $specials['trash']) {
                $trash_folder = $specials['trash'];
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

        $specials = get_special_folders($this, $form['imap_server_id']);
        if (array_key_exists('archive', $specials) && $specials['archive']) {
            $archive_folder = $specials['archive'];
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

            $form_folder = hex2bin($form['folder']);

            /* select source folder */
            if ($errors || !$imap->select_mailbox($form_folder)) {
                Hm_Msgs::add('ERRAn error occurred archiving the message');
                $errors++;
            }

            /* path according to original option setting */
            if ($this->user_config->get('original_folder_setting', false)) {
                $archive_folder .= '/'.$form_folder;
                if (!count($imap->get_mailbox_status($archive_folder))) {
                    if (! $imap->create_mailbox($archive_folder)) {
                        $debug = $imap->show_debug(true, true, true);
                        if (! empty($debug['debug'])) {
                            Hm_Msgs::add('ERR' . array_pop($debug['debug']));
                        } else {
                            Hm_Msgs::add('ERRCould not create configured archive folder for the original folder of the message');
                        }
                        $errors++;
                    }
                }
            }

            /* try to move the message */
            if (!$errors && $imap->message_action('MOVE', array($form['imap_msg_uid']), $archive_folder)) {
                Hm_Msgs::add("Message archived");
            }
            else {
                Hm_Msgs::add('ERRAn error occurred archiving the message');
            }
        }
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
 * Snooze message
 * @subpackage imap/handler
 */
class Hm_Handler_imap_snooze_message extends Hm_Handler_Module {
    /**
     * Use IMAP to snooze the selected message uid
     */
    public function process() {
        list($success, $form) = $this->process_form(array('imap_snooze_ids', 'imap_snooze_until'));
        if (!$success) {
            return;
        }
        $snoozed_messages = 0;
        $snooze_tag = null;
        if ($form['imap_snooze_until'] != 'unsnooze') {
            $at = date('D, d M Y H:i:s O');
            $until = get_snooze_date($form['imap_snooze_until']);
            $snooze_tag = "X-Snoozed: at $at; until $until";
        }
        $ids = explode(',', $form['imap_snooze_ids']);
        foreach ($ids as $msg_part) {
            list($imap_server_id, $msg_id, $folder) = explode('_', $msg_part);
            $cache = Hm_IMAP_List::get_cache($this->cache, $imap_server_id);
            $imap = Hm_IMAP_List::connect($imap_server_id, $cache);
            if (imap_authed($imap)) {
                $folder = hex2bin($folder);
                if (snooze_message($imap, $msg_id, $folder, $snooze_tag)) {
                    $snoozed_messages++;
                }
            }
        }
        $this->out('snoozed_messages', $snoozed_messages);
        if ($snoozed_messages == count($ids)) {
            $msg = 'Messages snoozed';
        } elseif ($snoozed_messages > 0) {
            $msg = 'Some messages have been snoozed';
        } else {
            $msg = 'ERRFailed to snooze selected messages';
        }
        Hm_Msgs::add($msg);
    }
}

/**
 * Add tag/label to message
 * @subpackage imap/handler
 */
class Hm_Handler_imap_add_tag_message extends Hm_Handler_Module {
    /**
     * Use IMAP to tag the selected message uid
     */
    public function process() {
        list($success, $form) = $this->process_form(array('tag_id', 'imap_server_ids'));
        if (!$success) {
            return;
        }
        $taged_messages = 0;
        $ids = explode(',', $form['imap_server_ids']);
        foreach ($ids as $msg_part) {
            list($imap_server_id, $msg_id, $folder) = explode('_', $msg_part);
            $cache = Hm_IMAP_List::get_cache($this->cache, $imap_server_id);
            $imap = Hm_IMAP_List::connect($imap_server_id, $cache);
            if (imap_authed($imap)) {
                $folder = hex2bin($folder);
                if (add_tag_to_message($imap, $msg_id, $folder, $form['tag_id'])) {
                    $taged_messages++;
                }
            }
        }
        $this->out('taged_messages', $taged_messages);
        if ($taged_messages == count($ids)) {
            $msg = 'Tag added';
        } elseif ($taged_messages > 0) {
            $msg = 'Some messages have been taged';
        } else {
            $msg = 'ERRFailed to tag selected messages';
        }
        Hm_Msgs::add($msg);
    }
}

/**
 * Unsnooze messages
 * @subpackage imap/handler
 */
class Hm_Handler_imap_unsnooze_message extends Hm_Handler_Module {
    /**
     * Use IMAP unsnooze messages in snoozed directory
     * This should use cron
     */
    public function process() {
        $servers = Hm_IMAP_List::dump();
        foreach (array_keys($servers) as $server_id) {
            $cache = Hm_IMAP_List::get_cache($this->cache, $server_id);
            $imap = Hm_IMAP_List::connect($server_id, $cache);
            if (imap_authed($imap)) {
                $folder = 'Snoozed';
                if (!count($imap->get_mailbox_status($folder))) {
                    continue;
                }
                $ret = $imap->get_mailbox_page($folder, 'DATE', false, 'ALL');
                foreach ($ret[1] as $msg) {
                    $msg_headers = $imap->get_message_headers($msg['uid']);
                    if (isset($msg_headers['X-Snoozed'])) {
                        try {
                            $snooze_headers = parse_snooze_header($msg_headers['X-Snoozed']);
                            if (new DateTime($snooze_headers['until']) <= new DateTime()) {
                                snooze_message($imap, $msg['uid'], $folder, null);
                            }
                        } catch (Exception $e) {
                            Hm_Debug::add(sprintf('ERR Cannot unsnooze message: %s', $msg_headers['subject']));
                        }
                    }
                }
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
                foreach ($ids as $server => $folders) {
                    $specials = get_special_folders($this, $server);
                    $trash_folder = false;
                    $archive_folder = false;
                    $cache = Hm_IMAP_List::get_cache($this->cache, $server);
                    $imap = Hm_IMAP_List::connect($server, $cache);
                    if (imap_authed($imap)) {
                        $server_details = $this->user_config->get('imap_servers')[$server];
                        if ($form['action_type'] == 'delete') {
                            if (array_key_exists('trash', $specials)) {
                                if ($specials['trash']) {
                                    $trash_folder = $specials['trash'];
                                } else {
                                    Hm_Msgs::add(sprintf('ERRNo trash folder configured for %s', $server_details['name']));
                                }
                            }
                        }
                        if ($form['action_type'] == 'archive') {
                            if(array_key_exists('archive', $specials)) {
                                if($specials['archive']) {
                                    $archive_folder = $specials['archive'];
                                } else {
                                    Hm_Msgs::add(sprintf('ERRNo archive folder configured for %s', $server_details['name']));
                                }
                            }
                        }

                        foreach ($folders as $folder => $uids) {
                            if ($imap->select_mailbox(hex2bin($folder))) {
                                $status['imap_'.$server.'_'.$folder] = $imap->folder_state;

                                if ($form['action_type'] == 'delete' && $trash_folder && $trash_folder != hex2bin($folder)) {
                                    if (!$imap->message_action('MOVE', $uids, $trash_folder)) {
                                        $errs++;
                                    }
                                    else {
                                        foreach ($uids as $uid) {
                                            $moved[] = sprintf("imap_%s_%s_%s", $server, $uid, $folder);
                                        }
                                    }
                                }
                                elseif ($form['action_type'] == 'archive' && $archive_folder && $archive_folder != hex2bin($folder)) {
                                    /* path according to original option setting */
                                    if ($this->user_config->get('original_folder_setting', false)) {
                                        $archive_folder .= '/'.hex2bin($folder);
                                        $dest_path_exists = count($imap->get_mailbox_status($archive_folder));
                                        if (!$dest_path_exists) {
                                            $imap->create_mailbox($archive_folder);
                                        }
                                    }
                                    if (!$imap->message_action('MOVE', $uids, $archive_folder)) {
                                        $errs++;
                                    }
                                    else {
                                        foreach ($uids as $uid) {
                                            $moved[] = sprintf("imap_%s_%s_%s", $server, $uid, $folder);
                                        }
                                    }
                                }
                                else {
                                    if (!$imap->message_action(mb_strtoupper($form['action_type']), $uids)) {
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
            $since = $this->session->get('search_since', DEFAULT_SEARCH_SINCE);
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
            $ids = explode(',', $form['imap_server_ids']);
            $folder = bin2hex('INBOX');
            if (array_key_exists('folder', $this->request->post)) {
                $folder = $this->request->post['folder'];
            }
            $folders = array($folder);
        } else {
            $data_sources = imap_data_sources('', $this->session->get('custom_imap_sources', user:true));
            $ids = array_map(function($ds) { return $ds['id']; }, $data_sources);
            $folders = array_map(function($ds) { return $ds['folder']; }, $data_sources);
        }

        if (array_key_exists('list_path', $this->request->get) && $this->request->get['list_path'] == 'email') {
            $limit = $this->user_config->get('all_email_per_source_setting', DEFAULT_ALL_EMAIL_PER_SOURCE);
            $date = process_since_argument($this->user_config->get('all_email_since_setting', DEFAULT_ALL_EMAIL_SINCE));
        }
        else {
            $limit = $this->user_config->get('all_per_source_setting', DEFAULT_ALL_PER_SOURCE);
            $date = process_since_argument($this->user_config->get('all_since_setting', DEFAULT_ALL_SINCE));
        }
        list($status, $msg_list) = merge_imap_search_results($ids, 'ALL', $this->session, $this->cache, array_map(fn ($folder) => hex2bin($folder), $folders), $limit, array(array('SINCE', $date)));
        
        $this->out('folder_status', $status);
        $this->out('imap_combined_inbox_data', $msg_list);
        $this->out('imap_server_ids', implode(',', $ids));
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
            $ids = explode(',', $form['imap_server_ids']);
            $folder = bin2hex('INBOX');
            if (array_key_exists('folder', $this->request->post)) {
                $folder = $this->request->post['folder'];
            }
            $folders = array($folder);
        } else {
            $data_sources = imap_data_sources('');
            $ids = array_map(function($ds) { return $ds['id']; }, $data_sources);
            $folders = array_map(function($ds) { return $ds['folder']; }, $data_sources);
        }
        $limit = $this->user_config->get('flagged_per_source_setting', DEFAULT_FLAGGED_PER_SOURCE);
        $date = process_since_argument($this->user_config->get('flagged_since_setting', DEFAULT_FLAGGED_SINCE));
        list($status, $msg_list) = merge_imap_search_results($ids, 'FLAGGED', $this->session, $this->cache, array_map(fn ($folder) => hex2bin($folder), $folders), $limit, array(array('SINCE', $date)));
        $this->out('folder_status', $status);
        $this->out('imap_flagged_data', $msg_list);
        $this->out('imap_server_ids', implode(',', $ids));
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
                    $this->out('imap_capabilities_list', $imap->get_capability());
                    $this->out('imap_connect_status', $imap->get_state());
                    $this->out('imap_status_server_id', $id);
                }
                else {
                    $this->out('imap_capabilities_list', "");
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
            $ids = explode(',', $form['imap_server_ids']);
            $folder = bin2hex('INBOX');
            if (array_key_exists('folder', $this->request->post)) {
                $folder = $this->request->post['folder'];
            }
            $folders = array($folder);
        } else {
            $data_sources = imap_data_sources('');
            $ids = array_map(function($ds) { return $ds['id']; }, $data_sources);
            $folders = array_map(function($ds) { return $ds['folder']; }, $data_sources);
        }

        $limit = $this->user_config->get('unread_per_source_setting', DEFAULT_UNREAD_PER_SOURCE);
        $date = process_since_argument($this->user_config->get('unread_since_setting', DEFAULT_UNREAD_SINCE));
        $msg_list = array();
        list($status, $msg_list) = merge_imap_search_results($ids, 'UNSEEN', $this->session, $this->cache, array_map(fn ($folder) => hex2bin($folder), $folders), $limit, array(array('SINCE', $date)));
        $this->out('folder_status', $status);
        $this->out('imap_unread_data', $msg_list);
        $this->out('imap_server_ids', implode(',', $ids));
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
                Hm_Msgs::add('ERRCould not access supplied URL');
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
            list($success, $form) = $this->process_form(
                array('new_imap_name',
                    'new_imap_address',
                    'new_imap_port')
            );
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
                if ($con = fsockopen($form['new_imap_address'], $form['new_imap_port'], $errno, $errstr, 5)) {
                    $imap_list = array(
                        'name' => $form['new_imap_name'],
                        'server' => $form['new_imap_address'],
                        'hide' => $hidden,
                        'port' => $form['new_imap_port'],
                        'tls' => $tls);

                    if (isset($this->request->post['sieve_config_host']) && $this->request->post['sieve_config_host']) {
                        $imap_list['sieve_config_host'] = $this->request->post['sieve_config_host'];
                    }
                    Hm_IMAP_List::add($imap_list);
                    Hm_Msgs::add('Added server!');
                    $this->session->record_unsaved('IMAP server added');
                }
                else {
                    Hm_Msgs::add(sprintf('ERRCould not add server: %s', $errstr));
                }
            }
        }
        $this->out('is_jmap_supported', $this->module_is_supported('jmap'));
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
        Hm_IMAP_List::save();
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
                $callback = 'imap_folder_content';
                break;
            case 'junk':
                $callback = 'imap_folder_content';
                break;
            case 'trash':
                $callback = 'imap_folder_content';
                break;
            case 'drafts':
                $callback = 'imap_folder_content';
                break;
            case 'tag':
                $callback = 'imap_tag_content';
                break;
            default:
                $callback = 'imap_background_unread_content';
                break;
        }
        if ($callback) {
            if ($callback != 'imap_background_unread_content') {
                $this->out('move_copy_controls', true);
            }
            if (in_array($path, ['sent', 'junk', 'trash', 'drafts'])) {
                foreach (imap_sources($callback, $this, $path) as $vals) {
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
 * Load IMAP servers permissions for shared folders
 * @subpackage imap/handler
 */
class Hm_Handler_load_imap_folders_permissions extends Hm_Handler_Module {
    /**
     * Output IMAP server permissions array for shared folders
     */
    public function process() {
        list($success, $form) = $this->process_form(array('imap_server_id','imap_folder_uid','imap_folder'));
        
        if ($success && !empty($form['imap_server_id']) && !empty($form['imap_folder'])  && !empty($form['imap_folder_uid'])) {
            Hm_IMAP_List::init($this->user_config, $this->session);
            $server = Hm_IMAP_List::dump($form['imap_server_id'], true);
            $cache = Hm_IMAP_List::get_cache($this->cache, $form['imap_server_id']);
            
            $imap = Hm_IMAP_List::connect($form['imap_server_id'], $cache, $server['user'], $server['pass']);
            $permissions = $imap->get_acl($form['imap_folder']);
            $this->out('imap_folders_permissions', $permissions);
        }
    }
}

/**
 * Load IMAP servers permissions for shared folders
 * @subpackage imap/handler
 */
class Hm_Handler_set_acl_to_imap_folders extends Hm_Handler_Module {
    /**
     * Output IMAP server permissions array for shared folders
     */
    public function process() {
        list($success, $form) = $this->process_form(array('imap_server_id','imap_folder','identifier','permissions','action'));
        
        if ($success && !empty($form['imap_server_id']) && !empty($form['identifier'])  && !empty($form['permissions']) && !empty($form['action'])) {

            Hm_IMAP_List::init($this->user_config, $this->session);
            $server = Hm_IMAP_List::dump($form['imap_server_id'], true);
            $cache = Hm_IMAP_List::get_cache($this->cache, $form['imap_server_id']);
            
            $imap = Hm_IMAP_List::connect($form['imap_server_id'], $cache, $server['user'], $server['pass']);
            if($form['action'] === 'add') {
                $response = $imap->set_acl($form['imap_folder'], $form['identifier'], $form['permissions']);
            } else {
                $response = $imap->delete_acl($form['imap_folder'], $form['identifier']);
            }
            if($response) {
                $permissions = $imap->get_acl($form['imap_folder']);
                $this->out('imap_folders_permissions', $permissions);
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
        Hm_IMAP_List::init($this->user_config, $this->session);
        $default_server_id = false;
        foreach (Hm_IMAP_List::getAll() as $id => $server) {
            if ($this->session->loaded) {
                if (array_key_exists('expiration', $server)) {
                    $server['expiration'] = 1;
                    Hm_IMAP_List::edit($id, $server);
                }
            }
            if (array_key_exists('default', $server) && $server['default']) {
                $default_server_id = $id;
            }
        }
        $auth_server = $this->session->get('imap_auth_server_settings', array());
        if (!empty($auth_server)) {
            if (array_key_exists('name', $auth_server)) {
                $name = $auth_server['name'];
            }
            else {
                $name = $this->config->get('imap_auth_name', 'Default');
            }
            $imap_details = array(
                'name' => $name,
                'default' => true,
                'server' => $auth_server['server'],
                'port' => $auth_server['port'],
                'tls' => $auth_server['tls'],
                'user' => $auth_server['username'],
                'pass' => $auth_server['password']
            );
            if (! empty($auth_server['sieve_config_host'])) {
                $imap_details['sieve_config_host'] = $auth_server['sieve_config_host'];
                $imap_details['sieve_tls'] = $auth_server['sieve_tls'];
            }
            if (!$default_server_id) {
                Hm_IMAP_List::add($imap_details);
            } else {
                // Perhaps something as changed
                Hm_IMAP_List::edit($default_server_id, $imap_details);
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
            if ( $server && array_key_exists('auth', $server) && $server['auth'] == 'xoauth2') {
                $results = imap_refresh_oauth2_token($server, $this->config);
                if (!empty($results)) {
                    if (Hm_IMAP_List::update_oauth2_token($server_id, $results[1], $results[0])) {
                        Hm_Debug::add(sprintf('Oauth2 token refreshed for IMAP server id %s', $server_id));
                        $updated++;
                    }
                }
            }
        }
        if ($updated > 0) {
            Hm_IMAP_List::save();
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
            list($success, $form) = $this->process_form(array('imap_server_id'));
            $imap_details = Hm_IMAP_List::dump($form['imap_server_id'], true);
            if ($success && $imap_details) {         
                if ($this->module_is_supported('sievefilters') && $this->user_config->get('enable_sieve_filter_setting', DEFAULT_ENABLE_SIEVE_FILTER)) {
                    try {
                        list($sieve_host, $sieve_port) = parse_sieve_config_host($imap_details['sieve_config_host']);
                        $client = new \PhpSieveManager\ManageSieve\Client($sieve_host, $sieve_port);
                        $client->connect($imap_details['user'], $imap_details['pass'], $imap_details['sieve_tls'], "", "PLAIN");
                    } catch (Exception $e) {
                        Hm_Msgs::add("ERRFailed to authenticate to the Sieve host");
                        return;
                    }
                }

                $cache = Hm_IMAP_List::get_cache($this->cache, $form['imap_server_id']);
                $imap = Hm_IMAP_List::connect($form['imap_server_id'], $cache, $imap_details['user'], $imap_details['pass']);
                if (imap_authed($imap)) {
                    Hm_Msgs::add(sprintf("Successfully authenticated to the %s server : %s", $imap->server_type, $imap_details['user']));
                    return;
                }
            }
            Hm_Msgs::add("ERRFailed to authenticate to IMAP server");
        }
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
            $this->out('msg_list_path', 'imap_'.$form['imap_server_id'].'_'.$form['folder']);
            $this->out('site_config', $this->config);
            $this->out('user_config', $this->user_config);
            $this->out('imap_accounts', $this->user_config->get('imap_servers'), array());
            $this->out('show_pagination_links', $this->user_config->get('pagination_links_setting', true));
            $this->out('auto_advance_email_enabled', $this->user_config->get('auto_advance_email_setting', true));
            $part = false;
            $prefetch = false;
            if (isset($this->request->post['imap_msg_part']) && preg_match("/[0-9\.]+/", $this->request->post['imap_msg_part'])) {
                $part = $this->request->post['imap_msg_part'];
            }
            elseif (isset($this->request->post['imap_prefetch']) && $this->request->post['imap_prefetch']) {
                $prefetch = true;
            }

            $this->out('header_allow_images', $this->config->get('allow_external_image_sources'));
            $this->out('images_whitelist', explode(',', $this->user_config->get('images_whitelist_setting')));

            $cache = Hm_IMAP_List::get_cache($this->cache, $form['imap_server_id']);
            $imap = Hm_IMAP_List::connect($form['imap_server_id'], $cache);
            if (imap_authed($imap)) {
                if ($this->user_config->get('unread_on_open_setting', false)) {
                    $imap->read_only = true;
                }
                else {
                    $imap->read_only = $prefetch;
                }
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
                    if (isset($msg_struct_current['subtype']) && mb_strtolower($msg_struct_current['subtype'] == 'html')) {
                        $msg_text = add_attached_images($msg_text, $form['imap_msg_uid'], $msg_struct, $imap);
                    }
                    $save_reply_text = false;
                    if ($part == 0 || (isset($msg_struct_current['type']) && mb_strtolower($msg_struct_current['type'] == 'text'))) {
                        $save_reply_text = true;
                    }
                    $msg_headers = $imap->get_message_headers($form['imap_msg_uid']);
                    $this->out('list_headers', get_list_headers($msg_headers));
                    $this->out('msg_headers', $msg_headers);
                    $this->out('imap_prefecth', $prefetch);
                    $this->out('imap_msg_part', "$part");
                    $this->out('use_message_part_icons', $this->user_config->get('msg_part_icons_setting', false));
                    $this->out('simple_msg_part_view', $this->user_config->get('simple_msg_parts_setting', DEFAULT_SIMPLE_MSG_PARTS));
                    $this->out('allow_delete_attachment', $this->user_config->get('allow_delete_attachment_setting', false));
                    if ($msg_struct_current) {
                        $this->out('msg_struct_current', $msg_struct_current);
                    }
                    $this->out('msg_text', $msg_text);
                    $download_args = sprintf("page=message&amp;uid=%s&amp;list_path=imap_%s_%s", $form['imap_msg_uid'], $form['imap_server_id'], $form['folder']);
                    $this->out('msg_download_args', $download_args.'&amp;imap_download_message=1');
                    $this->out('msg_attachment_remove_args', $download_args.'&amp;imap_remove_attachment=1');
                    $this->out('msg_show_args', sprintf("page=message&amp;uid=%s&amp;list_path=imap_%s_%s&amp;imap_show_message=1", $form['imap_msg_uid'], $form['imap_server_id'], $form['folder']));

                    if ($this->get('imap_allow_images', false)) {
                        if ($this->module_is_supported('contacts') && $this->user_config->get('contact_auto_collect_setting', false)) {
                            $this->out('collect_contacts', true);
                            $this->out('collected_contact_email', $msg_headers["Return-Path"]);
                            $this->out('collected_contact_name', $msg_headers["From"]);
                        }
                    }

                    if (!$prefetch) {
                        clear_existing_reply_details($this->session);
                        if ($part == 0) {
                            $msg_struct_current['type'] = 'text';
                            $msg_struct_current['subtype'] = 'plain';
                        }
                        $this->session->set(sprintf('reply_details_imap_%s_%s_%s', $form['imap_server_id'], $form['folder'], $form['imap_msg_uid']),
                            array('ts' => time(), 'msg_struct' => $msg_struct_current, 'msg_text' => ($save_reply_text ? $msg_text : ''), 'msg_headers' => $msg_headers));
                    }
                }
            }
        }
    }
}

/**
 * Get message source from an IMAP server
 */
class Hm_Handler_imap_message_source extends Hm_Handler_Module {
    public function process() {
        $imap_server_id = $this->request->get['imap_server_id'];
        $imap_msg_uid = $this->request->get['imap_msg_uid'];
        $folder = $this->request->get['imap_folder'];
        if ($imap_server_id && $imap_msg_uid && $folder) {
            $cache = Hm_IMAP_List::get_cache($this->cache, $imap_server_id);
            $imap = Hm_IMAP_List::connect($imap_server_id, $cache);
            if ($imap->select_mailbox(hex2bin($folder))) {
                $msg_source = $imap->get_message_content($imap_msg_uid, 0, false);
                $this->out('msg_source', $msg_source);
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

/**
 * @subpackage imap/handler
 */
class Hm_Handler_process_review_sent_email_setting extends Hm_Handler_Module {
    public function process() {
        function review_sent_email_callback($val) {
            return $val;
        }
        process_site_setting('review_sent_email', $this, 'review_sent_email_callback', false, true);
    }
}

/**
 * Get message headers for the Sent page
 * @subpackage imap/handler
 */
class Hm_Handler_imap_folder_data extends Hm_Handler_Module {
    /**
     * Returns list of message data for the sent page
     */
    public function process() {
        list($success, $form) = $this->process_form(array('imap_server_ids'));
        if ($success) {
            $ids = explode(',', $form['imap_server_ids']);
        } else {
            $data_sources = imap_sources('', $this, $this->request->get['list_path']);
            $ids = array_map(function($ds) { return $ds['id']; }, $data_sources);
            $folders = array_map(function($ds) { return $ds['folder']; }, $data_sources);
        }
        $path = $this->request->get['list_path'];
        $limit = $this->user_config->get($path.'_per_source_setting', DEFAULT_PER_SOURCE);
        $date = process_since_argument($this->user_config->get($path.'_since_setting', DEFAULT_UNREAD_SINCE));
        if (! isset($folders) || empty($folders)) {
            $folder = bin2hex('INBOX');
            if (array_key_exists('folder', $this->request->post)) {
                $folder = $this->request->post['folder'];
            }
            if (hex2bin($folder) == 'SPECIAL_USE_CHECK' || hex2bin($folder) == 'INBOX') {
                list($status, $msg_list) = merge_imap_search_results($ids, 'ALL', $this->session, $this->cache, array(hex2bin($folder)), $limit, array(array('SINCE', $date)), true);
            } else {
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
                $this->out('auto_'.$path.'_folder', $msg_list[0]['server_name'].' '.$auto_folder);
            }
        } else {
            list($status, $msg_list) = merge_imap_search_results($ids, 'ALL', $this->session, $this->cache, array_map(fn ($folder) => hex2bin($folder), $folders), $limit, array(array('SINCE', $date)), false);
        }
        if (array_key_exists('keyword', $this->request->get)) {
            $keyword = $this->request->get['keyword'];
            $search_pattern = "/$keyword/i";
            $search_result = array_filter($msg_list, function($filter_msg_list) use ($search_pattern) {
                return preg_grep($search_pattern, $filter_msg_list);
            });
            $msg_list = $search_result;
        }
        $this->out('folder_status', $status);
        $this->out('imap_'.$path.'_data', $msg_list);
        $this->out('imap_server_ids', implode(',', $ids));
    }
}

/**
 * Process first-time screen emails per page in the settings page
 * @subpackage core/handler
 */
class Hm_Handler_process_first_time_screen_emails_per_page_setting extends Hm_Handler_Module {
    public function process() {
        function process_first_time_screen_emails_callback($val) {
            return $val;
        }
        process_site_setting('first_time_screen_emails', $this, 'process_first_time_screen_emails_callback');
    }
}

class Hm_Handler_process_setting_move_messages_in_screen_email extends Hm_Handler_Module {
    public function process() {
        function process_move_messages_in_screen_email_enabled_callback($val) { return $val; }
        process_site_setting('move_messages_in_screen_email', $this, 'process_move_messages_in_screen_email_enabled_callback', true, true);
    }
}
class Hm_Handler_process_setting_active_preview_message extends Hm_Handler_Module {
    public function process() {
        function process_active_preview_message_callback($val) { return $val; }
        process_site_setting('active_preview_message', $this, 'process_active_preview_message_callback', true, true);
    }
}


