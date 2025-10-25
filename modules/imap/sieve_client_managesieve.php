<?php

/**
 * ManageSieve Client Implementation
 * Generic implementation using PhpSieveManager library
 * Works with Dovecot, Cyrus, and other RFC 5804 compliant servers
 * 
 * @package modules
 * @subpackage imap
 */

if (!defined('DEBUG_MODE')) { die(); }

require_once APP_PATH . 'modules/imap/sieve_client_base.php';

// Load PhpSieveManager if available
if (file_exists(VENDOR_PATH . 'autoload.php')) {
    require_once VENDOR_PATH . 'autoload.php';
}

/**
 * ManageSieve Client
 * 
 * Generic Sieve client using the ManageSieve protocol (RFC 5804).
 * Compatible with Dovecot, Cyrus IMAP, and other compliant servers.
 */
class SieveClientManageSieve extends SieveClientBase {
    
    /**
     * PhpSieveManager client instance
     * @var \PhpSieveManager\ManageSieve\Client
     */
    protected $client = null;
    
    /**
     * Connect to Sieve server
     * 
     * @param string $host Sieve server hostname
     * @param int $port Sieve server port
     * @param string $username Authentication username
     * @param string $password Authentication password
     * @param array $options Connection options
     * @return bool True on success
     * @throws SieveConnectionException
     */
    public function connect($host, $port, $username, $password, $options = array()) {
        try {
            // Validate parameters
            $this->validateConnectionParams($host, $port, $username, $password);
            
            // Store connection details
            $this->host = $host;
            $this->port = $port;
            $this->username = $username;
            $this->options = array_merge(array(
                'use_tls' => true,
                'timeout' => 30
            ), $options);
            
            $this->debug('Connecting to Sieve server', array(
                'host' => $host,
                'port' => $port,
                'username' => $username
            ));
            
            // Check if PhpSieveManager is available
            if (!class_exists('\PhpSieveManager\ManageSieve\Client')) {
                throw new SieveConnectionException('PhpSieveManager library not found');
            }
            
            // Create ManageSieve client
            $this->client = new \PhpSieveManager\ManageSieve\Client($host, $port);
            
            // Enable TLS if requested
            if ($this->options['use_tls']) {
                if (!$this->client->connect($username, $password, 'PLAIN', '', 'tls')) {
                    throw new SieveConnectionException('Failed to connect with TLS');
                }
            } else {
                if (!$this->client->connect($username, $password)) {
                    throw new SieveConnectionException('Failed to connect');
                }
            }
            
            // Get server capabilities
            $this->capabilities = $this->client->capability();
            
            $this->connected = true;
            $this->clearError();
            
            $this->debug('Successfully connected to Sieve server');
            
            return true;
            
        } catch (Exception $e) {
            $this->connected = false;
            $error = 'Connection failed: ' . $e->getMessage();
            $this->setError($error);
            throw new SieveConnectionException($error, 0, $e);
        }
    }
    
    /**
     * Add or update a Sieve rule
     * 
     * @param string $ruleName Rule name
     * @param string $ruleContent Sieve script content
     * @param bool $activate Activate the script
     * @return bool True on success
     * @throws SieveRuleException
     */
    public function addRule($ruleName, $ruleContent, $activate = true) {
        try {
            if (!$this->connected) {
                throw new SieveRuleException('Not connected to server');
            }
            
            $this->validateRuleName($ruleName);
            $this->validateRuleContent($ruleContent);
            
            $this->debug('Adding rule', array('name' => $ruleName, 'activate' => $activate));
            
            // Upload the script
            if (!$this->client->putScript($ruleName, $ruleContent)) {
                throw new SieveRuleException('Failed to upload script');
            }
            
            // Activate if requested
            if ($activate) {
                if (!$this->client->setActive($ruleName)) {
                    throw new SieveRuleException('Script uploaded but activation failed');
                }
            }
            
            $this->clearError();
            $this->debug('Rule added successfully', array('name' => $ruleName));
            
            return true;
            
        } catch (Exception $e) {
            $error = 'Failed to add rule: ' . $e->getMessage();
            $this->setError($error);
            throw new SieveRuleException($error, 0, $e);
        }
    }
    
    /**
     * Remove a Sieve rule
     * 
     * @param string $ruleName Rule name
     * @return bool True on success
     * @throws SieveRuleException
     */
    public function removeRule($ruleName) {
        try {
            if (!$this->connected) {
                throw new SieveRuleException('Not connected to server');
            }
            
            $this->validateRuleName($ruleName);
            
            $this->debug('Removing rule', array('name' => $ruleName));
            
            if (!$this->client->deleteScript($ruleName)) {
                throw new SieveRuleException('Failed to delete script');
            }
            
            $this->clearError();
            $this->debug('Rule removed successfully', array('name' => $ruleName));
            
            return true;
            
        } catch (Exception $e) {
            $error = 'Failed to remove rule: ' . $e->getMessage();
            $this->setError($error);
            throw new SieveRuleException($error, 0, $e);
        }
    }
    
    /**
     * List all Sieve rules
     * 
     * @return array Array of rule information
     * @throws SieveException
     */
    public function listRules() {
        try {
            if (!$this->connected) {
                throw new SieveException('Not connected to server');
            }
            
            $this->debug('Listing rules');
            
            $scripts = $this->client->listScripts();
            
            if ($scripts === false) {
                throw new SieveException('Failed to list scripts');
            }
            
            $rules = array();
            foreach ($scripts as $scriptName) {
                $rules[] = array(
                    'name' => $scriptName,
                    'active' => ($scriptName === $this->client->getActive()),
                    'size' => 0 // Size not available from this API
                );
            }
            
            $this->clearError();
            $this->debug('Listed rules', array('count' => count($rules)));
            
            return $rules;
            
        } catch (Exception $e) {
            $error = 'Failed to list rules: ' . $e->getMessage();
            $this->setError($error);
            throw new SieveException($error, 0, $e);
        }
    }
    
    /**
     * Get rule content
     * 
     * @param string $ruleName Rule name
     * @return string|false Script content or false
     * @throws SieveException
     */
    public function getRule($ruleName) {
        try {
            if (!$this->connected) {
                throw new SieveException('Not connected to server');
            }
            
            $this->validateRuleName($ruleName);
            
            $this->debug('Getting rule', array('name' => $ruleName));
            
            $content = $this->client->getScript($ruleName);
            
            if ($content === false) {
                $this->setError('Script not found: ' . $ruleName);
                return false;
            }
            
            $this->clearError();
            return $content;
            
        } catch (Exception $e) {
            $error = 'Failed to get rule: ' . $e->getMessage();
            $this->setError($error);
            throw new SieveException($error, 0, $e);
        }
    }
    
    /**
     * Activate a rule
     * 
     * @param string $ruleName Rule name
     * @return bool True on success
     * @throws SieveException
     */
    public function activateRule($ruleName) {
        try {
            if (!$this->connected) {
                throw new SieveException('Not connected to server');
            }
            
            $this->validateRuleName($ruleName);
            
            $this->debug('Activating rule', array('name' => $ruleName));
            
            if (!$this->client->setActive($ruleName)) {
                throw new SieveException('Failed to activate script');
            }
            
            $this->clearError();
            $this->debug('Rule activated successfully', array('name' => $ruleName));
            
            return true;
            
        } catch (Exception $e) {
            $error = 'Failed to activate rule: ' . $e->getMessage();
            $this->setError($error);
            throw new SieveException($error, 0, $e);
        }
    }
    
    /**
     * Deactivate all rules
     * 
     * @return bool True on success
     * @throws SieveException
     */
    public function deactivateAll() {
        try {
            if (!$this->connected) {
                throw new SieveException('Not connected to server');
            }
            
            $this->debug('Deactivating all rules');
            
            if (!$this->client->setActive('')) {
                throw new SieveException('Failed to deactivate all scripts');
            }
            
            $this->clearError();
            $this->debug('All rules deactivated successfully');
            
            return true;
            
        } catch (Exception $e) {
            $error = 'Failed to deactivate all: ' . $e->getMessage();
            $this->setError($error);
            throw new SieveException($error, 0, $e);
        }
    }
    
    /**
     * Disconnect from server
     * 
     * @return bool True on success
     */
    public function disconnect() {
        try {
            if ($this->client && $this->connected) {
                $this->debug('Disconnecting from Sieve server');
                $this->client->close();
            }
            
            $this->connected = false;
            $this->client = null;
            $this->clearError();
            
            return true;
            
        } catch (Exception $e) {
            $this->setError('Disconnect error: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Destructor - ensure disconnection
     */
    public function __destruct() {
        $this->disconnect();
    }
}

