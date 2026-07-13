<?php
/**
 * SieveFilters modules
 * @package modules
 * @subpackage sievefilters
 */

if (!defined('DEBUG_MODE')) { die(); }

use PhpSieveManager\ManageSieve\Client;
use PhpSieveManager\Exceptions\SocketException;

require_once APP_PATH.'modules/imap/functions.php';
require_once APP_PATH.'modules/imap/hm-imap.php';
require_once APP_PATH.'modules/smtp/hm-smtp.php';
require_once APP_PATH.'modules/smtp/hm-mime-message.php';
require_once APP_PATH.'modules/sievefilters/hm-sieve.php';
require_once APP_PATH.'modules/sievefilters/functions.php';

/**
 * @subpackage sievefilters/handler
 */
class Hm_Handler_sieve_edit_filter extends Hm_Handler_Module {
    public function process() {
        if ($this->should_skip_execution('enable_sieve_filter_setting', DEFAULT_ENABLE_SIEVE_FILTER)) return;

        foreach ($this->user_config->get('imap_servers') as $mailbox) {
            if ($mailbox['name'] == $this->request->post['imap_account']) {
                $imap_account = $mailbox;
            }
        }

        $factory = get_sieve_client_factory($this->config);
        try {
            $client = $factory->init($this->user_config, $imap_account, $this->module_is_supported('nux'));
            $script = $client->getScript($this->request->post['sieve_script_name']);
            $list = prepare_sieve_script ($script, 1, "encode");
            $this->out('conditions', $list);
            $list = prepare_sieve_script ($script, 2, "encode");
            $this->out('actions', $list);
            if (mb_strstr($script, 'allof')) {
                $this->out('test_type', 'ALLOF');
            } else {
                $this->out('test_type', 'ANYOF');
            }
            $client->close();
        } catch (Exception $e) {
            Hm_Msgs::add("Sieve: {$e->getMessage()}", "danger");
            return;
        }
    }
}

/**
 * @subpackage sievefilters/handler
 */
class Hm_Handler_sieve_filters_enabled extends Hm_Handler_Module {
    public function process() {
        $this->out('sieve_filters_enabled', $this->user_config->get('enable_sieve_filter_setting', DEFAULT_ENABLE_SIEVE_FILTER));
    }
}

/**
 * @subpackage sievefilters/handler
 */
class Hm_Handler_load_custom_actions extends Hm_Handler_Module {
    public function process() {
        $imap_account = '';
        if (!empty($this->request->post['imap_account'])) {
            $imap_account = trim($this->request->post['imap_account']);
        } elseif (!empty($this->request->get['imap_account'])) {
            $imap_account = trim($this->request->get['imap_account']);
        } elseif (!empty($this->get('mailbox_name'))) {
            $imap_account = trim($this->get('mailbox_name'));
        } elseif (!empty($this->request->get['list_path'])) {
            $path = $this->request->get['list_path'];
            if (preg_match('/^imap_(\w+)_(.+)$/', $path, $matches)) {
                $imap_server_id = $matches[1];
                $imap_servers = $this->user_config->get('imap_servers');
                if (!empty($imap_servers[$imap_server_id]['name'])) {
                    $imap_account = trim($imap_servers[$imap_server_id]['name']);
                }
            }
        }

        if ($imap_account === '') {
            $this->out('custom_action_error', 'Missing account');
            return;
        }

        $custom_actions = $this->user_config->get('custom_actions', []);
        $account_actions = [];
        if (!empty($custom_actions['by_account'][$imap_account])) {
            $account_actions = $custom_actions['by_account'][$imap_account];
        }

        $this->out('custom_actions', $account_actions);
    }
}

/**
 * @subpackage sievefilters/handler
 */
class Hm_Handler_sieve_filters_enabled_message_content extends Hm_Handler_Module {
    public function process() {
        $server = $this->user_config->get('imap_servers')[$this->request->post['imap_server_id']];
        $sieve_filters_enabled = $this->user_config->get('enable_sieve_filter_setting', DEFAULT_ENABLE_SIEVE_FILTER);
        if ($sieve_filters_enabled && !empty($server['sieve_config_host'])) {
            $factory = get_sieve_client_factory($this->config);
            try {
                $client = $factory->init($this->user_config, $server, $this->module_is_supported('nux'));
                $sieve_filters_enabled = true;
                $this->out('sieve_filters_client', $client);
            } catch (Exception $e) {
                Hm_Msgs::add("Sieve: {$e->getMessage()}", "danger");
            }
        }
        $this->out('sieve_filters_enabled', $sieve_filters_enabled);
    }
}

/**
 * @subpackage sievefilters/output
 */
class Hm_Output_sieve_edit_filter extends Hm_Output_Module {
    public function output() {
        $conditions = $this->get('conditions', '');
        $this->out('conditions', $conditions);
        $actions = $this->get('actions', '');
        $this->out('actions', $actions);
        $actions = $this->get('test_type', '');
        $this->out('test_type', $actions);
    }
}

/**
 * @subpackage sievefilters/handler
 */
class Hm_Handler_sieve_edit_script extends Hm_Handler_Module {
    public function process() {
        if ($this->should_skip_execution('enable_sieve_filter_setting', DEFAULT_ENABLE_SIEVE_FILTER)) return;

        foreach ($this->user_config->get('imap_servers') as $mailbox) {
            if ($mailbox['name'] == $this->request->post['imap_account']) {
                $imap_account = $mailbox;
            }
        }
        $factory = get_sieve_client_factory($this->config);
        try {
            $client = $factory->init($this->user_config, $imap_account, $this->module_is_supported('nux'));
            $script = $client->getScript($this->request->post['sieve_script_name']);
            $client->close();
            $this->out('script', $script);
        } catch (Exception $e) {
            Hm_Msgs::add("Sieve: {$e->getMessage()}", "danger");
        }
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
class Hm_Handler_sieve_delete_filter extends Hm_Handler_Module {
    public function process() {
        if ($this->should_skip_execution('enable_sieve_filter_setting', DEFAULT_ENABLE_SIEVE_FILTER)) return;

        foreach ($this->user_config->get('imap_servers') as $mailbox) {
            if ($mailbox['name'] == $this->request->post['imap_account']) {
                $imap_account = $mailbox;
            }
        }
        $factory = get_sieve_client_factory($this->config);
        try {
            $client = $factory->init($this->user_config, $imap_account, $this->module_is_supported('nux'));

            $scripts = $client->listScripts();
            foreach ($scripts as $script) {
                if ($script == 'main_script') {
                    $client->removeScripts('main_script');
                }
                if ($script == $this->request->post['sieve_script_name']) {
                    $client->removeScripts($this->request->post['sieve_script_name']);
                    $this->out('script_removed', true);
                }
            }
            $scripts = $client->listScripts();
            $main_script = generate_main_script($scripts);
            save_main_script($client, $main_script, $scripts);
            $client->activateScript('main_script');
            $client->close();
            Hm_Msgs::add('Script removed');
        } catch (Exception $e) {
            Hm_Msgs::add("Sieve: {$e->getMessage()}", "danger");
        }
    }
}


/**
 * @subpackage sievefilters/handler
 */
class Hm_Handler_sieve_delete_script extends Hm_Handler_Module {
    public function process() {
        if ($this->should_skip_execution('enable_sieve_filter_setting', DEFAULT_ENABLE_SIEVE_FILTER)) return;

        foreach ($this->user_config->get('imap_servers') as $mailbox) {
            if ($mailbox['name'] == $this->request->post['imap_account']) {
                $imap_account = $mailbox;
            }
        }
        $factory = get_sieve_client_factory($this->config);
        try {
            $client = $factory->init($this->user_config, $imap_account, $this->module_is_supported('nux'));

            $scripts = $client->listScripts();
            foreach ($scripts as $script) {
                if ($script == 'main_script') {
                    $client->removeScripts('main_script');
                }
                if ($script == $this->request->post['sieve_script_name']) {
                    $client->removeScripts($this->request->post['sieve_script_name']);
                    $this->out('script_removed', true);
                }
            }
            $scripts = $client->listScripts();
            $main_script = generate_main_script($scripts);

            save_main_script($client, $main_script, $scripts);
            $client->activateScript('main_script');
            $client->close();
            Hm_Msgs::add('Script removed');
        } catch (Exception $e) {
            Hm_Msgs::add("Sieve: {$e->getMessage()}", "danger");
        }
    }
}

/**
 * @subpackage sievefilters/handler
 */
class Hm_Handler_sieve_block_domain_script extends Hm_Handler_Module {
    public function process() {
        if ($this->should_skip_execution('enable_sieve_filter_setting', DEFAULT_ENABLE_SIEVE_FILTER)) return;

        $imap_account = null;
        foreach ($this->user_config->get('imap_servers') as $idx => $mailbox) {
            if ($idx == $this->request->post['imap_server_id']) {
                $imap_account = $mailbox;
                break;
            }
        }

        $email_sender = $this->request->post['sender'];
        $factory = get_sieve_client_factory($this->config);
        try {
            $client = $factory->init($this->user_config, $imap_account, $this->module_is_supported('nux'));
            $scripts = $client->listScripts();

            $current_script = $client->getScript('blocked_senders');
            $blocked_list = prepare_sieve_script ($current_script);

            $domain = get_domain($this->request->post['sender']);
            $blocked_wildcard = '@'.$domain;
            $new_blocked_list = [];
            foreach ($blocked_list as $idx => $blocked_sender) {
                if (!mb_strstr($blocked_sender, $blocked_wildcard)) {
                    $new_blocked_list[] = $blocked_sender;
                }
            }
            $new_blocked_list[] = $blocked_wildcard;

            if(array_search('blocked_senders', $scripts, true) === false) {
                $client->putScript(
                    'blocked_senders',
                    ''
                );
            }

            // Create Block List Filter
            $filter = \PhpSieveManager\Filters\FilterFactory::create('blocked_senders');
            $custom_condition = new \PhpSieveManager\Filters\Condition(
                "CYPHT GENERATED CONDITION", 'anyof'
            );
            foreach ($new_blocked_list as $blocked_sender) {
                $cond = \PhpSieveManager\Filters\FilterCriteria::if('header');
                $cond->contains('"From" ["'.$blocked_sender.'"]');
                $custom_condition->addCriteria($cond);
            }
            $custom_condition->addAction(
                new \PhpSieveManager\Filters\Actions\DiscardFilterAction()
            );
            $custom_condition->addAction(
                new \PhpSieveManager\Filters\Actions\StopFilterAction()
            );
            $filter->setCondition($custom_condition);
            $script_parsed = $filter->toScript();

            $main_script = generate_main_script($scripts);

            $header_obj = "# CYPHT CONFIG HEADER - DON'T REMOVE";
            $header_obj .= "\n# ".base64_encode(json_encode($new_blocked_list));
            $script_parsed = $header_obj."\n\n".$script_parsed;
            $client->putScript(
                'blocked_senders',
                $script_parsed
            );
            save_main_script($client, $main_script, $scripts);
            $client->activateScript('main_script');
            $client->close();
            $this->out('reload_page', true);
        } catch (Exception $e) {
            Hm_Msgs::add("Sieve: {$e->getMessage()}", "danger");
            return;
        }
    }
}
class Hm_Output_sieve_block_domain_output extends Hm_Output_Module {
    public function output() {
        $reload_page = $this->get('reload_page', false);
        $this->out('reload_page', $reload_page);
    }
}

/**
 * @subpackage sievefilters/handler
 */
class Hm_Handler_sieve_get_mailboxes_script extends Hm_Handler_Module {
    public function process() {
        if ($this->should_skip_execution('enable_sieve_filter_setting', DEFAULT_ENABLE_SIEVE_FILTER)) return;

        list($success, $form) = $this->process_form(array('imap_account'));
        if (!$success) {
            return;
        }
        $imap_server_id = null;
        foreach ($this->user_config->get('imap_servers') as $idx => $mailbox) {
            if ($mailbox['name'] == $this->request->post['imap_account']) {
                $imap_account = $mailbox;
                $imap_server_id = $idx;
            }
        }
        $mailbox = Hm_IMAP_List::get_connected_mailbox($imap_server_id, $this->cache);
        if (! $mailbox || ! $mailbox->authed()) {
            Hm_Msgs::add('IMAP Authentication Failed', 'danger');
            return;
        }
        $mailboxes = [];
        foreach ($mailbox->get_folders() as $idx => $mailbox) {
            $mailboxes[] = $mailbox['name'];
        }
        $this->out('mailboxes', json_encode($mailboxes));
    }
}


class Hm_Output_sieve_get_mailboxes_output extends Hm_Output_Module {
    public function output() {
        $mailboxes = $this->get('mailboxes', '');
        $this->out('mailboxes', $mailboxes);
    }
}

/**
* @subpackage sievefilters/handler
*/
class Hm_Handler_sieve_unblock_sender extends Hm_Handler_Module {
    public function process() {
        if ($this->should_skip_execution('enable_sieve_filter_setting', DEFAULT_ENABLE_SIEVE_FILTER)) return;

        list($success, $form) = $this->process_form(array('imap_server_id', 'sender'));

        if (!$success) {
            return;
        }

        foreach ($this->user_config->get('imap_servers') as $idx => $mailbox) {
            if ($idx == $form['imap_server_id']) {
                $imap_account = $mailbox;
            }
        }

        $email_sender = $this->request->post['sender'];
        if (mb_strstr($email_sender, '*')) {
            $email_sender = str_replace('*', '', $email_sender);
        }

        $default_behaviour = 'Discard';
        if ($this->user_config->get('sieve_block_default_behaviour')) {
            if (array_key_exists($form['imap_server_id'], $this->user_config->get('sieve_block_default_behaviour'))) {
                $default_behaviour = $this->user_config->get('sieve_block_default_behaviour')[$form['imap_server_id']];
            }
        }

        $factory = get_sieve_client_factory($this->config);
        try {
            $client = $factory->init($this->user_config, $imap_account, $this->module_is_supported('nux'));
            $scripts = $client->listScripts();

            if(array_search('blocked_senders', $scripts, true) === false) {
                $client->putScript(
                    'blocked_senders',
                    ''
                );
            }

            $blocked_senders = [];
            $current_script = $client->getScript('blocked_senders');
            $unblock_sender = false;
            if ($current_script != '') {
                $blocked_list = prepare_sieve_script ($current_script);
                foreach ($blocked_list as $blocked_sender) {
                    if ($blocked_sender != $email_sender) {
                        $blocked_senders[] = $blocked_sender;
                        continue;
                    }
                    $unblock_sender = true;
                }
            }
            if ($unblock_sender == false || $current_script == '') {
                $blocked_senders[] = $email_sender;
            }

            if (count($blocked_senders) == 0 && $unblock_sender) {
                $client->putScript(
                    'blocked_senders',
                    ''
                );
                Hm_Msgs::add('Sender Unblocked');
                return;
            }

            // Create Block List Filter
            $filter = \PhpSieveManager\Filters\FilterFactory::create('blocked_senders');
            $custom_condition = new \PhpSieveManager\Filters\Condition(
                "CYPHT GENERATED CONDITION", 'anyof'
            );
            foreach ($blocked_senders as $blocked_sender) {
                $cond = \PhpSieveManager\Filters\FilterCriteria::if('header');
                $cond->contains('"From" ["'.$blocked_sender.'"]');
                $custom_condition->addCriteria($cond);
            }

            if ($default_behaviour == 'Discard') {
                $custom_condition->addAction(
                    new \PhpSieveManager\Filters\Actions\DiscardFilterAction()
                );
            }
            elseif ($default_behaviour == 'Reject') {
                $filter->addRequirement('reject');
                $custom_condition->addAction(
                    new \PhpSieveManager\Filters\Actions\RejectFilterAction(['reason' => ''])
                );
            }
            $custom_condition->addAction(
                new \PhpSieveManager\Filters\Actions\StopFilterAction()
            );
            $filter->setCondition($custom_condition);
            $script_parsed = $filter->toScript();

            $main_script = generate_main_script($scripts);

            $header_obj = "# CYPHT CONFIG HEADER - DON'T REMOVE";
            $header_obj .= "\n# ".base64_encode(json_encode($blocked_senders));
            $script_parsed = $header_obj."\n\n".$script_parsed;
            $client->putScript(
                'blocked_senders',
                $script_parsed
            );
            save_main_script($client, $main_script, $scripts);
            $client->activateScript('main_script');
            $client->close();

            if ($unblock_sender) {
                Hm_Msgs::add('Sender Unblocked');
            } else {
                Hm_Msgs::add('Sender Blocked');
            }
        } catch (Exception $e) {
            Hm_Msgs::add("Sieve: {$e->getMessage()}", "danger");
            return;
        }
    }
}

/**
 * @subpackage sievefilters/handler
 */
class Hm_Handler_sieve_block_unblock_script extends Hm_Handler_Module {
    public function process() {
        if ($this->should_skip_execution('enable_sieve_filter_setting', DEFAULT_ENABLE_SIEVE_FILTER)) return;

        list($success, $form) = $this->process_form(array('imap_server_id', 'block_action', 'scope'));
        if (!$success) {
            return;
        }

        foreach ($this->user_config->get('imap_servers') as $idx => $mailbox) {
            if ($idx == $this->request->post['imap_server_id']) {
                $imap_account = $mailbox;
            }
        }
        $array_email_sender = [];
        $email_sender = null;

        if (isset($this->request->post['imap_msg_uid']) && !empty($this->request->post['imap_msg_uid'])) {
            $form['imap_msg_uid'] = $this->request->post['imap_msg_uid'];
            $mailbox = Hm_IMAP_List::get_connected_mailbox($this->request->post['imap_server_id'], $this->cache);
            if (! $mailbox || ! $mailbox->authed()) {
                Hm_Msgs::add('IMAP Authentication Failed', 'danger');
                return;
            }
            $msg_header = $mailbox->get_message_headers(hex2bin($this->request->post['folder']), $form['imap_msg_uid']);
            $test_pattern = "/(?:[a-z0-9!#$%&'*+=?^_`{|}~-]+(?:\.[a-z0-9!#$%&'*+=?^_`{|}~-]+)*|\"(?:[\x01-\x08\x0b\x0c\x0e-\x1f\x21\x23-\x5b\x5d-\x7f]|\\[\x01-\x09\x0b\x0c\x0e-\x7f])*\")@(?:(?:[a-z0-9](?:[a-z0-9-]*[a-z0-9])?\.)+[a-z0-9](?:[a-z0-9-]*[a-z0-9])?|\[(?:(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\.){3}(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?|[a-z0-9-]*[a-z0-9]:(?:[\x01-\x08\x0b\x0c\x0e-\x1f\x21-\x5a\x53-\x7f]|\\[\x01-\x09\x0b\x0c\x0e-\x7f])+)\])/";
            preg_match_all($test_pattern, $msg_header['From'], $email_sender);
            $email_sender = $email_sender[0][0];
        } elseif (!empty($this->request->post['sender'])) {
            $email_sender = $this->request->post['sender'];
            if (isset($this->request->post['is_screened'])) {
                $array_email_sender = explode(",", $email_sender);
                $email_sender = null;
            }
        } else {
            Hm_Msgs::add('Sender not found', 'warning');
            return;
        }

        $scope = 'sender';
        if (isset($this->request->post['scope']) && $this->request->post['scope'] == 'domain') {
            $email_sender = '*@'.get_domain($email_sender);
            $scope = 'domain';
        }
        $scope_title = ucfirst($scope);

        $factory = get_sieve_client_factory($this->config);
        try {
            $client = $factory->init($this->user_config, $imap_account, $this->module_is_supported('nux'));
            $scripts = $client->listScripts();

            if(array_search('blocked_senders', $scripts, true) === false) {
                $client->putScript(
                    'blocked_senders',
                    ''
                );
            }

            $blocked_senders = [];
            $current_script = $client->getScript('blocked_senders');

            $blocked_list_actions = [];
            $unblock_sender = false;
            if ($current_script != '') {
                $blocked_list = prepare_sieve_script ($current_script);
                if ($email_sender) {
                    foreach ($blocked_list as $blocked_sender) {
                        if ($blocked_sender != $email_sender) {
                            $blocked_senders[] = $blocked_sender;
                            continue;
                        }
                        $unblock_sender = true;
                    }
                } else {
                    if ($array_email_sender) {
                        $blocked_senders = array_diff($array_email_sender, $blocked_list);
                    }
                }
                $blocked_list_actions = prepare_sieve_script ($current_script, 2);
            }
            if (isset($this->request->post['change_behavior']) && $unblock_sender) {
                $unblock_sender = false;
            }
            if ($unblock_sender == false || $current_script == '') {
                if ($email_sender) {
                    $blocked_senders[] = $email_sender;
                }

                if ($array_email_sender) {
                    $blocked_senders = $array_email_sender;
                }
                
            }

            $blocked_senders = array_unique($blocked_senders);

            if (count($blocked_senders) == 0 && $unblock_sender) {
                $client->putScript(
                    'blocked_senders',
                    ''
                );
                Hm_Msgs::add($scope_title . ' Unblocked');
                return;
            }

            $filter = \PhpSieveManager\Filters\FilterFactory::create('blocked_senders');
            foreach ($blocked_senders as $blocked_sender) {
                if ($blocked_sender == $email_sender || ($array_email_sender && in_array($blocked_sender, $array_email_sender))) {
                    $actions = block_filter(
                        $filter,
                        $this->user_config,
                        $this->request->post['block_action'],
                        $this->request->post['imap_server_id'],
                        $blocked_sender,
                        $this->request->post['reject_message']
                    );
                } elseif (array_key_exists($blocked_sender, $blocked_list_actions)) {
                    $reject_message = '';
                    if ($blocked_list_actions[$blocked_sender]['action'] == 'reject_with_message') {
                        $reject_message = $blocked_list_actions[$blocked_sender]['reject_message'];
                    }
                    $actions = block_filter(
                        $filter,
                        $this->user_config,
                        $blocked_list_actions[$blocked_sender]['action'],$this->request->post['imap_server_id'],
                        $blocked_sender,
                        $reject_message
                    );
                } else {
                    $actions = block_filter(
                        $filter,
                        $this->user_config,
                        'default',
                        $this->request->post['imap_server_id'],
                        $blocked_sender
                    );
                }
                $blocked_list_actions[$blocked_sender] = $actions;
            }
            $script_parsed = $filter->toScript();

            $main_script = generate_main_script($scripts);

            $header_obj = "# CYPHT CONFIG HEADER - DON'T REMOVE";
            $header_obj .= "\n# ".base64_encode(json_encode($blocked_senders));
            $header_obj .= "\n# ".base64_encode(json_encode($blocked_list_actions));
            $script_parsed = $header_obj."\n\n".$script_parsed;

            $client->putScript(
                'blocked_senders',
                $script_parsed
            );
            save_main_script($client, $main_script, $scripts);
            $client->activateScript('main_script');
            $client->close();

            if (isset($this->request->post['change_behavior'])) {
                Hm_Msgs::add($scope_title . ' Behavior Changed');
            } else {
                if ($unblock_sender) {
                    Hm_Msgs::add($scope_title . ' Unblocked');
                } else {
                    Hm_Msgs::add($scope_title . ' Blocked');
                }
            }
        } catch (Exception $e) {
            Hm_Msgs::add("Sieve: {$e->getMessage()}", "danger");
            return;
        }
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
 * @subpackage sievefilters/output
 */
class Hm_Output_sieve_save_filter_output extends Hm_Output_Module {
    public function output() {
        $script_details = $this->get('script_details', []);
        $this->out('script_details', $script_details);
    }
}

/**
 * @subpackage sievefilters/handler
 */
class Hm_Handler_sieve_save_filter extends Hm_Handler_Module {
    public function process() {
        if ($this->should_skip_execution('enable_sieve_filter_setting', DEFAULT_ENABLE_SIEVE_FILTER)) return;

        $priority =  $this->request->post['sieve_filter_priority'];
        if ($this->request->post['sieve_filter_priority'] == '') {
            $priority = 0;
        }
        foreach ($this->user_config->get('imap_servers') as $mailbox) {
            if ($mailbox['name'] == $this->request->post['imap_account']) {
                $imap_account = $mailbox;
            }
        }
        $script_name = generate_filter_name($this->request->post['sieve_filter_name'], $priority);
        $conditions = json_decode($this->request->post['conditions_json']);
        $actions = json_decode($this->request->post['actions_json']);
        $test_type = mb_strtolower($this->request->post['filter_test_type']);

        $filter = \PhpSieveManager\Filters\FilterFactory::create($script_name);
        $custom_condition = new \PhpSieveManager\Filters\Condition(
            "CYPHT GENERATED CONDITION", $test_type
        );
        foreach ($conditions as $condition) {
            $cond = null;
            if ($condition->condition == 'body') {
                $filter->addRequirement('body');
                $cond = \PhpSieveManager\Filters\FilterCriteria::if('body');
                if ($condition->type == 'Matches') {
                    $cond->matches('"'.$condition->value.'"');
                }
                if ($condition->type == 'Contains') {
                    $cond->contains('"'.$condition->value.'"');
                }
                if ($condition->type == '!Matches') {
                    $cond = \PhpSieveManager\Filters\FilterCriteria::if('not body');
                    $cond->matches('"'.$condition->value.'"');
                }
                if ($condition->type == '!Contains') {
                    $cond = \PhpSieveManager\Filters\FilterCriteria::if('not body');
                    $cond->contains('"'.$condition->value.'"');
                }
                if ($condition->type == 'Regex') {
                    $filter->addRequirement('regex');
                    $cond = \PhpSieveManager\Filters\FilterCriteria::if('body');
                    $cond->regex('"'.$condition->value.'"');
                }
                if ($condition->type == '!Regex') {
                    $filter->addRequirement('regex');
                    $cond = \PhpSieveManager\Filters\FilterCriteria::if('not body');
                    $cond->regex('"'.$condition->value.'"');
                }
            }
            if ($condition->condition == 'subject') {
                $cond = \PhpSieveManager\Filters\FilterCriteria::if('header');
                if ($condition->type == 'Matches') {
                    $cond->matches('"Subject" ["'.$condition->value.'"]');
                }
                if ($condition->type == 'Contains') {
                    $cond->contains('"Subject" ["'.$condition->value.'"]');
                }
                if ($condition->type == '!Matches') {
                    $cond = \PhpSieveManager\Filters\FilterCriteria::if('not header');
                    $cond->matches('"Subject" ["'.$condition->value.'"]');
                }
                if ($condition->type == '!Contains') {
                    $cond = \PhpSieveManager\Filters\FilterCriteria::if('not header');
                    $cond->contains('"Subject" ["'.$condition->value.'"]');
                }
                if ($condition->type == 'Regex') {
                    $filter->addRequirement('regex');
                    $cond = \PhpSieveManager\Filters\FilterCriteria::if('header');
                    $cond->regex('"Subject" "'.$condition->value.'"');
                }
                if ($condition->type == '!Regex') {
                    $filter->addRequirement('regex');
                    $cond = \PhpSieveManager\Filters\FilterCriteria::if('not header');
                    $cond->regex('"Subject" "'.$condition->value.'"');
                }
            }
            if ($condition->condition == 'to') {
                $cond = \PhpSieveManager\Filters\FilterCriteria::if('header');
                if ($condition->type == 'Matches') {
                    $cond->matches('"Delivered-To" ["'.$condition->value.'"]');
                }
                if ($condition->type == 'Contains') {
                    $cond->contains('"Delivered-To" ["'.$condition->value.'"]');
                }
                if ($condition->type == '!Matches') {
                    $cond = \PhpSieveManager\Filters\FilterCriteria::if('not header');
                    $cond->matches('"Delivered-To" ["'.$condition->value.'"]');
                }
                if ($condition->type == '!Contains') {
                    $cond = \PhpSieveManager\Filters\FilterCriteria::if('not header');
                    $cond->contains('"Delivered-To" ["'.$condition->value.'"]');
                }
                if ($condition->type == 'Regex') {
                    $filter->addRequirement('regex');
                    $cond = \PhpSieveManager\Filters\FilterCriteria::if('header');
                    $cond->regex('"Delivered-To" "'.$condition->value.'"');
                }
                if ($condition->type == '!Regex') {
                    $filter->addRequirement('regex');
                    $cond = \PhpSieveManager\Filters\FilterCriteria::if('not header');
                    $cond->regex('"Delivered-To" "'.$condition->value.'"');
                }
            }
            if ($condition->condition == 'from') {
                $cond = \PhpSieveManager\Filters\FilterCriteria::if('header');
                if ($condition->type == 'Matches') {
                    $cond->matches('"From" ["'.$condition->value.'"]');
                }
                if ($condition->type == 'Contains') {
                    $cond->contains('"From" ["'.$condition->value.'"]');
                }
                if ($condition->type == '!Matches') {
                    $cond = \PhpSieveManager\Filters\FilterCriteria::if('not header');
                    $cond->matches('"From" ["'.$condition->value.'"]');
                }
                if ($condition->type == '!Contains') {
                    $cond = \PhpSieveManager\Filters\FilterCriteria::if('not header');
                    $cond->contains('"From" ["'.$condition->value.'"]');
                }
                if ($condition->type == 'Regex') {
                    $filter->addRequirement('regex');
                    $cond = \PhpSieveManager\Filters\FilterCriteria::if('header');
                    $cond->regex('"From" "'.$condition->value.'"');
                }
                if ($condition->type == '!Regex') {
                    $filter->addRequirement('regex');
                    $cond = \PhpSieveManager\Filters\FilterCriteria::if('not header');
                    $cond->regex('"From" "'.$condition->value.'"');
                }
            }
            if ($condition->condition == 'bcc') {
                $cond = \PhpSieveManager\Filters\FilterCriteria::if('header');
                if ($condition->type == 'Matches') {
                    $cond->matches('"Bcc" ["'.$condition->value.'"]');
                }
                if ($condition->type == 'Contains') {
                    $cond->contains('"Bcc" ["'.$condition->value.'"]');
                }
                if ($condition->type == '!Matches') {
                    $cond = \PhpSieveManager\Filters\FilterCriteria::if('not header');
                    $cond->matches('"Bcc" ["'.$condition->value.'"]');
                }
                if ($condition->type == '!Contains') {
                    $cond = \PhpSieveManager\Filters\FilterCriteria::if('not header');
                    $cond->contains('"Bcc" ["'.$condition->value.'"]');
                }
                if ($condition->type == 'Regex') {
                    $filter->addRequirement('regex');
                    $cond = \PhpSieveManager\Filters\FilterCriteria::if('header');
                    $cond->regex('"Bcc" "'.$condition->value.'"');
                }
                if ($condition->type == '!Regex') {
                    $filter->addRequirement('regex');
                    $cond = \PhpSieveManager\Filters\FilterCriteria::if('not header');
                    $cond->regex('"Bcc" "'.$condition->value.'"');
                }
            }
            if ($condition->condition == 'cc') {
                $cond = \PhpSieveManager\Filters\FilterCriteria::if('header');
                if ($condition->type == 'Matches') {
                    $cond->matches('"Cc" ["'.$condition->value.'"]');
                }
                if ($condition->type == 'Contains') {
                    $cond->contains('"Cc" ["'.$condition->value.'"]');
                }
                if ($condition->type == '!Matches') {
                    $cond = \PhpSieveManager\Filters\FilterCriteria::if('not header');
                    $cond->matches('"Cc" ["'.$condition->value.'"]');
                }
                if ($condition->type == '!Contains') {
                    $cond = \PhpSieveManager\Filters\FilterCriteria::if('not header');
                    $cond->contains('"Cc" ["'.$condition->value.'"]');
                }
                if ($condition->type == 'Regex') {
                    $filter->addRequirement('regex');
                    $cond = \PhpSieveManager\Filters\FilterCriteria::if('header');
                    $cond->regex('"Cc" "'.$condition->value.'"');
                }
                if ($condition->type == '!Regex') {
                    $filter->addRequirement('regex');
                    $cond = \PhpSieveManager\Filters\FilterCriteria::if('not header');
                    $cond->regex('"Cc" "'.$condition->value.'"');
                }
            }
            if ($condition->condition == 'to_or_cc') {
                $cond_to = \PhpSieveManager\Filters\FilterCriteria::if('header');
                $cond = \PhpSieveManager\Filters\FilterCriteria::if('header');
                if ($condition->type == 'Matches') {
                    $cond->matches('"Cc" ["'.$condition->value.'"]');
                    $cond_to->matches('"Delivered-To" ["'.$condition->value.'"]');
                }
                if ($condition->type == 'Contains') {
                    $cond->contains('"Cc" ["'.$condition->value.'"]');
                    $cond_to->contains('"Delivered-To" ["'.$condition->value.'"]');
                }
                if ($condition->type == '!Matches') {
                    $cond = \PhpSieveManager\Filters\FilterCriteria::if('not header');
                    $cond_to = \PhpSieveManager\Filters\FilterCriteria::if('not header');
                    $cond->matches('"Cc" ["'.$condition->value.'"]');
                    $cond_to->matches('"Delivered-To" ["'.$condition->value.'"]');
                }
                if ($condition->type == '!Contains') {
                    $cond = \PhpSieveManager\Filters\FilterCriteria::if('not header');
                    $cond_to = \PhpSieveManager\Filters\FilterCriteria::if('not header');
                    $cond->contains('"Cc" ["'.$condition->value.'"]');
                    $cond_to->contains('"Delivered-To" ["'.$condition->value.'"]');
                }
                if ($condition->type == 'Regex') {
                    $filter->addRequirement('regex');
                    $cond = \PhpSieveManager\Filters\FilterCriteria::if('header');
                    $cond_to = \PhpSieveManager\Filters\FilterCriteria::if('header');
                    $cond->regex('"Cc" "'.$condition->value.'"');
                    $cond_to->matches('"Delivered-To" "'.$condition->value.'"');
                }
                if ($condition->type == '!Regex') {
                    $filter->addRequirement('regex');
                    $cond = \PhpSieveManager\Filters\FilterCriteria::if('not header');
                    $cond_to = \PhpSieveManager\Filters\FilterCriteria::if('not header');
                    $cond->regex('"Cc" "'.$condition->value.'"');
                    $cond_to->matches('"Delivered-To" "'.$condition->value.'"');
                }
                $custom_condition->addCriteria($cond_to);
            }
            if ($condition->condition == 'size') {
                $cond = \PhpSieveManager\Filters\FilterCriteria::if('size');
                if ($condition->type == 'Over') {
                    $cond->over($condition->value.'K');
                }
                if ($condition->type == 'Under') {
                    $cond->under($condition->value.'K');
                }
                if ($condition->type == '!Over') {
                    $cond = \PhpSieveManager\Filters\FilterCriteria::if('not size');
                    $cond->over($condition->value.'K');
                }
                if ($condition->type == '!Under') {
                    $cond = \PhpSieveManager\Filters\FilterCriteria::if('not size');
                    $cond->under($condition->value.'K');
                }
            }
            if ($condition->condition == 'custom') {
                $cond = \PhpSieveManager\Filters\FilterCriteria::if('header');
                if ($condition->type == 'Matches') {
                    $cond->matches('"'.$condition->extra_option_value.'" "'.$condition->value.'"');
                }
                if ($condition->type == '!Matches') {
                    $cond = \PhpSieveManager\Filters\FilterCriteria::if('not header');
                    $cond->matches('"'.$condition->extra_option_value.'" "'.$condition->value.'"');
                }
                if ($condition->type == 'Contains') {
                    $cond->contains('"'.$condition->extra_option_value.'" "'.$condition->value.'"');
                }
                if ($condition->type == '!Contains') {
                    $cond = \PhpSieveManager\Filters\FilterCriteria::if('not header');
                    $cond->contains('"'.$condition->extra_option_value.'" "'.$condition->value.'"');
                }
                if ($condition->type == 'Regex') {
                    $cond->regex('"'.$condition->extra_option_value.'" "'.$condition->value.'"');
                }
                if ($condition->type == '!Regex') {
                    $cond = \PhpSieveManager\Filters\FilterCriteria::if('not header');
                    $cond->regex('"'.$condition->extra_option_value.'" "'.$condition->value.'"');
                }
            }
            if ($cond) {
                $custom_condition->addCriteria($cond);
            }
        }
        
        foreach ($actions as $action) {
            if ($action->action == 'discard') {
                $custom_condition->addAction(
                    new \PhpSieveManager\Filters\Actions\DiscardFilterAction()
                );
            }
            if ($action->action == 'keep') {
                $custom_condition->addAction(
                    new \PhpSieveManager\Filters\Actions\KeepFilterAction()
                );
            }
            if ($action->action == 'stop') {
                $custom_condition->addAction(
                    new \PhpSieveManager\Filters\Actions\StopFilterAction()
                );
            }
            if ($action->action == 'redirect') {
                $custom_condition->addAction(
                    new \PhpSieveManager\Filters\Actions\RedirectFilterAction(['address' => $action->value])
                );
            }
            if ($action->action == 'forward') {
                $custom_condition->addAction(
                    new \PhpSieveManager\Filters\Actions\RedirectFilterAction(['address' => $action->value])
                );
                $custom_condition->addAction(
                    new \PhpSieveManager\Filters\Actions\KeepFilterAction()
                );
            }
            if ($action->action == 'flag') {
                $custom_condition->addAction(
                    new \PhpSieveManager\Filters\Actions\FlagFilterAction(['flags' => [$action->value]])
                );
            }
            if ($action->action == 'addflag') {                
                $custom_condition->addAction(
                    new \PhpSieveManager\Filters\Actions\AddFlagFilterAction(['flags' => [$action->value]])
                );
            }
            if ($action->action == 'removeflag') {
                $custom_condition->addAction(
                    new \PhpSieveManager\Filters\Actions\RemoveFlagFilterAction(['flags' => [$action->value]])
                );
            }
            if ($action->action == 'move') {
                $custom_condition->addAction(
                    new \PhpSieveManager\Filters\Actions\FileIntoFilterAction(['mailbox' => $action->value])
                );
            }
            if ($action->action == 'reject') {
                $custom_condition->addAction(
                    new \PhpSieveManager\Filters\Actions\RejectFilterAction(['reason' => $action->value])
                );
            }
            if ($action->action == 'copy') {
                $custom_condition->addAction(
                    new \PhpSieveManager\Filters\Actions\FileIntoFilterAction(['mailbox' => $action->value])
                );
                $custom_condition->addAction(
                    new \PhpSieveManager\Filters\Actions\KeepFilterAction()
                );
            }
            if ($action->action == 'imap_move') {
                $custom_condition->addAction(
                    new \PhpSieveManager\Filters\Actions\FileIntoFilterAction(['mailbox' => $action->value])
                );
            }
            if ($action->action == 'imap_copy') {
                $custom_condition->addAction(
                    new \PhpSieveManager\Filters\Actions\FileIntoFilterAction(['mailbox' => $action->value])
                );
                $custom_condition->addAction(
                    new \PhpSieveManager\Filters\Actions\KeepFilterAction()
                );
            }
            if ($action->action == 'autoreply') {
                $custom_condition->addAction(
                    new \PhpSieveManager\Filters\Actions\VacationFilterAction(['reason' => $action->value, 'subject' => $action->extra_option_value])
                );
            }
        }
        $filter->setCondition($custom_condition);
        $script_parsed = $filter->toScript();

        if ($this->request->post['gen_script']) {
            $this->out('script_details', [
                'gen_script' => $script_parsed,
                'filter_priority' => $priority,
                'filter_name' => $this->request->post['sieve_filter_name']
            ]);
            return;
        }

        $header_obj = "# CYPHT CONFIG HEADER - DON'T REMOVE";
        $header_obj .= "\n# ".base64_encode($this->request->post['conditions_json']);
        $header_obj .= "\n# ".base64_encode($this->request->post['actions_json']);
        $header_obj .= "\n# ".base64_encode($this->request->post['filter_source']);
        $script_parsed = $header_obj."\n\n".$script_parsed;

        $factory = get_sieve_client_factory($this->config);
        try {
            $client = $factory->init($this->user_config, $imap_account, $this->module_is_supported('nux'));
            $scripts = $client->listScripts();
            foreach ($scripts as $script) {
                if ($script == 'main_script') {
                    $client->removeScripts('main_script');
                }
                if ($script == $this->request->post['current_editing_filter_name']) {
                    $client->removeScripts($this->request->post['current_editing_filter_name']);
                }
            }

            $client->putScript(
                $script_name,
                $script_parsed
            );

            $scripts = $client->listScripts();
            $main_script = generate_main_script($scripts);

            save_main_script($client, $main_script, $scripts);
            $client->activateScript('main_script');
            $client->close();
            Hm_Msgs::add('Filter saved');
        } catch (Exception $e) {
            Hm_Msgs::add("Sieve: {$e->getMessage()}", "danger");
            return;
        }
    }
}

/**
 * @subpackage sievefilters/handler
 */
class Hm_Handler_sieve_save_script extends Hm_Handler_Module {
    public function process() {
        if ($this->should_skip_execution('enable_sieve_filter_setting', DEFAULT_ENABLE_SIEVE_FILTER)) return;

        $priority =  $this->request->post['sieve_script_priority'];
        if ($this->request->post['sieve_script_priority'] == '') {
            $priority = 0;
        }
        $script_name = generate_script_name($this->request->post['sieve_script_name'], $priority);
        foreach ($this->user_config->get('imap_servers') as $mailbox) {
            if ($mailbox['name'] == $this->request->post['imap_account']) {
                $imap_account = $mailbox;
            }
        }
        $factory = get_sieve_client_factory($this->config);
        try {
            $client = $factory->init($this->user_config, $imap_account, $this->module_is_supported('nux'));
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
            $client->close();
            Hm_Msgs::add('Script saved');
        } catch (Exception $e) {
            Hm_Msgs::add("Sieve: {$e->getMessage()}", "danger");
            return;
        }
    }
}

/**
 * @subpackage sievefilters/handler
 */
class Hm_Handler_sieve_block_change_behaviour_script extends Hm_Handler_Module {
    public function process() {
        if ($this->should_skip_execution('enable_sieve_filter_setting', DEFAULT_ENABLE_SIEVE_FILTER)) return;

        $imap_server_id = $this->request->post['imap_server_id'];
        if (!$this->user_config->get('sieve_block_default_behaviour')) {
            $this->user_config->set('sieve_block_default_behaviour', []);
        }
        if (!$this->user_config->get('sieve_block_default_reject_message')) {
            $this->user_config->set('sieve_block_default_reject_message', []);
        }
        $behaviours = $this->user_config->get('sieve_block_default_behaviour');
        $behaviours[$imap_server_id] = $this->request->post['selected_behaviour'];
        $this->user_config->set('sieve_block_default_behaviour', $behaviours);

        $reject_messages = $this->user_config->get('sieve_block_default_reject_message');
        $reject_messages[$imap_server_id] = $this->request->post['reject_message'] ?? '';
        $this->user_config->set('sieve_block_default_reject_message', $reject_messages);

        $this->session->record_unsaved('Changed Sieve Block behaviour');
        $this->session->set('user_data', $this->user_config->dump());

        Hm_Msgs::add('Default behaviour changed');
    }
}

/**
 * @subpackage sievefilters/output
 */
class Hm_Output_sieve_lock_change_behaviour_output extends Hm_Output_Module {
    public function output() {

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
        $this->out('site_config', $this->config);
        $this->out('user_config', $this->user_config);
    }
}

/**
 * @subpackage sievefilters/output
 */
class Hm_Output_sievefilters_settings_link extends Hm_Output_Module {
    protected function output() {
        if (!$this->get('sieve_filters_enabled')) {
            return '';
        }
        $res = '<li class="menu_sieve_filters"><a class="unread_link" href="'.$this->build_page_url('sieve_filters').'">';
        if (!$this->get('hide_folder_icons')) {
            $res .= '<i class="bi bi-journal-bookmark-fill me-2"></i>';
        }
        $res .= $this->trans('Filters & Actions').'</a></li>';
        $res .= '<li class="menu_block_list"><a class="unread_link" href="'.$this->build_page_url('block_list').'">';
        if (!$this->get('hide_folder_icons')) {
            $res .= '<i class="bi bi-x-circle-fill me-2"></i>';
        }
        $res .= $this->trans('Block List').'</a></li>';
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
        $res = '<div class="sievefilters_settings p-0"><div class="content_title px-3">'.$this->trans('Filters & Actions').'</div>';
        $res .= '<div class="p-3">';
        $res .= '<div id="sieve_accounts"></div>';
        $res .= get_classic_filter_modal_content();
        $res .= get_script_modal_content();
        return $res;
    }
}

/**
 * @subpackage sievefilters/output
 */
class Hm_Output_sievefilters_title_start extends Hm_Output_Module {
    protected function output() {
        $socked_connected = $this->get('socket_connected', false);
        $res = '<div class="sievefilters_settings p-0"><div class="content_title px-3">'.$this->trans('Filters & Actions').'</div>';
        $res .= '<div class="p-3">';
        $res .= '<div id="sieve_accounts"></div>';
        return $res;
    }
}

/**
 * @subpackage sievefilters/output
 */
class Hm_Output_sievefilters_modal_content_start extends Hm_Output_Module
{
    protected function output()
    {
        $res = get_classic_filter_modal_content();
        $res .= get_script_modal_content();
        return $res;
    }
}

class Hm_output_custom_action_modal_content extends Hm_Output_Module {
    protected function output() {
        $res = '<div id="custom_action_template" class="d-none">';
        $res .= '<div class="sieve-filter-name-group mb-3">';
        $res .= '<label class="form-label fw-bold">Action Name:</label>';
        $res .= '<input type="text" class="custom_action_name_input form-control" placeholder="e.g., Move to Important" />';
        $res .= '</div>';
        $res .= get_classic_filter_modal_actions_content();
        $res .= '</div>';
        return $res;
    }
}

/**
 * @subpackage sievefilters/output
 */
class Hm_Output_new_sieve_filter_for_message_like_this extends Hm_Output_Module {
    public function output() {
        if (!$this->get('sieve_filters_enabled')) {
            return '';
        }
        $mailbox_name = $this->get('mailbox_name') ?? '';
        $headers = $this->get('filter_headers', []);
        
        $sender = $headers['from'] ?? '';
        $to = $headers['to'] ?? '';
        $subject = $headers['subject'] ?? '';
        $replyTo = $headers['reply-to'] ?? '';

        $res = '<a class="hLink text-decoration-none btn btn-sm btn-outline-secondary dropdown-toggle me-2" '
                    . 'id="filter_message" href="#" data-bs-toggle="dropdown" aria-expanded="false">'
                    . $this->trans('Filter similar messages')
                    . '</a>'
                    . '<div class="dropdown-menu move_to_location p-3">'
                    . '<form id="create-filter-form" style="min-width:260px;" account="' . $mailbox_name . '">'
                    . '<h5>' . $this->trans('Create filter for message matching: ') . '</h5>'

                    // From (enabled, checked by default)
                    . '<div class="form-check mb-1">'
                    . '<input class="form-check-input" type="checkbox" id="use_from" checked>'
                    . '<input type="hidden" name="from" value="' . htmlspecialchars($sender) . '">'
                    . '<label class="form-check-label small" for="use_from">'
                    . $this->trans('From:') . ' ' . htmlspecialchars($sender)
                    . '</label>'
                    . '</div>'

                    // To (disabled by default)
                    . '<div class="form-check mb-1">'
                    . '<input class="form-check-input" type="checkbox" id="use_to" >'
                    . '<input type="hidden" name="to" value="' . htmlspecialchars($to) . '">'
                    . '<label class="form-check-label small text-muted" for="use_to">'
                    . $this->trans('To:') . ' ' . $to
                    . '</label>'
                    . '</div>'

                    // Subject (disabled by default)
                    . '<div class="form-check mb-1">'
                    . '<input class="form-check-input" type="checkbox" id="use_subject" >'
                    . '<input type="hidden" name="subject" value="' . htmlspecialchars($subject) . '">'
                    . '<label class="form-check-label small text-muted" for="use_subject">'
                    . $this->trans('Subject contains:') . ' ' . $subject
                    . '</label>'
                    . '</div>'

                    // Reply-To (disabled by default)
                    . '<div class="form-check mb-2">'
                    . '<input class="form-check-input" type="checkbox" id="use_reply" >'
                    . '<input type="hidden" name="reply-to" value="' . htmlspecialchars($replyTo) . '">'
                    . '<label class="form-check-label small text-muted" for="use_reply">'
                    . $this->trans('Reply-To:') . ' ' . $replyTo
                    . '</label>'
                    . '</div>'

                    . '<button type="submit" id="create_filter" class="btn btn-primary btn-sm" >'
                    . $this->trans('Create filter')
                    . '</button>'
                    . '</form>'
                    . '</div>';

                    $this->out('new_filter', $res, false);
    }
}

/**
 * @subpackage sievefilters/output
 */
class Hm_Output_blocklist_settings_start extends Hm_Output_Module {
    protected function output() {
        $socked_connected = $this->get('socket_connected', false);
        $res = '<div class="sievefilters_settings p-0"><div class="content_title px-3">'.$this->trans('Block List').'</div>';
        $res .= '<div class="p-3" id="sieve_accounts"></div>';
        $res .= get_classic_filter_modal_content();
        $res .= get_script_modal_content();
        return $res;
    }
}

/**
 * @subpackage sievefilters/handler
 */
class Hm_Handler_load_behaviour extends Hm_Handler_Module
{
    public function process()
    {
        if ($this->should_skip_execution('enable_sieve_filter_setting', DEFAULT_ENABLE_SIEVE_FILTER)) return;

        if ($this->user_config->get('sieve_block_default_behaviour')) {
            $this->out('sieve_block_default_behaviour', $this->user_config->get('sieve_block_default_behaviour'));
        } else {
            $this->out('sieve_block_default_behaviour', []);
        }
        if ($this->user_config->get('sieve_block_default_reject_message')) {
            $this->out('sieve_block_default_reject_message', $this->user_config->get('sieve_block_default_reject_message'));
        } else {
            $this->out('sieve_block_default_reject_message', []);
        }
    }
}

/**
 * @subpackage sievefilters/output
 */
class Hm_Output_blocklist_settings_accounts extends Hm_Output_Module {
    protected function output() {
        if (! ($mailbox = $this->get('mailbox')) || empty($mailbox['sieve_config_host']) || !$this->get('sieve_filters_enabled')) {
            return;
        }
        $behaviours = $this->get('sieve_block_default_behaviour');
        $reject_messages = $this->get('sieve_block_default_reject_message');
        $default_behaviour = 'Discard';
        $default_reject_message = '';
        if (array_key_exists($mailbox['id'], $behaviours)) {
            $default_behaviour = $behaviours[$mailbox['id']];
        }
        if (array_key_exists($mailbox['id'], $reject_messages)) {
            $default_reject_message = $reject_messages[$mailbox['id']];
        }

        $default_behaviour_html = '<div class="col-xxl-12 col-xl-9 mb-4"><div class="input-group"><span class="input-group-text">Default Behaviour:</span> <select class="select_default_behaviour form-select " imap_account="' . $mailbox['id'] . '">'
            . '<option value="Discard"' . ($default_behaviour == 'Discard' ? ' selected' : '') . '>Discard</option>'
            . '<option value="Reject"' . ($default_behaviour == 'Reject' ? ' selected' : '') . '>' . $this->trans('Reject') . '</option>'
            . '<option value="Move" ' . ($default_behaviour == 'Move' ? ' selected' : '') . '>' . $this->trans('Move To Blocked Folder') . '</option></select>';
        if ($default_behaviour == 'Reject') {
            $default_behaviour_html .= '<input type="text" class="select_default_reject_message form-control" value="' . $default_reject_message . '" placeholder="' . $this->trans('Reject message') . '" />';
        }
        $default_behaviour_html .= '<button class="submit_default_behavior btn btn-primary">' . $this->trans('Submit') . '</button></div></div>';
        $blocked_senders = get_blocked_senders_array($mailbox, $this->get('site_config'), $this->get('user_config'));
        $num_blocked = $blocked_senders ? sizeof($blocked_senders) : 0;
        $res = '<div class="sievefilters_accounts_item">';
        $res .= '<div class="sievefilters_accounts_title settings_subtitle py-2 border-bottom cursor-pointer d-flex justify-content-between" data-num-blocked="' . $num_blocked . '">' . $mailbox['name'];
        $res .= '<span class="filters_count"><span id="filter_num_' . $mailbox['id'] . '">' . $num_blocked . '</span> ' . $this->trans('blocked') . '</span></div>';
        $res .= '<div class="sievefilters_accounts filter_block py-3 d-none"><div class="filter_subblock">';
        $res .= $default_behaviour_html;
        $res .= '<table class="filter_details blocked_senders_table table"><tbody>';
        $res .= '<tr><th class="col-sm-6">Sender</th><th class="col-sm-3">Behavior</th><th class="col-sm-3">Actions</th></tr>';
        $res .= get_blocked_senders($mailbox, $mailbox['id'], 'x-circle-fill', 'globe-europe-africa', $this->get('site_config'), $this->get('user_config'), $this);
        $res .= '</tbody></table>';
        $res .= '</div></div></div>';
        $this->out('sieve_detail_display', $res);
    }
}

/**
 * @subpackage sievefilters/output
 */
class Hm_Output_account_sieve_filters extends Hm_Output_Module {
    protected function output() {
        if (! ($mailbox = $this->get('mailbox')) || empty($mailbox['sieve_config_host']) || !$this->get('sieve_filters_enabled')) {
            return;
        }
        $result = get_mailbox_filters($mailbox, $this->get('site_config'), $this->get('user_config'));
        $num_filters = $result['count'];
        $custom_actions = $this->get('account_custom_actions', []);
        $num_custom_actions = count($custom_actions);

        $res = '<div class="sievefilters_accounts_item">';
        $res .= '<div class="sievefilters_accounts_title settings_subtitle py-2 d-flex justify-content-between border-bottom cursor-pointer">' . $mailbox['name'];
        $res .= '<span class="filters_count">' . sprintf($this->trans('%s filters'), $num_filters) . '</span></div>';
        $res .= '<div class="sievefilters_accounts filter_block p-3 d-none"><div class="filter_subblock">';

        // Tab navigation
        $tab_id = md5($mailbox['name']);
        $res .= '<ul class="nav nav-tabs mb-3" role="tablist">';
        $res .= '<li class="nav-item" role="presentation">';
        $res .= '<button class="nav-link active sieve-tab-btn" id="tab-filters-' . htmlspecialchars($tab_id) . '" data-tab="filters" type="button" role="tab" aria-selected="true">';
        $res .= $this->trans('Filters');
        $res .= '</button>';
        $res .= '</li>';
        $res .= '<li class="nav-item" role="presentation">';
        $res .= '<button class="nav-link sieve-tab-btn" id="tab-custom-actions-' . htmlspecialchars($tab_id) . '" data-tab="custom-actions" type="button" role="tab" aria-selected="false">';
        $res .= $this->trans('Custom action buttons') . ' (' . $num_custom_actions . ')';
        $res .= '</button>';
        $res .= '</li>';
        $res .= '</ul>';

        // Filters tab content
        $res .= '<div class="sieve-tab-content" data-tab-id="filters">';
        $res .= '<div class="sieve-info-bubble alert alert-info alert-dismissible fade show py-2" data-info-key="sieve_filters_info_dismissed" role="alert">';
        $res .= '<i class="bi bi-info-circle me-2"></i>';
        $res .= $this->trans('Filters run automatically: they check every incoming message against the conditions you set (sender, subject, etc.) and apply their actions without you doing anything.');
        $res .= '<button type="button" class="btn-close sieve-info-bubble-close" aria-label="' . $this->trans('Close') . '"></button>';
        $res .= '</div>';
        $res .= '<div class="mb-3">';
        $res .= '<button class="add_filter btn btn-primary me-2" account="'.$mailbox['name'].'"  sieve_extensions=\'' . json_encode($mailbox['sieve_extensions']) . '\'>Add Filter</button> <button  account="'.$mailbox['name'].'" class="add_script btn btn-light border">Add Script</button>';
        $res .= '</div>';
        $res .= '<div class="table-responsive rounded-3 border shadow-sm">';
        $res .= '<table class="filter_details table table-striped table-hover table-sm m-0"><tbody>';
        $res .= '<tr><th class="text-secondary fw-light col-sm-1">Priority</th><th class="text-secondary fw-light col-sm-9">Name</th><th class="text-secondary fw-light col-sm-2">Actions</th></tr>';
        $res .= $result['list'];
        $res .= '</tbody></table>';
        $res .= '</div>';
        $res .= '</div>';

        // Custom actions tab content
        $res .= '<div class="sieve-tab-content d-none" data-tab-id="custom-actions">';
        $res .= '<div class="sieve-info-bubble alert alert-info alert-dismissible fade show py-2" data-info-key="custom_actions_info_dismissed" role="alert">';
        $res .= '<i class="bi bi-info-circle me-2"></i>';
        $res .= $this->trans('Custom action buttons are manual: unlike filters, they never run on their own. You apply by selecting messages and clicking the custom action button.');
        $res .= '<button type="button" class="btn-close sieve-info-bubble-close" aria-label="' . $this->trans('Close') . '"></button>';
        $res .= '</div>';
        $res .= '<div class="mb-3">';
        $res .= '<button class="create_custom_action btn btn-primary" account="'.$mailbox['name'].'">Create Custom Action</button>';
        $res .= '</div>';
        
        if (!empty($custom_actions)) {
            $res .= '<div class="table-responsive rounded-3 border shadow-sm">';
            $res .= '<table class="custom_actions_details table table-striped table-hover table-sm m-0"><tbody>';
            $res .= '<tr><th class="text-secondary fw-light col-sm-9">Name</th><th class="text-secondary fw-light col-sm-3">Actions</th></tr>';
            foreach ($custom_actions as $action) {
                $res .= '<tr>';
                $res .= '<td>' . htmlspecialchars($action['name']) . '</td>';
                $res .= '<td>';
                $res .= '<span class="form-switch me-2">';
                $res .= '<input class="toggle_custom_action form-check-input" type="checkbox" role="switch" data-action-id="' . htmlspecialchars($action['id']) . '" data-imap-account="' . htmlspecialchars($mailbox['name']) . '" checked="">';
                $res .= '</span>';
                $res .= '<a href="#" class="edit_custom_action ps-2" data-action-id="' . htmlspecialchars($action['id']) . '" data-imap-account="' . htmlspecialchars($mailbox['name']) . '" data-action-name="' . htmlspecialchars($action['name']) . '">';
                $res .= '<i class="bi bi-pencil-fill"></i>';
                $res .= '</a>';
                $res .= '<a href="#" class="delete_custom_action ps-2" data-action-id="' . htmlspecialchars($action['id']) . '" data-imap-account="' . htmlspecialchars($mailbox['name']) . '">';
                $res .= '<i class="bi bi-trash3 text-danger"></i>';
                $res .= '</a>';
                $res .= '</td>';
                $res .= '</tr>';
            }
            $res .= '</tbody></table>';
            $res .= '</div>';
        } else {
            $res .= '<small class="text-muted">' . $this->trans('No custom actions defined') . '</small>';
        }
        $res .= '</div>';

        $res .= '</div></div></div>';

        $this->out('sieve_detail_display', $res);
        if (DEBUG_MODE) {
            Hm_Debug::add('Session after: ' . print_r($_SESSION, true), 'debug');
        }
    }
}

/**
 * @subpackage sievefilters/output
 */
class Hm_Output_check_filter_status extends Hm_Output_Module {
    protected function output() {
        if (!$this->get('sieve_filters_enabled')) {
            $res = '<div class="empty_list">' . $this->trans('Sieve filter is deactivated') . '</div>';
            $this->out('sieve_detail_display', $res);
        }
    }
}

/**
 * @subpackage keyboard_shortcuts/handler
 */
class Hm_Handler_process_enable_sieve_filter_setting extends Hm_Handler_Module {
    public function process() {
        function sieve_enabled_callback($val) { return $val; }
        process_site_setting('enable_sieve_filter', $this, 'sieve_enabled_callback', DEFAULT_ENABLE_SIEVE_FILTER, true);
    }
}

/**
 * @subpackage keyboard_shortcuts/output
 */
class Hm_Output_enable_sieve_filter_setting extends Hm_Output_Module {
    protected function output() {
        $settings = $this->get('user_settings');
        if ((array_key_exists('enable_sieve_filter', $settings) && $settings['enable_sieve_filter']) || DEFAULT_ENABLE_SIEVE_FILTER) {
            $checked = ' checked="checked"';
        }
        else {
            $checked = '';
        }
        
        if($settings['enable_sieve_filter'] != DEFAULT_ENABLE_SIEVE_FILTER) {
            $reset = '<span class="tooltip_restore" restore_aria_label="Restore default value"><i class="bi bi-arrow-counterclockwise refresh_list reset_default_value_checkbox"></i></span>';
        }else {
            $reset = '';
        }
        return '<tr class="general_setting"><td class="d-block d-md-table-cell"><label for="enable_sieve_filter">'.
            $this->trans('Enable sieve filter').'</label></td><td class="d-block d-md-table-cell"><div class="d-flex align-items-center"><input class="form-check-input me-2" type="checkbox" '.$checked.
            ' value="1" id="enable_sieve_filter" name="enable_sieve_filter" data-default-value="'.(DEFAULT_ENABLE_SIEVE_FILTER ? 'true' : 'false') . '"/>'.$reset.'</div></td></tr>';
    }
}

/**
 * Check the status of an SIEVE server
 * @subpackage sieve/handler
 */
class Hm_Handler_sieve_status extends Hm_Handler_Module {
    protected static $capabilities = [];

    /**
     * Output used on the info page to display the server status
     */
    public function process() {
        if ($this->should_skip_execution('enable_sieve_filter_setting', DEFAULT_ENABLE_SIEVE_FILTER)) return;

        list($success, $form) = $this->process_form(array('imap_server_ids'));
        if ($success) {
            $ids = explode(',', $form['imap_server_ids']);
            foreach ($ids as $id) {
                $imap_account = Hm_IMAP_List::get($id, true);
                if (isset($imap_account['sieve_config_host'])) {
                    if (isset(self::$capabilities[$imap_account['sieve_config_host']])) {
                        $this->out('sieve_server_capabilities', self::$capabilities[$imap_account['sieve_config_host']]);
                        continue;
                    }

                    $client = initialize_sieve_client_factory($this->config, null, $imap_account);

                    if ($client) {
                        $this->out('sieve_server_capabilities', $client->getCapabilities());
                        self::$capabilities[$imap_account['sieve_config_host']] = $client->getCapabilities();
                    }
                }
            }
        }
    }
}

/**
 * Check the connection to a SIEVE server
 * @subpackage sieve/handler
 */
class Hm_Handler_sieve_connect extends Hm_Handler_Module {
    public function process() {
        if ($this->should_skip_execution('enable_sieve_filter_setting', DEFAULT_ENABLE_SIEVE_FILTER)) return;

        if ($imap_details = $this->get('imap_connect_details')) {
            $factory = get_sieve_client_factory($this->site_config);
            try {
                $cxlient = $factory->init($this->user_config, $imap_details, $this->module_is_supported('nux'));
            } catch (Exception $e) {
                Hm_Msgs::add("Failed to authenticate to the Sieve host", "danger");
            }
        }
    }
}


class Hm_Handler_sieve_toggle_script_state extends Hm_Handler_Module {
    public function process() {
        if ($this->should_skip_execution('enable_sieve_filter_setting', DEFAULT_ENABLE_SIEVE_FILTER)) return;

        list($success, $form) = $this->process_form(array('imap_account', 'script_state', 'sieve_script_name'));
        if (!$success) {
            $this->out('success', false);
            return;
        }
        $imap_account = Hm_IMAP_List::dump($form['imap_account']);
        $factory = get_sieve_client_factory($this->config);
        $success = false;
        try {
            $client = $factory->init($this->user_config, $imap_account, $this->module_is_supported('nux'));
            $state = $form['script_state'] ? 'enabled': 'disabled';
            $scripts = $client->listScripts();
            foreach ($scripts as $key => $script) {
                if ($script == 'main_script') {
                    $client->removeScripts('main_script');
                }
                if ($script == $form['sieve_script_name']) {
                    if (! $form['script_state']) {
                        unset($scripts[$key]);
                    }
                    $client->renameScript($script, "s{$state}_");
                    $success = true;
                }
            }
            $scripts = $client->listScripts();
            $main_script = generate_main_script($scripts);
            save_main_script($client, $main_script, $scripts);
            $client->activateScript('main_script');
            $client->close();

            Hm_Msgs::add("Script $state");
        } catch (Exception $e) {
            Hm_Msgs::add("Sieve: {$e->getMessage()}", "danger");
        }
        $this->out('success', $success);
    }
}
class Hm_Handler_list_block_sieve_script extends Hm_Handler_Module {
    public function process() {
        if ($this->should_skip_execution('enable_sieve_filter_setting', DEFAULT_ENABLE_SIEVE_FILTER)) return;

        list($success, $form) = $this->process_form(array('imap_server_id'));
        if (!$success) {
            return;
        }

        Hm_IMAP_List::init($this->user_config, $this->session);
        $imap_account = Hm_IMAP_List::get($form['imap_server_id'], true);

        if (empty($imap_account['sieve_config_host'])) {
            return;
        }
        
        $factory = get_sieve_client_factory($this->config);
        try {
            $client = $factory->init($this->user_config, $imap_account);

            $blocked_senders = [];
            $current_script = $client->getScript('blocked_senders');
            if ($current_script != '') {
                $blocked_list = prepare_sieve_script ($current_script);
                foreach ($blocked_list as $blocked_sender) {
                    $blocked_senders[] = $blocked_sender;
                }
            }
            $this->out('ajax_list_block_sieve', json_encode($blocked_senders));
        } catch (Exception $e) {
            Hm_Msgs::add("ERRSieve: {$e->getMessage()}");
            return;
        }
    }
}

class Hm_Handler_check_sieve_configuration extends Hm_Handler_Module {
    public function process() {
        if ($this->should_skip_execution('enable_sieve_filter_setting', DEFAULT_ENABLE_SIEVE_FILTER)) return;

        Hm_IMAP_List::init($this->user_config, $this->session);
        $servers = Hm_IMAP_List::dump();
        $has_uncomplete_sieve_conf = (bool) array_filter($servers, fn($item) => $item['type'] !== 'ews' && empty($item['sieve_config_host']));
        if($has_uncomplete_sieve_conf) {
            $this->out('sieve_alert_message', 'Sieve is enabled but not fully configured on some servers. Please review and save the server configuration to complete setup.');
        }
    }
}

class Hm_Output_display_sieve_misconfig_alert extends Hm_Output_Module {
    protected function output() {
        if ($this->get('single_server_mode')) {
            return '';
        }
        $res = '';
        $sieve_alert_message = $this->get('sieve_alert_message');
        if(!empty($sieve_alert_message)) {
            $res = '<div class="mt-3"><div class="alert alert-warning alert-dismissible fade show" role="alert"><strong>'.$this->trans('Alert sieve!').'</strong> '. $sieve_alert_message .'<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div></div>';
        }
        return $res;
    }
}


class Hm_Output_list_block_sieve_output extends Hm_Output_Module {
    public function output() {
        $list_block_sieve = $this->get('ajax_list_block_sieve', "");
        $this->out('ajax_list_block_sieve', $list_block_sieve);
    }
}

class Hm_Handler_load_account_sieve_filters extends Hm_Handler_Module
{
    public function process()
    {
        if ($this->should_skip_execution('enable_sieve_filter_setting', DEFAULT_ENABLE_SIEVE_FILTER)) return;

        list($success, $form) = $this->process_form(array('imap_server_id'));

        if (!$success) {
            return;
        }
        $accounts = $this->get('imap_accounts');
        if (isset($accounts[$form['imap_server_id']])) {
            $account = $accounts[$form['imap_server_id']];
            $client = initialize_sieve_client_factory($this->config, null, $account);
            $account['sieve_extensions'] = [];

            if ($client) {
                $account['sieve_extensions'] = $client->getExtensions();
            }
            $this->out('mailbox', $account);
            $this->session->close_early();
        }
    }
}

class Hm_Handler_load_account_custom_actions extends Hm_Handler_Module
{
    public function process()
    {
        if ($this->should_skip_execution('enable_sieve_filter_setting', DEFAULT_ENABLE_SIEVE_FILTER)) return;

        $mailbox = $this->get('mailbox', []);
        if (empty($mailbox['name'])) {
            return;
        }

        $custom_actions = $this->user_config->get('custom_actions', []);
        $account_name = $mailbox['name'];
        $account_actions = [];
        
        if (!empty($custom_actions['by_account'][$account_name])) {
            $account_actions = $custom_actions['by_account'][$account_name];
        }

        $this->out('account_custom_actions', $account_actions);
    }
}

class Hm_Handler_load_custom_action_by_id extends Hm_Handler_Module
{
    public function process()
    {
        if ($this->should_skip_execution('enable_sieve_filter_setting', DEFAULT_ENABLE_SIEVE_FILTER)) return;

        list($success, $form) = $this->process_form(['imap_account', 'custom_action_id']);
        if (!$success) {
            $this->out('custom_action_error', 'Missing required fields');
            return;
        }

        $custom_actions = $this->user_config->get('custom_actions', []);
        $account_actions = $custom_actions['by_account'][$form['imap_account']] ?? [];

        if (!isset($account_actions[$form['custom_action_id']])) {
            $this->out('custom_action_error', 'Custom Action not found');
            return;
        }

        $action = $account_actions[$form['custom_action_id']];
        // Ensure actions is a proper indexed array, not associative with numeric keys
        if (isset($action['actions']) && is_array($action['actions'])) {
            $action['actions'] = array_values($action['actions']);
        }
        $this->out('custom_action', $action);
    }
}class Hm_Handler_load_mailbox_name extends Hm_Handler_Module
{
    public function process()
    {
        if ($this->should_skip_execution('enable_sieve_filter_setting', DEFAULT_ENABLE_SIEVE_FILTER)) return;

        $imap_server_id = null;
        if (array_key_exists('list_path', $this->request->get)) {
            $path = $this->request->get['list_path'];
            if (preg_match("/^imap_(\w+)_(.+)$/", $path, $matches)) {
                $imap_server_id = $matches[1];
            }
        }

        if ($imap_server_id === null) {
            return;
        }

        $imap_servers = $this->user_config->get('imap_servers');
        if (!isset($imap_servers[$imap_server_id]['name'])) {
            return;
        }

        $this->out('mailbox_name', $imap_servers[$imap_server_id]['name']);
    }
}

class Hm_Handler_load_automatic_actions extends Hm_Handler_Module
{
    public function process()
    {
        if ($this->should_skip_execution('enable_sieve_filter_setting', DEFAULT_ENABLE_SIEVE_FILTER)) return;

        $imap_server_id = null;
        if (array_key_exists('list_path', $this->request->get)) {
            $path = $this->request->get['list_path'];
            if (preg_match("/^imap_(\w+)_(.+)$/", $path, $matches)) {
                $imap_server_id = $matches[1];
            }
        }

        if ($imap_server_id === null) {
            $this->out('automatic_actions', []);
            return;
        }

        $imap_servers = $this->user_config->get('imap_servers');
        if (!isset($imap_servers[$imap_server_id])) {
            $this->out('automatic_actions', []);
            return;
        }

        $mailbox = $imap_servers[$imap_server_id];
        if (empty($mailbox['sieve_config_host'])) {
            $this->out('automatic_actions', []);
            return;
        }

        $filters = [];
        $factory = get_sieve_client_factory($this->config);
        try {
            $client = $factory->init($this->user_config, $mailbox, $this->module_is_supported('nux'));
            $scripts = $client->listScripts();
            
            foreach ($scripts as $script_name) {
                // Only include filter scripts (ending with cyphtfilter)
                if (mb_strstr($script_name, 'cyphtfilter')) {
                    $raw_script = $client->getScript($script_name); // raw content

                    // Parse the source from line 3 of the script header
                    $lines = split_script_lines($raw_script);
                    $source = ''; // default
                    if (isset($lines[3])) {
                        $meta_b64 = str_replace("# ", "", $lines[3]);
                        $source = base64_decode($meta_b64);
                    }

                    $exp_name = explode('-', $script_name);
                    $parsed_name = str_replace('_', ' ', implode('-', array_slice($exp_name, 0, count($exp_name) - 2)));
                    
                    if (preg_match('/^s(en|dis)abled/', $parsed_name)) {
                        $parsed_name = str_replace(['senabled ', 'sdisabled '], '', $parsed_name);
                    }

                    $filters[] = [
                        'id' => $script_name,
                        'name' => $parsed_name,
                        'source' => $source,
                    ];
                }
            }
            $client->close();
        } catch (Exception $e) {
            Hm_Msgs::add("Sieve: {$e->getMessage()}", "danger");
        }

        $this->out('automatic_actions', $filters);
    }
}

/**
 * @subpackage sievefilters/handler
 */
class Hm_Handler_save_custom_action extends Hm_Handler_Module {
    public function process() {
        list($success, $form) = $this->process_form(['custom_action_name', 'actions_json']);
        if (!$success) {
            $this->out('custom_action_error', 'Missing required fields');
            return;
        }

        $actions = json_decode($form['actions_json'], true);
        if (empty($actions)) {
            $this->out('custom_action_error', 'At least one action is required');
            return;
        }

        $actions = array_values($actions);

        $imap_account = isset($this->request->post['imap_account']) ? trim($this->request->post['imap_account']) : '';
        if ($imap_account === '') {
            $this->out('custom_action_error', 'Missing account');
            return;
        }

        $custom_actions = $this->user_config->get('custom_actions', []);

        if (!isset($custom_actions['by_account']) || !is_array($custom_actions['by_account'])) {
            $custom_actions['by_account'] = [];
        }

        $account_actions = $custom_actions['by_account'][$imap_account] ?? [];

        $posted_id = isset($this->request->post['action_id']) ? trim($this->request->post['action_id']) : '';
        if ($posted_id && array_key_exists($posted_id, $account_actions)) {
            $id = $posted_id;
            $message = 'Custom action updated';
        } else {
            $id = uniqid('ca_', true);
            $message = 'Custom action created';
        }
        $account_actions[$id] = [
            'id' => $id,
            'name' => $form['custom_action_name'],
            'actions' => $actions,
        ];
        $custom_actions['by_account'][$imap_account] = $account_actions;
        $this->user_config->set('custom_actions', $custom_actions);
        $this->session->record_unsaved($message);
        $this->session->set('user_data', $this->user_config->dump());
        $this->out('custom_action_saved', true);
        $this->out('custom_action_id', $id);
    }
}

/**
 * @subpackage sievefilters/handler
 */
class Hm_Handler_apply_custom_action extends Hm_Handler_Module {
    public function process() {
        list($success, $form) = $this->process_form(['imap_account', 'uids', 'actions_json']);
        if (!$success) {
            $this->out('custom_action_error', 'Missing required fields');
            return;
        }
        $uids = json_decode($form['uids'], true);
        if (empty($uids)) {
            $this->out('custom_action_error', 'No messages selected');
            return;
        }
        $actions = json_decode($form['actions_json'], true);
        if (empty($actions)) {
            $this->out('custom_action_error', 'No actions defined');
            return;
        }

        // Parse UIDs: format is imap_{server_id}_{uid}_{hex_folder}
        $grouped = [];
        foreach ($uids as $uid_str) {
            $parts = explode('_', $uid_str);
            if (count($parts) === 4 && $parts[0] === 'imap') {
                $grouped[$parts[1]][$parts[3]][] = $parts[2];
            }
        }
        if (empty($grouped)) {
            $this->out('custom_action_error', 'Could not parse message IDs');
            return;
        }

        // Lazy-init SMTP only if any action needs it
        $smtp_mailbox = null;
        $smtp_from    = '';
        $smtp_actions = ['redirect', 'forward', 'reject', 'autoreply'];
        $needs_smtp   = (bool) array_filter($actions, function($a) use ($smtp_actions) {
            return in_array(strtolower($a['action'] ?? ''), $smtp_actions, true);
        });
        if ($needs_smtp) {
            foreach (array_keys(Hm_SMTP_List::dump()) as $smtp_id) {
                $mb = Hm_SMTP_List::connect($smtp_id, false);
                if ($mb && $mb->authed()) {
                    $smtp_mailbox = $mb;
                    $srv = Hm_SMTP_List::dump($smtp_id, true);
                    $smtp_from = $srv['user'] ?? '';
                    break;
                }
            }
        }

        $errors = [];
        foreach ($grouped as $server_id => $folders) {
            $mailbox = Hm_IMAP_List::get_connected_mailbox($server_id, $this->cache);
            if (!$mailbox || !$mailbox->authed()) {
                $errors[] = 'Could not connect to server';
                continue;
            }
            foreach ($folders as $hex_folder => $msg_uids) {
                $folder          = hex2bin($hex_folder);
                $stop_processing = false;
                foreach ($actions as $action) {
                    if ($stop_processing) break;
                    $action_name = strtolower($action['action'] ?? '');
                    $value       = trim($action['value'] ?? '');
                    switch ($action_name) {
                        case 'stop':
                            $stop_processing = true;
                            break;
                        case 'keep':
                            break;
                        case 'discard':
                            $res = $mailbox->message_action($folder, 'DELETE', $msg_uids);
                            if ($res['status']) {
                                $mailbox->message_action($folder, 'EXPUNGE', $msg_uids);
                            }
                            break;
                        case 'move':
                        case 'imap_move':
                            if ($value) {
                                $mailbox->message_action($folder, 'MOVE', $msg_uids, $value);
                            }
                            break;
                        case 'copy':
                        case 'imap_copy':
                            if ($value) {
                                $mailbox->message_action($folder, 'COPY', $msg_uids, $value);
                            }
                            break;
                        case 'flag':
                        case 'addflag':
                            $flag_key = strtolower($value);
                            if ($flag_key === 'draft') {
                                $mailbox->message_action($folder, 'CUSTOM', $msg_uids, null, '\Draft');
                            } elseif ($flag_key !== 'recent') {
                                $cmd = $this->flag_value_to_cmd($value, true);
                                if ($cmd) {
                                    $mailbox->message_action($folder, $cmd, $msg_uids);
                                }
                            }
                            break;
                        case 'removeflag':
                            $flag_key = strtolower($value);
                            if ($flag_key !== 'draft' && $flag_key !== 'recent') {
                                $cmd = $this->flag_value_to_cmd($value, false);
                                if ($cmd) {
                                    $mailbox->message_action($folder, $cmd, $msg_uids);
                                }
                            }
                            break;
                        case 'redirect':
                        case 'forward':
                            // Re-send the raw RFC822 message to the specified address.
                            // 'forward' keeps the original copy; 'redirect' does the same
                            // at the IMAP level (deletion is a separate discard action).
                            if ($value && $smtp_mailbox) {
                                foreach ($msg_uids as $uid) {
                                    $raw = $mailbox->get_message_content($folder, $uid, 0);
                                    if ($raw) {
                                        $smtp_mailbox->send_message($smtp_from, [$value], $raw);
                                    }
                                }
                            }
                            break;
                        case 'reject':
                            // Send a rejection notice back to the original sender.
                            if ($smtp_mailbox) {
                                foreach ($msg_uids as $uid) {
                                    $hdrs       = array_change_key_case(
                                        $mailbox->get_message_headers($folder, $uid), CASE_LOWER
                                    );
                                    $reply_addr = trim($hdrs['reply-to'] ?? $hdrs['from'] ?? '');
                                    if ($reply_addr) {
                                        $orig_subj = $hdrs['subject'] ?? '';
                                        $body      = $value ?: 'Your message has been rejected.';
                                        $mime      = new Hm_MIME_Msg(
                                            $reply_addr,
                                            'Rejected: ' . $orig_subj,
                                            $body,
                                            $smtp_from
                                        );
                                        $smtp_mailbox->send_message(
                                            $smtp_from, [$reply_addr], $mime->get_mime_msg()
                                        );
                                    }
                                }
                            }
                            break;
                        case 'autoreply':
                            // Send an automated reply to the original sender.
                            if ($smtp_mailbox) {
                                foreach ($msg_uids as $uid) {
                                    $hdrs       = array_change_key_case(
                                        $mailbox->get_message_headers($folder, $uid), CASE_LOWER
                                    );
                                    $reply_addr = trim($hdrs['reply-to'] ?? $hdrs['from'] ?? '');
                                    if ($reply_addr) {
                                        $orig_subj = $hdrs['subject'] ?? '';
                                        $msg_id    = $hdrs['message-id'] ?? '';
                                        $mime      = new Hm_MIME_Msg(
                                            $reply_addr,
                                            'Re: ' . $orig_subj,
                                            $value,
                                            $smtp_from,
                                            false, '', '', $msg_id
                                        );
                                        $smtp_mailbox->send_message(
                                            $smtp_from, [$reply_addr], $mime->get_mime_msg()
                                        );
                                    }
                                }
                            }
                            break;
                    }
                }
            }
        }

        if (!empty($errors)) {
            $this->out('custom_action_error', implode('; ', $errors));
            return;
        }
        $this->out('apply_success', true);
        $this->out('apply_count', count($uids));
    }

    private function flag_value_to_cmd($flag, $add) {
        $add_map = [
            'seen'     => 'READ',
            'flagged'  => 'FLAG',
            'answered' => 'ANSWERED',
            'deleted'  => 'DELETE',
        ];
        $remove_map = [
            'seen'     => 'UNREAD',
            'flagged'  => 'UNFLAG',
            'answered' => 'UNREAD',   // no UNANSWERED in message_action; best-effort
            'deleted'  => 'UNDELETE',
        ];
        $key = strtolower($flag);
        return $add ? ($add_map[$key] ?? null) : ($remove_map[$key] ?? null);
    }
}

class Hm_Output_apply_custom_action extends Hm_Output_Module {
    protected function output() {
        $this->out('apply_success', $this->get('apply_success', false));
        $this->out('apply_count', $this->get('apply_count', 0));
        $error = $this->get('custom_action_error', '');
        if ($error) {
            $this->out('custom_action_error', $error);
        }
    }
}

/**
 * @subpackage sievefilters/output
 */
class Hm_Output_save_custom_action extends Hm_Output_Module {
    protected function output() {
        $this->out('custom_action_saved', $this->get('custom_action_saved', false));
        $this->out('custom_action_id', $this->get('custom_action_id', ''));
        $error = $this->get('custom_action_error', '');
        if ($error) {
            $this->out('custom_action_error', $error);
        }
    }
}

/**
 * @subpackage sievefilters/handlers
 */
class Hm_Handler_delete_custom_action extends Hm_Handler_Module {
    public function process() {
        $custom_action_id = $this->request->get('custom_action_id', '');
        $account = $this->request->get('imap_account', '');
        
        if (!$custom_action_id || !$account) {
            $this->out('custom_action_deleted', 0);
            return;
        }
        
        $user_config = $this->get('user_config', []);
        if (!isset($user_config['custom_actions']['by_account'][$account])) {
            $this->out('custom_action_deleted', 0);
            return;
        }
        
        $custom_actions = &$user_config['custom_actions']['by_account'][$account];
        $found = false;
        
        foreach ($custom_actions as $key => $action) {
            if ($action['id'] === $custom_action_id) {
                unset($custom_actions[$key]);
                $found = true;
                break;
            }
        }
        
        if ($found) {
            $this->out('user_config', $user_config);
            $this->out('custom_action_deleted', 1);
        } else {
            $this->out('custom_action_deleted', 0);
        }
    }
}

/**
 * @subpackage sievefilters/output
 */
class Hm_Output_delete_custom_action extends Hm_Output_Module {
    protected function output() {
        $this->out('custom_action_deleted', $this->get('custom_action_deleted', 0));
    }
}

class Hm_Output_message_list_automatic_actions extends Hm_Output_Module
{
    protected function output()
    {
        if (!$this->get('sieve_filters_enabled')) {
            return '';
        }
        $automatic_actions = $this->get('automatic_actions', []);
        $mailbox_name = $this->get('mailbox_name', '');

        $res = '<div class="dropdown">'
            .   '<a class="msg_custom core_msg_control btn btn-sm btn-light no_mobile border text-black-50 dropdown-toggle" '
            .   'id="filter_message" href="#" data-bs-toggle="dropdown" aria-expanded="false">'
            .   $this->trans('Automatic actions')
            .   '</a>'
            .   '<div class="dropdown-menu custom-actions p-2" aria-labelledby="filter_message">';

            if (!empty($automatic_actions)) {
                $res .= '<small class="dropdown-header text-muted px-2 py-1">'
                     .  '<i class="bi bi-info-circle me-1"></i>'.$this->trans('Auto-run on new emails')
                     .  '</small>';
                foreach ($automatic_actions as $filter) {
                    $res .= sprintf(
                        '<button class="dropdown-item msg_filter_action py-2 btn btn-secondary" data-filter-id="%s" data-imap-account="%s" data-filter-name="%s">'
                        .'<i class="bi bi-play-circle me-2 text-success"></i>%s</button>',
                        htmlspecialchars($filter['id']),
                        htmlspecialchars($mailbox_name),
                        htmlspecialchars($filter['name']),
                        htmlspecialchars($filter['name'])
                    );
                }
                $res .= '<hr class="dropdown-divider">';
            }

        $res .= '<button class="dropdown-item add_automatic_action text-primary btn btn-secondary py-2" '
                    .'id="add_automatic_action_button" account="'.$mailbox_name.'" '
                .'>'
                .   '<i class="bi bi-plus-circle me-2"></i>'.$this->trans('Create from Selected')
                . '</button>';
        $res .= '</div></div>';
 
        $this->concat('msg_controls_automatic_actions', $res);
    }
}

/**
 * @subpackage sievefilters/output
 * Custom actions dropdown for the message-list toolbar. Applies to whatever
 * rows are checked in the message table.
 */
class Hm_Output_message_list_custom_actions extends Hm_Output_Module
{
    protected function output()
    {
        if (!$this->get('sieve_filters_enabled')) {
            return '';
        }

        $custom_actions = $this->get('custom_actions', []);
        $mailbox_name = $this->get('mailbox_name', '');

        $res = render_custom_actions_dropdown($this, $custom_actions, $mailbox_name);
        $this->concat('msg_controls_custom_actions', $res);
    }
}

/**
 * @subpackage sievefilters/output
 * Custom actions dropdown for the opened message page.
 */
class Hm_Output_message_page_custom_actions extends Hm_Output_Module
{
    protected function output()
    {
        if (!$this->get('sieve_filters_enabled')) {
            return '';
        }
        $custom_actions = $this->get('custom_actions', []);

        $mailbox_name = $this->get('mailbox_name', '');

        $res = render_custom_actions_dropdown($this, $custom_actions, $mailbox_name, [
            'server_id' => $this->get('msg_server_id'),
            'uid'       => $this->get('msg_text_uid'),
            'folder'    => $this->get('msg_folder'),
        ]);

        $this->out('message_custom_actions', $res, false);
    }
}

/**
 * @subpackage sievefilters/handler
 * Lightweight context for the standalone message-list page custom actions endpoint.
 */
class Hm_Handler_load_message_custom_actions_context extends Hm_Handler_Module {
    public function process() {
        $server_id = '';
        $uid = '';
        $folder = '';
        if (!empty($this->request->post['imap_server_id'])) {
            $server_id = trim($this->request->post['imap_server_id']);
        }
        if (!empty($this->request->post['imap_msg_uid'])) {
            $uid = trim($this->request->post['imap_msg_uid']);
        }
        if (!empty($this->request->post['folder'])) {
            $folder = trim($this->request->post['folder']);
        }

        $this->out('msg_server_id', $server_id !== '' ? $server_id : null);
        $this->out('msg_text_uid', $uid !== '' ? $uid : null);
        $this->out('msg_folder', $folder !== '' ? $folder : null);

        $this->out('sieve_filters_enabled', $this->user_config->get('enable_sieve_filter_setting', DEFAULT_ENABLE_SIEVE_FILTER));

        if ($server_id !== '') {
            $imap_servers = $this->user_config->get('imap_servers', []);
            if (!empty($imap_servers[$server_id]['name'])) {
                $this->out('mailbox_name', $imap_servers[$server_id]['name']);
            }
        }
    }
}

class Hm_Handler_sieve_remame_folder extends Hm_Handler_Module
{
    public function process()
    {
        if ($this->should_skip_execution('enable_sieve_filter_setting', DEFAULT_ENABLE_SIEVE_FILTER)) return;

        list($success, $form) = $this->process_form(array('imap_server_id', 'folder', 'new_folder'));

        if (!$success) {
            return;
        }

        $mailbox = Hm_IMAP_List::get_connected_mailbox($form['imap_server_id'], $this->cache);
        if ($mailbox && $mailbox->authed() && $mailbox->is_imap()) {
            $imap_servers = $this->user_config->get('imap_servers');
            $imap_account = $imap_servers[$form['imap_server_id']];
            $linked_mailboxes = get_sieve_linked_mailbox($imap_account, $this);
            if ($linked_mailboxes && in_array($form['folder'], $linked_mailboxes)) {
                $factory = get_sieve_client_factory($this->site_config);
                try {
                    $client = $factory->init($this->user_config, $imap_account, $this->module_is_supported('nux'));
                    $script_names = array_filter(
                        $linked_mailboxes,
                        function ($value) use ($form) {
                            return $value == $form['folder'];
                        }
                    );
                    $script_names = array_keys($script_names);
                    foreach ($script_names as $script_name) {
                        $script_parsed = $client->getScript($script_name);
                        $script_parsed = str_replace('"'.$form['folder'].'"', '"'.$form['new_folder'].'"', $script_parsed);

                        $old_actions = base64_decode(split_script_lines($script_parsed)[2]);
                        $new_actions = base64_encode(str_replace('"'.$form['folder'].'"', '"'.$form['folder'].'"', $old_actions));
                        $script_parsed = str_replace(base64_encode($old_actions), $new_actions, $script_parsed);
                        $client->removeScripts($script_name);
                        $client->putScript(
                            $script_name,
                            $script_parsed
                        );
                    }
                    $client->close();
                    Hm_Msgs::add('Sieve filters using the folder were also updated to use the new folder name.', 'info');
                } catch (Exception $e) {
                    Hm_Msgs::add("Failed to rename folder in sieve scripts", "warning");
                }
            }
        }
    }
}

class Hm_Handler_sieve_can_delete_folder extends Hm_Handler_Module
{
    public function process()
    {
        if ($this->should_skip_execution('enable_sieve_filter_setting', DEFAULT_ENABLE_SIEVE_FILTER)) return;

        list($success, $form) = $this->process_form(array('imap_server_id', 'folder'));

        if (! $success) {
            return;
        }

        $mailbox = Hm_IMAP_List::get_connected_mailbox($form['imap_server_id'], $this->cache);
        if ($mailbox && $mailbox->authed() && $mailbox->is_imap()) {
            $del_folder = prep_folder_name($mailbox->get_connection(), $form['folder'], true);
            if (is_mailbox_linked_with_filters($del_folder, $form['imap_server_id'], $this)) {
                $this->out('sieve_can_delete_folder', false);
                Hm_Msgs::add('This folder can\'t be deleted because it is used in a Sieve filter.', 'warning');
            }
        }
    }
}
