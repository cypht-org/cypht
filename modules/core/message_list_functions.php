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
function get_message_list_settings($path, $handler) {
    $list_path = $path;
    $mailbox_list_title = array();
    $message_list_since = DEFAULT_SINCE;
    $per_source_limit = DEFAULT_PER_SOURCE;

    if ($path == 'unread') {
        $list_path = 'unread';
        $mailbox_list_title = array('Unread');
        $message_list_since = $handler->user_config->get('unread_since_setting', DEFAULT_SINCE);
        $per_source_limit = $handler->user_config->get('unread_per_source_setting', DEFAULT_PER_SOURCE);
    }
    elseif ($path == 'email') {
        $message_list_since = $handler->user_config->get('all_email_since_setting', DEFAULT_SINCE);
        $per_source_limit = $handler->user_config->get('all_email_per_source_setting', DEFAULT_PER_SOURCE);
        $list_path = 'email';
        $mailbox_list_title = array('All Email');
    }
    elseif ($path == 'flagged') {
        $list_path = 'flagged';
        $message_list_since = $handler->user_config->get('flagged_since_setting', DEFAULT_SINCE);
        $per_source_limit = $handler->user_config->get('flagged_per_source_setting', DEFAULT_PER_SOURCE);
        $mailbox_list_title = array('Flagged');
    }
    elseif ($path == 'combined_inbox') {
        $list_path = 'combined_inbox';
        $message_list_since = $handler->user_config->get('all_since_setting', DEFAULT_SINCE);
        $per_source_limit = $handler->user_config->get('all_per_source_setting', DEFAULT_PER_SOURCE);
        $mailbox_list_title = array('Everything');
    }
    return array($list_path, $mailbox_list_title, $message_list_since, $per_source_limit);
}

/**
 * Build meta information for a message list
 * @subpackage core/functions
 * @param array $input module output
 * @param object $output_mod Hm_Output_Module
 * @return string
 */
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
    $date = sprintf('%s', strtolower($output_mod->trans($times[$since])));
    $max = sprintf($output_mod->trans('sources@%d each'), $limit);

    return '<div class="list_meta">'.
        $date.
        '<b>-</b>'.
        '<span class="src_count"></span> '.$max.
        '<b>-</b>'.
        '<span class="total"></span> '.$output_mod->trans('total').'</div>';
}

/**
 * Build a human readable interval string
 * @subpackage core/functions
 * @param string $date_str date string parsable by strtotime()
 * @return string
 */
function human_readable_interval($date_str) {
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

    if ($interval < 0) {
        return 'From the future!';
    }
    elseif ($interval == 0) {
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
}

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
function message_list_row($values, $id, $style, $output_mod, $row_class='') {
    $res = '<tr class="'.$output_mod->html_safe($id);
    if ($row_class) {
        $res .= ' '.$output_mod->html_safe($row_class);
    }
    $res .= '">';
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
}

/**
 * Generic callback for a message list table cell
 * @subpackage core/functions
 * @param array $vals data for the cell
 * @param string $style message list style
 * @param object $output_mod Hm_Output_Module
 * @return string
 */
function safe_output_callback($vals, $style, $output_mod) {
    if ($style == 'email') {
        return sprintf('<td class="%s" title="%s">%s</td>', $output_mod->html_safe($vals[0]), $output_mod->html_safe($vals[1]), $output_mod->html_safe($vals[1]));
    }
    elseif ($style == 'news') {
        return sprintf('<div class="%s" title="%s">%s</div>', $output_mod->html_safe($vals[0]), $output_mod->html_safe($vals[1]), $output_mod->html_safe($vals[1]));
    }
}

/**
 * Callback for a message list checkbox
 * @subpackage core/functions
 * @param array $vals data for the cell
 * @param string $style message list style
 * @param object $output_mod Hm_Output_Module
 * @return string
 */
function checkbox_callback($vals, $style, $output_mod) {
    if ($style == 'email') {
        return sprintf('<td class="checkbox_cell">'.
            '<input id="'.$output_mod->html_safe($vals[0]).'" type="checkbox" value="%s" />'.
            '<label class="checkbox_label" for="'.$output_mod->html_safe($vals[0]).'"></label>'.
            '</td>', $output_mod->html_safe($vals[0]));
    }
    elseif ($style == 'news') {
        return sprintf('<input type="checkbox" id="%s" value="%s" />'.
            '<label class="checkbox_label" for="%s"></label>'.
            '</td><td class="news_cell">', $output_mod->html_safe($vals[0]),
            $output_mod->html_safe($vals[0]), $output_mod->html_safe($vals[0]));
    }
}

/**
 * Callback for a subject cell in a message list
 * @subpackage core/functions
 * @param array $vals data for the cell
 * @param string $style message list style
 * @param object $output_mod Hm_Output_Module
 * @return string
 */
function subject_callback($vals, $style, $output_mod) {
    $subject = $output_mod->html_safe($vals[0]);
    $hl_subject = preg_replace("/^(\[[^\]]+\])/", '<span class="s_pre">$1</span>', $subject);
    if ($style == 'email') {
        return sprintf('<td class="subject"><div class="%s"><a title="%s" href="%s">%s</a></div></td>', $output_mod->html_safe(implode(' ', $vals[2])), $subject, $output_mod->html_safe($vals[1]), $hl_subject);
    }
    elseif ($style == 'news') {
        return sprintf('<div class="subject"><div class="%s" title="%s"><a href="%s">%s</a></div></div>', $output_mod->html_safe(implode(' ', $vals[2])), $subject, $output_mod->html_safe($vals[1]), $hl_subject);
    }
}

/**
 * Callback for a date cell in a message list
 * @subpackage core/functions
 * @param array $vals data for the cell
 * @param string $style message list style
 * @param object $output_mod Hm_Output_Module
 * @return string
 */
function date_callback($vals, $style, $output_mod) {
    if ($style == 'email') {
        return sprintf('<td class="msg_date" title="%s">%s<input type="hidden" class="msg_timestamp" value="%s" /></td>', $output_mod->html_safe(date('r', $vals[1])), $output_mod->html_safe($vals[0]), $output_mod->html_safe($vals[1]));
    }
    elseif ($style == 'news') {
        return sprintf('<div class="msg_date">%s<input type="hidden" class="msg_timestamp" value="%s" /></div>', $output_mod->html_safe($vals[0]), $output_mod->html_safe($vals[1]));
    }
}

/**
 * Callback for an icon in a message list row
 * @subpackage core/functions
 * @param array $vals data for the cell
 * @param string $style message list style
 * @param object $output_mod Hm_Output_Module
 * @return string
 */
function icon_callback($vals, $style, $output_mod) {
    $icons = '';
    if (in_array('flagged', $vals[0])) {
        $icons .= '<img src="'.Hm_Image_Sources::$star.'" width="16" height="16" alt="'.$output_mod->trans('Flagged').'" />';
    }
    if (in_array('answered', $vals[0])) {
        $icons .= '<img src="'.Hm_Image_Sources::$circle_check.'" width="16" height="16" alt="'.$output_mod->trans('Answered').'" />';
    }
    if (in_array('attachment', $vals[0])) {
        $icons .= '<img src="'.Hm_Image_Sources::$paperclip.'" width="16" height="16" alt="'.$output_mod->trans('Attachment').'" />';
    }
    if ($style == 'email') {
        return sprintf('<td class="icon">%s</td>', $icons);
    }
    elseif ($style == 'news') {
        return sprintf('<div class="icon">%s</div>', $icons);
    }
}

/**
 * Output message controls
 * @subpackage core/functions
 * @param object $output_mod Hm_Output_Module
 * @return string
 */
function message_controls($output_mod) {
    $res = '<a class="toggle_link" href="#"><img alt="x" src="'.Hm_Image_Sources::$check.'" width="8" height="8" /></a>'.
        '<div class="msg_controls">'.
        '<a class="core_msg_control" href="#" data-action="read">'.$output_mod->trans('Read').'</a>'.
        '<a class="core_msg_control" href="#" data-action="unread">'.$output_mod->trans('Unread').'</a>'.
        '<a class="core_msg_control" href="#" data-action="flag">'.$output_mod->trans('Flag').'</a>'.
        '<a class="core_msg_control" href="#" data-action="unflag">'.$output_mod->trans('Unflag').'</a>'.
        '<a class="core_msg_control" href="#" data-action="delete">'.$output_mod->trans('Delete').'</a>';
    if ($output_mod->get('msg_controls_extra')) {
        $res .= $output_mod->get('msg_controls_extra');
    }
    $res .= '</div>';
    return $res;
}

/**
 * Output select element for "received since" options
 * @subpackage core/functions
 * @param string $since current value to pre-select
 * @param string $name name used as the elements id and name
 * @param object $output_mod Hm_Output_Module
 * @return string
 */
function message_since_dropdown($since, $name, $output_mod) {
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
    $res = '<select name="'.$name.'" id="'.$name.'" class="message_list_since">';
    foreach ($times as $val => $label) {
        $res .= '<option';
        if ($val == $since) {
            $res .= ' selected="selected"';
        }
        $res .= ' value="'.$val.'">'.$output_mod->trans($label).'</option>';
    }
    $res .= '</select>';
    return $res;
}

/**
 * Output a source list for a message list
 * @subpackage core/functions
 * @param array $sources source of the list
 * @param object $output_mod Hm_Output_Module
 */
function list_sources($sources, $output_mod) {
    $res = '<div class="list_sources">';
    $res .= '<div class="src_title">'.$output_mod->html_safe('Sources').'</div>';
    foreach ($sources as $src) {
        if (array_key_exists('group', $src) && $src['group'] == 'background') {
            continue;
        }
        if ($src['type'] == 'imap' && !array_key_exists('folder', $src)) {
            $folder = '_INBOX';
        }
        elseif (!array_key_exists('folder', $src)) {
            $folder = '';
        }
        else {
            $folder = '_'.hex2bin($src['folder']);
        }
        $res .= '<div class="list_src">'.$output_mod->html_safe($src['type']).' '.$output_mod->html_safe($src['name']);
        $res .= ' '.$output_mod->html_safe(str_replace('_', '', $folder));
        $res .= '</div>';
    }
    $res .= '</div>';
    return $res;
}

/**
 * Output message list controls
 * @subpackage core/functions
 * @param string $refresh_link refresh link tag
 * @param string $config_link configuration link tag
 * @param string $source_link source link tag
 * @return string
 */
function list_controls($refresh_link, $config_link, $source_link=false) {
    return '<div class="list_controls">'.
        $refresh_link.$source_link.$config_link.'</div>';
}

/**
 * Validate search terms
 * @subpackage core/functions
 * @param string $terms search terms to validate
 * @return string
 */
function validate_search_terms($terms) {
    $terms = trim(strip_tags($terms));
    if (!$terms) {
        $terms = '';
    }
    return $terms;
}

/**
 * Validate the name of a search field
 * @subpackage core/functions
 * @param string $fld name to validate
 * @return mixed
 */
function validate_search_fld($fld) {
    if (in_array($fld, array('TEXT', 'BODY', 'FROM', 'SUBJECT'))) {
        return $fld;
    }
    return false;
}

/**
 * Output a select element for the search field
 * @subpackage core/functions
 * @param string $current currently selected field
 * @param object $output_mod Hm_Output_Module
 * @return string
 */
function search_field_selection($current, $output_mod) {
    $flds = array(
        'TEXT' => 'Entire message',
        'BODY' => 'Message body',
        'SUBJECT' => 'Subject',
        'FROM' => 'From',
    );
    $res = '<select id="search_fld" name="search_fld">';
    foreach ($flds as $val => $name) {
        $res .= '<option ';
        if ($current == $val) {
            $res .= 'selected="selected" ';
        }
        $res .= 'value="'.$val.'">'.$output_mod->trans($name).'</option>';
    }
    $res .= '</select>';
    return $res;
}

