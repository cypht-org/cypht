<?php

/**
 * Contact modules
 * @package modules
 * @subpackage history
 */

/**
 * @subpackage history/output
 */
class Hm_Output_history_page_link extends Hm_Output_Module {
    protected function output() {
        $res = '<li class="menu_history"><a class="unread_link" href="?page=history">'.
            '<img class="account_icon" src="'.$this->html_safe(Hm_Image_Sources::$history).
            '" alt="" width="16" height="16" /> '.$this->trans('History').'</a></li>';
        if ($this->format == 'HTML5') {
            return $res;
        }
        $this->concat('formatted_folder_list', $res);
    }
}

/**
 * @subpackage history/output
 */
class Hm_Output_history_heading  extends Hm_Output_Module {
    protected function output() {
        $res = '<div class="content_title">'.$this->trans('Message history').'</div>'.
            '<div class="history_content"><table class="message_table"><colgroup><col class="source_col">'.
            '<col class="from_col"><col class="subject_col"><col class="date_col"><col class="icon_col">'.
            '</colgroup><thead></thead><tbody></tbody></table></div>';
        return $res;
    }
}
