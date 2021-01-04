<?php

if (!defined('DEBUG_MODE')) { die(); }

handler_source('imap');
output_source('imap');

/* setup imap info for all pages for background unread checks */
add_module_to_all_pages('handler', 'load_imap_servers_from_config', true, 'imap', 'load_user_data', 'after');
add_module_to_all_pages('handler', 'imap_oauth2_token_check', true, 'imap', 'load_imap_servers_from_config', 'after');
add_module_to_all_pages('handler', 'load_imap_servers_for_message_list', true, 'imap', 'imap_oauth2_token_check', 'after');
add_module_to_all_pages('handler', 'add_imap_servers_to_page_data', true, 'imap', 'imap_oauth2_token_check', 'after');
add_module_to_all_pages('handler', 'prefetch_imap_folders', true, 'imap', 'add_imap_servers_to_page_data', 'after');
add_module_to_all_pages('output', 'prefetch_imap_folder_ids', true, 'imap', 'content_start', 'after');

/* add stuff to the info page */
add_output('info', 'display_imap_status', true, 'imap', 'server_status_start', 'after');
add_output('info', 'imap_server_ids', true, 'imap', 'page_js', 'before');

/* servers page data */
add_handler('servers', 'process_add_imap_server', true, 'imap', 'message_list_type', 'after');
add_handler('servers', 'process_add_jmap_server', true, 'imap', 'process_add_imap_server', 'after');
add_handler('servers', 'save_imap_servers',  true, 'imap', 'process_add_jmap_server', 'after');
add_output('servers', 'add_imap_server_dialog', true, 'imap', 'server_content_start', 'after');
add_output('servers', 'display_configured_imap_servers', true, 'imap', 'add_imap_server_dialog', 'after');
add_output('servers', 'add_jmap_server_dialog', true, 'imap', 'display_configured_imap_servers', 'after');
add_output('servers', 'display_configured_jmap_servers', true, 'imap', 'add_jmap_server_dialog', 'after');
add_output('servers', 'imap_server_ids', true, 'imap', 'page_js', 'before');

/* settings page data */
add_handler('settings', 'process_sent_since_setting', true, 'imap', 'date', 'after');
add_handler('settings', 'process_sent_source_max_setting', true, 'imap', 'date', 'after');
add_handler('settings', 'process_text_only_setting', true, 'imap', 'date', 'after');
add_handler('settings', 'process_msg_part_icons', true, 'imap', 'date', 'after');
add_handler('settings', 'process_simple_msg_parts', true, 'imap', 'date', 'after');
add_handler('settings', 'process_imap_per_page_setting', true, 'imap', 'date', 'after');
add_output('settings', 'imap_server_ids', true, 'imap', 'page_js', 'before');
add_output('settings', 'start_sent_settings', true, 'imap', 'end_settings_form', 'before');
add_output('settings', 'sent_since_setting', true, 'imap', 'start_sent_settings', 'after');
add_output('settings', 'sent_source_max_setting', true, 'imap', 'sent_since_setting', 'after');
add_output('settings', 'text_only_setting', true, 'imap', 'list_style_setting', 'after');
add_output('settings', 'imap_msg_icons_setting', true, 'imap', 'msg_list_icons_setting', 'after');
add_output('settings', 'imap_simple_msg_parts', true, 'imap', 'imap_msg_icons_setting', 'after');
add_output('settings', 'imap_per_page_setting', true, 'imap', 'imap_simple_msg_parts', 'after');

/* compose page data */
add_output('compose', 'imap_server_ids', true, 'imap', 'page_js', 'before');
add_handler('compose', 'imap_forward_attachments', true, 'imap', 'add_imap_servers_to_page_data', 'after');
add_handler('compose', 'imap_mark_as_answered', true, 'imap', 'process_compose_form_submit', 'after');
add_handler('compose', 'imap_save_sent', true, 'imap', 'imap_mark_as_answered', 'after');
add_handler('compose', 'imap_unflag_on_send', true, 'imap', 'imap_save_sent', 'after');
add_output('compose', 'imap_unflag_on_send_controls', true, 'imap', 'compose_form_end', 'before');

/* search page data */
add_handler('search', 'load_imap_servers_for_search',  true, 'imap', 'message_list_type', 'after');
add_handler('search', 'imap_message_list_type', true, 'imap', 'message_list_type', 'after');

/* message list pages */
add_handler('message_list', 'imap_message_list_type', true, 'imap', 'message_list_type', 'after');
add_output('message_list', 'imap_custom_controls', true, 'imap', 'message_list_heading', 'before');
add_output('message_list', 'move_copy_controls', true, 'imap', 'message_list_heading', 'before');

/* message view page */
add_handler('message', 'imap_download_message', true, 'imap', 'message_list_type', 'after');
add_handler('message', 'imap_message_list_type', true, 'imap', 'message_list_type', 'after');
add_output('message', 'imap_server_ids', true, 'imap', 'page_js', 'before');

/* ajax mark as read */
setup_base_ajax_page('ajax_imap_mark_as_read', 'core');
add_handler('ajax_imap_mark_as_read', 'load_imap_servers_from_config', true, 'imap', 'load_user_data', 'after');
add_handler('ajax_imap_mark_as_read', 'imap_oauth2_token_check', true, 'imap', 'load_imap_servers_from_config', 'after');
add_handler('ajax_imap_mark_as_read', 'imap_mark_as_read',  true, 'imap', 'imap_oauth2_token_check', 'after');
add_handler('ajax_imap_mark_as_read', 'save_imap_cache',  true, 'imap', 'imap_mark_as_read', 'after');
add_handler('ajax_imap_mark_as_read', 'save_imap_servers',  true, 'imap', 'save_imap_cache', 'after');

/* page not found */
//add_output('notfound', 'imap_message_list', true, 'imap', 'folder_list_end', 'before');
//add_output('notfound', 'imap_server_ids', true, 'imap', 'page_js', 'before');

/* folder list */
add_handler('ajax_hm_folders', 'load_imap_servers_from_config',  true, 'imap', 'load_user_data', 'after');
add_handler('ajax_hm_folders', 'imap_oauth2_token_check', true, 'imap', 'load_imap_servers_from_config', 'after');
add_handler('ajax_hm_folders', 'load_imap_folders',  true, 'imap', 'load_imap_servers_from_config', 'after');
add_handler('ajax_hm_folders', 'add_imap_servers_to_page_data',  true, 'imap', 'load_imap_servers_from_config', 'after');
add_output('ajax_hm_folders', 'filter_imap_folders',  true, 'imap', 'folder_list_content_start', 'before');

/* ajax server setup callback data */
setup_base_ajax_page('ajax_imap_debug', 'core');
add_handler('ajax_imap_debug', 'load_imap_servers_from_config',  true);
add_handler('ajax_imap_debug', 'imap_oauth2_token_check', true);
add_handler('ajax_imap_debug', 'imap_hide', true);
add_handler('ajax_imap_debug', 'imap_connect', true);
add_handler('ajax_imap_debug', 'imap_delete', true);
add_handler('ajax_imap_debug', 'imap_forget', true);
add_handler('ajax_imap_debug', 'imap_save', true);
add_handler('ajax_imap_debug', 'save_imap_cache',  true);
add_handler('ajax_imap_debug', 'save_imap_servers',  true);
add_handler('ajax_imap_debug', 'save_user_data',  true, 'core');

/* flag a message from the message view page */
setup_base_ajax_page('ajax_imap_flag_message', 'core');
add_handler('ajax_imap_flag_message', 'load_imap_servers_from_config',  true);
add_handler('ajax_imap_flag_message', 'imap_oauth2_token_check', true);
add_handler('ajax_imap_flag_message', 'close_session_early',  true, 'core');
add_handler('ajax_imap_flag_message', 'flag_imap_message', true);
add_handler('ajax_imap_flag_message', 'save_imap_cache',  true);
add_handler('ajax_imap_flag_message', 'save_imap_servers',  true);

/* ajax message content */
setup_base_ajax_page('ajax_imap_message_content', 'core');
add_handler('ajax_imap_message_content', 'load_imap_servers_from_config',  true);
add_handler('ajax_imap_message_content', 'imap_bust_cache',  true);
add_handler('ajax_imap_message_content', 'imap_oauth2_token_check', true);
add_handler('ajax_imap_message_content', 'imap_message_content',  true);
add_handler('ajax_imap_message_content', 'save_imap_cache',  true);
add_handler('ajax_imap_message_content', 'save_imap_servers',  true);
add_handler('ajax_imap_message_content', 'close_session_early',  true, 'core');
add_output('ajax_imap_message_content', 'filter_message_headers', true);
add_output('ajax_imap_message_content', 'filter_message_body', true);
add_output('ajax_imap_message_content', 'filter_message_struct', true);

/* ajax sent callback data */
setup_base_ajax_page('ajax_imap_sent', 'core');
add_handler('ajax_imap_sent', 'message_list_type', true, 'core');
add_handler('ajax_imap_sent', 'imap_message_list_type', true);
add_handler('ajax_imap_sent', 'load_imap_servers_from_config',  true);
add_handler('ajax_imap_sent', 'imap_oauth2_token_check', true);
add_handler('ajax_imap_sent', 'close_session_early',  true, 'core');
add_handler('ajax_imap_sent', 'imap_sent',  true);
add_handler('ajax_imap_sent', 'save_imap_cache',  true);
add_output('ajax_imap_sent', 'filter_sent_data', true);

/* ajax folder status callback data */
setup_base_ajax_page('ajax_imap_folder_status', 'core');
add_handler('ajax_imap_folder_status', 'message_list_type', true, 'core');
add_handler('ajax_imap_folder_status', 'imap_message_list_type', true);
add_handler('ajax_imap_folder_status', 'load_imap_servers_from_config',  true);
add_handler('ajax_imap_folder_status', 'imap_oauth2_token_check', true);
add_handler('ajax_imap_folder_status', 'close_session_early',  true, 'core');
add_handler('ajax_imap_folder_status', 'imap_folder_status',  true, 'imap');

/* ajax unread callback data */
setup_base_ajax_page('ajax_imap_unread', 'core');
add_handler('ajax_imap_unread', 'message_list_type', true, 'core');
add_handler('ajax_imap_unread', 'load_imap_servers_from_config',  true);
add_handler('ajax_imap_unread', 'imap_oauth2_token_check', true);
add_handler('ajax_imap_unread', 'close_session_early',  true, 'core');
add_handler('ajax_imap_unread', 'imap_unread',  true);
add_handler('ajax_imap_unread', 'save_imap_cache',  true);
add_output('ajax_imap_unread', 'filter_unread_data', true);

/* ajax add/remove to combined view */
setup_base_ajax_page('ajax_imap_update_combined_source', 'core');
add_handler('ajax_imap_update_combined_source', 'load_imap_servers_from_config',  true);
add_handler('ajax_imap_update_combined_source', 'process_imap_source_update',  true);
add_handler('ajax_imap_update_combined_source', 'close_session_early',  true, 'core');

/* ajax status callback data */
setup_base_ajax_page('ajax_imap_status', 'core');
add_handler('ajax_imap_status', 'load_imap_servers_from_config',  true);
add_handler('ajax_imap_status', 'imap_oauth2_token_check', true);
add_handler('ajax_imap_status', 'close_session_early',  true, 'core');
add_handler('ajax_imap_status', 'imap_status',  true);
add_output('ajax_imap_status', 'filter_imap_status_data', true);

/* move/copy callback */
setup_base_ajax_page('ajax_imap_move_copy_action', 'core');
add_handler('ajax_imap_move_copy_action', 'load_imap_servers_from_config',  true);
add_handler('ajax_imap_move_copy_action', 'imap_oauth2_token_check', true);
add_handler('ajax_imap_move_copy_action', 'imap_process_move',  true);
add_handler('ajax_imap_move_copy_action', 'save_imap_cache',  true);
add_handler('ajax_imap_move_copy_action', 'close_session_early',  true, 'core');

/* ajax flagged callback data */
setup_base_ajax_page('ajax_imap_flagged', 'core');
add_handler('ajax_imap_flagged', 'message_list_type', true, 'core');
add_handler('ajax_imap_flagged', 'imap_message_list_type', true);
add_handler('ajax_imap_flagged', 'load_imap_servers_from_config',  true);
add_handler('ajax_imap_flagged', 'imap_oauth2_token_check', true);
add_handler('ajax_imap_flagged', 'close_session_early',  true, 'core');
add_handler('ajax_imap_flagged', 'imap_flagged',  true);
add_output('ajax_imap_flagged', 'filter_flagged_data', true);

/* delete message callback */
setup_base_ajax_page('ajax_imap_delete_message', 'core');
add_handler('ajax_imap_delete_message', 'message_list_type', true, 'core');
add_handler('ajax_imap_delete_message', 'imap_message_list_type', true);
add_handler('ajax_imap_delete_message', 'load_imap_servers_from_config',  true);
add_handler('ajax_imap_delete_message', 'imap_oauth2_token_check', true);
add_handler('ajax_imap_delete_message', 'close_session_early',  true, 'core');
add_handler('ajax_imap_delete_message', 'imap_delete_message',  true);

/* archive message callback */
setup_base_ajax_page('ajax_imap_archive_message', 'core');
add_handler('ajax_imap_archive_message', 'message_list_type', true, 'core');
add_handler('ajax_imap_archive_message', 'imap_message_list_type', true);
add_handler('ajax_imap_archive_message', 'load_imap_servers_from_config',  true);
add_handler('ajax_imap_archive_message', 'imap_oauth2_token_check', true);
add_handler('ajax_imap_archive_message', 'close_session_early',  true, 'core');
add_handler('ajax_imap_archive_message', 'imap_archive_message',  true);


/* ajax message action callback */
add_handler('ajax_message_action', 'load_imap_servers_from_config',  true, 'imap', 'load_user_data', 'after');
add_handler('ajax_message_action', 'imap_oauth2_token_check', true, 'imap', 'load_imap_servers_from_config', 'after');
add_handler('ajax_message_action', 'imap_message_action', true, 'imap', 'load_imap_servers_from_config', 'after');
add_handler('ajax_message_action', 'save_imap_cache',  true, 'imap', 'imap_message_action', 'after');
add_handler('ajax_message_action', 'save_imap_servers',  true, 'imap', 'save_imap_cache', 'after');

/* expand folder */
setup_base_ajax_page('ajax_imap_folder_expand', 'core');
add_handler('ajax_imap_folder_expand', 'load_imap_servers_from_config',  true);
add_handler('ajax_imap_folder_expand', 'imap_oauth2_token_check', true);
add_handler('ajax_imap_folder_expand', 'imap_folder_expand',  true);
add_handler('ajax_imap_folder_expand', 'save_imap_cache',  true);
add_output('ajax_imap_folder_expand', 'filter_expanded_folder_data', true);

/* select folder */
setup_base_ajax_page('ajax_imap_folder_display', 'core');
add_handler('ajax_imap_folder_display', 'message_list_type', true, 'core');
add_handler('ajax_imap_folder_display', 'imap_message_list_type', true);
add_handler('ajax_imap_folder_display', 'load_imap_servers_from_config',  true);
add_handler('ajax_imap_folder_display', 'imap_oauth2_token_check', true);
add_handler('ajax_imap_folder_display', 'imap_folder_page',  true);
add_handler('ajax_imap_folder_display', 'save_imap_cache',  true);
add_handler('ajax_imap_folder_display', 'close_session_early',  true, 'core');
add_output('ajax_imap_folder_display', 'filter_folder_page', true);

/* search results */
setup_base_ajax_page('ajax_imap_search', 'core');
add_handler('ajax_imap_search', 'default_sort_order_setting', true, 'core', 'load_user_data', 'after');
add_handler('ajax_imap_search', 'message_list_type', true, 'core');
add_handler('ajax_imap_search', 'imap_message_list_type', true);
add_handler('ajax_imap_search', 'load_imap_servers_from_config',  true);
add_handler('ajax_imap_search', 'imap_oauth2_token_check', true);
add_handler('ajax_imap_search', 'close_session_early',  true, 'core');
add_handler('ajax_imap_search', 'imap_search',  true);
add_output('ajax_imap_search', 'filter_imap_search', true);

/* advanced search results */
add_handler('ajax_adv_search', 'default_sort_order_setting', true, 'core', 'load_user_data', 'after');
add_handler('ajax_adv_search', 'load_imap_servers_from_config',  true, 'imap', 'load_user_data', 'after');
add_handler('ajax_adv_search', 'imap_oauth2_token_check', true, 'imap', 'load_imap_servers_from_config', 'after');

/* combined inbox */
setup_base_ajax_page('ajax_imap_combined_inbox', 'core');
add_handler('ajax_imap_combined_inbox', 'message_list_type', true, 'core');
add_handler('ajax_imap_combined_inbox', 'imap_message_list_type', true);
add_handler('ajax_imap_combined_inbox', 'load_imap_servers_from_config',  true);
add_handler('ajax_imap_combined_inbox', 'imap_oauth2_token_check', true);
add_handler('ajax_imap_combined_inbox', 'close_session_early',  true, 'core');
add_handler('ajax_imap_combined_inbox', 'imap_combined_inbox',  true);
add_output('ajax_imap_combined_inbox', 'filter_combined_inbox', true);

/* all email section */
setup_base_ajax_page('ajax_imap_all_email', 'core');
add_handler('ajax_imap_all_email', 'message_list_type', true, 'core');
add_handler('ajax_imap_all_email', 'imap_message_list_type', true);
add_handler('ajax_imap_all_email', 'load_imap_servers_from_config',  true);
add_handler('ajax_imap_all_email', 'imap_oauth2_token_check', true);
add_handler('ajax_imap_all_email', 'close_session_early',  true, 'core');
add_handler('ajax_imap_all_email', 'imap_combined_inbox',  true);
add_output('ajax_imap_all_email', 'filter_all_email', true);

add_handler('ajax_update_server_pw', 'load_imap_servers_from_config', true, 'imap', 'load_user_data', 'after');
add_handler('ajax_update_server_pw', 'save_imap_servers', true, 'imap', 'save_user_data', 'before');

/* allowed input */
return array(
    'allowed_pages' => array(
        'ajax_imap_debug',
        'ajax_imap_status',
        'ajax_imap_unread',
        'ajax_imap_sent',
        'ajax_imap_flagged',
        'ajax_imap_folder_expand',
        'ajax_imap_folder_display',
        'ajax_imap_combined_inbox',
        'ajax_imap_search',
        'ajax_unread_count',
        'ajax_imap_message_content',
        'ajax_imap_save_folder_state',
        'ajax_imap_message_action',
        'ajax_imap_delete_message',
        'ajax_imap_archive_message',
        'ajax_imap_flag_message',
        'ajax_imap_update_combined_source',
        'ajax_imap_mark_as_read',
        'ajax_imap_move_copy_action',
        'ajax_imap_folder_status',
    ),

    'allowed_output' => array(
        'imap_connect_status' => array(FILTER_SANITIZE_STRING, false),
        'connect_status' => array(FILTER_SANITIZE_STRING, false),
        'auto_sent_folder' => array(FILTER_SANITIZE_STRING, false),
        'imap_connect_time' => array(FILTER_SANITIZE_STRING, false),
        'imap_detail_display' => array(FILTER_UNSAFE_RAW, false),
        'imap_status_display' => array(FILTER_UNSAFE_RAW, false),
        'imap_status_server_id' => array(FILTER_VALIDATE_INT, false),
        'imap_expanded_folder_path' => array(FILTER_SANITIZE_STRING, false),
        'imap_expanded_folder_formatted' => array(FILTER_UNSAFE_RAW, false),
        'imap_server_ids' => array(FILTER_SANITIZE_STRING, false),
        'imap_server_id' => array(FILTER_VALIDATE_INT, false),
        'combined_inbox_server_ids' => array(FILTER_SANITIZE_STRING, false),
        'imap_delete_error' => array(FILTER_VALIDATE_BOOLEAN, false),
        'move_count' => array(FILTER_SANITIZE_STRING, FILTER_REQUIRE_ARRAY),
    ),

    'allowed_get' => array(
        'imap_server_id' => FILTER_VALIDATE_INT,
        'imap_download_message' => FILTER_VALIDATE_BOOLEAN,
        'imap_msg_part' => FILTER_SANITIZE_STRING
    ),

    'allowed_post' => array(
        'server_port' => FILTER_VALIDATE_INT,
        'server' => FILTER_SANITIZE_STRING,
        'imap_server_id' => FILTER_VALIDATE_INT,
        'imap_server_ids' => FILTER_SANITIZE_STRING,
        'imap_user' => FILTER_SANITIZE_STRING,
        'imap_pass' => FILTER_UNSAFE_RAW,
        'text_only' => FILTER_VALIDATE_BOOLEAN,
        'msg_part_icons' => FILTER_VALIDATE_BOOLEAN,
        'simple_msg_parts' => FILTER_VALIDATE_BOOLEAN,
        'imap_allow_images' => FILTER_VALIDATE_BOOLEAN,
        'imap_delete' => FILTER_SANITIZE_STRING,
        'imap_connect' => FILTER_SANITIZE_STRING,
        'imap_remember' => FILTER_VALIDATE_INT,
        'imap_folder_ids' => FILTER_SANITIZE_STRING,
        'imap_forget' => FILTER_SANITIZE_STRING,
        'imap_save' => FILTER_SANITIZE_STRING,
        'submit_imap_server' => FILTER_SANITIZE_STRING,
        'submit_jmap_server' => FILTER_SANITIZE_STRING,
        'new_jmap_address' => FILTER_SANITIZE_URL,
        'new_jmap_name' => FILTER_SANITIZE_STRING,
        'new_imap_address' => FILTER_SANITIZE_STRING,
        'new_imap_hidden' => FILTER_VALIDATE_BOOLEAN,
        'new_imap_port' => FILTER_VALIDATE_INT,
        'new_imap_name' => FILTER_SANITIZE_STRING,
        'tls' => FILTER_VALIDATE_BOOLEAN,
        'folder' => FILTER_SANITIZE_STRING,
        'force_update' => FILTER_VALIDATE_BOOLEAN,
        'imap_folder_state' => FILTER_UNSAFE_RAW,
        'imap_msg_uid' => FILTER_SANITIZE_STRING,
        'imap_msg_part' => FILTER_SANITIZE_STRING,
        'imap_prefetch' => FILTER_VALIDATE_BOOLEAN,
        'hide_imap_server' => FILTER_VALIDATE_BOOLEAN,
        'imap_flag_state' => FILTER_SANITIZE_STRING,
        'combined_source_state' => FILTER_VALIDATE_INT,
        'list_path' => FILTER_SANITIZE_STRING,
        'imap_move_ids' => FILTER_SANITIZE_STRING,
        'imap_move_to' => FILTER_SANITIZE_STRING,
        'imap_move_action' => FILTER_SANITIZE_STRING,
        'sent_since' => FILTER_SANITIZE_STRING,
        'sent_per_source' => FILTER_SANITIZE_STRING,
        'imap_move_page' => FILTER_SANITIZE_STRING,
        'compose_unflag_send' => FILTER_VALIDATE_BOOLEAN,
        'imap_per_page' => FILTER_VALIDATE_INT
    )
);


