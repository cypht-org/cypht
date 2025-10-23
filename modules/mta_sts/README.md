# MTA-STS Module

This module provides MTA-STS (Mail Transfer Agent Strict Transport Security) and TLS-RPT (TLS Reporting) support in Cypht.

## Features

- **Real-time MTA-STS checking**: Automatically checks if recipient domains support MTA-STS when composing emails
- **Visual status indicators**: Displays clear security status badges for each recipient
- **TLS-RPT detection**: Shows if domains have TLS reporting configured
- **Policy information**: Displays whether a domain enforces or prefers TLS encryption

## What is MTA-STS?

MTA-STS (RFC 8461) is a security standard that allows email domains to:
- Declare their ability to receive TLS-secured SMTP connections
- Require sending servers to refuse delivery if TLS is not available
- Protect against man-in-the-middle attacks on email delivery

## What is TLS-RPT?

TLS-RPT (RFC 8460) is a reporting mechanism that:
- Allows domains to receive reports about TLS connection failures
- Helps identify issues with email security
- Provides visibility into attempted attacks or misconfigurations

## How It Works

When you compose an email in Cypht, the module:

1. Extracts recipient email addresses from the "To" field
2. Checks each recipient's domain for MTA-STS DNS records
3. Fetches and parses the MTA-STS policy (if available)
4. Checks for TLS-RPT configuration
5. Displays the security status with visual indicators

## Status Indicators

### MTA-STS Status

- **Enforce Mode (Green)**: TLS encryption is required. Email will only be delivered over encrypted connections.
- **Testing Mode (Blue)**: TLS encryption is preferred but not required. Delivery will proceed even without TLS.
- **Not Configured (Yellow)**: Domain does not have MTA-STS configured. TLS security is not enforced.

### TLS-RPT Status

- **Enabled (Blue)**: Domain receives reports about TLS connection issues.

## Installation

The module is included with Cypht. To enable it:

1. Ensure the main MTA-STS library is present: `lib/mta_sts.php`
2. The module is automatically loaded if present in `modules/mta_sts/`
3. No additional configuration is required

## For Administrators

To set up MTA-STS for your own email domain, use the included setup script:

```bash
./scripts/setup_mta_sts.sh -d yourdomain.com -m "mail.yourdomain.com" -e security@yourdomain.com
```



## Dependencies

- PHP with cURL or allow_url_fopen enabled
- DNS functions available (dns_get_record)
- Internet access to check external domains

## Performance

- Results are cached for 1 hour to minimize DNS lookups and HTTP requests
- Only active when composing emails
- Checks are performed server-side

## Privacy

- The module checks public DNS records (TXT records)
- Fetches publicly available policy files over HTTPS
- No recipient information is sent to third parties
- All checks are performed by your Cypht server

## Troubleshooting

### Status Not Showing

- Ensure recipients are entered in the "To" field
- Check that DNS functions are available
- Verify internet connectivity from server

### Incorrect Status

- DNS records may be cached; clear the cache
- Policy files must be served over HTTPS with valid certificates
- Check domain configuration with validators

## Technical Details

### Files

- `modules.php`: Handler and output classes
- `setup.php`: Module registration and hooks

### Classes

- `Hm_Handler_check_mta_sts_status`: Checks MTA-STS and TLS-RPT status for recipients
- `Hm_Output_mta_sts_status_indicator`: Displays security status in compose form
- `Hm_Output_mta_sts_styles`: Adds CSS styling for status indicators

### Library

The module uses `Hm_MTA_STS` class from `lib/mta_sts.php`:

- `check_domain()`: Check if domain has MTA-STS enabled
- `check_tls_rpt()`: Check if domain has TLS-RPT enabled
- `extract_domain()`: Extract domain from email address
- `get_status_message()`: Get human-readable status message
- `get_status_class()`: Get CSS class for styling

## References

- [RFC 8461 - MTA-STS](https://tools.ietf.org/html/rfc8461)
- [RFC 8460 - TLS-RPT](https://tools.ietf.org/html/rfc8460)
- [MTA-STS Setup Guide](https://dmarcly.com/blog/how-to-set-up-mta-sts-and-tls-reporting)
- [Cypht Issue #337](https://github.com/cypht-org/cypht/issues/337)

## License

This module is part of Cypht and uses the same license.
