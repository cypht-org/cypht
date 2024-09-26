## Sieve IMAP Filters

Manage Sieve capable IMAP servers for message filtering.

### Email Filters with Cypht
Cypht supports Sieve, enabling powerful email filtering:
* Server-Side Filters: Work even when not logged in.
* Spam Filtering: Filter emails based on sender, subject, or content.
* Inbox Organization: Automatically sort emails into folders.
* Custom Notifications: Create alerts for important emails.

### Enabling Sieve in Cypht
Add sievefilters to CYPHT_MODULES in .env file:

```
CYPHT_MODULES="sievefilters"
```

### Managing Filters
1. Go to Settings > Filters.
2. Select an email account and click Add Filter.
3. Enter filter details:
* Priority: Define order of execution.
* Conditions: Set criteria based on sender, subject, body, etc.

### Creating Custom Notifications
1. Go to Settings > Filters.
2. Select an email account and click Add Script.
3. Enter a name and Sieve code in the Filter script field:

```
require ["fileinto", "imap4flags", "notify"];

set "boss_email" "boss@example.com";

if address :is "from" "${boss_email}" {
    notify :message "You have a new email from your boss!" :options ["Important"] :method "mailto:your-email@example.com";
}
```
Enjoy managing your filters with Cypht and PHP Sieve Manager! 😄

Uses https://packagist.org/packages/henrique-borba/php-sieve-manager
