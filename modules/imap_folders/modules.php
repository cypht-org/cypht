<?php

/**
 * IMAP folder management
 * @package modules
 * @subpackage imap_folders
 */

if (!defined('DEBUG_MODE')) { die(); }

// require_once APP_PATH.'modules/sievefilters/modules.php';

/**
 * @subpackage imap_folders/handler
 */
class Hm_Handler_fix_folder_assignments extends Hm_Handler_Module {
    public function process() {
        /* only run on login */
        if (!$this->session->loaded) {
            return;
        }
        /* check for already fixed settings */
        $specials = $this->user_config->get('special_imap_folders', array());
        if (count($specials) == 0 || array_key_exists('imap_server', reset($specials))) {
            return;
        }
        $updated = array();
        $count = 0;

        /* update special folders with imap details */
        foreach ($specials as $index => $vals) {
            if (!array_key_exists('imap_server', $vals)) {
                $count++;
                $server = Hm_IMAP_List::dump($index);
                if (!is_array($server) || !array_key_exists('server', $server)) {
                    continue;
                }
                $vals['imap_user'] = $server['user'];
                $vals['imap_server'] = $server['server'];
                $updated[$index] = $vals;
            }
        }

        /* save permanently if we updated anything */
        if ($count) {
            $this->user_config->set('special_imap_folders', $updated);
            $this->user_config->save_on_login = true;
        }
    }
}

/**
 * @subpackage imap_folders/handler
 */
class Hm_Handler_add_folder_manage_link extends Hm_Handler_Module {
    public function process() {
        $server = false;
        $folder = false;
        if (array_key_exists('imap_server_id', $this->request->post)) {
            $server = $this->request->post['imap_server_id'];
        }
        if (array_key_exists('folder', $this->request->post)) {
            $folder = $this->request->post['folder'];
        }
        if (($server || $server === 0) && !$folder) {
            $this->out('imap_folder_manage_link', sprintf('?page=folders&imap_server_id=%s', $server));
        }
    }
}

/**
 * @subpackage imap_folders/handler
 */
class Hm_Handler_process_clear_special_folder extends Hm_Handler_Module {
    public function process() {
        list($success, $form) = $this->process_form(array('special_folder_type', 'imap_server_id'));
        if (!$success || !in_array($form['special_folder_type'], array('sent', 'draft', 'trash', 'archive'), true)) {
            return;
        }
        $specials = $this->user_config->get('special_imap_folders', array());
        if (array_key_exists($form['imap_server_id'], $specials)) {
            $specials[$form['imap_server_id']][$form['special_folder_type']] = '';
        }
        $this->user_config->set('special_imap_folders', $specials);

        Hm_Msgs::add('Special folder unassigned');
        $this->session->record_unsaved('Special folder unassigned');
        $this->out('imap_special_name', '');
    }
}

/**
 * @subpackage imap_folders/handler
 */
class Hm_Handler_process_special_folder extends Hm_Handler_Module {
    public function process() {
        list($success, $form) = $this->process_form(array('special_folder_type', 'folder', 'imap_server_id'));
        if (!$success || !in_array($form['special_folder_type'], array('sent', 'draft', 'trash', 'archive'), true)) {
            return;
        }
        $cache = Hm_IMAP_List::get_cache($this->cache, $form['imap_server_id']);
        $imap = Hm_IMAP_List::connect($form['imap_server_id'], $cache);

        if (!is_object($imap) || $imap->get_state() != 'authenticated') {
            Hm_Msgs('ERRUnable to connect to the selected IMAP server');
            return;
        }
        $new_folder = prep_folder_name($imap, $form['folder'], true);
        if (!$new_folder || !$imap->select_mailbox($new_folder)) {
            Hm_Msgs('ERRSelected folder not found');
            return;
        }
        $specials = $this->user_config->get('special_imap_folders', array());
        if (!array_key_exists($form['imap_server_id'], $specials)) {
            $specials[$form['imap_server_id']] = array('sent' => '', 'draft' => '', 'trash' => '', 'archive' => '');
        }
        $specials[$form['imap_server_id']][$form['special_folder_type']] = $new_folder;
        $this->user_config->set('special_imap_folders', $specials);

        Hm_Msgs::add('Special folder assigned');

        $this->session->record_unsaved('Special folder assigned');
        $this->out('imap_special_name', $new_folder);
    }
}

/**
 * @subpackage imap_folders/handler
 */
class Hm_Handler_process_accept_special_folders extends Hm_Handler_Module {
    public function process() {
        list($success, $form) = $this->process_form(array('imap_server_id', 'imap_service_name'));
        if ($success) {            
            $cache = Hm_IMAP_List::get_cache($this->cache, $form['imap_server_id']);
            $imap = Hm_IMAP_List::connect($form['imap_server_id'], $cache);

            if (!is_object($imap) || $imap->get_state() != 'authenticated') {
                Hm_Msgs('ERRUnable to connect to the selected IMAP server');
                return;
            }

            $specials = $this->user_config->get('special_imap_folders', array());
            if ($exposed = $imap->get_special_use_mailboxes()) {
                $specials[$form['imap_server_id']] = array('sent' => $exposed['sent'], 'draft' => '', 'trash' => $exposed['trash'], 'archive' => '');
            } else if ($form['imap_service_name'] == 'gandi') {
                $specials[$form['imap_server_id']] = array('sent' => 'Sent', 'draft' => 'Drafts', 'trash' => 'Trash', 'archive' => 'Archive');
            } else {
                $specials[$form['imap_server_id']] = array('sent' => '', 'draft' => '', 'trash' => '', 'archive' => '');
            }     
            $this->user_config->set('special_imap_folders', $specials);

            $user_data = $this->user_config->dump();
            $this->session->set('user_data', $user_data);
            
            Hm_Msgs::add('Special folders assigned');
            $this->session->record_unsaved('Special folders assigned');
            $this->session->close_early();
        }
    }
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
            if (array_key_exists('parent', $this->request->post) && trim($this->request->post['parent'])) {
                $parent_str = decode_folder_str($this->request->post['parent']);
            }
            $cache = Hm_IMAP_List::get_cache($this->cache, $form['imap_server_id']);
            $imap = Hm_IMAP_List::connect($form['imap_server_id'], $cache);
            if (is_object($imap) && $imap->get_state() == 'authenticated') {
                $new_folder = prep_folder_name($imap, $form['folder'], false, $parent_str);
                if ($new_folder && $imap->create_mailbox($new_folder)) {
                    Hm_Msgs::add('Folder created');
                    $this->cache->del('imap_folders_imap_'.$form['imap_server_id'].'_');
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
            $cache = Hm_IMAP_List::get_cache($this->cache, $form['imap_server_id']);
            $imap = Hm_IMAP_List::connect($form['imap_server_id'], $cache);
            $parent_str = false;
            if (array_key_exists('parent', $this->request->post)) {
                $parent_str = $this->request->post['parent'];
            }
            if (is_object($imap) && $imap->get_state() == 'authenticated') {
                $old_folder = prep_folder_name($imap, $form['folder'], true);
                $new_folder = prep_folder_name($imap, $form['new_folder'], false, $parent_str);
                if ($new_folder && $old_folder && $imap->rename_mailbox($old_folder, $new_folder)) {
                    if ($this->module_is_supported('sievefilters') && $this->user_config->get('enable_sieve_filter_setting', true)) {
                        $imap_servers = $this->user_config->get('imap_servers');
                        $imap_account = $imap_servers[$form['imap_server_id']];
                        $linked_mailboxes = get_sieve_linked_mailbox($imap_account, $this);
                        if ($linked_mailboxes && in_array($old_folder, $linked_mailboxes)) {
                            require_once VENDOR_PATH.'autoload.php';
                            $sieve_options = explode(':', $imap_account['sieve_config_host']);
                            $client = new \PhpSieveManager\ManageSieve\Client($sieve_options[0], $sieve_options[1]);
                            $client->connect($imap_account['user'], $imap_account['pass'], false, "", "PLAIN");
                            $script_names = array_filter(
                                $linked_mailboxes,
                                function ($value) use($old_folder) { 
                                    return $value == $old_folder;
                                }
                            );
                            $script_names = array_keys($script_names);
                            foreach ($script_names as $script_name) {
                                $script_parsed = $client->getScript($script_name);
                                $script_parsed = str_replace('"'.$old_folder.'"', '"'.$new_folder.'"', $script_parsed);
                                
                                $old_actions = base64_decode(preg_split('#\r?\n#', $script_parsed, 0)[2]);
                                $new_actions = base64_encode(str_replace('"'.$old_folder.'"', '"'.$new_folder.'"', $old_actions));
                                $script_parsed = str_replace(base64_encode($old_actions), $new_actions, $script_parsed);
                                $client->removeScripts($script_name);
                                $client->putScript(
                                    $script_name,
                                    $script_parsed
                                );
                            }
                            $client->close();
                            Hm_Msgs::add('This folder is used in one or many filters, and it will be renamed as well');
                        }
                    }
                    Hm_Msgs::add('Folder renamed');
                    $this->cache->del('imap_folders_imap_'.$form['imap_server_id'].'_');
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
            $cache = Hm_IMAP_List::get_cache($this->cache, $form['imap_server_id']);
            $imap = Hm_IMAP_List::connect($form['imap_server_id'], $cache);
            if (is_object($imap) && $imap->get_state() == 'authenticated') {
                $del_folder = prep_folder_name($imap, $form['folder'], true);
                if ($this->module_is_supported('sievefilters') && $this->user_config->get('enable_sieve_filter_setting', true)) {
                    if (is_mailbox_linked_with_filters($del_folder, $form['imap_server_id'], $this)) {
                        Hm_Msgs::add('ERRThis folder can\'t be deleted because it is used in a filter.');
                        return;
                    }   
                }
                if ($del_folder && $imap->delete_mailbox($del_folder)) {
                    Hm_Msgs::add('Folder deleted');
                    $this->cache->del('imap_folders_imap_'.$form['imap_server_id'].'_');
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
class Hm_Handler_special_folders extends Hm_Handler_Module {
    public function process() {
        if (array_key_exists('imap_server_id', $this->request->get)) {
            $specials = $this->user_config->get('special_imap_folders', array());
            if (array_key_exists($this->request->get['imap_server_id'], $specials)) {
                $this->out('sent_folder', $specials[$this->request->get['imap_server_id']]['sent']);
                $this->out('trash_folder', $specials[$this->request->get['imap_server_id']]['trash']);
                $this->out('archive_folder', $specials[$this->request->get['imap_server_id']]['archive']);
                $this->out('draft_folder', $specials[$this->request->get['imap_server_id']]['draft']);
            }
        }
        else {
            $this->out('special_imap_folders', $this->user_config->get('special_imap_folders'));
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
        $server_id = $this->get('folder_server', -1);
        $res = '<div class="folders_page"><form method="get">';
        $res .= '<input type="hidden" name="page" value="folders" />';
        $res .= '<select id="imap_server_folder" name="imap_server_id">';
        $res .= '<option ';
        if ($server_id == -1) {
            $res .= 'selected="selected" ';
        }
        $res .= 'value="">'.$this->trans('Select an IMAP server').'</option>';
        foreach ($this->get('imap_servers', array()) as $id => $server) {
            $res .= '<option ';
            if ($server_id == $id) {
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
class Hm_Output_folders_sent_dialog extends Hm_Output_Module {
    protected function output() {
        if ($this->get('folder_server') === NULL) {
            return;
        }
        $sent_folder = $this->get('sent_folder', $this->trans('Not set'));
        if (!$sent_folder) {
            $sent_folder = $this->trans('Not set');
        }
        $res = '<div data-target=".sent_folder_dialog" class="settings_subtitle">'.$this->trans('Sent Folder');
        $res .= ':<span id="sent_val">'.$sent_folder.'</span></div>';
        $res .= '<div class="folder_dialog sent_folder_dialog">';
        $res .= '<div class="folder_row">';
        $res .= '<div class="sp_description">'.$this->trans('If set, a copy of outbound mail sent with a profile '.
            'tied to this IMAP account, will be saved in this folder').'</div>';
        $res .= '</div><div class="folder_row"><a href="#" class="select_sent_folder">';
        $res .= $this->trans('Select Folder').'</a>: <span class="selected_sent"></span></div>';
        $res .= '<ul class="folders sent_folder_select"><li class="sent_title"><a href="#" class="close">';
        $res .= $this->trans('Cancel').'</a></li></ul>';
        $res .= '<input type="hidden" value="" id="sent_source" />';
        $res .= ' <input type="button" id="set_sent_folder" value="'.$this->trans('Update').'" /> ';
        $res .= ' <input type="button" id="clear_sent_folder" value="'.$this->trans('Remove').'" /><br /><br />';
        $res .= '</div>';
        return $res;
    }
}

/**
 * @subpackage imap_folders/output
 */
class Hm_Output_folders_archive_dialog extends Hm_Output_Module {
    protected function output() {
        if ($this->get('folder_server') === NULL) {
            return;
        }
        $archive_folder = $this->get('archive_folder', $this->trans('Not set'));
        if (!$archive_folder) {
            $archive_folder = $this->trans('Not set');
        }
        $res = '<div data-target=".archive_folder_dialog" class="settings_subtitle">'.$this->trans('Archive Folder');
        $res .= ':<span id="archive_val">'.$archive_folder.'</span></div>';
        $res .= '<div class="folder_dialog archive_folder_dialog">';
        $res .= '<div class="folder_row">';
        $res .= '<div class="sp_description">'.$this->trans('If set, archived messages for this account will be moved to this folder').'</div>';
        $res .= '</div><div class="folder_row"><a href="#" class="select_archive_folder">';
        $res .= $this->trans('Select Folder').'</a>: <span class="selected_archive"></span></div>';
        $res .= '<ul class="folders archive_folder_select"><li class="archive_title"><a href="#" class="close">';
        $res .= $this->trans('Cancel').'</a></li></ul>';
        $res .= '<input type="hidden" value="" id="archive_source" />';
        $res .= ' <input type="button" id="set_archive_folder" value="'.$this->trans('Update').'" /> ';
        $res .= ' <input type="button" id="clear_archive_folder" value="'.$this->trans('Remove').'" /><br /><br />';
        $res .= '</div>';
        return $res;
    }
}

/**
 * @subpackage imap_folders/output
 */
class Hm_Output_folders_draft_dialog extends Hm_Output_Module {
    protected function output() {
        if ($this->get('folder_server') === NULL) {
            return;
        }
        $draft_folder = $this->get('draft_folder', $this->trans('Not set'));
        if (!$draft_folder) {
            $draft_folder = $this->trans('Not set');
        }
        $res = '<div data-target=".draft_folder_dialog" class="settings_subtitle">'.$this->trans('Draft Folder');
        $res .= ':<span id="draft_val">'.$draft_folder.'</span></div>';
        $res .= '<div class="folder_dialog draft_folder_dialog">';
        $res .= '<div class="folder_row">';
        $res .= '<div class="sp_description">'.$this->trans('If set, drafts will be saved in this folder').'</div>';
        $res .= '</div><div class="folder_row"><a href="#" class="select_draft_folder">';
        $res .= $this->trans('Select Folder').'</a>: <span class="selected_draft"></span></div>';
        $res .= '<ul class="folders draft_folder_select"><li class="draft_title"><a href="#" class="close">';
        $res .= $this->trans('Cancel').'</a></li></ul>';
        $res .= '<input type="hidden" value="" id="draft_source" />';
        $res .= ' <input type="button" id="set_draft_folder" value="'.$this->trans('Update').'" /> ';
        $res .= ' <input type="button" id="clear_draft_folder" value="'.$this->trans('Remove').'" /><br /><br />';
        $res .= '</div>';
        return $res;
    }
}

/**
 * @subpackage imap_folders/output
 */
class Hm_Output_folders_trash_dialog extends Hm_Output_Module {
    protected function output() {
        if ($this->get('folder_server') === NULL) {
            return;
        }
        $trash_folder = $this->get('trash_folder', $this->trans('Not set'));
        if (!$trash_folder) {
            $trash_folder = $this->trans('Not set');
        }
        $res = '<div data-target=".trash_folder_dialog" class="settings_subtitle">'.$this->trans('Trash Folder');
        $res .= ':<span id="trash_val">'.$trash_folder.'</span></div>';
        $res .= '<input type="hidden" id="not_set_string" value="'.$this->trans('Not set').'" />';
        $res .= '<div class="folder_dialog trash_folder_dialog">';
        $res .= '<div class="sp_description">'.$this->trans('If set, deleted messages for this account '.
            'will be moved to this folder').'</div>';
        $res .= '<div class="folder_row"><a href="#" class="select_trash_folder">';
        $res .= $this->trans('Select Folder').'</a>: <span class="selected_trash"></span></div>';
        $res .= '<ul class="folders trash_folder_select"><li class="trash_title"><a href="#" class="close">';
        $res .= $this->trans('Cancel').'</a></li></ul>';
        $res .= '<input type="hidden" value="" id="trash_source" />';
        $res .= ' <input type="button" id="set_trash_folder" value="'.$this->trans('Update').'" /> ';
        $res .= ' <input type="button" id="clear_trash_folder" value="'.$this->trans('Remove').'" /><br /><br />';
        $res .= '</div>';
        return $res;
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

if (!hm_exists('get_sieve_linked_mailbox')) {
    function get_sieve_linked_mailbox ($imap_account, $module) {
        if (!$module->module_is_supported('sievefilters') && $module->user_config->get('enable_sieve_filter_setting', true)) {
            return;
        }
        require_once VENDOR_PATH.'autoload.php';
        $sieve_options = explode(':', $imap_account['sieve_config_host']);
        $client = new \PhpSieveManager\ManageSieve\Client($sieve_options[0], $sieve_options[1]);
        $client->connect($imap_account['user'], $imap_account['pass'], false, "", "PLAIN");
        $scripts = $client->listScripts();
        $folders = [];
        foreach ($scripts as $s) {
            $script = $client->getScript($s);
            $base64_obj = str_replace("# ", "", preg_split('#\r?\n#', $script, 0)[2]);
            $obj = json_decode(base64_decode($base64_obj))[0];
            if ($obj && in_array($obj->action, ['copy', 'move'])) {
                $folders[$s] = $obj->value;
            }
        }
        $client->close();
        return $folders;
    }
}

if (!hm_exists('is_mailbox_linked_with_filters')) {
    function is_mailbox_linked_with_filters ($mailbox, $imap_server_id, $module) {
        $imap_servers = $module->user_config->get('imap_servers');
        $imap_account = $imap_servers[$imap_server_id];
        if (isset($imap_account['sieve_config_host'])) {
            $linked_mailboxes = get_sieve_linked_mailbox($imap_account, $module);
            return in_array($mailbox, $linked_mailboxes);
        }
        return false;  
    }
}
