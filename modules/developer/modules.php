<?php

/**
 * Developer modules
 * @package modules
 * @subpackage developer
 */

if (!defined('DEBUG_MODE')) { die(); }

/**
 * @subpackage developer/output
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
 * @subpackage developer/output
 */
class Hm_Output_info_heading extends Hm_Output_Module {
    protected function output() {
        return '<div class="info_content"><div class="content_title">'.$this->trans('Info').'</div>';
    }
}

/**
 * @subpackage developer/output
 */
class Hm_Output_developer_doc_link extends Hm_Output_Module {
    protected function output() {
        $res = '<li class="menu_dev"><a class="unread_link" href="?page=dev">'.
            '<img class="account_icon" src="'.$this->html_safe(Hm_Image_Sources::$code).'" alt="" width="16" height="16" /> '.$this->trans('Dev').'</a></li>';
        if ($this->format == 'HTML5') {
            return $res;
        }
        $this->concat('formatted_folder_list', $res);
    }
}


/**
 * @subpackage developer/output
 */
class Hm_Output_info_page_link extends Hm_Output_Module {
    protected function output() {
        $res = '<li class="menu_info"><a class="unread_link" href="?page=info">'.
            '<img class="account_icon" src="'.$this->html_safe(Hm_Image_Sources::$info).'" alt="" width="16" height="16" /> '.$this->trans('Info').'</a></li>';
        if ($this->format == 'HTML5') {
            return $res;
        }
        $this->concat('formatted_folder_list', $res);
    }
}

/**
 * Starts a status table used on the info page
 * @subpackage developer/output
 */
class Hm_Output_server_status_start extends Hm_Output_Module {
    /**
     * Modules populate this table to run a status check from the info page
     */
    protected function output() {
        $res = '<table><thead><tr><th>'.$this->trans('Type').'</th><th>'.$this->trans('Name').'</th><th>'.
                $this->trans('Status').'</th></tr></thead><tbody>';
        return $res;
    }
}

/**
 * Close the status table used on the info page
 * @subpackage developer/output
 */
class Hm_Output_server_status_end extends Hm_Output_Module {
    /**
     * Close the table opened in Hm_Output_server_status_start
     */
    protected function output() {
        return '</tbody></table></div>';
    }
}

?>
