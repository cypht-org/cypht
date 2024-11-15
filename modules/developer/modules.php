<?php

/**
 * Developer modules
 * @package modules
 * @subpackage developer
 */

if (!defined('DEBUG_MODE')) { die(); }
define('COMMITS_URL', 'https://github.com/cypht-org/cypht/commit/');

use Webklex\ComposerInfo\ComposerInfo;

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

        $branch_name = '-';
        $commit_hash = '-';
        $commit_url = '-';
        $commit_date = '-';

        new ComposerInfo([
            'location'=>VENDOR_PATH.'composer/installed.json'
        ]);
        $package = ComposerInfo::getPackage("jason-munro/cypht");
        if ($package) {
            // Cypht is embedded
            $branch_name = str_replace(['dev-', '-dev'], '', $package['version']);
            $commit_hash = mb_substr($package['dist']['reference'], 0, 7);
            $commit_url = COMMITS_URL.$commit_hash;
            $commit_date = $package['time'];
        } elseif (exec('git --version')) {
            // Standalone cypht
            $branch_name = trim(exec('git rev-parse --abbrev-ref HEAD'));
            $commit_hash = mb_substr(trim(exec('git log --pretty="%H" -n1 HEAD')), 0, 7);
            $commit_url = COMMITS_URL.$commit_hash;
            $commit_date = trim(exec('git log -n1 --pretty=%ci HEAD'));
        }

        if ($commit_hash != '-') {
            // Get right commit date (not merge date) if not a local commit
            $ch = Hm_Functions::c_init();
            if ($ch) {
                Hm_Functions::c_setopt($ch, CURLOPT_URL, 'https://api.github.com/repos/cypht-org/cypht/commits/'.$commit_hash);
                Hm_Functions::c_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                Hm_Functions::c_setopt($ch, CURLOPT_CONNECTTIMEOUT, 1);
                Hm_Functions::c_setopt($ch, CURLOPT_USERAGENT, $this->request->server["HTTP_USER_AGENT"]);
                $curl_result = Hm_Functions::c_exec($ch);
                if (trim($curl_result)) {
                    if (!mb_strstr($curl_result, 'No commit found for SHA')) {
                        $json_commit = json_decode($curl_result);
                        $commit_date = $json_commit->commit->author->date;
                    }
                }
            }
        }

        $res['branch_name'] = $branch_name;
        $res['commit_hash'] = $commit_hash;
        $res['commit_url'] = $commit_url;

        if ($commit_date != '-') {
            $commit_date = new \DateTime($commit_date);
            $commit_date->setTimezone(new \DateTimeZone('UTC'));
            $res['commit_date'] = $commit_date->format('M d, Y');
        }

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
        return '<div class="dev_content p-0"><div class="content_title px-3">'.$this->trans('Developer Documentation').'</div>'.
            '<div class="p-3">'.
            '<p class="mb-2">There is not a lot of documentation yet, but there are a few resources available online. First is the module overview page at our website intended for developers interested in creating module sets.</p>'.
            '<a class="ms-5" href="http://cypht.org/modules.html" data-external="true">http://cypht.org/modules.html</a>'.
            '<p class="mt-4 mb-2">Code Documentation for Cypht is auto-generated using <a href="http://www.apigen.org/" data-external="true">Apigen</a> and while not yet complete, has a lot of useful information</p>'.
            '<a class="ms-5" href="http://cypht.org/docs/code_docs/index.html" data-external="true">http://cypht.org/docs/code_docs/index.html</a>'.
            '<p class="mt-4 mb-2">Finally there is a "hello world" module with lots of comments included in the project download and browsable at github</p>'.
            '<a class="ms-5" href="https://github.com/cypht-org/cypht/tree/master/modules/hello_world" data-external="true">https://github.com/cypht-org/cypht/tree/master/modules/hello_world</a>'.
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
        return '<div class="info_content px-0"><div class="content_title p-3">'.$this->trans('Info').'</div>';
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
            $res .= '<i class="bi bi-bug-fill menu-icon"></i>';
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
            $res .= '<i class="bi bi-info-circle menu-icon"></i>';
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
            return '<div class="server_info p-3"><table class="info table table-borderless">'.
                '<tr><th class="text-secondary fw-light text-nowrap">Server Name</th><td>'.$server_info['HTTP_HOST'].'</td></tr>'.
                '<tr><th class="text-secondary fw-light text-nowrap">Server Scheme</th><td>'.$server_info['REQUEST_SCHEME'].'</td></tr>'.
                '<tr><th class="text-secondary fw-light text-nowrap">Server Address</th><td>'.$server_info['SERVER_ADDR'].'</td></tr>'.
                '<tr><th class="text-secondary fw-light text-nowrap">Client Address</th><td>'.$server_info['REMOTE_ADDR'].'</td></tr>'.
                '<tr><th class="text-secondary fw-light text-nowrap">PHP version</th><td>'.$server_info['phpversion'].'</td></tr>'.
                '<tr><th class="text-secondary fw-light text-nowrap">Zend version</th><td>'.$server_info['zend_version'].'</td></tr>'.
                '<tr><th class="text-secondary fw-light text-nowrap">SAPI</th><td>'.$server_info['sapi'].'</td></tr>'.
                '<tr><th class="text-secondary fw-light text-nowrap">Enabled Modules</th><td>'.str_replace(',', ', ', implode(',', $this->get('router_module_list'))).'</td></tr>'.
                '<tr><th class="text-secondary fw-light text-nowrap">Git version</th><td>'.$server_info['branch_name'].' at revision <a href="'.$server_info['commit_url'].'">'.$server_info['commit_hash'].'</a> ('.$server_info['commit_date'].')</td></tr>'.
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
    $res = '<div class="content_title px-3">'.$this->trans('Configuration Map').'</div><table class="config_map">';
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
    $res .= '<tr><td colspan="3"><div class="settings_subtitle mt-3">Pages</div></td></tr>';
    foreach ($handlers as $page => $mods) {
        if (mb_substr($page, 0, 4) == 'ajax') {
            continue;
        }
        $res .= '<tr><td colspan="3" class="config_map_page" data-target="c'.$page.'">'.$page.'</td></tr>';
        $res .= '<tr><th class="c'.$page.'" >Handler Modules</th><th class="c'.$page.'" >'.$this->trans('Source').'</th><th class="c'.$page.'" >Docs/Code</th></tr>';
        foreach ($mods as $name => $vals) {
            $res .= '<tr><td class="hmod c'.$page.'">'.$name.'</td><td class="hmod_val c'.$page.'">'.$vals[0].'</td>';
            $res .= '<td class="hmod c'.$page.'"><a href="https://cypht.org/docs/code_docs/classes/Hm_Handler_'.$name.'.html" data-external="true"><i alt="Refresh" class="bi bi-code-slash"></i></a></td></tr>';
        }
        if (array_key_exists($page, $outputs)) {
            $res .= '<tr><th class="c'.$page.'" >Output Modules</th><th class="c'.$page.'" >'.$this->trans('Source').'</th><th class="c'.$page.'" >Docs/Code</th></tr>';
            foreach($outputs[$page] as $name => $vals) {
                $res .= '<tr><td class="omod c'.$page.'">'.$name.'</td><td class="omod_val c'.$page.'">'.$vals[0].'</td>';
                $res .= '<td class="omod c'.$page.'"><a href="https://cypht.org/docs/code_docs/classes/Hm_Output_'.$name.'.html" data-external="true"><i alt="Refresh" class="bi bi-code-slash"></i></a></td></tr>';
            }
        }
    }
    $res .= '<tr><td colspan="3"><div class="settings_subtitle mt-3">AJAX Requests</div></td></tr>';
    foreach ($handlers as $page => $mods) {
        if (mb_substr($page, 0, 4) != 'ajax') {
            continue;
        }
        $res .= '<tr><td colspan="3" class="config_map_page" data-target="c'.$page.'">'.$page.'</td></tr>';
        $res .= '<tr><th class="c'.$page.'" >Handler Modules</th><th class="c'.$page.'" >'.$this->trans('Source').'</th><th class="c'.$page.'" >Docs/Code</th></tr>';
        foreach ($mods as $name => $vals) {
            $res .= '<tr><td class="hmod c'.$page.'">'.$name.'</td><td class="hmod_val c'.$page.'">'.$vals[0].'</td>';
            $res .= '<td class="hmod c'.$page.'"><a href="https://cypht.org/docs/code_docs/classes/Hm_Handler_'.$name.'.html" data-external="true"><i class="bi bi-code-slash"></i></a></td></tr>';
        }
        if (array_key_exists($page, $outputs)) {
            $res .= '<tr><th class="c'.$page.'" >Output Modules</th><th class="c'.$page.'" >'.$this->trans('Source').'</th><th class="c'.$page.'" >Docs/Code</th></tr>';
            foreach($outputs[$page] as $name => $vals) {
                $res .= '<tr><td class="omod c'.$page.'">'.$name.'</td><td class="omod_val c'.$page.'">'.$vals[0].'</td>';
                $res .= '<td class="omod c'.$page.'"><a href="https://cypht.org/docs/code_docs/classes/Hm_Output_'.$name.'.html" data-external="true"><i class="bi bi-code-slash"></i></a></td></tr>';
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
        $res = '<div class="content_title px-3">'.$this->trans('Status').'</div><div class="p-3"><table class="table table-borderless"><thead><tr><th class="text-secondary fw-light">'.$this->trans('Type').'</th><th class="text-secondary fw-light">'.$this->trans('Name').'</th><th class="text-secondary fw-light">'.
                $this->trans('Status').'</th><th class="text-secondary fw-light">'.
                $this->trans('Sieve server capabilities').'</th></tr></thead><tbody>';
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
        return '</tbody></table></div></div>';
    }
}

/**
 * Starts a capabilities table used on the info page
 * @subpackage developer/output
 */
class Hm_Output_server_capabilities_start extends Hm_Output_Module {
    /**
     * Modules populate this table to run a status check from the info page
     */
    protected function output() {
        $res = '<div class="content_title px-3">'.$this->trans('Capabilities').'</div><div class="p-3"><table class="table table-borderless"><thead><tr><th class="text-secondary fw-light">'.$this->trans('Type').'</th><th class="text-secondary fw-light">'.$this->trans('Name').'</th><th class="text-secondary fw-light">'.
                $this->trans('Server capabilities').'</th></tr></thead><tbody>';
        return $res;
    }
}

/**
 * Close the capabilities table used on the info page
 * @subpackage developer/output
 */
class Hm_Output_server_capabilities_end extends Hm_Output_Module {
    /**
     * Close the table opened in Hm_Output_server_capabilities_start
     */
    protected function output() {
        return '</tbody></table></div></div>';
    }
}
