<?php

/**
 * Local Block List Manager
 * Manages immediate local filtering of blocked email senders
 * @package framework
 * @subpackage spam_filtering
 */

if (!defined('DEBUG_MODE')) { die(); }

/**
 * Local Block List Class
 * Provides instant local filtering for blocked senders (independent of Sieve sync)
 */
class LocalBlockList {
    
    /**
     * Path to the local block list file
     * @var string
     */
    private static $file = 'data/local_blocked.json';
    
    /**
     * Get full path to the file
     * @return string Full file path
     */
    private static function get_file_path() {
        return APP_PATH . self::$file;
    }
    
    /**
     * Read block list data with file locking
     * @return array Block list data
     */
    private static function read_list() {
        $file_path = self::get_file_path();
        
        if (!file_exists($file_path)) {
            self::initialize_file();
        }
        
        $fp = fopen($file_path, 'r');
        if (!$fp) {
            Hm_Debug::add('Failed to open local block list for reading', 'error');
            return array('blocked_senders' => array());
        }
        
        // Acquire shared lock for reading
        if (flock($fp, LOCK_SH)) {
            $content = fread($fp, filesize($file_path) ?: 1);
            flock($fp, LOCK_UN);
            fclose($fp);
            
            $data = json_decode($content, true);
            if ($data === null) {
                Hm_Debug::add('Invalid JSON in local block list, reinitializing', 'warning');
                return array('blocked_senders' => array());
            }
            
            return $data;
        } else {
            fclose($fp);
            Hm_Debug::add('Failed to acquire lock on local block list', 'error');
            return array('blocked_senders' => array());
        }
    }
    
    /**
     * Write block list data with file locking
     * @param array $data Block list data to write
     * @return bool Success status
     */
    private static function write_list($data) {
        $file_path = self::get_file_path();

        $dir = dirname($file_path);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        
        $fp = fopen($file_path, 'c');
        if (!$fp) {
            Hm_Debug::add('Failed to open local block list for writing', 'error');
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
            Hm_Debug::add('Failed to acquire exclusive lock on local block list', 'error');
            return false;
        }
    }
    
    /**
     * Initialize block list file if it doesn't exist
     * @return void
     */
    private static function initialize_file() {
        $file_path = self::get_file_path();
        
        if (!file_exists($file_path)) {
            $initial_data = array(
                'version' => '1.0',
                'last_updated' => gmdate('Y-m-d\TH:i:s\Z'),
                'blocked_senders' => array()
            );
            
            $dir = dirname($file_path);
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
            
            file_put_contents($file_path, json_encode($initial_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            Hm_Debug::add('Local block list file initialized: ' . $file_path);
        }
    }
    
    /**
     * Normalize email address
     * @param string $email Email address
     * @return string Normalized email
     */
    private static function normalize_email($email) {
        return strtolower(trim($email));
    }
    
    /**
     * Add a sender to the local block list
     * @param string $email Email address to block
     * @return bool Success status
     */
    public static function add($email) {
        $email = self::normalize_email($email);
        
        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            Hm_Debug::add('Invalid email address: ' . $email, 'error');
            return false;
        }
        
        $data = self::read_list();

        if (in_array($email, $data['blocked_senders'])) {
            Hm_Debug::add('Email already in local block list: ' . $email);
            return true; // Consider it success
        }

        $data['blocked_senders'][] = $email;
        $data['last_updated'] = gmdate('Y-m-d\TH:i:s\Z');
        
        if (self::write_list($data)) {
            Hm_Debug::add('Added to local block list: ' . $email);
            return true;
        }
        
        return false;
    }
    
    /**
     * Check if an email exists in the local block list
     * @param string $email Email address to check
     * @return bool True if blocked
     */
    public static function exists($email) {
        $email = self::normalize_email($email);
        
        if (empty($email)) {
            return false;
        }
        
        $data = self::read_list();
        return in_array($email, $data['blocked_senders']);
    }
    
    /**
     * Get all blocked senders
     * @return array Array of blocked email addresses
     */
    public static function getAll() {
        $data = self::read_list();
        return $data['blocked_senders'];
    }
    
    /**
     * Remove a sender from the local block list
     * @param string $email Email address to unblock
     * @return bool Success status
     */
    public static function remove($email) {
        $email = self::normalize_email($email);
        
        if (empty($email)) {
            return false;
        }
        
        $data = self::read_list();

        $key = array_search($email, $data['blocked_senders']);
        if ($key === false) {
            Hm_Debug::add('Email not found in local block list: ' . $email);
            return false;
        }
        
        // Remove from list
        unset($data['blocked_senders'][$key]);
        $data['blocked_senders'] = array_values($data['blocked_senders']); // Re-index
        $data['last_updated'] = gmdate('Y-m-d\TH:i:s\Z');
        
        if (self::write_list($data)) {
            Hm_Debug::add('Removed from local block list: ' . $email);
            return true;
        }
        
        return false;
    }
    
    /**
     * Get statistics about the block list
     * @return array Statistics
     */
    public static function get_stats() {
        $data = self::read_list();
        return array(
            'total_blocked' => count($data['blocked_senders']),
            'last_updated' => $data['last_updated']
        );
    }
    
    /**
     * Clear all blocked senders (use with caution!)
     * @return bool Success status
     */
    public static function clear() {
        $data = self::read_list();
        $data['blocked_senders'] = array();
        $data['last_updated'] = gmdate('Y-m-d\TH:i:s\Z');
        
        return self::write_list($data);
    }
    
    /**
     * Bulk add senders to the block list
     * @param array $emails Array of email addresses
     * @return array Results: added, skipped, failed
     */
    public static function bulk_add($emails) {
        $results = array(
            'added' => 0,
            'skipped' => 0,
            'failed' => 0
        );
        
        foreach ($emails as $email) {
            $email = self::normalize_email($email);
            
            if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $results['failed']++;
                continue;
            }
            
            if (self::exists($email)) {
                $results['skipped']++;
                continue;
            }
            
            if (self::add($email)) {
                $results['added']++;
            } else {
                $results['failed']++;
            }
        }
        
        return $results;
    }
}

