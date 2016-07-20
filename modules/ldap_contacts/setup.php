<?php

if (!defined('DEBUG_MODE')) { die(); }

handler_source('ldap_contacts');
output_source('ldap_contacts');

add_handler('contacts', 'load_ldap_contacts', true, 'ldap_contacts', 'load_contacts', 'after');
add_handler('contacts', 'load_edit_ldap_contact', true, 'ldap_contacts', 'load_ldap_contacts', 'after');
add_output('contacts', 'ldap_contact_form_start', true, 'ldap_contacts', 'contacts_content_start', 'after');
add_output('contacts', 'ldap_form_first_name', true, 'ldap_contacts', 'ldap_contact_form_start', 'after');
add_output('contacts', 'ldap_form_last_name', true, 'ldap_contacts', 'ldap_form_first_name', 'after');
add_output('contacts', 'ldap_form_mail', true, 'ldap_contacts', 'ldap_form_last_name', 'after');
add_output('contacts', 'ldap_form_displayname', true, 'ldap_contacts', 'ldap_form_mail', 'after');
add_output('contacts', 'ldap_form_locality', true, 'ldap_contacts', 'ldap_form_displayname', 'after');
add_output('contacts', 'ldap_form_state', true, 'ldap_contacts', 'ldap_form_locality', 'after');
add_output('contacts', 'ldap_form_street', true, 'ldap_contacts', 'ldap_form_state', 'after');
add_output('contacts', 'ldap_form_postalcode', true, 'ldap_contacts', 'ldap_form_street', 'after');
add_output('contacts', 'ldap_form_title', true, 'ldap_contacts', 'ldap_form_postalcode', 'after');
add_output('contacts', 'ldap_form_phone', true, 'ldap_contacts', 'ldap_form_title', 'after');
add_output('contacts', 'ldap_form_fax', true, 'ldap_contacts', 'ldap_form_phone', 'after');
add_output('contacts', 'ldap_form_mobile', true, 'ldap_contacts', 'ldap_form_fax', 'after');
add_output('contacts', 'ldap_form_room', true, 'ldap_contacts', 'ldap_form_mobile', 'after');
add_output('contacts', 'ldap_form_car', true, 'ldap_contacts', 'ldap_form_room', 'after');
add_output('contacts', 'ldap_form_org', true, 'ldap_contacts', 'ldap_form_car', 'after');
add_output('contacts', 'ldap_form_org_unit', true, 'ldap_contacts', 'ldap_form_org', 'after');
add_output('contacts', 'ldap_form_org_dpt', true, 'ldap_contacts', 'ldap_form_org_unit', 'after');
add_output('contacts', 'ldap_form_emp_num', true, 'ldap_contacts', 'ldap_form_org_dpt', 'after');
add_output('contacts', 'ldap_form_emp_type', true, 'ldap_contacts', 'ldap_form_emp_num', 'after');
add_output('contacts', 'ldap_form_lang', true, 'ldap_contacts', 'ldap_form_emp_type', 'after');
add_output('contacts', 'ldap_form_uri', true, 'ldap_contacts', 'ldap_form_lang', 'after');
add_output('contacts', 'ldap_form_submit', true, 'ldap_contacts', 'ldap_form_uri', 'after');
add_output('contacts', 'ldap_contact_form_end', true, 'ldap_contacts', 'ldap_form_submit', 'after');

add_handler('ajax_autocomplete_contact', 'load_ldap_contacts', true, 'ldap_contacts', 'load_contacts', 'after');
add_handler('ajax_imap_message_content', 'load_ldap_contacts', true, 'ldap_contacts', 'load_contacts', 'after');
add_handler('compose', 'load_ldap_contacts', true, 'ldap_contacts', 'load_contacts', 'after');
add_handler('ajax_delete_contact', 'load_ldap_contacts', true, 'ldap_contacts', 'load_contacts', 'after');
add_handler('ajax_add_contact', 'load_ldap_contacts', true, 'ldap_contacts', 'load_contacts', 'after');

return array(
    'allowed_post' => array(
    )
);
/*
dn: cn=Barbara Jensen,ou=Product Development,dc=siroe,dc=com
objectClass: top
objectClass: person
objectClass: organizationalPerson
objectClass: inetOrgPerson
cn: Barbara Jensen
cn: Babs Jensen
displayName: Babs Jensen
sn: Jensen
givenName: Barbara
initials: BJJ

l: Somewhere
street: 12 Banana Ave
st: QLD
postalcode: 4100
pager: 

title: manager, product development
uid: bjensen
mail: bjensen@siroe.com
telephoneNumber: +1 408 555 1862
facsimileTelephoneNumber: +1 408 555 1992
mobile: +1 408 555 1941
roomNumber: 0209
carLicense: 6ABC246
o: Siroe
ou: Product Development
departmentNumber: 2604
employeeNumber: 42
employeeType: full time
preferredLanguage: fr, en-gb;q=0.8, en;q=0.7
labeledURI: http://www.siroe.com/users/bjensen My Home Page
 */
