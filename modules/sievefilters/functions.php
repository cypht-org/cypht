<?php

if (!defined('DEBUG_MODE')) { die(); }

if (!hm_exists('get_script_modal_content')) {
    function get_script_modal_content()
    {
        return '<div id="edit_script_modal" class="d-none">
            <div class="mb-2">
                <h3 class="mb-1">General</h3>
                <small>Input a name and order for your filter. In filters, the order of execution is important. You can define an order value (or priority value) for your filter. Filters will run from lowest to highest priority value.</small>
            </div>
            <div class="mb-2 mt-4">
                <label for="sieve-script-name" class="form-label fw-bold">Filter Name:</label>
                <input class="modal_sieve_script_name form-control" type="text" placeholder="Your filter name" id="sieve-script-name" />
            </div>
            <div class="mb-2">
                <label for="sieve-script-priority" class="form-label fw-bold">Priority:</label>
                <input class="modal_sieve_script_priority form-control" type="number" placeholder="0" id="sieve-script-priority"" />
            </div>
            <div class="mb-2">
                <h3 class="mb-1">Sieve Script</h3>
                <small>Paste the Sieve script in the field below. Manually added scripts cannot be edited with the filters interface.</small>
            </div>
            <div class="mb-2 mt-4">
                <textarea rows="20" class="modal_sieve_script_textarea form-control"></textarea>
            </div>
        </div>';
    }
}


if (!hm_exists('get_classic_filter_modal_content')) {
    function get_classic_filter_modal_content()
    {
        return '<div id="edit_filter_modal" class="d-none">
            <div class="mb-2">
                <h3 class="mb-1">General</h3>
                <small>Input a name and order for your filter. In filters, the order of execution is important. You can define an order value (or priority value) for your filter. Filters will run from lowest to highest priority value.</small>
            </div>
            <div class="mb-2 mt-4">
                <label for="sieve-filter-name" class="form-label fw-bold">Filter Name:</label>
                <input type="text" class="modal_sieve_filter_name form-control" placeholder="Your filter name" id="sieve-filter-name" />
            </div>
            <div class="mb-2">
                <label for="sieve-filter-priority" class="form-label fw-bold">Priority:</label>
                <input class="modal_sieve_filter_priority form-control" type="number" placeholder="0" id="sieve-filter-priority" />
            </div>
            <div class="mb-2">
                <label for="sieve-filter-test" class="form-label fw-bold">Test:</label>
                <select class="modal_sieve_filter_test form-control" name="test_type" placeholder="0" id="sieve-filter-test">
                    <option value="ANYOF">ANYOF (OR)</option>
                    <option value="ALLOF" selected>ALLOF (AND)</option>
                </select>
            </div>
            <div class="d-block mb-2 mt-4">
                <h3 class="mb-1">Conditions & Actions</h3>
                <small>Filters must have at least one action and one condition</small>
            </div>
            <div class="mt-2 rounded card">
                <div class="p-3">
                    <div class="d-flex">
                        <div class="col-sm-10">
                            <h5 class="mt-0">Conditions</h5>
                        </div>
                        <div class="flex-grow-1 text-end">
                            <button class="sieve_add_condition_modal_button btn btn-sm border btn-primary">Add Condition</button>
                        </div>
                    </div>
                    <div class="d-block mt-3 table-responsive">
                        <table class="sieve_list_conditions_modal table">
                        </table>
                    </div>
                </div>
                <hr/>
                <div class="p-3">
                    <div class="d-flex">
                        <div class="col-sm-10">
                            <h5 class="mt-0">Actions</h5>
                        </div>
                        <div class="flex-grow-1 text-end">
                            <button class="filter_modal_add_action_btn btn btn-sm border btn-primary">Add Action</button>
                        </div>
                    </div>
                    <div class="d-block mt-3 table-responsive">
                        <table class="filter_actions_modal_table table">
                        </table>
                    </div>
                </div>
                <hr/>
                <div class="p-3">
                    <div class="d-flex">
                        <div class="col-sm-10">
                            <input type="checkbox" id="stop_filtering"/>
                            <label for="stop_filtering" class="form-label">Stop filtering</label>
                        </div>
                    </div>
                </div>
            </div>
        </div>';
    }
}

if (!hm_exists('get_mailbox_filters')) {
    function get_mailbox_filters($mailbox, $site_config, $user_config)
    {
        $factory = get_sieve_client_factory($site_config);
        try {
            $client = $factory->init($user_config, $mailbox, in_array(mb_strtolower('nux'), $site_config->get_modules(true), true));
            $scripts = [];
            foreach ($client->listScripts() as $script) {
                if (mb_strstr($script, 'cypht')) {
                    $scripts[] = $script;
                }
            }
        } catch (Exception $e) {
            Hm_Msgs::add("Sieve: {$e->getMessage()}", "danger");
            return ['count' => 0, 'list' => ''];
        }

        $scripts_sorted = [];
        foreach ($scripts as $script_name) {
            $exp_name = explode('-', $script_name);
            if (end($exp_name) == 'cypht') {
                $base_class = 'script';
            }
            else if (end($exp_name) == 'cyphtfilter') {
                $base_class = 'filter';
            }
            else {
                continue;
            }
            $parsed_name = str_replace('_', ' ', $exp_name[0]);
            $scripts_sorted[$script_name] = $exp_name[sizeof($exp_name) - 2];
        }
        asort($scripts_sorted);

        $script_list = '';
        foreach ($scripts_sorted as $script_name => $sc) {
            $exp_name = explode('-', $script_name);
            $parsed_name = str_replace('_', ' ', $exp_name[0]);
            $display_name = str_replace('_', ' ', implode('-', array_slice($exp_name, 0, count($exp_name) - 2)));
            $checked = ' checked';
            if (preg_match('/^s(en|dis)abled/', $display_name)) {
                $display_name = str_replace(['senabled ', 'sdisabled '], '', $display_name);
                if (strpos($display_name, 'sdisabled') === 0) {
                    $checked = '';
                }
            }
            $script_list .= '
            <tr>
                <td>'. $exp_name[sizeof($exp_name) - 2] .'</td>
                <td>' . str_replace('_', ' ', implode('-', array_slice($exp_name, 0, count($exp_name) - 2))) . '</td>
                <td>
                    <span class="form-switch">
                        <input script_name_parsed="'.$parsed_name.'" priority="'.$exp_name[sizeof($exp_name) - 2].'" imap_account="'.$mailbox['name'].'" script_name="'.$script_name.'" class="toggle_filter form-check-input" type="checkbox" role="switch" id="Check" name="script_state"'.$checked.'>
                    </span>
                    <a href="#" script_name_parsed="'.$parsed_name.'"  priority="'.$exp_name[sizeof($exp_name) - 2].'" imap_account="'.$mailbox['name'].'" script_name="'.$script_name.'"  class="edit_'.$base_class.'">
                        <i class="bi bi-pencil-fill"></i>
                    </a>
                    <a href="#" script_name_parsed="'.$parsed_name.'" priority="'.$exp_name[sizeof($exp_name) - 2].'" imap_account="'.$mailbox['name'].'" script_name="'.$script_name.'" class="delete_'.$base_class.' ps-2">
                        <i class="bi bi-trash3 text-danger"></i>
                    </a>
                </td>
            </tr>
            ';
        }
        return ['count' => count($scripts), 'list' => $script_list];
    }
}

if (!hm_exists('generate_main_script')) {
    function generate_main_script($script_list)
    {
        $sorted_list = [];
        foreach ($script_list as $script_name) {
            if ($script_name == 'main_script') {
                continue;
            }

            if (mb_strstr($script_name, 'cypht')) {
                $ex_name = explode('-', $script_name);
                $sorted_list[$script_name] = $ex_name[1];
            }
        }
        asort($sorted_list);
        $include_header = 'require ["include"];'."\n\n";
        $include_body = '';

        // Block List MUST be the first script executed
        $include_body .= 'include :personal "blocked_senders";'."\n";

        foreach ($sorted_list as $script_name => $include_script) {
            $include_body .= 'include :personal "'.$script_name.'";'."\n";
        }
        return $include_header.$include_body;
    }
}

if (!hm_exists('save_main_script')) {
    function save_main_script($client, $main_script, $scripts)
    {
        $success = $client->putScript(
            'main_script',
            $main_script
        );
        if (! $success && mb_strpos($client->getErrorMessage(), 'failed to include') !== false) {
            $main_script = '';
            foreach ($scripts as $scriptName) {
                if ($scriptName == 'main_script') {
                    $client->removeScripts('main_script');
                    continue;
                }
                $script = $client->getScript($scriptName);
                if (mb_strpos($script, 'failed to include') !== false) {
                    $script = mb_substr($script, mb_strpos($script, '#'));
                    $client->putScript(
                        $scriptName,
                        $script
                    );
                }
                $main_script .= $script . "\n";
            }
            $main_script = format_main_script($main_script);
            $ret = $client->putScript(
                'main_script',
                $main_script
            );
            if (! $ret) {
                throw new Exception($client->getErrorMessage());
            }
        }
    }
}

if (!hm_exists('format_main_script')) {
    function format_main_script($script)
    {
        // We need to remove require statements found in middle of script
        $lines = explode("\n", $script);
        $reqs = [];
        foreach ($lines as $key => $line) {
            if (preg_match('/^require (\[.+\]);$/', $line, $matches)) {
                unset($lines[$key]);
                $reqs = array_merge($reqs, json_decode($matches[1]));
            } else if (preg_match('/^#/', $line)) {
                unset($lines[$key]);
            }
        }
        $reqs = array_unique($reqs);
        $reqs = array_map(function($req) {
            return '"' . $req . '"';
        }, $reqs);

        $script = 'require [' . implode(',', $reqs) . '];' . "\n";
        $script .= implode("\n", $lines);
        
        return $script;
    }
}

if (!hm_exists('generate_script_name')) {
    function generate_script_name($name, $priority)
    {
        return str_replace(' ', '_', mb_strtolower($name)).'-'.$priority.'-cypht';
    }
}

if (!hm_exists('generate_filter_name')) {
    function generate_filter_name($name, $priority)
    {
        return str_replace(' ', '_', mb_strtolower($name)).'-'.$priority.'-cyphtfilter';
    }
}

if (!hm_exists('get_sieve_client_factory')) {
    function get_sieve_client_factory($site_config)
    {
        if (!is_null($site_config) && isset($site_config) && $factory_class = $site_config->get('sieve_client_factory')) {
            return new $factory_class;
        } else {
            return new Hm_Sieve_Client_Factory;
        }
    }
}

if (!hm_exists('prepare_sieve_script ')) {
    function prepare_sieve_script ($script, $index = 1, $action = "decode")
    {
        $blocked_list = [];
        if ($script != '') {
            $base64_obj = str_replace("# ", "", preg_split('#\r?\n#', $script, 0)[$index]);
            if ($action == "decode") {
                $blocked_list = json_decode(str_replace("*", "", base64_decode($base64_obj)));
            } else {
                $blocked_list = json_encode(base64_decode($base64_obj));
            }
        }
        return $blocked_list;
    }
}

if (!hm_exists('get_domain')) {
    function get_domain($email)
    {
        $domain = explode('@', $email)[1];
        if (preg_match('/(?P<domain>[a-z0-9][a-z0-9\-]{1,63}\.[a-z\.]{2,6})$/i', $domain, $regs)) {
            return $regs['domain'];
        }
        return false;
    }
}

if (!hm_exists('default_reject_message')) {
    function default_reject_message($user_config, $imap_server_id)
    {
        $reject_message = '';
        if ($user_config->get('sieve_block_default_reject_message')) {
            if (array_key_exists($imap_server_id, $user_config->get('sieve_block_default_reject_message'))) {
                $reject_message = $user_config->get('sieve_block_default_reject_message')[$imap_server_id];
            }
        }
        return $reject_message;
    }
}

if (!hm_exists('block_filter')) {
    function block_filter($filter, $user_config, $action, $imap_server_id, $sender, $custom_reject_message = '')
    {
        $ret = ['action' => $action];

        if (explode('@', $sender)[0] == '*') {
            $filter->addRequirement('regex');
        }
        $custom_condition = new \PhpSieveManager\Filters\Condition(
            "", 'anyof'
        );
        $cond = \PhpSieveManager\Filters\FilterCriteria::if('header');
        $cond->contains('"From" ["'.$sender.'"]');

        if ($action == 'default') {
            $default_behaviour = 'Discard';
            if ($user_config->get('sieve_block_default_behaviour')) {
                if (array_key_exists($imap_server_id, $user_config->get('sieve_block_default_behaviour'))) {
                    $default_behaviour = $user_config->get('sieve_block_default_behaviour')[$imap_server_id];
                    if ($default_behaviour == 'Reject') {
                        $reject_message = default_reject_message($user_config, $imap_server_id);
                    }
                }
            }
        } elseif ($action == 'discard') {
            $default_behaviour = 'Discard';
        } elseif ($action == 'reject_default') {
            $default_behaviour = 'Reject';
            $reject_message = default_reject_message($user_config, $imap_server_id);
            $ret['reject_message'] = $reject_message;
        } elseif ($action == 'reject_with_message') {
            $default_behaviour = 'Reject';
            $reject_message = $custom_reject_message;
            $ret['reject_message'] = $custom_reject_message;
        } elseif ($action == 'blocked') {
            $default_behaviour = 'Move';
        }

        $custom_condition->addCriteria($cond);

        if ($default_behaviour == 'Discard') {
            $custom_condition->addAction(
                new \PhpSieveManager\Filters\Actions\DiscardFilterAction()
            );
        }
        elseif ($default_behaviour == 'Reject') {
            $filter->addRequirement('reject');
            $custom_condition->addAction(
                new \PhpSieveManager\Filters\Actions\RejectFilterAction([$reject_message])
            );
        }
        elseif ($default_behaviour == 'Move') {
            $filter->addRequirement('fileinto');
            $custom_condition->addAction(
                new \PhpSieveManager\Filters\Actions\FileIntoFilterAction(['Blocked'])
            );
        }

        $custom_condition->addAction(
            new \PhpSieveManager\Filters\Actions\StopFilterAction()
        );

        $filter->setCondition($custom_condition);

        return $ret;
    }
}

if (!hm_exists('block_filter_dropdown')) {
    function block_filter_dropdown ($mod, $mailbox_id = null, $with_scope = true, $submit_id = 'block_sender', $submit_title = 'Block', $increment = "") {
        $ret = '<div class="dropdown-menu p-3" id="dropdownMenuBlockSender' .$increment. '">'
            .'<form id="block_sender_form' .$increment. '" >';
        if ($with_scope) {
            $ret .= '<div class="mb-2">'
                .   '<label for="blockSenderScope" class="form-label">'.$mod->trans('Who Is Blocked').'</label>'
                .   '<select name="scope" class="form-select form-select-sm" id="blockSenderScope">'
                .   '<option value="sender">'.$mod->trans('This Sender').'</option>'
                .   '<option value="domain">'.$mod->trans('Whole domain').'</option></select>'
                .'</div>';
        }
        $ret .= '<div class="mb-2">'
            .   '<label for="block_action" class="form-label">'.$mod->trans('Action').'</label>'
            .   '<select class="form-select form-select-sm block_action" name="block_action" id="block_action' .$increment. '">'
            .       '<option value="default">'.$mod->trans('Default action').'</option>'
            .       '<option value="discard">'.$mod->trans('Discard').'</option>'
            .       '<option value="blocked">'.$mod->trans('Move To Blocked Folder').'</option>'
            .       '<option value="reject_default">'.$mod->trans('Reject With Default Message').'</option>'
            .       '<option value="reject_with_message">'.$mod->trans('Reject With Specific Message').'</option>'
            .   '</select>'
            .'</div>'
            .'<div class="d-grid gap-1">'
            .   '<button class="btn btn-danger btn-sm mt-2 '.$submit_id.'" type="submit" id="'.$submit_id.$increment. '" mailbox_id="'.$mailbox_id.'">'
            .       $mod->trans($submit_title)
            .   '</button>'
            .'</div>'
            .'</form>'
            .'</div>';
        return $ret;
    }
}

if (!hm_exists('get_blocked_senders_array')) {
    function get_blocked_senders_array($mailbox, $site_config, $user_config)
    {
        $factory = get_sieve_client_factory($site_config);
        try {
            $client = $factory->init($user_config, $mailbox, in_array(mb_strtolower('nux'), $site_config->get_modules(true), true));
            $scripts = $client->listScripts();

            if (array_search('blocked_senders', $scripts, true) === false) {
                return [];
            }

            $blocked_senders = [];
            $current_script = $client->getScript('blocked_senders');
            if ($current_script != '') {
                $blocked_list = prepare_sieve_script ($current_script);
                if (!$blocked_list) {
                    return [];
                }
                foreach ($blocked_list as $blocked_sender) {
                    if ($blocked_sender) {
                        if (explode('@', $blocked_sender)[0] == '') {
                            $blocked_sender = '*' . $blocked_sender;
                        }
                    }
                    $blocked_senders[] = $blocked_sender;
                }
            }
            return $blocked_senders;
        } catch (Exception $e) {
            Hm_Msgs::add("Sieve: {$e->getMessage()}", "danger");
            return [];
        }
    }
}

if (!hm_exists('get_blocked_senders')){
    function get_blocked_senders($mailbox, $mailbox_id, $icon_svg, $icon_block_domain_svg, $site_config, $user_config, $module) {
        $factory = get_sieve_client_factory($site_config);
        try {
            $client = $factory->init($user_config, $mailbox, in_array(mb_strtolower('nux'), $site_config->get_modules(true), true));
            $scripts = $client->listScripts();
            if (array_search('blocked_senders', $scripts, true) === false) {
                return '';
            }
            $current_script = $client->getScript('blocked_senders');
            $blocked_list_actions = [];
            $blocked_senders = [];
            if ($current_script != '') {
                $script_split = preg_split('#\r?\n#', $current_script, 0);
                if (!isset($script_split[1])) {
                    return '';
                }
                $base64_obj = str_replace("# ", "", $script_split[1]);
                $blocked_list = json_decode(base64_decode($base64_obj));
                if (!$blocked_list) {
                    return '';
                }
                if (isset($script_split[2])) {
                    $base64_obj_actions = str_replace("# ", "", $script_split[2]);
                    $blocked_list_actions = json_decode(base64_decode($base64_obj_actions), true);
                }
                foreach ($blocked_list as $blocked_sender) {
                    if (explode('@', $blocked_sender)[0] == '') {
                        $blocked_sender = '*'.$blocked_sender;
                    }
                    $blocked_senders[] = $blocked_sender;
                }
            }

            $actions_map = [
                'blocked' => $module->trans('Move To Blocked'),
                'reject_with_message' => $module->trans('Reject With Message'),
                'reject_default' => $module->trans('Reject'),
                'discard' => $module->trans('Discard'),
                'default' => $module->trans('Default'),
            ];
            $ret = '';
            foreach ($blocked_senders as $k => $sender) {
                $reject_message = $blocked_list_actions[$sender]['reject_message'];
                $ret .= '<tr><td>'.$sender.'</td><td>';
                if (is_array($blocked_list_actions) && array_key_exists($sender, $blocked_list_actions)) {
                    $action = $blocked_list_actions[$sender]['action'] ?: 'default';
                    $ret .= $actions_map[$action];
                    if ($action == 'reject_with_message') {
                        $ret .= ' - '.$reject_message;
                    }
                } else {
                    $action = 'default';
                    $ret .= 'Default';
                }
                $ret .= '<a href="#" mailbox_id="'.$mailbox_id.'" data-action="'.$action.'" data-reject-message="'.$reject_message.'" title="'.$module->trans('Change Behavior').'" class="block_sender_link toggle-behavior-dropdown" aria-labelledby="dropdownMenuBlockSender'.$k.'" data-bs-toggle="dropdown" aria-expanded="false"> <i class="bi bi-pencil-fill ms-3"></i></a>';
                $ret .= block_filter_dropdown($module, $mailbox_id, false, 'edit_blocked_behavior', 'Edit', $k);

                $ret .= '</td><td><i class="bi bi-'.$icon_svg.' unblock_button" mailbox_id="'.$mailbox_id.'"></i>';
                if (!mb_strstr($sender, '*')) {
                    $ret .= ' <i class="bi bi-'.$icon_block_domain_svg.' block_domain_button" mailbox_id="'.$mailbox_id.'"></i>';
                }
                $ret .= '</td></tr>';
            }
            return $ret;
        } catch (Exception $e) {
            Hm_Msgs::add("Sieve: {$e->getMessage()}", "danger");
            return '';
        }
    }
}

if (!hm_exists('initialize_sieve_client_factory')) {
    function initialize_sieve_client_factory($site_config, $user_config, $imapServer) {
        $factory = get_sieve_client_factory($site_config);
        return $factory->init($user_config, $imapServer, in_array(mb_strtolower('nux'), $site_config->get_modules(true), true));
    }
}

if (!hm_exists('get_sieve_host_from_services')) {
    require_once APP_PATH.'modules/nux/modules.php';
    function get_sieve_host_from_services($imap_host) {
        $services = Nux_Quick_Services::get();
        foreach ($services as $service) {
            if (isset($service['server']) && $service['server'] === $imap_host && isset($service['sieve'])) {
                return [
                    'host' => $service['sieve']['host'],
                    'port' => $service['sieve']['port'] ?? 4190,
                ];
            }
        }
        return null;
    }
}
