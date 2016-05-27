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
        $res = '<table class="shortcut_table">';
        $res .= '<tr><td colspan="2" class="settings_subtitle">'.$this->trans('General').'</th></tr>';
        $res .= '<tr><th class="keys">Esc</th><th>'.$this->trans('Unfocus all input elements').'</th></tr>';
        $res .= '<tr><th class="keys">Meta + e</th><th>'.$this->trans('Jump to the "Everything" page').'</th></tr>';
        $res .= '<tr><th class="keys">Meta + u</th><th>'.$this->trans('Jump to the "Unread" page').'</th></tr>';
        $res .= '<tr><th class="keys">Meta + f</th><th>'.$this->trans('Jump to the "Flagged" page').'</th></tr>';
        $res .= '<tr><th class="keys">Meta + c</th><th>'.$this->trans('Jump to Contacts').'</th></tr>';
        $res .= '<tr><th class="keys">Meta + h</th><th>'.$this->trans('Jump to History').'</th></tr>';
        $res .= '<tr><th class="keys">Meta + s</th><th>'.$this->trans('Jump to the Compose page').'</th></tr>';
        $res .= '<tr><th class="keys">Meta + t</th><th>'.$this->trans('Toggle the folder list').'</th></tr>';
        $res .= '<tr><td colspan="2" class="settings_subtitle">'.$this->trans('Message List').'</td></tr>';
        $res .= '<tr><th class="keys">n</th><th>'.$this->trans('Focus the next message in the list').'</th></tr>';
        $res .= '<tr><th class="keys">p</th><th>'.$this->trans('Focus the previous message in the list').'</th></tr>';
        $res .= '<tr><th class="keys">Enter</th><th>'.$this->trans('Open the currently focused message').'</th></tr>';
        $res .= '<tr><th class="keys">s</th><th>'.$this->trans('Select/unselect the currently focused message').'</th></tr>';
        $res .= '<tr><th class="keys">a</th><th>'.$this->trans('Toggle all message selections').'</th></tr>';
        $res .= '<tr><th class="keys">Shift + r</th><th>'.$this->trans('Mark selected messages as read').'</th></tr>';
        $res .= '<tr><th class="keys">Shift + u</th><th>'.$this->trans('Mark selected messages as unread').'</th></tr>';
        $res .= '<tr><th class="keys">Shift + f</th><th>'.$this->trans('Mark selected messages as flagged').'</th></tr>';
        $res .= '<tr><th class="keys">Shift + e</th><th>'.$this->trans('Mark selected messages as unflagged').'</th></tr>';
        $res .= '<tr><th class="keys">Shift + d</th><th>'.$this->trans('Delete selected messages').'</th></tr>';
        $res .= '<tr><td colspan="2" class="settings_subtitle">'.$this->trans('Message View').'</td></tr>';
        $res .= '<tr><th class="keys">n</th><th>'.$this->trans('View the next message in the list').'</th></tr>';
        $res .= '<tr><th class="keys">p</th><th>'.$this->trans('View the previous message in the list').'</th></tr>';
        $res .= '<tr><th class="keys">r</th><th>'.$this->trans('Reply').'</th></tr>';
        $res .= '<tr><th class="keys">Shift + r</th><th>'.$this->trans('Reply All').'</th></tr>';
        $res .= '<tr><th class="keys">Shift + f</th><th>'.$this->trans('Forward').'</th></tr>';
        $res .= '<tr><th class="keys">f</th><th>'.$this->trans('Flag the message').'</th></tr>';
        $res .= '<tr><th class="keys">u</th><th>'.$this->trans('Unflag the message').'</th></tr>';
        $res .= '<tr><th class="keys">d</th><th>'.$this->trans('Delete the message').'</th></tr>';
        $res .= '</table>';
        $res .= '</div>';
        return $res;
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

