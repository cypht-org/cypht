## CardDAV Contacts

This module provides experimental support for contacts from CardDAV servers. **Note:** This module is incomplete and not fully implemented. It is still under development.

### Current Capabilities

* **Contact Retrieval** — Import contacts from a configured CardDAV server
* **Contact Integration** — Retrieved contacts integrate with the compose page, providing autocompletion in relevant fields.

### Limitations

* Read-only functionality is stable; add/edit/delete features are not fully tested
* Significant work remains to be done for full CardDAV support

### Configuration

CardDAV servers are configured via environment variables in `config/carddav.php`. The default configuration expects a local CardDAV server at `http://localhost:5232`:

To use a different CardDAV server, set the `CARD_DAV_SERVER` environment variable.

Users provide their CardDAV server credentials in Settings > CardDAV Addressbooks.

### Requirements

* Contacts module must be enabled
* CardDAV-compliant server accessible from Cypht
* Valid user credentials for the CardDAV server
