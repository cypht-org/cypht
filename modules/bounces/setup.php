<?php

/**
 * Bounce handling module set: detects bounced messages, tracks bouncing
 * addresses and warns before writing to them again.
 */

if (!defined('DEBUG_MODE')) { die(); }

handler_source('bounces');
output_source('bounces');

/* record bounces when a delivery failure report is opened */
add_handler('ajax_imap_message_content', 'bounce_message_check', true, 'bounces', 'imap_message_content', 'after');
add_handler('ajax_imap_message_content', 'save_user_data', true, 'core', 'bounce_message_check', 'after');
add_output('ajax_imap_message_content', 'bounce_message_banner', true, 'bounces', 'filter_message_headers', 'after');

/* compose warning */
add_handler('compose', 'load_bounce_tracking_setting', true, 'bounces', 'load_user_data', 'after');
add_output('compose', 'bounce_recipients_container', true, 'bounces', 'compose_form_content', 'after');
setup_base_ajax_page('ajax_bounce_recipients_status', 'core');
add_handler('ajax_bounce_recipients_status', 'close_session_early', true, 'core', 'load_user_data', 'after');
add_handler('ajax_bounce_recipients_status', 'check_bounce_recipients', true, 'bounces', 'close_session_early', 'after');
add_output('ajax_bounce_recipients_status', 'filter_bounce_recipients', true, 'bounces');

/* bounced addresses page */
setup_base_page('bounces', 'core');
add_handler('bounces', 'process_bounce_action', true, 'bounces', 'load_user_data', 'after');
add_handler('bounces', 'load_bounce_addresses', true, 'bounces', 'process_bounce_action', 'after');
add_output('bounces', 'bounce_addresses_page', true, 'bounces', 'content_section_start', 'after');
add_output('ajax_hm_folders', 'bounces_page_link', true, 'bounces', 'settings_menu_end', 'before');

/* setting */
add_handler('settings', 'process_bounce_tracking_setting', true, 'bounces', 'save_user_settings', 'before');
add_output('settings', 'bounce_tracking_setting', true, 'bounces', 'start_general_settings', 'after');

return [
    'allowed_pages' => [
        'bounces',
        'ajax_bounce_recipients_status',
    ],
    'allowed_output' => [
        'bounce_recipients_display' => [FILTER_UNSAFE_RAW, false],
    ],
    'allowed_post' => [
        'compose_to' => FILTER_UNSAFE_RAW,
        'compose_cc' => FILTER_UNSAFE_RAW,
        'compose_bcc' => FILTER_UNSAFE_RAW,
        'bounce_email' => FILTER_UNSAFE_RAW,
        'bounce_action' => FILTER_UNSAFE_RAW,
        'bounce_tracking' => FILTER_VALIDATE_INT,
    ],
];
