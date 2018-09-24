<?php

/**
 * Developer modules
 * @package modules
 * @subpackage developer
 */

if (!defined('DEBUG_MODE')) { die(); }

/**
 * Build server information data
 * @subpackage developer/handler
 */
class Hm_Handler_process_server_info extends Hm_Handler_Module {
    /***
     * Collect environment info
     */
    public function process() {
        $res = $this->request->server;
        $res['phpversion'] = phpversion();
        $res['zend_version'] = zend_version();
        $res['sapi'] = php_sapi_name();
        $res['handlers'] = Hm_Handler_Modules::dump();
        $res['output'] = Hm_Output_Modules::dump();
        $this->out('server_info', $res);
    }
}

/**
 * Output links to developer resources
 * @subpackage developer/output
 */
class Hm_Output_dev_content extends Hm_Output_Module {
    /**
     * Dev resources
     */
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
 * Start the info section on the dev page
 * @subpackage developer/output
 */
class Hm_Output_info_heading extends Hm_Output_Module {
    /**
     * Leaves an open div
     */
    protected function output() {
        return '<div class="info_content"><div class="content_title">'.$this->trans('Info').'</div>';
    }
}

/**
 * Adds a link to the dev resources page to the folder list
 * @subpackage developer/output
 */
class Hm_Output_developer_doc_link extends Hm_Output_Module {
    /**
     * Link to the dev page
     */
    protected function output() {
        $res = '<li class="menu_dev"><a class="unread_link" href="?page=dev">';
        if (!$this->get('hide_folder_icons')) {
            $res .= '<img class="account_icon" src="'.$this->html_safe(Hm_Image_Sources::$bug).'" alt="" width="16" height="16" /> ';
        }
        $res .= $this->trans('Dev').'</a></li>';
        if ($this->format == 'HTML5') {
            return $res;
        }
        $this->concat('formatted_folder_list', $res);
    }
}


/**
 * Adds a link to the info page to the folder list
 * @subpackage developer/output
 */
class Hm_Output_info_page_link extends Hm_Output_Module {
    /**
     * Info page link
     */
    protected function output() {
        $res = '<li class="menu_info"><a class="unread_link" href="?page=info">';
        if (!$this->get('hide_folder_icons')) {
            $res .= '<img class="account_icon" src="'.$this->html_safe(Hm_Image_Sources::$info).'" alt="" width="16" height="16" /> ';
        }
        $res .= $this->trans('Info').'</a></li>';
        if ($this->format == 'HTML5') {
            return $res;
        }
        $this->concat('formatted_folder_list', $res);
    }
}

/**
 * Outputs server information
 * @subpackage developer/output
 */
class Hm_Output_server_information extends Hm_Output_Module {
    /**
     * Information about the running instance
     */
    protected function output() {
        $server_info = $this->get('server_info', array());
        if (!empty($server_info)) {
            return '<div class="server_info"><table class="info">'.
                '<tr><th>Server Name</th><td>'.$server_info['HTTP_HOST'].'</td></tr>'.
                '<tr><th>Server Scheme</th><td>'.$server_info['REQUEST_SCHEME'].'</td></tr>'.
                '<tr><th>Server Address</th><td>'.$server_info['SERVER_ADDR'].'</td></tr>'.
                '<tr><th>Client Address</th><td>'.$server_info['REMOTE_ADDR'].'</td></tr>'.
                '<tr><th>PHP version</th><td>'.$server_info['phpversion'].'</td></tr>'.
                '<tr><th>Zend version</th><td>'.$server_info['zend_version'].'</td></tr>'.
                '<tr><th>SAPI</th><td>'.$server_info['sapi'].'</td></tr>'.
                '<tr><th>Enabled Modules</th><td>'.str_replace(',', ', ', implode(',', $this->get('router_module_list'))).'</td></tr>'.
                '</table></div>';
        }
        return '';
    }
}

/**
 * Output the current configuration setup
 * @subpackage developer/output
 */
class Hm_Output_config_map extends Hm_Output_Module {
    /**
     * Show pages, module assignments, and input filters
     */
    protected function output() {
    $res = '<div class="content_title">'.$this->trans('Configuration Map').'</div><table class="config_map">';
    $handlers = array();
    $outputs = array();
    $ajax = array();
    $normal = array();
    $server_info = $this->get('server_info', array());
    if (!empty($server_info)) {
        $handlers = $server_info['handlers'];
        ksort($handlers);
        $outputs = $server_info['output'];
    }
    $res .= '<tr><td colspan="3"><div class="settings_subtitle">Pages</div></td></tr>';
    foreach ($handlers as $page => $mods) {
        if (substr($page, 0, 4) == 'ajax') {
            continue;
        }
        $res .= '<tr><td colspan="3" class="config_map_page" data-target="c'.$page.'">'.$page.'</td></tr>';
        $res .= '<tr><th class="c'.$page.'" >Handler Modules</th><th class="c'.$page.'" >'.$this->trans('Source').'</th><th class="c'.$page.'" >Docs/Code</th></tr>';
        foreach ($mods as $name => $vals) {
            $res .= '<tr><td class="hmod c'.$page.'">'.$name.'</td><td class="hmod_val c'.$page.'">'.$vals[0].'</td>';
            $res .= '<td class="hmod c'.$page.'"><a href="https://cypht.org/docs/code_docs/class-Hm_Handler_'.$name.'.html"><img src="'.Hm_Image_Sources::$code.'" /></a></td></tr>';
        }
        if (array_key_exists($page, $outputs)) {
            $res .= '<tr><th class="c'.$page.'" >Output Modules</th><th class="c'.$page.'" >'.$this->trans('Source').'</th><th class="c'.$page.'" >Docs/Code</th></tr>';
            foreach($outputs[$page] as $name => $vals) {
                $res .= '<tr><td class="omod c'.$page.'">'.$name.'</td><td class="omod_val c'.$page.'">'.$vals[0].'</td>';
                $res .= '<td class="omod c'.$page.'"><a href="https://cypht.org/docs/code_docs/class-Hm_Output_'.$name.'.html"><img src="'.Hm_Image_Sources::$code.'" /></a></td></tr>';
            }
        }
    }
    $res .= '<tr><td colspan="3"><div class="settings_subtitle">AJAX Requests</div></td></tr>';
    foreach ($handlers as $page => $mods) {
        if (substr($page, 0, 4) != 'ajax') {
            continue;
        }
        $res .= '<tr><td colspan="3" class="config_map_page" data-target="c'.$page.'">'.$page.'</td></tr>';
        $res .= '<tr><th class="c'.$page.'" >Handler Modules</th><th class="c'.$page.'" >'.$this->trans('Source').'</th><th class="c'.$page.'" >Docs/Code</th></tr>';
        foreach ($mods as $name => $vals) {
            $res .= '<tr><td class="hmod c'.$page.'">'.$name.'</td><td class="hmod_val c'.$page.'">'.$vals[0].'</td>';
            $res .= '<td class="hmod c'.$page.'"><a href="https://cypht.org/docs/code_docs/class-Hm_Handler_'.$name.'.html"><img src="'.Hm_Image_Sources::$code.'" /></a></td></tr>';
        }
        if (array_key_exists($page, $outputs)) {
            $res .= '<tr><th class="c'.$page.'" >Output Modules</th><th class="c'.$page.'" >'.$this->trans('Source').'</th><th class="c'.$page.'" >Docs/Code</th></tr>';
            foreach($outputs[$page] as $name => $vals) {
                $res .= '<tr><td class="omod c'.$page.'">'.$name.'</td><td class="omod_val c'.$page.'">'.$vals[0].'</td>';
                $res .= '<td class="omod c'.$page.'"><a href="https://cypht.org/docs/code_docs/class-Hm_Output_'.$name.'.html"><img src="'.Hm_Image_Sources::$code.'" /></a></td></tr>';
            }
        }
    }
    $res .= '</table>';
    return $res;
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
        $res = '<div class="content_title">Status</div><table><thead><tr><th>'.$this->trans('Type').'</th><th>'.$this->trans('Name').'</th><th>'.
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


