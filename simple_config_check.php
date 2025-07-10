<?php
/**
 * Simple configuration check
 */

echo "=== Simple Configuration Check ===\n\n";

// Check common configuration locations
$possible_locations = [
    'data',
    'site',
    'config',
    'users',
    'user_data'
];

foreach ($possible_locations as $location) {
    if (is_dir($location)) {
        echo "Found directory: $location\n";
        $files = scandir($location);
        foreach ($files as $file) {
            if ($file !== '.' && $file !== '..') {
                echo "  - $file\n";
            }
        }
        echo "\n";
    }
}

// Check for any .hmrc files
echo "Searching for .hmrc files:\n";
$hmrc_files = glob('**/*.hmrc', GLOB_BRACE);
if (empty($hmrc_files)) {
    echo "No .hmrc files found\n";
} else {
    foreach ($hmrc_files as $file) {
        echo "Found: $file\n";
    }
}

echo "\n=== Manual Configuration ===\n";
echo "If no configuration files are found, you may need to:\n";
echo "1. Log into Cypht first to create user configuration\n";
echo "2. Then configure Sieve for your IMAP server\n";
echo "3. For Gmail, use: sieve.gmail.com:4190\n"; 