<?php

if (!defined('DEBUG_MODE')) { die(); }

handler_source('desktop_notifications');
output_source('desktop_notifications');

add_module_to_all_pages('output', 'push_js_include', true, 'desktop_notifications', 'header_css', 'after');

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
