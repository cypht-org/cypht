<?php


if (!defined('DEBUG_MODE')) { die(); }

if (!hm_exists('connect_to_smtp_serve')) {
    function connect_to_smtp_server($address, $name, $port, $user, $pass, $tls, $context, $errno = null, $errstr = null) {
        if ($con = @fsockopen($address, $port, $errno, $errstr, 2)) {
              Hm_SMTP_List::add( array(
                  'name' => $name,
                  'server' => $address,
                  'port' => $port,
                  'user' => $user,
                  'pass' => $pass,
                  'tls' => $tls));

              $smtp_servers = Hm_SMTP_List::dump(false, true);
              $ids = array_keys($smtp_servers);
              $smtp_server_id = array_pop($ids);
              
              return $smtp_server_id;
        }
        
        return null;
    }
}

if (!hm_exists('authenticate_to_smtp_server')) {
    function authenticate_to_smtp_server($user, $pass, $smtp_server_id, $context) {
        if (in_server_list('Hm_SMTP_List', $smtp_server_id, $user)) {
          Hm_Msgs::add('ERRThis SMTP server and username are already configured');
          return false;
       }

       $smtp = Hm_SMTP_List::connect($smtp_server_id, false, $user, $pass, true);
       if (is_object($smtp) && $smtp->state == 'authed') {
           $smtp_servers = Hm_SMTP_List::dump(false, true);
           $context->user_config->set('smtp_servers', $smtp_servers);
           $context->just_saved_credentials = true;
           $context->session->record_unsaved('SMTP server saved');
           return true;
       }
       else {
           Hm_Msgs::add("ERRUnable to save this server, are the username and password correct?");
           Hm_SMTP_List::forget_credentials($smtp_server_id);
           return false;
       }
    }
}

if (!hm_exists('delete_smtp_server')) {
    function delete_smtp_server($smtp_server_id, $context) {
       $res = Hm_SMTP_List::del($smtp_server_id);
       if ($res) {
           Hm_SMTP_List::forget_credentials($smtp_server_id);
           $smtp_servers = Hm_SMTP_List::dump(false, true);
           $context->user_config->set('smtp_servers', $smtp_servers);
       }
    }
}