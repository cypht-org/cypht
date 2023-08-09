<?php

if (!defined('DEBUG_MODE')) { die(); }

handler_source('sieve_filters');
output_source('sievefilters');

add_module_to_all_pages('handler', 'sieve_filters_enabled', true, 'sievefilters', 'load_imap_servers_from_config', 'after');
add_handler('ajax_imap_message_content', 'sieve_filters_enabled_message_content', true, 'sievefilters', 'imap_message_content', 'after');
add_handler('ajax_hm_folders', 'sieve_filters_enabled', true, 'core', 'load_user_data', 'after');

add_handler('ajax_imap_status', 'sieve_status', true, 'sievefilters', 'imap_status', 'before');

setup_base_page('sieve_filters', 'core');
setup_base_page('block_list', 'core');

// sieve filter
add_output('sieve_filters', 'sievefilters_settings_start', true, 'sievefilters', 'content_section_start', 'after');
add_output('sieve_filters', 'sievefilters_settings_accounts', true, 'sievefilters', 'sievefilters_settings_start', 'after');
add_handler('sieve_filters', 'settings_load_imap', true, 'sievefilters', 'load_user_data', 'after');
add_output('ajax_hm_folders', 'sievefilters_settings_link', true, 'sievefilters', 'settings_menu_end', 'before');

// block list
add_output('block_list', 'blocklist_settings_start', true, 'sievefilters', 'content_section_start', 'after');
add_output('block_list', 'blocklist_settings_accounts', true, 'sievefilters', 'blocklist_settings_start', 'after');
add_handler('block_list', 'load_behaviour', true, 'sievefilters', 'load_user_data', 'after');
add_handler('block_list', 'settings_load_imap', true, 'sievefilters', 'load_user_data', 'after');

/* save filter */
setup_base_ajax_page('ajax_sieve_save_filter', 'core');
add_handler('ajax_sieve_save_filter', 'settings_load_imap',  true);
add_handler('ajax_sieve_save_filter', 'sieve_save_filter',  true);
add_output('ajax_sieve_save_filter', 'sieve_save_filter_output',  true);

/* edit filter */
setup_base_ajax_page('ajax_sieve_edit_filter', 'core');
add_handler('ajax_sieve_edit_filter', 'settings_load_imap',  true);
add_handler('ajax_sieve_edit_filter', 'sieve_edit_filter',  true);
add_output('ajax_sieve_edit_filter', 'sieve_edit_filter_output',  true);

/* delete filter */
setup_base_ajax_page('ajax_sieve_delete_filter', 'core');
add_handler('ajax_sieve_delete_filter', 'settings_load_imap',  true);
add_handler('ajax_sieve_delete_filter', 'sieve_delete_filter',  true);
add_output('ajax_sieve_delete_filter', 'sieve_delete_output',  true);

/* save script */
setup_base_ajax_page('ajax_sieve_save_script', 'core');
add_handler('ajax_sieve_save_script', 'settings_load_imap',  true);
add_handler('ajax_sieve_save_script', 'sieve_save_script',  true);
add_output('ajax_sieve_save_script', 'sieve_save_script_output',  true);

/* edit script */
setup_base_ajax_page('ajax_sieve_edit_script', 'core');
add_handler('ajax_sieve_edit_script', 'settings_load_imap',  true);
add_handler('ajax_sieve_edit_script', 'sieve_edit_script',  true);
add_output('ajax_sieve_edit_script', 'sieve_edit_script_output',  true);

/* delete script */
setup_base_ajax_page('ajax_sieve_delete_script', 'core');
add_handler('ajax_sieve_delete_script', 'settings_load_imap',  true);
add_handler('ajax_sieve_delete_script', 'sieve_delete_script',  true);
add_output('ajax_sieve_delete_script', 'sieve_delete_output',  true);

/* block/unblock script */
setup_base_ajax_page('ajax_sieve_block_unblock', 'core');
add_handler('ajax_sieve_block_unblock', 'load_imap_servers_from_config', true, 'imap', 'load_user_data', 'after');
add_handler('ajax_sieve_block_unblock', 'load_smtp_servers_from_config', true, 'imap', 'load_user_data', 'after');
add_handler('ajax_sieve_block_unblock', 'sieve_block_unblock_script',  true);
add_output('ajax_sieve_block_unblock', 'sieve_block_unblock_output',  true);

/* unblock script */
setup_base_ajax_page('ajax_sieve_unblock_sender', 'core');
add_handler('ajax_sieve_unblock_sender', 'load_imap_servers_from_config', true, 'imap', 'load_user_data', 'after');
add_handler('ajax_sieve_unblock_sender', 'load_smtp_servers_from_config', true, 'imap', 'load_user_data', 'after');
add_handler('ajax_sieve_unblock_sender', 'sieve_unblock_sender',  true);
add_output('ajax_sieve_unblock_sender', 'sieve_block_unblock_output',  true);

/* get mailboxes script */
setup_base_ajax_page('ajax_sieve_get_mailboxes', 'core');
add_handler('ajax_sieve_get_mailboxes', 'load_imap_servers_from_config', true, 'imap', 'load_user_data', 'after');
add_handler('ajax_sieve_get_mailboxes', 'settings_load_imap',  true);
add_handler('ajax_sieve_get_mailboxes', 'sieve_get_mailboxes_script',  true);
add_output('ajax_sieve_get_mailboxes', 'sieve_get_mailboxes_output',  true);

/* get mailboxes script */
setup_base_ajax_page('ajax_sieve_block_domain', 'core');
add_handler('ajax_sieve_block_domain', 'load_imap_servers_from_config', true, 'imap', 'load_user_data', 'after');
add_handler('ajax_sieve_block_domain', 'settings_load_imap',  true);
add_handler('ajax_sieve_block_domain', 'sieve_block_domain_script',  true);
add_output('ajax_sieve_block_domain', 'sieve_block_domain_output',  true);

/* change blocking default behaviour */
setup_base_ajax_page('ajax_sieve_block_change_behaviour', 'core');
add_handler('ajax_sieve_block_change_behaviour', 'load_imap_servers_from_config', true, 'imap', 'load_user_data', 'after');
add_handler('ajax_sieve_block_change_behaviour', 'settings_load_imap',  true);
add_handler('ajax_sieve_block_change_behaviour', 'sieve_block_change_behaviour_script',  true);
add_output('ajax_sieve_block_change_behaviour', 'sieve_block_change_behaviour_output',  true);

add_handler('settings', 'process_enable_sieve_filter_setting', true, 'sievefilters', 'save_user_settings', 'before');
add_output('settings', 'enable_sieve_filter_setting', true, 'sievefilters', 'start_general_settings', 'after');

return array(
    'allowed_pages' => array(
        'block_list',
        'sieve_filters',
        'ajax_sieve_save_script',
        'ajax_sieve_edit_script',
        'ajax_sieve_delete_script',
        'ajax_sieve_save_filter',
        'ajax_sieve_edit_filter',
        'ajax_sieve_delete_filter',
        'ajax_sieve_block_unblock',
        'ajax_sieve_unblock_sender',
        'ajax_sieve_get_mailboxes',
        'ajax_sieve_block_domain',
        'ajax_sieve_block_change_behaviour',
    ),
    'allowed_output' => array(
        'imap_server_ids' => array(FILTER_UNSAFE_RAW, false),
        'script_removed' => array(FILTER_UNSAFE_RAW, false),
        'script' => array(FILTER_UNSAFE_RAW, false),
        'conditions' => array(FILTER_UNSAFE_RAW, false),
        'actions' => array(FILTER_UNSAFE_RAW, false),
        'test_type' => array(FILTER_UNSAFE_RAW, false),
        'mailboxes' => array(FILTER_UNSAFE_RAW, false),
        'sieve_detail_display' => array(FILTER_UNSAFE_RAW, false),
    ),
    'allowed_get' => array(),
    'allowed_post' => array(
        'imap_account' => FILTER_SANITIZE_FULL_SPECIAL_CHARS,
        'sieve_script_name' => FILTER_SANITIZE_FULL_SPECIAL_CHARS,
        'sieve_script_priority' => FILTER_VALIDATE_INT,
        'sieve_filter_name' => FILTER_SANITIZE_FULL_SPECIAL_CHARS,
        'sieve_filter_priority' => FILTER_VALIDATE_INT,
        'script' => FILTER_UNSAFE_RAW,
        'current_editing_script' => FILTER_SANITIZE_FULL_SPECIAL_CHARS,
        'current_editing_filter_name' => FILTER_SANITIZE_FULL_SPECIAL_CHARS,
        'conditions_json' => FILTER_UNSAFE_RAW,
        'actions_json' => FILTER_UNSAFE_RAW,
        'filter_test_type' => FILTER_SANITIZE_FULL_SPECIAL_CHARS,
        'imap_msg_uid' => FILTER_VALIDATE_INT,
        'imap_server_id' => FILTER_VALIDATE_INT,
        'folder' => FILTER_SANITIZE_FULL_SPECIAL_CHARS,
        'sender' => FILTER_UNSAFE_RAW,
        'selected_behaviour' => FILTER_SANITIZE_FULL_SPECIAL_CHARS,
        'enable_sieve_filter' => FILTER_VALIDATE_INT,
        'scope' => FILTER_SANITIZE_FULL_SPECIAL_CHARS,
        'block_action' => FILTER_SANITIZE_FULL_SPECIAL_CHARS,
        'reject_message' => FILTER_SANITIZE_FULL_SPECIAL_CHARS,
        'change_behavior' => FILTER_VALIDATE_BOOL
    )
);
