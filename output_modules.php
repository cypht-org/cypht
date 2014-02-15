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
                    $vals['tls'] ? 'false' : 'true' );
                $res .= ' <form class="imap_connect" method="POST" action="">'.
                    '<input type="submit" value="Connect" name="connect" /></form><br />';
            }
            $res .= '</div>';
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

?>
