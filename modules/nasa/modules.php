<?php

/**
 * NASA API modules
 * @package modules
 * @subpackage nasa
 */

if (!defined('DEBUG_MODE')) { die(); }
define('APOD_URL', 'https://api.nasa.gov/planetary/apod?concept_tags=True&api_key=%s');

/**
 * @subpackage nasa/handler
 */
class Hm_Handler_process_nasa_connection extends Hm_Handler_Module {
    public function process() {
        list($success, $form) = $this->process_form(array('api_key'));
        if ($success) {
            $api = new Hm_API_Curl();
            $result = $api->command(sprintf(APOD_URL, $form['api_key']));
            if (empty($result) || (array_key_exists('error', $result) && !empty($result['error']))) {
                Hm_Msgs::add(sprintf('%s', $result['error']['message']), 'danger');
                $this->out('nasa_action_status', false);
            }
            else {
                $this->user_config->set('nasa_api_key', $form['api_key']);
                $user_data = $this->user_config->dump();
                $this->session->set('user_data', $user_data);
                $this->session->record_unsaved('NASA API connection');
                Hm_Msgs::add('Successfully connected to NASA APIs');
                $this->out('nasa_action_status', true);
            }
            return;
        }
        list($success, $form) = $this->process_form(array('nasa_disconnect'));
        if ($success) {
            $this->user_config->set('nasa_api_key', '');
            $user_data = $this->user_config->dump();
            $this->session->set('user_data', $user_data);
            $this->session->record_unsaved('NASA API connection disabled');
            Hm_Msgs::add('NASA APIs disabled');
            $this->out('nasa_action_status', true);
        }
    }
}

/**
 * @subpackage nasa/handler
 */
class Hm_Handler_fetch_apod_content extends Hm_Handler_Module {
    public function process() {
        $key = $this->user_config->get('nasa_api_key', '');
        // Today needs to be the day at NASA HQ
        $date = dateInESTTZ();
        if ($key) {
            if (array_key_exists('apod_date', $this->request->get)) {
                $apod_date = $this->request->get['apod_date'];
                if (preg_match("/^\d{4}-\d{2}-\d{2}$/", $apod_date))
                    $date = $apod_date;
            }
            $api = new Hm_API_Curl();
            $res = $api->command(sprintf(APOD_URL.'&date=%s', $key, $date));
            $this->out('apod_data', $res);
            $this->out('apod_date', $date);
            $headers = $this->get('http_headers');
            $headers['Content-Security-Policy'] = str_replace('img-src', 'img-src http://apod.nasa.gov', $headers['Content-Security-Policy']);
            $this->out('http_headers', $headers);
        }
    }
}

/**
 * @subpackage nasa/handler
 */
class Hm_Handler_nasa_folder_data extends Hm_Handler_Module {
    public function process() {
        $this->out('nasa_api_key', $this->user_config->get('nasa_api_key', ''));
    }
}

/**
 * @subpackage nasa/output
 */
class Hm_Output_apod_content extends Hm_Output_Module {
    protected function output() {
        $data = $this->get('apod_data');
        $date = $this->get('apod_date', date('Y-m-d'));
        $res = '<div class="content_title">'.$this->trans('Astronomy Picture of the Day');
        $res .= apod_date_form($date, $this);
        $res .= '</div>';
        if (!$data || $data == array() || array_key_exists('error', $data) || array_key_exists('code', $data)) {
            $res .= '<div class="apod_error">';
            if (is_array($data) && array_key_exists('error', $data) && array_key_exists('message', $data['error'])) {
                $res .= $this->html_safe($data['error']['message']);
            }
            else {
                $res .= $this->trans('Could not find a picture for the requested day');
            }
            $res .= '</div>';
        }
        else {
            if (array_key_exists('title', $data)) {
                $res .= '<div class="apod_title">'.$this->html_safe($data['title']).'</div>';
            }
            if (array_key_exists('media_type', $data)) {
                if ($data['media_type'] == 'image' && array_key_exists('url', $data)) {
                    $res .= '<div class="apod_image"><img class="msg_img" src="'.$this->html_safe($data['url']).
                        '" alt="'.$this->trans('Picture of the day').'" /></div>';
                }
                elseif ($data['media_type'] == 'video' && array_key_exists('url', $data)) {
                    $res .= '<div class="apod_video"><a href="'.$this->html_safe($data['url']).'" target="_blank" rel="noopener">YouTube</a></div>';
                }
            }
            elseif (array_key_exists('url', $data) && preg_match("/jpg$/i", $data['url'])) {
                $res .= '<div class="apod_image"><img class="msg_img" alt="'.$this->trans('Picture of the day').
                    '" src="'.$this->html_safe($data['url']).'" /></div>';
            }
            if (array_key_exists('explanation', $data)) {
                $res .= '<div class="apod_desc">'.$this->html_safe($data['explanation']).'</div>';
            }
        }
        return $res;
    }
}

/**
 * @subpackage nasa/output
 */
class Hm_Output_nasa_connect_section extends Hm_Output_Module {
    protected function output() {
        $res = '<div class="nasa_connect"><div data-target=".nasa_connect_section" class="server_section border-bottom cursor-pointer px-1 py-3 pe-auto">
                    <a href="#" class="pe-auto">
                        <i class="bi bi-key-fill me-3"></i>
                        <b>'.$this->trans('NASA APIs').'</b>
                    </a>
                </div>';

        $res .= '<div class="nasa_connect_section"><div class="nasa_connect_inner_1" ';

        if ($this->get('nasa_api_key')) {
            $res .= 'style="display: none;"';
        }
        $res .= '><div>Connect to NASA APIs</div>';
        $res .= '<div class="col-lg-4 col-sm-12"><input type="text" size="50" class="nasa_api_key form-control warn_on_paste" placeholder="'.$this->trans('Enter your API key').'" /><br/>';
        $res .= '<input type="button" class="nasa_api_connect btn btn-secondary" value="'.$this->trans('Connect').'" /></div></div>';
        $res .= '<div class="nasa_connect_inner_2" ';
        if (!$this->get('nasa_api_key')) {
            $res .= 'style="display: none;"';
        }
        $res .= '><div>'.$this->trans('Already connected').'</div>';
        $res .= '<div><input type="button" class="nasa_api_disconnect btn btn-danger" value="'.$this->trans('Disconnect').'" /></div>';
        $res .= '</div></div></div>';
        return $res;
    }
}

/**
 * @subpackage nasa/output
 */
class Hm_Output_nasa_folders extends Hm_Output_Module {
    protected function output() {
        if ($this->get('nasa_api_key')) {
            $res = '<li class="menu_nasa_apod"><a class="unread_link" href="?page=nasa_apod">';
            if (!$this->get('hide_folder_icons')) {
                $res .= '<i class="bi bi-globe-europe-africa account_icon"></i> ';
            }
            $res .= $this->trans('APOD').'</a></li>';
            $this->append('folder_sources', array('nASA_folders', $res));
        }
    }
}

/**
 * @subpackage nasa/functions
 */
if (!hm_exists('apod_date_form')) {
function apod_date_form($date, $output_mod) {
    $next = '';
    // There are no APOD's for June 17, 18 and 19 in 1995 - the only dates
    // since the first picture on June 16 1995, so these are excluded for
    // prev and next days.
    if (strtotime(dateInESTTZ()) > strtotime($date)) {
        if (date('Y-m-d', strtotime($date)) == "1995-06-16")
            $nextday = strtotime("1995-06-20");
        else
            $nextday = strtotime('+1 days', strtotime($date));
        $next = sprintf('?page=nasa_apod&amp;apod_date=%s', date('Y-m-d', $nextday));
    }
    if (strtotime($date) > strtotime("1995-06-16")) {
        if (date("Y-m-d", strtotime($date)) == "1995-06-20")
            $prevday = strtotime("1995-06-16");
        else
            $prevday = strtotime('-1 days', strtotime($date));
        $prev = sprintf('?page=nasa_apod&amp;apod_date=%s', date('Y-m-d', $prevday));
    }
    $res = '<form class="apod_date" method="get">';
    // Previous can be empty if the first picture of June 16 1995 has been reached
    if ($prev) {
        $res .= '<a href="'.$prev.'">'.$output_mod->trans('Previous').'</a>';
    }
    $res .= '<input name="apod_date" class="apod_date_fld" type="date" value="'.$date.'" min="1995-06-16" max="'.dateInESTTZ().'"/>';
    $res .= '<input type="hidden" name="page" value="nasa_apod" />';
    $res .= '<input type="submit" value="'.$output_mod->trans('Update').'" />';
    // Next can be empty if the day hasn't ticked over at NASA HQ yet
    if ($next) {
        $res .= '<a href="'.$next.'">'.$output_mod->trans('Next').'</a>';
    }
    $res .= '</form>';
    return $res;
}}

function dateInESTTZ() {
    // Create a "today" at NASA's HQ (in EST/EDT timezone)
    $tz = "America/New_York";
    $timestamp = time();
    $dt = new DateTime("now", new DateTimeZone($tz));
    $dt->setTimeStamp($timestamp);
    $date = $dt->format('Y-m-d');
    return $date;
}
