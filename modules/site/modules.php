<?php

if (!defined('DEBUG_MODE')) { die(); }


class Hm_Handler_site_http_headers extends Hm_Handler_Module {
    public function process() {
        /* output custom headers here */
    }
}

class Hm_Handler_disable_servers_page extends Hm_Handler_Module {
    public function process() {
        Hm_Dispatch::page_redirect('?page=home');
    }
}

