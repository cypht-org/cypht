<?php

if (!defined('DEBUG_MODE')) { die(); }

handler_source('report_spam');
output_source('report_spam');

// Settings page handlers and outputs
add_handler('settings', 'process_spam_report_settings', true, 'report_spam', 'save_user_settings', 'before');

add_output('settings', 'start_report_spam_settings', true, 'report_spam', 'drafts_source_max_setting', 'after');
add_output('settings', 'spamcop_enabled_setting', true, 'report_spam', 'start_report_spam_settings', 'after');
add_output('settings', 'spamcop_submission_email_setting', true, 'report_spam', 'spamcop_enabled_setting', 'after');
add_output('settings', 'spamcop_from_email_setting', true, 'report_spam', 'spamcop_submission_email_setting', 'after');
add_output('settings', 'apwg_enabled_setting', true, 'report_spam', 'spamcop_from_email_setting', 'after');
add_output('settings', 'apwg_from_email_setting', true, 'report_spam', 'apwg_enabled_setting', 'after');
add_output('settings', 'abuseipdb_enabled_setting', true, 'report_spam', 'apwg_from_email_setting', 'after');
add_output('settings', 'abuseipdb_api_key_setting', true, 'report_spam', 'abuseipdb_enabled_setting', 'after');

/* report spam callback */
setup_base_ajax_page('ajax_report_spam', 'core');
add_handler('ajax_report_spam', 'message_list_type', true, 'core');
add_handler('ajax_report_spam', 'imap_message_list_type', true);
add_handler('ajax_report_spam', 'load_imap_servers_from_config', true, 'imap', 'load_user_data', 'after');
add_handler('ajax_report_spam', 'load_smtp_servers_from_config', true, 'smtp', 'load_user_data', 'after');
add_handler('ajax_report_spam', 'imap_oauth2_token_check', true);
add_handler('ajax_report_spam', 'close_session_early', true, 'core');
add_handler('ajax_report_spam', 'report_spam', true);

// Modal output
// add_output('message', 'report_spam_modal', true, 'report_spam', 'modals', 'after');
// add_output('message_list', 'report_spam_modal', true, 'report_spam', 'modals', 'after');
// add_output('home', 'report_spam_modal', true, 'report_spam', 'modals', 'after');

return array(
    'allowed_pages' => array(
        'ajax_report_spam',
    ),

    'allowed_output' => array(
        'spam_report_error' => array(FILTER_VALIDATE_BOOLEAN, false),
        'spam_report_message' => array(FILTER_UNSAFE_RAW, false),
        'spam_report_count' => array(FILTER_VALIDATE_INT, false),
        'router_user_msgs' => array(FILTER_UNSAFE_RAW, FILTER_REQUIRE_ARRAY),
    ),

    'allowed_get' => array(
    ),

    'allowed_post' => array(
        'message_ids' => array(FILTER_UNSAFE_RAW, false),
        'spam_reasons' => array('filter' => FILTER_UNSAFE_RAW, 'flags' => FILTER_FORCE_ARRAY),
        'spamcop_settings' => array('filter' => FILTER_UNSAFE_RAW, 'flags' => FILTER_FORCE_ARRAY),
        'apwg_settings' => array('filter' => FILTER_UNSAFE_RAW, 'flags' => FILTER_FORCE_ARRAY),
        'abuseipdb_settings' => array('filter' => FILTER_UNSAFE_RAW, 'flags' => FILTER_FORCE_ARRAY),
    )
);
