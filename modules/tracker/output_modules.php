<?php

class Hm_Output_tracker extends Hm_Output_Module {
    protected function output($input, $format) {
        if ($format == 'HTML5') {
            $res = '<div class="tracker_output"><div class="subtitle">Registered Modules</div><table class="module_list">';
            if (isset($input['module_debug'])) {
                foreach ($input['module_debug'] as $vals) {
                    $res .= $this->format_row($vals);
                }
            }
            $res .= '</table></div>';
            $res .= '<script type="text/javascript">$(document).ajaxSuccess(function(event, xhr, settings) {'.
                'var debug_data = jQuery.parseJSON(xhr.responseText); $(".module_list").html(debug_data.module_debug);'.
                '});</script>';

            return $res;
        }
        elseif ($format == 'JSON' && isset($input['module_debug'])) {
            $res = '';
            foreach ($input['module_debug'] as $vals) {
                $res .= $this->format_row($vals);
            }
            $input['module_debug'] = $res;
            return $input;
        }
    }

    private function format_row($vals) {
        return sprintf("<tr><td>%s</td><td>%s</td><td class='%s'>%s</td></tr>", $vals['type'], $vals['mod'], $vals['active'], $vals['active']);
    }
}

?>
