<?php

/**
 * Github modules
 * @package modules
 * @subpackage github
 */

if (!defined('DEBUG_MODE')) { die(); }

/**
 * @subpackage github/handler
 */
class Hm_Handler_setup_github_connect extends Hm_Handler_Module {
    public function process() {
    }
}

/**
 * @subpackage github/handler
 */
class Hm_Handler_github_list_type extends Hm_Handler_Module {
    public function process() {
        if (array_key_exists('list_path', $this->request->get)) {
            $path = $this->request->get['list_path'];
            if ($path == 'github_all') {
                $this->out('list_path', 'github_all');
                $this->out('list_parent', 'github_all');
                $this->out('mailbox_list_title', array('Github'));
                $this->append('data_sources', array('callback' => 'load_github_all_data', 'type' => 'github', 'name' => 'Github', 'id' => 0));
            }
        }
    }
}
/**
 * @subpackage github/output
 */
class Hm_Output_github_folders extends Hm_Output_Module {
    protected function output() {
        $res = '<li class="menu_hn_trending"><a class="unread_link" href="?page=message_list&list_path=github_all">'.
            '<img class="account_icon" src="'.$this->html_safe(Hm_Image_Sources::$code).
            '" alt="" width="16" height="16" /> '.$this->trans('All').'</a></li>';

        $this->append('folder_sources', 'github_folders');
        Hm_Page_Cache::add('github_folders', $res, true);
        return '';
    }
}

/**
 * @subpackage github/output
 */
class Hm_Output_github_connect_section extends Hm_Output_Module {
    protected function output() {
        $res = '<div class="github_connect"><div data-target=".github_connect_section" class="server_section">'.
            '<img src="'.Hm_Image_Sources::$key.'" alt="" width="16" height="16" /> '.
            $this->trans('Github Connect').'</div><div class="github_connect_section">'.
            'Connect to Github<br /><br />'.
            '</div></div>';

        return $res;
    }
}

?>
