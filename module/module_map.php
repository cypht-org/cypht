<?php

/* Homepage data handler modules */
Hm_Handler_Modules::add('home', 'language',  true);
Hm_Handler_Modules::add('home', 'title', true);
Hm_Handler_Modules::add('home', 'date', true);
Hm_Handler_Modules::add('home', 'logout', true);
Hm_Handler_Modules::add('home', 'imap_delete', true);
Hm_Handler_Modules::add('home', 'imap_setup', true);
Hm_Handler_Modules::add('home', 'imap_setup_display', true);
Hm_Handler_Modules::add('home', 'imap_connect', true);
Hm_Handler_Modules::add('home', 'http_headers', true);

/* Homepage output modules */
Hm_Output_Modules::add('home', 'header', false);
Hm_Output_Modules::add('home', 'css', false);
Hm_Output_Modules::add('home', 'jquery', true);
Hm_Output_Modules::add('home', 'logout', true);
Hm_Output_Modules::add('home', 'login', false);
Hm_Output_Modules::add('home', 'title', true);
Hm_Output_Modules::add('home', 'msgs', true);
Hm_Output_Modules::add('home', 'date', true);
Hm_Output_Modules::add('home', 'imap_setup', true);
Hm_Output_Modules::add('home', 'imap_setup_display', true);
Hm_Output_Modules::add('home', 'imap_debug', true);
Hm_Output_Modules::add('home', 'js', true);
Hm_Output_Modules::add('home', 'footer', true);

/* ajax callback */
Hm_Handler_Modules::add('ajax_imap_debug', 'imap_connect', true);

/* Not found page modules */
Hm_Handler_Modules::add('notfound', 'title', true);
Hm_Output_Modules::add('notfound', 'title', true);

?>
