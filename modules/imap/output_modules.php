<?php

/**
 * IMAP modules
 * @package modules
 * @subpackage imap
 */

if (!defined('DEBUG_MODE')) { die(); }

/**
 * Format a custom list controls section
 * @subpackage imap/output
 */
class Hm_Output_imap_custom_controls extends Hm_Output_Module {
    /**
     * Adds list controls to the IMAP folder page view
     */
    protected function output() {
        if ($this->get('custom_list_controls_type')) {
            $filter = $this->get('list_filter');
            $sort = $this->get('list_sort');
            $keyword = $this->get('list_keyword');
            $opts = array('all' => $this->trans('All'), 'unseen' => $this->trans('Unread'),
                'seen' => $this->trans('Read'), 'flagged' => $this->trans('Flagged'),
                'unflagged' => $this->trans('Unflagged'), 'answered' => $this->trans('Answered'),
                'unanswered' => $this->trans('Unanswered'));

            $default_sort_order = $this->get('default_sort_order');
            if ($default_sort_order == 'arrival') {
                $sorts = array('arrival' => $this->trans('Arrival Date'), 'from' => $this->trans('From'),
                    'to' => $this->trans('To'), 'subject' => $this->trans('Subject'), 'date' => $this->trans('Sent Date'));
            } else {
                $sorts = array('date' => $this->trans('Sent Date'), 'from' => $this->trans('From'),
                    'to' => $this->trans('To'), 'subject' => $this->trans('Subject'), 'arrival' => $this->trans('Arrival Date'));
            }

            $custom = '<form id="imap_filter_form" method="GET" class="d-flex align-content-center">';
            $custom .= '<input type="hidden" name="page" value="message_list" />';
            $custom .= '<input type="hidden" name="list_path" value="'.$this->html_safe($this->get('list_path')).'" />';
            $custom .= '<input type="search" placeholder="'.$this->trans('Search').
                '" class="imap_keyword form-control form-control-sm" name="keyword" value="'.$this->html_safe($keyword).'" />';
            $custom .= '<select name="sort" class="imap_sort form-control form-control-sm">';
            foreach ($sorts as $name => $val) {
                $custom .= '<option ';
                if ($name == $sort) {
                    $custom .= 'selected="selected" ';
                }
                $custom .= 'value="'.$name.'">'.$val.' &darr;</option>';
                $custom .= '<option ';
                if ('-'.$name == $sort) {
                    $custom .= 'selected="selected" ';
                }
                $custom .= 'value="-'.$name.'">'.$val.' &uarr;</option>';
            }
            $custom .= '</select>';

            $custom .= '<select name="filter" class="imap_filter form-control form-control-sm">';
            foreach ($opts as $name => $val) {
                $custom .= '<option ';
                if ($name == $filter) {
                    $custom .= 'selected="selected" ';
                }
                $custom .= 'value="'.$name.'">'.$val.'</option>';
            }
            $custom .= '</select></form>';

            if ($this->get('custom_list_controls_type') == 'remove') {
                $custom .= '<a class="remove_source" title="'.$this->trans('Remove this folder from combined pages').
                    '" href=""><i class="bi bi-x-circle-fill refresh_list"></i></a><a style="display: none;" class="add_source" title="'.
                    $this->trans('Add this folder to combined pages').'" href=""><i class="bi bi-check-circle-fill refresh_list"></i></a>';
            }
            else {
                $custom .= '<a style="display: none;" class="remove_source" title="'.$this->trans('Remove this folder from combined pages').
                    '" href=""><i class="bi bi-x-circle-fill refresh_list"></i></a><a class="add_source" title="'.$this->trans('Add this folder to combined pages').
                    '" href=""><i class="bi bi-check-circle-fill refresh_list"></i></a>';
            }
            if ($this->get('folder')) {
                if ($this->get('screen_emails')) {
                    $custom .= '<span title="Disike" class="screen-email-unlike cursor-pointer"><i class="bi bi-hand-thumbs-down-fill"></i></span><span title="Like" class="screen-email-like cursor-pointer"></i><i class="bi bi-hand-thumbs-up-fill"></i></span>';
                    if ($this->get('move_messages_in_screen_email')) {
                        $custom .= '<input type="hidden" value="1" id="move_messages_in_screen_email"/>';
                    } else {
                        $custom .= '<input type="hidden" value="0" id="move_messages_in_screen_email"/>';
                    }
                } else {
                    $path = sprintf('?page=message_list&list_path=%s&screen_emails=1', $this->html_safe($this->get('list_path')));
                    $custom .= '<a title="'.sprintf($this->trans('Screen %s first emails'), $this->get('first_time_screen_emails')).
                    '" href="'. $path .'"><i class="bi bi-hand-thumbs-up-fill"></i></a>';
                }
            }
            $this->out('custom_list_controls', $custom);
        }
    }
}

/**
 * Format a message part body for display
 * @subpackage imap/output
 */
class Hm_Output_filter_message_body extends Hm_Output_Module {
    /**
     * Format html, text, or image content
     */

    protected function output() {
        $txt = '<div class="msg_text_inner">';
        if ($this->get('msg_text')) {
            $struct = $this->get('msg_struct_current', array());
            if (array_key_exists('envelope', $struct) && is_array($struct['envelope']) && count($struct['envelope']) > 0) {
                $txt .= format_imap_envelope($struct['envelope'], $this);
            }
            if (isset($struct['subtype']) && mb_strtolower($struct['subtype']) == 'html') {
                $allowed = $this->get('header_allow_images');
                $msgText = $this->get('msg_text');
                // Everything in the message starting with src="http:// or src="https:// or src='http:// or src='https://
                $externalResRegexp = '/src="(https?:\/\/[^"]*)"|src=\'(https?:\/\/[^\']*)\'/i';

                if ($allowed) {
                    $images_whitelist = $this->get('images_whitelist');
                    $sender_email = $this->get('sender_email');
                    if (! in_array($sender_email, $images_whitelist)) {
                        $msgText = preg_replace_callback($externalResRegexp, function ($matches) {
                            return 'data-src="' . $matches[1] . '" ' . 'src="" ' . 'data-message-part="' . $this->html_safe($this->get('imap_msg_part')) . '"';
                        }, $msgText);
                    }
                }

                $msgText = sanitize_email_html($msgText);
                $txt .= format_msg_html($msgText, $allowed);
            }
            elseif (isset($struct['type']) && mb_strtolower($struct['type']) == 'image') {
                $txt .= format_msg_image($this->get('msg_text'), mb_strtolower($struct['subtype']));
            }
            else {
                if ($this->get('imap_msg_part') === "0") {
                    $txt .= format_msg_text($this->get('msg_text'), $this, false);
                }
                else {
                    $txt .= format_msg_text($this->get('msg_text'), $this);
                }
            }
        }
        $txt .= '</div>';
        $this->out('msg_text', $txt);
    }
}

/**
 * Format the message part section of the message view
 * @subpackage imap/output
 */
class Hm_Output_filter_message_struct extends Hm_Output_Module {
    /**
     * Build message part section HTML
     */
    protected function output() {
        if ($this->get('msg_struct')) {
            $res = '<table class="msg_parts">';
            if (!$this->get('is_mobile')) {
                $res .= '<colgroup><col class="msg_part_mime"><col class="msg_part_size">';
                $res .= '<col class="msg_part_encoding"><col class="msg_part_charset"><col class="msg_part_desc">';
                $res .= '<col class="msg_part_download"></colgroup>';
            }
            $part = $this->get('imap_msg_part', '1');
            $args = $this->get('msg_download_args', '');
            $att_args = $this->get('msg_attachment_remove_args', '');
            $showMsgArgs = $this->get('msg_show_args', '');
            $res .=  format_msg_part_section($this->get('msg_struct'), $this, $part, $args, $att_args);
            $res .= '</table>';
            $res .= format_attached_image_section($this->get('msg_struct'), $this, $showMsgArgs);
            $this->out('msg_parts', $res);
        }
    }
}

/**
 * Format the message headers section of the message view
 * @subpackage imap/output
 */
class Hm_Output_filter_message_headers extends Hm_Output_Module {
    /**
     * Build message header HTML
     */
    protected function output() {
        if ($this->get('msg_headers')) {
            $txt = '';
            $small_headers = array('subject', 'x-snoozed', 'date', 'from', 'to', 'reply-to', 'cc', 'flags');
            $reply_args = sprintf('&amp;list_path=%s&amp;uid=%s',
                $this->html_safe($this->get('msg_list_path')),
                $this->html_safe($this->get('msg_text_uid'))
            );
            $msg_part = $this->get('imap_msg_part');
            $headers = $this->get('msg_headers', array());
            if (!array_key_exists('subject', lc_headers($headers)) || !trim(lc_headers($headers)['subject'])) {
                $headers['subject'] = $this->trans('[No Subject]');
            }
            $txt .= '<table class="msg_headers"><colgroup><col class="header_name_col"><col class="header_val_col"></colgroup>';
            foreach ($small_headers as $fld) {
                foreach ($headers as $name => $value) {
                    if ($fld == mb_strtolower($name)) {
                        if ($fld == 'subject') {
                            $txt .= '<tr class="header_'.$fld.'"><th colspan="2">';
                            if (isset($headers['Flags']) && mb_stristr($headers['Flags'], 'flagged')) {
                                $txt .= ' <i class="bi bi-star-half account_icon"></i> ';
                            }
                            $txt .= $this->html_safe($value).'</th></tr>';
                        }
                        elseif ($fld == 'x-snoozed') {
                            $snooze_header = parse_delayed_header($value, 'X-Snoozed');
                            $txt .= '<tr class="header_'.$fld.'"><th>';
                            $txt .= $this->trans('Snoozed').'</th><td>'.$this->trans('Until').' '.$this->html_safe($snooze_header['until']).' <a href="#" data-value="unsnooze" class="unsnooze nexter_date_helper">Unsnooze</a></td></tr>';
                        }
                        elseif ($fld == 'date') {
                            try {
                                $dt = new DateTime($value);
                                $value = sprintf('%s (%s)', $dt->format('c Z'), human_readable_interval($value));
                            } catch (Exception $e) {}
                            $txt .= '<tr class="header_'.$fld.'"><th>'.$this->trans($name).'</th><td>'.$this->html_safe($value).'</td></tr>';
                            }
                        elseif($fld == 'from'){

                            $regexp = '/\s*(.*[^\s])\s*<\s*(.*[^\s])\s*>/';

                            $contact_email = "";
                            $contact_name = "";

                            if(preg_match($regexp, $value, $matches)){
                                $contact_name = $matches[1];
                                $contact_email =  $matches[2];
                            }else{
                                $EmailRegexp = "/[\._a-zA-Z0-9-]+@[\._a-zA-Z0-9-]+/i";
                                if(preg_match($EmailRegexp, $value, $matches)){
                                    $contact_email = $matches[0];
                                }
                            }

                            $this->out('sender_email', $contact_email);

                            $contact_store = $this->get('contact_store');
                            $contact = !$contact_store ? null : $contact_store->get(null, false, $contact_email);
                            $contact_exists = !empty($contact);

                            $txt .= '<tr class="header_'.$fld.'"><th>'.$this->trans($name).'
                                        </th>
                                            <td>
                                                <div class="dropdown">
                                                    <a id="contact_info" data-bs-toggle="dropdown" id="dropdownMenuContact" class="dropdown-toggle" href="#">' . $this->html_safe($value) . '
                                                    </a>
                                                    <div class="dropdown-menu p-4" id="contact_popup" aria-labelledby="dropdownMenuContact">
                                                        <div id="contact_popup_body">';

                            if($contact_exists){
                                $txt .= '<div>
                                            <table>
                                                <tr>
                                                    <td><strong>Name :</strong></td>
                                                    <td>
                                                        '.$this->html_safe($contact->value('display_name')).'
                                                    </td>
                                                </tr>
                                                <tr>
                                                    <td><strong>Email :</strong></td>
                                                    <td>
                                                        '.$this->html_safe($contact->value('email_address')).'
                                                    </td>
                                                </tr>
                                                <tr>
                                                    <td><strong>Tel :</strong></td>
                                                    <td>
                                                        <a href="tel:'.$this->html_safe($contact->value('phone_number')).'" data-external="true">'.
                                                        $this->html_safe($contact->value('phone_number')).'</a>
                                                    </td>
                                                </tr>
                                                <tr>
                                                    <td><strong>Source :</strong></td>
                                                    <td>
                                                        '.$this->html_safe($contact->value('source')).'
                                                    </td>
                                                </tr>
                                            </table>
                                        </div>';
                            } else {
                                $txt .= '<div class="popup-container_footer">
                                            <button onclick="return add_contact_from_popup(event)" class="add_contact_btn btn btn-primary" type="button" value="">'.$this->trans('Add local contacts').'
                                            </button>
                                        </div>';
                            }

                            $txt .= '               </div>
                                                </div>
                                            </div>
                                        </td>
                                    </tr>';
                        }
                        elseif ($fld == 'reply-to') {
                            $from = addr_parse($headers['From']);

                            $replyEmails = array_map(function ($addr) {
                                return $addr['email'];
                            }, process_address_fld($headers['Reply-To']));

                            if (count($replyEmails) === 1 && ($replyEmails[0] === $from['email'])) {
                                $txt .= '<tr style="display: none;" class="long_header"><th>'.$this->html_safe($name).'</th><td>'.$this->html_safe($value).'</td></tr>';
                            } else {
                                $txt .= '<tr class="header_'.$fld.'"><th>'.$this->trans($name).'</th><td>'.$this->html_safe(join(',', $replyEmails)).'</td></tr>';
                            }
                        }
                        else {
                            if (mb_strtolower($name) == 'flags') {
                                $name = $this->trans('Tags');
                                $value = str_replace('\\', '', $value);
                                $new_value = array();
                                foreach (explode(' ', $value) as $v) {
                                    $new_value[] = $this->trans(trim($v));
                                }
                                $value = implode(', ', $new_value);

                            }
                            $txt .= '<tr class="header_'.$fld.'"><th>'.$this->trans($name).'</th><td>'.$this->html_safe($value).'</td></tr>';
                        }
                        break;
                    }
                }
            }
            foreach ($headers as $name => $value) {
                if (!in_array(mb_strtolower($name), $small_headers)) {
                    if (is_array($value)) {
                        foreach ($value as $line) {
                            $txt .= '<tr style="display: none;" class="long_header"><th>'.$this->html_safe($name).'</th><td>'.$this->html_safe($line).'</td></tr>';
                        }
                    }
                    else {
                        $txt .= '<tr style="display: none;" class="long_header"><th>'.$this->html_safe($name).'</th><td>'.$this->html_safe($value).'</td></tr>';
                    }
                }
            }
            if ($this->get('list_headers')) {
                $txt .= format_list_headers($this);
            }
            $lc_headers = lc_headers($headers);
            if (array_key_exists('to', $lc_headers)) {
                $addr_list = process_address_fld($lc_headers['to']);
                $size = count($addr_list);
            }
            if (array_key_exists('cc', $lc_headers)) {
                $addr_list = process_address_fld($lc_headers['cc']);
                $size += count($addr_list);
            }
            if (array_key_exists('from', $lc_headers)) {
                $imap_server_id = explode('_', $this->get('msg_list_path'))[1];
                $server = Hm_IMAP_List::get($imap_server_id, false);
                if ($server) {
                    $addr_list = process_address_fld($lc_headers['from']);
                    $addr_list = array_filter($addr_list, function ($addr) use($server) {
                        return $addr['email'] != $server['user'];
                    });
                    $size += count($addr_list);
                }
            }

            $txt .= '<tr><td class="header_space" colspan="2"></td></tr>';
            $txt .= '<tr><th colspan="2" class="header_links">';
            $txt .= '<ul class="msg_move_to">'.
                '<li><a href="#" class="hlink all_headers">'.$this->trans('All headers').'</a></li>'.
                '<li><a class="hlink small_headers" style="display: none;" href="#">'.$this->trans('Small headers').'</a></li>';
            if (!isset($headers['Flags']) || !mb_stristr($headers['Flags'], 'draft')) {
                $txt .= ' | <li><a class="reply_link hlink" href="?page=compose&amp;reply=1'.$reply_args.'">'.$this->trans('Reply').'</a></li>';
                if ($size > 1) {
                    $txt .= ' | <li><a class="reply_all_link hlink" href="?page=compose&amp;reply_all=1'.$reply_args.'">'.$this->trans('Reply-all').'</a></li>';
                }
                else {
                    $txt .= ' | <li><a class="reply_all_link hlink disabled_link">'.$this->trans('Reply-all').'</a></li>';
                }
                $txt .= ' | ' . forward_dropdown($this, $reply_args);
            }
            if (isset($headers['Flags']) && mb_stristr($headers['Flags'], 'flagged')) {
                $txt .= ' | <li><a style="display: none;" class="flagged_link hlink" id="flag_msg" data-state="unflagged" href="#">'.$this->trans('Flag').'</a></li>';
                $txt .= '<li><a id="unflag_msg" class="unflagged_link hlink" data-state="flagged" href="#">'.$this->trans('Unflag').'</a></li>';
            }
            else {
                $txt .= ' | <li><a id="flag_msg" class="unflagged_link hlink" data-state="unflagged" href="#">'.$this->trans('Flag').'</a></li>';
                $txt .= '<li><a style="display: none;" class="flagged_link hlink" id="unflag_msg" data-state="flagged" href="#">'.$this->trans('Unflag').'</a></li>';
            }

            $txt .= ' | <li><a class="hlink" id="unread_message" href="#" >'.$this->trans('Unread').'</a></li>';
            $txt .= ' | <li><a class="delete_link hlink" id="delete_message" href="#">'.$this->trans('Delete').'</a></li>';
            $txt .= ' | <li><a class="hlink" id="copy_message" href="#">'.$this->trans('Copy').'</a><div class="move_to_location"></div></li>';
            $txt .= ' | <li><a class="hlink" id="move_message" href="#">'.$this->trans('Move').'</a><div class="move_to_location"></div></li>';
            $txt .= ' | <li><a class="archive_link hlink" id="archive_message" href="#">'.$this->trans('Archive').'</a></li>';
            if($this->get('tags')){
                $txt .= ' | '. tags_dropdown($this, $headers);
            }
            if (isset($headers['X-Schedule'])) {
                $txt .= ' | ' . schedule_dropdown($this, true);
            }

            $is_draft = isset($headers['Flags']) && mb_stristr($headers['Flags'], 'draft');
            $settings = $this->get('user_settings', array());
            if(array_key_exists('enable_snooze_setting', $settings) && $settings['enable_snooze_setting']) {
                $txt .= ' | ' . snooze_dropdown($this, isset($headers['X-Snoozed']));
            }
            if ($this->get('sieve_filters_enabled') && !$is_draft) {
                $server_id = $this->get('msg_server_id');
                $imap_server = $this->get('imap_accounts')[$server_id];
                if ($this->get('sieve_filters_client')) {
                    $user_config = $this->get('user_config');
                    $contact_list = $user_config->get('contacts', []);
                    $existing_emails = array_column($contact_list, 'email_address');
                    $sender = addr_parse($headers['From'])['email'];
                    $domain = '*@'.get_domain($sender);
                    $blocked_senders = get_blocked_senders_array($imap_server, $this->get('site_config'), $this->get('user_config'));
                    $sender_blocked = in_array($sender, $blocked_senders);
                    $domain_blocked = in_array($domain, $blocked_senders);
                    if(!in_array($sender, $existing_emails)){
                        $txt .= ' | <li><div class="dropdown d-inline-block"><a class="block_sender_link hlink dropdown-toggle'.($domain_blocked || $sender_blocked ? '" id="unblock_sender" data-target="'.($domain_blocked? 'domain':'sender').'"' : '"').' href="#" aria-labelledby="dropdownMenuBlockSender" data-bs-toggle="dropdown"><i class="bi bi-lock-fill"></i> <span id="filter_block_txt">'.$this->trans($domain_blocked ? 'Unblock Domain' : ($sender_blocked ? 'Unblock Sender' : 'Block Sender')).'</span></a></li>';
                        $txt .= block_filter_dropdown($this);
                    }
                } else {
                    $txt .= ' | <li><span data-bs-toogle="tooltip" title="This functionality requires the email server support &quot;Sieve&quot; technology which is not provided. Contact your email provider to fix it or enable it if supported."><i class="bi bi-lock-fill"></i> <span id="filter_block_txt">'.$this->trans('Block Sender').'</span></span></li>';
                }
            }
            $txt .= ' | <li><a class="hlink" id="show_message_source" href="#">' . $this->trans('Show Source') . '</a></li>';

            if ($is_draft) {
                $txt .= ' | <li><a class="edit_draft_link hlink" id="edit_draft" href="?page=compose'.$reply_args.'&imap_draft=1">'.$this->trans('Edit Draft').'</a></li>';
            }
            $txt .= '</ul><span id="extra-header-buttons"></span>';
            $txt .= '<input type="hidden" class="move_to_type" value="" />';
            $txt .= '<input type="hidden" class="move_to_string1" value="'.$this->trans('Move to ...').'" />';
            $txt .= '<input type="hidden" class="move_to_string2" value="'.$this->trans('Copy to ...').'" />';
            $txt .= '<input type="hidden" class="move_to_string3" value="'.$this->trans('Removed non-IMAP messages from selection. They cannot be moved or copied').'" />';
            $txt .= '</th></tr>';
            $txt .= '</table>';

            $this->out('msg_headers', $txt, false);
        }
    }
}

/**
 * Format configured IMAP servers for the servers page
 * @subpackage imap/output
 */
class Hm_Output_display_configured_imap_servers extends Hm_Output_Module {
    /**
     * Build HTML for configured IMAP servers
     */
    protected function output() {
        if ($this->get('single_server_mode')) {
            return '';
        }
        $list = $this->get('imap_servers', array());

        if (empty($list)) {
            return '';
        }

        $res = '<div class="subtitle mt-4 fw-bold">'.$this->trans('IMAP and JMAP Servers').'</div>';
        foreach ($list as $index => $vals) {
            $server_id = $vals['id'];
            $type = 'IMAP';

            if (array_key_exists('type', $vals) && $vals['type'] == 'jmap') {
                $type = 'JMAP';
            }

            if (array_key_exists('type', $vals) && $vals['type'] == 'ews') {
                continue;
            }

            if (array_key_exists('user', $vals) && !array_key_exists('nopass', $vals)) {
                $disabled = 'disabled="disabled"';
                $user_pc = $vals['user'];
                $pass_pc = $this->trans('[saved]');
                $pass_value = '*************';
            }
            elseif (array_key_exists('nopass', $vals)) {
                if (array_key_exists('user', $vals)) {
                    $user_pc = $vals['user'];
                }
                else {
                    $user_pc = '';
                }
                $pass_pc = $this->trans('Password');
                $disabled = '';
                $pass_value = '';
            }
            else {
                $user_pc = '';
                $pass_pc = $this->trans('Password');
                $disabled = '';
                $pass_value = '';
            }
            $res .= '<div class="' . mb_strtolower($type) . '_server mb-3">';
            $res .= '<form class="imap_connect" method="POST">';
            $res .= '<input type="hidden" name="hm_page_key" value="'.$this->html_safe(Hm_Request_Key::generate()).'" />';
            $res .= '<input type="hidden" name="imap_server_id" class="imap_server_id" value="'.$this->html_safe($server_id).'" />';
            $res .= '<div class="row m-0 p-0 credentials-container"><div class="col-xl-2 col-lg-2 col-md-6 overflow-auto" >';
            $res .= sprintf('
                <div class="text-muted"><strong>%s</strong></div>
                <div class="server_subtitle">%s/%d %s</div>',
                $this->html_safe($vals['name']), $this->html_safe($vals['server']), $this->html_safe($vals['port']),
                $vals['tls'] ? 'TLS' : '' );

            $res .= '</div> <div class="col-xl-7 col-lg-7 col-md-9"> <div class="row"> <div class="col-md-6 col-lg-4 mb-2 ">';

            // IMAP Username
            $res .= '<div class="form-floating">';
            $res .= '<input '.$disabled.' id="imap_user_'.$server_id.'" class="form-control credentials" type="text" name="imap_user" value="'.$this->html_safe($user_pc).'" placeholder="'.$this->trans('Username').'">';
            $res .= '<label for="imap_user_'.$server_id.'">'.$this->trans('IMAP username').'</label></div>';
            $res .= '</div><div class="col-md-6 col-lg-4">';
            // IMAP Password
            $res .= '<div class="form-floating">';
            $res .= '<input '.$disabled.' id="imap_pass_'.$server_id.'" class="form-control credentials imap_password" type="password" name="imap_pass" value="'.$pass_value.'" placeholder="'.$pass_pc.'">';
            $res .= '<label for="imap_pass_'.$server_id.'">'.$this->trans('IMAP password').'</label></div>';
            $res .= '</div><div class="col-md-6 col-lg-4">';

            // Sieve Host (Conditional)

            if ($this->get('sieve_filters_enabled') && isset($vals['sieve_config_host'])) {
                $res .= '<div class="form-floating">';
                $default_value = $vals['sieve_config_host'];
                $res .= '<input '.$disabled.' id="imap_sieve_host_'.$server_id.'" class="form-control credentials imap_sieve_host_input" type="text" name="imap_sieve_host" value="'.$default_value.'" placeholder="Sieve Host">';
                $res .= '<label for="imap_sieve_host_'.$server_id.'">'.$this->trans('Sieve Host').'</label></div>';
            }
            $res .= '</div></div></div><div class="col-xl-3 col-lg-4 col-md-6 d-flex justify-content-start align-items-center">';

            // Buttons
            $disabled = isset($vals['default']) ? ' disabled': '';
            if (!isset($vals['user']) || !$vals['user']) {
                $res .= '<input type="submit" value="'.$this->trans('Delete').'" class="imap_delete btn btn-outline-danger btn-sm me-2 mt-3" />';
            } else {
                $keysToRemove = array('object', 'connected', 'default', 'nopass');
                $serverDetails = array_diff_key($vals, array_flip($keysToRemove));

                $type = $vals['type'] ?? 'imap';
                $res .= '<input type="submit" value="'.$this->trans('Edit').'" class="edit_server_connection btn btn-outline-success btn-sm me-2 mt-3"'.$disabled.' data-server-details=\''.$this->html_safe(json_encode($serverDetails)).'\' data-id="'.$this->html_safe($serverDetails['name']).'" data-type="'.$type.'" />';
                $res .= '<input type="submit" value="'.$this->trans('Test').'" class="test_imap_connect btn btn-outline-primary btn-sm me-2 mt-3" />';
                $res .= '<input type="submit" value="'.$this->trans('Delete').'" class="imap_delete btn btn-outline-danger btn-sm me-2 mt-3"'.$disabled.' />';
            }

            // Hide/Unhide Buttons
            $hidden = array_key_exists('hide', $vals) && $vals['hide'];
            $res .= '<input type="submit" '.($hidden ? 'style="display: none;" ' : '').'value="'.$this->trans('Hide').'" class="hide_imap_connection btn btn-outline-secondary btn-sm me-2 mt-3" />';
            $res .= '<input type="submit" '.(!$hidden ? 'style="display: none;" ' : '').'value="'.$this->trans('Unhide').'" class="unhide_imap_connection btn btn-outline-secondary btn-sm me-2 mt-3" />';

            $res .= '<input type="hidden" value="ajax_imap_debug" name="hm_ajax_hook" />';
            $res .= '</div></div></div></form>';
        }
        $res .= '';
        return $res;
    }
}

/**
 * Format the add IMAP server dialog for the servers page
 * @subpackage imap/output
 */
class Hm_Output_add_imap_server_dialog extends Hm_Output_Module {
    /**
     * Build the HTML for the add server dialog
     */
    protected function output() {
        if ($this->get('single_server_mode')) {
            return '';
        }
        $count = count(array_filter($this->get('imap_servers', array()), function($v) { return !array_key_exists('type', $v) || $v['type'] != 'jmap'; }));
        $count = sprintf($this->trans('%d configured'), $count);

        $sieve_extra2 = '';
        $sieve_extra = '';
        if ($this->get('sieve_filters_enabled')) {
            $sieve_extra = '<tr class="sieve_config" style="display: none;"><td><div class="subtitle">'.$this->trans('Sieve Configuration').'</div></td></tr>'.
                '<tr class="sieve_config" style="display: none;"><td colspan="2"><label class="screen_reader" for="new_imap_port">'.$this->trans('Sieve Host').'</label>'.
                '<input class="form-control" type="text" id="sieve_config_host" name="sieve_config_host" class="txt_fld" placeholder="'.$this->trans('localhost:4190').'"></td></tr>';

                $sieve_extra2 = '<tr><td colspan="2"><div class="form-check mb-3 mt-2"><input type="checkbox" id="enable_sieve_filter" name="enable_sieve_filter" class="form-check-input" value="0">'.
                '<label for="enable_sieve_filter" class="form-check-label"> '.$this->trans('Enable Sieve Filters').'</label></div></td></tr>';
        }

        return '<div class="imap_server_setup"><div data-target=".imap_section" class="server_section">'.
            '<i class="bi bi-envelope-fill"></i>'.
            ' '.$this->trans('IMAP Servers').'<div class="server_count">'.$count.'</div></div><div class="imap_section"></div></div>';
    }
}

/**
 * Format the add IMAP server dialog for the servers page
 * @subpackage imap/output
 */
class Hm_Output_add_jmap_server_dialog extends Hm_Output_Module {
    /**
     * Build the HTML for the add server dialog
     */
    protected function output() {
        if ($this->get('single_server_mode')) {
            return '';
        }

        if(!$this->get('is_jmap_supported')){
            return '<div class="jmap_server_setup"><div class="jmap_section" style="display: none;">';
        }

        $count = count(array_filter($this->get('imap_servers', array()), function($v) { return array_key_exists('type', $v) && $v['type'] == 'jmap';}));
        $count = sprintf($this->trans('%d configured'), $count);

        return '
        <div class="jmap_server_setup">
            <div data-target=".jmap_section" class="server_section border-bottom cursor-pointer px-1 py-3 pe-auto">
                <a href="#" class="pe-auto">
                    <i class="bi bi-envelope-fill me-3"></i>
                    <b>'.$this->trans('JMAP Servers').'</b>
                </a>
                <div class="server_count">'.$count.'</div>
            </div>
            <div class="jmap_section px-4 pt-3">
                <div class="row">
                <div class="col-12 col-lg-4">
                    <form class="add_server" method="POST">
                        <input type="hidden" name="hm_page_key" value="'.$this->html_safe(Hm_Request_Key::generate()).'" />
                        <div class="subtitle">'.$this->trans('Add a JMAP Server').'</div>

                        <div class="form-floating mb-3">
                            <input id="new_jmap_name" required type="text" name="new_jmap_name" class="txt_fld form-control" value="" placeholder="'.$this->trans('Account name').'">
                            <label class="" for="new_jmap_name">'.$this->trans('Account name').'</label>
                        </div>

                        <div class="form-floating mb-3">
                            <input required type="url" id="new_jmap_address" name="new_jmap_address" class="txt_fld form-control" placeholder="'.$this->trans('Server URL').'" value="">
                            <label class="" for="new_jmap_address">'.$this->trans('Server URL').'</label>
                        </div>

                        <div class="mb-3">
                            <input type="checkbox" id="new_jmap_hidden" name="new_jmap_hidden" class="form-check-input" value="1">
                            <label for="new_jmap_hidden">'.$this->trans('Hide From Combined Pages').'</label>
                        </div>

                        <input type="submit" class="btn btn-primary px-5" value="'.$this->trans('Add').'" name="submit_jmap_server" />
                    </form>
                </div>';
    }

}

/**
 * Format configured JMAP servers for the servers page
 * @subpackage imap/output
 */
class Hm_Output_display_configured_jmap_servers extends Hm_Output_Module {
    /**
     * Build HTML for configured JMAP servers
     */
    protected function output() {
        if ($this->get('single_server_mode')) {
            return '';
        }
        $res = '';
        foreach ($this->get('imap_servers', array()) as $index => $vals) {
            $server_id = $vals['id'];
            if (!array_key_exists('type', $vals) || $vals['type'] != 'jmap') {
                continue;
            }
            if (array_key_exists('user', $vals) && !array_key_exists('nopass', $vals)) {
                $disabled = 'disabled="disabled"';
                $user_pc = $vals['user'];
                $pass_pc = $this->trans('[saved]');
                $pass_value = '*************';
            }
            elseif (array_key_exists('nopass', $vals)) {
                if (array_key_exists('user', $vals)) {
                    $user_pc = $vals['user'];
                }
                else {
                    $user_pc = '';
                }
                $pass_pc = $this->trans('Password');
                $disabled = '';
                $pass_value = '';
            }
            else {
                $user_pc = '';
                $pass_pc = $this->trans('Password');
                $disabled = '';
                $pass_value = '';
            }
            $res .= '<div class="configured_server col-12 col-lg-4 mb-2"><div class="card card-body">';
            $res .= sprintf('<div class="server_title">%s</div><div class="server_subtitle">%s</div>',
                $this->html_safe($vals['name']), $this->html_safe($vals['server']));

            $res .= '<form class="imap_connect" method="POST">';
            $res .= '<input type="hidden" name="hm_page_key" value="'.$this->html_safe(Hm_Request_Key::generate()).'" />';
            $res .= '<input type="hidden" name="imap_server_id" class="imap_server_id" value="'.$this->html_safe($server_id).'" />';

            // JMAP Username
            $res .= '<div class="form-floating mb-2">';
            $res .= '<input '.$disabled.' id="imap_user_'.$server_id.'" class="form-control credentials" type="text" name="imap_user" value="'.$this->html_safe($user_pc).'" placeholder="'.$this->trans('Username').'">';
            $res .= '<label for="imap_user_'.$server_id.'">'.$this->trans('JMAP username').'</label></div>';

            // JMAP Password
            $res .= '<div class="form-floating">';
            $res .= '<input '.$disabled.' id="imap_pass_'.$server_id.'" class="form-control credentials imap_password" type="password" name="imap_pass" value="'.$pass_value.'" placeholder="'.$pass_pc.'">';
            $res .= '<label for="imap_pass_'.$server_id.'">'.$this->trans('JMAP password').'</label></div>';

            // Buttons
            if (!isset($vals['user']) || !$vals['user']) {
                $res .= '<input type="submit" value="'.$this->trans('Delete').'" class="btn btn-outline-danger btn-sm imap_delete me-2" />';
            } else {
                $res .= '<input type="submit" value="'.$this->trans('Test').'" class="btn btn-primary btn-sm test_imap_connect me-2" />';
                $res .= '<input type="submit" value="'.$this->trans('Delete').'" class="btn btn-danger btn-sm imap_delete me-2" />';
            }

            // Hide/Unhide Button Logic
            $hidden = array_key_exists('hide', $vals) && $vals['hide'];
            $res .= '<input type="submit" '.($hidden ? 'style="display: none;" ' : '').'value="'.$this->trans('Hide').'" class="btn btn-outline-secondary btn-sm hide_imap_connection me-2" />';
            $res .= '<input type="submit" '.(!$hidden ? 'style="display: none;" ' : '').'value="'.$this->trans('Unhide').'" class="btn btn-outline-secondary btn-sm unhide_imap_connection me-2" />';

            $res .= '<input type="hidden" value="ajax_imap_debug" name="hm_ajax_hook" />';
            $res .= '</form>';


            $res .= '</div></div>';
        }
        $res .= '<br class="clear_float" /></div></div></div>';
        return $res;
    }
}
/**
 * Format the IMAP status output on the info page
 * @subpackage imap/output
 */
class Hm_Output_display_imap_status extends Hm_Output_Module {
    /**
     * Build the HTML for the status rows. Will be populated by an ajax call per server
     */
    protected function output() {
        $settings = $this->get('user_settings', array());
        $enable_sieve = $settings['enable_sieve_filter_setting'] ?? DEFAULT_ENABLE_SIEVE_FILTER;
        $res = '';
        if(!$this->get('is_mobile')) {
            foreach ($this->get('imap_servers', array()) as $index => $vals) {
                $res .= '<tr><td>'.(strtoupper($vals['type'] ?? 'IMAP')).'</td><td>'.$vals['name'].'</td><td class="imap_status_'.$vals['id'].' imap_status" data-id="'.$vals['id'].'"></td>';
                if ($enable_sieve) {
                    $res .= '<td class="imap_detail_'.$vals['id'].'"></td>';
                }
                $res .= '</tr>';
            }
        }   
        return $res;
    }
}

/**
 * Format the IMAP status output on the info page
 * @subpackage imap/output
 */
class Hm_Output_display_imap_capability extends Hm_Output_Module {
    /**
     * Build the HTML for the status rows. Will be populated by an ajax call per server
     */
    protected function output() {
        $res = '';
        if(!$this->get('is_mobile')) {
            foreach ($this->get('imap_servers', array()) as $index => $vals) {
                $res .= '<tr><td>IMAP</td><td>'.$vals['name'].'</td>'.
                    '<td class="imap_capabilities_'.$vals['id'].'"></td></tr>';
            }
        }
        return $res;
    }
}

/**
 * Output a hidden field with all the IMAP server ids
 * @subpackage imap/output
 */
class Hm_Output_imap_server_ids extends Hm_Output_Module {
    /**
     * Build HTML for the IMAP server ids
     */
    protected function output() {
        return '<input type="hidden" class="imap_server_ids" value="'.$this->html_safe(implode(',', array_keys($this->get('imap_servers', array ())))).'" />';
    }
}

/**
 * Format a list of subfolders
 * @subpackage imap/output
 */
class Hm_Output_filter_expanded_folder_data extends Hm_Output_Module {
    /**
     * Build the HTML for a list of subfolders. The page cache is used to pass this to the folder list.
     */
    protected function output() {
        $folder_data = $this->get('imap_expanded_folder_data', array());
        $with_input = $this->get('with_input', false);
        $can_share_folders = $this->get('can_share_folders', false);
        if (!empty($folder_data)) {
            $res = format_imap_folder_section($folder_data, $this->get('imap_expanded_folder_id'), $this, $with_input, $can_share_folders);
            $this->out('imap_expanded_folder_formatted', $res);
        }
    }
}

/**
 * @subpackage imap/output
 */
class Hm_Output_get_list_imap_folders_permissions extends Hm_Output_Module {
    public function output() {
        $this->out('ajax_imap_folders_permissions', $this->get('imap_folders_permissions', array()));
    }
}

/**
 * Add move/copy dialog to the message list controls
 * @subpackage imap/output
 */
class Hm_Output_move_copy_controls extends Hm_Output_Module {
    protected function output() {
        if ($this->get('move_copy_controls', false)) {
            $res = '<span class="ctr_divider"></span> <div class="d-flex align-items-start gap-1 dropdown"><a class="imap_move disabled_input btn btn-sm btn-secondary no_mobile" href="#" data-action="copy">'.$this->trans('Copy').'</a>';
            $res .= '<a class="imap_move disabled_input btn btn-sm btn-secondary no_mobile" href="#" data-action="move">'.$this->trans('Move').'</a>';
            $res .= '<a class="imap_move disabled_input btn btn-outline-success btn-sm on_mobile" href="#" data-action="copy">'.$this->trans('Copy').'</a>';
            $res .= '<a class="imap_move disabled_input btn btn-outline-success btn-sm on_mobile" href="#" data-action="move">'.$this->trans('Move').'</a>';
            $res .= '<div class="move_to_location dropdown-menu"></div>';
            $res .= '<input type="hidden" class="move_to_type" value="" />';
            $res .= '<input type="hidden" class="move_to_string1" value="'.$this->trans('Move to ...').'" />';
            $res .= '<input type="hidden" class="move_to_string2" value="'.$this->trans('Copy to ...').'" />';
            $res .= '<input type="hidden" class="move_to_string3" value="'.$this->trans('Removed non-IMAP messages from selection. They cannot be moved or copied').'" /></div>';
            $this->concat('msg_controls_extra', $res);
        }
    }
}

/**
 * Format the status of an IMAP connection used on the info page
 * @subpackage imap/output
 */
class Hm_Output_filter_imap_status_data extends Hm_Output_Module {
    /**
     * Build AJAX response for an IMAP server status
     */
    protected function output() {
        $res = '';
        if ($this->get('imap_connect_status') != 'disconnected') {
            $res .= '<span class="online">'.$this->trans(ucwords($this->get('imap_connect_status'))).
                '</span> in '.round($this->get('imap_connect_time', 0.0), 3);
        }
        else {
            $res .= '<span class="down">'.$this->trans('Down').'</span>';
        }
        $this->out('imap_status_display', $res);
        $res = '';
        $capabilities = $this->get('sieve_server_capabilities', array());
        if ($capabilities) {
            foreach ($capabilities as $key => $val) {
                $capabilities[$key] = $key . ': ' . (is_array($val) ? implode(', ', $val) : $val);
            }
            $res .= '<span class="sieve_extensions">' . implode(', ', $capabilities) . '</span>';
        }
        $this->out('sieve_detail_display', $res);
        $res = '';
        $extensions = $this->get('imap_capabilities_list', "");
        if ($extensions) {
            $res .= '<span class="imap_extensions">'.$extensions.'</span>';
        }
        $this->out('imap_extensions_display', $res);
    }
}

/**
 * Format the top level IMAP folders for the folder list
 * @subpackage imap/output
 */
class Hm_Output_filter_imap_folders extends Hm_Output_Module {
    /**
     * Build HTML for the Email section of the folder list
     */
    protected function output() {
        $res = '';
        if ($this->get('imap_folders')) {
            foreach ($this->get('imap_folders', array()) as $id => $folder) {
                $res .= '<li class="imap_'.$id.'_"><a href="#" class="imap_folder_link" data-target="imap_'.$id.'_">';
                if (!$this->get('hide_folder_icons')) {
                    $res .= '<i class="bi bi-folder fs-5 me-2"></i>';
                }
                $res .= $this->html_safe($folder).'</a></li>';
            }
        }
        if ($res) {
            $this->append('folder_sources', array('email_folders', $res));
        }
        return '';
    }
}

/**
 * Format search results row
 * @subpackage imap/output
 */
class Hm_Output_filter_imap_search extends Hm_Output_Module {
    /**
     * Build ajax response from an IMAP server for a search
     */
    protected function output() {
        if ($this->get('imap_search_results')) {
            prepare_imap_message_list($this->get('imap_search_results'), $this, 'search');
        }
        elseif (!$this->get('formatted_message_list')) {
            $this->out('formatted_message_list', array());
        }
    }
}

/**
 * Format message headers for the unread and flagged page
 * @subpackage imap/output
 */
class Hm_Output_filter_by_type extends Hm_Output_Module {
    /**
     * Build ajax response for the Flagged message list
     */
    protected function output() {
        if ($this->get('imap_filter_by_type_data')) {
            prepare_imap_message_list($this->get('imap_filter_by_type_data'), $this, $this->get('type'));
        }
        elseif (!$this->get('formatted_message_list')) {
            $this->out('formatted_message_list', array());
        }
    }
}

/**
 * Format message headers for the All, Combined Inbox, Sent, Junk, Draft, Unread, Trash E-mail pages
 * @subpackage imap/output
 */
class Hm_Output_filter_data extends Hm_Output_Module {
    /**
     * Build ajax response for the All E-mail message list
     */
    protected function output() {
        if ($this->get('imap_message_list_data')) {
            prepare_imap_message_list($this->get('imap_message_list_data'), $this, $this->get('list_path'));
        }
        else {
            $this->out('formatted_message_list', array());
        }
    }
}

/**
 * Normal IMAP folder view
 * @subpackage imap/output
 */
class Hm_Output_filter_folder_page extends Hm_Output_Module {
    /**
     * Build ajax response for a folder page
     */
    protected function output() {
        $res = array();
        if ($this->get('imap_mailbox_page')) {
            $details = $this->get('imap_folder_detail');
            $type = mb_stripos($details['name'], 'Sent') !== false ? 'sent' : false;
            prepare_imap_message_list($this->get('imap_mailbox_page'), $this, $type);
            $max_pages = ceil($details['detail']['exists']/$details['limit']);
            $this->out('pages', $max_pages);
        }
        elseif (!$this->get('formatted_message_list')) {
            $this->out('formatted_message_list', array());
            $this->out('pages', 0);
        } else {
            $this->out('pages', 0);
        }
        $this->out('do_not_flag_as_read_on_open', $this->get('do_not_flag_as_read_on_open', false));
    }
}

/**
 * Start the sent section on the settings page.
 * @subpackage imap/output
 */
class Hm_Output_start_sent_settings extends Hm_Output_Module {
    /**
     * Settings in this section control the Sent E-mail view.
     */
    protected function output() {
        return '<tr><td data-target=".sent_setting" colspan="2" class="settings_subtitle cursor-pointer border-bottom p-2">'.
            '<i class="bi bi-envelope-fill fs-5 me-2"></i>'.
            $this->trans('Sent').'</td></tr>';
    }
}

/**
 * Option for the "received since" date range for the All E-mail page
 * @subpackage imap/output
 */
class Hm_Output_sent_since_setting extends Hm_Output_Module {
    protected function output() {
        if (!email_is_active($this->get('router_module_list'))) {
            return '';
        }
        $since = DEFAULT_SENT_SINCE;
        $settings = $this->get('user_settings', array());
        if (array_key_exists('sent_since', $settings) && $settings['sent_since']) {
            $since = $settings['sent_since'];
        }
        return '<tr class="sent_setting"><td><label for="sent_since">'.
            $this->trans('Show messages received since').'</label></td>'.
            '<td>'.message_since_dropdown($since, 'sent_since', $this, DEFAULT_SENT_SINCE).'</td></tr>';
    }
}

/**
 * Option to unflag a message on reply
 * @subpackage imap/output
 */
class Hm_Output_imap_unflag_on_send_controls extends Hm_Output_Module {
    protected function output() {
        $flagged = false;
        $details = $this->get('reply_details', array());
        if (is_array($details) && array_key_exists('msg_headers', $details) && array_key_exists('Flags', $details['msg_headers'])) {
            if (mb_stristr($details['msg_headers']['Flags'], 'flagged')) {
                $flagged = true;
            }
        }
        if (!$flagged) {
            return;
        }
        if ($this->get('list_path') || $this->get('compose_msg_path')) {
            return '<div class="unflag_send_div"><input type="checkbox" value="1" name="compose_unflag_send" id="unflag_send">'.
                '<label for="unflag_send">'.$this->trans('Unflag on reply').'</label></div>';
        }
    }
}

/**
 * Option to enable/disable flagging a message as read when opened (defaults to true)
 * @subpackage imap/output
 */
class Hm_Output_imap_unread_on_open extends Hm_Output_Module {
    protected function output() {
        $checked = '';
        $settings = $this->get('user_settings', array());
        if (array_key_exists('unread_on_open', $settings) && $settings['unread_on_open']) {
            $checked = ' checked="checked"';
        }
        return '<tr class="general_setting"><td><label class="form-check-label" for="unread_on_open">'.
            $this->trans('Don\'t flag a message as read on open').'</label></td>'.
            '<td><input class="form-check-input" type="checkbox" '.$checked.' id="unread_on_open" name="unread_on_open" data-default-value="false" value="1" /></td></tr>';
    }
}

/**
 * Option to enable/disable simple message structure on the message view
 * @subpackage imap/output
 */
class Hm_Output_imap_simple_msg_parts extends Hm_Output_Module {
    protected function output() {
        $checked = '';
        $settings = $this->get('user_settings', array());
        $reset = '';
        if (array_key_exists('simple_msg_parts', $settings) && $settings['simple_msg_parts']) {
            $checked = ' checked="checked"';
        } else {
            $reset = '<span class="tooltip_restore" restore_aria_label="Restore default value"><i class="bi bi-arrow-counterclockwise refresh_list reset_default_value_checkbox"></i></span>';
        }
        return '<tr class="general_setting"><td><label class="form-check-label" for="simple_msg_parts">'.
            $this->trans('Show simple message part structure when reading a message').'</label></td>'.
            '<td><input class="form-check-input" type="checkbox" '.$checked.' id="simple_msg_parts" name="simple_msg_parts" data-default-value="false" value="1" />'.$reset.'</td></tr>';
    }
}

/**
 * Option to enable/disable pagination links on the message view
 * @subpackage imap/output
 */
class Hm_Output_imap_pagination_links extends Hm_Output_Module {
    protected function output() {
        $checked = '';
        $reset = '';
        $settings = $this->get('user_settings', array());
        if (!array_key_exists('pagination_links', $settings) || (array_key_exists('pagination_links', $settings) && $settings['pagination_links'])) {
            $checked = ' checked="checked"';
        }
        if($settings['pagination_links'] !== DEFAULT_PAGINATION_LINKS) {
            $reset = '<span class="tooltip_restore" restore_aria_label="Restore default value"><i class="bi bi-arrow-counterclockwise refresh_list reset_default_value_checkbox"></i></span>';
        }
        $res = '<tr class="general_setting"><td><label class="form-check-label" for="pagination_links">'.
            $this->trans('Show next & previous emails links when reading a message').'</label></td>'.
            '<td><input class="form-check-input" type="checkbox"'.$checked.' id="pagination_links" name="pagination_links"  data-default-value="'.(DEFAULT_PAGINATION_LINKS ? 'true' : 'false') . '" value="1" />'.$reset.'</td></tr>';
        return $res;
    }
}

/**
 * Option to enable/disable loading the next email instead of returning to your inbox after performing a message action (delete, archive, move, etc.)
 * @subpackage imap/output
 */
class Hm_Output_imap_auto_advance_email extends Hm_Output_Module {
    protected function output() {
        $checked = '';
        $reset = '';
        $settings = $this->get('user_settings', array());
        if (!array_key_exists('auto_advance_email', $settings) || (array_key_exists('auto_advance_email', $settings) && $settings['auto_advance_email'])) {
            $checked = ' checked="checked"';
        } else {
            $reset = '<span class="tooltip_restore" restore_aria_label="Restore default value"><i class="bi bi-arrow-counterclockwise refresh_list reset_default_value_checkbox"></i></span>';
        }
        $res = '<tr class="general_setting"><td><label class="form-check-label" for="auto_advance_email">'.
            $this->trans('Show next email instead of your inbox after performing action (delete, archive, move, etc)').'</label></td>'.
            '<td><input class="form-check-input" type="checkbox"'.$checked.' id="auto_advance_email" name="auto_advance_email" data-default-value="true" value="1" />'.$reset.'</td></tr>';
        return $res;
    }
}

/**
 * Output imap prefetch ids
 * @subpackage imap/output
 */
class Hm_Output_prefetch_imap_folder_ids extends Hm_Output_Module {
    protected function output() {
        $ids = $this->get('prefetch_folder_ids', array());
        if (count($ids) == 0) {
            return;
        }
        return '<input type="hidden" id="imap_prefetch_ids" value="'.$this->html_safe(implode(',', $ids)).'" />';
    }
}

/**
 * Option to set the per page count for IMAP folder views
 * @subpackage imap/output
 */
class Hm_Output_imap_per_page_setting extends Hm_Output_Module {
    protected function output() {
        $settings = $this->get('user_settings', array());
        $per_page = DEFAULT_IMAP_PER_PAGE;
        $reset = '';
        if (array_key_exists('imap_per_page', $settings)) {
            $per_page = $settings['imap_per_page'];
        }
        if ($per_page != DEFAULT_IMAP_PER_PAGE) {
            $reset = '<span class="tooltip_restore" restore_aria_label="Restore default value"><i class="bi bi-arrow-counterclockwise refresh_list reset_default_value_input"></i></span>';
        }
        return '<tr class="general_setting"><td><label for="imap_per_page">'.
            $this->trans('Messages per page for IMAP folder views').'</label></td><td class="d-flex"><input class="form-control form-control-sm w-auto" type="text" id="imap_per_page" '.
            'name="imap_per_page" value="'.$this->html_safe($per_page).'" data-default-value="'.DEFAULT_IMAP_PER_PAGE.'"/>'.$reset.'</td></tr>';
    }
}

/**
 * Option to set number of google contacts
 * @subpackage imap/output
 */
class Hm_Output_max_google_contacts_number extends Hm_Output_Module {
    protected function output() {
        $settings = $this->get('user_settings', array());
        $max_google_contacts_number = DEFAULT_MAX_GOOGLE_CONTACTS_NUMBER;
        $reset = '';
        if (array_key_exists('max_google_contacts_number', $settings)) {
            $max_google_contacts_number = $settings['max_google_contacts_number'];
        }
        if ($max_google_contacts_number != DEFAULT_MAX_GOOGLE_CONTACTS_NUMBER) {
            $reset = '<span class="tooltip_restore" restore_aria_label="Restore default value"><i class="bi bi-arrow-counterclockwise refresh_list reset_default_value_input" default-value="'.DEFAULT_MAX_GOOGLE_CONTACTS_NUMBER.'"></i></span>';
        }
        return '<tr class="general_setting"><td><label for="max_google_contacts_number">'.
            $this->trans('Max google contacts number').'</label></td><td><input class="form-control form-control-sm w-auto" type="number" id="max_google_contacts_number" '.
            'name="max_google_contacts_number" value="'.$this->html_safe($max_google_contacts_number).'" />'.$reset.'</td></tr>';
    }
}

/**
 * Option to enable/disable message part icons on the message view
 * @subpackage imap/output
 */
class Hm_Output_imap_msg_icons_setting extends Hm_Output_Module {
    protected function output() {
        $checked = '';
        $settings = $this->get('user_settings', array());
        $reset = '';
        if (array_key_exists('msg_part_icons', $settings) && $settings['msg_part_icons']) {
            $checked = ' checked="checked"';
            $reset = '<span class="tooltip_restore" restore_aria_label="Restore default value"><i class="bi bi-arrow-counterclockwise refresh_list reset_default_value_checkbox"></i></span>';
        }
        return '<tr class="general_setting"><td><label class="form-check-label" for="msg_part_icons">'.
            $this->trans('Show message part icons when reading a message').'</label></td>'.
            '<td><input class="form-check-input" type="checkbox" '.$checked.' id="msg_part_icons" name="msg_part_icons" data-default-value="false" value="1" />'.$reset.'</td></tr>';
    }
}

/**
 * Option to limit mail fromat to text only when possible (not defaulting to HTML)
 * @subpackage imap/output
 */
class Hm_Output_text_only_setting extends Hm_Output_Module {
    protected function output() {
        $checked = '';
        $settings = $this->get('user_settings', array());
        $reset = '';
        if (array_key_exists('text_only', $settings) && $settings['text_only']) {
            $checked = ' checked="checked"';
        }
        if($settings['text_only'] !== DEFAULT_TEXT_ONLY) {
            $reset = '<span class="tooltip_restore" restore_aria_label="Restore default value"><i class="bi bi-arrow-counterclockwise refresh_list reset_default_value_checkbox"></i></span>';
        }
        return '<tr class="general_setting"><td><label class="form-check-label" for="text_only">'.
            $this->trans('Prefer text over HTML when reading messages').'</label></td>'.
            '<td><input class="form-check-input" type="checkbox" '.$checked.' id="text_only" name="text_only" data-default-value="'.(DEFAULT_TEXT_ONLY ? 'true' : 'false') . '" value="1" />'.$reset.'</td></tr>';
    }
}

/**
 * Option for the maximum number of messages per source for the All E-mail  page
 * @subpackage imap/output
 */
class Hm_Output_sent_source_max_setting extends Hm_Output_Module {
    protected function output() {
        $sources = DEFAULT_SENT_PER_SOURCE;
        $settings = $this->get('user_settings', array());
        $reset = '';
        if (array_key_exists('sent_per_source', $settings)) {
            $sources = $settings['sent_per_source'];
        }
        if ($sources != DEFAULT_SENT_PER_SOURCE) {
            $reset = '<span class="tooltip_restore" restore_aria_label="Restore default value"><i class="bi bi-arrow-counterclockwise refresh_list reset_default_value_input"></i></span>';
        }
        return '<tr class="sent_setting"><td><label for="sent_per_source">'.
            $this->trans('Max messages per source').'</label></td>'.
            '<td class="d-flex"><input class="form-control form-control-sm w-auto" type="text" size="2" id="sent_per_source" name="sent_per_source" value="'.$this->html_safe($sources).'" data-default-value="'.DEFAULT_SENT_PER_SOURCE.'" />'.$reset.'</td></tr>';
    }
}

/**
 * Option for the maximum number of messages per source for the All E-mail  page
 * @subpackage imap/output
 */
class Hm_Output_original_folder_setting extends Hm_Output_Module {
    protected function output() {
        $checked = '';
        $reset = '';
        $settings = $this->get('user_settings', array());
        if (array_key_exists('original_folder', $settings) && $settings['original_folder']) {
            $checked = ' checked="checked"';
            $reset = '<span class="tooltip_restore" restore_aria_label="Restore default value"><i class="bi bi-arrow-counterclockwise refresh_list reset_default_value_checkbox"></i></span>';
        }
        return '<tr class="general_setting"><td><label class="form-check-label" for="original_folder">'.
            $this->trans('Archive to the original folder').'</label></td>'.
            '<td><input class="form-check-input" type="checkbox" '.$checked.' id="original_folder" name="original_folder" data-default-value="false" value="1" />'.$reset.'</td></tr>';
    }
}

/**
 * Option to enable/disable snooze functionality
 * @subpackage imap/output
 */
class Hm_Output_setting_enable_snooze extends Hm_Output_Module {
    protected function output() {
        $settings = $this->get('user_settings', array());
        $checked = '';
        $reset = '';
        if (array_key_exists('enable_snooze', $settings) && $settings['enable_snooze']) {
            $checked = ' checked="checked"';
        }
        if ($settings['enable_snooze'] !== DEFAULT_ENABLE_SNOOZE) {
            $reset = '<span class="tooltip_restore" restore_aria_label="Restore default value"><i class="bi bi-arrow-repeat refresh_list reset_default_value_checkbox"></i></span>';
        }
        return '<tr class="general_setting"><td><label class="form-check-label" for="enable_snooze">'.
            $this->trans('Enable Snooze functionality').'</label></td>'.
            '<td><input class="form-check-input" type="checkbox" '.$checked.' id="enable_snooze" name="enable_snooze" data-default-value="'.(DEFAULT_ENABLE_SNOOZE ? 'true' : 'false') . '" value="1" />'.$reset.'</td></tr>';
    }
}

/**
 * @subpackage imap/output
 */
class Hm_Output_review_sent_email extends Hm_Output_Module {
    protected function output() {
        $checked = '';
        $reset = '';
        $settings = $this->get('user_settings', array());
        if (!array_key_exists('review_sent_email', $settings) || (array_key_exists('review_sent_email', $settings) && $settings['review_sent_email'])) {
            $checked = ' checked="checked"';
        }
        if($settings['review_sent_email'] !== DEFAULT_REVIEW_SENT_EMAIL) {
            $reset = '<span class="tooltip_restore" restore_aria_label="Restore default value"><i class="bi bi-arrow-counterclockwise refresh_list reset_default_value_checkbox"></i></span>';
        }
        return '<tr class="general_setting"><td><label class="form-check-label" for="review_sent_email">'.
            $this->trans('Review sent message').'</label></td>'.
            '<td><input class="form-check-input" type="checkbox" '.$checked.' id="review_sent_email" name="review_sent_email" data-default-value="'.(DEFAULT_REVIEW_SENT_EMAIL ? 'true' : 'false') . '" value="1" />'.$reset.'</td></tr>';
    }
}

/**
 * Add snooze dialog to the message list controls
 * @subpackage imap/output
 */
class Hm_Output_snooze_msg_control extends Hm_Output_Module {
    protected function output() {
        $settings = $this->get('user_settings', array());
        $enable_snooze = array_key_exists('enable_snooze_setting', $settings) && $settings['enable_snooze_setting'];

        if (!$enable_snooze) {
            return;
        }
        
        $parts = explode('_', $this->get('list_path'));
        $unsnooze = $parts[0] == 'imap' && hex2bin($parts[2]) == 'Snoozed';
        $res = snooze_dropdown($this, $unsnooze);
        $this->concat('msg_controls_extra', $res);
    }
}

/**
 * Output imap message source
 * @subpackage imap/output
 */
class Hm_Output_imap_message_source extends Hm_Output_Module {
    protected function output() {
        $res = '<div class="w-auto mx-auto p-5">';
        $res .= '
        <div class="d-flex flex-column gap-2 mb-4">
        <h1>Message source</h1>
        <div class="d-flex justify-content-between mb-3">
            <button class="btn btn-success" onclick="handleDownloadMsgSource()">Download</button>
            <a href="#" class="hlink" onClick="handleCopyMsgSource(event)">Copy to clipboard</a>
        </div>
        </div>
        ';
        if($this->get('msg_source')){
            $res .= '<div><pre class="msg_source">'.$this->html_safe($this->get('msg_source')).'</pre></div>';
        }
        $res .= '</div>';
        return $res;
    }
}
/**
 * @subpackage jmap/output
 */
class Hm_Output_stepper_setup_server_jmap extends Hm_Output_Module {
    protected function output() {
        if(!in_array('jmap', $this->get('router_module_list'), true)) return '';
        return '
                <div class="form-check"  id="srv_setup_stepper_jmap_select_box">
                    <input class="form-check-input" type="checkbox" role="switch" id="srv_setup_stepper_only_jmap" name="srv_setup_stepper_only_jmap" onchange="handleJmapCheckboxChange(this)">
                    <label class="form-check-label" style="font-size: 12px;" for="srv_setup_stepper_only_jmap">
                      '.$this->trans('Setup JMAP Server').'
                    </label>
                </div>
                <div class="step_config-jmap_bloc hide" id="step_config-jmap_bloc">
                      <label><strong>JMAP</strong></label>
                      <div class="form-floating">
                          <input required type="text" id="srv_setup_stepper_jmap_address" name="srv_setup_stepper_jmap_address" class="txt_fld form-control" value="" placeholder="'.$this->trans('Address').'">
                          <label class="" for="srv_setup_stepper_jmap_address">'.$this->trans('Address').'</label>
                          <span id="srv_setup_stepper_jmap_address-error" class="invalid-feedback"></span>
                      </div>
                </div>
        ';
    }
}

/**
 * @subpackage imap/output
 */
class Hm_Output_stepper_setup_server_imap extends Hm_Output_Module {
    protected function output() {
       $res = '
                   <div class="step_config-imap_bloc mb-6" id="step_config-imap_bloc">
                      <label><strong>IMAP</strong></label>
                      <div class="form-floating">
                        <input required type="text" id="srv_setup_stepper_imap_address" name="srv_setup_stepper_imap_address" class="txt_fld form-control" value="" placeholder="'.$this->trans('Address').'">
                        <label class="" for="srv_setup_stepper_imap_address">'.$this->trans('Address').'</label>
                        <span id="srv_setup_stepper_imap_address-error" class="invalid-feedback"></span>
                      </div>
                      <div class="d-flex">
                        <div class="flex-fill">
                              <div class="form-floating">
                                <input required type="number" id="srv_setup_stepper_imap_port" name="srv_setup_stepper_imap_port" class="txt_fld form-control" value="" placeholder="'.$this->trans('Port').'">
                                <label class="" for="srv_setup_stepper_imap_port">'.$this->trans('Port').'</label>
                                <span id="srv_setup_stepper_imap_port-error" class="invalid-feedback"></span>
                              </div>
                              <span id="srv_setup_stepper_imap_port-error" class="invalid-feedback"></span>
                        </div>
                        <div class="p-2 flex-fill">
                            <div class="form-check">
                                <input class="form-check-input" type="radio" id="imap_tls" name="srv_setup_stepper_imap_tls" value="true">
                                <label class="form-check-label" style="font-size: 12px;" for="imap_tls">
                                  '.$this->trans('Use TLS').'
                                </label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" id="imap_start_tls" name="srv_setup_stepper_imap_tls" value="false" checked>
                                <label class="form-check-label" style="font-size: 12px;" for="imap_start_tls">
                                  '.$this->trans('STARTTLS or unencrypted').'
                                </label>
                            </div>
                        </div>
                      </div>
                   ';

                     if ($this->get('sieve_filters_enabled')) {
                         $default_value = '';
                             $res .=  '

                                        <div class="form-check">
                                           <input class="form-check-input" type="checkbox" id="srv_setup_stepper_enable_sieve"  onchange="handleSieveStatusChange(this)">
                                           <label class="form-check-label" for="srv_setup_stepper_enable_sieve">'.$this->trans('Enable Sieve').'</label>
                                        </div>
                                        <div id="srv_setup_stepper_imap_sieve_host_bloc" class="hide">
                                            <div class="form-floating">
                                                <input required type="text" id="srv_setup_stepper_imap_sieve_host" name="srv_setup_stepper_imap_sieve_host" class="txt_fld form-control" value="" placeholder="'.$this->trans('Sieve Host').'">
                                                <label class="" for="srv_setup_stepper_imap_sieve_host">'.$this->trans('Sieve Host').'</label>
                                                <span id="srv_setup_stepper_imap_sieve_host-error" class="invalid-feedback"></span>
                                            </div>
                                            <div class="form-floating">
                                                <div class="form-check" id="srv_setup_stepper_imap_sieve_mode_tls_bloc">
                                                    <input class="form-check-input" type="checkbox" role="switch" value="1" id="srv_setup_stepper_imap_sieve_mode_tls" name="srv_setup_stepper_imap_sieve_mode_tls">
                                                    <label class="form-check-label" for="srv_setup_stepper_imap_sieve_mode_tls">
                                                        '.$this->trans('Sieve TLS Mode').'
                                                    </label>
                                                </div>
                                            </div>
                                        </div>';
                     }
                $res .= '</div>';

       return $res;
    }
}

class Hm_Output_stepper_setup_server_jmap_imap_common extends Hm_Output_Module {
    protected function output() {
        $res = '
            <div class="form-check" id="step_config_combined_view">
                <input class="form-check-input" type="checkbox" role="switch" id="srv_setup_stepper_imap_hide_from_c_page" name="srv_setup_stepper_imap_hide_from_c_page">
                <label class="form-check-label" for="srv_setup_stepper_imap_hide_from_c_page">
                    '.$this->trans('Hide From Combined Pages').'
                </label>
            </div>
        ';

        return $res;
    }
}

class Hm_Output_server_config_ews extends Hm_Output_Module {
    protected function output() {
        $hasEWSActivated = in_array('imap', $this->get('router_module_list'), true);

        if(! $hasEWSActivated){
            return '';
        }

        $ews_servers_count = count(array_filter($this->get('imap_servers', array()), function($v) { return array_key_exists('type', $v) && $v['type'] == 'ews'; }));

        $res = '<div class="ews_server_setup">
                <div data-target=".ews_server_config_section" class="server_section border-bottom cursor-pointer px-1 py-3 pe-auto">
                    <a href="#" class="pe-auto">
                        <i class="bi bi-envelope-fill me-3"></i>
                        <b>' . $this->trans('Exchange Servers') . '</b>
                    </a>
                    <div class="server_count"><span class="ews_server_count"> ' . $ews_servers_count .'</span> ' . $this->trans('EWS') . '</div>
                </div>
                <div class="ews_server_config_section px-4 pt-3 me-0">
                    <div class="d-none col-12 col-xl-7 mb-4" id="ews_form">
                        <form class="me-0" method="POST">
                        <div>
                            <input type="hidden" name="hm_page_key" value="'.$this->html_safe(Hm_Request_Key::generate()).'" />
                            <input type="hidden" name="ews_server_id" id="ews_server_id" />
                            <div class="form-floating mb-3">
                                <input required type="text" id="ews_profile_name" name="ews_profile_name" class="txt_fld form-control" value="" placeholder="'.$this->trans('Name').'">
                                <label class="" for="ews_profile_name">'.$this->trans('Name').'</label>
                            </div>
                            <div class="form-floating mb-3">
                                <input required type="text" id="ews_server" name="ews_server" class="txt_fld form-control warn_on_paste" value="" placeholder="'.$this->trans('Server Address').'">
                                <label class="" for="ews_server">'.$this->trans('Server Address').'</label>
                            </div>
                            <div class="form-floating mb-3">
                                <input required type="text" id="ews_email" name="ews_email" class="txt_fld form-control warn_on_paste" value="" placeholder="'.$this->trans('Email or Username').'" autocomplete="username">
                                <label class="" for="ews_email">'.$this->trans('Email or Username').'</label>
                            </div>
                            <div class="form-floating mb-3">
                                <input type="password" id="ews_password" name="ews_password" class="txt_fld form-control warn_on_paste" value="" placeholder="'.$this->trans('Password').'" autocomplete="new-password">
                                <label class="" for="ews_password">'.$this->trans('Password').'</label>
                            </div>
                            <div class="form-check">
                                <input type="hidden" name="ews_hide_from_c_page" value="0">
                                <input class="form-check-input" type="checkbox" role="switch" id="ews_hide_from_c_page" name="ews_hide_from_c_page" value="1">
                                <label class="form-check-label" for="ews_hide_from_c_page">
                                    '.$this->trans('Hide From Combined Pages').'
                                </label>
                            </div>
                            <div class="form-check form-switch mt-3">
                                <input type="hidden" name="ews_create_profile" value="0">
                                <input class="form-check-input" type="checkbox" role="switch" onchange="handleCreateProfileCheckboxChange(this)" id="ews_create_profile" name="ews_create_profile" value="1" checked>
                                <label class="form-check-label" for="ews_create_profile">'.$this->trans('Create Profile').'</label>
                            </div>
                            <div class="ms-3">
                                <div class="form-floating mb-2">
                                    <input type="text" id="ews_profile_reply_to" name="ews_profile_reply_to" class="txt_fld form-control" value="" placeholder="'.$this->trans('Reply to').'">
                                    <label class="" for="ews_profile_reply_to">'.$this->trans('Reply to').'</label>
                                </div>
                                <div class="form-floating mb-2">
                                    <input type="text" id="ews_profile_signature" name="ews_profile_signature" class="txt_fld form-control" value="" placeholder="'.$this->trans('Signature').'">
                                    <label class="" for="ews_profile_signature">'.$this->trans('Signature').'</label>
                                </div>
                                <div class="form-check">
                                    <input type="hidden" name="ews_profile_is_default" value="0">
                                    <input class="form-check-input" type="checkbox" role="switch" id="ews_profile_is_default" name="ews_profile_is_default" value="1" checked>
                                    <label class="form-check-label" for="ews_profile_is_default">'.$this->trans('Set this profile default').'</label>
                                </div>
                            </div>
                        </div>
                        <div class="step_config-actions mt-4 d-flex justify-content-between">
                            <input type="submit" class="btn btn-primary px-3" value="'.$this->trans('Save').'">
                        </div>
                        </form>
                    </div>
                    <button class="ews-btn btn btn-primary px-4" id="add_new_ews_button"><i class="bi bi-plus-square-fill me-2"></i> '.$this->trans('Add a new server').'</button>';

        $list = $this->get('imap_servers', array());
        foreach ($list as $index => $vals) {
            if (! array_key_exists('type', $vals) || $vals['type'] != 'ews') {
                continue;
            }

            $server_id = $vals['id'];

            if (array_key_exists('user', $vals) && !array_key_exists('nopass', $vals)) {
                $disabled = 'disabled="disabled"';
                $user_pc = $vals['user'];
                $pass_pc = $this->trans('[saved]');
                $pass_value = '*************';
            }
            elseif (array_key_exists('nopass', $vals)) {
                if (array_key_exists('user', $vals)) {
                    $user_pc = $vals['user'];
                }
                else {
                    $user_pc = '';
                }
                $pass_pc = $this->trans('Password');
                $disabled = '';
                $pass_value = '';
            }
            else {
                $user_pc = '';
                $pass_pc = $this->trans('Password');
                $disabled = '';
                $pass_value = '';
            }
            $res .= '<div class="ews_server mt-3 mb-3">';
            $res .= '<form class="imap_connect" method="POST">';
            $res .= '<input type="hidden" name="hm_page_key" value="'.$this->html_safe(Hm_Request_Key::generate()).'" />';
            $res .= '<input type="hidden" name="imap_server_id" class="imap_server_id" value="'.$this->html_safe($server_id).'" />';
            $res .= '<div class="row m-0 p-0 credentials-container"><div class="col-xl-2 col-lg-2 col-md-6">';
            $res .= sprintf('
                <div class="text-muted"><strong>%s</strong></div>
                <div class="server_subtitle">%s</div>',
                $this->html_safe($vals['name']), $this->html_safe($vals['server']));

            $res .= '</div> <div class="col-xl-7 col-lg-7 col-md-9"> <div class="row"> <div class="col-md-6 col-lg-4  ">';

            $res .= '<div class="form-floating">';
            $res .= '<input '.$disabled.' id="imap_user_'.$server_id.'" class="form-control credentials" type="text" name="imap_user" value="'.$this->html_safe($user_pc).'" placeholder="'.$this->trans('Username').'">';
            $res .= '<label for="imap_user_'.$server_id.'">'.$this->trans('Username').'</label></div>';
            $res .= '</div><div class="col-md-6 col-lg-4">';
            $res .= '<div class="form-floating">';
            $res .= '<input '.$disabled.' id="imap_pass_'.$server_id.'" class="form-control credentials imap_password" type="password" name="imap_pass" value="'.$pass_value.'" placeholder="'.$pass_pc.'">';
            $res .= '<label for="imap_pass_'.$server_id.'">'.$this->trans('Password').'</label></div>';
            $res .= '</div><div class="col-md-6 col-lg-4">';

            $res .= '</div></div></div><div class="col-lg-3 d-flex justify-content-start align-items-center">';

            // Buttons
            $disabled = isset($vals['default']) ? ' disabled': '';
            if (!isset($vals['user']) || !$vals['user']) {
                $res .= '<input type="submit" value="'.$this->trans('Delete').'" class="imap_delete btn btn-outline-danger btn-sm me-2" />';
                $res .= '<input type="submit" value="'.$this->trans('Save').'" class="save_imap_connection btn btn-primary btn-sm me-2" />';
            } else {
                $keysToRemove = array('object', 'connected', 'default', 'nopass');
                $serverDetails = array_diff_key($vals, array_flip($keysToRemove));

                $res .= '<input type="submit" value="'.$this->trans('Edit').'" class="edit_ews_server_connection btn btn-outline-success btn-sm me-2"'.$disabled.' data-server-details=\''.$this->html_safe(json_encode($serverDetails)).'\' data-id="'.$this->html_safe($serverDetails['name']).'" data-type="ews" />';
                $res .= '<input type="submit" value="'.$this->trans('Test').'" class="test_imap_connect btn btn-outline-primary btn-sm me-2" />';
                $res .= '<input type="submit" value="'.$this->trans('Delete').'" class="imap_delete btn btn-outline-danger btn-sm me-2"'.$disabled.' />';
            }

            // Hide/Unhide Buttons
            $hidden = array_key_exists('hide', $vals) && $vals['hide'];
            $res .= '<input type="submit" '.($hidden ? 'style="display: none;" ' : '').'value="'.$this->trans('Hide').'" class="hide_imap_connection btn btn-outline-secondary btn-sm me-2" />';
            $res .= '<input type="submit" '.(!$hidden ? 'style="display: none;" ' : '').'value="'.$this->trans('Unhide').'" class="unhide_imap_connection btn btn-outline-secondary btn-sm me-2" />';

            $res .= '<input type="hidden" value="ajax_imap_debug" name="hm_ajax_hook" />';
            $res .= '</div></div></div></form>';
        }

        $res .= '
                </div>
            </div>';

        return $res;
    }
}

/**
 * Option to set the per page count for IMAP folder views
 * @subpackage imap/output
 */
class Hm_Output_first_time_screen_emails_per_page_setting extends Hm_Output_Module {
    protected function output() {
        $settings = $this->get('user_settings', array());
        $per_page = 20;
        $reset = '';
        if (array_key_exists('first_time_screen_emails', $settings) && $settings['first_time_screen_emails']) {
            $per_page = $settings['first_time_screen_emails'];
        }
        if ($per_page != 20) {
            $reset = '<span class="tooltip_restore" restore_aria_label="Restore default value"><i class="bi bi-arrow-counterclockwise refresh_list reset_default_value_input"></i></span>';
        }
        return '<tr class="general_setting"><td><label for="first_time_screen_emails">'.
            $this->trans('First-time screen senders').'</label></td><td><input class="form-control form-control-sm w-auto" type="text" id="first_time_screen_emails" '.
            'name="first_time_screen_emails" value="'.$this->html_safe($per_page).'" />'.$reset.'</td></tr>';
    }
}
class Hm_Output_setting_move_messages_in_screen_email extends Hm_Output_Module {
    protected function output() {
        $settings = $this->get('user_settings', array());
        $checked = "";
        if (array_key_exists('move_messages_in_screen_email', $settings) && $settings['move_messages_in_screen_email']) {
            if ($settings['move_messages_in_screen_email']) {
                $checked = "checked";
            }
        }
        $res = '<tr class="general_setting"><td><label for="move_messages_in_screen_email">'.
            $this->trans('Move all messages in Screen Email folder if dislike email').'</label></td><td><input class="form-check-input" type="checkbox" role="switch" id="move_messages_in_screen_email" name="move_messages_in_screen_email" '.$checked.' ></td></tr>';

        return $res;
    }
}

class Hm_Output_setting_active_preview_message extends Hm_Output_Module {
    protected function output() {
        $settings = $this->get('user_settings', array());
        $checked = "";
        if (array_key_exists('active_preview_message', $settings) && $settings['active_preview_message']) {
            if ($settings['active_preview_message']) {
                $checked = "checked";
            }
        }
        $res = '<tr class="general_setting"><td><label for="active_preview_message">'.
            $this->trans('Active preview message').'</label></td><td><input class="form-check-input" type="checkbox" role="switch" id="active_preview_message" name="active_preview_message" '.$checked.' ></td></tr>';
        return $res;
    }
}
class Hm_Output_setting_ceo_detection_fraud extends Hm_Output_Module {
    protected function output() {
        $settings = $this->get('user_settings', array());
        $ceo_use_detect_ceo_fraud = "";
        $ceo_use_trusted_contact = "checked";
        $ceo_suspicious_terms = "wire transfer, urgent, account details, payment instruction";
        $ceo_rate_limit = "100";
        if (array_key_exists('ceo_use_detect_ceo_fraud', $settings)) {
            if ($settings['ceo_use_detect_ceo_fraud']) {
                $ceo_use_detect_ceo_fraud = "checked";
            } else {
                $ceo_use_detect_ceo_fraud = "";
            }
        }

        if (array_key_exists('ceo_use_trusted_contact', $settings)) {
            if ($settings['ceo_use_trusted_contact']) {
                $ceo_use_trusted_contact = "checked";
            } else {
                $ceo_use_trusted_contact = "";
            }
        }

        if (array_key_exists('ceo_suspicious_terms', $settings) && $settings['ceo_suspicious_terms']) {
            if ($settings['ceo_suspicious_terms']) {
                $ceo_suspicious_terms = $settings['ceo_suspicious_terms'];
            }
        }
        if (array_key_exists('ceo_rate_limit', $settings) && $settings['ceo_rate_limit']) {
            if ($settings['ceo_rate_limit']) {
                $ceo_rate_limit = $settings['ceo_rate_limit'];
            }
        }

        $res = '<tr class="general_setting"><td><label for="ceo_use_detect_ceo_fraud">'.
            $this->trans('CEO fraud: Use Detect CEO Fraud').
            '</label></td><td><input class="form-check-input" type="checkbox" role="switch" id="ceo_use_detect_ceo_fraud" name="ceo_use_detect_ceo_fraud" '. $ceo_use_detect_ceo_fraud .' ></td></tr>';
        $res .= '<tr class="general_setting"><td><label for="ceo_use_trusted_contact">'.
            $this->trans('CEO fraud: Use Trusted Contacts as Valid emails ').
            '</label></td><td><input class="form-check-input" type="checkbox" role="switch" id="ceo_use_trusted_contact" name="ceo_use_trusted_contact" '. $ceo_use_trusted_contact .' ></td></tr>';
        $res .= '<tr class="general_setting"><td><label for="ceo_suspicious_terms">'.
            $this->trans('CEO fraud: Suspicious Phrases or Requests(separate by ",")').
            '</label></td><td><textarea class="form-control form-control-sm w-auto" role="switch" id="ceo_suspicious_terms" name="ceo_suspicious_terms">'. $ceo_suspicious_terms .'</textarea></td></tr>';
        $res .= '<tr class="general_setting"><td><label for="ceo_rate_limit">'.
            $this->trans('CEO fraud: Rate-Limit or Monitor Unusual Requests').
            '</label></td><td><input class="form-control form-control-sm w-auto" type="number" min="0" role="switch" id="ceo_rate_limit" name="ceo_rate_limit" value="'. $ceo_rate_limit .'" ></td></tr>';
        return $res;
    }
}
