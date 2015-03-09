<?php

/**
 * NUX module services
 * @package modules
 * @subpackage nux
 */

if (!defined('DEBUG_MODE')) { die(); }

/**
 * @subpackage nux/services
 */

Nux_Quick_Services::add('gmail', array(
    'server' => 'imap.gmail.com',
    'type' => 'imap',
    'tls' => true,
    'port' => 993,
    'name' => 'Gmail',
    'auth' => 'oauth2',
    'scope' => ' https://mail.google.com/',
    'smtp' => array(
        'server' => 'smtp.gmail.com',
        'port' => 465,
        'tls' => true
    )
));

Nux_Quick_Services::add('outlook', array(
    'server' => 'imap.live.com',
    'type' => 'imap',
    'tls' => true,
    'port' => 993,
    'name' => 'Outlook.com',
    'auth' => 'oauth2',
    'scope' => 'wl.imap',
));

Nux_Quick_Services::add('yahoo', array(
    'server' => 'imap.mail.yahoo.com',
    'type' => 'imap',
    'tls' => true,
    'port' => 993,
    'name' => 'Yahoo',
    'auth' => 'login',
    'smtp' => array(
        'server' => 'smtp.mail.yahoo.com',
        'port' => 587,
        'tls' => true
    )
));

Nux_Quick_Services::add('mailcom', array(
    'server' => 'imap.mail.com',
    'type' => 'imap',
    'tls' => true,
    'port' => 993,
    'name' => 'Mail.com',
    'auth' => 'login',
    'smtp' => array(
        'server' => 'smtp.mail.com',
        'port' => 587,
        'tls' => true
    )
));

Nux_Quick_Services::add('aol', array(
    'server' => 'imap.aol.com',
    'type' => 'imap',
    'tls' => true,
    'port' => 993,
    'name' => 'AOL',
    'auth' => 'login',
    'smtp' => array(
        'server' => 'smtp.aol.com',
        'port' => 587,
        'tls' => true
    )
));

Nux_Quick_Services::add('gmx', array(
    'server' => 'imap.gmx.com',
    'type' => 'imap',
    'tls' => true,
    'port' => 143,
    'name' => 'GMX',
    'auth' => 'login',
    'smtp' => array(
        'server' => 'smtp.gmx.com',
        'port' => 465,
        'tls' => true
    )
));

Nux_Quick_Services::add('zoho', array(
    'server' => 'imap.zoho.com',
    'type' => 'imap',
    'tls' => true,
    'port' => 993,
    'name' => 'Zoho',
    'auth' => 'login',
    'smtp' => array(
        'server' => 'smtp.zoho.com',
        'port' => 465,
        'tls' => true
    )
));

Nux_Quick_Services::add('fastmail', array(
    'server' => 'mail.messagingengine.com',
    'type' => 'imap',
    'tls' => true,
    'port' => 993,
    'name' => 'Fastmail',
    'auth' => 'login',
    'smtp' => array(
        'server' => 'mail.messagingengine.com',
        'port' => 465,
        'tls' => true
    )
));

Nux_Quick_Services::add('yandex', array(
    'server' => 'imap.yandex.com',
    'type' => 'imap',
    'tls' => true,
    'port' => 993,
    'name' => 'Yandex',
    'auth' => 'login',
    'smtp' => array(
        'server' => 'smtp.yandex.com',
        'port' => 465,
        'tls' => true
    )
));

Nux_Quick_Services::add('inbox', array(
    'server' => 'imap.inbox.com',
    'type' => 'imap',
    'tls' => true,
    'port' => 993,
    'name' => 'Inbox.com',
    'auth' => 'login',
    'smtp' => array(
        'server' => 'my.inbox.com',
        'port' => 465,
        'tls' => true
    )
));

?>
