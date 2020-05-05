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
        $this->out('msg_list_icons', $this->user_config->get('show_list_icons_setting', false));
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
            Hm_Msgs::add('ERRInvalid date format');
            return;
        }
        $flags = array('ALL');
        if (array_key_exists('adv_flags', $this->request->post)) {
            if (!$this->validate_flags($this->request->post['adv_flags'])) {
                Hm_Msgs::add('ERRInvalid flag');
                return;
            }
            $flags = $this->request->post['adv_flags'];
        }
        if (!$this->validate_source($form['adv_source'])) {
            Hm_Msgs::add('ERRInvalid source');
            return;
        }
        $charset = false;
        if (array_key_exists('charset', $this->request->post) &&
            in_array($this->request->post, array('UTF-8', 'ASCII'), true)) {
            $charset = $this->request->post['charset'];
        }

        $cache = Hm_IMAP_List::get_cache($this->cache, $this->imap_id);
        $imap = Hm_IMAP_List::connect($this->imap_id, $cache);
        if (!imap_authed($imap)) {
            return;
        }
        if (!$imap->select_mailbox($this->folder)) {
            return;
        }
        if ($charset) {
            $imap->search_charset = $charset;
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
        $this->out('imap_search_results', $this->imap_search($flags, $imap, $params, $limit));
        $this->out('folder_status', $imap->folder_state);
        $this->out('imap_server_ids', array($this->imap_id));
    }

    private function imap_search($flags, $imap, $params, $limit) {
        $msg_list = array();
        $exclude_deleted = true;
        if (in_array('deleted', $flags, true)) {
            $exclude_deleted = false;
        }
        $msgs = $imap->search($flags, false, $params, array(), $exclude_deleted);
        if (!$msgs) {
            return $msg_list;
        }
        $server_details = Hm_IMAP_List::dump($this->imap_id);
        foreach ($imap->get_message_list($msgs) as $msg) {
            if (array_key_exists('content-type', $msg) && stristr($msg['content-type'], 'multipart/mixed')) {
                $msg['flags'] .= ' \Attachment';
            }
            if (stristr($msg['flags'], 'deleted')) {
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
        if (substr_count($val, '_') !== 2) {
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
        return '<div class="search_content"><div class="content_title">'.
            '<img width="16" height="16" src="'.Hm_Image_Sources::$plus.'" '.
            'alt="'.$this->trans('Expand all').'" class="adv_expand_all">'.
            '<img width="16" height="16" src="'.Hm_Image_Sources::$minus.'" '.
            'alt="'.$this->trans('Expand all').'" class="adv_collapse_all">'.
            $this->trans('Advanced Search').'</div>';
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
            '<div class="submit_section"><input type="button" id="adv_search" value="'.$this->trans('Search').'" />'.
            ' <input type="button" class="adv_reset" value="'.$this->trans('Reset').'" />';
    }

    protected function targets() {
        return '<div data-target=".targets_section" class="settings_subtitle"><img width="16" height="16" alt="'.
            $this->trans('targets').'" src="'.Hm_Image_Sources::$doc.'" />'.$this->trans('Targets').
            '<span class="target_count">'.sprintf($this->trans('targets: %d'), 0).'</span></div>'.
            '<div class="targets_section"><table id="adv_target" class="adv_targets"><tr><th>'.
            '<input type="radio" value="TEXT" id="adv_msg" class="target_radio" checked="checked" '.
            'name="target_type" /><label for="adv_msg">'.$this->trans('Entire message').'</label></th>'.
            '<th><input type="radio" class="target_radio" value="BODY" name="target_type" id="adv_body" '.
            '/><label for="adv_body">'.$this->trans('Body').'</label></th><td></td></tr><tr><th><input type="radio" '.
            'class="target_radio" value="header" id="adv_header_radio" name="target_type" /><label for="adv_header_radio">'.
            $this->trans('Header').'</label></th><td>'.'<select class="adv_header_select" ><option value="FROM">'.
            $this->trans('From').'</option><option value="SUBJECT">'.$this->trans('Subject').'</option><option value="TO">'.
            $this->trans('To').'</option><option value="CC">'.$this->trans('Cc').'</option></select></td></tr>'.
            '<tr><th><input type="radio" class="target_radio" value="custom" id="adv_custom" name="target_type" />'.
            '<label for="adv_custom">'.$this->trans('Custom Header').'</label></th><td><input class="adv_custom_header" '.
            'type="text" /></td></tr></table><img class="new_target" width="16" height="16" alt="'.$this->trans('Add').
            '" src="'.Hm_Image_Sources::$plus.'" /></div>';
    }

    protected function terms() {
        return '<div data-target=".terms_section" class="settings_subtitle"><img width="16" height="16" alt="'.
            $this->trans('terms').'" src="'.Hm_Image_Sources::$search.'" />'.$this->trans('Terms').
            '<span class="term_count">'.sprintf($this->trans('terms: %d'), 0).'</span></div>'.
            '<div class="terms_section">'.
            '<span id="adv_term_not" class="adv_term_nots"><input type="checkbox" value="not" id="adv_term_not" /> !</span>'.
            '<input class="adv_terms" id="adv_term" type="text" /><img class="new_term" '.
            'width="16" height="16" alt="'.$this->trans('Add').'" src="'.Hm_Image_Sources::$plus.'" /></div>';
    }

    protected function times() {
        $from_time = strtotime("-1 year", time());
        $from_date = date("Y-m-d", $from_time);
        $to_time = strtotime("+1 day", time());
        $to_date = date("Y-m-d", $to_time);
        return '<div data-target=".time_section" class="settings_subtitle"><img width="16" height="16" alt="'.
            $this->trans('time').'" src="'.Hm_Image_Sources::$calendar.'" />'.$this->trans('Time').
            '<span class="time_count">'.sprintf($this->trans('time ranges: %d'), 0).'</span></div>'.
            '<div class="time_section"><span id="adv_time" class="adv_times">'.$this->trans('From').
            ' <input class="adv_time_fld_from" type="date" value="'.$this->html_safe($from_date).
            '" /> '.$this->trans('To').' <input class="adv_time_fld_to" type="date" value="'.
            $this->html_safe($to_date).'" /></span> <img class="new_time" width="16" height="16" alt="'.
            $this->trans('Add').'" src="'.Hm_Image_Sources::$plus.'" /></div>';
    }

    protected function sources() {
        return '<div data-target=".source_section" class="settings_subtitle"><img width="16" height="16" alt="'.
            $this->trans('sources').'" src="'.Hm_Image_Sources::$folder.'" />'.$this->trans('Sources').
            '<span class="source_count">'.sprintf($this->trans('sources: %d'), 0).'</span></div>'.
            '<div class="source_section">'.$this->trans('IMAP').' <img class="adv_folder_select" width="16" '.
            'height="16" alt="'.$this->trans('Add').'" src="'.Hm_Image_Sources::$plus.'" /><br /><div '.
            'class="adv_folder_list"></div><div class="adv_source_list"></div></div>';
    }

    protected function other() {
        return '<div data-target=".other_section" class="settings_subtitle"><img width="16" height="16" alt="'.
            $this->trans('other').'" src="'.Hm_Image_Sources::$cog.'" />'.$this->trans('Other').
            '<span class="other_count">'.sprintf($this->trans('other settings: %d'), 0).'</span></div>'.
            '<div class="other_section"><table><tr><th>'.$this->trans('Character set').'</th><td><select class="charset">'.
            '<option value="">'.$this->trans('Default').'</option><option value="UTF-8">UTF-8</option>'.
            '<option value="ASCII">ASCII</option></select></td></tr><tr><th>'.$this->trans('Results per source').'</th>'.
            '<td><input type="number" value="100" class="adv_source_limit" /></td></tr><tr><th>'.$this->trans('Flags').'</th><td>'.
            '<div class="flags"><input id="adv_flag_read" class="adv_flag" value="SEEN" type="checkbox">'.
            '<label for="adv_flag_read">'.$this->trans('Read').
            ' </label><input id="adv_flag_unread" class="adv_flag" value="UNSEEN" type="checkbox">'.
            '<label for="adv_flag_unread">'.$this->trans('Unread').
            '<br /></label><input id="adv_flag_answered" class="adv_flag" value="ANSWERED" type="checkbox">'.
            '<label for="adv_flag_answered">'.$this->trans('Answered').
            '</label><input id="adv_flag_unanswered" class="adv_flag" value="UNANSWERED" type="checkbox">'.
            '<label for="adv_flag_unanswered">'.$this->trans('Unanswered').
            '<br /></label><input id="adv_flag_flagged" class="adv_flag" value="FLAGGED" type="checkbox">'.
            '<label for="adv_flag_flagged">'.$this->trans('Flagged').
            '</label><input id="adv_flag_unflagged" class="adv_flag" value="UNFLAGGED" type="checkbox">'.
            '<label for="adv_flag_unflagged">'.$this->trans('Unflagged').
            '<br /></label><input id="adv_flag_deleted" class="adv_flag" value="DELETED" type="checkbox">'.
            '<label for="adv_flag_deleted">'.$this->trans('Deleted').
            '</label><input id="adv_flag_undeleted" class="adv_flag" value="UNDELETED" type="checkbox">'.
            '<label for="adv_flag_undeleted">'.$this->trans('Not deleted').
            '</label></div></td></tr></table></div>';
    }
}

/**
 * Finish the advanced search form
 * @subpackage advanced_search/output
 */
class Hm_Output_advanced_search_form_end extends Hm_Output_Module {
    protected function output() {
        return '</div><div class="content_title search_result_title"><img width="16" height="16" src="'.
            Hm_Image_Sources::$env_closed.'" alt="'.$this->trans('Results').
            '" class="adv_expand_all">'.$this->trans('Search Results').'</div>'.
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

