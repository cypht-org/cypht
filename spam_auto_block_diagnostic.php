<?php
/**
 * Spam Auto-Block Diagnostic Script
 * 
 * This script helps diagnose issues with the "Automatically block sender when reporting spam" feature
 */

echo "=== Spam Auto-Block Diagnostic ===\n\n";

// Check if we're in the right directory
if (!file_exists('config/app.php')) {
    echo "❌ Error: Please run this script from the Cypht root directory\n";
    exit(1);
}

// Define env function if it doesn't exist (for standalone script)
if (!function_exists('env')) {
    function env($key, $default = null) {
        return $default;
    }
}

// Include Composer's autoloader for PhpSieveManager
if (file_exists('vendor/autoload.php')) {
    require_once 'vendor/autoload.php';
}

// 1. Check if sievefilters module is enabled
echo "1. Checking module configuration...\n";

// Read the config file manually to avoid env() function issues
$config_content = file_get_contents('config/app.php');
if (preg_match('/\'modules\'\s*=>\s*explode\(\',\',\s*env\(\'CYPHT_MODULES\',\s*\'([^\']+)\'\)/', $config_content, $matches)) {
    $modules_string = $matches[1];
    $modules = explode(',', $modules_string);
    
    if (in_array('sievefilters', $modules)) {
        echo "✅ sievefilters module is enabled\n";
    } else {
        echo "❌ sievefilters module is NOT enabled\n";
        echo "   Current modules: " . implode(', ', $modules) . "\n";
        echo "   This is likely the main issue!\n\n";
    }
} else {
    echo "⚠️  Could not parse modules configuration from config/app.php\n";
    echo "   Please check the file manually\n\n";
}

// 2. Check if required files exist
echo "\n2. Checking required files...\n";
$required_files = [
    'modules/sievefilters/modules.php',
    'modules/sievefilters/functions.php',
    'modules/sievefilters/hm-sieve.php',
    'modules/imap/spam_report_utils.php',
    'modules/imap/spam_report_config.php',
    'modules/imap/spam_report_services.php'
];

foreach ($required_files as $file) {
    if (file_exists($file)) {
        echo "✅ $file exists\n";
    } else {
        echo "❌ $file missing\n";
    }
}

// 3. Check for user configuration files
echo "\n3. Checking user configuration...\n";
$config_dirs = ['data', 'site', 'users', 'user_data'];
$found_config = false;

foreach ($config_dirs as $dir) {
    if (is_dir($dir)) {
        echo "Found directory: $dir\n";
        $files = scandir($dir);
        foreach ($files as $file) {
            if ($file !== '.' && $file !== '..' && (strpos($file, '.hmrc') !== false || strpos($file, '.ini') !== false)) {
                echo "  - $file\n";
                $found_config = true;
            }
        }
    }
}

if (!$found_config) {
    echo "⚠️  No user configuration files found. You may need to log in first to create configuration.\n";
}

// 4. Check IMAP server configurations
echo "\n4. Checking IMAP server configurations...\n";
$config_files = glob('data/*.hmrc');
$config_files = array_merge($config_files, glob('site/*.hmrc'));
$config_files = array_merge($config_files, glob('users/*.hmrc'));

$imap_servers_found = false;
foreach ($config_files as $config_file) {
    echo "Checking: $config_file\n";
    $content = file_get_contents($config_file);
    
    // Look for IMAP server configurations
    if (preg_match_all('/imap_servers\[([^\]]+)\]\[([^\]]+)\]\s*=\s*([^\n]+)/', $content, $matches, PREG_SET_ORDER)) {
        $imap_servers_found = true;
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
            echo "    Sieve Host: " . (isset($server['sieve_config_host']) ? $server['sieve_config_host'] : 'NOT CONFIGURED') . "\n";
            
            if (isset($server['sieve_config_host']) && !empty($server['sieve_config_host'])) {
                echo "    ✅ Sieve is configured for auto-blocking\n";
            } else {
                echo "    ❌ Sieve is NOT configured - auto-blocking will not work\n";
            }
            echo "\n";
        }
    }
}

if (!$imap_servers_found) {
    echo "⚠️  No IMAP server configurations found in config files\n";
}

// 5. Check auto-block settings
echo "\n5. Checking auto-block settings...\n";
foreach ($config_files as $config_file) {
    $content = file_get_contents($config_file);
    
    // Look for auto-block settings
    if (preg_match('/auto_block_spam_sender\s*=\s*([^\n]+)/', $content, $matches)) {
        $value = trim($matches[1]);
        echo "Found auto_block_spam_sender setting: $value\n";
    }
    
    if (preg_match('/auto_block_spam_action\s*=\s*([^\n]+)/', $content, $matches)) {
        $value = trim($matches[1]);
        echo "Found auto_block_spam_action setting: $value\n";
    }
    
    if (preg_match('/auto_block_spam_scope\s*=\s*([^\n]+)/', $content, $matches)) {
        $value = trim($matches[1]);
        echo "Found auto_block_spam_scope setting: $value\n";
    }
}

// 6. Check for debug logs
echo "\n6. Checking for debug logs...\n";
$log_files = glob('data/*.log');
$log_files = array_merge($log_files, glob('logs/*.log'));

if (empty($log_files)) {
    echo "No log files found. Debug logging may be disabled.\n";
} else {
    foreach ($log_files as $log_file) {
        echo "Found log file: $log_file\n";
        $content = file_get_contents($log_file);
        
        // Look for auto-block related entries
        if (strpos($content, 'auto_block') !== false || strpos($content, 'spam') !== false) {
            echo "  Contains auto-block or spam related entries\n";
            
            // Show last few relevant lines
            $lines = explode("\n", $content);
            $relevant_lines = [];
            foreach (array_reverse($lines) as $line) {
                if (strpos($line, 'auto_block') !== false || strpos($line, 'spam') !== false) {
                    $relevant_lines[] = $line;
                    if (count($relevant_lines) >= 5) break;
                }
            }
            
            if (!empty($relevant_lines)) {
                echo "  Recent relevant entries:\n";
                foreach (array_reverse($relevant_lines) as $line) {
                    echo "    " . trim($line) . "\n";
                }
            }
        }
    }
}

// 7. Check PHP extensions
echo "\n7. Checking PHP extensions...\n";
$required_extensions = ['openssl', 'mbstring', 'json'];
foreach ($required_extensions as $ext) {
    if (extension_loaded($ext)) {
        echo "✅ $ext extension is loaded\n";
    } else {
        echo "❌ $ext extension is NOT loaded\n";
    }
}

// 8. Check for PhpSieveManager
echo "\n8. Checking PhpSieveManager...\n";
if (class_exists('PhpSieveManager\ManageSieve\Client')) {
    echo "✅ PhpSieveManager is available\n";
} else {
    echo "❌ PhpSieveManager is NOT available\n";
    echo "   This is required for Sieve functionality\n";
}

// 9. Test Sieve connection (if possible)
echo "\n9. Testing Sieve connections...\n";
foreach ($config_files as $config_file) {
    $content = file_get_contents($config_file);
    
    if (preg_match_all('/sieve_config_host\s*=\s*([^\n]+)/', $content, $matches)) {
        foreach ($matches[1] as $sieve_host) {
            $sieve_host = trim($sieve_host, '"\'');
            if (!empty($sieve_host)) {
                echo "Testing Sieve connection to: $sieve_host\n";
                
                // Parse host and port
                $parts = explode(':', $sieve_host);
                $host = $parts[0];
                $port = isset($parts[1]) ? $parts[1] : 4190;
                
                // Test connection
                $connection = @fsockopen($host, $port, $errno, $errstr, 5);
                if ($connection) {
                    echo "  ✅ Connection successful\n";
                    fclose($connection);
                } else {
                    echo "  ❌ Connection failed: $errstr ($errno)\n";
                }
            }
        }
    }
}

echo "\n=== Diagnostic Complete ===\n\n";

// Summary and recommendations
echo "SUMMARY AND RECOMMENDATIONS:\n";
echo "============================\n\n";

if (isset($modules) && !in_array('sievefilters', $modules)) {
    echo "🔴 CRITICAL ISSUE: The sievefilters module is not enabled!\n";
    echo "   To fix this:\n";
    echo "   1. Edit config/app.php\n";
    echo "   2. Add 'sievefilters' to the modules array\n";
    echo "   3. Restart your web server\n\n";
}

echo "To enable auto-blocking:\n";
echo "1. Ensure sievefilters module is enabled\n";
echo "2. Configure Sieve host for your IMAP servers\n";
echo "3. Enable auto-blocking in user settings\n";
echo "4. Test with a spam message\n\n";

echo "Common Sieve configurations:\n";
echo "- Gmail: sieve.gmail.com:4190\n";
echo "- Outlook: sieve-mail.outlook.com:4190\n";
echo "- Yahoo: sieve.mail.yahoo.com:4190\n";
echo "- Custom: mail.yourdomain.com:4190\n\n";

echo "For more help, see SIEVE_CONFIGURATION_GUIDE.md\n"; 