<?php
/**
 * SieveFilters modules
 * @package modules
 * @subpackage sievefilters
 */

if (!defined('DEBUG_MODE')) { die(); }

require_once VENDOR_PATH.'autoload.php';
use PhpSieveManager\ManageSieve\Client;

/**
 * @subpackage sievefilters/handler
 */
class Hm_Handler_sieve_edit_script extends Hm_Handler_Module {
    public function process() {
        foreach ($this->user_config->get('imap_servers') as $mailbox) {
            if ($mailbox['name'] == $this->request->post['imap_account']) {
                $imap_account = $mailbox;
            }
        }
        $sieve_options = explode(':', $imap_account['sieve_config_host']);
        $client = new \PhpSieveManager\ManageSieve\Client($sieve_options[0], $sieve_options[1]);
        $client->connect($mailbox['sieve_config_username'], $mailbox['sieve_config_password'], false, "", "PLAIN");
        $script = $client->getScript($this->request->post['sieve_script_name']);
        $this->out('script', $script);
    }
}

/**
 * @subpackage sievefilters/output
 */
class Hm_Output_sieve_edit_output extends Hm_Output_Module {
    public function output() {
        $script = $this->get('script', '');
        $this->out('script', $script);
    }
}


/**
 * @subpackage sievefilters/handler
 */
class Hm_Handler_sieve_delete_script extends Hm_Handler_Module {
    public function process() {
        foreach ($this->user_config->get('imap_servers') as $mailbox) {
            if ($mailbox['name'] == $this->request->post['imap_account']) {
                $imap_account = $mailbox;
            }
        }
        $sieve_options = explode(':', $imap_account['sieve_config_host']);
        $client = new \PhpSieveManager\ManageSieve\Client($sieve_options[0], $sieve_options[1]);
        $client->connect($mailbox['sieve_config_username'], $mailbox['sieve_config_password'], false, "", "PLAIN");
        $scripts = $client->listScripts();
        foreach ($scripts as $script) {
            if ($script == $this->request->post['sieve_script_name']) {
                $client->removeScripts($this->request->post['sieve_script_name']);
                $this->out('script_removed', true);
            }
        }
        Hm_Msgs::add('Script removed');
    }
}

/**
 * @subpackage sievefilters/output
 */
class Hm_Output_sieve_delete_output extends Hm_Output_Module {
    public function output() {
        $removed = $this->get('script_removed', false);
        $this->out('script_removed', $removed);
    }
}

/**
 * @subpackage sievefilters/handler
 */
class Hm_Handler_sieve_save_script extends Hm_Handler_Module {
    public function process() {
        $script_name = generate_script_name($this->request->post['sieve_script_name'], $this->request->post['sieve_script_priority']);
        foreach ($this->user_config->get('imap_servers') as $mailbox) {
            if ($mailbox['name'] == $this->request->post['imap_account']) {
                $imap_account = $mailbox;
            }
        }
        $sieve_options = explode(':', $imap_account['sieve_config_host']);
        $client = new \PhpSieveManager\ManageSieve\Client($sieve_options[0], $sieve_options[1]);
        $client->connect($mailbox['sieve_config_username'], $mailbox['sieve_config_password'], false, "", "PLAIN");
        $scripts = $client->listScripts();
        foreach ($scripts as $script) {
            if ($script == $this->request->post['current_editing_script']) {
                $client->removeScripts($this->request->post['current_editing_script']);
            }
        }
        $client->putScript(
            $script_name,
            $this->request->post['script']
        );
    }
}

/**
 * @subpackage sievefilters/output
 */
class Hm_Output_sieve_save_script_output extends Hm_Output_Module {
    public function output() {

    }
}

/**
 * @subpackage sievefilters/handler
 */
class Hm_Handler_settings_load_imap extends Hm_Handler_Module {
    public function process() {
        $this->out('imap_accounts', $this->user_config->get('imap_servers'), array());
    }
}

/**
 * @subpackage sievefilters/output
 */
class Hm_Output_sievefilters_settings_link extends Hm_Output_Module {
    protected function output() {
        $res = '<li class="menu_profiles"><a class="unread_link" href="?page=sievefilters">';
        if (!$this->get('hide_folder_icons')) {
            $res .= '<img class="account_icon" src="'.$this->html_safe(Hm_Image_Sources::$book).'" alt="" width="16" height="16" /> ';
        }
        $res .= $this->trans('Filters').'</a></li>';
        if ($this->format == 'HTML5') {
            return $res;
        }
        $this->concat('formatted_folder_list', $res);
    }
}

/**
 * @subpackage sievefilters/output
 */
class Hm_Output_sievefilters_settings_start extends Hm_Output_Module {
    protected function output() {
        $socked_connected = $this->get('socket_connected', false);

        $res = '<div class="sievefilters_settings"><div class="content_title">'.$this->trans('Filters').'</div>';
        $res .= '<script type="text/css" src="'.WEB_ROOT.'modules/pgp/sievefilters/tingle.min.css"></script>';
        $res .= '<script type="text/css" src="'.WEB_ROOT.'modules/pgp/sievefilters/litegraph.css"></script>';
        return $res;
    }
}

/**
 * @subpackage sievefilters/output
 */
class Hm_Output_sievefilters_settings_accounts extends Hm_Output_Module {
    protected function output() {
        $mailboxes = $this->get('imap_accounts', array());
        $res = get_classic_filter_modal_content();
        $res .= get_script_modal_content();
        foreach($mailboxes as $mailbox) {
            if (isset($mailbox['sieve_config_username'])) {
                $num_filters = sizeof(get_mailbox_filters($mailbox));
                $res .= '<div class="sievefilters_accounts_item">';
                $res .= '<div class="sievefilters_accounts_title settings_subtitle">' . $mailbox['name'];
                $res .= '<span class="private_key_count">' . sprintf($this->trans('%s filters'), $num_filters) . '</span></div>';
                $res .= '<div class="sievefilters_accounts pgp_block"><div class="pgp_subblock">';
                $res .= '<button class="add_filter" account="'.$mailbox['name'].'">Add Filter</button> <button  account="'.$mailbox['name'].'" class="add_script">Add Script</button>';
                $res .= '<table class="filter_details"><tbody>';
                $res .= '<tr><th style="width: 80px;">Priority</th><th>Name</th><th>Actions</th></tr>';
                $res .= get_mailbox_filters($mailbox, true);
                $res .= '</tbody></table>';
                $res .= '<div style="height: 40px; margin-bottom: 10px;">
                                <div style="width: 90%;">
                                    <h3 style="margin-bottom: 2px;">If conditions are not met</h3>
                                    <small>Define the actions if conditions are not met. If no actions are provided the next filter will be executed. If there are no other filters to be executed, the email will be delivered as expected.</small>
                                </div>
                            </div>
                            <div style="background-color: #f7f2ef; margin-top: 25px; width: 90%;">
                                <div style="padding: 10px;">
                                    <div style="display: flex; height: 30px;">
                                        <div style="width: 80%;">
                                            <h5 style="margin-top: 0">Actions</h5>
                                        </div>
                                        <div style="flex-grow: 1; text-align: right;">
                                            <button style="margin-right: 10px;" class="filter_modal_add_else_action_btn">Add Action</button>
                                        </div>
                                    </div>
                                    <div style="width: 100%;">
                                        <table class="filter_else_actions_modal_table">
                                        </table>
                                    </div>
                                </div>
                            </div>';
                $res .= '</div></div></div>';
            }
        }
        return $res;
    }
}

if (!hm_exists('get_script_modal_content')) {
    function get_script_modal_content()
    {
        return '<div id="edit_script_modal" style="display: none;">
            <h1 class="script_modal_title"></h1>  
            <hr/>
            <div style="display: flex; height: 70px; margin-bottom: 10px;">
                <div style="width: 100%;">
                    <h3 style="margin-bottom: 2px;">General</h3>
                    <small>Input a name and order for your filter. In filters, the order of execution is important. You can define an order value (or priority value) for your filter. Filters will run from lowest to highest priority value.</small>
                </div>
            </div>
            <div style="margin-bottom: 10px; margin-top: 25px;">
                <b>Filter Name:</b><input class="modal_sieve_script_name" type="text" placeholder="Your filter name" style="margin-left: 10px;" /> 
                <b style="margin-left: 50px;">Priority:</b><input class="modal_sieve_script_priority" type="number" placeholder="0" style="margin-left: 10px;" /> 
            </div>
            <div style="display: flex; height: 70px; margin-bottom: 10px;">
                <div style="width: 100%;">
                    <h3 style="margin-bottom: 2px;">Sieve Script</h3>
                    <small>Paste the Sieve script in the field below. Manually added scripts cannot be edited with the filters interface.</small>
                </div>
            </div>
            <div style="margin-bottom: 10px;">
                <textarea style="width: 100%;" rows="30" class="modal_sieve_script_textarea"></textarea>
            </div>
        </div>';
    }
}


if (!hm_exists('get_classic_filter_modal_content')) {
    function get_classic_filter_modal_content()
    {
            return '<div id="edit_filter_modal" style="display: none;">
            <h1 class="filter_modal_title"></h1>  
            <hr/>
            <div style="display: flex; height: 70px; margin-bottom: 10px;">
                <div style="width: 100%;">
                    <h3 style="margin-bottom: 2px;">General</h3>
                    <small>Input a name and order for your filter. In filters, the order of execution is important. You can define an order value (or priority value) for your filter. Filters will run from lowest to highest priority value.</small>
                </div>
            </div>
            <div style="margin-bottom: 10px; margin-top: 25px;">
                <b>Filter Name:</b><input type="text" placeholder="Your filter name" style="margin-left: 10px;" /> 
                <b style="margin-left: 50px;">Priority:</b><input type="number" placeholder="0" style="margin-left: 10px;" /> 
            </div>
            <div style="display: flex; height: 70px; margin-bottom: 10px;">
                <div style="width: 100%;">
                    <h3 style="margin-bottom: 2px;">Conditions & Actions</h3>
                    <small>Filters must have at least one condition. If no actions are provided the email will be delivered normally.</small>
                </div>
            </div>
            <div style="background-color: #f7f2ef; margin-top: 10px;">
                <div style="padding: 10px;">
                    <div style="display: flex; height: 30px;">
                        <div style="width: 80%;">
                            <h5 style="margin-top: 0">Conditions</h5>
                        </div>
                        <div style="flex-grow: 1; text-align: right;">
                            <button style="margin-right: 10px;" class="sieve_add_condition_modal_button">Add Condition</button>
                        </div>
                    </div>
                    <div style="width: 100%;">
                        <table class="sieve_list_conditions_modal">
                        </table>
                    </div>
                </div>
                <hr/>
                <div style="padding: 10px;">
                    <div style="display: flex; height: 30px;">
                        <div style="width: 80%;">
                            <h5 style="margin-top: 0">Actions</h5>
                        </div>
                        <div style="flex-grow: 1; text-align: right;">
                            <button style="margin-right: 10px;" class="filter_modal_add_action_btn">Add Action</button>
                        </div>
                    </div>
                    <div style="width: 100%;">
                        <table class="filter_actions_modal_table">
                        </table>
                    </div>
                </div>
            </div>
        </div>';
    }
}

if (!hm_exists('get_mailbox_filters')) {
    function get_mailbox_filters($mailbox, $html=false)
    {
        $sieve_options = explode(':', $mailbox['sieve_config_host']);
        $client = new \PhpSieveManager\ManageSieve\Client($sieve_options[0], $sieve_options[1]);
        $client->connect($mailbox['sieve_config_username'], $mailbox['sieve_config_password'], false, "", "PLAIN");
        $scripts = $client->listScripts();

        if ($html == false) {
            return $scripts;
        }

        $script_list = '';
        foreach ($scripts as $script_name) {
            $exp_name = explode('-', $script_name);
            if (end($exp_name) == 'cypht') {
                $base_class = 'script';
            }
            if (end($exp_name) == 'cyphtfilter') {
                $base_class = 'filter';
            }
            $parsed_name = str_replace('_', ' ', $exp_name[0]);
            $script_list .= '
            <tr>
                <td>'. $exp_name[sizeof($exp_name) - 2] .'</td>
                <td>' . str_replace('_', ' ', $exp_name[sizeof($exp_name) - 3]) . '</td>
                <td>
                    <a href="#" script_name_parsed="'.$parsed_name.'"  priority="'.$exp_name[sizeof($exp_name) - 2].'" imap_account="'.$mailbox['name'].'" script_name="'.$script_name.'"  class="edit_'.$base_class.'">
                        <img width="16" height="16" src="' . Hm_Image_Sources::$edit . '" />
                    </a>
                    <a href="#" script_name_parsed="'.$parsed_name.'" priority="'.$exp_name[sizeof($exp_name) - 2].'" imap_account="'.$mailbox['name'].'" style="padding-left: 5px;" script_name="'.$script_name.'" class="delete_'.$base_class.'">
                        <img width="16" height="16" src="' . Hm_Image_Sources::$minus . '" />
                    </a>
                </td>
            </tr>
            ';
        }
        return $script_list;
    }
}

if (!hm_exists('generate_script_name')) {
    function generate_script_name($name, $priority)
    {
        return str_replace(' ', '_', strtolower($name)).'-'.$priority.'-cypht';
    }
}
