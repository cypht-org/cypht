<?php

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
                $res .= ' Remember: <input type="checkbox" '. (isset($vals['user']) ? 'checked="checked" ' : '') . 'value="1" name="imap_remember" /><br /><br />'.
                    ' <input type="submit" value="Test Connection" id="imap_connect'.$index.'" />'.
                    ' <input type="submit" value="Delete" id="imap_delete'.$index.'" />'.
                    ' <input type="hidden" value="ajax_imap_debug" name="hm_ajax_hook" /></form><script type="text/javascript">'.
                    '$("#imap_delete'.$index.'").on("click", function() {'.
                        '$(".imap_debug_data").empty(); '.
                        'event.preventDefault(); Hm_Ajax.request( $( this ).parent().serializeArray(), function(res) {'.
                        'Hm_Notices.show(res.router_user_msgs); if (res.deleted_server_id > -1 ) {$("#imap_server'.$index.'").remove();}},'.
                        '{"imap_delete": 1});});'.
                    '$("#imap_connect'.$index.'").on("click", function() {'.
                        '$(this).attr("disabled", true); $(".imap_debug_data").empty(); '.
                        'event.preventDefault(); form = $(this).parent(); Hm_Ajax.request( $(this).parent().serializeArray(), function(res) {'.
                        'Hm_Notices.show(res.router_user_msgs); '.
                        'if (res.just_saved_credentials) { $("#pass_row'.$index.'").remove(); $("#user_row'.$index.'").remove(); } '.
                        'if (res.just_forgot_credentials) { $(\''.$pass_row.'\').insertAfter("#imap_server_id'.$index.'"); '.
                        '$(\''.$user_row.'\').insertAfter("#imap_server_id'.$index.'"); } '.
                        '$("#imap_connect'.$index.'").attr("disabled", false); Hm_Folders.show(res.imap_folders); $(".imap_debug_data").html(res.imap_debug); },'.
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
        if ($format == 'HTML5') {
            $res = '<div class="imap_debug"><div class="subtitle">IMAP Debug</div><pre class="imap_debug_data">';
            if (isset($input['imap_debug'])) {
                $res .= $this->html_safe($input['imap_debug']);
            }
            $res .= '</pre></div>';
            return $res;
        }
        elseif ($format == 'JSON') {
            if (isset($input['imap_debug'])) {
                $input['imap_debug'] = $this->html_safe($input['imap_debug']);
            }
            return $input;
        }
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
                '<input type="submit" value="Add" onclick="$( this ).css(\'visibility\', \'hidden\'); return true;" name="submit_server" /></form>';
        }
    }
}}

if (!class_exists('Hm_Output_imap_folders')) {
class Hm_Output_imap_folders extends Hm_Output_Module {
    protected function output($input, $format, $lang_str=false) {
        if ($format == 'HTML5') {
            $res = '<div class="imap_folders"><div class="subtitle">Top Level Folders</div><div class="imap_folder_data">';
            if (isset($input['imap_folders'])) {
                foreach (array_keys($input['imap_folders']) as $folder) {
                    $res .= '<div class="folder">'.$this->html_safe($folder).'</div>';
                }
            }
            $res .= '</div></div>';
            return $res;
        }
        elseif ($format == 'JSON') {
            if (isset($input['imap_folders'])) {
                $input['imap_folders'] = array_map(function($v) { return $this->html_safe($v); }, array_keys($input['imap_folders']));
            }
            return $input;
        }
    }
}}

?>
