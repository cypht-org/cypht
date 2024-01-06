<?php

if (!defined('DEBUG_MODE')) { die(); }

if (!hm_exists('add_profile')) {
    function add_profile($name, $signature, $reply_to, $is_default, $email, $server_mail, $smtp_server_id, $imap_server_id, $context) {
        $profile = array(
            'name' => $name,
            'sig' => $signature,
            'smtp_id' => $smtp_server_id,
            'replyto' => $reply_to,
            'default' => $is_default,
            'address' => $email,
            'server' =>  $server_mail,
            'user' => $email,
            'type' => 'imap'
        );
       
         $profiles = new Hm_Profiles($context);
         $profiles->add($profile);
         $context->session->record_unsaved('Profile added');

         $profiles->save($context->user_config);
         $user_data = $context->user_config->dump();
         $context->session->set('user_data', $user_data);
    }
}