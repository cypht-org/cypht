# Auto-Blocking System Fixes

This document describes the fixes implemented to make the auto-blocking system work properly in Cypht.

## Issues Fixed

### 1. Missing Module Dependency
**Problem**: The `sievefilters` module was not enabled in the default configuration, causing all Sieve-related functions to be unavailable.

**Solution**: Added `sievefilters` to the modules list in `config/app.php`:
```php
'modules' => explode(',', env('CYPHT_MODULES','core,contacts,local_contacts,feeds,imap,smtp,account,idle_timer,calendar,themes,nux,developer,history,saved_searches,advanced_search,highlights,profiles,inline_message,imap_folders,keyboard_shortcuts,tags,sievefilters')),
```

### 2. Missing Function Dependencies
**Problem**: The auto-blocking system relied on functions that were only available when the `sievefilters` module was loaded.

**Solution**: Added fallback includes in `modules/imap/spam_report_utils.php`:
```php
// Include sievefilters functions if module is not loaded
if (!function_exists('get_sieve_client_factory')) {
    require_once APP_PATH.'modules/sievefilters/functions.php';
}
```

### 3. Poor Error Handling
**Problem**: The system didn't provide clear error messages when auto-blocking failed.

**Solution**: Added comprehensive error handling and logging throughout the auto-blocking process.

### 4. Missing Configuration Validation
**Problem**: The system didn't check if IMAP servers had Sieve configuration before attempting auto-blocking.

**Solution**: Added validation to ensure `sieve_config_host` is configured and provide user feedback.

## How the Auto-Blocking System Works

### 1. User Interface
- Users can report spam by clicking the "Report Spam" button
- A modal dialog appears asking for a reason
- Users can report individual messages or bulk-select multiple messages

### 2. Backend Processing
1. **Input Validation**: Validates required fields (message UIDs, server ID, folder, spam reason)
2. **Rate Limiting**: Prevents abuse using rate limiting
3. **Spam Service Reporting**: Reports to enabled spam services (SpamCop, AbuseIPDB, etc.)
4. **Message Movement**: Moves the reported message to the junk folder
5. **Auto-Blocking**: Automatically blocks the sender if enabled

### 3. Auto-Blocking Process
1. **Extract Sender**: Gets the sender email from message headers
2. **Check Configuration**: Verifies auto-blocking is enabled and Sieve is configured
3. **Create Sieve Filter**: Generates a Sieve script to block future messages
4. **Apply Action**: Moves, discards, or rejects future messages from the sender
5. **Save Configuration**: Updates the Sieve script on the server

## Configuration Requirements

### 1. Enable Sieve Filters Module
The `sievefilters` module must be enabled in `config/app.php`.

### 2. Configure IMAP Server Sieve Settings
Each IMAP server must have the `sieve_config_host` field configured:
- Go to Settings → Servers
- Edit your IMAP server
- Set the "Sieve Configuration Host" field (e.g., `mail.example.com:4190`)

### 3. User Settings
Users can configure auto-blocking behavior in Settings:
- **Enable/Disable**: Toggle auto-blocking on/off
- **Action**: Choose what happens to blocked messages (move to junk, discard, reject)
- **Scope**: Block specific sender or entire domain

## Testing the System

Run the test script to verify the system is working:
```bash
php test_auto_block.php
```

This will check:
- Module availability
- Required functions
- PhpSieveManager classes
- Email extraction
- Domain extraction
- Action mapping

## Troubleshooting

### Auto-blocking not working?
1. **Check module**: Ensure `sievefilters` is enabled in configuration
2. **Check Sieve host**: Verify `sieve_config_host` is set for your IMAP server
3. **Check user settings**: Ensure auto-blocking is enabled in user settings
4. **Check logs**: Look for error messages in the debug logs

### Common Error Messages
- **"Sieve filters module not enabled"**: Enable the `sievefilters` module
- **"Sieve server not configured"**: Set the `sieve_config_host` for your IMAP server
- **"Failed to initialize Sieve client"**: Check your Sieve server connection
- **"Failed to save Sieve script"**: Check Sieve server permissions

### Debug Logging
The system provides detailed debug logging. Enable debug mode to see:
- Auto-blocking attempts
- Success/failure status
- Error details
- Configuration issues

## Files Modified

1. **config/app.php**: Added `sievefilters` to modules list
2. **modules/imap/spam_report_utils.php**: Added error handling and fallback includes
3. **modules/imap/handler_modules.php**: Added better validation and logging
4. **modules/imap/site.js**: Added user feedback for warnings
5. **modules/imap/output_modules.php**: Added configuration warnings

## Dependencies

- **PhpSieveManager**: Required for Sieve script generation (already included in composer.json)
- **sievefilters module**: Provides Sieve client functionality
- **IMAP server with Sieve support**: Required for auto-blocking to work

## Security Considerations

- Auto-blocking uses Sieve filters which run on the mail server
- Rate limiting prevents abuse of the spam reporting feature
- User settings allow granular control over auto-blocking behavior
- All actions are logged for audit purposes 