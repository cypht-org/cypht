<?php

if (!class_exists('Hm_Output_title')) {
class Hm_Output_title extends Hm_Output_Module {
    protected function output($input, $format) {
        if ($format == 'HTML5') {
            return '<h1 class="title">'.$this->html_safe($input['title']).'</h1>';
        }
    }
}}

if (!class_exists('Hm_Output_login')) {
class Hm_Output_login extends Hm_Output_Module {
    protected function output($input, $format) {
        if ($format == 'HTML5') {
            if (!$input['router_login_state']) {
                return '<form class="login_form" method="POST">'.
                    ' '.$this->trans('Username').': <input type="text" name="username" value="">'.
                    ' '.$this->trans('Password').': <input type="password" name="password">'.
                    ' <input type="submit" /></form>';
            }
        }
        return '';
    }
}}

if (!class_exists('Hm_Output_date')) {
class Hm_Output_date extends Hm_Output_Module {
    protected function output($input, $format) {
        if ($format == 'HTML5') {
            return '<div class="date">'.$this->html_safe($input['date']).'</div>';
        }
    }
}}

if (!class_exists('Hm_Output_logout')) {
class Hm_Output_logout extends Hm_Output_Module {
    protected function output($input, $format) {
        if ($format == 'HTML5' && $input['router_login_state']) {
            return '<form class="logout_form" method="POST"><input type="submit" name="logout" value="Logout" /></form>';
        }
    }
}}

if (!class_exists('Hm_Output_msgs')) {
class Hm_Output_msgs extends Hm_Output_Module {
    protected function output($input, $format) {
        if ($format == 'HTML5') {
            $res = '';
            $msgs = Hm_Msgs::get();
            $res .= '<div class="sys_messages">';
            if (!empty($msgs)) {
                foreach ($msgs as $val) {
                    $res .= $this->html_safe($val).' ';
                }
            }
            $res .= '</div>';
            return $res;
        }
        return '';
    }
}}

if (!class_exists('Hm_Output_header')) {
class Hm_Output_header extends Hm_Output_Module {
    protected function output($input, $format) {
        if ($format == 'HTML5' ) {
            $lang = '';
            if ($this->lang) {
                $lang = 'lang='.strtolower(str_replace('_', '-', $this->lang));
            }
            return '<!DOCTYPE html><html '.$lang.'><head><title>HM3</title><meta charset="utf-8" /></head><body>';
        }
        elseif ($format == 'CLI') {
            return sprintf("\nHM3 CLI Interface\n\n");
        }
        return '';
    }
}}

if (!class_exists('Hm_Output_footer')) {
class Hm_Output_footer extends Hm_Output_Module {
    protected function output($input, $format, $js=array(), $css=array()) {
        if ($format == 'HTML5' ) {
            $res = '<script type="text/javascript">'.implode(' ', $js).'</script>';
            $res .= '<style type="text/css">'.implode(' ', $css).'</style>';
            $res .= '</body></html>';
            return $res;
        }
        return '';
    }
}}

if (!class_exists('Hm_Output_css')) {
class Hm_Output_css extends Hm_Output_Module {
    protected function output($input, $format) {
        if ($format == 'HTML5' ) {
            return '<link href="site.css" media="all" rel="stylesheet" type="text/css" />';
        }
        return '';
    }
}}

if (!class_exists('Hm_Output_js')) {
class Hm_Output_js extends Hm_Output_Module {
    protected function output($input, $format) {
        if ($format == 'HTML5') {
            return '<script type="text/javascript" src="site.js"></script>';
        }
    }
}}

?>
