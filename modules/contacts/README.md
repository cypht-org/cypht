## Contacts

This module provides the base contact management framework, including the UI and a protocol layer that unifies all contact sources into a single, accessible storage layer. Currently, three contact sources are supported:

- local contacts: Basic contact support locally within Cypht
- LDAP contacts: Uses an LDAP server to access/manage contacts
- Gmail contacts: Read-only access to contacts from Gmail accounts

One or all of those module sets can be enabled with this one to add contact
support for that source. Some work has been done to add CardDav contact
support, however there is much left to do.
