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
add_output('profiles', 'profile_edit_form', true, 'profiles', 'version_upgrade_checker', 'after');
add_output('profiles', 'profile_content', true, 'profiles', 'profile_edit_form', 'after');

add_handler('folders', 'load_default_server_from_profiles', true, 'profiles', 'folders_server_id', 'before');

add_output('ajax_hm_folders', 'profile_page_link', true, 'profiles', 'settings_menu_end', 'before');
add_output('compose', 'compose_signature_button', true, 'profiles', 'compose_form_end', 'before');
add_output('compose', 'compose_signature_values', true, 'profiles', 'compose_form_start', 'before');
add_handler('compose', 'compose_profile_data', true, 'profiles', 'load_smtp_servers_from_config', 'after');

add_handler('ajax_smtp_save_draft', 'compose_profile_data', true, 'profiles', 'load_smtp_servers_from_config', 'after');
add_handler('ajax_smtp_attach_file', 'compose_profile_data', true, 'profiles', 'load_smtp_servers_from_config', 'after');
add_handler('servers', 'compose_profile_data', true, 'profiles', 'load_smtp_servers_from_config', 'after');
add_handler('ajax_smtp_debug', 'process_smtp_server_data_delete', true, 'profiles','smtp_delete', 'after');
add_handler('ajax_imap_debug', 'process_imap_server_data_delete', true, 'profiles','imap_delete', 'after');

return array(
    'allowed_pages' => array(
        'profiles'
    ),
    'allowed_post' => array(
        'profile_name' => FILTER_UNSAFE_RAW,
        'profile_id' => FILTER_UNSAFE_RAW,
        'profile_replyto' => FILTER_UNSAFE_RAW,
        'profile_smtp' => FILTER_UNSAFE_RAW,
        'profile_imap' => FILTER_UNSAFE_RAW,
        'profile_default' => FILTER_VALIDATE_INT,
        'profile_address' => FILTER_UNSAFE_RAW,
        'profile_sig' => FILTER_UNSAFE_RAW,
        'profile_rmk' => FILTER_UNSAFE_RAW,
        'profile_delete' => FILTER_UNSAFE_RAW,
        'profile_quickly_create_value' => FILTER_UNSAFE_RAW
    ),
    'allowed_get' => array(
        'profile_id' => FILTER_UNSAFE_RAW,
    ),
);
