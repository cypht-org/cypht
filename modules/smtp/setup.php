<?php

if (!defined('DEBUG_MODE')) { die(); }

handler_source('smtp');
output_source('smtp');

add_module_to_all_pages('handler', 'smtp_default_server', true, 'smtp', 'load_user_data', 'after');
add_handler('compose', 'load_smtp_reply_to_details', true, 'smtp', 'load_user_data', 'after');
add_handler('compose', 'load_smtp_is_imap_draft', true, 'smtp', 'load_user_data', 'after');
add_handler('compose', 'smtp_from_replace', true, 'smtp', 'load_user_data', 'after');
add_handler('compose', 'load_smtp_servers_from_config', true, 'smtp', 'load_smtp_reply_to_details', 'after');
add_handler('compose', 'add_smtp_servers_to_page_data', true, 'smtp', 'load_smtp_servers_from_config', 'after');
add_handler('compose', 'process_compose_form_submit', true, 'smtp', 'load_smtp_servers_from_config', 'after');
add_output('compose', 'compose_form_start', true, 'smtp', 'content_section_start', 'after');
add_output('compose', 'compose_form_draft_list', true, 'smtp', 'compose_form_start', 'before');
add_output('compose', 'compose_form_content', true, 'smtp', 'compose_form_start', 'after');
add_output('compose', 'compose_form_end', true, 'smtp', 'compose_form_content', 'after');
add_output('compose', 'compose_form_attach', true, 'smtp', 'compose_form_end', 'after');
add_handler('compose', 'load_smtp_is_imap_forward', true, 'smtp', 'load_user_data', 'after');


add_handler('functional_api', 'default_smtp_server', true, 'smtp');

add_handler('profiles', 'load_smtp_servers_from_config', true, 'smtp', 'load_user_data', 'after');
add_handler('profiles', 'add_smtp_servers_to_page_data', true, 'smtp', 'load_smtp_servers_from_config', 'after');

/* servers page */
add_handler('servers', 'load_smtp_servers_from_config', true, 'smtp', 'language', 'after');
add_handler('servers', 'process_add_smtp_server', true, 'smtp', 'load_smtp_servers_from_config', 'after');
add_handler('servers', 'add_smtp_servers_to_page_data', true, 'smtp', 'process_add_smtp_server', 'after');
add_handler('servers', 'save_smtp_servers', true, 'smtp', 'add_smtp_servers_to_page_data', 'after');
add_output('servers', 'stepper_setup_server_smtp', true, 'smtp', 'server_config_stepper', 'after');
add_output('servers', 'display_configured_smtp_servers', true, 'smtp', 'server_config_stepper_accordion_end_part', 'before');

add_handler('settings', 'process_compose_type', true, 'smtp', 'save_user_settings', 'before');
add_handler('settings', 'process_auto_bcc', true, 'smtp', 'save_user_settings', 'before');
add_output('settings', 'compose_type_setting', true, 'smtp', 'start_general_settings', 'after');
add_output('settings', 'auto_bcc_setting', true, 'smtp', 'compose_type_setting', 'after');
add_handler('settings', 'attachment_dir', true, 'smtp', 'save_user_settings', 'after');
add_output('settings', 'attachment_setting', true, 'smtp', 'compose_type_setting', 'after');

add_output('settings', 'enable_attachment_reminder_setting', true, 'smtp', 'attachment_setting', 'after');
add_handler('settings', 'process_enable_attachment_reminder_setting', true, 'smtp', 'save_user_settings', 'before');

/* ajax server setup callback data */
add_handler('ajax_smtp_debug', 'login', false, 'core');
add_handler('ajax_smtp_debug', 'load_user_data',  true, 'core');
add_handler('ajax_smtp_debug', 'load_smtp_servers_from_config',  true);
add_handler('ajax_smtp_debug', 'add_smtp_servers_to_page_data',  true);
add_handler('ajax_smtp_debug', 'smtp_connect', true);
add_handler('ajax_smtp_debug', 'smtp_delete', true);
add_handler('ajax_smtp_debug', 'smtp_forget', true);
add_handler('ajax_smtp_debug', 'smtp_save', true);
add_handler('ajax_smtp_debug', 'save_smtp_servers', true);
add_handler('ajax_smtp_debug', 'save_user_data',  true, 'core');
add_handler('ajax_smtp_debug', 'date', true, 'core');
add_handler('ajax_smtp_debug', 'http_headers', true, 'core');

/* save draft ajax request */
add_handler('ajax_smtp_save_draft', 'load_imap_servers_from_config', true, 'imap', 'load_user_data', 'after');
add_handler('ajax_smtp_save_draft', 'load_smtp_servers_from_config', true, 'imap', 'load_user_data', 'after');
add_handler('ajax_smtp_save_draft', 'login', false, 'core');
add_handler('ajax_smtp_save_draft', 'load_user_data',  true, 'core');
add_handler('ajax_smtp_save_draft', 'smtp_save_draft',  true);
add_handler('ajax_smtp_save_draft', 'date', true, 'core');
add_handler('ajax_smtp_save_draft', 'http_headers', true, 'core');

/* resumable test chunk */
add_handler('ajax_get_test_chunk', 'load_imap_servers_from_config', true, 'imap', 'load_user_data', 'after');
add_handler('ajax_get_test_chunk', 'load_smtp_servers_from_config', true, 'imap', 'load_user_data', 'after');
add_handler('ajax_get_test_chunk', 'login', false, 'core');
add_handler('ajax_get_test_chunk', 'load_user_data',  true, 'core');
add_handler('ajax_get_test_chunk', 'get_test_chunk',  true);

/* resumable upload chunk */
add_handler('ajax_upload_chunk', 'load_imap_servers_from_config', true, 'smtp', 'load_user_data', 'after');
add_handler('ajax_upload_chunk', 'load_smtp_servers_from_config', true, 'smtp', 'load_user_data', 'after');
add_handler('ajax_upload_chunk', 'login', false, 'core');
add_handler('ajax_upload_chunk', 'load_user_data',  true, 'core');
add_handler('ajax_upload_chunk', 'compose_profile_data',  true, 'profiles');
add_handler('ajax_upload_chunk', 'upload_chunk',  true);

setup_base_ajax_page('ajax_smtp_delete_draft', 'core');
add_handler('ajax_smtp_delete_draft', 'process_delete_draft', true, 'smtp', 'load_user_data', 'after');

/* folder list link */
add_output('ajax_hm_folders', 'compose_page_link', true, 'smtp', 'logout_menu_item', 'before');
add_handler('ajax_hm_folders', 'smtp_auto_bcc_check',  true, 'smtp', 'load_imap_servers_from_config', 'after');
add_output('ajax_hm_folders', 'sent_folder_link', true, 'smtp', 'logout_menu_item', 'before');

add_handler('ajax_update_server_pw', 'load_smtp_servers_from_config', true, 'smtp', 'load_user_data', 'after');

setup_base_ajax_page('ajax_profiles_status', 'core');
add_handler('ajax_profiles_status', 'load_imap_servers_from_config', true, 'imap', 'load_user_data', 'after');
add_handler('ajax_profiles_status', 'profile_status', true, 'smtp', 'load_imap_servers_from_config', 'after');

/* resumable clear chunks */
add_handler('ajax_clear_attachment_chunks', 'login', false, 'core');
add_handler('ajax_clear_attachment_chunks', 'load_user_data',  true, 'core');
add_handler('ajax_clear_attachment_chunks', 'clear_attachment_chunks',  true);

/* send delayed emails */
setup_base_ajax_page('ajax_send_scheduled_messages', 'core');
add_handler('ajax_send_scheduled_messages', 'load_imap_servers_from_config', true, 'imap', 'load_user_data', 'after');
add_handler('ajax_send_scheduled_messages', 'load_smtp_servers_from_config', true, 'smtp', 'load_user_data', 'after');
add_handler('ajax_send_scheduled_messages', 'compose_profile_data',  true, 'profiles');
add_handler('ajax_send_scheduled_messages', 'send_scheduled_messages', true, 'smtp');

return array(
    'allowed_pages' => array(
        'ajax_clear_attachment_chunks',
        'ajax_smtp_debug',
        'ajax_smtp_save_draft',
        'ajax_smtp_delete_draft',
        'ajax_profiles_status',
        'ajax_attachment_reminder_check',
        'ajax_get_test_chunk',
        'ajax_upload_chunk',
        'ajax_send_scheduled_messages'
    ),
    'allowed_get' => array(
        'imap_draft' => FILTER_VALIDATE_INT,
        'reply' => FILTER_VALIDATE_INT,
        'reply_all' => FILTER_VALIDATE_INT,
        'forward' => FILTER_VALIDATE_INT,
        'draft_id' => FILTER_VALIDATE_INT,
        'hm_ajax_hook' => FILTER_DEFAULT,
        'compose_to' => FILTER_DEFAULT,
        'mailto_uri' => FILTER_DEFAULT,
        'compose_from' => FILTER_DEFAULT,
        'resumableChunkNumber' => FILTER_VALIDATE_INT,
        'resumableTotalChunks' => FILTER_VALIDATE_INT,
        'resumableChunkSize' => FILTER_VALIDATE_INT,
        'resumableCurrentChunkSize' => FILTER_VALIDATE_INT,
        'resumableTotalSize' => FILTER_VALIDATE_INT,
        'resumableType' => FILTER_DEFAULT,
        'resumableIdentifier' => FILTER_DEFAULT,
        'resumableFilename' => FILTER_DEFAULT,
        'resumableRelativePath' => FILTER_DEFAULT,
        'draft_smtp' => FILTER_DEFAULT
    ),
    'allowed_output' => array(
        'file_details' => array(FILTER_UNSAFE_RAW, false),
        'draft_subject' => array(FILTER_DEFAULT, false),
        'draft_id' => array(FILTER_VALIDATE_INT, false),
        'profile_value' => array(FILTER_DEFAULT, false),
        'msg_sent_and_archived' => array(FILTER_VALIDATE_BOOLEAN, false),
        'sent_msg_id' => array(FILTER_VALIDATE_BOOLEAN, false),
        'enable_attachment_reminder' => array(FILTER_VALIDATE_BOOLEAN, false),
        'scheduled_msg_count' => array(FILTER_VALIDATE_INT, false),
    ),
    'allowed_post' => array(
        'post_archive' => FILTER_VALIDATE_INT,
        'send_tomorrow_morning' => FILTER_DEFAULT,
        'send_today_afternoon' => FILTER_DEFAULT,
        'schedule_sending' => FILTER_DEFAULT,
        'send_date' => FILTER_DEFAULT,
        'send_time' => FILTER_DEFAULT,
        'attachment_id' => FILTER_DEFAULT,
        'smtp_compose_type' => FILTER_VALIDATE_INT,
        'new_smtp_name' => FILTER_DEFAULT,
        'new_smtp_address' => FILTER_DEFAULT,
        'new_smtp_port' => FILTER_DEFAULT,
        'smtp_connect' => FILTER_VALIDATE_INT,
        'smtp_forget' => FILTER_VALIDATE_INT,
        'smtp_save' => FILTER_VALIDATE_INT,
        'smtp_delete' => FILTER_VALIDATE_INT,
        'smtp_send' => FILTER_VALIDATE_INT,
        'submit_smtp_server' => FILTER_DEFAULT,
        'smtp_server_id' => FILTER_DEFAULT,
        'smtp_user' => FILTER_DEFAULT,
        'smtp_pass' => FILTER_UNSAFE_RAW,
        'delete_uploaded_files' => FILTER_VALIDATE_BOOLEAN,
        'compose_to' => FILTER_UNSAFE_RAW,
        'compose_msg_path' => FILTER_DEFAULT,
        'compose_msg_uid' => FILTER_VALIDATE_INT,
        'compose_body' => FILTER_UNSAFE_RAW,
        'compose_subject' => FILTER_UNSAFE_RAW,
        'compose_in_reply_to' => FILTER_UNSAFE_RAW,
        'compose_cc' => FILTER_UNSAFE_RAW,
        'compose_bcc' => FILTER_UNSAFE_RAW,
        'compose_delivery_receipt' => FILTER_VALIDATE_BOOLEAN,
        'compose_smtp_id' => FILTER_DEFAULT,
        'draft_id' => FILTER_VALIDATE_INT,
        'draft_body' => FILTER_UNSAFE_RAW,
        'draft_subject' => FILTER_UNSAFE_RAW,
        'draft_to' => FILTER_UNSAFE_RAW,
        'draft_smtp' => FILTER_DEFAULT,
        'draft_cc' => FILTER_UNSAFE_RAW,
        'draft_bcc' => FILTER_UNSAFE_RAW,
        'draft_in_reply_to' => FILTER_UNSAFE_RAW,
        'draft_notice' => FILTER_VALIDATE_BOOLEAN,
        'smtp_auto_bcc' => FILTER_VALIDATE_INT,
        'profile_value' => FILTER_DEFAULT,
        'uploaded_files' => FILTER_DEFAULT,
        'send_uploaded_files' => FILTER_DEFAULT,
        'next_email_post' => FILTER_DEFAULT,
        'enable_attachment_reminder' => FILTER_VALIDATE_INT,
        'schedule' => FILTER_DEFAULT,
    )
);
