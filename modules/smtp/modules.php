<?php

/**
 * SMTP modules
 * @package modules
 * @subpackage smtp
 */

if (!defined('DEBUG_MODE')) { die(); }

use League\CommonMark\GithubFlavoredMarkdownConverter;

define('MAX_RECIPIENT_WARNING', 20);
require_once APP_PATH.'modules/smtp/functions.php';
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
                $reply_type = get_reply_type($this->request->get);
                if ($reply_type == 'reply_all') {
                    recip_count_check($reply_details['msg_headers'], $this);
                }
                $this->out('reply_details', $reply_details);
            }
        }
    }
}

/**
 * @subpackage smtp/handler
 */
class Hm_Handler_load_smtp_is_imap_draft extends Hm_Handler_Module {
    public function unangle($str) {
        return trim(preg_replace("/(^| )</", ' ', preg_replace("/>($| )/", ' ', $str)));
    }
    public function process() {
        if (!$this->module_is_supported('imap')) {
            Hm_Msgs::add('IMAP module unavailable.', 'danger');
            return;
        }
        if (array_key_exists('imap_draft', $this->request->get)
            && array_key_exists('list_path', $this->request->get)
            && array_key_exists('uid', $this->request->get)) {
            $path = explode('_', $this->request->get['list_path']);
            $mailbox = Hm_IMAP_List::get_connected_mailbox($path[1]);
            if ($mailbox && $mailbox->authed()) {
                list ($msg_struct, $msg_struct_first, $msg_text, $part) = $mailbox->get_structured_message(hex2bin($path[2]), $this->request->get['uid'], false, true);
                $msg_header = $mailbox->get_message_headers(hex2bin($path[2]), $this->request->get['uid']);
                if (!array_key_exists('From', $msg_header) || count($msg_header) == 0) {
                    return;
                }

                # Attachment Download
                # Draft attachments must be redownloaded and added to the file cache to prevent
                # attachments from being deleted when editing a previously saved draft.
                $attached_files = [];
                $this->session->set('uploaded_files', array());
                if (array_key_exists(0, $msg_struct) && array_key_exists('subs', $msg_struct[0])) {
                    foreach ($msg_struct[0]['subs'] as $ind => $sub) {
                        if ($ind != '0.1') {
                            $new_attachment['basename'] = $sub['description'];
                            $new_attachment['name'] = $sub['description'];
                            $new_attachment['size'] = $sub['size'];
                            $new_attachment['type'] = $sub['type'];
                            $file_path = $this->config->get('attachment_dir').DIRECTORY_SEPARATOR.$new_attachment['name'];
                            $content = Hm_Crypt::ciphertext($mailbox->get_message_content(hex2bin($path[2]), $this->request->get['uid'], $ind), Hm_Request_Key::generate());
                            file_put_contents($file_path, $content);
                            $new_attachment['tmp_name'] = $file_path;
                            $new_attachment['filename'] = $file_path;
                            $attached_files[$this->request->get['uid']][] = $new_attachment;
                        }
                    }
                }
                $this->session->set('uploaded_files', $attached_files);

                $imap_draft = array(
                    'From' => $msg_header['From'],
                    'To' => $this->unangle($msg_header['To']),
                    'Subject' => $msg_header['Subject'],
                    'Message-Id' => $msg_header['Message-Id'],
                    'Content-Type' => $msg_header['Content-Type'],
                    'Body' => $msg_text,
                    'Reply-To' => $msg_header['Reply-To']
                );

                if (array_key_exists('Cc', $msg_header)) {
                    $imap_draft['Cc'] = $this->unangle($msg_header['Cc']);
                }

                if ($imap_draft) {
                    recip_count_check($imap_draft, $this);
                    $this->out('draft_id', $this->request->get['uid']);
                    $this->out('imap_draft', $imap_draft);
                }
                return;
            }
            Hm_Msgs::add('Could not load the IMAP mailbox.', 'danger');
        }
    }
}

/**
 * @subpackage smtp/handler
 */
class Hm_Handler_load_smtp_is_imap_forward_as_attachment extends Hm_Handler_Module
{
    public function process() {
        if (!$this->module_is_supported('imap')) {
            return;
        }

        if (array_key_exists('forward_as_attachment', $this->request->get)) {
            $path = explode('_', $this->request->get['list_path']);
            $mailbox = Hm_IMAP_List::get_connected_mailbox($path[1]);
            if ($mailbox && $mailbox->authed()) {
                $msg_header = $mailbox->get_message_headers(hex2bin($path[2]), $this->request->get['uid']);
                if (!array_key_exists('From', $msg_header) || count($msg_header) == 0) {
                    return;
                }
                $content = $mailbox->get_message_content(hex2bin($path[2]), $this->request->get['uid']);

                # Attachment Download
                $attached_files = [];
                $this->session->set('uploaded_files', array());

                $file_dir = $this->config->get('attachment_dir') . DIRECTORY_SEPARATOR . md5($this->session->get('username', false)) . DIRECTORY_SEPARATOR;
                if (!is_dir($file_dir)) {
                    mkdir($file_dir);
                }
                $basename = str_replace(',', '', $msg_header['Subject']);
                $name = $basename. '.eml';
                $file_path = $file_dir . $name;
                $attached_files[$this->request->get['uid']][] = array(
                    'name' => $name,
                    'type' => 'message/rfc822',
                    'size' => strlen($content),
                    'tmp_name' => $file_path,
                    'filename' => $file_path,
                    'basename' => $basename
                );
                $content = Hm_Crypt::ciphertext($content, Hm_Request_Key::generate());
                file_put_contents($file_path, $content);
                $this->session->set('uploaded_files', $attached_files);
                $this->out('as_attr', true);
            }
        }
    }
}

/**
 * @subpackage smtp/handler
 */
class Hm_Handler_load_smtp_is_imap_forward extends Hm_Handler_Module
{
    public function process() {
        if (!$this->module_is_supported('imap')) {
            return;
        }
        if (array_key_exists('forward', $this->request->get)) {
            $path = explode('_', $this->request->get['list_path']);
            $mailbox = Hm_IMAP_List::get_connected_mailbox($path[1]);
            if ($mailbox && $mailbox->authed()) {
                list ($msg_struct, $msg_struct_first, $msg_text, $part) = $mailbox->get_structured_message(hex2bin($path[2]), $this->request->get['uid'], false, true);
                $msg_header = $mailbox->get_message_headers(hex2bin($path[2]), $this->request->get['uid']);
                if (!array_key_exists('From', $msg_header) || count($msg_header) == 0) {
                    return;
                }

                # Attachment Download
                $attached_files = [];
                $this->session->set('uploaded_files', array());
                if (array_key_exists(0, $msg_struct) && array_key_exists('subs', $msg_struct[0])) {
                    $file_dir = $this->config->get('attachment_dir') . DIRECTORY_SEPARATOR . md5($this->session->get('username', false)) . DIRECTORY_SEPARATOR;
                    if (!is_dir($file_dir)) {
                        mkdir($file_dir);
                    }
                    foreach ($msg_struct[0]['subs'] as $ind => $sub) {
                        if ($ind != '0.1') {
                            $new_attachment['basename'] = $sub['description'];
                            $new_attachment['name'] = $sub['attributes']['name'] ?? $sub['attributes']['filename']; // some file types (i.e. pdf) use the filename attribute instead of name.
                            $new_attachment['size'] = $sub['size'];
                            $new_attachment['type'] = $sub['type'] . "/" . $sub['subtype'];
                            $file_path = $file_dir . $new_attachment['name'];
                            $content = Hm_Crypt::ciphertext($mailbox->get_message_content(hex2bin($path[2]), $this->request->get['uid'], $ind), Hm_Request_Key::generate());
                            file_put_contents($file_path, $content);
                            $new_attachment['tmp_name'] = $file_path;
                            $new_attachment['filename'] = $file_path;
                            $attached_files[$this->request->get['uid']][] = $new_attachment;
                        }
                    }
                }
                $this->session->set('uploaded_files', $attached_files);
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
            default_smtp_server($this->user_config, $this->session, $this->request,
                $this->config, $form['username'], $form['password']);
        }
    }
}

/**
 * @subpackage smtp/handler
 */
class Hm_Handler_process_compose_type extends Hm_Handler_Module {
    public function process() {
        function smtp_compose_type_callback($val) { return $val; }
        process_site_setting('smtp_compose_type', $this, 'smtp_compose_type_callback', DEFAULT_SMTP_COMPOSE_TYPE);
    }
}

/**
 * @subpackage smtp/handler
 */
class Hm_Handler_process_auto_bcc extends Hm_Handler_Module {
    public function process() {
        function smtp_auto_bcc_callback($val) { return $val; }
        process_site_setting('smtp_auto_bcc', $this, 'smtp_auto_bcc_callback', DEFAULT_SMTP_AUTO_BCC, true);
    }
}

/**
 * @subpackage smtp/handler
 */
class Hm_Handler_get_test_chunk extends Hm_Handler_Module {
    public function process() {
        $filepath = $this->config->get('attachment_dir');
        $filepath = $filepath.'/chunks-'.$this->request->get['resumableIdentifier'];
        $chunk_file = $filepath.'/'.$this->request->get['resumableFilename'].'.part'.$this->request->get['resumableChunkNumber'];
        if (file_exists($chunk_file)) {
            header("HTTP/1.0 200 Ok");
        } else {
            header("HTTP/1.0 404 Not Found");
        }
    }
}

/**
 * @subpackage smtp/handler
 */
class Hm_Handler_upload_chunk extends Hm_Handler_Module {
    public function process() {
        $from = $this->request->get['draft_smtp'];
        $filepath = $this->config->get('attachment_dir');

        $userpath = md5($this->session->get('username', false));

        // create the attachment folder for the profile to avoid
        if (!is_dir($filepath.'/'.$userpath)) {
            mkdir($filepath.'/'.$userpath, 0777, true);
        }

        if (!empty($this->request->files)) foreach ($this->request->files as $file) {
            if ($file['error'] != 0) {
                Hm_Msgs::add('Error '.$file['error'].' in file '.$this->request->get['resumableFilename'], 'danger');
                continue;
            }

            if(isset($this->request->get['resumableIdentifier']) && trim($this->request->get['resumableIdentifier'])!=''){
                $temp_dir = $filepath.'/'.$userpath.'/chunks-'.$this->request->get['resumableIdentifier'];
            }
            $dest_file = $temp_dir.'/'.$this->request->get['resumableFilename'].'.part'.$this->request->get['resumableChunkNumber'];

            // create the temporary directory
            if (!is_dir($temp_dir)) {
                mkdir($temp_dir, 0777, true);
            }

            // move the temporary file
            if (!move_uploaded_file($file['tmp_name'], $dest_file)) {
                Hm_Msgs::add('Error saving (move_uploaded_file) chunk '.$this->request->get['resumableChunkNumber'].' for file '.$this->request->get['resumableFilename'], 'danger');
            } else {
                // check if all the parts present, and create the final destination file
                $result = createFileFromChunks($temp_dir, $this->request->get['resumableFilename'],
                                $this->request->get['resumableChunkSize'],
                                $this->request->get['resumableTotalSize'],
                                $this->request->get['resumableTotalChunks']);
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
        $uploaded_files = array_key_exists('uploaded_files', $this->request->post) ? $this->request->post['uploaded_files'] : false;
        $delivery_receipt = array_key_exists('compose_delivery_receipt', $this->request->post) ? $this->request->post['compose_delivery_receipt'] : false;
        $schedule = array_key_exists('schedule', $this->request->post) ? $this->request->post['schedule'] : '';

        if (array_key_exists('delete_uploaded_files', $this->request->post) && $this->request->post['delete_uploaded_files']) {
            delete_uploaded_files($this->session, $draft_id);
            return;
        }

        $msg_attrs = array('draft_smtp' => $smtp, 'draft_to' => $to, 'draft_body' => $body,
        'draft_subject' => $subject, 'draft_cc' => $cc, 'draft_bcc' => $bcc,
        'draft_in_reply_to' => $inreplyto);
        $uploaded_files = !$uploaded_files ? []: explode(',', $uploaded_files);
        $profiles = $this->get('compose_profiles', array());
        $profile = profile_from_compose_smtp_id($profiles, $smtp);

        if ($this->get('save_draft_to_imap') === false) {
            $from = isset($profile) ? $profile['replyto'] : '';
            $name = isset($profile) ? $profile['name'] : '';
            $mime = prepare_draft_mime($msg_attrs, $uploaded_files, $from, $name, $profile['id']);
            $this->out('draft_mime', $mime);
            return;
        }

        if ($this->module_is_supported('imap')) {
            $userpath = md5($this->session->get('username', false));
            foreach($uploaded_files as $key => $file) {
                $uploaded_files[$key] = $this->config->get('attachment_dir').DIRECTORY_SEPARATOR.$userpath.DIRECTORY_SEPARATOR.$file;
            }
            $new_draft_id = save_imap_draft(array('draft_smtp' => $smtp, 'draft_to' => $to, 'draft_body' => $body,
                    'draft_subject' => $subject, 'draft_cc' => $cc, 'draft_bcc' => $bcc,
                    'draft_in_reply_to' => $inreplyto, 'delivery_receipt' => $delivery_receipt, 'schedule' => $schedule), $draft_id, $this->session,
                    $this, $this->cache, $uploaded_files, $profile);
            if ($new_draft_id >= 0) {
                if ($draft_notice) {
                    $msg = $schedule ? 'Message scheduled to be sent later' : 'Draft saved';
                    Hm_Msgs::add($msg);
                }
                $this->out('draft_id', $new_draft_id);
            }
            elseif ($draft_notice) {
                $msg = $schedule ? 'Something went wrong when scheduling draft' : 'Unable to save draft';
                Hm_Msgs::add($msg, 'danger');
            }
            return;
        }
    }
}

/**
 * @subpackage smtp/handler
 */
class Hm_Handler_load_smtp_servers_from_config extends Hm_Handler_Module {
    public function process() {
        Hm_SMTP_List::init($this->user_config, $this->session);
        if (Hm_SMTP_List::count() == 0 && $this->page == 'compose') {
            Hm_Msgs::add('You need at least one configured SMTP server to send outbound messages', 'warning');
        }
        $draft = array();
        $draft_id = next_draft_key($this->session);
        $reply_type = get_reply_type($this->request->get);
        if ($reply_type == 'forward') {
            $draft_id = $this->get('compose_draft_id', -1);
            if ($draft_id >= 0) {
                $draft = get_draft($draft_id, $this->session);
            }
        } elseif (array_key_exists('draft_id', $this->request->get)) {
            $draft = get_draft($this->request->get['draft_id'], $this->session);
            $draft_id = $this->request->get['draft_id'];
        }
        elseif (array_key_exists('draft_id', $this->request->post)) {
            $draft = get_draft($this->request->post['draft_id'], $this->session);
            $draft_id = $this->request->post['draft_id'];
        }
        if ($reply_type) {
            $this->out('reply_type', $reply_type);
        }
        if (is_dir($this->config->get('attachment_dir'))) {
            $this->out('attachment_dir_access', true);
        } else {
            $this->out('attachment_dir_access', false);
            Hm_Msgs::add('Attachment storage unavailable, please contact your site administrator', 'warning');
        }

        $this->out('compose_draft', $draft, false);
        $this->out('compose_draft_id', $draft_id);

        if ($draft_id <= 0 && array_key_exists('uid', $this->request->get)) {
            $draft_id = $this->request->get['uid'];
        }

        $this->out('uploaded_files', get_uploaded_files($draft_id, $this->session));
        $settings = $this->user_config;
        $compose_type = $settings->get('smtp_compose_type_setting', DEFAULT_SMTP_COMPOSE_TYPE);
        if ($this->get('is_mobile', false)) {
            $compose_type = 0;
        }
        if (is_array($this->get('compose_draft')) && mb_strlen(trim(join('', $this->get('compose_draft')))) == 0 && array_key_exists('compose_to', $this->request->get)) {
            $draft = array();
            foreach (parse_mailto($this->request->get['compose_to']) as $name => $val) {
                if (!$val) {
                    continue;
                }
                $draft[$name] = $val;
            }
            $this->out('compose_draft', $draft);
        }
        $this->out('smtp_compose_type', $compose_type);
        $this->out('enable_compose_delivery_receipt_setting', $settings->get('enable_compose_delivery_receipt_setting'));
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
                Hm_Msgs::add('You must supply a name, a server and a port', 'warning');
            }
            else {
                $tls = false;
                if (array_key_exists('tls', $this->request->post) && $this->request->post['tls']) {
                    $tls = true;
                }
                if ($con = @fsockopen($form['new_smtp_address'], $form['new_smtp_port'], $errno, $errstr, 2)) {
                    Hm_SMTP_List::add(array(
                        'name' => $form['new_smtp_name'],
                        'server' => $form['new_smtp_address'],
                        'port' => $form['new_smtp_port'],
                        'tls' => $tls));

                    Hm_Msgs::add('Added SMTP server!');
                    $this->session->record_unsaved('SMTP server added');
                }
                else {
                    $this->session->set('add_form_vals', $form);
                    Hm_Msgs::add(sprintf('Could not add server: %s', $errstr), 'danger');
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
        Hm_SMTP_List::save();
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
            list($success, $form) = $this->process_form(array('smtp_server_id'));
            $smtp_details = Hm_SMTP_List::dump($form['smtp_server_id'], true);
            if ($success && $smtp_details) {
                if (array_key_exists('auth', $smtp_details) && $smtp_details['auth'] == 'xoauth2') {
                    $results = smtp_refresh_oauth2_token($smtp_details, $this->config);
                    if (!empty($results)) {
                        if (Hm_SMTP_List::update_oauth2_token($form['smtp_server_id'], $results[1], $results[0])) {
                            Hm_Debug::add(sprintf('Oauth2 token refreshed for SMTP server id %s', $form['smtp_server_id']), 'info');
                            Hm_SMTP_List::save();
                        }
                    }
                }
                $mailbox = Hm_SMTP_List::connect($form['smtp_server_id'], false, $smtp_details['user'], $smtp_details['pass']);
                if ($mailbox && $mailbox->authed()) {
                    Hm_Msgs::add(sprintf("Successfully authenticated to the %s server : %s", $smtp_details['type'], $smtp_details['user']));
                }
                elseif ($mailbox && $mailbox->state() == 'connected') {
                    Hm_Msgs::add("Connected, but failed to authenticate to the SMTP server", "warning");
                }
                else {
                    Hm_Msgs::add("Failed to authenticate to the SMTP server", "danger");
                }
            }
        }
    }
}

/**
 * @subpackage smtp/handler
 */
class Hm_Handler_profile_status extends Hm_Handler_Module {
    public function process() {
        $profiles = $this->user_config->get('profiles');
        $profile_value = $this->request->post['profile_value'];

        if (!mb_strstr($profile_value, '.')) {
            Hm_Msgs::add('Please create a profile for saving sent messages', 'warning');
            return;
        }
        $profile = profile_from_compose_smtp_id($profiles, $profile_value);
        if (!$profile) {
            Hm_Msgs::add('Please create a profile for saving sent messages', 'warning');
            return;
        }
        $imap_profile = Hm_IMAP_List::fetch($profile['user'], $profile['server']);
        $specials = get_special_folders($this, $imap_profile['id']);
        if ($imap_profile && (!array_key_exists('sent', $specials) || !$specials['sent'])) {
            Hm_Msgs::add('Please configure a sent folder for account ' . $imap_profile['name'], 'warning');
        }
    }
}

if (!hm_exists('get_mime_type')) {
    function get_mime_type($filename)
    {
        $idx = explode('.', $filename);
        $count_explode = count($idx);
        $idx = mb_strtolower($idx[$count_explode - 1]);

        $mimet = array(
            'txt' => 'text/plain',
            'htm' => 'text/html',
            'html' => 'text/html',
            'php' => 'text/html',
            'css' => 'text/css',
            'js' => 'application/javascript',
            'json' => 'application/json',
            'xml' => 'application/xml',
            'swf' => 'application/x-shockwave-flash',
            'flv' => 'video/x-flv',

            // images
            'png' => 'image/png',
            'jpe' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'jpg' => 'image/jpeg',
            'gif' => 'image/gif',
            'bmp' => 'image/bmp',
            'ico' => 'image/vnd.microsoft.icon',
            'tiff' => 'image/tiff',
            'tif' => 'image/tiff',
            'svg' => 'image/svg+xml',
            'svgz' => 'image/svg+xml',

            // archives
            'zip' => 'application/zip',
            'rar' => 'application/x-rar-compressed',
            'exe' => 'application/x-msdownload',
            'msi' => 'application/x-msdownload',
            'cab' => 'application/vnd.ms-cab-compressed',

            // audio/video
            'mp3' => 'audio/mpeg',
            'qt' => 'video/quicktime',
            'mov' => 'video/quicktime',

            // adobe
            'pdf' => 'application/pdf',
            'psd' => 'image/vnd.adobe.photoshop',
            'ai' => 'application/postscript',
            'eps' => 'application/postscript',
            'ps' => 'application/postscript',

            // ms office
            'doc' => 'application/msword',
            'rtf' => 'application/rtf',
            'xls' => 'application/vnd.ms-excel',
            'ppt' => 'application/vnd.ms-powerpoint',
            'docx' => 'application/msword',
            'xlsx' => 'application/vnd.ms-excel',
            'pptx' => 'application/vnd.ms-powerpoint',


            // open office
            'odt' => 'application/vnd.oasis.opendocument.text',
            'ods' => 'application/vnd.oasis.opendocument.spreadsheet',
        );

        if (isset($mimet[$idx])) {
            return $mimet[$idx];
        } else {
            return 'application/octet-stream';
        }
    }
}

/**
 * @subpackage smtp/handler
 */
class Hm_Handler_process_compose_form_submit extends Hm_Handler_Module {
    public function process() {
        /* not sending */
        if (!array_key_exists('smtp_send', $this->request->post)) {
            $this->out('enable_attachment_reminder', $this->user_config->get('enable_attachment_reminder_setting', false));
            return;
        }
        if (!array_key_exists('compose_smtp_id', $this->request->post)) {
            return;
        }

        /* missing field */
        list($success, $form) = $this->process_form(array('compose_to', 'compose_subject', 'compose_body', 'compose_smtp_id', 'draft_id', 'post_archive', 'next_email_post'));
        if (!$success) {
            Hm_Msgs::add('Required field missing', 'warning');
            return;
        }

        /* defaults */
        $smtp_id = server_from_compose_smtp_id($form['compose_smtp_id']);
        $to = $form['compose_to'];
        $subject = $form['compose_subject'];
        $body_type = $this->get('smtp_compose_type', DEFAULT_SMTP_COMPOSE_TYPE);
        $draft = array(
            'draft_to' => $form['compose_to'],
            'draft_body' => '',
            'draft_subject' => $form['compose_subject'],
            'draft_smtp' => $smtp_id
        );
        $delivery_receipt = !empty($this->request->post['compose_delivery_receipt']);
        $from_params = '';
        $recipients_params = '';

        /* parse attachments */
        $uploaded_files = [];
        if (!empty($this->request->post['send_uploaded_files'])) {
            $uploaded_files = explode(',', $this->request->post['send_uploaded_files']);
            foreach($uploaded_files as $key => $file) {
                $uploaded_files[$key] = $this->config->get('attachment_dir').'/'.md5($this->session->get('username', false)).'/'.$file;
            }
        }

        $uploaded_files = get_uploaded_files_from_array(
            $uploaded_files
        );

        /* msg details */
        list($body, $cc, $bcc, $in_reply_to, $draft) = get_outbound_msg_detail($this->request->post, $draft, $body_type);

        /* smtp server details */
        $smtp_details = Hm_SMTP_List::dump($smtp_id, true);
        if (!$smtp_details) {
            Hm_Msgs::add('Could not use the selected SMTP server', 'warning');
            repopulate_compose_form($draft, $this);
            return;
        }

        /* profile details */
        $profiles = $this->get('compose_profiles', array());
        list($imap_server, $from_name, $reply_to, $from) = get_outbound_msg_profile_detail($form, $profiles, $smtp_details, $this);

        /* xoauth2 check */
        smtp_refresh_oauth2_token_on_send($smtp_details, $this, $smtp_id);

        /* adjust from and reply to addresses */
        list($from, $reply_to) = outbound_address_check($this, $from, $reply_to);

        /* try to connect */
        $mailbox = Hm_SMTP_List::connect($smtp_id, false);
        if (! $mailbox || ! $mailbox->authed()) {
            Hm_Msgs::add("Failed to authenticate to the SMTP server", "danger");
            repopulate_compose_form($draft, $this);
            return;
        }

        /* build message */
        $mime = new Hm_MIME_Msg($to, $subject, $body, $from, $body_type, $cc, $bcc, $in_reply_to, $from_name, $reply_to, $delivery_receipt);

        /* add attachments */
        $mime->add_attachments($uploaded_files);
        $res = $mime->process_attachments();

        /* get smtp recipients */
        $recipients = $mime->get_recipient_addresses();
        if (empty($recipients)) {
            Hm_Msgs::add("No valid recipients found", "warning");
            repopulate_compose_form($draft, $this);
            return;
        }

        /* send the message */
        $err_msg = $mailbox->send_message($from, $recipients, $mime->get_mime_msg(), $this->user_config->get('enable_compose_delivery_receipt_setting', false) && !empty($this->request->post['compose_delivery_receipt']));
        if ($err_msg) {
            Hm_Msgs::add(sprintf("%s", $err_msg), 'danger');
            repopulate_compose_form($draft, $this);
            return;
        }

        /* check for auto-bcc */
        $auto_bcc = $this->user_config->get('smtp_auto_bcc_setting', DEFAULT_SMTP_AUTO_BCC);
        if ($auto_bcc) {
            $mime->set_auto_bcc($from);
            $bcc_err_msg = $mailbox->send_message($from, array($from), $mime->get_mime_msg());
        }

        /* check for associated IMAP server to save a copy */
        if ($imap_server !== false) {
            $this->out('save_sent_server', $imap_server, false);
            $this->out('save_sent_msg', $mime);
        }
        else {
            Hm_Debug::add(sprintf('Unable to save sent message, no IMAP server found for SMTP server: %s', $smtp_details['server']));
        }
        if ($this->module_is_supported('contacts') && $this->user_config->get('contact_auto_collect_setting', false)) {
            $this->out('collect_contacts', true);
        }

        // Archive replied message
        if ($form['post_archive']) {
            $msg_path = explode('_', $this->request->post['compose_msg_path']);
            $msg_uid = $this->request->post['compose_msg_uid'];

            $mailbox = Hm_IMAP_List::get_connected_mailbox($msg_path[1]);
            if ($mailbox && $mailbox->authed()) {
                $specials = get_special_folders($this, $msg_path[1]);
                if (array_key_exists('archive', $specials) && $specials['archive']) {
                    $archive_folder = $specials['archive'];
                    $mailbox->message_action(hex2bin($msg_path[2]), 'ARCHIVE', array($msg_uid));
                    $mailbox->message_action(hex2bin($msg_path[2]), 'MOVE', array($msg_uid), $archive_folder);
                }
            }
        }

        if ($form['next_email_post']) {
            $this->out('msg_next_link', $form['next_email_post']);
        }

        /* clean up */
        $this->out('msg_sent', true);
        Hm_Msgs::add("Message Sent");

        /* if it is a draft, remove it */
        if ($this->module_is_supported('imap') && $imap_server !== false && $form['draft_id'] > 0) {
            $specials = get_special_folders($this, $imap_server);
            delete_draft($form['draft_id'], $this->cache, $imap_server, $specials['draft']);
        }

        delete_uploaded_files($this->session, $form['draft_id']);
        if ($form['draft_id'] > 0) {
            delete_uploaded_files($this->session, 0);
        }
    }
}

/**
 * Determine if composer_from is set
 * @subpackage smtp/handler
 */
class Hm_Handler_smtp_from_replace extends Hm_Handler_Module {
    public function process()
    {
        if (array_key_exists('compose_from', $this->request->get)) {
            $this->out('compose_from', $this->request->get['compose_from']);
        }
    }
}

/**
 * Determine if composer_from is set
 * @subpackage smtp/handler
 */
class Hm_Handler_smtp_subject_replace extends Hm_Handler_Module {
    public function process()
    {
        if (array_key_exists('compose_subject', $this->request->get)) {
            $this->out('compose_subject', $this->request->get['compose_subject']);
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
        $this->out('auto_bcc_enabled', $this->user_config->get('smtp_auto_bcc_setting', DEFAULT_SMTP_AUTO_BCC));
    }
}

class Hm_Handler_process_enable_attachment_reminder_setting extends Hm_Handler_Module {
    public function process() {
        function enable_attachment_reminder_callback($val) { return $val; }
        process_site_setting('enable_attachment_reminder', $this, 'enable_attachment_reminder_callback', false, true);
    }
}

/**
 * @subpackage smtp/handler
 */
class Hm_Handler_attachment_dir extends Hm_Handler_Module {
    public function process() {
        $this->out('attachment_dir', $this->config->get('attachment_dir'));
    }
}

/**
 * @subpackage smtp/handler
 */
class Hm_Handler_clear_attachment_chunks extends Hm_Handler_Module {
    public function process() {
        $attachment_dir = $this->config->get('attachment_dir');
        $rii = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($attachment_dir));
        foreach ($rii as $file) {
            if ($file->getFilename() == '.' || $file->getFilename() == '..') {
                continue;
            }
            if (is_dir($file->getPath()) && $file->getPath() != $attachment_dir){
                if (mb_strpos($file->getPath(), 'chunks-') !== False) {
                    rrmdir($file->getPath());
                }
            }
        }
        Hm_Msgs::add('Attachment chunks cleaned');
    }
}

/**
 * @subpackage keyboard_shortcuts/handler
 */
class Hm_Handler_process_enable_compose_delivery_receipt_setting extends Hm_Handler_Module {
    public function process() {
        function compose_delivery_receipt_enabled_callback($val) { return $val; }
        process_site_setting('enable_compose_delivery_receipt', $this, 'compose_delivery_receipt_enabled_callback', false, true);
    }
}

class Hm_Output_enable_compose_delivery_receipt_setting extends Hm_Output_Module {
    protected function output() {
        $settings = $this->get('user_settings');
        if (array_key_exists('enable_compose_delivery_receipt', $settings) && $settings['enable_compose_delivery_receipt']) {
            $checked = ' checked="checked"';
            $reset = '<span class="tooltip_restore" restore_aria_label="Restore default value"><i class="bi bi-arrow-counterclockwise refresh_list reset_default_value_checkbox"></i></span>';
        }
        else {
            $checked = '';
            $reset = '';
        }
        return '<tr class="general_setting"><td><label class="form-check-label" for="enable_compose_delivery_receipt">'.
            $this->trans('Enable delivery receipt').'</label></td>'.
            '<td><input class="form-check-input" type="checkbox" '.$checked.
            ' value="1" id="enable_compose_delivery_receipt" name="enable_compose_delivery_receipt" />'.$reset.'</td></tr>';
    }
}

/**
 * @subpackage keyboard_shortcuts/output
 */
class Hm_Output_attachment_setting extends Hm_Output_Module {
    protected function output() {
        $size_in_kbs = 0;
        $num_chunks = 0;
        if (!is_dir($this->get('attachment_dir'))) {
            return;
        }
        $rii = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($this->get('attachment_dir')));
        $files = array();

        foreach ($rii as $file) {
            if ($file->getFilename() == '.' || $file->getFilename() == '..') {
                continue;
            }
            if ($file->isDir()){
                continue;
            }
            if (mb_strpos($file->getPathname(), '.part') !== False) {
                $num_chunks++;
                $size_in_kbs += filesize($file->getPathname());
                $files[] = $file->getPathname();
            }
        }
        if ($size_in_kbs > 0) {
            $size_in_kbs = round(($size_in_kbs / 1024), 2);
        }
        return '<tr class="general_setting"><td><label>'.
            $this->trans('Attachment Chunks').'</label></td>'.
            '<td><small>('.$num_chunks.' Chunks) '.$size_in_kbs.' KB</small> <button id="clear_chunks_button" class="btn btn-light btn-sm border">Clear Chunks</button></td></tr>';
    }
}

/**
 * @subpackage smtp/output
 */
class Hm_Output_enable_attachment_reminder_setting extends Hm_Output_Module {
    protected function output() {
        $settings = $this->get('user_settings');
        if (array_key_exists('enable_attachment_reminder', $settings) && $settings['enable_attachment_reminder']) {
            $checked = ' checked="checked"';
            if(!$settings['enable_attachment_reminder']) {
                $reset = '<span class="tooltip_restore" restore_aria_label="Restore default value"><i class="bi bi-arrow-counterclockwise refresh_list reset_default_value_checkbox"></i></span>';
            }
            else {
                $reset='';
            }
        }
        else {
            $checked = '';
            $reset='';
        }
        return '<tr class="general_setting"><td><label class="form-check-label" for="enable_attachment_reminder">'.
            $this->trans('Enable Attachment Reminder').'</label></td>'.
            '<td><input type="checkbox" '.$checked.
            ' value="1" id="enable_attachment_reminder" class="form-check-input" name="enable_attachment_reminder" data-default-value="false" />'.$reset.'</td></tr>';
    }
}

/**
 * @subpackage smtp/output
 */
class Hm_Output_sent_folder_link extends Hm_Output_Module {
    protected function output() {
        $res = '<li class="menu_sent"><a class="unread_link" href="?page=message_list&amp;list_path=sent">';
        if (!$this->get('hide_folder_icons')) {
            $res .= '<i class="bi bi-send-check-fill menu-icon"></i>';
        }
        $res .= '<span class="nav-label">'.$this->trans('Sent').'</span></a></li>';
        $this->concat('formatted_folder_list', $res);
    }
}

/**
 * @subpackage smtp/output
 */
class Hm_Output_compose_title extends Hm_Output_Module {
    protected function output() {
        return'<div class="content_title px-3">'.$this->trans('Compose').'</div>';
    }
}

/**
 * @subpackage smtp/output
 */
class Hm_Output_compose_form_start extends Hm_Output_Module {
    protected function output() {
        $res = '<div class="container">';
        $res .= '<div class="row justify-content-md-center">';
        $res .= '<div class="col col-lg-10 col-xl-8">';
        $res .= '<form class="compose_form p-4" method="post" action="?page=compose" data-reminder="' . $this->get('enable_attachment_reminder', 0) . '">';
        return $res;
    }
}

/**
 * @subpackage smtp/output
 */
class Hm_Output_compose_form_end extends Hm_Output_Module {
    protected function output() {
        return '</form></div></div></div>';
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
            '</form>';
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
        $res = '<i class="bi bi-pencil-square draft_title refresh_list" title="'.$this->trans('Drafts').'"></i>';
        $res .= '<div class="draft_list">';
        foreach ($drafts as $id => $draft) {
            $subject = trim($draft['draft_subject']) ? trim($draft['draft_subject']) : 'Draft '.($id+1);
            $res .= '<div class="draft_'.$this->html_safe($id).'"><a class="draft_link" href="?page=compose&draft_id='.
                $this->html_safe($id).'">'.$this->html_safe($subject).'</a> '.
                '<i class="bi bi-x-circle-fill delete_draft" data-id="'.$this->html_safe($id).'"></i></div>';
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
        $body = '';
        $files = $this->get('uploaded_files', array());
        $cc = '';
        $bcc = '';
        $in_reply_to = '';
        $recip = '';

        $draft = $this->get('compose_draft', array());
        $reply = $this->get('reply_details', array());
        $imap_draft = $this->get('imap_draft', array());
        $reply_type = $this->get('reply_type', '');
        $html = $this->get('smtp_compose_type', DEFAULT_SMTP_COMPOSE_TYPE);
        $msg_path = $this->get('list_path', '');
        $msg_uid = $this->get('uid', '');
        $from = $this->get('compose_from');
        $subject = $this->get('compose_subject');
        $forward_as_attachment=$this->get('as_attr');

        if (!$msg_path) {
            $msg_path = $this->get('compose_msg_path', '');
        }
        if (!$msg_uid) {
            $msg_uid = $this->get('compose_msg_uid', '');
        }
        $smtp_id = false;
        $draft_id = $this->get('compose_draft_id', 0);

        if (!empty($reply)) {
            list($to, $cc, $subject, $body, $in_reply_to) = format_reply_fields(
                $reply['msg_text'], $reply['msg_headers'], $reply['msg_struct'], $html, $this, $reply_type);

            $recip = get_primary_recipient($this->get('compose_profiles', array()), $reply['msg_headers'],
                $this->get('smtp_servers', array()));
        }

        if(!empty($imap_draft)) {
            $recip = get_primary_recipient($this->get('compose_profiles', array()), $imap_draft,
                $this->get('smtp_servers', array(), True));
        }

        if (!empty($draft)) {
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
        if(isset($forward_as_attachment)){
           $body="";
           $to="";
           $subject ="";
           $cc="";
           $from="";
        }
        if ($imap_draft) {
            if (array_key_exists('Body', $imap_draft)) {
                $body = $imap_draft['Body'];
            }
            if (array_key_exists('To', $imap_draft)) {
                $to = $imap_draft['To'];
            }
            if (array_key_exists('Subject', $imap_draft)) {
                $subject = $imap_draft['Subject'];
            }
            if (array_key_exists('Cc', $imap_draft)) {
                $cc = $imap_draft['Cc'];
            }
            if (array_key_exists('From', $imap_draft)) {
                $from = $imap_draft['From'];
            }
            $draft_id = $msg_uid;
        }

        // User clicked on compose
        if ($reply_type) {
            $imap_server_id = explode('_', $msg_path)[1];
            $imap_server = Hm_IMAP_List::get($imap_server_id, false);
            $reply_from = process_address_fld($reply['msg_headers']['From']);

            if ($reply_type == 'reply_all' && $reply_from[0]['email'] != $imap_server['user'] && mb_strpos($to, $reply_from[0]['email']) === false) {
                $to .= ', '.$reply_from[0]['label'].' '.$reply_from[0]['email'];
            }
        }

        $send_disabled = '';
        if (count($this->get('smtp_servers', array())) == 0) {
            $send_disabled = 'disabled="disabled" ';
        }
        $res = '';
        if ($html == 1) {
            // TODO: The client should be provided all relevant configs so it can tell what appropriate js code to execute. This should not be handled by backend modules.
            $res .= '<script type="text/javascript">window.HTMLEditor = true</script>';
        }

        $res .= '<input type="hidden" name="hm_page_key" value="'.$this->html_safe(Hm_Request_Key::generate()).'" />'.
                '<input type="hidden" name="compose_msg_path" value="'.$this->html_safe($msg_path).'" />'.
                '<input type="hidden" name="post_archive" class="compose_post_archive" value="0" />'.
                '<input type="hidden" name="next_email_post" class="compose_next_email_data" value="" />'.
                '<input type="hidden" name="compose_msg_uid" value="'.$this->html_safe($msg_uid).'" />'.
                '<input type="hidden" class="compose_draft_id" name="draft_id" value="'.$this->html_safe($draft_id).'" />'.
                '<input type="hidden" class="saving_draft" value="0" />'.
                '<input type="hidden" class="compose_in_reply_to" name="compose_in_reply_to" value="'.$this->html_safe($in_reply_to).'" />'.

                '<div class="to_outer">'.
                    '<div class="mb-3 position-relative compose_container p-1 w-100 form-control">'.
                        '<div class="bubbles bubble_dropdown"></div>'.
                        '<input autocomplete="off" value="'.$this->html_safe($to).'" required name="compose_to" class="compose_to w-75" type="text" placeholder="'.$this->trans('To').'" id="compose_to" />'.
                        '<a href="#" tabindex="-1" class="toggle_recipients position-absolute end-0 pe-2"><i class="bi bi-plus-square-fill fs-3"></i></a>'.
                        '<div id="to_contacts"></div>'.
                    '</div>'.
                '</div>'.

                '<div class="recipient_fields">'.
                    '<div class="mb-3 compose_container form-control">'.
                        '<div class="bubbles bubble_dropdown"></div>'.
                        '<input autocomplete="off" value="'.$this->html_safe($cc).'" name="compose_cc" class="compose_cc" type="text" placeholder="'.$this->trans('Cc').'" id="compose_cc" />'.
                    '</div>'.
                    '<div id="cc_contacts"></div>'.
                    '<div class="mb-3 compose_container form-control">'.
                        '<div class="bubbles bubble_dropdown"></div>'.
                        '<input autocomplete="off" value="'.$this->html_safe($bcc).'" name="compose_bcc" class="compose_bcc" type="text" placeholder="'.$this->trans('Bcc').'" id="compose_bcc" />'.
                    '</div>'.
                    '<div id="bcc_contacts"></div>'.
                '</div>'.
                '<div class="form-floating mb-3">'.
                    '<input value="'.$this->html_safe($subject).'" name="compose_subject" class="compose_subject form-control" type="text" placeholder="'.$this->trans('Subject').'" id="compose_subject" />'.
                    '<label for="compose_subject">'.$this->trans('Subject').'</label>'.
                '</div>'.
                '<div class="form-floating mb-3">'.
                    '<textarea id="compose_body" name="compose_body" class="compose_body form-control" placeholder="'.$this->trans('Message').'">'.$this->html_safe($body).'</textarea>'.
                    (!$html ? '<label for="compose_body">'.$this->trans('Message').'</label>': '').
                '</div>';
                if($this->get('enable_compose_delivery_receipt_setting')) {
                    $res .= '<div class="form-check mb-3"><input value="1" name="compose_delivery_receipt" id="compose_delivery_receipt" type="checkbox" class="form-check-input" checked/><label for="compose_delivery_receipt" class="form-check-label">'.$this->trans('Request a delivery receipt').'</label></div>';
                }
        if ($html == 2) {
            $res .= '<link href="'.WEB_ROOT.'modules/smtp/assets/markdown/editor.css" rel="stylesheet" />'.
                '<script type="text/javascript" src="'.WEB_ROOT.'modules/smtp/assets/markdown/editor.js"></script>'.
                '<script type="text/javascript" src="'.WEB_ROOT.'modules/smtp/assets/markdown/marked.js"></script>'.
                '<script type="text/javascript">window.mdEditor = new Editor(); mdEditor.render();</script>';
        }
        $res .= '<table class="uploaded_files">';

        foreach ($files as $file) {
            $res .= format_attachment_row($file, $this);
        }

        /* select the correct account to unsubscribe from mailing lists */
        $selected_id = false;
        if (empty($recip) && !empty($from)) {
            /* This solves the problem when a profile is not associated with the email */
            $server_found = false;
            foreach ($this->module_output()['compose_profiles'] as $id => $server) {
                if ($server['address'] == $from) {
                    $server_found = true;
                    $smtp_profiles = profiles_by_smtp_id($this->get('compose_profiles'), $server['smtp_id']);
                    foreach ($smtp_profiles as $index => $smtp_profile) {
                        if ($server == $smtp_profile) {
                            $selected_id = $server['smtp_id'] . '.' . ($index + 1);
                            break 2;
                        }
                    }
                }
            }
            if (!$server_found) {
                foreach ($this->module_output()['smtp_servers'] as $id => $server) {
                    if ($server['user'] == $from) {
                        $selected_id = $id;
                    }
                }
                $recip = null;
            }
        }

        // replying a message takes the original imap server and tries to find a profile
        // matching that server to preselect on the list of available smtp options
        // relying on recipient here might fail as real recipient is sometimes different
        // than the address specified in the profile
        if (! empty($reply) && $imap_server) {
            foreach ($this->get('compose_profiles') as $profile) {
                if ($profile['server'] == $imap_server['server'] && $profile['user'] == $imap_server['user']) {
                    $smtp_profiles = profiles_by_smtp_id($this->get('compose_profiles'), $profile['smtp_id']);
                    foreach ($smtp_profiles as $index => $smtp_profile) {
                        if ($profile == $smtp_profile) {
                            $selected_id = $profile['smtp_id'] . '.' . ($index + 1);
                            break 2;
                        }
                    }
                }
            }
        }

        $res .= '</table>'.
            smtp_server_dropdown($this->module_output(), $this, $recip, $selected_id).
            '<div class="btn-group dropup">
                <button class="smtp_send_placeholder btn btn-primary mt-3" type="button" '.$send_disabled.'>'.$this->trans('Send').'</button><input class="smtp_send d-none" type="submit" value="'.$this->trans('Send').'" name="smtp_send"/>
                <button type="button" class="smtp_schedule_send btn btn-primary mt-3 dropdown-toggle dropdown-toggle-split" data-bs-toggle="dropdown" aria-expanded="false" '.$send_disabled.'>
                    <span class="visually-hidden">Toggle Dropdown</span>
                </button>'.
                schedule_dropdown($this).
            '</div>';

        if ($this->get('list_path') && ($reply_type == 'reply' || $reply_type == 'reply_all')) {
            $res .= '<input class="smtp_send_archive btn btn-primary mt-3" type="button" value="'.$this->trans('Send & Archive').'" name="smtp_send" '.$send_disabled.'/>';
        }

        $disabled_attachment = $this->get('attachment_dir_access') ? '' : 'disabled="disabled"';
        $res .= '<input type="hidden" value="" id="send_uploaded_files" name="send_uploaded_files" />'.
                '<input class="smtp_save btn btn-light float-end mt-3 border" type="button" value="'.$this->trans('Save').'" />'.
                '<input class="smtp_reset btn btn-light float-end mt-3 me-2 border" type="button" value="'.$this->trans('Reset').'" />'.
                '<input class="compose_attach_button btn btn-light float-end mt-3 me-2 border" value="'.$this->trans('Attach').'" name="compose_attach_button" type="button" '.$disabled_attachment.' />';
        return $res;
    }
}

/**
 * @subpackage smtp/output
 */
class Hm_Output_add_smtp_server_dialog extends Hm_Output_Module {
    protected function output() {
        if ($this->get('single_server_mode')) {
            return '';
        }
        $count = count(array_filter($this->get('smtp_servers', array()), fn($s) => ($s['type'] ?? null) !== 'ews'));
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

        return '<div class="smtp_server_setup">
                    <div data-target=".smtp_section" class="server_section border-bottom cursor-pointer px-1 py-3 pe-auto">
                        <a href="#" class="pe-auto">
                            <i class="bi bi-file-earmark-text-fill me-3"></i>
                            <b>'.$this->trans('SMTP Servers').'</b>
                        </a>
                        <div class="server_count">'.$count.'</div>
                    </div>
                    <div class="smtp_section px-4 pt-3">
                        <div class="row">
                        <div class="col-12 col-lg-4 mb-4">
                            <form class="" method="POST">
                                <input type="hidden" name="hm_page_key" value="'.$this->html_safe(Hm_Request_Key::generate()).'" />
                                <div class="subtitle">'.$this->trans('Add an SMTP Server').'</div>

                                <div class="form-floating mb-3">
                                    <input required type="text" class="form-control" id="new_smtp_name" name="new_smtp_name" placeholder="'.$this->trans('Account name').'" value="'.$name.'">
                                    <label for="new_smtp_name">'.$this->trans('SMTP account name').'</label>
                                </div>

                                <div class="form-floating mb-3">
                                    <input required type="text" class="form-control" id="new_smtp_address" name="new_smtp_address" placeholder="'.$this->trans('SMTP server address').'" value="'.$address.'">
                                    <label for="new_smtp_address">'.$this->trans('SMTP server address').'</label>
                                </div>

                                <div class="form-floating mb-3">
                                    <input required type="number" class="form-control" id="new_smtp_port" name="new_smtp_port" placeholder="'.$this->trans('Port').'" value="'.$port.'">
                                    <label for="new_smtp_port">'.$this->trans('SMTP port').'</label>
                                </div>

                                <div class="mb-3">
                                    <input type="radio" name="tls" value="1" id="smtp_tls" checked="checked" />
                                    <label for="smtp_tls">'.$this->trans('Use TLS').'</label>
                                    <br />
                                    <input type="radio" name="tls" id="smtp_notls" value="0" />
                                    <label for="smtp_notls">'.$this->trans('STARTTLS or unencrypted').'</label>
                                </div>

                                <div class="">
                                    <input class="btn btn-primary px-5" type="submit" value="'.$this->trans('Add').'" name="submit_smtp_server" />
                                </div>
                            </form>
                        </div>';

    }
}

/**
 * @subpackage smtp/output
 */
class Hm_Output_compose_type_setting extends Hm_Output_Module {
    protected function output() {
        $selected = DEFAULT_SMTP_COMPOSE_TYPE;
        $settings = $this->get('user_settings', array());
        $reset = '';
        if (array_key_exists('smtp_compose_type', $settings)) {
            $selected = $settings['smtp_compose_type'];
        }
        $res = '<tr class="general_setting"><td>'.$this->trans('Outbound mail format').'</td><td><select class="form-select form-select-sm w-auto" name="smtp_compose_type" data-default-value="'.DEFAULT_SMTP_COMPOSE_TYPE.'">';
        $res .= '<option ';
        if ($selected == 0) {
            $res .= 'selected="selected" ';
        }
        $res .= 'value="0">'.$this->trans('Plain text').'</option><option ';
        if ($selected == 1) {
            $res .= 'selected="selected" ';
        }
        $res .= 'value="1">'.$this->trans('HTML').'</option><option ';
        if ($selected == 2) {
            $res .= 'selected="selected" ';
        }
        if ($selected != 0) {
            $reset = '<span class="tooltip_restore" restore_aria_label="Restore default value"><i class="bi bi-arrow-counterclockwise refresh_list reset_default_value_select"></i></span>';
        }
        $res .= 'value="2">'.$this->trans('Markdown').'</option></select>'.$reset.'</td></tr>';
        return $res;
    }
}

/**
 * @subpackage smtp/output
 */
class Hm_Output_auto_bcc_setting extends Hm_Output_Module {
    protected function output() {
        $auto = DEFAULT_SMTP_AUTO_BCC;
        $settings = $this->get('user_settings', array());
        if (array_key_exists('smtp_auto_bcc', $settings)) {
            $auto = $settings['smtp_auto_bcc'];
        }
        $res = '<tr class="general_setting"><td><label class="form-check-label" for="smtp_auto_bcc">'.$this->trans('Always BCC sending address').'</label></td><td><input class="form-check-input" value="1" type="checkbox" name="smtp_auto_bcc" id="smtp_auto_bcc"  data-default-value="false"';
        $reset = '';
        if ($auto) {
            $res .= ' checked="checked"';
            $reset = '<span class="tooltip_restore" restore_aria_label="Restore default value"><i class="bi bi-arrow-counterclockwise refresh_list reset_default_value_checkbox"></i></span>';
        }
        $res .= '>'.$reset.'</td></tr>';
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
        if ($this->get('single_server_mode')) {
            return '';
        }

        $list = $this->get('smtp_servers', array());

        if (empty($list)) {
            return '';
        }

        $res = '<div class="subtitle mt-4 fw-bold border-top pt-4">'.$this->trans('SMTP Servers').'</div>';
        foreach ($list as $index => $vals) {

            $no_edit = false;

            if (array_key_exists('type', $vals) && $vals['type'] == 'ews') {
                continue;
            }

            if (array_key_exists('user', $vals) && !array_key_exists('nopass', $vals)) {
                $disabled = 'disabled="disabled"';
                $user_pc = $vals['user'];
                $pass_pc = $this->trans('[saved]');
                $pass_value = '************';
            }
            elseif (array_key_exists('user', $vals) && array_key_exists('nopass', $vals)) {
                $user_pc = $vals['user'];
                $pass_pc = $this->trans('Password');
                $disabled = '';
                $pass_value = '';
            }
            else {
                $user_pc = '';
                $pass_pc = $this->trans('Password');
                $disabled = '';
                $pass_value = '';
            }
            $res .= '<div class="smtp_server mb-3">';

            $res .= '<form class="smtp_connect"  method="POST"><div class="row">';
            $res .= '<input type="hidden" name="hm_page_key" value="'.$this->html_safe(Hm_Request_Key::generate()).'" />';
            $res .= '<input type="hidden" name="smtp_server_id" class="smtp_server_id" value="'.$this->html_safe($index).'" />';
            $res .= '<div class="row m-0 p-0 credentials-container"><div class="col-lg-2 col-md-6 mb-2 overflow-auto">';
            $res .= sprintf('<div class="text-muted"><strong>%s</strong></div>
                <div class="server_subtitle">%s/%d %s</div>',
                $this->html_safe($vals['name']), $this->html_safe($vals['server']), $this->html_safe($vals['port']), $vals['tls'] ? 'TLS' : '' );
            $res .= '</div><div class="col-xl-7 col-lg-7 col-md-9"><div class="row"><div class="col-md-6 col-lg-4">';

            // SMTP Username
            $res .= '<div class="form-floating mb-2">';
            $res .= '<input '.$disabled.' class="form-control credentials" id="smtp_user_'.$index.'" type="text" name="smtp_user" value="'.$this->html_safe($user_pc).'" placeholder="'.$this->trans('Username').'">';
            $res .= '<label for="smtp_user_'.$index.'">'.$this->trans('SMTP username').'</label></div>';
            $res .= '</div><div class="col-md-6 col-lg-4">';

            // SMTP Password
            $res .= '<div class="form-floating mb-2">';
            $res .= '<input '.$disabled.' class="form-control credentials smtp_password" type="password" id="smtp_pass_'.$index.'" name="smtp_pass" value="'.$pass_value.'" placeholder="'.$pass_pc.'">';
            $res .= '<label for="smtp_pass_'.$index.'">'.$this->trans('SMTP password').'</label></div>';
            $res .= '</div><div class="col-md-6 col-lg-4"></div>';

            // Buttons
            $res .= '</div> </div> <div class="col-lg-6 col-xl-3 col-mg-6 d-flex justify-content-start align-items-center">';
            if (!$no_edit) {
                if (!isset($vals['user']) || !$vals['user']) {
                    $res .= '<input type="submit" value="'.$this->trans('Delete').'" class="delete_smtp_connection btn btn-light border btn-sm me-2" />';
                }
                else {
                    $keysToRemove = array('object', 'connected');
                    $serverDetails = array_diff_key($vals, array_flip($keysToRemove));

                    $res .= '<input type="submit" value="'.$this->trans('Edit').'" class="edit_server_connection btn btn-outline-success btn-sm me-2" data-server-details=\''.$this->html_safe(json_encode($serverDetails)).'\' data-id="'.$this->html_safe($serverDetails['name']).'" data-type="smtp" />';
                    $res .= '<input type="submit" value="'.$this->trans('Test').'" class="test_smtp_connect btn btn-outline-primary btn-sm me-2" />';
                    $res .= '<input type="submit" value="'.$this->trans('Delete').'" class="delete_smtp_connection btn btn-outline-danger btn-sm me-2" />';
                }
                $res .= '<input type="hidden" value="ajax_smtp_debug" name="hm_ajax_hook" />';
            }
            $res .= '</div></div> </div> </form> </div>';
        }
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
            $res .= '<i class="bi bi-file-earmark-text menu-icon"></i>';
        }
        $res .= '<span class="nav-label">'.$this->trans('Compose').'</span></a></li>';

        if ($this->format == 'HTML5') {
            return $res;
        }
        $this->concat('formatted_folder_list', $res);
    }
}

/**
 * @subpackage
 */
class Hm_Output_stepper_setup_server_smtp extends Hm_Output_Module {
    protected function output() {
        return '
            <div class="step_config-smtp_bloc mb-3" id="step_config-smtp_bloc">
               <label><strong>SMTP</strong></label>
                 <div class="form-floating">
                   <input required type="text" id="srv_setup_stepper_smtp_address" name="srv_setup_stepper_smtp_address" class="txt_fld form-control" value="" placeholder="'.$this->trans('Address').'">
                   <label class="" for="srv_setup_stepper_smtp_address">'.$this->trans('Address').'</label>
                   <span id="srv_setup_stepper_smtp_address-error" class="invalid-feedback"></span>
                 </div>
                 <div class="d-flex">
                   <div class="flex-fill">
                         <div class="form-floating">
                           <input required type="number" id="srv_setup_stepper_smtp_port" name="srv_setup_stepper_smtp_port" class="txt_fld form-control" value="" placeholder="'.$this->trans('Port').'">
                           <label class="" for="srv_setup_stepper_smtp_port">'.$this->trans('Port').'</label>
                           <span id="srv_setup_stepper_imap_address-error" class="invalid-feedback"></span>
                         </div>
                         <span id="srv_setup_stepper_smtp_port-error" class="invalid-feedback"></span>
                   </div>
                   <div class="p-2 flex-fill">
                       <div class="form-check">
                           <input class="form-check-input" type="radio" id="smtp_tls" name="srv_setup_stepper_smtp_tls" value="true" checked>
                           <label class="form-check-label" style="font-size: 12px;" for="smtp_tls">
                             '.$this->trans('Use TLS').'
                           </label>
                       </div>
                       <div class="form-check">
                           <input class="form-check-input" type="radio" id="smtp_start_tls" name="srv_setup_stepper_smtp_tls"  value="false" >
                           <label class="form-check-label" style="font-size: 12px;" for="smtp_start_tls">
                             '.$this->trans('STARTTLS or unencrypted').'
                           </label>
                       </div>
                   </div>
                 </div>
           </div>
        ';
    }
}

/**
 * Send scheduled messages
 * @subpackage smtp/handler
 */
class Hm_Handler_send_scheduled_messages extends Hm_Handler_Module {
    /**
     * Send delayed messages
     * This should use cron
     */
    public function process() {
        if (!($this->module_is_supported('imap') || $this->module_is_supported('profiles'))) {
            return;
        }

        $servers = Hm_IMAP_List::dumpForMailbox();
        $scheduled_msg_count = 0;

        foreach ($servers as $server_id => $config) {
            $mailbox = new Hm_Mailbox($server_id, $this->user_config, $this->session, $config);
            if ($mailbox->connect()) {
                $folder = 'Scheduled';
                if (! $mailbox->folder_exists($folder)) {
                    continue;
                }
                $ret = $mailbox->get_messages($folder, 'DATE', false, 'ALL');
                foreach ($ret[1] as $msg) {
                    $msg_headers = $mailbox->get_message_headers($folder, $msg['uid']);
                    if (! empty($msg_headers['X-Schedule'])) {
                        $scheduled_msg_count++;
                    } else {
                        continue;
                    }
                    if (send_scheduled_message($this, $mailbox, $folder, $msg['uid'])) {
                        $scheduled_msg_count++;
                    }
                }
            }
        }

        $this->out('scheduled_msg_count', $scheduled_msg_count);
    }
}

/**
 * Changes the schedule of the message
 * @subpackage smtp/handler
 */
class Hm_Handler_re_schedule_message_sending extends Hm_Handler_Module {
    public function process() {
        if (!($this->module_is_supported('imap') || $this->module_is_supported('profiles'))) {
            return;
        }
        list($success, $form) = $this->process_form(array('schedule_date', 'scheduled_msg_ids'));
        if (!$success) {
            return;
        }
        $scheduled_msg_count = 0;
        $new_schedule_date = $form['schedule_date'];
        if ($form['schedule_date'] != 'now') {
            $new_schedule_date = get_scheduled_date($form['schedule_date']);
        }
        $ids = explode(',', $form['scheduled_msg_ids']);
        foreach ($ids as $msg_part) {
            list($imap_server_id, $msg_id, $folder) = explode('_', $msg_part);
            $imap_server = Hm_IMAP_List::getForMailbox($imap_server_id);

            $mailbox = new Hm_Mailbox($imap_server_id, $this->user_config, $this->session, $imap_server);
                if ($mailbox->connect()) {
                $folder = hex2bin($folder);
                if (reschedule_message_sending($this, $mailbox, $msg_id, $folder, $new_schedule_date)) {
                    $scheduled_msg_count++;
                }
            }
        }
        $this->out('scheduled_msg_count', $scheduled_msg_count);
        if ($scheduled_msg_count == count($ids)) {
            $msg = 'Operation successful';
        } elseif ($scheduled_msg_count > 0) {
            $msg = 'Some messages have been scheduled for sending';
        } else {
            $msg = 'ERRFailed to schedule sending for messages';
        }
        Hm_Msgs::add($msg);
        $this->save_hm_msgs();
    }
}

/**
 * Add scheduled send to the message list controls
 * @subpackage imap/output
 */
class Hm_Output_scheduled_send_msg_control extends Hm_Output_Module {
    protected function output() {
        $parts = explode('_', $this->get('list_path'));
        if ($parts[0] == 'imap' && hex2bin($parts[2]) == 'Scheduled') {
            $res = schedule_dropdown($this, true);
            $this->concat('msg_controls_extra', $res);
        }
    }
}

/**
 * @subpackage smtp/functions
 */
if (!hm_exists('smtp_server_dropdown')) {
function smtp_server_dropdown($data, $output_mod, $recip, $selected_id=false) {
    $res = '<div class="form-floating"><select id="compose_smtp_id" name="compose_smtp_id" class="compose_server form-select" aria-label="'.$output_mod->trans('Compose SMTP ID').'">';
    $profiles = array();
    if (array_key_exists('compose_profiles', $data)) {
        $profiles = $data['compose_profiles'];
    }
    if (array_key_exists('smtp_servers', $data)) {
        $selected = false;
        $default = false;
        foreach ($data['smtp_servers'] as $id => $vals) {
            foreach (profiles_by_smtp_id($profiles, $vals['id']) as $index => $profile) {
                if ($profile['default']) {
                    $default = $vals['id'].'.'.($index + 1);
                }
                if ((string) $selected_id === sprintf('%s.%s', $vals['id'], ($index + 1))) {
                    $selected = $vals['id'].'.'.($index + 1);
                }
                elseif ($recip && trim($recip) == $profile['address']) {
                    $selected = $vals['id'].'.'.($index + 1);
                }
            }
            if (!$selected && $selected_id !== false && $vals['id'] == $selected_id) {
                $selected = $vals['id'];
            }
            if (!$selected && $recip && trim($recip) == trim($vals['user'])) {
                $selected = $vals['id'];
            }
        }
        if ($selected === false && $default !== false) {
            $selected = $default;
        }
        foreach ($data['smtp_servers'] as $id => $vals) {
            $smtp_profiles = profiles_by_smtp_id($profiles, $vals['id']);
            if (count($smtp_profiles) > 0) {
                foreach ($smtp_profiles as $index => $profile) {
                    $res .= '<option data-email="'.$profile['address'].'"';
                    if ((string) $selected === sprintf('%s.%s', $vals['id'], ($index + 1)) || (! mb_strstr(strval($selected), '.') && strval($selected) === strval($vals['id']))) {
                        $res .= 'selected="selected" ';
                    }
                    $res .= 'value="'.$output_mod->html_safe($vals['id'].'.'.($index+1)).'">';
                    $res .= $output_mod->html_safe(sprintf('"%s" %s %s', $profile['name'], ($profile['name'] != $profile['address']) ? $profile['address']: "", ($vals['name'] != $profile['name'] && $vals['name'] != $profile['address']) ? $vals['name'] : ""));
                    $res .= '</option>';
                }
            }
            else {
                $res .= '<option data-email="'.$vals['user'].'"';
                if ($selected === $id) {
                    $res .= 'selected="selected" ';
                }
                $res .= 'value="'.$output_mod->html_safe($vals['id']).'">';
                $res .= $output_mod->html_safe(($vals['user'] == $vals['name']) ? $vals['user'] : sprintf("%s - %s", $vals['user'], $vals['name']));
                $res .= '</option>';
            }
        }
    }
    $res .= '</select><label for="compose_smtp_id">'.$output_mod->trans('Compose SMTP ID').'</label></div>';
    return $res;
}}

/**
 * Check for and do an Oauth2 token reset if needed
 * @param array $server SMTP server data
 * @param object $config site config object
 * @return mixed
 */
if (!hm_exists('smtp_refresh_oauth2_token')) {
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
}}

/**
 * @subpackage smtp/functions
 */
if (!hm_exists('delete_uploaded_files')) {
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
}}

/**
 * @subpackage/functions
 */
if (!hm_exists('get_uploaded_files')) {
function get_uploaded_files($id, $session) {
    $files = $session->get('uploaded_files', array());
    if (array_key_exists($id, $files)) {
        return $files[$id];
    }

    return array();
}}

/**
 * @subpackage/functions
 */
if (!hm_exists('save_uploaded_file')) {
function save_uploaded_file($id, $atts, $session) {
    $files = $session->get('uploaded_files', array());
    if (array_key_exists($id, $files)) {
        $files[$id][] = $atts;
    }
    else {
        $files[$id] = array($atts);
    }
    $session->set('uploaded_files', $files);
}}

/**
 * @subpackage smtp/functions
 */
if (!hm_exists('format_attachment_row')) {
function format_attachment_row($file, $output_mod) {
    $unique_identifier = str_replace(' ', '_', $output_mod->html_safe($file['name']));
    return '<tr id="tr-'.$unique_identifier.'"><td>'.
            $output_mod->html_safe($file['name']).'</td><td>'.$output_mod->html_safe($file['type']).' ' .$output_mod->html_safe(round($file['size']/1024, 2)). 'KB '.
            '<td style="display:none"><input name="uploaded_files[]" type="text" value="'.$file['name'].'" /></td>'.
            '</td><td><a class="remove_attachment text-danger" id="remove-'.$unique_identifier.'" href="#">Remove</a><a style="display:none" id="pause-'.$unique_identifier.'" class="pause_upload" href="#">Pause</a><a style="display:none" id="resume-'.$unique_identifier.'" class="resume_upload" href="#">Resume</a></td></tr><tr><td colspan="2">'.
            '<div class="meter" style="width:100%; display: none;"><span id="progress-'.
            $unique_identifier.'" style="width:0%;"><span class="progress" id="progress-bar-'.
            $unique_identifier.'"></span></span></div></td></tr>';
}}

/**
 * @subpackage smtp/functions
 */
if (!hm_exists('get_primary_recipient')) {
function get_primary_recipient($profiles, $headers, $smtp_servers, $is_draft=False) {
    $addresses = array();
    $flds = array('to', 'delivered-to', 'x-delivered-to', 'envelope-to', 'x-original-to', 'cc', 'reply-to');
    if ($is_draft) {
        $flds = array('from', 'to', 'delivered-to', 'x-delivered-to', 'envelope-to', 'x-original-to', 'cc', 'reply-to');
    }
    $headers = lc_headers($headers);
    foreach ($flds as $fld) {
        if (array_key_exists($fld, $headers)) {
            $header = $headers[$fld];
            if (is_array($header)) {
                foreach ($header as $value) {
                    foreach (process_address_fld($value) as $address) {
                        $addresses[] = $address['email'];
                    }
                }
            } else {
                foreach (process_address_fld($header) as $address) {
                    $addresses[] = $address['email'];
                }
            }
        }
    }
    $addresses = array_unique($addresses);
    foreach ($addresses as $address) {
        foreach ($smtp_servers as $id => $vals) {
            foreach (profiles_by_smtp_id($profiles, $vals['id']) as $profile) {
                if ($profile['address'] == $address) {
                    return $address;
                }
            }
        }
    }
    foreach ($addresses as $address) {
        foreach ($smtp_servers as $id => $vals) {
            if ($vals['user'] == $address) {
                return $address;
            }
        }
    }
    return false;
}}

/**
 * @subpackage/functions
 */
if (!hm_exists('delete_draft')) {
function delete_draft($id, $cache, $imap_server_id, $folder) {
    $mailbox = Hm_IMAP_List::get_connected_mailbox($imap_server_id);
    if ($mailbox && $mailbox->authed()) {
        if ($mailbox->message_action($folder, 'DELETE', array($id))['status']) {
            $mailbox->message_action($folder, 'EXPUNGE', array($id));
            return true;
        }
    }
    return false;
}}

/**
 * @subpackage smtp/functions
 */
if (!hm_exists('find_imap_by_smtp')) {
function find_imap_by_smtp($imap_profiles, $smtp_profile) {
    foreach ($imap_profiles as $profile) {
        if ($smtp_profile['user'] == $profile['user']) {
            return $profile;
        }
        if (explode('@', $smtp_profile['user'])[0]
            == explode('@', $profile['user'])[0]) {
            return $profile;
        }
        if ($smtp_profile['user'] == $profile['name']) {
            return $profile;
        }
    }
}}


/**
 * Delete a directory RECURSIVELY
 * @param string $dir - directory path
 * @link http://php.net/manual/en/function.rmdir.php
 */
if (!hm_exists('rrmdir')) {
    function rrmdir($dir) {
        if (is_dir($dir)) {
            $objects = scandir($dir);
            foreach ($objects as $object) {
                if ($object != "." && $object != "..") {
                    if (filetype($dir . "/" . $object) == "dir") {
                        rrmdir($dir . "/" . $object);
                    } else {
                        unlink($dir . "/" . $object);
                    }
                }
            }
            reset($objects);
            rmdir($dir);
        }
    }
}

/**
 *
 * Check if all the parts exist, and
 * gather all the parts of the file together
 * @param string $temp_dir - the temporary directory holding all the parts of the file
 * @param string $fileName - the original file name
 * @param string $chunkSize - each chunk size (in bytes)
 * @param string $totalSize - original file size (in bytes)
 * @subpackage smtp/functions
 */
if (!hm_exists('createFileFromChunks')) {
    function createFileFromChunks($temp_dir, $fileName, $chunkSize, $totalSize,$total_files) {
        // count all the parts of this file
        // $fileName = Hm_Crypt::ciphertext($fileName, Hm_Request_Key::generate());
        $total_files_on_server_size = 0;
        $temp_total = 0;
        foreach(scandir($temp_dir) as $file) {
            $temp_total = $total_files_on_server_size;
            $tempfilesize = filesize($temp_dir.'/'.$file);
            $total_files_on_server_size = $temp_total + $tempfilesize;
        }
        // check that all the parts are present
        // If the Size of all the chunks on the server is equal to the size of the file uploaded.
        if ($total_files_on_server_size >= $totalSize) {
        // create the final destination file
            if (($fp = fopen($temp_dir.'/../'.$fileName, 'w')) !== false) {
                for ($i=1; $i<=$total_files; $i++) {
                    fwrite($fp, file_get_contents($temp_dir.'/'.$fileName.'.part'.$i));
                }
                fclose($fp);
                $hashed_content = Hm_Crypt::ciphertext(file_get_contents($temp_dir.'/../'.$fileName), Hm_Request_Key::generate());
                file_put_contents($temp_dir.'/../'.$fileName, $hashed_content);
            } else {
                return false;
            }

            // rename the temporary directory (to avoid access from other
            // concurrent chunks uploads) and than delete it
            if (rename($temp_dir, $temp_dir.'_UNUSED')) {
                rrmdir($temp_dir.'_UNUSED');
            } else {
                rrmdir($temp_dir);
            }
        }
        return true;
    }
}

/**
 * @subpackage smtp/functions
 */
if (!hm_exists('get_uploaded_files_from_array')) {
function get_uploaded_files_from_array($uploaded_files) {
    $parsed_files = [];
    foreach($uploaded_files as $file) {
        $parsed_path = explode('/', $file);
        $parsed_files[] = [
            'filename' => $file,
            'type' => get_mime_type($file),
            'name' => end($parsed_path)
        ];
    }
    return $parsed_files;
}
}

function prepare_draft_mime($atts, $uploaded_files, $from = false, $name = '', $profile_id = null) {
    $uploaded_files = get_uploaded_files_from_array($uploaded_files);
    $mime = new Hm_MIME_Msg(
        $atts['draft_to'],
        $atts['draft_subject'],
        $atts['draft_body'],
        $from,
        false,
        $atts['draft_cc'],
        $atts['draft_bcc'],
        '',
        $name,
        $atts['draft_in_reply_to'],
        $atts['delivery_receipt'],
        $atts['schedule'],
        $profile_id
    );

    $mime->add_attachments($uploaded_files);

    return $mime;
}

/**
 * @subpackage smtp/functions
 */
if (!hm_exists('save_imap_draft')) {
function save_imap_draft($atts, $id, $session, $mod, $mod_cache, $uploaded_files, $profile) {
    $imap_profile = false;
    $from = false;
    $name = '';
    $uploaded_files = get_uploaded_files_from_array($uploaded_files);
    
    if ($profile  && $profile['type'] == 'imap' && $mod->module_is_supported('imap')) {
        $from = $profile['replyto'];
        $name = $profile['name'];
        $imap_profile = Hm_IMAP_List::fetch($profile['user'], $profile['server']);
    }
    if (!$imap_profile || empty($imap_profile)) {
        $imap_profile = find_imap_by_smtp(
            $mod->user_config->get('imap_servers'),
            $mod->user_config->get('smtp_servers')[$atts['draft_smtp']]
        );
        if ($imap_profile) {
            $from = $mod->user_config->get('smtp_servers')[$atts['draft_smtp']]['user'];
        }
    }
    if (empty($imap_profile)) {
        return -1;
    }

    $imap_profile = Hm_IMAP_List::getForMailbox($imap_profile['id']);
    $specials = get_special_folders($mod, $imap_profile['id']);

    if ((!array_key_exists('draft', $specials) || !$specials['draft']) && !array_key_exists('schedule', $atts)) {
        Hm_Msgs::add('There is no draft directory configured for this account.', 'warning');
        return -1;
    }
    $mailbox = new Hm_Mailbox($imap_profile['id'], $mod->user_config, $session, $imap_profile);
    
    if (! $mailbox->connect()) {
        return -1;
    }

    if (!empty($atts['schedule'])) {
        $folder ='Scheduled';
        if (!$mailbox->folder_exists($folder)) {
            $mailbox->create_folder($folder);
        }
        $atts['schedule'] = get_scheduled_date($atts['schedule']);
    } else {
        $folder = $specials['draft'];
    }
    
    $mime = prepare_draft_mime($atts, $uploaded_files, $from, $name, $profile['id']);
    $res = $mime->process_attachments();

    if (! empty($atts['schedule']) && empty($mime->get_recipient_addresses())) {
        Hm_Msgs::add("ERRNo valid recipients found");
        return -1;
    }

    $msg = str_replace("\r\n", "\n", $mime->get_mime_msg());
    $msg = str_replace("\n", "\r\n", $msg);
    $msg = rtrim($msg)."\r\n";

    if (! $mailbox->store_message($folder, $msg, false, true)) {
        Hm_Msgs::add('An error occurred saving the draft message', 'danger');
        return -1;
    }
    
    $messages = $mailbox->get_messages($folder, 'ARRIVAL', true, 'DRAFT', 0, 10);
    
    // Remove old version from the mailbox
    if ($id) {
        $mailbox->message_action($folder, 'DELETE', array($id));
        $mailbox->message_action($folder, 'EXPUNGE', array($id));
    }
    foreach ($messages[1] as $mail) {
        $msg_header = $mailbox->get_message_headers($folder, $mail['uid']);
        // Convert all header keys to lowercase
        $msg_header_lower = array_change_key_case($msg_header, CASE_LOWER);
        $mime_headers_lower = array_change_key_case($mime->get_headers(), CASE_LOWER);
        if (!empty($msg_header_lower['message_id']) && !empty($mime_headers_lower['message_id'])) {
            if (trim($msg_header_lower['message_id']) === trim($mime_headers_lower['message_id'])) {
                return $mail['uid'];
            }
        }
        if (!empty($msg_header_lower['message-id']) && !empty($mime_headers_lower['message-id'])) {
            $msg_message_id = trim(str_replace(array('<', '>'), '', trim($msg_header_lower['message-id'])));
            $msg_mime_id = trim(str_replace(array('<', '>'), '', trim($mime_headers_lower['message-id'])));
            if (trim($msg_message_id) === trim($msg_mime_id)) {
                return $mail['uid'];
            }
        }
    }
    return -1;
}}

/**
 * @subpackage smtp/functions
 */
if (!hm_exists('get_draft')) {
function get_draft($id, $session) {
    $drafts = $session->get('compose_drafts', array());
    if (array_key_exists($id, $drafts)) {
        return $drafts[$id];
    }
    return false;
}}

/**
 * @subpackage smtp/functions
 */
if (!hm_exists('next_draft_key')) {
function next_draft_key($session) {
    $drafts = $session->get('compose_drafts', array());
    if (count($drafts)) {
        return max(array_keys($drafts))+1;
    } else {
        return 0;
    }
}}

/**
 * @subpackage smtp/functions
 */
if (!hm_exists('get_outbound_msg_detail')) {
function get_outbound_msg_detail($post, $draft, $body_type) {
    $body = '';
    $cc = '';
    $bcc = '';
    $in_reply_to = '';

    if (array_key_exists('compose_body', $post)) {
        $body = $post['compose_body'];
        $draft['draft_body'] = $post['compose_body'];
    }
    if (array_key_exists('compose_cc', $post)) {
        $cc = $post['compose_cc'];
        $draft['draft_cc'] = $post['compose_cc'];
    }
    if (array_key_exists('compose_bcc', $post)) {
        $bcc = $post['compose_bcc'];
        $draft['draft_bcc'] = $post['compose_bcc'];
    }
    if (array_key_exists('compose_in_reply_to', $post)) {
        $in_reply_to = $post['compose_in_reply_to'];
        $draft['draft_in_reply_to'] = $post['compose_in_reply_to'];
    }
    if ($body_type == 2) {
        $converter = new GithubFlavoredMarkdownConverter([
            'html_input' => 'strip',
            'allow_unsafe_links' => false,
        ]);
        $body = $converter->convert($body);
    }
    return array($body, $cc, $bcc, $in_reply_to, $draft);
}}

/**
 * @subpackage smtp/functions
 */
if (!hm_exists('get_outbound_msg_profile_detail')) {
function get_outbound_msg_profile_detail($form, $profiles, $smtp_details, $hmod) {
    $imap_server = false;
    $from_name = '';
    $reply_to = '';
    $from = $smtp_details['user'];
    $profile = profile_from_compose_smtp_id($profiles, $form['compose_smtp_id']);
    if ($profile) {
        if ($profile['type'] == 'imap' && $hmod->module_is_supported('imap')) {
            $imap = Hm_IMAP_List::fetch($profile['user'], $profile['server']);
            if ($imap) {
                $imap_server = $imap['id'];
            }
        }
        $from_name = $profile['name'];
        $reply_to = $profile['replyto'];
        if ($profile['address']) {
            $from = $profile['address'];
        }
    }
    if ($from == $smtp_details['user'] && mb_strpos($from, '@') === false) {
        if (array_key_exists('HTTP_HOST', $hmod->request->server)) {
            $from .= sprintf('@%s', $hmod->request->server['HTTP_HOST']);
        }
    }
    return array($imap_server, $from_name, $reply_to, $from);
}}

/**
 * @subpackage smtp/functions
 */
if (!hm_exists('smtp_refresh_oauth2_token_on_send')) {
function smtp_refresh_oauth2_token_on_send($smtp_details, $mod, $smtp_id) {
    if (array_key_exists('auth', $smtp_details) && $smtp_details['auth'] == 'xoauth2') {
        $results = smtp_refresh_oauth2_token($smtp_details, $mod->config);
        if (!empty($results)) {
            if (Hm_SMTP_List::update_oauth2_token($smtp_id, $results[1], $results[0])) {
                Hm_Debug::add(sprintf('Oauth2 token refreshed for SMTP server id %s', $smtp_id), 'info');
                Hm_SMTP_List::save();
            }
        }
    }
}}

/*
 * @subpackage smtp/functions
 */
if (!hm_exists('outbound_address_check')) {
function outbound_address_check($mod, $from, $reply_to) {
    $domain = $mod->config->get('default_email_domain');
    if (!$domain) {
        if (array_key_exists('HTTP_HOST', $mod->request->server)) {
            $domain = $mod->request->server['HTTP_HOST'];
        }
    }
    if ($domain) {
        if (mb_strpos($from, '@') === false) {
            $from = $from.'@'.$domain;
        }
        if (!trim($reply_to)) {
            $reply_to = $from;
        }
        elseif (mb_strpos($reply_to, '@') === false) {
            $reply_to = $reply_to.'@'.$domain;
        }
    }
    return array($from, $reply_to);
}}

/**
 * @subpackage smtp/functions
 */
if (!hm_exists('repopulate_compose_form')) {
function repopulate_compose_form($draft, $handler_mod) {
    $handler_mod->out('no_redirect', true);
    $handler_mod->out('compose_draft', $draft);
    if (array_key_exists('compose_msg_path', $handler_mod->request->post)
        && $handler_mod->request->post['compose_msg_path']) {
        $handler_mod->out('compose_msg_path', $handler_mod->request->post['compose_msg_path']);
    }
    if (array_key_exists('compose_msg_uid', $handler_mod->request->post)
        && $handler_mod->request->post['compose_msg_uid']) {
        $handler_mod->out('compose_msg_uid', $handler_mod->request->post['compose_msg_uid']);
    }
}}

/**
 * @subpackage smtp/functions
 */
if (!hm_exists('server_from_compose_smtp_id')) {
function server_from_compose_smtp_id($id) {
    $pos = mb_strpos($id, '.');
    if ($pos === false) {
        return $id;
    }
    return mb_substr($id, 0, $pos);
}}

/**
 * @subpackage smtp/functions
 */
if (!hm_exists('profile_from_compose_smtp_id')) {
function profile_from_compose_smtp_id($profiles, $id) {
    if (mb_strpos($id, '.') === false) {
        return false;
    }
    $smtp_id = server_from_compose_smtp_id($id);
    $profiles = profiles_by_smtp_id($profiles, $smtp_id);
    foreach ($profiles as $index => $profile) {
        if ((string) $id === sprintf('%s.%s', $smtp_id, ($index+1))) {
            return $profile;
        }
    }
    return false;
}}

/**
 * @subpackage smtp/functions
 */
if (!hm_exists('parse_mailto')) {
function parse_mailto($str) {
    $res = array(
        'draft_to' => '',
        'draft_cc' => '',
        'draft_bcc' => '',
        'draft_subject' => '',
        'draft_body' => ''
    );
    $mailto = parse_url(urldecode($str));
    if (!is_array($mailto) || !array_key_exists('path', $mailto) || !$mailto['path']) {
        return $res;
    }
    $res['draft_to'] = $mailto['path'];
    if (!array_key_exists('query', $mailto) || !$mailto['query']) {
        return $res;
    }
    parse_str($mailto['query'], $args);
    foreach ($args as $name => $val) {
        $res['draft_'.$name] = $val;
    }
    return $res;
}}

/**
 * @subpackage smtp/functions
 */
if (!hm_exists('default_smtp_server')) {
function default_smtp_server($user_config, $session, $request, $config, $user, $pass) {
    $smtp_server = $config->get('default_smtp_server', false);
    if (!$smtp_server) {
        return;
    }
    $smtp_port = $config->get('default_smtp_port', 465);
    $smtp_tls = $config->get('default_smtp_tls', true);
    Hm_SMTP_List::init($user_config, $session);
    $attributes = array(
        'name' => $config->get('default_smtp_name', 'Default'),
        'default' => true,
        'type' => 'smtp',
        'server' => $smtp_server,
        'port' => $smtp_port,
        'tls' => $smtp_tls,
        'user' => $user,
        'pass' => $pass
    );
    if ($config->get('default_smtp_no_auth', false)) {
        $attributes['no_auth'] = true;
    }
    Hm_SMTP_List::add($attributes);
    Hm_Debug::add('Default SMTP server added', 'info');
}}

/**
 * @subpackage smtp/functions
 */
if (!hm_exists('recip_count_check')) {
function recip_count_check($headers, $omod) {
    $headers = lc_headers($headers);
    $recip_count = 0;

    if (array_key_exists('to', $headers) && $headers['to']) {
        $recip_count += count(process_address_fld($headers['to']));
    }

    if (array_key_exists('cc', $headers) && $headers['cc']) {
        $recip_count += count(process_address_fld($headers['cc']));
    }
    if ($recip_count > MAX_RECIPIENT_WARNING) {
        Hm_Msgs::add('Message contains more than the maximum number of recipients, proceed with caution', 'warning');
    }
}}
