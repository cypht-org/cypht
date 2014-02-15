<?php

/* base class for output formatting */
abstract class HM_Format {

    protected $modules = false;

    abstract protected function content($input);

    public function format_content($input) {
        $this->modules = Hm_Output_Modules::get_for_page($input['router_page_name']);
        $formatted = $this->content($input);
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

    public function content($input) {
        return json_encode($input, JSON_FORCE_OBJECT);
    }
}

/* HTML5 output format */
class Hm_Format_HTML5 extends HM_Format {

    public function content($input) {
        $output = $this->run_modules($input, 'HTML5');
        return implode('', $output);
    }
}

/* CLI compatible output format */
class Hm_Format_Terminal extends HM_Format {

    public function content($input) {
        return implode('', $this->run_modules($input, 'CLI'));
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
