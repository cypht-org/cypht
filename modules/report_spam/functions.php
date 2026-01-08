<?php
use ZBateson\MailMimeParser\MailMimeParser;
/**
 * Report Spam modules
 * @package modules
 * @subpackage report_spam
 */

if (!defined('DEBUG_MODE')) { die(); }

 /**
 * Get setting value with fallback to _setting suffix
 * @subpackage report_spam/functions
 * @param array $settings User settings array
 * @param string $key Setting key without _setting suffix
 * @param mixed $default Default value
 * @return mixed Setting value
 */
if (!hm_exists('get_setting_value')) {
    function get_setting_value($settings, $key, $default = '') {
        if (array_key_exists($key, $settings)) {
            return $settings[$key];
        }
        if (array_key_exists($key . '_setting', $settings)) {
            return $settings[$key . '_setting'];
        }
        return $default;
    }
}

/**
 * Report spam message to SpamCop
 * Uses authenticated SMTP to ensure proper SPF/DKIM validation
 * Must use the exact email address from the IMAP server where the message is located
 */
if (!hm_exists('report_spam_to_spamcop')) {
function report_spam_to_spamcop($message_source, $reasons, $user_config, $session = null, $imap_server_email = '') {
    $spamcop_enabled = $user_config->get('spamcop_enabled_setting', false);
    if (!$spamcop_enabled) {
        return array('success' => false, 'error' => 'SpamCop reporting is not enabled');
    }

    $spamcop_email = $user_config->get('spamcop_submission_email_setting', '');
    if (empty($spamcop_email)) {
        return array('success' => false, 'error' => 'SpamCop submission email not configured');
    }

    $sanitized_message = sanitize_message_for_spam_report($message_source, $user_config);

    // SpamCop requires the exact email address associated with the account
    $from_email = '';
    if (!empty($imap_server_email)) {
        $from_email = $imap_server_email;
    } else {
        $from_email = $user_config->get('spamcop_from_email_setting', '');
        if (empty($from_email)) {
            if (class_exists('Hm_IMAP_List')) {
                $imap_servers = Hm_IMAP_List::dump();
                if (!empty($imap_servers)) {
                    $first_server = reset($imap_servers);
                    $from_email = isset($first_server['user']) ? $first_server['user'] : '';
                }
            }
        }
    }

    if (empty($from_email)) {
        return array('success' => false, 'error' => 'No sender email address configured');
    }

    $subject = 'Spam report';

    if (!class_exists('Hm_MIME_Msg')) {
        return array('success' => false, 'error' => 'SMTP module required for SpamCop reporting. Please enable the SMTP module.');
    }
    
    $temp_file = create_spam_report_temp_file($sanitized_message, $user_config, $session, 'spamcop_');
    
    $body = '';
    $mime = new Hm_MIME_Msg($spamcop_email, $subject, $body, $from_email, false, '', '', '', '', $from_email);
    
    $attachment = array(
        'name' => 'spam.eml',
        'type' => 'message/rfc822',
        'size' => strlen($sanitized_message),
        'filename' => $temp_file
    );
    
    $mime->add_attachments(array($attachment));

    $mime_message = $mime->get_mime_msg();
    
    // SpamCop rejects automated submissions, so remove X-Mailer headers
    $parser = new MailMimeParser();
    $message = $parser->parse($mime_message, false);
    $message->removeHeader('X-Mailer');
    $mime_message = (string) $message;
    
    $encoding_result = fix_spam_report_encoding($mime_message, 'SpamCop');
    $mime_message = $encoding_result['mime_message'];
    $mime_body = $encoding_result['mime_body'];
    $boundary = $encoding_result['boundary'];
    
    @unlink($temp_file);
    
    $headers = extract_spam_report_headers($mime_message, $boundary);
  
    $smtp_result = send_spam_report_via_smtp($from_email, $spamcop_email, $subject, $mime_body, $boundary, $user_config, $session, 'SpamCop', false);
    if ($smtp_result !== false) {
        return $smtp_result;
    }
    
    return send_spam_report_via_mail($spamcop_email, $subject, $mime_body, $headers, 'SpamCop');
}}

/**
 * Report phishing message to APWG (Anti-Phishing Working Group)
 * Uses authenticated SMTP to ensure proper SPF/DKIM validation
 * Must use the exact email address from the IMAP server where the message is located
 */
if (!hm_exists('report_spam_to_apwg')) {
function report_spam_to_apwg($message_source, $reasons, $user_config, $session = null, $imap_server_email = '') {
    $apwg_enabled = $user_config->get('apwg_enabled_setting', false);
    if (!$apwg_enabled) {
        return array('success' => false, 'error' => 'APWG reporting is not enabled');
    }

    $apwg_email = 'reportphishing@apwg.org';

    $sanitized_message = sanitize_message_for_spam_report($message_source, $user_config);

    $from_email = $user_config->get('apwg_from_email_setting', '');
    if (empty($from_email)) {
        $from_email = $imap_server_email;
    }

    if (empty($from_email)) {
        return array('success' => false, 'error' => 'No sender email address configured');
    }

    $subject = 'Phishing Report';

    if (!class_exists('Hm_MIME_Msg')) {
        return array('success' => false, 'error' => 'SMTP module required for APWG reporting. Please enable the SMTP module.');
    }

    $temp_file = create_spam_report_temp_file($sanitized_message, $user_config, $session, 'apwg_');
    
    $body = '';
    $mime = new Hm_MIME_Msg($apwg_email, $subject, $body, $from_email, false, '', '', '', '', $from_email);
    
    $attachment = array(
        'name' => 'phishing.eml',
        'type' => 'message/rfc822',
        'size' => strlen($sanitized_message),
        'filename' => $temp_file
    );
    
    $mime->add_attachments(array($attachment));

    $mime_message = $mime->get_mime_msg();

    $parser = new MailMimeParser();
    $message = $parser->parse($mime_message, false);
    $mime_message = (string) $message;

    $encoding_result = fix_spam_report_encoding($mime_message, 'APWG');
    $mime_message = $encoding_result['mime_message'];
    $mime_body = $encoding_result['mime_body'];
    $boundary = $encoding_result['boundary'];

    @unlink($temp_file);
    
    $headers = extract_spam_report_headers($mime_message, $boundary);
    
    $smtp_result = send_spam_report_via_smtp($from_email, $apwg_email, $subject, $mime_body, $boundary, $user_config, $session, 'APWG', true);
    if ($smtp_result !== false) {
        return $smtp_result;
    }

    return send_spam_report_via_mail($apwg_email, $subject, $mime_body, $headers, 'APWG');
}}

/**
 * Report spam message to AbuseIPDB
 * @param string $message_source Full email message source
 * @param array $reasons Array of spam reasons selected by user
 * @param object $user_config User configuration object
 * @return array Result array with 'success' (bool) and 'error' (string) keys
 */
if (!hm_exists('report_spam_to_abuseipdb')) {
function report_spam_to_abuseipdb($message_source, $reasons, $user_config) {
    $enabled = $user_config->get('abuseipdb_enabled_setting', false);
    if (!$enabled) {
        return array('success' => false, 'error' => 'AbuseIPDB reporting is not enabled');
    }

    $api_key = $user_config->get('abuseipdb_api_key_setting', '');
    if (empty($api_key)) {
        return array('success' => false, 'error' => 'AbuseIPDB API key not configured');
    }

    $rate_limit_key = 'abuseipdb_rate_limit_timestamp';
    $rate_limit_timestamp = $user_config->get($rate_limit_key, 0);
    $rate_limit_cooldown = 15 * 60;
    if ($rate_limit_timestamp > 0 && (time() - $rate_limit_timestamp) < $rate_limit_cooldown) {
        $remaining_minutes = ceil(($rate_limit_cooldown - (time() - $rate_limit_timestamp)) / 60);
        return array('success' => false, 'error' => sprintf('AbuseIPDB rate limit cooldown active. Please wait %d more minute(s) before trying again.', $remaining_minutes));
    }

    $ip = extract_ip_from_message($message_source);
    if (!$ip) {
        return array('success' => false, 'error' => 'Could not extract IP address from message');
    }

    $comment = implode(', ', $reasons);
    if (empty($comment)) {
        $comment = 'Spam email reported via Cypht';
    }
    
    $data = array(
        'ip' => $ip,
        'categories' => '11', // Category 11 = Email Spam (spam email content, infected attachments, and phishing emails)
        'comment' => $comment
    );

    $ch = curl_init('https://api.abuseipdb.com/api/v2/report');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
        'Accept: application/json',
        'Key: ' . $api_key
    ));
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    $curl_errno = curl_errno($ch);
    curl_close($ch);

    if ($curl_error || $curl_errno !== 0) {
        // Include HTTP code if available, otherwise just cURL error
        $error_msg = 'Failed to connect to AbuseIPDB';
        if ($http_code > 0) {
            $error_msg .= sprintf(' (HTTP %d)', $http_code);
        }
        if ($curl_error) {
            $error_msg .= ': ' . $curl_error;
        } elseif ($curl_errno !== 0) {
            $error_msg .= sprintf(' (cURL error %d)', $curl_errno);
        }
        return array('success' => false, 'error' => $error_msg);
    }
    
    if ($http_code === 200) {
        $result = json_decode($response, true);
        if (isset($result['data']['ipAddress'])) {
            $user_config->set($rate_limit_key, 0);
            return array('success' => true);
        } else {
            return array('success' => false, 'error' => 'Invalid response from AbuseIPDB');
        }
    } elseif ($http_code === 429) {
        // Rate limit exceeded - store timestamp to prevent immediate re-attempts
        $user_config->set($rate_limit_key, time());
        
        return array('success' => false, 'error' => 'AbuseIPDB rate limit exceeded. Please try again later.');
    } elseif ($http_code === 422) {
        $result = json_decode($response, true);
        $error_detail = 'Invalid request to AbuseIPDB';
        if (isset($result['errors'][0]['detail'])) {
            $error_detail = $result['errors'][0]['detail'];
        } elseif (isset($result['errors'][0]['title'])) {
            $error_detail = $result['errors'][0]['title'];
        }
        return array('success' => false, 'error' => 'AbuseIPDB validation error: ' . $error_detail);
    } elseif ($http_code === 401) {
        return array('success' => false, 'error' => 'AbuseIPDB API key is invalid. Please check your API key in Settings.');
    } else {
        $result = json_decode($response, true);
        $error_detail = sprintf('Failed to report to AbuseIPDB (HTTP %d)', $http_code);
        if (isset($result['errors'][0]['detail'])) {
            $error_detail = $result['errors'][0]['detail'];
        } elseif (isset($result['errors'][0]['title'])) {
            $error_detail = $result['errors'][0]['title'];
        }
        return array('success' => false, 'error' => 'AbuseIPDB error: ' . $error_detail);
    }
}
}

/**
 * Sanitize message source for spam reporting
 */
if (!hm_exists('sanitize_message_for_spam_report')) {
function sanitize_message_for_spam_report($message_source, $user_config) {
    $parser = new MailMimeParser();
    $message = $parser->parse($message_source, false);

    $user_emails = array();
    // Use Hm_IMAP_List which is already initialized by load_imap_servers_from_config handler
    if (class_exists('Hm_IMAP_List')) {
        $imap_servers = Hm_IMAP_List::dump();
        foreach ($imap_servers as $server) {
            if (isset($server['user'])) {
                $user_emails[] = strtolower($server['user']);
            }
        }
    }

    if (!empty($user_emails)) {
        $address_headers = array('From', 'To', 'Cc', 'Bcc', 'Reply-To', 'Sender', 'Return-Path');
        foreach ($address_headers as $header_name) {
            $header = $message->getHeader($header_name);
            if ($header) {
                $header_value = $header->getValue();
                if ($header_value) {
                    foreach ($user_emails as $email) {
                        $header_value = preg_replace('/\b' . preg_quote($email, '/') . '\b/i', '[REDACTED]', $header_value);
                    }

                    $message->setRawHeader($header_name, $header_value);
                }
            }
        }
    }

    $sensitive_headers = array('X-Original-From', 'X-Forwarded-For', 'X-Real-IP');
    foreach ($sensitive_headers as $header_name) {
        $message->removeHeader($header_name);
    }

    return (string) $message;
}
}

/**
 * Extract IP address from email message headers
 * @param string $message_source Full email message source
 * @return string|false IP address (IPv4 or IPv6) or false if not found
 */
if (!hm_exists('extract_ip_from_message')) {
function extract_ip_from_message($message_source) {
    $parts = explode("\r\n\r\n", $message_source, 2);
    $headers = isset($parts[0]) ? $parts[0] : '';
    
    if (empty($headers)) {
        return false;
    }

    $header_lines = explode("\r\n", $headers);
    $received_headers = array();
    $current_header = '';
    
    foreach ($header_lines as $line) {
        if (preg_match('/^Received:/i', $line)) {
            if (!empty($current_header)) {
                $received_headers[] = $current_header;
            }
            $current_header = $line;
        } elseif (!empty($current_header) && preg_match('/^\s+/', $line)) {
            $current_header .= ' ' . trim($line);
        } elseif (!empty($current_header)) {
            $received_headers[] = $current_header;
            $current_header = '';
        }
    }
    if (!empty($current_header)) {
        $received_headers[] = $current_header;
    }

    $valid_ips = array();
    
    foreach (array_reverse($received_headers) as $received) {
        // Pattern 1: from [IP] or from hostname [IP] (most common)
        // Matches: "from [192.168.1.1]" or "from mail.example.com [192.168.1.1]"
        if (preg_match('/from\s+(?:[^\s]+\s+)?\[?(\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3})\]?/i', $received, $matches)) {
            $candidate = $matches[1];
            if (filter_var($candidate, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 | FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                $valid_ips[] = $candidate;
            }
        }
        
        // Pattern 2: by hostname ([IP])
        // Matches: "by mail.example.com ([192.168.1.1])"
        if (preg_match('/by\s+[^\s]+\s+\(\[?(\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3})\]?\)/i', $received, $matches)) {
            $candidate = $matches[1];
            if (filter_var($candidate, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 | FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                $valid_ips[] = $candidate;
            }
        }
        
        // Pattern 3: IPv6 addresses
        // Matches: "from [2001:db8::1]" or "from [::1]"
        if (preg_match('/from\s+(?:[^\s]+\s+)?\[?([0-9a-f:]+)\]?/i', $received, $matches)) {
            $candidate = trim($matches[1], '[]');
            if (filter_var($candidate, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6 | FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                $valid_ips[] = $candidate;
            }
        }
        
        // Pattern 4: Generic IP pattern (fallback for edge cases)
        // Matches any valid-looking IP in the header
        if (preg_match('/\b(\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3})\b/', $received, $matches)) {
            $candidate = $matches[1];
            if (filter_var($candidate, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 | FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                // Avoid duplicates
                if (!in_array($candidate, $valid_ips)) {
                    $valid_ips[] = $candidate;
                }
            }
        }
    }
    
    // THe original sender, will be the first valid founded since we checked in reverse
    if (!empty($valid_ips)) {
        return $valid_ips[0];
    }

    $fallback_headers = array('X-Originating-IP', 'X-Forwarded-For', 'X-Real-IP');
    foreach ($fallback_headers as $header_name) {
        if (preg_match('/^' . preg_quote($header_name, '/') . ':\s*(.+)$/mi', $headers, $matches)) {
            $ip = trim($matches[1]);
            if (strpos($ip, ',') !== false) {
                $ip = trim(explode(',', $ip)[0]);
            }
            // Remove port if present
            if (strpos($ip, ':') !== false && !preg_match('/^\[.*\]$/', $ip)) {
                $ip_parts = explode(':', $ip);
                $ip = $ip_parts[0];
            }
            if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                return $ip;
            }
        }
    }
    
    return false;
}
}

/**
 * Error messages from spam reporting services
 * @subpackage imap/functions
 * @param string $error_msg Raw error message from service
 * @return string User-friendly error message
 */
if (!hm_exists('normalize_spam_report_error')) {
function normalize_spam_report_error($error_msg) {
    $error_mappings = array(
        // SpamCop error mappings
        'not enabled' => 'SpamCop reporting is not enabled. Please enable it in Settings.',
        'not configured' => 'SpamCop submission email is not configured. Please configure it in Settings.',
        'submission email' => 'SpamCop submission email is not configured. Please configure it in Settings.',
        'sender email' => 'No sender email address configured. Please configure it in Settings.',
        'No sender' => 'No sender email address configured. Please configure it in Settings.',
        'Failed to send email' => 'Failed to send email to SpamCop. Please check your server mail configuration.',
        'send email' => 'Failed to send email to SpamCop. Please check your server mail configuration.',
        
        // AbuseIPDB error mappings
        'AbuseIPDB reporting is not enabled' => 'AbuseIPDB reporting is not enabled. Please enable it in Settings.',
        'AbuseIPDB API key not configured' => 'AbuseIPDB API key is not configured. Please configure it in Settings.',
        'AbuseIPDB API key' => 'AbuseIPDB API key is not configured. Please configure it in Settings.',
        'AbuseIPDB API key is invalid' => 'AbuseIPDB API key is invalid. Please check your API key in Settings.',
        'Could not extract IP address' => 'Could not extract IP address from message. The email may not contain valid IP information.',
        'Could not extract IP address from message' => 'Could not extract IP address from message. The email may not contain valid IP information.',
        'Failed to connect to AbuseIPDB' => 'Failed to connect to AbuseIPDB. Please check your internet connection.',
        'AbuseIPDB rate limit exceeded' => 'AbuseIPDB rate limit exceeded. Please try again later.',
        'AbuseIPDB rate limit cooldown active' => 'AbuseIPDB rate limit cooldown active. Please wait before trying again.',
        'AbuseIPDB validation error' => 'AbuseIPDB validation error. Please check your API key and configuration.',
        'AbuseIPDB error' => 'An error occurred while reporting to AbuseIPDB. Please try again later.',
        'Invalid response from AbuseIPDB' => 'Invalid response from AbuseIPDB. Please try again later.',
        'cURL error' => 'Failed to connect to AbuseIPDB. Please check your internet connection.',
        
        // APWG error mappings
        'APWG reporting is not enabled' => 'APWG reporting is not enabled. Please enable it in Settings.',
        'No sender email address configured' => 'No sender email address configured. Please configure it in Settings.',
        'Failed to send email to APWG' => 'Failed to send email to APWG. Please check your server mail configuration.',
        'send email to APWG' => 'Failed to send email to APWG. Please check your server mail configuration.',
        'SMTP error' => 'Failed to send email to APWG. The SMTP server did not accept the message. Please check your SMTP configuration.',
        'SMTP server did not confirm delivery' => 'Failed to send email to APWG. The SMTP server did not confirm delivery (expected 250 OK response). Please try again later.',
        'SMTP server did not accept' => 'Failed to send email to APWG. The SMTP server did not accept the message for delivery. Please check your SMTP configuration.',
        'RCPT command failed' => 'Failed to send email to APWG. The recipient address may be invalid or rejected by the SMTP server.',
        'DATA command failed' => 'Failed to send email to APWG. The SMTP server did not accept the message data. Please try again later.',
        '250' => 'Email was successfully sent to APWG (250 OK response received).',
        '550' => 'Failed to send email to APWG. The recipient address was rejected by the mail server (550 error).',
        '551' => 'Failed to send email to APWG. The recipient address does not exist (551 error).',
        '552' => 'Failed to send email to APWG. The mail server rejected the message due to size limits (552 error).',
        '553' => 'Failed to send email to APWG. The recipient address format is invalid (553 error).',
        '554' => 'Failed to send email to APWG. The mail server rejected the message (554 error).'
    );
    
    foreach ($error_mappings as $key => $message) {
        if (strpos($error_msg, $key) !== false) {
            return $message;
        }
    }
    
    return $error_msg;
}}

/**
 * Create temporary file for spam report attachment
 * @param string $sanitized_message The sanitized message content
 * @param object $user_config User configuration object
 * @param object $session Session object
 * @param string $prefix File prefix (e.g., 'spamcop_' or 'apwg_')
 * @return string Path to temporary file
 */
if (!hm_exists('create_spam_report_temp_file')) {
function create_spam_report_temp_file($sanitized_message, $user_config, $session, $prefix) {
    $file_dir = $user_config->get('attachment_dir', sys_get_temp_dir());
    if (!is_dir($file_dir)) {
        $file_dir = sys_get_temp_dir();
    }

    if ($file_dir !== sys_get_temp_dir() && $session) {
        $user_dir = $file_dir . DIRECTORY_SEPARATOR . md5($session->get('username', 'default'));
        if (!is_dir($user_dir)) {
            @mkdir($user_dir, 0755, true);
        }
        $file_dir = $user_dir;
    }
    $temp_file = tempnam($file_dir, $prefix);
    
    if (class_exists('Hm_Crypt') && class_exists('Hm_Request_Key')) {
        $encrypted_content = Hm_Crypt::ciphertext($sanitized_message, Hm_Request_Key::generate());
        file_put_contents($temp_file, $encrypted_content);
    } else {
        file_put_contents($temp_file, $sanitized_message);
    }
    
    return $temp_file;
}}

/**
 * @param string $mime_message The MIME message
 * @param string $service_name Service name for debug messages (e.g., 'SpamCop' or 'APWG')
 * @return array Array with 'mime_message', 'mime_body', and 'boundary'
 */
if (!hm_exists('fix_spam_report_encoding')) {
function fix_spam_report_encoding($mime_message, $service_name) {
    // Split headers and body
    $parts = explode("\r\n\r\n", $mime_message, 2);
    $mime_body = isset($parts[1]) ? $parts[1] : '';
    
    $boundary = '';
    if (preg_match('/^--([A-Za-z0-9]+)/m', $mime_body, $boundary_match)) {
        $boundary = $boundary_match[1];
    }
    
    return array(
        'mime_message' => $mime_message,
        'mime_body' => $mime_body,
        'boundary' => $boundary
    );
}}

/**
 * Extract headers array from MIME message for mail() function
 * @param string $mime_message The complete MIME message
 * @param string $boundary The MIME boundary
 * @return array Headers array for mail() function
 */
if (!hm_exists('extract_spam_report_headers')) {
function extract_spam_report_headers($mime_message, $boundary) {
    $parser = new MailMimeParser();
    $message = $parser->parse($mime_message, false);
    
    $headers = array();
    
    $from = $message->getHeaderValue('From');
    if ($from) {
        $headers[] = 'From: ' . $from;
    }
    
    $reply_to = $message->getHeaderValue('Reply-To');
    if ($reply_to) {
        $headers[] = 'Reply-To: ' . $reply_to;
    }
    
    $headers[] = 'MIME-Version: 1.0';
    
    if (!empty($boundary)) {
        $headers[] = 'Content-Type: multipart/mixed; boundary="' . $boundary . '"';
    } else {
        $content_type = $message->getHeaderValue('Content-Type');
        if ($content_type) {
            $headers[] = 'Content-Type: ' . $content_type;
        }
    }
    
    return $headers;
}}

/**
 * @param string $from_email Sender email address
 * @param string $to_email Recipient email address
 * @param string $subject Email subject
 * @param string $mime_body MIME message body
 * @param string $boundary MIME boundary
 * @param object $user_config User configuration object
 * @param object $session Session object
 * @param string $service_name Service name for logging (e.g., 'SpamCop' or 'APWG')
 * @param bool $use_fallback_smtp Whether to use fallback SMTP server if exact match not found
 * @return array|false Array with 'success' and optional 'error', or false if SMTP not available
 */
if (!hm_exists('send_spam_report_via_smtp')) {
function send_spam_report_via_smtp($from_email, $to_email, $subject, $mime_body, $boundary, $user_config, $session, $service_name, $use_fallback_smtp = false) {
    if (!class_exists('Hm_SMTP_List') || $session === null) {
        return false;
    }
    
    try {
        $smtp_servers = Hm_SMTP_List::dump();
        if (empty($smtp_servers)) {
            return false;
        }
        
        $smtp_id = false;
        foreach ($smtp_servers as $id => $server) {
            if (isset($server['user']) && strtolower(trim($server['user'])) === strtolower(trim($from_email))) {
                $smtp_id = $id;
                break;
            }
        }
        
        if ($use_fallback_smtp && $smtp_id === false) {
            $smtp_id = key($smtp_servers);
        }
        
        if ($smtp_id === false) {
            return false;
        }
        
        $mailbox = Hm_SMTP_List::connect($smtp_id, false);
        if (!$mailbox || !$mailbox->authed()) {
            if (defined('DEBUG_MODE') && DEBUG_MODE) {
                Hm_Debug::add(sprintf('%s: SMTP connection failed for server ID %s', $service_name, $smtp_id), 'warning');
            }
            return false;
        }
        
        // Manual header building required for SpamCop compatibility
        $smtp_headers = array();
        $smtp_headers[] = 'From: ' . $from_email;
        $smtp_headers[] = 'Reply-To: ' . $from_email;
        $smtp_headers[] = 'To: ' . $to_email;
        $smtp_headers[] = 'Subject: ' . $subject;   
        $smtp_headers[] = 'MIME-Version: 1.0';
        if (!empty($boundary)) {
            $smtp_headers[] = 'Content-Type: multipart/mixed; boundary="' . $boundary . '"';
        }
        $smtp_headers[] = 'Date: ' . date('r');
        $smtp_headers[] = 'Message-ID: <' . md5(uniqid(rand(), true)) . '@' . php_uname('n') . '>';
        
        $smtp_message = implode("\r\n", $smtp_headers) . "\r\n\r\n" . $mime_body;
        
        $err_msg = $mailbox->send_message($from_email, array($to_email), $smtp_message);
        
        if ($err_msg === false) {
            // 250 OK response - mail server accepted the email for delivery
            if (defined('DEBUG_MODE') && DEBUG_MODE) {
                if ($service_name === 'APWG') {
                    Hm_Debug::add(sprintf('%s: Email accepted by SMTP server (250 OK)', $service_name), 'info');
                }
            }
            return array('success' => true);
        } else {
            if (defined('DEBUG_MODE') && DEBUG_MODE) {
                Hm_Debug::add(sprintf('%s: SMTP send failed: %s', $service_name, $err_msg), 'warning');
            }
            return false;
        }
    } catch (Exception $e) {
        if (defined('DEBUG_MODE') && DEBUG_MODE) {
            Hm_Debug::add(sprintf('%s: SMTP exception: %s', $service_name, $e->getMessage()), 'error');
        }
        return false;
    }
}}
// DO we REALLY NEED THIS?

/**
 * Send spam report via PHP mail() function (fallback)
 * @param string $to_email Recipient email address
 * @param string $subject Email subject
 * @param string $mime_body MIME message body
 * @param array $headers Headers array for mail() function
 * @param string $service_name Service name for logging (e.g., 'SpamCop' or 'APWG')
 * @return array Array with 'success' and optional 'error'
 */
if (!hm_exists('send_spam_report_via_mail')) {
function send_spam_report_via_mail($to_email, $subject, $mime_body, $headers, $service_name) {
    $timeout = 10;
    $old_timeout = ini_get('default_socket_timeout');
    ini_set('default_socket_timeout', $timeout);
    
    try {
        $mail_sent = @mail($to_email, $subject, $mime_body, implode("\r\n", $headers));
        
        ini_set('default_socket_timeout', $old_timeout);
        
        if ($mail_sent) {
            if (defined('DEBUG_MODE') && DEBUG_MODE) {
                if ($service_name === 'APWG') {
                    Hm_Debug::add(sprintf('%s: mail() function returned true (delivery status unknown - no SMTP response available)', $service_name), 'info');
                }
            }
            return array('success' => true);
        } else {
            $error = sprintf('Failed to send email to %s. Please ensure your server has valid SPF/DKIM records or configure an SMTP server.', $service_name);
            if (defined('DEBUG_MODE') && DEBUG_MODE) {
                Hm_Debug::add(sprintf('%s: mail() function failed', $service_name), 'error');
                if ($service_name === 'APWG') {
                    $last_error = error_get_last();
                    if ($last_error) {
                        Hm_Debug::add(sprintf('%s: PHP error: %s', $service_name, $last_error['message']), 'error');
                    }
                }
            }
            return array('success' => false, 'error' => $error);
        }
    } catch (Exception $e) {
        ini_set('default_socket_timeout', $old_timeout);
        if (defined('DEBUG_MODE') && DEBUG_MODE) {
            Hm_Debug::add(sprintf('%s: Exception in mail(): %s', $service_name, $e->getMessage()), 'error');
        }
        return array('success' => false, 'error' => $e->getMessage());
    }
}}
