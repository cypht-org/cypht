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
            
            // Group entries by user and server
            $entry_groups = array();
            foreach ($pending_entries as $entry) {
                $user_id = $entry['user_id'];
                $server_id = $entry['imap_server_id'] ?? 'unknown';

                if (!isset($entry_groups[$user_id])) {
                    $entry_groups[$user_id] = array();
                }
                if (!isset($entry_groups[$user_id][$server_id])) {
                    $entry_groups[$user_id][$server_id] = array();
                }

                $entry_groups[$user_id][$server_id][] = $entry;
            }
            
            delayed_debug_log('SieveSync: Grouped by users and servers', array(
                'user_count' => count($entry_groups),
                'users' => array_keys($entry_groups)
            ));
            
            $total_processed = 0;
            $total_synced = 0;
            $total_failed = 0;
            
            // Process each user/server group
            foreach ($entry_groups as $user_id => $server_groups) {
                foreach ($server_groups as $server_id => $entries) {
                    delayed_debug_log('SieveSync: Processing user/server group', array(
                        'user_id' => $user_id,
                        'server_id' => $server_id,
                        'entry_count' => count($entries)
                    ));
                    
                    $result = self::processServerEntries($user_id, $server_id, $entries, $user_config, $session);
                    
                    $total_processed += $result['processed'];
                    $total_synced += $result['synced'];
                    $total_failed += $result['failed'];
                }
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
    private static function processServerEntries($user_id, $server_id, $entries, $user_config = null, $session = null) {
        $processed = 0;
        $synced = 0;
        $failed = 0;
        
        // Get Sieve configuration for this user/server combination
        $sieve_config = self::getSieveConfig($user_id, $server_id, $user_config, $session);
        
        if (!$sieve_config) {
            delayed_debug_log('SieveSync: No Sieve config for user/server', array(
                'user_id' => $user_id,
                'server_id' => $server_id
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
            $username = $sieve_config['username'] ?? '';
            $password = $sieve_config['password'] ?? '';
            $options = $sieve_config['options'] ?? array();

            if (!is_array($options)) {
                $options = array();
            }

            if (empty($username) || empty($password)) {
                throw new Exception('Sieve credentials missing for server ' . ($sieve_config['server_id'] ?? 'unknown'));
            }

            $connected = $sieve_client->connect(
                $sieve_config['host'],
                $sieve_config['port'],
                $username,
                $password,
                $options
            );
            
            if (!$connected) {
                throw new Exception('Failed to connect to Sieve server: ' . $sieve_client->getLastError());
            }
            
            delayed_debug_log('SieveSync: Connected to Sieve server', array(
                'user_id' => $user_id,
                'server_id' => $server_id,
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
            delayed_debug_log('SieveSync: Error processing server entries', array(
                'user_id' => $user_id,
                'server_id' => $server_id,
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
     * Get Sieve configuration for a user/server combination
     * @param string $user_id User identifier
     * @param string $server_id Server identifier
     * @param object $user_config User configuration object
     * @param object $session Session object
     * @return array|false Sieve configuration or false if not found
     */
    private static function getSieveConfig($user_id, $server_id, $user_config = null, $session = null) {
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
                
                if (!isset($imap_servers[$server_id])) {
                    delayed_debug_log('SieveSync: Target server not found for user', array(
                        'user_id' => $user_id,
                        'server_id' => $server_id
                    ), 'warning');
                    return null;
                }

                $server_config = $imap_servers[$server_id];

                if (!self::serverSupportsSieve($server_config)) {
                    delayed_debug_log('SieveSync: Server does not support Sieve', array(
                        'user_id' => $user_id,
                        'server_id' => $server_id,
                        'server_name' => $server_config['name'] ?? 'Unknown',
                        'server_host' => $server_config['server'] ?? 'Unknown'
                    ), 'warning');
                    return null;
                }

                delayed_debug_log('SieveSync: Found Sieve-capable server', array(
                    'user_id' => $user_id,
                    'server_id' => $server_id,
                    'server_name' => $server_config['name'] ?? 'Unknown',
                    'server_host' => $server_config['server'] ?? 'Unknown',
                    'sieve_config_host' => $server_config['sieve_config_host'] ?? null
                ));

                // Extract Sieve configuration from IMAP server config
                $sieve_config = self::extractSieveConfigFromImap($server_config, $server_id);

                if ($sieve_config) {
                    $sieve_config = self::enrichSieveCredentials($server_id, $sieve_config);

                    delayed_debug_log('SieveSync: Generated Sieve config', array(
                        'user_id' => $user_id,
                        'server_id' => $server_id,
                        'sieve_config' => $sieve_config
                    ));

                    return $sieve_config;
                }

                delayed_debug_log('SieveSync: Failed to extract Sieve config for server', array(
                    'user_id' => $user_id,
                    'server_id' => $server_id
                ), 'warning');
                
                delayed_debug_log('SieveSync: No Sieve-capable servers found', array(
                    'user_id' => $user_id,
                    'target_server_id' => $server_id,
                    'total_servers' => count($imap_servers)
                ));
                
            } catch (Exception $e) {
                delayed_debug_log('SieveSync: Error loading user config', array(
                    'user_id' => $user_id,
                    'server_id' => $server_id,
                    'error' => $e->getMessage()
                ), 'warning');
            }
        }
        
        // No Sieve configuration found - return null to skip sync
        delayed_debug_log('SieveSync: No Sieve configuration found, skipping sync', array(
            'user_id' => $user_id,
            'server_id' => $server_id
        ), 'info');
        
        return null;
    }
    
    /**
     * Check if an IMAP server supports Sieve
     * @param array $server_config Server configuration
     * @return bool True if server supports Sieve
     */
    private static function serverSupportsSieve($server_config) {
        $type = strtolower($server_config['type'] ?? 'imap');
        if ($type !== 'imap') {
            return false;
        }

        if (!empty($server_config['sieve_config_host'])) {
            return true;
        }

        if (!empty($server_config['sieve_support'])) {
            return true;
        }

        $detected_provider = self::detectSieveProvider($server_config);
        return $detected_provider !== 'generic';
    }
    
    /**
     * Extract Sieve configuration from IMAP server configuration
     * @param array $server_config IMAP server configuration
     * @param string $server_id Server identifier
     * @return array|false Sieve configuration or false if not supported
     */
    private static function extractSieveConfigFromImap($server_config, $server_id) {
        $server_name = $server_config['name'] ?? '';
        $provider_hint = strtolower($server_config['sieve_provider'] ?? '');

        if (!empty($server_config['sieve_config_host'])) {
            $parsed = self::parseSieveEndpoint($server_config['sieve_config_host'], $server_config);

            if ($parsed) {
                $provider = $provider_hint ?: self::detectSieveProvider($server_config, $parsed['host']);

                return array(
                    'provider' => $provider,
                    'host' => $parsed['host'],
                    'port' => $parsed['port'],
                    'username' => $server_config['user'] ?? '',
                    'password' => $server_config['pass'] ?? '',
                    'options' => array('use_tls' => $parsed['tls']),
                    'server_id' => $server_id,
                    'server_name' => $server_name,
                    'source' => 'explicit_sieve_config'
                );
            }
        }

        $provider = $provider_hint ?: self::detectSieveProvider($server_config);
        $server_host = $server_config['server'] ?? '';

        switch ($provider) {
            case 'migadu':
                $host = 'imap.migadu.com';
                break;
            case 'dovecot':
            case 'cyrus':
                $host = $server_host;
                break;
            default:
                $host = '';
        }

        if (!empty($host)) {
            return array(
                'provider' => $provider,
                'host' => $host,
                'port' => 4190,
                'username' => $server_config['user'] ?? '',
                'password' => $server_config['pass'] ?? '',
                'options' => array('use_tls' => isset($server_config['tls']) ? (bool)$server_config['tls'] : true),
                'server_id' => $server_id,
                'server_name' => $server_name,
                'source' => 'provider_heuristic'
            );
        }

        return false;
    }

    /**
     * Parse a sieve_config_host value into host/port/TLS components
     * @param string $raw_endpoint
     * @param array $server_config
     * @return array|false
     */
    private static function parseSieveEndpoint($raw_endpoint, $server_config) {
        $raw_endpoint = trim((string)$raw_endpoint);
        if ($raw_endpoint === '') {
            return false;
        }

        $has_scheme = preg_match('#^[a-z][a-z0-9+\-.]*://#i', $raw_endpoint) === 1;
        $endpoint_to_parse = $has_scheme ? $raw_endpoint : 'sieve://' . $raw_endpoint;

        $parts = parse_url($endpoint_to_parse);
        if ($parts === false) {
            delayed_debug_log('SieveSync: Failed to parse sieve_config_host', array(
                'endpoint' => $raw_endpoint
            ), 'warning');
            return false;
        }

        $host = $parts['host'] ?? $parts['path'] ?? '';
        $host = trim($host, '[]');
        if ($host === '') {
            delayed_debug_log('SieveSync: Parsed Sieve host empty', array(
                'endpoint' => $raw_endpoint
            ), 'warning');
            return false;
        }

        $port = isset($parts['port']) ? (int)$parts['port'] : 4190;
        if ($port <= 0 || $port > 65535) {
            $port = 4190;
        }

        $tls = null;
        if (!empty($parts['scheme'])) {
            $scheme = strtolower($parts['scheme']);
            if (in_array($scheme, array('tls', 'sieves', 'sieve+tls', 'ssl'), true)) {
                $tls = true;
            } elseif (in_array($scheme, array('tcp', 'imap'), true)) {
                $tls = false;
            }
        }

        if (!empty($parts['query'])) {
            parse_str($parts['query'], $query);
            if (isset($query['tls'])) {
                $tls_flag = filter_var($query['tls'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
                if ($tls_flag !== null) {
                    $tls = $tls_flag;
                }
            }
        }

        if ($tls === null) {
            $tls = isset($server_config['tls']) ? (bool)$server_config['tls'] : true;
        }

        return array(
            'host' => $host,
            'port' => $port,
            'tls' => $tls
        );
    }

    /**
     * Detect provider from server configuration
     * @param array $server_config
     * @param string $resolved_host
     * @return string
     */
    private static function detectSieveProvider($server_config, $resolved_host = '') {
        if (!empty($server_config['sieve_provider'])) {
            return strtolower($server_config['sieve_provider']);
        }

        $haystack = strtolower(trim(
            ($resolved_host ? $resolved_host . ' ' : '') .
            ($server_config['server'] ?? '') . ' ' .
            ($server_config['name'] ?? '')
        ));

        $providers = array('migadu', 'dovecot', 'cyrus');
        foreach ($providers as $provider) {
            if (strpos($haystack, $provider) !== false) {
                return $provider;
            }
        }

        return 'generic';
    }

    /**
     * Ensure Sieve credentials are populated using the stored mailbox definition
     * @param string $server_id
     * @param array $sieve_config
     * @return array
     */
    private static function enrichSieveCredentials($server_id, array $sieve_config) {
        try {
            $full_details = Hm_IMAP_List::dumpForMailbox($server_id);

            if (is_array($full_details)) {
                if (empty($sieve_config['username']) && !empty($full_details['username'])) {
                    $sieve_config['username'] = $full_details['username'];
                }
                if (empty($sieve_config['password']) && !empty($full_details['password'])) {
                    $sieve_config['password'] = $full_details['password'];
                }

                $existing_options = $sieve_config['options'] ?? array();
                if (!is_array($existing_options)) {
                    $existing_options = array();
                }

                if (!isset($existing_options['use_tls']) && isset($full_details['tls'])) {
                    $existing_options['use_tls'] = (bool)$full_details['tls'];
                }

                $sieve_config['options'] = $existing_options;
            }
        } catch (Exception $e) {
            delayed_debug_log('SieveSync: Failed to enrich credentials', array(
                'server_id' => $server_id,
                'error' => $e->getMessage()
            ), 'warning');
        }

        if (empty($sieve_config['username']) || empty($sieve_config['password'])) {
            delayed_debug_log('SieveSync: Sieve credentials incomplete after enrichment', array(
                'server_id' => $server_id,
                'has_username' => !empty($sieve_config['username']),
                'has_password' => !empty($sieve_config['password'])
            ), 'warning');
        }

        return $sieve_config;
    }
    
    /**
     * Generate a Sieve rule for blocking a sender
     * @param string $sender_email Email address to block
     * @return string Sieve rule content
     */
    private static function generateSpamRule($sender_email) {
        return sprintf(
            'require ["fileinto"];

if address :is "From" "%s" {
    fileinto "Junk";
    stop;
}',
            $sender_email
        );
    }
}

