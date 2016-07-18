<?php

if (!defined('DEBUG_MODE')) { die(); }

handler_source('ldap_contacts');
output_source('ldap_contacts');

add_handler('contacts', 'load_ldap_contacts', true, 'ldap_contacts', 'load_contacts', 'after');

add_handler('ajax_autocomplete_contact', 'load_ldap_contacts', true, 'ldap_contacts', 'load_contacts', 'after');
add_handler('ajax_imap_message_content', 'load_ldap_contacts', true, 'ldap_contacts', 'load_contacts', 'after');
add_handler('compose', 'load_ldap_contacts', true, 'ldap_contacts', 'load_contacts', 'after');

return array();
