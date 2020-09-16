<?php

/**
 * Saved search modules
 * @package modules
 * @subpackage savedsearches
 */

if (!defined('DEBUG_MODE')) { die(); }

 /**
 * @subpackage savedsearches/handler
 */
class Hm_Handler_save_searches_data extends Hm_Handler_Module {
    public function process() {
        $name = array_key_exists('search_name', $this->request->get) ? $this->request->get['search_name'] : '';
        $searches = new Hm_Saved_Searches($this->user_config->get('saved_searches', array()));
        $params = $name ? $searches->get($name, array()) : array();
        $url_search = get_search_from_url($this->request);
        $diff = array_diff_assoc($params, $url_search);
        if (count($diff) > 0) {
            $this->out('search_param_update', true);
        }
        $this->out('search_name', $name);
        $this->out('search_params', $params);
    }
}

 /**
 * @subpackage savedsearches/handler
 */
class Hm_Handler_saved_search_folder_data extends Hm_Handler_Module {
    public function process() {
        $this->out('saved_searches', $this->user_config->get('saved_searches', array()));
    }
}

 /**
 * @subpackage savedsearches/handler
 */
class Hm_Handler_update_search extends Hm_Handler_Module {
    public function process() {
        list($success, $form) = $this->process_form(array('search_name'));
        if ($success) {
            $data = get_search_from_post($this->request);
            $searches = new Hm_Saved_Searches($this->user_config->get('saved_searches', array()));
            if ($searches->update($form['search_name'], $data)) {
                $this->session->record_unsaved('Updated a search');
                $this->user_config->set('saved_searches', $searches->dump());
                $this->session->set('user_data', $this->user_config->dump());
                $this->out('updated_search', true);
                Hm_Msgs::add('Saved search updated');
            }
            else {
                Hm_Msgs::add('ERRUnable to update the search paramaters');
            }
        }
    }
}

 /**
 * @subpackage savedsearches/handler
 */
class Hm_Handler_delete_search extends Hm_Handler_Module {
    public function process() {
        list($success, $form) = $this->process_form(array('search_name'));
        if ($success) {
            $searches = new Hm_Saved_Searches($this->user_config->get('saved_searches', array()));
            if ($searches->delete($form['search_name'])) {
                $this->session->record_unsaved('Deleted a search');
                $this->user_config->set('saved_searches', $searches->dump());
                $this->session->set('user_data', $this->user_config->dump());
                $this->out('deleted_search', true);
            }
        }
    }
}


 /**
 * @subpackage savedsearches/handler
 */
class Hm_Handler_update_save_search_label extends Hm_Handler_Module {
    public function process() {
        list($success, $form) = $this->process_form(array('search_name', 'search_terms_label', 'old_search_terms_label'));
        if ($success) {
            $searches = new Hm_Saved_Searches($this->user_config->get('saved_searches', array()));
            if ($searches->rename($form['old_search_terms_label'], $form['search_terms_label'])) {
                $this->session->record_unsaved('Update a saved search label');
                $this->user_config->set('saved_searches', $searches->dump());
                $this->session->set('user_data', $this->user_config->dump());
                $this->out('new_saved_search_label', $form['search_terms_label']);
                $this->out('update_save_search_label', true);
                Hm_Msgs::add('Saved search label updated');
            }
        }
    }
}

 /**
 * @subpackage savedsearches/handler
 */
class Hm_Handler_save_search extends Hm_Handler_Module {
    public function process() {
        list($success, $form) = $this->process_form(array('search_name'));
        if ($success) {
            $data = get_search_from_post($this->request);
            $searches = new Hm_Saved_Searches($this->user_config->get('saved_searches', array()));
            if ($searches->add($form['search_name'], $data)) {
                $this->session->record_unsaved('Saved a search');
                $this->user_config->set('saved_searches', $searches->dump());
                $this->session->set('user_data', $this->user_config->dump());
                $this->out('saved_search', true);
            }
            else {
                Hm_Msgs::add('ERRYou already have a search by that name');
            }
        }
    }
}

/**
 * @subpackage savedsearches/output
 */
class Hm_Output_search_name_fld extends Hm_Output_Module {
    protected function output() {
        $name = $this->get('search_name', '');
        return '<input type="hidden" class="search_name" name="search_name" value="'.$this->html_safe($name).'" />';
    }
}

/**
 * @subpackage savedsearches/output
 */
class Hm_Output_filter_saved_search_result extends Hm_Output_Module {
    protected function output() {
        if ($this->get('saved_search') || $this->get('updated_search') || $this->get('deleted_search') || $this->get('update_save_search_label')) {
            $this->out('saved_search_result', 1);
        }
        else {
            $this->out('saved_search_result', 0);
        }
    }
}

/**
 * @subpackage savedsearches/output
 */
class Hm_Output_update_search_icon extends Hm_Output_Module {
    protected function output() {
        if ($this->get('search_param_update')) {
            return '<a href="" class="update_search" title="'.$this->trans('Update saved search').'"><img width="20" height="20" alt="'.
                $this->trans('Update search').'" src="'.Hm_Image_Sources::$circle_check.'" /></a>';
        }
    }
}


/**
 * @subpackage savedsearches/output
 */
class Hm_Output_update_search_label_icon extends Hm_Output_Module {
    protected function output() {
        $style = '';
        if (!$this->get('search_name')) {
            $style = 'style="display: none;"';
        }
        $res = '<a href="" class="update_search_label" title="'.$this->trans('Update saved search label').'"><img '.$style.' width="20" height="20" alt="'.
            $this->trans('Update saved search label').'" src="'.Hm_Image_Sources::$edit.'" /></a>' . update_search_label_field($this->get('search_name'), $this);
        return $res;
    }
}

/**
 * @subpackage savedsearches/output
 */
class Hm_Output_delete_search_icon extends Hm_Output_Module {
    protected function output() {
        $style = '';
        if (!$this->get('search_name')) {
            $style = 'style="display: none;"';
        }
        return '<a href="" '.$style.' class="delete_search" title="'.$this->trans('Delete saved search').'"><img width="20" height="20" alt="'.
            $this->trans('Delete search').'" src="'.Hm_Image_Sources::$circle_x.'" /></a>';
    }
}

/**
 * @subpackage savedsearches/output
 */
class Hm_Output_save_search_icon extends Hm_Output_Module {
    protected function output() {
        $name = $this->get('search_name', '');
        if (!$name) {
            return '<a style="display: none;" href="" class="save_search" title="'.$this->trans('Save search').'"><img width="20" height="20" alt="'.
                $this->trans('Save search').'" src="'.Hm_Image_Sources::$circle_check.'" /></a>';
        }
    }
}

/**
 * @subpackage savedsearches/output
 */
class Hm_Output_save_searches_form extends Hm_Output_Module {
    protected function output() {
        $name = $this->get('search_name', '');
        if (!$name) {
            return '<form style="display: none;" class="saved_searches_form"><input type="text" placeholder="'.$this->trans('Search Name').
                '" class="new_search_name" name="search_name" value="" />'.
                '<input type="submit" class="save_search" value="'.$this->trans('Save').'" /></form>';
        }
    }
}

/**
 * @subpackage savedsearches/output
 */
class Hm_Output_search_folders extends Hm_Output_Module {
    protected function output() {
        $res = '';
        $details = $this->get('saved_searches', array());
        if (!empty($details)) {
            foreach ($details as $name => $args) {
                $url = sprintf('?page=search&amp;search_terms=%s&amp;search_fld=%s&amp;search_since=%s&amp;search_name=%s',
                    $this->html_safe(urlencode($args[0])),
                    $this->html_safe(urlencode($args[2])),
                    $this->html_safe(urlencode($args[1])),
                    $this->html_safe(urlencode($name))
                );
                $res .= '<li class="menu_search_'.$this->html_safe($name).'"><a class="unread_link" href="'.$url.'">';
                if (!$this->get('hide_folder_icons')) {
                    $res .= '<img class="account_icon" src="'.$this->html_safe(Hm_Image_Sources::$search).'" alt="" width="16" height="16" /> ';
                }
                $res .= $this->html_safe($name).'</a></li>';
            }
            $this->append('folder_sources', array('search_folders', $res));
        }
    }
}

/**
 * @subpackage savedsearches/lib
 */
class Hm_Saved_Searches {

    private $searches;

    public function __construct($data) {
        $this->searches = $data;
    }
    public function dump() {
        ksort($this->searches);
        return $this->searches;
    }
    public function update($name, $search) {
        if (array_key_exists($name, $this->searches)) {
            $this->searches[$name] = $search;
            return true;
        }
        return false;
    }
    public function add($name, $search) {
        if (!array_key_exists($name, $this->searches)) {
            $this->searches[$name] = $search;
            return true;
        }
        return false;
    }
    public function get($name, $default=false) {
        if (array_key_exists($name, $this->searches)) {
            return $this->searches[$name];
        }
        return $default;
    }
    public function delete($del_name) {
        $new_searches = array();
        $old_searches = $this->searches;
        foreach ($old_searches as $name => $vals) {
            if ($name !== $del_name) {
                $new_searches[$name] = $vals;
            }
        }
        $this->searches = $new_searches;
        return count($new_searches) !== count($old_searches);
    }
    public function rename($old_name, $new_name) {
        if(array_key_exists($old_name, $this->searches)) {
            $this->searches[$new_name] = $this->searches[$old_name];
            unset($this->searches[$old_name]);
            return true;
        }
        return false;
    }
}

/**
 * @subpackage savedsearches/functions
 */
if (!hm_exists('get_search_from_post')) {
function get_search_from_post($request) {
    return array(
        array_key_exists('search_terms', $request->post) ? $request->post['search_terms'] : '',
        array_key_exists('search_since', $request->post) ? $request->post['search_since'] : DEFAULT_SINCE,
        array_key_exists('search_fld', $request->post) ? $request->post['search_fld'] : DEFAULT_SEARCH_FLD,
        array_key_exists('search_name', $request->post) ? $request->post['search_name'] : '',
    );
}}

/**
 * @subpackage savedsearches/functions
 */
if (!hm_exists('get_search_from_url')) {
function get_search_from_url($request) {
    return array(
        array_key_exists('search_terms', $request->get) ? $request->get['search_terms'] : '',
        array_key_exists('search_since', $request->get) ? $request->get['search_since'] : DEFAULT_SINCE,
        array_key_exists('search_fld', $request->get) ? $request->get['search_fld'] : DEFAULT_SEARCH_FLD,
        array_key_exists('search_name', $request->get) ? $request->get['search_name'] : '',
    );
}}

