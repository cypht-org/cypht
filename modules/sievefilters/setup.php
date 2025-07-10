<?php

if (!defined('DEBUG_MODE')) { die(); }

handler_source('sievefilters');
output_source('sievefilters');

add_module_to_all_pages('handler', 'sieve_filters_enabled', true, 'sievefilters', 'load_imap_servers_from_config', 'after');
setup_base_page('sieve_filters', 'core');
setup_base_page('block_list', 'core');

// addons and hooks in other modules
add_handler('ajax_imap_message_content', 'sieve_filters_enabled_message_content', true, 'sievefilters', 'imap_message_content', 'after');
add_handler('ajax_hm_folders', 'sieve_filters_enabled', true, 'core', 'load_user_data', 'after');
add_handler('ajax_imap_folders_rename', 'sieve_remame_folder', true, 'imap_folders', 'process_folder_rename', 'after');
add_handler('ajax_imap_folders_delete', 'sieve_can_delete_folder', true, 'imap_folders', 'process_folder_delete', 'before');
add_handler('ajax_imap_status', 'sieve_status', true, 'sievefilters', 'imap_status', 'before');
add_handler('ajax_imap_debug', 'sieve_connect', true, 'imap', 'imap_connect', 'after');

// sieve filter
add_output('sieve_filters', 'sievefilters_settings_start', true, 'sievefilters', 'content_section_start', 'after');
add_output('ajax_hm_folders', 'sievefilters_settings_link', true, 'sievefilters', 'settings_menu_end', 'before');
setup_base_ajax_page('ajax_account_sieve_filters', 'core');
add_handler('ajax_account_sieve_filters', 'settings_load_imap', true, 'sievefilters', 'load_user_data', 'after');
add_handler('ajax_account_sieve_filters', 'load_account_sieve_filters', true, 'sievefilters', 'settings_load_imap', 'after');
add_handler('ajax_account_sieve_filters', 'sieve_filters_enabled', true, 'sievefilters', 'load_account_sieve_filters', 'after');
add_output('ajax_account_sieve_filters', 'account_sieve_filters', true, 'sievefilters');
add_output('ajax_account_sieve_filters', 'check_filter_status', true, 'sievefilters');

// block list
add_output('block_list', 'blocklist_settings_start', true, 'sievefilters', 'content_section_start', 'after');
setup_base_ajax_page('ajax_block_account_sieve_filters', 'core');
add_handler('ajax_block_account_sieve_filters', 'settings_load_imap', true, 'sievefilters', 'load_user_data', 'after');
add_handler('ajax_block_account_sieve_filters', 'load_behaviour', true, 'sievefilters', 'settings_load_imap', 'after');
add_handler('ajax_block_account_sieve_filters', 'load_account_sieve_filters', true, 'sievefilters', 'load_behaviour', 'after');
add_handler('ajax_block_account_sieve_filters', 'sieve_filters_enabled', true, 'sievefilters', 'load_account_sieve_filters', 'after');
add_output('ajax_block_account_sieve_filters', 'blocklist_settings_accounts', true, 'sievefilters');
add_output('ajax_block_account_sieve_filters', 'check_filter_status', true, 'sievefilters');

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

/* liste of sieve blocked */
setup_base_ajax_page('ajax_list_block_sieve', 'core');
add_handler('ajax_list_block_sieve', 'list_block_sieve_script',  true);
add_output('ajax_list_block_sieve', 'list_block_sieve_output',  true);

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

add_handler('home', 'check_sieve_configuration', true, 'nux','load_imap_servers_from_config', 'after');
add_output('home', 'display_sieve_misconfig_alert', true, 'nux', 'start_welcome_dialog', 'after');

/**
 * toggle fliter
 */
setup_base_ajax_page('ajax_sieve_toggle_script_state', 'core');
add_handler('ajax_sieve_toggle_script_state', 'load_imap_servers_from_config', true, 'imap', 'load_user_data', 'after');
add_handler('ajax_sieve_toggle_script_state', 'settings_load_imap', true);
add_handler('ajax_sieve_toggle_script_state', 'sieve_toggle_script_state', true);

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
        'ajax_sieve_toggle_script_state',
        'ajax_list_block_sieve',
        'message_list',
        'ajax_account_sieve_filters',
        'ajax_block_account_sieve_filters',
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
        'imap_extensions_display' => array(FILTER_UNSAFE_RAW, false),
        'script_details' => array(FILTER_UNSAFE_RAW, FILTER_REQUIRE_ARRAY),
        'ajax_list_block_sieve' => array(FILTER_UNSAFE_RAW, false),
        'mailbox' => array(FILTER_UNSAFE_RAW, false),
    ),
    'allowed_get' => array(),
    'allowed_post' => array(
        'script_state' => FILTER_UNSAFE_RAW,
        'imap_account' => FILTER_UNSAFE_RAW,
        'sieve_script_name' => FILTER_UNSAFE_RAW,
        'sieve_script_priority' => FILTER_VALIDATE_INT,
        'sieve_filter_name' => FILTER_UNSAFE_RAW,
        'sieve_filter_priority' => FILTER_VALIDATE_INT,
        'script' => FILTER_UNSAFE_RAW,
        'current_editing_script' => FILTER_UNSAFE_RAW,
        'current_editing_filter_name' => FILTER_UNSAFE_RAW,
        'conditions_json' => FILTER_UNSAFE_RAW,
        'actions_json' => FILTER_UNSAFE_RAW,
        'filter_test_type' => FILTER_UNSAFE_RAW,
        'imap_msg_uid' => FILTER_VALIDATE_INT,
        'imap_server_id' => FILTER_UNSAFE_RAW,
        'folder' => FILTER_UNSAFE_RAW,
        'new_folder' => FILTER_UNSAFE_RAW,
        'sender' => FILTER_UNSAFE_RAW,
        'selected_behaviour' => FILTER_UNSAFE_RAW,
        'enable_sieve_filter' => FILTER_VALIDATE_INT,
        'scope' => FILTER_UNSAFE_RAW,
        'block_action' => FILTER_UNSAFE_RAW,
        'reject_message' => FILTER_UNSAFE_RAW,
        'change_behavior' => FILTER_VALIDATE_BOOL,
        'gen_script' => FILTER_VALIDATE_BOOL,
        'is_screened' => FILTER_VALIDATE_BOOL,
    )
);
