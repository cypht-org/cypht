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

add_handler('ajax_imap_summary', 'imap_tracker', true, 'tracker', 'prep_imap_summary_display', 'after');
add_handler('ajax_imap_summary', 'tracker', false, 'tracker', 'prep_imap_summary_display', 'after');
add_output('ajax_imap_summary', 'show_debug', false, 'tracker');
add_output('ajax_imap_summary', 'tracker', false, 'tracker');

add_handler('ajax_pop3_summary', 'pop3_tracker', true, 'tracker', 'prep_pop3_summary_display', 'after');
add_handler('ajax_pop3_summary', 'tracker', false, 'tracker', 'prep_pop3_summary_display', 'after');
add_output('ajax_pop3_summary', 'show_debug', false, 'tracker');
add_output('ajax_pop3_summary', 'tracker', false, 'tracker');

add_handler('ajax_imap_unread', 'tracker', false, 'tracker', 'imap_unread', 'after');
add_handler('ajax_imap_unread', 'imap_tracker', true, 'tracker', 'imap_unread', 'after');
add_output('ajax_imap_unread', 'tracker', false, 'tracker');
add_output('ajax_imap_unread', 'show_debug', false, 'tracker');

add_handler('ajax_imap_folders', 'tracker', false, 'tracker',  'load_imap_folders', 'after');
add_handler('ajax_imap_folders', 'imap_tracker', true, 'tracker', 'load_imap_folders', 'after');
add_output('ajax_imap_folders', 'tracker', false, 'tracker');
add_output('ajax_imap_folders', 'show_debug', false, 'tracker');

add_handler('settings', 'tracker', false, 'tracker', 'http_headers', 'after');
add_output('settings', 'show_debug', false, 'tracker', 'content_end', 'before');
add_output('settings', 'tracker', false, 'tracker', 'content_end', 'before');

?>
