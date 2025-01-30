<?php

/**
 * Core modules
 * @package modules
 * @subpackage core
 */

/**
 * get basic message list settings
 * @subpackage core/functions
 * @param string $path message list path
 * @param object $handler hm handler module
 * @return array
 */
if (!hm_exists('get_message_list_settings')) {
function get_message_list_settings($path, $handler) {
    $list_path = $path;
    $mailbox_list_title = array();
    $message_list_since = DEFAULT_SINCE;
    $per_source_limit = DEFAULT_PER_SOURCE;

    if ($path == 'unread') {
        $list_path = 'unread';
        $mailbox_list_title = array('Unread');
        $message_list_since = $handler->user_config->get('unread_since_setting', DEFAULT_UNREAD_SINCE);
        $per_source_limit = $handler->user_config->get('unread_per_source_setting', DEFAULT_UNREAD_PER_SOURCE);
    }
    elseif ($path == 'email') {
        $message_list_since = $handler->user_config->get('all_email_since_setting', DEFAULT_ALL_EMAIL_SINCE);
        $per_source_limit = $handler->user_config->get('all_email_per_source_setting', DEFAULT_ALL_EMAIL_PER_SOURCE);
        $list_path = 'email';
        $mailbox_list_title = array('All Email');
    }
    elseif ($path == 'flagged') {
        $list_path = 'flagged';
        $message_list_since = $handler->user_config->get('flagged_since_setting', DEFAULT_FLAGGED_SINCE);
        $per_source_limit = $handler->user_config->get('flagged_per_source_setting', DEFAULT_FLAGGED_PER_SOURCE);
        $mailbox_list_title = array('Flagged');
    }
    elseif ($path == 'combined_inbox') {
        $list_path = 'combined_inbox';
        $message_list_since = $handler->user_config->get('all_since_setting', DEFAULT_ALL_SINCE);
        $per_source_limit = $handler->user_config->get('all_per_source_setting', DEFAULT_ALL_PER_SOURCE);
        $mailbox_list_title = array('Everything');
    }
    elseif ($path == 'junk') {
        $list_path = 'junk';
        $message_list_since = $handler->user_config->get('junk_since_setting', DEFAULT_JUNK_SINCE);
        $per_source_limit = $handler->user_config->get('junk_per_source_setting', DEFAULT_JUNK_PER_SOURCE);
        $mailbox_list_title = array('Junk');
    }
    elseif ($path == 'snoozed') {
        $list_path = 'snoozed';
        $message_list_since = $handler->user_config->get('snoozed_since_setting', DEFAULT_SNOOZED_SINCE);
        $per_source_limit = $handler->user_config->get('snoozed_per_source_setting', DEFAULT_SNOOZED_PER_SOURCE);
        $mailbox_list_title = array('Snoozed');
    }
    elseif ($path == 'trash') {
        $list_path = 'trash';
        $message_list_since = $handler->user_config->get('trash_since_setting', DEFAULT_TRASH_SINCE);
        $per_source_limit = $handler->user_config->get('trash_per_source_setting', DEFAULT_TRASH_PER_SOURCE);
        $mailbox_list_title = array('Trash');
    }
    elseif ($path == 'drafts') {
        $list_path = 'drafts';
        $message_list_since = $handler->user_config->get('drafts_since_setting', DEFAULT_DRAFT_SINCE);
        $per_source_limit = $handler->user_config->get('drafts_per_source_setting', DEFAULT_DRAFT_PER_SOURCE);
        $mailbox_list_title = array('Drafts');
    }
    elseif ($path == 'tag' && $handler->module_is_supported('tags')) {
        $list_path = 'tag';
        $message_list_since = $handler->user_config->get('tag_since_setting', DEFAULT_TAGS_SINCE);
        $per_source_limit = $handler->user_config->get('tag_per_source_setting', DEFAULT_TAGS_PER_SOURCE);
        $mailbox_list_title = array('Tag');
    }
    return array($list_path, $mailbox_list_title, $message_list_since, $per_source_limit);
}}

/**
 * Build meta information for a message list
 * @subpackage core/functions
 * @param array $input module output
 * @param object $output_mod Hm_Output_Module
 * @return string
 */
if (!hm_exists('message_list_meta')) {
function message_list_meta($input, $output_mod) {
    if (!array_key_exists('list_meta', $input) || !$input['list_meta']) {
        return '';
    }
    $limit = 0;
    $since = false;
    $times = array(
        'today' => 'Today',
        '-1 week' => 'Last 7 days',
        '-2 weeks' => 'Last 2 weeks',
        '-4 weeks' => 'Last 4 weeks',
        '-6 weeks' => 'Last 6 weeks',
        '-6 months' => 'Last 6 months',
        '-1 year' => 'Last year',
        '-5 years' => 'Last 5 years'
    );
    if (array_key_exists('per_source_limit', $input)) {
        $limit = $input['per_source_limit'];
    }
    if (!$limit) {
        $limit = DEFAULT_PER_SOURCE;
    }
    if (array_key_exists('message_list_since', $input)) {
        $since = $input['message_list_since'];
    }
    if (!$since) {
        $since = DEFAULT_SINCE;
    }
    $date = sprintf('%s', mb_strtolower($output_mod->trans($times[$since])));
    $max = sprintf($output_mod->trans('sources@%d each'), $limit);

    return '<div class="list_meta d-flex align-items-center fs-6">'.
        $date.
        '<b>-</b>'.
        '<span class="src_count"></span> '.$max.
        '<b>-</b>'.
        '<span class="total"></span> '.$output_mod->trans('total').'</div>';
}}

/**
 * Build sort dialog for a combined list
 * @subpackage core/functions
 * @param object $output_mod Hm_Output_Module
 * @return string
 */
if (!hm_exists('combined_sort_dialog')) {
function combined_sort_dialog($mod) {
    $sorts = [
        'arrival' => $mod->trans('Arrival Date'),
        'date' => $mod->trans('Sent Date'),
        'from' => $mod->trans('From'),
        'to' => $mod->trans('To'),
        'subject' => $mod->trans('Subject')
    ];

    $res = '<select name="sort" style="width: 150px" class="combined_sort form-select form-select-sm">';
    foreach ($sorts as $name => $val) {
        $res .= '<option value="'.$name.'">'.$val.' &darr;</option>';
        $res .= '<option value="-'.$name.'">'.$val.' &uarr;</option>';
    }
    $res .= '</select>';
    return $res;
}}

/**
 * Build a human readable interval string
 * @subpackage core/functions
 * @param string $date_str date string parsable by strtotime()
 * @return string
 */
if (!hm_exists('human_readable_interval')) {
function human_readable_interval($date_str) {
    if (!$date_str) {
        return 'Unknown';
    }
    $precision     = 2;
    $interval_time = array();
    $date          = strtotime($date_str);
    $interval      = time() - $date;
    $res           = array();

    $t['second'] = 1;
    $t['minute'] = $t['second']*60;
    $t['hour']   = $t['minute']*60;
    $t['day']    = $t['hour']*24;
    $t['week']   = $t['day']*7;
    $t['month']  = $t['day']*30;
    $t['year']   = $t['week']*52;

    if ($interval < -300) {
        return 'From the future!';
    }
    elseif ($interval <= 0) {
        return 'Just now';
    }
    foreach (array_reverse($t) as $name => $val) {
        if ($interval_time[$name] = ($interval/$val > 0) ? floor($interval/$val) : false) {
            $interval -= $val*$interval_time[$name];
        }
    }
    $interval_time = array_slice(array_filter($interval_time, function($v) { return $v > 0; }), 0, $precision);
    foreach($interval_time as $name => $val) {
        if ($val > 1) {
            $res[] = sprintf('%d %ss', $val, $name);
        }
        else {
            $res[] = sprintf('%d %s', $val, $name);
        }
    }
    return implode(', ', $res);
}}

/**
 * Output a message list row of data using callbacks
 * @subpackage core/functions
 * @param array $values data and callbacks for each cell
 * @param string $id unique id for the message
 * @param string $style message list style (news or email)
 * @param object $output_mod Hm_Output_Module
 * @param string $row_class optional table row css class
 * @return array
 */
if (!hm_exists('message_list_row')) {
function message_list_row($values, $id, $style, $output_mod, $row_class='') {
    $res = '<tr class="'.$output_mod->html_safe($id);
    if ($row_class) {
        $res .= ' '.$output_mod->html_safe($row_class);
    }
    $data_uid = "";
    if ($uids = explode("_", $id)) {
        if (isset($uids[2])) {
            $data_uid = 'data-uid="'. $uids[2] .'"';
        }
    }
    if (!empty($data_uid)) {
        $res .= '" '.$data_uid.'>';
    } else {
        $res .= '">';
    }
    
    if ($style == 'news') {
        $res .= '<td class="news_cell checkbox_cell">';
    }
    foreach ($values as $vals) {
        if (function_exists($vals[0])) {
            $function = array_shift($vals);
            $res .= $function($vals, $style, $output_mod);
        }
    }
    if ($style == 'news') {
        $res .= '</td>';
    }
    $res .= '</tr>';
    return array($res, $id);
}}

/**
 * Generic callback for a message list table cell
 * @subpackage core/functions
 * @param array $vals data for the cell
 * @param string $style message list style
 * @param object $output_mod Hm_Output_Module
 * @return string
 */
if (!hm_exists('safe_output_callback')) {
function safe_output_callback($vals, $style, $output_mod) {
    $img = '';
    $title = '';
    if (count($vals) > 2) {
        if ($vals[2]){
            $img = '<i class="bi bi-filetype-'.$vals[2].'"></i>';
        }
        if (count($vals) > 3) {
            $title = $output_mod->html_safe($vals[3]);
        } else {
            $title = $output_mod->html_safe($vals[1]);
        }
    }
    if ($style == 'news') {
        return sprintf('<div class="%s" data-title="%s">%s%s</div>', $output_mod->html_safe($vals[0]), $title, $img, $output_mod->html_safe($vals[1]));
    }
    return sprintf('<td class="%s" data-title="%s">%s%s</td>', $output_mod->html_safe($vals[0]), $title, $img, $output_mod->html_safe($vals[1]));
}}

/**
 * Callback for a message list checkbox
 * @subpackage core/functions
 * @param array $vals data for the cell
 * @param string $style message list style
 * @param object $output_mod Hm_Output_Module
 * @return string
 */
if (!hm_exists('checkbox_callback')) {
function checkbox_callback($vals, $style, $output_mod) {
    if ($style == 'news') {
        return sprintf('<input type="checkbox" id="%s" value="%s" />'.
            '<label class="checkbox_label" for="%s"></label>'.
            '</td><td class="news_cell">', $output_mod->html_safe($vals[0]),
            $output_mod->html_safe($vals[0]), $output_mod->html_safe($vals[0]));
    }
    return sprintf('<td class="checkbox_cell">'.
        '<input id="'.$output_mod->html_safe($vals[0]).'" type="checkbox" value="%s" />'.
        '<label class="checkbox_label" for="'.$output_mod->html_safe($vals[0]).'"></label>'.
        '</td>', $output_mod->html_safe($vals[0]));
}}

/**
 * Callback for a subject cell in a message list
 * @subpackage core/functions
 * @param array $vals data for the cell
 * @param string $style message list style
 * @param object $output_mod Hm_Output_Module
 * @return string
 */
if (!hm_exists('subject_callback')) {
function subject_callback($vals, $style, $output_mod) {
    $img = '';
    $subject = '';
    $preview_msg = '';
    if (isset($vals[3]) && $vals[3]) {
        $img = '<i class="bi bi-filetype-'.$vals[3].'"></i>';
    }
    $subject = $output_mod->html_safe($vals[0]);
    if (isset($vals[4]) && $vals[4]) {
        $preview_msg = $output_mod->html_safe($vals[4]);
    }
    
    $hl_subject = preg_replace("/^(\[[^\]]+\])/", '<span class="s_pre">$1</span>', $subject);
    if ($style == 'news') {
        if ($output_mod->get('is_mobile')) {
            return sprintf('<div class="subject"><div class="%s" title="%s">%s <a href="%s">%s</a></div></div>', $output_mod->html_safe(implode(' ', $vals[2])), $subject, $img, $output_mod->html_safe($vals[1]), $hl_subject);
        }
        return sprintf('<div class="subject"><div class="%s" title="%s">%s <a href="%s">%s</a><p class="fw-light">%s</p></div></div>', $output_mod->html_safe(implode(' ', $vals[2])), $subject, $img, $output_mod->html_safe($vals[1]), $hl_subject, $preview_msg);
    }

    if ($output_mod->get('is_mobile')) {
        return sprintf('<td class="subject"><div class="%s"><a title="%s" href="%s">%s</a></div></td>', $output_mod->html_safe(implode(' ', $vals[2])), $subject, $output_mod->html_safe($vals[1]), $hl_subject);
    }
    return sprintf('<td class="subject"><div class="%s"><a title="%s" href="%s">%s</a><p class="fw-light">%s</p></div></td>', $output_mod->html_safe(implode(' ', $vals[2])), $subject, $output_mod->html_safe($vals[1]), $hl_subject, $preview_msg);
}}

/**
 * Callback for a date cell in a message list
 * @subpackage core/functions
 * @param array $vals data for the cell
 * @param string $style message list style
 * @param object $output_mod Hm_Output_Module
 * @return string
 */
if (!hm_exists('date_callback')) {
function date_callback($vals, $style, $output_mod) {
    $snooze_class = isset($vals[2]) && $vals[2]? ' snoozed_date': '';
    if ($style == 'news') {
        return sprintf('<div class="msg_date%s">%s<input type="hidden" class="msg_timestamp" value="%s" /></div>', $snooze_class, $output_mod->html_safe($vals[0]), $output_mod->html_safe($vals[1]));
    }
    return sprintf('<td class="msg_date%s" title="%s">%s<input type="hidden" class="msg_timestamp" value="%s" /></td>', $snooze_class, $output_mod->html_safe(date('r', $vals[1])), $output_mod->html_safe($vals[0]), $output_mod->html_safe($vals[1]));
}}

function dates_holders_callback($vals) {
    $res = '<td class="dates d-none">';
    $res .= '<input type="hidden" name="arrival" class="arrival" value="'. $vals[0] .'" arial-label="Arrival date" />';
    $res .= '<input type="hidden" name="date" class="date" value="'. $vals[1] .'" arial-label="Sent date" />';
    $res .= '</td>';
    return $res;
}

/**
 * Callback for an icon in a message list row
 * @subpackage core/functions
 * @param array $vals data for the cell
 * @param string $style message list style
 * @param object $output_mod Hm_Output_Module
 * @return string
 */
if (!hm_exists('icon_callback')) {
function icon_callback($vals, $style, $output_mod) {
    $icons = '';
    $title = array();
    $show_icons = $output_mod->get('msg_list_icons');
    if (in_array('flagged', $vals[0])) {
        $icons .= $show_icons ? '<i class="bi bi-star-half"></i>' : ' F';
        $title[] = $output_mod->trans('Flagged');
    }
    if (in_array('draft', $vals[0])) {
        $icons .= $show_icons ? '<i class="bi bi-star-half"></i>' : ' D';
        $title[] = $output_mod->trans('Draft');
    }
    if (in_array('answered', $vals[0])) {
        $icons .= $show_icons ? '<i class="bi bi-check-circle-fill"></i>' : ' A';
        $title[] = $output_mod->trans('Answered');
    }
    if (in_array('attachment', $vals[0])) {
        $icons .= $show_icons ? '<i class="bi bi-paperclip"></i>' : ' <i class="bi bi-plus-circle"></i>';
        $title[] = $output_mod->trans('Attachment');
    }
    $title = implode(', ', $title);
    if ($style == 'news') {
        return sprintf('<div class="icon" title="%s">%s</div>', $title, $icons);
    }
    return sprintf('<td class="icon" title="%s">%s</td>', $title, $icons);
}}

/**
 * Output message controls
 * @subpackage core/functions
 * @param object $output_mod Hm_Output_Module
 * @return string
 */
if (!hm_exists('message_controls')) {
function message_controls($output_mod) {
    $txt = '';
    $res = '<a class="toggle_link" href="#"><i class="bi bi-check-square-fill"></i></a>'.
        '<div class="msg_controls fs-6 d-none gap-1 align-items-center">'.
            '<div class="dropdown on_mobile">'.
                '<button type="button" class="btn btn-outline-success btn-sm dropdown-toggle" id="coreMsgControlDropdown" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="true">Actions</button>'.
                '<ul class="dropdown-menu" aria-labelledby="coreMsgControlDropdown">'.
                    '<li><a class="dropdown-item msg_read core_msg_control btn btn-sm btn-light text-black-50" href="#" data-action="read">'.$output_mod->trans('Read').'</a></li>'.
                    '<li><a class="dropdown-item msg_unread core_msg_control btn btn-sm btn-light text-black-50" href="#" data-action="unread">'.$output_mod->trans('Unread').'</a></li>'.
                    '<li><a class="dropdown-item msg_flag core_msg_control btn btn-sm btn-light text-black-50" href="#" data-action="flag">'.$output_mod->trans('Flag').'</a></li>'.
                    '<li><a class="dropdown-item msg_unflag core_msg_control btn btn-sm btn-light text-black-50" href="#" data-action="unflag">'.$output_mod->trans('Unflag').'</a></li>'.
                    '<li><a class="dropdown-item msg_delete core_msg_control btn btn-sm btn-light text-black-50" href="#" data-action="delete">'.$output_mod->trans('Delete').'</a></li>'.
                    '<li><a class="dropdown-item msg_archive core_msg_control btn btn-sm btn-light text-black-50" href="#" data-action="archive">'.$output_mod->trans('Archive').'</a></li>'.
                '</ul>'.
            '</div>'.
            '<a class="msg_read core_msg_control btn btn-sm btn-light no_mobile border text-black-50" href="#" data-action="read">'.$output_mod->trans('Read').'</a>'.
            '<a class="msg_unread core_msg_control btn btn-sm btn-light no_mobile border text-black-50" href="#" data-action="unread">'.$output_mod->trans('Unread').'</a>'.
            '<a class="msg_flag core_msg_control btn btn-sm btn-light no_mobile border text-black-50" href="#" data-action="flag">'.$output_mod->trans('Flag').'</a>'.
            '<a class="msg_unflag core_msg_control btn btn-sm btn-light no_mobile border text-black-50" href="#" data-action="unflag">'.$output_mod->trans('Unflag').'</a>'.
            '<a class="msg_delete core_msg_control btn btn-sm btn-light no_mobile border text-black-50" href="#" data-action="delete">'.$output_mod->trans('Delete').'</a>'.
            '<a class="msg_archive core_msg_control btn btn-sm btn-light no_mobile border text-black-50" href="#" data-action="archive">'.$output_mod->trans('Archive').'</a>';

    if ($output_mod->get('msg_controls_extra')) {
        $res .= $output_mod->get('msg_controls_extra');
    }
    if(!empty($output_mod->get('tags'))) {
        $res .= tags_dropdown($output_mod, []);
    }
    $res .= '</div>';
    return $res;
}}

/**
 * Output select element for "received since" options
 * @subpackage core/functions
 * @param string $since current value to pre-select
 * @param string $name name used as the elements id and name
 * @param object $output_mod Hm_Output_Module
 * @return string
 */
if (!hm_exists('message_since_dropdown')) {
function message_since_dropdown($since, $name, $output_mod, $original_default_value = '-1 week') {
    $times = array(
        'today' => 'Today',
        '-1 week' => 'Last 7 days',
        '-2 weeks' => 'Last 2 weeks',
        '-4 weeks' => 'Last 4 weeks',
        '-6 weeks' => 'Last 6 weeks',
        '-6 months' => 'Last 6 months',
        '-1 year' => 'Last year',
        '-5 years' => 'Last 5 years'
    );
    $res = '<select name="'.$name.'" id="'.$name.'" class="message_list_since form-select form-select-sm w-auto" data-default-value="'.$original_default_value.'">';
    $reset = '';
    foreach ($times as $val => $label) {
        $res .= '<option';
        if ($val == $since) {
            $res .= ' selected="selected"';
            if ($val != $original_default_value) {
                $reset = '<span class="tooltip_restore" restore_aria_label="Restore default value"><i class="bi bi-arrow-repeat refresh_list reset_default_value_select"></i></span>';
            }

        }
        $res .= ' value="'.$val.'">'.$output_mod->trans($label).'</option>';
    }
    $res .= '</select>'.$reset;
    return $res;
}}

/**
 * Output a source list for a message list
 * @subpackage core/functions
 * @param array $sources source of the list
 * @param object $output_mod Hm_Output_Module
 */
if (!hm_exists('list_sources')) {
function list_sources($sources, $output_mod) {
    $res = '<div class="list_sources">';
    $res .= '<div class="src_title fs-5 mb-2">'.$output_mod->html_safe('Sources').'</div>';
    foreach ($sources as $src) {
        if (array_key_exists('group', $src) && $src['group'] == 'background') {
            continue;
        }
        if (array_key_exists('nodisplay', $src) && $src['nodisplay']) {
            continue;
        }
        if ($src['type'] == 'imap' && !array_key_exists('folder_name', $src)) {
            $folder = 'INBOX';
        }
        elseif (!array_key_exists('folder_name', $src)) {
            $folder = '';
        }
        else {
            $folder = $src['folder_name'];
        }
        $res .= '<div class="list_src">'.$output_mod->html_safe($src['type']).' '.$output_mod->html_safe($src['name']);
        $res .= ' '.$output_mod->html_safe($folder);
        $res .= '</div>';
    }
    $res .= '</div>';
    return $res;
}}



/**
 * Output a source list for a message list
 * @subpackage core/functions
 * @param array $sources source of the list
 * @param object $output_mod Hm_Output_Module
 */
if (!hm_exists('update_search_label_field')) {
function update_search_label_field($search_term, $output_mod) {
    $res = '<div class="update_search_label_field">';
    $res .= '<div class="update_saved_search_title">'.$output_mod->html_safe('Update saved search label') .'</div>';
    $res .= '<div>
    <input type="hidden" name="page" value="search">
    <input type="hidden" name="search_terms" value="'. $search_term .'">
    <label class="screen_reader" for="search_terms_label">Current Search Label</label>
    <input required="" disabled id="old_search_terms_label" type="search" value="' . $search_term . '" class="old_search_terms_label form-control form-control-sm" name="old_search_terms_label">
    <label class="screen_reader" for="search_terms_label">New Search Terms</label>
    <input required="" placeholder="New search terms label" id="search_terms_label" type="search" class="search_terms_label form-control form-control-sm" name="search_terms_label">
    <div>
        <input type="button" class="search_label_update btn w-100 btn-primary btn-sm" value="Update">
    </div>
    </div>';
    $res .= '</div>';
    return $res;
}}



















/**
 * Output message list controls
 * @subpackage core/functions
 * @param string $refresh_link refresh link tag
 * @param string $config_link configuration link tag
 * @param string $source_link source link tag
 * @return string
 */
if (!hm_exists('list_controls')) {
function list_controls($refresh_link, $config_link, $source_link=false, $search_field='') {
    return '<div class="list_controls no_mobile d-flex gap-3 align-items-center">'.
        $refresh_link.$source_link.$config_link.$search_field.'</div>
    <div class="list_controls on_mobile">'.$search_field.'
        <i class="bi bi-filter-circle" onclick="listControlsMenu()"></i>
        <div id="list_controls_menu" classs="list_controls_menu">'.$refresh_link.$source_link.$config_link.'</div>
    </div>';
}}

/**
 * Validate search terms
 * @subpackage core/functions
 * @param string $terms search terms to validate
 * @return string
 */
if (!hm_exists('validate_search_terms')) {
function validate_search_terms($terms) {
    $terms = trim(strip_tags($terms));
    if (!$terms) {
        $terms = '';
    }
    return $terms;
}}

/**
 * Validate the name of a search field
 * @subpackage core/functions
 * @param string $fld name to validate
 * @return mixed
 */
if (!hm_exists('validate_search_fld')) {
function validate_search_fld($fld) {
    if (in_array($fld, array('TEXT', 'BODY', 'FROM', 'SUBJECT', 'TO', 'CC'))) {
        return $fld;
    }
    return false;
}}

/**
 * Output a select element for the search field
 * @subpackage core/functions
 * @param string $current currently selected field
 * @param object $output_mod Hm_Output_Module
 * @return string
 */
if (!hm_exists('search_field_selection')) {
function search_field_selection($current, $output_mod) {
    $flds = array(
        'TEXT' => 'Entire message',
        'BODY' => 'Message body',
        'SUBJECT' => 'Subject',
        'FROM' => 'From',
        'TO' => 'To',
        'CC' => 'Cc',
    );
    $res = '<select class="form-select form-select-sm w-auto" id="search_fld" name="search_fld">';
    foreach ($flds as $val => $name) {
        $res .= '<option ';
        if ($current == $val) {
            $res .= 'selected="selected" ';
        }
        $res .= 'value="'.$val.'">'.$output_mod->trans($name).'</option>';
    }
    $res .= '</select>';
    return $res;
}}
