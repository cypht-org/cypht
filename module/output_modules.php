<?php

abstract class Hm_Output_Module {

    use Hm_Sanitize;

    protected $lstr = array();
    protected $lang = false;

    abstract protected function output($input, $format);

    protected function trans($string) {
        if (isset($this->lstr[$string])) {
            if ($this->lstr[$string] === false) {
                return $string;
            }
            else {
                return $this->lstr[$string];
            }
        }
        else {
            Hm_Debug::add(sprintf('No translation found: %s', $string));
        }
        return $string;
    }

    public function output_content($input, $format, $lang_str) {
        $this->lstr = $lang_str;
        if (isset($lang_str['interface_lang'])) {
            $this->lang = $lang_str['interface_lang'];
        }
        return $this->output($input, $format);
    }
}

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

if (!class_exists('Hm_Output_imap_setup_display')) {
class Hm_Output_imap_setup_display extends Hm_Output_Module {
    protected function output($input, $format) {
        $res = '';
        if ($format == 'HTML5') {
            $res = '<div class="configured_servers"><div class="subtitle">Configured Servers</div>';
            foreach ($input['imap_servers'] as $index => $vals) {

                $user_row = '<span id="user_row'.$index.'"> '.$this->trans('Username').': <input type="text" name="imap_user" value=""></span>';
                $pass_row = '<span id="pass_row'.$index.'"> '.$this->trans('Password').': <input type="password" name="imap_pass"></span>';

                $res .= '<div class="configured_server" id="imap_server'.$index.'">';
                $res .= sprintf("Server: %s<br />Port: %d<br />TLS: %s<br /><br />", $this->html_safe($vals['server']),
                    $this->html_safe($vals['port']), $vals['tls'] ? 'true' : 'false' );
                $res .= ' <form class="imap_connect" method="POST">'.
                    '<input type="hidden" id="imap_server_id'.$index.'" name="imap_server_id" value="'.$this->html_safe($index).'" />';
                if (!isset($vals['user'])) {
                    $res .= $user_row.$pass_row;
                }
                $res .= ' Remember: <input type="checkbox" '. (isset($vals['user']) ? 'checked="checked" ' : '') . 'value="1" name="imap_remember" /><br />'.
                    ' <input type="submit" value="Connect" id="imap_connect'.$index.'" />'.
                    ' <input type="submit" value="Delete" id="imap_delete'.$index.'" />'.
                    ' <input type="hidden" value="ajax_imap_debug" name="hm_ajax_hook" /></form><script type="text/javascript">'.
                    '$("#imap_delete'.$index.'").on("click", function() {'.
                        '$(".imap_debug").empty(); '.
                        'event.preventDefault(); Hm_Ajax.request( $( this ).parent().serializeArray(), function(res) {'.
                        'Hm_Notices.show(res.router_user_msgs); if (res.deleted_server_id > -1 ) {$("#imap_server'.$index.'").remove();}},'.
                        '{"imap_delete": 1});});'.
                    '$("#imap_connect'.$index.'").on("click", function() {'.
                        '$(this).attr("disabled", true); $(".imap_debug").empty(); '.
                        'event.preventDefault(); form = $(this).parent(); Hm_Ajax.request( $(this).parent().serializeArray(), function(res) {'.
                        'Hm_Notices.show(res.router_user_msgs); '.
                        'if (res.just_saved_credentials) { $("#pass_row'.$index.'").remove(); $("#user_row'.$index.'").remove(); } '.
                        'if (res.just_forgot_credentials) { $(\''.$pass_row.'\').insertAfter("#imap_server_id'.$index.'"); '.
                        '$(\''.$user_row.'\').insertAfter("#imap_server_id'.$index.'"); } '.
                        '$("#imap_connect'.$index.'").attr("disabled", false); $(".imap_debug").html(res.imap_debug); },'.
                        '{"imap_connect": 1});});'.
                    '</script></div>';
            }
            $res .= '</div>';
        }
        return $res;
    }
}}

if (!class_exists('Hm_Output_imap_debug')) {
class Hm_Output_imap_debug extends Hm_Output_Module {
    protected function output($input, $format) {
        $res = '';
        if ($format == 'HTML5') {
            $res = '<div class="imap_debug"><div class="subtitle">IMAP Debug</div><pre>';
            if (isset($input['imap_debug'])) {
                $res .= $this->html_safe(print_r($input['imap_debug'], true));
            }
            $res .= '</pre></div>';
        }
        return $res;
    }
}}

if (!class_exists('Hm_Output_imap_setup')) {
class Hm_Output_imap_setup extends Hm_Output_Module {
    protected function output($input, $format) {
        if ($format == 'HTML5') {
            return '<form class="add_server" method="POST">'.
                '<div class="subtitle">Add a mail server</div>'.
                'Server name or address: <input type="text" name="new_imap_server" value=""/><br />'.
                'Server Port: <input type="text" name="new_imap_port" value="143"><br />'.
                'Use TLS: <input type="checkbox" name="tls" value="1" /><br />'.
                '<input type="submit" value="Add" name="submit_server" /></form>';
        }
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
        return '';
    }
}}

if (!class_exists('Hm_Output_footer')) {
class Hm_Output_footer extends Hm_Output_Module {
    protected function output($input, $format) {
        if ($format == 'HTML5' ) {
        return '</body></html>';
        }
        return '';
    }
}}

if (!class_exists('Hm_Output_jquery')) {
class Hm_Output_jquery extends Hm_Output_Module {
    protected function output($input, $format) {
        if ($format == 'HTML5' ) {
            return '<script type="text/javascript" src="js/jquery-1.11.0.min.js"></script>';
        }
        return '';
    }
}}

if (!class_exists('Hm_Output_css')) {
class Hm_Output_css extends Hm_Output_Module {
    protected function output($input, $format) {
        if ($format == 'HTML5' ) {
            return '<link href="css/site.css" media="all" rel="stylesheet" type="text/css" />';
        }
        return '';
    }
}}

if (!class_exists('Hm_Output_js')) {
    class Hm_Output_js extends Hm_Output_Module {
        protected function output($input, $format) {
            return '<script type="text/javascript" src="js/site.js"></script>';
        }
    }
}

?>
