<?php

function main_menu ($input, $output_mod) {
    $email = false;
    if (array_key_exists('folder_sources', $input) && is_array($input['folder_sources'])) {
        if (in_array('email_folders', $input['folder_sources'])) {
            $email = true;
        }
    }
    $res = '';
    $res .= '<div class="src_name main_menu" onclick="return toggle_section(\'.main\');">Main'.
        '<img alt="" class="menu_caret" src="'.Hm_Image_Sources::$chevron.'" width="8" height="8" />'.
        '</div><div class="main"><ul class="folders">'.
        '<li class="menu_search"><form method="get"><a class="unread_link" href="?page=search">'.
        '<img class="account_icon" src="'.$output_mod->html_safe(Hm_Image_Sources::$search).'" alt="" width="16" height="16" /></a><input type="hidden" name="page" value="search" />'.
        '<input type="text" class="search_terms" name="search_terms" placeholder="'.$output_mod->trans('Search').'" size="14" /></form></li>'.
        '<li class="menu_home"><a class="unread_link" href="?page=home">'.
        '<img class="account_icon" src="'.$output_mod->html_safe(Hm_Image_Sources::$home).'" alt="" width="16" height="16" /> '.$output_mod->trans('Home').'</a></li>'.
        '<li class="menu_combined_inbox"><a class="unread_link" href="?page=message_list&amp;list_path=combined_inbox">'.
        '<img class="account_icon" src="'.$output_mod->html_safe(Hm_Image_Sources::$box).'" alt="" width="16" height="16" /> '.$output_mod->trans('Everything').
        '</a><span class="combined_inbox_count"></span></li>';
    if ($email) {
        $res .= '<li class="menu_unread"><a class="unread_link" href="?page=message_list&amp;list_path=unread">'.
            '<img class="account_icon" src="'.$output_mod->html_safe(Hm_Image_Sources::$env_closed).'" alt="" width="16" height="16" /> '.$output_mod->trans('Unread').'</a></li>';
    }
    $res .= '<li class="menu_flagged"><a class="unread_link" href="?page=message_list&amp;list_path=flagged">'.
        '<img class="account_icon" src="'.$output_mod->html_safe(Hm_Image_Sources::$star).'" alt="" width="16" height="16" /> '.$output_mod->trans('Flagged').
        '</a> <span class="flagged_count"></span></li>'.
        '<li class="menu_compose"><a class="unread_link" href="?page=compose">'.
        '<img class="account_icon" src="'.$output_mod->html_safe(Hm_Image_Sources::$doc).'" alt="" width="16" height="16" /> '.$output_mod->trans('Compose').'</a></li>';

    $res .=  '<li><form class="logout_form" method="POST">'.
        '<a class="unread_link" href="#" onclick="return confirm_logout()"><img class="account_icon" src="'.
        $output_mod->html_safe(Hm_Image_Sources::$power).'" alt="" width="16" height="16" /> '.$output_mod->trans('Logout').'</a>'.
        '<div class="confirm_logout"><div class="confirm_text">You must enter your password to save your settings on logout</div>'.
        '<input name="password" class="save_settings_password" type="password" placeholder="Password" />'.
        '<input class="save_settings" type="submit" name="save_and_logout" value="Save and Logout" />'.
        '<input class="save_settings" type="submit" name="logout" value="Just Logout" />'.
        '<input class="save_settings" onclick="$(\'.confirm_logout\').hide(); return false;" type="button" value="Cancel" />'.
        '</div></form></li></ul></div>';
    return $res;
}

function folder_source_menu( $input, $output_mod) {
    $res = '';
    if (array_key_exists('folder_sources', $input) && is_array($input['folder_sources'])) {
        foreach (array_unique($input['folder_sources']) as $src) {
            $parts = explode('_', $src);
            $name = ucfirst(strtolower($parts[0]));
            $res .= '<div class="src_name" onclick="return toggle_section(\'.'.$output_mod->html_safe($src).
                '\');">'.$output_mod->html_safe($name).
                '<img class="menu_caret" src="'.Hm_Image_Sources::$chevron.'" alt="" width="8" height="8" /></div>';

            $res .= '<div style="display: none;" ';
            $res .= 'class="'.$output_mod->html_safe($src).'"><ul class="folders">';
            if ($name == 'Email') {
                $res .= '<li class="menu_email"><a class="unread_link" href="?page=message_list&amp;list_path=email">'.
                '<img class="account_icon" src="'.$output_mod->html_safe(Hm_Image_Sources::$globe).'" alt="" width="16" height="16" /> '.$output_mod->trans('All').'</a> <span class="unread_mail_count"></span></li>';
            }
            $cache = Hm_Page_Cache::get($src);
            Hm_Page_Cache::del($src);
            if ($cache) {
                $res .= $cache;
            }
            $res .= '</ul></div>';
        }
    }
    return $res;
}

function settings_menu( $input, $output_mod) {
    return '<div class="src_name" onclick="return toggle_section(\'.settings\');">Settings'.
        '<img class="menu_caret" src="'.Hm_Image_Sources::$chevron.'" alt="" width="8" height="8" />'.
        '</div><ul style="display: none;" class="settings folders">'.
        '<li class="menu_servers"><a class="unread_link" href="?page=servers">'.
        '<img class="account_icon" src="'.$output_mod->html_safe(Hm_Image_Sources::$monitor).'" alt="" width="16" height="16" /> '.$output_mod->trans('Servers').'</a></li>'.
        '<li class="menu_settings"><a class="unread_link" href="?page=settings">'.
        '<img class="account_icon" src="'.$output_mod->html_safe(Hm_Image_Sources::$cog).'" alt="" width="16" height="16" /> '.$output_mod->trans('Site').'</a></li>'.
        '<li class="menu_profiles"><a class="unread_link" href="?page=profiles">'.
        '<img class="account_icon" src="'.$output_mod->html_safe(Hm_Image_Sources::$people).'" alt="" width="16" height="16" /> '.$output_mod->trans('Profiles').'</a></li>'.
        '<li class="menu_help"><a class="unread_link" href="?page=help">'.
        '<img class="account_icon" src="'.$output_mod->html_safe(Hm_Image_Sources::$info).'" alt="" width="16" height="16" /> '.$output_mod->trans('Help').'</a></li>'.
        '<li class="menu_bug_report"><a class="unread_link" href="?page=bug_report">'.
        '<img class="account_icon" src="'.$output_mod->html_safe(Hm_Image_Sources::$bug).'" alt="" width="16" height="16" /> '.$output_mod->trans('FAIL').'</a></li>'.
        '<li class="menu_dev"><a class="unread_link" href="?page=dev">'.
        '<img class="account_icon" src="'.$output_mod->html_safe(Hm_Image_Sources::$code).'" alt="" width="16" height="16" /> '.$output_mod->trans('Dev').'</a></li>'.
        '</ul>';
}

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
        '-1 year' => 'Last year'
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
    $dt = sprintf('%s', strtolower($times[$since]));
    $max = sprintf('sources@%d each', $limit);

    return '<div class="list_meta">'.
        $output_mod->html_safe($dt).
        '<b>-</b>'.
        '<span class="src_count"></span> '.$output_mod->html_safe($max).
        '<b>-</b>'.
        '<span class="total"></span> total</div>';
}

function human_readable_interval($date_str) {
    $precision     = 2;
    $interval_time = array();
    $now           = time();
    $date          = strtotime($date_str);
    $interval      = $now - $date;
    $res           = array();

    $t['second'] = 1;
    $t['minute'] = $t['second']*60;
    $t['hour']   = $t['minute']*60;
    $t['day']    = $t['hour']*24;
    $t['week']   = $t['day']*7;
    $t['month']  = $t['day']*30;
    $t['year']   = $t['week']*52;

    if ($interval < 0) {
        $interval += $t['hour'];
        if ($interval < 0) {
            return 'From the future!';
        }
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

function message_list_row($subject, $date, $timestamp, $from, $source, $id, $flags, $style, $url, $output_mod) {
        if ($style == 'email') {
            return array(
                '<tr style="display: none;" class="'.$output_mod->html_safe(str_replace(' ', '-', $id)).'">'.
                    '<td class="checkbox_cell"><input type="checkbox" value="'.$output_mod->html_safe($id).'" /></td>'.
                    '<td class="source">'.$output_mod->html_safe($source).'</td>'.
                    '<td class="from">'.$output_mod->html_safe($from).'</td>'.
                    '<td class="subject"><div class="'.$output_mod->html_safe(implode(' ', $flags)).'">'.
                        '<a href="'.$output_mod->html_safe($url).'">'.$output_mod->html_safe($subject).'</a>'.
                    '</div></td>'.
                    '<td class="msg_date">'.$date.'<input type="hidden" class="msg_timestamp" value="'.$output_mod->html_safe($timestamp).'" /></td>'.
                    '<td class="icon">'.(in_array('flagged', $flags) ? '<img alt="" src="'.Hm_Image_Sources::$star.'" width="16" height="16" />' : '').'</td>'.
                '</tr>', str_replace(' ', '-', $id));
        }
        else {
            return array(
                '<tr style="display: none;" class="'.$output_mod->html_safe($id).'">'.
                    '<td class="news_cell checkbox_cell"><input type="checkbox" value="'.$output_mod->html_safe($id).'" /></td>'.
                    '<td class="news_cell"><div class="icon">'.(in_array('flagged', $flags) ? '<img alt="" src="'.Hm_Image_Sources::$star.'" width="16" height="16" />' : '').'</div>'.
                    '<div class="subject"><div class="'.$output_mod->html_safe(implode(' ', $flags)).'">'.
                        '<a href="'.$output_mod->html_safe($url).'">'.$output_mod->html_safe($subject).'</a>'.
                    '</div></div>'.
                    '<div class="from">'.$output_mod->html_safe($from).' '.$output_mod->html_safe($source).'</div>'.
                    '<div class="msg_date">'.$date.'<input type="hidden" class="msg_timestamp" value="'.$output_mod->html_safe($timestamp).'" /></div>'.
                '</td></tr>', $id);
        }
}

function message_controls() {
    return '<a class="toggle_link" href="#" onclick="return toggle_rows();"><img alt="x" src="'.Hm_Image_Sources::$check.'" width="8" height="8" /></a>'.
        '<div class="msg_controls">'.
        '<a href="#" onclick="return message_action(\'read\');">Read</a>'.
        '<a href="#" onclick="return message_action(\'unread\');">Unread</a>'.
        '<a href="#" onclick="return message_action(\'flag\');">Flag</a>'.
        '<a href="#" onclick="return message_action(\'unflag\');">Unflag</a>'.
        '<a href="#" onclick="return message_action(\'delete\');">Delete</a></div>';
}

function message_since_dropdown($since, $name) {
    $times = array(
        'today' => 'Today',
        '-1 week' => 'Last 7 days',
        '-2 weeks' => 'Last 2 weeks',
        '-4 weeks' => 'Last 4 weeks',
        '-6 weeks' => 'Last 6 weeks',
        '-6 months' => 'Last 6 months',
        '-1 year' => 'Last year'
    );
    $res = '<select name="'.$name.'" class="message_list_since">';
    foreach ($times as $val => $label) {
        $res .= '<option';
        if ($val == $since) {
            $res .= ' selected="selected"';
        }
        $res .= ' value="'.$val.'">'.$label.'</option>';
    }
    $res .= '</select>';
    return $res;
}

function process_since_argument($val, $validate=false) {
    $date = false;
    $valid = false;
    if (in_array($val, array('-1 week', '-2 weeks', '-4 weeks', '-6 weeks', '-6 months', '-1 year'))) {
        $valid = $val;
        $date = date('j-M-Y', strtotime($val));
    }
    else {
        $val = 'today';
        $valid = $val;
        $date = date('j-M-Y');
    }
    if ($validate) {
        return $valid;
    }
    return $date;
}

function format_msg_html($str, $external_resources=false) {
    require 'third_party/HTMLPurifier.standalone.php';
    $config = HTMLPurifier_Config::createDefault();
    $config->set('Cache.DefinitionImpl', null);
    if (!$external_resources) {
        $config->set('URI.DisableResources', true);
        $config->set('URI.DisableExternalResources', true);
        $config->set('URI.DisableExternal', true);
    }
    $config->set('HTML.TargetBlank', true);
    $config->set('Filter.ExtractStyleBlocks.TidyImpl', true);
    $purifier = new HTMLPurifier($config);
    $res = $purifier->purify($str);
    return $res;
}

function format_msg_image($str, $mime_type) {
    return '<img alt="" src="data:image/'.$mime_type.';base64,'.chunk_split(base64_encode($str)).'" />';
}

function format_msg_text($str, $output_mod, $links=true) {
    $str = nl2br(str_replace(' ', '&#160;&#8203;', ($output_mod->html_safe($str))));
    if ($links) {
        $link_regex = "/((http|ftp|rtsp)s?:\/\/(%[[:digit:]A-Fa-f][[:digit:]A-Fa-f]|[-_\.!~\*';\/\?#:@&=\+$,\[\]%[:alnum:]])+)/m";
        $str = preg_replace($link_regex, "<a target=\"_blank\" href=\"$1\">$1</a>", $str);
    }
    return $str;
}

function build_msg_gravatar($from) {
    if (preg_match("/[\S]+\@[\S]+/", $from, $matches)) {
        $hash = md5(strtolower(trim($matches[0], " \"><'\t\n\r\0\x0B")));
        return '<img alt="" class="gravatar" src="http://www.gravatar.com/avatar/'.$hash.'?d=mm" />';
    }
}

function display_value($name, $haystack, $type=false, $default='') {
    if (!array_key_exists($name, $haystack)) {
        return $default;
    }
    $value = $haystack[$name];
    $res = false;
    if ($type) {
        $name = $type;
    }
    switch($name) {
        case 'from':
            $value = preg_replace("/(\<.+\>)/U", '', $value);
            $res = str_replace('"', '', $value);
            break;
        case 'date':
            $res = human_readable_interval($value);
            break;
        case 'time':
            $res = strtotime($value);
            break;
        default:
            $res = $value;
            break;
    }
    return $res;
}

function validate_search_terms($terms) {
    $terms = trim(strip_tags($terms));
    if (!$terms) {
        $terms = false;
    }
    return $terms;
}

function validate_search_fld($fld) {
    if (in_array($fld, array('TEXT', 'BODY', 'FROM', 'SUBJECT'))) {
        return $fld;
    }
    return false;
}


function search_field_selection($current) {
    $flds = array(
        'TEXT' => 'Entire message',
        'BODY' => 'Message body',
        'SUBJECT' => 'Subject',
        'FROM' => 'From',
    );
    $res = '<select name="search_fld">';
    foreach ($flds as $val => $name) {
        $res .= '<option ';
        if ($current == $val) {
            $res .= 'selected="selected" ';
        }
        $res .= 'value="'.$val.'">'.$name.'</option>';
    }
    $res .= '</select>';
    return $res;
}


function search_form($data, $output_mod) {
    $terms = '';
    if (array_key_exists('search_terms', $data)) {
        $terms = $data['search_terms'];
    }
    $res = '<div class="search_form">'.
        '<form method="get"><input type="hidden" name="page" value="search" />'.
        ' <input type="text" class="search_terms" name="search_terms" value="'.$output_mod->html_safe($terms).'" />'.
        ' '.search_field_selection($data['search_fld']).
        ' '.message_since_dropdown($data['search_since'], 'search_since').
        ' <input type="submit" class="search_update" value="Go!" /></form></div>';
    return $res;
}

function format_reply_text($txt) {
    return '> '.str_replace("\n", "\n> ", $txt);
}

?>
