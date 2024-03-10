<?php


if (!defined('DEBUG_MODE')) { die(); }

if (!hm_exists('add_and_connect_to_smtp_server')) {
    function connect_to_smtp_server($address, $name, $port, $user, $pass, $tls, $context) {
        Hm_SMTP_List::init($context->user_config, $context->session);
        $smtp_list = array(
            'name' => $name,
            'server' => $address,
            'hide' => false,
            'port' => $port,
            'user' => $user,
            'pass' => $pass,
            'tls' => $tls);

        Hm_SMTP_List::add($smtp_list);
        $servers = Hm_SMTP_List::dump(false, true);
        $ids = array_keys($servers);
        $smtp_server_id = array_pop($ids);
        $server = Hm_SMTP_List::get($smtp_server_id, false);

        $result = Hm_SMTP_List::service_connect($smtp_server_id, $server, $user, $pass, false);

        $smtp_servers = Hm_SMTP_List::dump(false, true);
        $context->user_config->set('smtp_servers', $smtp_servers);

        return $result;
    }
}

if (!hm_exists('delete_smtp_server')) {
    function delete_smtp_server($smtp_server_id) {
       Hm_SMTP_List::del($smtp_server_id);
    }
}