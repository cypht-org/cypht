<?php

/**
 * Sieve Sync Manager
 * Handles lazy synchronization of blocked senders to Sieve servers
 * @package framework
 * @subpackage spam_filtering
 */

if (!defined('DEBUG_MODE')) { die(); }

/**
 * Sieve Sync Class
 * Provides lazy synchronization functionality for the Sieve queue
 */
class SieveSync {
    
    /**
     * Process the Sieve queue for all users
     * @param object $user_config User configuration object
     * @param object $session Session object
     * @return array Results of the sync operation
     */
    public static function processQueue($user_config = null, $session = null) {
        try {
            delayed_debug_log('SieveSync: Starting lazy sync process');
            
            // Get pending entries from queue
            $pending_entries = SieveQueue::getAll(50); // Limit to 50 entries
            
            if (empty($pending_entries)) {
                delayed_debug_log('SieveSync: No pending entries found');
                return array(
                    'status' => 'success',
                    'message' => 'No pending entries',
                    'processed' => 0,
                    'synced' => 0,
                    'failed' => 0
                );
            }
            
            delayed_debug_log('SieveSync: Found pending entries', array(
                'count' => count($pending_entries)
            ));
            
            // Group entries by user
            $user_groups = array();
            foreach ($pending_entries as $entry) {
                $user_id = $entry['user_id'];
                if (!isset($user_groups[$user_id])) {
                    $user_groups[$user_id] = array();
                }
                $user_groups[$user_id][] = $entry;
            }
            
            delayed_debug_log('SieveSync: Grouped by users', array(
                'user_count' => count($user_groups),
                'users' => array_keys($user_groups)
            ));
            
            $total_processed = 0;
            $total_synced = 0;
            $total_failed = 0;
            
            // Process each user's entries
            foreach ($user_groups as $user_id => $entries) {
                delayed_debug_log('SieveSync: Processing user', array(
                    'user_id' => $user_id,
                    'entry_count' => count($entries)
                ));
                
                $result = self::processUserEntries($user_id, $entries, $user_config, $session);
                
                $total_processed += $result['processed'];
                $total_synced += $result['synced'];
                $total_failed += $result['failed'];
            }
            
            // Clean up old entries
            $cleanup_result = SieveQueue::cleanup();
            
            delayed_debug_log('SieveSync: Completed', array(
                'processed' => $total_processed,
                'synced' => $total_synced,
                'failed' => $total_failed,
                'cleanup' => $cleanup_result
            ));
            
            return array(
                'status' => 'success',
                'message' => 'Sync completed',
                'processed' => $total_processed,
                'synced' => $total_synced,
                'failed' => $total_failed,
                'cleanup' => $cleanup_result
            );
            
        } catch (Exception $e) {
            delayed_debug_log('SieveSync: Error during sync', array(
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ), 'error');
            
            return array(
                'status' => 'error',
                'message' => $e->getMessage(),
                'processed' => 0,
                'synced' => 0,
                'failed' => 0
            );
        }
    }
    
    /**
     * Process entries for a specific user
     * @param string $user_id User identifier
     * @param array $entries Array of queue entries
     * @param object $user_config User configuration object
     * @param object $session Session object
     * @return array Results for this user
     */
    private static function processUserEntries($user_id, $entries, $user_config = null, $session = null) {
        $processed = 0;
        $synced = 0;
        $failed = 0;
        
        // Get Sieve configuration for this user
        $sieve_config = self::getSieveConfig($user_id, $user_config, $session);
        
        if (!$sieve_config) {
            delayed_debug_log('SieveSync: No Sieve config for user', array(
                'user_id' => $user_id
            ), 'warning');
            
            // Mark all entries as failed
            foreach ($entries as $entry) {
                SieveQueue::mark_failed($entry['id'], 'No Sieve configuration found');
                $failed++;
            }
            
            return array(
                'processed' => count($entries),
                'synced' => 0,
                'failed' => $failed
            );
        }
        
        // Create Sieve client
        $provider = $sieve_config['provider'] ?? 'generic';
        $sieve_client = SieveClientFactory::create($provider);
        
        if (!$sieve_client) {
            delayed_debug_log('SieveSync: Failed to create Sieve client', array(
                'user_id' => $user_id,
                'config' => $sieve_config
            ), 'error');
            
            // Mark all entries as failed
            foreach ($entries as $entry) {
                SieveQueue::mark_failed($entry['id'], 'Failed to create Sieve client');
                $failed++;
            }
            
            return array(
                'processed' => count($entries),
                'synced' => 0,
                'failed' => $failed
            );
        }
        
        try {
            // Connect to Sieve server
            $connected = $sieve_client->connect(
                $sieve_config['host'],
                $sieve_config['port'],
                $sieve_config['username'],
                $sieve_config['password']
            );
            
            if (!$connected) {
                throw new Exception('Failed to connect to Sieve server: ' . $sieve_client->getLastError());
            }
            
            delayed_debug_log('SieveSync: Connected to Sieve server', array(
                'user_id' => $user_id,
                'host' => $sieve_config['host'],
                'port' => $sieve_config['port']
            ));
            
            // Process each entry
            foreach ($entries as $entry) {
                $processed++;
                
                try {
                    // Generate rule name
                    $rule_name = 'block_' . str_replace(['@', '.'], ['_at_', '_dot_'], $entry['sender_email']);
                    
                    // Generate rule content
                    $rule_content = self::generateSpamRule($entry['sender_email']);
                    
                    // Add rule to Sieve script
                    $rule_added = $sieve_client->addRule($rule_name, $rule_content);
                    
                    if ($rule_added) {
                        SieveQueue::mark_synced($entry['id']);
                        $synced++;
                        
                        delayed_debug_log('SieveSync: Rule added successfully', array(
                            'user_id' => $user_id,
                            'sender' => $entry['sender_email'],
                            'rule_name' => $rule_name
                        ));
                    } else {
                        throw new Exception('Failed to add rule: ' . $sieve_client->getLastError());
                    }
                    
                } catch (Exception $e) {
                    SieveQueue::mark_failed($entry['id'], $e->getMessage());
                    $failed++;
                    
                    delayed_debug_log('SieveSync: Failed to process entry', array(
                        'user_id' => $user_id,
                        'entry_id' => $entry['id'],
                        'sender' => $entry['sender_email'],
                        'error' => $e->getMessage()
                    ), 'warning');
                }
            }
            
            // Disconnect
            $sieve_client->disconnect();
            
        } catch (Exception $e) {
            delayed_debug_log('SieveSync: Error processing user entries', array(
                'user_id' => $user_id,
                'error' => $e->getMessage()
            ), 'error');
            
            // Mark remaining entries as failed
            foreach ($entries as $entry) {
                SieveQueue::mark_failed($entry['id'], $e->getMessage());
                $failed++;
            }
        }
        
        return array(
            'processed' => $processed,
            'synced' => $synced,
            'failed' => $failed
        );
    }
    
    /**
     * Get Sieve configuration for a user
     * @param string $user_id User identifier
     * @param object $user_config User configuration object
     * @param object $session Session object
     * @return array|false Sieve configuration or false if not found
     */
    private static function getSieveConfig($user_id, $user_config = null, $session = null) {
        // Try to load from user's IMAP configuration
        if ($user_config && $session) {
            try {
                // Initialize IMAP list to access server configurations
                Hm_IMAP_List::init($user_config, $session);
                
                // Get all IMAP servers for this user
                $imap_servers = Hm_IMAP_List::getAll();
                
                delayed_debug_log('SieveSync: Found IMAP servers', array(
                    'user_id' => $user_id,
                    'server_count' => count($imap_servers),
                    'servers' => array_keys($imap_servers)
                ));
                
                // Look for servers that support Sieve (typically Migadu, Dovecot, etc.)
                foreach ($imap_servers as $server_id => $server_config) {
                    // Check if this server supports Sieve
                    if (self::serverSupportsSieve($server_config)) {
                        delayed_debug_log('SieveSync: Found Sieve-capable server', array(
                            'user_id' => $user_id,
                            'server_id' => $server_id,
                            'server_name' => $server_config['name'] ?? 'Unknown',
                            'server_host' => $server_config['server'] ?? 'Unknown'
                        ));
                        
                        // Extract Sieve configuration from IMAP server config
                        $sieve_config = self::extractSieveConfigFromImap($server_config, $server_id);
                        
                        if ($sieve_config) {
                            delayed_debug_log('SieveSync: Generated Sieve config', array(
                                'user_id' => $user_id,
                                'server_id' => $server_id,
                                'sieve_config' => $sieve_config
                            ));
                            
                            return $sieve_config;
                        }
                    }
                }
                
                delayed_debug_log('SieveSync: No Sieve-capable servers found', array(
                    'user_id' => $user_id,
                    'total_servers' => count($imap_servers)
                ));
                
            } catch (Exception $e) {
                delayed_debug_log('SieveSync: Error loading user config', array(
                    'user_id' => $user_id,
                    'error' => $e->getMessage()
                ), 'warning');
            }
        }
        
        // No Sieve configuration found - return null to skip sync
        delayed_debug_log('SieveSync: No Sieve configuration found, skipping sync', array(
            'user_id' => $user_id
        ), 'info');
        
        return null;
    }
    
    /**
     * Check if an IMAP server supports Sieve
     * @param array $server_config Server configuration
     * @return bool True if server supports Sieve
     */
    private static function serverSupportsSieve($server_config) {
        // Check for known Sieve-capable providers
        $sieve_providers = array('migadu', 'dovecot', 'cyrus');
        
        // Check server name/host for known providers
        $server_name = strtolower($server_config['name'] ?? '');
        $server_host = strtolower($server_config['server'] ?? '');
        
        foreach ($sieve_providers as $provider) {
            if (strpos($server_name, $provider) !== false || 
                strpos($server_host, $provider) !== false) {
                return true;
            }
        }
        
        // Check for explicit Sieve support flag
        if (isset($server_config['sieve_support']) && $server_config['sieve_support']) {
            return true;
        }
        
        return false;
    }
    
    /**
     * Extract Sieve configuration from IMAP server configuration
     * @param array $server_config IMAP server configuration
     * @param string $server_id Server identifier
     * @return array|false Sieve configuration or false if not supported
     */
    private static function extractSieveConfigFromImap($server_config, $server_id) {
        $server_host = $server_config['server'] ?? '';
        $server_name = $server_config['name'] ?? '';
        
        // Determine Sieve host and port based on provider
        if (strpos(strtolower($server_host), 'migadu') !== false) {
            return array(
                'provider' => 'migadu',
                'host' => 'imap.migadu.com',  // Migadu uses same host for Sieve
                'port' => 4190,
                'username' => $server_config['user'] ?? '',
                'password' => $server_config['pass'] ?? '',
                'server_id' => $server_id,
                'server_name' => $server_name
            );
        } elseif (strpos(strtolower($server_host), 'dovecot') !== false) {
            return array(
                'provider' => 'dovecot',
                'host' => $server_host,  // Use same host as IMAP
                'port' => 4190,
                'username' => $server_config['user'] ?? '',
                'password' => $server_config['pass'] ?? '',
                'server_id' => $server_id,
                'server_name' => $server_name
            );
        } elseif (strpos(strtolower($server_host), 'cyrus') !== false) {
            return array(
                'provider' => 'cyrus',
                'host' => $server_host,  // Use same host as IMAP
                'port' => 4190,
                'username' => $server_config['user'] ?? '',
                'password' => $server_config['pass'] ?? '',
                'server_id' => $server_id,
                'server_name' => $server_name
            );
        }
        
        return false;
    }
    
    /**
     * Generate a Sieve rule for blocking a sender
     * @param string $sender_email Email address to block
     * @return string Sieve rule content
     */
    private static function generateSpamRule($sender_email) {
        return sprintf(
            'if address :is "From" "%s" {
    fileinto "Junk";
    stop;
}',
            $sender_email
        );
    }
}

