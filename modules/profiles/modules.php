<?php

/**
 * Profile modules
 * @package modules
 * @subpackage profile
 */

if (!defined('DEBUG_MODE')) { die(); }

/**
 * @subpackage profile/handler
 */
class Hm_Handler_profile_data extends Hm_Handler_Module {
    public function process() {
        $accounts = array();
        if ($this->module_is_supported('imap')) {
            foreach (Hm_IMAP_List::dump() as $server) {
                $server['profile_details'] = $this->user_config->get('profile_imap_'.$server['server'].'_'.$server['user'], array());
                $server['type'] = 'imap';
                $accounts[] = $server;
            }
        }
        if ($this->module_is_supported('pop3')) {
            foreach (Hm_POP3_List::dump() as $server) {
                $server['profile_details'] = $this->user_config->get('profile_pop3_'.$server['server'].'_'.$server['user'], array());
                $server['type'] = 'pop3';
                $accounts[] = $server;
            }
        }
        $this->out('account_profiles', $accounts);
    }
}

/**
 * @subpackage profile/output
 */
class Hm_Output_profile_page_link extends Hm_Output_Module {
    protected function output() {
        $res = '<li class="menu_profiles"><a class="unread_link" href="?page=profiles">'.
            '<img class="account_icon" src="'.$this->html_safe(Hm_Image_Sources::$person).
            '" alt="" width="16" height="16" /> '.$this->trans('Profiles').'</a></li>';
        if ($this->format == 'HTML5') {
            return $res;
        }
        $this->concat('formatted_folder_list', $res);
    }
}

/**
 * @subpackage profile/output
 */
class Hm_Output_profile_content extends Hm_Output_Module {
    protected function output() {
        $profiles = $this->get('account_profiles');
        $res = '<div class="profile_content"><div class="content_title">'.$this->trans('Profiles').'</div>';
        if (count($profiles) > 0) {
            $res .= '<table class="profile_details"><tr>'.
                '<th>'.$this->trans('Name').'</th>'.
                '<th>'.$this->trans('Server').'</th>'.
                '<th>'.$this->trans('Username').'</th>'.
                '<th>'.$this->trans('Full Name').'</th>'.
                '<th>'.$this->trans('Reply-to').'</th>'.
                '<th>'.$this->trans('SMTP Server').'</th>'.
                '<th></th></tr>';
            foreach ($profiles as $id => $profile) {
                $res .= '<tr>'.
                    '<td>'.$this->html_safe($profile['name']).'</td>'.
                    '<td>'.$this->html_safe($profile['server']).'</td>'.
                    '<td>'.$this->html_safe($profile['user']).'</td>'.
                    '<td></td>'.
                    '<td></td>'.
                    '<td></td>'.
                    '<td><a href="?page=profiles&amp;profile_id='.$this->html_safe($id).'" title="Edit">'.
                    '<img alt="'.$this->trans('Edit').'" width="16" height="16" src="'.Hm_Image_Sources::$cog.'" /></a></td>'.
                    '</tr>';
            }
            $res .= '</table>';
        }
        else {
            $res .= '<div>'.$this->trans('No IMAP or POP3 servers configured').'</div>';
        }
        $res .= '</div>';
        return $res;
    }
}
