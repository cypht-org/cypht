<?php

/**
 * IMAP modules
 * @package modules
 * @subpackage imap
 */

if (!defined('DEBUG_MODE')) { die(); }

/**
 * Build a source list for sent folders
 * @subpackage imap/functions
 * @param string $callback javascript callback function name
 * @param array $configured user specific sent folders
 * @param string $inbox include inbox in search for auto-bcc messages
 * @return array
 */
if (!hm_exists('imap_sent_sources')) {
function imap_sent_sources($callback, $configured, $inbox) {
    $sources = array();
    foreach (Hm_IMAP_List::dump() as $index => $vals) {
        if (array_key_exists('hide', $vals) && $vals['hide']) {
            continue;
        }
        if (array_key_exists($index, $configured) && array_key_exists('sent', $configured[$index]) && $configured[$index]['sent']) {
            $sources[] = array('callback' => $callback, 'folder' => bin2hex($configured[$index]['sent']), 'type' => 'imap', 'name' => $vals['name'], 'id' => $index);
        }
        elseif ($inbox) {
            $sources[] = array('callback' => $callback, 'folder' => bin2hex('INBOX'), 'type' => 'imap', 'name' => $vals['name'], 'id' => $index);
        }
        else {
            $sources[] = array('callback' => $callback, 'folder' => bin2hex('SPECIAL_USE_CHECK'), 'nodisplay' => true, 'type' => 'imap', 'name' => $vals['name'], 'id' => $index);
        }
    }
    return $sources;
}}

/**
 * Build a source list
 * @subpackage imap/functions
 * @param string $callback javascript callback function name
 * @param array $custom user specific assignments
 * @return array
 */
if (!hm_exists('imap_data_sources')) {
function imap_data_sources($callback, $custom=array()) {
    $sources = array();
    foreach (Hm_IMAP_List::dump() as $index => $vals) {
        if (array_key_exists('hide', $vals) && $vals['hide']) {
            continue;
        }
        if (!array_key_exists('user', $vals)) {
            continue;
        }
        $sources[] = array('callback' => $callback, 'folder' => bin2hex('INBOX'), 'type' => 'imap', 'name' => $vals['name'], 'id' => $index);
    }
    foreach ($custom as $path => $type) {
        $parts = explode('_', $path, 3);
        $remove_id = false;

        if ($type == 'add') {
            $details = Hm_IMAP_List::dump($parts[1]);
            if ($details) {
                $sources[] = array('callback' => $callback, 'folder' => $parts[2], 'type' => 'imap', 'name' => $details['name'], 'id' => $parts[1]);
            }
        }
        elseif ($type == 'remove') {
            foreach ($sources as $index => $vals) {
                if ($vals['folder'] == $parts[2] && $vals['id'] == $parts[1]) {
                    $remove_id = $index;
                    break;
                }
            }
            if ($remove_id !== false) {
                unset($sources[$remove_id]);
            }
        }
    }
    return $sources;
}}

/**
 * Prepare and format message list data 
 * @subpackage imap/functions
 * @param array $msgs list of message headers to format
 * @param object $mod Hm_Output_Module
 * @return void
 */
if (!hm_exists('prepare_imap_message_list')) {
function prepare_imap_message_list($msgs, $mod, $type) {
    $style = $mod->get('news_list_style') ? 'news' : 'email';
    if ($mod->get('is_mobile')) {
        $style = 'news';
    }
    $res = format_imap_message_list($msgs, $mod, $type, $style);
    $mod->out('formatted_message_list', $res);
}}

/**
 * Build HTML for a list of IMAP folders
 * @subpackage imap/functions
 * @param array $folders list of folder data
 * @param mixed $id IMAP server id
 * @param object $mod Hm_Output_Module
 * @return string
 */
if (!hm_exists('format_imap_folder_section')) {
function format_imap_folder_section($folders, $id, $output_mod) {
    $results = '<ul class="inner_list">';
    $manage = $output_mod->get('imap_folder_manage_link');
    foreach ($folders as $folder_name => $folder) {
        $folder_name = bin2hex($folder_name);
        $results .= '<li class="imap_'.$id.'_'.$output_mod->html_safe($folder_name).'">';
        if ($folder['children']) {
            $results .= '<a href="#" class="imap_folder_link expand_link" data-target="imap_'.intval($id).'_'.$output_mod->html_safe($folder_name).'">+</a>';
        }
        else {
            $results .= ' <img class="folder_icon" src="'.Hm_Image_Sources::$folder.'" alt="" width="16" height="16" />';
        }
        if (!$folder['noselect']) {
            $results .= '<a data-id="imap_'.intval($id).'_'.$output_mod->html_safe($folder_name).
                '" href="?page=message_list&amp;list_path='.
                urlencode('imap_'.intval($id).'_'.$output_mod->html_safe($folder_name)).
                '">'.$output_mod->html_safe($folder['basename']).'</a>';
        }
        else {
            $results .= $output_mod->html_safe($folder['basename']);
        }
        $results .= '<span class="unread_count unread_imap_'.$id.'_'.$output_mod->html_safe($folder_name).'"></span></li>';
    }
    if ($manage) {
        $results .= '<li class="manage_folders_li"><a class="manage_folder_link" href="'.$manage.'"><img class="folder_icon manage_folder_icon" src="'.Hm_Image_Sources::$cog.'" alt="" width="16" height="16" />'.$output_mod->trans('Manage Folders').'</a>';
    }
    $results .= '</ul>';
    return $results;
}}

/**
 * Format a from/to field for message list display
 * @subpackage imap/functions
 * @param string $fld field to format
 * @return string
 */
if (!hm_exists('format_imap_from_fld')) {
function format_imap_from_fld($fld) {
    $res = array();
    foreach (process_address_fld($fld) as $vals) {
        if (trim($vals['label'])) {
            $res[] = $vals['label'];
        }
        elseif (trim($vals['email'])) {
            $res[] = $vals['email'];
        }
    }
    return implode(', ', $res);
}}

/**
 * Format a list of message headers
 * @subpackage imap/functions
 * @param array $msg_list list of message headers
 * @param object $mod Hm_Output_Module
 * @param mixed $parent_list parent list id
 * @param string $style list style (email or news)
 * @return array
 */
if (!hm_exists('format_imap_message_list')) {
function format_imap_message_list($msg_list, $output_module, $parent_list=false, $style='email') {
    $res = array();
    if ($msg_list === array(false)) {
        return $msg_list;
    }
    $show_icons = $output_module->get('msg_list_icons');
    $list_page = $output_module->get('list_page', 0);
    $list_sort = $output_module->get('list_sort', $output_module->get('default_sort_order'));
    $list_filter = $output_module->get('list_filter');
    foreach($msg_list as $msg) {
        $row_class = 'email';
        $icon = 'env_open';
        if (!$parent_list) {
            $parent_value = sprintf('imap_%d_%s', $msg['server_id'], $msg['folder']);
        }
        else {
            $parent_value = $parent_list;
        }
        $id = sprintf("imap_%s_%s_%s", $msg['server_id'], $msg['uid'], $msg['folder']);
        if (!trim($msg['subject'])) {
            $msg['subject'] = '[No Subject]';
        }
        $subject = $msg['subject'];
        if ($parent_list == 'sent') {
            $icon = 'sent';
            $from = $msg['to'];
        }
        else {
            $from = $msg['from'];
        }
        $from = format_imap_from_fld($from);
        $nofrom = '';
        if (!trim($from)) {
            $from = '[No From]';
            $nofrom = ' nofrom';
        }
        if ($list_sort == 'date') {
            $date_field = 'date';
        } else {
            $date_field = 'internal_date';
        }
        $timestamp = strtotime($msg[$date_field]);
        $date = translate_time_str(human_readable_interval($msg[$date_field]), $output_module);
        $flags = array();
        if (!stristr($msg['flags'], 'seen')) {
            $flags[] = 'unseen';
            $row_class .= ' unseen';
            if ($icon != 'sent') {
                $icon = 'env_closed';
            }
        }
        if (trim($msg['x_auto_bcc']) === 'cypht') {
            $from = preg_replace("/(\<.+\>)/U", '', $msg['to']);
            $icon = 'sent';
        }
        foreach (array('attachment', 'deleted', 'flagged', 'answered') as $flag) {
            if (stristr($msg['flags'], $flag)) {
                $flags[] = $flag;
            }
        }
        $source = $msg['server_name'];
        $row_class .= ' '.str_replace(' ', '_', $source);
        if ($msg['folder'] && hex2bin($msg['folder']) != 'INBOX') {
            $source .= '-'.preg_replace("/^INBOX.{1}/", '', hex2bin($msg['folder']));
        }
        $url = '?page=message&uid='.$msg['uid'].'&list_path='.sprintf('imap_%d_%s', $msg['server_id'], $msg['folder']).'&list_parent='.$parent_value;
        if ($list_page) {
            $url .= '&list_page='.$output_module->html_safe($list_page);
        }
        if ($list_sort) {
            $url .= '&sort='.$output_module->html_safe($list_sort);
        }
        if ($list_filter) {
            $url .= '&filter='.$output_module->html_safe($list_filter);
        }
        if (!$show_icons) {
            $icon = false;
        }
        if ($style == 'news') {
            $res[$id] = message_list_row(array(
                    array('checkbox_callback', $id),
                    array('icon_callback', $flags),
                    array('subject_callback', $subject, $url, $flags, $icon),
                    array('safe_output_callback', 'source', $source),
                    array('safe_output_callback', 'from'.$nofrom, $from),
                    array('date_callback', $date, $timestamp),
                ),
                $id,
                $style,
                $output_module,
                $row_class
            );
        }
        else {
            $res[$id] = message_list_row(array(
                    array('checkbox_callback', $id),
                    array('safe_output_callback', 'source', $source, $icon),
                    array('safe_output_callback', 'from'.$nofrom, $from),
                    array('subject_callback', $subject, $url, $flags),
                    array('date_callback', $date, $timestamp),
                    array('icon_callback', $flags)
                ),
                $id,
                $style,
                $output_module,
                $row_class
            );
        }
    }
    return $res;
}}

/**
 * Process message ids
 * @subpackage imap/functions
 * @param array $ids list of ids
 * @return array
 */
if (!hm_exists('process_imap_message_ids')) {
function process_imap_message_ids($ids) {
    $res = array();
    foreach (explode(',', $ids) as $id) {
        if (preg_match("/imap_(\S+)_(\S+)_(\S+)$/", $id, $matches)) {
            $server = $matches[1];
            $uid = $matches[2];
            $folder = $matches[3];
            if (!isset($res[$server])) {
                $res[$server] = array();
            }
            if (!isset($res[$server][$folder])) {
                $res[$server][$folder] = array();
            }
            $res[$server][$folder][] = $uid;
        }
    }
    return $res;
}}

/**
 * Format a message part row
 * @subpackage imap/functions
 * @param string $id message identifier
 * @param array $vals details of the message
 * @param object $mod Hm_Output_Module
 * @param int $level indention level
 * @param string $part currently selected part
 * @param string $dl_args base arguments for a download link URL
 * @param bool $use_icons flag to enable/disable message part icons
 * @param bool $simmple_view flag to hide complex message structure
 * @param bool $mobile flag to indicate a mobile browser
 * @return string
 */
if (!hm_exists('format_msg_part_row')) {
function format_msg_part_row($id, $vals, $output_mod, $level, $part, $dl_args, $use_icons=false, $simple_view=false, $mobile=false) {
    $allowed = array(
        'textplain',
        'texthtml',
        'messagedisposition-notification',
        'messagedelivery-status',
        'messagerfc822-headers',
        'textcsv',
        'textcss',
        'textunknown',
        'textx-vcard',
        'textcalendar',
        'textx-vcalendar',
        'textx-sql',
        'textx-comma-separated-values',
        'textenriched',
        'textrfc822-headers',
        'textx-diff',
        'textx-patch',
        'applicationpgp-signature',
        'applicationx-httpd-php',
        'imagepng',
        'imagesvg+xml',
        'imagejpg',
        'imagejpeg',
        'imagepjpeg',
        'imagegif',
    );
    $icons = array(
        'text' => 'doc',
        'image' => 'camera',
        'application' => 'save',
        'multipart' => 'folder',
        'audio' => 'audio',
        'video' => 'monitor',
        'binary' => 'save',

        'textx-vcard' => 'calendar',
        'textcalendar' => 'calendar',
        'textx-vcalendar' => 'calendar',
        'applicationics' => 'calendar',
        'multipartdigest' => 'spreadsheet',
        'applicationpgp-keys' => 'key',
        'applicationpgp-signature' => 'key',
        'multipartsigned' => 'lock',
        'messagerfc822' => 'env_open',
        'octetstream' => 'paperclip',
    );
    $hidden_parts= array(
        'multipartdigest',
        'multipartsigned',
        'multipartmixed',
        'messagerfc822',
    );
    $lc_type = strtolower($vals['type']).strtolower($vals['subtype']);
    if ($simple_view) {
        if (filter_message_part($vals)) {
            return '';
        }
        if (in_array($lc_type, $hidden_parts, true)) {
            return '';
        }
    }
    if ($level > 6) {
        $class = 'row_indent_max';
    }
    else {
        $class = 'row_indent_'.$level;
    }
    $desc = get_part_desc($vals, $id, $part);
    $size = get_imap_size($vals);
    $res = '<tr';
    if ($id == $part) {
        $res .= ' class="selected_part"';
    }
    $res .= '><td><div class="'.$class.'">';
    $icon = false;
    if ($use_icons && array_key_exists($lc_type, $icons)) {
        $icon = $icons[$lc_type];
    }
    elseif ($use_icons && array_key_exists(strtolower($vals['type']), $icons)) {
        $icon = $icons[strtolower($vals['type'])];
    }
    if ($icon) {
        $res .= '<img class="msg_part_icon" src="'.Hm_Image_Sources::$$icon.'" width="16" height="16" alt="'.$output_mod->trans('Attachment').'" /> ';
    }
    else {
        $res .= '<img class="msg_part_icon msg_part_placeholder" src="'.Hm_Image_Sources::$doc.'" width="16" height="16" alt="'.$output_mod->trans('Attachment').'" /> ';
    }
    if (in_array($lc_type, $allowed, true)) {
        $res .= '<a href="#" class="msg_part_link" data-message-part="'.$output_mod->html_safe($id).'">'.$output_mod->html_safe(strtolower($vals['type'])).
            ' / '.$output_mod->html_safe(strtolower($vals['subtype'])).'</a>';
    }
    else {
        $res .= $output_mod->html_safe(strtolower($vals['type'])).' / '.$output_mod->html_safe(strtolower($vals['subtype']));
    }
    if ($mobile) {
        $res .= '<div class="part_size">'.$output_mod->html_safe($size);
        $res .= '</div><div class="part_desc">'.$output_mod->html_safe(decode_fld($desc)).'</div>';
        $res .= '<div class="download_link"><a href="?'.$dl_args.'&amp;imap_msg_part='.$output_mod->html_safe($id).'">'.$output_mod->trans('Download').'</a></div></td></tr>';
    }
    else {
        $res .= '</td><td class="part_size">'.$output_mod->html_safe($size);
        if (!$simple_view) {
            $res .= '</td><td class="part_encoding">'.(isset($vals['encoding']) ? $output_mod->html_safe(strtolower($vals['encoding'])) : '').
                '</td><td class="part_charset">'.(isset($vals['attributes']['charset']) && trim($vals['attributes']['charset']) ? $output_mod->html_safe(strtolower($vals['attributes']['charset'])) : '');
        }
        $res .= '</td><td class="part_desc">'.$output_mod->html_safe(decode_fld($desc)).'</td>';
        $res .= '<td class="download_link"><a href="?'.$dl_args.'&amp;imap_msg_part='.$output_mod->html_safe($id).'">'.$output_mod->trans('Download').'</a></td></tr>';
    }
    return $res;
}}

/*
 * Find a message part description/filename
 * @param array $vals bodystructure info for this message part
 * @param int $uid message number
 * @param string $part_id message part number
 * @return string
 */
if (!hm_exists('get_part_desc')) {
function get_part_desc($vals, $id, $part) {
    $desc = '';
    if (isset($vals['description']) && trim($vals['description'])) {
        $desc = $vals['description'];
    }
    elseif (isset($vals['name']) && trim($vals['name'])) {
        $desc = $vals['name'];
    }
    elseif (isset($vals['filename']) && trim($vals['filename'])) {
        $desc = $vals['filename'];
    }
    elseif (isset($vals['envelope']['subject']) && trim($vals['envelope']['subject'])) {
        $desc = $vals['envelope']['subject'];
    }
    $filename = get_imap_part_name($vals, $id, $part, true);
    if (!$desc && $filename) {
        $desc = $filename;
    }
    return $desc;
}}

/*
 * Get a human readable message size
 * @param array $vals bodystructure info for this message part
 * @return string
 */
if (!hm_exists('get_imap_size')) {
function get_imap_size($vals) {
    if (!array_key_exists('size', $vals) || !$vals['size']) {
        return '';
    }
    $size = intval($vals['size']);
    switch (true) {
        case $size > 1000:
            $size = $size/1000;
            $label = 'KB';
            break;
        case $size > 1000000:
            $size = $size/1000000;
            $label = 'MB';
            break;
        case $size > 1000000000:
            $size = $size/1000000000;
            $label = 'GB';
            break;
        default:
            $label = 'B';
    }
    return sprintf('%s %s', round($size, 2), $label);
}}

/**
 * Format the message part section of the message view page
 * @subpackage imap/functions
 * @param array $struct message structure
 * @param object $mod Hm_Output_Module
 * @param string $part currently selected message part id
 * @param string $dl_link base arguments for a download link
 * @param int $level indention level
 * @return string
 */
if (!hm_exists('format_msg_part_section')) {
function format_msg_part_section($struct, $output_mod, $part, $dl_link, $level=0) {
    $res = '';
    $simple_view = $output_mod->get('simple_msg_part_view', false);
    $use_icons = $output_mod->get('use_message_part_icons', false);
    $mobile = $output_mod->get('is_mobile');
    if ($mobile) {
        $simple_view = true;
    }
    foreach ($struct as $id => $vals) {
        if (is_array($vals) && isset($vals['type'])) {
            $row = format_msg_part_row($id, $vals, $output_mod, $level, $part, $dl_link, $use_icons, $simple_view, $mobile);
            if (!$row) {
                $level--;
            }
            $res .= $row;
            if (isset($vals['subs'])) {
                $res .= format_msg_part_section($vals['subs'], $output_mod, $part, $dl_link, ($level + 1));
            }
        }
        else {
            if (is_array($vals) && count($vals) == 1 && isset($vals['subs'])) {
                $res .= format_msg_part_section($vals['subs'], $output_mod, $part, $dl_link, $level);
            }
        }
    }
    return $res;
}}

/**
 * Filter out message parts that are not attachments
 * @param array message structure
 * @return bool
 */
if (!hm_exists('filter_message_part')) {
function filter_message_part($vals) {
    if (array_key_exists('disposition', $vals) && is_array($vals['disposition']) && array_key_exists('inline', $vals['disposition'])) {
        return true;
    }
    if (array_key_exists('type', $vals) && $vals['type'] == 'multipart') {
        return true;
    }
    return false;
}}

/**
 * Sort callback to sort by internal date
 * @subpackage imap/functions
 * @param array $a first message detail
 * @param array $b second message detail
 * @return int
 */
if (!hm_exists('sort_by_internal_date')) {
function sort_by_internal_date($a, $b) {
    if ($a['internal_date'] == $b['internal_date']) return 0;
    return (strtotime($a['internal_date']) < strtotime($b['internal_date']))? -1 : 1;
}}

/**
 * Merge IMAP search results
 * @subpackage imap/functions
 * @param array $ids IMAP server ids
 * @param string $search_type
 * @param object $session session object
 * @param object $hm_cache cache object
 * @param array $folders list of folders to search
 * @param int $limit max results
 * @param array $terms list of search terms
 * @param bool $sent flag to fetch auto-bcc'ed messages
 * @return array
 */
if (!hm_exists('merge_imap_search_results')) {
function merge_imap_search_results($ids, $search_type, $session, $hm_cache, $folders = array('INBOX'), $limit=0, $terms=array(), $sent=false) {
    $msg_list = array();
    $connection_failed = false;
    $sent_results = array();
    $status = array();
    foreach($ids as $index => $id) {
        $id = intval($id);
        $cache = Hm_IMAP_List::get_cache($hm_cache, $id);
        $imap = Hm_IMAP_List::connect($id, $cache);
        if (imap_authed($imap)) {
            $server_details = Hm_IMAP_List::dump($id);
            $folder = $folders[$index];
            if ($sent) {
                $sent_folder = $imap->get_special_use_mailboxes('sent');
                if (array_key_exists('sent', $sent_folder)) {
                    list($sent_status, $sent_results) = merge_imap_search_results($ids, $search_type, $session, $hm_cache, array($sent_folder['sent']), $limit, $terms, false);
                    $status = array_merge($status, $sent_status);
                }
                if ($folder == 'SPECIAL_USE_CHECK') {
                    continue;
                }
            }
            if ($imap->select_mailbox($folder)) {
                $status['imap_'.$id.'_'.bin2hex($folder)] = $imap->folder_state;
                if (!empty($terms)) {
                    foreach ($terms as $term) {
                        if (preg_match('/(?:[^\x00-\x7F])/', $term[1]) === 1) {
                            $imap->search_charset = 'UTF-8';
                            break;
                        }
                    }
                    if ($sent) {
                        $msgs = $imap->search($search_type, false, $terms, array(), true, false, true);
                    }
                    else {
                        $msgs = $imap->search($search_type, false, $terms);
                    }
                }
                else {
                    $msgs = $imap->search($search_type);
                }
                if ($msgs) {
                    if ($limit) {
                        rsort($msgs);
                        $msgs = array_slice($msgs, 0, $limit);
                    }
                    foreach ($imap->get_message_list($msgs) as $msg) {
                        if (array_key_exists('content-type', $msg) && stristr($msg['content-type'], 'multipart/mixed')) {
                            $msg['flags'] .= ' \Attachment';
                        }
                        if (stristr($msg['flags'], 'deleted')) {
                            continue;
                        }
                        $msg['server_id'] = $id;
                        $msg['folder'] = bin2hex($folder);
                        $msg['server_name'] = $server_details['name'];
                        $msg_list[] = $msg;
                    }
                }
            }
        }
        else {
            $connection_failed = true;
        }
    }
    $session->set('imap_folder_status', $status);
    if ($connection_failed && empty($msg_list)) {
        return array(array(), false);
    }
    if (count($sent_results) > 0) {
        $msg_list = array_merge($msg_list, $sent_results);
    }
    return array($status, $msg_list);
}}

/**
 * Replace inline images in an HTML message part
 * @subpackage imap/functions
 * @param string $txt HTML
 * @param string $uid message id
 * @param array $struct message structure array
 * @param object $imap IMAP server object
 */
if (!hm_exists('add_attached_images')) {
function add_attached_images($txt, $uid, $struct, $imap) {
    if (preg_match_all("/src=('|\"|)cid:([^\s'\"]+)/", $txt, $matches)) {
        $cids = array_pop($matches);
        foreach ($cids as $id) {
            $part = $imap->search_bodystructure($struct, array('id' => $id, 'type' => 'image'), true);
            $part_ids = array_keys($part);
            $part_id = array_pop($part_ids);
            $img = $imap->get_message_content($uid, $part_id, false, $part[$part_id]);
            $txt = str_replace('cid:'.$id, 'data:image/'.$part[$part_id]['subtype'].';base64,'.base64_encode($img), $txt);
        }
    }
    return $txt;
}}

/**
 * Check for and do an Oauth2 token reset if needed
 * @subpackage imap/functions
 * @param array $server imap server data
 * @param object $config site config object
 * @return mixed
 */
if (!hm_exists('imap_refresh_oauth2_token')) {
function imap_refresh_oauth2_token($server, $config) {
    if ((int) $server['expiration'] <= time()) {
        $oauth2_data = get_oauth2_data($config);
        $details = array();
        if ($server['server'] == 'imap.gmail.com') {
            $details = $oauth2_data['gmail'];
        }
        elseif ($server['server'] == 'imap-mail.outlook.com') {
            $details = $oauth2_data['outlook'];
        }
        if (!empty($details)) {
            $oauth2 = new Hm_Oauth2($details['client_id'], $details['client_secret'], $details['client_uri']);
            $result = $oauth2->refresh_token($details['refresh_uri'], $server['refresh_token']);
            if (array_key_exists('access_token', $result)) {
                return array(strtotime(sprintf('+%d seconds', $result['expires_in'])), $result['access_token']);
            }
        }
    }
    return array();
}}

/**
 * Copy/Move messages on the same IMAP server
 * @subpackage imap/functions
 * @param array $ids list of message ids with server and folder info
 * @param string $action action type, copy or move
 * @param object $hm_cache system cache
 * @param array $dest_path imap id and folder to copy/move to
 * @return int count of messages moved
 */
if (!hm_exists('imap_move_same_server')) {
function imap_move_same_server($ids, $action, $hm_cache, $dest_path) {
    $moved = array();
    $keys = array_keys($ids);
    $server_id = array_pop($keys);
    $cache = Hm_IMAP_List::get_cache($hm_cache, $server_id);
    $imap = Hm_IMAP_List::connect($server_id, $cache);
    foreach ($ids[$server_id] as $folder => $msgs) {
        if (imap_authed($imap) && $imap->select_mailbox(hex2bin($folder))) {
            if ($imap->message_action(strtoupper($action), $msgs, hex2bin($dest_path[2]))) {
                foreach ($msgs as $msg) {
                    $moved[]  = sprintf('imap_%s_%s_%s', $server_id, $msg, $folder);
                }
            }
        }
    }
    return $moved;
}}

/**
 * Copy/Move messages on different IMAP servers
 * @subpackage imap/functions
 * @param array $ids list of message ids with server and folder info
 * @param string $action action type, copy or move
 * @param array $dest_path imap id and folder to copy/move to
 * @param object $hm_cache cache interface
 * @return int count of messages moved
 */
if (!hm_exists('imap_move_different_server')) {
function imap_move_different_server($ids, $action, $dest_path, $hm_cache) {
    $moved = array();
    $cache = Hm_IMAP_List::get_cache($hm_cache, $dest_path[1]);
    $dest_imap = Hm_IMAP_List::connect($dest_path[1], $cache);
    if ($dest_imap) {
        foreach ($ids as $server_id => $folders) {
            $cache = Hm_IMAP_List::get_cache($hm_cache, $server_id);
            $imap = Hm_IMAP_List::connect($server_id, $cache);
            foreach ($folders as $folder => $msg_ids) {
                if (imap_authed($imap) && $imap->select_mailbox(hex2bin($folder))) {
                    foreach ($msg_ids as $msg_id) {
                        $detail = $imap->get_message_list(array($msg_id));
                        if (array_key_exists($msg_id, $detail)) {
                            if (stristr($detail[$msg_id]['flags'], 'seen')) {
                                $seen = true;
                            }
                            else {
                                $seen = false;
                            }
                        }
                        $msg = $imap->get_message_content($msg_id, 0);
                        $msg = str_replace("\r\n", "\n", $msg);
                        $msg = str_replace("\n", "\r\n", $msg);
                        $msg = rtrim($msg)."\r\n";
                        if (!$seen) {
                            $imap->message_action('UNREAD', array($msg_id));
                        }
                        if ($dest_imap->append_start(hex2bin($dest_path[2]), strlen($msg), $seen)) {
                            $dest_imap->append_feed($msg."\r\n");
                            if ($dest_imap->append_end()) {
                                if ($action == 'move') {
                                    if ($imap->message_action('DELETE', array($msg_id))) {
                                        $imap->message_action('EXPUNGE', array($msg_id));
                                    }
                                }
                                $moved[] = sprintf('imap_%s_%s_%s', $server_id, $msg_id, $folder);
                            }
                        }
                    }
                }
            }
        }
    }
    return $moved;
}}

/**
 * Group info about move/copy messages
 * @subpackage imap/functions
 * @param array $form move copy input
 * @return array grouped lists of messages to move/copy
 */
if (!hm_exists('process_move_to_arguments')) {
function process_move_to_arguments($form) {
    $msg_ids = explode(',', $form['imap_move_ids']);
    $same_server_ids = array();
    $other_server_ids = array();
    $dest_path = explode('_', $form['imap_move_to']);
    if (count($dest_path) == 3 && $dest_path[0] == 'imap' && in_array($form['imap_move_action'], array('move', 'copy'), true)) {
        foreach ($msg_ids as $msg_id) {
            $path = explode('_', $msg_id);
            if (count($path) == 4 && $path[0] == 'imap') {
                if (sprintf('%s_%s', $path[0], $path[1]) == sprintf('%s_%s', $dest_path[0], $dest_path[1])) {
                    $same_server_ids[$path[1]][$path[3]][] = $path[2];
                }
                else {
                    $other_server_ids[$path[1]][$path[3]][] = $path[2];
                }
            }
        }
    }
    return array($msg_ids, $dest_path, $same_server_ids, $other_server_ids);
}}

/**
 * Get a file extension for a mime type
 * @subpackage imap/functions
 * @param string $type primary mime type
 * @param string $subtype secondary mime type
 * @todo add tons more type conversions!
 * @return string
 */
if (!hm_exists('get_imap_mime_extension')) {
function get_imap_mime_extension($type, $subtype) {
    $extension = $subtype;
    if ($type == 'multipart' || ($type == 'message' && $subtype == 'rfc822')) {
        $extension = 'eml';
    }
    if ($type == 'text') {
        switch ($subtype) {
            case 'plain':
                $extension = 'txt';
                break;
            case 'richtext':
                $extension = 'rtf';
                break;
        }
    }
    return '.'.$extension;
}}

/**
 * Try to find a filename for a message part download
 * @subpackage imap/functions
 * @param array $struct message part structure
 * @param int $uid message number
 * @param string $part_id message part number
 * @param bool $no_default don't return a default value
 * @return string
 */
if (!hm_exists('get_imap_part_name')) {
function get_imap_part_name($struct, $uid, $part_id, $no_default=false) {
    $extension = get_imap_mime_extension(strtolower($struct['type']), strtolower($struct['subtype']));
    if (array_key_exists('file_attributes', $struct) && is_array($struct['file_attributes']) &&
        array_key_exists('attachment', $struct['file_attributes']) && is_array($struct['file_attributes']['attachment'])) {
        for ($i=0;$i<count($struct['file_attributes']['attachment']);$i++) {
            if (strtolower(trim($struct['file_attributes']['attachment'][$i])) == 'filename') {
                if (array_key_exists(($i+1), $struct['file_attributes']['attachment'])) {
                    return trim($struct['file_attributes']['attachment'][($i+1)]);
                }
            }
        }
    }

    if (array_key_exists('disposition', $struct) && is_array($struct['disposition']) && array_key_exists('attachment', $struct['disposition'])) {
        for ($i=0;$i<count($struct['disposition']['attachment']);$i++) {
            if (strtolower(trim($struct['disposition']['attachment'][$i])) == 'filename') {
                if (array_key_exists(($i+1), $struct['disposition']['attachment'])) {
                    return trim($struct['disposition']['attachment'][($i+1)]);
                }
            }
        }
    }

    if (array_key_exists('attributes', $struct) && is_array($struct['attributes']) && array_key_exists('name', $struct['attributes'])) {
        return trim($struct['attributes']['name']);
    }
    if (array_key_exists('description', $struct) && trim($struct['description'])) {
        return trim(str_replace(array("\n", ' '), '_', $struct['description'])).$extension;
    }
    if (array_key_exists('name', $struct) && trim($struct['name'])) {
        return trim($struct['name']);
    }
    if ($no_default) {
        return '';
    }
    return 'message_'.$uid.'_part_'.$part_id.$extension;
}}

/**
 * @subpackage imap/functions
 */
if (!hm_exists('clear_existing_reply_details')) {
function clear_existing_reply_details($session) {
    foreach ($session->dump() as $name => $val) {
        if (substr($name, 0, 19) == 'reply_details_imap_') {
            $session->del($name);
        }
    }
}}

/**
 * @subpackage imap/functions
 * @param object $imap imap library object
 * @return bool
 */
if (!hm_exists('imap_authed')) {
function imap_authed($imap) {
    return is_object($imap) && ($imap->get_state() == 'authenticated' || $imap->get_state() == 'selected');
}}

/**
 * @subpackage imap/functions
 */
if (!hm_exists('process_sort_arg')) {
function process_sort_arg($sort, $default = 'arrival') {
    if (!$sort) {
        $default = strtoupper($default);
        return array($default, true);
    }
    $rev = false;
    if (substr($sort, 0, 1) == '-') {
        $rev = true;
        $sort = substr($sort, 1);
    }
    $sort = strtoupper($sort);
    if ($sort == 'ARRIVAL' || $sort == 'DATE') {
        $rev = $rev ? false : true;
    }
    return array($sort, $rev);
}}

/**
 * @subpackage imap/functions
 */
if (!hm_exists('imap_server_type')) {
function imap_server_type($id) {
    $type = 'IMAP';
    $details = Hm_IMAP_List::dump($id);
    if (is_array($details) && array_key_exists('type', $details)) {
        $type = strtoupper($details['type']);
    }
    return $type;
}}

/**
 * @subpackage imap/functions
 */
if (!hm_exists('get_list_headers')) {
function get_list_headers($headers) {
    $res = array();
    $list_headers = array('list-archive', 'list-unsubscribe',
        'list-subscribe', 'list-archive', 'list-post', 'list-help');
    foreach (lc_headers($headers) as $name => $val) {
        if (in_array($name, $list_headers, true)) {
            $res[substr($name, 5)] = process_list_fld($val);
        }
    }
    return $res;
}}

/**
 * @subpackage imap/functions
 */
if (!hm_exists('process_list_fld')) {
function process_list_fld($fld) {
    $res = array('links' => array(), 'email' => array(), 'values' => array());
    foreach (explode(',', $fld) as $val) {
        $val = trim(str_replace(array('<', '>'), '', $val));
        if (preg_match("/^http/", $val)) {
            $res['links'][] = $val;
        }
        elseif (preg_match("/^mailto/", $val)) {
            $res['email'][] = substr($val, 7);
        }
        else {
            $res['values'][] = $val;
        }
    }
    return $res;
}}

/**
 * @subpackage imap/functions
 */
if (!hm_exists('format_imap_envelope')) {
function format_imap_envelope($env, $mod) {
    $env = lc_headers($env);
    $res = '<table class="imap_envelope"><colgroup><col class="header_name_col"><col class="header_val_col"></colgroup>';
    if (array_key_exists('subject', $env) && trim($env['subject'])) {
        $res .= '<tr class="header_subject"><th colspan="2">'.$mod->html_safe($env['subject']).
            '</th></tr>';
    }

    foreach ($env as $name => $val) {
        if (in_array($name, array('date', 'from', 'to', 'message-id'), true)) {
            $res .= '<tr><th>'.$mod->html_safe(ucfirst($name)).'</th>'.
                '<td>'.$mod->html_safe($val).'</td></tr>';
        }
    }
    $res .= '</table>';
    return $res;
}}

/**
 * @subpackage imap/functions
 */
if (!hm_exists('format_list_headers')) {
function format_list_headers($mod) {
    $res = '<tr><th>'.$mod->trans('List').'</th><td>';
    $sections = array();
    foreach ($mod->get('list_headers') as $name => $vals) {
        if (count($vals['email']) > 0 || count($vals['links']) > 0) {
            $sources = array();
            $section = ' '.$mod->html_safe($name).': ';
            foreach ($vals['email'] as $v) {
                $sources[] = '<a href="?page=compose&compose_to='.urlencode($mod->html_safe($v)).
                    '">'.$mod->trans('email').'</a>';
            }
            foreach ($vals['links'] as $v) {
                $sources[] = '<a href="'.$mod->html_safe($v).'">'.$mod->trans('link').'</a>';
            }
            $section .= implode(', ', $sources);
            $sections[] = $section;
        }
    }
    $res .= implode(' | ', $sections).'</td></tr>';
    return $res;
}}

/**
 * @subpackage imap/functions
 */
if (!hm_exists('decode_folder_str')) {
function decode_folder_str($folder) {
    $folder_name = false;
    $parts = explode('_', $folder, 3);
    if (count($parts) == 3) {
        $folder_name = hex2bin($parts[2]);
    }
    return $folder_name;
}}

/**
 * @subpackage imap/functions
 */
if (!hm_exists('prep_folder_name')) {
function prep_folder_name($imap, $folder, $decode_folder=false, $parent=false) {
    if ($parent && $decode_folder) {
        $parent = decode_folder_str($parent);
    }
    if ($decode_folder) {
        $folder = decode_folder_str($folder);
    }
    $ns = get_personal_ns($imap);
    if (!$folder) {
        return false;
    }
    if ($parent && !$ns['delim']) {
        return false;
    }
    if ($parent) {
        $folder = sprintf('%s%s%s', $parent, $ns['delim'], $folder);
    }
    if ($folder && $ns['prefix'] && substr($folder, 0, strlen($ns['prefix'])) !== $ns['prefix']) {
        $folder = sprintf('%s%s', $ns['prefix'], $folder);
    }
    return $folder;
}}

/**
 * @subpackage imap/functions
 */
if (!hm_exists('get_personal_ns')) {
function get_personal_ns($imap) {
    $namespaces = $imap->get_namespaces();
    foreach ($namespaces as $ns) {
        if ($ns['class'] == 'personal') {
            return $ns;
        }
    }
    return array(
        'prefix' => false,
        'delim'=> false
    );
}}

