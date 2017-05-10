<?php

/**
 * 2FA module set that uses TOTP and Google authenticator
 */
if (!defined('DEBUG_MODE')) { die(); }

handler_source('2fa');
output_source('2fa');

add_module_to_all_pages('handler', '2fa_check', true, '2fa', 'save_user_data', 'after');
add_module_to_all_pages('output', '2fa_dialog', true, '2fa', 'header_start', 'before');

add_handler('settings', 'process_enable_2fa', true, '2fa', 'save_user_settings', 'before');
add_output('settings', 'enable_2fa_setting', true, '2fa', 'end_settings_form', 'before');

return array( 'allowed_post' => array(
    '2fa_code' => FILTER_SANITIZE_STRING,
    '2fa_enable' => FILTER_VALIDATE_INT,
    '2fa_backup_codes' => array('filter' => FILTER_VALIDATE_INT, 'flags'  => FILTER_FORCE_ARRAY)
));
