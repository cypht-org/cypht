<?php

if (!defined('DEBUG_MODE')) { die(); }

handler_source('gmail_contacts');
output_source('gmail_contacts');

add_handler('contacts', 'load_gmail_contacts', true, 'gmail_contacts', 'load_contacts', 'after');

add_handler('ajax_autocomplete_contact', 'load_gmail_contacts', true, 'gmail_contacts', 'load_contacts', 'after');
add_handler('ajax_imap_message_content', 'load_gmail_contacts', true, 'gmail_contacts', 'load_contacts', 'after');
add_handler('compose', 'load_gmail_contacts', true, 'gmail_contacts', 'load_contacts', 'after');
add_handler('ajax_delete_contact', 'load_gmail_contacts', true, 'gmail_contacts', 'load_contacts', 'after');

return array();
