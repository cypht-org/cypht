<?php

/**
 * Sieve Client Factory
 * Creates appropriate Sieve client based on provider configuration
 * 
 * @package modules
 * @subpackage imap
 */

if (!defined('DEBUG_MODE')) { die(); }

require_once APP_PATH . 'modules/imap/sieve_client_interface.php';
require_once APP_PATH . 'modules/imap/sieve_client_base.php';

/**
 * Sieve Client Factory
 * 
 * Factory class to create the appropriate Sieve client implementation
 * based on the provider type or auto-detection.
 */
class SieveClientFactory {
    
    /**
     * Supported providers
     */
    const PROVIDER_MIGADU = 'migadu';
    const PROVIDER_DOVECOT = 'dovecot';
    const PROVIDER_CYRUS = 'cyrus';
    const PROVIDER_GENERIC = 'generic';
    const PROVIDER_AUTO = 'auto';
    
    /**
     * Create a Sieve client instance
     * 
     * @param string $provider Provider type (migadu, dovecot, cyrus, generic, auto)
     * @param bool $debug Enable debug mode
     * @return SieveClientInterface Sieve client instance
     * @throws Exception if provider is not supported
     */
    public static function create($provider = self::PROVIDER_AUTO, $debug = false) {
        $provider = strtolower($provider);
        
        switch ($provider) {
            case self::PROVIDER_MIGADU:
                return self::createMigaduClient($debug);
                
            case self::PROVIDER_DOVECOT:
                return self::createDovecotClient($debug);
                
            case self::PROVIDER_CYRUS:
                return self::createCyrusClient($debug);
                
            case self::PROVIDER_GENERIC:
            case self::PROVIDER_AUTO:
                return self::createGenericClient($debug);
                
            default:
                throw new Exception('Unsupported Sieve provider: ' . $provider);
        }
    }
    
    /**
     * Create client from IMAP server configuration
     * 
     * @param array $imap_config IMAP server configuration
     * @param bool $debug Enable debug mode
     * @return SieveClientInterface|null Sieve client instance or null if Sieve not configured
     */
    public static function createFromImapConfig($imap_config, $debug = false) {
        // Check if Sieve is configured
        if (empty($imap_config['sieve_config_host'])) {
            return null;
        }
        
        // Detect provider from host or configuration
        $provider = self::detectProvider($imap_config);
        
        return self::create($provider, $debug);
    }
    
    /**
     * Detect provider from IMAP configuration
     * 
     * @param array $imap_config IMAP server configuration
     * @return string Provider type
     */
    protected static function detectProvider($imap_config) {
        $host = isset($imap_config['sieve_config_host']) ? $imap_config['sieve_config_host'] : '';
        
        // Check for explicit provider setting
        if (isset($imap_config['sieve_provider'])) {
            return $imap_config['sieve_provider'];
        }
        
        // Auto-detect based on hostname
        if (strpos($host, 'migadu.com') !== false) {
            return self::PROVIDER_MIGADU;
        }
        
        // Default to generic ManageSieve
        return self::PROVIDER_GENERIC;
    }
    
    /**
     * Create Migadu-specific client
     * 
     * @param bool $debug Enable debug mode
     * @return SieveClientInterface
     */
    protected static function createMigaduClient($debug) {
        require_once APP_PATH . 'modules/imap/sieve_client_migadu.php';
        return new SieveClientMigadu($debug);
    }
    
    /**
     * Create Dovecot-specific client
     * 
     * @param bool $debug Enable debug mode
     * @return SieveClientInterface
     */
    protected static function createDovecotClient($debug) {
        require_once APP_PATH . 'modules/imap/sieve_client_managesieve.php';
        return new SieveClientManageSieve($debug);
    }
    
    /**
     * Create Cyrus-specific client
     * 
     * @param bool $debug Enable debug mode
     * @return SieveClientInterface
     */
    protected static function createCyrusClient($debug) {
        require_once APP_PATH . 'modules/imap/sieve_client_managesieve.php';
        return new SieveClientManageSieve($debug);
    }
    
    /**
     * Create generic ManageSieve client
     * 
     * @param bool $debug Enable debug mode
     * @return SieveClientInterface
     */
    protected static function createGenericClient($debug) {
        require_once APP_PATH . 'modules/imap/sieve_client_managesieve.php';
        return new SieveClientManageSieve($debug);
    }
    
    /**
     * Get list of supported providers
     * 
     * @return array Array of provider identifiers
     */
    public static function getSupportedProviders() {
        return array(
            self::PROVIDER_MIGADU,
            self::PROVIDER_DOVECOT,
            self::PROVIDER_CYRUS,
            self::PROVIDER_GENERIC,
            self::PROVIDER_AUTO
        );
    }
    
    /**
     * Check if a provider is supported
     * 
     * @param string $provider Provider identifier
     * @return bool True if supported
     */
    public static function isProviderSupported($provider) {
        return in_array(strtolower($provider), self::getSupportedProviders());
    }
}

