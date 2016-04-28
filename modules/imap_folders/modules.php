<?php

/**
 * IMAP folder management
 * @package modules
 * @subpackage imap_folders
 */

if (!defined('DEBUG_MODE')) { die(); }

/**
 * @subpackage imap_folders/output
 */
class Hm_Output_folders_content extends Hm_Output_Module {
    protected function output() {
        $res = '<div class="content_title">'.$this->trans('Folders').'</div>';
        return $res;
    }
}

/**
 * @subpackage imap_folders/output
 */
class Hm_Output_folders_page_link extends Hm_Output_Module {
    protected function output() {
        $res = '<li class="menu_folders"><a class="unread_link" href="?page=folders">'.
            '<img class="account_icon" src="'.$this->html_safe(Hm_Image_Sources::$folder).
            '" alt="" width="16" height="16" /> '.$this->trans('Folders').'</a></li>';
        if ($this->format == 'HTML5') {
            return $res;
        }
        $this->concat('formatted_folder_list', $res);
    }
}
