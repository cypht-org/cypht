<?php


if (!defined('DEBUG_MODE')) { die(); }

if (!hm_exists('connect_to_smtp_server')) {
    function connect_to_smtp_server($address, $name, $port, $user, $pass, $tls, $server_id = false) {
        $smtp_list = array(
            'name' => $name,
            'server' => $address,
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
            if (! can_save_last_added_server('Hm_SMTP_List', $user)) {
                return;
            }
        }

        $smtp = Hm_SMTP_List::connect($smtp_server_id, false);
        if (smtp_authed($smtp)) {
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
