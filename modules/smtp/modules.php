<?php

if (!defined('DEBUG_MODE')) { die(); }

class Hm_Output_compose_form extends Hm_Output_Module {
    protected function output($input, $format) {
        return '<div class="compose_page"><div class="content_title">Compose</div>'.
            '<form class="compose_form">'.
            '<input class="compose_to" type="text" placeholder="To" />'.
            '<input class="compose_subject" type="text" placeholder="Subject" />'.
            '<textarea class="compose_body"></textarea></form></div>';
    }
}
?>
