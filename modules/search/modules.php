<?php

if (!defined('DEBUG_MODE')) { die(); }

class Hm_Handler_process_search_terms extends Hm_Handler_Module {
    public function process() {
        if (array_key_exists('search_terms', $this->request->get)) {
            $this->out('run_search', true);
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
            '<img class="account_icon" src="'.$this->html_safe(Hm_Image_Sources::$search).
            '" alt="" width="16" height="16" /></a><input type="hidden" name="page" value="search" />'.
            '<input type="search" class="search_terms" name="search_terms" placeholder="'.
            $this->trans('Search').'" /></form></li>';
        if ($format == 'HTML5') {
            return $res;
        }
        $this->concat('formatted_folder_list', $res);
    }
}

class Hm_Output_search_content_start extends Hm_Output_Module {
    protected function output($input, $format) {
        return '<div class="search_content"><div class="content_title">'.$this->trans('Search');
    }
}

class Hm_Output_search_content_end extends Hm_Output_Module {
    protected function output($input, $format) {
        return '</div>';
    }
}

class Hm_Output_search_form extends Hm_Output_Module {
    protected function output($input, $format) {
        $source_link = '<a href="#" title="Sources" class="source_link"><img class="refresh_list" src="'.Hm_Image_Sources::$folder.'" width="20" height="20" /></a>';
        $refresh_link = '<a class="refresh_link" title="'.$this->trans('Refresh').'" href="#"><img alt="Refresh" class="refresh_list" src="'.Hm_Image_Sources::$refresh.'" width="20" height="20" /></a>';
        $terms = $this->get('search_terms', '');
        $res = '<div class="search_form">'.
            '<form method="get"><input type="hidden" name="page" value="search" />'.
            ' <input type="search" class="search_terms" name="search_terms" value="'.$this->html_safe($terms).'" />'.
            ' '.search_field_selection($this->get('search_fld', ''), $this).
            ' '.message_since_dropdown($this->get('search_since', ''), 'search_since', $this).
            ' <input type="submit" class="search_update" value="'.$this->trans('Update').'" /></form></div>'.
            list_controls($refresh_link, false, $source_link).
            '</div>';
        return $res;
    }
}

class Hm_Output_search_results_table_end extends Hm_Output_Module {
    protected function output($input, $format) {
        return '</tbody></table>';
    }
}

class Hm_Output_js_search_data extends Hm_Output_Module {
    protected function output($input, $format) {
        return '<script type="text/javascript">'.
            'var hm_search_terms = "'.$this->html_safe($this->get('search_terms', '')).'";'.
            'var hm_search_fld = "'.$this->html_safe($this->get('search_fld', '')).'";'.
            'var hm_search_since = "'.$this->html_safe($this->get('search_since', '')).'";'.
            'var hm_run_search = "'.$this->html_safe($this->get('run_search', 0)).'";'.
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

function search_field_selection($current, $output_mod) {
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
        $res .= 'value="'.$val.'">'.$output_mod->trans($name).'</option>';
    }
    $res .= '</select>';
    return $res;
}

?>
