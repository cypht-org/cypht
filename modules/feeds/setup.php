<?php

if (!defined('DEBUG_MODE')) { die(); }

handler_source('feeds');
output_source('feeds');

/* add stuff to the home page */
add_handler('home', 'load_feeds_from_config', true, 'feeds', 'language', 'after');
add_handler('home', 'add_feeds_to_page_data', true, 'feeds', 'load_feeds_from_config', 'after');
add_output('home', 'display_feeds_status', true, 'feeds', 'server_status_start', 'after');
add_output('home', 'feed_ids', true, 'feeds', 'page_js', 'before');

/* servers page data */
add_handler('servers', 'load_feeds_from_config',  true, 'feeds', 'load_user_data', 'after');
add_handler('servers', 'process_add_feed', true, 'feeds', 'load_feeds_from_config', 'after');
add_handler('servers', 'add_feeds_to_page_data', true, 'feeds', 'process_add_feed', 'after');
add_handler('servers', 'save_feeds',  true, 'feeds', 'add_feeds_to_page_data', 'after');
add_output('servers', 'add_feed_dialog', true, 'feeds', 'content_section_start', 'after');
add_output('servers', 'display_configured_feeds', true, 'feeds', 'add_feed_dialog', 'after');
add_output('servers', 'feed_ids', true, 'feeds', 'page_js', 'before');

/* search */
add_handler('search', 'load_feeds_from_config',  true, 'feeds', 'load_user_data', 'after');
add_handler('search', 'feed_list_type', true, 'feeds', 'message_list_type', 'after');
add_handler('search', 'add_feeds_to_page_data', true, 'feeds', 'load_feeds_from_config', 'after');
add_output('search', 'feed_ids', true, 'feeds', 'page_js', 'before');

/* settings page */
add_handler('settings', 'process_unread_feeds_setting', true, 'feeds', 'save_user_settings', 'before'); 
add_output('settings', 'unread_feeds_included', true, 'feeds', 'unread_source_max_setting', 'after');
add_handler('settings', 'process_feed_limit_setting', true, 'feeds', 'save_user_settings', 'before');
add_handler('settings', 'process_feed_since_setting', true, 'feeds', 'save_user_settings', 'before');
add_output('settings', 'start_feed_settings', true, 'feeds', 'end_settings_form', 'before');
add_output('settings', 'feed_since_setting', true, 'feeds', 'start_feed_settings', 'after');
add_output('settings', 'feed_limit_setting', true, 'feeds', 'feed_since_setting', 'after');

add_handler('ajax_hm_folders', 'load_feeds_from_config',  true, 'feeds', 'load_user_data', 'after');
add_handler('ajax_hm_folders', 'load_feed_folders',  true, 'feeds', 'load_feeds_from_config', 'after');
add_handler('ajax_hm_folders', 'add_feeds_to_page_data', true, 'feeds', 'load_feeds_from_config', 'after');
add_output('ajax_hm_folders', 'filter_feed_folders',  true, 'feeds', 'folder_list_content_start', 'before');

/* message action callback */
add_handler('ajax_message_action', 'load_feeds_from_config', true, 'feeds', 'load_user_data', 'after');
add_handler('ajax_message_action', 'feed_message_action', true, 'feeds', 'load_feeds_from_config', 'after');
add_handler('ajax_message_action', 'save_feeds', true, 'feeds', 'feed_message_action', 'after');

/* message list page */
add_handler('message_list', 'load_feeds_from_config', true, 'feeds', 'load_user_data', 'after');
add_handler('message_list', 'feed_list_type', true, 'feeds', 'message_list_type', 'after');
add_handler('message_list', 'add_feeds_to_page_data', true, 'feeds', 'feed_list_type', 'after');
add_output('message_list', 'feed_ids', true, 'feeds', 'page_js', 'before');

/* combined inbox */
add_handler('ajax_feed_combined_inbox', 'login', false, 'core');
add_handler('ajax_feed_combined_inbox', 'load_user_data', true, 'core');
add_handler('ajax_feed_combined_inbox', 'message_list_type', true, 'core');
add_handler('ajax_feed_combined_inbox', 'feed_list_type', true);
add_handler('ajax_feed_combined_inbox', 'load_feeds_from_config',  true);
add_handler('ajax_feed_combined_inbox', 'feed_list_content',  true);
add_handler('ajax_feed_combined_inbox', 'date', true, 'core');
add_output('ajax_feed_combined_inbox', 'filter_feed_list_data', true);

/* combined feeds */
add_handler('ajax_feed_combined', 'login', false, 'core');
add_handler('ajax_feed_combined', 'load_user_data', true, 'core');
add_handler('ajax_feed_combined', 'message_list_type', true, 'core');
add_handler('ajax_feed_combined', 'feed_list_type', true);
add_handler('ajax_feed_combined', 'load_feeds_from_config',  true);
add_handler('ajax_feed_combined', 'feed_list_content',  true);
add_handler('ajax_feed_combined', 'date', true, 'core');
add_output('ajax_feed_combined', 'filter_feed_list_data', true);

/* unread */
add_handler('ajax_feed_unread', 'login', false, 'core');
add_handler('ajax_feed_unread', 'load_user_data', true, 'core');
add_handler('ajax_feed_unread', 'message_list_type', true, 'core');
add_handler('ajax_feed_unread', 'feed_list_type', true);
add_handler('ajax_feed_unread', 'load_feeds_from_config',  true);
add_handler('ajax_feed_unread', 'feed_list_content',  true);
add_handler('ajax_feed_unread', 'date', true, 'core');
add_output('ajax_feed_unread', 'filter_feed_list_data', true);


/* feed list */
add_handler('ajax_feed_list_display', 'login', false, 'core');
add_handler('ajax_feed_list_display', 'load_user_data', true, 'core');
add_handler('ajax_feed_list_display', 'message_list_type', false, 'core');
add_handler('ajax_feed_list_display', 'feed_list_type', false);
add_handler('ajax_feed_list_display', 'load_feeds_from_config',  true);
add_handler('ajax_feed_list_display', 'feed_list_content',  true);
add_handler('ajax_feed_list_display', 'date', true, 'core');
add_output('ajax_feed_list_display', 'filter_feed_list_data', true);

add_handler('message', 'load_feeds_from_config', true, 'feeds', 'load_user_data', 'after');
add_handler('message', 'feed_list_type', true, 'feeds', 'message_list_type', 'after');
add_handler('message', 'add_feeds_to_page_data',  true, 'feeds', 'load_feeds_from_config', 'after');

/* message view */
add_handler('ajax_feed_item_content', 'login', false, 'core');
add_handler('ajax_feed_item_content', 'load_user_data', true, 'core');
add_handler('ajax_feed_item_content', 'load_feeds_from_config',  true);
add_handler('ajax_feed_item_content', 'feed_item_content',  true);
add_handler('ajax_feed_item_content', 'save_feeds',  true);
add_handler('ajax_feed_item_content', 'date', true, 'core');
add_output('ajax_feed_item_content', 'filter_feed_item_content', true);

add_handler('ajax_feed_debug', 'login', false, 'core');
add_handler('ajax_feed_debug', 'load_user_data', true, 'core');
add_handler('ajax_feed_debug', 'load_feeds_from_config',  true);
add_handler('ajax_feed_debug', 'delete_feed', true);
add_handler('ajax_feed_debug', 'feed_connect', true);
add_handler('ajax_feed_debug', 'save_feeds',  true);
add_handler('ajax_feed_debug', 'save_user_data',  true, 'core');
add_handler('ajax_feed_debug', 'date', true, 'core');

add_handler('ajax_feed_status', 'login', false, 'core');
add_handler('ajax_feed_status', 'load_user_data', true, 'core');
add_handler('ajax_feed_status', 'load_feeds_from_config',  true);
add_handler('ajax_feed_status', 'feed_status',  true);
add_handler('ajax_feed_status', 'date', true, 'core');
add_output('ajax_feed_status', 'filter_feed_status_data', true);

return array(

    'allowed_pages' => array(
        'ajax_feed_combined_inbox',
        'ajax_feed_list_display',
        'ajax_feed_item_content',
        'ajax_feed_combined',
        'ajax_feed_debug',
        'ajax_feed_status'
    ),

    'allowed_post' => array(
        'feed_id' => FILTER_VALIDATE_INT,
        'delete_feed' => FILTER_VALIDATE_INT,
        'feed_connect' => FILTER_VALIDATE_INT,
        'feed_server_ids' => FILTER_SANITIZE_STRING,
        'submit_feed' => FILTER_SANITIZE_STRING,
        'new_feed_name' => FILTER_SANITIZE_STRING,
        'feed_delete' => FILTER_VALIDATE_INT,
        'new_feed_address' => FILTER_SANITIZE_STRING,
        'unread_exclude_feeds' => FILTER_VALIDATE_INT,
        'feed_list_path' => FILTER_SANITIZE_STRING,
        'feed_uid' => FILTER_SANITIZE_STRING,
        'feed_since' => FILTER_SANITIZE_STRING,
        'feed_limit' => FILTER_VALIDATE_INT,
        'feed_search' => FILTER_VALIDATE_INT,
    )
);

?>
