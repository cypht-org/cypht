<?php

handler_source('history');
output_source('history');

setup_base_page('history', 'core');
add_handler('history', 'load_message_history', true, 'history', 'save_user_data', 'before');
add_output('history', 'history_heading', true, 'history', 'content_section_start', 'after');
add_output('history', 'history_content', true, 'history', 'history_heading', 'after');
add_output('history', 'history_footer', true, 'history', 'history_content', 'after');

add_output('ajax_hm_folders', 'history_page_link', true, 'history', 'logout_menu_item', 'before');

add_handler('ajax_imap_message_content', 'history_record_imap_message', true, 'history', 'imap_message_content', 'after');
add_handler('ajax_feed_item_content', 'history_record_feed_message', true, 'history', 'feed_item_content', 'after');
add_handler('ajax_pop3_message_display', 'history_record_pop3_message', true, 'history', 'pop3_message_content', 'after');
add_handler('ajax_github_event_detail', 'history_record_github_message', true, 'history', 'github_event_detail', 'after');
add_handler('ajax_wp_notice_display', 'history_record_wp_message', true, 'history', 'get_wp_notice_data', 'after');

return array(
    'allowed_pages' => array(
        'history',
    )
);

