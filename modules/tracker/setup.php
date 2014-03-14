<?php

Hm_Handler_Modules::add('home', 'tracker', false, 'http_headers', 'after');
Hm_Handler_Modules::add('home', 'imap_tracker', true, 'tracker', 'after');
Hm_Output_Modules::add('home', 'tracker', false, 'footer', 'before');
Hm_Output_Modules::add('home', 'show_debug', false, 'footer', 'before');

Hm_Handler_Modules::add('servers', 'tracker', false, 'http_headers', 'after');
Hm_Output_Modules::add('servers', 'show_debug', false, 'footer', 'before');
Hm_Output_Modules::add('servers', 'tracker', false, 'footer', 'before');

Hm_Handler_Modules::add('unread', 'tracker', false, 'http_headers', 'after');
Hm_Output_Modules::add('unread', 'tracker', false, 'footer', 'before');
Hm_Output_Modules::add('unread', 'show_debug', false, 'footer', 'before');

Hm_Handler_Modules::add('ajax_imap_debug', 'tracker', false, 'save_imap_servers', 'after');
Hm_Handler_Modules::add('ajax_imap_debug', 'imap_tracker', true, 'imap_connect', 'after');
Hm_Output_Modules::add('ajax_imap_debug', 'tracker', false);
Hm_Output_Modules::add('ajax_imap_debug', 'show_debug', false);

Hm_Handler_Modules::add('ajax_imap_summary', 'imap_tracker', true, 'imap_summary', 'after');
Hm_Handler_Modules::add('ajax_imap_summary', 'tracker', false, 'imap_summary', 'after');
Hm_Output_Modules::add('ajax_imap_summary', 'show_debug', false);
Hm_Output_Modules::add('ajax_imap_summary', 'tracker', false);

Hm_Handler_Modules::add('ajax_imap_unread', 'tracker', false, 'imap_unread', 'after');
Hm_Handler_Modules::add('ajax_imap_unread', 'imap_tracker', true, 'imap_unread', 'after');
Hm_Output_Modules::add('ajax_imap_unread', 'tracker', false);
Hm_Output_Modules::add('ajax_imap_unread', 'show_debug', false);

?>
