<?php

/**
 * @subpackage scheduled_sends/output
 */
class Hm_Output_scheduled_folder_link extends Hm_Output_Module {
    protected function output() {
        $res = '<li class="menu_scheduled"><a class="unread_link" href="'.$this->build_page_url('message_list', ['list_path' => 'scheduled']).'">';
        if (!$this->get('hide_folder_icons')) {
            $res .= '<i class="bi bi-alarm menu-icon"></i>';
        }
        $res .= '<span class="nav-label">'.$this->trans('Scheduled').'</span></a></li>';
        if ($this->format == 'HTML5') {
            return $res;
        }
        $this->concat('formatted_folder_list', $res);
    }
}

/**
 * Add reschedule dialog to the message list controls
 * @subpackage imap/output
 */
class Hm_Output_reschedule_msg_control extends Hm_Output_Module {
    protected function output() {
        if ($this->get('list_path') != 'scheduled') {
            return;
        }

        $this->concat('msg_controls_extra', schedule_dropdown($this, true, true));
    }
}
