<?php


if (!defined('DEBUG_MODE')) { die(); }

if (!hm_exists('connect_to_smtp_server')) {
    function connect_to_smtp_server($address, $name, $port, $user, $pass, $tls, $type, $server_id = false) {
        $smtp_list = array(
            'name' => $name,
            'server' => $address,
            'type' => $type,
            'hide' => false,
            'port' => $port,
            'user' => $user,
            'tls' => $tls);

        if (!$server_id || ($server_id && $pass)) {
            $smtp_list['pass'] = $pass;
        }

        if ($server_id) {
            if (Hm_SMTP_List::edit($server_id, $smtp_list)) {
                $smtp_server_id = $server_id;
            } else {
                return;
            }
        } else {
            $smtp_server_id =  Hm_SMTP_List::add($smtp_list);
            if ($type != 'ews' && ! can_save_last_added_server('Hm_SMTP_List', $user)) {
                return;
            }
        }

        $mailbox = Hm_SMTP_List::connect($smtp_server_id, false);
        if ($mailbox->authed()) {
            return $smtp_server_id;
        }
        else {
            Hm_SMTP_List::del($smtp_server_id);
            Hm_Msgs::add('Authentication failed', 'danger');
            return null;
        }
    }
}

if (!hm_exists('delete_smtp_server')) {
    function delete_smtp_server($smtp_server_id) {
       Hm_SMTP_List::del($smtp_server_id);
    }
}

if (!hm_exists('get_reply_type')) {
    function get_reply_type($request) {
        if (array_key_exists('reply', $request) && $request['reply']) {
            return 'reply';
        } elseif (array_key_exists('reply_all', $request) && $request['reply_all']) {
            return 'reply_all';
        } elseif (array_key_exists('forward', $request) && $request['forward']) {
            return 'forward';
        }
        return false;
    }
}

/**
 * @subpackage smtp/functions
 */
if (!hm_exists('send_scheduled_message')) {
function send_scheduled_message($handler, $imapMailbox, $folder, $msg_id, $send_now = false) {    
    $msg_headers = $imapMailbox->get_message_headers($folder, $msg_id);    
    $mailbox_details = $imapMailbox->get_config();  
    try {
        if (empty($msg_headers['X-Schedule']) || empty($msg_headers['X-Profile-ID'])) {
            return false;
        }

        if (new DateTime($msg_headers['X-Schedule']) <= new DateTime() || $send_now) {
            $profile = Hm_Profiles::get($msg_headers['X-Profile-ID']);
            if (!$profile) {
                $profiles = Hm_Profiles::search('server', $mailbox_details['server']);

                if (!$profiles) {
                    Hm_Debug::add(sprintf('ERRCannot find profiles corresponding with MAILBOX server: %s', $mailbox_details['server']));
                    return false;
                }
                $profile = $profiles[0];
            }
            $smtpMailbox = Hm_SMTP_List::connect($profile['smtp_id'], false);
            if (! $smtpMailbox || ! $smtpMailbox->authed()) {
                Hm_Msgs::add("ERRFailed to authenticate to the SMTP server");
                return;
            }

            $delivery_receipt = isset($msg_headers['X-Delivery']);

            $recipients = [];
            foreach (['To', 'Cc', 'Bcc'] as $fld) {
                if (array_key_exists($fld, $msg_headers)) {
                    $recipients = array_merge($recipients, Hm_MIME_Msg::find_addresses($msg_headers[$fld]));
                }
            }

            $msg_content = $imapMailbox->get_message_content($folder, $msg_id, 0);
            $from = process_address_fld($msg_headers['From']);

            $err_msg = $smtpMailbox->send_message($from[0]['email'], $recipients, $msg_content, $delivery_receipt);
            if (!$err_msg) {
                $imapMailbox->delete_message($folder, $msg_id, false);
                save_sent_msg($handler, $imapMailbox->get_config()['id'], $imapMailbox, $mailbox_details, $msg_content, $msg_id, false);
                return true; 
            }
        }
    } catch (Exception $e) {
        Hm_Debug::add(sprintf('ERRCannot send message: %s', $msg_headers['subject']));
    }
    return false; 
}}

/**
 * @subpackage smtp/functions
 */
if (!hm_exists('reschedule_message_sending')) {
function reschedule_message_sending($handler, $mailbox, $msg_id, $folder, $new_date) {
    if ($new_date == 'now') {
        return send_scheduled_message($handler, $mailbox, $folder, $msg_id, true);
    }
    $msg = $mailbox->get_message_content($folder, $msg_id, 0);
    $new_date = get_scheduled_date($new_date);
    preg_match("/^X-Schedule:.*(\r?\n[ \t]+.*)*\r?\n?/im", $msg, $matches);

    if (count($matches)) {
        $msg = str_replace($matches[0], "X-Schedule: {$new_date}\n", $msg);
    } else {
        return;
    }
    $msg = str_replace("\r\n", "\n", $msg);
    $msg = str_replace("\n", "\r\n", $msg);
    $msg = rtrim($msg)."\r\n";

    $schedule_folder = 'Scheduled';
    if ($mailbox->folder_exists($schedule_folder)) {
        return;
    }
    $res = false;
    if ($mailbox->store_message($schedule_folder, $msg)) {
        if ($mailbox->delete_message($folder, $msg_id, false)) {
            $res = true;
        }
    }
    return $res; 
}}

if (!hm_exists('send_smtp_message')) {
function send_smtp_message($handler_mod, $smtp_id, $from_address, $recipients, $mime_message, $delivery_receipt = false) {
    $smtp_details = Hm_SMTP_List::dump($smtp_id, true);
    if (!$smtp_details) {
        Hm_Msgs::add('Could not load selected SMTP server details.', 'danger');
        return 'Could not load selected SMTP server details.';
    }

    // Refresh OAuth2 token if needed
    smtp_refresh_oauth2_token_on_send($smtp_details, $handler_mod, $smtp_id);

    $smtp_mailbox = Hm_SMTP_List::connect($smtp_id, false);
    if (!$smtp_mailbox || !$smtp_mailbox->authed()) {
        Hm_Msgs::add("Failed to authenticate to the SMTP server.", "danger");
        return "Failed to authenticate to the SMTP server.";
    }

    $err_msg = $smtp_mailbox->send_message($from_address, $recipients, $mime_message, $delivery_receipt);

    if (!$err_msg) {
        return false; // Indicates success
    } else {
        Hm_Msgs::add('Failed to send email: ' . $err_msg, 'danger');
        return $err_msg;
    }
}}
