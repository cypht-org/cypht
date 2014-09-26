<?php

if (!defined('DEBUG_MODE')) { die(); }

handler_source('smtp');
output_source('smtp');

add_output('compose', 'compose_form', true, 'smtp', 'content_section_start', 'after');

return array();

?>
