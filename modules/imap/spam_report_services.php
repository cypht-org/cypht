<?php
/**
 * Spam reporting service handlers
 * @package modules
 * @subpackage imap
 */

if (!defined('DEBUG_MODE')) { die(); }

/**
 * Base class for spam reporting services
 */
abstract class Hm_Spam_Reporter {
    protected $config;
    protected $mailbox;
    protected $message_data;

    public function __construct($config, $mailbox, $message_data) {
        $this->config = $config;
        $this->mailbox = $mailbox;
        $this->message_data = $message_data;
    }

    abstract public function report($reason);
    abstract protected function validate_config();
}

/**
 * SpamCop reporter
 */
class Hm_SpamCop_Reporter extends Hm_Spam_Reporter {
    public function report($reason) {
        if (!$this->validate_config()) {
            delayed_debug_log('SpamCop: Invalid configuration');
            return array('success' => false, 'error' => 'Invalid SpamCop configuration');
        }

        try {
            delayed_debug_log('SpamCop: Getting message content', array(
                'folder' => $this->message_data['folder'],
                'uid' => $this->message_data['uid']
            ));

            // Get full message content
            $message_content = $this->mailbox->get_message_content(
                $this->message_data['folder'],
                $this->message_data['uid']
            );

            if (!$message_content) {
                delayed_debug_log('SpamCop: Could not retrieve message content');
                return array('success' => false, 'error' => 'Could not retrieve message content');
            }

            delayed_debug_log('SpamCop: Message content retrieved', array(
                'content_length' => strlen($message_content)
            ));

            // Prepare email headers
            $headers = array(
                'From' => $this->message_data['from'],
                'To' => $this->config['config']['submit_address'],
                'Subject' => 'Spam Report: ' . $this->message_data['subject'],
                'Content-Type' => 'message/rfc822'
            );

            delayed_debug_log('SpamCop: Prepared headers', $headers);

            // Send the report
            delayed_debug_log('SpamCop: Attempting to send email');
            $result = Hm_Functions::send_email(
                $this->config['config']['submit_address'],
                $headers,
                $message_content
            );

            if (!$result) {
                $error = error_get_last();
                delayed_debug_log('SpamCop: Failed to send email', array(
                    'error' => $error ? $error['message'] : 'Unknown error',
                    'headers' => $headers,
                    'content_length' => strlen($message_content)
                ));
            } else {
                delayed_debug_log('SpamCop: Email sent successfully');
            }

            return array(
                'success' => $result,
                'service' => 'SpamCop',
                'message' => $result ? 'Report sent to SpamCop' : 'Failed to send report to SpamCop'
            );
        } catch (Exception $e) {
            delayed_debug_log('SpamCop: Exception during report', array(
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ));
            return array(
                'success' => false,
                'error' => 'SpamCop report failed: ' . $e->getMessage()
            );
        }
    }

    protected function validate_config() {
        return !empty($this->config['config']['submit_address']);
    }
}

/**
 * AbuseIPDB reporter
 */
class Hm_AbuseIPDB_Reporter extends Hm_Spam_Reporter {
    public function report($reason) {
        if (!$this->validate_config()) {
            return array('success' => false, 'error' => 'Invalid AbuseIPDB configuration');
        }

        try {
            // Extract IP from message headers
            $ip = $this->extract_ip_from_headers();
            if (!$ip) {
                return array('success' => false, 'error' => 'Could not extract IP from message');
            }

            // Prepare API request
            $data = array(
                'ip' => $ip,
                'categories' => $this->config['config']['categories']['spam'],
                'comment' => $reason
            );

            $headers = array(
                'Key: ' . $this->config['config']['api_key'],
                'Accept: application/json'
            );

            // Send report to AbuseIPDB
            $response = Hm_Functions::http_request(
                $this->config['config']['api_url'],
                'POST',
                $data,
                $headers
            );

            if (!$response) {
                return array('success' => false, 'error' => 'Failed to connect to AbuseIPDB');
            }

            $result = json_decode($response, true);
            return array(
                'success' => isset($result['data']['abuseConfidenceScore']),
                'service' => 'AbuseIPDB',
                'message' => isset($result['data']['abuseConfidenceScore']) ? 
                    'IP reported to AbuseIPDB' : 'Failed to report IP to AbuseIPDB'
            );
        } catch (Exception $e) {
            return array(
                'success' => false,
                'error' => 'AbuseIPDB report failed: ' . $e->getMessage()
            );
        }
    }

    protected function validate_config() {
        return !empty($this->config['config']['api_key']) && 
               !empty($this->config['config']['api_url']);
    }

    private function extract_ip_from_headers() {
        $headers = $this->mailbox->get_message_headers(
            $this->message_data['folder'],
            $this->message_data['uid']
        );

        // Try to get IP from Received headers
        if (isset($headers['Received'])) {
            if (preg_match('/\[([0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3})\]/', $headers['Received'], $matches)) {
                return $matches[1];
            }
        }

        return false;
    }
}

/**
 * StopForumSpam reporter
 */
class Hm_StopForumSpam_Reporter extends Hm_Spam_Reporter {
    public function report($reason) {
        if (!$this->validate_config()) {
            return array('success' => false, 'error' => 'Invalid StopForumSpam configuration');
        }

        try {
            // Extract email and IP from message
            $email = $this->extract_email_from_headers();
            $ip = $this->extract_ip_from_headers();

            if (!$email && !$ip) {
                return array('success' => false, 'error' => 'Could not extract email or IP from message');
            }

            // Prepare API request
            $data = array(
                'api_key' => $this->config['config']['api_key'],
                'evidence' => $reason
            );

            if ($email) {
                $data['email'] = $email;
            }
            if ($ip) {
                $data['ip'] = $ip;
            }

            // Send report to StopForumSpam
            $response = Hm_Functions::http_request(
                $this->config['config']['api_url'],
                'POST',
                $data
            );

            if (!$response) {
                return array('success' => false, 'error' => 'Failed to connect to StopForumSpam');
            }

            $result = json_decode($response, true);
            return array(
                'success' => isset($result['success']),
                'service' => 'StopForumSpam',
                'message' => isset($result['success']) ? 
                    'Report sent to StopForumSpam' : 'Failed to send report to StopForumSpam'
            );
        } catch (Exception $e) {
            return array(
                'success' => false,
                'error' => 'StopForumSpam report failed: ' . $e->getMessage()
            );
        }
    }

    protected function validate_config() {
        return !empty($this->config['config']['api_key']) && 
               !empty($this->config['config']['api_url']);
    }

    private function extract_email_from_headers() {
        $headers = $this->mailbox->get_message_headers(
            $this->message_data['folder'],
            $this->message_data['uid']
        );

        if (isset($headers['From'])) {
            if (preg_match('/<([^>]+)>/', $headers['From'], $matches)) {
                return $matches[1];
            }
        }

        return false;
    }

    private function extract_ip_from_headers() {
        $headers = $this->mailbox->get_message_headers(
            $this->message_data['folder'],
            $this->message_data['uid']
        );

        if (isset($headers['Received'])) {
            if (preg_match('/\[([0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3})\]/', $headers['Received'], $matches)) {
                return $matches[1];
            }
        }

        return false;
    }
}

/**
 * CleanTalk reporter
 */
class Hm_CleanTalk_Reporter extends Hm_Spam_Reporter {
    public function report($reason) {
        if (!$this->validate_config()) {
            return array('success' => false, 'error' => 'Invalid CleanTalk configuration');
        }

        try {
            // Get message content
            $message_content = $this->mailbox->get_message_content(
                $this->message_data['folder'],
                $this->message_data['uid']
            );

            if (!$message_content) {
                return array('success' => false, 'error' => 'Could not retrieve message content');
            }

            // Prepare API request
            $data = array(
                'auth_key' => $this->config['config']['api_key'],
                'method_name' => 'spam_check',
                'message' => $message_content,
                'sender_email' => $this->extract_email_from_headers(),
                'sender_ip' => $this->extract_ip_from_headers(),
                'submit_time' => time()
            );

            // Send report to CleanTalk
            $response = Hm_Functions::http_request(
                $this->config['config']['api_url'],
                'POST',
                $data
            );

            if (!$response) {
                return array('success' => false, 'error' => 'Failed to connect to CleanTalk');
            }

            $result = json_decode($response, true);
            return array(
                'success' => isset($result['allow']) && $result['allow'] == 0,
                'service' => 'CleanTalk',
                'message' => isset($result['allow']) && $result['allow'] == 0 ? 
                    'Content reported to CleanTalk' : 'Failed to report content to CleanTalk'
            );
        } catch (Exception $e) {
            return array(
                'success' => false,
                'error' => 'CleanTalk report failed: ' . $e->getMessage()
            );
        }
    }

    protected function validate_config() {
        return !empty($this->config['config']['api_key']) && 
               !empty($this->config['config']['api_url']);
    }

    private function extract_email_from_headers() {
        $headers = $this->mailbox->get_message_headers(
            $this->message_data['folder'],
            $this->message_data['uid']
        );

        if (isset($headers['From'])) {
            if (preg_match('/<([^>]+)>/', $headers['From'], $matches)) {
                return $matches[1];
            }
        }

        return false;
    }

    private function extract_ip_from_headers() {
        $headers = $this->mailbox->get_message_headers(
            $this->message_data['folder'],
            $this->message_data['uid']
        );

        if (isset($headers['Received'])) {
            if (preg_match('/\[([0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3})\]/', $headers['Received'], $matches)) {
                return $matches[1];
            }
        }

        return false;
    }
}

/**
 * Factory class to create spam reporters
 */
class Hm_Spam_Reporter_Factory {
    public static function create($service_name, $mailbox, $message_data) {
        $config = get_spam_service_config($service_name);
        if (!$config) {
            return false;
        }

        switch ($service_name) {
            case 'spamcop':
                return new Hm_SpamCop_Reporter($config, $mailbox, $message_data);
            case 'abuseipdb':
                return new Hm_AbuseIPDB_Reporter($config, $mailbox, $message_data);
            case 'stopforumspam':
                return new Hm_StopForumSpam_Reporter($config, $mailbox, $message_data);
            case 'cleantalk':
                return new Hm_CleanTalk_Reporter($config, $mailbox, $message_data);
            default:
                return false;
        }
    }
} 