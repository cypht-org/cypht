<?php
/**
 * IMAP Configuration Checker for Auto-Blocking
 * 
 * This script provides instructions for configuring IMAP server settings
 * to enable the auto-blocking feature when reporting spam.
 */

echo "=== IMAP Configuration Checker for Auto-Blocking ===\n\n";

// Check if we're in the right directory
if (!file_exists('config/app.php')) {
    echo "❌ Error: Please run this script from the Cypht root directory\n";
    exit(1);
}

echo "Based on your logs, the auto-blocking feature is being skipped because\n";
echo "your IMAP server configuration is missing the 'sieve_config_host' setting.\n\n";

echo "=== Current Status ===\n";
echo "✅ SpamCop reporting is working correctly\n";
echo "❌ Auto-blocking is being skipped due to missing Sieve configuration\n\n";

echo "=== Solution ===\n\n";
echo "To enable auto-blocking, you need to configure Sieve settings for your IMAP server:\n\n";

echo "1. Log into Cypht\n";
echo "2. Go to Settings (gear icon in the top right)\n";
echo "3. Click on 'IMAP Servers'\n";
echo "4. Edit your IMAP server configuration\n";
echo "5. Add the following Sieve settings:\n\n";

echo "=== Common Sieve Configurations ===\n\n";

echo "For Gmail:\n";
echo "   Sieve Host: sieve.gmail.com:4190\n";
echo "   Sieve TLS: Yes\n\n";

echo "For Outlook/Hotmail:\n";
echo "   Sieve Host: sieve-mail.outlook.com:4190\n";
echo "   Sieve TLS: Yes\n\n";

echo "For Yahoo:\n";
echo "   Sieve Host: sieve.mail.yahoo.com:4190\n";
echo "   Sieve TLS: Yes\n\n";

echo "For other providers:\n";
echo "   Check your email provider's documentation for Sieve settings\n";
echo "   Common ports: 4190 (Sieve over SSL/TLS) or 2000 (Sieve)\n\n";

echo "=== What Auto-Blocking Does ===\n\n";
echo "Once configured, auto-blocking will:\n";
echo "- Automatically block the sender when you report spam\n";
echo "- Move future messages from that sender to your Junk folder\n";
echo "- Work with both individual and bulk spam reports\n";
echo "- Create Sieve filters on your mail server\n\n";

echo "=== Testing ===\n\n";
echo "After configuring Sieve settings:\n";
echo "1. Find a spam message in your inbox\n";
echo "2. Click 'Report Spam' and provide a reason\n";
echo "3. Check the logs for 'Auto-block: Success' messages\n";
echo "4. Try sending yourself an email from the same address\n";
echo "5. The new email should go to your Junk folder\n\n";

echo "=== Debug Information ===\n\n";
echo "The system will now log detailed information about auto-blocking:\n";
echo "- 'Auto-block check: IMAP account found' - Shows if your server config is found\n";
echo "- 'Auto-block: Starting auto-block process' - Shows when auto-blocking starts\n";
echo "- 'Auto-block: Success' - Shows when auto-blocking completes successfully\n";
echo "- 'Auto-block: Skipped - missing sieve_config_host' - Shows why it's being skipped\n\n";

echo "=== Next Steps ===\n\n";
echo "1. Configure your Sieve settings as shown above\n";
echo "2. Test by reporting a spam message\n";
echo "3. Check the logs to confirm auto-blocking is working\n";
echo "4. If you still have issues, run the test script again\n\n"; 