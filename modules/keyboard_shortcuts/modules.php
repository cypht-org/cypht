<?php

/**
 * keyboard shortcuts modules
 * @package modules
 * @subpackage keyboard_shortcuts
 */

if (!defined('DEBUG_MODE')) { die(); }

/**
 * @subpackage keyboard_shortcuts/output
 */
class Hm_Output_start_shortcuts_page extends Hm_Output_Module {
    protected function output() {
        return '<div class="shortcut_content"><div class="content_title">'.$this->trans('Shortcuts').'</div>';
    }
}

/**
 * @subpackage keyboard_shortcuts/output
 */
class Hm_Output_shortcuts_content extends Hm_Output_Module {
    protected function output() {
        return '</div>';
    }
}

/**
 * @subpackage keyboard_shortcuts/output
 */
class Hm_Output_shortcuts_page_link extends Hm_Output_Module {
    protected function output() {
        $res = '<li class="menu_shortcuts"><a class="unread_link" href="?page=shortcuts">'.
            '<img class="account_icon" src="'.$this->html_safe(Hm_Image_Sources::$code).
            '" alt="" width="16" height="16" /> '.$this->trans('Shortcuts').'</a></li>';
        if ($this->format == 'HTML5') {
            return $res;
        }
        $this->concat('formatted_folder_list', $res);
    }
}

