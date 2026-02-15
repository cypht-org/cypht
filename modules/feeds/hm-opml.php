<?php

/**
 * OPML parser for feed subscriptions
 * @package modules
 * @subpackage feeds
 */

if (!defined('DEBUG_MODE')) { die(); }

/**
 * Parse OPML files to extract feed subscriptions
 * @subpackage feeds/lib
 */
class Hm_Opml_Parser {

    var $feeds;
    var $error;
    var $has_error;

    /**
     * Constructor - initialize parser state
     * @return void
     */
    function __construct() {
        $this->feeds = array();
        $this->error = '';
        $this->has_error = false;
    }

    /**
     * Parse OPML content string
     * @param string $content OPML XML content
     * @return bool True on success, false on failure
     */
    function parse($content) {
        if (empty($content)) {
            $this->error = 'Empty OPML content';
            $this->has_error = true;
            return false;
        }

        libxml_use_internal_errors(true);
        $xml = simplexml_load_string($content);

        if ($xml === false) {
            $errors = libxml_get_errors();
            libxml_clear_errors();
            if (!empty($errors)) {
                $this->error = 'Invalid XML: ' . $errors[0]->message;
            } else {
                $this->error = 'Failed to parse OPML XML';
            }
            $this->has_error = true;
            return false;
        }

        if ($xml->getName() !== 'opml') {
            $this->error = 'Root element must be <opml>';
            $this->has_error = true;
            return false;
        }

        if (!isset($xml->body) || !isset($xml->body->outline)) {
            $this->error = 'No feed outlines found in OPML';
            $this->has_error = true;
            return false;
        }

        $this->feeds = $this->processOutlines($xml->body->outline);

        return true;
    }

    /**
     * Recursively process outline elements
     * @param SimpleXMLElement $outlines Outline node(s)
     * @return array List of feeds
     */
    function processOutlines($outlines) {
        $result = array();

        foreach ($outlines as $outline) {
            $feed = $this->extractFeedInfo($outline);
            if ($feed !== null) {
                $result[] = $feed;
            }

            if (isset($outline->outline)) {
                $nested = $this->processOutlines($outline->outline);
                $result = array_merge($result, $nested);
            }
        }

        return $result;
    }

    /**
     * Extract feed information from an outline element
     * @param SimpleXMLElement $outline Outline element
     * @return array|null Feed info array or null if invalid
     */
    function extractFeedInfo($outline) {
        $xmlUrl = (string) $outline['xmlUrl'];
        if (empty($xmlUrl)) {
            return null;
        }

        if (!$this->validateUrl($xmlUrl)) {
            return null;
        }

        $name = '';
        if (isset($outline['text'])) {
            $name = (string) $outline['text'];
        } elseif (isset($outline['title'])) {
            $name = (string) $outline['title'];
        }

        if (empty($name)) {
            $name = $xmlUrl;  // Fallback to URL if no name provided
        }

        $tls = strpos($xmlUrl, 'https://') === 0;
        $port = $tls ? 443 : 80;

        return array(
            'name' => $name,
            'server' => $xmlUrl,
            'tls' => $tls,
            'port' => $port,
        );
    }

    /**
     * Validate URL format for feed
     * @param string $url URL to validate
     * @return bool True if valid HTTP/HTTPS URL
     */
    function validateUrl($url) {
        if (empty($url)) {
            return false;
        }

        $url = trim($url);
        if (!preg_match('/^https?:\/\//i', $url)) {
            return false;
        }

        return filter_var($url, FILTER_VALIDATE_URL) !== false;
    }

    /**
     * Get parsed feeds list
     * @return array List of feed information
     */
    function getFeeds() {
        return $this->feeds;
    }
}
