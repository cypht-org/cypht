<?php

/**
 * Migadu Sieve Client Implementation
 * Migadu-specific Sieve client with optimizations for Migadu's infrastructure
 * 
 * @package modules
 * @subpackage imap
 */

if (!defined('DEBUG_MODE')) { die(); }

require_once APP_PATH . 'modules/imap/sieve_client_managesieve.php';

/**
 * Migadu Sieve Client
 * 
 * Sieve client optimized for Migadu's mail service.
 * Extends ManageSieve client with Migadu-specific defaults and behaviors.
 */
class SieveClientMigadu extends SieveClientManageSieve {
    
    /**
     * Migadu default port
     */
    const DEFAULT_PORT = 4190;
    
    /**
     * Connect to Migadu Sieve server
     * 
     * Applies Migadu-specific connection defaults and optimizations.
     * 
     * @param string $host Sieve server hostname (e.g., imap.migadu.com)
     * @param int $port Sieve server port (default: 4190)
     * @param string $username Full email address
     * @param string $password Account password
     * @param array $options Connection options
     * @return bool True on success
     * @throws SieveConnectionException
     */
    public function connect($host, $port, $username, $password, $options = array()) {
        // Migadu-specific defaults
        $migadu_defaults = array(
            'use_tls' => true,         // Migadu requires TLS
            'timeout' => 30,
            'auth_method' => 'PLAIN'   // Migadu uses PLAIN auth over TLS
        );
        
        $options = array_merge($migadu_defaults, $options);
        
        // Normalize Migadu hostname
        if (!empty($host) && strpos($host, ':') === false) {
            // If port not in hostname, use provided port or default
            $port = $port ?: self::DEFAULT_PORT;
        }
        
        $this->debug('Connecting to Migadu Sieve server', array(
            'host' => $host,
            'port' => $port,
            'username' => $username
        ));
        
        // Call parent connect with Migadu-specific options
        return parent::connect($host, $port, $username, $password, $options);
    }
    
    /**
     * Add or update a Sieve rule with Migadu optimizations
     * 
     * Migadu has some folder name conventions and limitations.
     * 
     * @param string $ruleName Rule name
     * @param string $ruleContent Sieve script content
     * @param bool $activate Activate the script
     * @return bool True on success
     * @throws SieveRuleException
     */
    public function addRule($ruleName, $ruleContent, $activate = true) {
        try {
            // Validate rule content for Migadu-specific issues
            $this->validateMigaduRuleContent($ruleContent);
            
            // Call parent addRule
            return parent::addRule($ruleName, $ruleContent, $activate);
            
        } catch (Exception $e) {
            throw $e;
        }
    }
    
    /**
     * Validate Sieve rule content for Migadu-specific requirements
     * 
     * @param string $ruleContent Rule content to validate
     * @return bool True if valid
     * @throws SieveRuleException
     */
    protected function validateMigaduRuleContent($ruleContent) {
        // Check for common Migadu folder names
        // Migadu typically uses: Junk, Trash, Sent, Drafts, Archive
        
        // Warn if using non-standard folder names
        $common_folders = array('Junk', 'Trash', 'Sent', 'Drafts', 'Archive', 'INBOX');
        
        // Extract fileinto commands
        if (preg_match_all('/fileinto\s+"([^"]+)"/', $ruleContent, $matches)) {
            foreach ($matches[1] as $folder) {
                if (!in_array($folder, $common_folders)) {
                    $this->debug('Warning: Non-standard folder name used', array(
                        'folder' => $folder,
                        'suggestion' => 'Migadu typically uses: ' . implode(', ', $common_folders)
                    ));
                }
            }
        }
        
        return true;
    }
    
    /**
     * Get Migadu-recommended folder names
     * 
     * @return array Array of standard folder names
     */
    public function getStandardFolders() {
        return array(
            'inbox' => 'INBOX',
            'junk' => 'Junk',
            'trash' => 'Trash',
            'sent' => 'Sent',
            'drafts' => 'Drafts',
            'archive' => 'Archive'
        );
    }
    
    /**
     * Create a spam filter rule optimized for Migadu
     * 
     * Helper method to generate Migadu-compatible spam filter rules.
     * 
     * @param string $senderEmail Email address to block
     * @param string $action Action to take ('fileinto', 'discard', 'reject')
     * @param string $folder Target folder for fileinto (default: 'Junk')
     * @param string $reason Reason for blocking (for reject action)
     * @return string Sieve script content
     */
    public function generateSpamFilter($senderEmail, $action = 'fileinto', $folder = 'Junk', $reason = '') {
        $script = "# Spam filter for: {$senderEmail}\n";
        $script .= "require [\"fileinto\", \"envelope\", \"reject\"];\n\n";
        
        // Determine if blocking sender or domain
        if (strpos($senderEmail, '*@') === 0) {
            // Domain block
            $domain = substr($senderEmail, 2);
            $script .= "# Block entire domain: {$domain}\n";
            $script .= "if address :domain :is \"from\" \"{$domain}\" {\n";
        } else {
            // Specific sender block
            $script .= "# Block specific sender: {$senderEmail}\n";
            $script .= "if address :is \"from\" \"{$senderEmail}\" {\n";
        }
        
        // Add action
        switch ($action) {
            case 'discard':
                $script .= "    discard;\n";
                $script .= "    stop;\n";
                break;
                
            case 'reject':
                $reject_reason = !empty($reason) ? $reason : 'Message rejected';
                $script .= "    reject text:\n";
                $script .= "{$reject_reason}\n";
                $script .= ".\n";
                $script .= "    ;\n";
                $script .= "    stop;\n";
                break;
                
            case 'fileinto':
            default:
                $script .= "    fileinto \"{$folder}\";\n";
                $script .= "    stop;\n";
                break;
        }
        
        $script .= "}\n";
        
        return $script;
    }
    
    /**
     * Test connection to Migadu
     * 
     * Performs a quick connectivity test without full authentication.
     * 
     * @param string $host Sieve server hostname
     * @param int $port Sieve server port
     * @return array Test results
     */
    public static function testConnection($host, $port = self::DEFAULT_PORT) {
        $result = array(
            'success' => false,
            'reachable' => false,
            'tls_available' => false,
            'error' => null
        );
        
        try {
            // Test if port is reachable
            $errno = 0;
            $errstr = '';
            $timeout = 10;
            
            $socket = @fsockopen($host, $port, $errno, $errstr, $timeout);
            
            if ($socket) {
                $result['reachable'] = true;
                
                // Read greeting
                $greeting = fgets($socket);
                
                if (strpos($greeting, 'OK') !== false || strpos($greeting, 'IMPLEMENTATION') !== false) {
                    $result['success'] = true;
                }
                
                // Check for STARTTLS capability
                fwrite($socket, "CAPABILITY\r\n");
                $cap_response = fgets($socket);
                
                if (strpos($cap_response, 'STARTTLS') !== false) {
                    $result['tls_available'] = true;
                }
                
                fclose($socket);
            } else {
                $result['error'] = "Cannot reach server: {$errstr} (errno: {$errno})";
            }
            
        } catch (Exception $e) {
            $result['error'] = 'Connection test failed: ' . $e->getMessage();
        }
        
        return $result;
    }
}

