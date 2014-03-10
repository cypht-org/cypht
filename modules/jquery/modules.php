<?php

class Hm_Output_jquery extends Hm_Output_Module {
    protected function output($input, $format) {
        if ($format == 'HTML5' ) {
            return '<script type="text/javascript" src="modules/jquery/jquery-1.11.0.min.js"></script>';
        }
        return '';
    }
}

?>
