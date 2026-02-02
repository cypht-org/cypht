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
    $modal .= '<div class="spam-report-targets-list text-muted">'.$trans('No targets configured').'</div>';
    $modal .= '</div>';
    $modal .= '<div class="spam-report-preview">';
    $modal .= '<div class="fw-bold mb-2">'.$trans('Preview').'</div>';
    $modal .= '<label class="form-label">'.$trans('Headers').'</label>';
    $modal .= '<textarea class="form-control spam-report-headers" rows="8"></textarea>';
    $modal .= '<label class="form-label mt-3">'.$trans('Plain text body').'</label>';
    $modal .= '<textarea class="form-control spam-report-body" rows="8"></textarea>';
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
    $modal .= '<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">'.$trans('Close').'</button>';
    $modal .= '</div>';
    $modal .= '</div></div></div>';
    return $modal;
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
