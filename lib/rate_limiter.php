<?php

/**
 * Rate limiting functionality
 * @package framework
 * @subpackage rate_limiter
 */

if (!defined('DEBUG_MODE')) { die(); }

/**
 * Rate limiting configuration
 */
class Hm_Rate_Limiter_Config {
    public $endpoints = [];
    public $default_limits = [];
    
    public function __construct() {
        // Default rate limits (requests per time window)
        $this->default_limits = [
            'window_size' => 3600, // 1 hour in seconds
            'max_requests' => 100,  // 100 requests per hour
            'burst_limit' => 10,    // 10 requests per burst window
            'burst_window' => 60    // 1 minute burst window
        ];
        
        // Endpoint-specific configurations
        $this->endpoints = [
            'ajax_imap_report_spam' => [
                'window_size' => 3600,    // 1 hour
                'max_requests' => 50,     // 50 spam reports per hour
                'burst_limit' => 5,       // 5 reports per minute
                'burst_window' => 60,     // 1 minute
                'penalty_duration' => 7200 // 2 hour penalty for violations
            ],
            'ajax_imap_message_action' => [
                'window_size' => 3600,
                'max_requests' => 200,
                'burst_limit' => 20,
                'burst_window' => 60
            ],
            'ajax_imap_search' => [
                'window_size' => 3600,
                'max_requests' => 300,
                'burst_limit' => 30,
                'burst_window' => 60
            ]
        ];
    }
    
    /**
     * Get rate limit configuration for an endpoint
     * @param string $endpoint Endpoint name
     * @return array Rate limit configuration
     */
    public function get_endpoint_config($endpoint) {
        if (isset($this->endpoints[$endpoint])) {
            return array_merge($this->default_limits, $this->endpoints[$endpoint]);
        }
        return $this->default_limits;
    }
}

/**
 * Rate limiting implementation
 */
class Hm_Rate_Limiter {
    private $cache;
    private $config;
    private $user_id;
    private $ip_address;
    
    public function __construct($cache, $user_id = null, $ip_address = null) {
        $this->cache = $cache;
        $this->config = new Hm_Rate_Limiter_Config();
        $this->user_id = $user_id;
        $this->ip_address = $ip_address;
    }
    
    /**
     * Check if a request is allowed based on rate limits
     * @param string $endpoint Endpoint name
     * @param string $identifier User ID or IP address
     * @return array Result with allowed status and remaining requests
     */
    public function check_rate_limit($endpoint, $identifier = null) {
        if (!$identifier) {
            $identifier = $this->user_id ?: $this->ip_address;
        }
        
        if (!$identifier) {
            return ['allowed' => true, 'remaining' => 0, 'reset_time' => 0];
        }
        
        $config = $this->config->get_endpoint_config($endpoint);
        $now = time();
        
        // Check if user is in penalty period
        $penalty_key = "rate_limit_penalty:{$endpoint}:{$identifier}";
        $penalty_until = $this->cache->get($penalty_key);
        
        if ($penalty_until && $now < $penalty_until) {
            $remaining_penalty = $penalty_until - $now;
            return [
                'allowed' => false,
                'remaining' => 0,
                'reset_time' => $penalty_until,
                'penalty' => true,
                'penalty_remaining' => $remaining_penalty,
                'message' => "Rate limit exceeded. Please wait {$remaining_penalty} seconds."
            ];
        }
        
        // Check burst limit
        $burst_key = "rate_limit_burst:{$endpoint}:{$identifier}";
        $burst_requests = $this->cache->get($burst_key, []);
        
        // Clean old burst entries
        $burst_requests = array_filter($burst_requests, function($timestamp) use ($now, $config) {
            return ($now - $timestamp) < $config['burst_window'];
        });
        
        if (count($burst_requests) >= $config['burst_limit']) {
            // Apply penalty for burst violation
            $penalty_until = $now + ($config['penalty_duration'] ?? 3600);
            $this->cache->set($penalty_key, $penalty_until, $config['penalty_duration'] ?? 3600);
            
            return [
                'allowed' => false,
                'remaining' => 0,
                'reset_time' => $penalty_until,
                'penalty' => true,
                'message' => "Burst rate limit exceeded. Please wait " . ($config['penalty_duration'] ?? 3600) . " seconds."
            ];
        }
        
        // Check main rate limit
        $main_key = "rate_limit_main:{$endpoint}:{$identifier}";
        $main_requests = $this->cache->get($main_key, []);
        
        // Clean old main entries
        $main_requests = array_filter($main_requests, function($timestamp) use ($now, $config) {
            return ($now - $timestamp) < $config['window_size'];
        });
        
        $remaining = $config['max_requests'] - count($main_requests);
        
        if ($remaining <= 0) {
            return [
                'allowed' => false,
                'remaining' => 0,
                'reset_time' => $now + $config['window_size'],
                'message' => "Rate limit exceeded. Please wait " . $config['window_size'] . " seconds."
            ];
        }
        
        return [
            'allowed' => true,
            'remaining' => $remaining,
            'reset_time' => $now + $config['window_size']
        ];
    }
    
    /**
     * Record a request for rate limiting
     * @param string $endpoint Endpoint name
     * @param string $identifier User ID or IP address
     * @return bool Success status
     */
    public function record_request($endpoint, $identifier = null) {
        if (!$identifier) {
            $identifier = $this->user_id ?: $this->ip_address;
        }
        
        if (!$identifier) {
            return false;
        }
        
        $config = $this->config->get_endpoint_config($endpoint);
        $now = time();
        
        // Record burst request
        $burst_key = "rate_limit_burst:{$endpoint}:{$identifier}";
        $burst_requests = $this->cache->get($burst_key, []);
        $burst_requests[] = $now;
        $this->cache->set($burst_key, $burst_requests, $config['burst_window']);
        
        // Record main request
        $main_key = "rate_limit_main:{$endpoint}:{$identifier}";
        $main_requests = $this->cache->get($main_key, []);
        $main_requests[] = $now;
        $this->cache->set($main_key, $main_requests, $config['window_size']);
        
        return true;
    }
    
    /**
     * Get rate limit headers for response
     * @param string $endpoint Endpoint name
     * @param string $identifier User ID or IP address
     * @return array Headers to include in response
     */
    public function get_rate_limit_headers($endpoint, $identifier = null) {
        if (!$identifier) {
            $identifier = $this->user_id ?: $this->ip_address;
        }
        
        if (!$identifier) {
            return [];
        }
        
        $config = $this->config->get_endpoint_config($endpoint);
        $now = time();
        
        // Get current usage
        $main_key = "rate_limit_main:{$endpoint}:{$identifier}";
        $main_requests = $this->cache->get($main_key, []);
        $main_requests = array_filter($main_requests, function($timestamp) use ($now, $config) {
            return ($now - $timestamp) < $config['window_size'];
        });
        
        $remaining = $config['max_requests'] - count($main_requests);
        $reset_time = $now + $config['window_size'];
        
        return [
            'X-RateLimit-Limit' => $config['max_requests'],
            'X-RateLimit-Remaining' => max(0, $remaining),
            'X-RateLimit-Reset' => $reset_time,
            'X-RateLimit-Window' => $config['window_size']
        ];
    }
    
    /**
     * Clear rate limit data for a user/IP
     * @param string $endpoint Endpoint name
     * @param string $identifier User ID or IP address
     * @return bool Success status
     */
    public function clear_rate_limit($endpoint, $identifier = null) {
        if (!$identifier) {
            $identifier = $this->user_id ?: $this->ip_address;
        }
        
        if (!$identifier) {
            return false;
        }
        
        $burst_key = "rate_limit_burst:{$endpoint}:{$identifier}";
        $main_key = "rate_limit_main:{$endpoint}:{$identifier}";
        $penalty_key = "rate_limit_penalty:{$endpoint}:{$identifier}";
        
        $this->cache->del($burst_key);
        $this->cache->del($main_key);
        $this->cache->del($penalty_key);
        
        return true;
    }
}

/**
 * Rate limiting trait for handler modules
 */
trait Hm_Rate_Limiter_Trait {
    
    /**
     * Check rate limit before processing request
     * @param string $endpoint Endpoint name
     * @return bool True if request should continue
     */
    protected function check_rate_limit($endpoint) {
        $rate_limiter = new Hm_Rate_Limiter(
            $this->cache,
            $this->session->get('username'),
            $this->request->server['REMOTE_ADDR'] ?? null
        );
        
        $result = $rate_limiter->check_rate_limit($endpoint);
        
        if (!$result['allowed']) {
            Hm_Msgs::add($result['message'], 'error');
            $this->out('rate_limit_error', true);
            $this->out('rate_limit_reset', $result['reset_time']);
            
            // Add rate limit headers to response
            $headers = $rate_limiter->get_rate_limit_headers($endpoint);
            foreach ($headers as $name => $value) {
                $this->out('response_headers', [$name => $value], false);
            }
            
            return false;
        }
        
        // Record the request
        $rate_limiter->record_request($endpoint);
        
        // Add rate limit headers to response
        $headers = $rate_limiter->get_rate_limit_headers($endpoint);
        foreach ($headers as $name => $value) {
            $this->out('response_headers', [$name => $value], false);
        }
        
        return true;
    }
}
