<?php

class Hm_Handler_imap_tracker extends Hm_Handler_Module {
    public function process($data) {
        if (DEBUG_MODE) {
            $debug = array();
            $servers = Hm_IMAP_List::dump(false, true);
            foreach ($servers as $server) {
                if (is_object($server['object'])) {
                    $debug[] = $server['object']->show_debug(false, true);
                }
            }
            $data['imap_summary_debug'] = $debug;
        }
        return $data;
    }
}

class Hm_Handler_tracker extends Hm_Handler_Module {
    public function process($data) {
        if (!DEBUG_MODE) {
            $data['module_debug'] = array();
            return $data;
        }
        $debug = array();
        foreach (Hm_Handler_Modules::get_for_page($this->page) as $mod => $args) {
            $debug[] = $this->get_module('handler', $mod, $args);
        }
        foreach (Hm_Output_Modules::get_for_page($this->page) as $mod => $args) {
            $debug[] = $this->get_module('output', $mod, $args);
        }
        $data['module_debug'] = $debug;
        return $data;
    }

    private function get_module($type, $mod, $args) {
        $active = false;
        if (!$args['logged_in'] || ($args['logged_in'] && $this->session->active)) {
            $active = true;
        }
        return array('type' => $type, 'mod' => $mod, 'active' => $active ? 'enabled' : 'disabled');
    }
}
class Hm_Output_show_debug extends Hm_Output_Module {
    protected function output($input, $format) {
        if (DEBUG_MODE) {
            global $start_time;
            Hm_Debug::add(sprintf("Execution Time: %f", (microtime(true) - $start_time)));
            Hm_Debug::load_page_stats();
            if (isset($input['imap_summary_debug'])) {
                $imap_debug = $input['imap_summary_debug'];
            }
            else {
                $imap_debug = array();
            }
            if ($format == 'HTML5') {
                return '<div class="tracker_debug"><div class="subtitle">HM3 Debug</div><pre class="hm3_debug">'.Hm_Debug::show('return').'</pre></div>'.
                    '<div class="imap_summary_debug"><div class="subtitle">IMAP Debug</div><pre class="hm3_imap_debug">'.print_r($imap_debug, true).'</pre></div>';
            }
            elseif ($format == 'JSON') {
                $input['imap_summary_debug'] = print_r($input['imap_summary_debug'], true);
                $input['hm3_debug'] = Hm_Debug::show('return');

                return $input;
            }
        }
        elseif ($format == 'JSON') {
            return $input;
        }
    }
}

class Hm_Output_tracker extends Hm_Output_Module {
    protected function output($input, $format) {
        if ($format == 'HTML5') {
            if (!DEBUG_MODE) {
                return '';
            }
            $res = '<div class="tracker_output"><div class="subtitle">Registered Modules</div><table class="module_list">';
            if (isset($input['module_debug'])) {
                foreach ($input['module_debug'] as $vals) {
                    $res .= $this->format_row($vals);
                }
            }
            $res .= '</table></div>';
            return $res;
        }
        elseif ($format == 'JSON' && isset($input['module_debug'])) {
            if (!DEBUG_MODE) {
                return $input;
            }
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
