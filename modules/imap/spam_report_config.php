<?php
/**
 * Spam reporting configuration
 * @package modules
 * @subpackage imap
 */

if (!defined('DEBUG_MODE')) { die(); }

/**
 * Get spam reporting service configuration
 * 
 * Each service should have:
 * - name: Service name
 * - type: Service type (email, api, lookup)
 * - enabled: Whether the service is enabled
 * - config: Service-specific configuration
 */
function get_spam_report_services() {
    return array(
        'spamcop' => array(
            'name' => 'SpamCop',
            'type' => 'email',
            'enabled' => true,
            'config' => array(
                'submit_address' => 'submit.u4GqXFse5hLoqP34@spam.spamcop.net',
                'require_headers' => true,
                'require_body' => true
            )
        ),
        'abuseipdb' => array(
            'name' => 'AbuseIPDB',
            'type' => 'api',
            'enabled' => false,
            'config' => array(
                'api_url' => 'https://api.abuseipdb.com/api/v2/report',
                'api_key' => 'f28126304fbf1d99174941cb104f0181caee70097db8f2a2b2fc5955b4c589fb2fd75ad093aa92c9',  // Set your API key here
                'categories' => array(
                    'spam' => 3,  // Category ID for spam
                    'phishing' => 4,
                    'malware' => 5
                )
            )
        ),
        'stopforumspam' => array(
            'name' => 'StopForumSpam',
            'type' => 'api',
            'enabled' => false,
            'config' => array(
                'api_url' => 'https://www.stopforumspam.com/add',
                'api_key' => '54hnax62ubiwe1',  // Set your API key here
                'evidence' => true  // Whether to include message content as evidence
            )
        ),
        'cleantalk' => array(
            'name' => 'CleanTalk',
            'type' => 'api',
            'enabled' => false,
            'config' => array(
                'api_url' => 'https://moderate.cleantalk.org/api2.0',
                'api_key' => '8e6ytu7u6yzubah',  // Set your API key here
                'check_type' => 'spam'
            )
        )
    );
}

/**
 * Get auto-block configuration for spam reporting
 * @return array Auto-block configuration
 */
function get_auto_block_spam_config() {
    return array(
        'enabled' => true,  // Whether auto-blocking is enabled
        'action' => 'move_to_junk',  // Default action: move_to_junk, discard, reject
        'scope' => 'sender',  // Default scope: sender, domain
        'junk_folder' => 'Junk'  // Default junk folder name
    );
}

/**
 * Helper function to get enabled spam reporting services
 * @return array List of enabled services
 */
function get_enabled_spam_services() {
    $spam_report_services = get_spam_report_services();
    
    // Ensure $spam_report_services is initialized
    if (!isset($spam_report_services) || !is_array($spam_report_services)) {
        delayed_debug_log('Warning: $spam_report_services not properly initialized');
        return array();
    }
    
    return array_filter($spam_report_services, function($service) {
        return isset($service['enabled']) && $service['enabled'];
    });
}

/**
 * Helper function to get service configuration
 * @param string $service_name Service identifier
 * @return array|false Service configuration or false if not found
 */
function get_spam_service_config($service_name) {
    $spam_report_services = get_spam_report_services();
    return isset($spam_report_services[$service_name]) ? $spam_report_services[$service_name] : false;
}

/**
 * Helper function to check if a service is enabled
 * @param string $service_name Service identifier
 * @return boolean True if service is enabled
 */
function is_spam_service_enabled($service_name) {
    $config = get_spam_service_config($service_name);
    return $config && $config['enabled'];
}

/**
 * Helper function to check if auto-blocking is enabled
 * @param object $user_config User configuration object
 * @return boolean True if auto-blocking is enabled
 */
function is_auto_block_spam_enabled($user_config) {
    // return $user_config->get('auto_block_spam_scope', 'sender');
    //return $user_config->get('auto_block_spam_sender', true);
    // HARDCODED FOR TESTING - TODO: Revert to user_config after testing
    return true;
    // Original: return $user_config->get('auto_block_spam_sender', true);
}

/**
 * Helper function to get auto-block action
 * @param object $user_config User configuration object
 * @return string Auto-block action
 */
function get_auto_block_spam_action($user_config) {
    //return $user_config->get('auto_block_spam_action', 'move_to_junk');
    // HARDCODED FOR TESTING - TODO: Revert to user_config after testing
    return 'move_to_junk';
    // Original: return $user_config->get('auto_block_spam_action', 'move_to_junk');
}

/**
 * Helper function to get auto-block scope
 * @param object $user_config User configuration object
 * @return string Auto-block scope
 */
function get_auto_block_spam_scope($user_config) {
    // HARDCODED FOR TESTING - TODO: Revert to user_config after testing
    return 'sender';
    // Original: return $user_config->get('auto_block_spam_scope', 'sender');
} 