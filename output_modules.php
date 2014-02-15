<?php

abstract class Hm_Output_Module {
    abstract public function output($input, $format);
}

class Hm_Output_Module_Title extends Hm_Output_Module {
    public function output($input, $format) {
        if ($format == 'HTML5') {
            return '<h1 class="title">'.$input['title'].'</h1>';
        }
    }
}

class Hm_Output_Module_Login extends Hm_Output_Module {
    public function output($input, $format) {
        if ($format == 'HTML5') {
            if (!$input['router_login_state']) {
                return '<form class="login_form" method="POST" action="">'.
                    ' username: <input type="text" name="username" value="">'.
                    ' password: <input type="password" name="password">'.
                    ' <input type="submit" /></form>';
            }
            else {
                return '<div class="logged_in">[ Logged in ]</div>';
            }
        }
    }
}

class Hm_Output_Module_Date extends Hm_Output_Module {
    public function output($input, $format) {
        if ($format == 'HTML5') {
            return '<div class="date">'.$input['date'].'</div>';
        }
    }
}

class Hm_Output_Module_Logout extends Hm_Output_Module {
    public function output($input, $format) {
        if ($format == 'HTML5' && $input['router_login_state']) {
            return '<form class="logout_form" method="POST" action=""><input type="submit" name="logout" value="Logout" /></form>';
        }
    }
}

class Hm_Output_Module_Msgs extends Hm_Output_Module {
    public function output($input, $format) {
        $res = '<div class="sys_messages"><div class="subtitle">Messages</div>';
        if ($format == 'HTML5') {
            foreach (Hm_Msgs::get() as $val) {
                $res .= '<div>'.$val.'</div>';
            }
        }
        $res .= '</div>';
        return $res;
    }
}
class Hm_Output_Module_Imap_setup_display extends Hm_Output_Module {
    public function output($input, $format) {
        $res = '';
        if ($format == 'HTML5') {
            $res = '<div class="configured_servers"><div class="subtitle">Configured Servers</div>';
            foreach ($input['imap_servers'] as $index => $vals) {
                $res .= sprintf("Server: %s Port: %d TLS: %s", $vals['server'], $vals['port'],
                    $vals['tls'] ? 'true' : 'false' );
                $res .= ' <form class="imap_connect" method="POST" action="">'.
                    '<input type="hidden" name="imap_server_id" value="'.$index.'" />'.
                    ' Username: <input type="text" name="imap_user" value="">'.
                    ' Password: <input type="password" name="imap_pass">'.
                    ' <input type="submit" value="Connect" name="connect" />'.
                    '</form><br />';
            }
            $res .= '</div>';
        }
        return $res;
    }
}
class Hm_Output_Module_Imap_debug extends Hm_Output_Module {
    public function output($input, $format) {
        $res = '';
        if ($format == 'HTML5') {
            $res = '<div class="imap_debug"><pre>';
            if (isset($input['imap_debug'])) {
                $res .= print_r($input['imap_debug'], true);
            }
            $res .= '</pre></div>';
        }
        return $res;
    }
}
class Hm_Output_Module_Imap_setup extends Hm_Output_Module {
    public function output($input, $format) {
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
class Hm_Output_Module_Header extends Hm_Output_Module {
    public function output($input, $format) {
        if ($format == 'HTML5' ) {
        return '<!DOCTYPE html><html lang=en-us><head></head><body>';
        }
        return '';
    }
}
class Hm_Output_Module_Footer extends Hm_Output_Module {
    public function output($input, $format) {
        if ($format == 'HTML5' ) {
        return '</body></html>';
        }
        return '';
    }
}
class Hm_Output_Module_Jquery extends Hm_Output_Module {
    public function output($input, $format) {
        if ($format == 'HTML5' ) {
            return '<script type="text/javascript" src="jquery-1.11.0.min.js"></script>';
        }
        return '';
    }
}
class Hm_Output_Module_Css extends Hm_Output_Module {
    public function output($input, $format) {
        if ($format == 'HTML5' ) {
            return '<style type="text/css">'.
                '.add_server, .login_form { border: solid 1px #ccc; padding: 10px; width: 200px; }'.
                '.subtitle { padding-bottom: 5px; font-weight: bold; font-size: 110%; }'.
                '.date { float: right; }'.
                '.imap_connect { display: inline; }'.
                '.imap_debug { flaot: left; width: 600px; font-size: 75%; clear: left; }'.
                '.add_server { float: left; clear: left; margin-bottom: 10px; }'.
                '.sys_messages { float: left; clear: left; }'.
                '.logout_form { float: right; clear: none; padding-left: 10px; margin-top: -5px; }'.
                '.configured_servers { float: left; clear: left; margin-bottom: 10px; }'.
                '.logged_in { float: right; padding-right: 10px; }'.
                '.title { font-weight: bold; float: left; padding: 0px; font-size: 125%; margin: 0px; padding-bottom: 10px; }'.
                '</style>';
        }
        return '';
    }
}

?>
