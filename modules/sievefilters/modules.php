<?php
/**
 * SieveFilters modules
 * @package modules
 * @subpackage sievefilters
 */

if (!defined('DEBUG_MODE')) { die(); }

require_once VENDOR_PATH.'autoload.php';
use PhpSieveManager\ManageSieve\Client;


require_once APP_PATH.'modules/imap/functions.php';
require_once APP_PATH.'modules/imap/hm-imap.php';


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

        $sieve_options = explode(':', $imap_account['sieve_config_host']);
        $client = new \PhpSieveManager\ManageSieve\Client($sieve_options[0], $sieve_options[1]);
        $client->connect($imap_account['user'], $imap_account['pass'], false, "", "PLAIN");
        $script = $client->getScript($this->request->post['sieve_script_name']);
        $base64_obj = str_replace("# ", "", preg_split('#\r?\n#', $script, 0)[1]);
        $this->out('conditions', json_encode(base64_decode($base64_obj)));
        $base64_obj = str_replace("# ", "", preg_split('#\r?\n#', $script, 0)[2]);
        $this->out('actions', json_encode(base64_decode($base64_obj)));
        if (strstr($script, 'allof')) {
            $this->out('test_type', 'ALLOF');
        } else {
            $this->out('test_type', 'ANYOF');
        }
        $client->close();
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
        $sieve_options = explode(':', $imap_account['sieve_config_host']);
        $client = new \PhpSieveManager\ManageSieve\Client($sieve_options[0], $sieve_options[1]);
        $client->connect($imap_account['user'], $imap_account['pass'], false, "", "PLAIN");
        $script = $client->getScript($this->request->post['sieve_script_name']);
        $client->close();
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
class Hm_Handler_sieve_delete_filter extends Hm_Handler_Module {
    public function process() {
        foreach ($this->user_config->get('imap_servers') as $mailbox) {
            if ($mailbox['name'] == $this->request->post['imap_account']) {
                $imap_account = $mailbox;
            }
        }
        $sieve_options = explode(':', $imap_account['sieve_config_host']);
        $client = new \PhpSieveManager\ManageSieve\Client($sieve_options[0], $sieve_options[1]);
        $client->connect($imap_account['user'], $imap_account['pass'], false, "", "PLAIN");
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
        $client->putScript(
            'main_script',
            $main_script
        );
        $client->activateScript('main_script');
        $client->close();
        Hm_Msgs::add('Script removed');
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
        $client->connect($imap_account['user'], $imap_account['pass'], false, "", "PLAIN");
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

        $client->putScript(
            'main_script',
            $main_script
        );
        $client->activateScript('main_script');
        $client->close();
        Hm_Msgs::add('Script removed');
    }
}

function get_blocked_senders($mailbox, $mailbox_id, $icon_svg, $icon_block_domain_svg) {
    $sieve_options = explode(':', $mailbox['sieve_config_host']);
    $client = new \PhpSieveManager\ManageSieve\Client($sieve_options[0], $sieve_options[1]);

    try {
        $client->connect($mailbox['user'], $mailbox['pass'], false, "", "PLAIN");
    } catch (Exception $e) {
        return '';
    }
    $scripts = $client->listScripts();
    if (!array_search('blocked_senders', $scripts, true)) {
        return '';
    }
    $current_script = $client->getScript('blocked_senders');
    if ($current_script != '') {
        $base64_obj = str_replace("# ", "", preg_split('#\r?\n#', $current_script, 0)[1]);
        $blocked_list = json_decode(base64_decode($base64_obj));
        if (!$blocked_list) {
            return '';
        }
        foreach ($blocked_list as $blocked_sender) {
            if (explode('@', $blocked_sender)[0] == '') {
                $blocked_sender = '*'.$blocked_sender;
            }
            $blocked_senders[] = $blocked_sender;
        }
    }

    $ret = '';
    foreach ($blocked_senders as $sender) {
        $ret .= '<tr><td>'.$sender.'</td><td><img class="unblock_button" mailbox_id="'.$mailbox_id.'" src="'.$icon_svg.'" />';
        if (!strstr($sender, '*')) {
            $ret .= ' <img class="block_domain_button" mailbox_id="'.$mailbox_id.'" src="'.$icon_block_domain_svg.'" />';
        }
        $ret .= '</tr></td></tr>';
    }
    return $ret;
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
        $sieve_options = explode(':', $imap_account['sieve_config_host']);
        $client = new \PhpSieveManager\ManageSieve\Client($sieve_options[0], $sieve_options[1]);
        $client->connect($imap_account['user'], $imap_account['pass'], false, "", "PLAIN");

        $scripts = $client->listScripts();

        $current_script = $client->getScript('blocked_senders');
        $base64_obj = str_replace("# ", "", preg_split('#\r?\n#', $current_script, 0)[1]);
        $blocked_list = json_decode(base64_decode($base64_obj));

        $domain = explode('@', $this->request->post['sender'])[1];
        $blocked_wildcard = '@'.$domain;
        $new_blocked_list = [];
        foreach ($blocked_list as $idx => $blocked_sender) {
            if (!strstr($blocked_sender, $blocked_wildcard)) {
                $new_blocked_list[] = $blocked_sender;
            }
        }
        $new_blocked_list[] = $blocked_wildcard;

        if(!array_search('blocked_senders', $scripts, true)) {
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
        $client->putScript(
            'main_script',
            $main_script
        );
        $client->activateScript('main_script');
        $client->close();
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
        if (strstr($email_sender, '*')) {
            $email_sender = str_replace('*', '', $email_sender);
        }

        $default_behaviour = 'Discard';
        if ($this->user_config->get('sieve_block_default_behaviour')) {
            if (array_key_exists($this->request->post['imap_server_id'], $this->user_config->get('sieve_block_default_behaviour'))) {
                $default_behaviour = $this->user_config->get('sieve_block_default_behaviour')[$this->request->post['imap_server_id']];
            }
        }

        $sieve_options = explode(':', $imap_account['sieve_config_host']);
        $client = new \PhpSieveManager\ManageSieve\Client($sieve_options[0], $sieve_options[1]);
        $client->connect($imap_account['user'], $imap_account['pass'], false, "", "PLAIN");

        $scripts = $client->listScripts();

        if(!array_search('blocked_senders', $scripts, true)) {
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
        $client->putScript(
            'main_script',
            $main_script
        );
        $client->activateScript('main_script');
        $client->close();

        if ($unblock_sender) {
            Hm_Msgs::add('Sender Unblocked');
        } else {
            Hm_Msgs::add('Sender Blocked');
        }
    }
}

/**
 * @subpackage sievefilters/handler
 */
class Hm_Handler_sieve_block_unblock_script extends Hm_Handler_Module {
    public function process() {
        list($success, $form) = $this->process_form(array('imap_msg_uid', 'imap_server_id', 'folder'));

        if (!$success) {
            return;
        }

        foreach ($this->user_config->get('imap_servers') as $idx => $mailbox) {
            if ($idx == $this->request->post['imap_server_id']) {
                $imap_account = $mailbox;
            }
        }

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
        $test_pattern = "/(?:[a-z0-9!#$%&'*+=?^_`{|}~-]+(?:\.[a-z0-9!#$%&'*+=?^_`{|}~-]+)*|\"(?:[\x01-\x08\x0b\x0c\x0e-\x1f\x21\x23-\x5b\x5d-\x7f]|\\[\x01-\x09\x0b\x0c\x0e-\x7f])*\")@(?:(?:[a-z0-9](?:[a-z0-9-]*[a-z0-9])?\.)+[a-z0-9](?:[a-z0-9-]*[a-z0-9])?|\[(?:(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\.){3}(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?|[a-z0-9-]*[a-z0-9]:(?:[\x01-\x08\x0b\x0c\x0e-\x1f\x21-\x5a\x53-\x7f]|\\[\x01-\x09\x0b\x0c\x0e-\x7f])+)\])/";        preg_match_all($pattern, $msg_header['From'], $email_senders);
        preg_match_all($test_pattern, $msg_header['From'], $email_sender);
        $email_sender = $email_sender[0][0];

        $sieve_options = explode(':', $imap_account['sieve_config_host']);
        $client = new \PhpSieveManager\ManageSieve\Client($sieve_options[0], $sieve_options[1]);
        $client->connect($imap_account['user'], $imap_account['pass'], false, "", "PLAIN");

        $scripts = $client->listScripts();

        if(!array_search('blocked_senders', $scripts, true)) {
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
        $header_obj .= "\n# ".base64_encode(json_encode($blocked_senders));
        $script_parsed = $header_obj."\n\n".$script_parsed;
        $client->putScript(
            'blocked_senders',
            $script_parsed
        );
        $client->putScript(
            'main_script',
            $main_script
        );
        $client->activateScript('main_script');
        $client->close();

        if ($unblock_sender) {
            Hm_Msgs::add('Sender Unblocked');
        } else {
            Hm_Msgs::add('Sender Blocked');
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
        $sieve_options = explode(':', $imap_account['sieve_config_host']);
        $conditions = json_decode($this->request->post['conditions_json']);
        $actions = json_decode($this->request->post['actions_json']);
        $test_type = strtolower($this->request->post['filter_test_type']);
        
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
                    new \PhpSieveManager\Filters\Actions\RedirectFilterAction([$action->value])
                );
            }
            if ($action->action == 'flag') {
                $custom_condition->addAction(
                    new \PhpSieveManager\Filters\Actions\FlagFilterAction([$action->value])
                );
            }
            if ($action->action == 'addflag') {
                $filter->addRequirement('imap4flags');
                $custom_condition->addAction(
                    new \PhpSieveManager\Filters\Actions\AddFlagFilterAction([$action->value])
                );
            }
            if ($action->action == 'removeflag') {
                $filter->addRequirement('imap4flags');
                $custom_condition->addAction(
                    new \PhpSieveManager\Filters\Actions\RemoveFlagFilterAction([$action->value])
                );
            }
            if ($action->action == 'move') {
                $filter->addRequirement('fileinto');
                $custom_condition->addAction(
                    new \PhpSieveManager\Filters\Actions\FileIntoFilterAction([$action->value])
                );
            }
            if ($action->action == 'reject') {
                $filter->addRequirement('reject');
                $custom_condition->addAction(
                    new \PhpSieveManager\Filters\Actions\RejectFilterAction([$action->value])
                );
            }
            if ($action->action == 'copy') {
                $filter->addRequirement('fileinto');
                $custom_condition->addAction(
                    new \PhpSieveManager\Filters\Actions\FileIntoFilterAction([$action->value])
                );
                $custom_condition->addAction(
                    new \PhpSieveManager\Filters\Actions\KeepFilterAction()
                );
            }
            if ($action->action == 'autoreply') {
                $filter->addRequirement('vacation');
                $custom_condition->addAction(
                    new \PhpSieveManager\Filters\Actions\VacationFilterAction([$action->extra_option_value, $action->value])
                );
            }
        }
        $filter->setCondition($custom_condition);
        $script_parsed = $filter->toScript();

        $header_obj = "# CYPHT CONFIG HEADER - DON'T REMOVE";
        $header_obj .= "\n# ".base64_encode($this->request->post['conditions_json']);
        $header_obj .= "\n# ".base64_encode($this->request->post['actions_json']);
        $script_parsed = $header_obj."\n\n".$script_parsed;

        $client = new \PhpSieveManager\ManageSieve\Client($sieve_options[0], $sieve_options[1]);
        $client->connect($imap_account['user'], $imap_account['pass'], false, "", "PLAIN");
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

        $client->putScript(
            'main_script',
            $main_script
        );
        $client->activateScript('main_script');
        $client->close();
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
        $sieve_options = explode(':', $imap_account['sieve_config_host']);
        $client = new \PhpSieveManager\ManageSieve\Client($sieve_options[0], $sieve_options[1]);
        $client->connect($imap_account['user'], $imap_account['pass'], false, "", "PLAIN");
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
 * @subpackage sievefilters/handler
 */
class Hm_Handler_sieve_block_change_behaviour_script extends Hm_Handler_Module {
    public function process() {
        $imap_server_id = $this->request->post['imap_server_id'];
        if (!$this->user_config->get('sieve_block_default_behaviour')) {
            $this->user_config->set('sieve_block_default_behaviour', []);
        }
        $behaviours = $this->user_config->get('sieve_block_default_behaviour');
        $behaviours[$imap_server_id] = $this->request->post['selected_behaviour'];
        $this->user_config->set('sieve_block_default_behaviour', $behaviours);
        $this->session->record_unsaved('Changed Sieve Block behaviour');
        $this->session->set('user_data', $this->user_config->dump());
        $default_behaviour = $this->request->post['selected_behaviour'];

        foreach ($this->user_config->get('imap_servers') as $idx => $mailbox) {
            if ($idx == $this->request->post['imap_server_id']) {
                $imap_account = $mailbox;
            }
        }

        $sieve_options = explode(':', $imap_account['sieve_config_host']);
        $client = new \PhpSieveManager\ManageSieve\Client($sieve_options[0], $sieve_options[1]);
        $client->connect($imap_account['user'], $imap_account['pass'], false, "", "PLAIN");

        $scripts = $client->listScripts();

        if(!array_search('blocked_senders', $scripts, true)) {
            $client->putScript(
                'blocked_senders',
                ''
            );
        }

        $blocked_senders = [];
        $current_script = $client->getScript('blocked_senders');

        if ($current_script == '') {
            return;
        }

        if ($current_script != '') {
            $base64_obj = str_replace("# ", "", preg_split('#\r?\n#', $current_script, 0)[1]);
            $blocked_list = json_decode(base64_decode($base64_obj));
            foreach ($blocked_list as $blocked_sender) {
                $blocked_senders[] = $blocked_sender;
            }
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

        $header_obj = "# CYPHT CONFIG HEADER - DON'T REMOVE";
        $header_obj .= "\n# ".base64_encode(json_encode($blocked_senders));
        $script_parsed = $header_obj."\n\n".$script_parsed;

        $client->putScript(
            'blocked_senders',
            $script_parsed
        );
        $client->close();

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
    }
}

/**
 * @subpackage sievefilters/output
 */
class Hm_Output_sievefilters_settings_link extends Hm_Output_Module {
    protected function output() {
        $res = '<li class="menu_profiles"><a class="unread_link" href="?page=sieve_filters">';
        if (!$this->get('hide_folder_icons')) {
            $res .= '<img class="account_icon" src="'.$this->html_safe(Hm_Image_Sources::$book).'" alt="" width="16" height="16" /> ';
        }
        $res .= $this->trans('Filters').'</a></li>';
        $res .= '<li class="menu_profiles"><a class="unread_link" href="?page=block_list">';
        if (!$this->get('hide_folder_icons')) {
            $res .= '<img class="account_icon" src="'.$this->html_safe(Hm_Image_Sources::$circle_x).'" alt="" width="16" height="16" /> ';
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
        $res = '<div class="sievefilters_settings"><div class="content_title">'.$this->trans('Filters').'</div>';
        $res .= '<script type="text/css" src="'.WEB_ROOT.'modules/sievefilters/assets/tingle.min.css"></script>';
        return $res;
    }
}

/**
 * @subpackage sievefilters/output
 */
class Hm_Output_blocklist_settings_start extends Hm_Output_Module {
    protected function output() {
        $socked_connected = $this->get('socket_connected', false);
        $res = '<div class="sievefilters_settings"><div class="content_title">'.$this->trans('Block List').'</div>';
        $res .= '<script type="text/css" src="'.WEB_ROOT.'modules/sievefilters/assets/tingle.min.css"></script>';
        return $res;
    }
}

function get_blocked_senders_array($mailbox) {
    $sieve_options = explode(':', $mailbox['sieve_config_host']);
    $client = new \PhpSieveManager\ManageSieve\Client($sieve_options[0], $sieve_options[1]);

    try {
        $client->connect($mailbox['user'], $mailbox['pass'], false, "", "PLAIN");
    } catch (Exception $e) {
        return [];
    }
    $scripts = $client->listScripts();

    if (!array_search('blocked_senders', $scripts, true)) {
        return [];
    }

    $current_script = $client->getScript('blocked_senders');
    if ($current_script != '') {
        $base64_obj = str_replace("# ", "", preg_split('#\r?\n#', $current_script, 0)[1]);
        $blocked_list = json_decode(base64_decode($base64_obj));
        if (!$blocked_list) {
            return [];
        }
        foreach ($blocked_list as $blocked_sender) {
            if (explode('@', $blocked_sender)[0] == '') {
                $blocked_sender = '*'.$blocked_sender;
            }
            $blocked_senders[] = $blocked_sender;
        }
    }
    return $blocked_senders;
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
    }
}

/**
 * @subpackage sievefilters/output
 */
class Hm_Output_blocklist_settings_accounts extends Hm_Output_Module {
    protected function output() {
        $mailboxes = $this->get('imap_accounts', array());
        $res = get_classic_filter_modal_content();
        $res .= get_script_modal_content();
        foreach($mailboxes as $idx => $mailbox) {
            $behaviours = $this->get('sieve_block_default_behaviour');
            $default_behaviour = 'Discard';
            if (array_key_exists($idx, $behaviours)) {
                $default_behaviour = $behaviours[$idx];
            }
            if (isset($mailbox['sieve_config_host'])) {
                if ($default_behaviour == 'Discard') {
                    $default_behaviour_html = 'Default Behaviour: <select class="select_default_behaviour" imap_account="'.$idx.'"><option value="Discard">Discard</option><option value="Reject">Bounce</option></select>';
                }
                elseif ($default_behaviour == 'Reject') {
                    $default_behaviour_html = 'Default Behaviour: <select class="select_default_behaviour" imap_account="'.$idx.'"><option value="Discard">Discard</option><option value="Reject" selected>Bounce</option></select>';
                }
                $num_blocked = sizeof(get_blocked_senders_array($mailbox));
                $res .= '<div class="sievefilters_accounts_item">';
                $res .= '<div class="sievefilters_accounts_title settings_subtitle">' . $mailbox['name'];
                $res .= '<span class="filters_count"><span id="filter_num_'.$idx.'">'.$num_blocked.'</span> '.$this->trans('blocked'). '</span></div>';
                $res .= '<div class="sievefilters_accounts filter_block" style="display: none;"><div class="filter_subblock">';
                $res .=  $default_behaviour_html;
                $res .= '<table class="filter_details"><tbody>';
                $res .= '<tr><th style="width: 80px;">Sender</th><th style="width: 15%;">Actions</th></tr>';
                $res .= get_blocked_senders($mailbox, $idx, $this->html_safe(Hm_Image_Sources::$minus), $this->html_safe(Hm_Image_Sources::$globe));
                $res .= '</tbody></table>';
                $res .= '</div></div></div>';
            }
        }
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
            if (isset($mailbox['sieve_config_host'])) {
                $num_filters = sizeof(get_mailbox_filters($mailbox));
                $res .= '<div class="sievefilters_accounts_item">';
                $res .= '<div class="sievefilters_accounts_title settings_subtitle">' . $mailbox['name'];
                $res .= '<span class="filters_count">' . sprintf($this->trans('%s filters'), $num_filters) . '</span></div>';
                $res .= '<div class="sievefilters_accounts filter_block" style="display: none;"><div class="filter_subblock">';
                $res .= '<button class="add_filter" account="'.$mailbox['name'].'">Add Filter</button> <button  account="'.$mailbox['name'].'" class="add_script">Add Script</button>';
                $res .= '<table class="filter_details"><tbody>';
                $res .= '<tr><th style="width: 80px;">Priority</th><th>Name</th><th style="width: 15%;">Actions</th></tr>';
                $res .= get_mailbox_filters($mailbox, true);
                $res .= '</tbody></table>';
                $res .= '<div style="height: 40px; margin-bottom: 10px; display: none;">
                                <div style="width: 90%;">
                                    <h3 style="margin-bottom: 2px;">If conditions are not met</h3>
                                    <small>Define the actions if conditions are not met. If no actions are provided the next filter will be executed. If there are no other filters to be executed, the email will be delivered as expected.</small>
                                </div>
                            </div>
                            <div style="background-color: #f7f2ef; margin-top: 25px; width: 90%; display: none;">
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
                <b>Filter Name:</b><input type="text" class="modal_sieve_filter_name" placeholder="Your filter name" style="margin-left: 10px;" /> 
                <b style="margin-left: 20px;">Priority:</b><input class="modal_sieve_filter_priority" type="number" placeholder="0" style="margin-left: 10px;" /> 
                <b style="margin-left: 20px;">Test:</b>
                    <select class="modal_sieve_filter_test" name="test_type" placeholder="0" style="margin-left: 10px;"> 
                        <option value="ANYOF">ANYOF (OR)</option>
                        <option value="ALLOF" selected>ALLOF (AND)</option>
                    </select>
            </div>
            <div style="display: flex; height: 70px; margin-bottom: 10px;">
                <div style="width: 100%;">
                    <h3 style="margin-bottom: 2px;">Conditions & Actions</h3>
                    <small>Filters must have at least one action and one condition</small>
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
        try {
            $sieve_options = explode(':', $mailbox['sieve_config_host']);
            $client = new \PhpSieveManager\ManageSieve\Client($sieve_options[0], $sieve_options[1]);
            $client->connect($mailbox['user'], $mailbox['pass'], false, "", "PLAIN");
            $scripts = [];
            foreach ($client->listScripts() as $script) {
                if (strstr($script, 'cypht')) {
                    $scripts[] = $script;
                }
            }
        } catch (PhpSieveManager\Exceptions\SocketException $e) {
            return '';
        }

        if ($html == false) {
            return $scripts;
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

if (!hm_exists('generate_main_script')) {
    function generate_main_script($script_list)
    {
        $sorted_list = [];
        foreach ($script_list as $script_name) {
            if ($script_name == 'main_script') {
                continue;
            }

            if (strstr($script_name, 'cypht')) {
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

if (!hm_exists('generate_script_name')) {
    function generate_script_name($name, $priority)
    {
        return str_replace(' ', '_', strtolower($name)).'-'.$priority.'-cypht';
    }
}

if (!hm_exists('generate_filter_name')) {
    function generate_filter_name($name, $priority)
    {
        return str_replace(' ', '_', strtolower($name)).'-'.$priority.'-cyphtfilter';
    }
}
