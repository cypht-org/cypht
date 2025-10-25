<?php

/**
 * Sieve Queue Manager
 * Manages a queue of spam senders to be blocked via Sieve filters
 * @package modules
 * @subpackage imap
 */

if (!defined('DEBUG_MODE')) { die(); }

/**
 * Sieve Queue Manager Class
 * Handles queuing and processing of spam sender blocks for async Sieve synchronization
 */
class Hm_Sieve_Queue {
    
    /**
     * Path to the queue file
     * @var string
     */
    private $queue_file;
    
    /**
     * Maximum number of sync attempts before marking as failed
     * @var int
     */
    private $max_attempts = 5;
    
    /**
     * Number of days to keep synced entries before archiving
     * @var int
     */
    private $archive_after_days = 30;
    
    /**
     * Constructor
     * @param string $queue_file_path Optional custom path to queue file
     */
    public function __construct($queue_file_path = null) {
        $this->queue_file = $queue_file_path ?? APP_PATH . 'data/blocked_senders.json';
        $this->initialize_queue_file();
    }
    
    /**
     * Initialize queue file if it doesn't exist
     * @return void
     */
    private function initialize_queue_file() {
        if (!file_exists($this->queue_file)) {
            $initial_data = array(
                'version' => '1.0',
                'last_cleanup' => null,
                'entries' => array()
            );
            $this->write_queue($initial_data);
            Hm_Debug::add('Sieve queue file initialized: ' . $this->queue_file);
        }
    }
    
    /**
     * Read queue data with file locking
     * @return array Queue data
     */
    private function read_queue() {
        if (!file_exists($this->queue_file)) {
            $this->initialize_queue_file();
        }
        
        $fp = fopen($this->queue_file, 'r');
        if (!$fp) {
            Hm_Debug::add('Failed to open queue file for reading', 'error');
            return array('version' => '1.0', 'last_cleanup' => null, 'entries' => array());
        }
        
        // Acquire shared lock for reading
        if (flock($fp, LOCK_SH)) {
            $content = fread($fp, filesize($this->queue_file) ?: 1);
            flock($fp, LOCK_UN);
            fclose($fp);
            
            $data = json_decode($content, true);
            if ($data === null) {
                Hm_Debug::add('Invalid JSON in queue file, reinitializing', 'warning');
                return array('version' => '1.0', 'last_cleanup' => null, 'entries' => array());
            }
            
            return $data;
        } else {
            fclose($fp);
            Hm_Debug::add('Failed to acquire lock on queue file', 'error');
            return array('version' => '1.0', 'last_cleanup' => null, 'entries' => array());
        }
    }
    
    /**
     * Write queue data with file locking
     * @param array $data Queue data to write
     * @return bool Success status
     */
    private function write_queue($data) {
        $fp = fopen($this->queue_file, 'c');
        if (!$fp) {
            Hm_Debug::add('Failed to open queue file for writing', 'error');
            return false;
        }
        
        // Acquire exclusive lock for writing
        if (flock($fp, LOCK_EX)) {
            ftruncate($fp, 0);
            rewind($fp);
            $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
            fwrite($fp, $json);
            fflush($fp);
            flock($fp, LOCK_UN);
            fclose($fp);
            return true;
        } else {
            fclose($fp);
            Hm_Debug::add('Failed to acquire exclusive lock on queue file', 'error');
            return false;
        }
    }
    
    /**
     * Generate unique ID for queue entry
     * @return string UUID-like identifier
     */
    private function generate_id() {
        return sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );
    }
    
    /**
     * Add a new spam sender to the queue
     * @param string $user_id User identifier
     * @param string $sender_email Email address to block
     * @param array $options Additional options (block_scope, block_action, imap_server_id, etc.)
     * @return string|false Entry ID on success, false on failure
     */
    public function add($user_id, $sender_email, $options = array()) {
        // Validate required fields
        if (empty($user_id) || empty($sender_email)) {
            Hm_Debug::add('Cannot add to queue: user_id and sender_email are required', 'error');
            return false;
        }
        
        if (!isset($options['imap_server_id']) || empty($options['imap_server_id'])) {
            Hm_Debug::add('Cannot add to queue: imap_server_id is required', 'error');
            return false;
        }
        
        $queue = $this->read_queue();
        
        // Check for duplicates (same user, sender, server combination that's pending or recently synced)
        foreach ($queue['entries'] as $entry) {
            if ($entry['user_id'] == $user_id && 
                $entry['sender_email'] == $sender_email && 
                $entry['imap_server_id'] == $options['imap_server_id'] &&
                in_array($entry['status'], array('pending', 'synced'))) {
                
                Hm_Debug::add(sprintf(
                    'Duplicate entry found for %s (user: %s, server: %s), skipping',
                    $sender_email, $user_id, $options['imap_server_id']
                ));
                
                return $entry['id']; // Return existing ID
            }
        }
        
        $now = gmdate('Y-m-d\TH:i:s\Z');
        
        $entry = array(
            'id' => $this->generate_id(),
            'user_id' => (string)$user_id,
            'username' => isset($options['username']) ? $options['username'] : '',
            'sender_email' => $sender_email,
            'block_scope' => isset($options['block_scope']) ? $options['block_scope'] : 'sender',
            'block_action' => isset($options['block_action']) ? $options['block_action'] : 'move_to_junk',
            'status' => 'pending',
            'imap_server_id' => $options['imap_server_id'],
            'spam_reason' => isset($options['spam_reason']) ? $options['spam_reason'] : '',
            'message_uid' => isset($options['message_uid']) ? $options['message_uid'] : '',
            'folder' => isset($options['folder']) ? $options['folder'] : '',
            'created_at' => $now,
            'updated_at' => $now,
            'last_sync_attempt' => null,
            'sync_attempts' => 0,
            'sync_error' => null,
            'retry_after' => null,
            'synced_at' => null
        );
        
        $queue['entries'][] = $entry;
        
        if ($this->write_queue($queue)) {
            Hm_Debug::add(sprintf(
                'Added to Sieve queue: %s (ID: %s, User: %s, Server: %s)',
                $sender_email, $entry['id'], $user_id, $options['imap_server_id']
            ));
            return $entry['id'];
        }
        
        return false;
    }
    
    /**
     * Get pending entries ready for sync
     * @param int $limit Maximum number of entries to return
     * @return array Array of pending entries
     */
    public function get_pending($limit = 100) {
        $queue = $this->read_queue();
        $pending = array();
        $now = time();
        
        foreach ($queue['entries'] as $entry) {
            if ($entry['status'] !== 'pending') {
                continue;
            }
            
            // Check if we should retry this entry
            if ($entry['retry_after'] !== null) {
                $retry_time = strtotime($entry['retry_after']);
                if ($now < $retry_time) {
                    continue; // Not ready for retry yet
                }
            }
            
            // Check if max attempts reached
            if ($entry['sync_attempts'] >= $this->max_attempts) {
                // Mark as failed/skipped
                $this->mark_failed($entry['id'], 'Maximum retry attempts reached');
                continue;
            }
            
            $pending[] = $entry;
            
            if (count($pending) >= $limit) {
                break;
            }
        }
        
        return $pending;
    }
    
    /**
     * Mark an entry as successfully synced
     * @param string $id Entry ID
     * @return bool Success status
     */
    public function mark_synced($id) {
        $queue = $this->read_queue();
        $found = false;
        
        foreach ($queue['entries'] as &$entry) {
            if ($entry['id'] === $id) {
                $entry['status'] = 'synced';
                $entry['synced_at'] = gmdate('Y-m-d\TH:i:s\Z');
                $entry['updated_at'] = gmdate('Y-m-d\TH:i:s\Z');
                $entry['sync_error'] = null;
                $found = true;
                break;
            }
        }
        unset($entry);
        
        if ($found) {
            if ($this->write_queue($queue)) {
                Hm_Debug::add('Marked entry as synced: ' . $id);
                return true;
            }
        } else {
            Hm_Debug::add('Entry not found for marking as synced: ' . $id, 'warning');
        }
        
        return false;
    }
    
    /**
     * Mark an entry as failed and schedule retry
     * @param string $id Entry ID
     * @param string $error Error message
     * @return bool Success status
     */
    public function mark_failed($id, $error) {
        $queue = $this->read_queue();
        $found = false;
        
        foreach ($queue['entries'] as &$entry) {
            if ($entry['id'] === $id) {
                $entry['sync_attempts']++;
                $entry['last_sync_attempt'] = gmdate('Y-m-d\TH:i:s\Z');
                $entry['updated_at'] = gmdate('Y-m-d\TH:i:s\Z');
                $entry['sync_error'] = $error;
                
                // Calculate exponential backoff
                if ($entry['sync_attempts'] >= $this->max_attempts) {
                    // Max attempts reached, mark as skipped
                    $entry['status'] = 'skipped';
                    $entry['retry_after'] = null;
                    Hm_Debug::add(sprintf(
                        'Entry marked as skipped after %d attempts: %s',
                        $entry['sync_attempts'], $id
                    ), 'warning');
                } else {
                    // Calculate retry delay: 1min, 5min, 15min, 30min, 60min
                    $delays = array(60, 300, 900, 1800, 3600);
                    $delay_index = min($entry['sync_attempts'] - 1, count($delays) - 1);
                    $delay_seconds = $delays[$delay_index];
                    
                    $entry['retry_after'] = gmdate('Y-m-d\TH:i:s\Z', time() + $delay_seconds);
                    $entry['status'] = 'pending';
                }
                
                $found = true;
                break;
            }
        }
        unset($entry);
        
        if ($found) {
            if ($this->write_queue($queue)) {
                Hm_Debug::add(sprintf(
                    'Marked entry as failed (attempt %d): %s - %s',
                    $queue['entries'][array_search($id, array_column($queue['entries'], 'id'))]['sync_attempts'],
                    $id, $error
                ));
                return true;
            }
        } else {
            Hm_Debug::add('Entry not found for marking as failed: ' . $id, 'warning');
        }
        
        return false;
    }
    
    /**
     * Get all entries for a specific user
     * @param string $user_id User identifier
     * @param string $imap_server_id Optional server ID to filter by
     * @return array Array of entries
     */
    public function get_by_user($user_id, $imap_server_id = null) {
        $queue = $this->read_queue();
        $user_entries = array();
        
        foreach ($queue['entries'] as $entry) {
            if ($entry['user_id'] == $user_id) {
                if ($imap_server_id === null || $entry['imap_server_id'] == $imap_server_id) {
                    $user_entries[] = $entry;
                }
            }
        }
        
        return $user_entries;
    }
    
    /**
     * Get entry by ID
     * @param string $id Entry ID
     * @return array|false Entry data or false if not found
     */
    public function get_by_id($id) {
        $queue = $this->read_queue();
        
        foreach ($queue['entries'] as $entry) {
            if ($entry['id'] === $id) {
                return $entry;
            }
        }
        
        return false;
    }
    
    /**
     * Get statistics about the queue
     * @return array Queue statistics
     */
    public function get_stats() {
        $queue = $this->read_queue();
        $stats = array(
            'total' => count($queue['entries']),
            'pending' => 0,
            'synced' => 0,
            'failed' => 0,
            'skipped' => 0,
            'oldest_pending' => null,
            'last_cleanup' => $queue['last_cleanup']
        );
        
        foreach ($queue['entries'] as $entry) {
            if (isset($stats[$entry['status']])) {
                $stats[$entry['status']]++;
            }
            
            if ($entry['status'] === 'pending' && 
                ($stats['oldest_pending'] === null || $entry['created_at'] < $stats['oldest_pending'])) {
                $stats['oldest_pending'] = $entry['created_at'];
            }
        }
        
        return $stats;
    }
    
    /**
     * Clean up old synced entries (archive after 30 days)
     * @param bool $force Force cleanup even if recently done
     * @return array Cleanup results
     */
    public function cleanup_old_entries($force = false) {
        $queue = $this->read_queue();
        $now = time();
        
        // Check if cleanup is needed (only run once per day unless forced)
        if (!$force && $queue['last_cleanup'] !== null) {
            $last_cleanup = strtotime($queue['last_cleanup']);
            if ($now - $last_cleanup < 86400) { // 24 hours
                return array(
                    'skipped' => true,
                    'reason' => 'Cleanup already performed recently'
                );
            }
        }
        
        $cutoff_date = gmdate('Y-m-d\TH:i:s\Z', $now - ($this->archive_after_days * 86400));
        $archived = array();
        $new_entries = array();
        
        foreach ($queue['entries'] as $entry) {
            // Archive synced entries older than 30 days
            if ($entry['status'] === 'synced' && $entry['synced_at'] < $cutoff_date) {
                $archived[] = $entry;
            } else {
                $new_entries[] = $entry;
            }
        }
        
        $queue['entries'] = $new_entries;
        $queue['last_cleanup'] = gmdate('Y-m-d\TH:i:s\Z');
        
        // Archive old entries to a separate file
        if (!empty($archived)) {
            $archive_file = str_replace('.json', '_archive_' . gmdate('Y_m') . '.json', $this->queue_file);
            
            // Read existing archive if it exists
            $existing_archive = array();
            if (file_exists($archive_file)) {
                $existing_archive = json_decode(file_get_contents($archive_file), true) ?: array();
            }
            
            // Append new archived entries
            $existing_archive = array_merge($existing_archive, $archived);
            
            file_put_contents(
                $archive_file,
                json_encode($existing_archive, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
            );
        }
        
        $this->write_queue($queue);
        
        Hm_Debug::add(sprintf(
            'Queue cleanup completed: %d entries archived, %d remaining',
            count($archived), count($new_entries)
        ));
        
        return array(
            'archived' => count($archived),
            'remaining' => count($new_entries),
            'archive_file' => isset($archive_file) ? $archive_file : null
        );
    }
    
    /**
     * Delete an entry from the queue
     * @param string $id Entry ID
     * @return bool Success status
     */
    public function delete($id) {
        $queue = $this->read_queue();
        $initial_count = count($queue['entries']);
        
        $queue['entries'] = array_filter($queue['entries'], function($entry) use ($id) {
            return $entry['id'] !== $id;
        });
        
        // Re-index array
        $queue['entries'] = array_values($queue['entries']);
        
        if (count($queue['entries']) < $initial_count) {
            if ($this->write_queue($queue)) {
                Hm_Debug::add('Deleted entry from queue: ' . $id);
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Clear all entries from the queue (use with caution!)
     * @param string $status Optional: only clear entries with this status
     * @return bool Success status
     */
    public function clear($status = null) {
        $queue = $this->read_queue();
        
        if ($status === null) {
            $queue['entries'] = array();
        } else {
            $queue['entries'] = array_filter($queue['entries'], function($entry) use ($status) {
                return $entry['status'] !== $status;
            });
            $queue['entries'] = array_values($queue['entries']);
        }
        
        return $this->write_queue($queue);
    }
}

