<?php

/**
 * Core modules
 * @package modules
 * @subpackage core
 */

/**
 * Simple search form for the folder list
 * @subpackage core/output
 */
class Hm_Output_search_from_folder_list extends Hm_Output_Module {
    /**
     * Add a search form to the top of the folder list
     */
    protected function output() {
        $res = '<li class="menu_search mb-2"><form method="get">';
        $res .= '<div class="input-group">';
        if (!$this->get('hide_folder_icons')) {
            $res .= '<a href="?page=search" class="input-group-text" id="basic-addon1">' . 
            '<i class="bi bi-search"></i>' .
            '</a>';
        }
        $res .= '<input type="hidden" name="page" value="search" />'.
            '<input type="search" class="search_terms form-control form-control-sm" aria-describedby="basic-addon1" '.
            'name="search_terms" placeholder="'.$this->trans('Search').'" /></div></form></li>';
        if ($this->format == 'HTML5') {
            return $res;
        }
        $this->concat('formatted_folder_list', $res);
    }
}

/**
 * Start the search results content section
 * @subpackage core/output
 */
class Hm_Output_search_content_start extends Hm_Output_Module {
    /**
     * Leaves two open div tags that are closed in Hm_Output_search_content_end and Hm_Output_search_form
     */
    protected function output() {
        return '<div class="search_content px-0"><div class="content_title px-3 d-flex align-items-center">'.
            message_controls($this).$this->trans('Search');
    }
}

/**
 * End of the search results content section
 * @subpackage core/output
 */
class Hm_Output_search_content_end extends Hm_Output_Module {
    /**
     * Closes a div opened in Hm_Output_search_content_start
     */
    protected function output() {
        return '</div>';
    }
}

/**
 * Unsaved data reminder
 * @subpackage core/output
 */
class Hm_Output_save_reminder extends Hm_Output_Module {
    protected function output() {
        if ($this->get('single_server_mode')) {
            return '';
        }
        $changed = $this->get('changed_settings', array());
        if (!empty($changed)) {
            return '<div class="save_reminder"><a title="'.$this->trans('You have unsaved changes').
                '" href="?page=save"><i class="bi bi-save2-fill fs-4"></i></a></div>';
        }
        return '';
    }
}

/**
 * Start the search form
 * @subpackage core/output
 */
class Hm_Output_search_form_start extends Hm_Output_Module {
    protected function output() {
        return '<div class="search_form"><form class="d-flex align-items-center" method="get">';
    }
}

/**
 * Search form content
 * @subpackage core/output
 */
class Hm_Output_search_form_content extends Hm_Output_Module {
    protected function output() {
        $terms = $this->get('search_terms', '');

        return '<input type="hidden" name="page" value="search" />'.
            ' <label class="screen_reader" for="search_terms">'.$this->trans('Search Terms').'</label>'.
            '<input required placeholder="'.$this->trans('Search Terms').'" id="search_terms" type="search" class="search_terms form-control form-control-sm" name="search_terms" value="'.$this->html_safe($terms).'" />'.
            ' <label class="screen_reader" for="search_fld">'.$this->trans('Search Field').'</label>'.
            search_field_selection($this->get('search_fld', DEFAULT_SEARCH_FLD), $this).
            ' <label class="screen_reader" for="search_since">'.$this->trans('Search Since').'</label>'.
            message_since_dropdown($this->get('search_since', DEFAULT_SEARCH_SINCE), 'search_since', $this).
            combined_sort_dialog($this).
            ' | <input type="submit" class="search_update btn btn-primary btn-sm" value="'.$this->trans('Update').'" />'.
            ' <input type="button" class="search_reset btn btn-light border btn-sm" value="'.$this->trans('Reset').'" />';
    }
}

/**
 * Finish the search form
 * @subpackage core/output
 */
class Hm_Output_search_form_end extends Hm_Output_Module {
    protected function output() {
        $source_link = '<a href="#" title="'.$this->trans('Sources').'" class="source_link"><i class="bi bi-folder-fill refresh_list"></i></a>';
        $refresh_link = '<a class="refresh_link ms-3" title="'.$this->trans('Refresh').'" href="#"><i class="bi bi-arrow-clockwise refresh_list"></i></a>';
        return '</form></div>'.
            list_controls($refresh_link, false, $source_link).list_sources($this->get('data_sources', array()), $this).'</div>';
    }
}

/**
 * Closes the search results table
 * @subpackage core/output
 */
class Hm_Output_search_results_table_end extends Hm_Output_Module {
    /**
     */
    protected function output() {
        return '</tbody></table>';
    }
}

/**
 * Some inline JS used by the search page
 * @subpackage core/output
 */
class Hm_Output_js_search_data extends Hm_Output_Module {
    /**
     * adds two JS functions used on the search page
     */
    protected function output() {
        return '<script type="text/javascript" id="search-data">'.
            'var hm_search_terms = function() { return "'.$this->html_safe($this->get('search_terms', ''), true).'"; };'.
            'var hm_run_search = function() { return "'.$this->html_safe($this->get('run_search', 0)).'"; };'.
            '</script>';
    }
}

/**
 * Outputs the end of the login or logout form
 * @subpackage core/output
 */
class Hm_Output_login_end extends Hm_Output_Module {
    /**
     * Closes the login form
     */
    protected function output() {
        $fancy_login= $this->get('fancy_login_allowed');
        if(!$fancy_login){
            return '</form>';
        }
        return '</form></div>';
    }
}

/**
 * Outputs the start of the login or logout form
 * @subpackage core/output
 */
class Hm_Output_login_start extends Hm_Output_Module {
    /**
     * Looks at the current login state and outputs the correct form
     */
    protected function output() {
        $fancy_login = $this->get('fancy_login_allowed');
        if (!$fancy_login) {
            if (!$this->get('router_login_state')) {
                return '<form class="login_form" method="POST">';
            }
            else {
                return '<form class="logout_form" method="POST">';
            }
        } else {
            if (!$this->get('router_login_state')) {
                $css = '<style type="text/css">body,html{max-width:100vw !important; max-height:100vh !important; overflow:hidden !important;}.form-container{background-color:#f1f1f1;'.
                    'background: linear-gradient( rgba(4, 26, 0, 0.85), rgba(4, 26, 0, 0.85)), url('.WEB_ROOT.'modules/core/assets/images/cloud.jpg);'.
                    'background-attachment: fixed;background-position: center;background-repeat: no-repeat;background-size: cover;'.
                    'display:grid; place-items:center; height:100vh; width:100vw;} .logged_out{display:block !important;}.sys_messages'.
                    '{position:fixed;right:20px;top:15px;min-height:30px;display:none;background-color:#fff;color:teal;'.
                    'margin-top:0px;padding:15px;padding-bottom:5px;white-space:nowrap;border:solid 1px #999;border-radius:'.
                    '5px;filter:drop-shadow(4px 4px 4px #ccc);z-index:101;}.g-recaptcha{margin:0px 10px 10px 10px;}.mobile .g-recaptcha{'.
                    'margin:0px 10px 5px 10px;}.title{font-weight:normal;padding:0px;margin:0px;margin-left:20px;'.
                    'margin-bottom:20px;letter-spacing:-1px;color:#999;}html,body{min-width:100px !important;'.
                    'background-color:#fff;}body{background:linear-gradient(180deg,#faf6f5,#faf6f5,#faf6f5,#faf6f5,'.
                    '#fff);font-size:1em;color:#333;font-family:Arial;padding:0px;margin:0px;min-width:700px;'.
                    'font-size:100%;}input,option,select{font-size:100%;padding:3px;}textarea,select,input{border:solid '.
                    '1px #ddd;background-color:#fff;color:#333;border-radius:3px;}.screen_reader{position:absolute'.
                    ';top:auto;width:1px;height:1px;overflow:hidden;}.login_form{display:flex; justify-content:space-evenly; align-items:center; flex-direction:column;font-size:90%;'.
                    'padding-top:60px;height:360px;border-radius:20px 20px 20px 20px;margin:0px;background-color:rgba(0,0,0,.6);'.
                    'min-width:300px;}.login_form input{clear:both;float:left;padding:4px;'.
                    'margin-top:10px;margin-bottom:10px;}#username,#password{width:200px; height:25px;} .err{color:red !important;}.long_session'.
                    '{float:left;}.long_session input{padding:0px;float:none;font-size:18px;}.mobile .long_session{float:left;clear:both;} @media screen and (min-width:400px){.login_form{min-width:400px;}}'.
                    '.user-icon_signin{display:block; background-color:white; border-radius:100%; padding:10px; height:40px; margin-top:-120px; box-shadow: #6eb549 .4px 2.4px 6.2px; }'.
                    '.label_signin{width:210px; margin:0px 0px -18px 0px;color:#fff;opacity:0.7;} @media (max-height : 500px){ .user-icon_signin{display:none;}}
                    </style>';

                return $css.'<div class="form-container"><form class="login_form" method="POST">';
            }
            else {
                return '<div class="form-container"><form class="logout_form" method="POST">';
            }
        }
    }
}

/**
 * Outputs the login or logout form
 * @subpackage core/output
 */
class Hm_Output_login extends Hm_Output_Module {
    /**
     * Looks at the current login state and outputs the correct form
     */
    protected function output() {
        $stay_logged_in = '';
        if ($this->get('allow_long_session')) {
            $stay_logged_in = '<div class="form-check form-switch long-session">
                <input type="checkbox" id="stay_logged_in" value="1" name="stay_logged_in" class="form-check-input">
                <label class="form-check-label" for="stay_logged_in">'.$this->trans('Stay logged in').'</label>
            </div>';
        }
        if (!$this->get('router_login_state')) {
            $fancy_login = $this->get('fancy_login_allowed');
            if(!$fancy_login){
                return '<div class="bg-light"><div class="d-flex align-items-center justify-content-center vh-100 p-3">
                    <div class="card col-12 col-md-6 col-lg-4 p-3">
                        <div class="card-body">
                            <p class="text-center"><img class="w-50" src="'.WEB_ROOT. 'modules/core/assets/images/logo_dark.svg"></p>
                            <div class="mt-5">
                                <div class="mb-3 form-floating">
                                    <input autofocus required type="text" placeholder="'.$this->trans('Username').'" id="username" name="username" class="form-control">
                                    <label for="username" class="form-label screen-reader">'.$this->trans('Username').'</label>
                                </div>
                                <div class="mb-3 form-floating">
                                    <input required type="password" id="password" placeholder="'.$this->trans('Password').'" name="password" class="form-control">
                                    <label for="password" class="form-label screen-reader">'.$this->trans('Password').'</label>
                                </div>'.
                                '<div class="d-grid">'.$stay_logged_in.
                                    '<input type="hidden" name="hm_page_key" value="'.Hm_Request_Key::generate().'" />
                                    <input type="submit" id="login" class="btn btn-primary btn-lg" value="'.$this->trans('Login').'">
                                </div>
                            </div>
                        </div>
                    </div>
                </div></div>';
            } else {
                return '<svg class="user-icon_signin" viewBox="0 0 20 20"><path d="M12.075,10.812c1.358-0.853,2.242-2.507,2.242-4.037c0-2.181-1.795-4.618-4.198-4.618S5.921,4.594,5.921,6.775c0,1.53,0.884,3.185,2.242,4.037c-3.222,0.865-5.6,3.807-5.6,7.298c0,0.23,0.189,0.42,0.42,0.42h14.273c0.23,0,0.42-0.189,0.42-0.42C17.676,14.619,15.297,11.677,12.075,10.812 M6.761,6.775c0-2.162,1.773-3.778,3.358-3.778s3.359,1.616,3.359,3.778c0,2.162-1.774,3.778-3.359,3.778S6.761,8.937,6.761,6.775 M3.415,17.69c0.218-3.51,3.142-6.297,6.704-6.297c3.562,0,6.486,2.787,6.705,6.297H3.415z"></path></svg>'.
                '<img src="'.WEB_ROOT. 'modules/core/assets/images/logo.svg" style="height:90px;">'.
                '<!--h1 class="title">'.$this->html_safe($this->get('router_app_name', '')).'</h1-->'.
                ' <input type="hidden" name="hm_page_key" value="'.Hm_Request_Key::generate().'" />'.
                ' <label class="label_signin" for="username">'.$this->trans('Username').'</label>'.
                '<input autofocus required type="text" placeholder="'.$this->trans('Username').'" id="username" name="username" value="">'.
                ' <label class="label_signin" for="password">'.$this->trans('Password').'</label>'.
                '<input required type="password" id="password" placeholder="'.$this->trans('Password').'" name="password">'.
                $stay_logged_in.' <input style="cursor:pointer; display:block; width:210px; background-color:#6eb549; color:white; height:40px;" type="submit" id="login" value="'.$this->trans('Login').'" />';
            }

        }
        else {
            $settings = $this->get('changed_settings', array());
            $single = $this->get('single_server_mode');
            $changed = 0;
            if (!$single && count($settings) > 0) {
                $changed = 1;
            }

        return '<div class="modal fade" id="confirmLogoutModal" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" aria-labelledby="confirmLogoutModalLabel" aria-hidden="true">
                <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                    <h5 class="modal-title" id="confirmLogoutModalLabel">'.$this->trans('Do you want to log out?').'</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="hm_page_key" value="'.Hm_Request_Key::generate().'" />
                        <p class="text-wrap">'.$this->trans('Unsaved changes will be lost! Re-enter your password to save and exit.').' <a href="?page=save">'.$this->trans('More info').'</a></p>
                        <input type="text" value="'.$this->html_safe($this->get('username', 'cypht_user')).'" autocomplete="username" style="display: none;"/>
                        <div class="my-3 form-floating">
                            <input id="logout_password" autocomplete="current-password" name="password" class="form-control warn_on_paste" type="password" placeholder="'.$this->trans('Password').'">
                            <label for="logout_password" class="form-label screen-reader">'.$this->trans('Password').'</label>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <input class="cancel_logout save_settings btn btn-secondary" data-bs-dismiss="modal" type="button" value="'.$this->trans('Cancel').'" />
                        <input class="save_settings btn btn-primary" id="logout_without_saving" type="submit" name="logout" value="'.$this->trans('Just Logout').'" />
                        <input class="save_settings btn btn-primary" type="submit" name="save_and_logout" value="'.$this->trans('Save and Logout').'" />
                    </div>
                </div>
                </div>
            </div>';
        }
    }
}

/**
 * Start the content section for the servers page
 * @subpackage core/output
 */
class Hm_Output_server_content_start extends Hm_Output_Module {
    /**
     * The server_content div is closed in Hm_Output_server_content_end
     */
    protected function output() {
        return '<div class="content_title">'.$this->trans('Servers').
            '</div><div class="server_content">';
    }
}

/**
 * Close the server content section
 * @subpackage core/output
 */
class Hm_Output_server_content_end extends Hm_Output_Module {
    /**
     * Closes the div tag opened in Hm_Output_server_content_start
     */
    protected function output() {
        return '</div>';
    }
}

/**
 * Output a date
 * @subpackage core/output
 */
class Hm_Output_date extends Hm_Output_Module {
    /**
     * Currently not show in the display (hidden with CSS)
     */
    protected function output() {
        return '<div class="date">'.$this->html_safe($this->get('date')).'</div>';
    }
}

/**
 * Display system messages
 * @subpackage core/output
 */
class Hm_Output_msgs extends Hm_Output_Module {
    /**
     * Error level messages start with ERR and will be shown in red
     */
    protected function output() {
        $res = '';
        $msgs = Hm_Msgs::getRaw();
        $logged_out_class = '';
        if (!$this->get('router_login_state') && !empty($msgs)) {
            $logged_out_class = ' logged_out';
        }
        $res .= '<div class="position-fixed top-0 col-sm-4 col-md-3 end-0 mt-3 me-3 sys_messages'.$logged_out_class.'"></div>';
        $res .= '<script type="text/javascript">var hm_msgs = '.json_encode($msgs).'</script>';
        return $res;
    }
}

/**
 * Start the HTML5 document
 * @subpackage core/output
 */
class Hm_Output_header_start extends Hm_Output_Module {
    /**
     * Doctype, html, head, and a meta tag for charset. The head section is not closed yet
     */
    protected function output() {
        $lang = 'en';
        $dir = 'ltr';
        if ($this->lang) {
            $lang = mb_strtolower(str_replace('_', '-', $this->lang));
        }
        if ($this->dir) {
            $dir = $this->dir;
        }
        $class = $dir."_page";
        $res = '<!DOCTYPE html><html dir="'.$this->html_safe($dir).'" class="'.
            $this->html_safe($class).'" lang='.$this->html_safe($lang).'><head>'.
            '<meta name="apple-mobile-web-app-capable" content="yes" />'.
            '<meta name="mobile-web-app-capable" content="yes" />'.
            '<meta name="apple-mobile-web-app-status-bar-style" content="black" />'.
            '<meta name="theme-color" content="#888888" /><meta charset="utf-8" />';

        if ($this->get('router_login_state')) {
            $res .= '<meta name="referrer" content="no-referrer" />';
        }
        return $res;
    }
}

/**
 * Close the HTML5 head tag
 * @subpackage core/output
 */
class Hm_Output_header_end extends Hm_Output_Module {
    /**
     * Close the head tag opened in Hm_Output_header_start
     */
    protected function output() {
        return '</head>';
    }
}

/**
 * Start the content section
 * @subpackage core/output
 */
class Hm_Output_content_start extends Hm_Output_Module {
    /**
     * Outputs the starting body tag and a noscript warning. Clears the local session cache
     * if not logged in, or adds a page wide key used by ajax requests
     */
    protected function output() {
        $res = '<body class="'.($this->get('is_mobile', false) ? 'mobile' : '').'"><noscript class="noscript">'.
            sprintf($this->trans('You need to have Javascript enabled to use %s, sorry about that!'),
                $this->html_safe($this->get('router_app_name'))).'</noscript>';
        if (!$this->get('router_login_state')) {
            $res .= '<script type="text/javascript">sessionStorage.clear();</script>';
        }
        else {
            $res .= '<input type="hidden" id="hm_page_key" value="'.$this->html_safe(Hm_Request_Key::generate()).'" />';
        }
        if (!$this->get('single_server_mode') && count($this->get('changed_settings', array())) > 0) {
            $res .= '<a class="unsaved_icon" href="?page=save" title="'.$this->trans('Unsaved Changes').
                '"><i class="bi bi-save2-fill fs-5 unsaved_reminder"></i></a>';
        }
        $res .= '<div class="cypht-layout">';
        return $res;
    }
}

/**
 * Outputs HTML5 head tag content
 * @subpackage core/output
 */
class Hm_Output_header_content extends Hm_Output_Module {
    /**
     * Setup a title, base URL, a tab icon, and viewport settings
     */
    protected function output() {
        $title = '';
        if (!$this->get('router_login_state')) {
            $title = $this->get('router_app_name');
        }
        elseif ($this->exists('page_title')) {
            $title .= $this->get('page_title');
        }
        elseif ($this->exists('mailbox_list_title')) {
            $title .= ' '.implode('-', $this->get('mailbox_list_title', array()));
        }
        if (!trim((string) $title) && $this->exists('router_page_name')) {
            $title = '';
            if ($this->get('list_path') == 'message_list') {
                $title .= ' '.ucwords(str_replace('_', ' ', $this->get('list_path')));
            }
            elseif ($this->get('router_page_name') == 'notfound') {
                $title .= ' Nope';
            }
            else {
                $title .= ' '.ucwords(str_replace('_', ' ', $this->get('router_page_name')));
            }
        }
        return '<title>'.$this->trans(trim((string) $title)).'</title>'.
            '<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0">'.
            '<link rel="icon" class="tab_icon" type="image/png" href="data:image/png;base64,iVBORw0KGgo'.
            'AAAANSUhEUgAAABAAAAAQCAYAAAAf8/9hAAAABHNCSVQICAgIfAhkiAAAAAlwSFlzAAALEwAACxMBAJqcGAAAAFVJR'.
            'EFUOI3NkkEKACEMA2d92fpzfVn3oHhYqAF7qIFeSpImUMjGA1jEoEQTFKAC/UDbp3bhBRqj0m7a5C78F56Rx5MEdUB'.
            'HFMlkV09ogN3xB7kG+fgA0tc160Jy09wAAAAASUVORK5CYII=" >'.
            '<base href="'.$this->html_safe($this->get('router_url_path')).'" />';
    }
}

/**
 * Output CSS
 * @subpackage core/output
 */
class Hm_Output_header_css extends Hm_Output_Module {
    /**
     * In debug mode adds each module css file to the page, otherwise uses the combined version
     */
    protected function output() {
        $res = '';
        $mods = $this->get('router_module_list');
        if (! $this->get('theme')) {
            $res .= '<link href="' . WEB_ROOT . 'modules/themes/assets/default/css/default.css?v=' . CACHE_ID . '" media="all" rel="stylesheet" type="text/css" />';
        }
        if (DEBUG_MODE) {
            $res .= '<link href="'.WEB_ROOT.'vendor/twbs/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet" type="text/css" />';
            foreach (glob(APP_PATH.'modules'.DIRECTORY_SEPARATOR.'**', GLOB_ONLYDIR | GLOB_MARK) as $name) {
                $rel_name = str_replace(APP_PATH, '', $name);
                $mod = str_replace(array('modules', DIRECTORY_SEPARATOR), '', $rel_name);
                if (in_array($mod, $mods, true) && is_readable(sprintf("%ssite.css", $name))) {
                    $res .= '<link href="'.WEB_ROOT.sprintf("%ssite.css", $rel_name).'" media="all" rel="stylesheet" type="text/css" />';
                }
            }
            // load pcss3t.cs only if one of: ['contacts','local_contacts','ldap_contacts','gmail_contacts'] is enabled
            if(count(array_intersect(['contacts','local_contacts','ldap_contacts','gmail_contacts'], $mods)) > 0){
                $res .= '<link href="'.WEB_ROOT.'third_party/contact-group.css" media="all" rel="stylesheet" type="text/css" />';
            }
        }
        else {
            $res .= '<link href="'.WEB_ROOT.'site.css?v='.CACHE_ID.'" ';
            if (defined('CSS_HASH') && CSS_HASH) {
                $res .= 'integrity="'.CSS_HASH.'" ';
            }
            $res .= 'media="all" rel="stylesheet" type="text/css" />';
        }
        $res .= '<style type="text/css">@font-face {font-family:"Behdad";'.
            'src:url("'.WEB_ROOT.'modules/core/assets/fonts/Behdad/Behdad-Regular.woff2") format("woff2"),'.
            'url("'.WEB_ROOT.'modules/core/assets/fonts/Behdad/Behdad-Regular.woff") format("woff");</style>';
        return $res;
    }
}

/**
 * Output JS
 * @subpackage core/output
 */
class Hm_Output_page_js extends Hm_Output_Module {
    /**
     * In debug mode adds each module js file to the page, otherwise uses the combined version.
     * Includes the cash.js library, and the forge lib if it's needed.
     */
    protected function output() {
        if (DEBUG_MODE) {
            $res = '';
            $js_exclude_dependencies = explode(',', $this->get('router_js_exclude_deps', ''));
            $js_lib = get_js_libs($js_exclude_dependencies);
            if ($this->get('encrypt_ajax_requests', '') || $this->get('encrypt_local_storage', '')) {
                $js_lib .= '<script type="text/javascript" src="'.WEB_ROOT.'third_party/forge.min.js"></script>';
            }
            $core = false;
            $mods = $this->get('router_module_list');
            foreach (glob(APP_PATH.'modules'.DIRECTORY_SEPARATOR.'**', GLOB_ONLYDIR | GLOB_MARK) as $name) {
                $rel_name = str_replace(APP_PATH, '', $name);
                $mod = str_replace(array('modules', DIRECTORY_SEPARATOR), '', $rel_name);
                if (in_array($mod, $mods, true)) {
                    $directoriesPattern = str_replace('/', DIRECTORY_SEPARATOR, "{*,*/*}");
                    foreach (glob($name.'js_modules' . DIRECTORY_SEPARATOR . $directoriesPattern . "*.js", GLOB_BRACE) as $js) {
                        if (preg_match('/\[(.+)\]/', $js, $matches)) {
                            $dep = $matches[1];
                            if (in_array($dep, $js_exclude_dependencies)) {
                                continue;
                            }
                        }
                        $res .= '<script type="text/javascript" src="'.WEB_ROOT.str_replace(APP_PATH, '', $js).'"></script>';
                    }

                    if ($rel_name == 'modules'.DIRECTORY_SEPARATOR.'core'.DIRECTORY_SEPARATOR) {
                        $core = $rel_name;
                        continue;
                    }

                    if (is_readable(sprintf("%ssite.js", $name))) {
                        $res .= '<script type="text/javascript" src="'.WEB_ROOT.sprintf("%ssite.js", $rel_name).'"></script>';
                    }
                }
            }
            if ($core) {
                $res = // Load navigation utilities used by subsequent modules' handlers
                '<script type="text/javascript" src="' . WEB_ROOT . 'modules/core/navigation/utils.js"></script>'.
                '<script type="text/javascript" src="'.WEB_ROOT.sprintf("%ssite.js", $core).'"></script>'.
                $res;
                /* Load navigation js modules
                    * routes.js, navigation.js
                    * They have to be loaded after each module's js files, because routes.js depend on the handlers defined in the modules.
                    * Therefore, navigation.js is also loaded after routes.js, because the routes should be loaded beforehand to be able to navigate.
                */
                foreach (['routes', 'navigation', 'navbar'] as $js) {
                    $res .= '<script type="text/javascript" src="'.WEB_ROOT.sprintf("%snavigation/%s.js", $core, $js).'"></script>';
                }
            }
            return $js_lib.$res;
        }
        else {
            $res = '<script type="text/javascript" ';
            if (defined('JS_HASH') && JS_HASH) {
                $res .= 'integrity="'.JS_HASH.'" ';
            }
            $res .= 'src="'.WEB_ROOT.'site.js?v='.CACHE_ID.'" async></script>';
            return $res;
        }
    }
}

/**
 * End the page content
 * @subpackage core/output
 */
class Hm_Output_content_end extends Hm_Output_Module {
    /**
     * Closes the layout wrapper, body, and html tags
     */
    protected function output() {
        return '</div></body></html>';
    }
}

/**
 * Outputs page details for the JS
 * @subpackage core/output
 */
class Hm_Output_js_data extends Hm_Output_Module {
    /**
     * Uses function wrappers to make the data immutable from JS
     */
    protected function output() {
        $res = '<script type="text/javascript" id="data-store">'.
            'var globals = {};'.
            'var hm_is_logged = function () { return '.($this->get('is_logged') ? '1' : '0').'; };'.
            'var hm_empty_folder = function() { return "'.$this->trans('So alone').'"; };'.
            'var hm_mobile = function() { return '.($this->get('is_mobile') ? '1' : '0').'; };'.
            'var hm_debug = function() { return "'.(DEBUG_MODE ? '1' : '0').'"; };'.
            'var hm_mailto = function() { return '.($this->get('mailto_handler') ? '1' : '0').'; };'.
            'var hm_page_name = function() { return "'.$this->html_safe($this->get('router_page_name')).'"; };'.
            'var hm_language_direction = function() { return "'.$this->html_safe($this->dir).'"; };'.
            'var hm_list_path = function() { return "'.$this->html_safe($this->get('list_path', '')).'"; };'.
            'var hm_list_parent = function() { return "'.$this->html_safe($this->get('list_parent', '')).'"; };'.
            'var hm_msg_uid = function() { return Hm_Utils.get_from_global("msg_uid", "'.$this->html_safe($this->get('uid', '')).'"); };'.
            'var hm_encrypt_ajax_requests = function() { return "'.$this->html_safe($this->get('encrypt_ajax_requests', '')).'"; };'.
            'var hm_encrypt_local_storage = function() { return "'.$this->html_safe($this->get('encrypt_local_storage', '')).'"; };'.
            'var hm_web_root_path = function() { return "'.WEB_ROOT.'"; };'.
            'var hm_flag_image_src = function() { return "<i class=\"bi bi-star-half\"></i>"; };'.
            'var hm_check_dirty_flag = function() { return '.($this->get('warn_for_unsaved_changes', '') ? '1' : '0').'; };'.
            format_data_sources($this->get('data_sources', array()), $this);

        if (!$this->get('disable_delete_prompt', DEFAULT_DISABLE_DELETE_PROMPT)) {
            $res .= 'var hm_delete_prompt = function() { return confirm("'.$this->trans('Are you sure?').'"); };';
        }
        else {
            $res .= 'var hm_delete_prompt = function() { return true; };';
        }
        $res .= 'window.hm_current_lang = "'.$this->lang.'";'.
            'window.hm_translations = '.json_encode($this->all_trans()).';'.
            'var hm_trans = function(key, lang = window.hm_current_lang) {'.
            '    const langTranslations = window.hm_translations && window.hm_translations[lang];'.
            '    if (langTranslations && langTranslations[key] !== undefined && langTranslations[key] !== false) {'.
            '        return langTranslations[key];'.
            '    }'.
            '    return key;'.
            '};';
        $res .= 'window.hm_default_timezone = "'.$this->get('default_timezone','UTC').'";';
        $res .= 'var hm_module_is_supported = function(module) {'.
            '    return '.json_encode($this->get('enabled_modules', array())).'.indexOf(module) !== -1;'.
            '};';
        $res .= '</script>';
        return $res;
    }
}

/**
 * Outputs a load icon
 * @subpackage core/output
 */
class Hm_Output_loading_icon extends Hm_Output_Module {
    /**
     * Sort of ugly loading icon animated with js/css
     */
    protected function output() {
        return '<div class="loading_icon"></div>';
    }
}

/**
 * Start the main form on the settings page
 * @subpackage core/output
 */
class Hm_Output_start_settings_form extends Hm_Output_Module {
    /**
     * Opens a div, form and table
     */
    protected function output() {
        return '<div class="user_settings px-0"><div class="content_title px-3">'.$this->trans('Site Settings').'</div>'.
            '<form method="POST"><input type="hidden" name="hm_page_key" value="'.$this->html_safe(Hm_Request_Key::generate()).'" />'.
            '<div class="px-3"><table class="settings_table table table-borderless"><colgroup>'.
            '<col class="label_col"><col class="setting_col"></colgroup>';
    }
}

/**
 * Outputs the start page option on the settings page
 * @subpackage core/output
 */
class Hm_Output_start_page_setting extends Hm_Output_Module {
    /**
     * Can be any of the main combined pages
     */
    protected function output() {
        $options = start_page_opts();
        $settings = $this->get('user_settings', array());
        $res = '';
        $reset = '';

        if (array_key_exists('start_page', $settings)) {
            $start_page = $settings['start_page'];
        }
        else {
            $start_page = DEFAULT_START_PAGE;
        }
        $res = '<tr class="general_setting"><td><label for="start_page">'.
            $this->trans('First page after login').'</label></td>'.
            '<td><select class="form-select form-select-sm w-auto" id="start_page" name="start_page">';
        foreach ($options as $label => $val) {
            $res .= '<option ';
            if ($start_page == $val) {
                $res .= 'selected="selected" ';
                if ($start_page != DEFAULT_START_PAGE) {
                    $reset = '<span class="tooltip_restore" restore_aria_label="Restore default value"><i class="bi bi-arrow-repeat refresh_list reset_default_value_select"></i></span>';
                }
            }
            $res .= 'value="'.$val.'">'.$this->trans($label).'</option>';
        }
        $res .= '</select>'.$reset.'</td></tr>';
        return $res;
    }
}

/**
 * Outputs the default sort order option on the settings page
 * @subpackage core/output
 */
class Hm_Output_default_sort_order_setting extends Hm_Output_Module {
    /**
     * Can be any of the main combined pages
     */
    protected function output() {
        $options = default_sort_order_opts();
        $settings = $this->get('user_settings', array());
        $reset = '';

        if (array_key_exists('default_sort_order', $settings)) {
            $default_sort_order = $settings['default_sort_order'];
        }
        else {
            $default_sort_order = null;
        }
        $res = '<tr class="general_setting"><td><label for="default_sort_order">'.
            $this->trans('Default message sort order').'</label></td>'.
            '<td><select class="form-select form-select-sm w-auto" id="default_sort_order" name="default_sort_order">';
        foreach ($options as $val => $label) {
            $res .= '<option ';
            if ($default_sort_order == $val) {
                $res .= 'selected="selected" ';
                if ($default_sort_order != 'arrival') {
                    $reset = '<span class="tooltip_restore" restore_aria_label="Restore default value"><i class="bi bi-arrow-repeat refresh_list reset_default_value_select"></i></span>';
                }
            }
            $res .= 'value="'.$val.'">'.$this->trans($label).'</option>';
        }
        $res .= '</select>'.$reset.'</td></tr>';
        return $res;
    }
}

/**
 * Outputs the list style option on the settings page
 * @subpackage core/output
 */
class Hm_Output_list_style_setting extends Hm_Output_Module {
    /**
     * Can be either "news style" or the default "email style"
     */
    protected function output() {
        $options = array('email_style' => 'Email', 'news_style' => 'News');
        $settings = $this->get('user_settings', array());
        $reset = '';

        if (array_key_exists('list_style', $settings)) {
            $list_style = $settings['list_style'];
        }
        else {
            $list_style = DEFAULT_LIST_STYLE;
        }
        $res = '<tr class="general_setting"><td><label for="list_style">'.
            $this->trans('Message list style').'</label></td>'.
            '<td><select class="form-select form-select-sm w-auto" id="list_style" name="list_style" data-default-value="'.DEFAULT_LIST_STYLE.'">';
        foreach ($options as $val => $label) {
            $res .= '<option ';
            if ($list_style == $val) {
                $res .= 'selected="selected" ';
                if ($list_style != DEFAULT_LIST_STYLE) {
                    $reset = '<span class="tooltip_restore" restore_aria_label="Restore default value"><i class="bi bi-arrow-repeat refresh_list reset_default_value_select"></i></span>';
                }
            }
            $res .= 'value="'.$val.'">'.$this->trans($label).'</option>';
        }
        $res .= '</select>'.$reset.'</td></tr>';
        return $res;
    }
}

/**
 * @subpackage core/output
 */
class Hm_Output_mailto_handler_setting extends Hm_Output_Module {
    protected function output() {
        $settings = $this->get('user_settings');
        if (array_key_exists('mailto_handler', $settings) && $settings['mailto_handler']) {
            $checked = ' checked="checked"';
            $reset = '<span class="tooltip_restore" restore_aria_label="Restore default value"><i class="bi bi-arrow-repeat refresh_list reset_default_value_checkbox"></i></span>';
        }
        else {
            $checked = '';
            $reset = '';
        }
        return '<tr class="general_setting"><td><label class="form-check-label" for="mailto_handler">'.$this->trans('Allow handling of mailto links').'</label></td>'.
            '<td><input class="form-check-input" type="checkbox" '.$checked.' value="1" id="mailto_handler" name="mailto_handler" data-default-value="false" />'.$reset.'</td></tr>';
    }
}

/**
 * @subpackage core/output
 */
class Hm_Output_no_folder_icon_setting extends Hm_Output_Module {
    protected function output() {
        $settings = $this->get('user_settings');
        if (array_key_exists('no_folder_icons', $settings) && $settings['no_folder_icons']) {
            $checked = ' checked="checked"';
            $reset = '<span class="tooltip_restore" restore_aria_label="Restore default value"><i class="bi bi-arrow-repeat refresh_list reset_default_value_checkbox"></i></span>';
        }
        else {
            $checked = '';
            $reset = '';
        }
        return '<tr class="general_setting"><td><label class="form-check-label" for="no_folder_icons">'.$this->trans('Hide folder list icons').'</label></td>'.
            '<td><input class="form-check-input" type="checkbox" '.$checked.' value="1" id="no_folder_icons" name="no_folder_icons" data-default-value="false" />'.$reset.'</td></tr>';
    }
}

/**
 * @subpackage core/output
 */
class Hm_Output_no_password_setting extends Hm_Output_Module {
    protected function output() {
        $settings = $this->get('user_settings');
        $reset = '';
        if (array_key_exists('no_password_save', $settings) && $settings['no_password_save']) {
            $checked = ' checked="checked"';
        }
        else {
            $checked = '';
        }
        if(isset($settings['no_password_save']) && $settings['no_password_save'] !== DEFAULT_NO_PASSWORD_SAVE) {
            $reset = '<span class="tooltip_restore" restore_aria_label="Restore default value"><i class="bi bi-arrow-repeat refresh_list reset_default_value_checkbox"></i></span>';
        }
        return '<tr class="general_setting"><td><label class="form-check-label" for="no_password_save">'.$this->trans('Don\'t save account passwords between logins').'</label></td>'.
            '<td><input class="form-check-input" type="checkbox" '.$checked.' value="1" id="no_password_save" name="no_password_save" data-default-value="'.(DEFAULT_NO_PASSWORD_SAVE ? 'true' : 'false') . '" />'.$reset.'</td></tr>';
    }
}

/**
 * @subpackage core/output
 */
class Hm_Output_delete_prompt_setting extends Hm_Output_Module {
    protected function output() {
        $settings = $this->get('user_settings');
        $reset = '';
        $reset = '<span class="tooltip_restore" restore_aria_label="Restore default value"><i class="bi bi-arrow-repeat refresh_list reset_default_value_checkbox"></i></span>';
        if (array_key_exists('disable_delete_prompt', $settings) && $settings['disable_delete_prompt']) {
            $checked = ' checked="checked"';
        }
        else {
            $checked = '';
        }
        if(isset($settings['disable_delete_prompt']) && $settings['disable_delete_prompt'] !== DEFAULT_DISABLE_DELETE_PROMPT) {
            $reset = '<span class="tooltip_restore" restore_aria_label="Restore default value"><i class="bi bi-arrow-repeat refresh_list reset_default_value_checkbox"></i></span>';
        }
        return '<tr class="general_setting"><td><label class="form-check-label" for="disable_delete_prompt">'.$this->trans('Disable prompts when deleting').'</label></td>'.
            '<td><input class="form-check-input" type="checkbox" '.$checked.' value="1" id="disable_delete_prompt" name="disable_delete_prompt" data-default-value="'.(DEFAULT_DISABLE_DELETE_PROMPT ? 'true' : 'false') . '" />'.$reset.'</td></tr>';
    }
}

/**
 * @subpackage core/output
 */
class Hm_Output_delete_attachment_setting extends Hm_Output_Module {
    protected function output() {
        $checked = '';
        $reset = '';
        $settings = $this->get('user_settings');
        if (array_key_exists('allow_delete_attachment', $settings) && $settings['allow_delete_attachment']) {
            $checked = ' checked="checked"';
        }
        else {
            $reset = '<span class="tooltip_restore" restore_aria_label="Restore default value"><i class="bi bi-arrow-repeat refresh_list reset_default_value_checkbox"></i></span>';
        }
        return '<tr class="general_setting"><td><label class="form-check-label" for="allow_delete_attachment">'.$this->trans('Allow delete attachment').'</label></td>'.
            '<td><input class="form-check-input" type="checkbox" '.$checked.' value="1" id="allow_delete_attachment" name="allow_delete_attachment" data-default-value="false" />'.$reset.'</td></tr>';
    }
}

/**
 * Starts the Flagged section on the settings page
 * @subpackage core/output
 */
class Hm_Output_start_flagged_settings extends Hm_Output_Module {
    /**
     * Settings in this section control the flagged messages view
     */
    protected function output() {
        return '<tr><td data-target=".flagged_setting" colspan="2" class="settings_subtitle cursor-pointer border-bottom p-2">'.
            '<i class="bi bi-flag-fill fs-5 me-2"></i>'.
            $this->trans('Flagged').'</td></tr>';
    }
}

/**
 * Start the Everything section on the settings page
 * @subpackage core/output
 */
class Hm_Output_start_everything_settings extends Hm_Output_Module {
    /**
     * Setttings in this section control the Everything view
     */
    protected function output() {
        if ($this->get('single_server_mode')) {
            return '';
        }
        return '<tr><td data-target=".all_setting" colspan="2" class="settings_subtitle cursor-pointer border-bottom p-2">'.
            '<i class="bi bi-box2-fill fs-5 me-2"></i>'.
            $this->trans('Everything').'</td></tr>';
    }
}

/**
 * Start the Unread section on the settings page
 * @subpackage core/output
 */
class Hm_Output_start_unread_settings extends Hm_Output_Module {
    /**
     * Settings in this section control the Unread view
     */
    protected function output() {
        return '<tr><td data-target=".unread_setting" colspan="2" class="settings_subtitle cursor-pointer border-bottom p-2">'.
            '<i class="bi bi-envelope-fill fs-5 me-2"></i>'.
            $this->trans('Unread').'</td></tr>';
    }
}

/**
 * Start the E-mail section on the settings page.
 * @subpackage core/output
 */
class Hm_Output_start_all_email_settings extends Hm_Output_Module {
    /**
     * Settings in this section control the All E-mail view. Skipped if there are no
     * E-mail enabled module sets.
     */
    protected function output() {
        if (!email_is_active($this->get('router_module_list'))) {
            return '';
        }
        if ($this->get('single_server_mode')) {
            return '';
        }
        return '<tr><td data-target=".email_setting" colspan="2" class="settings_subtitle cursor-pointer border-bottom p-2">'.
            '<i class="bi bi-envelope-fill fs-5 me-2"></i>'.
            $this->trans('All Email').'</td></tr>';
    }
}

/**
 * Start the general settings section
 * @subpackage core/output
 */
class Hm_Output_start_general_settings extends Hm_Output_Module {
    /**
     * General settings like langauge and timezone will go here
     */
    protected function output() {
        return '<tr><td data-target=".general_setting" colspan="2" class="settings_subtitle cursor-pointer border-bottom p-2">'.
            '<i class="bi bi-gear-wide-connected fs-5 me-2"></i>'.
            $this->trans('General').'</td></tr>';
    }
}

/**
 * Option for the maximum number of messages per source for the Unread page
 * @subpackage core/output
 */
class Hm_Output_unread_source_max_setting extends Hm_Output_Module {
    /**
     * Processed by Hm_Handler_process_unread_source_max_setting
     */
    protected function output() {
        $sources = DEFAULT_UNREAD_PER_SOURCE;
        $settings = $this->get('user_settings', array());
        $reset = '';
        if (array_key_exists('unread_per_source', $settings)) {
            $sources = $settings['unread_per_source'];
        }
        if ($sources != DEFAULT_UNREAD_PER_SOURCE) {
            $reset = '<span class="tooltip_restore" restore_aria_label="Restore default value"><i class="bi bi-arrow-repeat refresh_list reset_default_value_input"></i></span>';
        }
        return '<tr class="unread_setting"><td><label for="unread_per_source">'.
            $this->trans('Max messages per source').'</label></td>'.
            '<td class="d-flex"><input class="form-control form-control-sm w-auto" type="text" size="2" id="unread_per_source" name="unread_per_source" value="'.$this->html_safe($sources).'" data-default-value="'.DEFAULT_UNREAD_PER_SOURCE.'" />'.$reset.'</td></tr>';
    }
}

/**
 * Option for the "received since" date range for the Unread page
 * @subpackage core/output
 */
class Hm_Output_unread_since_setting extends Hm_Output_Module {
    /**
     * Processed by Hm_Handler_process_unread_since_setting
     */
    protected function output() {
        $settings = $this->get('user_settings', array());
        $since = DEFAULT_UNREAD_SINCE;
        if (array_key_exists('unread_since', $settings) && $settings['unread_since']) {
            $since = $settings['unread_since'];
        }
        return '<tr class="unread_setting"><td><label for="unread_since">'.
            $this->trans('Show messages received since').'</label></td>'.
            '<td>'.message_since_dropdown($since, 'unread_since', $this,DEFAULT_UNREAD_SINCE).'</td></tr>';
    }
}

/**
 * Option for the maximum number of messages per source for the Flagged page
 * @subpackage core/output
 */
class Hm_Output_flagged_source_max_setting extends Hm_Output_Module {
    /**
     * Processed by Hm_Handler_process_flagged_source_max_setting
     */
    protected function output() {
        $sources = DEFAULT_FLAGGED_PER_SOURCE;
        $settings = $this->get('user_settings', array());
        $reset = '';
        if (array_key_exists('flagged_per_source', $settings)) {
            $sources = $settings['flagged_per_source'];
        }
        if ($sources != DEFAULT_FLAGGED_PER_SOURCE) {
            $reset = '<span class="tooltip_restore" restore_aria_label="Restore default value"><i class="bi bi-arrow-repeat refresh_list reset_default_value_input"></i></span>';
        }
        return '<tr class="flagged_setting"><td><label for="flagged_per_source">'.
            $this->trans('Max messages per source').'</label></td>'.
            '<td class="d-flex"><input class="form-control form-control-sm w-auto" type="text" size="2" id="flagged_per_source" name="flagged_per_source" value="'.$this->html_safe($sources).'" data-default-value="'.DEFAULT_FLAGGED_PER_SOURCE.'" />'.$reset.'</td></tr>';
    }
}

/**
 * Option for the "received since" date range for the Flagged page
 * @subpackage core/output
 */
class Hm_Output_flagged_since_setting extends Hm_Output_Module {
    protected function output() {
        /**
         * Processed by Hm_Handler_process_flagged_since_setting
         */
        $since = DEFAULT_FLAGGED_SINCE;
        $settings = $this->get('user_settings', array());
        if (array_key_exists('flagged_since', $settings) && $settings['flagged_since']) {
            $since = $settings['flagged_since'];
        }
        return '<tr class="flagged_setting"><td><label for="flagged_since">'.
            $this->trans('Show messages received since').'</label></td>'.
            '<td>'.message_since_dropdown($since, 'flagged_since', $this, DEFAULT_FLAGGED_SINCE).'</td></tr>';
    }
}

/**
 * Option for the maximum number of messages per source for the All E-mail  page
 * @subpackage core/output
 */
class Hm_Output_all_email_source_max_setting extends Hm_Output_Module {
    /**
     * Processed by Hm_Handler_process_all_email_source_max_setting
     */
    protected function output() {
        if (!email_is_active($this->get('router_module_list'))) {
            return '';
        }
        $sources = DEFAULT_ALL_EMAIL_PER_SOURCE;
        $settings = $this->get('user_settings', array());
        $reset = '';
        if (array_key_exists('all_email_per_source', $settings)) {
            $sources = $settings['all_email_per_source'];
        }
        if ($sources != DEFAULT_ALL_EMAIL_PER_SOURCE) {
            $reset = '<span class="tooltip_restore" restore_aria_label="Restore default value"><i class="bi bi-arrow-repeat refresh_list reset_default_value_input"></i></span>';
        }
        return '<tr class="email_setting"><td><label for="all_email_per_source">'.
            $this->trans('Max messages per source').'</label></td>'.
            '<td class="d-flex"><input class="form-control form-control-sm w-auto" type="text" size="2" id="all_email_per_source" name="all_email_per_source" value="'.$this->html_safe($sources).'" data-default-value="'.DEFAULT_ALL_EMAIL_PER_SOURCE.'" />'.$reset.'</td></tr>';
    }
}

/**
 * Option for the maximum number of messages per source for the Everything page
 * @subpackage core/output
 */
class Hm_Output_all_source_max_setting extends Hm_Output_Module {
    /**
     * Processed by Hm_Handler_process_all_source_max_setting
     */
    protected function output() {
        $sources = DEFAULT_ALL_PER_SOURCE;
        $settings = $this->get('user_settings', array());
        $reset = '';
        if (array_key_exists('all_per_source', $settings)) {
            $sources = $settings['all_per_source'];
        }
        if ($sources != DEFAULT_ALL_PER_SOURCE) {
            $reset = '<span class="tooltip_restore" restore_aria_label="Restore default value"><i class="bi bi-arrow-repeat refresh_list reset_default_value_input"></i></span>';
        }
        return '<tr class="all_setting"><td><label for="all_per_source">'.
            $this->trans('Max messages per source').'</label></td>'.
            '<td class="d-flex"><input class="form-control form-control-sm w-auto" type="text" size="2" id="all_per_source" name="all_per_source" value="'.$this->html_safe($sources).'" data-default-value="'.DEFAULT_ALL_PER_SOURCE.'" />'.$reset.'</td></tr>';
    }
}

/**
 * Option for the "received since" date range for the All E-mail page
 * @subpackage core/output
 */
class Hm_Output_all_email_since_setting extends Hm_Output_Module {
    /**
     * Processed by Hm_Handler_process_all_email_since_setting
     */
    protected function output() {
        if (!email_is_active($this->get('router_module_list'))) {
            return '';
        }
        $since = DEFAULT_ALL_EMAIL_SINCE;
        $settings = $this->get('user_settings', array());
        if (array_key_exists('all_email_since', $settings) && $settings['all_email_since']) {
            $since = $settings['all_email_since'];
        }
        return '<tr class="email_setting"><td><label for="all_email_since">'.
            $this->trans('Show messages received since').'</label></td>'.
            '<td>'.message_since_dropdown($since, 'all_email_since', $this, DEFAULT_ALL_EMAIL_SINCE).'</td></tr>';
    }
}

/**
 * Option for the "received since" date range for the Everything page
 * @subpackage core/output
 */
class Hm_Output_all_since_setting extends Hm_Output_Module {
    /**
     * Processed by Hm_Handler_process_all_since_setting
     */
    protected function output() {
        $since = DEFAULT_ALL_SINCE;
        $settings = $this->get('user_settings', array());
        if (array_key_exists('all_since', $settings) && $settings['all_since']) {
            $since = $settings['all_since'];
        }
        return '<tr class="all_setting"><td><label for="all_since">'.
            $this->trans('Show messages received since').'</label></td>'.
            '<td>'.message_since_dropdown($since, 'all_since', $this, DEFAULT_ALL_SINCE).'</td></tr>';
    }
}

/**
 * Option for the language setting
 * @subpackage core/output
 */
class Hm_Output_language_setting extends Hm_Output_Module {
    /**
     * Processed by Hm_Handler_process_language_setting
     */
    protected function output() {
        $langs = interface_langs();
        $translated = array();
        $reset = '';
        foreach ($langs as $code => $name) {
            $translated[$code] = $this->trans($name);
        }
        asort($translated);
        $mylang = $this->get('language', '');
        $res = '<tr class="general_setting"><td><label for="language">'.
            $this->trans('Language').'</label></td>'.
            '<td><select id="language" class="form-select form-select-sm w-auto" name="language">';
        foreach ($translated as $id => $lang) {
            $res .= '<option ';
            if ($id == $mylang) {
                $res .= 'selected="selected" ';
                if ($id != 'en') {
                    $reset = '<span class="tooltip_restore" restore_aria_label="Restore default value"><i class="bi bi-arrow-repeat refresh_list reset_default_value_select"></i></span>';
                }
            }
            $res .= 'value="'.$id.'">'.$lang.'</option>';
        }
        $res .= '</select>'.$reset.'</td></tr>';
        return $res;
    }
}

/**
 * Option for the timezone setting
 * @subpackage core/output
 */
class Hm_Output_timezone_setting extends Hm_Output_Module {
    /**
     * Processed by Hm_Handler_process_timezone_setting
     */
    protected function output() {
        $zones = timezone_identifiers_list();
        $settings = $this->get('user_settings', array());
        $reset = '';
        if (array_key_exists('timezone', $settings)) {
            $myzone = $settings['timezone'];
        }
        else {
            $myzone = $this->get('default_timezone','UTC');
        }
        $res = '<tr class="general_setting"><td><label for="timezone">'.
            $this->trans('Timezone').'</label></td><td><select class="w-auto form-select form-select-sm" id="timezone" name="timezone">';
        foreach ($zones as $zone) {
            $res .= '<option ';
            if ($zone == $myzone) {
                $res .= 'selected="selected" ';
                if ($zone != $this->get('default_timezone','UTC')) {
                    $reset = '<span class="tooltip_restore" restore_aria_label="Restore default value"><i class="bi bi-arrow-repeat refresh_list reset_default_timezone"></i></span>';
                }
            }
            $res .= 'value="'.$zone.'">'.$zone.'</option>';
        }
        $res .= '</select>'.$reset.'</td></tr>';
        return $res;
    }
}

/**
 * Option to enable/disable message list icons
 * @subpackage core/output
 */
class Hm_Output_msg_list_icons_setting extends Hm_Output_Module {
    protected function output() {
        $checked = '';
        $settings = $this->get('user_settings', array());
        $reset = '';
        if (array_key_exists('show_list_icons', $settings) && $settings['show_list_icons']) {
            $checked = ' checked="checked"';
            $reset = '<span class="tooltip_restore" restore_aria_label="Restore default value"><i class="bi bi-arrow-repeat refresh_list reset_default_value_checkbox"></i></span>';
        }
        return '<tr class="general_setting"><td><label class="form-check-label" for="show_list_icons">'.
            $this->trans('Show icons in message lists').'</label></td>'.
            '<td><input class="form-check-input" type="checkbox" '.$checked.' id="show_list_icons" name="show_list_icons" data-default-value="false" value="1" />'.$reset.'</td></tr>';
    }
}

/**
 * Ends the settings table
 * @subpackage core/output
 */
class Hm_Output_end_settings_form extends Hm_Output_Module {
    /**
     * Closes the table, form and div opened in Hm_Output_start_settings_form
     */
    protected function output() {
        return '<tr><td class="submit_cell" colspan="2">'.
            '<input class="save_settings btn btn-primary" type="submit" name="save_settings" value="'.$this->trans('Save').'" />'.
            '</td></tr></table></div></form>'.
            '<div class="px-3 d-flex justify-content-end"><form method="POST"><input type="hidden" name="hm_page_key" value="'.$this->html_safe(Hm_Request_Key::generate()).'" />'.
            '<input class="reset_factory_button btn btn-light border" type="submit" name="reset_factory" value="'.$this->trans('Restore Defaults').'" /></form></div>'.
            '</div>';
    }
}

/**
 * Start the folder list
 * @subpackage core/output
 */
class Hm_Output_folder_list_start extends Hm_Output_Module {
    /**
     * Opens the folder list nav tag
     */
    protected function output() {
        $res = '<nav class="folder_cell"><div class="folder_list">';
        return $res;
    }
}

/**
 * Start the folder list content
 * @subpackage core/output
 */
class Hm_Output_folder_list_content_start extends Hm_Output_Module {
    /**
     * Creates a modfiable string called formatted_folder_list other modules append to
     */
    protected function output() {
        if ($this->format == 'HTML5') {
            return '';
        }
        $this->out('formatted_folder_list', '', false);
    }
}

/**
 * Start the Main menu section of the folder list
 * @subpackage core/output
 */
class Hm_Output_main_menu_start extends Hm_Output_Module {
    /**
     * Opens a div and unordered list tag
     */
    protected function output() {
        $res = '';
        if (DEBUG_MODE) {
            $res .= '<span title="'.
                $this->trans('Running in debug mode. See https://cypht.org/install.html Section 6 for more detail.').
                '" class="debug_title">'.$this->trans('Debug').'</span>';
        }
        $res .= '<a href="?page=home" class="menu_home"><img class="app-logo" src="'.WEB_ROOT. 'modules/core/assets/images/logo_dark.svg"></a>';
        $res .= '<div class="main"><ul class="folders">';
        if ($this->format == 'HTML5') {
            return $res;
        }
        $this->concat('formatted_folder_list', $res);
    }
}

/**
 * Content for the Main menu section of the folder list
 * @subpackage core/output
 */
class Hm_Output_main_menu_content extends Hm_Output_Module {
    /**
     * Links to default pages: Home, Everything, Unread and Flagged
     * @todo break this up into smaller modules
     */
    protected function output() {
        $res = '';
        $email = false;
        if (array_key_exists('email_folders', merge_folder_list_details($this->get('folder_sources', array())))) {
            $email = true;
        }
        $total_accounts = count($this->get('imap_servers', array())) + count($this->get('feeds', array()));
        if ($total_accounts > 1) {
            $res .= '<li class="menu_combined_inbox"><a class="unread_link" href="?page=message_list&amp;list_path=combined_inbox">';
            if (!$this->get('hide_folder_icons')) {
                $res .= '<i class="bi bi-box2-fill menu-icon"></i>';
            }
            $res .= '<span class="nav-label">'.$this->trans('Everything').'</span</a><span class="combined_inbox_count"></span></li>';
        }
        $res .= '<li class="menu_unread d-flex align-items-center"><a class="unread_link d-flex align-items-center" href="?page=message_list&amp;list_path=unread">';
        if (!$this->get('hide_folder_icons')) {
            $res .= '<i class="bi bi-envelope-fill menu-icon"></i>';
        }
        $res .= '<span class="nav-label">'.$this->trans('Unread').'</span></a><span class="total_unread_count badge rounded-pill text-bg-info ms-2 px-1"></span></li>';
        $res .= '<li class="menu_flagged"><a class="unread_link" href="?page=message_list&amp;list_path=flagged">';
        if (!$this->get('hide_folder_icons')) {
            $res .= '<i class="bi bi-flag-fill menu-icon"></i>';
        }
        $res .= '<span class="nav-label">'.$this->trans('Flagged').'</span></a> <span class="flagged_count"></span></li>';
        $res .= '<li class="menu_junk"><a class="unread_link" href="?page=message_list&amp;list_path=junk">';
        if (!$this->get('hide_folder_icons')) {
            $res .= '<i class="bi bi-envelope-x-fill menu-icon"></i>';
        }
        $res .= '<span class="nav-label">'.$this->trans('Junk').'</span></a></li>';
        $res .= '<li class="menu_trash"><a class="unread_link" href="?page=message_list&amp;list_path=trash">';
        if (!$this->get('hide_folder_icons')) {
            $res .= '<i class="bi bi-trash3-fill menu-icon"></i>';
        }
        $res .= '<span class="nav-label">'.$this->trans('Trash').'</span></a></li>';
        $res .= '<li class="menu_drafts"><a class="unread_link" href="?page=message_list&amp;list_path=drafts">';
        if (!$this->get('hide_folder_icons')) {
            $res .= '<i class="bi bi-pencil-square menu-icon"></i>';
        }
        $res .= '<span class="nav-label">'.$this->trans('Drafts').'</span></a></li>';
        $res .= '<li class="menu_snoozed"><a class="unread_link" href="?page=message_list&amp;list_path=snoozed">';
        if (!$this->get('hide_folder_icons')) {
            $res .= '<i class="bi bi-clock-fill menu-icon"></i>';
        }
        $res .= '<span class="nav-label">'.$this->trans('Snoozed').'</span></a></li>';

        if ($this->format == 'HTML5') {
            return $res;
        }
        $this->concat('formatted_folder_list', $res);
    }
}

/**
 * Close the Main menu section of the folder list
 * @subpackage core/output
 */
class Hm_Output_main_menu_end extends Hm_Output_Module {
    protected function output() {
        $res = '</ul></div>';
        if ($this->format == 'HTML5') {
            return $res;
        }
        $this->concat('formatted_folder_list', $res);
    }
}

/**
 * Output the E-mail section of the folder list
 * @subpackage core/output
 */
class Hm_Output_email_menu_content extends Hm_Output_Module {
    /**
     * Displays a list of all configured E-mail accounts
     */
    protected function output() {
        $res = '';
        $folder_sources = merge_folder_list_details($this->get('folder_sources'));
        $single = $this->get('single_server_mode');
        foreach ($folder_sources as $src => $content) {
            $parts = explode('_', $src);
            array_pop($parts);
            $name = ucwords(implode(' ', $parts));
            $class = $this->html_safe($src);
            if (!$single) {
                $res .= '<div class="src_name d-flex justify-content-between pe-2" data-bs-toggle="collapse" role="button" data-bs-target=".'.$this->html_safe($src).'">'.$this->trans($name).
                    '<i class="bi bi-chevron-down"></i></div>';
                $class .= ' collapse';
            }

            $res .= '<div class="'.$class.'"><ul class="folders">';
            if ($name == 'Email' && count($this->get('imap_servers', array()))  > 1) {
                $res .= '<li class="menu_email"><a class="unread_link" href="?page=message_list&amp;list_path=email">';
                if (!$this->get('hide_folder_icons')) {
                    $res .= '<i class="bi bi-globe-americas menu-icon"></i>';
                }
                $res .= $this->trans('All').'</a> <span class="unread_mail_count"></span></li>';
            }
            $res .= $content.'</ul></div>';
        }
        if ($this->format == 'HTML5') {
            return $res;
        }
        $this->concat('formatted_folder_list', $res);
    }
}

/**
 * Start the Settings section of the folder list
 * @subpackage core/output
 */
class Hm_Output_settings_menu_start extends Hm_Output_Module {
    /**
     * Opens an unordered list
     */
    protected function output() {
        $res = '<div class="src_name d-flex justify-content-between pe-2" data-bs-toggle="collapse" role="button" data-bs-target=".settings">'.$this->trans('Settings').
            '<i class="bi bi-chevron-down"></i></div>'.
            '<ul class="collapse settings folders">';
        if ($this->format == 'HTML5') {
            return $res;
        }
        $this->concat('formatted_folder_list', $res);
    }
}

/**
 * Save settings page content
 * @subpackage core/output
 */
class Hm_Output_save_form extends Hm_Output_Module {
    /**
     * Outputs save form
     */
    protected function output() {
        $changed = $this->get('changed_settings', array());
        $res = '<div class="save_settings_page p-0"><div class="content_title px-3">'.$this->trans('Save Settings').'</div>';
        $res .= '<div class="save_details p-3">'.$this->trans('Settings are not saved permanently on the server unless you explicitly allow it. '.
            'If you don\'t save your settings, any changes made since you last logged in will be deleted when your '.
            'session expires or you logout. You must re-enter your password for security purposes to save your settings '.
            'permanently.');
        $res .= '<div class="save_subtitle mt-3"><b>'.$this->trans('Unsaved Changes').'</b></div>';
        $res .= '<ul class="unsaved_settings">';
        if (!empty($changed)) {
            $changed = array_count_values($changed);
            foreach ($changed as $change => $num) {
                $res .= '<li>'.$this->trans($change).' ('.$this->html_safe($num).'X)</li>';
            }
        }
        else {
            $res .= '<li>'.$this->trans('No changes need to be saved').'</li>';
        }
        $res .= '</ul></div><div class="save_perm_form px-3"><form method="post">'.
            '<input type="hidden" name="hm_page_key" value="'.$this->html_safe(Hm_Request_Key::generate()).'" />'.
            '<input type="text" value="'.$this->html_safe($this->get('username', 'cypht_user')).'" autocomplete="username" style="display: none;"/>'.
            '<label class="screen_reader" for="password">Password</label><input required id="password" '.
            'name="password" autocomplete="current-password" class="save_settings_password form-control mb-2 warn_on_paste" type="password" placeholder="'.$this->trans('Password').'" />'.
            '<input class="save_settings btn btn-primary me-2" type="submit" name="save_settings_permanently" value="'.$this->trans('Save').'" />'.
            '<input class="save_settings btn btn-outline-secondary me-2" type="submit" name="save_settings_permanently_then_logout" value="'.$this->trans('Save and Logout').'" />'.
            '</form><form method="post"><input type="hidden" name="hm_page_key" value="'.$this->html_safe(Hm_Request_Key::generate()).'" />'.
            '<input class="save_settings btn btn-outline-secondary" type="submit" name="logout" value="'.$this->trans('Just Logout').'" />'.
            '</form></div>';

        $res .= '</div>';
        return $res;
    }
}

/**
 * Servers link for the Settings menu section of the folder list
 * @subpackage core/output
 */
class Hm_Output_settings_servers_link extends Hm_Output_Module {
    /**
     * Outputs links to the Servers settings pages
     */
    protected function output() {
        $res = '<li class="menu_servers"><a class="unread_link" href="?page=servers">';
        if (!$this->get('hide_folder_icons')) {
            $res .= '<i class="bi bi-pc-display-horizontal menu-icon"></i>';
        }
        $res .= $this->trans('Servers').'</a></li>';
        $this->concat('formatted_folder_list', $res);
    }
}

/**
 * Site link for the Settings menu section of the folder list
 * @subpackage core/output
 */
class Hm_Output_settings_site_link extends Hm_Output_Module {
    /**
     * Outputs links to the Site Settings pages
     */
    protected function output() {
        $res = '<li class="menu_settings"><a class="unread_link" href="?page=settings">';
        if (!$this->get('hide_folder_icons')) {
            $res .= '<i class="bi bi-gear-wide-connected menu-icon"></i>';
        }
        $res .= $this->trans('Site').'</a></li>';
        $this->concat('formatted_folder_list', $res);
    }
}

/**
 * Save link for the Settings menu section of the folder list
 * @subpackage core/output
 */
class Hm_Output_settings_save_link extends Hm_Output_Module {
    /**
     * Outputs links to the Servers and Site Settings pages
     */
    protected function output() {
        if ($this->get('single_server_mode')) {
            return;
        }
        $res = '<li class="menu_save"><a class="unread_link" href="?page=save">';
        if (!$this->get('hide_folder_icons')) {
            $res .= '<i class="bi bi-download menu-icon"></i>';
        }
        $res .= $this->trans('Save').'</a></li>';
        $this->concat('formatted_folder_list', $res);
    }
}

/**
 * Closes the Settings menu in the folder list
 * @subpackage core/output
 */
class Hm_Output_settings_menu_end extends Hm_Output_Module {
    /**
     * Closes the ul tag opened in Hm_Output_settings_menu_start
     */
    protected function output() {
        $res = '</ul>';
        if ($this->format == 'HTML5') {
            return $res;
        }
        $this->concat('formatted_folder_list', $res);
    }
}

/**
 * End of the content section of the folder list
 * @subpackage core/output
 */
class Hm_Output_folder_list_content_end extends Hm_Output_Module {
    /**
     * Adds collapse and reload links
     */
    protected function output() {
        $res = '<div class="sidebar-footer">';
        $res .= '<a class="logout_link" href="#" title="'. $this->trans('Logout') .'">';
        if (!$this->get('hide_folder_icons')) {
            $res .= '<i class="bi bi-power menu-icon"></i>';
        }
        $res .= '<span class="nav-label">' . $this->trans('Logout') .'</span>';
        $res .= '</a>';
        $res .= '<a href="#" class="update_message_list" title="'. $this->trans('Reload') .'">';
        if (!$this->get('hide_folder_icons')) {
            $res .= '<i class="bi bi-arrow-clockwise menu-icon"></i>';
        }
        $res .= '<span class="nav-label">' . $this->trans('Reload') . '</span>';
        $res .= '</a>';
        /** Sidebar footer end */

        $res .= '<div class="menu-toggle rounded-pill fw-bold cursor-pointer"><i class="bi bi-list fs-5 fw-bold"></i></div>';
        if ($this->format == 'HTML5') {
            return $res;
        }
        $this->concat('formatted_folder_list', $res);
    }
}

/**
 * Closes the folder list
 * @subpackage core/output
 */
class Hm_Output_folder_list_end extends Hm_Output_Module {
    /**
     * Closes the div and nav tags opened in Hm_Output_folder_list_start
     */
    protected function output() {
        return '</div></nav>';
    }
}

/**
 * Starts the main content section
 * @subpackage core/output
 */
class Hm_Output_content_section_start extends Hm_Output_Module {
    /**
     * Opens a main tag for the primary content section
     */
    protected function output() {
        return '<main class="container-fluid content_cell" id="cypht-main"><div class="offline">'.$this->trans('Offline').'</div><div class="row m-0 position-relative">';
    }
}

/**
 * Closes the main content section
 * @subpackage core/output
 */
class Hm_Output_content_section_end extends Hm_Output_Module {
    /**
     * Closes the main tag opened in Hm_Output_content_section_start
     */
    protected function output() {
        return '</div></main>';
    }
}

/**
 * modals
 * @subpackage core/output
 */
class Hm_Output_modals extends Hm_Output_Module {
    /**
     * Outputs modals
     */
    protected function output() {
        $share_folder_modal = '<div class="modal fade" id="shareFolderModal" tabindex="-1" aria-labelledby="shareFolderModalLabel" aria-hidden="true">';
        $share_folder_modal .= '<div class="modal-dialog modal-lg">';
        $share_folder_modal .= '<div class="modal-content">';
        $share_folder_modal .= '<div class="modal-header">';
        $share_folder_modal .= '<h5 class="modal-title" id="shareFolderModalLabel">'.$this->trans('Edit Permissions').'</h5>';
        $share_folder_modal .= '<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>';
        $share_folder_modal .= '</div>';

        $share_folder_modal .= '<div class="modal-body">';
        $share_folder_modal .= '<div class="row">';

        $share_folder_modal .= '<div class="col-lg-8 col-md-12">';

        $share_folder_modal .= '<div id="loadingSpinner" class="text-center">';
        $share_folder_modal .= '<div class="spinner-border text-primary" role="status">';
        $share_folder_modal .= '<span class="visually-hidden">Loading...</span>';
        $share_folder_modal .= '</div>';
        $share_folder_modal .= '</div>';

        $share_folder_modal .= '<table class="table table-striped" id="permissionTable" style="display:none;">';
        $share_folder_modal .= '<thead>';
        $share_folder_modal .= '<tr>';
        $share_folder_modal .= '<th>'.$this->trans('User').'</th>';
        $share_folder_modal .= '<th>'.$this->trans('Permissions').'</th>';
        $share_folder_modal .= '<th>'.$this->trans('Actions').'</th>';
        $share_folder_modal .= '</tr>';
        $share_folder_modal .= '</thead>';
        $share_folder_modal .= '<tbody></tbody>';
        $share_folder_modal .= '</table>';
        
        $share_folder_modal .= '</div>';
        
        $share_folder_modal .= '<div class="col-lg-4 col-md-12">';
        $share_folder_modal .= '<form id="shareForm" action="" method="POST">';
        $share_folder_modal .= '<input type="hidden" name="server_id" id="server_id" value="">';
        $share_folder_modal .= '<input type="hidden" name="folder_uid" id="folder_uid" value="">';
        $share_folder_modal .= '<input type="hidden" name="folder" id="folder" value="">';

        $share_folder_modal .= '<div class="mb-3 row">';
        $share_folder_modal .= '<div class="col-12">';
        $share_folder_modal .= '<label class="form-label">'.$this->trans('Identifier').'</label>';
        $share_folder_modal .= '<div>';
        $share_folder_modal .= '<input type="radio" name="identifier" value="user" id="identifierUser" checked>';
        $share_folder_modal .= '<label for="identifierUser">'.$this->trans('User').':</label>';
        $share_folder_modal .= '<input type="text" class="form-control d-inline-block" id="email" name="email" required placeholder="Enter email">';
        $share_folder_modal .= '</div>';
        $share_folder_modal .= '<div>';
        $share_folder_modal .= '<input type="radio" name="identifier" value="all" id="identifierAll">';
        $share_folder_modal .= '<label for="identifierAll">'.$this->trans('All users (anyone)').'</label>';
        $share_folder_modal .= '</div>';
        $share_folder_modal .= '<div>';
        $share_folder_modal .= '<input type="radio" name="identifier" value="guests" id="identifierGuests">';
        $share_folder_modal .= '<label for="identifierGuests">'.$this->trans('Guests (anonymous)').'</label>';
        $share_folder_modal .= '</div>';
        $share_folder_modal .= '</div>';
        $share_folder_modal .= '</div>';

        $share_folder_modal .= '<div class="mb-3 row">';
        $share_folder_modal .= '<div class="col-12">';
        $share_folder_modal .= '<label class="form-label">'.$this->trans('Access Rights').'</label>';
        $share_folder_modal .= '<div>';
        $share_folder_modal .= '<input type="checkbox" name="access_read" id="accessRead" checked>';
        $share_folder_modal .= '<label for="accessRead">'.$this->trans('Read').'</label>';
        $share_folder_modal .= '</div>';
        $share_folder_modal .= '<div>';
        $share_folder_modal .= '<input type="checkbox" name="access_write" id="accessWrite">';
        $share_folder_modal .= '<label for="accessWrite">'.$this->trans('Write').'</label>';
        $share_folder_modal .= '</div>';
        $share_folder_modal .= '<div>';
        $share_folder_modal .= '<input type="checkbox" name="access_delete" id="accessDelete">';
        $share_folder_modal .= '<label for="accessDelete">'.$this->trans('Delete').'</label>';
        $share_folder_modal .= '</div>';
        $share_folder_modal .= '<div>';
        $share_folder_modal .= '<input type="checkbox" name="access_other" id="accessOther">';
        $share_folder_modal .= '<label for="accessOther">'.$this->trans('Other').'</label>';
        $share_folder_modal .= '</div>';
        $share_folder_modal .= '</div>';
        $share_folder_modal .= '</div>';

        $share_folder_modal .= '<div class="modal-footer">';
        $share_folder_modal .= '<button type="submit" class="btn btn-primary">'.$this->trans('Save').'</button>';
        $share_folder_modal .= '<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">'.$this->trans('Cancel').'</button>';
        $share_folder_modal .= '</div>';
        $share_folder_modal .= '</form>';
        $share_folder_modal .= '</div>';

        $share_folder_modal .= '</div>';
        $share_folder_modal .= '</div>';

        $share_folder_modal .= '</div>';
        $share_folder_modal .= '</div>';
        $share_folder_modal .= '</div>';

        return $share_folder_modal;
    }
}

/**
 * Starts the message view page
 * @subpackage core/output
 */
class Hm_Output_message_start extends Hm_Output_Module {
    /**
     * @todo this is pretty ugly, clean it up and remove module specific stuff
     */
    protected function output() {
        if ($this->in('list_parent', array('advanced_search', 'github_all', 'sent', 'search', 'flagged', 'combined_inbox', 'unread', 'feeds', 'email'))) {
            if ($this->get('list_parent') == 'combined_inbox') {
                $list_name = $this->trans('Everything');
            }
            elseif ($this->get('list_parent') == 'email') {
                $list_name = $this->trans('All Email');
            }
            else {
                $list_name = $this->trans(ucwords(str_replace('_', ' ', $this->get('list_parent', ''))));
            }
            if ($this->get('list_parent') == 'advanced_search') {
                $page = 'advanced_search';
            }
            elseif ($this->get('list_parent') == 'search') {
                $page = 'search';
            }
            else {
                $page = 'message_list';
            }
            $title = '<a href="?page='.$page.'&amp;list_path='.$this->html_safe($this->get('list_parent')).'">'.$list_name.'</a>';
            if (count($this->get('mailbox_list_title', array())) > 0) {
                $mb_title = array_map( function($v) { return $this->trans($v); }, $this->get('mailbox_list_title', array()));
                if (($key = array_search($list_name, $mb_title)) !== false) {
                    unset($mb_title[$key]);
                }
                $title .= '<i class="bi bi-caret-right-fill path_delim"></i>'.
                    '<a href="?page=message_list&amp;list_path='.$this->html_safe($this->get('list_path')).'">'.
                    implode('<i class="bi bi-caret-right-fill path_delim"></i>',
                    array_map( function($v) { return $this->trans($v); }, $mb_title)).'</a>';
            }
        }
        elseif ($this->get('mailbox_list_title')) {
            $url = '?page=message_list&amp;list_path='.$this->html_safe($this->get('list_path'));
            if ($this->get('list_page', 0)) {
                $url .= '&list_page='.$this->html_safe($this->get('list_page'));
            }
            if ($this->get('list_filter', '')) {
                $url .= '&filter='.$this->html_safe($this->get('list_filter'));
            }
            if ($this->get('list_sort', '')) {
                $url .= '&sort='.$this->html_safe($this->get('list_sort'));
            }
            $title = '<a href="'.$url.'">'.
                implode('<i class="bi bi-caret-right-fill path_delim"></i>',
                array_map( function($v) { return $this->trans($v); }, $this->get('mailbox_list_title', array()))).'</a>';
        }
        else {
            $title = '';
        }
        $res = '';
        if ($this->get('uid')) {
            $res .= '<input type="hidden" class="msg_uid" value="'.$this->html_safe($this->get('uid')).'" />';
        }
        $res .= '<div class="content_title">'.$title.'</div>';
        $res .= '<div class="msg_text">';
        return $res;
    }
}

/**
 * Close the message view content section
 * @subpackage core/output
 */
class Hm_Output_message_end extends Hm_Output_Module {
    /**
     * Closes the div opened in Hm_Output_message_start
     */
    protected function output() {
        return '</div>';
    }
}

/**
 * Not found content for a bad page request
 * @subpackage core/output
 */
class Hm_Output_notfound_content extends Hm_Output_Module {
    /**
     * Simple "page not found" page content
     */
    protected function output() {
        $res = '<div class="content_title">'.$this->trans('Page Not Found!').'</div>';
        $res .= '<div class="empty_list"><br />'.$this->trans('Nothingness').'</div>';
        return $res;
    }
}

/**
 * Start the table for a message list
 * @subpackage core/output
 */
class Hm_Output_message_list_start extends Hm_Output_Module {
    /**
     * Uses the message_list_fields input to determine the format.
     */
    protected function output() {

        $col_flds = array();
        $header_flds = array();
        foreach ($this->get('message_list_fields', array()) as $vals) {
            if ($vals[0]) {
                $col_flds[] = sprintf('<col class="%s">', $vals[0]);
            }
            if ($vals[1] && $vals[2]) {
                $header_flds[] = sprintf('<th class="%s">%s</th>', $vals[1], $this->trans($vals[2]));
            }
            else {
                $header_flds[] = '<th></th>';
            }
        }
        $res = '<div class="p-3"><table class="message_table table">';
        if (!$this->get('no_message_list_headers')) {
            if (!empty($col_flds)) {
                $res .= '<colgroup>'.implode('', $col_flds).'</colgroup>';
            }
            if (!empty($header_flds)) {
                $res .= '<thead><tr>'.implode('', $header_flds).'</tr></thead>';
            }
        }
        $res .= '<tbody class="message_table_body">';
        return $res;
    }
}

/**
 * Output the heading for the home page
 * @subpackage core/output
 */
class Hm_Output_home_heading extends Hm_Output_Module {
    /**
     */
    protected function output() {
        return '<div class="content_title">'.$this->trans('Home').'</div>';
    }
}

/**
 * Output password dialogs if no_password_save is active
 * @subpackage core/output
 */
class Hm_Output_home_password_dialogs extends Hm_Output_Module {
    /**
     * Allow the user to input passwords for this session
     */
    protected function output() {
        $missing = $this->get('missing_pw_servers', array());
        if (count($missing) > 0) {
            $res = '<div class="home_password_dialogs mt-3 col-lg-6 col-md-5 col-sm-12">';
            $res .= '<div class="card"><div class="card-body">';
            $res .= '<div class="card_title"><h4>Passwords</h4></div><p>'.$this->trans('You have elected to not store passwords between logins.').
                ' '.$this->trans('Enter your passwords below to gain access to these services during this session.').'</p>';

            foreach ($missing as $vals) {
                $id = $this->html_safe(sprintf('%s_%s', mb_strtolower($vals['type']), $vals['id']));
                $res .= '<div class="div_'.$id.' mt-3">'.$this->html_safe($vals['type']).' '.$this->html_safe($vals['name']).
                    ' '.$this->html_safe($vals['user']).' '.$this->html_safe($vals['server']).' <div class="input-group mt-2"><input placeholder="'.$this->trans('Password').
                    '" type="password" class="form-control pw_input" id="update_pw_'.$id.'" /> <input type="button" class="pw_update btn btn-primary" data-id="'.$id.
                    '" value="'.$this->trans('Update').'" /></div></div>';
            }
            $res .= '</div></div></div>';
            return $res;
        }
    }
}

/**
 * Output the heading for a message list
 * @subpackage core/output
 */
class Hm_Output_message_list_heading extends Hm_Output_Module {
    /**
     * Title, list controls, and message controls
     */
    protected function output() {
        $search_field = '';
        $terms = $this->get('search_terms', '');
        if ($this->get('custom_list_controls', '')) {
            $config_link = $this->get('custom_list_controls');
            $source_link = '';
            $refresh_link = '<a class="refresh_link" title="'.$this->trans('Refresh').'" href="#"><i class="bi bi-arrow-clockwise refresh_list"></i></a>';
        }
        elseif (!$this->get('no_list_controls', false)) {
            $source_link = '<a href="#" title="'.$this->trans('Sources').'" class="source_link"><i class="bi bi-folder-fill refresh_list"></i></a>';
            if ($this->get('list_path') == 'combined_inbox') {
                $path = 'all';
            }
            else {
                $path = $this->get('list_path');
            }
            $config_link = '<a title="'.$this->trans('Configure').'" href="?page=settings#'.$path.'_setting"><i class="bi bi-gear-wide refresh_list"></i></a>';
            $refresh_link = '<a class="refresh_link" title="'.$this->trans('Refresh').'" href="#"><i class="bi bi-arrow-clockwise refresh_list"></i></a>';
            //$search_field = '<form method="GET">
            //<input type="hidden" name="page" value="message_list" />
            //<input type="hidden" name="list_path" value="'.$this->html_safe($this->get('list_path')).'"/>
            //<input required type="search" placeholder="'.$this->trans('Search').'" id="search_terms" class="imap_keyword" name="search_terms" value="'.$this->html_safe($terms).'"/></form>';

        }
        else {
            $config_link = '';
            $source_link = '';
            $refresh_link = '';
            $search_field = '';
        }
        $res = '';
        $res .= '<div class="message_list p-0 '.$this->html_safe($this->get('list_path')).'_list"><div class="content_title d-flex gap-3 justify-content-between px-3 align-items-center">';
        $res .= '<div class="d-flex align-items-center gap-1">' . message_controls($this).'<div class="mailbox_list_title">'.
            implode('<i class="bi bi-caret-right-fill path_delim"></i>', array_map( function($v) { return $this->trans($v); },
                $this->get('mailbox_list_title', array()))).'</div>';
        if (!$this->get('is_mobile') && mb_substr((string) $this->get('list_path'), 0, 5) != 'imap_') {
            $res .= combined_sort_dialog($this);
        }
        $res .= '</div>';
        $res .= message_list_meta($this->module_output(), $this);
        $res .= list_controls($refresh_link, $config_link, $source_link, $search_field);
        $res .= list_sources($this->get('data_sources', array()), $this);
        $res .= '</div>';
        return $res;
    }
}

/**
 * End a message list table
 * @subpackage core/output
 */
class Hm_Output_message_list_end extends Hm_Output_Module {
    /**
     * Close the table opened in Hm_Output_message_list_start
     */
    protected function output() {
        $res = '</tbody></table></div></div>';
        return $res;
    }
}

/**
 * Add move/copy dialog to the search page
 * @subpackage imap/output
 */
class Hm_Output_search_move_copy_controls extends Hm_Output_Module {
    protected function output() {
        $res = '<span class="ctr_divider"></span> <a class="imap_move disabled_input btn btn-sm btn-secondary" href="#" data-action="copy">'.$this->trans('Copy').'</a>';
        $res .= '<a class="imap_move disabled_input btn btn-sm btn-secondary" href="#" data-action="move">'.$this->trans('Move').'</a>';
        $res .= '<div class="move_to_location"></div>';
        $res .= '<input type="hidden" class="move_to_type" value="" />';
        $res .= '<input type="hidden" class="move_to_string1" value="'.$this->trans('Move to ...').'" />';
        $res .= '<input type="hidden" class="move_to_string2" value="'.$this->trans('Copy to ...').'" />';
        $res .= '<input type="hidden" class="move_to_string3" value="'.$this->trans('Removed non-IMAP messages from selection. They cannot be moved or copied').'" />';
        // $res = "<strong>COPY/MOVE</strong>";
        $this->concat('msg_controls_extra', $res);
    }
}

/**
 * Starts the Junk section on the settings page
 * @subpackage core/output
 */
class Hm_Output_start_junk_settings extends Hm_Output_Module {
    /**
     * Settings in this section control the flagged messages view
     */
    protected function output() {
        return '<tr><td data-target=".junk_setting" colspan="2" class="settings_subtitle cursor-pointer border-bottom p-2">'.
            '<i class="bi bi-envelope-x-fill fs-5 me-2"></i>'.
            $this->trans('Junk').'</td></tr>';
    }
}

/**
 * Option for the maximum number of messages per source for the Junk page
 * @subpackage core/output
 */
class Hm_Output_junk_source_max_setting extends Hm_Output_Module {
    /**
     * Processed by Hm_Handler_process_all_source_max_setting
     */
    protected function output() {
        $sources = DEFAULT_JUNK_PER_SOURCE;
        $settings = $this->get('user_settings', array());
        $reset = '';
        if (array_key_exists('junk_per_source', $settings)) {
            $sources = $settings['junk_per_source'];
        }
        if ($sources != DEFAULT_JUNK_PER_SOURCE) {
            $reset = '<span class="tooltip_restore" restore_aria_label="Restore default value"><i class="bi bi-arrow-repeat refresh_list reset_default_value_input"></i></span>';
        }
        return '<tr class="junk_setting"><td><label for="junk_per_source">'.
            $this->trans('Max messages per source').'</label></td>'.
            '<td class="d-flex"><input class="form-control form-control-sm w-auto" type="text" size="2" id="junk_per_source" name="junk_per_source" value="'.$this->html_safe($sources).'" data-default-value="'.DEFAULT_JUNK_PER_SOURCE.'" />'.$reset.'</td></tr>';
    }
}

/**
 * Option for the "junk since" date range for the Junk page
 * @subpackage core/output
 */
class Hm_Output_junk_since_setting extends Hm_Output_Module {
    /**
     * Processed by Hm_Handler_process_junk_since_setting
     */
    protected function output() {
        $since = DEFAULT_JUNK_SINCE;
        $settings = $this->get('user_settings', array());
        if (array_key_exists('junk_since', $settings) && $settings['junk_since']) {
            $since = $settings['junk_since'];
        }
        return '<tr class="junk_setting"><td><label for="junk_since">'.
            $this->trans('Show junk messages since').'</label></td>'.
            '<td>'.message_since_dropdown($since, 'junk_since', $this, DEFAULT_JUNK_SINCE).'</td></tr>';
    }
}

/**
 * Starts the Trash section on the settings page
 * @subpackage core/output
 */
class Hm_Output_start_trash_settings extends Hm_Output_Module {
    /**
     * Settings in this section control the flagged messages view
     */
    protected function output() {
        return '<tr><td data-target=".trash_setting" colspan="2" class="settings_subtitle cursor-pointer border-bottom p-2">'.
            '<i class="bi bi-trash3-fill fs-5 me-2"></i>'.
            $this->trans('Trash').'</td></tr>';
    }
}

/**
 * Option for the maximum number of messages per source for the Trash page
 * @subpackage core/output
 */
class Hm_Output_trash_source_max_setting extends Hm_Output_Module {
    /**
     * Processed by Hm_Handler_process_all_source_max_setting
     */
    protected function output() {
        $sources = DEFAULT_TRASH_PER_SOURCE;
        $settings = $this->get('user_settings', array());
        $reset = '';
        if (array_key_exists('trash_per_source', $settings)) {
            $sources = $settings['trash_per_source'];
        }
        if ($sources != DEFAULT_TRASH_PER_SOURCE) {
            $reset = '<span class="tooltip_restore" restore_aria_label="Restore default value"><i class="bi bi-arrow-repeat refresh_list reset_default_value_input"></i></span>';
        }
        return '<tr class="trash_setting"><td><label for="trash_per_source">'.
            $this->trans('Max messages per source').'</label></td>'.
            '<td class="d-flex"><input class="form-control form-control-sm w-auto" type="text" size="2" id="trash_per_source" name="trash_per_source" value="'.$this->html_safe($sources).'" data-default-value="'.DEFAULT_TRASH_PER_SOURCE.'" />'.$reset.'</td></tr>';
    }
}

/**
 * Option for the "trash since" date range for the Trash page
 * @subpackage core/output
 */
class Hm_Output_trash_since_setting extends Hm_Output_Module {
    /**
     * Processed by Hm_Handler_process_trash_since_setting
     */
    protected function output() {
        $since = DEFAULT_TRASH_SINCE;
        $settings = $this->get('user_settings', array());
        if (array_key_exists('trash_since', $settings) && $settings['trash_since']) {
            $since = $settings['trash_since'];
        }
        return '<tr class="trash_setting"><td><label for="trash_since">'.
            $this->trans('Show trash messages since').'</label></td>'.
            '<td>'.message_since_dropdown($since, 'trash_since', $this, DEFAULT_TRASH_SINCE).'</td></tr>';
    }
}
/**
 * Starts the Snoozed section on the settings page
 * @subpackage core/output
 */
class Hm_Output_start_snoozed_settings extends Hm_Output_Module {
    /**
     * Settings in this section control the snoozed messages view
     */
    protected function output() {
        return '<tr><td data-target=".snoozed_setting" colspan="2" class="settings_subtitle cursor-pointer border-bottom p-2">'.
            '<i class="bi bi-clock-fill fs-5 me-2"></i>'.
            $this->trans('Snoozed').'</td></tr>';
    }
}
/**
 * Option for the maximum number of messages per source for the Snoozed page
 * @subpackage core/output
 */
class Hm_Output_snoozed_source_max_setting extends Hm_Output_Module {
    /**
     * Processed by Hm_Handler_process_snoozed_source_max_setting
     */
    protected function output() {
        $sources = DEFAULT_SNOOZED_PER_SOURCE;
        $settings = $this->get('user_settings', array());
        $reset = '';
        if (array_key_exists('snoozed_per_source', $settings)) {
            $sources = $settings['snoozed_per_source'];
        }
        if ($sources != DEFAULT_SNOOZED_PER_SOURCE) {
            $reset = '<span class="tooltip_restore" restore_aria_label="Restore default value"><i class="bi bi-arrow-repeat refresh_list reset_default_value_input"></i></span>';
        }
        return '<tr class="snoozed_setting"><td><label for="snoozed_per_source">'.
            $this->trans('Max messages per source for Snoozed').'</label></td>' .
            '<td class="d-flex"><input class="form-control form-control-sm w-auto" type="text" size="2" id="snoozed_per_source" name="snoozed_per_source" value="'.$this->html_safe($sources).'" data-default-value="'.DEFAULT_SNOOZED_PER_SOURCE.'" />'.$reset.'</td></tr>';
    }
}
/**
 * Option for the snoozed messages date range
 * @subpackage core/output
 */
class Hm_Output_snoozed_since_setting extends Hm_Output_Module {
    /**
     * Processed by Hm_Handler_process_snoozed_since_setting
     */
    protected function output() {
        $since = DEFAULT_SNOOZED_SINCE;
        $settings = $this->get('user_settings', array());
        if (array_key_exists('snoozed_since', $settings) && $settings['snoozed_since']) {
            $since = $settings['snoozed_since'];
        }
        return '<tr class="snoozed_setting"><td><label for="snoozed_since">'.
            $this->trans('Show snoozed messages since').'</label></td>'.
            '<td>'.message_since_dropdown($since, 'snoozed_since', $this, DEFAULT_SNOOZED_SINCE).'</td></tr>';
    }
}
/**
 * Starts the Draft section on the settings page
 * @subpackage core/output
 */
class Hm_Output_start_drafts_settings extends Hm_Output_Module {
    /**
     * Settings in this section control the flagged messages view
     */
    protected function output() {
        return '<tr><td data-target=".drafts_setting" colspan="2" class="settings_subtitle cursor-pointer border-bottom p-2">'.
            '<i class="bi bi-pencil-square fs-5 me-2"></i>'.
            $this->trans('Drafts').'</td></tr>';
    }
}

/**
 * Option for the maximum number of messages per source for the Draft page
 * @subpackage core/output
 */
class Hm_Output_drafts_source_max_setting extends Hm_Output_Module {
    /**
     * Processed by Hm_Handler_process_all_source_max_setting
     */
    protected function output() {
        $sources = DEFAULT_DRAFT_PER_SOURCE;
        $settings = $this->get('user_settings', array());
        $reset = '';
        if (array_key_exists('drafts_per_source', $settings)) {
            $sources = $settings['drafts_per_source'];
        }
        if ($sources != DEFAULT_DRAFT_PER_SOURCE) {
            $reset = '<span class="tooltip_restore" restore_aria_label="Restore default value"><i class="bi bi-arrow-repeat refresh_list reset_default_value_input"></i></span>';
        }
        return '<tr class="drafts_setting"><td><label for="drafts_per_source">'.
            $this->trans('Max messages per source').'</label></td>'.
            '<td class="d-flex"><input class="form-control form-control-sm w-auto" type="text" size="2" id="drafts_per_source" name="drafts_per_source" value="'.$this->html_safe($sources).'" data-default-value="'.DEFAULT_DRAFT_PER_SOURCE.'" />'.$reset.'</td></tr>';
    }
}

/**
 * Option for the "draft since" date range for the Draft page
 * @subpackage core/output
 */
class Hm_Output_drafts_since_setting extends Hm_Output_Module {
    /**
     * Processed by Hm_Handler_process_draft_since_setting
     */
    protected function output() {
        $since = DEFAULT_DRAFT_SINCE;
        $settings = $this->get('user_settings', array());
        if (array_key_exists('drafts_since', $settings) && $settings['drafts_since']) {
            $since = $settings['drafts_since'];
        }
        return '<tr class="drafts_setting"><td><label for="drafts_since">'.
            $this->trans('Show draft messages since').'</label></td>'.
            '<td>'.message_since_dropdown($since, 'drafts_since', $this, DEFAULT_DRAFT_SINCE).'</td></tr>';
    }
}

/**
 * Option to warn user when he has unsaved changes.
 * @subpackage imap/output
 */
class Hm_Output_warn_for_unsaved_changes_setting extends Hm_Output_Module {
    protected function output() {
        $checked = '';
        $settings = $this->get('user_settings', array());
        $reset = '';
        if (array_key_exists('warn_for_unsaved_changes', $settings) && $settings['warn_for_unsaved_changes']) {
            $checked = ' checked="checked"';
            $reset = '<span class="tooltip_restore" restore_aria_label="Restore default value"><i class="bi bi-arrow-repeat refresh_list reset_default_value_checkbox"></i></span>';
        }
        return '<tr class="general_setting"><td><label class="form-check-label" for="warn_for_unsaved_changes">'.
            $this->trans('Warn for unsaved changes').'</label></td>'.
            '<td><input type="checkbox" '.$checked.' id="warn_for_unsaved_changes" name="warn_for_unsaved_changes" class="form-check-input" value="1" data-default-value="false" />'.$reset.'</td></tr>';
    }
}

class Hm_Output_server_config_stepper extends Hm_Output_Module {
    protected function output() {
        $accordionTitle = '';
        $configuredText = $this->trans('Configured') .' ';
        $hasEssentialModuleActivated = false;

        $hasImapActivated = in_array('imap', $this->get('router_module_list'), true);
        $hasSmtpActivated = in_array('smtp', $this->get('router_module_list'), true);
        $hasJmapActivated = in_array('jmap', $this->get('router_module_list'), true);

        if($hasImapActivated){
            $imap_servers_count = count(array_filter($this->get('imap_servers', array()), function($v) { return !array_key_exists('type', $v) || $v['type'] == 'imap'; }));
            $accordionTitle .= 'IMAP';
            $configuredText .=  '<span class="imap_server_count"> ' . $imap_servers_count .'</span> IMAP';
            $hasEssentialModuleActivated = true;
        }

        if($hasJmapActivated){

            $jmap_servers_count = count(array_filter($this->get('imap_servers', array()), function($v) { return array_key_exists('type', $v) && $v['type'] == 'jmap'; }));
            if($accordionTitle != ''){
                $accordionTitle .= ' - ';
                $configuredText .= ' / ';
            }
            $accordionTitle .= 'JMAP';
            $configuredText .= '<span class="jmap_server_count">' . $jmap_servers_count .'</span> JMAP';
            $hasEssentialModuleActivated = true;
        }

        if($hasSmtpActivated){
            $smtp_servers_count = count($this->get('smtp_servers', array()));
            if($accordionTitle != ''){
                $accordionTitle .= ' - ';
                $configuredText .= ' / ';
            }
            $accordionTitle .= 'SMTP';
            $configuredText .= '<span class="smtp_server_count">' . $smtp_servers_count .'</span> SMTP';
            $hasEssentialModuleActivated = true;
        }

        $accordionTitle .= ' Servers';

        // When essential module is not activated, we don't display the accordion
        if(!$hasEssentialModuleActivated) return '';

        $serverList = null;

        if(class_exists('Nux_Quick_Services')){
            $serverList = Nux_Quick_Services::option_list(false, $this);
        }

        $hideClass = 'd-none';

        // Don't hide this section if at least one of the essential module is activated
        if($hasImapActivated || $hasSmtpActivated || $hasJmapActivated){
            $hideClass = '';
        }

        $res = '<div class="smtp_imap_server_setup '. $hideClass .'">
                  <div data-target=".server_config_section" class="server_section border-bottom cursor-pointer px-1 py-3 pe-auto">
                      <a href="#" class="pe-auto">
                          <i class="bi bi-envelope-fill me-3"></i>
                          <b> '.$accordionTitle.'</b>
                      </a>
                      <div class="server_count">'.$configuredText.'</div>
                  </div>
             <div class="server_config_section px-4 pt-3 me-0">
                <div class="stepper col-12 col-xl-7 mb-4" id="srv_setup_stepper_stepper">
                    <div class="step-container">
                        <div id="step_config_1" class="step step_config">
                            <div class="step_config-title">
                                <h2>'.$this->trans('Step 1').'</h2>
                                <span>('.$this->trans('Authentication').')</span>
                            </div>
                            <div>
                                <form class=" me-0" method="POST">
                                        <input type="hidden" name="hm_page_key" value="'.$this->html_safe(Hm_Request_Key::generate()).'" />
                                        <input type="hidden" name="srv_setup_stepper_imap_server_id" id="srv_setup_stepper_imap_server_id" />
                                        <input type="hidden" name="srv_setup_stepper_smtp_server_id" id="srv_setup_stepper_smtp_server_id" />
                                        <div class="form-floating mb-3">
                                            <input required type="text" id="srv_setup_stepper_profile_name" name="srv_setup_stepper_profile_name" class="txt_fld form-control" value="" placeholder="'.$this->trans('Name').'">
                                            <label class="" for="srv_setup_stepper_profile_name">'.$this->trans('Name').'</label>
                                            <span id="srv_setup_stepper_profile_name-error" class="invalid-feedback"></span>
                                        </div>
                                        <div class="form-floating mb-3">
                                            <input required type="text" id="srv_setup_stepper_email" name="srv_setup_stepper_email" class="txt_fld form-control warn_on_paste" value="" placeholder="'.$this->trans('Email or Username').'">
                                            <label class="" for="srv_setup_stepper_email">'.$this->trans('Email or Username').'</label>
                                            <span id="srv_setup_stepper_email-error" class="invalid-feedback"></span>
                                        </div>
                                        <div class="form-floating mb-3">
                                            <input required type="password" id="srv_setup_stepper_password" name="srv_setup_stepper_password" class="txt_fld form-control warn_on_paste" value="" placeholder="'.$this->trans('Password').'">
                                            <label class="" for="srv_setup_stepper_password">'.$this->trans('Password').'</label>
                                            <span id="srv_setup_stepper_password-error" class="invalid-feedback"></span>
                                        </div>
                                </form>
                            </div>
                            <div class="step_config-actions mt-4 d-flex justify-content-between">
                                <button class="btn btn-primary px-5" onclick="display_config_step(0);resetQuickSetupForm();">'.$this->trans('Cancel').'</button>
                                <button class="btn btn-primary px-5" id="step_config_action_next" onclick="display_config_step(2)">'.$this->trans('Next').'</button>
                            </div>
                        </div>
                        <div id="step_config_2" class="step step_config">
                            <div class="step_config-title">
                                <h2>'.$this->trans('Step 2').'</h2>
                                <span>('.$this->trans('Mail server configuration').')</span>
                            </div>
                            <div>
                                <form>
                                    <div class="form-floating mb-3">
                                      <select class="form-select" id="srv_setup_stepper_provider" onchange="handleProviderChange(this)" label="'.$this->trans('Provider').'">
                                        <option value="">'.$this->trans('Other').'</option>'.$serverList.'
                                      </select>
                                      <label for="srv_setup_stepper_provider">'.$this->trans('Provider').'</label>
                                    </div>';

        if($hasSmtpActivated && $hasImapActivated) {
            $res .= '
                 <div class="form-check form-switch">
                   <input class="form-check-input" type="checkbox" role="switch" onchange="handleSmtpImapCheckboxChange(this)" id="srv_setup_stepper_is_sender" checked>
                   <label class="form-check-label" for="srv_setup_stepper_is_sender">'.$this->trans('Sender account').'</label>
                 </div>
                 <div class="form-check form-switch">
                   <input class="form-check-input" type="checkbox" role="switch" onchange="handleSmtpImapCheckboxChange(this)" id="srv_setup_stepper_is_receiver" checked>
                   <label class="form-check-label" for="srv_setup_stepper_is_receiver">'.$this->trans('Receiver account').'</label>
                 </div>
                  <span id="srv_setup_stepper_serve_type-error" class="invalid-feedback"></span>
            ';
        }

        $res .= '<div class="step_config-smtp_imap_bloc">';

        return $res;
    }
}

class Hm_Output_server_config_stepper_end_part extends Hm_Output_Module {
    protected function output() {
        $res = '</div>';

        if(in_array('profiles', $this->get('router_module_list'), true)) {
            $res .= '
                <div class="form-check form-switch mt-3" id="srv_setup_stepper_profile_checkbox_bloc">
                    <input class="form-check-input" type="checkbox" role="switch" onchange="handleCreateProfileCheckboxChange(this)" id="srv_setup_stepper_create_profile" checked>
                    <label class="form-check-label" for="srv_setup_stepper_create_profile">'.$this->trans('Create Profile').'</label>
                </div>
                <div class="ms-3" id="srv_setup_stepper_profile_bloc">
                    <div class="form-floating mb-2">
                        <input required type="text" id="srv_setup_stepper_profile_reply_to" name="srv_setup_stepper_profile_reply_to" class="txt_fld form-control" value="" placeholder="'.$this->trans('Reply to').'">
                        <label class="" for="srv_setup_stepper_profile_reply_to">'.$this->trans('Reply to').'</label>
                    </div>
                    <div class="form-floating mb-2">
                        <input required type="text" id="srv_setup_stepper_profile_signature" name="srv_setup_stepper_profile_signature" class="txt_fld form-control" value="" placeholder="'.$this->trans('Signature').'">
                        <label class="" for="srv_setup_stepper_profile_signature">'.$this->trans('Signature').'</label>
                    </div>
                    <div class="form-check" id="srv_setup_stepper_profile_checkbox_bloc">
                        <input class="form-check-input" type="checkbox" role="switch" id="srv_setup_stepper_profile_is_default" checked>
                        <label class="form-check-label" for="srv_setup_stepper_profile_is_default">'.$this->trans('Set this profile default').'</label>
                    </div>
                </div>
            ';
        }

        $res .= '</form>
            </div>
            <div class="step_config-actions mt-4 d-flex justify-content-between">
                <button class="btn btn-danger px-3" onclick="display_config_step(0);resetQuickSetupForm();">'.$this->trans('Cancel').'</button>
                <button class="btn btn-primary px-4" onclick="display_config_step(1)">'.$this->trans('Previous').'</button>
                <button class="btn btn-primary px-3" id="step_config_action_finish" onclick="display_config_step(3)" id="stepper-action-finish">'.$this->trans('Finish').'</button>
            </div>
        </div>
        <div id="step_config_0" class="step_config current_config_step">
            <button class="imap-jmap-smtp-btn btn btn-primary px-4" id="add_new_server_button" onclick="display_config_step(1)"><i class="bi bi-plus-square-fill me-2"></i> '.$this->trans('Add a new server').'</button>
        </div>
    </div>
</div>
<div class="d-block">';

        return $res;
    }
}

class Hm_Output_server_config_stepper_accordion_end_part extends Hm_Output_Module {
    protected function output() {
        return '</div></div></div>';
    }
}

class Hm_Output_privacy_settings extends Hm_Output_Module {
    static $settings = [
        'images_whitelist' => [
            'type' => 'text',
            'label' => 'External images whitelist',
            'description' => 'Cypht automatically prevents untrusted external images from loading in messages. Add senders from whom you want to allow images to load.',
            'separator' => ','
        ]
    ];

    protected function output()
    {
        $res = '<tr><td data-target=".privacy_setting" colspan="2" class="settings_subtitle cursor-pointer border-bottom p-2">'.
            '<i class="bi bi-shield fs-5 me-2"></i>'.
            $this->trans('Privacy').'</td></tr>';
        $userSettings = $this->get('user_settings', array());
        foreach (self::$settings as $key => $setting) {
            $value = $userSettings[$key] ?? '';
            ['type' => $type, 'label' => $label, 'description' => $description] = $setting;
            $res .= "<tr class='privacy_setting'>" .
            "<td><label for='$key'>$label</label></td>" .
            "<td>
                <input type='$type' id='$key' name='$key' value='$value' class='form-control' />
                <div class='setting_description'>$description</div>
            </td>" .
            "</tr>";
        }
        return $res;
    }
}

class Hm_output_combined_message_list extends Hm_Output_Module {
    protected function output() {
        $messageList = [];
        $style = $this->get('news_list_style') || $this->get('is_mobile') ? 'news' : 'email';
        if ($this->get('imap_combined_inbox_data')) {
            $messageList = array_merge($messageList, format_imap_message_list($this->get('imap_combined_inbox_data'), $this, 'combined_inbox', $style));
        }
        if ($this->get('feed_list_data')) {
            $messageList = array_merge($messageList, $this->get('feed_list_data'), Hm_Output_filter_feed_list_data::formatMessageList($this));
        }
        $this->out('formatted_message_list', $messageList);
    }
}
