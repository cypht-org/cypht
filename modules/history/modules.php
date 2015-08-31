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
            '<img class="account_icon" src="'.$this->html_safe(Hm_Image_Sources::$env_closed).'" alt="" width="16" height="16" /> '.$this->trans('History').'</a></li>';
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
               '<table class="history_links"></table>';
        return $res;
    }
}
