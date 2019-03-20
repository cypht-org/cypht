<?php

if (!defined('DEBUG_MODE')) { die(); }

handler_source('contacts');
output_source('contacts');


/* contacts page */
setup_base_page('contacts', 'core');

add_handler('contacts', 'load_contacts', true, 'contacts', 'load_user_data', 'after');
add_output('contacts', 'contacts_content_start', true, 'contacts', 'content_section_start', 'after');
add_output('contacts', 'contacts_list', true, 'contacts', 'contacts_content_start', 'after');
add_output('contacts', 'contacts_content_end', true, 'contacts', 'contacts_list', 'after');

add_output('ajax_hm_folders', 'contacts_page_link', true, 'contacts', 'logout_menu_item', 'before');

add_handler('compose', 'load_contacts', true, 'contacts', 'load_user_data', 'after');
add_handler('compose', 'process_send_to_contact', true, 'contacts', 'save_user_data', 'before');

add_handler('ajax_imap_message_content', 'load_contacts', true, 'contacts', 'load_user_data', 'after');
add_handler('ajax_imap_message_content', 'find_message_contacts', true, 'contacts', 'imap_message_content', 'after');
add_output('ajax_imap_message_content', 'add_message_contacts', true, 'contacts', 'filter_message_headers', 'after');

setup_base_ajax_page('ajax_add_contact', 'core');
add_handler('ajax_add_contact', 'load_contacts', true, 'contacts', 'load_user_data', 'after');
add_handler('ajax_add_contact', 'save_user_data', true, 'core', 'language', 'after');


setup_base_ajax_page('ajax_autocomplete_contact', 'core');
add_handler('ajax_autocomplete_contact', 'load_contacts', true, 'contacts', 'load_user_data', 'after');
add_handler('ajax_autocomplete_contact', 'autocomplete_contact', true, 'contacts', 'load_contacts', 'after');
add_output('ajax_autocomplete_contact', 'filter_autocomplete_list', true, 'contacts');

setup_base_ajax_page('ajax_delete_contact', 'core');
add_handler('ajax_delete_contact', 'load_contacts', true, 'contacts', 'load_user_data', 'after');
add_handler('ajax_delete_contact', 'save_user_data', true, 'core', 'language', 'after');

return array(
    'allowed_pages' => array(
        'contacts',
        'ajax_add_contact',
        'ajax_delete_contact',
        'ajax_autocomplete_contact'
    ),
    'allowed_post' => array(
        'contact_email' => FILTER_SANITIZE_STRING,
        'contact_name' => FILTER_SANITIZE_STRING,
        'contact_phone' => FILTER_SANITIZE_STRING,
        'contact_id' => FILTER_VALIDATE_INT,
        'contact_value' => FILTER_SANITIZE_STRING,
        'edit_contact' => FILTER_SANITIZE_STRING,
        'add_contact' => FILTER_SANITIZE_STRING,
        'contact_source' => FILTER_SANITIZE_STRING,
        'contact_type' => FILTER_SANITIZE_STRING
    ),
    'allowed_get' => array(
        'contact_id' => FILTER_VALIDATE_INT,
        'contact_page' => FILTER_VALIDATE_INT,
        'contact_type' => FILTER_SANITIZE_STRING,
        'contact_source' => FILTER_SANITIZE_STRING,
    ),
    'allowed_output' => array(
        'contact_deleted' => array(FILTER_VALIDATE_INT, false),
        'contact_suggestions' => array(FILTER_SANITIZE_STRING, FILTER_REQUIRE_ARRAY)
    ),
);


