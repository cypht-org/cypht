<?php

abstract class Hm_Output_Module {

    protected $language = 'en_US';
    protected $languages = array(
        'en_US',
    ); 

    abstract protected function output($input, $format);

    protected function trans($string) {
        return $string;
    }

    protected function html_safe($string) {
        return htmlspecialchars($string, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    public function output_content($input, $format) {
        if (isset($input['language']) && in_array($input['language'], $this->languages)) {
            $this->language = $input['language'];
        }
        return $this->output($input, $format);
    }
}

class Hm_Output_title extends Hm_Output_Module {
    protected function output($input, $format) {
        if ($format == 'HTML5') {
            return '<h1 class="title">'.$this->html_safe($input['title']).'</h1>';
        }
    }
}

class Hm_Output_login extends Hm_Output_Module {
    protected function output($input, $format) {
        if ($format == 'HTML5') {
            if (!$input['router_login_state']) {
                return '<form class="login_form" method="POST" action="">'.
                    ' username: <input type="text" name="username" value="">'.
                    ' password: <input type="password" name="password">'.
                    ' <input type="submit" /></form>';
            }
        }
        return '';
    }
}

class Hm_Output_date extends Hm_Output_Module {
    protected function output($input, $format) {
        if ($format == 'HTML5') {
            return '<div class="date">'.$this->html_safe($input['date']).'</div>';
        }
    }
}

class Hm_Output_logout extends Hm_Output_Module {
    protected function output($input, $format) {
        if ($format == 'HTML5' && $input['router_login_state']) {
            return '<form class="logout_form" method="POST" action=""><input type="submit" name="logout" value="Logout" /></form>';
        }
    }
}

class Hm_Output_msgs extends Hm_Output_Module {
    protected function output($input, $format) {
        $res = '';
        $msgs = Hm_Msgs::get();
        if (!empty($msgs)) {
            $res .= '<div class="sys_messages"><span class="subtitle">Notices: </span>';
            if ($format == 'HTML5') {
                foreach ($msgs as $val) {
                    $res .= $this->html_safe($val).' ';
                }
            }
            $res .= '</div>';
        }
        return $res;
    }
}

class Hm_Output_imap_setup_display extends Hm_Output_Module {
    protected function output($input, $format) {
        $res = '';
        if ($format == 'HTML5') {
            $res = '<div class="configured_servers"><div class="subtitle">Configured Servers</div>';
            foreach ($input['imap_servers'] as $index => $vals) {
                $res .= '<div class="configured_server">';
                $res .= sprintf("Server: %s<br />Port: %d<br />TLS: %s<br /><br />", $this->html_safe($vals['server']),
                    $this->html_safe($vals['port']), $vals['tls'] ? 'true' : 'false' );
                $res .= ' <form class="imap_connect" method="POST" action="">'.
                    '<input type="hidden" name="imap_server_id" value="'.$this->html_safe($index).'" />'.
                    ' Username: <input type="text" name="imap_user" value="">'.
                    ' Password: <input type="password" name="imap_pass">'.
                    ' <input type="submit" value="Connect" name="imap_connect" />'.
                    ' <input type="submit" value="Delete" name="imap_delete" />'.
                    '</form></div>';
            }
            $res .= '</div>';
        }
        return $res;
    }
}

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
}

class Hm_Output_imap_setup extends Hm_Output_Module {
    protected function output($input, $format) {
        if ($format == 'HTML5') {
            return '<div><form class="add_server" method="POST" action="">'.
                '<div class="subtitle">Add a mail server</div>'.
                'Server name or address: <input type="text" name="new_imap_server" value=""/><br />'.
                'Server Port: <input type="text" name="new_imap_port" value="143"><br />'.
                'Use TLS: <input type="checkbox" name="tls" value="1" /><br />'.
                '<input type="submit" value="Add" name="submit_server" /></form></div>';
        }
        return $res;
    }
}

class Hm_Output_header extends Hm_Output_Module {
    protected function output($input, $format) {
        if ($format == 'HTML5' ) {
        return '<!DOCTYPE html><html lang=en-us><head></head><body>';
        }
        return '';
    }
}

class Hm_Output_footer extends Hm_Output_Module {
    protected function output($input, $format) {
        if ($format == 'HTML5' ) {
        return '</body></html>';
        }
        return '';
    }
}

class Hm_Output_jquery extends Hm_Output_Module {
    protected function output($input, $format) {
        if ($format == 'HTML5' ) {
            return '<script type="text/javascript" src="jquery-1.11.0.min.js"></script>';
        }
        return '';
    }
}

class Hm_Output_css extends Hm_Output_Module {
    protected function output($input, $format) {
        if ($format == 'HTML5' ) {
            return '<style type="text/css">'.
                '.configured_server, .add_server, .login_form { border: solid 1px #ccc; padding: 10px; width: 200px; }'.
                '.subtitle { padding-bottom: 5px; font-weight: bold; font-size: 110%; }'.
                '.date { float: right; }'.
                '.imap_connect { display: inline; }'.
                '.imap_debug { flaot: left; width: 600px; clear: left; }'.
                '.add_server { float: left; clear: left; margin-bottom: 10px; }'.
                '.configured_server { float: left; margin: 10px; }'.
                '.sys_messages { margin-left: 20px; margin-top: 2px; float: left }'.
                '.logout_form { float: right; clear: none; padding-left: 10px; margin-top: -5px; }'.
                '.configured_servers { float: left; clear: left; margin-bottom: 10px; }'.
                '.logged_in { padding-left: 10px; float: right; padding-right: 10px; }'.
                '.title { font-weight: bold; float: left; padding: 0px; font-size: 125%; margin: 0px; padding-bottom: 10px; }'.
                '</style>';
        }
        return '';
    }
}

?>
