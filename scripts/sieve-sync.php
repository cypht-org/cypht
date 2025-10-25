#!/usr/bin/env php
<?php

/**
 * Sieve Sync Script
 * 
 * Processes the spam sender queue and synchronizes blocking rules
 * with mail server Sieve filters.
 * 
 * Usage:
 *   php scripts/sieve-sync.php [options]
 * 
 * Options:
 *   --limit=N     Process max N entries (default: 50)
 *   --user=EMAIL  Process only this user
 *   --debug       Enable debug output
 *   --dry-run     Don't actually sync, just show what would be done
 *   --force       Force sync even if retry_after not reached
 * 
 * Cron setup:
 *   10 * * * * /usr/bin/php /path/to/cypht/scripts/sieve-sync.php >> /path/to/cypht/logs/sieve-sync.log 2>&1
 * 
 * @package Cypht
 * @subpackage scripts
 */

// Bootstrap
define('DEBUG_MODE', true);
define('APP_PATH', dirname(dirname(__FILE__)) . '/');
define('VENDOR_PATH', APP_PATH . 'vendor/');

// Check if running from CLI
if (php_sapi_name() !== 'cli') {
    die("This script must be run from the command line\n");
}

// Load dependencies
require_once APP_PATH . 'lib/framework.php';
require_once APP_PATH . 'modules/imap/sieve_queue.php';
require_once APP_PATH . 'modules/imap/sieve_client_factory.php';

// Parse command line options
$options = getopt('', array('limit:', 'user:', 'debug', 'dry-run', 'force', 'help'));

if (isset($options['help'])) {
    show_help();
    exit(0);
}

$limit = isset($options['limit']) ? (int)$options['limit'] : 50;
$filter_user = isset($options['user']) ? $options['user'] : null;
$debug = isset($options['debug']);
$dry_run = isset($options['dry-run']);
$force = isset($options['force']);

// Initialize logger
$logger = new SieveSyncLogger(APP_PATH . 'logs/sieve-sync.log', $debug);

// Main execution
try {
    $logger->info("=== Sieve Sync Started ===");
    $logger->info("Options: limit=$limit, user=$filter_user, debug=$debug, dry-run=$dry_run, force=$force");
    
    $sync = new SieveSyncProcessor($logger, $dry_run);
    $results = $sync->process($limit, $filter_user, $force);
    
    $logger->info("=== Sieve Sync Completed ===");
    $logger->info("Processed: {$results['processed']}, Synced: {$results['synced']}, Failed: {$results['failed']}, Skipped: {$results['skipped']}");
    
    exit(0);
    
} catch (Exception $e) {
    $logger->error("Fatal error: " . $e->getMessage());
    $logger->error("Stack trace: " . $e->getTraceAsString());
    exit(1);
}

// ============================================================================
// Helper Functions
// ============================================================================

/**
 * Show help message
 */
function show_help() {
    echo <<<HELP
Sieve Sync Script - Synchronize spam sender blocks with mail server

Usage:
  php scripts/sieve-sync.php [options]

Options:
  --limit=N     Process maximum N entries (default: 50)
  --user=EMAIL  Process only this specific user
  --debug       Enable debug output to console
  --dry-run     Show what would be done without actually syncing
  --force       Force sync even if retry_after timestamp not reached
  --help        Show this help message

Examples:
  # Process up to 50 pending entries
  php scripts/sieve-sync.php

  # Process only entries for specific user
  php scripts/sieve-sync.php --user=admin@example.com

  # Debug mode with dry-run
  php scripts/sieve-sync.php --debug --dry-run

  # Force sync all pending (ignore retry delays)
  php scripts/sieve-sync.php --force

Cron Setup:
  # Run every 10 minutes
  */10 * * * * /usr/bin/php /path/to/cypht/scripts/sieve-sync.php >> /path/to/logs/sieve-sync.log 2>&1

HELP;
}

// ============================================================================
// Logger Class
// ============================================================================

/**
 * Simple file logger for sieve sync operations
 */
class SieveSyncLogger {
    private $log_file;
    private $debug;
    private $fp;
    
    public function __construct($log_file, $debug = false) {
        $this->log_file = $log_file;
        $this->debug = $debug;
        
        // Ensure log directory exists
        $log_dir = dirname($log_file);
        if (!is_dir($log_dir)) {
            mkdir($log_dir, 0755, true);
        }
        
        // Open log file
        $this->fp = fopen($log_file, 'a');
        if (!$this->fp) {
            throw new Exception("Failed to open log file: $log_file");
        }
    }
    
    public function info($message) {
        $this->log('INFO', $message);
    }
    
    public function error($message) {
        $this->log('ERROR', $message);
    }
    
    public function warning($message) {
        $this->log('WARNING', $message);
    }
    
    public function debug($message) {
        if ($this->debug) {
            $this->log('DEBUG', $message);
        }
    }
    
    private function log($level, $message) {
        $timestamp = date('Y-m-d H:i:s');
        $line = "[$timestamp] [$level] $message\n";
        
        // Write to file
        if ($this->fp) {
            fwrite($this->fp, $line);
            fflush($this->fp);
        }
        
        // Also output to console if debug mode
        if ($this->debug) {
            echo $line;
        }
    }
    
    public function __destruct() {
        if ($this->fp) {
            fclose($this->fp);
        }
    }
}

// ============================================================================
// Main Processor Class
// ============================================================================

/**
 * Sieve sync processor
 */
class SieveSyncProcessor {
    private $logger;
    private $queue;
    private $dry_run;
    private $stats;
    
    public function __construct($logger, $dry_run = false) {
        $this->logger = $logger;
        $this->dry_run = $dry_run;
        $this->queue = new Hm_Sieve_Queue();
        $this->stats = array(
            'processed' => 0,
            'synced' => 0,
            'failed' => 0,
            'skipped' => 0
        );
    }
    
    /**
     * Process the queue
     */
    public function process($limit = 50, $filter_user = null, $force = false) {
        // Get pending entries
        $pending = $this->queue->get_pending($limit);
        
        $this->logger->info("Found " . count($pending) . " pending entries");
        
        if (empty($pending)) {
            $this->logger->info("No pending entries to process");
            return $this->stats;
        }
        
        // Group by user
        $grouped = $this->group_by_user($pending, $filter_user);
        
        $this->logger->info("Grouped into " . count($grouped) . " users");
        
        // Process each user
        foreach ($grouped as $user_id => $entries) {
            $this->logger->info("Processing user: $user_id (" . count($entries) . " entries)");
            $this->process_user($user_id, $entries);
        }
        
        // Cleanup old entries (skip in dry-run mode)
        if (!$this->dry_run) {
            $this->logger->info("Running cleanup...");
            $cleanup_result = $this->queue->cleanup_old_entries();
            if (isset($cleanup_result['archived'])) {
                $this->logger->info("Archived {$cleanup_result['archived']} old entries");
            }
        } else {
            $this->logger->info("[DRY-RUN] Skipping cleanup");
        }
        
        return $this->stats;
    }
    
    /**
     * Group entries by user
     */
    private function group_by_user($entries, $filter_user = null) {
        $grouped = array();
        
        foreach ($entries as $entry) {
            // Filter by user if specified
            if ($filter_user !== null && $entry['user_id'] !== $filter_user) {
                continue;
            }
            
            $user_id = $entry['user_id'];
            if (!isset($grouped[$user_id])) {
                $grouped[$user_id] = array();
            }
            $grouped[$user_id][] = $entry;
        }
        
        return $grouped;
    }
    
    /**
     * Process entries for a specific user
     */
    private function process_user($user_id, $entries) {
        // Get user's Sieve configuration
        $sieve_config = $this->get_sieve_config($user_id, $entries[0]['imap_server_id']);
        
        if (!$sieve_config) {
            $this->logger->error("No Sieve configuration found for user: $user_id");
            foreach ($entries as $entry) {
                $this->mark_failed($entry, 'No Sieve configuration');
            }
            return;
        }
        
        $this->logger->debug("Sieve config: " . json_encode($sieve_config));
        
        // Create Sieve client
        try {
            $client = SieveClientFactory::create($sieve_config['provider'], false);
            $this->logger->debug("Created Sieve client: " . get_class($client));
            
        } catch (Exception $e) {
            $this->logger->error("Failed to create Sieve client: " . $e->getMessage());
            foreach ($entries as $entry) {
                $this->mark_failed($entry, 'Failed to create client: ' . $e->getMessage());
            }
            return;
        }
        
        // Connect to Sieve server
        try {
            if ($this->dry_run) {
                $this->logger->info("[DRY-RUN] Would connect to {$sieve_config['host']}:{$sieve_config['port']}");
            } else {
                $this->logger->info("Connecting to {$sieve_config['host']}:{$sieve_config['port']}");
                
                $client->connect(
                    $sieve_config['host'],
                    $sieve_config['port'],
                    $sieve_config['username'],
                    $sieve_config['password']
                );
                
                $this->logger->info("Connected successfully");
            }
            
        } catch (SieveConnectionException $e) {
            $this->logger->error("Connection failed: " . $e->getMessage());
            foreach ($entries as $entry) {
                $this->mark_failed($entry, 'Connection failed: ' . $e->getMessage());
            }
            return;
        }
        
        // Get existing blocked_senders script
        $existing_script = null;
        if (!$this->dry_run && $client->isConnected()) {
            try {
                if ($client->ruleExists('blocked_senders')) {
                    $existing_script = $client->getRule('blocked_senders');
                    $this->logger->debug("Found existing blocked_senders script");
                } else {
                    $this->logger->debug("No existing blocked_senders script");
                }
            } catch (Exception $e) {
                $this->logger->warning("Failed to get existing script: " . $e->getMessage());
            }
        }
        
        // Parse existing blocked senders
        $existing_senders = $this->parse_existing_senders($existing_script);
        $this->logger->debug("Existing blocked senders: " . count($existing_senders));
        
        // Process each entry
        $new_senders = array();
        foreach ($entries as $entry) {
            $this->stats['processed']++;
            
            // Check if already blocked
            if (in_array($entry['sender_email'], $existing_senders)) {
                $this->logger->info("Sender already blocked: {$entry['sender_email']}");
                $this->mark_synced($entry);
                continue;
            }
            
            $new_senders[] = $entry;
        }
        
        // If no new senders, we're done
        if (empty($new_senders)) {
            $this->logger->info("No new senders to block for user: $user_id");
            $client->disconnect();
            return;
        }
        
        // Generate updated Sieve script
        $all_senders = array_merge($existing_senders, array_map(function($e) {
            return $e['sender_email'];
        }, $new_senders));
        
        $script = $this->generate_sieve_script($all_senders, $new_senders[0]['block_action']);
        
        $this->logger->debug("Generated script for " . count($all_senders) . " senders");
        
        // Upload the script
        try {
            if ($this->dry_run) {
                $this->logger->info("[DRY-RUN] Would upload script with " . count($new_senders) . " new senders");
                foreach ($new_senders as $entry) {
                    $this->logger->info("[DRY-RUN]   - {$entry['sender_email']}");
                    $this->stats['synced']++;
                }
            } else {
                $this->logger->info("Uploading script with " . count($new_senders) . " new senders");
                
                $client->addRule('blocked_senders', $script, true);
                
                $this->logger->info("Script uploaded and activated successfully");
                
                // Mark all new entries as synced
                foreach ($new_senders as $entry) {
                    $this->mark_synced($entry);
                }
            }
            
        } catch (SieveRuleException $e) {
            $this->logger->error("Failed to upload script: " . $e->getMessage());
            foreach ($new_senders as $entry) {
                $this->mark_failed($entry, 'Upload failed: ' . $e->getMessage());
            }
        }
        
        // Disconnect
        if (!$this->dry_run && $client->isConnected()) {
            $client->disconnect();
            $this->logger->info("Disconnected from Sieve server");
        }
    }
    
    /**
     * Get Sieve configuration for a user
     */
    private function get_sieve_config($user_id, $imap_server_id) {
        // TODO: Load from user's actual IMAP configuration
        // For now, return hardcoded Migadu config for testing
        
        // In production, this should load from:
        // 1. User's config file
        // 2. Database
        // 3. IMAP server configuration
        
        // Example: Load from user config
        // $user_config = new Hm_User_Config_File($site_config);
        // $user_config->load($user_id, $password);
        // $imap_servers = $user_config->get('imap_servers', array());
        // $imap_config = $imap_servers[$imap_server_id];
        
        // For now, hardcoded for testing with Migadu
        return array(
            'provider' => 'migadu',
            'host' => 'imap.migadu.com',
            'port' => 4190,
            'username' => 'YOUR_EMAIL@example.com',  // TODO: Get from user config
            'password' => 'YOUR_PASSWORD'             // TODO: Get from user config
        );
    }
    
    /**
     * Parse existing blocked senders from Sieve script
     */
    private function parse_existing_senders($script) {
        if (empty($script)) {
            return array();
        }
        
        $senders = array();
        
        // Look for CYPHT CONFIG HEADER
        if (preg_match('/#\s*CYPHT CONFIG HEADER.*?#\s*([A-Za-z0-9+\/=]+)/s', $script, $matches)) {
            $encoded = $matches[1];
            $decoded = base64_decode($encoded);
            $data = json_decode($decoded, true);
            if (is_array($data)) {
                $senders = $data;
            }
        }
        
        return $senders;
    }
    
    /**
     * Generate Sieve script for blocked senders
     */
    private function generate_sieve_script($senders, $action = 'move_to_junk') {
        $script = "# CYPHT CONFIG HEADER - DON'T REMOVE\n";
        $script .= "# " . base64_encode(json_encode($senders)) . "\n\n";
        
        $script .= "require [\"fileinto\", \"envelope\"];\n\n";
        
        foreach ($senders as $sender) {
            $script .= "# Block: $sender\n";
            
            if (strpos($sender, '*@') === 0) {
                // Domain block
                $domain = substr($sender, 2);
                $script .= "if address :domain :is \"from\" \"$domain\" {\n";
            } else {
                // Specific sender block
                $script .= "if address :is \"from\" \"$sender\" {\n";
            }
            
            switch ($action) {
                case 'discard':
                    $script .= "    discard;\n";
                    break;
                case 'reject':
                    $script .= "    reject \"Message rejected\";\n";
                    break;
                case 'move_to_junk':
                default:
                    $script .= "    fileinto \"Junk\";\n";
                    break;
            }
            
            $script .= "    stop;\n";
            $script .= "}\n\n";
        }
        
        return $script;
    }
    
    /**
     * Mark entry as synced
     */
    private function mark_synced($entry) {
        if (!$this->dry_run) {
            $this->queue->mark_synced($entry['id']);
        }
        $this->stats['synced']++;
        $this->logger->info("✓ Synced: {$entry['sender_email']} (ID: {$entry['id']})");
    }
    
    /**
     * Mark entry as failed
     */
    private function mark_failed($entry, $error) {
        if (!$this->dry_run) {
            $this->queue->mark_failed($entry['id'], $error);
        }
        $this->stats['failed']++;
        $this->logger->error("✗ Failed: {$entry['sender_email']} - $error");
    }
}

