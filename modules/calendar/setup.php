<?php

if (!defined('DEBUG_MODE')) { die(); }

handler_source('calendar');
output_source('calendar');


/* calendar page */
setup_base_page('calendar', 'core');
add_handler('calendar', 'get_calendar_date', true, 'calendar', 'load_user_data', 'after');
add_handler('calendar', 'process_add_event', true, 'calendar', 'get_calendar_date', 'after');
add_handler('calendar', 'process_delete_event', true, 'calendar', 'get_calendar_date', 'after');
add_handler('ajax_imap_message_content', 'vcalendar_check',  true, 'calendar', 'imap_message_content', 'after');
/*add_output('ajax_imap_message_content', 'vcalendar_add_output', true, 'calendar', 'filter_message_headers', 'after');*/
add_output('calendar', 'calendar_content', true, 'calendar', 'version_upgrade_checker', 'after');
add_output('calendar', 'add_cal_event_form', true, 'calendar', 'version_upgrade_checker', 'after');
add_output('ajax_hm_folders', 'calendar_page_link', true, 'calendar', 'main_menu_content', 'before');

return array(
    'allowed_pages' => array(
        'calendar',
    ),
    'allowed_post' => array(
        'event_title' => FILTER_UNSAFE_RAW,
        'event_detail' => FILTER_UNSAFE_RAW,
        'event_date' => FILTER_UNSAFE_RAW,
        'event_time' => FILTER_UNSAFE_RAW,
        'event_repeat' => FILTER_UNSAFE_RAW,
        'delete_id' => FILTER_UNSAFE_RAW
    ),
    'allowed_get' => array(
        'date' => FILTER_SANITIZE_FULL_SPECIAL_CHARS,
        'view' => FILTER_SANITIZE_FULL_SPECIAL_CHARS,
        'action' => FILTER_SANITIZE_FULL_SPECIAL_CHARS,
    ),
);
