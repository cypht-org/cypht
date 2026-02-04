<?php

/**
 * Spam reporting helpers and models
 * @package modules
 * @subpackage spam_reporting
 */

use ZBateson\MailMimeParser\MailMimeParser;

if (!defined('DEBUG_MODE')) { die(); }

/**
 * Internal spam report model
 * @subpackage spam_reporting/lib
 */
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
}

/**
 * Spam report payload container
 * @subpackage spam_reporting/lib
 */
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

/**
 * Delivery context for report targets
 * @subpackage spam_reporting/lib
 */
class Hm_Spam_Report_Delivery_Context {
    public $site_config;
    public $user_config;
    public $session;

    public function __construct($site_config, $user_config, $session) {
        $this->site_config = $site_config;
        $this->user_config = $user_config;
        $this->session = $session;
    }
}

/**
 * Delivery result container
 * @subpackage spam_reporting/lib
 */
class Hm_Spam_Report_Result {
    public $ok;
    public $message;
    public $details;

    public function __construct($ok, $message = '', array $details = array()) {
        $this->ok = (bool) $ok;
        $this->message = $message;
        $this->details = $details;
    }
}

/**
 * Parse a raw MIME message using the existing parser
 * @param string $raw_message
 * @return object|false
 */
if (!hm_exists('spam_reporting_parse_message')) {
function spam_reporting_parse_message($raw_message) {
    if (!trim($raw_message)) {
        return false;
    }
    $parser = new MailMimeParser();
    return $parser->parse($raw_message, false);
}}

/**
 * Collect headers as parser objects (no manual parsing)
 * @param object $message
 * @return array
 */
if (!hm_exists('spam_reporting_collect_headers')) {
function spam_reporting_collect_headers($message) {
    if (!$message || !method_exists($message, 'getAllHeaders')) {
        return array();
    }
    return $message->getAllHeaders();
}}

/**
 * Format raw headers into a single string
 * @param object $message
 * @return string
 */
if (!hm_exists('spam_reporting_format_raw_headers')) {
function spam_reporting_format_raw_headers($message) {
    if (!$message || !method_exists($message, 'getRawHeaders')) {
        return '';
    }
    $lines = array();
    foreach ($message->getRawHeaders() as $header) {
        if (is_array($header) && count($header) >= 2) {
            $lines[] = $header[0] . ': ' . $header[1];
        }
    }
    return implode("\r\n", $lines);
}}

/**
 * Build a target registry from config
 * @param object $site_config
 * @return Hm_Spam_Report_Targets_Registry
 */
if (!hm_exists('spam_reporting_build_registry')) {
function spam_reporting_build_registry($site_config) {
    $registry = new Hm_Spam_Report_Targets_Registry();
    $targets = $site_config->get('spam_reporting_targets');
    if (is_array($targets)) {
        foreach ($targets as $class_name) {
            if (is_string($class_name) && class_exists($class_name)) {
                $registry->register_target(new $class_name());
            } elseif (is_array($class_name) && array_key_exists('class', $class_name)) {
                $class = $class_name['class'];
                if (is_string($class) && class_exists($class)) {
                    $ref = new ReflectionClass($class);
                    $ctor = $ref->getConstructor();
                    if ($ctor && $ctor->getNumberOfParameters() > 0) {
                        $registry->register_target($ref->newInstance($class_name));
                    } else {
                        $target = $ref->newInstance();
                        if (method_exists($target, 'configure')) {
                            $target->configure($class_name);
                        }
                        $registry->register_target($target);
                    }
                }
            }
        }
    }
    return $registry;
}}

/**
 * Build the spam report modal markup
 * @param callable $trans translation callback
 * @return string
 */
if (!hm_exists('spam_reporting_modal_markup')) {
function spam_reporting_modal_markup($trans) {
    $modal = '<div class="modal fade" id="spamReportModal" tabindex="-1" aria-hidden="true">';
    $modal .= '<div class="modal-dialog modal-lg modal-dialog-scrollable">';
    $modal .= '<div class="modal-content">';
    $modal .= '<div class="modal-header">';
    $modal .= '<h5 class="modal-title">'.$trans('Report spam').'</h5>';
    $modal .= '<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="'.$trans('Close').'"></button>';
    $modal .= '</div>';
    $modal .= '<div class="modal-body">';
    $modal .= '<div class="spam-report-targets mb-3">';
    $modal .= '<div class="fw-bold mb-2">'.$trans('Targets').'</div>';
    $modal .= '<select class="form-select spam-report-target-select" disabled="disabled"></select>';
    $modal .= '<div class="spam-report-targets-empty text-muted mt-2">'.$trans('No targets configured').'</div>';
    $modal .= '</div>';
    $modal .= '<div class="spam-report-preview">';
    $modal .= '<div class="fw-bold mb-2">'.$trans('Preview').'</div>';
    $modal .= '<label class="form-label">'.$trans('Headers').'</label>';
    $modal .= '<textarea class="form-control spam-report-headers" rows="8"></textarea>';
    $modal .= '<label class="form-label mt-3">'.$trans('Plain text body').'</label>';
    $modal .= '<textarea class="form-control spam-report-body" rows="8"></textarea>';
    $modal .= '<label class="form-label mt-3">'.$trans('Notes').'</label>';
    $modal .= '<textarea class="form-control spam-report-notes" rows="3"></textarea>';
    $modal .= '<div class="form-check mt-3">';
    $modal .= '<input class="form-check-input spam-report-toggle-html" type="checkbox" id="spam_report_show_html">';
    $modal .= '<label class="form-check-label" for="spam_report_show_html">'.$trans('Show HTML body').'</label>';
    $modal .= '</div>';
    $modal .= '<div class="spam-report-html mt-2 d-none">';
    $modal .= '<label class="form-label">'.$trans('HTML body').'</label>';
    $modal .= '<textarea class="form-control spam-report-body-html" rows="8"></textarea>';
    $modal .= '</div>';
    $modal .= '</div>';
    $modal .= '</div>';
    $modal .= '<div class="modal-footer">';
    $modal .= '<div class="spam-report-status text-muted me-auto"></div>';
    $modal .= '<button type="button" class="btn btn-outline-secondary spam-report-send" disabled="disabled">'.$trans('Send report').'</button>';
    $modal .= '<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">'.$trans('Close').'</button>';
    $modal .= '</div>';
    $modal .= '</div></div></div>';
    return $modal;
}}

/**
 * Build a system SMTP mailbox for spam reporting
 * @param object $site_config
 * @return array [mailbox, server_id]
 */
if (!hm_exists('spam_reporting_get_smtp_mailbox')) {
function spam_reporting_get_smtp_mailbox($site_config) {
    $server = $site_config->get('spam_reporting_smtp_server', '');
    if (!trim((string) $server)) {
        return array(false, false);
    }
    $smtp = array(
        'name' => $site_config->get('spam_reporting_smtp_name', 'Spam Reporting'),
        'server' => $server,
        'type' => 'smtp',
        'hide' => true,
        'port' => $site_config->get('spam_reporting_smtp_port', 587),
        'user' => $site_config->get('spam_reporting_smtp_user', ''),
        'pass' => $site_config->get('spam_reporting_smtp_pass', ''),
        'tls' => $site_config->get('spam_reporting_smtp_tls', true),
    );
    if ($site_config->get('spam_reporting_smtp_no_auth', false)) {
        $smtp['no_auth'] = true;
    }
    $server_id = Hm_SMTP_List::add($smtp, false);
    $mailbox = Hm_SMTP_List::connect($server_id, false, $smtp['user'], $smtp['pass'], false);
    return array($mailbox, $server_id);
}}

/**
 * Enforce a per-user rate limit
 * @param object $session
 * @param object $site_config
 * @return array [allowed, retry_after]
 */
if (!hm_exists('spam_reporting_rate_limit')) {
function spam_reporting_rate_limit($session, $site_config, $record = false) {
    $limit = (int) $site_config->get('spam_reporting_rate_limit_count', 5);
    $window = (int) $site_config->get('spam_reporting_rate_limit_window', 3600);
    $now = time();
    if ($limit <= 0 || $window <= 0) {
        return array(true, 0);
    }
    $log = $session->get('spam_reporting_rate_log', array());
    $log = array_values(array_filter($log, function($ts) use ($now, $window) {
        return is_int($ts) && ($now - $ts) < $window;
    }));
    if (count($log) >= $limit) {
        $retry_after = $window - ($now - $log[0]);
        return array(false, max(0, $retry_after));
    }
    if ($record) {
        $log[] = $now;
        $session->set('spam_reporting_rate_log', $log);
    }
    return array(true, 0);
}}

/**
 * Build a spam report from a mailbox/UID
 * @param object $mailbox
 * @param string $folder
 * @param string|int $uid
 * @param array $metadata
 * @return Hm_Spam_Report|false
 */
if (!hm_exists('spam_reporting_build_report')) {
function spam_reporting_build_report($mailbox, $folder, $uid, array $metadata = array()) {
    if (!$mailbox) {
        return false;
    }
    return Hm_Spam_Report::from_mailbox($mailbox, $folder, $uid, $metadata);
}}
