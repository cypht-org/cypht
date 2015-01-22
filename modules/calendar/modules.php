<?php

if (!defined('DEBUG_MODE')) { die(); }

class Hm_Handler_get_calendar_date extends Hm_Handler_Module {
    public function process() {
        $date = date('r');
        elog($this->request);
        if (array_key_exists('date', $this->request->get)) {
            if (strtotime($this->request->get['date']) !== false) {
                $date = $this->request->get['date'];
            }
        }
        $this->out('calendar_date', $date);
    }
}

class Hm_Output_calendar_page_link extends Hm_Output_Module {
    protected function output($format) {
        $res = '<li class="menu_calendar"><a class="unread_link" href="?page=calendar">'.
            '<img class="account_icon" src="'.$this->html_safe(Hm_Image_Sources::$calendar).'" alt="" width="16" height="16" /> '.$this->trans('Calendar').'</a></li>';
        if ($format == 'HTML5') {
            return $res;
        }
        $this->concat('formatted_folder_list', $res);
    }
}

class Hm_Output_calendar_content extends Hm_Output_Module {
    protected function output($format) {
        $date = $this->get('calendar_date', date('r'));
        $cal = new Hm_Calendar($this, $date);
        return '<div class="calendar"><div class="content_title">'.$this->trans(date('F, Y', strtotime($date))).'</div>'.
            $cal->output().'</div>';
    }
}

class Hm_Calendar {

    private $date;
    private $output_mod;
    private $today;

    public function __construct($output_mod, $date=false) {
        $this->output_mod = $output_mod;
        $this->set_date($date);
        $this->set_today();
    }

    private function set_today() {
        if (date('Ym', $this->date) == date('Ym')) {
            $this->today = (int) date('d');
        }
    }

    private function set_date($date) {
        if (!$date) {
            $this->date = time();
        }
        else {
            $this->date = strtotime($date);
        }
    }

    private function month_params() {
        return array(
            date('w', strtotime(date('Y-m-01', $this->date))),
            date('t', $this->date)
        );
    }

    private function heading() {
        $days = array('Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday');
        return '<tr>'.implode('', array_map(function($v) {
            return sprintf('<th>%s</th>', $this->output_mod->trans($v)); }, $days)).'</tr>';

    }

    public function output() {
        $day = 1;
        list($start, $end) = $this->month_params();
        $res = '<table class="calendar">';
        $res .= $this->heading();
        while ($day < $end) {
            list($row, $day) = $this->row($day, $start, $end);
            $start = 0;
            $res .= $row;
        }
        $res .= '</table>';
        return $res;
    }

    private function row($day, $start, $end) {
        $res = '<tr>';
        for ($i = 0; $i < 7; $i++) {
            list($cell, $day) = $this->cell($i, $day, $start, $end);
            $res .= $cell;
        }
        $res .= '</tr>';
        return array($res, $day);
    }

    private function cell($current_day, $day, $start, $end) {
        $res = '<td';
        if ($day == $this->today) {
            $res .= ' class="today"';
        }
        $res .= '>';
        if ($current_day >= $start && $day <= $end) {
            $res .= $day;
            $day++;
        }
        $res .= '</td>';
        return array($res, $day);
    }
}
?>
