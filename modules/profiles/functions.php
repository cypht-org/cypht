<?php

if (!defined('DEBUG_MODE')) { die(); }

if (!hm_exists('add_profile')) {
    function add_profile($name, $signature, $reply_to, $is_default, $email, $imap_server_id, $smtp_server_id) {
        $profile = array(
            'name' => $name,
            'sig' => $signature,
            'smtp_id' => $smtp_server_id,
            'replyto' => $reply_to,
            'default' => $is_default,
            'address' => $email,
            'server' =>  $imap_server_id,
            'user' => $email,
            'type' => 'imap'
        );

        Hm_Profiles::add($profile);
    }
}
