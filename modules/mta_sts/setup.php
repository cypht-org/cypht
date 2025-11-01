<?php

/**
 * MTA-STS module setup
 * @package modules
 * @subpackage mta_sts
 */

if (!defined('DEBUG_MODE')) { die(); }

/* Load module sources */
handler_source('mta_sts');
output_source('mta_sts');

/* Add MTA-STS checking to compose page */
add_handler('compose', 'check_mta_sts_status', true, 'mta_sts', 'load_user_data', 'after');

/* Add MTA-STS status indicator output to compose page */
add_output('compose', 'mta_sts_styles', true, 'mta_sts', 'header_start', 'after');
add_output('compose', 'mta_sts_status_indicator', true, 'mta_sts', 'compose_form_content', 'after');

return array();
