<?php

/**
 * Sieve Provider Adapter
 * Provides provider-aware helpers for Sieve script generation.
 *
 * @package framework
 * @subpackage spam_filtering
 */

if (!defined('DEBUG_MODE')) { die(); }

/**
 * Sieve Provider Adapter
 */
class SieveProviderAdapter {

    /**
     * Adapter context
     * @var array
     */
    protected $context = array();
    protected $overrides = array();

    /**
     * Supported provider specific adapter classes
     * @var array
     */
    protected static $provider_map = array(
        'kolabnow' => SieveProviderKolabAdapter::class,
        'kolabnow.com' => SieveProviderKolabAdapter::class,
        'kolab' => SieveProviderKolabAdapter::class,
        'migadu' => SieveProviderMigaduAdapter::class,
        'migadu.com' => SieveProviderMigaduAdapter::class,
        'gmail' => SieveProviderGmailAdapter::class,
        'gmail.com' => SieveProviderGmailAdapter::class,
        'fastmail' => SieveProviderFastmailAdapter::class,
        'fastmail.com' => SieveProviderFastmailAdapter::class,
        'gandi' => SieveProviderGandiAdapter::class,
        'gmx' => SieveProviderGmxAdapter::class,
        'mail.com' => SieveProviderMailComAdapter::class,
        'mailbox.org' => SieveProviderMailboxAdapter::class,
        'office365' => SieveProviderOffice365Adapter::class,
        'outlook.com' => SieveProviderOffice365Adapter::class,
        'hotmail' => SieveProviderOffice365Adapter::class,
        'live.com' => SieveProviderOffice365Adapter::class,
        'postale' => SieveProviderPostaleAdapter::class,
        'postale.io' => SieveProviderPostaleAdapter::class,
        'yahoo' => SieveProviderYahooAdapter::class,
        'yahoo.com' => SieveProviderYahooAdapter::class,
        'yandex' => SieveProviderYandexAdapter::class,
        'zoho' => SieveProviderZohoAdapter::class,
        'aol' => SieveProviderAolAdapter::class,
        'aol.com' => SieveProviderAolAdapter::class,
        'all-inkl' => SieveProviderAllInklAdapter::class,
        'kasserver' => SieveProviderAllInklAdapter::class,
        'icloud' => SieveProviderIcloudAdapter::class,
        'mail.me.com' => SieveProviderIcloudAdapter::class,
        'inbox.com' => SieveProviderInboxAdapter::class
    );

    /**
     * Factory method
     * @param array $context
     * @return SieveProviderAdapter
     */
    public static function create(array $context) {
        $tokens = array();
        if (!empty($context['provider'])) {
            $tokens[] = strtolower($context['provider']);
        }
        if (!empty($context['host'])) {
            $tokens[] = strtolower($context['host']);
        }
        if (!empty($context['server_name'])) {
            $tokens[] = strtolower($context['server_name']);
        }
        if (!empty($context['server_config']['server'])) {
            $tokens[] = strtolower($context['server_config']['server']);
        }

        foreach ($tokens as $token) {
            foreach (self::$provider_map as $key => $class) {
                if (strpos($token, $key) !== false && class_exists($class)) {
                    return new $class($context);
                }
            }
        }

        return new self($context);
    }

    /**
     * Constructor
     * @param array $context
     */
    public function __construct(array $context) {
        $this->context = $context;
        $this->overrides = isset($context['overrides']) && is_array($context['overrides'])
            ? $context['overrides']
            : array();
    }

    /**
     * Resolve target junk folder
     * @param string|null $custom_folder
     * @return string
     */
    public function resolveJunkFolder($custom_folder = null) {
        if (!empty($custom_folder)) {
            return $custom_folder;
        }

        if (!empty($this->overrides['junk_folder'])) {
            return $this->overrides['junk_folder'];
        }

        $defaults = $this->context['defaults'] ?? array();
        if (!empty($defaults['junk_folder'])) {
            return $defaults['junk_folder'];
        }

        $special = $this->context['special_folders'] ?? array();
        if (!empty($special['junk'])) {
            return $special['junk'];
        }
        if (!empty($special['spam'])) {
            return $special['spam'];
        }

        return 'Junk';
    }

    /**
     * Get required extensions filtered by capabilities
     * @param array $extra
     * @return array
     */
    public function getRequiredExtensions(array $extra = array()) {
        $required = array_merge(
            array('fileinto'),
            (array)($this->context['defaults']['require'] ?? array()),
            (array)($this->overrides['require'] ?? array()),
            $extra
        );

        $extensions = $this->getSupportedExtensions();

        if (empty($extensions)) {
            return array_values(array_unique(array_filter($required)));
        }

        $filtered = array();
        foreach ($required as $ext) {
            $ext_lc = strtolower($ext);
            if (in_array($ext_lc, $extensions, true)) {
                $filtered[] = $ext_lc;
            }
        }

        return array_values(array_unique($filtered));
    }

    /**
     * Determine if provider supports replacing scripts
     * @return bool
     */
    public function supportsReplaceScript() {
        if (isset($this->overrides['supports_replace'])) {
            return (bool)$this->overrides['supports_replace'];
        }

        return !empty($this->context['defaults']['supports_replace']);
    }

    /**
     * Sanitize folder name for Sieve
     * @param string $folder
     * @return string
     */
    public function sanitizeFolderName($folder) {
        if ($folder === '') {
            return '"Junk"';
        }

        $needs_quotes = preg_match('/[^A-Za-z0-9._-]/', $folder);
        $escaped = str_replace(array('\\', '"'), array('\\\\', '\\"'), $folder);

        if ($needs_quotes) {
            return '"' . $escaped . '"';
        }

        return '"' . $escaped . '"';
    }

    /**
     * Get supported extensions from capabilities
     * @return array
     */
    protected function getSupportedExtensions() {
        $capabilities = $this->context['capabilities'] ?? array();

        if (isset($capabilities['extensions']) && is_array($capabilities['extensions'])) {
            return array_map('strtolower', $capabilities['extensions']);
        }

        if (isset($capabilities['SIEVE'])) {
            $extensions = preg_split('/\s+/', strtolower($capabilities['SIEVE']));
            return array_filter($extensions, 'strlen');
        }

        if (isset($capabilities['sieve'])) {
            $extensions = preg_split('/\s+/', strtolower($capabilities['sieve']));
            return array_filter($extensions, 'strlen');
        }

        return array();
    }
}

/**
 * Kolab specific adapter
 */
class SieveProviderKolabAdapter extends SieveProviderAdapter {
    public function supportsReplaceScript() {
        return true;
    }
}

/**
 * Migadu specific adapter
 */
class SieveProviderMigaduAdapter extends SieveProviderAdapter {
}

/**
 * Gmail specific adapter
 */
class SieveProviderGmailAdapter extends SieveProviderAdapter {
    public function resolveJunkFolder($custom_folder = null) {
        if (!empty($custom_folder)) {
            return $custom_folder;
        }
        return '[Gmail]/Spam';
    }
}

class SieveProviderFastmailAdapter extends SieveProviderAdapter {}
class SieveProviderGandiAdapter extends SieveProviderAdapter {}
class SieveProviderGmxAdapter extends SieveProviderAdapter {}
class SieveProviderMailComAdapter extends SieveProviderAdapter {}
class SieveProviderMailboxAdapter extends SieveProviderAdapter {}

class SieveProviderOffice365Adapter extends SieveProviderAdapter {
    public function resolveJunkFolder($custom_folder = null) {
        if (!empty($custom_folder)) {
            return $custom_folder;
        }
        return 'Junk Email';
    }
}

class SieveProviderPostaleAdapter extends SieveProviderAdapter {}
class SieveProviderYahooAdapter extends SieveProviderAdapter {}
class SieveProviderYandexAdapter extends SieveProviderAdapter {}
class SieveProviderZohoAdapter extends SieveProviderAdapter {}
class SieveProviderAolAdapter extends SieveProviderAdapter {}
class SieveProviderAllInklAdapter extends SieveProviderAdapter {}
class SieveProviderIcloudAdapter extends SieveProviderAdapter {
    public function resolveJunkFolder($custom_folder = null) {
        if (!empty($custom_folder)) {
            return $custom_folder;
        }
        return 'Junk';
    }
}
class SieveProviderInboxAdapter extends SieveProviderAdapter {}

