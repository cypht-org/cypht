<?php

/**
 * Profile modules
 * @package modules
 * @subpackage profile
 */

if (!defined('DEBUG_MODE')) { die(); }

/**
 * @subpackage profile/output
 */
class Hm_Output_profile_page_link extends Hm_Output_Module {
    protected function output($format) {
        $res = '<li class="menu_profiles"><a class="unread_link" href="?page=profiles">'.
            '<img class="account_icon" src="'.$this->html_safe(Hm_Image_Sources::$person).'" alt="" width="16" height="16" /> '.$this->trans('Profiles').'</a></li>';
        if ($this->format == 'HTML5') {
            return $res;
        }
        $this->concat('formatted_folder_list', $res);
    }
}

/**
 * @subpackage profile/output
 */
class Hm_Output_profile_content extends Hm_Output_Module {
    protected function output($format) {
        return '<div class="profile_content"><div class="content_title">'.$this->trans('Profiles').'</div></div>';
    }
}

?>
