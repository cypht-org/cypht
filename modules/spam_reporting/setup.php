<?php

if (!defined('DEBUG_MODE')) { die(); }

handler_source('spam_reporting');
output_source('spam_reporting');

/* message view page */
add_output('message', 'spam_report_modal', true, 'spam_reporting', 'message_end', 'before');

/* message content ajax */
add_output('ajax_imap_message_content', 'spam_report_action', true, 'spam_reporting', 'filter_message_headers', 'before');
add_output('ajax_imap_message_content', 'spam_report_modal_inline', true, 'spam_reporting', 'filter_message_headers', 'after');

/* spam report preview ajax */
setup_base_ajax_page('ajax_spam_report_preview', 'core');
add_handler('ajax_spam_report_preview', 'load_imap_servers_from_config', true, 'imap', 'load_user_data', 'after');
add_handler('ajax_spam_report_preview', 'imap_oauth2_token_check', true, 'imap', 'load_imap_servers_from_config', 'after');
add_handler('ajax_spam_report_preview', 'spam_report_preview', true, 'spam_reporting', 'imap_oauth2_token_check', 'after');
add_output('ajax_spam_report_preview', 'spam_report_preview', true, 'spam_reporting');

return array(
    'allowed_pages' => array(
        'ajax_spam_report_preview'
    ),
    'allowed_output' => array(
        'spam_report_targets' => array(FILTER_UNSAFE_RAW, false),
        'spam_report_preview' => array(FILTER_UNSAFE_RAW, false), // Preview-only raw message data (not rendered or persisted).
        'spam_report_error' => array(FILTER_UNSAFE_RAW, false)
    ),
    'allowed_post' => array(
        'list_path' => FILTER_UNSAFE_RAW,
        'uid' => FILTER_UNSAFE_RAW
    ),
    'allowed_get' => array(
    )
);
