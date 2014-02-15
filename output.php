<?php

/* base class for output formatting */
abstract class HM_Format {

    protected $modules = false;

    abstract protected function header($input);
    abstract protected function content($input);
    abstract protected function footer($input);

    public function format_content($input) {
        $this->modules = Hm_Output_Modules::get_for_page($input['router_page_name']);
        $formatted = $this->header($input);
        $formatted .= $this->content($input);
        $formatted .= $this->footer($input);
        return $formatted;
    }

    protected function run_modules($input, $format) {
        $mod_output = array();
        foreach ($this->modules as $name => $args) {
            $name = 'Hm_Output_Module_'.ucfirst($name);
            if (class_exists($name)) {
                if (!$args['logged_in'] || ($args['logged_in'] && $input['router_login_state'])) {
                    $mod = new $name();
                    $mod_output[] = $mod->output($input, $format);
                }
            }
            else {
                Hm_Msgs::add(sprintf('Output module %s activated but not found', $name));
            }
        }
        return $mod_output;
    }
}

/* JSON output format */
class Hm_Format_JSON extends HM_Format {

    public function header($input) {
        return '';
    }
    public function footer($input) {
        return '';
    }
    public function content($input) {
        return json_encode($input, JSON_FORCE_OBJECT);
    }
}

/* HTML5 output format */
class Hm_Format_HTML5 extends HM_Format {

    public function header($input) {
        return '<!DOCTYPE html><html lang=en-us><head>'.
            '</head><body>';
    }
    public function footer($input) {
        return '</body></html>';
    }

    public function js($input) {
        return '<script type="text/javascript" src="jquery-1.11.0.min.js"></script>';
    }

    public function css($input) {
        return '<style type="text/css">'.
            '.add_server, .login_form { border: solid 1px #ccc; padding: 10px; width: 200px; }'.
            '.subtitle { padding-bottom: 5px; font-weight: bold; font-size: 110%; }'.
            '.date { float: right; }'.
            '.imap_connect { display: inline; }'.
            '.add_server { float: left; clear: left; margin-bottom: 10px; }'.
            '.sys_messages { float: left; clear: left; }'.
            '.logout_form { float: right; clear: none; padding-left: 10px; margin-top: -5px; }'.
            '.configured_servers { float: left; clear: left; margin-bottom: 10px; }'.
            '.logged_in { float: right; padding-right: 10px; }'.
            '.title { font-weight: bold; float: left; padding: 0px; font-size: 125%; margin: 0px; padding-bottom: 10px; }'.
            '</style>';
    }

    public function content($input) {
        $output = $this->run_modules($input, 'HTML5');
        $output[] = $this->js($input);
        $output[] = $this->css($input);
        return implode('', $output);
    }
}

/* CLI compatible output format */
class Hm_Format_Terminal extends HM_Format {

    public function header($input) {
        return "\n";
    }
    public function footer($input) {
        return "\n";
    }
    public function content($input) {
        if (is_string($input)) {
            return wordwrap($input, 80, "\n", true);
        }
        else {
            return sprintf("Title: %s\n", $input['title']);
        }
    }
}

/* base output class */
abstract class Hm_Output {

    abstract protected function output_headers($headers);
    abstract protected function output_content($content);

    public function send_response($response, $headers=array()) {
        $this->output_headers($headers);
        $this->output_content($response);
    }

}

/* HTTP output class */
class Hm_Output_HTTP extends Hm_Output {

    protected function output_headers($headers) {
        foreach ($headers as $header) {
            header($header);
        }
    }

    protected function output_content($content) {
        //ob_end_clean();
        echo $content;
    }
}

/* STDOUT output class */
class Hm_Output_STDOUT extends Hm_Output {

    protected function output_headers($headers) {
        return;
    }

    protected function output_content($content) {
        $stdout = fopen('php://stdout', 'w');
        fwrite($stdout, $content);
        fclose($stdout);
    }
}

/* file output class */
class Hm_Output_File extends Hm_Output {

    public $filename = 'test.out';

    protected function output_headers($headers) {
        return;
    }

    protected function output_content($content) {
        $fh = fopen($this->filename, 'a');
        fwrite($fh, $content);
        fclose($fh);
    }
}

?>
