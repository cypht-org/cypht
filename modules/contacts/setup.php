<?php

if (!defined('DEBUG_MODE')) { die(); }

handler_source('contacts');
output_source('contacts');


/* contacts page */
setup_base_page('contacts', 'core');

add_handler('contacts', 'load_contacts', true, 'contacts', 'load_user_data', 'after');
add_handler('contacts', 'check_imported_contacts', true, 'contacts', 'load_user_data', 'after');
add_output('contacts', 'contacts_content_start', true, 'contacts', 'version_upgrade_checker', 'after');
add_output('contacts', 'contacts_list', true, 'contacts', 'contacts_content_start', 'after');
add_output('contacts', 'contacts_content_end', true, 'contacts', 'contacts_list', 'after');
add_output('settings', 'contact_auto_collect_setting', true, 'contacts', 'max_google_contacts_number', 'after');

add_output('ajax_hm_folders', 'contacts_page_link', true, 'contacts', 'main_menu_content', 'before');

add_handler('compose', 'load_contacts', true, 'contacts', 'load_user_data', 'after');
add_handler('compose', 'process_send_to_contact', true, 'contacts', 'save_user_data', 'before');
add_handler('compose', 'store_contact_message', true, 'contacts', 'load_contacts', 'after');

add_handler('ajax_imap_folder_display', 'load_contacts', true, 'contacts', 'load_user_data', 'after');

add_handler('ajax_imap_message_content', 'load_contacts', true, 'contacts', 'load_user_data', 'after');
add_handler('ajax_imap_message_content', 'find_message_contacts', true, 'contacts', 'imap_message_content', 'after');
add_output('ajax_imap_message_content', 'add_message_contacts', true, 'contacts', 'filter_message_headers', 'after');
add_handler('ajax_imap_message_content', 'store_contact_allow_images', true, 'contacts', 'imap_message_content', 'after');

setup_base_ajax_page('ajax_add_contact', 'core');
add_handler('ajax_add_contact', 'load_contacts', true, 'contacts', 'load_user_data', 'after');
add_handler('ajax_add_contact', 'save_user_data', true, 'core', 'language', 'after');
add_handler('ajax_add_contact', 'save_contact',  true);


setup_base_ajax_page('ajax_autocomplete_contact', 'core');
add_handler('ajax_autocomplete_contact', 'load_contacts', true, 'contacts', 'load_user_data', 'after');
add_handler('ajax_autocomplete_contact', 'autocomplete_contact', true, 'contacts', 'load_contacts', 'after');
add_output('ajax_autocomplete_contact', 'filter_autocomplete_list', true, 'contacts');

setup_base_ajax_page('ajax_delete_contact', 'core');
add_handler('ajax_delete_contact', 'load_contacts', true, 'contacts', 'load_user_data', 'after');
add_handler('ajax_delete_contact', 'save_user_data', true, 'core', 'language', 'after');

setup_base_page('export_contact', 'core');
add_handler('export_contact', 'load_contacts', true, 'contacts', 'load_user_data', 'after');
add_handler('export_contact', 'process_export_contacts', true, 'contacts', 'load_contacts', 'after');
add_handler('settings', 'process_contact_auto_collect_setting', true, 'contacts', 'date', 'after');

add_output('compose', 'load_contact_mails', true, 'contacts', 'compose_form_end', 'after');

add_handler('settings', 'process_enable_warn_contacts_cc_not_exist_in_list_contact', true, 'contacts', 'save_user_settings', 'before');
add_handler('settings', 'process_enable_collect_address_on_send_setting', true, 'core', 'save_user_settings', 'before');
add_output('settings', 'enable_warn_contacts_cc_not_exist_in_list_contact', true, 'contacts', 'start_general_settings', 'after');
add_output('settings', 'enable_collect_address_on_send_setting', true, 'core', 'enable_warn_contacts_cc_not_exist_in_list_contact', 'after');


return array(
    'allowed_pages' => array(
        'contacts',
        'ajax_add_contact',
        'ajax_delete_contact',
        'export_contact',
        'ajax_autocomplete_contact'
    ),
    'allowed_post' => array(
        'contact_email' => FILTER_UNSAFE_RAW,
        'contact_name' => FILTER_UNSAFE_RAW,
        'contact_phone' => FILTER_UNSAFE_RAW,
        'contact_id' => FILTER_UNSAFE_RAW,
        'contact_value' => FILTER_UNSAFE_RAW,
        'edit_contact' => FILTER_UNSAFE_RAW,
        'add_contact' => FILTER_UNSAFE_RAW,
        'contact_source' => FILTER_UNSAFE_RAW,
        'contact_type' => FILTER_UNSAFE_RAW,
        'import_contact' => FILTER_UNSAFE_RAW,
        'contact_auto_collect' => FILTER_VALIDATE_BOOLEAN,
        'enable_warn_contacts_cc_not_exist_in_list_contact' => FILTER_VALIDATE_INT,
        'enable_collect_address_on_send' => FILTER_VALIDATE_INT,
        'email_address' => FILTER_UNSAFE_RAW,
        'ldap_dn' => FILTER_UNSAFE_RAW,
    ),
    'allowed_get' => array(
        'contact_id' => FILTER_SANITIZE_FULL_SPECIAL_CHARS,
        'contact_page' => FILTER_VALIDATE_INT,
        'contact_type' => FILTER_SANITIZE_FULL_SPECIAL_CHARS,
        'contact_source' => FILTER_SANITIZE_FULL_SPECIAL_CHARS,
        'import_contact' => FILTER_SANITIZE_FULL_SPECIAL_CHARS,
        'dn' => FILTER_UNSAFE_RAW,
    ),
    'allowed_output' => array(
        'contact_deleted' => array(FILTER_VALIDATE_INT, false),
        'imported_contact' => array(FILTER_SANITIZE_FULL_SPECIAL_CHARS, FILTER_REQUIRE_ARRAY),
        'contact_suggestions' => array(FILTER_SANITIZE_FULL_SPECIAL_CHARS, FILTER_REQUIRE_ARRAY),
        'collect_contacts' => array(FILTER_VALIDATE_BOOLEAN, false),
        'imap_allow_images' => array(FILTER_VALIDATE_BOOLEAN, false),
        'collected_contact_email' => array(FILTER_SANITIZE_FULL_SPECIAL_CHARS, false),
        'collected_contact_name' => array(FILTER_SANITIZE_FULL_SPECIAL_CHARS, false),
    ),
);
