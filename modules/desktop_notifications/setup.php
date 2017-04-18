<?php

if (!defined('DEBUG_MODE')) { die(); }

handler_source('desktop_notifications');
output_source('desktop_notifications');

add_module_to_all_pages('output', 'push_js_include', true, 'desktop_notifications', 'header_start', 'after');
//add_output('home', 'hello_world_home_page', true, 'hello_world', 'content_section_start', 'after');
//add_handler('hello_world', 'hello_world_page_handler', true, 'hello_world', 'load_user_data', 'after');

return array(
    'allowed_pages' => array(
    ),
    'allowed_output' => array(
    ),
    'allowed_get' => array(
    ),
    'allowed_post' => array(
    )
);
