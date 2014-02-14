<?php

/* base class for output formatting */
abstract class HM_Format {

    abstract protected function header($input);
    abstract protected function content($input);
    abstract protected function footer($input);

    public function format_content($input) {
        unset($input['format']);
        $formatted = $this->header($input);
        $formatted .= $this->content($input);
        $formatted .= $this->footer($input);
        return $formatted;
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
        return '<script type="text/javascript" src="jquery-1.11.0.min.js"></script>'.
            '<script type="text/javascript">'.
            '$.ajax({ url: "index.php?page=asdf"}).done(function() {alert("here")});'.
            '</script>';
    }

    public function content($input) {
        return '<h1>'.$input['title'].'</h1>'. $this->js($input);
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
