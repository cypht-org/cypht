<?php

/**
 * Calendar modules
 * @package modules
 * @subpackage calendar
 */

if (!defined('DEBUG_MODE')) { die(); }

require_once APP_PATH.'modules/calendar/hm-calendar.php';

/**
 * @subpackage calendar/handler
 */
class Hm_Handler_get_calendar_date extends Hm_Handler_Module {
    public function process() {
        $date = date('r');
        if (array_key_exists('date', $this->request->get)) {
            if (strtotime($this->request->get['date']) !== false) {
                $date = $this->request->get['date'];
            }
        }
        if (array_key_exists('view', $this->request->get)) {
            if (in_array($this->request->get['view'], array('month', 'year', 'week'), true)) {
                $this->out('calendar_view', $this->request->get['view']);
            }
        }
        $this->out('calendar_date', $date);
    }
}

/**
 * @subpackage calendar/output
 */
class Hm_Output_calendar_page_link extends Hm_Output_Module {
    protected function output() {
        $res = '<li class="menu_calendar"><a class="unread_link" href="?page=calendar">'.
            '<img class="account_icon" src="'.$this->html_safe(Hm_Image_Sources::$calendar).'" alt="" width="16" height="16" /> '.$this->trans('Calendar').'</a></li>';
        if ($this->format == 'HTML5') {
            return $res;
        }
        $this->concat('formatted_folder_list', $res);
    }
}

/**
 * @subpackage calendar/output
 */
class Hm_Output_calendar_content extends Hm_Output_Module {
    protected function output() {
        $date = $this->get('calendar_date', date('r'));
        $view = $this->get('calendar_view', 'month');
        $cal = new Hm_Cal_Data();
        $data = $cal->output($date, $view);
        $out = new Hm_Cal_Output($this);
        $out = $out->output($data, $date, $view);
        return '<div class="calendar"><div class="content_title">'.$this->trans('Calendar').'</div>'.
            $out.'</div>';
    }
}


