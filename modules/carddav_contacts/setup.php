<?php

if (!defined('DEBUG_MODE')) { die(); }

handler_source('carddav_contacts');
output_source('carddav_contacts');

add_handler('contacts', 'load_carddav_contacts', true, 'carddav_contacts', 'load_contacts', 'after');
add_handler('ajax_autocomplete_contact', 'load_carddav_contacts', true, 'carddav_contacts', 'load_contacts', 'after');
add_handler('ajax_imap_message_content', 'load_carddav_contacts', true, 'carddav_contacts', 'load_contacts', 'after');
add_handler('compose', 'load_carddav_contacts', true, 'carddav_contacts', 'load_contacts', 'after');
add_handler('ajax_delete_contact', 'load_carddav_contacts', true, 'carddav_contacts', 'load_contacts', 'after');
add_handler('ajax_add_contact', 'load_carddav_contacts', true, 'carddav_contacts', 'load_contacts', 'after');

add_handler('settings', 'load_carddav_settings', true, 'carddav_contacts', 'load_user_data', 'after');
add_handler('settings', 'process_carddav_auth_settings', true, 'carddav_contacts', 'save_user_settings', 'before');
add_output('settings', 'carddav_auth_settings', true, 'carddav_contacts', 'end_settings_form', 'before');

return array(
    'allowed_post' => array(
        'carddav_usernames' => array('filter' => FILTER_SANITIZE_STRING, 'flags'  => FILTER_FORCE_ARRAY),
        'carddav_passwords' => array('filter' => FILTER_UNSAFE_RAW, 'flags'  => FILTER_FORCE_ARRAY)
    )
);
