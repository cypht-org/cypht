<?php

if (!defined('DEBUG_MODE')) { die(); }

handler_source('hacker_news');
output_source('hacker_news');

add_handler('message_list', 'hacker_news_fields', true, 'hacker_news', 'message_list_type', 'after');

add_output('ajax_hm_folders', 'hacker_news_folders',  true, 'hacker_news', 'folder_list_content_start', 'before');

add_handler('ajax_hacker_news_data', 'login', false, 'core');
add_handler('ajax_hacker_news_data', 'load_user_data', true, 'core');
add_handler('ajax_hacker_news_data', 'language', true, 'core');
add_handler('ajax_hacker_news_data', 'message_list_type', true, 'core');
add_handler('ajax_hacker_news_data', 'hacker_news_fields', true);
add_handler('ajax_hacker_news_data', 'hacker_news_data',  true);
add_handler('ajax_hacker_news_data', 'date', true, 'core');
add_handler('ajax_hacker_news_data', 'http_headers', true, 'core');
add_output('ajax_hacker_news_data', 'filter_hacker_news_data', true);

return array(
    'allowed_pages' => array(
        'hacker_news',
        'ajax_hacker_news_data'
    )
);

?>
