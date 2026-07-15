<?php

if (!defined('DEBUG_MODE')) { die(); }

handler_source('inline_message');
output_source('inline_message');

add_handler('settings', 'process_inline_message_setting', true, 'inline_message', 'save_user_settings', 'before');
add_handler('settings', 'process_inline_message_style', true, 'inline_message', 'save_user_settings', 'before');
add_output('settings', 'inline_message_setting', true, 'inline_message', 'start_general_settings', 'after');
add_output('settings', 'inline_message_style', true, 'inline_message', 'inline_message_setting', 'after');

add_handler('message_list', 'get_inline_message_setting', true, 'inline_message', 'load_user_data', 'after');
add_handler('search', 'get_inline_message_setting', true, 'inline_message', 'load_user_data', 'after');
add_handler('history', 'get_inline_message_setting', true, 'inline_message', 'load_user_data', 'after');
add_handler('ajax_imap_folder_display', 'get_inline_message_setting', true, 'inline_message', 'load_user_data', 'after');
// Aggregated/combined message lists, search and tags render their rows via dedicated AJAX hooks
// (not the message_list/search page itself), so get_inline_message_setting must be registered for
// them as well; otherwise the inline_message_setting output var is empty, subject_callback emits a
// real href instead of href="#"+data-src, and the SPA navigation interceptor hijacks the click to
// the full message page (the inline pane only opens for a moment).
add_handler('ajax_imap_message_list', 'get_inline_message_setting', true, 'inline_message', 'load_user_data', 'after');
add_handler('ajax_imap_search', 'get_inline_message_setting', true, 'inline_message', 'load_user_data', 'after');
add_handler('ajax_imap_tag_data', 'get_inline_message_setting', true, 'inline_message', 'load_user_data', 'after');
add_handler('ajax_feed_combined', 'get_inline_message_setting', true, 'inline_message', 'load_user_data', 'after');
add_output('message_list', 'inline_message_flag', true, 'inline_message', 'header_end', 'before');
add_output('history', 'inline_message_flag', true, 'inline_message', 'header_end', 'before');
add_output('search', 'inline_message_flag', true, 'inline_message', 'header_end', 'before');

return array(
    'allowed_pages' => array(),
    'allowed_output' => array(),
    'allowed_get' => array(
    ),
    'allowed_post' => array(
        'inline_message' => FILTER_VALIDATE_INT,
        'inline_message_style' => FILTER_UNSAFE_RAW
    )
);
