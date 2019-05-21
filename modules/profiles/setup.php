<?php

if (!defined('DEBUG_MODE')) { die(); }

handler_source('profiles');
output_source('profiles');


/* profiles page */
setup_base_page('profiles', 'core');
add_handler('profiles', 'profile_data', true, 'profiles', 'load_user_data', 'after');
add_handler('profiles', 'profile_edit_data', true, 'profiles', 'profile_data', 'after');
add_handler('profiles', 'process_profile_delete', true, 'profiles', 'profile_data', 'after');
add_handler('profiles', 'process_profile_update', true, 'profiles', 'process_profile_delete', 'after');
add_output('profiles', 'profile_edit_form', true, 'profiles', 'content_section_start', 'after');
add_output('profiles', 'profile_content', true, 'profiles', 'profile_edit_form', 'after');

add_output('ajax_hm_folders', 'profile_page_link', true, 'profiles', 'settings_menu_end', 'before');
add_output('compose', 'compose_signature_button', true, 'profiles', 'compose_form_end', 'before');
add_output('compose', 'compose_signature_values', true, 'profiles', 'compose_form_start', 'before');
add_handler('compose', 'compose_profile_data', true, 'profiles', 'load_smtp_servers_from_config', 'after');

return array(
    'allowed_pages' => array(
        'profiles'
    ),
    'allowed_post' => array(
        'profile_name' => FILTER_SANITIZE_STRING,
        'profile_id' => FILTER_VALIDATE_INT,
        'profile_replyto' => FILTER_SANITIZE_STRING,
        'profile_smtp' => FILTER_VALIDATE_INT,
        'profile_imap' => FILTER_SANITIZE_STRING,
        'profile_default' => FILTER_VALIDATE_INT,
        'profile_address' => FILTER_SANITIZE_STRING,
        'profile_sig' => FILTER_UNSAFE_RAW,
        'profile_delete' => FILTER_SANITIZE_STRING
    ),
    'allowed_get' => array(
        'profile_id' => FILTER_VALIDATE_INT,
    ),
);
