<?php

/**
 * Sieve Queue Wrapper
 * Simplified wrapper around Hm_Sieve_Queue for the lazy sync architecture
 * @package framework
 * @subpackage spam_filtering
 */

if (!defined('DEBUG_MODE')) { die(); }

require_once APP_PATH . 'modules/imap/sieve_queue.php';

/**
 * Sieve Queue Static Class
 * Provides static convenience methods for managing the Sieve sync queue
 */
class SieveQueue {
    
    /**
     * Singleton instance of Hm_Sieve_Queue
     * @var Hm_Sieve_Queue
     */
    private static $instance = null;
    
    /**
     * Get or create the singleton instance
     * @return Hm_Sieve_Queue Instance
     */
    private static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new Hm_Sieve_Queue();
        }
        return self::$instance;
    }
    
    /**
     * Add a sender to the Sieve sync queue
     * @param string $user_id User identifier
     * @param string $sender_email Email address to block
     * @param array $options Additional options
     * @return string|false Entry ID on success, false on failure
     */
    public static function add($user_id, $sender_email, $options = array()) {
        return self::get_instance()->add($user_id, $sender_email, $options);
    }
    
    /**
     * Remove an entry from the Sieve sync queue
     * @param string $id Entry ID
     * @return bool Success status
     */
    public static function remove($id) {
        return self::get_instance()->delete($id);
    }
    
    /**
     * Get all pending entries from the queue
     * @param int $limit Maximum number of entries
     * @return array Array of pending entries
     */
    public static function getAll($limit = 100) {
        return self::get_instance()->get_pending($limit);
    }
    
    /**
     * Check if a sender is pending in the queue
     * @param string $user_id User identifier
     * @param string $sender_email Email address
     * @param string $imap_server_id Server ID
     * @return bool True if pending
     */
    public static function isPending($user_id, $sender_email, $imap_server_id) {
        $queue = self::get_instance();
        $entries = $queue->get_by_user($user_id, $imap_server_id);
        
        foreach ($entries as $entry) {
            if ($entry['sender_email'] === $sender_email && $entry['status'] === 'pending') {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Get queue statistics
     * @return array Statistics
     */
    public static function get_stats() {
        return self::get_instance()->get_stats();
    }
    
    /**
     * Mark an entry as synced
     * @param string $id Entry ID
     * @return bool Success status
     */
    public static function mark_synced($id) {
        return self::get_instance()->mark_synced($id);
    }
    
    /**
     * Mark an entry as failed
     * @param string $id Entry ID
     * @param string $error Error message
     * @return bool Success status
     */
    public static function mark_failed($id, $error) {
        return self::get_instance()->mark_failed($id, $error);
    }
    
    /**
     * Get entries by user
     * @param string $user_id User identifier
     * @param string $imap_server_id Optional server ID filter
     * @return array Array of entries
     */
    public static function get_by_user($user_id, $imap_server_id = null) {
        return self::get_instance()->get_by_user($user_id, $imap_server_id);
    }
    
    /**
     * Get entry by ID
     * @param string $id Entry ID
     * @return array|false Entry data or false
     */
    public static function get_by_id($id) {
        return self::get_instance()->get_by_id($id);
    }
    
    /**
     * Clean up old entries
     * @param bool $force Force cleanup
     * @return array Cleanup results
     */
    public static function cleanup($force = false) {
        return self::get_instance()->cleanup_old_entries($force);
    }
}

