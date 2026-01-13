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
 */
if (!hm_exists('report_spam_to_spamcop')) {
function report_spam_to_spamcop($message_source, $user_config, $session = null, $imap_server_email = '') {
    $spamcop_enabled = $user_config->get('spamcop_enabled_setting', false);
    if (!$spamcop_enabled) {
        return array('success' => false, 'error' => 'SpamCop reporting is not enabled');
    }

    $spamcop_email = $user_config->get('spamcop_submission_email_setting', '');
    if (empty($spamcop_email)) {
        return array('success' => false, 'error' => 'SpamCop submission email not configured');
    }

    $sanitized_message = sanitize_message_for_spam_report($message_source);

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
    
    $parser = new MailMimeParser();
    $message = $parser->parse($mime_message, false);
    $message->removeHeader('X-Mailer');
    
    $original_boundary = $message->getHeaderParameter('Content-Type', 'boundary');
        
    $mime_message = (string) $message;
    
    $encoding_result = fix_spam_report_encoding($mime_message, $original_boundary);
    $mime_message = $encoding_result['mime_message'];
    $mime_body = $encoding_result['mime_body'];
    $boundary = $encoding_result['boundary'];
    
    @unlink($temp_file);
  
    $smtp_result = send_spam_report_via_smtp($from_email, $spamcop_email, $subject, $mime_body, $boundary, $session, 'SpamCop', false);
    if ($smtp_result !== false) {
        return $smtp_result;
    }
    
    return array('success' => false, 'error' => 'Failed to send email to SpamCop. Please check your SMTP configuration.');
}}

/**
 * Report phishing message to APWG (Anti-Phishing Working Group)
 */
if (!hm_exists('report_spam_to_apwg')) {
function report_spam_to_apwg($message_source, $user_config, $session = null, $imap_server_email = '') {
    $apwg_enabled = $user_config->get('apwg_enabled_setting', false);
    if (!$apwg_enabled) {
        return array('success' => false, 'error' => 'APWG reporting is not enabled');
    }

    $apwg_email = 'reportphishing@apwg.org';

    $sanitized_message = sanitize_message_for_spam_report($message_source);

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
    
    $original_boundary = $message->getHeaderParameter('Content-Type', 'boundary');
    
    $mime_message = (string) $message;

    $encoding_result = fix_spam_report_encoding($mime_message, $original_boundary);
    $mime_message = $encoding_result['mime_message'];
    $mime_body = $encoding_result['mime_body'];
    $boundary = $encoding_result['boundary'];

    @unlink($temp_file);
    
    $smtp_result = send_spam_report_via_smtp($from_email, $apwg_email, $subject, $mime_body, $boundary, $session, 'APWG', true);
    if ($smtp_result !== false) {
        return $smtp_result;
    }

    return array('success' => false, 'error' => 'Failed to send email to APWG. Please check your SMTP configuration.');
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

    $api = new Hm_API_Curl('json');
    $headers = array(
        'Accept: application/json',
        'Key: ' . $api_key
    );
    $result = $api->command('https://api.abuseipdb.com/api/v2/report', $headers, $data);
    $http_code = $api->last_status;

    if ($http_code === 200) {
        if (isset($result['data']['ipAddress'])) {
            $user_config->set($rate_limit_key, 0);
            return array('success' => true);
        } else {
            return array('success' => false, 'error' => 'Invalid response from AbuseIPDB');
        }
    } elseif ($http_code === 429) {
        $user_config->set($rate_limit_key, time());
        return array('success' => false, 'error' => 'AbuseIPDB rate limit exceeded. Please try again later.');
    } elseif ($http_code === 422) {
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
function sanitize_message_for_spam_report($message_source) {
    $parser = new MailMimeParser();
    $message = $parser->parse($message_source, false);

    $user_emails = array();
    if (class_exists('Hm_IMAP_List')) {
        $imap_servers = Hm_IMAP_List::dump();
        foreach ($imap_servers as $server) {
            if (isset($server['user'])) {
                $user_emails[] = strtolower($server['user']);
            }
        }
    }

    if (!empty($user_emails)) {
        $user_email_map = array_flip($user_emails);
        
        $address_headers = array('From', 'To', 'Cc', 'Bcc', 'Reply-To', 'Sender', 'Return-Path');
        foreach ($address_headers as $header_name) {
            $header = $message->getHeader($header_name);
            if ($header) {
                $header_value = $header->getValue();
                if ($header_value) {
                    $addresses = process_address_fld($header_value);    
                    $redacted_addresses = array();
                    
                    foreach ($addresses as $addr) {
                        $email = strtolower(trim($addr['email']));
                        $should_redact = isset($user_email_map[$email]);
                        
                        if ($should_redact) {
                            $addr['email'] = '[REDACTED]';
                        }
                        
                        if (!empty($addr['label'])) {
                            $display_name = trim($addr['label']);
                            if (preg_match("/^[a-zA-Z0-9 !#$%&'\*\+\-\/\=\?\^_`\{\|\}]+$/", $display_name)) {
                                $redacted_addresses[] = $display_name . ' <' . $addr['email'] . '>';
                            } else {
                                $display_name = '"' . str_replace('"', '\\"', $display_name) . '"';
                                $redacted_addresses[] = $display_name . ' <' . $addr['email'] . '>';
                            }
                        } else {
                            $redacted_addresses[] = '<' . $addr['email'] . '>';
                        }
                    }
                    
                    $redacted_header_value = implode(', ', $redacted_addresses);
                    $message->setRawHeader($header_name, $redacted_header_value);
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
 * Extract IP addresses from a Received header value using regex patterns
 * @param string $received_header_value Raw Received header value
 * @return array Array of valid public IP addresses found (IPv4 or IPv6)
 */
if (!hm_exists('extract_ips_from_received_header')) {
function extract_ips_from_received_header($received_header_value) {
    $candidates = array();
    
    if (preg_match_all('/(\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3})/', $received_header_value, $matches)) {
        $candidates = array_merge($candidates, $matches[1]);
    }
    
    if (preg_match_all('/\[([0-9a-f:]+)\]/i', $received_header_value, $matches)) {
        foreach ($matches[1] as $match) {
            $candidates[] = trim($match, '[]');
        }
    }
    
    $valid_ips = array();
    $seen = array();
    
    foreach ($candidates as $candidate) {
        if (isset($seen[$candidate])) {
            continue;
        }
        
        $is_ipv6 = strpos($candidate, ':') !== false;
        $flags = FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE;
        $ip_flags = $is_ipv6 ? (FILTER_FLAG_IPV6 | $flags) : (FILTER_FLAG_IPV4 | $flags);
        
        $valid = filter_var($candidate, FILTER_VALIDATE_IP, $ip_flags);
        if ($valid) {
            $valid_ips[] = $valid;
            $seen[$valid] = true;
        }
    }
    
    return $valid_ips;
}
}

/**
 * Extract IP address from email message headers using MailMimeParser
 * @param string $message_source Full email message source
 * @return string|false IP address (IPv4 or IPv6) or false if not found
 */
if (!hm_exists('extract_ip_from_message')) {
function extract_ip_from_message($message_source) {
    if (empty($message_source)) {
        return false;
    }
    
    $parser = new MailMimeParser();
    $message = $parser->parse($message_source, false);
    
    if (!$message) {
        return false;
    }
    
    $received_headers = array();
    $offset = 0;
    while (($header = $message->getHeader('Received', $offset)) !== null) {
        $received_headers[] = $header->getValue();
        $offset++;
    }
    
    foreach (array_reverse($received_headers) as $received_value) {
        $ips = extract_ips_from_received_header($received_value);
        if (!empty($ips)) {
            return $ips[0];
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
 * Extract MIME message parts (body and boundary) for SMTP sending
 * @param string $mime_message The full MIME message (headers + body)
 * @param string $pre_extracted_boundary Optional boundary extracted from original message before MailMimeParser reconstruction
 * @return array Array with 'mime_message' (original, unchanged), 'mime_body' (extracted body), and 'boundary' (extracted from Content-Type or body)
 */
if (!hm_exists('fix_spam_report_encoding')) {
function fix_spam_report_encoding($mime_message, $pre_extracted_boundary = '') {
    $boundary = '';
    $mime_body = '';
    
    if (!empty($pre_extracted_boundary)) {
        $boundary = $pre_extracted_boundary;
    }
    
    $parser = new MailMimeParser();
    // $message = $parser->parse($mime_message, false);
    // Since format preservation is critical for SMTP, we validate with MailMimeParser then extract body from original string
    $parts = explode("\r\n\r\n", $mime_message, 2);
    $mime_body = isset($parts[1]) ? $parts[1] : '';
        
    if (!empty($boundary)) {
        $boundary = trim($boundary, '"\''); 
    }
    
    return array(
        'mime_message' => $mime_message,
        'mime_body' => $mime_body,
        'boundary' => $boundary
    );
}}

/**
 * @param string $from_email Sender email address
 * @param string $to_email Recipient email address
 * @param string $subject Email subject
 * @param string $mime_body MIME message body
 * @param string $boundary MIME boundary
 * @param object $session Session object
 * @param string $service_name Service name for logging (e.g., 'SpamCop' or 'APWG')
 * @param bool $use_fallback_smtp Whether to use fallback SMTP server if exact match not found
 * @return array|false Array with 'success' and optional 'error', or false if SMTP not available
 */
if (!hm_exists('send_spam_report_via_smtp')) {
function send_spam_report_via_smtp($from_email, $to_email, $subject, $mime_body, $boundary, $session, $service_name, $use_fallback_smtp = false) {
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
