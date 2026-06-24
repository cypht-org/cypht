<?php

/**
 * Spam report payload container
 * @package modules
 * @subpackage spam_reporting
 */

if (!defined('DEBUG_MODE')) { die(); }

class Hm_Spam_Report_Payload {
    public $content_type;
    public $to;
    public $subject;
    public $body;
    public $headers;
    public $raw_message;

    public function __construct($to, $subject, $body, $content_type = 'text/plain', array $headers = array(), $raw_message = '') {
        $this->to = $to;
        $this->subject = $subject;
        $this->body = $body;
        $this->content_type = $content_type;
        $this->headers = $headers;
        $this->raw_message = $raw_message;
    }
}
