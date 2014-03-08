<?php

class Hm_Output_tracker extends Hm_Output_Module {
    protected function output($input, $format) {
        if ($format == 'HTML5') {
            $res = '<div class="tracker_output"><div class="subtitle">Registered Modules</div><table class="module_list">';
            if (isset($input['module_debug'])) {
                foreach ($input['module_debug'] as $vals) {
                    $res .= sprintf("<tr><td>%s</td><td>%s</td><td class='%s'>%s</td></tr>", $vals['type'], $vals['mod'], $vals['active'], $vals['active']);
                }
            }
            $res .= '</table></div>';
            $res .= '<script type="text/javascript">$(document).ajaxSuccess(function(event, xhr, settings) {
                var debug_data = jQuery.parseJSON(xhr.responseText);
                var mod_list = "";
                for (index in debug_data.module_debug) {
                    mod = debug_data.module_debug[index];
                    mod_list += "<tr><td>"+mod.type+"</td><td>"+mod.mod+"</td><td class=\'"+mod.active+"\'>"+mod.active+"</td></tr>";
                }
                $(".module_list").html(mod_list);
                });</script>';
            return $res;
        }
    }
}

?>
