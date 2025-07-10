<?php
/**
 * Fix Server Cache - Add missing Sieve configuration
 * 
 * This script fixes servers that are missing sieve_config_host in their configuration.
 * It directly reads and updates the user configuration files.
 */

echo "=== Fix Server Cache - Add Missing Sieve Configuration ===\n\n";

// Look for user configuration files in the correct location
$data_dir = 'data';
$site_dir = 'site';
$user_settings_dir = 'C:/laragon/data/cypht/users'; // From your config

echo "Looking for user configuration files...\n";

$config_files = array_merge(
    glob($data_dir . '/*.conf'),
    glob($site_dir . '/*.conf'),
    glob($data_dir . '/*.ini'),
    glob($site_dir . '/*.ini'),
    glob($user_settings_dir . '/*.conf'),
    glob($user_settings_dir . '/*.ini'),
    glob($user_settings_dir . '/*.txt')  // User config files are .txt
);

if (empty($config_files)) {
    echo "❌ No user configuration files found.\n";
    echo "This might be because:\n";
    echo "1. User data is stored in a database\n";
    echo "2. User data is stored in PHP sessions\n";
    echo "3. Configuration files are in a different location\n\n";
    
    echo "Let's try a different approach...\n\n";
    
    // Try to find session files
    echo "Looking for session files...\n";
    $session_files = glob('/tmp/sess_*');
    if (!empty($session_files)) {
        echo "Found session files:\n";
        foreach ($session_files as $file) {
            echo "  - " . basename($file) . "\n";
        }
    } else {
        echo "No session files found in /tmp/\n";
    }
    
    echo "\nSince we can't access the configuration directly, let's provide manual instructions:\n\n";
    
    echo "=== MANUAL FIX INSTRUCTIONS ===\n\n";
    
    echo "The issue is that your server with ID '684b1017a7861' (grandi0z) is missing the Sieve configuration.\n\n";
    
    echo "Here's how to fix it:\n\n";
    
    echo "1. **Log out of Cypht completely**\n";
    echo "   - Go to Cypht and click 'Logout'\n";
    echo "   - Close all browser tabs for Cypht\n\n";
    
    echo "2. **Clear browser cache and cookies**\n";
    echo "   - Open your browser settings\n";
    echo "   - Clear all cookies and cache for your Cypht site\n\n";
    
    echo "3. **Log back in to Cypht**\n\n";
    
    echo "4. **Delete the problematic server**\n";
    echo "   - Go to Settings → Servers\n";
    echo "   - Find the server named 'grandi0z' or 'super_grandi0z'\n";
    echo "   - Click 'Delete' to remove it completely\n\n";
    
    echo "5. **Add a new server manually**\n";
    echo "   - Click 'Add a new server' (NOT 'Add an E-mail Account')\n";
    echo "   - Fill in the details:\n";
    echo "     * Name: grandi0z\n";
    echo "     * Server: imap.migadu.com\n";
    echo "     * Port: 993\n";
    echo "     * TLS: Checked\n";
    echo "     * Username: joseph.kausi@evoludata.com\n";
    echo "     * Password: (your password)\n";
    echo "     * Sieve Host: imap.migadu.com:4190\n";
    echo "     * Sieve TLS Mode: Checked\n";
    echo "   - Save the configuration\n\n";
    
    echo "6. **Test auto-blocking**\n";
    echo "   - Try reporting another spam message\n";
    echo "   - Check if auto-blocking works\n\n";
    
    echo "=== ALTERNATIVE: Database Fix ===\n\n";
    
    echo "If you have database access, you can also fix this directly in the database:\n\n";
    
    echo "1. Connect to your database\n";
    echo "2. Find the table that stores user settings (usually 'hm_user_settings')\n";
    echo "3. Find your user's settings record\n";
    echo "4. Look for the 'imap_servers' configuration\n";
    echo "5. Add 'sieve_config_host' => 'imap.migadu.com:4190' to the server with ID '684b1017a7861'\n";
    echo "6. Add 'sieve_tls' => true to the same server\n\n";
    
    exit(1);
}

echo "Found configuration files:\n";
foreach ($config_files as $file) {
    echo "  - " . $file . "\n";
}

echo "\n=== Analyzing Configuration Files ===\n\n";

$servers_fixed = 0;

foreach ($config_files as $config_file) {
    echo "Checking: $config_file\n";
    
    $content = file_get_contents($config_file);
    if (!$content) {
        echo "  ❌ Could not read file\n";
        continue;
    }
    
    // Look for IMAP server configurations
    if (preg_match_all('/imap_servers\[([^\]]+)\]\[([^\]]+)\]\s*=\s*([^\n]+)/', $content, $matches, PREG_SET_ORDER)) {
        $servers = [];
        
        foreach ($matches as $match) {
            $server_id = $match[1];
            $key = $match[2];
            $value = trim($match[3], '"\'');
            
            if (!isset($servers[$server_id])) {
                $servers[$server_id] = [];
            }
            $servers[$server_id][$key] = $value;
        }
        
        foreach ($servers as $server_id => $server) {
            echo "  Server ID: $server_id\n";
            echo "    Name: " . (isset($server['name']) ? $server['name'] : 'Not set') . "\n";
            echo "    Server: " . (isset($server['server']) ? $server['server'] : 'Not set') . "\n";
            
            // Check if this server needs Sieve configuration
            if (!isset($server['sieve_config_host']) || empty($server['sieve_config_host'])) {
                echo "    ❌ Sieve Host: NOT CONFIGURED\n";
                
                // Determine correct Sieve configuration
                $server_host = isset($server['server']) ? $server['server'] : '';
                $sieve_host = '';
                
                if (strpos($server_host, 'migadu.com') !== false) {
                    $sieve_host = 'imap.migadu.com:4190';
                } elseif (strpos($server_host, 'postale.io') !== false) {
                    $sieve_host = 'mail.postale.io:4190';
                } elseif (strpos($server_host, 'gmail.com') !== false) {
                    $sieve_host = 'sieve.gmail.com:4190';
                } elseif (strpos($server_host, 'outlook.com') !== false || strpos($server_host, 'hotmail.com') !== false) {
                    $sieve_host = 'sieve-mail.outlook.com:4190';
                } elseif (strpos($server_host, 'yahoo.com') !== false) {
                    $sieve_host = 'sieve.mail.yahoo.com:4190';
                } else {
                    $sieve_host = $server_host . ':4190';
                }
                
                // Add Sieve configuration to the content
                $new_content = $content;
                $new_content .= "\nimap_servers[$server_id][sieve_config_host] = \"$sieve_host\"\n";
                $new_content .= "imap_servers[$server_id][sieve_tls] = true\n";
                
                // Write the updated content back to the file
                if (file_put_contents($config_file, $new_content)) {
                    echo "    ✅ Added Sieve Host: $sieve_host\n";
                    echo "    ✅ Added Sieve TLS: true\n";
                    echo "    ✅ Configuration updated successfully\n";
                    $servers_fixed++;
                } else {
                    echo "    ❌ Failed to update configuration\n";
                }
            } else {
                echo "    ✅ Sieve Host: " . $server['sieve_config_host'] . "\n";
            }
            echo "\n";
        }
    } else {
        echo "  ⚠️  No IMAP server configurations found\n";
    }
}

echo "=== Summary ===\n";
echo "Configuration files checked: " . count($config_files) . "\n";
echo "Servers fixed: $servers_fixed\n\n";

if ($servers_fixed > 0) {
    echo "🎉 Successfully fixed $servers_fixed server(s)!\n";
    echo "   Auto-blocking should now work for the fixed servers.\n\n";
    
    echo "=== Next Steps ===\n";
    echo "1. Log out and log back in to Cypht\n";
    echo "2. Go to Settings → Servers to verify the Sieve configuration\n";
    echo "3. Try reporting a spam message to test auto-blocking\n";
    echo "4. Check the debug logs to confirm auto-blocking is working\n\n";
} else {
    echo "ℹ️  No servers needed fixing, or no configuration files were found.\n\n";
    
    echo "If auto-blocking is still not working, try the manual fix instructions above.\n\n";
} 