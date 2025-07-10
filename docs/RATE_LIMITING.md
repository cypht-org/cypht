# Rate Limiting in Cypht

## Important: Centralized Spam Reporting Settings

All spam reporting, rate limiting, and auto-block settings are now managed in a single **Spam Reporting** section under **Site Settings** in the web interface. Previous per-module settings have been removed for clarity and ease of use.

- To configure spam reporting, rate limits, or auto-block options, go to **Site Settings → Spam Reporting** in the admin UI.
- All related options are now grouped together for easier management.

## Overview

Cypht now includes a comprehensive endpoint-specific rate limiting system to prevent abuse of API endpoints. This feature helps protect against spam, brute force attacks, and excessive resource consumption.

## Features

### 1. **Endpoint-Specific Configuration**
- Different rate limits for different endpoints
- Configurable time windows and request limits
- Burst protection to prevent rapid-fire requests

### 2. **Flexible Storage Backend**
- Supports multiple storage backends (Cache, Redis, Memcached)
- Automatic cleanup of expired rate limit data
- Persistent rate limiting across server restarts

### 3. **User-Friendly Interface**
- Rate limit settings in the admin interface
- Real-time feedback on rate limit status
- Clear error messages with countdown timers

### 4. **Security Features**
- IP-based and user-based rate limiting
- Penalty periods for violations
- Exempt IP addresses and user agents
- Comprehensive logging

## Configuration

### Environment Variables

You can configure rate limiting using environment variables:

```bash
# Enable/disable rate limiting
RATE_LIMITING_ENABLED=true

# Default settings
RATE_LIMIT_WINDOW_SIZE=3600
RATE_LIMIT_MAX_REQUESTS=100
RATE_LIMIT_BURST_LIMIT=10
RATE_LIMIT_BURST_WINDOW=60
RATE_LIMIT_PENALTY_DURATION=3600

# Spam reporting specific limits
SPAM_REPORT_WINDOW_SIZE=3600
SPAM_REPORT_MAX_REQUESTS=50
SPAM_REPORT_BURST_LIMIT=5
SPAM_REPORT_BURST_WINDOW=60
SPAM_REPORT_PENALTY_DURATION=7200

# Storage backend
RATE_LIMITING_STORAGE=cache

# Headers
RATE_LIMITING_HEADERS=true

# Logging
RATE_LIMITING_LOGGING=true
RATE_LIMITING_LOG_LEVEL=warning
```

### Configuration File

The main configuration is in `config/rate_limiting.php`:

```php
return [
    'enabled' => true,
    'defaults' => [
        'window_size' => 3600,
        'max_requests' => 100,
        'burst_limit' => 10,
        'burst_window' => 60,
        'penalty_duration' => 3600,
    ],
    'endpoints' => [
        'ajax_imap_report_spam' => [
            'window_size' => 3600,
            'max_requests' => 50,
            'burst_limit' => 5,
            'burst_window' => 60,
            'penalty_duration' => 7200,
        ],
        // ... other endpoints
    ],
];
```

## Default Rate Limits

| Endpoint        | Window | Max Requests | Burst Limit | Burst Window | Penalty |
|-----------------|--------|--------------|-------------|--------------|---------|
| Spam Report     | 1 hour | 50           | 5           | 1 minute     | 2 hours |
| Message Actions | 1 hour | 200          | 20          | 1 minute     | 1 hour |
| Search          | 1 hour | 300          | 30          | 1 minute     | 1 hour |
| Message List    | 1 hour | 500          | 50          | 1 minute     | 1 hour |
| Folder Display  | 1 hour | 200          | 20          | 1 minute     | 1 hour |

## Implementation Details

### Rate Limiting Algorithm

The system uses a **sliding window** approach with **burst protection**:

1. **Main Rate Limit**: Tracks requests over a configurable time window
2. **Burst Limit**: Prevents rapid-fire requests within a shorter window
3. **Penalty Period**: Temporarily blocks users who exceed burst limits

### Storage Keys

Rate limiting data is stored using these key patterns:

- `rate_limit_main:{endpoint}:{identifier}` - Main rate limit tracking
- `rate_limit_burst:{endpoint}:{identifier}` - Burst limit tracking
- `rate_limit_penalty:{endpoint}:{identifier}` - Penalty period tracking

### Response Headers

When rate limiting is enabled, responses include these headers:

- `X-RateLimit-Limit` - Maximum requests allowed
- `X-RateLimit-Remaining` - Remaining requests in current window
- `X-RateLimit-Reset` - Time when the limit resets
- `X-RateLimit-Window` - Size of the rate limit window

## Usage

### For Developers

To add rate limiting to a new endpoint:

1. **Add the trait to your handler**:
```php
class Hm_Handler_your_endpoint extends Hm_Handler_Module {
    use Hm_Rate_Limiter_Trait;
    
    public function process() {
        // Check rate limit before processing
        if (!$this->check_rate_limit('your_endpoint_name')) {
            return; // Request blocked
        }
        
        // Your endpoint logic here
    }
}
```

2. **Configure the endpoint** in `config/rate_limiting.php`:
```php
'endpoints' => [
    'your_endpoint_name' => [
        'window_size' => 3600,
        'max_requests' => 100,
        'burst_limit' => 10,
        'burst_window' => 60,
    ],
],
```

### For Administrators

1. **Enable rate limiting** in the admin interface
2. **Configure limits** for different endpoints
3. **Monitor logs** for rate limit violations
4. **Adjust settings** based on usage patterns

## Monitoring and Logging

### Log Messages

Rate limiting events are logged with these patterns:

- `Rate limit exceeded for endpoint: {endpoint}`
- `Burst rate limit exceeded for endpoint: {endpoint}`
- `Rate limit reset for endpoint: {endpoint}`

### Metrics

You can monitor rate limiting effectiveness by tracking:

- Number of rate limit violations
- Most frequently rate-limited endpoints
- Users/IPs with the most violations
- Average time between violations

## Security Considerations

### Exemptions

You can exempt certain IP addresses or user agents from rate limiting:

```php
'exempt_ips' => [
    '127.0.0.1',
    '::1',
    '192.168.1.100',
],

'exempt_user_agents' => [
    'MonitoringBot/1.0',
    'HealthCheck/1.0',
],
```

### Best Practices

1. **Start with conservative limits** and adjust based on usage
2. **Monitor logs** for unusual patterns
3. **Use different limits** for different user types (admin vs regular users)
4. **Consider time-of-day patterns** when setting limits
5. **Test rate limiting** in development before production

## Troubleshooting

### Common Issues

1. **Rate limits too strict**: Increase `max_requests` or `window_size`
2. **Burst limits too low**: Increase `burst_limit` or `burst_window`
3. **Penalties too long**: Decrease `penalty_duration`
4. **Storage issues**: Check cache/Redis/Memcached configuration

### Debug Mode

Enable debug logging to see detailed rate limiting information:

```php
'logging' => [
    'enabled' => true,
    'level' => 'debug',
],
```

## API Reference

### Hm_Rate_Limiter Class

```php
$rate_limiter = new Hm_Rate_Limiter($cache, $user_id, $ip_address);

// Check if request is allowed
$result = $rate_limiter->check_rate_limit('endpoint_name');

// Record a request
$rate_limiter->record_request('endpoint_name');

// Get rate limit headers
$headers = $rate_limiter->get_rate_limit_headers('endpoint_name');

// Clear rate limit data
$rate_limiter->clear_rate_limit('endpoint_name');
```

### Hm_Rate_Limiter_Trait

```php
// Check rate limit in handler
$this->check_rate_limit('endpoint_name');
```

## Migration Guide

### From No Rate Limiting

1. Enable rate limiting with conservative defaults
2. Monitor usage patterns for 1-2 weeks
3. Adjust limits based on actual usage
4. Gradually tighten limits as needed

### From Basic Rate Limiting

1. Review existing rate limiting configuration
2. Map existing limits to new endpoint-specific system
3. Test new configuration in staging
4. Deploy with monitoring enabled

## Support

For issues or questions about rate limiting:

1. Check the logs for detailed error messages
2. Review the configuration settings
3. Test with different rate limit values
4. Contact the development team with specific error details