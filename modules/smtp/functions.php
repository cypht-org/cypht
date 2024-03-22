<?php


if (!defined('DEBUG_MODE')) { die(); }

if (!hm_exists('connect_to_smtp_server')) {
    function connect_to_smtp_server($address, $name, $port, $user, $pass, $tls, $context) {
        $smtp_list = array(
            'name' => $name,
            'server' => $address,
            'hide' => false,
            'port' => $port,
            'user' => $user,
            'pass' => $pass,
            'tls' => $tls);

        $smtp_server_id =  Hm_SMTP_List::add($smtp_list);
        $server = Hm_SMTP_List::get($smtp_server_id, false);

        $result = Hm_SMTP_List::service_connect($smtp_server_id, $server, $user, $pass, false);

        return $result;
    }
}

if (!hm_exists('delete_smtp_server')) {
    function delete_smtp_server($smtp_server_id) {
       Hm_SMTP_List::del($smtp_server_id);
    }
}