<?php

if (!defined('DEBUG_MODE')) { die(); }

handler_source('ldap_contacts');
output_source('ldap_contacts');

add_handler('contacts', 'load_ldap_contacts', true, 'ldap_contacts', 'load_contacts', 'after');
add_handler('contacts', 'load_edit_ldap_contact', true, 'ldap_contacts', 'load_ldap_contacts', 'after');
add_handler('contacts', 'process_add_ldap_contact', true, 'ldap_contacts', 'load_edit_ldap_contact', 'after');
add_handler('contacts', 'process_ldap_fields', true, 'ldap_contacts', 'process_add_ldap_contact', 'after');
add_handler('contacts', 'process_add_to_ldap_server', true, 'ldap_contacts', 'process_ldap_fields', 'after');
add_handler('contacts', 'process_update_ldap_contact', true, 'ldap_contacts', 'load_edit_ldap_contact', 'after');
add_handler('contacts', 'process_update_ldap_server', true, 'ldap_contacts', 'process_ldap_fields', 'after');

add_output('contacts', 'ldap_contact_form_start', true, 'ldap_contacts', 'contacts_content_start', 'after');
add_output('contacts', 'ldap_form_first_name', true, 'ldap_contacts', 'ldap_contact_form_start', 'after');
add_output('contacts', 'ldap_form_last_name', true, 'ldap_contacts', 'ldap_form_first_name', 'after');
add_output('contacts', 'ldap_form_mail', true, 'ldap_contacts', 'ldap_form_last_name', 'after');
add_output('contacts', 'ldap_form_displayname', true, 'ldap_contacts', 'ldap_form_mail', 'after');
add_output('contacts', 'ldap_form_locality', true, 'ldap_contacts', 'ldap_form_displayname', 'after');
add_output('contacts', 'ldap_form_state', true, 'ldap_contacts', 'ldap_form_locality', 'after');
add_output('contacts', 'ldap_form_street', true, 'ldap_contacts', 'ldap_form_state', 'after');
add_output('contacts', 'ldap_form_postalcode', true, 'ldap_contacts', 'ldap_form_street', 'after');
add_output('contacts', 'ldap_form_title', true, 'ldap_contacts', 'ldap_form_postalcode', 'after');
add_output('contacts', 'ldap_form_phone', true, 'ldap_contacts', 'ldap_form_title', 'after');
add_output('contacts', 'ldap_form_fax', true, 'ldap_contacts', 'ldap_form_phone', 'after');
add_output('contacts', 'ldap_form_mobile', true, 'ldap_contacts', 'ldap_form_fax', 'after');
add_output('contacts', 'ldap_form_room', true, 'ldap_contacts', 'ldap_form_mobile', 'after');
add_output('contacts', 'ldap_form_car', true, 'ldap_contacts', 'ldap_form_room', 'after');
add_output('contacts', 'ldap_form_org', true, 'ldap_contacts', 'ldap_form_car', 'after');
add_output('contacts', 'ldap_form_org_unit', true, 'ldap_contacts', 'ldap_form_org', 'after');
add_output('contacts', 'ldap_form_org_dpt', true, 'ldap_contacts', 'ldap_form_org_unit', 'after');
add_output('contacts', 'ldap_form_emp_num', true, 'ldap_contacts', 'ldap_form_org_dpt', 'after');
add_output('contacts', 'ldap_form_emp_type', true, 'ldap_contacts', 'ldap_form_emp_num', 'after');
add_output('contacts', 'ldap_form_lang', true, 'ldap_contacts', 'ldap_form_emp_type', 'after');
add_output('contacts', 'ldap_form_uri', true, 'ldap_contacts', 'ldap_form_lang', 'after');
add_output('contacts', 'ldap_form_submit', true, 'ldap_contacts', 'ldap_form_uri', 'after');
add_output('contacts', 'ldap_contact_form_end', true, 'ldap_contacts', 'ldap_form_submit', 'after');

add_handler('ajax_autocomplete_contact', 'load_ldap_contacts', true, 'ldap_contacts', 'load_contacts', 'after');
add_handler('ajax_imap_message_content', 'load_ldap_contacts', true, 'ldap_contacts', 'load_contacts', 'after');
add_handler('compose', 'load_ldap_contacts', true, 'ldap_contacts', 'load_contacts', 'after');
add_handler('ajax_delete_contact', 'load_ldap_contacts', true, 'ldap_contacts', 'load_contacts', 'after');
add_handler('ajax_delete_contact', 'process_delete_ldap_contact', true, 'ldap_contacts', 'load_ldap_contacts', 'after');
add_handler('ajax_add_contact', 'load_ldap_contacts', true, 'ldap_contacts', 'load_contacts', 'after');
add_handler('ajax_add_contact', 'process_add_ldap_contact_from_message', true, 'ldap_contacts', 'save_user_data', 'before');

add_handler('settings', 'load_ldap_settings', true, 'ldap_contacts', 'load_user_data', 'after');
add_handler('settings', 'process_ldap_auth_settings', true, 'ldap_contacts', 'save_user_settings', 'before');
add_output('settings', 'ldap_auth_settings', true, 'ldap_contacts', 'end_settings_form', 'before');

return array(
    'allowed_post' => array(
        'ldap_first_name' => FILTER_SANITIZE_STRING,
        'ldap_last_name' => FILTER_SANITIZE_STRING,
        'ldap_displayname' => FILTER_SANITIZE_STRING,
        'ldap_mail' => FILTER_SANITIZE_STRING,
        'ldap_locality' => FILTER_SANITIZE_STRING,
        'ldap_state' => FILTER_SANITIZE_STRING,
        'ldap_street' => FILTER_SANITIZE_STRING,
        'ldap_postalcode' => FILTER_SANITIZE_STRING,
        'ldap_title' => FILTER_SANITIZE_STRING,
        'ldap_phone' => FILTER_SANITIZE_STRING,
        'ldap_fax' => FILTER_SANITIZE_STRING,
        'ldap_mobile' => FILTER_SANITIZE_STRING,
        'ldap_room' => FILTER_SANITIZE_STRING,
        'ldap_car' => FILTER_SANITIZE_STRING,
        'ldap_org' => FILTER_SANITIZE_STRING,
        'ldap_org_unit' => FILTER_SANITIZE_STRING,
        'ldap_org_dpt' => FILTER_SANITIZE_STRING,
        'ldap_emp_num' => FILTER_SANITIZE_STRING,
        'ldap_emp_type' => FILTER_SANITIZE_STRING,
        'ldap_lang' => FILTER_SANITIZE_STRING,
        'ldap_uri' => FILTER_SANITIZE_STRING,
        'add_ldap_contact' => FILTER_SANITIZE_STRING,
        'update_ldap_contact' => FILTER_SANITIZE_STRING,
        'ldap_source' => FILTER_SANITIZE_STRING,
        'ldap_usernames' => array('filter' => FILTER_SANITIZE_STRING, 'flags'  => FILTER_FORCE_ARRAY),
        'ldap_passwords' => array('filter' => FILTER_UNSAFE_RAW, 'flags'  => FILTER_FORCE_ARRAY)
    )
);
