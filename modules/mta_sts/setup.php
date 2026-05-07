<?php

/**
 * MTA-STS module setup
 * @package modules
 * @subpackage mta_sts
 */

if (!defined('DEBUG_MODE')) { die(); }

/* Load module sources */
handler_source('mta_sts');
output_source('mta_sts');

/* Add MTA-STS checking to compose page */
add_handler('compose', 'check_mta_sts_status', true, 'mta_sts', 'add_smtp_servers_to_page_data', 'after');

/* Add MTA-STS status indicator output to compose page */
add_output('compose', 'mta_sts_status_indicator', true, 'mta_sts', 'compose_form_content', 'after');

/* AJAX status checks for compose recipient edits */
setup_base_ajax_page('ajax_mta_sts_status', 'core');
add_handler('ajax_mta_sts_status', 'close_session_early', true, 'core', 'load_user_data', 'after');
add_handler('ajax_mta_sts_status', 'check_mta_sts_status', true, 'mta_sts', 'close_session_early', 'after');
add_output('ajax_mta_sts_status', 'filter_mta_sts_status', true, 'mta_sts');

/* Settings page handlers */
add_handler('settings', 'process_enable_mta_sts_setting', true, 'mta_sts', 'save_user_settings', 'before');
add_output('settings', 'enable_mta_sts_check_setting', true, 'mta_sts', 'start_general_settings', 'after');

return array(
    'allowed_pages' => array(
        'ajax_mta_sts_status'
    ),
    'allowed_output' => array(
        'mta_sts_status_display' => array(FILTER_UNSAFE_RAW, false)
    ),
    'allowed_post' => array(
        'compose_to' => FILTER_UNSAFE_RAW,
        'compose_cc' => FILTER_UNSAFE_RAW,
        'compose_bcc' => FILTER_UNSAFE_RAW
    )
);
