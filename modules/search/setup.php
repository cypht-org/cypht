<?php

if (!defined('DEBUG_MODE')) { die(); }

handler_source('search');
output_source('search');


/* search page */
setup_base_page('search', 'core');
add_output('search', 'search_content', true, 'search', 'content_section_start', 'after');
add_output('ajax_hm_folders', 'search_from_folder_list', true, 'search', 'main_menu_start', 'after');

/* add search term processing to all pages */
add_module_to_all_pages('handler', 'process_search_terms', true, 'search', 'language', 'after');
add_module_to_all_pages('output', 'js_search_data', true, 'search', 'js_data', 'after');

return array(
    'allowed_pages' => array(
        'search'
    )
);

?>
