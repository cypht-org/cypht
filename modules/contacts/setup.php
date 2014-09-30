<?php

if (!defined('DEBUG_MODE')) { die(); }

handler_source('contacts');
output_source('contacts');


/* contacts page */
setup_base_page('contacts', 'core');
add_output('contacts', 'contacts_content', true, 'contacts', 'content_section_start', 'after');
add_output('ajax_hm_folders', 'contacts_page_link', true, 'contacts', 'settings_menu_end', 'before');

return array(
    'allowed_pages' => array(
        'contacts'
    )
);


?>
