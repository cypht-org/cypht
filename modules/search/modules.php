<?php

if (!defined('DEBUG_MODE')) { die(); }

class Hm_Handler_process_search_terms extends Hm_Handler_Module {
    public function process() {
        if (array_key_exists('search_terms', $this->request->get)) {
            $this->session->set('search_terms', validate_search_terms($this->request->get['search_terms']));
        }
        if (array_key_exists('search_since', $this->request->get)) {
            $this->session->set('search_since', process_since_argument($this->request->get['search_since'], true));
        }
        if (array_key_exists('search_fld', $this->request->get)) {
            $this->session->set('search_fld', validate_search_fld($this->request->get['search_fld']));
        }
        $this->out('search_since', $this->session->get('search_since', DEFAULT_SINCE));
        $this->out('search_terms', $this->session->get('search_terms', ''));
        $this->out('search_fld', $this->session->get('search_fld', 'TEXT'));
    }
}

class Hm_Output_search_from_folder_list extends Hm_Output_Module {
    protected function output($input, $format) {
        $res = '<li class="menu_search"><form method="get"><a class="unread_link" href="?page=search">'.
            '<img class="account_icon" src="'.$this->html_safe(Hm_Image_Sources::$search).'" alt="" width="16" height="16" /></a><input type="hidden" name="page" value="search" />'.
            '<input type="search" class="search_terms" name="search_terms" placeholder="'.$this->trans('Search').'" /></form></li>';
        if ($format == 'HTML5') {
            return $res;
        }
        $this->concat('formatted_folder_list', $res);

    }
}

class Hm_Output_search_content extends Hm_Output_Module {
    protected function output($input, $format) {
        $res = '<div class="search_content"><div class="content_title">Search'.
            search_form($this->module_output(), $this).'</div>';
        $res .= '<table class="message_table">';
        if (!$this->get('no_message_list_headers')) {
            $res .= '<colgroup><col class="chkbox_col"><col class="source_col">'.
            '<col class="from_col"><col class="subject_col"><col class="date_col">'.
            '<col class="icon_col"></colgroup><!--<thead><tr><th colspan="2" class="source">'.
            'Source</th><th class="from">From</th><th class="subject">Subject</th>'.
            '<th class="msg_date">Date</th><th></th></tr></thead>-->';
        }
        $res .= '<tbody></tbody></table>';
        return $res;
    }
}

class Hm_Output_js_search_data extends Hm_Output_Module {
    protected function output($input, $format) {
        return '<script type="text/javascript">'.
            'var hm_search_terms = "'.$this->get('search_terms', '').'";'.
            'var hm_search_fld = "'.$this->get('search_fld', '').'";'.
            'var hm_search_since = "'.$this->get('search_since', '').'";'.
            '</script>';
    }
}

function validate_search_terms($terms) {
    $terms = trim(strip_tags($terms));
    if (!$terms) {
        $terms = false;
    }
    return $terms;
}

function validate_search_fld($fld) {
    if (in_array($fld, array('TEXT', 'BODY', 'FROM', 'SUBJECT'))) {
        return $fld;
    }
    return false;
}

function search_field_selection($current) {
    $flds = array(
        'TEXT' => 'Entire message',
        'BODY' => 'Message body',
        'SUBJECT' => 'Subject',
        'FROM' => 'From',
    );
    $res = '<select name="search_fld">';
    foreach ($flds as $val => $name) {
        $res .= '<option ';
        if ($current == $val) {
            $res .= 'selected="selected" ';
        }
        $res .= 'value="'.$val.'">'.$name.'</option>';
    }
    $res .= '</select>';
    return $res;
}

function search_form($data, $output_mod) {
    $terms = '';
    if (array_key_exists('search_terms', $data)) {
        $terms = $data['search_terms'];
    }
    $res = '<div class="search_form">'.
        '<form method="get"><input type="hidden" name="page" value="search" />'.
        ' <input type="text" class="search_terms" name="search_terms" value="'.$output_mod->html_safe($terms).'" />'.
        ' '.search_field_selection($data['search_fld']).
        ' '.message_since_dropdown($data['search_since'], 'search_since', $output_mod).
        ' <input type="submit" class="search_update" value="Update" /></form></div>';
    return $res;
}



?>
