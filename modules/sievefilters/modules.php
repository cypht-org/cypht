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
require_once APP_PATH.'modules/sievefilters/hm-sieve.php';
require_once APP_PATH.'modules/sievefilters/functions.php';

/**
 * @subpackage sievefilters/handler
 */
class Hm_Handler_sieve_edit_filter extends Hm_Handler_Module {
    public function process() {
        foreach ($this->user_config->get('imap_servers') as $mailbox) {
            if ($mailbox['name'] == $this->request->post['imap_account']) {
                $imap_account = $mailbox;
            }
        }

        $factory = get_sieve_client_factory($this->config);
        try {
            $client = $factory->init($this->user_config, $imap_account);
            $script = $client->getScript($this->request->post['sieve_script_name']);
            $base64_obj = str_replace("# ", "", preg_split('#\r?\n#', $script, 0)[1]);
            $this->out('conditions', json_encode(base64_decode($base64_obj)));
            $base64_obj = str_replace("# ", "", preg_split('#\r?\n#', $script, 0)[2]);
            $this->out('actions', json_encode(base64_decode($base64_obj)));
            if (mb_strstr($script, 'allof')) {
                $this->out('test_type', 'ALLOF');
            } else {
                $this->out('test_type', 'ANYOF');
            }
            $client->close();
        } catch (Exception $e) {
            Hm_Msgs::add("ERRSieve: {$e->getMessage()}");
            return;
        }
    }
}

/**
 * @subpackage sievefilters/handler
 */
class Hm_Handler_sieve_filters_enabled extends Hm_Handler_Module {
    public function process() {
        $this->out('sieve_filters_enabled', $this->user_config->get('enable_sieve_filter_setting', true));
    }
}

/**
 * @subpackage sievefilters/handler
 */
class Hm_Handler_sieve_filters_enabled_message_content extends Hm_Handler_Module {
    public function process() {
        $server = $this->user_config->get('imap_servers')[$this->request->post['imap_server_id']];
        $sieve_filters_enabled = $this->user_config->get('enable_sieve_filter_setting', true);
        if ($sieve_filters_enabled) {
            $factory = get_sieve_client_factory($this->config);
            try {
                $client = $factory->init($this->user_config, $server);
                $sieve_filters_enabled = true;
                $this->out('sieve_filters_client', $client);
            } catch (Exception $e) {
                Hm_Msgs::add("ERRSieve: {$e->getMessage()}");
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
        foreach ($this->user_config->get('imap_servers') as $mailbox) {
            if ($mailbox['name'] == $this->request->post['imap_account']) {
                $imap_account = $mailbox;
            }
        }
        $factory = get_sieve_client_factory($this->config);
        try {
            $client = $factory->init($this->user_config, $imap_account);
            $script = $client->getScript($this->request->post['sieve_script_name']);
            $client->close();
            $this->out('script', $script);
        } catch (Exception $e) {
            Hm_Msgs::add("ERRSieve: {$e->getMessage()}");
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
        foreach ($this->user_config->get('imap_servers') as $mailbox) {
            if ($mailbox['name'] == $this->request->post['imap_account']) {
                $imap_account = $mailbox;
            }
        }
        $factory = get_sieve_client_factory($this->config);
        try {
            $client = $factory->init($this->user_config, $imap_account);

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
            Hm_Msgs::add("ERRSieve: {$e->getMessage()}");
        }
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
        $factory = get_sieve_client_factory($this->config);
        try {
            $client = $factory->init($this->user_config, $imap_account);

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
            Hm_Msgs::add("ERRSieve: {$e->getMessage()}");
        }
    }
}

/**
 * @subpackage sievefilters/handler
 */
class Hm_Handler_sieve_block_domain_script extends Hm_Handler_Module {
    public function process() {
        foreach ($this->user_config->get('imap_servers') as $idx => $mailbox) {
            if ($idx == $this->request->post['imap_server_id']) {
                $imap_account = $mailbox;
            }
        }

        $email_sender = $this->request->post['sender'];
        $factory = get_sieve_client_factory($this->config);
        try {
            $client = $factory->init($this->user_config, $imap_account);
            $scripts = $client->listScripts();

            $current_script = $client->getScript('blocked_senders');
            $base64_obj = str_replace("# ", "", preg_split('#\r?\n#', $current_script, 0)[1]);
            $blocked_list = json_decode(base64_decode($base64_obj));

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
        } catch (Exception $e) {
            Hm_Msgs::add("ERRSieve: {$e->getMessage()}");
            return;
        }
    }
}


/**
 * @subpackage sievefilters/handler
 */
class Hm_Handler_sieve_get_mailboxes_script extends Hm_Handler_Module {
    public function process() {
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
        $cache = Hm_IMAP_List::get_cache($this->cache, $imap_server_id);
        $imap = Hm_IMAP_List::connect($imap_server_id, $cache);
        if (!imap_authed($imap)) {
            Hm_Msgs::add('ERRIMAP Authentication Failed');
            return;
        }
        $mailboxes = [];
        foreach ($imap->get_mailbox_list() as $idx => $mailbox) {
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
        list($success, $form) = $this->process_form(array('imap_server_id', 'sender'));

        if (!$success) {
            return;
        }

        foreach ($this->user_config->get('imap_servers') as $idx => $mailbox) {
            if ($idx == $this->request->post['imap_server_id']) {
                $imap_account = $mailbox;
            }
        }

        $email_sender = $this->request->post['sender'];
        if (mb_strstr($email_sender, '*')) {
            $email_sender = str_replace('*', '', $email_sender);
        }

        $default_behaviour = 'Discard';
        if ($this->user_config->get('sieve_block_default_behaviour')) {
            if (array_key_exists($this->request->post['imap_server_id'], $this->user_config->get('sieve_block_default_behaviour'))) {
                $default_behaviour = $this->user_config->get('sieve_block_default_behaviour')[$this->request->post['imap_server_id']];
            }
        }

        $factory = get_sieve_client_factory($this->config);
        try {
            $client = $factory->init($this->user_config, $imap_account);
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
                $base64_obj = str_replace("# ", "", preg_split('#\r?\n#', $current_script, 0)[1]);
                $blocked_list = json_decode(base64_decode($base64_obj));
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
                    new \PhpSieveManager\Filters\Actions\RejectFilterAction([""])
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
            Hm_Msgs::add("ERRSieve: {$e->getMessage()}");
            return;
        }
    }
}

/**
 * @subpackage sievefilters/handler
 */
class Hm_Handler_sieve_block_unblock_script extends Hm_Handler_Module {
    public function process() {
        list($success, $form) = $this->process_form(array('imap_server_id', 'block_action', 'scope'));

        if (!$success) {
            return;
        }

        foreach ($this->user_config->get('imap_servers') as $idx => $mailbox) {
            if ($idx == $this->request->post['imap_server_id']) {
                $imap_account = $mailbox;
            }
        }

        if (isset($this->request->post['imap_msg_uid'])) {
            $imap = Hm_IMAP_List::connect($this->request->post['imap_server_id']);

            if (!imap_authed($imap)) {
                Hm_Msgs::add('ERRIMAP Authentication Failed');
                return;
            }
            if (!$imap->select_mailbox(hex2bin($this->request->post['folder']))) {
                Hm_Msgs::add('ERRIMAP Mailbox select error');
                return;
            }
            $msg_header = $imap->get_message_headers($form['imap_msg_uid']);
            $test_pattern = "/(?:[a-z0-9!#$%&'*+=?^_`{|}~-]+(?:\.[a-z0-9!#$%&'*+=?^_`{|}~-]+)*|\"(?:[\x01-\x08\x0b\x0c\x0e-\x1f\x21\x23-\x5b\x5d-\x7f]|\\[\x01-\x09\x0b\x0c\x0e-\x7f])*\")@(?:(?:[a-z0-9](?:[a-z0-9-]*[a-z0-9])?\.)+[a-z0-9](?:[a-z0-9-]*[a-z0-9])?|\[(?:(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\.){3}(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?|[a-z0-9-]*[a-z0-9]:(?:[\x01-\x08\x0b\x0c\x0e-\x1f\x21-\x5a\x53-\x7f]|\\[\x01-\x09\x0b\x0c\x0e-\x7f])+)\])/";
            preg_match_all($test_pattern, $msg_header['From'], $email_sender);
            $email_sender = $email_sender[0][0];
        } elseif (!empty($this->request->post['sender'])) {
            $email_sender = $this->request->post['sender'];
        } else {
            Hm_Msgs::add('ERRSender not found');
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
            $client = $factory->init($this->user_config, $imap_account);
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
                $script_split = preg_split('#\r?\n#', $current_script, 0);
                $base64_obj = str_replace("# ", "", $script_split[1]);
                $blocked_list = json_decode(base64_decode($base64_obj));
                foreach ($blocked_list as $blocked_sender) {
                    if ($blocked_sender != $email_sender) {
                        $blocked_senders[] = $blocked_sender;
                        continue;
                    }
                    $unblock_sender = true;
                }
                $base64_obj_actions = str_replace("# ", "", $script_split[2]);
                if ($base64_obj_actions) {
                    $blocked_list_actions = json_decode(base64_decode($base64_obj_actions), true);
                }
            }
            if (isset($this->request->post['change_behavior']) && $unblock_sender) {
                $unblock_sender = false;
            }
            if ($unblock_sender == false || $current_script == '') {
                $blocked_senders[] = $email_sender;
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
                if ($blocked_sender == $email_sender) {
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
            Hm_Msgs::add("ERRSieve: {$e->getMessage()}");
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
                    new \PhpSieveManager\Filters\Actions\FileIntoFilterAction(['mailbox' => [$action->value]])
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
        $script_parsed = $header_obj."\n\n".$script_parsed;

        $factory = get_sieve_client_factory($this->config);
        try {
            $client = $factory->init($this->user_config, $imap_account);
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
        } catch (Exception $e) {
            Hm_Msgs::add("ERRSieve: {$e->getMessage()}");
            return;
        }
    }
}

/**
 * @subpackage sievefilters/handler
 */
class Hm_Handler_sieve_save_script extends Hm_Handler_Module {
    public function process() {
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
            $client = $factory->init($this->user_config, $imap_account);
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
        } catch (Exception $e) {
            Hm_Msgs::add("ERRSieve: {$e->getMessage()}");
            return;
        }
    }
}

/**
 * @subpackage sievefilters/handler
 */
class Hm_Handler_sieve_block_change_behaviour_script extends Hm_Handler_Module {
    public function process() {
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
        $res = '<li class="menu_sieve_filters"><a class="unread_link" href="?page=sieve_filters">';
        if (!$this->get('hide_folder_icons')) {
            $res .= '<i class="bi bi-journal-bookmark-fill fs-5 me-2"></i>';
        }
        $res .= $this->trans('Filters').'</a></li>';
        $res .= '<li class="menu_block_list"><a class="unread_link" href="?page=block_list">';
        if (!$this->get('hide_folder_icons')) {
            $res .= '<i class="bi bi-x-circle-fill fs-5 me-2"></i>';
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
        $res = '<div class="sievefilters_settings p-0"><div class="content_title px-3">'.$this->trans('Filters').'</div>';
        $res .= '<div class="p-3">';
        return $res;
    }
}

/**
 * @subpackage sievefilters/output
 */
class Hm_Output_blocklist_settings_start extends Hm_Output_Module {
    protected function output() {
        $socked_connected = $this->get('socket_connected', false);
        $res = '<div class="sievefilters_settings p-0"><div class="content_title px-3">'.$this->trans('Block List').'</div>';
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
        if (!$this->get('sieve_filters_enabled')) {
            return '<div class="empty_list">' . $this->trans('Sieve filter is deactivated') . '</div>';
        }
        $mailboxes = $this->get('imap_accounts', array());
        $res = get_classic_filter_modal_content();
        $res .= get_script_modal_content();
        $res .= '<div class="p-3">';
        foreach($mailboxes as $idx => $mailbox) {
            if (empty($mailbox['sieve_config_host'])) {
                continue;
            }
            $behaviours = $this->get('sieve_block_default_behaviour');
            $reject_messages = $this->get('sieve_block_default_reject_message');
            $default_behaviour = 'Discard';
            $default_reject_message = '';
            if (array_key_exists($idx, $behaviours)) {
                $default_behaviour = $behaviours[$idx];
            }
            if (array_key_exists($idx, $reject_messages)) {
                $default_reject_message = $reject_messages[$idx];
            }

            $default_behaviour_html = '<div class="col-sm-7 mb-4"><div class="input-group"><span class="input-group-text">Default Behaviour:</span> <select class="select_default_behaviour form-select " imap_account="'.$idx.'">'
            .'<option value="Discard"'.($default_behaviour == 'Discard'? ' selected': '').'>Discard</option>'
            .'<option value="Reject"'.($default_behaviour == 'Reject'? ' selected': '').'>'.$this->trans('Reject').'</option>'
            .'<option value="Move" '.($default_behaviour == 'Move'? ' selected': '').'>'.$this->trans('Move To Blocked Folder').'</option></select>';
            if ($default_behaviour == 'Reject') {
                $default_behaviour_html .= '<input type="text" class="select_default_reject_message form-control" value="'.$default_reject_message.'" placeholder="'.$this->trans('Reject message').'" />';
            }
            $default_behaviour_html .= '<button class="submit_default_behavior btn btn-primary">'.$this->trans('Submit').'</button></div></div>';
            $blocked_senders = get_blocked_senders_array($mailbox, $this->get('site_config'), $this->get('user_config'));
            $num_blocked = $blocked_senders ? sizeof($blocked_senders) : 0;
            $res .= '<div class="sievefilters_accounts_item">';
            $res .= '<div class="sievefilters_accounts_title settings_subtitle py-2 border-bottom cursor-pointer d-flex justify-content-between">' . $mailbox['name'];
            $res .= '<span class="filters_count"><span id="filter_num_'.$idx.'">'.$num_blocked.'</span> '.$this->trans('blocked'). '</span></div>';
            $res .= '<div class="sievefilters_accounts filter_block py-3 d-none"><div class="filter_subblock">';
            $res .=  $default_behaviour_html;
            $res .= '<table class="filter_details table"><tbody>';
            $res .= '<tr><th class="col-sm-6">Sender</th><th class="col-sm-3">Behavior</th><th class="col-sm-3">Actions</th></tr>';
            $res .= get_blocked_senders($mailbox, $idx, 'x-circle-fill', 'globe-europe-africa', $this->get('site_config'), $this->get('user_config'), $this);
            $res .= '</tbody></table>';
            $res .= '</div></div></div>';
        }
        $res .= block_filter_dropdown($this, false, 'edit_blocked_behavior', 'Edit');
        $res .= '</div></div>';
        return $res;
    }
}

/**
 * @subpackage sievefilters/output
 */
class Hm_Output_sievefilters_settings_accounts extends Hm_Output_Module {
    protected function output() {
        if (!$this->get('sieve_filters_enabled')) {
            return '<div class="empty_list">' . $this->trans('Sieve filter is deactivated') . '</div>';
        }
        $mailboxes = $this->get('imap_accounts', array());
        $res = get_classic_filter_modal_content();
        $res .= get_script_modal_content();
        $sieve_supported = 0;
        foreach($mailboxes as $mailbox) {
            if (empty($mailbox['sieve_config_host'])) {
                continue;
            }
            $sieve_supported++;
            $result = get_mailbox_filters($mailbox, $this->get('site_config'), $this->get('user_config'));
            $num_filters = $result['count'];
            $res .= '<div class="sievefilters_accounts_item">';
            $res .= '<div class="sievefilters_accounts_title settings_subtitle py-2 d-flex justify-content-between border-bottom cursor-pointer">' . $mailbox['name'];
            $res .= '<span class="filters_count">' . sprintf($this->trans('%s filters'), $num_filters) . '</span></div>';
            $res .= '<div class="sievefilters_accounts filter_block p-3 d-none"><div class="filter_subblock">';
            $res .= '<button class="add_filter btn btn-primary" account="'.$mailbox['name'].'">Add Filter</button> <button  account="'.$mailbox['name'].'" class="add_script btn btn-light border">Add Script</button>';
            $res .= '<table class="filter_details table my-3"><tbody>';
            $res .= '<tr><th class="text-secondary fw-light col-sm-1">Priority</th><th class="text-secondary fw-light col-sm-9">Name</th><th class="text-secondary fw-light col-sm-2">Actions</th></tr>';
            $res .= $result['list'];
            $res .= '</tbody></table>';
            $res .= '<div class="mb-3 d-none">
                            <div class="d-block">
                                <h3 class="mb-1">If conditions are not met</h3>
                                <small>Define the actions if conditions are not met. If no actions are provided the next filter will be executed. If there are no other filters to be executed, the email will be delivered as expected.</small>
                            </div>
                        </div>
                        <div class="col-sm-12 mt-5 d-none" style="background-color: #f7f2ef;">
                            <div class="d-flex p-3">
                                <div class="d-block">
                                    <h5 class="mt-0">Actions</h5>
                                </div>
                                <div class="text-end flex-grow-1">
                                    <button class="filter_modal_add_else_action_btn me-2">Add Action</button>
                                </div>
                            </div>
                            <div class="d-block">
                                <table class="filter_else_actions_modal_table">
                                </table>
                            </div>
                        </div>';
            $res .= '</div></div></div>';
        }
        if ($sieve_supported == 0) {
            $res .= '<div class="empty_list">None of the configured IMAP servers support Sieve</div>';
        }
        return $res;
    }
}

/**
 * @subpackage keyboard_shortcuts/handler
 */
class Hm_Handler_process_enable_sieve_filter_setting extends Hm_Handler_Module {
    public function process() {
        function sieve_enabled_callback($val) { return $val; }
        process_site_setting('enable_sieve_filter', $this, 'sieve_enabled_callback', true, true);
    }
}

/**
 * @subpackage keyboard_shortcuts/output
 */
class Hm_Output_enable_sieve_filter_setting extends Hm_Output_Module {
    protected function output() {
        $settings = $this->get('user_settings');
        if (array_key_exists('enable_sieve_filter', $settings) && $settings['enable_sieve_filter']) {
            $checked = ' checked="checked"';
            $reset = '';
        }
        else {
            $checked = '';
            $reset = '<span class="tooltip_restore" restore_aria_label="Restore default value"><i class="bi bi-arrow-repeat refresh_list reset_default_value_checkbox"></i></span>';
        }
        return '<tr class="general_setting"><td><label class="form-check-label" for="enable_sieve_filter">'.
            $this->trans('Enable sieve filter').'</label></td>'.
            '<td><input class="form-check-input" type="checkbox" '.$checked.
            ' value="1" id="enable_sieve_filter" name="enable_sieve_filter" />'.$reset.'</td></tr>';
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

                    $client = initialize_sieve_client_factory(null, null, $imap_account);

                    if ($client) {
                        $this->out('sieve_server_capabilities', $client->getCapabilities());
                        self::$capabilities[$imap_account['sieve_config_host']] = $client->getCapabilities();
                    }
                }
            }
        }
    }
}
