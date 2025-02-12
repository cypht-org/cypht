<?php

/**
 * Advanced search modules
 * @package modules
 * @subpackage advanced_search
 */

if (!defined('DEBUG_MODE')) { die(); }


/**
 * Setup advanced search
 * @subpackage advanced_search/output
 */
class Hm_Handler_advanced_search_prepare extends Hm_Handler_Module {

    public function process() {
        $this->out('msg_list_icons', $this->user_config->get('show_list_icons_setting', DEFAULT_SHOW_LIST_ICONS));
        $this->out('imap_supported', $this->module_is_supported('imap'));
        $this->out('list_parent', 'advanced_search');
    }
}

/**
 * Process an advanced search ajax request
 * @subpackage advanced_search/output
 */
class Hm_Handler_process_adv_search_request extends Hm_Handler_Module {

    private $imap_id;
    private $folder;

    public function process() {
        if (!$this->module_is_supported('imap')) {
            return;
        }
        list($success, $form) = $this->process_form(array(
            'adv_source',
            'adv_start',
            'adv_end',
            'adv_source_limit',
            'adv_terms',
            'adv_targets'
        ));
        if (!$success) {
            return;
        }
        $limit = $form['adv_source_limit'];
        if (!$limit || !is_int($limit) || (is_int($limit) && $limit > 1000)) {
            $limit = 100;
        }
        if (!$this->validate_date($form['adv_start']) ||
            !$this->validate_date($form['adv_end'])) {
            Hm_Msgs::add('Invalid date format', 'warning');
            return;
        }
        $flags = array('ALL');
        if (array_key_exists('adv_flags', $this->request->post)) {
            if (!$this->validate_flags($this->request->post['adv_flags'])) {
                Hm_Msgs::add('Invalid flag', 'warning');
                return;
            }
            $flags = $this->request->post['adv_flags'];
        }
        if (!$this->validate_source($form['adv_source'])) {
            Hm_Msgs::add('Invalid source', 'warning');
            return;
        }
        $charset = false;
        if (array_key_exists('charset', $this->request->post) &&
            in_array($this->request->post, array('UTF-8', 'ASCII'), true)) {
            $charset = $this->request->post['charset'];
        }

        $mailbox = Hm_IMAP_List::get_connected_mailbox($this->imap_id, $this->cache);
        if (! $mailbox || ! $mailbox->authed()) {
            return;
        }
        if ($charset) {
            $mailbox->set_search_charset($charset);
        }
        $params = array(
            array('SENTBEFORE', date('j-M-Y', strtotime($form['adv_end']))),
            array('SENTSINCE', date('j-M-Y', strtotime($form['adv_start'])))
        );
        foreach ($form['adv_terms'] as $term) {
            foreach ($form['adv_targets'] as $target) {
                $params[] = array($target, $term);
            }
        }

        $searchInAllFolders = $this->request->post['all_folders'] ?? false;
        $searchInSpecialFolders = $this->request->post['all_special_folders'] ?? false;
        $includeSubfolders = $this->request->post['include_subfolders'] ?? false;
        if ($searchInAllFolders) {
            $msg_list = $this->all_folders_search($mailbox, $flags, $params, $limit);
        } elseif ($searchInSpecialFolders) {
            $msg_list = $this->special_folders_search($mailbox, $flags, $params, $limit);
        } else if ($includeSubfolders) {
            $msg_list = $this->all_folders_search($mailbox, $flags, $params, $limit, $this->folder);
        } else if (! $mailbox->select_folder($this->folder)) {
            return;
        } else {
            $msg_list = $this->imap_search($flags, $mailbox, $params, $limit);
        }
        $this->out('imap_search_results', $msg_list);
        $this->out('folder_status', $mailbox->get_folder_state());
        $this->out('imap_server_ids', array($this->imap_id));
    }

    private function all_folders_search($mailbox, $flags, $params, $limit, $parent = '') {
        if ($parent) {
            $folders = $mailbox->get_subfolders($parent);
        } else {
            $folders = $mailbox->get_folders();
        }
        $msg_list = array();
        foreach ($folders as $folder) {
            $this->folder = $folder['name'];
            $msgs = $this->imap_search($flags, $mailbox, $params, $limit);
            $msg_list = array_merge($msg_list, $msgs);
        }
        return $msg_list;
    }

    private function special_folders_search($mailbox, $flags, $params, $limit) {
        $specials = $this->user_config->get('special_imap_folders', array());
        $folders = $specials[$this->imap_id] ?? [];

        $msg_list = array();
        foreach ($folders as $folder) {
            $this->folder = $folder;
            $mailbox->select_folder($this->folder);
            $msgs = $this->imap_search($flags, $mailbox, $params, $limit);
            $msg_list = array_merge($msg_list, $msgs);
        }
        return $msg_list;
    }

    private function imap_search($flags, $mailbox, $params, $limit) {
        $msg_list = array();
        $exclude_deleted = true;
        if (in_array('deleted', $flags, true)) {
            $exclude_deleted = false;
        }
        $msgs = $mailbox->search($this->folder, $flags, false, $params, array(), $exclude_deleted);
        if (!$msgs) {
            return $msg_list;
        }
        $server_details = Hm_IMAP_List::dump($this->imap_id);
        foreach ($mailbox->get_message_list($this->folder, $msgs) as $msg) {
            if (array_key_exists('content-type', $msg) && mb_stristr($msg['content-type'], 'multipart/mixed')) {
                $msg['flags'] .= ' \Attachment';
            }
            if (mb_stristr($msg['flags'], 'deleted')) {
                continue;
            }
            $msg['server_id'] = $this->imap_id;
            $msg['folder'] = bin2hex($this->folder);
            $msg['server_name'] = $server_details['name'];
            $msg_list[] = $msg;
        }
        usort($msg_list, function($a, $b) {
            if (!array_key_exists('internal_date', $a) || (!array_key_exists('internal_date', $b))) {
                return 0;
            }
            return strtotime($b['internal_date']) - strtotime($a['internal_date']);
        });
        return array_slice($msg_list, 0, $limit);
    }

    private function validate_source($val) {
        if (mb_substr_count($val, '_') !== 2) {
            return false;
        }
        $source_parts = explode('_', $val);
        $this->imap_id = $source_parts[1];
        $this->folder = hex2bin($source_parts[2]);
        return true;
    }

    private function validate_date($val) {
        return preg_match("/\d{4}-\d{2}-\d{2}/", $val) ? true : false;
    }

    private function validate_flags($data) {
        $flags = array('SEEN', 'UNSEEN', 'UNSEEN', 'SEEN', 'ANSWERED', 'UNANSWERED',
            'FLAGGED', 'UNFLAGGED', 'DELETED', 'UNDELETED');
        if (!array_key_exists('adv_flags', $data)) {
            return true;
        }
        if (!is_array($adv_flags)) {
            return false;
        }
        foreach ($data as $flag) {
            if (!in_array($flag, $flags, true)) {
                return false;
            }
        }
        return true;
    }
}

/**
 * Advanced search link
 * @subpackage advanced_search/output
 */
class Hm_Output_advanced_search_link extends Hm_Output_Module {
    protected function output() {
        return '<a class="adv_search_link" href="?page=advanced_search">'.$this->trans('Advanced').'</a>';
    }
}

/**
 * Start the advanced search form
 * @subpackage advanced_search/output
 */
class Hm_Output_advanced_search_form_start extends Hm_Output_Module {
    protected function output() {
        return '<div class="advanced_search_form">';
    }
}

/**
 * Start the advanced search results content section
 * @subpackage advanced_search/output
 */
class Hm_Output_advanced_search_content_start extends Hm_Output_Module {
    protected function output() {
        return '<div class="search_content px-0"><div class="content_title d-flex align-items-center px-3">'.
            '<i class="bi bi-caret-down-fill adv_expand_all cursor-pointer"></i>'.
            '<i class="bi bi-caret-up adv_collapse_all cursor-pointer"></i>'.
            '<lable class="ms-2">'.$this->trans('Advanced Search').'</label></div>';
    }
}

/**
 * End of the advanced search results content section
 * @subpackage advanced_search/output
 */
class Hm_Output_advanced_search_content_end extends Hm_Output_Module {
    protected function output() {
        return '</div></div></div>';
    }
}

/**
 * Advanced search form content
 * @subpackage advanced_search/output
 */
class Hm_Output_advanced_search_form_content extends Hm_Output_Module {
    protected function output() {
        if (!$this->get('imap_supported')) {
            return '<div class="imap_support_required">'.
                $this->trans('the IMAP module set must be enabled for advanced search').'</div>';
        }
        return
            $this->terms().
            $this->sources().
            $this->targets().
            $this->times().
            $this->other().
            '<div class="submit_section px-5"><input type="button" class="btn btn-primary" id="adv_search" value="'.$this->trans('Search').'" />'.
            ' <input class="btn btn-light border" type="button" class="adv_reset" value="'.$this->trans('Reset').'" />';
    }

    protected function targets() {
        return '<div data-target=".targets_section" class="settings_subtitle cursor-pointer px-3 py-2 "><i class="bi bi-file-earmark-fill me-2"></i>'.$this->trans('Targets').
            '<span class="target_count">'.sprintf($this->trans('targets: %d'), 0).'</span></div>'.
            '<div class="targets_section mx-5 py-4"><div class="col-lg-6 col-12" id="adv_target"><table class="adv_targets table table-borderless"><tr><th>'.
            '<input type="radio" value="TEXT" id="adv_msg" class="target_radio form-check-input" checked="checked" '.
            'name="target_type" /><label class="form-check-label ms-2" for="adv_msg">'.$this->trans('Entire message').'</label></th>'.
            '<th><input type="radio" class="target_radio form-check-input" value="BODY" name="target_type" id="adv_body" '.
            '/><label class="form-check-label ms-2" for="adv_body">'.$this->trans('Body').'</label></th><td></td></tr><tr><th><input type="radio" '.
            'class="target_radio form-check-input" value="header" id="adv_header_radio" name="target_type" /><label class="form-check-label ms-2" for="adv_header_radio">'.
            $this->trans('Header').'</label></th><td>'.'<select class="adv_header_select form-select" ><option value="FROM">'.
            $this->trans('From').'</option><option value="SUBJECT">'.$this->trans('Subject').'</option><option value="TO">'.
            $this->trans('To').'</option><option value="CC">'.$this->trans('Cc').'</option></select></td></tr>'.
            '<tr><th><input type="radio" class="target_radio form-check-input" value="custom" id="adv_custom" name="target_type" />'.
            '<label class="form-check-label ms-2" for="adv_custom">'.$this->trans('Custom Header').'</label></th><td><input class="adv_custom_header form-control" '.
            'type="text" /></td></tr></table></div><i class="bi bi-plus-circle new_target cursor-pointer ms-2"></i></div>';
    }

    protected function terms() {
        return '<div data-target=".terms_section" class="settings_subtitle cursor-pointer px-3 py-2 mt-3">'.
            '<i class="bi bi-search me-2"></i>'.$this->trans('Terms').
            '<span class="term_count">'.sprintf($this->trans('terms: %d'), 0).'</span></div>'.
            '<div class="terms_section mx-5 py-4">'.
            '<div class="d-flex align-items-center"><span id="adv_term_not" class="adv_term_nots"><input type="checkbox" class="form-check-input" value="not" id="adv_term_not" /> !</span>'.
            '<input class="adv_terms form-control w-auto" id="adv_term" type="text" /><i class="bi bi-plus-circle new_term cursor-pointer ms-3"></i></div></div>';
    }

    protected function times() {
        $from_time = strtotime("-1 year", time());
        $from_date = date("Y-m-d", $from_time);
        $to_time = strtotime("+1 day", time());
        $to_date = date("Y-m-d", $to_time);
        return '<div data-target=".time_section" class="settings_subtitle cursor-pointer px-3 py-2 "><i class="bi bi-calendar3-week-fill me-2"></i>'.$this->trans('Time').
            '<span class="time_count">'.sprintf($this->trans('time ranges: %d'), 0).'</span></div>'.
            '<div class="time_section mx-5 py-4"><span id="adv_time" class="adv_times d-flex align-items-center gap-2">'.$this->trans('From').
            ' <input class="adv_time_fld_from form-control w-auto" type="date" value="'.$this->html_safe($from_date).
            '" /> '.$this->trans('To').' <input class="adv_time_fld_to form-control w-auto" type="date" value="'.
            $this->html_safe($to_date).'" /></span><i class="bi bi-plus-circle new_time cursor-pointer"></i></div>';
    }

    protected function sources() {
        return '<div data-target=".source_section" class="settings_subtitle cursor-pointer px-3 py-2 "><i class="bi bi-folder-fill me-2"></i>'.$this->trans('Sources').
            '<span class="source_count">'.sprintf($this->trans('sources: %d'), 0).'</span></div>'.
            '<div class="source_section mx-5 py-4">'.$this->trans('IMAP').' <i class="bi bi-plus-circle adv_folder_select cursor-pointer"></i><br /><div '.
            'class="adv_folder_list"></div><div class="adv_source_list"></div></div>';
    }

    protected function other() {
        return '<div data-target=".other_section" class="settings_subtitle cursor-pointer px-3 py-2 "><i class="bi bi-gear-fill me-2"></i>'.$this->trans('Other').
            '<span class="other_count">'.sprintf($this->trans('other settings: %d'), 0).'</span></div>'.
            '<div class="other_section mx-5 py-4"><div class="col-lg-6 col-12"><table class="table table-borderless"><tr><th>'.$this->trans('Character set').'</th><td><select class="charset form-select w-auto">'.
            '<option value="">'.$this->trans('Default').'</option><option value="UTF-8">UTF-8</option>'.
            '<option value="ASCII">ASCII</option></select></td></tr><tr><th>'.$this->trans('Results per source').'</th>'.
            '<td><input type="number" value="100" class="adv_source_limit form-control" /></td></tr><tr><th>'.$this->trans('Flags').'</th><td>'.
            '<div class="flags d-flex flex-column"><div><input id="adv_flag_read" class="adv_flag form-check-input" value="SEEN" type="checkbox">'.
            '<label class="form-check-label ms-2" for="adv_flag_read">'.$this->trans('Read').
            ' </label></div><div><input id="adv_flag_unread" class="adv_flag form-check-input" value="UNSEEN" type="checkbox">'.
            '<label class="form-check-label ms-2" for="adv_flag_unread">'.$this->trans('Unread').
            '</label></div><div><input id="adv_flag_answered" class="adv_flag form-check-input" value="ANSWERED" type="checkbox">'.
            '<label class="form-check-label ms-2" for="adv_flag_answered">'.$this->trans('Answered').
            '</label></div><div><input id="adv_flag_unanswered" class="adv_flag form-check-input" value="UNANSWERED" type="checkbox">'.
            '<label class="form-check-label ms-2" for="adv_flag_unanswered">'.$this->trans('Unanswered').
            '</label></div><div><input id="adv_flag_flagged" class="adv_flag form-check-input" value="FLAGGED" type="checkbox">'.
            '<label class="form-check-label ms-2" for="adv_flag_flagged">'.$this->trans('Flagged').
            '</label></div><div><input id="adv_flag_unflagged" class="adv_flag form-check-input" value="UNFLAGGED" type="checkbox">'.
            '<label class="form-check-label ms-2" for="adv_flag_unflagged">'.$this->trans('Unflagged').
            '</label></div><div><input id="adv_flag_deleted" class="adv_flag form-check-input" value="DELETED" type="checkbox">'.
            '<label class="form-check-label ms-2" for="adv_flag_deleted">'.$this->trans('Deleted').
            '</label></div><div><input id="adv_flag_undeleted" class="adv_flag form-check-input" value="UNDELETED" type="checkbox">'.
            '<label class="form-check-label ms-2" for="adv_flag_undeleted">'.$this->trans('Not deleted').
            '</label></div></div></td></tr></table></div></div>';
    }
}

/**
 * Finish the advanced search form
 * @subpackage advanced_search/output
 */
class Hm_Output_advanced_search_form_end extends Hm_Output_Module {
    protected function output() {
        return '</div><div class="content_title search_result_title mb-3 px-3"><i class="bi bi-envelope-check-fill adv_expand_all"></i>'.$this->trans('Search Results').'</div>'.
            '<div class="adv_controls">'.message_controls($this).' '.combined_sort_dialog($this).'</div>'.
            '<div class="message_list">';
    }
}

/**
 * Closes the advanced search results table
 * @subpackage advanced_search/output
 */
class Hm_Output_advanced_search_results_table_end extends Hm_Output_Module {
    protected function output() {
        return '</tbody></table>';
    }
}

/**
 * Format search results row
 * @subpackage advanced_search/output
 */
class Hm_Output_filter_imap_advanced_search extends Hm_Output_Module {
    /**
     * Build ajax response from an IMAP server for a search
     */
    protected function output() {
        if ($this->get('imap_search_results')) {
            prepare_imap_message_list($this->get('imap_search_results'), $this, 'advanced_search');
        }
        elseif (!$this->get('formatted_message_list')) {
            $this->out('formatted_message_list', array());
        }
    }
}
