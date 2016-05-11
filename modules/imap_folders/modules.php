<?php

/**
 * IMAP folder management
 * @package modules
 * @subpackage imap_folders
 */

/*
 * TODO:
 * add new strings to lang files
 */

if (!defined('DEBUG_MODE')) { die(); }

/**
 * @subpackage imap_folders/function
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
            $cache = Hm_IMAP_List::get_cache($this->session, $form['imap_server_id']);
            $imap = Hm_IMAP_List::connect($form['imap_server_id'], $cache);
            $err = true;
            if (is_object($imap) && $imap->get_state() == 'authenticated') {
                $ns = get_personal_ns($imap);
                if ($parent_str) {
                    $parts = explode('_', $parent_str, 3);
                    if (count($parts) == 3) {
                        $parent = hex2bin($parts[2]);
                    }
                    if (!$ns['delim']) {
                        Hm_Msgs::add('ERRCould not determine IMAP server delimiter');
                        return;
                    }
                    elseif (!$parent) {
                        Hm_Msgs::add('ERRInvalid parent mailbox name');
                    }
                    else {
                        $new_folder = sprintf('%s%s%s', $parent, $ns['delim'], $form['folder']);
                    }
                }
                else {
                    if ($ns['prefix']) {
                        $new_folder = sprintf('%s%s', $ns['prefix'], $new_folder);
                    }
                    else {
                        $new_folder = $form['folder'];
                    }
                }
                if ($imap->create_mailbox($new_folder)) {
                    $err = false;
                    Hm_Msgs::add('Folder created');
                }
                else {
                    Hm_Msgs::add('ERRAn error occurred creating the folder');
                }
                if (!$err) {
                    /* TODO
                     * add reload module from core
                     * flush cache
                     */
                    $this->out('reload_folders', true, false);
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
        list($success, $form) = $this->process_form(array('folder', 'new_folder'));
        if ($success) {
            /* TODO:
             * start imap
             * rename folder
             * reset folder list on success
             */
        }
    }
}

/**
 * @subpackage imap_folders/handler
 */
class Hm_Handler_process_folder_delete extends Hm_Handler_Module {
    public function process() {
        list($success, $form) = $this->process_form(array('folder'));
        if ($success) {
            /* TODO: 
             * start imap
             * delete folder
             * reset folder list on success
             */
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
            $res .= '<div class="folder_row"><a href="#" class="select_delete_folder">'.$this->trans('Select Folder').'</a>: <span class="selected_delete">';
            $res .= '</span></div>';
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
        /* TODO:
         * add rename parent support
         */
        if ($this->get('folder_server') !== NULL) {
            $res = '<div data-target=".rename_dialog" class="settings_subtitle">'.$this->trans('Rename a Folder').'</div>';
            $res .= '<div class="rename_dialog folder_dialog">';
            $res .= '<div class="folder_row"><a href="#" class="select_rename_folder">'.$this->trans('Select Folder').'</a>: <span class="selected_rename">';
            $res .= '</span></div>';
            $res .= '<ul class="folders rename_folder_select"><li class="rename_title"><a href="#" class="close">';
            $res .= $this->trans('Cancel').'</a></li></ul>';
            $res .= ' <input id="rename_value" type="text" value="" placeholder="'.$this->trans('New Folder Name').'" /><br />';
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
            $res .= '<input class="create_folder_name" id="create_value" type="text" value="" placeholder="'.$this->trans('New Folder Name').'" /><br />';
            $res .= '<div class="folder_row"><a href="#" class="select_parent_folder">'.$this->trans('Select Parent Folder (optional)').'</a>: <span class="selected_parent">';
            $res .= '</span></div>';
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
