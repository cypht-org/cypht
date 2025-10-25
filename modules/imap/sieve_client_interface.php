<?php

/**
 * Sieve Client Interface
 * Defines a standard interface for interacting with Sieve servers
 * Supports multiple providers: Migadu, Dovecot, Cyrus, etc.
 * 
 * @package modules
 * @subpackage imap
 */

if (!defined('DEBUG_MODE')) { die(); }

/**
 * Sieve Client Interface
 * 
 * This interface defines the contract for Sieve client implementations.
 * Different providers (Migadu, Dovecot, Cyrus, etc.) can implement this
 * interface to provide consistent Sieve filter management.
 */
interface SieveClientInterface {
    
    /**
     * Connect to the Sieve server
     * 
     * @param string $host Sieve server hostname (e.g., 'imap.migadu.com')
     * @param int $port Sieve server port (typically 4190)
     * @param string $username Authentication username
     * @param string $password Authentication password
     * @param array $options Additional connection options (TLS, timeout, etc.)
     * @return bool True on successful connection, false otherwise
     * @throws SieveConnectionException on connection failure
     */
    public function connect($host, $port, $username, $password, $options = array());
    
    /**
     * Check if client is currently connected
     * 
     * @return bool True if connected, false otherwise
     */
    public function isConnected();
    
    /**
     * Add or update a Sieve rule
     * 
     * @param string $ruleName Name/identifier for the rule
     * @param string $ruleContent Sieve script content for the rule
     * @param bool $activate Whether to activate the script immediately
     * @return bool True on success, false otherwise
     * @throws SieveRuleException on rule creation failure
     */
    public function addRule($ruleName, $ruleContent, $activate = true);
    
    /**
     * Remove a Sieve rule
     * 
     * @param string $ruleName Name of the rule to remove
     * @return bool True on success, false otherwise
     * @throws SieveRuleException on rule deletion failure
     */
    public function removeRule($ruleName);
    
    /**
     * List all Sieve rules/scripts
     * 
     * @return array Array of rule information, each containing:
     *               - 'name' (string): Rule name
     *               - 'active' (bool): Whether the rule is active
     *               - 'size' (int): Script size in bytes (optional)
     * @throws SieveException on list failure
     */
    public function listRules();
    
    /**
     * Get the content of a specific Sieve rule
     * 
     * @param string $ruleName Name of the rule to retrieve
     * @return string|false Script content or false if not found
     * @throws SieveException on retrieval failure
     */
    public function getRule($ruleName);
    
    /**
     * Activate a Sieve script
     * 
     * @param string $ruleName Name of the rule to activate
     * @return bool True on success, false otherwise
     * @throws SieveException on activation failure
     */
    public function activateRule($ruleName);
    
    /**
     * Deactivate all Sieve scripts
     * 
     * @return bool True on success, false otherwise
     * @throws SieveException on deactivation failure
     */
    public function deactivateAll();
    
    /**
     * Check if a rule exists
     * 
     * @param string $ruleName Name of the rule to check
     * @return bool True if rule exists, false otherwise
     */
    public function ruleExists($ruleName);
    
    /**
     * Disconnect from the Sieve server
     * 
     * @return bool True on successful disconnection
     */
    public function disconnect();
    
    /**
     * Get the last error message
     * 
     * @return string|null Last error message or null if no error
     */
    public function getLastError();
    
    /**
     * Get server capabilities
     * 
     * @return array List of server capabilities (e.g., supported Sieve extensions)
     */
    public function getCapabilities();
    
    /**
     * Test if the server supports a specific Sieve extension
     * 
     * @param string $extension Extension name (e.g., 'fileinto', 'vacation', 'regex')
     * @return bool True if supported, false otherwise
     */
    public function supportsExtension($extension);
}

/**
 * Sieve Connection Exception
 * Thrown when connection to Sieve server fails
 */
class SieveConnectionException extends Exception {
    public function __construct($message = "", $code = 0, ?Throwable $previous = null) {
        parent::__construct("Sieve Connection Error: " . $message, $code, $previous);
    }
}

/**
 * Sieve Rule Exception
 * Thrown when rule operations fail
 */
class SieveRuleException extends Exception {
    public function __construct($message = "", $code = 0, ?Throwable $previous = null) {
        parent::__construct("Sieve Rule Error: " . $message, $code, $previous);
    }
}

/**
 * Sieve Exception
 * General Sieve operation exception
 */
class SieveException extends Exception {
    public function __construct($message = "", $code = 0, ?Throwable $previous = null) {
        parent::__construct("Sieve Error: " . $message, $code, $previous);
    }
}

