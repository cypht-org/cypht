<?php

/**
 * Calendar modules
 * @package modules
 * @subpackage calendar
 */

if (!defined('DEBUG_MODE')) { die(); }

/**
 * @subpackage calendar/lib
 */
class Hm_Cal_Output {

    private $output_mod;
    private $events;
    private $today;
    private $month;
    private $year;
    private $format;
    private $date;

    public function __construct($output_mod, $events) {
        $this->events = $events;
        $this->output_mod = $output_mod;
        $this->today = date('Y-m-d');
    }

    public function output($data, $date, $format) {
        $this->date = $date;
        $this->year = date('Y', strtotime($date));
        $this->format = $format;
        if (method_exists($this, 'output_'.$format)) {
            if ($format != 'year') {
                $this->month = (int) date('n', strtotime($date));
            }
            return $this->{'output_'.$format}($data);
        }
        else {
            die('Invalid output format');
        }
    }

    private function output_day($day) {
        $res = '<td class="';
        if ($day == $this->today) {
            $res .= 'today ';
        }
        if ($this->month != (int) date('n', strtotime($day))) {
            $res .= 'offmonth ';
        }
        $res .= '">'.date('d', strtotime($day));
        $res .= $this->output_event($day);
        $res .= '</td>';
        return $res;
    }

    private function output_event($day) {
        if (array_key_exists($day, $this->events)) {
            usort($this->events[$day], function($a, $b) {
                if ($a['ts'] == $b['ts']) {
                    return 0;
                }
                return ($a['ts'] < $b['ts']) ? -1 : 1;
            });
            $res = '';
            foreach ($this->events[$day] as $event) {
                $res .= '<div class="cal_event">'.
                    $this->output_event_details($event).
                    $this->output_mod->html_safe(date('H:i', $event['ts'])).
                    ' <a class="cal_title">'.$this->output_mod->html_safe($event['title']).
                    '</a></div>';
            }
            return $res;
        }
        return '';
    }

    private function output_event_details($event) {
        $res = '<div class="event_details">'.
            '<div class="event_title">'.$this->output_mod->html_safe($event['title']).
            '<div class="event_date">'.$this->output_mod->html_safe(date('H:i A', $event['ts'])).'</div></div>';
        if (strlen(trim($event['description']))) {
            $res .= '<div class="event_detail">'.$this->output_mod->html_safe($event['description']).'</div>';
        }
        if (strlen(trim($event['repeat_interval']))) {
            $res .= '<div class="event_repeat">'.$this->output_mod->trans(sprintf('Repeats every %s', $event['repeat_interval'])).'</div>';
        }
            $res .= '<form method="post"><input type="hidden" name="delete_ts" value="'.$this->output_mod->html_safe($event['ts']).'" />'.
                '<input type="hidden" name="delete_id" value="'.$this->output_mod->html_safe($event['id']).'" />'.
                '<input type="hidden" name="hm_page_key" value="'.$this->output_mod->html_safe(Hm_Request_Key::generate()).'" />'.
                '<div class="event_delete"><a>Delete</a></div></form>';
        $res .= '</div>';
        return $res;
    }

    private function output_week($week) {
        $res = '';
        if ($this->format == 'week') {
            $res .= $this->title();
            $res .= '<table class="calendar_week">';
            $res .= $this->output_heading();
        }
        $res .= '<tr>';
        foreach ($week as $day) {
            $res .= $this->output_day($day);
        }
        $res .= '</tr>';
        if ($this->format == 'week') {
            $res .= '</table>';
        }
        return $res;
    }

    private function output_month($month) {
        $res = $this->title();
        $res .= '<table class="calendar_month">';
        $res .= $this->output_heading();
        foreach ($month as $week) {
            $res .= $this->output_week($week);
        }
        $res .= '</table>';
        return $res;
    }

    private function output_year($year) {
        $res = '<div class="calendar_year">';
        foreach ($year as $id => $month) {
            $this->month = $id;
            $res .= $this->output_month($month);
        }
        $res .= '</div>';
        return $res;
    }

    private function prev_next_week() {
        return array(sprintf('<a href="?page=calendar&date=%s">&lt;</a>', date('Y-m-d', strtotime("-1 week", strtotime($this->date)))),
            sprintf('<a href="?page=calendar&date=%s">&gt;</a>', date('Y-m-d', strtotime("+1 week", strtotime($this->date)))));
    }

    private function prev_next_month() {
        return array(sprintf('<a href="?page=calendar&date=%s">&lt;</a>', date('Y-m', strtotime("-1 month", strtotime($this->year.'-'.$this->month)))),
            sprintf('<a href="?page=calendar&date=%s">&gt;</a>', date('Y-m', strtotime("+1 month", strtotime($this->year.'-'.$this->month)))));
    }

    private function prev_next_year() {
        return array(sprintf('<a href="?page=calendar&date=%s">&lt;</a>', date('Y', strtotime("-1 year", strtotime($this->date)))),
            sprintf('<a href="?page=calendar&date=%s">&gt;</a>', date('Y', strtotime("+1 year", strtotime($this->date)))));
    }

    private function prev_next() {
        if (method_exists($this, 'prev_next_'.$this->format)) {
            return $this->{'prev_next_'.$this->format}();
        }
        die('invalid format');
    }

    private function title() {
        list($prev, $next) = $this->prev_next();
        $year = date('Y', strtotime($this->year.'-'.$this->month));
        $month = date('F', strtotime($this->year.'-'.$this->month));
        $title = sprintf('%s, %%s', $month);
        $title = sprintf($this->output_mod->trans($title), $year);
        return '<div class="month_label">'.$prev.' '.$title.' '.$next.'</div>';
    }

    private function output_heading() {
        $days = array('Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday');
        return '<tr>'.implode('', array_map(function($v) {
            return sprintf('<th>%s</th>', $this->output_mod->trans($v)); }, $days)).'</tr>';
    }
}

/**
 * @subpackage calendar/lib
 */
class Hm_Cal_Event_Store {
    private $data = array();

    public function __construct() {
    }

    public function load($data) {
        foreach ($data as $event) {
            $event = new Hm_Cal_Event($event);
            if ($event->is_valid()) {
                $this->data[] = $event;
            }
        }
    }

    public function add($data) {
        $event = new Hm_Cal_Event($data);
        if ($event->is_valid()) {
            $this->data[] = $event;
            return true;
        }
        return false;
    }

    public function dump() {
        $res = array();
        foreach ($this->data as $event) {
            $res[] = $event->dump();
        }
        return $res;
    }

    public function in_date_range($start, $end) {
        $events = array();
        foreach ($this->data as $event) {
            $event_data = $event->in_date_range($start, $end);
            if (count($event_data)) {
                foreach ($event_data as $event) {
                    if (array_key_exists($event['date'], $events)) {
                        $events[$event['date']][] = $event;
                    }
                    else {
                        $events[$event['date']] = array($event);
                    }
                }
            }
        }
        return $events;
    }

    public function delete($id) {
        $events = array();
        $deleted = false;
        foreach ($this->data as $event) {
            if ($event->get('id') == $id) {
                $deleted = true;
                continue;
            }
            $events[] = $event;
        }
        $this->data = $events;
        return $deleted;
    }
}

/**
 * @subpackage calendar/lib
 */
class Hm_Cal_Event {

    private $data = array(
        'title' => NULL,
        'description' => NULL,
        'date' => NULL,
        'repeat_interval' => NULL,
    );

    public function __construct($data) {
        if (is_array($data)) {
            foreach ($this->data as $name => $value) {
                if (array_key_exists($name, $data)) {
                    $this->data[$name] = $data[$name];
                }
            }
        }
        $this->data['id'] = md5(implode('', array_values($this->data)));
    }

    public function is_valid() {
        return $this->get('date');
    }

    public function get($name, $default=false) {
        if (array_key_exists($name, $this->data)) {
            return $this->data[$name];
        }
        return $default;
    }
    public function dump() {
        return $this->data;
    }

    private function check_start_date($start, $end) {
        $ts = false;
        if ($this->get('date')) {
            $ts = $this->get('date');
            if ($ts) {
                return $ts;
            }
        }
        return $ts;
    }

    private function fast_forward_repeat_event($ts, $interval, $limit) {
        while ($ts < $limit) {
            $ts = strtotime('+1 '.$interval, $ts);
        }
        return $ts;
    }

    private function collect_repeat_events($ts, $interval, $limit) {
        $times = array();
        while ($ts < $limit) {
            $times[] = $ts;
            $ts = strtotime('+1 '.$interval, $ts);
        }
        return $times;
    }

    private function check_repeat($start, $end, $ts) {
        $times = array();
        if ($this->get('repeat_interval')) {
            if (strtotime('+1 '.$this->get('repeat_interval'), $ts)) {
                $ts = $this->fast_forward_repeat_event($ts, $this->get('repeat_interval'), $start);
                if ($ts < $end) {
                    $times = array_merge($times, $this->collect_repeat_events($ts, $this->get('repeat_interval'), $end));
                }
            }
        }
        return $times;
    }

    public function in_date_range($start_date, $end_date) {
        $start = strtotime($start_date);
        $end = strtotime($end_date);
        $times = array();
        $results = array();
        if ($start && $end) {
            $ts = $this->check_start_date($start, $end);
            if ($ts) {
                if ($ts > $start && $ts < $end) {
                    $times[] = $ts;
                }
                $times = array_merge($times, $this->check_repeat($start, $end, $ts));
            }
        }
        $times = array_unique($times);
        return $this->fill_event_details($times);
    }

    private function fill_event_details($times) {
        $results = array();
        foreach ($times as $time) {
            $result_item = array();
            $day = date('Y-m-d', $time);
            foreach ($this->data as $name => $val) {
                $result_item[$name] = $val;
            }
            $result_item['ts'] = $time;
            $result_item['date'] = date('Y-m-d', $time);
            $results[] = $result_item;
        }
        return $results;
    }
}

/**
 * @subpackage calendar/lib
 */
class Hm_Cal_Data {

    private $ts = false;
    private $today = false;
    private $day_pos = false;
    private $month_pos = false;

    public function __construct() {
        $this->today = time();
    }

    private function check_input_params($str_time, $format) {
        $this->ts = strtotime($str_time);
        if ($this->ts === false || $this->ts === -1) {
            die('Invalid date input');
        }
        $this->set_start_ts($format);
    }

    public function output($str_time, $format='month') {
        $this->check_input_params($str_time, $format);
        if (method_exists($this, $format)) {
            return $this->$format();
        }
    }

    private function set_start_ts($format) {
        if (method_exists($this, 'start_'.$format)) {
            $this->{'start_'.$format}($this->ts);
        }
        else {
            die('Invalid output format');
        }
    }

    private function start_week($ts) {
        $offset = date('w', $ts);
        $this->day_pos = strtotime(sprintf('-%d day', $offset), $ts);
    }

    private function start_month($ts) {
        $first_day = strtotime(date('Y-m-01', $ts));
        $this->month_pos = (int) date('n', $ts);
        $offset = date('w', $first_day);
        $this->day_pos = strtotime(sprintf('-%d day', $offset), $first_day);
    }
 
    private function start_year($ts) {
        $first_day = strtotime(date('Y-01-01', $ts));
        $offset = date('w', $first_day);
        $this->day_pos = strtotime(sprintf('-%d day', $offset), $first_day);
        $this->month_pos = 1;
    }

    private function increment_day() {
        $this->day_pos = strtotime('+1 day', $this->day_pos);
    }

    private function week() {
        $res = array();
        for ($i = 0; $i < 7; $i++) {
            $res[] = $this->day();
        }
        return $res;
    }

    private function day() {
        $day = date('Y-m-d', $this->day_pos);
        $this->increment_day();
        return $day;
    }

    private function month() {
        $res = array();
        for ($i = 0; $i < 6; $i++) {
            $res[] = $this->week();
            if ((int) date('n', $this->day_pos) !== $this->month_pos) {
                break;
            }
        }
        return $res;
    }

    private function year() {
        $res = array();
        for ($i = 0; $i < 12; $i++) {
            $res[$this->month_pos] = $this->month();
            $this->start_month($this->day_pos);
        }
        return $res;
    }
}
