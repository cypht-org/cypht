<?php
handler_source('tracker');
output_source('tracker');

add_handler('home', 'tracker', false, 'tracker', 'http_headers', 'after');
add_handler('home', 'imap_tracker', true, 'tracker', 'tracker', 'after');
add_output('home', 'show_debug', false, 'tracker', 'content_end', 'before');
add_output('home', 'tracker', false, 'tracker', 'content_end', 'before');

add_handler('servers', 'tracker', false, 'tracker', 'http_headers', 'after');
add_output('servers', 'show_debug', false, 'tracker', 'content_end', 'before');
add_output('servers', 'tracker', false, 'tracker', 'content_end', 'before');

add_handler('unread', 'tracker', false, 'tracker', 'http_headers', 'after');
add_output('unread', 'show_debug', false, 'tracker', 'content_end', 'before');
add_output('unread', 'tracker', false, 'tracker', 'content_end', 'before');

add_handler('ajax_pop3_debug', 'tracker', false, 'tracker', 'save_pop3_servers', 'after');
add_handler('ajax_pop3_debug', 'pop3_tracker', true, 'tracker', 'pop3_connect', 'after');
add_output('ajax_pop3_debug', 'tracker', false, 'tracker');
add_output('ajax_pop3_debug', 'show_debug', false, 'tracker');

add_handler('ajax_imap_debug', 'tracker', false, 'tracker', 'save_imap_servers', 'after');
add_handler('ajax_imap_debug', 'imap_tracker', true, 'tracker', 'imap_connect', 'after');
add_output('ajax_imap_debug', 'tracker', false, 'tracker');
add_output('ajax_imap_debug', 'show_debug', false, 'tracker');

add_handler('ajax_imap_unread', 'tracker', false, 'tracker', 'imap_unread', 'after');
add_handler('ajax_imap_unread', 'imap_tracker', true, 'tracker', 'imap_unread', 'after');
add_output('ajax_imap_unread', 'tracker', false, 'tracker');
add_output('ajax_imap_unread', 'show_debug', false, 'tracker');

add_handler('ajax_hm_folders', 'tracker', false, 'tracker',  'load_imap_folders', 'after');
add_handler('ajax_hm_folders', 'imap_tracker', true, 'tracker', 'load_imap_folders', 'after');
add_output('ajax_hm_folders', 'tracker', false, 'tracker');
add_output('ajax_hm_folders', 'show_debug', false, 'tracker');

add_handler('ajax_imap_msg_text', 'tracker', false, 'tracker',  'imap_message_text', 'after');
add_handler('ajax_imap_msg_text', 'imap_tracker', true, 'tracker', 'imap_message_text', 'after');
add_output('ajax_imap_msg_text', 'tracker', false, 'tracker');
add_output('ajax_imap_msg_text', 'show_debug', false, 'tracker');

add_handler('ajax_imap_folder_expand', 'tracker', false, 'tracker',  'imap_folder_expand', 'after');
add_handler('ajax_imap_folder_expand', 'imap_tracker', true, 'tracker', 'imap_folder_expand', 'after');
add_output('ajax_imap_folder_expand', 'tracker', false, 'tracker');
add_output('ajax_imap_folder_expand', 'show_debug', false, 'tracker');

add_handler('ajax_imap_folder_display', 'tracker', false, 'tracker',  'imap_folder_page', 'after');
add_handler('ajax_imap_folder_display', 'imap_tracker', true, 'tracker', 'imap_folder_page', 'after');
add_output('ajax_imap_folder_display', 'tracker', false, 'tracker');
add_output('ajax_imap_folder_display', 'show_debug', false, 'tracker');

add_handler('settings', 'tracker', false, 'tracker', 'http_headers', 'after');
add_output('settings', 'show_debug', false, 'tracker', 'content_end', 'before');
add_output('settings', 'tracker', false, 'tracker', 'content_end', 'before');

?>
