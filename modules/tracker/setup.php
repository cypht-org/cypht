<?php

Hm_Handler_Modules::add('home', 'tracker', false, 'http_headers', 'after');
Hm_Handler_Modules::add('servers', 'tracker', false, 'http_headers', 'after');
Hm_Handler_Modules::add('ajax_imap_debug', 'tracker', false, 'save_imap_servers', 'after');

Hm_Output_Modules::add('home', 'tracker', false, 'footer', 'before');
Hm_Output_Modules::add('servers', 'tracker', false, 'footer', 'before');
Hm_Output_Modules::add('home', 'show_debug', false, 'footer', 'before');
Hm_Output_Modules::add('servers', 'show_debug', false, 'footer', 'before');

Hm_Output_Modules::add('ajax_imap_debug', 'tracker', false);
Hm_Output_Modules::add('ajax_imap_debug', 'show_debug', false, 'imap_debug', 'after');

Hm_Handler_Modules::add('ajax_imap_summary', 'imap_tracker', true, 'imap_summary', 'after');
Hm_Handler_Modules::add('ajax_imap_debug', 'imap_tracker', true, 'imap_connect', 'after');
Hm_Handler_Modules::add('home', 'imap_tracker', true, 'tracker', 'after');

Hm_Handler_Modules::add('ajax_imap_summary', 'tracker', false, 'imap_summary', 'after');
Hm_Output_Modules::add('ajax_imap_summary', 'show_debug', false);
Hm_Output_Modules::add('ajax_imap_summary', 'tracker', false);

?>
