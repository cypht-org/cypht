<?php

if (!defined('DEBUG_MODE')) { die(); }

class Hm_Output_ckeditor_includes extends Hm_Output_Module {
    protected function output($input, $format) {
        return '<script type="text/javascript" src="modules/smtp/ckeditor/ckeditor.js"></script>'.
            '<script type="text/javascript" src="modules/smtp/ckeditor/adapters/jquery.js"></script>';
    }
}

class Hm_Output_compose_form extends Hm_Output_Module {
    protected function output($input, $format) {
        return '<div class="compose_page"><div class="content_title">Compose</div>'.
            '<form class="compose_form">'.
            '<input class="compose_to" type="text" placeholder="To" />'.
            '<input class="compose_subject" type="text" placeholder="Subject" />'.
            '<textarea class="compose_text"></textarea></form></div>';
    }
}
?>
