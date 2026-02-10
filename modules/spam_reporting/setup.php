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

/* spam report send ajax */
setup_base_ajax_page('ajax_spam_report_send', 'core');
add_handler('ajax_spam_report_send', 'load_imap_servers_from_config', true, 'imap', 'load_user_data', 'after');
add_handler('ajax_spam_report_send', 'imap_oauth2_token_check', true, 'imap', 'load_imap_servers_from_config', 'after');
add_handler('ajax_spam_report_send', 'spam_report_send', true, 'spam_reporting', 'imap_oauth2_token_check', 'after');
add_output('ajax_spam_report_send', 'spam_report_send', true, 'spam_reporting');

/* settings page - spam reporting user preferences */
add_handler('settings', 'process_spam_report_settings', true, 'spam_reporting', 'save_user_settings', 'before');
add_output('settings', 'spam_report_settings_section', true, 'spam_reporting', 'default_sort_order_setting', 'after');

return array(
    'allowed_pages' => array(
        'ajax_spam_report_preview',
        'ajax_spam_report_send'
    ),
    'allowed_output' => array(
        'spam_reporting_configs_for_ui' => array(FILTER_UNSAFE_RAW, FILTER_REQUIRE_ARRAY),
        'spam_reporting_adapter_types' => array(FILTER_UNSAFE_RAW, FILTER_REQUIRE_ARRAY),
        'spam_report_targets' => array(FILTER_UNSAFE_RAW, FILTER_REQUIRE_ARRAY),
        'spam_report_suggestion' => array(FILTER_UNSAFE_RAW, FILTER_REQUIRE_ARRAY),
        'spam_report_platforms' => array(FILTER_UNSAFE_RAW, FILTER_REQUIRE_ARRAY),
        'spam_report_preview' => array(FILTER_UNSAFE_RAW, FILTER_REQUIRE_ARRAY), // Preview-only raw message data (not rendered or persisted).
        'spam_report_error' => array(FILTER_UNSAFE_RAW, false),
        'spam_report_debug' => array(FILTER_UNSAFE_RAW, FILTER_REQUIRE_ARRAY),
        'spam_report_send_ok' => array(FILTER_VALIDATE_BOOLEAN, false),
        'spam_report_send_message' => array(FILTER_UNSAFE_RAW, false)
    ),
    'allowed_post' => array(
        'list_path' => FILTER_UNSAFE_RAW,
        'uid' => FILTER_UNSAFE_RAW,
        'target_id' => FILTER_UNSAFE_RAW,
        'user_notes' => FILTER_UNSAFE_RAW,
        'spam_reporting_enabled' => FILTER_VALIDATE_INT,
        'spam_reporting_platform_abuseipdb' => FILTER_VALIDATE_INT,
        'spam_reporting_platform_spamcop' => FILTER_VALIDATE_INT,
        'spam_reporting_platform_spamhaus' => FILTER_VALIDATE_INT,
        'spam_reporting_platform_signal_spam' => FILTER_VALIDATE_INT,
        'spam_reporting_platform_apwg' => FILTER_VALIDATE_INT,
        'spam_reporting_platform_google_abuse' => FILTER_VALIDATE_INT,
        'spam_reporting_platform_microsoft_abuse' => FILTER_VALIDATE_INT,
        'spam_reporting_platform_yahoo_abuse' => FILTER_VALIDATE_INT,
        'spam_reporting_platform_zoho_abuse' => FILTER_VALIDATE_INT,
        'spam_reporting_target_configurations' => FILTER_UNSAFE_RAW
    ),
    'allowed_get' => array(
    )
);
