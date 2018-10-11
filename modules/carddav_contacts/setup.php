<?php

if (!defined('DEBUG_MODE')) { die(); }

handler_source('carddav_contacts');
output_source('carddav_contacts');

add_handler('contacts', 'load_carddav_contacts', true, 'carddav_contacts', 'load_contacts', 'after');
add_handler('ajax_autocomplete_contact', 'load_carddav_contacts', true, 'carddav_contacts', 'load_contacts', 'after');
add_handler('ajax_imap_message_content', 'load_carddav_contacts', true, 'carddav_contacts', 'load_contacts', 'after');
add_handler('compose', 'load_carddav_contacts', true, 'carddav_contacts', 'load_contacts', 'after');
add_handler('ajax_delete_contact', 'load_carddav_contacts', true, 'carddav_contacts', 'load_contacts', 'after');
add_handler('ajax_add_contact', 'load_carddav_contacts', true, 'carddav_contacts', 'load_contacts', 'after');

return array();
