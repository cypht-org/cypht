<?php

/**
 * Saved search modules
 * @package modules
 * @subpackage saved_searches
 */

if (!defined('DEBUG_MODE')) { die(); }

 /**
 * @subpackage saved_searches/handler
 */
class Hm_Handler_save_searches_data extends Hm_Handler_Module {
    public function process() {
        $search = '';
        $data = get_search_terms($this->session, $this->request);
        $searches = new Hm_Saved_Searches($this->user_config->get('saved_searches', array()));
        foreach ($searches->dump() as $name => $args) {
            if (empty(array_diff($args, $data))) {
                $search = $name;
                break;
            }
        }
        $this->out('search_name', $search);
    }
}

 /**
 * @subpackage saved_searches/handler
 */
class Hm_Handler_saved_search_folder_data extends Hm_Handler_Module {
    public function process() {
        $this->out('saved_searches', $this->user_config->get('saved_searches', array()));
    }
}

 /**
 * Try to save a search
 * @subpackage saved_searches/handler
 */
class Hm_Handler_update_search extends Hm_Handler_Module {
    public function process() {
        list($success, $form) = $this->process_form(array('search_name'));
        if ($success) {
            $data = get_search_terms($this->session, $this->request);
            $searches = new Hm_Saved_Searches($this->user_config->get('saved_searches', array()));
            if ($searches->update($form['search_name'], $data)) {
                $this->session->record_unsaved('Updated a search');
                $this->user_config->set('saved_searches', $searches->dump());
                $this->session->set('user_data', $this->user_config->dump());
                Hm_Msgs::add('Search Updated');
            }
            else {
                Hm_Msgs::add('ERRUnable to update the search paramaters');
            }
        }
    }
}

 /**
 * Try to save a search
 * @subpackage saved_searches/handler
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
                Hm_Msgs::add('Search Deleted');
            }
            else {
                Hm_Msgs::add('ERRUnable to delete the search');
            }
        }
    }
}

 /**
 * Try to save a search
 * @subpackage saved_searches/handler
 */
class Hm_Handler_save_search extends Hm_Handler_Module {
    public function process() {
        list($success, $form) = $this->process_form(array('search_name'));
        if ($success) {
            $data = get_search_terms($this->session, $this->request);
            $searches = new Hm_Saved_Searches($this->user_config->get('saved_searches', array()));
            if ($searches->add($form['search_name'], $data)) {
                $this->session->record_unsaved('Saved a search');
                $this->user_config->set('saved_searches', $searches->dump());
                $this->session->set('user_data', $this->user_config->dump());
                Hm_Msgs::add('Search saved');
            }
            else {
                Hm_Msgs::add('ERRYou already have a search by that name');
            }
        }
    }
}

/**
 * @subpackage saved_searches/output
 */
class Hm_Output_save_searches_form extends Hm_Output_Module {
    protected function output() {
        $name = $this->get('search_name', '');
        $res = '<form class="saved_searches_form"><input type="text" placeholder="'.$this->trans('Search Name').
            '" class="search_name" name="search_name" value="';
        if ($name) {
            $res .= $this->html_safe($name).'" disabled="disabled" /><input type="submit" class="update_search" value="'.
                $this->trans('Update').'" /><input type="submit" class="delete_search" value="'.$this->trans('Delete').'"/></form>';
        }
        else {
            $res .= '" /><input type="submit" class="save_search" value="'.$this->trans('Save').'" /></form>';
        }
        return $res;
    }
}

/**
 * @subpackage saved_searches/output
 */
class Hm_Output_search_folders extends Hm_Output_Module {
    protected function output() {
        $res = '';
        $details = $this->get('saved_searches', array());
        if (!empty($details)) {
            foreach ($details as $name => $args) {
                $url = sprintf('?page=search&amp;search_terms=%s&amp;search_fld=%s&amp;search_since=%s',
                    $this->html_safe(urlencode($args[0])),
                    $this->html_safe(urlencode($args[2])),
                    $this->html_safe(urlencode($args[1]))
                );
                $res .= '<li class="menu_search_'.$this->html_safe($name).'"><a class="unread_link" href="'.$url.'">'.
                    '<img class="account_icon" src="'.$this->html_safe(Hm_Image_Sources::$search).
                    '" alt="" width="16" height="16" /> '.$this->html_safe($name).'</a></li>';
            }
            $this->append('folder_sources', array('search_folders', $res));
        }
    }
}

/**
 * @subpackage saved_searches/lib
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
}

function get_search_terms($session, $request) {
    $data = array(
        array_key_exists('search_terms', $request->get) ? $request->get['search_terms'] : false,
        array_key_exists('search_terms', $request->get) ? $request->get['search_since'] : false,
        array_key_exists('search_terms', $request->get) ? $request->get['search_fld'] : false
    );
    if ($data[0] === false) {
        $data[0] = $session->get('search_terms', '');
    }
    if ($data[1] === false) {
        $data[1] = $session->get('search_since', DEFAULT_SINCE);
    }
    if ($data[2] === false) {
        $data[2] = $session->get('search_fld', 'TEXT');
    }
    return $data;
}
