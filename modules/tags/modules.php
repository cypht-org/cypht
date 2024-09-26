<?php

/**
 * Tags modules
 * @package modules
 * @subpackage tags
 */

if (!defined('DEBUG_MODE')) { die(); }

require_once APP_PATH . 'modules/tags/functions.php';
require_once APP_PATH . 'modules/tags/hm-tags.php';

/**
 * @subpackage tags/handler
 */
class Hm_Handler_mod_env extends Hm_Handler_Module {
    public function process() {
        $this->out('mod_support', array_filter(array(
            $this->module_is_supported('imap') ? 'imap' : false,
            $this->module_is_supported('feeds') ? 'feeds' : false,
            $this->module_is_supported('github') ? 'github' : false,
            $this->module_is_supported('wordpress') ? 'wordpress' : false
        )));
    }
}

/**
 * @subpackage tags/handler
 */
class Hm_Handler_tag_edit_data extends Hm_Handler_Module {
    public function process() {
        $id = false;
        if (array_key_exists('tag_id', $this->request->get)) {
            $id = $this->request->get['tag_id'];
        }
        $folders = $this->get('tags');
        $folder = null;
        foreach ($folders as $f) {
            if ($f['id'] === $id) {
                $folder = $f;
            }
        }
        if ($id !== false) {
            $this->out('edit_tag', $folder);
            $this->out('edit_tag_id', $id);
        }
        else {
            $this->out('new_tag_id', count($folders));
        }
    }
}
/**
 * @subpackage tags/handler
 */
class Hm_Handler_process_tag_update extends Hm_Handler_Module {
    public function process() {
        if (array_key_exists('tag_delete', $this->request->post)) {
            return;
        }
        list($success, $form) = $this->process_form(array('tag_name','parent_tag','tag_id'));// 'tag_id', parent_tag
        if (!$success) {
            return;
        }
        $tag = array(
            'name' => html_entity_decode($form['tag_name'], ENT_QUOTES),
            'parent' => $form['parent_tag'] ?? null
        );
        if (!is_null($form['tag_id']) AND Hm_Tags::get($form['tag_id'])) {
            $tag['id'] = $form['tag_id'];
            Hm_Tags::edit($form['tag_id'], $tag);
            Hm_Msgs::add('Tag Edited');
        } else {
            Hm_Tags::add($tag);
            Hm_Msgs::add('Tag Created');
        }
    }
}

/**
 * @subpackage profile/handler
 */
class Hm_Handler_process_tag_delete extends Hm_Handler_Module {
    public function process() {
        list($success, $form) = $this->process_form(array('tag_delete', 'tag_id'));
        if (!$success) {
            return;
        }
        if (($tag = Hm_Tags::get($form['tag_id']))) {
            Hm_Tags::del($tag['id']);
            Hm_Msgs::add('Tag Deleted');
        } else {
            Hm_Msgs::add('ERRTag ID not found');
            return;
        }
    }
}

/**
 * @subpackage tags/handler
 */
class Hm_Handler_imap_tag_content extends Hm_Handler_Module {
    public function process() {
        list($success, $form) = $this->process_form(array('imap_server_ids'));
        if ($success) {
            $limit = $this->user_config->get('tag_per_source_setting', DEFAULT_TAGS_PER_SOURCE);
            $date = process_since_argument($this->user_config->get('tag_since_setting', DEFAULT_TAGS_SINCE));
            $tag_id = $this->request->get['tag_id'];
            $ids = explode(',', $form['imap_server_ids']);
            $folder = bin2hex('INBOX');
            if (array_key_exists('folder', $this->request->post)) {
                $folder = $this->request->post['folder'];
            }
            list($status, $msg_list) = merge_imap_search_results($ids, 'ALL', $this->session, $this->cache, array(hex2bin($folder)), $limit, array(array('SINCE', $date), array('HEADER X-Cypht-Tags', $tag_id)));
            $this->out('folder_status', $status);
            $this->out('imap_tag_data', $msg_list);
            $this->out('imap_server_ids', $form['imap_server_ids']);
        }
    }
}

/**
 * Process "tag_per_source" setting for the tag page in the settings page
 * @subpackage core/handler
 */
class Hm_Handler_process_tag_source_max_setting extends Hm_Handler_Module {
    /**
     * Allowed values are greater than zero and less than MAX_PER_SOURCE
     */
    public function process() {
        process_site_setting('tag_per_source', $this, 'max_source_setting_callback', DEFAULT_TAGS_PER_SOURCE);
    }
}

class Hm_Handler_tag_data extends Hm_Handler_Module {
    public function process() {
        Hm_Tags::init($this);
        $this->out('tags', Hm_Tags::getAll());
    }
}

class Hm_Output_filter_tag_data extends Hm_Output_Module {
    /**
     * Build ajax response for the tag message list
     */
    protected function output() {
        if ($this->get('imap_tag_data')) {
            prepare_imap_message_list($this->get('imap_tag_data'), $this, 'tag');
        }
        elseif (!$this->get('formatted_message_list')) {
            $this->out('formatted_message_list', array());
        }
    }
}

/**
 * Option for the maximum number of messages per source for the Junk page
 * @subpackage core/output
 */
class Hm_Output_tag_per_source_setting extends Hm_Output_Module {
    /**
     * Processed by Hm_Handler_process_tag_source_max_setting
     */
    protected function output() {
        $sources = DEFAULT_TAGS_PER_SOURCE;
        $settings = $this->get('user_settings', array());
        $reset = '';
        if (array_key_exists('tag_per_source', $settings)) {
            $sources = $settings['tag_per_source'];
        }
        if ($sources != DEFAULT_TAGS_PER_SOURCE) {
            $reset = '<span class="tooltip_restore" restore_aria_label="Restore default value"><i class="bi bi-arrow-repeat refresh_list reset_default_value_input"></i></span>';
        }
        return '<tr class="tag_setting"><td><label for="tag_per_source">'.
            $this->trans('Max messages per source').'</label></td>'.
            '<td class="d-flex"><input type="text" size="2" class="form-control form-control-sm w-auto" id="tag_per_source" name="tag_per_source" value="'.$this->html_safe($sources).'" data-default-value="'.DEFAULT_TAGS_PER_SOURCE.'"/>'.$reset.'</td></tr>';
    }
}

/**
 * Starts the Tag section on the settings page
 * @subpackage core/output
 */
class Hm_Output_start_tag_settings extends Hm_Output_Module {
    /**
     * Settings in this section control the tag messages view
     */
    protected function output() {
        $res = '<tr><td data-target=".tag_setting" colspan="2" class="settings_subtitle cursor-pointer border-bottom p-2">'.
            '<i class="bi bi-tags fs-5 me-2"></i>'.
            $this->trans('Tags').'</td></tr>';
            print_r($res);
        return $res;
    }
}

/**
 * Process "since" setting for the junk page in the settings page
 * @subpackage core/handler
 */
class Hm_Handler_process_tag_since_setting extends Hm_Handler_Module {
    /**
     * valid values are defined in the process_since_argument function
     */
    public function process() {
        process_site_setting('tag_since', $this, 'since_setting_callback');
    }
}

/**
 * Option for the "junk since" date range for the Junk page
 * @subpackage core/output
 */
class Hm_Output_tag_since_setting extends Hm_Output_Module {
    /**
     * Processed by Hm_Handler_process_tag_since_setting
     */
    protected function output() {
        $since = DEFAULT_TAGS_SINCE;
        $settings = $this->get('user_settings', array());
        if (array_key_exists('tag_since', $settings) && $settings['tag_since']) {
            $since = $settings['tag_since'];
        }
        return '<tr class="tag_setting"><td><label for="tag_since">'.
            $this->trans('Show junk messages since').'</label></td>'.
            '<td>'.message_since_dropdown($since, 'tag_since', $this, DEFAULT_TAGS_SINCE).'</td></tr>';
    }
}

class Hm_Output_tags_heading extends Hm_Output_Module {
    /**
     */
    protected function output() {
        return '<div class="content_title">'.$this->trans('Tags').'</div>';
    }
}
class Hm_Output_tags_tree extends Hm_Output_Module {
    /**
     */
    protected function output() {
        if ($this->format == 'HTML5') {
            $folders = $this->get('tags', array());
            $tag = $this->get('edit_tag');
            $id = $this->get('edit_tag_id');
            
            // Organize folders into a tree structure
            $folderTree = [];
            foreach ($folders as $folderId => $folder) {
                if (isset($folder['parent']) && $folder['parent']) {
                    $folders[$folder['parent']]['children'][$folderId] = &$folders[$folderId];
                } else {
                    $folderTree[$folderId] = &$folders[$folderId];
                }
            }               
        
            // Generate the tree view HTML
            $treeViewHtml = generate_tree_view($folderTree, $this->html_safe(Hm_Request_Key::generate()));
            $treeContent = count($folderTree) > 0 ? $treeViewHtml : '<p>'. $this->trans('No tags available yet.') .'</p>';
            return '<div class="tags_tree mt-3 col-lg-8 col-md-8 col-sm-12">
                    <div class="card m-3 mr-0">
                        <div class="card-body">
                            <div class="tree-view">
                                ' . $treeContent . '
                            </div>
                        </div>
                    </div>
                </div>';
        
        }
    }
}

class Hm_Output_tags_form extends Hm_Output_Module {
    /**
     */
    protected function output() {
        if ($this->format == 'HTML5') {
        $count = count($this->get('tags', array()));
        $count = sprintf($this->trans('%d configured'), $count);
        $tag = $this->get('edit_tag', null);
        $id = $this->get('edit_tag_id', null);
        $parent_tag = !empty($tag) ? $tag['parent'] : null;
        $options = '';
        
        foreach ($this->get('tags', array()) as $index => $folder) {
            $option_selected = !is_null($id) && $folder['id'] === $parent_tag ? 'selected' : '';
            $options .= '<option '. $option_selected.' value="'.$this->html_safe($folder['id']).'">'.$this->html_safe($folder['name']).'</option>';
        }
        $form_title = !is_null($id) ? $this->trans('Edit tag/label').': '.$tag['name'] : $this->trans('Add an tag/label');
        return '<div class="tags_tree mt-3 col-lg-4 col-md-4 col-sm-12">
                    <div class="card m-4">
                        <div class="card-body">
                            <form class="add_tag me-0" method="POST" action="?page=tags">
                                <input type="hidden" name="hm_page_key" id="hm_page_key" value="'.$this->html_safe(Hm_Request_Key::generate()).'" />

                                <div class="subtitle mt-4">'.$form_title.'</div>

                                <div class="form-floating mb-3">
                                    <input type="hidden" id="hm_ajax_hook" name="hm_ajax_hook" class="txt_fld form-control" value="ajax_process_tag_update">
                                    <input type="hidden" id="tag_id" name="tag_id" class="txt_fld form-control" value="'.$this->html_safe($id).'" placeholder="'.$this->trans('Tag id').'">
                                    <input required type="text" id="tag_name" name="tag_name" class="txt_fld form-control" value="'.$this->html_safe($tag['name']).'" placeholder="'.$this->trans('Tag name').'">
                                    <label class="" for="tag_name">'.$this->trans('Tag name').'</label>
                                </div>
                                <div class="form-floating mb-3">
                                    <select id="parent_tag" name="parent_tag" class="form-select form-select-lg mb-3" aria-label="">
                                        <option value="" selected>None</option>
                                        '.$options.'
                                    </select>
                                    <label class="" for="parent_tag">'.$this->trans('Parent Tag name').'</label>
                                </div>

                                <input type="submit" class="btn btn-primary px-5" value="'.$this->trans('Add').'" name="submit_tag" />
                            </form>
                        </div>
                    </div>
                </div>';
        }
    }
}

/**
 * @subpackage tags/output
 */
class Hm_Output_tags extends hm_output_module {
    protected function output() {
        $res = '';
        $folders = $this->get('tags', array());
        if (is_array($folders) && !empty($folders)) {
            if(count($this->get('tags', array()))  > 1) {
                $res .= '<li class="menu_tags"><a class="all_tags" href="?page=tags">';
                if (!$this->get('hide_folder_icons')) {
                    $res .= '<i class="bi bi-tags fs-5 me-2"></i>';
                }
                $res .= $this->trans('All');
                $res .= '<span class="tags_count">('.count($folders).')</span></a></li>';
            }
            $folderTree = [];
            foreach ($folders as $folderId => $folder) {
                if (isset($folder['parent']) && $folder['parent']) {
                    $folders[$folder['parent']]['children'][$folderId] = &$folders[$folderId];
                } else {
                    $folderTree[$folderId] = &$folders[$folderId];
                }
            }
            foreach ($folderTree as $id => $folder) {
                $hasChild = isset($folder['children']) && !empty($folder['children']);
                $res .= '<li class="tags_'.$this->html_safe($id).'">';
                if (!$this->get('hide_folder_icons')) {
                    $res .= $hasChild ? '<i class="bi bi-caret-down"></i>' : '<i class="bi bi-tags fs-5 me-2"></i>';
                }
                $res .= '<a data-id="tag_'.$this->html_safe($id).'" href="?page=message_list&list_path=tag&tag_id='.$this->html_safe($id).'">';
                $res .= $this->html_safe($folder['name']).'</a>';
                if($hasChild) {
                    $res .= '<ul>';
                    foreach ($folder['children'] as $key => $child) {
                        $res .= '<li class="tag_'.$this->html_safe($child['id']).'">';
                        $res .= '<a data-id="tag_'.$this->html_safe($child['id']).'" href="?page=message_list&list_path=tag&tag_id='.$this->html_safe($child['id']).'">';
                        $res .= $this->html_safe($folder['name']).'</a>';
                        $res .= '</li>';
                    }
                    $res .= '</ul>';
                }
                $res .= '</li>';
            }
        }
        $res .= '<li class="tags_add_new"><a href="?page=tags">';
        if (!$this->get('hide_folder_icons')) {
            $res .= '<i class="bi bi-plus-square fs-5 me-2"></i>';
        }
        $res .= $this->trans('Add label').'</a></li>';
        $this->append('folder_sources', array('tags_folders', $res));
    }
}

/**
 * @subpackage tags/output
 */
class Hm_Output_tag_bar extends hm_output_module {
    protected function output() {
        $headers = $this->get('msg_headers');
        if (is_string($headers)) {
            $this->out('msg_headers', $headers.'<i class="bi bi-tags-fill fs-4 tag_icon refresh_list"></i>');
        }
    }
}

class Hm_Output_display_configured_tags extends Hm_Output_Module {
    protected function output() {
        $res = '';
        if ($this->format == 'HTML5') {
            foreach ($this->get('feeds', array()) as $index => $vals) {
                $res .= '<div class="configured_server col-12 col-lg-4 mb-2"><div class="card card-body">';
                $res .= sprintf('<div class="server_title"><b>%s</b></div><div title="%s" class="server_subtitle">%s</div>',
                    $this->html_safe($vals['name']), $this->html_safe($vals['server']), $this->html_safe($vals['server']));
                $res .= '<form class="feed_connect d-flex gap-2" method="POST">';
                $res .= '<input type="hidden" name="feed_id" value="'.$this->html_safe($index).'" />';
                $res .= '<input type="submit" value="'.$this->trans('Test').'" class="test_feed_connect btn btn-primary btn-sm" />';
                $res .= '<input type="submit" value="'.$this->trans('Delete').'" class="feed_delete btn btn-outline-danger btn-sm" />';
                $res .= '<input type="hidden" value="ajax_feed_debug" name="hm_ajax_hook" />';
                $res .= '</form></div></div>';
            }
            $res .= '<br class="clear_float" /></div></div>';
        }
        return $res;
    }
}
