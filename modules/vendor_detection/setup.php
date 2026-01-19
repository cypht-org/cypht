<?php

if (!defined('DEBUG_MODE')) { die(); }

handler_source('vendor_detection');
output_source('vendor_detection');

add_handler('settings', 'process_vendor_detection_setting', true, 'vendor_detection', 'save_user_settings', 'before');
add_output('settings', 'vendor_detection_setting', true, 'vendor_detection', 'start_general_settings', 'after');

add_output('ajax_imap_message_content', 'vendor_detection_label', true, 'vendor_detection', 'filter_message_headers', 'after');

return array(
    'allowed_pages' => array(),
    'allowed_output' => array(),
    'allowed_get' => array(),
    'allowed_post' => array(
        'vendor_detection_ui' => FILTER_VALIDATE_INT
    )
);
