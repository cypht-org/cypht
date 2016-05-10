<?php

/**
 * IMAP folder management
 * @package modules
 * @subpackage imap_folders
 */

if (!defined('DEBUG_MODE')) { die(); }


/**
 * @subpackage imap_folders/handler
 */
class Hm_Handler_folders_server_id extends Hm_Handler_Module {
    public function process() {
        if (array_key_exists('imap_server_id', $this->request->get)) {
            $this->out('folder_server', $this->request->get['imap_server_id']);
        }
    }
}

/**
 * @subpackage imap_folders/handler
 */
class Hm_Handler_imap_folder_check extends Hm_Handler_Module {
    public function process() {
        $this->out('imap_support', $this->module_is_supported('imap'));
    }
}

/**
 * @subpackage imap_folders/output
 */
class Hm_Output_folders_server_select extends Hm_Output_Module {
    protected function output() {
        $server_id = $this->get('folder_server');
        $res = '<div class="folders_page"><form method="get">';
        $res .= '<input type="hidden" name="page" value="folders" />';
        $res .= '<select id="imap_server_folder" name="imap_server_id">';
        $res .= '<option value="">'.$this->trans('Select an IMAP server').'</option>';
        foreach ($this->get('imap_servers', array()) as $id => $server) {
            $res .= '<option ';
            if ($server_id === $id) {
                $res .= 'selected="selected" ';
            }
            $res .= 'value="'.$this->html_safe($id).'">';
            $res .= $this->html_safe($server['name']);
        }
        $res .= '</select></form></div>';
        return $res;
    }
}

/**
 * @subpackage imap_folders/output
 */
class Hm_Output_folders_delete_dialog extends Hm_Output_Module {
    protected function output() {
        if ($this->get('folder_server') !== false) {
            $res = '<div data-target=".delete_dialog" class="settings_subtitle">'.$this->trans('Delete a Folder').'</div>';
            $res .= '<div class="delete_dialog folder_dialog">';
            $res .= '<div class="folder_row"><a href="#" class="select_delete_folder">'.$this->trans('Select Folder').'</a>: <span class="selected_delete">';
            $res .= '</span></div>';
            $res .= '<ul class="folders delete_folder_select"><li class="delete_title"><a href="#" class="close">';
            $res .= $this->trans('Cancel').'</a></li></ul>';
            $res .= ' <input type="button" value="'.$this->trans('Delete').'" />';
            $res .= '</div>';
            return $res;
        }
    }
}

/**
 * @subpackage imap_folders/output
 */
class Hm_Output_folders_rename_dialog extends Hm_Output_Module {
    protected function output() {
        if ($this->get('folder_server') !== false) {
            $res = '<div data-target=".rename_dialog" class="settings_subtitle">'.$this->trans('Rename a Folder').'</div>';
            $res .= '<div class="rename_dialog folder_dialog">';
            $res .= '<div class="folder_row"><a href="#" class="select_rename_folder">'.$this->trans('Select Folder').'</a>: <span class="selected_rename">';
            $res .= '</span></div>';
            $res .= '<ul class="folders rename_folder_select"><li class="rename_title"><a href="#" class="close">';
            $res .= $this->trans('Cancel').'</a></li></ul>';
            $res .= ' <input type="text" value="" placeholder="'.$this->trans('New Folder Name').'" /><br />';
            $res .= ' <input type="button" value="'.$this->trans('Rename').'" />';
            $res .= '</div>';
            return $res;
        }
    }
}

/**
 * @subpackage imap_folders/output
 */
class Hm_Output_folders_create_dialog extends Hm_Output_Module {
    protected function output() {
        if ($this->get('folder_server') !== false) {
            $res = '<div data-target=".create_dialog" class="settings_subtitle">'.$this->trans('Create a New Folder').'</div>';
            $res .= '<div class="create_dialog folder_dialog">';
            $res .= '<input class="create_folder_name" type="text" value="" placeholder="'.$this->trans('Folder name').'" /><br />';
            $res .= '<div class="folder_row"><a href="#" class="select_parent_folder">'.$this->trans('Select Parent Folder').'</a>: <span class="selected_parent">';
            $res .= '</span></div>';
            $res .= '<ul class="folders parent_folder_select"><li class="parent_title"><a href="#" class="close">';
            $res .= $this->trans('Cancel').'</a></li></ul>';
            $res .= ' <input type="button" value="'.$this->trans('Create').'" />';
            $res .= '</div>';
            return $res;
        }
    }
}

/**
 * @subpackage imap_folders/output
 */
class Hm_Output_folders_content_start extends Hm_Output_Module {
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
        if ($this->get('imap_support')) {
            $res = '<li class="menu_folders"><a class="unread_link" href="?page=folders">'.
                '<img class="account_icon" src="'.$this->html_safe(Hm_Image_Sources::$folder).
                '" alt="" width="16" height="16" /> '.$this->trans('Folders').'</a></li>';
            if ($this->format == 'HTML5') {
                return $res;
            }
            $this->concat('formatted_folder_list', $res);
        }
    }
}
