<?php

if (!defined('DEBUG_MODE')) { die(); }

handler_source('carddav_contacts');
output_source('carddav_contacts');

add_handler('contacts', 'load_carddav_contacts', true, 'carddav_contacts', 'load_contacts', 'after');
add_handler('contacts', 'load_edit_carddav_contact', true, 'carddav_contacts', 'load_carddav_contacts', 'after');
add_handler('contacts', 'process_edit_carddav_contact', true, 'carddav_contacts', 'load_edit_carddav_contact', 'after');
add_handler('contacts', 'process_add_carddav_contact', true, 'carddav_contacts', 'load_edit_carddav_contact', 'after');
add_handler('ajax_autocomplete_contact', 'load_carddav_contacts', true, 'carddav_contacts', 'load_contacts', 'after');
add_handler('ajax_imap_message_content', 'load_carddav_contacts', true, 'carddav_contacts', 'load_contacts', 'after');
add_handler('compose', 'load_carddav_contacts', true, 'carddav_contacts', 'load_contacts', 'after');
add_handler('ajax_delete_contact', 'load_carddav_contacts', true, 'carddav_contacts', 'load_contacts', 'after');
add_handler('ajax_delete_contact', 'process_delete_carddav_contact', true, 'carddav_contacts', 'load_carddav_contacts', 'after');
add_handler('ajax_add_contact', 'load_carddav_contacts', true, 'carddav_contacts', 'load_contacts', 'after');
add_handler('ajax_add_contact', 'process_add_carddav_contact_from_msg', true, 'local_contacts', 'save_user_data', 'before');
add_output('contacts', 'carddav_contacts_form', true, 'carddav_contacts', 'contacts_content_start', 'after');

add_handler('settings', 'load_carddav_settings', true, 'carddav_contacts', 'load_user_data', 'after');
add_handler('settings', 'process_carddav_auth_settings', true, 'carddav_contacts', 'save_user_settings', 'before');
add_output('settings', 'carddav_auth_settings', true, 'carddav_contacts', 'end_settings_form', 'before');

return array(
    'allowed_post' => array(
        'carddav_usernames' => array('filter' => FILTER_SANITIZE_STRING, 'flags'  => FILTER_FORCE_ARRAY),
        'carddav_passwords' => array('filter' => FILTER_UNSAFE_RAW, 'flags'  => FILTER_FORCE_ARRAY),
        'carddav_email' => FILTER_SANITIZE_STRING,
        'carddav_fn' => FILTER_SANITIZE_STRING,
        'carddav_phone' => FILTER_SANITIZE_STRING,
        'carddav_phone_id' => FILTER_SANITIZE_STRING,
        'carddav_fn_id' => FILTER_SANITIZE_STRING,
        'carddav_email_id' => FILTER_SANITIZE_STRING
    )
);
