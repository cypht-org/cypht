<?php

if (!defined('DEBUG_MODE')) { die(); }

handler_source('smtp');
output_source('smtp');

add_module_to_all_pages('handler', 'smtp_default_server', true, 'smtp', 'load_user_data', 'after');
add_handler('compose', 'load_smtp_reply_to_details', true, 'smtp', 'load_user_data', 'after');
add_handler('compose', 'load_smtp_servers_from_config', true, 'smtp', 'load_smtp_reply_to_details', 'after');
add_handler('compose', 'add_smtp_servers_to_page_data', true, 'smtp', 'load_smtp_servers_from_config', 'after');
add_handler('compose', 'process_compose_form_submit', true, 'smtp', 'load_smtp_servers_from_config', 'after');
add_output('compose', 'compose_form_start', true, 'smtp', 'content_section_start', 'after');
add_output('compose', 'compose_form_draft_list', true, 'smtp', 'compose_form_start', 'before');
add_output('compose', 'compose_form_content', true, 'smtp', 'compose_form_start', 'after');
add_output('compose', 'compose_form_end', true, 'smtp', 'compose_form_content', 'after');
add_output('compose', 'compose_form_attach', true, 'smtp', 'compose_form_end', 'after');

add_handler('functional_api', 'default_smtp_server', true, 'smtp');

add_handler('profiles', 'load_smtp_servers_from_config', true, 'smtp', 'load_user_data', 'after');
add_handler('profiles', 'add_smtp_servers_to_page_data', true, 'smtp', 'load_smtp_servers_from_config', 'after');

/* servers page */
add_handler('servers', 'load_smtp_servers_from_config', true, 'smtp', 'language', 'after');
add_handler('servers', 'process_add_smtp_server', true, 'smtp', 'load_smtp_servers_from_config', 'after');
add_handler('servers', 'add_smtp_servers_to_page_data', true, 'smtp', 'process_add_smtp_server', 'after');
add_handler('servers', 'save_smtp_servers', true, 'smtp', 'add_smtp_servers_to_page_data', 'after');
add_output('servers', 'add_smtp_server_dialog', true, 'smtp', 'server_content_start', 'after');
add_output('servers', 'display_configured_smtp_servers', true, 'smtp', 'add_smtp_server_dialog', 'after');

add_handler('settings', 'process_compose_type', true, 'smtp', 'save_user_settings', 'before');
add_handler('settings', 'process_auto_bcc', true, 'smtp', 'save_user_settings', 'before');
add_output('settings', 'compose_type_setting', true, 'smtp', 'start_general_settings', 'after');
add_output('settings', 'auto_bcc_setting', true, 'smtp', 'compose_type_setting', 'after');

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
add_handler('ajax_smtp_save_draft', 'login', false, 'core');
add_handler('ajax_smtp_save_draft', 'load_user_data',  true, 'core');
add_handler('ajax_smtp_save_draft', 'smtp_save_draft',  true);
//add_handler('ajax_smtp_save_draft', 'close_session_early',  true, 'core');
add_handler('ajax_smtp_save_draft', 'date', true, 'core');
add_handler('ajax_smtp_save_draft', 'http_headers', true, 'core');

/* attach file */
add_handler('ajax_smtp_attach_file', 'login', false, 'core');
add_handler('ajax_smtp_attach_file', 'load_user_data',  true, 'core');
add_handler('ajax_smtp_attach_file', 'smtp_attach_file',  true);
add_handler('ajax_smtp_attach_file', 'save_user_data',  true, 'core');
add_handler('ajax_smtp_attach_file', 'date', true, 'core');
add_handler('ajax_smtp_attach_file', 'http_headers', true, 'core');
add_output('ajax_smtp_attach_file', 'filter_upload_file_details', true);

/* delete attached file */
add_handler('ajax_smtp_delete_attachment', 'login', false, 'core');
add_handler('ajax_smtp_delete_attachment', 'load_user_data',  true, 'core');
add_handler('ajax_smtp_delete_attachment', 'smtp_delete_attached_file',  true);
add_handler('ajax_smtp_delete_attachment', 'save_user_data',  true, 'core');
add_handler('ajax_smtp_delete_attachment', 'date', true, 'core');
add_handler('ajax_smtp_delete_attachment', 'http_headers', true, 'core');

setup_base_ajax_page('ajax_smtp_delete_draft', 'core');
add_handler('ajax_smtp_delete_draft', 'process_delete_draft', true, 'smtp', 'load_user_data', 'after');

/* folder list link */
add_output('ajax_hm_folders', 'compose_page_link', true, 'smtp', 'logout_menu_item', 'before');
add_handler('ajax_hm_folders', 'smtp_auto_bcc_check',  true, 'smtp', 'load_imap_servers_from_config', 'after');
add_output('ajax_hm_folders', 'sent_folder_link', true, 'smtp', 'logout_menu_item', 'before');

add_handler('ajax_update_server_pw', 'load_smtp_servers_from_config', true, 'smtp', 'load_user_data', 'after');
return array(
    'allowed_pages' => array(
        'ajax_smtp_debug',
        'ajax_smtp_save_draft',
        'ajax_smtp_attach_file',
        'ajax_smtp_delete_attachment',
        'ajax_smtp_delete_draft'
    ),
    'allowed_get' => array(
        'reply' => FILTER_VALIDATE_INT,
        'reply_all' => FILTER_VALIDATE_INT,
        'forward' => FILTER_VALIDATE_INT,
        'draft_id' => FILTER_VALIDATE_INT,
        'compose_to' => FILTER_SANITIZE_STRING,
        'mailto_uri' => FILTER_SANITIZE_STRING,
    ),
    'allowed_output' => array(
        'file_details' => array(FILTER_UNSAFE_RAW, false),
        'draft_subject' => array(FILTER_SANITIZE_STRING, false),
        'draft_id' => array(FILTER_VALIDATE_INT, false)
    ),
    'allowed_post' => array(
        'attachment_id' => FILTER_SANITIZE_STRING,
        'smtp_compose_type' => FILTER_VALIDATE_INT,
        'new_smtp_name' => FILTER_SANITIZE_STRING,
        'new_smtp_address' => FILTER_SANITIZE_STRING,
        'new_smtp_port' => FILTER_SANITIZE_STRING,
        'smtp_connect' => FILTER_VALIDATE_INT,
        'smtp_forget' => FILTER_VALIDATE_INT,
        'smtp_save' => FILTER_VALIDATE_INT,
        'smtp_delete' => FILTER_VALIDATE_INT,
        'smtp_send' => FILTER_VALIDATE_INT,
        'submit_smtp_server' => FILTER_SANITIZE_STRING,
        'smtp_server_id' => FILTER_VALIDATE_INT,
        'smtp_user' => FILTER_SANITIZE_STRING,
        'smtp_pass' => FILTER_UNSAFE_RAW,
        'delete_uploaded_files' => FILTER_VALIDATE_BOOLEAN,
        'compose_to' => FILTER_UNSAFE_RAW,
        'compose_msg_path' => FILTER_SANITIZE_STRING,
        'compose_msg_uid' => FILTER_VALIDATE_INT,
        'compose_body' => FILTER_UNSAFE_RAW,
        'compose_subject' => FILTER_UNSAFE_RAW,
        'compose_in_reply_to' => FILTER_UNSAFE_RAW,
        'compose_cc' => FILTER_UNSAFE_RAW,
        'compose_bcc' => FILTER_UNSAFE_RAW,
        'compose_smtp_id' => FILTER_VALIDATE_FLOAT,
        'draft_id' => FILTER_VALIDATE_INT,
        'draft_body' => FILTER_UNSAFE_RAW,
        'draft_subject' => FILTER_UNSAFE_RAW,
        'draft_to' => FILTER_UNSAFE_RAW,
        'draft_smtp' => FILTER_VALIDATE_FLOAT,
        'draft_cc' => FILTER_UNSAFE_RAW,
        'draft_bcc' => FILTER_UNSAFE_RAW,
        'draft_in_reply_to' => FILTER_UNSAFE_RAW,
        'draft_notice' => FILTER_VALIDATE_BOOLEAN,
        'smtp_auto_bcc' => FILTER_VALIDATE_INT,
    )
);

