#!/bin/bash

# Wait for LDAP service to be ready
echo "Waiting for OpenLDAP service to be ready..."
for i in {1..30}; do
    if ldapsearch -x -H ldap://localhost:389 -b "dc=cypht,dc=test" -D "cn=admin,dc=cypht,dc=test" -w "cypht_test" >/dev/null 2>&1; then
        echo "OpenLDAP is ready!"
        break
    fi
    echo "Waiting for LDAP... ($i/30)"
    sleep 2
done

# Add test organizational units and contacts
cat << EOF | ldapadd -x -H ldap://localhost:389 -D "cn=admin,dc=cypht,dc=test" -w "cypht_test"
dn: ou=people,dc=cypht,dc=test
objectClass: organizationalUnit
ou: people

dn: ou=contacts,dc=cypht,dc=test
objectClass: organizationalUnit
ou: contacts

dn: cn=Test Contact,ou=contacts,dc=cypht,dc=test
objectClass: inetOrgPerson
cn: Test Contact
sn: Contact
givenName: Test
mail: test.contact@cypht.test
telephoneNumber: +1 555 123 4567

dn: cn=John Doe,ou=contacts,dc=cypht,dc=test
objectClass: inetOrgPerson
cn: John Doe
sn: Doe
givenName: John
mail: john.doe@cypht.test
telephoneNumber: +1 555 987 6543
mobile: +1 555 111 2222
EOF

echo "LDAP test data populated successfully"
