<?php

class Hm_Output_imap_setup_display extends Hm_Output_Module {
    protected function output($input, $format) {
        $res = '';
        if ($format == 'HTML5') {
            $res = '<div class="configured_servers"><div class="subtitle">Configured Servers</div>';
            foreach ($input['imap_servers'] as $index => $vals) {

                if (isset($vals['user'])) {
                    $disabled = 'disabled="true"';
                    $display = 'none';
                }
                else {
                    $disabled = '';
                    $display = 'inline';
                }
                $res .= '<div class="configured_server">';
                $res .= sprintf("Server: %s<br />Port: %d<br />TLS: %s<br /><br />", $this->html_safe($vals['server']),
                    $this->html_safe($vals['port']), $vals['tls'] ? 'true' : 'false' );
                $res .= 
                    ' <form class="imap_connect" method="POST">'.
                    '<input type="hidden" id="imap_server_id" name="imap_server_id" value="'.$this->html_safe($index).'" />'.
                    '<span style="display: '.$display.'"> '.$this->trans('Username').': '.
                    '<input '.$disabled.' class="credentials" type="text" name="imap_user" value=""></span>'.
                    '<span style="display: '.$display.'"> '.$this->trans('Password').': '.
                    '<input '.$disabled.' class="credentials" type="password" name="imap_pass"></span>'.
                    ' Remember: <input type="checkbox" '. (isset($vals['user']) ? 'checked="checked" ' : '').
                    ' value="1" name="imap_remember" /><br /><br />'.
                    ' <input type="submit" value="Test Connection" class="test_connect" />'.
                    ' <input type="submit" value="Delete" class="imap_delete" />'.
                    ' <input type="hidden" value="ajax_imap_debug" name="hm_ajax_hook" />';
                    '</form></div>';
            }
            $res .= '</div>';
        }
        return $res;
    }
}

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
}

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
}

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
}

?>
