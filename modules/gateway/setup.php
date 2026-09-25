<?php

/**
 * Private bridge between Cypht and cypht-gateway.
 *
 * Every page requires a normal authenticated Cypht session plus the private
 * X-Cypht-Gateway-Key header. Public clients must never call these pages.
 */
if (!defined('DEBUG_MODE')) { die(); }

handler_source('gateway');
if (function_exists('setup_base_page')) {
    setup_base_page('gateway', 'core');
}
if (function_exists('add_output')) {
    add_output('ajax_hm_folders', 'gateway_settings_link', true, 'gateway', 'settings_menu_end', 'before');
    add_output('gateway', 'gateway_page_content', true, 'gateway', 'content_section_start', 'after');
}

$gateway_read_pages = array(
    'ajax_gateway_ping',
    'ajax_gateway_accounts',
    'ajax_gateway_profiles',
    'ajax_gateway_mailboxes',
    'ajax_gateway_messages',
    'ajax_gateway_search',
    'ajax_gateway_message',
    'ajax_gateway_attachment',
    'ajax_gateway_sieve_status'
);

$gateway_write_pages = array(
    'ajax_gateway_upload',
    'ajax_gateway_send',
    'ajax_gateway_draft',
    'ajax_gateway_message_update',
    'ajax_gateway_message_move',
    'ajax_gateway_message_archive',
    'ajax_gateway_message_delete'
);

$gateway_feed_read_pages = array('ajax_gateway_feeds', 'ajax_gateway_feed');
$gateway_calendar_read_pages = array('ajax_gateway_calendar_events');
$gateway_calendar_write_pages = array('ajax_gateway_calendar_create', 'ajax_gateway_calendar_delete');
$gateway_saved_search_read_pages = array('ajax_gateway_saved_searches');
$gateway_saved_search_write_pages = array(
    'ajax_gateway_saved_search_create',
    'ajax_gateway_saved_search_update',
    'ajax_gateway_saved_search_delete'
);
$gateway_pages = array_merge($gateway_read_pages, $gateway_write_pages);
$gateway_pages = array_merge($gateway_pages, $gateway_feed_read_pages);

foreach ($gateway_pages as $page) {
    setup_base_ajax_page($page, 'core');
    if (function_exists('replace_module')) { replace_module('handler', 'login', 'gateway_login', $page); }
    add_handler($page, 'gateway_guard', true, 'gateway', 'load_user_data', 'after');
    add_handler($page, 'load_imap_servers_from_config', true, 'imap', 'gateway_guard', 'after');
    add_handler($page, 'imap_oauth2_token_check', true, 'imap', 'load_imap_servers_from_config', 'after');
}

foreach (array_merge($gateway_calendar_read_pages, $gateway_calendar_write_pages) as $page) {
    setup_base_ajax_page($page, 'core');
    if (function_exists('replace_module')) { replace_module('handler', 'login', 'gateway_login', $page); }
    add_handler($page, 'gateway_guard', true, 'gateway', 'load_user_data', 'after');
    $gateway_pages[] = $page;
}

/* Write/profile pages need Cypht's existing SMTP and profile implementations loaded. */
foreach (array('ajax_gateway_accounts', 'ajax_gateway_profiles', 'ajax_gateway_upload', 'ajax_gateway_send', 'ajax_gateway_draft') as $page) {
    add_handler($page, 'load_smtp_servers_from_config', true, 'smtp', 'load_imap_servers_from_config', 'after');
    add_handler($page, 'compose_profile_data', true, 'profiles', 'load_smtp_servers_from_config', 'after');
}

add_handler('ajax_gateway_feeds', 'gateway_feeds', true, 'gateway', 'gateway_guard', 'after');
add_handler('ajax_gateway_feed', 'gateway_feed', true, 'gateway', 'gateway_guard', 'after');
add_handler('ajax_gateway_sieve_status', 'gateway_sieve_status', true, 'gateway', 'load_imap_servers_from_config', 'after');
add_handler('ajax_gateway_ping', 'gateway_ping', true, 'gateway', 'load_imap_servers_from_config', 'after');
add_handler('ajax_gateway_accounts', 'gateway_accounts', true, 'gateway', 'compose_profile_data', 'after');
add_handler('ajax_gateway_profiles', 'gateway_profiles', true, 'gateway', 'compose_profile_data', 'after');
add_handler('ajax_gateway_mailboxes', 'gateway_mailboxes', true, 'gateway', 'load_imap_servers_from_config', 'after');
add_handler('ajax_gateway_messages', 'gateway_messages', true, 'gateway', 'load_imap_servers_from_config', 'after');
add_handler('ajax_gateway_search', 'gateway_search', true, 'gateway', 'load_imap_servers_from_config', 'after');
add_handler('ajax_gateway_message', 'gateway_message', true, 'gateway', 'load_imap_servers_from_config', 'after');
add_handler('ajax_gateway_attachment', 'gateway_attachment', true, 'gateway', 'load_imap_servers_from_config', 'after');
add_handler('ajax_gateway_upload', 'gateway_upload', true, 'gateway', 'compose_profile_data', 'after');
add_handler('ajax_gateway_send', 'gateway_send', true, 'gateway', 'compose_profile_data', 'after');
add_handler('ajax_gateway_draft', 'gateway_draft', true, 'gateway', 'compose_profile_data', 'after');
add_handler('ajax_gateway_message_update', 'gateway_message_update', true, 'gateway', 'load_imap_servers_from_config', 'after');
add_handler('ajax_gateway_message_move', 'gateway_message_move', true, 'gateway', 'load_imap_servers_from_config', 'after');
add_handler('ajax_gateway_message_archive', 'gateway_message_archive', true, 'gateway', 'load_imap_servers_from_config', 'after');
add_handler('ajax_gateway_message_delete', 'gateway_message_delete', true, 'gateway', 'load_imap_servers_from_config', 'after');

$gateway_contact_handlers = array(
    'ajax_gateway_contacts' => 'gateway_contacts',
    'ajax_gateway_contact' => 'gateway_contact',
    'ajax_gateway_contact_create' => 'gateway_contact_create',
    'ajax_gateway_contact_update' => 'gateway_contact_update',
    'ajax_gateway_contact_delete' => 'gateway_contact_delete'
);
foreach ($gateway_contact_handlers as $page => $handler) {
    setup_base_ajax_page($page, 'core');
    if (function_exists('replace_module')) { replace_module('handler', 'login', 'gateway_login', $page); }
    add_handler($page, 'gateway_guard', true, 'gateway', 'load_user_data', 'after');
    add_handler($page, $handler, true, 'gateway', 'gateway_guard', 'after');
    $gateway_pages[] = $page;
}
$gateway_tag_handlers = array(
    'ajax_gateway_tags' => 'gateway_tags',
    'ajax_gateway_tag_create' => 'gateway_tag_create',
    'ajax_gateway_tag_update' => 'gateway_tag_update',
    'ajax_gateway_tag_delete' => 'gateway_tag_delete'
);
foreach ($gateway_tag_handlers as $page => $handler) {
    setup_base_ajax_page($page, 'core');
    if (function_exists('replace_module')) { replace_module('handler', 'login', 'gateway_login', $page); }
    add_handler($page, 'gateway_guard', true, 'gateway', 'load_user_data', 'after');
    add_handler($page, $handler, true, 'gateway', 'gateway_guard', 'after');
    $gateway_pages[] = $page;
}

$gateway_message_tag_handlers = array(
    'ajax_gateway_message_tag_add' => 'gateway_message_tag_add',
    'ajax_gateway_message_tag_remove' => 'gateway_message_tag_remove'
);
foreach ($gateway_message_tag_handlers as $page => $handler) {
    setup_base_ajax_page($page, 'core');
    if (function_exists('replace_module')) { replace_module('handler', 'login', 'gateway_login', $page); }
    add_handler($page, 'gateway_guard', true, 'gateway', 'load_user_data', 'after');
    add_handler($page, 'load_imap_servers_from_config', true, 'imap', 'gateway_guard', 'after');
    add_handler($page, 'imap_oauth2_token_check', true, 'imap', 'load_imap_servers_from_config', 'after');
    add_handler($page, $handler, true, 'gateway', 'load_imap_servers_from_config', 'after');
    $gateway_pages[] = $page;
}
foreach (array_merge($gateway_saved_search_read_pages, $gateway_saved_search_write_pages) as $page) {
    setup_base_ajax_page($page, 'core');
    if (function_exists('replace_module')) { replace_module('handler', 'login', 'gateway_login', $page); }
    add_handler($page, 'gateway_guard', true, 'gateway', 'load_user_data', 'after');
    add_handler($page, 'load_imap_servers_from_config', true, 'imap', 'gateway_guard', 'after');
    add_handler($page, 'imap_oauth2_token_check', true, 'imap', 'load_imap_servers_from_config', 'after');
    $gateway_pages[] = $page;
}
add_handler('ajax_gateway_saved_searches', 'gateway_saved_searches', true, 'gateway', 'gateway_guard', 'after');
add_handler('ajax_gateway_saved_search_create', 'gateway_saved_search_create', true, 'gateway', 'gateway_guard', 'after');
add_handler('ajax_gateway_saved_search_update', 'gateway_saved_search_update', true, 'gateway', 'gateway_guard', 'after');
add_handler('ajax_gateway_saved_search_delete', 'gateway_saved_search_delete', true, 'gateway', 'gateway_guard', 'after');
add_handler('ajax_gateway_calendar_events', 'gateway_calendar_events', true, 'gateway', 'gateway_guard', 'after');
add_handler('ajax_gateway_calendar_create', 'gateway_calendar_create', true, 'gateway', 'gateway_guard', 'after');
add_handler('ajax_gateway_calendar_delete', 'gateway_calendar_delete', true, 'gateway', 'gateway_guard', 'after');
return array(
    'allowed_pages' => array_merge(array('gateway'), $gateway_pages),
    'allowed_server' => array(
        'HTTP_X_CYPHT_GATEWAY_KEY' => FILTER_UNSAFE_RAW,
        'HTTP_X_CYPHT_GATEWAY_CONFIG_KEY' => FILTER_UNSAFE_RAW,
        'HTTP_X_CYPHT_GATEWAY_FILENAME' => FILTER_UNSAFE_RAW,
        'HTTP_X_CYPHT_GATEWAY_CONTENT_TYPE' => FILTER_UNSAFE_RAW,
        'CONTENT_LENGTH' => FILTER_VALIDATE_INT
    ),
    'allowed_get' => array(
        'account_id' => FILTER_UNSAFE_RAW,
        'contact_id' => FILTER_UNSAFE_RAW,
        'account_ids' => FILTER_UNSAFE_RAW,
        'folder' => FILTER_UNSAFE_RAW,
        'uid' => FILTER_UNSAFE_RAW,
        'part' => FILTER_UNSAFE_RAW,
        'query' => FILTER_UNSAFE_RAW,
        'start_at' => FILTER_VALIDATE_INT,
        'end_at' => FILTER_VALIDATE_INT,
        'feed_id' => FILTER_UNSAFE_RAW,
        'offset' => FILTER_VALIDATE_INT,
        'limit' => FILTER_VALIDATE_INT
    ),
    'allowed_post' => array(
        'payload' => FILTER_UNSAFE_RAW
    )
);
