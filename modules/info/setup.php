<?php

if (!defined('DEBUG_MODE')) { die(); }

handler_source('info');
output_source('info');


/* help page */
setup_base_page('help', 'core');
add_output('help', 'help_content', true, 'info', 'content_section_start', 'after');

/* folder list */
add_output('ajax_hm_folders', 'help_page_link', true, 'info', 'settings_menu_end', 'before');
add_output('ajax_hm_folders', 'bug_report_link', true, 'info', 'settings_menu_end', 'before');
add_output('ajax_hm_folders', 'developer_doc_link', true, 'info', 'settings_menu_end', 'before');

/* bug report page */
setup_base_page('bug_report', 'core');
add_output('bug_report', 'bug_report_form', true, 'info', 'content_section_start', 'after');

/* developer docs */
setup_base_page('dev', 'core');
add_output('dev', 'dev_content', true, 'info', 'content_section_start', 'after');

return array();

?>
