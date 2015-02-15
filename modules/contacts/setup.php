<?php

if (!defined('DEBUG_MODE')) { die(); }

handler_source('contacts');
output_source('contacts');


/* contacts page */
setup_base_page('contacts', 'core');

add_handler('contacts', 'process_add_contact', true, 'contacts', 'load_user_data', 'after');
add_output('contacts', 'contacts_content_start', true, 'contacts', 'content_section_start', 'after');
add_output('contacts', 'contacts_content_add_form', true, 'contacts', 'contacts_content_start', 'after');
add_output('contacts', 'contacts_content_end', true, 'contacts', 'contacts_content_add_form', 'after');

add_output('ajax_hm_folders', 'contacts_page_link', true, 'contacts', 'settings_menu_end', 'before');

return array(
    'allowed_pages' => array(
        'contacts'
    ),
    'allowed_post' => array(
        'contact_email' => FILTER_SANITIZE_STRING,
        'contact_name' => FILTER_SANITIZE_STRING,
    )
);


?>
