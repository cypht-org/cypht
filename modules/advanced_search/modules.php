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
    public function process() {
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
            '<tr><th><input type="radio" value="msg" class="target_radio" checked="checked" name="target_type" /><label>'.
            $this->trans('Entire message').'</label></th><td></td></tr><tr><th><input type="radio" '.
            'class="target_radio" value="body" name="target_type" /><label>'.$this->trans('Body').'</label></th><td></td></tr>'.
            '<tr><th><input type="radio" class="target_radio" value="header" name="target_type" /><label>'.$this->trans('Header').
            '</label></th><td>'.'<select class="adv_header_select" ><option value="from">'.$this->trans('From').'</option><option value="subject">'.$this->trans('Subject').
            '</option><option value="to">'.$this->trans('To').'</option><option value="cc">'.$this->trans('Cc').'</option></select></td></tr>'.
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
            '<option value="UTF-8">UTF-8</option><option value="ASCII">ASCII</option></select></td></tr><tr><th>'.$this->trans('Flags').'</th><td>'.
            '<div class="flags"></label><input class="adv_flag" value="read" type="checkbox"><label>'.$this->trans('Read').
            '<br /></label><input class="adv_flag" value="unread" type="checkbox"><label>'.$this->trans('Unread').
            '<br /></label><input class="adv_flag" value="answered" type="checkbox"><label>'.$this->trans('Answered').
            '<br /></label><input class="adv_flag" value="unanswered" type="checkbox"><label>'.$this->trans('Unanswered').
            '<br /></label><input class="adv_flag" value="flagged" type="checkbox"><label>'.$this->trans('Flagged').
            '<br /></label><input class="adv_flag" value="unflagged" type="checkbox"><label>'.$this->trans('Unflagged').
            '<br /></label><input class="adv_flag" value="deleted" type="checkbox"><label>'.$this->trans('Deleted').
            '<br /></label><input class="adv_flag" value="undeleted" type="checkbox"><label>'.$this->trans('Not deleted').
            '</div></td></tr></table></div>';
    }
}

/**
 * Finish the advanced search form
 * @subpackage advanced_search/output
 */
class Hm_Output_advanced_search_form_end extends Hm_Output_Module {
    protected function output() {
        return '</div>';
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

