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
        $mailbox = Hm_IMAP_List::get_connected_mailbox($path[1], $this->cache);
        if (! $mailbox) {
            return;
        }
        $content = $mailbox->get_message_content(hex2bin($path[2]), $uid);
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
            $mailbox = Hm_IMAP_List::get_connected_mailbox($form['imap_server_id'], $this->cache);
            if ($mailbox && $mailbox->authed()) {
                $this->out('folder_status', array('imap_'.$form['imap_server_id'].'_'.$form['folder'] => $mailbox->get_folder_status(hex2bin($form['folder']))));
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
            if ($form['imap_move_action'] == "screen_mail") {
                $mailbox = Hm_IMAP_List::get_connected_mailbox($imap_server_id, $this->cache);
                if ($mailbox && $mailbox->authed()) {
                    $form['imap_move_action'] = "move";
                    $screen = true;
                    $screen_folder = 'Screen emails';
                    if (! count($mailbox->get_folder_status($screen_folder))) {
                        $mailbox->create_folder($screen_folder);
                    }
                    $form['imap_move_to'] = $parts[0] ."_". $parts[1] ."_".bin2hex($screen_folder);
                }
            }

            list($msg_ids, $dest_path, $same_server_ids, $other_server_ids) = process_move_to_arguments($form);
            $moved = array();
            if (count($same_server_ids) > 0) {
                $action = imap_move_same_server($same_server_ids, $form['imap_move_action'], $this->cache, $dest_path, $screen);
                $moved = array_merge($moved, $action['moved']);
            }
            if (count($other_server_ids) > 0) {
                $action = imap_move_different_server($other_server_ids, $form['imap_move_action'], $dest_path, $this->cache);
                $moved = array_merge($moved, $action['moved']);

            }
            if (count($moved) > 0) {
                $this->out('move_responses', $action['responses']);
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
        $imap_details = Hm_IMAP_List::dump($imap_id);
        $sent_folder = false;
        $mailbox = Hm_IMAP_List::get_connected_mailbox($imap_id, $this->cache);
        if ($mailbox && $mailbox->authed()) {
            $uid = save_sent_msg($this, $imap_id, $mailbox, $imap_details, $msg, $mime->get_headers()['Message-Id']);
            if ($uid && $this->user_config->get('review_sent_email_setting', false)) {
                $this->out('redirect_url', '?page=message&uid='.$uid.'&list_path=imap_'.$imap_id.'_'.bin2hex($sent_folder));
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
                    $mailbox = Hm_IMAP_List::get_connected_mailbox($path[1], $this->cache);
                    if ($mailbox && $mailbox->authed()) {
                        $mailbox->message_action(hex2bin($path[2]), 'UNFLAG', array($form['compose_msg_uid']));
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
                    $mailbox = Hm_IMAP_List::get_connected_mailbox($path[1], $this->cache);
                    if ($mailbox && $mailbox->authed()) {
                        $this->out('folder_status', array('imap_'.$path[1].'_'.$path[2] => $mailbox->get_folder_state()));
                        $mailbox->message_action(hex2bin($path[2]), 'ANSWERED', array($form['compose_msg_uid']));
                    }
                }
            }
        }
        if ($this->get('msg_next_link') && !$this->user_config->get('review_sent_email_setting', true)) {
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
            $mailbox = Hm_IMAP_List::get_connected_mailbox($form['imap_server_id'], $this->cache);
            if ($mailbox && $mailbox->authed()) {
                $this->out('folder_status', array('imap_'.$form['imap_server_id'].'_'.$form['folder'] => $mailbox->get_folder_state()));
                $mailbox->message_action(hex2bin($form['folder']), 'READ', array($form['imap_msg_uid']));
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
                $mailbox = Hm_IMAP_List::get_connected_mailbox($server_id, $this->cache);
                if ($mailbox && $mailbox->authed()) {
                    $mailbox->stream_message_part($folder, $uid, $msg_id, function ($content_type) {
                        header('Content-Type: ' . $content_type);
                        header('Content-Transfer-Encoding: binary');
                        ob_end_clean();
                    });
                    Hm_Functions::cease();
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
                $mailbox = Hm_IMAP_List::get_connected_mailbox($server_id, $this->cache);
                if ($mailbox && $mailbox->authed()) {
                    $mailbox->stream_message_part($folder, $uid, $msg_id, function ($content_type, $part_name) {
                        header('Content-Disposition: attachment; filename="' . $part_name . '"');
                        header('Content-Type: ' . $content_type);
                        header('Content-Transfer-Encoding: binary');
                        ob_end_clean();
                    });
                    Hm_Functions::cease();
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
                foreach (imap_data_sources($this->user_config->get('custom_imap_sources', array())) as $vals) {
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
                    $mailbox = Hm_IMAP_List::get_connected_mailbox($details['id'], $this->cache);
                    if ($mailbox && $mailbox->authed()) {
                        $label = $mailbox->get_folder_name($folder);
                    } else {
                        $label = $folder;
                    }
                    $title = array(strtoupper($details['type'] ?? 'IMAP'), $details['name'], $label);
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
            } elseif ($path == 'sent') {
                $this->out('mailbox_list_title', array('Sent'));
                $this->out('per_source_limit', $this->user_config->get('sent_per_source_setting', DEFAULT_SENT_PER_SOURCE));
                $this->out('message_list_since', $this->user_config->get('sent_since_setting', DEFAULT_SENT_SINCE));
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
                $mailbox = Hm_IMAP_List::get_connected_mailbox($server_id, $this->cache);
                if ($mailbox && $mailbox->authed()) {
                    if ($mailbox->remove_attachment($folder, $uid, $this->request->get['imap_msg_part'])) {
                        Hm_Msgs::add('Attachment deleted');
                        $this->out('redirect_url', '?page=message_list&list_path=' . $this->request->get['list_path']);
                        return;
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
            $mailbox = Hm_IMAP_List::get_connected_mailbox($form['imap_server_id'], $this->cache);
            if ($mailbox && $mailbox->authed()) {
                $this->out('can_share_folders', stripos($mailbox->get_capability(), 'ACL') !== false);
                $quota_root = $mailbox->get_quota($folder ? $folder : 'INBOX', true);
                if ($quota_root && isset($quota_root[0]['name'])) {
                    $quota = $mailbox->get_quota($quota_root[0]['name'], false);
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
            if ($mailbox && $mailbox->authed()) {
                $only_subscribed = $this->user_config->get('only_subscribed_folders_setting', false);
                if ($with_subscription) {
                    $only_subscribed = false;
                }
                $msgs = $mailbox->get_subfolders(hex2bin($folder), $only_subscribed, $with_subscription);
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
                Hm_Msgs::add(sprintf('ERRCould not authenticate to the selected %s server (%s)', $mailbox->server_type(), $this->user_config->get('imap_servers')[$form['imap_server_id']]['user']));
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
        $include_content_body = false;
        $include_preview = $this->user_config->get('active_preview_message_setting', false);
        $ceo_use_detect_ceo_fraud = $this->user_config->get('ceo_use_detect_ceo_fraud_setting', false);
        if ($include_preview || $ceo_use_detect_ceo_fraud) {
            $include_content_body = true;
        }

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
            $mailbox = Hm_IMAP_List::get_connected_mailbox($form['imap_server_id'], $this->cache);
            if ($mailbox && $mailbox->authed()) {
                $this->out('imap_mailbox_page_path', $path);
                if (isset($this->request->get['screen_emails']) && hex2bin($form['folder']) == 'INBOX' && $this->module_is_supported("contacts")) {
                    $contacts = $this->get('contact_store');
                    $contact_list = $contacts->getAll();

                    $existingEmails = array_map(function($c){
                        return $c->value('email_address');
                    },$contact_list);
                    list($total, $results) = $mailbox->get_messages(hex2bin($form['folder']), $sort, $rev, $filter, $offset, $limit, $keyword, $existingEmails, $include_content_body);
                } else {
                    list($total, $results) = $mailbox->get_messages(hex2bin($form['folder']), $sort, $rev, $filter, $offset, $limit, $keyword, null, $include_content_body);
                }
                foreach ($results as $msg) {
                    $msg['server_id'] = $form['imap_server_id'];
                    $msg['server_name'] = $details['name'];
                    $msg['folder'] = $form['folder'];
                    $uid = $msg['uid'];

                    if ($ceo_use_detect_ceo_fraud && hex2bin($form['folder']) == 'INBOX') {
                        if ($this->isCeoFraud($msg['to'], $msg['subject'], $msg['preview_msg'])) {
                            
                            $folder = "Suspicious emails";
                            if (!count($mailbox->get_mailbox_status($folder))) {
                                $mailbox->create_folder($folder);
                            }
                            $dest_folder = bin2hex($folder);
                            $server_ids = array(
                                $form['imap_server_id'] => [
                                    $form['folder'] => $uid
                                ]
                            );
                            imap_move_same_server($server_ids, "move", $this->cache, [null, null, $dest_folder]);
                            $msg = [];
                            $total--;
                        }
                    }
                    
                    if ($msg) {
                        if (! $include_preview && isset($msg['preview_msg'])) {
                            $msg['preview_msg'] = "";
                        }
                        $msgs[] = $msg;
                    }
                }
                if ($folder = $mailbox->get_selected_folder()) {
                    $folder['detail']['exists'] = $total;
                    $this->out('imap_folder_detail', array_merge($folder, array('offset' => $offset, 'limit' => $limit)));
                }
                $this->out('folder_status', array('imap_'.$form['imap_server_id'].'_'.$form['folder'] => $mailbox->get_folder_state()));
            }
            $this->out('imap_mailbox_page', $msgs);
            $this->out('list_page', $list_page);
            $this->out('imap_server_id', $form['imap_server_id']);
            $this->out('do_not_flag_as_read_on_open', $this->user_config->get('unread_on_open_setting', false));
        }
    }
    public function isCeoFraud($email, $subject, $msg) {          
        // 1. Check Suspicious Terms or Requests
        $suspiciousTerms = explode(",", $this->user_config->get("ceo_suspicious_terms_setting"));
        if ($this->detectSuspiciousTerms($msg, $suspiciousTerms) || $this->detectSuspiciousTerms($subject, $suspiciousTerms)) {
           
            // 2. check ceo_rate_limit
            $amounts = $this->extractAmountFromEmail($msg);
            $amountLimit = $this->user_config->get("ceo_amount_limit_setting");
            $isUpperAmount = array_reduce($amounts, function ($carry, $value) use ($amountLimit) {
                return $carry || $value > $amountLimit;
            }, false);
            
            if ($isUpperAmount) {
                if ($this->user_config->get("ceo_use_trusted_contact_setting")) {
                    $contacts = $this->get('contact_store');
                    $contact_list = $contacts->getAll();
                    $existingEmails = array_map(function($c){
                        return $c->value('email_address');
                    },$contact_list);
                    if (!$this->isEmailInTrustedDomainList(array_values($existingEmails), $email)) {
                        return true;
                    }
                } else {
                    return true;
                }
            }
        }
        return false;
    }
    private function detectSuspiciousTerms($msg, $suspiciousTerms) {
        foreach ($suspiciousTerms as $phrase) {
            if (stripos($msg, trim($phrase)) !== false) {
                return true;
            }
        }
        return false;
    }
    private function isEmailInTrustedDomainList($trustedDomain, $email) {
        if (in_array($email, $trustedDomain)) {
            return true;
        }
        return false;
    }
    private function extractAmountFromEmail($emailBody) {
        $pattern = '/\b\d+(?:,\d+)?\.?\d*\s*(?:USD|dollars?|US\$?|EUR|euros?|€|JPY|yen|¥|GBP|pounds?|£|CAD|CAD\$|AUD|AUD\$)/i';
    
        preg_match_all($pattern, $emailBody, $matches);
    
        if ($matches) {
            return array_map(function($value) { 
                return floatval(preg_replace('/[^0-9]/', '', $value)); 
            }, $matches[0]);
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
            $trash_folder = false;
            $specials = get_special_folders($this, $form['imap_server_id']);
            if (array_key_exists('trash', $specials) && $specials['trash']) {
                $trash_folder = $specials['trash'];
            }
            $mailbox = Hm_IMAP_List::get_connected_mailbox($form['imap_server_id'], $this->cache);
            if ($mailbox && $mailbox->authed()) {
                if ($mailbox->delete_message(hex2bin($form['folder']), $form['imap_msg_uid'], $trash_folder)) {
                    $del_result = true;
                }
                $this->out('folder_status', array('imap_'.$form['imap_server_id'].'_'.$form['folder'] => $mailbox->get_folder_state()));
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

        $archive_folder = false;
        $form_folder = hex2bin($form['folder']);
        $errors = 0;
        $status = null;

        $specials = get_special_folders($this, $form['imap_server_id']);
        if (array_key_exists('archive', $specials) && $specials['archive']) {
            $archive_folder = $specials['archive'];
        }

        $mailbox = Hm_IMAP_List::get_connected_mailbox($form['imap_server_id'], $this->cache);
        if ($mailbox && ! $mailbox->is_imap() && empty($archive_folder)) {
            // EWS supports archiving to user archive folders
            $status = $mailbox->message_action($form_folder, 'ARCHIVE', array($form['imap_msg_uid']))['status'];
        } else {
            if (!$archive_folder) {
                Hm_Msgs::add('No archive folder configured for this IMAP server');
                $errors++;
            }

            if (! $errors && $mailbox && $mailbox->authed()) {
                $archive_exists = count($mailbox->get_folder_status($archive_folder));
                if (!$archive_exists) {
                    Hm_Msgs::add('Configured archive folder for this IMAP server does not exist');
                    $errors++;
                }

                /* path according to original option setting */
                if ($this->user_config->get('original_folder_setting', false)) {
                    $archive_folder .= '/' . $form_folder;
                    if (!count($mailbox->get_folder_status($archive_folder))) {
                        if (! $mailbox->create_folder($archive_folder)) {
                            $debug = $mailbox->get_debug();
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
                if (! $errors) {
                    $status = $mailbox->message_action($form_folder, 'MOVE', array($form['imap_msg_uid']), $archive_folder)['status'];
                }
            }
        }

        if ($status) {
            Hm_Msgs::add("Message archived");
        } else {
            Hm_Msgs::add('ERRAn error occurred archiving the message');
        }

        $this->save_hm_msgs();
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
            $mailbox = Hm_IMAP_List::get_connected_mailbox($form['imap_server_id'], $this->cache);
            if ($mailbox && $mailbox->authed()) {
                if ($form['imap_flag_state'] == 'flagged') {
                    $cmd = 'UNFLAG';
                }
                else {
                    $cmd = 'FLAG';
                }
                if ($mailbox->message_action(hex2bin($form['folder']), $cmd, array($form['imap_msg_uid']))['status']) {
                    $flag_result = true;
                }
                $this->out('folder_status', array('imap_'.$form['imap_server_id'].'_'.$form['folder'] => $mailbox->get_folder_state()));
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
        $snoozed_messages = [];
        $snooze_tag = null;
        if ($form['imap_snooze_until'] != 'unsnooze') {
            $at = date('D, d M Y H:i:s O');
            $until = get_scheduled_date($form['imap_snooze_until']);
            $snooze_tag = "X-Snoozed: at $at; until $until";
        }
        $ids = explode(',', $form['imap_snooze_ids']);
        foreach ($ids as $msg_part) {
            list($imap_server_id, $msg_id, $folder) = explode('_', $msg_part);
            $mailbox = Hm_IMAP_List::get_connected_mailbox($imap_server_id, $this->cache);
            if ($mailbox && $mailbox->authed()) {
                $folder = hex2bin($folder);
                if (snooze_message($mailbox, $msg_id, $folder, $snooze_tag)) {
                    $snoozed_messages[] = $msg_id;
                }
            }
        }
        $this->out('snoozed_messages', $snoozed_messages);
        if (count($snoozed_messages) == count($ids)) {
            $msg = 'Messages snoozed';
        } elseif (count($snoozed_messages) > 0) {
            $msg = 'Some messages have been snoozed';
        } else {
            $msg = 'ERRFailed to snooze selected messages';
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
            $mailbox = Hm_IMAP_List::get_connected_mailbox($server_id, $this->cache);
            if ($mailbox && $mailbox->authed()) {
                $folder = 'Snoozed';
                $status = $mailbox->get_folder_status($folder);
                if (! count($status)) {
                    continue;
                }
                $folder = $status['id'];
                $ret = $mailbox->get_messages($folder, 'DATE', false, 'ALL');
                foreach ($ret[1] as $msg) {
                    $msg_headers = $mailbox->get_message_headers($folder, $msg['uid']);
                    if (isset($msg_headers['X-Snoozed'])) {
                        try {
                            $snooze_headers = parse_nexter_header($msg_headers['X-Snoozed'], 'X-Snoozed');
                            if (new DateTime($snooze_headers['until']) <= new DateTime()) {
                                snooze_message($mailbox, $msg['uid'], $folder, null);
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
                    $mailbox = Hm_IMAP_List::get_connected_mailbox($server, $this->cache);
                    if ($mailbox && $mailbox->authed()) {
                        $server_details = $this->user_config->get('imap_servers')[$server];
                        if ($form['action_type'] == 'delete') {
                            if (array_key_exists('trash', $specials)) {
                                if ($specials['trash']) {
                                    $trash_folder = $specials['trash'];
                                } elseif ($mailbox->is_imap()) {
                                    Hm_Msgs::add(sprintf('ERRNo trash folder configured for %s', $server_details['name']));
                                }
                            }
                        }
                        if ($form['action_type'] == 'archive') {
                            if(array_key_exists('archive', $specials)) {
                                if($specials['archive']) {
                                    $archive_folder = $specials['archive'];
                                } elseif ($mailbox->is_imap()) {
                                    Hm_Msgs::add(sprintf('ERRNo archive folder configured for %s', $server_details['name']));
                                }
                            }
                        }

                        foreach ($folders as $folder => $uids) {
                            $status['imap_'.$server.'_'.$folder] = $imap->folder_state;

                            if ($mailbox->is_imap() && $form['action_type'] == 'delete' && $trash_folder && $trash_folder != hex2bin($folder)) {
                                if (! $mailbox->message_action(hex2bin($folder), 'MOVE', $uids, $trash_folder)['status']) {
                                    $errs++;
                                }
                                else {
                                    foreach ($uids as $uid) {
                                        $moved[] = sprintf("imap_%s_%s_%s", $server, $uid, $folder);
                                    }
                                }
                            }
                            elseif ($mailbox->is_imap() && $form['action_type'] == 'archive' && $archive_folder && $archive_folder != hex2bin($folder)) {
                                /* path according to original option setting */
                                if ($this->user_config->get('original_folder_setting', false)) {
                                    $archive_folder .= '/' . hex2bin($folder);
                                    $dest_path_exists = count($mailbox->get_folder_status($archive_folder));
                                    if (!$dest_path_exists) {
                                        $mailbox->create_folder($archive_folder);
                                    }
                                }
                                if (! $mailbox->message_action(hex2bin($folder), 'MOVE', $uids, $archive_folder)['status']) {
                                    $errs++;
                                }
                                else {
                                    foreach ($uids as $uid) {
                                        $moved[] = sprintf("imap_%s_%s_%s", $server, $uid, $folder);
                                    }
                                }
                            }
                            else {
                                if (! $mailbox->message_action(hex2bin($folder), mb_strtoupper($form['action_type']), $uids)['status']) {
                                    $errs++;
                                }
                                else {
                                    $msgs += count($uids);
                                    if ($form['action_type'] == 'delete') {
                                        $mailbox->message_action(hex2bin($folder), 'EXPUNGE', $uids);
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
        } else {
            $userCustomSources = $this->session->get('custom_imap_sources', user:true);
            if (! $userCustomSources) {
                $userCustomSources = [];
            }
            $data_sources = imap_data_sources($userCustomSources);
            $ids = array_map(function($ds) { return $ds['id']; }, $data_sources);
        }

        if (array_key_exists('list_path', $this->request->get) && $this->request->get['list_path'] == 'email') {
            $limit = $this->user_config->get('all_email_per_source_setting', DEFAULT_ALL_EMAIL_PER_SOURCE);
            $date = process_since_argument($this->user_config->get('all_email_since_setting', DEFAULT_SINCE));
        }
        else {
            $limit = $this->user_config->get('all_per_source_setting', DEFAULT_ALL_PER_SOURCE);
            $date = process_since_argument($this->user_config->get('all_since_setting', DEFAULT_SINCE));
        }

        $filter = 'ALL';
        $offset = 0;
        $list_page = 1;
        $maxPerSource = round($limit / count($data_sources));
        
        if (isset($this->request->get['list_page'])) {
            $list_page = (int) $this->request->get['list_page'];
            if ($list_page && $list_page > 1) {
                $offset = ($list_page - 1)*$maxPerSource;
            }
            else {
                $list_page = 1;
            }
        }
        if ($this->get('list_filter')) {
            $filter = mb_strtoupper($this->get('list_filter'));
        }

        $offsets = $this->request->get['offsets'] ?? '';
        if ($offsets) {
            $offsets = explode(',', $offsets);
        }

        $result = getCombinedMessagesLists($data_sources, $this->cache, [['SINCE', $date]], $list_page, $limit, $offsets, $offset, $filter);

        $list = flattenMessagesLists($result['lists'], $maxPerSource);
        $messagesList = $list['messages'];

        usort($messagesList, function($a, $b) {
            return strtotime($b['internal_date']) - strtotime($a['internal_date']);
        });

        $maxPages = ceil($result['total'] / $limit);
        $this->out('pages', $maxPages);
        $this->out('folder_status', $result['status']);
        $this->out('imap_combined_inbox_data', $messagesList);
        $this->out('imap_server_ids', implode(',', $ids));
        $this->out('offsets', implode(',', $list['offsets']));
    }
}

/**
 * Get message headers for the Flagged page
 * @subpackage imap/handler
 */
class Hm_Handler_imap_filter_by_type extends Hm_Handler_Module {
    /**
     * Fetch flagged messages from an IMAP server
     */
    public function process() {
        $data_sources = imap_data_sources();
        $ids = array_map(function($ds) { return $ds['id']; }, $data_sources);

        $filter_type = $this->request->post['filter_type'];

        if ($filter_type === 'flagged') {
            $filter = 'FLAGGED';
            $date = process_since_argument($this->user_config->get('flagged_since_setting', DEFAULT_FLAGGED_SINCE));
            $limit = $this->user_config->get('flagged_per_source_setting', DEFAULT_FLAGGED_PER_SOURCE);
        } else if ($filter_type === 'unread') {
            $filter = 'UNSEEN';
            $date = process_since_argument($this->user_config->get('unread_since_setting', DEFAULT_UNREAD_SINCE));
            $limit = $this->user_config->get('unread_per_source_setting', DEFAULT_PER_SOURCE);
        } else {
            return;
        }

        $list_page = (int) $this->request->get['list_page'];
        $offsets = $this->request->get['offsets'] ?? '';
        $keyword = $this->request->get['keyword'] ?? '';

        $maxPerSource = round($limit / count($data_sources));
        $offset = 0;

        if ($list_page && $list_page > 1) {
            $offset = ($list_page - 1)*$maxPerSource;
        }

        $searchTerms = [];
        if ($keyword) {
            $searchTerms[] = ['TEXT', $keyword];
        }
        if ($offsets) {
            $offsets = explode(',', $offsets);
        }
        $searchTerms[] = ['SINCE', $date];
        
        $result = getCombinedMessagesLists($data_sources, $this->cache, $searchTerms, $list_page, $limit, $offsets, $offset, $filter);

        $list = flattenMessagesLists($result['lists'], $maxPerSource);
        $messagesList = $list['messages'];

        usort($messagesList, function($a, $b) {
            return strtotime($b['internal_date']) - strtotime($a['internal_date']);
        });

        $maxPages = ceil($result['total'] / $limit);
        $this->out('pages', $maxPages);
        $this->out('folder_status', $result['status']);
        $this->out('imap_filter_by_type_data', $messagesList);
        $this->out('type', $filter_type);
        $this->out('imap_server_ids', implode(',', $ids));
        $this->out('offsets', implode(',', $list['offsets']));
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
                $start_time = microtime(true);
                $mailbox = Hm_IMAP_List::get_connected_mailbox($id, $this->cache);
                $this->out('imap_connect_time', microtime(true) - $start_time);
                if ($mailbox && $mailbox->authed()) {
                    $this->out('imap_capabilities_list', $mailbox->get_capability());
                    $this->out('imap_connect_status', $mailbox->get_state());
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
                if ($server['object']->use_cache()) {
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
 * Save EWS server details
 * @subpackage imap/handler
 */
class Hm_Handler_save_ews_server extends Hm_Handler_Module {
    public function process() {
        list($success, $form) = $this->process_form(array(
            'ews_profile_name',
            'ews_email',
            'ews_password',
            'ews_server',
            'ews_server_id',
            'ews_hide_from_c_page',
            'ews_create_profile',
            'ews_profile_signature',
            'ews_profile_reply_to',
            'ews_profile_is_default',
        ));
        if ($success) {
            $imap_server_id = connect_to_imap_server(
                $form['ews_server'],
                $form['ews_profile_name'],
                null,
                $form['ews_email'],
                $form['ews_password'],
                null,
                null,
                null,
                'ews',
                $this,
                $form['ews_hide_from_c_page'],
                $form['ews_server_id'],
            );
            if(empty($imap_server_id)) {
                Hm_Msgs::add("ERRCould not save server");
                return;
            }
            $smtp_server_id = connect_to_smtp_server(
                $form['ews_server'],
                $form['ews_profile_name'],
                null,
                $form['ews_email'],
                $form['ews_password'],
                null,
                'ews',
                $form['ews_server_id'],
            );
            if ($form['ews_create_profile'] && $imap_server_id && $smtp_server_id) {
                if (! strstr($form['ews_email'], '@')) {
                    $address = $form['ews_email'] . '@' . $form['ews_server'];
                } else {
                    $address = $form['ews_email'];
                }
                add_profile($form['ews_profile_name'], $form['ews_profile_signature'], $form['ews_profile_reply_to'], $form['ews_profile_is_default'], $address, $form['ews_server'], $form['ews_email'], $smtp_server_id, $imap_server_id, $this);
            }
            // auto-assign special folders
            $mailbox = Hm_IMAP_List::get_connected_mailbox($imap_server_id, $this->cache);
            if (is_object($mailbox) && $mailbox->authed()) {
                $specials = $this->user_config->get('special_imap_folders', array());
                $exposed = $mailbox->get_special_use_mailboxes();
                $specials[$imap_server_id] = [
                    'sent' => $exposed['sent'] ?? '',
                    'draft' => $exposed['drafts'] ?? '',
                    'trash' => $exposed['trash'] ?? '',
                    'archive' => $exposed['archive'] ?? '',
                    'junk' => $exposed['junk'] ?? ''
                ];
                $this->user_config->set('special_imap_folders', $specials);
            }
            $this->session->record_unsaved('EWS server added');
            $this->session->secure_cookie($this->request, 'hm_reload_folders', '1');
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
        foreach(imap_data_sources($this->user_config->get('custom_imap_sources', array())) as $vals) {
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
        if (array_key_exists('list_path', $this->request->get)) {
            $path = $this->request->get['list_path'];
        }
        else {
            $path = '';
        }
        if (in_array($path, ['sent', 'junk', 'snoozed','trash', 'drafts'])) {
            foreach (imap_sources($this, $path) as $vals) {
                $this->append('data_sources', $vals);
            }
        }
        else {
            foreach (imap_data_sources($this->user_config->get('custom_imap_sources', array())) as $vals) {
                $this->append('data_sources', $vals);
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
                'pass' => $auth_server['password'],
                'type' => 'imap',
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

                $mailbox = false;
                $cache = Hm_IMAP_List::get_cache($this->cache, $form['imap_server_id']);
                $mailbox = Hm_IMAP_List::connect($form['imap_server_id'], $cache, $form['imap_user'], $form['imap_pass']);
                if ($mailbox) {
                    if ($mailbox->authed()) {
                        Hm_Msgs::add(sprintf("Successfully authenticated to the %s server : %s", $mailbox->server_type(), $form['imap_user']));
                    }
                    else {
                        Hm_Msgs::add(sprintf("ERRFailed to authenticate to the %s server : %s", $mailbox->server_type(), $form['imap_user']));
                    }
                }
                else {
                    Hm_Msgs::add('ERRUsername and password are required');
                    $this->out('old_form', $form);
                }
            }
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

            $mailbox = Hm_IMAP_List::get_connected_mailbox($form['imap_server_id'], $this->cache);
            if ($mailbox && $mailbox->authed()) {
                if ($this->user_config->get('unread_on_open_setting', false)) {
                    $mailbox->set_read_only(true);
                }
                else {
                    $mailbox->set_read_only($prefetch);
                }
                list($msg_struct, $msg_struct_current, $msg_text, $part) = $mailbox->get_structured_message(hex2bin($form['folder']), $form['imap_msg_uid'], $part, $this->user_config->get('text_only_setting', false));
                $save_reply_text = false;
                if ($part == 0 || (isset($msg_struct_current['type']) && mb_strtolower($msg_struct_current['type'] == 'text'))) {
                    $save_reply_text = true;
                }
                $msg_headers = $mailbox->get_message_headers(hex2bin($form['folder']), $form['imap_msg_uid']);
                $this->out('folder_status', array('imap_'.$form['imap_server_id'].'_'.$form['folder'] => $mailbox->get_folder_state()));
                $this->out('msg_struct', $msg_struct);
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

/**
 * Get message source from an IMAP server
 */
class Hm_Handler_imap_message_source extends Hm_Handler_Module {
    public function process() {
        $imap_server_id = $this->request->get['imap_server_id'];
        $imap_msg_uid = $this->request->get['imap_msg_uid'];
        $folder = $this->request->get['imap_folder'];
        if ($imap_server_id && $imap_msg_uid && $folder) {
            $mailbox = Hm_IMAP_List::get_connected_mailbox($imap_server_id, $this->cache);
            if ($mailbox && $mailbox->authed()) {
                $msg_source = $mailbox->get_message_content(hex2bin($folder), $imap_msg_uid);
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
                $type = imap_server_type($form['imap_server_id']);
                if (strtolower($type) == 'ews') {
                    $details = Hm_IMAP_List::dump($form['imap_server_id']);
                    foreach (Hm_Profiles::getAll() as $profile) {
                        if ($details['user'] == $profile['user'] && $details['server'] == $profile['server']) {
                            Hm_Profiles::del($profile['id']);
                            Hm_SMTP_List::del($profile['smtp_id']);
                        }
                    }
                }
                $res = Hm_IMAP_List::del($form['imap_server_id']);
                if ($res) {
                    $this->out('deleted_server_id', $form['imap_server_id']);
                    Hm_Msgs::add('Server deleted');
                    $this->session->record_unsaved(sprintf('%s server deleted', $type));
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
        process_site_setting('review_sent_email', $this, 'review_sent_email_callback', DEFAULT_REVIEW_SENT_EMAIL, true);
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
            $data_sources = imap_sources($this, $this->request->get['list_path']);
            $ids = array_map(function($ds) { return $ds['id']; }, $data_sources);
        }
        $path = $this->request->get['list_path'];
        $keyword = $this->request->get['keyword'] ?? '';
        $list_page = (int) $this->request->get['list_page'] ?? 1;
        $offsets = $this->request->get['offsets'] ?? '';

        $limit = $this->user_config->get($path.'_per_source_setting', DEFAULT_PER_SOURCE);
        $date = process_since_argument($this->user_config->get($path.'_since_setting', DEFAULT_SINCE));

        $maxPerSource = round($limit / count($data_sources));
        $offset = 0;

        if ($list_page && $list_page > 1) {
            $offset = ($list_page - 1)*$maxPerSource;
        }

        $searchTerms = [];

        if ($keyword) {
            $searchTerms[] = ['TEXT', $keyword];
        }
        if ($offsets) {
            $offsets = explode(',', $offsets);
        }
        $searchTerms[] = ['SINCE', $date];

        $result = getCombinedMessagesLists($data_sources, $this->cache, $searchTerms, $list_page, $limit, $offsets, $offset, 'ALL');

        $list = flattenMessagesLists($result['lists'], $maxPerSource);
        $messagesList = $list['messages'];

        usort($messagesList, function($a, $b) {
            return strtotime($b['internal_date']) - strtotime($a['internal_date']);
        });

        $maxPages = ceil($result['total'] / $limit);
        $this->out('pages', $maxPages);
        $this->out('folder_status', $result['status']);
        $this->out('imap_'.$path.'_data', $messagesList);
        $this->out('imap_server_ids', implode(',', $ids));
        $this->out('offsets', implode(',', $list['offsets']));
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

/**
 * Process setting_ceo_detection_fraud in the settings page
 * @subpackage core/handler
 */
class Hm_Handler_process_setting_ceo_detection_fraud extends Hm_Handler_Module {
    public function process() {
        function process_ceo_use_detect_ceo_fraud_callback($val) { return $val; }
        function process_ceo_use_trusted_contact_callback($val) { return $val; }
        function process_ceo_suspicious_terms_callback($val) { return $val; }
        function process_ceo_amount_limit_callback($val) { return $val; }
        
        process_site_setting('ceo_use_detect_ceo_fraud', $this, 'process_ceo_use_detect_ceo_fraud_callback');
        process_site_setting('ceo_use_trusted_contact', $this, 'process_ceo_use_trusted_contact_callback');
        process_site_setting('ceo_suspicious_terms', $this, 'process_ceo_suspicious_terms_callback');
        process_site_setting('ceo_rate_limit', $this, 'process_ceo_amount_limit_callback');
    }
}
