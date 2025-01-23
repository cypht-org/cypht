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
            Hm_Msgs::add('ERRAuthentication failed');
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
function send_scheduled_message($handler, $imap, $folder, $msg_id, $server_id, $send_now = false) {    
    $msg_headers = $imap->get_message_headers($folder, $msg_id);    
    $imap_details = $imap->get_config();       

    try {
        if (empty($msg_headers['X-Schedule'])) {
            return false;
        }

        if (new DateTime($msg_headers['X-Schedule']) <= new DateTime() || $send_now) {
            $profile = Hm_Profiles::get($msg_headers['X-Profile-ID']);
            if (!$profile) {
                $profiles = Hm_Profiles::search('server', $imap_details['server']);

                if (!$profiles) {
                    Hm_Debug::add(sprintf('ERRCannot find profiles corresponding with IMAP server: %s', $imap_details['server']));
                    return false;
                }
                $profile = $profiles[0];
            }

            $smtp = Hm_SMTP_List::connect($profile['smtp_id'], false);

            if ($smtp) {
                $delivery_receipt = isset($msg_headers['X-Delivery']);

                $recipients = [];
                foreach (['To', 'Cc', 'Bcc'] as $fld) {
                    if (array_key_exists($fld, $msg_headers)) {
                        $recipients = array_merge($recipients, Hm_MIME_Msg::find_addresses($msg_headers[$fld]));
                    }
                }

                $msg_content = $imap->get_message_content($folder, $msg_id, 0);
                $from = process_address_fld($msg_headers['From']);

                $err_msg = $smtp->send_message($from[0]['email'], $recipients, $msg_content, $delivery_receipt);

                if (!$err_msg) {
                    $imap->delete_message($folder, $msg_id, false);
                    save_sent_msg($handler, $server_id, $imap, $imap_details, $msg_content, $msg_id, false);
                    return true; 
                }
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
function reschedule_message_sending($handler, $imap, $msg_id, $folder, $new_date, $server_id) {
    if ($new_date == 'now') {
        return send_scheduled_message($handler, $imap, $folder, $msg_id, $server_id, true);
    }
    $msg = $imap->get_message_content($folder, $msg_id, 0);
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
    if (!count($imap->get_folder_status($schedule_folder))) {
        return;
    }
    $res = false;
    if ($imap->store_message($schedule_folder, $msg)) {
        if ($imap->delete_message($folder, $msg_id, false)) {
            $res = true;
        }
    }
    return $res; 
}}
