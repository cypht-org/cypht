<?php
/**
 * Identify Server Configuration Issue
 * 
 * This script helps identify why server ID 684b1017a7861 is being used instead of 67adcf04539a1.
 */

echo "=== Server Configuration Issue Analysis ===\n\n";

echo "🔍 Current URL shows server ID: 67adcf04539a1\n";
echo "🔍 Spam logs show server ID: 684b1017a7861\n\n";

echo "=== Possible Causes ===\n\n";

echo "1. **Multiple IMAP Servers Configured**\n";
echo "   - You have multiple IMAP servers in your configuration\n";
echo "   - The spam report is picking up a different server than the one you're viewing\n\n";

echo "2. **Cached Configuration**\n";
echo "   - Server 684b1017a7861 might be cached from a previous session\n";
echo "   - The configuration might not be properly updated\n\n";

echo "3. **Frontend/Backend Mismatch**\n";
echo "   - The frontend might be sending the wrong server ID\n";
echo "   - There might be a JavaScript issue\n\n";

echo "=== Recommended Solutions ===\n\n";

echo "**Solution 1: Check Your Server Configuration**\n";
echo "1. Go to Cypht → Settings → Servers\n";
echo "2. Look for any server with ID 684b1017a7861\n";
echo "3. If found, check if it has Sieve configuration\n";
echo "4. If no Sieve config, add it or delete the server\n\n";

echo "**Solution 2: Clear Browser Cache**\n";
echo "1. Log out of Cypht\n";
echo "2. Clear browser cache and cookies\n";
echo "3. Log back in\n";
echo "4. Try reporting spam again\n\n";

echo "**Solution 3: Check Browser Developer Tools**\n";
echo "1. Open browser developer tools (F12)\n";
echo "2. Go to Network tab\n";
echo "3. Report a spam message\n";
echo "4. Check the request payload for 'imap_server_id'\n";
echo "5. See which server ID is being sent\n\n";

echo "**Solution 4: Manual Server Cleanup**\n";
echo "1. Go to Settings → Servers\n";
echo "2. Delete any servers you don't recognize\n";
echo "3. Keep only the servers you actively use\n";
echo "4. Make sure each server has proper Sieve configuration\n\n";

echo "=== Debug Information ===\n\n";

echo "To get more detailed information, run:\n";
echo "php examine_session.php\n\n";

echo "This will show you all configured servers and their IDs.\n\n";

echo "=== Expected Behavior ===\n\n";

echo "✅ When you report spam from server 67adcf04539a1:\n";
echo "   - The form should send imap_server_id=67adcf04539a1\n";
echo "   - Auto-blocking should work if Sieve is configured\n\n";

echo "❌ Current behavior:\n";
echo "   - Form is sending imap_server_id=684b1017a7861\n";
echo "   - This server doesn't have Sieve configuration\n";
echo "   - Auto-blocking is being skipped\n\n";

echo "=== Next Steps ===\n\n";

echo "1. First, check your server configuration in Cypht\n";
echo "2. If you see server 684b1017a7861, either configure it properly or delete it\n";
echo "3. Clear browser cache and try again\n";
echo "4. If the issue persists, check the browser developer tools\n\n";

echo "=== End of Analysis ===\n";
?> 