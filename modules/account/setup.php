<?php

if (!defined('DEBUG_MODE')) { die(); }

handler_source('account');
output_source('account');

/* create account page */
setup_base_page('create_account', 'core');
add_handler('create_account', 'process_create_account', true, 'account', 'login', 'after');
add_handler('create_account', 'check_internal_users', true, 'account', 'login', 'after');
add_output('create_account', 'create_form', true, 'account', 'content_section_start', 'after');

setup_base_page('change_password', 'core');
add_handler('change_password', 'process_change_password', true, 'account', 'load_user_data', 'after');
add_handler('change_password', 'check_internal_users', true, 'account', 'login', 'after');
add_output('change_password', 'change_password', true, 'account', 'content_section_start', 'after');

/* folder list link */
add_handler('ajax_hm_folders', 'check_internal_users', true, 'account', 'login', 'after');
add_output('ajax_hm_folders', 'create_account_link', true, 'account', 'settings_menu_end', 'before');
add_output('ajax_hm_folders', 'change_password_link', true, 'account', 'settings_save_link', 'after');

/* input/output */
return array(
    'allowed_pages' => array(
        'create_account',
        'change_password'
    ),
    'allowed_post' => array(
        'create_username' => FILTER_SANITIZE_STRING,
        'create_password' => FILTER_SANITIZE_STRING,
        'create_password_again' => FILTER_SANITIZE_STRING,
        'new_pass1' => FILTER_SANITIZE_STRING,
        'new_pass2' => FILTER_SANITIZE_STRING,
        'old_pass' => FILTER_SANITIZE_STRING,
        'change_password' => FILTER_SANITIZE_STRING,
    )
);


