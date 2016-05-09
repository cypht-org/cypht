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
class Hm_Handler_mod_env extends Hm_Handler_Module {
    public function process() {
        $this->out('mod_support', array_filter(array(
            $this->module_is_supported('imap') ? 'imap' : false,
            $this->module_is_supported('pop3') ? 'pop3' : false,
            $this->module_is_supported('feeds') ? 'feeds' : false,
            $this->module_is_supported('github') ? 'github' : false,
            $this->module_is_supported('wordpress') ? 'wordpress' : false
        )));
    }
}

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
class Hm_Output_tag_folders extends hm_output_module {
    protected function output() {
        $this->append('folder_sources', array('tags_folders', ''));
    }
}

/**
 * @subpackage tags/output
 */
class Hm_Output_tag_bar extends hm_output_module {
    protected function output() {
        $headers = $this->get('msg_headers');
        if (is_string($headers)) {
            $this->out('msg_headers', $headers.'<img class="tag_icon refresh_list" src="'.
                Hm_Image_Sources::$tags.'" alt="'.$this->trans('Tags').'" width="24" height="24" />');
        }
    }
}
