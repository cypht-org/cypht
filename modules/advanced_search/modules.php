<?php

/**
 * Advanced search modules
 * @package modules
 * @subpackage advanced_search
 */

if (!defined('DEBUG_MODE')) { die(); }

/**
 * Process an advanced search ajax request
 * @subpackage advanced_search/output
 */
class Hm_Handler_process_adv_search_request extends Hm_Handler_Module {

    private $imap_id;
    private $folder;

    public function process() {
        list($success, $form) = $this->process_form(array(
            'adv_source',
            'adv_start',
            'adv_end',
            'adv_charset',
            'adv_terms',
            'adv_targets'
        ));
        if (!$success) {
            return;
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
            'SENTBEFORE' => date('j-M-Y', strtotime($form['adv_end'])),
            'SENTSINCE' => date('j-M-Y', strtotime($form['adv_start'])),
        );
        foreach ($form['adv_terms'] as $term) {
            foreach ($form['adv_targets'] as $target) {
                $params[$target] = $term;
            }
        }
        $this->out('imap_search_results', $this->imap_search($flags, $imap, $params));
        $this->out('folder_status', $imap->folder_state);
        $this->out('imap_server_ids', array($this->imap_id));
    }

    private function imap_search($flags, $imap, $params) {
        $msg_list = array();
        $msgs = $imap->search($flags, false, $params);
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
        return $msg_list;
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
    /**
     * Leaves two open div tags that are closed in Hm_Output_advanced_search_content_end and Hm_Output_advanced_search_form
     */
    protected function output() {
        return '<div class="search_content"><div class="content_title">'.
            '<img width="16" height="16" src="'.Hm_Image_Sources::$plus.'" '.
            'alt="'.$this->trans('Expand all').'" class="adv_expand_all">'.
            $this->trans('Advanced Search').'</div>';
    }
}

/**
 * End of the advanced search results content section
 * @subpackage advanced_search/output
 */
class Hm_Output_advanced_search_content_end extends Hm_Output_Module {
    /**
     * Closes a div opened in Hm_Output_advanced_search_content_start
     */
    protected function output() {
        return '</div>';
    }
}

/**
 * Advanced search form content
 * @subpackage advanced_search/output
 */
class Hm_Output_advanced_search_form_content extends Hm_Output_Module {
    protected function output() {
        /**
         * TODO:
         * - disable if no imap
         * - fill out all drop down options
         * - labels + ids
         * - input names + values
         * - set defaults:
         *     - time: 1 year from today
         *     - sources: none
         *     - other: none
         */
        return 
            $this->terms().
            $this->sources().
            $this->targets().
            $this->times().
            $this->other().
            '<div class="submit_section"><input type="button" id="adv_search" value="'.$this->trans('Search').'" />';
    }

    protected function targets() {
        return '<div data-target=".targets_section" class="settings_subtitle"><img width="16" height="16" alt="'.
            $this->trans('targets').'" src="'.Hm_Image_Sources::$doc.'" />'.$this->trans('Targets').'</div>'.
            '<div class="targets_section"><table id="adv_target" class="adv_targets">'.
            '<tr><th><input type="radio" value="TEXT" class="target_radio" checked="checked" name="target_type" /><label>'.
            $this->trans('Entire message').'</label></th><td></td></tr><tr><th><input type="radio" '.
            'class="target_radio" value="BODY" name="target_type" /><label>'.$this->trans('Body').'</label></th><td></td></tr>'.
            '<tr><th><input type="radio" class="target_radio" value="header" name="target_type" /><label>'.$this->trans('Header').
            '</label></th><td>'.'<select class="adv_header_select" ><option value="FROM">'.$this->trans('From').'</option><option value="SUBJECT">'.$this->trans('Subject').
            '</option><option value="TO">'.$this->trans('To').'</option><option value="CC">'.$this->trans('Cc').'</option></select></td></tr>'.
            '<tr><th><input type="radio" class="target_radio" value="custom" name="target_type" /><label>'.$this->trans('Custom Header').
            '</label></th><td><input class="adv_custom_header" type="text" /></td></tr></table><img class="new_target" width="16" height="16" alt="'.
            $this->trans('Add').'" src="'.Hm_Image_Sources::$plus.'" /></div>';
    }

    protected function terms() {
        return '<div data-target=".terms_section" class="settings_subtitle"><img width="16" height="16" alt="'.
            $this->trans('terms').'" src="'.Hm_Image_Sources::$search.'" />'.$this->trans('Terms').'</div>'.
            '<div class="terms_section"><input class="adv_terms" id="adv_term" type="text" /><img class="new_term" '.
            'width="16" height="16" alt="'.$this->trans('Add').'" src="'.Hm_Image_Sources::$plus.'" /></div>';
    }

    protected function times() {
        $time = strtotime("-1 year", time());
        $from_date = date("Y-m-d", $time);
        $to_date = date("Y-m-d", time());
        return '<div data-target=".time_section" class="settings_subtitle"><img width="16" height="16" alt="'.
            $this->trans('time').'" src="'.Hm_Image_Sources::$calendar.'" />'.$this->trans('Time').'</div>'.
            '<div class="time_section"><span id="adv_time" class="adv_times">'.$this->trans('From').
            ' <input class="adv_time_fld_from" type="date" value="'.$this->html_safe($from_date).
            '" /> '.$this->trans('To').' <input class="adv_time_fld_to" type="date" value="'.
            $this->html_safe($to_date).'" /></span> <img class="new_time" width="16" height="16" alt="'.
            $this->trans('Add').'" src="'.Hm_Image_Sources::$plus.'" /></div>';
    }

    protected function sources() {
        return '<div data-target=".source_section" class="settings_subtitle"><img width="16" height="16" alt="'.
            $this->trans('sources').'" src="'.Hm_Image_Sources::$folder.'" />'.$this->trans('Sources').'</div>'.
            '<div class="source_section">'.$this->trans('IMAP').' <img class="adv_folder_select" width="16" '.
            'height="16" alt="'.$this->trans('Add').'" src="'.Hm_Image_Sources::$plus.'" /><br /><div '.
            'class="adv_folder_list"></div><div class="adv_source_list"></div></div>';
    }

    protected function other() {
        return '<div data-target=".other_section" class="settings_subtitle"><img width="16" height="16" alt="'.
            $this->trans('other').'" src="'.Hm_Image_Sources::$cog.'" />'.$this->trans('Other').'</div>'.
            '<div class="other_section"><table><tr><th>'.$this->trans('Character set').'</th><td><select class="charset" />'.
            '<option value="">'.$this->trans('None').'</option><option value="UTF-8">UTF-8</option><option value="ASCII">ASCII</option></select></td></tr><tr><th>'.$this->trans('Flags').'</th><td>'.
            '<div class="flags"></label><input class="adv_flag" value="SEEN" type="checkbox"><label>'.$this->trans('Read').
            '<br /></label><input class="adv_flag" value="UNSEEN" type="checkbox"><label>'.$this->trans('Unread').
            '<br /></label><input class="adv_flag" value="ANSWERED" type="checkbox"><label>'.$this->trans('Answered').
            '<br /></label><input class="adv_flag" value="UNANSWERED" type="checkbox"><label>'.$this->trans('Unanswered').
            '<br /></label><input class="adv_flag" value="FLAGGED" type="checkbox"><label>'.$this->trans('Flagged').
            '<br /></label><input class="adv_flag" value="UNFLAGGED" type="checkbox"><label>'.$this->trans('Unflagged').
            '<br /></label><input class="adv_flag" value="DELETED" type="checkbox"><label>'.$this->trans('Deleted').
            '<br /></label><input class="adv_flag" value="UNDELETED" type="checkbox"><label>'.$this->trans('Not deleted').
            '</div></td></tr></table></div>';
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
            '<div class="adv_controls">'.message_controls($this).'</div>';
    }
}

/**
 * Closes the advanced search results table
 * @subpackage advanced_search/output
 */
class Hm_Output_advanced_search_results_table_end extends Hm_Output_Module {
    /**
     */
    protected function output() {
        return '</tbody></table>';
    }
}

