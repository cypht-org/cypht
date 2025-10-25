<?php

/**
 * Sieve Client Base Class
 * Abstract base implementation providing common functionality
 * 
 * @package modules
 * @subpackage imap
 */

if (!defined('DEBUG_MODE')) { die(); }

require_once APP_PATH . 'modules/imap/sieve_client_interface.php';

/**
 * Abstract Sieve Client Base Class
 * 
 * Provides common functionality for Sieve client implementations.
 * Provider-specific clients should extend this class.
 */
abstract class SieveClientBase implements SieveClientInterface {
    
    /**
     * Connection status
     * @var bool
     */
    protected $connected = false;
    
    /**
     * Server host
     * @var string
     */
    protected $host = '';
    
    /**
     * Server port
     * @var int
     */
    protected $port = 4190;
    
    /**
     * Username
     * @var string
     */
    protected $username = '';
    
    /**
     * Connection options
     * @var array
     */
    protected $options = array();
    
    /**
     * Last error message
     * @var string|null
     */
    protected $lastError = null;
    
    /**
     * Server capabilities
     * @var array
     */
    protected $capabilities = array();
    
    /**
     * Debug mode flag
     * @var bool
     */
    protected $debug = false;
    
    /**
     * Constructor
     * 
     * @param bool $debug Enable debug mode
     */
    public function __construct($debug = false) {
        $this->debug = $debug;
    }
    
    /**
     * Check if client is connected
     * 
     * @return bool
     */
    public function isConnected() {
        return $this->connected;
    }
    
    /**
     * Get last error message
     * 
     * @return string|null
     */
    public function getLastError() {
        return $this->lastError;
    }
    
    /**
     * Set last error message
     * 
     * @param string $error Error message
     * @return void
     */
    protected function setError($error) {
        $this->lastError = $error;
        if ($this->debug) {
            Hm_Debug::add('Sieve Client Error: ' . $error);
        }
    }
    
    /**
     * Clear last error
     * 
     * @return void
     */
    protected function clearError() {
        $this->lastError = null;
    }
    
    /**
     * Get server capabilities
     * 
     * @return array
     */
    public function getCapabilities() {
        return $this->capabilities;
    }
    
    /**
     * Test if server supports an extension
     * 
     * @param string $extension Extension name
     * @return bool
     */
    public function supportsExtension($extension) {
        return in_array(strtolower($extension), array_map('strtolower', $this->capabilities));
    }
    
    /**
     * Check if a rule exists
     * 
     * @param string $ruleName Rule name
     * @return bool
     */
    public function ruleExists($ruleName) {
        try {
            $rules = $this->listRules();
            foreach ($rules as $rule) {
                if ($rule['name'] === $ruleName) {
                    return true;
                }
            }
            return false;
        } catch (Exception $e) {
            $this->setError('Failed to check rule existence: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Log debug message
     * 
     * @param string $message Debug message
     * @param array $context Additional context
     * @return void
     */
    protected function debug($message, $context = array()) {
        if ($this->debug) {
            if (!empty($context)) {
                Hm_Debug::add('Sieve: ' . $message . ' - ' . json_encode($context));
            } else {
                Hm_Debug::add('Sieve: ' . $message);
            }
        }
    }
    
    /**
     * Validate connection parameters
     * 
     * @param string $host Host
     * @param int $port Port
     * @param string $username Username
     * @param string $password Password
     * @return bool
     * @throws SieveConnectionException
     */
    protected function validateConnectionParams($host, $port, $username, $password) {
        if (empty($host)) {
            throw new SieveConnectionException('Host cannot be empty');
        }
        if (empty($username)) {
            throw new SieveConnectionException('Username cannot be empty');
        }
        if (empty($password)) {
            throw new SieveConnectionException('Password cannot be empty');
        }
        if ($port < 1 || $port > 65535) {
            throw new SieveConnectionException('Invalid port number: ' . $port);
        }
        return true;
    }
    
    /**
     * Validate rule name
     * 
     * @param string $ruleName Rule name
     * @return bool
     * @throws SieveRuleException
     */
    protected function validateRuleName($ruleName) {
        if (empty($ruleName)) {
            throw new SieveRuleException('Rule name cannot be empty');
        }
        if (strlen($ruleName) > 255) {
            throw new SieveRuleException('Rule name too long (max 255 characters)');
        }
        // Basic validation - no control characters
        if (preg_match('/[\x00-\x1F\x7F]/', $ruleName)) {
            throw new SieveRuleException('Rule name contains invalid characters');
        }
        return true;
    }
    
    /**
     * Validate rule content
     * 
     * @param string $ruleContent Rule content
     * @return bool
     * @throws SieveRuleException
     */
    protected function validateRuleContent($ruleContent) {
        if (empty($ruleContent)) {
            throw new SieveRuleException('Rule content cannot be empty');
        }
        return true;
    }
    
    // Abstract methods that must be implemented by child classes
    abstract public function connect($host, $port, $username, $password, $options = array());
    abstract public function addRule($ruleName, $ruleContent, $activate = true);
    abstract public function removeRule($ruleName);
    abstract public function listRules();
    abstract public function getRule($ruleName);
    abstract public function activateRule($ruleName);
    abstract public function deactivateAll();
    abstract public function disconnect();
}

