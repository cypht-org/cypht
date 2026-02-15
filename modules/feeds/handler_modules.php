<?php

/**
 * Feeds handler modules
 * @package modules
 * @subpackage feeds
 */

if (!defined('DEBUG_MODE')) { die(); }

require APP_PATH.'modules/feeds/hm-opml.php';

/**
 * @subpackage feeds/handler
 */
class Hm_Handler_process_import_opml extends Hm_Handler_Module {
    public function process() {
        list($success, $form) = $this->process_form(array('opml_file'));
        if (!$success) {
            $this->out('opml_import_result', array(
                'success' => false,
                'error' => 'No OPML file provided',
                'code' => 'NO_FILE'
            ));
            return;
        }

        $opml_content = $form['opml_file'];

        if (empty($opml_content)) {
            $this->out('opml_import_result', array(
                'success' => false,
                'error' => 'Empty OPML file',
                'code' => 'EMPTY_FILE'
            ));
            return;
        }

        // Decode base64 if needed
        $decoded = base64_decode($opml_content, true);
        if ($decoded !== false) {
            $opml_content = $decoded;
        }

        // Validate file size (1MB max = 1048576 bytes)
        $content_size = strlen($opml_content);
        if ($content_size > 10485760) {
            $this->out('opml_import_result', array(
                'success' => false,
                'error' => 'File size exceeds maximum limit of 10MB',
                'code' => 'FILE_TOO_LARGE'
            ));
            return;
        }

        // Validate MIME type (basic check)
        $allowed_mime_types = array('text/xml', 'application/xml', 'text/plain');
        $detected_type = false;

        // Check for UTF-8 BOM or XML declaration
        if (preg_match('/^\xEF\xBB\xBF/', $opml_content) ||
            preg_match('/^<\?xml/', ltrim($opml_content))) {
            $detected_type = 'application/xml';
        } elseif (preg_match('/^<opml/i', ltrim($opml_content))) {
            $detected_type = 'text/xml';
        }

        if (!$detected_type) {
            $this->out('opml_import_result', array(
                'success' => false,
                'error' => 'Invalid file type. Please upload a valid OPML/XML file',
                'code' => 'INVALID_TYPE'
            ));
            return;
        }

        // Parse OPML
        $parser = new Hm_Opml_Parser();
        if (!$parser->parse($opml_content)) {
            $error_msg = $parser->error ?: 'Failed to parse OPML file';
            $this->out('opml_import_result', array(
                'success' => false,
                'error' => $error_msg,
                'code' => 'PARSE_ERROR'
            ));
            return;
        }

        $feeds = $parser->getFeeds();
        $total = count($feeds);
        $imported = 0;
        $skipped = 0;
        $failed = 0;
        $failed_details = array();

        foreach ($feeds as $feed) {
            // Skip if URL is empty or invalid
            if (empty($feed['server']) || !$parser->validateUrl($feed['server'])) {
                $failed++;
                $failed_details[] = array(
                    'name' => $feed['name'],
                    'error' => 'Invalid URL'
                );
                continue;
            }

            // Check if feed already exists
            if (feed_exists($feed['server'])) {
                $skipped++;
                continue;
            }

            // Add the feed
            $result = Hm_Feed_List::add(array(
                'name' => $feed['name'],
                'server' => $feed['server'],
                'tls' => $feed['tls'],
                'port' => $feed['port']
            ));

            if ($result) {
                $imported++;
            } else {
                $failed++;
                $failed_details[] = array(
                    'name' => $feed['name'],
                    'url' => $feed['server'],
                    'error' => 'Failed to add feed'
                );
            }
        }

        // Save feeds
        Hm_Feed_List::save();

        $message = $imported > 0
            ? sprintf('Successfully imported %d feed(s)', $imported)
            : 'No new feeds to import';

        $this->out('reload_folders', true);
        $this->session->record_unsaved('OPML import completed');

        $this->out('opml_import_result', array(
            'success' => true,
            'total' => $total,
            'imported' => $imported,
            'skipped' => $skipped,
            'failed' => $failed,
            'message' => $message,
            'failed_details' => $failed_details,
            'reload_folders' => true
        ));
    }
}
