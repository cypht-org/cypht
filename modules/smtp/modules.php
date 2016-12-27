<?php

/**
 * SMTP modules
 * @package modules
 * @subpackage smtp
 */

if (!defined('DEBUG_MODE')) { die(); }

require APP_PATH.'modules/smtp/hm-smtp.php';
require APP_PATH.'modules/smtp/hm-mime-message.php';

/**
 * @subpackage smtp/handler
 */
class Hm_Handler_load_smtp_reply_to_details extends Hm_Handler_Module {
    public function process() {
        if (array_key_exists('list_path', $this->request->get) &&
            array_key_exists('uid', $this->request->get)) {

            $cache_name = sprintf('reply_details_%s_%s',
                $this->request->get['list_path'],
                $this->request->get['uid']
            );
            $reply_details = $this->session->get($cache_name, false);
            if ($reply_details) {
                $this->out('reply_details', $reply_details);
            }
        }
    }
}

/**
 * @subpackage smtp/handler
 */
class Hm_Handler_smtp_default_server extends Hm_Handler_Module {
    public function process() {
        list($success, $form) = $this->process_form(array('username', 'password'));
        if ($success) {
            $smtp_server = $this->config->get('default_smtp_server', false);
            if ($smtp_server) {
                $smtp_port = $this->config->get('default_smtp_port', 465);
                $smtp_tls = $this->config->get('default_smtp_tls', true);
                $servers = $this->user_config->get('smtp_servers', array());
                foreach ($servers as $index => $server) {
                    if ($server['server'] == $smtp_server && $server['tls'] == $smtp_tls && $server['port'] == $smtp_port) {
                        continue;
                    }
                    Hm_SMTP_List::add($server, $index);
                }
                $attributes = array(
                    'name' => $this->config->get('default_smtp_name', 'Default'),
                    'default' => true,
                    'server' => $smtp_server,
                    'port' => $smtp_port,
                    'tls' => $smtp_tls,
                    'user' => $form['username'],
                    'pass' => $form['password']
                );
                if ($this->config->get('default_smtp_no_auth', false)) {
                    $attributes['no_auth'] = true;
                }
                Hm_SMTP_List::add($attributes);
                $smtp_servers = Hm_SMTP_List::dump(false, true);
                $this->user_config->set('smtp_servers', $smtp_servers);
                $user_data = $this->user_config->dump();
                $this->session->set('user_data', $user_data);
                Hm_Debug::add('Default SMTP server added');
            }
        }
    }
}

/**
 * @subpackage smtp/handler
 */
class Hm_Handler_process_compose_type extends Hm_Handler_Module {
    public function process() {
        function smtp_compose_type_callback($val) { return $val; }
        process_site_setting('smtp_compose_type', $this, 'smtp_compose_type_callback');
    }
}

/**
 * @subpackage smtp/handler
 */
class Hm_Handler_process_auto_bcc extends Hm_Handler_Module {
    public function process() {
        function smtp_auto_bcc_callback($val) { return $val; }
        process_site_setting('smtp_auto_bcc', $this, 'smtp_auto_bcc_callback', false, true);
    }
}

/**
 * @subpackage smtp/handler
 */
class Hm_Handler_smtp_delete_attached_file extends Hm_Handler_Module {
    public function process() {
        if (array_key_exists('attachment_id', $this->request->post)) {
            $id = $this->request->post['attachment_id'];
            $filename = false;
            $remaining_files = array();
            $res = delete_uploaded_files($this->session, false, $id);
            if ($res) {
                Hm_Msgs::add('Attachment deleted');
            }
            else {
                Hm_Msgs::add('ERRAn error occurred trying to delete the attachment');
            }
        }
    }
}

/**
 * @subpackage smtp/handler
 */
class Hm_Handler_smtp_attach_file extends Hm_Handler_Module {
    public function process() {
        if (!array_key_exists('upload_file', $this->request->files)) {
            return;
        }
        if (!array_key_exists('draft_id', $this->request->post)) {
            return;
        }
        $file = $this->request->files['upload_file'];
        $draft_id = $this->request->post['draft_id'];
        $filepath = $this->config->get('attachment_dir');

        if (!$filepath) {
            Hm_Msgs::add('ERRNo directory configured for uploaded files.');
            return;
        }
        if (!is_readable($file['tmp_name'])) {
            Hm_Msgs::add('ERRAn error occurred saving the uploaded file.');
            return;
        }
        $content = file_get_contents($file['tmp_name']);
        if (!$content) {
            Hm_Msgs::add('ERRAn error occurred reading the uploaded file.');
            return;
        }
        if (!attach_file($content, $file, $filepath, $draft_id, $this)) {
            Hm_Msgs::add('ERRAn error occurred attaching the uploaded file.');
        }
    }
}

/**
 * @subpackage smtp/handler
 */
class Hm_Handler_process_delete_draft extends Hm_Handler_Module {
    public function process() {
        list($success, $form) = $this->process_form(array('draft_id'));
        if ($success) {
            delete_uploaded_files($this->session, $form['draft_id']);
            if (delete_draft($form['draft_id'], $this->session)) {
                Hm_Msgs::add('Draft deleted');
                $this->out('draft_id', $form['draft_id']);
            }
            else {
                Hm_Msgs::add('ERRAn error occurred trying to delete the requested draft');
                $this->out('draft_id', -1);
            }
        }
    }
}

/**
 * @subpackage smtp/handler
 */
class Hm_Handler_smtp_save_draft extends Hm_Handler_Module {
    public function process() {
        $to = array_key_exists('draft_to', $this->request->post) ? $this->request->post['draft_to'] : '';
        $body = array_key_exists('draft_body', $this->request->post) ? $this->request->post['draft_body'] : '';
        $subject = array_key_exists('draft_subject', $this->request->post) ? $this->request->post['draft_subject'] : '';
        $smtp = array_key_exists('draft_smtp', $this->request->post) ? $this->request->post['draft_smtp'] : '';
        $cc = array_key_exists('draft_cc', $this->request->post) ? $this->request->post['draft_cc'] : '';
        $bcc = array_key_exists('draft_bcc', $this->request->post) ? $this->request->post['draft_bcc'] : '';
        $inreplyto = array_key_exists('draft_in_reply_to', $this->request->post) ? $this->request->post['draft_in_reply_to'] : '';
        $draft_id = array_key_exists('draft_id', $this->request->post) ? $this->request->post['draft_id'] : false;
        $draft_notice = array_key_exists('draft_notice', $this->request->post) ? $this->request->post['draft_notice'] : false;

        if (array_key_exists('delete_uploaded_files', $this->request->post) && $this->request->post['delete_uploaded_files']) {
            delete_uploaded_files($this->session, $draft_id);
            delete_draft($draft_id, $this->session);
        }
        else {
            if (save_draft(array('draft_smtp' => $smtp, 'draft_to' => $to, 'draft_body' => $body,
                'draft_subject' => $subject, 'draft_cc' => $cc, 'draft_bcc' => $bcc,
                'draft_in_reply_to' => $inreplyto), $draft_id, $this->session) !== false) {
                if ($draft_notice) {
                    Hm_Msgs::add('Draft saved');
                    $this->out('draft_subject', $subject);
                }
            }
            elseif ($draft_notice) {
                Hm_Msgs::add('ERRYou must enter a subject to save a draft');
            }
        }
    }
}

/**
 * @subpackage smtp/handler
 */
class Hm_Handler_load_smtp_servers_from_config extends Hm_Handler_Module {
    public function process() {
        $servers = $this->user_config->get('smtp_servers', array());
        foreach ($servers as $index => $server) {
            Hm_SMTP_List::add( $server, $index );
        }
        $draft = array();
        $draft_id = count($this->session->get('compose_drafts', array()));
        $reply_type = false;
        if (array_key_exists('reply', $this->request->get) && $this->request->get['reply']) {
            $reply_type = 'reply';
        }
        elseif (array_key_exists('reply_all', $this->request->get) && $this->request->get['reply_all']) {
            $reply_type = 'reply_all';
        }
        elseif (array_key_exists('forward', $this->request->get) && $this->request->get['forward']) {
            $reply_type = 'forward';
        }
        elseif (array_key_exists('draft_id', $this->request->get)) {
            $draft = get_draft($this->request->get['draft_id'], $this->session);
            $draft_id = $this->request->get['draft_id'];
        }
        if ($reply_type) {
            $this->out('reply_type', $reply_type);
        }
        $this->out('compose_draft', $draft, false);
        $this->out('compose_draft_id', $draft_id);
        $this->out('uploaded_files', get_uploaded_files($draft_id, $this->session));
        $compose_type = $this->user_config->get('smtp_compose_type_setting', 0);
        if ($this->get('is_mobile', false)) {
            $compose_type = 0;
        }
        if (is_array($this->get('compose_draft')) && strlen(trim(join('', $this->get('compose_draft')))) == 0 && array_key_exists('compose_to', $this->request->get)) {
            $this->out('compose_draft', array('draft_to' => preg_replace('/^mailto:/', '', $this->request->get['compose_to'])));
        }
        $this->out('smtp_compose_type', $compose_type);
    }
}

/**
 * @subpackage smtp/handler
 */
class Hm_Handler_process_add_smtp_server extends Hm_Handler_Module {
    public function process() {
        if (isset($this->request->post['submit_smtp_server'])) {
            list($success, $form) = $this->process_form(array('new_smtp_name', 'new_smtp_address', 'new_smtp_port'));
            if (!$success) {
                Hm_Msgs::add('ERRYou must supply a name, a server and a port');
            }
            else {
                $tls = false;
                if (isset($this->request->post['tls'])) {
                    $tls = true;
                }
                if ($con = @fsockopen($form['new_smtp_address'], $form['new_smtp_port'], $errno, $errstr, 2)) {
                    Hm_SMTP_List::add( array(
                        'name' => $form['new_smtp_name'],
                        'server' => $form['new_smtp_address'],
                        'port' => $form['new_smtp_port'],
                        'tls' => $tls));
                    Hm_Msgs::add('Added SMTP server!');
                    $this->session->record_unsaved('SMTP server added');
                }
                else {
                    $this->session->set('add_form_vals', $form);
                    Hm_Msgs::add(sprintf('ERRCound not add server: %s', $errstr));
                }
            }
        }
        else {
            $this->out('add_form_vals', $this->session->get('add_form_vals', array()));
            $this->session->set('add_form_vals', array());
        }
    }
}

/**
 * @subpackage smtp/handler
 */
class Hm_Handler_add_smtp_servers_to_page_data extends Hm_Handler_Module {
    public function process() {
        $servers = Hm_SMTP_List::dump();
        $this->out('smtp_servers', $servers);
        $this->out('compose_drafts', $this->session->get('compose_drafts', array()));
    }
}

/**
 * @subpackage smtp/handler
 */
class Hm_Handler_save_smtp_servers extends Hm_Handler_Module {
    public function process() {
        $servers = Hm_SMTP_List::dump(false, true);
        $this->user_config->set('smtp_servers', $servers);
    }
}

/**
 * @subpackage smtp/handler
 */
class Hm_Handler_smtp_save extends Hm_Handler_Module {
    public function process() {
        $just_saved_credentials = false;
        if (isset($this->request->post['smtp_save'])) {
            list($success, $form) = $this->process_form(array('smtp_user', 'smtp_pass', 'smtp_server_id'));
            if (!$success) {
                Hm_Msgs::add('ERRUsername and Password are required to save a connection');
            }
            else {
                $smtp = Hm_SMTP_List::connect($form['smtp_server_id'], false, $form['smtp_user'], $form['smtp_pass'], true);
                if ($smtp->state == 'authed') {
                    $just_saved_credentials = true;
                    Hm_Msgs::add("Server saved");
                    $this->session->record_unsaved('SMTP server saved');
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
 * @subpackage smtp/handler
 */
class Hm_Handler_smtp_forget extends Hm_Handler_Module {
    public function process() {
        $just_forgot_credentials = false;
        if (isset($this->request->post['smtp_forget'])) {
            list($success, $form) = $this->process_form(array('smtp_server_id'));
            if ($success) {
                Hm_SMTP_List::forget_credentials($form['smtp_server_id']);
                $just_forgot_credentials = true;
                Hm_Msgs::add('Server credentials forgotten');
                $this->session->record_unsaved('SMTP server credentials forgotten');
            }
        }
        $this->out('just_forgot_credentials', $just_forgot_credentials);
    }
}

/**
 * @subpackage smtp/handler
 */
class Hm_Handler_smtp_delete extends Hm_Handler_Module {
    public function process() {
        if (isset($this->request->post['smtp_delete'])) {
            list($success, $form) = $this->process_form(array('smtp_server_id'));
            if ($success) {
                $res = Hm_SMTP_List::del($form['smtp_server_id']);
                if ($res) {
                    $this->out('deleted_server_id', $form['smtp_server_id']);
                    Hm_Msgs::add('Server deleted');
                    $this->session->record_unsaved('SMTP server deleted');
                }
            }
        }
    }
}

/**
 * @subpackage smtp/handler
 */
class Hm_Handler_smtp_connect extends Hm_Handler_Module {
    public function process() {
        $smtp = false;
        if (isset($this->request->post['smtp_connect'])) {
            list($success, $form) = $this->process_form(array('smtp_user', 'smtp_pass', 'smtp_server_id'));
            $smtp_details = Hm_SMTP_List::dump($form['smtp_server_id'], true);
            if ($smtp_details && ($success | array_key_exists('smtp_server_id', $form))) {
                if (array_key_exists('auth', $smtp_details) && $smtp_details['auth'] == 'xoauth2') {
                    $results = smtp_refresh_oauth2_token($smtp_details, $this->config);
                    if (!empty($results)) {
                        if (Hm_SMTP_List::update_oauth2_token($form['smtp_server_id'], $results[1], $results[0])) {
                            Hm_Debug::add(sprintf('Oauth2 token refreshed for SMTP server id %d', $form['smtp_server_id']));
                            $servers = Hm_SMTP_List::dump(false, true);
                            $this->user_config->set('smtp_servers', $servers);
                            $this->session->set('user_data', $this->user_config->dump());
                        }
                    }
                }
            }
            if ($success) {
                $smtp = Hm_SMTP_List::connect($form['smtp_server_id'], false, $form['smtp_user'], $form['smtp_pass']);
            }
            elseif (isset($form['smtp_server_id'])) {
                $smtp = Hm_SMTP_List::connect($form['smtp_server_id'], false);
            }
            if ($smtp && $smtp->state == 'authed') {
                Hm_Msgs::add("Successfully authenticated to the SMTP server");
            }
            elseif ($smtp && $smtp->state == 'connected') {
                # TODO: needs translated
                Hm_Msgs::add("ERRConnected, but failed to authenticate to the SMTP server");
            }
            else {
                Hm_Msgs::add("ERRFailed to authenticate to the SMTP server");
            }
        }
    }
}

/**
 * @subpackage smtp/handler
 */
class Hm_Handler_process_compose_form_submit extends Hm_Handler_Module {
    public function process() {
        if (array_key_exists('smtp_send', $this->request->post)) {
            list($success, $form) = $this->process_form(array('compose_to', 'compose_subject', 'smtp_server_id', 'draft_id'));
            if ($success) {
                $failed = true;
                $draft = array(
                    'draft_to' => $form['compose_to'],
                    'draft_body' => '',
                    'draft_subject' => $form['compose_subject'],
                    'draft_smtp' => $form['smtp_server_id']
                );
                $to = $form['compose_to'];
                $subject = $form['compose_subject'];
                $body = '';
                $from = '';
                $cc = '';
                $bcc = '';
                $from_name = '';
                $in_reply_to = '';
                $reply_to = '';
                if (array_key_exists('compose_body', $this->request->post)) {
                    $body = $this->request->post['compose_body'];
                    $draft['draft_body'] = $this->request->post['compose_body'];
                }
                if (array_key_exists('compose_cc', $this->request->post)) {
                    $cc = $this->request->post['compose_cc'];
                    $draft['draft_cc'] = $this->request->post['compose_cc'];
                }
                if (array_key_exists('compose_bcc', $this->request->post)) {
                    $bcc = $this->request->post['compose_bcc'];
                    $draft['draft_bcc'] = $this->request->post['compose_bcc'];
                }
                if (array_key_exists('compose_in_reply_to', $this->request->post)) {
                    $in_reply_to = $this->request->post['compose_in_reply_to'];
                    $draft['draft_in_reply_to'] = $this->request->post['compose_in_reply_to'];
                }
                $smtp_details = Hm_SMTP_List::dump($form['smtp_server_id'], true);
                $profiles = $this->get('compose_profiles', array());
                $imap_server = false;
                if ($smtp_details) {
                    $from = $smtp_details['user'];
                    if (array_key_exists($form['smtp_server_id'], $profiles)) {
                        if ($profiles[$form['smtp_server_id']]['name'][1] == 'imap') {
                            $imap_server = $profiles[$form['smtp_server_id']]['name'];
                        }
                        $from_name = $profiles[$form['smtp_server_id']]['profile_name'];
                        $reply_to = $profiles[$form['smtp_server_id']]['profile_replyto'];
                        if (array_key_exists('profile_address', $profiles[$form['smtp_server_id']]) &&
                            trim($profiles[$form['smtp_server_id']]['profile_address'])) {
                            $from = $profiles[$form['smtp_server_id']]['profile_address'];
                        }
                    }
                    if (array_key_exists('auth', $smtp_details) && $smtp_details['auth'] == 'xoauth2') {
                        $results = smtp_refresh_oauth2_token($smtp_details, $this->config);
                        if (!empty($results)) {
                            if (Hm_SMTP_List::update_oauth2_token($form['smtp_server_id'], $results[1], $results[0])) {
                                Hm_Debug::add(sprintf('Oauth2 token refreshed for SMTP server id %d', $form['smtp_server_id']));
                                $servers = Hm_SMTP_List::dump(false, true);
                                $this->user_config->set('smtp_servers', $servers);
                                $this->session->set('user_data', $this->user_config->dump());
                            }
                        }
                    }
                    $domain = $this->config->get('default_email_domain');
                    if ($domain) {
                        if (!is_email($from)) {
                            $from = $from.'@'.$domain;
                        }
                        if (!is_email($reply_to)) {
                            if (!trim($reply_to)) {
                                $reply_to = $smtp_details['user'];
                            }
                            $reply_to = $reply_to.'@'.$domain;
                        }
                    }
                    $smtp = Hm_SMTP_List::connect($form['smtp_server_id'], false);
                    if ($smtp && $smtp->state == 'authed') {
                        $mime = new Hm_MIME_Msg($to, $subject, $body, $from, $this->get('smtp_compose_type', 0),
                            $cc, $bcc, $in_reply_to, $from_name, $reply_to);
                        $mime->add_attachments(get_uploaded_files($form['draft_id'], $this->session));
                        $recipients = $mime->get_recipient_addresses();
                        if (empty($recipients)) {
                            Hm_Msgs::add("ERRNo valid receipts found");
                        }
                        else {
                            $err_msg = $smtp->send_message($from, $recipients, $mime->get_mime_msg());
                            if ($err_msg) {
                                Hm_Msgs::add(sprintf("ERR%s", $err_msg));
                            }
                            else {
                                $auto_bcc = $this->user_config->get('smtp_auto_bcc_setting', false);
                                if ($auto_bcc) {
                                    $mime->set_auto_bcc($from);
                                    $bcc_err_msg = $smtp->send_message($from, array($from), $mime->get_mime_msg());
                                }
                                if ($imap_server) {
                                    $this->out('save_sent_server', $imap_server);
                                    $this->out('save_sent_msg', $mime);
                                }
                                $this->out('msg_sent', true);
                                $failed = false;
                                Hm_Msgs::add("Message Sent");
                                delete_draft($form['draft_id'], $this->session);
                                delete_uploaded_files($this->session, $form['draft_id']);
                            }
                        }
                    }
                    else {
                        Hm_Msgs::add("ERRFailed to authenticate to the SMTP server");
                    }
                }
                if ($failed) {
                    /* HERE */
                }
            }
            else {
                Hm_Msgs::add('ERRRequired field missing');
            }
        }
    }
}

/**
 * Determine if auto-bcc is active
 * @subpackage smtp/handler
 */
class Hm_Handler_smtp_auto_bcc_check extends Hm_Handler_Module {
    /**
     * Set the auto bcc state for output modules to use
     */
    public function process() {
        $this->out('auto_bcc_enabled', $this->user_config->get('smtp_auto_bcc_setting', 0));
    }
}

/**
 * @subpackage smtp/output
 */
class Hm_Output_sent_folder_link extends Hm_Output_Module {
    protected function output() {
        $res = '<li class="menu_sent"><a class="unread_link" href="?page=message_list&amp;list_path=sent">';
        if (!$this->get('hide_folder_icons')) {
            $res .= '<img class="account_icon" src="'.$this->html_safe(Hm_Image_Sources::$sent).'" alt="" width="16" height="16" /> ';
        }
        $res .= $this->trans('Sent').'</a></li>';
        $this->concat('formatted_folder_list', $res);
    }
}

/**
 * @subpackage smtp/output
 */
class Hm_Output_compose_form_start extends Hm_Output_Module {
    protected function output() {
        return'<div class="compose_page"><div class="content_title">'.$this->trans('Compose').'</div>'.
            '<form class="compose_form" method="post" action="?page=compose">';
    }
}

/**
 * @subpackage smtp/output
 */
class Hm_Output_compose_form_end extends Hm_Output_Module {
    protected function output() {
        return '</form>';
    }
}

/**
 * @subpackage smtp/output
 */
class Hm_Output_compose_form_attach extends Hm_Output_Module {
    protected function output() {
        return '<form enctype="multipart/form-data" class="compose_attach_form">'.
            '<input class="compose_attach_file" type="file" name="compose_attach_file" />'.
            '<input type="hidden" name="compose_attach_page_id" value="ajax_smtp_attach_file" />'.
            '</form></div>';
    }
}

/**
 * @subpackage smtp/output
 */
class Hm_Output_compose_form_draft_list extends Hm_Output_Module {
    protected function output() {
        $drafts = $this->get('compose_drafts', array());
        if (!count($drafts)) {
            return;
        }
        $res = '<img class="draft_title refresh_list" width="24" height="24" src="'.
            Hm_Image_Sources::$doc.'" title="'.$this->trans('Drafts').'" alt="'.$this->trans('Drafts').'" />';
        $res .= '<div class="draft_list">';
        foreach ($drafts as $id => $draft) {
            if (trim($draft['draft_subject'])) {
                $res .= '<div class="draft_'.$this->html_safe($id).'"><a class="draft_link" href="?page=compose&draft_id='.
                    $this->html_safe($id).'">'.$this->html_safe($draft['draft_subject']).'</a> '.
                    '<img class="delete_draft" width="16" height="16" data-id="'.$this->html_safe($id).'" src="'.Hm_Image_Sources::$circle_x.'" /></div>';
            }
        }
        $res .= '</div>';
        return $res;
    }
}
/**
 * @subpackage smtp/output
 */
class Hm_Output_compose_form_content extends Hm_Output_Module {
    protected function output() {
        $to = '';
        $subject = '';
        $body = '';
        $files = $this->get('uploaded_files', array());
        $cc = '';
        $bcc = '';
        $in_reply_to = '';
        $recip = '';

        $draft = $this->get('compose_draft', array());
        $reply = $this->get('reply_details', array());
        $reply_type = $this->get('reply_type', '');
        $html = $this->get('smtp_compose_type', 0);
        $msg_path = $this->get('list_path', '');
        $msg_uid = $this->get('uid', '');
        $smtp_id = false;
        $draft_id = $this->get('compose_draft_id', 0);
        if (!empty($reply)) {
            list($to, $cc, $subject, $body, $in_reply_to) = format_reply_fields(
                $reply['msg_text'], $reply['msg_headers'], $reply['msg_struct'], $html, $this, $reply_type,
                Hm_SMTP_List::address_list());

            $recip = get_primary_recipients($reply['msg_headers'], $this->get('smtp_servers', array()));
        }
        elseif (!empty($draft)) {
            if (array_key_exists('draft_to', $draft)) {
                $to = $draft['draft_to'];
            }
            if (array_key_exists('draft_subject', $draft)) {
                $subject = $draft['draft_subject'];
            }
            if (array_key_exists('draft_body', $draft)) {
                $body= $draft['draft_body'];
            }
            if (array_key_exists('draft_smtp', $draft)) {
                $smtp_id = $draft['draft_smtp'];
            }
            if (array_key_exists('draft_in_reply_to', $draft)) {
                $in_reply_to = $draft['draft_in_reply_to'];
            }
            if (array_key_exists('draft_cc', $draft)) {
                $cc = $draft['draft_cc'];
            }
            if (array_key_exists('draft_bcc', $draft)) {
                $bcc = $draft['draft_bcc'];
            }
        }
        $res = '';
        if ($html == 1) {
            $res .= '<script type="text/javascript" src="modules/smtp/assets/kindeditor/kindeditor-all-min.js"></script>'.
                '<link href="modules/smtp/assets/kindeditor/themes/default/default.css" rel="stylesheet" />'.
                '<script type="text/javascript">KindEditor.ready(function(K) { K.create("#compose_body", {items:'.
                "['formatblock', 'fontname', 'fontsize', 'forecolor', 'hilitecolor', 'bold',".
                "'italic', 'underline', 'strikethrough', 'lineheight', 'table', 'hr', 'pagebreak', 'link', 'unlink',".
                "'justifyleft', 'justifycenter', 'justifyright',".
                "'justifyfull', 'insertorderedlist', 'insertunorderedlist', 'indent', 'outdent', '|',".
                "'undo', 'redo', 'preview', 'print', '|', 'selectall', 'cut', 'copy', 'paste',".
                "'plainpaste', 'wordpaste', '|', 'source', 'fullscreen']".
                ",basePath: 'modules/smtp/assets/kindeditor/'".
                '})});;</script>';
        }
        $res .= '<input type="hidden" name="hm_page_key" value="'.$this->html_safe(Hm_Request_Key::generate()).'" />'.
            '<input type="hidden" name="compose_msg_path" value="'.$this->html_safe($msg_path).'" />'.
            '<input type="hidden" name="compose_msg_uid" value="'.$this->html_safe($msg_uid).'" />'.
            '<input type="hidden" class="compose_draft_id" name="draft_id" value="'.$this->html_safe($draft_id).'" />'.
            '<input type="hidden" class="compose_in_reply_to" name="compose_in_reply_to" value="'.$this->html_safe($in_reply_to).'" />'.
            '<div class="to_outer"><input autocomplete="off" value="'.$this->html_safe($to).
            '" required name="compose_to" class="compose_to" type="text" placeholder="'.$this->trans('To').'" />'.
            '<a href="#" tabindex="-1" class="toggle_recipients">+</a></div><div id="to_contacts"></div>'.
            '<div class="recipient_fields"><input autocomplete="off" value="'.$this->html_safe($cc).
            '" name="compose_cc" class="compose_cc" type="text" placeholder="'.$this->trans('Cc').
            '" /><div id="cc_contacts"></div><input autocomplete="off" value="'.$this->html_safe($bcc).
            '" name="compose_bcc" class="compose_bcc" type="text" placeholder="'.$this->trans('Bcc').'" />'.
            '</div><div id="bcc_contacts"></div><input value="'.$this->html_safe($subject).
            '" required name="compose_subject" class="compose_subject" type="text" placeholder="'.
            $this->trans('Subject').'" /><textarea id="compose_body" name="compose_body" class="compose_body">'.
            $this->html_safe($body).'</textarea><table class="uploaded_files">';

        foreach ($files as $file) {
            $res .= format_attachment_row($file, $this);
        }
        $res .= '</table>'.
            smtp_server_dropdown($this->module_output(), $this, $recip, $smtp_id).
            '<input class="smtp_send" type="submit" value="'.$this->trans('Send').'" name="smtp_send" />'.
            '<input class="smtp_save" type="button" value="'.$this->trans('Save').'" />'.
            '<input class="smtp_reset" type="button" value="'.$this->trans('Reset').'" />'.
            '<input class="compose_attach_button" value="'.$this->trans('Attach').
            '" name="compose_attach_button" type="button" />';
        return $res;
    }
}

/**
 * @subpackage smtp/output
 */
class Hm_Output_add_smtp_server_dialog extends Hm_Output_Module {
    protected function output() {
        $count = count($this->get('smtp_servers', array()));
        $count = sprintf($this->trans('%d configured'), $count);
        $name = '';
        $address = '';
        $port = 465;
        $add_form_vals = $this->get('add_form_vals', array());
        if (array_key_exists('new_smtp_name', $add_form_vals)) {
            $name = $this->html_safe($add_form_vals['new_smtp_name']);
        }
        if (array_key_exists('new_smtp_address', $add_form_vals)) {
            $address = $this->html_safe($add_form_vals['new_smtp_address']);
        }
        if (array_key_exists('new_smtp_port', $add_form_vals)) {
            $port = $this->html_safe($add_form_vals['new_smtp_port']);
        }
        return '<div class="smtp_server_setup"><div data-target=".smtp_section" class="server_section">'.
            '<img alt="" src="'.Hm_Image_Sources::$doc.'" width="16" height="16" />'.
            ' '.$this->trans('SMTP Servers').' <div class="server_count">'.$count.'</div></div><div class="smtp_section"><form class="add_server" method="POST">'.
            '<input type="hidden" name="hm_page_key" value="'.$this->html_safe(Hm_Request_Key::generate()).'" />'.
            '<div class="subtitle">'.$this->trans('Add an SMTP Server').'</div>'.
            '<table><tr><td colspan="2"><label for="new_smtp_name" class="screen_reader">'.$this->trans('SMTP account name').'</label>'.
            '<input required type="text" id="new_smtp_name" name="new_smtp_name" class="txt_fld" value="'.$name.'" placeholder="'.$this->trans('Account name').'" /></td></tr>'.
            '<tr><td colspan="2"><label for="new_smtp_address" class="screen_reader">'.$this->trans('SMTP server address').'</label>'.
            '<input required type="text" id="new_smtp_address" name="new_smtp_address" value="'.$address.'" class="txt_fld" placeholder="'.$this->trans('SMTP server address').'" /></td></tr>'.
            '<tr><td colspan="2"><label for="new_smtp_port" class="screen_reader">'.$this->trans('SMTP port').'</label>'.
            '<input required type="number" id="new_smtp_port" name="new_smtp_port" class="port_fld" value="'.$port.'" placeholder="'.$this->trans('Port').'"></td></tr>'.
            '<tr><td><input type="checkbox" name="tls" value="1" id="smtp_tls" checked="checked" /> <label for="smtp_tls">'.$this->trans('Use TLS').'</label></td>'.
            '<td><input type="submit" value="'.$this->trans('Add').'" name="submit_smtp_server" /></td></tr>'.
            '</table></form>';
    }
}

/**
 * @subpackage smtp/output
 */
class Hm_Output_compose_type_setting extends Hm_Output_Module {
    protected function output() {
        $selected = 2;
        $settings = $this->get('user_settings', array());
        if (array_key_exists('smtp_compose_type', $settings)) {
            $selected = $settings['smtp_compose_type'];
        }
        $res = '<tr class="general_setting"><td>'.$this->trans('Outbound mail format').'</td><td><select name="smtp_compose_type">';
        $res .= '<option ';
        if ($selected == 0) {
            $res .= 'selected="selected" ';
        }
        $res .= 'value="0">'.$this->trans('Plain text').'</option><option ';
        if ($selected == 1) {
            $res .= 'selected="selected" ';
        }
        $res .= 'value="1">'.$this->trans('HTML').'</option></select></td></tr>';
        return $res;
    }
}

/**
 * @subpackage smtp/output
 */
class Hm_Output_auto_bcc_setting extends Hm_Output_Module {
    protected function output() {
        $auto = false;
        $settings = $this->get('user_settings', array());
        if (array_key_exists('smtp_auto_bcc', $settings)) {
            $auto = $settings['smtp_auto_bcc'];
        }
        $res = '<tr class="general_setting"><td>'.$this->trans('Always BCC sending address').'</td><td><input value="1" type="checkbox" name="smtp_auto_bcc"';
        if ($auto) {
            $res .= ' checked="checked"';
        }
        $res .= '></td></tr>';
        return $res;
    }
}

/**
 * @subpackage smtp/output
 */
class Hm_Output_filter_upload_file_details extends Hm_Output_Module {
    protected function output() {
        $file = $this->get('upload_file_details', array());
        if (!empty($file)) {
            $this->out('file_details', format_attachment_row($file, $this));
        }
    }
}

/**
 * @subpackage smtp/output
 */
class Hm_Output_display_configured_smtp_servers extends Hm_Output_Module {
    protected function output() {
        $res = '';
        foreach ($this->get('smtp_servers', array()) as $index => $vals) {

            $no_edit = false;

            if (array_key_exists('user', $vals) && !array_key_exists('nopass', $vals)) {
                $disabled = 'disabled="disabled"';
                $user_pc = $vals['user'];
                $pass_pc = $this->trans('[saved]');
            }
            elseif (array_key_exists('nopass', $vals)) {
                $user_pc = $vals['user'];
                $pass_pc = $this->trans('Password');
                $disabled = '';
            }
            else {
                $user_pc = '';
                $pass_pc = $this->trans('Password');
                $disabled = '';
            }
            $res .= '<div class="configured_server">';
            $res .= sprintf('<div class="server_title">%s</div><div class="server_subtitle">%s/%d %s</div>',
                $this->html_safe($vals['name']), $this->html_safe($vals['server']), $this->html_safe($vals['port']), $vals['tls'] ? 'TLS' : '' );
            $res .= 
                '<form class="smtp_connect" method="POST">'.
                '<input type="hidden" name="hm_page_key" value="'.$this->html_safe(Hm_Request_Key::generate()).'" />'.
                '<input type="hidden" name="smtp_server_id" value="'.$this->html_safe($index).'" /><span> '.
                '<label class="screen_reader" for="smtp_user_'.$index.'">'.$this->trans('SMTP username').'</label>'.
                '<input '.$disabled.' class="credentials" id="smtp_user_'.$index.'" placeholder="'.$this->trans('Username').
                '" type="text" name="smtp_user" value="'.$this->html_safe($user_pc).'"></span><span> <label class="screen_reader" for="smtp_pass_'.
                $index.'">'.$this->trans('SMTP password').'</label><input '.$disabled.' class="credentials smtp_password" placeholder="'.
                $pass_pc.'" type="password" id="smtp_pass_'.$index.'" name="smtp_pass"></span>';

            if (!$no_edit) {
                $res .= '<input type="submit" value="'.$this->trans('Test').'" class="test_smtp_connect" />';
                if (!isset($vals['user']) || !$vals['user']) {
                    $res .= '<input type="submit" value="'.$this->trans('Delete').'" class="delete_smtp_connection" />';
                    $res .= '<input type="submit" value="'.$this->trans('Save').'" class="save_smtp_connection" />';
                }
                else {
                    $res .= '<input type="submit" value="'.$this->trans('Delete').'" class="delete_smtp_connection" />';
                    $res .= '<input type="submit" value="'.$this->trans('Forget').'" class="forget_smtp_connection" />';
                }
                $res .= '<input type="hidden" value="ajax_smtp_debug" name="hm_ajax_hook" />';
            }
            $res .= '</form></div>';
        }
        $res .= '<br class="clear_float" /></div></div>';
        return $res;
    }
}

/**
 * @subpackage smtp/output
 */
class Hm_Output_compose_page_link extends Hm_Output_Module {
    protected function output() {
        $res = '<li class="menu_compose"><a class="unread_link" href="?page=compose">';
        if (!$this->get('hide_folder_icons')) {
            $res .= '<img class="account_icon" src="'.$this->html_safe(Hm_Image_Sources::$doc).'" alt="" width="16" height="16" /> ';
        }
        $res .= $this->trans('Compose').'</a></li>';

        if ($this->format == 'HTML5') {
            return $res;
        }
        $this->concat('formatted_folder_list', $res);
    }
}

/**
 * @subpackage smtp/functions
 */
function smtp_server_dropdown($data, $output_mod, $recip, $selected_id=false) {
    $res = '<select name="smtp_server_id" class="compose_server">';
    $profiles = array();
    if (array_key_exists('compose_profiles', $data)) {
        $profiles = $data['compose_profiles'];
    }
    if (array_key_exists('smtp_servers', $data)) {
        $selected = false;
        $default = false;
        foreach ($data['smtp_servers'] as $id => $vals) {
            if ($selected_id !== false && $id == $selected_id) {
                $selected = $id;
            }
            elseif ($recip && trim($recip) == trim($vals['user'])) {
                $selected = $id;
            }
            elseif (array_key_exists($id, $profiles) && $profiles[$id]['profile_default']) {
                $default = $id;
            }
        }
        if ($selected === false && $default !== false) {
            $selected = $default;
        }
        foreach ($data['smtp_servers'] as $id => $vals) {
            $res .= '<option ';
            if ($selected === $id) {
                $res .= 'selected="selected" ';
            }
            $res .= 'value="'.$output_mod->html_safe($id).'">';
            if (array_key_exists($id, $profiles)) {
                $res .= $output_mod->html_safe(sprintf('"%s" %s %s', $profiles[$id]['profile_name'], $vals['user'], $vals['name']));
            }
            else {
                $res .= $output_mod->html_safe(sprintf("%s - %s", $vals['user'], $vals['name']));
            }
            $res .= '</option>';
        }
    }
    $res .= '</select>';
    return $res;
}

/**
 * Check for and do an Oauth2 token reset if needed
 * @param array $server SMTP server data
 * @param object $config site config object
 * @return mixed
 */
function smtp_refresh_oauth2_token($server, $config) {

    if (array_key_exists('expiration', $server) && (int) $server['expiration'] <= time()) {
        $oauth2_data = get_oauth2_data($config);
        $details = array();
        if ($server['server'] == 'smtp.gmail.com') {
            $details = $oauth2_data['gmail'];
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

/**
 * @subpackage smtp/functions
 */
function delete_uploaded_files($session, $draft_id=false, $filename=false) {

    $files = $session->get('uploaded_files', array());
    $deleted = 0;
    foreach ($files as $id => $file_list) {
        foreach ($file_list as $file_id => $file) {
            if (($draft_id === false && !$filename) || $draft_id === $id || $filename === $file['basename']) {
                @unlink($file['filename']);
                $deleted++;
                if ($filename) {
                    unset($files[$id][$file_id]);
                }
            }
        }
    }
    if ($draft_id !== false) {
        if (array_key_exists($draft_id, $files)) {
            unset($files[$draft_id]);
        }
    }
    elseif ($draft_id === false && !$filename) {
        $files = array();
    }
    $session->set('uploaded_files', $files);
    return $deleted;
}

/**
 * @subpackage/functions
 */
function get_uploaded_files($id, $session) {
    $files = $session->get('uploaded_files', array());
    if (array_key_exists($id, $files)) {
        return $files[$id];
    }
    return array();
}

/**
 * @subpackage/functions
 */
function save_uploaded_file($id, $atts, $session) {
    $files = $session->get('uploaded_files', array());
    if (array_key_exists($id, $files)) {
        $files[$id][] = $atts;
    }
    else {
        $files[$id] = array($atts);
    }
    $session->set('uploaded_files', $files);
}

/**
 * @subpackage smtp/functions
 */
function format_attachment_row($file, $output_mod) {
    return '<tr><td>'.
        '<img src="'.Hm_Image_Sources::$paperclip.'" alt="" width="16" height="16" />'.
        '</td><td>'.$output_mod->html_safe($file['name']).
        '</td><td>'.$output_mod->html_safe($file['type']).'</td><td>'.
        $output_mod->html_safe(round($file['size']/1024, 2)).'KB</td>'.
        '<td>'.
        '<a title="'.$output_mod->trans('Delete').'" href="#" data-id="'.$output_mod->html_safe($file['basename']).
        '" class="delete_attachment"><img src="'.Hm_Image_Sources::$circle_x.'" alt="X" width="16" height="16" />'.
        '</a>'.
        '</td>'.
        '</tr>';
}

/**
 * @subpackage smtp/functions
 */
function get_primary_recipients($headers, $smtp_servers) {
    $recip_headers = array('to', 'cc', 'envelope-to');
    $lc_headers = array();
    foreach ($headers as $name => $value) {
        if (in_array(strtolower($name), $recip_headers, true)) {
            $lc_headers[strtolower($name)] = $value;
        }
    }
    if (array_key_exists('envelope-to', $lc_headers)) {
        return $lc_headers['envelope-to'];
    }
    $users = array_map(function($a) { return $a['user']; }, $smtp_servers);
    foreach ($users as $user) {
        if (strpos($user, '@') !== false) {
            $testuser = explode('@', $user)[1];
        }
        else {
            $testuser = $user;
        }
        foreach ($lc_headers as $header) {
            if (stristr($header, $testuser) !== false) {
                return $user;
            }
        }
    }
    return false;
}

/**
 * @subpackage/functions
 */
function delete_draft($id, $session) {
    $drafts = $session->get('compose_drafts', array());
    if (array_key_exists($id, $drafts)) {
        unset($drafts[$id]);
        $session->set('compose_drafts', $drafts);
        return true;
    }
    return false;
}

/**
 * @subpackage smtp/functions
 */
function save_draft($atts, $id, $session) {
    if (!trim($atts['draft_subject'])) {
        return false;
    }
    $drafts = $session->get('compose_drafts', array());
    if ($id !== false) {
        $drafts[$id] = $atts;
    }
    else {
        $drafts[] = $atts;
        $id = count($drafts) - 1;
    }
    $session->set('compose_drafts', $drafts);
    return $id;
}

/**
 * @subpackage smtp/functions
 */
function get_draft($id, $session) {
    $drafts = $session->get('compose_drafts', array());
    if (array_key_exists($id, $drafts)) {
        return $drafts[$id];
    }
    return false;
}

/**
 * @subpackage smtp/functions
 */
function attach_file($content, $file, $filepath, $draft_id, $mod) {
    $content = Hm_Crypt::ciphertext($content, Hm_Request_Key::generate());
    $filename = hash('sha512', $content);
    $filepath = rtrim($filepath, '/');
    if (@file_put_contents($filepath.'/'.$filename, $content)) {
        $file['filename'] = $filepath.'/'.$filename;
        $file['basename'] = $filename;
        save_uploaded_file($draft_id, $file, $mod->session);
        $mod->out('upload_file_details', $file);
        return true;
    }
    return false;
}

