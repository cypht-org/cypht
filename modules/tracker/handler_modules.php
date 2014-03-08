<?php

class Hm_Handler_tracker extends Hm_Handler_Module {
    public function process($data) {
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


?>
