<?php

if (!defined('DEBUG_MODE')) { die(); }

handler_source('local_contacts');
output_source('local_contacts');

add_handler('contacts', 'load_local_contacts', true, 'local_contacts', 'load_contacts', 'after');
add_handler('contacts', 'load_edit_contact', true, 'local_contacts', 'load_local_contacts', 'after');
// add_handler('contacts', 'process_add_contact', true, 'local_contacts', 'load_contacts', 'after');
add_handler('contacts', 'process_import_contact', true, 'local_contacts', 'load_edit_contact', 'after');
// add_handler('contacts', 'process_edit_contact', true, 'local_contacts', 'load_local_contacts', 'after');
add_output('contacts', 'import_contacts_form', true, 'contacts', 'contacts_content_start', 'after');
add_output('contacts', 'contacts_form', true, 'contacts', 'contacts_content_start', 'after');

add_handler('ajax_autocomplete_contact', 'load_local_contacts', true, 'local_contacts', 'load_contacts', 'after');
add_handler('ajax_imap_message_content', 'load_local_contacts', true, 'local_contacts', 'load_contacts', 'after');
add_handler('compose', 'load_local_contacts', true, 'local_contacts', 'load_contacts', 'after');
add_handler('ajax_add_contact', 'load_local_contacts', true, 'local_contacts', 'load_contacts', 'after');
add_handler('ajax_add_contact', 'process_add_contact_from_message', true, 'local_contacts', 'save_user_data', 'before');
add_handler('ajax_add_contact', 'process_add_contact', true, 'local_contacts', 'load_contacts', 'after');
add_handler('ajax_update_contact', 'process_edit_contact', true, 'local_contacts', 'load_contacts', 'after');

add_handler('ajax_delete_contact', 'load_local_contacts', true, 'local_contacts', 'load_contacts', 'after');
add_handler('ajax_delete_contact', 'process_delete_contact', true, 'local_contacts', 'save_user_data', 'before');


return array(
    'allowed_pages' => array(
        'contacts'
    ),
    'allowed_post' => array(
        'contact_email' => FILTER_SANITIZE_EMAIL,
        'contact_name' => FILTER_UNSAFE_RAW,
        'contact_phone' => FILTER_UNSAFE_RAW,
        'contact_group' => FILTER_UNSAFE_RAW,
        'contact_id' => FILTER_UNSAFE_RAW,
        'add_contact' => FILTER_UNSAFE_RAW,
        'edit_contact' => FILTER_UNSAFE_RAW,
        'import_contact' => FILTER_UNSAFE_RAW,
        'contact_source' => FILTER_UNSAFE_RAW,
    ),
    'allowed_get' => array(
        'contact_id' => FILTER_SANITIZE_FULL_SPECIAL_CHARS,
        'contact_type' => FILTER_SANITIZE_FULL_SPECIAL_CHARS,
        'contact_source' => FILTER_SANITIZE_FULL_SPECIAL_CHARS,
    ),
    'allowed_output' => array(
        'current_contact' => array(FILTER_UNSAFE_RAW, FILTER_REQUIRE_ARRAY),
        'contact_added' => FILTER_VALIDATE_INT,
        'contact_updated' => FILTER_VALIDATE_INT,
    ),
);
