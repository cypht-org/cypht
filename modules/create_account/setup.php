<?php

if (!defined('DEBUG_MODE')) { die(); }

handler_source('create_account');
output_source('create_account');


setup_base_page('create_account', 'core');
replace_module('output', 'login', 'no_login', 'create_account');
add_handler('create_account', 'process_create_account', false, 'create_account', 'login', 'before');
add_output('create_account', 'create_form', false, 'create_account', 'content_section_start', 'after');
add_output('home', 'create_account_link', false, 'create_account', 'login', 'after');

/* input/output */
return array(
    'allowed_pages' => array(
        'create_account'
    ),
    'allowed_post' => array(
        'create_username' => FILTER_SANITIZE_STRING,
        'create_password' => FILTER_SANITIZE_STRING,
        'create_password_again' => FILTER_SANITIZE_STRING
    )
);

?>
