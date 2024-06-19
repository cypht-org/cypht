<?php

if (!defined('DEBUG_MODE')) { die(); }

if (!hm_exists('add_profile')) {
    function add_profile($name, $signature, $reply_to, $is_default, $email, $server, $user, $smtp_server_id, $imap_server_id, $context, $remark = '') {
        $profile = array(
            'name' => $name,
            'sig' => $signature,
            'rmk' => $remark,
            'smtp_id' => $smtp_server_id,
            'replyto' => $reply_to,
            'default' => $is_default,
            'address' => $email,
            'server' =>  $imap_server_id,
            'user' => $email,
            'type' => 'imap'
        );
        $id = Hm_Profiles::add($profile);
        if ($is_default) {
            Hm_Profiles::setDefault($id);
        }
    }
}
