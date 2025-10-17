<?php

/**
 * IMAP modules
 * @package modules
 * @subpackage imap
 */

if (!defined('DEBUG_MODE')) { die(); }

// Add helper function for delayed logging
function delayed_debug_log($message, $data = null, $delay = 1) {
    if ($data) {
        Hm_Debug::add($message . ': ' . json_encode($data));
    } else {
        Hm_Debug::add($message);
    }
    sleep($delay);
}

// Include spam report utilities for debugging functions
require_once APP_PATH . 'modules/imap/spam_report_utils.php';

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
            $emails_to_block = [];
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
                    $imap_move_ids = explode(",", $form['imap_move_ids']);

                    foreach ($imap_move_ids as $imap_msg_id) {
                        $array_imap_msg_id = explode("_", $imap_msg_id);
                        if (isset($array_imap_msg_id[2])) {
                            $msg_header = $mailbox->get_message_headers(hex2bin($array_imap_msg_id[3]), $array_imap_msg_id[2]);
                            $email_sender = process_address_fld($msg_header['From'])[0]['email'] ?? null;
                            if ($email_sender) {
                                $emails_to_block[] = $email_sender;
                            }
                        }
                    }
                    $emails_to_block = array_unique($emails_to_block);
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
                        Hm_Msgs::add('Some Emails moved to Screen email folder', 'warning');
                    } else {
                        Hm_Msgs::add('Some messages moved (only IMAP message types can be moved)', 'warning');
                    }
                }
                else {
                    Hm_Msgs::add('Some messages copied (only IMAP message types can be copied)', 'warning');
                }
            }
            elseif (count($moved) == 0) {
                Hm_Msgs::add('Unable to move/copy selected messages', 'danger');
            }
            $this->out('move_count', $moved);
            $this->out('emails_to_block', implode(",", $emails_to_block));
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
        $mailbox = Hm_IMAP_List::get_connected_mailbox($imap_id, $this->cache);
        if ($mailbox && $mailbox->authed()) {
            list($uid, $sent_folder) = save_sent_msg($this, $imap_id, $mailbox, $imap_details, $msg, $mime->get_headers()['Message-Id']);
            if ($uid) {
                $this->out('sent_msg_uid', $uid);
                $this->out('sent_imap_id', $imap_id);

                if ($this->user_config->get('review_sent_email_setting', false)) {
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
                Hm_Msgs::add('Folder added to combined pages', 'info');
                $this->session->record_unsaved('Added folder to combined pages');
            }
            else {
                $sources[$form['list_path']] = 'remove';
                Hm_Msgs::add('Folder removed from combined pages', 'info');
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
            Hm_Msgs::add('An Error occurred trying to download the message', 'danger');
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
            Hm_Msgs::add('An Error occurred trying to download the message', 'danger');
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
                $spcial_folders = get_special_folders($this, $parts[1]);
                if (array_key_exists(strtolower($folder), $spcial_folders)) {
                    $this->out('core_msg_control_folder', $spcial_folders[strtolower($folder)]);
                }
                if (!empty($details)) {
                    if (array_key_exists('folder_label', $this->request->get)) {
                        $folder = $this->request->get['folder_label'];
                        $this->out('folder_label', $folder);
                    } else {
                        $folder = hex2bin($parts[2]);
                    }
                    
                    $mailbox = Hm_IMAP_List::get_mailbox_without_connection($details);
                    $label = $mailbox->get_folder_name($folder);
                    if(!$label) {
                        if ($this->config->get('allow_session_cache', false)) {
                            $paths = explode("_", $path);
                            $short_path = $paths[0] . "_" . $paths[1] . "_";
                            $cached_folders = $this->cache->get('imap_folders_'.$short_path, true);
                            $label = !empty($cached_folders[$folder]['name']) ? $cached_folders[$folder]['name'] : '';
                        } else {
                            Hm_Msgs::add('Folder name loaded directly from the server. This may be slower. Enable session caching for better performance.', 'warning');
                            if (isset($details['type']) && $details['type'] === 'ews') {
                                $connected_mailbox = Hm_IMAP_List::get_connected_mailbox($parts[1], $this->cache);
                                if ($connected_mailbox && $connected_mailbox->authed()) {
                                    $folder_status = $connected_mailbox->get_folder_status($folder, false);
                                    $label = $folder_status['name'] ?? null;
                                }
                            }
                        }
                    }
                    $title = array(strtoupper($details['type'] ?? 'IMAP'), $details['name'], $label);
                    if ($this->get('list_page', 0)) {
                        $title[] = sprintf('Page %d', $this->get('list_page', 0));
                    }
                    $this->out('mailbox_list_title', $title);
                }

                if ($this->module_is_supported("contacts") && strtoupper($folder) == 'INBOX') {
                    $this->out('folder', $folder);
                    $this->out('screen_emails', isset($this->request->get['screen_emails']));
                    $this->out('first_time_screen_emails', $this->user_config->get('first_time_screen_emails_setting', DEFAULT_PER_SOURCE));
                    $this->out('move_messages_in_screen_email', $this->user_config->get('move_messages_in_screen_email_setting', DEFAULT_PER_SOURCE));
                }
            }
            if (array_key_exists('sort', $this->request->get) || array_key_exists('sort', $this->request->post)) {
                $sort = $this->request->get['sort'] ?? $this->request->post['sort'] ?? '';
                if (in_array($sort, array('arrival', 'from', 'subject',
                    'date', 'to', '-arrival', '-from', '-subject', '-date', '-to'), true)) {
                    $this->out('list_sort', $sort);
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
            Hm_Msgs::add('An Error occurred trying to remove attachment to the message', 'danger');
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
                $count_children = false;
                if (isset($this->request->post['count_children'])){
                    $count_children = $this->request->post['count_children'];
                }
                $msgs = $mailbox->get_subfolders(hex2bin($folder), $only_subscribed, $with_subscription, $count_children);
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
                $details = Hm_IMAP_List::get($form['imap_server_id'], false);
                if ($details) {
                    $type = $details['type'] ?? '';
                } else {
                    $type = '';
                }
                Hm_Msgs::add(sprintf('Could not authenticate to the selected %s server (%s)', $type, $this->user_config->get('imap_servers')[$form['imap_server_id']]['user']), 'warning');
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
                Hm_Msgs::add('An error occurred trying to delete this message', 'danger');
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
        if ($mailbox && ! $mailbox->is_imap()) {
            // EWS supports archiving to user archive folders
            $status = $mailbox->message_action($form_folder, 'ARCHIVE', array($form['imap_msg_uid']))['status'];
        } else {
            if (!$archive_folder) {
                Hm_Msgs::add('No archive folder configured for this IMAP server', 'warning');
                $errors++;
            }

            if (! $errors && $mailbox && $mailbox->authed()) {
                $archive_exists = count($mailbox->get_folder_status($archive_folder));
                if (!$archive_exists) {
                    Hm_Msgs::add('Configured archive folder for this IMAP server does not exist', 'warning');
                    $errors++;
                }

                /* path according to original option setting */
                if ($this->user_config->get('original_folder_setting', false)) {
                    $archive_folder .= '/' . $form_folder;
                    if (!count($mailbox->get_folder_status($archive_folder))) {
                        if (! $mailbox->create_folder($archive_folder)) {
                            $debug = $mailbox->get_debug();
                            if (! empty($debug['debug'])) {
                                Hm_Msgs::add(array_pop($debug['debug']), 'danger');
                            } else {
                                Hm_Msgs::add('Could not create configured archive folder for the original folder of the message', 'danger');
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
            Hm_Msgs::add('An error occurred archiving the message', 'danger');
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
                Hm_Msgs::add('An error occurred trying to flag this message', 'danger');
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
        if ($this->should_skip_execution('enable_snooze_setting', DEFAULT_ENABLE_SNOOZE)) return;

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
        $type = 'success';
        if (count($snoozed_messages) == count($ids)) {
            $msg = 'Messages snoozed';
        } elseif (count($snoozed_messages) > 0) {
            $msg = 'Some messages have been snoozed';
            $type = 'warning';
        } else {
            $msg = 'Failed to snooze selected messages';
            $type = 'danger';
        }
        Hm_Msgs::add($msg, $type);
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
        if ($this->should_skip_execution('enable_snooze_setting', DEFAULT_ENABLE_SNOOZE)) return;

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
                            $snooze_headers = parse_delayed_header($msg_headers['X-Snoozed'], 'X-Snoozed');
                            if (new DateTime($snooze_headers['until']) <= new DateTime()) {
                                snooze_message($mailbox, $msg['uid'], $folder, null);
                            }
                        } catch (Exception $e) {
                            Hm_Debug::add(sprintf('Cannot unsnooze message: %s', $msg_headers['subject']));
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
     * Read, unread, delete, flag, unflag, archive, or mark as junk a set of message uids
     */
    public function process() {
        list($success, $form) = $this->process_form(array('action_type', 'message_ids'));
        if ($success) {
            if (in_array($form['action_type'], array('delete', 'read', 'unread', 'flag', 'unflag', 'archive', 'junk'))) {
                $ids = process_imap_message_ids($form['message_ids']);
                $errs = 0;
                $msgs = 0;
                $moved = array();
                $status = array();
                foreach ($ids as $server => $folders) {
                    $specials = get_special_folders($this, $server);
                    $mailbox = Hm_IMAP_List::get_connected_mailbox($server, $this->cache);
                    if ($mailbox && $mailbox->authed()) {
                        $server_details = $this->user_config->get('imap_servers')[$server];

                        foreach ($folders as $folder => $uids) {
                            $status['imap_'.$server.'_'.$folder] = $mailbox->get_folder_state();
                            $action_result = $this->perform_action($mailbox, $form['action_type'], $uids, $folder, $specials, $server_details);
                            if ($action_result['error'] && ! $action_result['folder_not_found_error']) {
                                $errs++;
                            } else {
                                $msgs += count($uids);
                                $moved = array_merge($moved, $action_result['moved']);
                            }
                        }
                    }
                }
                if ($errs > 0) {
                    Hm_Msgs::add(sprintf('An error occurred trying to %s some messages!', $form['action_type'], $server), 'danger');
                }
                $this->out('move_count', $moved);
                if (count($status) > 0) {
                    $this->out('folder_state', $status);
                }
            }
        }
    }

    /**
     * Perform a specified action on a set of messages in a mailbox.
     *
     * This function processes messages based on the provided action type (e.g., 'move', 'delete'),
     * moving them to a special folder if necessary, or performing an operation like expunging deleted messages.
     * It handles creating folders, moving messages to a special folder, and managing message status accordingly.
     *
     * @param object $mailbox The mailbox object used to perform actions.
     * @param string $action_type The type of action to perform (e.g., 'move', 'delete').
     * @param array $uids The unique identifiers (UIDs) of the messages to act upon.
     * @param string $folder The folder where the messages currently reside.
     * @param array $specials Special folder information for handling specific actions.
     * @param array $server_details Details of the server, including its unique ID and settings.
     *
     * @return array Returns an associative array with:
     *   - 'error' => bool Indicates if an error occurred during the operation.
     *   - 'moved' => array List of moved message identifiers in a specific format.
     */
    private function perform_action($mailbox, $action_type, $uids, $folder, $specials, $server_details) {
        $error = false;
        $moved = array();
        $folder_name = hex2bin($folder);
        $special_folder = $this->get_special_folder($action_type, $specials, $server_details);

        if ($special_folder && $special_folder != $folder_name) {
            if ($this->user_config->get('original_folder_setting', false)) {
                $special_folder .= '/' . $folder_name;
                if (!count($mailbox->get_folder_status($special_folder))) {
                    $mailbox->create_folder($special_folder);
                }
            }
            if (!$mailbox->message_action($folder_name, 'MOVE', $uids, $special_folder)['status']) {
                $error = true;
            } else {
                foreach ($uids as $uid) {
                    $moved[] = sprintf("imap_%s_%s_%s", $server_details['id'], $uid, $folder);
                }
            }
        } else {
            if (!$mailbox->message_action($folder_name, mb_strtoupper($action_type), $uids)['status']) {
                $error = true;
            } else {
                foreach ($uids as $uid) {
                    $moved[] = sprintf("imap_%s_%s_%s", $server_details['id'], $uid, $folder);
                }
                if ($action_type == 'delete') {
                    $mailbox->message_action($folder_name, 'EXPUNGE', $uids);
                }
            }
        }

        $folderNotFoundError = false;
        if (!$special_folder && $action_type != 'read' && $action_type != 'unread' && $action_type != 'flag' && $action_type != 'unflag') {
            Hm_Msgs::add(sprintf('No %s folder configured for %s. Please go to <a href="?page=folders&imap_server_id=%s">Folders seetting</a> and configure one', $action_type, $server_details['name'], $server_details['id']), empty($moved) ? 'danger' : 'warning');
            $folderNotFoundError = true;
        }

        return ['error' => $error, 'moved' => $moved, 'folder_not_found_error' => $folderNotFoundError];
    }

    /**
     * Retrieves the special folder associated with a specific action type.
     *
     * This function checks the given action type (e.g., 'delete', 'archive', 'junk') and looks for a corresponding
     * special folder from the provided special folders list. If the folder is not found for the action, it logs an
     * error message, unless the action type is one of 'read', 'unread', 'flag', or 'unflag'.
     *
     * @param string $action_type The action type that determines which special folder to retrieve (e.g., 'delete', 'archive').
     * @param array $specials An associative array of special folder names, like 'trash', 'archive', and 'junk'.
     * @param array $server_details Details of the server, including its name.
     *
     * @return string|false Returns the special folder name if found, or false if no corresponding folder is configured.
     */
    private function get_special_folder($action_type, $specials, $server_details) {
        $folder = false;
        if ($action_type == 'delete' && array_key_exists('trash', $specials)) {
            $folder = $specials['trash'];
        } elseif ($action_type == 'archive' && array_key_exists('archive', $specials)) {
            $folder = $specials['archive'];
        } elseif ($action_type == 'junk' && array_key_exists('junk', $specials)) {
            $folder = $specials['junk'];
        }
        return $folder;
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
            $terms = validate_search_terms($this->request->get['search_terms']);
            $since = isset($this->request->get['search_since']) ? process_since_argument($this->request->get['search_since'], true): DEFAULT_SEARCH_SINCE;
            $fld = isset($this->request->get['search_fld']) ? validate_search_fld($this->request->get['search_fld']): DEFAULT_SEARCH_FLD;
            $ids = explode(',', $form['imap_server_ids']);
            $date = process_since_argument($since);
            $folder = bin2hex('INBOX');
            if (array_key_exists('folder', $this->request->post)) {
                $folder = $this->request->post['folder'];
            }
            list($status, $msg_list) = merge_imap_search_results($ids, 'ALL', $this->session, $this->cache, array(hex2bin($folder)), MAX_PER_SOURCE, array(array(search_since_based_on_setting($this->user_config), $date), array($fld, $terms)));
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
class Hm_Handler_imap_message_list extends Hm_Handler_Module {
    /**
     * Returns list of message data for the Everthing page
     */
    public function process() {
        $defaultGetParams = [
            'list_page' => 1,
            'sort' => 'arrival',
        ];
        $this->request->get = array_merge($defaultGetParams, $this->request->get);

        list($success, $form) = $this->process_form(array('imap_server_ids', 'imap_folder_ids'));

        if ($success) {
            $ids = explode(',', $form['imap_server_ids']);
            $folders = explode(',', $form['imap_folder_ids']);
        } else {
            $userCustomSources = $this->session->get('custom_imap_sources', user:true);
            if (! $userCustomSources) {
                $userCustomSources = [];
            }
            $data_sources = imap_data_sources($userCustomSources);
            $ids = array_map(function($ds) { return $ds['id']; }, $data_sources);
            $folders = array_map(function($ds) { return $ds['folder']; }, $data_sources);
        }

        list($sort, $reverse) = process_sort_arg($this->request->get['sort'], $this->user_config->get('default_sort_order_setting', 'arrival'));

        if (isset($this->request->post['list_path'])) {
            $list_path = $this->request->post['list_path'];
        } else {
            $list_path = $this->get('list_path');
        }

        switch ($list_path) {
            case 'email':
                $filter = 'ALL';
                $limit = $this->user_config->get('all_email_per_source_setting', DEFAULT_ALL_EMAIL_PER_SOURCE);
                $date = process_since_argument($this->user_config->get('all_email_since_setting', DEFAULT_SINCE));
                break;
            case 'combined_inbox':
                $filter = 'ALL';
                $limit = $this->user_config->get('all_per_source_setting', DEFAULT_ALL_EMAIL_PER_SOURCE);
                $date = process_since_argument($this->user_config->get('all_since_setting', DEFAULT_SINCE));
                break;
            case 'flagged':
            case 'unread':
                $filter = $list_path == 'unread' ? 'UNSEEN' : mb_strtoupper($list_path);
            default:
                if (empty($filter)) {
                    $filter = 'ALL';
                }
                if ($list_path) {
                    $limit = $this->user_config->get($list_path.'_per_source_setting', DEFAULT_PER_SOURCE);
                    $date = process_since_argument($this->user_config->get($list_path.'_since_setting', DEFAULT_SINCE));
                } else {
                    $limit = $this->user_config->get('all_per_source_setting', DEFAULT_ALL_PER_SOURCE);
                    $date = process_since_argument($this->user_config->get('all_since_setting', DEFAULT_SINCE));
                }
        }

        if ($this->get('list_filter')) {
            $filter = mb_strtoupper($this->get('list_filter'));
        }

        $terms = [[search_since_based_on_setting($this->user_config), $date]];

        $messages = [];
        $status = [];
        foreach ($ids as $key => $id) {
            $details = Hm_IMAP_List::dump($id);
            $mailbox = Hm_IMAP_List::get_connected_mailbox($id, $this->cache);
            if($this->get('list_path') == 'snoozed' && !$mailbox->folder_exists('Snoozed')) {
                continue;
            }
            $uids = $mailbox->search(hex2bin($folders[$key]), $filter, $terms, $sort, $reverse);

            $total = count($uids);
            $uids = array_slice($uids, 0, $limit);

            $headers = $mailbox->get_message_list(hex2bin($folders[$key]), $uids);
            foreach ($uids as $uid) {
                if (isset($headers[$uid])) {
                    $msg = $headers[$uid];
                } elseif (isset($headers[bin2hex($uid)])) {
                    $msg = $headers[bin2hex($uid)];
                } else {
                    continue;
                }
                
                // Debug: Check if message is from a blocked sender
                if (isset($msg['from']) && !empty($msg['from'])) {
                    $sender_email = extract_sender_email_from_headers(array('From' => $msg['from']));
                    if ($sender_email) {
                        delayed_debug_log('Message received from sender', array(
                            'sender' => $sender_email,
                            'subject' => isset($msg['subject']) ? $msg['subject'] : 'No subject',
                            'uid' => $uid,
                            'folder' => $folders[$key],
                            'server_id' => $id,
                            'server_name' => $details['name']
                        ));
                        
                        // Check if this sender is in the blocked list
                        if (is_auto_block_spam_enabled($this->user_config)) {
                            $blocked_senders = get_blocked_senders_list($this->user_config, $id);
                            if (in_array($sender_email, $blocked_senders)) {
                                delayed_debug_log('BLOCKED SENDER DETECTED - Message should be filtered', array(
                                    'sender' => $sender_email,
                                    'subject' => isset($msg['subject']) ? $msg['subject'] : 'No subject',
                                    'uid' => $uid,
                                    'folder' => $folders[$key],
                                    'server_id' => $id,
                                    'blocked_senders_count' => count($blocked_senders),
                                    'blocked_senders_sample' => array_slice($blocked_senders, 0, 5)
                                ));
                            } else {
                                delayed_debug_log('Sender not in blocked list', array(
                                    'sender' => $sender_email,
                                    'blocked_senders_count' => count($blocked_senders),
                                    'blocked_senders_sample' => array_slice($blocked_senders, 0, 5)
                                ));
                            }
                        } else {
                            delayed_debug_log('Auto-blocking is disabled', array(
                                'sender' => $sender_email,
                                'auto_block_enabled' => false
                            ));
                        }
                    }
                }
                
                $msg['server_id'] = $id;
                $msg['server_name'] = $details['name'];
                $msg['folder'] = $folders[$key];
                $messages[] = $msg;
            }

            $status['imap_'.$id.'_'.$folders[$key]] = $mailbox->get_folder_state(); // this is faster than get_folder_status as search call above already gets this folder's state
        }

        $this->out('folder_status', $status);
        $this->out('imap_message_list_data', $messages);
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
                Hm_Msgs::add('You must supply a name and a JMAP server URL', 'warning');
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
                Hm_Msgs::add("Added server!. To preserve these settings after logout, please go to <a class='alert-link' href='/?page=save'>Save Settings</a>.");
                $this->session->record_unsaved('JMAP server added');
            }
            else {
                Hm_Msgs::add('Could not access supplied URL', 'warning');
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
                Hm_Msgs::add('You must supply a name, a server and a port', 'warning');
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
                    Hm_Msgs::add(sprintf('Could not add server: %s', $errstr), 'danger');
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
                Hm_Msgs::add("Could not save EWS server", 'danger');
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
            Hm_Msgs::add("EWS server saved. To preserve these settings after logout, please go to <a class='alert-link' href='/?page=save'>Save Settings</a>.");
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
            $this->out('move_copy_controls', true);
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
        if (count($active)===0) {
            $data_sources = imap_data_sources();
            $active = array_map(function($ds) { return $ds['id']; }, $data_sources);
        }
        $updated = 0;
        foreach ($active as $server_id) {
            $server = Hm_IMAP_List::dump($server_id, true);
            if ( $server && array_key_exists('auth', $server) && $server['auth'] == 'xoauth2') {
                $results = imap_refresh_oauth2_token($server, $this->config);
                if (!empty($results)) {
                    if (Hm_IMAP_List::update_oauth2_token($server_id, $results[1], $results[0])) {
                        Hm_Debug::add(sprintf('Oauth2 token refreshed for IMAP server id %s', $server_id), 'info');
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
        // Output user_config for output modules that need it
        $this->out('user_config', $this->user_config);
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
        Hm_Debug::add(sprintf('Busted cache for IMAP server %s', $form['imap_server_id']), 'info');
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
                    // Ensure sieve functions are loaded
                    if (!function_exists('get_sieve_client_factory')) {
                        require_once APP_PATH.'modules/sievefilters/functions.php';
                    }
                    if (!class_exists('Hm_Sieve_Client_Factory')) {
                        require_once APP_PATH.'modules/sievefilters/hm-sieve.php';
                    }
                    
                    $factory = get_sieve_client_factory($this->config);
                    try {
                        $client = $factory->init($this->user_config, $imap_details, $this->module_is_supported('nux'));
                    } catch (Exception $e) {
                        Hm_Msgs::add("Failed to authenticate to the Sieve host", "danger");
                        return;
                    }
                }

                $mailbox = false;
                $cache = Hm_IMAP_List::get_cache($this->cache, $form['imap_server_id']);
                $mailbox = Hm_IMAP_List::connect($form['imap_server_id'], $cache);
                if ($mailbox) {
                    if ($mailbox->authed()) {
                        Hm_Msgs::add(sprintf("Successfully authenticated to the %s server : %s", $mailbox->server_type(), $imap_details['user']));
                    }
                    else {
                        Hm_Msgs::add(sprintf("Failed to authenticate to the %s server : %s", $mailbox->server_type(), $imap_details['user']), "danger");
                    }
                }
                else {
                    Hm_Msgs::add('Username and password are required', 'warning');
                    $this->out('old_form', $form);
                }
                $this->out('imap_connect_details', $imap_details);
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

                $this->out('is_archive_folder', $mailbox->is_archive_folder($form['imap_server_id'], $this->user_config, $form['folder']));
                $this->out('folder_status', array('imap_'.$form['imap_server_id'].'_'.$form['folder'] => $mailbox->get_folder_state()));
                $this->out('msg_struct', $msg_struct);
                $this->out('list_headers', get_list_headers($msg_headers));
                $this->out('msg_headers', $msg_headers);
                $this->out('imap_prefetch', $prefetch);
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
 * Store reply details for a message if not already in session
 * @subpackage imap/handler
 */
class Hm_Handler_imap_store_reply_details extends Hm_Handler_Module {
    public function process() {
        if (! array_key_exists('list_path', $this->request->get) || ! array_key_exists('uid', $this->request->get)) {
            return;
        }

        $cache_name = sprintf('reply_details_%s_%s',
            $this->request->get['list_path'],
            $this->request->get['uid']
        );
        $reply_details = $this->session->get($cache_name, false);

        if ($reply_details) {
            return;
        }

        list($type, $server_id, $folder) = explode('_', $this->request->get['list_path']);
        $uid = $this->request->get['uid'];

        $mailbox = Hm_IMAP_List::get_connected_mailbox($server_id, $this->cache);
        if ($mailbox && $mailbox->authed()) {
            $prefetch = true;
            $mailbox->set_read_only($prefetch);
            $part = false;
            list($msg_struct, $msg_struct_current, $msg_text, $part) = $mailbox->get_structured_message(hex2bin($folder), $uid, $part, $this->user_config->get('text_only_setting', false));
            $msg_headers = $mailbox->get_message_headers(hex2bin($folder), $uid);

            clear_existing_reply_details($this->session);
            $msg_struct_current['type'] = 'text';
            $msg_struct_current['subtype'] = 'plain';
            $this->session->set(sprintf('reply_details_imap_%s_%s_%s', $server_id, $folder, $uid),
                array('ts' => time(), 'msg_struct' => $msg_struct_current, 'msg_text' => $msg_text, 'msg_headers' => $msg_headers));
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
                $action = (bool) $this->request->post['hide_imap_server'];
                $server_type = imap_server_type($form['imap_server_id']);
                Hm_IMAP_List::toggle_hidden($form['imap_server_id'], $action);
                if ($action) {
                    Hm_Msgs::add(sprintf('%s server has been hidden', $server_type));
                } else {
                    Hm_Msgs::add(sprintf('%s server is now visible', $server_type));
                }
                $this->session->record_unsaved(sprintf('%s server visibility updated', $server_type));
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

/**
 * Report a message as spam
 * @subpackage imap/handler
 */
class Hm_Handler_imap_report_spam extends Hm_Handler_Module {
    use Hm_Rate_Limiter_Trait;
    public function process() {
        // Include Composer autoloader for PhpSieveManager
        if (file_exists(VENDOR_PATH . 'autoload.php')) {
            require_once VENDOR_PATH . 'autoload.php';
        }
        
        // Include Sieve functions for auto-blocking
        if (!function_exists('get_sieve_client_factory')) {
            require_once APP_PATH.'modules/sievefilters/functions.php';
        }
        
        // Include Sieve client factory class
        if (!class_exists('Hm_Sieve_Client_Factory')) {
            require_once APP_PATH.'modules/sievefilters/hm-sieve.php';
        }
        
        // Include spam reporting configuration
        if (!function_exists('is_auto_block_spam_enabled')) {
            require_once APP_PATH.'modules/imap/spam_report_config.php';
        }
        
        // Include spam reporting utilities
        if (!function_exists('auto_block_spam_sender')) {
            require_once APP_PATH.'modules/imap/spam_report_utils.php';
        }
        
        // Include spam reporting services
        if (!class_exists('Hm_Spam_Reporter_Factory')) {
            require_once APP_PATH.'modules/imap/spam_report_services.php';
        }
        
        // Debug: Log what we've loaded
        delayed_debug_log('Spam report handler: Dependencies loaded', array(
            'sieve_functions_loaded' => function_exists('get_sieve_client_factory'),
            'sieve_factory_class_loaded' => class_exists('Hm_Sieve_Client_Factory'),
            'spam_config_loaded' => function_exists('is_auto_block_spam_enabled'),
            'spam_utils_loaded' => function_exists('auto_block_spam_sender'),
            'spam_services_loaded' => class_exists('Hm_Spam_Reporter_Factory'),
            'php_sieve_manager_client_exists' => class_exists('PhpSieveManager\ManageSieve\Client'),
            'php_sieve_manager_filter_factory_exists' => class_exists('PhpSieveManager\Filters\FilterFactory'),
            'composer_autoloader_exists' => file_exists(VENDOR_PATH . 'autoload.php')
        ));
        try {
            delayed_debug_log('Report Spam handler starting');
            
            // Debug: Check if IMAP_List is initialized
            delayed_debug_log('Auto-block debug: IMAP_List initialization check', array(
                'imap_list_initialized' => class_exists('Hm_IMAP_List'),
                'user_config_loaded' => !empty($this->user_config),
                'session_loaded' => !empty($this->session)
            ));
            list($success, $form) = $this->process_form(array('imap_msg_uids', 'imap_server_id', 'folder', 'spam_reason'));
            delayed_debug_log('Form data:', $form);
            if (!$this->check_rate_limit('ajax_imap_report_spam')) {
                delayed_debug_log('Rate limit exceeded for spam report');
                return;
            }
            if (!$success || !isset($form['imap_msg_uids'])) {
                delayed_debug_log('imap_msg_uids missing in form data');
                Hm_Msgs::add('Failed to process spam report: Missing message UIDs', 'error');
                $this->out('imap_report_spam_error', true);
                return;
            }
            $uids = explode(',', $form['imap_msg_uids']);
            $uids = array_filter($uids, function($uid) { return !empty(trim($uid)); });
            delayed_debug_log('Processed UIDs:', $uids);
            if (!isset($form['imap_server_id']) || !isset($form['folder']) || empty($uids)) {
                delayed_debug_log('Missing required form fields or empty UIDs', $form);
                Hm_Msgs::add('Failed to process spam report: Invalid request data', 'error');
                $this->out('imap_report_spam_error', true);
                return;
            }
            $junk_folder = false;
            $form_folder = hex2bin($form['folder']);
            $errors = 0;
            $status = null;
            $spam_reason = isset($form['spam_reason']) && !empty($form['spam_reason']) ? $form['spam_reason'] : 'No reason provided';
            $specials = get_special_folders($this, $form['imap_server_id']);
            if (array_key_exists('junk', $specials) && $specials['junk']) {
                $junk_folder = $specials['junk'];
            } else {
                Hm_Msgs::add('No junk folder configured for this IMAP server', 'warning');
                $errors++;
            }
            $mailbox = Hm_IMAP_List::get_connected_mailbox($form['imap_server_id'], $this->cache);
            if (!$mailbox) {
                Hm_Msgs::add('Failed to connect to mailbox', 'error');
                $errors++;
            } elseif (!$mailbox->authed()) {
                Hm_Msgs::add('Not authenticated to mailbox', 'error');
                $errors++;
            }
            $bulk_results = array();
            if (!$errors) {
                foreach ($uids as $uid) {
                    $result = array('uid' => $uid, 'success' => false, 'error' => '');
                    $headers = $mailbox->get_message_headers($form_folder, $uid);
                    $message_data = array(
                        'uid' => $uid,
                        'folder' => $form_folder,
                        'from' => isset($headers['From']) ? $headers['From'] : '',
                        'subject' => isset($headers['Subject']) ? $headers['Subject'] : '',
                        'headers' => $headers
                    );
                    $enabled_services = get_enabled_spam_services();
                    foreach ($enabled_services as $service_name => $service_config) {
                        $reporter = Hm_Spam_Reporter_Factory::create($service_name, $mailbox, $message_data);
                        if ($reporter) {
                            $service_result = $reporter->report($spam_reason);
                            if (!$service_result['success']) {
                                $result['error'] .= $service_result['error'] . '; ';
                            }
                        }
                    }
                    $move_result = $mailbox->message_action($form_folder, 'MOVE', array($uid), $junk_folder);
                    if ($move_result['status']) {
                        $result['success'] = true;
                        // Debug: Check user configuration first
                        $user_imap_servers = $this->user_config->get('imap_servers', array());
                        delayed_debug_log('Auto-block debug: User IMAP servers', array(
                            'user_imap_servers_keys' => array_keys($user_imap_servers),
                            'user_imap_servers_data' => $user_imap_servers
                        ));
                        
                        // Debug: Check if the specific server exists in user config
                        if (isset($user_imap_servers[$form['imap_server_id']])) {
                            $user_server_config = $user_imap_servers[$form['imap_server_id']];
                            delayed_debug_log('Auto-block debug: User server config found', array(
                                'server_id' => $form['imap_server_id'],
                                'user_server_keys' => array_keys($user_server_config),
                                'user_server_data' => $user_server_config,
                                'has_sieve_in_user_config' => isset($user_server_config['sieve_config_host'])
                            ));
                        } else {
                            delayed_debug_log('Auto-block debug: Server not found in user config', array(
                                'server_id' => $form['imap_server_id'],
                                'available_server_ids' => array_keys($user_imap_servers)
                            ));
                        }
                        
                        // Debug: Check Hm_IMAP_List state
                        delayed_debug_log('Auto-block debug: Hm_IMAP_List state', array(
                            'all_servers' => Hm_IMAP_List::dump(),
                            'specific_server' => Hm_IMAP_List::dump($form['imap_server_id'], true)
                        ));
                        
                        // Debug: Check if IMAP_List is initialized properly
                        delayed_debug_log('Auto-block debug: IMAP_List initialization', array(
                            'imap_list_class_exists' => class_exists('Hm_IMAP_List'),
                            'imap_list_initialized' => method_exists('Hm_IMAP_List', 'dump'),
                            'user_config_imap_servers_count' => count($user_imap_servers),
                            'imap_list_dump_count' => count(Hm_IMAP_List::dump())
                        ));
                        
                        // Use Hm_IMAP_List::dump() to get the proper IMAP server configuration
                        $imap_account = Hm_IMAP_List::dump($form['imap_server_id'], true);
                        delayed_debug_log('Auto-block check: IMAP account found', array(
                            'has_account' => !empty($imap_account),
                            'has_sieve_host' => isset($imap_account['sieve_config_host']),
                            'sieve_host' => isset($imap_account['sieve_config_host']) ? $imap_account['sieve_config_host'] : 'not set',
                            'imap_account_keys' => array_keys($imap_account),
                            'imap_account_data' => $imap_account
                        ));
                        
                        if ($imap_account && isset($imap_account['sieve_config_host'])) {
                            delayed_debug_log('Auto-block: Starting auto-block process', array(
                                'junk_folder' => $junk_folder
                            ));
                            $auto_block_result = auto_block_spam_sender(
                                $this->user_config,
                                $this->config,
                                $form['imap_server_id'],
                                $message_data,
                                $spam_reason,
                                $junk_folder
                            );
                            if (!$auto_block_result['success']) {
                                $result['error'] .= 'Auto-block failed: ' . $auto_block_result['error'] . '; ';
                                delayed_debug_log('Auto-block: Failed', array('error' => $auto_block_result['error']));
                            } else {
                                delayed_debug_log('Auto-block: Success', array('result' => $auto_block_result));
                            }
                        } else {
                            delayed_debug_log('Auto-block: Skipped - missing sieve_config_host in IMAP server configuration', array(
                                'server_id' => $form['imap_server_id'],
                                'server_name' => $imap_account['name'],
                                'available_keys' => array_keys($imap_account)
                            ));
                        }
                    } else {
                        $result['error'] .= 'Move to junk failed; ';
                    }
                    $bulk_results[] = $result;
                }
            }
            if (count($uids) === 1) {
                $status = isset($bulk_results[0]['success']) ? $bulk_results[0]['success'] : false;
                $this->out('imap_report_spam_error', !$status);
                if ($status) {
                    Hm_Msgs::add('Message reported as spam and moved to junk folder', 'success');
                } else {
                    Hm_Msgs::add('Failed to report message as spam', 'danger');
                }
            } else {
                $this->out('imap_report_spam_error', false);
                $this->out('bulk_spam_report_results', $bulk_results);
                $success_count = 0;
                foreach ($bulk_results as $result) {
                    if ($result['success']) {
                        $success_count++;
                    }
                }
                if ($success_count === count($bulk_results)) {
                    Hm_Msgs::add('All messages reported as spam and moved to junk folder', 'success');
                } elseif ($success_count > 0) {
                    Hm_Msgs::add("$success_count of " . count($bulk_results) . " messages reported as spam", 'warning');
                } else {
                    Hm_Msgs::add('Failed to report messages as spam', 'danger');
                }
            }
            $this->save_hm_msgs();
        } catch (Exception $e) {
            Hm_Msgs::add('An unexpected error occurred while reporting spam', 'error');
            $this->out('imap_report_spam_error', true);
            $this->save_hm_msgs();
        }
    }
}

/**
 * Process auto-block spam settings
 * @subpackage imap/handler
 */
class Hm_Handler_process_auto_block_spam_setting extends Hm_Handler_Module {
    public function process() {
        function auto_block_spam_enabled_callback($val) { return $val; }
        function auto_block_spam_action_callback($val) { return $val; }
        function auto_block_spam_scope_callback($val) { return $val; }
        
        process_site_setting('auto_block_spam_sender', $this, 'auto_block_spam_enabled_callback', true, true);
        process_site_setting('auto_block_spam_action', $this, 'auto_block_spam_action_callback', 'move_to_junk', true);
        process_site_setting('auto_block_spam_scope', $this, 'auto_block_spam_scope_callback', 'sender', true);
    }
}

/**
 * Process rate limiting settings
 * @subpackage imap/handler
 */
class Hm_Handler_process_rate_limit_settings extends Hm_Handler_Module {
    public function process() {
        function rate_limit_enabled_callback($val) { return $val; }
        function rate_limit_window_size_callback($val) { return max(60, min(86400, intval($val))); } // 1 minute to 24 hours
        function rate_limit_max_requests_callback($val) { return max(1, min(10000, intval($val))); } // 1 to 10000 requests
        function rate_limit_burst_limit_callback($val) { return max(1, min(1000, intval($val))); } // 1 to 1000 burst requests
        function rate_limit_burst_window_callback($val) { return max(10, min(3600, intval($val))); } // 10 seconds to 1 hour
        
        process_site_setting('rate_limit_enabled', $this, 'rate_limit_enabled_callback', true, true);
        process_site_setting('rate_limit_window_size', $this, 'rate_limit_window_size_callback', 3600, true);
        process_site_setting('rate_limit_max_requests', $this, 'rate_limit_max_requests_callback', 100, true);
        process_site_setting('rate_limit_burst_limit', $this, 'rate_limit_burst_limit_callback', 10, true);
        process_site_setting('rate_limit_burst_window', $this, 'rate_limit_burst_window_callback', 60, true);
    }
}

/**
 * Process external spam service enable/disable settings
 * @subpackage imap/handler
 */
class Hm_Handler_process_spam_services_setting extends Hm_Handler_Module {
    public function process() {
        function enable_spamcop_callback($val) { return (bool)$val; }
        function enable_abuseipdb_callback($val) { return (bool)$val; }
        function enable_stopforumspam_callback($val) { return (bool)$val; }
        function enable_cleantalk_callback($val) { return (bool)$val; }
        process_site_setting('enable_spamcop', $this, 'enable_spamcop_callback', true, true);
        process_site_setting('enable_abuseipdb', $this, 'enable_abuseipdb_callback', false, true);
        process_site_setting('enable_stopforumspam', $this, 'enable_stopforumspam_callback', false, true);
        process_site_setting('enable_cleantalk', $this, 'enable_cleantalk_callback', false, true);
    }
}

/**
 * Load spam services for management
 * @subpackage imap/handler
 */
class Hm_Handler_load_spam_services extends Hm_Handler_Module {
    public function process() {
        $manager = new Hm_Spam_Service_Manager($this->user_config);
        $services = $manager->getServices();
        $service_types = $manager->getServiceTypes();
        $template_variables = $manager->getTemplateVariables();
        
        // Debug output
        Hm_Debug::add('Spam services loaded: ' . count($services));
        Hm_Debug::add('Service types loaded: ' . count($service_types));
        Hm_Debug::add('Service type keys: ' . implode(', ', array_keys($service_types)));
        
        $this->out('spam_services', $services);
        $this->out('spam_service_types', $service_types);
        $this->out('spam_template_variables', $template_variables);
    }
}

/**
 * Add new spam service
 * @subpackage imap/handler
 */
class Hm_Handler_add_spam_service extends Hm_Handler_Module {
    public function process() {
        if (!array_key_exists('add_spam_service', $this->request->post)) {
            return;
        }

        list($success, $form) = $this->process_form(array('service_name', 'service_type', 'service_enabled'));
        if (!$success) {
            Hm_Msgs::add('Failed to process service data', 'error');
            return;
        }

        $manager = new Hm_Spam_Service_Manager($this->user_config);
        
        // Build service configuration based on type
        $service_config = array(
            'name' => $form['service_name'],
            'type' => $form['service_type'],
            'enabled' => isset($form['service_enabled']) ? true : false
        );

        // Add type-specific fields
        switch ($form['service_type']) {
            case 'email':
                if (isset($form['email_endpoint'])) {
                    $service_config['endpoint'] = $form['email_endpoint'];
                }
                if (isset($form['email_subject_template'])) {
                    $service_config['subject_template'] = $form['email_subject_template'];
                }
                if (isset($form['email_body_template'])) {
                    $service_config['body_template'] = $form['email_body_template'];
                }
                $service_config['require_headers'] = isset($form['email_require_headers']) ? true : false;
                $service_config['require_body'] = isset($form['email_require_body']) ? true : false;
                break;

            case 'api':
                if (isset($form['api_endpoint'])) {
                    $service_config['endpoint'] = $form['api_endpoint'];
                }
                if (isset($form['api_method'])) {
                    $service_config['method'] = $form['api_method'];
                }
                if (isset($form['api_auth_type'])) {
                    $service_config['auth_type'] = $form['api_auth_type'];
                }
                if (isset($form['api_auth_header'])) {
                    $service_config['auth_header'] = $form['api_auth_header'];
                }
                if (isset($form['api_auth_value'])) {
                    $service_config['auth_value'] = $form['api_auth_value'];
                }
                if (isset($form['api_payload_template'])) {
                    $service_config['payload_template'] = $form['api_payload_template'];
                }
                if (isset($form['api_response_code'])) {
                    $service_config['response_code'] = intval($form['api_response_code']);
                }
                if (isset($form['api_timeout'])) {
                    $service_config['timeout'] = intval($form['api_timeout']);
                }
                break;

            case 'dns':
                if (isset($form['dns_query_format'])) {
                    $service_config['query_format'] = $form['dns_query_format'];
                }
                if (isset($form['dns_response_type'])) {
                    $service_config['response_type'] = $form['dns_response_type'];
                }
                if (isset($form['dns_timeout'])) {
                    $service_config['timeout'] = intval($form['dns_timeout']);
                }
                break;

            case 'custom':
                if (isset($form['custom_fields'])) {
                    $service_config['custom_fields'] = $form['custom_fields'];
                }
                break;
        }

        $service_id = $manager->addService($service_config);
        if ($service_id) {
            Hm_Msgs::add('Service added successfully', 'success');
            $this->out('spam_service_added', $service_id);
        } else {
            Hm_Msgs::add('Failed to add service: Invalid configuration', 'error');
        }
    }
}

/**
 * Edit existing spam service
 * @subpackage imap/handler
 */
class Hm_Handler_edit_spam_service extends Hm_Handler_Module {
    public function process() {
        if (!array_key_exists('edit_spam_service', $this->request->post)) {
            return;
        }

        list($success, $form) = $this->process_form(array('service_id', 'service_name', 'service_type', 'service_enabled'));
        if (!$success) {
            Hm_Msgs::add('Failed to process service data', 'error');
            return;
        }

        $manager = new Hm_Spam_Service_Manager($this->user_config);
        
        // Build service configuration (same as add, but with service_id)
        $service_config = array(
            'name' => $form['service_name'],
            'type' => $form['service_type'],
            'enabled' => isset($form['service_enabled']) ? true : false
        );

        // Add type-specific fields (same logic as add)
        switch ($form['service_type']) {
            case 'email':
                if (isset($form['email_endpoint'])) {
                    $service_config['endpoint'] = $form['email_endpoint'];
                }
                if (isset($form['email_subject_template'])) {
                    $service_config['subject_template'] = $form['email_subject_template'];
                }
                if (isset($form['email_body_template'])) {
                    $service_config['body_template'] = $form['email_body_template'];
                }
                $service_config['require_headers'] = isset($form['email_require_headers']) ? true : false;
                $service_config['require_body'] = isset($form['email_require_body']) ? true : false;
                break;

            case 'api':
                if (isset($form['api_endpoint'])) {
                    $service_config['endpoint'] = $form['api_endpoint'];
                }
                if (isset($form['api_method'])) {
                    $service_config['method'] = $form['api_method'];
                }
                if (isset($form['api_auth_type'])) {
                    $service_config['auth_type'] = $form['api_auth_type'];
                }
                if (isset($form['api_auth_header'])) {
                    $service_config['auth_header'] = $form['api_auth_header'];
                }
                if (isset($form['api_auth_value'])) {
                    $service_config['auth_value'] = $form['api_auth_value'];
                }
                if (isset($form['api_payload_template'])) {
                    $service_config['payload_template'] = $form['api_payload_template'];
                }
                if (isset($form['api_response_code'])) {
                    $service_config['response_code'] = intval($form['api_response_code']);
                }
                if (isset($form['api_timeout'])) {
                    $service_config['timeout'] = intval($form['api_timeout']);
                }
                break;

            case 'dns':
                if (isset($form['dns_query_format'])) {
                    $service_config['query_format'] = $form['dns_query_format'];
                }
                if (isset($form['dns_response_type'])) {
                    $service_config['response_type'] = $form['dns_response_type'];
                }
                if (isset($form['dns_timeout'])) {
                    $service_config['timeout'] = intval($form['dns_timeout']);
                }
                break;

            case 'custom':
                if (isset($form['custom_fields'])) {
                    $service_config['custom_fields'] = $form['custom_fields'];
                }
                break;
        }

        $success = $manager->updateService($form['service_id'], $service_config);
        if ($success) {
            Hm_Msgs::add('Service updated successfully', 'success');
        } else {
            Hm_Msgs::add('Failed to update service: Invalid configuration', 'error');
        }
    }
}

/**
 * Delete spam service
 * @subpackage imap/handler
 */
class Hm_Handler_delete_spam_service extends Hm_Handler_Module {
    public function process() {
        if (!array_key_exists('delete_spam_service', $this->request->post)) {
            return;
        }

        list($success, $form) = $this->process_form(array('service_id'));
        if (!$success) {
            Hm_Msgs::add('Failed to process delete request', 'error');
            return;
        }

        $manager = new Hm_Spam_Service_Manager($this->user_config);
        $success = $manager->deleteService($form['service_id']);
        
        if ($success) {
            Hm_Msgs::add('Service deleted successfully', 'success');
        } else {
            Hm_Msgs::add('Failed to delete service', 'error');
        }
    }
}

/**
 * Toggle spam service enabled/disabled
 * @subpackage imap/handler
 */
class Hm_Handler_toggle_spam_service extends Hm_Handler_Module {
    public function process() {
        if (!array_key_exists('toggle_spam_service', $this->request->post)) {
            return;
        }

        list($success, $form) = $this->process_form(array('service_id', 'enabled'));
        if (!$success) {
            Hm_Msgs::add('Failed to process toggle request', 'error');
            return;
        }

        $manager = new Hm_Spam_Service_Manager($this->user_config);
        $success = $manager->setServiceEnabled($form['service_id'], $form['enabled'] === 'true');
        
        if ($success) {
            $status = $form['enabled'] === 'true' ? 'enabled' : 'disabled';
            Hm_Msgs::add('Service ' . $status . ' successfully', 'success');
        } else {
            Hm_Msgs::add('Failed to update service status', 'error');
        }
    }
}

/**
 * Update predefined spam service
 * @subpackage imap/handler
 */
class Hm_Handler_update_predefined_service extends Hm_Handler_Module {
    public function process() {
        if (!array_key_exists('update_predefined_service', $this->request->post)) {
            return;
        }

        list($success, $form) = $this->process_form(array('service_id'));
        if (!$success) {
            Hm_Msgs::add('Failed to process service data', 'error');
            return;
        }

        $service_id = $form['service_id'];
        $manager = new Hm_Spam_Service_Manager($this->user_config);
        
        // Build service configuration based on service type
        switch ($service_id) {
            case 'spamcop':
                $service_config = array(
                    'name' => 'SpamCop',
                    'type' => 'email',
                    'enabled' => isset($this->request->post['service_enabled']) ? true : false
                );
                
                if (isset($this->request->post['email_endpoint'])) {
                    $service_config['endpoint'] = $this->request->post['email_endpoint'];
                }
                break;
                
            case 'abuseipdb':
                $service_config = array(
                    'name' => 'AbuseIPDB',
                    'type' => 'api',
                    'enabled' => isset($this->request->post['service_enabled']) ? true : false
                );
                
                if (isset($this->request->post['api_endpoint'])) {
                    $service_config['endpoint'] = $this->request->post['api_endpoint'];
                }
                if (isset($this->request->post['api_method'])) {
                    $service_config['method'] = $this->request->post['api_method'];
                }
                if (isset($this->request->post['api_auth_type'])) {
                    $service_config['auth_type'] = $this->request->post['api_auth_type'];
                }
                if (isset($this->request->post['api_auth_header'])) {
                    $service_config['auth_header'] = $this->request->post['api_auth_header'];
                }
                if (isset($this->request->post['api_auth_value'])) {
                    $service_config['auth_value'] = $this->request->post['api_auth_value'];
                }
                if (isset($this->request->post['api_payload_template'])) {
                    $service_config['payload_template'] = $this->request->post['api_payload_template'];
                }
                break;
                
            case 'stopforumspam':
                $service_config = array(
                    'name' => 'StopForumSpam',
                    'type' => 'api',
                    'enabled' => isset($this->request->post['service_enabled']) ? true : false
                );
                
                if (isset($this->request->post['api_endpoint'])) {
                    $service_config['endpoint'] = $this->request->post['api_endpoint'];
                }
                if (isset($this->request->post['api_method'])) {
                    $service_config['method'] = $this->request->post['api_method'];
                }
                if (isset($this->request->post['api_auth_type'])) {
                    $service_config['auth_type'] = $this->request->post['api_auth_type'];
                }
                if (isset($this->request->post['api_auth_header'])) {
                    $service_config['auth_header'] = $this->request->post['api_auth_header'];
                }
                if (isset($this->request->post['api_auth_value'])) {
                    $service_config['auth_value'] = $this->request->post['api_auth_value'];
                }
                if (isset($this->request->post['api_payload_template'])) {
                    $service_config['payload_template'] = $this->request->post['api_payload_template'];
                }
                break;
                
            case 'cleantalk':
                $service_config = array(
                    'name' => 'CleanTalk',
                    'type' => 'api',
                    'enabled' => isset($this->request->post['service_enabled']) ? true : false
                );
                
                if (isset($this->request->post['api_endpoint'])) {
                    $service_config['endpoint'] = $this->request->post['api_endpoint'];
                }
                if (isset($this->request->post['api_method'])) {
                    $service_config['method'] = $this->request->post['api_method'];
                }
                if (isset($this->request->post['api_auth_type'])) {
                    $service_config['auth_type'] = $this->request->post['api_auth_type'];
                }
                if (isset($this->request->post['api_auth_header'])) {
                    $service_config['auth_header'] = $this->request->post['api_auth_header'];
                }
                if (isset($this->request->post['api_auth_value'])) {
                    $service_config['auth_value'] = $this->request->post['api_auth_value'];
                }
                if (isset($this->request->post['api_payload_template'])) {
                    $service_config['payload_template'] = $this->request->post['api_payload_template'];
                }
                break;
                
            default:
                Hm_Msgs::add('Unknown service type', 'error');
                return;
        }

        $success = $manager->updateService($service_id, $service_config);
        if ($success) {
            Hm_Msgs::add('Service updated successfully', 'success');
        } else {
            Hm_Msgs::add('Failed to update service: Invalid configuration', 'error');
        }
    }
}
