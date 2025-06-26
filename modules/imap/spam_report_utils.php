<?php
/**
 * Utility functions for spam reporting
 * @package modules
 * @subpackage imap
 */

if (!defined('DEBUG_MODE')) { die(); }

// The utility functions have been moved to lib/framework.php in the Hm_Functions class
// This file is kept for backward compatibility and future extensions 

/**
 * Auto-block sender after spam report
 * @param object $user_config User configuration object
 * @param object $site_config Site configuration object
 * @param string $imap_server_id IMAP server ID
 * @param array $message_data Message data containing sender information
 * @param string $spam_reason Reason for spam report
 * @return array Result of auto-block operation
 */
function auto_block_spam_sender($user_config, $site_config, $imap_server_id, $message_data, $spam_reason) {
    try {
        // Check if auto-blocking is enabled
        if (!is_auto_block_spam_enabled($user_config)) {
            delayed_debug_log('Auto-block spam sender: Disabled by user configuration');
            return array('success' => false, 'message' => 'Auto-blocking disabled');
        }

        // Get IMAP server configuration
        $imap_servers = $user_config->get('imap_servers', array());
        $imap_account = null;
        foreach ($imap_servers as $idx => $mailbox) {
            if ($idx == $imap_server_id) {
                $imap_account = $mailbox;
                break;
            }
        }

        if (!$imap_account) {
            delayed_debug_log('Auto-block spam sender: IMAP server not found', array('server_id' => $imap_server_id));
            return array('success' => false, 'error' => 'IMAP server not found');
        }

        // Extract sender email from message headers
        $sender_email = extract_sender_email_from_headers($message_data['headers']);
        if (!$sender_email) {
            delayed_debug_log('Auto-block spam sender: Could not extract sender email');
            return array('success' => false, 'error' => 'Could not extract sender email');
        }

        // Get auto-block configuration
        $action = get_auto_block_spam_action($user_config);
        $scope = get_auto_block_spam_scope($user_config);

        // Adjust sender email based on scope
        if ($scope == 'domain') {
            $sender_email = '*@' . get_domain($sender_email);
        }

        delayed_debug_log('Auto-block spam sender: Starting auto-block', array(
            'sender' => $sender_email,
            'action' => $action,
            'scope' => $scope,
            'reason' => $spam_reason
        ));

        // Initialize Sieve client
        $factory = get_sieve_client_factory($site_config);
        $client = $factory->init($user_config, $imap_account, in_array(mb_strtolower('nux'), $site_config->get_modules(true), true));
        
        if (!$client) {
            delayed_debug_log('Auto-block spam sender: Failed to initialize Sieve client');
            return array('success' => false, 'error' => 'Failed to initialize Sieve client');
        }

        $scripts = $client->listScripts();

        // Create blocked_senders script if it doesn't exist
        if (array_search('blocked_senders', $scripts, true) === false) {
            $client->putScript('blocked_senders', '');
        }

        // Get current blocked senders
        $blocked_senders = array();
        $blocked_list_actions = array();
        $current_script = $client->getScript('blocked_senders');

        if ($current_script != '') {
            $blocked_list = prepare_sieve_script($current_script);
            $blocked_list_actions = prepare_sieve_script($current_script, 2);
            
            if ($blocked_list) {
                foreach ($blocked_list as $blocked_sender) {
                    if ($blocked_sender != $sender_email) {
                        $blocked_senders[] = $blocked_sender;
                    }
                }
            }
        }

        // Add the new sender to blocked list
        $blocked_senders[] = $sender_email;
        $blocked_senders = array_unique($blocked_senders);

        // Create Sieve filter
        $filter = \PhpSieveManager\Filters\FilterFactory::create('blocked_senders');
        
        // Map action to Sieve action
        $sieve_action = map_auto_block_action_to_sieve($action, $user_config, $imap_server_id);
        
        foreach ($blocked_senders as $blocked_sender) {
            if ($blocked_sender == $sender_email) {
                $actions = block_filter(
                    $filter,
                    $user_config,
                    $sieve_action,
                    $imap_server_id,
                    $blocked_sender,
                    'Auto-blocked after spam report: ' . $spam_reason
                );
            } elseif (array_key_exists($blocked_sender, $blocked_list_actions)) {
                $reject_message = '';
                if ($blocked_list_actions[$blocked_sender]['action'] == 'reject_with_message') {
                    $reject_message = $blocked_list_actions[$blocked_sender]['reject_message'];
                }
                $actions = block_filter(
                    $filter,
                    $user_config,
                    $blocked_list_actions[$blocked_sender]['action'],
                    $imap_server_id,
                    $blocked_sender,
                    $reject_message
                );
            } else {
                $actions = block_filter(
                    $filter,
                    $user_config,
                    'default',
                    $imap_server_id,
                    $blocked_sender
                );
            }
            $blocked_list_actions[$blocked_sender] = $actions;
        }

        // Generate and save Sieve script
        $script_parsed = $filter->toScript();
        $main_script = generate_main_script($scripts);

        $header_obj = "# CYPHT CONFIG HEADER - DON'T REMOVE";
        $header_obj .= "\n# " . base64_encode(json_encode($blocked_senders));
        $header_obj .= "\n# " . base64_encode(json_encode($blocked_list_actions));
        $script_parsed = $header_obj . "\n\n" . $script_parsed;

        $client->putScript('blocked_senders', $script_parsed);
        save_main_script($client, $main_script, $scripts);
        $client->activateScript('main_script');
        $client->close();

        delayed_debug_log('Auto-block spam sender: Successfully blocked sender', array(
            'sender' => $sender_email,
            'action' => $action,
            'scope' => $scope
        ));

        return array(
            'success' => true,
            'sender' => $sender_email,
            'action' => $action,
            'scope' => $scope,
            'message' => sprintf('Sender %s automatically blocked (%s)', $sender_email, $action)
        );

    } catch (Exception $e) {
        delayed_debug_log('Auto-block spam sender: Exception occurred', array(
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ));
        return array(
            'success' => false,
            'error' => 'Auto-block failed: ' . $e->getMessage()
        );
    }
}

/**
 * Extract sender email from message headers
 * @param array $headers Message headers
 * @return string|false Sender email or false if not found
 */
function extract_sender_email_from_headers($headers) {
    if (!isset($headers['From'])) {
        return false;
    }

    $from_header = $headers['From'];
    
    // Try to extract email from format: "Name <email@domain.com>"
    if (preg_match('/<([^>]+)>/', $from_header, $matches)) {
        return $matches[1];
    }
    
    // Try to extract email from format: email@domain.com
    if (preg_match('/^([a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,})$/', $from_header, $matches)) {
        return $matches[1];
    }
    
    return false;
}

/**
 * Map auto-block action to Sieve action
 * @param string $action Auto-block action
 * @param object $user_config User configuration object
 * @param string $imap_server_id IMAP server ID
 * @return string Sieve action
 */
function map_auto_block_action_to_sieve($action, $user_config, $imap_server_id) {
    switch ($action) {
        case 'move_to_junk':
            return 'blocked'; // This will move to the configured blocked folder
        case 'discard':
            return 'discard';
        case 'reject':
            return 'reject_default';
        default:
            return 'default';
    }
}

/**
 * Get domain from email address
 * @param string $email Email address
 * @return string Domain
 */
function get_domain($email) {
    $parts = explode('@', $email);
    return isset($parts[1]) ? $parts[1] : '';
} 