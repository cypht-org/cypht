<?php

handler_source('history');
output_source('history');

setup_base_page('history', 'core');
add_output('history', 'history_heading', true, 'history', 'content_section_start', 'after');
add_output('ajax_hm_folders', 'history_page_link', true, 'history', 'logout_menu_item', 'before');

return array(
    'allowed_pages' => array(
        'history',
    )
);

