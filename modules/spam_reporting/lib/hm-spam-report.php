<?php

/**
 * Internal spam report model
 * @package modules
 * @subpackage spam_reporting
 */

if (!defined('DEBUG_MODE')) { die(); }

class Hm_Spam_Report {
    public $raw_message;
    public $headers;
    public $body_text;
    public $body_html;
    public $source_ip;
    public $extracted_urls;
    public $metadata;

    private $message;

    public function __construct($raw_message, $message, array $metadata = array()) {
        $this->raw_message = $raw_message;
        $this->message = $message;
        $this->headers = spam_reporting_collect_headers($message);
        $this->body_text = $message->getTextContent();
        $this->body_html = $message->getHtmlContent();
        $this->source_ip = null;
        $this->extracted_urls = array();
        $this->metadata = $metadata;
    }

    public static function from_raw_message($raw_message, array $metadata = array()) {
        $message = spam_reporting_parse_message($raw_message);
        if (!$message) {
            return false;
        }
        return new self($raw_message, $message, $metadata);
    }

    public static function from_mailbox($mailbox, $folder, $uid, array $metadata = array()) {
        $raw_message = $mailbox->get_message_content($folder, $uid, 0);
        if (!$raw_message) {
            return false;
        }
        $metadata = array_merge($metadata, array(
            'folder' => $folder,
            'uid' => $uid
        ));
        return self::from_raw_message($raw_message, $metadata);
    }

    public function get_parsed_message() {
        return $this->message;
    }

    public function get_raw_headers_string() {
        return spam_reporting_format_raw_headers($this->message);
    }

    /**
     * Get source IPs extracted from Received headers (first-hop)
     * @return array of IP strings
     */
    public function get_source_ips() {
        if ($this->message) {
            return spam_reporting_extract_source_ips($this->message);
        }
        return array();
    }
}
