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
class Hm_Handler_process_folder_create extends Hm_Handler_Module {
    public function process() {
        list($success, $form) = $this->process_form(array('folder', 'imap_server_id'));
        if ($success) {
            $parent = false;
            $parent_str = false;
            if (array_key_exists('parent', $this->request->post)) {
                $parent_str = $this->request->post['parent'];
            }
            $cache = Hm_IMAP_List::get_cache($this->session, $this->config, $form['imap_server_id']);
            $imap = Hm_IMAP_List::connect($form['imap_server_id'], $cache);
            if (is_object($imap) && $imap->get_state() == 'authenticated') {
                $new_folder = prep_folder_name($imap, $form['folder'], false, $parent_str);
                if ($new_folder && $imap->create_mailbox($new_folder)) {
                    Hm_Msgs::add('Folder created');
                    Hm_Page_Cache::flush($this->session);
                    $this->out('imap_folders_success', true);
                }
                else {
                    Hm_Msgs::add('ERRAn error occurred creating the folder');
                }
            }
        }
    }
}

/**
 * @subpackage imap_folders/handler
 */
class Hm_Handler_process_folder_rename extends Hm_Handler_Module {
    public function process() {
        list($success, $form) = $this->process_form(array('imap_server_id', 'folder', 'new_folder'));
        if ($success) {
            $cache = Hm_IMAP_List::get_cache($this->session, $this->config, $form['imap_server_id']);
            $imap = Hm_IMAP_List::connect($form['imap_server_id'], $cache);
            $parent_str = false;
            if (array_key_exists('parent', $this->request->post)) {
                $parent_str = $this->request->post['parent'];
            }
            if (is_object($imap) && $imap->get_state() == 'authenticated') {
                $old_folder = prep_folder_name($imap, $form['folder'], true);
                $new_folder = prep_folder_name($imap, $form['new_folder'], false, $parent_str);
                if ($new_folder && $old_folder && $imap->rename_mailbox($old_folder, $new_folder)) {
                    Hm_Msgs::add('Folder renamed');
                    Hm_Page_Cache::flush($this->session);
                    $this->out('imap_folders_success', true);
                }
                else {
                    Hm_Msgs::add('ERRAn error occurred renaming the folder');
                }
            }
        }
    }
}

/**
 * @subpackage imap_folders/handler
 */
class Hm_Handler_process_folder_delete extends Hm_Handler_Module {
    public function process() {
        list($success, $form) = $this->process_form(array('imap_server_id', 'folder'));
        if ($success) {
            $cache = Hm_IMAP_List::get_cache($this->session, $this->config, $form['imap_server_id']);
            $imap = Hm_IMAP_List::connect($form['imap_server_id'], $cache);
            if (is_object($imap) && $imap->get_state() == 'authenticated') {
                $del_folder = prep_folder_name($imap, $form['folder'], true);
                if ($del_folder && $imap->delete_mailbox($del_folder)) {
                    Hm_Msgs::add('Folder deleted');
                    Hm_Page_Cache::flush($this->session);
                    $this->out('imap_folders_success', true);
                }
                else {
                    Hm_Msgs::add('ERRAn error occurred deleting the folder');
                }
            }
        }
    }
}

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
        $res .= '<input type="hidden" id="server_error" value="'.$this->trans('You must select an IMAP server first').'" />';
        $res .= '<input type="hidden" id="folder_name_error" value="'.$this->trans('New folder name is required').'" />';
        $res .= '<input type="hidden" id="delete_folder_error" value="'.$this->trans('Folder to delete is required').'" />';
        $res .= '<input type="hidden" id="delete_folder_confirm" value="'.$this->trans('Are you sure you want to delete this folder, and all the messages in it?').'" />';
        $res .= '<input type="hidden" id="rename_folder_error" value="'.$this->trans('Folder to rename is required').'" />';
        return $res;
    }
}

/**
 * @subpackage imap_folders/output
 */
class Hm_Output_folders_delete_dialog extends Hm_Output_Module {
    protected function output() {
        if ($this->get('folder_server') !== NULL) {
            $res = '<div data-target=".delete_dialog" class="settings_subtitle">'.$this->trans('Delete a Folder').'</div>';
            $res .= '<div class="delete_dialog folder_dialog">';
            $res .= '<div class="folder_row"><a href="#" class="select_delete_folder">';
            $res .= $this->trans('Select Folder').'</a>: <span class="selected_delete"></span></div>';
            $res .= '<ul class="folders delete_folder_select"><li class="delete_title"><a href="#" class="close">';
            $res .= $this->trans('Cancel').'</a></li></ul>';
            $res .= '<input type="hidden" value="" id="delete_source" />';
            $res .= ' <input type="button" id="delete_folder" value="'.$this->trans('Delete').'" />';
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
        if ($this->get('folder_server') !== NULL) {
            $res = '<div data-target=".rename_dialog" class="settings_subtitle">'.$this->trans('Rename a Folder').'</div>';
            $res .= '<div class="rename_dialog folder_dialog">';
            $res .= '<div class="folder_row"><a href="#" class="select_rename_folder">'.$this->trans('Select Folder');
            $res .= '</a>: <span class="selected_rename"></span></div>';
            $res .= '<ul class="folders rename_folder_select"><li class="rename_title"><a href="#" class="close">';
            $res .= $this->trans('Cancel').'</a></li></ul>';
            $res .= ' <input id="rename_value" type="text" value="" placeholder="'.$this->trans('New Folder Name').'" /><br />';

            $res .= '<div class="folder_row"><a href="#" class="select_rename_parent_folder">'.$this->trans('Select Parent Folder (optional)');
            $res .= '</a>: <span class="selected_rename_parent"></span></div>';
            $res .= '<ul class="folders rename_parent_folder_select"><li class="rename_parent_title"><a href="#" class="close">';
            $res .= $this->trans('Cancel').'</a></li></ul>';
            $res .= '<input type="hidden" value="" id="rename_parent_source" />';

            $res .= '<input type="hidden" value="" id="rename_source" />';
            $res .= ' <input type="button" id="rename_folder" value="'.$this->trans('Rename').'" />';
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
        if ($this->get('folder_server') !== NULL) {
            $res = '<div data-target=".create_dialog" class="settings_subtitle">'.$this->trans('Create a New Folder').'</div>';
            $res .= '<div class="create_dialog folder_dialog">';
            $res .= '<input class="create_folder_name" id="create_value" type="text" value="" placeholder="';
            $res .= $this->trans('New Folder Name').'" /><br /><div class="folder_row"><a href="#" class="select_parent_folder">';
            $res .= $this->trans('Select Parent Folder (optional)').'</a>: <span class="selected_parent"></span></div>';
            $res .= '<ul class="folders parent_folder_select"><li class="parent_title"><a href="#" class="close">';
            $res .= $this->trans('Cancel').'</a></li></ul>';
            $res .= '<input type="hidden" value="" id="create_parent" />';
            $res .= ' <input type="button" id="create_folder" value="'.$this->trans('Create').'" />';
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
            $res = '<li class="menu_folders"><a class="unread_link" href="?page=folders">';
            if (!$this->get('hide_folder_icons')) {
                $res .= '<img class="account_icon" src="'.$this->html_safe(Hm_Image_Sources::$folder).'" alt="" width="16" height="16" /> ';
            }
            $res .= $this->trans('Folders').'</a></li>';
            if ($this->format == 'HTML5') {
                return $res;
            }
            $this->concat('formatted_folder_list', $res);
        }
    }
}

/**
 * @subpackage imap_folders/functions
 */
function decode_folder_str($folder) {
    $folder_name = false;
    $parts = explode('_', $folder, 3);
    if (count($parts) == 3) {
        $folder_name = hex2bin($parts[2]);
    }
    return $folder_name;
}

/**
 * @subpackage imap_folders/functions
 */
function prep_folder_name($imap, $folder, $decode_folder=false, $parent=false) {
    if ($parent) {
        $parent = decode_folder_str($parent);
    }
    if ($decode_folder) {
        $folder = decode_folder_str($folder);
    }
    $ns = get_personal_ns($imap);
    if (!$folder) {
        return false;
    }
    if ($parent && !$ns['delim']) {
        return false;
    }
    if ($parent) {
        $folder = sprintf('%s%s%s', $parent, $ns['delim'], $folder);
    }
    if ($folder && $ns['prefix'] && substr($folder, 0, strlen($ns['prefix'])) !== $ns['prefix']) {
        $folder = sprintf('%s%s', $ns['prefix'], $folder);
    }
    return $folder;
}

/**
 * @subpackage imap_folders/functions
 */
function get_personal_ns($imap) {
    $namespaces = $imap->get_namespaces();
    foreach ($namespaces as $ns) {
        if ($ns['class'] == 'personal') {
            return $ns;
        }
    }
    return array(
        'prefix' => false,
        'delim'=> false
    );
}

