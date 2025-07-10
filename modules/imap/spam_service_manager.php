<?php
/**
 * Spam Service Manager
 * @package modules
 * @subpackage imap
 */

if (!defined('DEBUG_MODE')) { die(); }

/**
 * Spam Service Manager Class
 * Handles CRUD operations for external spam reporting services
 */
class Hm_Spam_Service_Manager {
    private $user_config;
    private $services_key = 'spam_reporting_services';

    public function __construct($user_config) {
        $this->user_config = $user_config;
    }

    /**
     * Get all configured services
     * @return array Array of service configurations
     */
    public function getServices() {
        $services = $this->user_config->get($this->services_key, array());
        
        // If no services configured, return default services
        if (empty($services)) {
            return $this->getDefaultServices();
        }
        
        return $services;
    }

    /**
     * Get a specific service by ID
     * @param string $service_id Service identifier
     * @return array|false Service configuration or false if not found
     */
    public function getService($service_id) {
        $services = $this->getServices();
        return isset($services[$service_id]) ? $services[$service_id] : false;
    }

    /**
     * Add a new service
     * @param array $service_config Service configuration
     * @return string|false Service ID on success, false on failure
     */
    public function addService($service_config) {
        if (!$this->validateServiceConfig($service_config)) {
            return false;
        }

        $services = $this->getServices();
        $service_id = $this->generateServiceId($service_config['name']);
        
        // Ensure unique ID
        $counter = 1;
        $original_id = $service_id;
        while (isset($services[$service_id])) {
            $service_id = $original_id . '_' . $counter;
            $counter++;
        }

        $services[$service_id] = $service_config;
        $this->user_config->set($this->services_key, $services);
        
        return $service_id;
    }

    /**
     * Update an existing service
     * @param string $service_id Service identifier
     * @param array $service_config Updated service configuration
     * @return boolean True on success, false on failure
     */
    public function updateService($service_id, $service_config) {
        if (!$this->validateServiceConfig($service_config)) {
            return false;
        }

        $services = $this->getServices();
        if (!isset($services[$service_id])) {
            return false;
        }

        $services[$service_id] = $service_config;
        $this->user_config->set($this->services_key, $services);
        
        return true;
    }

    /**
     * Delete a service
     * @param string $service_id Service identifier
     * @return boolean True on success, false on failure
     */
    public function deleteService($service_id) {
        $services = $this->getServices();
        if (!isset($services[$service_id])) {
            return false;
        }

        unset($services[$service_id]);
        $this->user_config->set($this->services_key, $services);
        
        return true;
    }

    /**
     * Enable/disable a service
     * @param string $service_id Service identifier
     * @param boolean $enabled Enable/disable flag
     * @return boolean True on success, false on failure
     */
    public function setServiceEnabled($service_id, $enabled) {
        $services = $this->getServices();
        if (!isset($services[$service_id])) {
            return false;
        }

        $services[$service_id]['enabled'] = (bool)$enabled;
        $this->user_config->set($this->services_key, $services);
        
        return true;
    }

    /**
     * Get enabled services only
     * @return array Array of enabled service configurations
     */
    public function getEnabledServices() {
        $services = $this->getServices();
        return array_filter($services, function($service) {
            return isset($service['enabled']) && $service['enabled'];
        });
    }

    /**
     * Get service types and their configurations
     * @return array Service type definitions
     */
    public function getServiceTypes() {
        return array(
            'email' => array(
                'name' => 'Email Service',
                'description' => 'Send spam reports via email',
                'fields' => array(
                    'endpoint' => array('type' => 'email', 'label' => 'Email Address', 'required' => true)
                )
            ),
            'api' => array(
                'name' => 'API Service',
                'description' => 'Send spam reports via REST API',
                'fields' => array(
                    'endpoint' => array('type' => 'url', 'label' => 'API Endpoint', 'required' => true),
                    'method' => array('type' => 'select', 'label' => 'HTTP Method', 'required' => true, 'options' => array('POST', 'GET', 'PUT')),
                    'auth_type' => array('type' => 'select', 'label' => 'Authentication', 'required' => false, 'options' => array('none', 'header', 'bearer', 'basic')),
                    'auth_header' => array('type' => 'text', 'label' => 'Auth Header Name', 'required' => false),
                    'auth_value' => array('type' => 'password', 'label' => 'Auth Value', 'required' => false),
                    'payload_template' => array('type' => 'json', 'label' => 'Payload Template', 'required' => true)
                )
            ),
            'custom' => array(
                'name' => 'Custom Service',
                'description' => 'Custom integration with custom fields',
                'fields' => array(
                    'custom_fields' => array('type' => 'json', 'label' => 'Custom Configuration', 'required' => true)
                )
            )
        );
    }

    /**
     * Get available template variables
     * @return array Template variables and their descriptions
     */
    public function getTemplateVariables() {
        return array(
            '{{ ip }}' => 'Sender IP address',
            '{{ email }}' => 'Sender email address',
            '{{ domain }}' => 'Sender domain',
            '{{ subject }}' => 'Message subject',
            '{{ reason }}' => 'Spam report reason',
            '{{ message_id }}' => 'Message ID',
            '{{ date }}' => 'Report date (ISO format)',
            '{{ timestamp }}' => 'Unix timestamp',
            '{{ headers }}' => 'Message headers (JSON)',
            '{{ body }}' => 'Message body content'
        );
    }

    /**
     * Validate service configuration
     * @param array $config Service configuration
     * @return boolean True if valid, false otherwise
     */
    private function validateServiceConfig($config) {
        $required_fields = array('name', 'type', 'enabled');
        
        foreach ($required_fields as $field) {
            if (!isset($config[$field])) {
                return false;
            }
        }

        if (empty($config['name']) || !in_array($config['type'], array('email', 'api', 'custom'))) {
            return false;
        }

        // Type-specific validation
        switch ($config['type']) {
            case 'email':
                if (empty($config['endpoint']) || !filter_var($config['endpoint'], FILTER_VALIDATE_EMAIL)) {
                    return false;
                }
                break;
            case 'api':
                if (empty($config['endpoint']) || !filter_var($config['endpoint'], FILTER_VALIDATE_URL)) {
                    return false;
                }
                if (empty($config['method']) || !in_array($config['method'], array('GET', 'POST', 'PUT'))) {
                    return false;
                }
                break;
        }

        return true;
    }

    /**
     * Generate service ID from name
     * @param string $name Service name
     * @return string Service ID
     */
    private function generateServiceId($name) {
        return strtolower(preg_replace('/[^a-zA-Z0-9]/', '_', $name));
    }

    /**
     * Get default services configuration
     * @return array Default services
     */
    private function getDefaultServices() {
        return array(
            'spamcop' => array(
                'name' => 'SpamCop',
                'type' => 'email',
                'enabled' => true,
                'endpoint' => 'submit.u4GqXFse5hLoqP34@spam.spamcop.net'
            ),
            'abuseipdb' => array(
                'name' => 'AbuseIPDB',
                'type' => 'api',
                'enabled' => false,
                'endpoint' => 'https://api.abuseipdb.com/api/v2/report',
                'method' => 'POST',
                'auth_type' => 'header',
                'auth_header' => 'Key',
                'auth_value' => '',
                'payload_template' => '{"ip": "{{ ip }}", "categories": [3], "comment": "{{ reason }}"}',
                'response_code' => 200,
                'timeout' => 30
            ),
            'stopforumspam' => array(
                'name' => 'StopForumSpam',
                'type' => 'api',
                'enabled' => false,
                'endpoint' => 'https://www.stopforumspam.com/add',
                'method' => 'POST',
                'auth_type' => 'header',
                'auth_header' => 'api_key',
                'auth_value' => '',
                'payload_template' => '{"email": "{{ email }}", "ip": "{{ ip }}", "evidence": "{{ reason }}"}',
                'response_code' => 200,
                'timeout' => 30
            ),
            'cleantalk' => array(
                'name' => 'CleanTalk',
                'type' => 'api',
                'enabled' => false,
                'endpoint' => 'https://moderate.cleantalk.org/api2.0',
                'method' => 'POST',
                'auth_type' => 'header',
                'auth_header' => 'auth_key',
                'auth_value' => '',
                'payload_template' => '{"auth_key": "{{ auth_value }}", "method_name": "spam_check", "message": "{{ body }}", "sender_email": "{{ email }}", "sender_ip": "{{ ip }}"}',
                'response_code' => 200,
                'timeout' => 30
            )
        );
    }
} 