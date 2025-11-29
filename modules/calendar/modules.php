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
            $link = '<a class="add_vcal float-end mt-2 pe-4" href="">Add to calendar</a>';
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
            $res .= '<i class="bi bi-calendar-week-fill menu-icon"></i>';
        }
        $res .= '<span class="nav-label">'. $this->trans('Calendar').'</span></a></li>';
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
            $res = '<div class="calendar p-0">
                        <div class="content_title px-3">'.$this->trans('Add an Event').'</div>
                        <div class="px-4 mt-5">
                            <form method="post">
                                <input type="hidden" name="hm_page_key" value="'.$this->html_safe(Hm_Request_Key::generate()).'" />

                                <div class="mb-3 row">
                                    <label for="event_title" class="col-md-2 col-form-label">'.$this->trans('Title').'</label>
                                    <div class="col-md-10">
                                        <input required type="text" class="form-control" id="event_title" name="event_title">
                                    </div>
                                </div>

                                <div class="mb-3 row">
                                    <label for="event_detail" class="col-md-2 col-form-label">'.$this->trans('Detail').'</label>
                                    <div class="col-md-10">
                                        <textarea class="form-control" id="event_detail" name="event_detail"></textarea>
                                    </div>
                                </div>

                                <div class="mb-3 row">
                                    <label for="event_date" class="col-md-2 col-form-label">'.$this->trans('Date').'</label>
                                    <div class="col-md-5">
                                        <input required type="date" class="form-control" id="event_date" name="event_date" placeholder="MM/DD/YYYY">
                                    </div>
                                </div>

                                <div class="mb-3 row">
                                    <label for="event_time" class="col-md-2 col-form-label">'.$this->trans('Time').'</label>
                                    <div class="col-md-5">
                                        <input required type="time" class="form-control" id="event_time" name="event_time" placeholder="HH:MM">
                                    </div>
                                </div>

                                <div class="mb-3 row">
                                    <label for="event_repeat" class="col-md-2 col-form-label">'.$this->trans('Repeat').'</label>
                                    <div class="col-md-5">
                                        <select class="form-select" id="event_repeat" name="event_repeat">';
                                        foreach ($repeat_opts as $val => $name) {
                                            $res .= '<option value="'.$val.'">'.$name.'</option>';
                                        }
                                        $res .= '</select>
                                    </div>
                                </div>

                                <div class="mb-3 row">
                                    <div class="col-md-2"></div>
                                    <div class="col-md-10">
                                        <button type="submit" class="btn btn-primary">'.$this->trans('Create').'</button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>';

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
            return '<div class="calendar p-0"><div class="content_title d-flex justify-content-between px-3"><span class="calendar_content_title">'.$this->trans('Calendar').'</span>'.
                '<a href="?page=calendar&amp;action=add" title="'.$this->trans('Add Event').'" class="btn btn-light btn-sm text-decoration-none">'.
                '<i class="bi bi-plus-circle me-2"></i> '.$this->trans('Add Event').'</a></div>'.
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
