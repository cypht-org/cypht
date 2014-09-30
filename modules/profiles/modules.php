<?php

if (!defined('DEBUG_MODE')) { die(); }

class Hm_Output_profile_page_link extends Hm_Output_Module {
    protected function output($input, $format) {
        $res = '<li class="menu_profiles"><a class="unread_link" href="?page=profiles">'.
            '<img class="account_icon" src="'.$this->html_safe(Hm_Image_Sources::$person).'" alt="" width="16" height="16" /> '.$this->trans('Profiles').'</a></li>';
        if ($format == 'HTML5') {
            return $res;
        }
        $input['formatted_folder_list'] .= $res;
        return $input;
    }
}

class Hm_Output_profile_content extends Hm_Output_Module {
    protected function output($input, $format) {
        return '<div class="profile_content"><div class="content_title">Profiles</div></div>';
    }
}

?>
