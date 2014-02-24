<?php

$is_valid_page = function ($page) {

    $valid_pages = array(
        'home',
        'notfound'
    );
    if (in_array($page, $valid_pages)) {
        return $page;
    }
    return false;
};

return array(
    'allowed_cookie' => array(
        'PHPSESSID' => FILTER_SANITIZE_STRING
    ),
    'allowed_server' => array(
        'REQUEST_URI' => FILTER_SANITIZE_STRING,
        'SERVER_ADDR' => FILTER_VALIDATE_IP,
        'SERVER_PORT' => FILTER_VALIDATE_INT,
        'PHP_SELF' => FILTER_SANITIZE_STRING,
        'HTTP_USER_AGENT' => FILTER_SANITIZE_STRING,
        'SERVER_NAME' => FILTER_SANITIZE_STRING
    ),

    'allowed_get' => array(
        'page' => array(
            'filter' => FILTER_CALLBACK,
            'options' => $is_valid_page
        ),
        'imap_server_id' => FILTER_VALIDATE_INT,
    ),

    'allowed_post' => array(
        'logout' => FILTER_VALIDATE_BOOLEAN,
        'tls' => FILTER_VALIDATE_BOOLEAN,
        'server_port' => FILTER_VALIDATE_INT,
        'server' => FILTER_SANITIZE_STRING,
        'username' => FILTER_SANITIZE_STRING,
        'password' => FILTER_SANITIZE_STRING,
        'new_imap_server' => FILTER_SANITIZE_STRING,
        'new_imap_port' => FILTER_VALIDATE_INT,
        'imap_server_id' => FILTER_VALIDATE_INT,
        'imap_user' => FILTER_SANITIZE_STRING,
        'imap_pass' => FILTER_SANITIZE_STRING,
        'imap_delete' => FILTER_SANITIZE_STRING,
        'submit_server' => FILTER_SANITIZE_STRING,
        'imap_connect' => FILTER_SANITIZE_STRING,
        'hm_ajax_hook' => FILTER_SANITIZE_STRING,
        'imap_remember' => FILTER_VALIDATE_INT,
    )
);

?>
