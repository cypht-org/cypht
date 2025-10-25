<?php

/**
 * Spam Queue Integration Functions
 * Functions to integrate the Sieve queue with the spam report handler
 * @package modules
 * @subpackage imap
 */

if (!defined('DEBUG_MODE')) { die(); }

/**
 * Queue spam sender for async Sieve synchronization
 * This replaces the synchronous auto_block_spam_sender() function
 * 
 * @param object $user_config User configuration object
 * @param object $session Session object (for getting username)
 * @param string $imap_server_id IMAP server ID
 * @param array $message_data Message data containing sender information
 * @param string $spam_reason Reason for spam report
 * @param string $junk_folder Junk folder name (for reference)
 * @return array Result of queue operation
 */
function queue_spam_sender_for_blocking($user_config, $session, $imap_server_id, $message_data, $spam_reason, $junk_folder = 'Junk') {
    try {
        // Check if auto-blocking is enabled
        if (!is_auto_block_spam_enabled($user_config)) {
            delayed_debug_log('Queue spam sender: Auto-blocking disabled by user');
            return array(
                'success' => true, 
                'queued' => false,
                'message' => 'Auto-blocking disabled'
            );
        }

        // Load the queue manager
        if (!class_exists('Hm_Sieve_Queue')) {
            require_once APP_PATH . 'modules/imap/sieve_queue.php';
        }

        // Extract sender email from message headers
        $sender_email = extract_sender_email_from_headers($message_data['headers']);
        if (!$sender_email) {
            delayed_debug_log('Queue spam sender: Could not extract sender email');
            return array(
                'success' => false, 
                'queued' => false,
                'error' => 'Could not extract sender email'
            );
        }

        // Get auto-block configuration
        $block_action = get_auto_block_spam_action($user_config);
        $block_scope = get_auto_block_spam_scope($user_config);

        // Adjust sender email based on scope
        $sender_to_block = $sender_email;
        if ($block_scope === 'domain') {
            $domain = get_domain($sender_email);
            if ($domain) {
                $sender_to_block = '*@' . $domain;
            }
        }

        delayed_debug_log('Queue spam sender: Preparing to queue', array(
            'original_sender' => $sender_email,
            'sender_to_block' => $sender_to_block,
            'block_action' => $block_action,
            'block_scope' => $block_scope,
            'server_id' => $imap_server_id
        ));

        // Initialize queue
        $queue = new Hm_Sieve_Queue();

        // Get user details from session
        $username = $session->get('username', '');
        $user_id = !empty($username) ? $username : 'unknown';

        // Add to queue
        $entry_id = $queue->add(
            $user_id,
            $sender_to_block,
            array(
                'username' => $username,
                'block_scope' => $block_scope,
                'block_action' => $block_action,
                'imap_server_id' => $imap_server_id,
                'spam_reason' => $spam_reason,
                'message_uid' => isset($message_data['uid']) ? $message_data['uid'] : '',
                'folder' => isset($message_data['folder']) ? $message_data['folder'] : ''
            )
        );

        if ($entry_id) {
            delayed_debug_log('Queue spam sender: Successfully queued', array(
                'entry_id' => $entry_id,
                'sender' => $sender_to_block,
                'user_id' => $user_id,
                'server_id' => $imap_server_id
            ));

            return array(
                'success' => true,
                'queued' => true,
                'entry_id' => $entry_id,
                'sender' => $sender_to_block,
                'message' => sprintf(
                    'Sender %s queued for blocking (action: %s). Will be synchronized shortly.',
                    $sender_to_block,
                    $block_action
                )
            );
        } else {
            delayed_debug_log('Queue spam sender: Failed to add to queue');
            return array(
                'success' => false,
                'queued' => false,
                'error' => 'Failed to add to queue'
            );
        }

    } catch (Exception $e) {
        delayed_debug_log('Queue spam sender: Exception occurred', array(
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ));
        return array(
            'success' => false,
            'queued' => false,
            'error' => 'Queue operation failed: ' . $e->getMessage()
        );
    }
}

/**
 * Get queue statistics for a specific user
 * Useful for displaying blocked senders status in UI
 * 
 * @param object $user_config User configuration object
 * @param object $session Session object (for getting username)
 * @param string $imap_server_id Optional server ID to filter by
 * @return array Queue statistics for the user
 */
function get_user_blocked_senders_queue($user_config, $session, $imap_server_id = null) {
    try {
        if (!class_exists('Hm_Sieve_Queue')) {
            require_once APP_PATH . 'modules/imap/sieve_queue.php';
        }

        $queue = new Hm_Sieve_Queue();
        $username = $session->get('username', '');
        $user_id = !empty($username) ? $username : 'unknown';
        
        $entries = $queue->get_by_user($user_id, $imap_server_id);
        
        // Organize by status
        $result = array(
            'total' => count($entries),
            'pending' => 0,
            'synced' => 0,
            'failed' => 0,
            'skipped' => 0,
            'entries' => $entries
        );
        
        foreach ($entries as $entry) {
            if (isset($result[$entry['status']])) {
                $result[$entry['status']]++;
            }
        }
        
        return $result;
        
    } catch (Exception $e) {
        delayed_debug_log('Get user queue: Exception occurred', array(
            'error' => $e->getMessage()
        ));
        return array(
            'total' => 0,
            'pending' => 0,
            'synced' => 0,
            'failed' => 0,
            'skipped' => 0,
            'entries' => array(),
            'error' => $e->getMessage()
        );
    }
}

/**
 * Check if a sender is already queued or blocked for a user
 * 
 * @param object $user_config User configuration object
 * @param object $session Session object (for getting username)
 * @param string $sender_email Email to check
 * @param string $imap_server_id Server ID
 * @return array Status information
 */
function check_sender_queue_status($user_config, $session, $sender_email, $imap_server_id) {
    try {
        if (!class_exists('Hm_Sieve_Queue')) {
            require_once APP_PATH . 'modules/imap/sieve_queue.php';
        }

        $queue = new Hm_Sieve_Queue();
        $username = $session->get('username', '');
        $user_id = !empty($username) ? $username : 'unknown';
        
        $entries = $queue->get_by_user($user_id, $imap_server_id);
        
        foreach ($entries as $entry) {
            if ($entry['sender_email'] === $sender_email) {
                return array(
                    'exists' => true,
                    'status' => $entry['status'],
                    'entry' => $entry
                );
            }
        }
        
        return array('exists' => false);
        
    } catch (Exception $e) {
        return array(
            'exists' => false,
            'error' => $e->getMessage()
        );
    }
}

