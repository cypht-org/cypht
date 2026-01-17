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
            if (!$searches->is_advanced($form['search_name']) && $searches->update($form['search_name'], $data)) {
                $this->session->record_unsaved('Updated a search');
                $this->user_config->set('saved_searches', $searches->dump());
                $this->session->set('user_data', $this->user_config->dump());
                $this->out('updated_search', true);
                if (isPageConfigured('save')) {
                    Hm_Msgs::add("Saved search updated. To preserve your searches after logout, please go to <a class='alert-link' href='/?page=save'>Save Settings</a>.", 'info');
                }
                else {
                    Hm_Msgs::add('Saved search updated', 'info');
                }
            }
            else {
                Hm_Msgs::add('Unable to update the search parameters', 'danger');
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
                if (isPageConfigured('save')) {
                    Hm_Msgs::add("Search saved. To preserve your searches after logout, please go to <a class='alert-link' href='/?page=save'>Save Settings</a>.", 'success');
                }
                else {
                    Hm_Msgs::add('Search saved', 'success');
                }
            }
            else {
                Hm_Msgs::add('You already have a search by that name', 'warning');
            }
        }
    }
}

/**
 * @subpackage savedsearches/handler
 */
class Hm_Handler_save_advanced_search extends Hm_Handler_Module {
    public function process() {
        list($success, $form) = $this->process_form(array('search_name'));
        if ($success) {
            $data = get_advanced_search_from_post($this->request);
            if (!$data) {
                Hm_Msgs::add('Invalid advanced search parameters', 'danger');
                return;
            }

            $searches = new Hm_Saved_Searches($this->user_config->get('saved_searches', array()));

            if (array_key_exists($form['search_name'], $searches->dump())) {
                Hm_Msgs::add('You already have a search by that name', 'warning');
                return;
            }

            if ($searches->add_advanced($form['search_name'], $data)) {
                $this->session->record_unsaved('Saved an advanced search');
                $this->user_config->set('saved_searches', $searches->dump());
                $this->session->set('user_data', $this->user_config->dump());
                $this->out('saved_advanced_search', true);
                if (isPageConfigured('save')) {
                    Hm_Msgs::add("Advanced search saved. To preserve your searches after logout, please go to <a class='alert-link' href='/?page=save'>Save Settings</a>.", 'success');
                }
                else {
                    Hm_Msgs::add('Advanced search saved', 'success');
                }
            }
            else {
                Hm_Msgs::add('Failed to save advanced search - name already exists', 'danger');
            }
        }
    }
}

/**
 * @subpackage savedsearches/handler
 */
class Hm_Handler_load_advanced_search extends Hm_Handler_Module {
    public function process() {
        list($success, $form) = $this->process_form(array('search_name'));
        if ($success) {
            $searches = new Hm_Saved_Searches($this->user_config->get('saved_searches', array()));
            $data = $searches->get_advanced($form['search_name']);
            if ($data) {
                $this->out('advanced_search_data', $data);
                $this->out('advanced_search_name', $form['search_name']);
                $this->out('loaded_advanced_search', true);
            }
            else {
                Hm_Msgs::add('Advanced search not found', 'danger');
            }
        }
    }
}

/**
 * @subpackage savedsearches/handler
 */
class Hm_Handler_update_advanced_search extends Hm_Handler_Module {
    public function process() {
        list($success, $form) = $this->process_form(array('search_name'));
        if ($success) {
            $data = get_advanced_search_from_post($this->request);
            if (!$data) {
                Hm_Msgs::add('Invalid advanced search parameters', 'danger');
                return;
            }

            $searches = new Hm_Saved_Searches($this->user_config->get('saved_searches', array()));
            if ($searches->update_advanced($form['search_name'], $data)) {
                $this->session->record_unsaved('Updated an advanced search');
                $this->user_config->set('saved_searches', $searches->dump());
                $this->session->set('user_data', $this->user_config->dump());
                $this->out('updated_advanced_search', true);
                if (isPageConfigured('save')) {
                    Hm_Msgs::add("Advanced search updated. To preserve your searches after logout, please go to <a class='alert-link' href='/?page=save'>Save Settings</a>.", 'info');
                }
                else {
                    Hm_Msgs::add('Advanced search updated', 'info');
                }
            }
            else {
                Hm_Msgs::add('Unable to update the advanced search parameters', 'danger');
            }
        }
    }
}

/**
 * @subpackage savedsearches/handler
 */
class Hm_Handler_delete_advanced_search extends Hm_Handler_Module {
    public function process() {
        list($success, $form) = $this->process_form(array('search_name'));
        if ($success) {
            $searches = new Hm_Saved_Searches($this->user_config->get('saved_searches', array()));
            if ($searches->is_advanced($form['search_name']) && $searches->delete($form['search_name'])) {
                $this->session->record_unsaved('Deleted an advanced search');
                $this->user_config->set('saved_searches', $searches->dump());
                $this->session->set('user_data', $this->user_config->dump());
                $this->out('deleted_advanced_search', true);
                Hm_Msgs::add('Advanced search deleted', 'info');
            }
            else {
                Hm_Msgs::add('Unable to delete the advanced search', 'danger');
            }
        }
    }
}

/**
 * @subpackage savedsearches/handler
 */
class Hm_Handler_advanced_search_data extends Hm_Handler_Module {
    public function process() {
        $search_name = '';

        if (array_key_exists('search_name', $this->request->get) && !empty($this->request->get['search_name'])) {
            $search_name = trim($this->request->get['search_name']);
            $searches = new Hm_Saved_Searches($this->user_config->get('saved_searches', array()));
            $search_data = $searches->get_advanced($search_name);
            if ($search_data) {
                $this->out('advanced_search_name', $search_name);
                $this->out('advanced_search_data', $search_data);
                $this->out('load_advanced_search_js', true);
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
class Hm_Output_filter_advanced_search_result extends Hm_Output_Module {
    protected function output() {
        if ($this->get('saved_advanced_search') || $this->get('updated_advanced_search') || $this->get('deleted_advanced_search') || $this->get('loaded_advanced_search')) {
            $this->out('advanced_search_result', 1);
            if ($this->get('loaded_advanced_search')) {
                $this->out('advanced_search_data', $this->get('advanced_search_data', array()));
                $this->out('advanced_search_name', $this->get('advanced_search_name', ''));
            }
        }
        else {
            $this->out('advanced_search_result', 0);
        }
    }
}

/**
 * @subpackage savedsearches/output
 */
class Hm_Output_advanced_search_data_handler extends Hm_Output_Module {
    protected function output() {
        if ($this->get('load_advanced_search_js') && $this->get('advanced_search_data')) {
            $search_data = $this->get('advanced_search_data');
            return '<script type="text/javascript">
                $(document).ready(function() {
                    if (typeof load_advanced_search_from_data === "function") {
                        load_advanced_search_from_data(' . json_encode($search_data) . ', true);
                    }
                });
            </script>';
        }
        return '';
    }
}

/**
 * @subpackage savedsearches/output
 */
class Hm_Output_advanced_search_save_icon extends Hm_Output_Module {
    protected function output() {
        $name = $this->get('advanced_search_name', '');
        if (!$name) {
            return '<div class="advanced_search_save_controls mt-3">' .
                '<button type="button" class="btn btn-primary btn-sm show_save_advanced_search" title="'.$this->trans('Save this search').'">' .
                '<i class="bi bi-bookmark-plus"></i> '.$this->trans('Save search').'</button>' .
                '<div class="save_advanced_search_form mt-2" style="display: none;">' .
                '<input type="text" class="advanced_search_name form-control form-control-sm mb-2" placeholder="'.$this->trans('Search Name').'" style="max-width: 300px;" />' .
                '<button type="button" class="btn btn-primary btn-sm save_advanced_search_btn me-2">'.$this->trans('Save').'</button>' .
                '<button type="button" class="btn btn-secondary btn-sm cancel_save_advanced_search">'.$this->trans('Cancel').'</button>' .
                '</div></div>';
        }
    }
}

/**
 * @subpackage savedsearches/output
 */
class Hm_Output_advanced_search_update_icon extends Hm_Output_Module {
    protected function output() {
        $name = $this->get('advanced_search_name', '');
        if ($name) {
            return '<div class="advanced_search_update_controls mt-3 d-inline-block">' .
                '<span class="current_search_name me-3">'.$this->trans('Current search').': <strong>'.$this->html_safe($name).'</strong></span>' .
                '<button type="button" class="btn btn-primary btn-sm update_advanced_search_btn" title="'.$this->trans('Update saved search').'">' .
                '<i class="bi bi-check-circle"></i> '.$this->trans('Update').'</button>' .
                '<input type="hidden" class="current_advanced_search_name" value="'.$this->html_safe($name).'" />' .
                '</div>';
        }
    }
}

/**
 * @subpackage savedsearches/output
 */
class Hm_Output_advanced_search_delete_icon extends Hm_Output_Module {
    protected function output() {
        $name = $this->get('advanced_search_name', '');
        if ($name) {
            return '<button type="button" class="btn btn-danger btn-sm delete_advanced_search_btn ms-2" title="'.$this->trans('Delete saved search').'">' .
                '<i class="bi bi bi-trash"></i> '.$this->trans('Delete').'</button>';
        }
    }
}

/**
 * @subpackage savedsearches/output
 */
class Hm_Output_update_search_icon extends Hm_Output_Module {
    protected function output() {
        if ($this->get('search_param_update')) {
            return '<a href="" class="update_search" title="'.$this->trans('Update saved search').'"><i class="bi bi-check-circle-fill"></i></a>';
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
        $res = '<a href="" class="update_search_label" title="'.$this->trans('Update saved search label').'"><i class="bi bi-pencil-fill"></i></a>' . update_search_label_field($this->get('search_name'), $this);
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
        return '<a href="" '.$style.' class="delete_search btn btn-light" title="'.$this->trans('Delete saved search').'"><i class="bi bi-x-circle-fill"></i></a>';
    }
}

/**
 * @subpackage savedsearches/output
 */
class Hm_Output_save_search_icon extends Hm_Output_Module {
    protected function output() {
        $name = $this->get('search_name', '');
        if (!$name) {
            return '<a style="display: none;" href="" class="save_search" title="'.$this->trans('Save search').'"><i class="bi bi-check-circle-fill"></i></a>';
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
            $searches = new Hm_Saved_Searches($details);
            $search_types = $searches->get_by_type();

            foreach ($search_types['simple'] as $name => $args) {
                $url = sprintf('?page=search&amp;search_terms=%s&amp;search_fld=%s&amp;search_since=%s&amp;search_name=%s',
                    $this->html_safe(urlencode($args[0])),
                    $this->html_safe(urlencode($args[2])),
                    $this->html_safe(urlencode($args[1])),
                    $this->html_safe(urlencode($name))
                );
                $res .= '<li class="menu_search_'.$this->html_safe($name).'"><a class="unread_link" href="'.$url.'">';
                if (!$this->get('hide_folder_icons')) {
                    $res .= '<i class="bi bi-search account_icon"></i> ';
                }
                $res .= $this->html_safe($name).'</a></li>';
            }

            foreach ($search_types['advanced'] as $name => $search_data) {
                $url = sprintf('?page=advanced_search&amp;search_name=%s',
                    $this->html_safe(urlencode($name))
                );
                $res .= '<li class="menu_search_advanced_'.$this->html_safe($name).'"><a class="unread_link advanced_search_link" href="'.$url.'" data-search-name="'.$this->html_safe($name).'">';
                if (!$this->get('hide_folder_icons')) {
                    $res .= '<i class="bi bi-gear-wide-connected account_icon" title="'.$this->trans('Advanced Search').'"></i> ';
                }
                $res .= $this->html_safe($name).'</a></li>';
            }

            if (!empty($res)) {
                $this->append('folder_sources', array('search_folders', $res));
            }
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

    public function add_advanced($name, $search_data) {
        $formatted_data = array(
            'type' => 'advanced',
            'data' => $search_data,
            'name' => $name
        );
        return $this->add($name, $formatted_data);
    }

    public function update_advanced($name, $search_data) {
        if (array_key_exists($name, $this->searches) && $this->is_advanced($name)) {
            $formatted_data = array(
                'type' => 'advanced',
                'data' => $search_data,
                'name' => $name
            );
            return $this->update($name, $formatted_data);
        }
        return false;
    }

    /**
     * Check if a saved search is an advanced search
     * @param string $name search name
     * @return bool true if advanced search
     */
    public function is_advanced($name) {
        $search = $this->get($name);
        return is_array($search) && array_key_exists('type', $search) && $search['type'] === 'advanced';
    }

    /**
     * Get advanced search data
     * @param string $name search name
     * @param mixed $default default value if not found
     * @return mixed advanced search data or default
     */
    public function get_advanced($name, $default = false) {
        if ($this->is_advanced($name)) {
            $search = $this->get($name);
            return array_key_exists('data', $search) ? $search['data'] : $default;
        }
        return $default;
    }

    /**
     * Get all searches separated by type
     * @return array array with 'simple' and 'advanced' keys
     */
    public function get_by_type() {
        $simple = array();
        $advanced = array();

        foreach ($this->searches as $name => $data) {
            if ($this->is_advanced($name)) {
                $advanced[$name] = $data;
            } else {
                $simple[$name] = $data;
            }
        }
        return array('simple' => $simple, 'advanced' => $advanced);
    }
}

/**
 * @subpackage savedsearches/functions
 */
if (!hm_exists('get_search_from_post')) {
function get_search_from_post($request) {
    return array(
        array_key_exists('search_terms', $request->post) ? $request->post['search_terms'] : '',
        array_key_exists('search_since', $request->post) ? $request->post['search_since'] : DEFAULT_SEARCH_SINCE,
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
        array_key_exists('search_since', $request->get) ? $request->get['search_since'] : DEFAULT_SEARCH_SINCE,
        array_key_exists('search_fld', $request->get) ? $request->get['search_fld'] : DEFAULT_SEARCH_FLD,
        array_key_exists('search_name', $request->get) ? $request->get['search_name'] : '',
    );
}}

/**
 * @subpackage savedsearches/functions
 */
if (!hm_exists('get_advanced_search_from_post')) {
function get_advanced_search_from_post($request) {
    $data = array();
    if (array_key_exists('adv_search_data', $request->post)) {
        $search_data = json_decode($request->post['adv_search_data'], true);
        if (is_array($search_data)) {
            return $search_data;
        }
    }
    return false;
}}

/**
 * @subpackage savedsearches/functions
 */
if (!hm_exists('update_search_label_field')) {
function update_search_label_field($search_name, $output_mod) {
    return '<div class="update_search_label_field" style="display: none;"><input type="text" class="search_terms_label form-control" placeholder="'.$output_mod->trans('Search Label').'" value="" /><input type="hidden" class="old_search_terms_label" value="'.$output_mod->html_safe($search_name).'" /><input type="submit" class="search_label_update btn btn-primary btn-sm" value="'.$output_mod->trans('Update').'" /></div>';
}}
