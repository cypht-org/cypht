<?php

if (!defined('DEBUG_MODE')) { die(); }

handler_source('contacts');
output_source('contacts');


/* contacts page */
setup_base_page('contacts', 'core');

add_handler('contacts', 'load_contacts', true, 'contacts', 'load_user_data', 'after');
add_handler('contacts', 'process_add_contact', true, 'contacts', 'load_contacts', 'after');
add_handler('contacts', 'process_edit_contact', true, 'contacts', 'load_contacts', 'after');
add_output('contacts', 'contacts_content_start', true, 'contacts', 'content_section_start', 'after');
add_output('contacts', 'contacts_content_add_form', true, 'contacts', 'contacts_content_start', 'after');
add_output('contacts', 'contacts_list', true, 'contacts', 'contacts_content_add_form', 'after');
add_output('contacts', 'contacts_content_end', true, 'contacts', 'contacts_content_add_form', 'after');

add_output('ajax_hm_folders', 'contacts_page_link', true, 'contacts', 'settings_menu_end', 'before');

add_handler('compose', 'process_send_to_contact', true, 'contacts', 'save_user_data', 'before');

add_handler('ajax_imap_message_content', 'find_message_contacts', true, 'contacts', 'imap_message_content', 'after');
add_output('ajax_imap_message_content', 'add_message_contacts', true, 'contacts', 'filter_message_headers', 'after');

add_handler('ajax_add_contact', 'login', false, 'core');
add_handler('ajax_add_contact', 'load_user_data', true, 'core');
add_handler('ajax_add_contact', 'load_contacts', true);
add_handler('ajax_add_contact', 'process_add_contact_from_message', true);
add_handler('ajax_add_contact', 'language', true, 'core');
add_handler('ajax_add_contact', 'save_user_data',  true, 'core');
add_handler('ajax_add_contact', 'date', true, 'core');
add_handler('ajax_add_contact', 'http_headers', true, 'core');

add_handler('ajax_delete_contact', 'login', false, 'core');
add_handler('ajax_delete_contact', 'load_user_data', true, 'core');
add_handler('ajax_delete_contact', 'load_contacts', true);
add_handler('ajax_delete_contact', 'process_delete_contact', true);
add_handler('ajax_delete_contact', 'language', true, 'core');
add_handler('ajax_delete_contact', 'save_user_data',  true, 'core');
add_handler('ajax_delete_contact', 'date', true, 'core');
add_handler('ajax_delete_contact', 'http_headers', true, 'core');

return array(
    'allowed_pages' => array(
        'contacts',
        'ajax_add_contact',
        'ajax_delete_contact'
    ),
    'allowed_post' => array(
        'contact_email' => FILTER_SANITIZE_STRING,
        'contact_name' => FILTER_SANITIZE_STRING,
        'contact_phone' => FILTER_SANITIZE_STRING,
        'contact_id' => FILTER_VALIDATE_INT,
        'contact_value' => FILTER_SANITIZE_STRING,
        'edit_contact' => FILTER_SANITIZE_STRING,
        'add_contact' => FILTER_SANITIZE_STRING
    ),
    'allowed_get' => array(
        'contact_id' => FILTER_VALIDATE_INT
    )
);


