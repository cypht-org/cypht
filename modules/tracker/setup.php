<?php

Hm_Handler_Modules::add('home', 'tracker', false, 'http_headers', 'after');
Hm_Handler_Modules::add('ajax_imap_debug', 'tracker', false, 'save_imap_servers', 'after');

Hm_Output_Modules::add('home', 'tracker', false, 'logout', 'after');
Hm_Output_Modules::add('home', 'show_debug', false, 'footer', 'before');
Hm_Output_Modules::add('ajax_imap_debug', 'tracker', false);
Hm_Output_Modules::add('ajax_imap_debug', 'show_debug', false, 'imap_debug', 'after');

?>
