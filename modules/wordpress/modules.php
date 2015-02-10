<?php

/**
 * WordPress modules
 * @package modules
 * @subpackage wordpress
 */
if (!defined('DEBUG_MODE')) { die(); }

/**
 * @subpackage wordpress/output
 */
class Hm_Output_wordpress_folders extends Hm_Output_Module {
    protected function output() {
        $res = '<li class="menu_wp_notifications"><a class="unread_link" href="?page=wordpress&list_path=wp_notifications">'.
            '<img class="account_icon" src="'.$this->html_safe(Hm_Image_Sources::$env_closed).
            '" alt="" width="16" height="16" /> '.$this->trans('Notifications').'</a></li>';
        $this->append('folder_sources', 'wordPress_folders');
        Hm_Page_Cache::add('wordPress_folders', $res, true);
        return '';
    }
}

?>
