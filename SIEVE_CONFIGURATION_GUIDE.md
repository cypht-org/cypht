# Sieve Configuration Guide for Auto-Blocking

This guide will help you configure Sieve filters to enable the auto-blocking feature in Cypht.

## What is Sieve?

Sieve is a filtering language for email that runs on the mail server. It allows you to create rules that automatically process incoming emails (move to folders, delete, reject, etc.).

## Why is Sieve Required for Auto-Blocking?

The auto-blocking feature creates Sieve filters on your mail server to automatically block future emails from senders you've reported as spam. Without Sieve support, the auto-blocking cannot work.

## Step 1: Check if Your Email Provider Supports Sieve

### Common Email Providers and Sieve Support

| Provider | Sieve Support | Default Configuration |
|----------|---------------|----------------------|
| Gmail | ✅ Yes | `sieve.gmail.com:4190` |
| Outlook/Hotmail | ✅ Yes | `sieve-mail.outlook.com:4190` |
| Yahoo | ✅ Yes | `sieve.mail.yahoo.com:4190` |
| ProtonMail | ✅ Yes | `127.0.0.1:4190` (local) |
| Fastmail | ✅ Yes | `sieve.fastmail.com:4190` |
| Zoho | ✅ Yes | `sieve.zoho.com:4190` |
| Custom/Private | Varies | Usually `mail.yourdomain.com:4190` |

### Test Your Email Provider

Run the Sieve connection test script:

```bash
php test_sieve_connection.php your-email@domain.com
```

This will test common Sieve configurations for your email domain.

## Step 2: Configure Sieve in Cypht

### Method 1: Via Web Interface

1. **Login to Cypht**
2. **Go to Settings → Servers**
3. **Find your IMAP server** and click **"Edit"**
4. **Look for "Sieve Host" field**
5. **Enter your Sieve server configuration** (e.g., `sieve.gmail.com:4190`)
6. **Save the configuration**

### Method 2: Via Configuration File

If you have access to the configuration files, you can add Sieve settings directly:

```php
// In your IMAP server configuration
$imap_list = array(
    'name' => 'Your Email',
    'server' => 'imap.yourdomain.com',
    'port' => 993,
    'tls' => true,
    'user' => 'your-email@yourdomain.com',
    'pass' => 'your-password',
    'sieve_config_host' => 'sieve.yourdomain.com:4190',  // Add this line
    'sieve_tls' => true  // Add this line if using TLS
);
```

## Step 3: Enable Auto-Blocking

1. **Go to Settings → Auto-Block Spam Sender Settings**
2. **Check "Automatically block sender when reporting spam"**
3. **Choose your preferred action:**
   - **Move to Junk Folder**: Moves future emails to spam folder
   - **Discard Messages**: Deletes future emails immediately
   - **Reject with Bounce**: Sends rejection back to sender
4. **Choose blocking scope:**
   - **Block specific sender only**: Blocks only the exact email address
   - **Block entire domain**: Blocks all emails from that domain
5. **Save settings**

## Step 4: Test the Configuration

### Test 1: Check Module Availability

```bash
php test_auto_block.php
```

This should show all tests passing.

### Test 2: Test Sieve Connection

```bash
php test_sieve_connection.php your-email@domain.com
```

This should find working Sieve configurations.

### Test 3: Test Auto-Blocking

1. **Send a test email** from a different account
2. **Report it as spam** in Cypht
3. **Check if the sender gets blocked** by sending another email

## Troubleshooting

### Issue: "Sieve server not configured"

**Solution**: Configure the `sieve_config_host` for your IMAP server.

### Issue: "Failed to initialize Sieve client"

**Possible causes:**
- Wrong Sieve server hostname/port
- Sieve not enabled on your email account
- Network connectivity issues
- Authentication problems

**Solutions:**
1. **Verify Sieve server details** with your email provider
2. **Enable Sieve in your email account settings**
3. **Check network connectivity** to the Sieve server
4. **Verify authentication credentials**

### Issue: "Sieve filters module not enabled"

**Solution**: The `sievefilters` module is now enabled by default. If you still see this error, check your configuration.

### Issue: Auto-blocking not working after configuration

**Check these:**
1. **Auto-blocking is enabled** in user settings
2. **Sieve server is properly configured**
3. **Email provider supports Sieve**
4. **No firewall blocking port 4190**

## Common Email Provider Configurations

### Gmail
- **Sieve Host**: `sieve.gmail.com:4190`
- **TLS**: Enabled
- **Note**: Requires "Less secure app access" or OAuth2

### Outlook/Hotmail
- **Sieve Host**: `sieve-mail.outlook.com:4190`
- **TLS**: Enabled
- **Note**: May require app-specific password

### Yahoo
- **Sieve Host**: `sieve.mail.yahoo.com:4190`
- **TLS**: Enabled
- **Note**: Requires app-specific password

### Custom/Private Servers
- **Sieve Host**: Usually `mail.yourdomain.com:4190` or `sieve.yourdomain.com:4190`
- **TLS**: Usually enabled
- **Note**: Check with your email provider for exact settings

## Security Considerations

- **Sieve filters run on the mail server**, so they're secure
- **Auto-blocking uses your email credentials** to authenticate with Sieve
- **Rate limiting** prevents abuse of the spam reporting feature
- **All actions are logged** for audit purposes

## Getting Help

If you're still having issues:

1. **Check the debug logs** for specific error messages
2. **Contact your email provider** for Sieve configuration details
3. **Test with a different email provider** that supports Sieve
4. **Check the Cypht documentation** for additional troubleshooting

## Example: Complete Configuration

Here's an example of a complete IMAP server configuration with Sieve:

```php
$imap_server = array(
    'name' => 'My Gmail',
    'server' => 'imap.gmail.com',
    'port' => 993,
    'tls' => true,
    'user' => 'myemail@gmail.com',
    'pass' => 'my-password',
    'sieve_config_host' => 'sieve.gmail.com:4190',
    'sieve_tls' => true
);
```

With this configuration, auto-blocking should work properly when you report spam. 