<?php

/**
 * Info modules
 * @package modules
 * @subpackage info
 */

if (!defined('DEBUG_MODE')) { die(); }

/**
 * @subpackage info/output
 */
class Hm_Output_dev_content extends Hm_Output_Module {
    protected function output($format) {
        return '<div class="dev_content"><div class="content_title">'.$this->trans('Developer Documentation').'</div>'.
            '<div class="long_text"></div></div>';
    }
}

/**
 * @subpackage info/output
 */
class Hm_Output_bug_report_form extends Hm_Output_Module {
    protected function output($format) {
        return '<div class="bug_report"><div class="content_title">'.$this->trans('Report a bug').'</div>'.
            '<div class="long_text">'.$this->trans('If you found a bug or want a feature we want to hear from you!').'</div></div>';
    }
}

/**
 * @subpackage info/output
 */
class Hm_Output_help_content extends Hm_Output_Module {
    protected function output($format) {
        return '<div class="help_content"><div class="content_title">'.$this->trans('Help').'</div>'.
            '<div class="long_text"></div></div>';
    }
}

/**
 * @subpackage info/output
 */
class Hm_Output_developer_doc_link extends Hm_Output_Module {
    protected function output($format) {
        $res = '<li class="menu_dev"><a class="unread_link" href="?page=dev">'.
            '<img class="account_icon" src="'.$this->html_safe(Hm_Image_Sources::$code).'" alt="" width="16" height="16" /> '.$this->trans('Development').'</a></li>';
        if ($format == 'HTML5') {
            return $res;
        }
        $this->concat('formatted_folder_list', $res);
    }
}

/**
 * @subpackage info/output
 */
class Hm_Output_bug_report_link extends Hm_Output_Module {
    protected function output($format) {
        $res = '<li class="menu_bug_report"><a class="unread_link" href="?page=bug_report">'.
            '<img class="account_icon" src="'.$this->html_safe(Hm_Image_Sources::$bug).'" alt="" width="16" height="16" /> '.$this->trans('Bugs').'</a></li>';
        if ($format == 'HTML5') {
            return $res;
        }
        $this->concat('formatted_folder_list', $res);
    }
}

/**
 * @subpackage info/output
 */
class Hm_Output_help_page_link extends Hm_Output_Module {
    protected function output($format) {
        $res = '<li class="menu_help"><a class="unread_link" href="?page=help">'.
            '<img class="account_icon" src="'.$this->html_safe(Hm_Image_Sources::$info).'" alt="" width="16" height="16" /> '.$this->trans('Help').'</a></li>';
        if ($format == 'HTML5') {
            return $res;
        }
        $this->concat('formatted_folder_list', $res);
    }
}

?>
