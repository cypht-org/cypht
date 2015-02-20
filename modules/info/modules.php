<?php

/**
 * Info modules
 * @package modules
 * @subpackage info
 */

if (!defined('DEBUG_MODE')) { die(); }

/**
 * @subpackage info/output
 */
class Hm_Output_dev_content extends Hm_Output_Module {
    protected function output() {
        return '<div class="dev_content"><div class="content_title">'.$this->trans('Developer Documentation').'</div>'.
            '<div class="long_text">'.
            'There is not a lot of documentation yet, but there are a few resources available online. First is the module overview page at our website intended for developers interested in creating module sets.'.
            '<br /><br />&nbsp;&nbsp;&nbsp;<a href="http://cypht.org/modules.html">http://cypht.org/modules.html</a>'.
            '<br /><br />Code Documentation for Cypht is auto-generated using <a href="http://www.apigen.org/">Apigen</a> and while '.
            'not yet complete, has a lot of useful information'.
            '<br /><br />&nbsp;&nbsp;&nbsp;<a href="http://cypht.org/docs/code_docs/index.html">http://cypht.org/docs/code_docs/index.html</a>'.
            '<br /><br />Finally there is a "hello world" module with lots of comments included in the project download and browsable at github'.
            '<br /><br />&nbsp;&nbsp;&nbsp;<a href="https://github.com/jasonmunro/hm3/tree/master/modules/hello_world">https://github.com/jasonmunro/hm3/tree/master/modules/hello_world</a>'.
            '</div></div>';
    }
}

/**
 * @subpackage info/output
 */
class Hm_Output_bug_report_form extends Hm_Output_Module {
    protected function output() {
        return '<div class="bug_report"><div class="content_title">'.$this->trans('Report a bug').'</div>'.
            '<div class="long_text">'.
            'If you found a bug or want to request a feature - we want to hear from you!'.
            '<br /><br />Please file an issue report at github with the following form'.
            '<br /><br />&nbsp;&nbsp;&nbsp;<a href="https://github.com/jasonmunro/hm3/issues/new">https://github.com/jasonmunro/hm3/issues/new</a>'.
            '<br /><br />Thanks for your help making Cypht better!'.
            '</div></div>';
    }
}

/**
 * @subpackage info/output
 */
class Hm_Output_help_content extends Hm_Output_Module {
    protected function output() {
        return '<div class="help_content"><div class="content_title">'.$this->trans('Help').'</div>'.
            '<div class="long_text"></div></div>';
    }
}

/**
 * @subpackage info/output
 */
class Hm_Output_developer_doc_link extends Hm_Output_Module {
    protected function output() {
        $res = '<li class="menu_dev"><a class="unread_link" href="?page=dev">'.
            '<img class="account_icon" src="'.$this->html_safe(Hm_Image_Sources::$code).'" alt="" width="16" height="16" /> '.$this->trans('Development').'</a></li>';
        if ($this->format == 'HTML5') {
            return $res;
        }
        $this->concat('formatted_folder_list', $res);
    }
}

/**
 * @subpackage info/output
 */
class Hm_Output_bug_report_link extends Hm_Output_Module {
    protected function output() {
        $res = '<li class="menu_bug_report"><a class="unread_link" href="?page=bug_report">'.
            '<img class="account_icon" src="'.$this->html_safe(Hm_Image_Sources::$bug).'" alt="" width="16" height="16" /> '.$this->trans('Bugs').'</a></li>';
        if ($this->format == 'HTML5') {
            return $res;
        }
        $this->concat('formatted_folder_list', $res);
    }
}

/**
 * @subpackage info/output
 */
class Hm_Output_help_page_link extends Hm_Output_Module {
    protected function output() {
        $res = '<li class="menu_help"><a class="unread_link" href="?page=help">'.
            '<img class="account_icon" src="'.$this->html_safe(Hm_Image_Sources::$info).'" alt="" width="16" height="16" /> '.$this->trans('Help').'</a></li>';
        if ($this->format == 'HTML5') {
            return $res;
        }
        $this->concat('formatted_folder_list', $res);
    }
}

?>
