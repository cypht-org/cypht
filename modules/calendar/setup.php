<?php

if (!defined('DEBUG_MODE')) { die(); }

handler_source('calendar');
output_source('calendar');


/* calendar page */
setup_base_page('calendar', 'core');
add_handler('calendar', 'get_calendar_date', true, 'calendar', 'language', 'after');
add_output('calendar', 'calendar_content', true, 'calendar', 'content_section_start', 'after');
add_output('ajax_hm_folders', 'calendar_page_link', true, 'calendar', 'logout_menu_item', 'before');

return array(
    'allowed_pages' => array(
        'calendar',
    ),
    'allowed_get' => array(
        'date' => FILTER_SANITIZE_STRING,
    ),
);


?>
