<?php

if (!defined('DEBUG_MODE')) { die(); }

handler_source('hacker_news');
output_source('hacker_news');

setup_base_page('hacker_news', 'core');
add_handler('hacker_news', 'hacker_news_fields', true, 'hacker_news', 'message_list_type', 'after');
add_output('hacker_news', 'hacker_news_heading', true, 'core', 'content_section_start', 'after');
add_output('hacker_news', 'message_list_start', true, 'core', 'hacker_news_heading', 'after');
add_output('hacker_news', 'hacker_news_table_end', true, 'core', 'message_list_start', 'after');

add_output('ajax_hm_folders', 'hacker_news_folders',  true, 'hacker_news', 'folder_list_content_start', 'before');

add_handler('ajax_hacker_news_data', 'login', false, 'core');
add_handler('ajax_hacker_news_data', 'load_user_data', true, 'core');
add_handler('ajax_hacker_news_data', 'language', true, 'core');
add_handler('ajax_hacker_news_data', 'hacker_news_data',  true);
add_handler('ajax_hacker_news_data', 'date', true, 'core');
add_handler('ajax_hacker_news_data', 'http_headers', true, 'core');
add_output('ajax_hacker_news_data', 'filter_hacker_news_data', true);

return array(
    'allowed_pages' => array(
        'hacker_news',
        'ajax_hacker_news_data'
    ),
    'allowed_output' => array(
    ),
    'allowed_post' => array(
    )
);

?>
