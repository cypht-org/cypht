<?php

/**
 * Tags modules
 * @package modules
 * @subpackage tags
 */

if (!defined('DEBUG_MODE')) { die(); }

/**
 * @subpackage tags/handler
 */
class Hm_Handler_tag_data extends Hm_Handler_Module {
    public function process() {
    }
}

/**
 * @subpackage tags/output
 */
class hm_output_tag_folders extends hm_output_module {
    protected function output() {
        $this->append('folder_sources', array('tags_folders', ''));
    }
}
