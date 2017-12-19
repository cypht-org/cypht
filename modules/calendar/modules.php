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
class Hm_Handler_vcalendar_check extends Hm_Handler_Module {
    public function process() {
        $struct = $this->get('msg_struct', array());
        if (count($struct) > 0 && class_exists('Hm_IMAP')) {
            $imap = new Hm_IMAP();
            $imap->struct_object = new Hm_IMAP_Struct(array(), $imap);
            $cal_struct = $imap->search_bodystructure($struct, array('subtype' => 'calendar'));
            if (is_array($cal_struct) && count($cal_struct) > 0) {
                $this->out('imap_calendar_struct', $cal_struct);
            }
        }
    }
}

/**
 * @subpackage calendar/handler
 */
class Hm_Handler_process_delete_event extends Hm_Handler_Module {
    public function process() {
        list($success, $form) = $this->process_form(array('delete_id'));
        if ($success) {
            $cal_events = $this->get('cal_events');
            if (is_object($cal_events)) {
                if ($cal_events->delete($form['delete_id'])) {
                    Hm_Msgs::add('Event Deleted');
                    $this->session->record_unsaved('Calendar updated');
                    $this->user_config->set('calendar_events', $cal_events->dump());
                }
            }
        }
    }
}

/**
 * @subpackage calendar/handler
 */
class Hm_Handler_process_add_event extends Hm_Handler_Module {
    public function process() {
        list($success, $form) = $this->process_form(array('event_title',
            'event_date', 'event_time', 'event_repeat'));
        if ($success) {
            $ts = strtotime(sprintf('%s %s', $form['event_date'], $form['event_time']));
            if ($ts) {
                $repeat = '';
                if (in_array($form['event_repeat'], array('day', 'week', 'month', 'year'), true)) {
                    $repeat = $form['event_repeat'];
                }
                $detail = '';
                if (array_key_exists('event_detail', $this->request->post)) {
                    $detail = $this->request->post['event_detail'];
                }
                $cal_events = $this->get('cal_events');
                if (is_object($cal_events)) {
                    if ($cal_events->add(array(
                        'title' => html_entity_decode($form['event_title'], ENT_QUOTES),
                        'description' => html_entity_decode($detail, ENT_QUOTES),
                        'date' => $ts,
                        'repeat_interval' => $repeat
                        ))) {
                        Hm_Msgs::add('Event Created');
                        $this->session->record_unsaved('Calendar updated');
                        $this->user_config->set('calendar_events', $cal_events->dump());
                    }
                }
            }
        }
    }
}

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
        if (array_key_exists('action', $this->request->get)) {
            if ($this->request->get['action'] == 'add') {
                $this->out('cal_action', 'add');
            }
        }
        $cal_events = new Hm_Cal_Event_Store();
        $events = $this->user_config->get('calendar_events', array());
        if (count($events) > 0) {
            $cal_events->load($events);
        }
        $this->out('cal_events', $cal_events);
        $this->out('calendar_date', $date);
    }
}

/**
 * @subpackage calendar/output
 */
class Hm_Output_vcalendar_add_output extends Hm_Output_Module {
    protected function output() {
        if ($this->get('imap_calendar_struct')) {
            $link = '<a class="add_vcal" href="">Add to calendar</a>';
            $this->concat('msg_headers', $link);
        }
    }
}

/**
 * @subpackage calendar/output
 */
class Hm_Output_calendar_page_link extends Hm_Output_Module {
    protected function output() {
        $res = '<li class="menu_calendar"><a class="unread_link" href="?page=calendar">';
        if (!$this->get('hide_folder_icons')) {
            $res .= '<img class="account_icon" src="'.$this->html_safe(Hm_Image_Sources::$calendar).'" alt="" width="16" height="16" /> ';
        }
        $res .= $this->trans('Calendar').'</a></li>';
        if ($this->format == 'HTML5') {
            return $res;
        }
        $this->concat('formatted_folder_list', $res);
    }
}

/**
 * @subpackage calendar/output
 */
class Hm_Output_add_cal_event_form extends Hm_Output_Module {
    protected function output() {
        $repeat_opts = array(
            'none' => $this->trans('None'),
            'day' => $this->trans('Daily'),
            'week' => $this->trans('Weekly'),
            'month' => $this->trans('Monthly'),
            'year' => $this->trans('Yearly')
        );
        if ($this->get('cal_action') == 'add') {
            $res = '<div class="calendar"><div class="content_title">'.$this->trans('Add an Event').'</div>'.
                '<form method="post">'.
                '<input type="hidden" name="hm_page_key" value="'.$this->html_safe(Hm_Request_Key::generate()).'" />'.
                '<table class="event_form">'.
                '<tr><td class="cal_label">'.$this->trans('Title').'</td>'.
                '<td><input required type="text" name="event_title" /></td></tr>'.
                '<tr><td class="cal_label">'.$this->trans('Detail').'</td>'.
                '<td><textarea name="event_detail"></textarea></td></tr>'.
                '<tr><td class="cal_label">'.$this->trans('Date').'</td>'.
                '<td><input required type="date" name="event_date" placeholder="MM/DD/YYYY" /></td></tr>'.
                '<tr><td class="cal_label">'.$this->trans('Time').'</td>'.
                '<td><input required type="time" name="event_time" placeholder="HH:MM" /></td></tr>'.
                '<tr><td class="cal_label">'.$this->trans('Repeat').'</td>'.
                '<td><select name="event_repeat">';
            foreach ($repeat_opts as $val => $name) {
                $res .= '<option value="'.$val.'">'.$name.'</option>';
            }
            $res .= '</select></td></tr>'.
                '<tr><td></td><td class="event_submit"><input type="submit" value="'.$this->trans('Create').
                '" /></td></tr></tbody></table></form></div>';

            return $res;
        }
    }
}

/**
 * @subpackage calendar/output
 */
class Hm_Output_calendar_content extends Hm_Output_Module {
    protected function output() {
        if (!$this->get('cal_action')) {
            $cal_events = $this->get('cal_events');
            $date = $this->get('calendar_date', date('r'));
            $view = $this->get('calendar_view', 'month');
            $cal = new Hm_Cal_Data();
            $data = $cal->output($date, $view);
            $bounds = get_date_bounds($data, $view);
            $events = $cal_events->in_date_range($bounds[0], $bounds[1]);
            $out = new Hm_Cal_Output($this, $events);
            $out = $out->output($data, $date, $view);
            return '<div class="calendar"><div class="content_title">'.$this->trans('Calendar').'</div>'.
                '<div class="list_controls"><a href="?page=calendar&amp;action=add" title="'.$this->trans('Add Event').'" class="refresh_list">'.
                '<img src="'.Hm_Image_Sources::$plus.'" /></a></div>'.
                $out.'</div>';
        }
    }
}

if (!hm_exists('get_date_bounds')) {
function get_date_bounds($data, $view) {
    if ($view == 'week') {
        $start = $data[0];
        $end = array_pop($data);
    }
    elseif ($view == 'month') {
        $start = $data[0][0];
        $last_week = array_pop($data);
        $end = array_pop($last_week);
    }
    elseif ($view == 'year') {
        $start = $data[1][0][0];
        $last_month = array_pop($data);
        $last_week = array_pop($last_month);
        $end = array_pop($last_week);
    }
    return array($start, $end);
}}
