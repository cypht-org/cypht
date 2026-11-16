<?php

/**
 * Bounce handling module set
 * @package modules
 * @subpackage bounces
 */

if (!defined('DEBUG_MODE')) { die(); }

require_once APP_PATH.'modules/core/message_functions.php';
require_once APP_PATH.'modules/bounces/functions.php';

/**
 * Record the bounces reported by a message when its content is retrieved
 * (opened or pre-fetched), and keep the result in the cache for the banner
 * @subpackage bounces/handler
 */
class Hm_Handler_bounce_message_check extends Hm_Handler_Module {
    public function process() {
        if (!$this->user_config->get('bounce_tracking_setting', true)) {
            return;
        }
        $headers = $this->get('msg_headers', []);
        if (!is_array($headers) || !bounce_headers_look_like_bounce($headers)) {
            return;
        }
        list($success, $form) = $this->process_form(['imap_server_id', 'imap_msg_uid', 'folder']);
        if (!$success) {
            return;
        }
        $cacheKey = 'bounces_'.$form['imap_server_id'].'_'.$form['folder'].'_'.$form['imap_msg_uid'];
        $results = $this->cache->get($cacheKey, false);
        if ($results === false) {
            $results = $this->checkMessage($form, $headers);
            if ($results === false) {
                return;
            }
            $this->cache->set($cacheKey, $results);
        }
        $this->out('bounce_results', $results);
    }

    private function checkMessage($form, $headers) {
        $mailbox = Hm_IMAP_List::get_connected_mailbox($form['imap_server_id'], $this->cache);
        if (!$mailbox || !$mailbox->authed()) {
            return false;
        }
        $raw = $mailbox->get_message_content(hex2bin($form['folder']), $form['imap_msg_uid'], 0);
        $parser = new Hm_Bounce_Parser();
        $events = $parser->parse((string) $raw, $headers);
        if (!$events) {
            return [];
        }
        $results = get_bounce_service($this->config, $this->user_config)->recordEvents($events);
        if (array_filter(array_column($results, 'recorded'))) {
            $this->session->record_unsaved('Bounce recorded');
        }
        return $results;
    }
}

/**
 * Bounce banner above the message
 * @subpackage bounces/output
 */
class Hm_Output_bounce_message_banner extends Hm_Output_Module {
    protected function output() {
        $results = $this->get('bounce_results', []);
        $headers = $this->get('msg_headers');
        if (!$results || !is_string($headers)) {
            return;
        }
        $lines = [];
        foreach ($results as $result) {
            $event = $result['event'];
            $text = $event['type'] === Hm_Bounce_Policy::TYPE_HARD ? 'Delivery failed permanently to' : 'Delivery failed temporarily to';
            $line = $this->trans($text).' <b>'.$this->html_safe($event['email']).'</b>';
            if ($event['status']) {
                $line .= ' ('.$this->html_safe($event['status']).')';
            }
            $address = $result['address'] ?? [];
            if ($address && $address['status'] !== Hm_Bounce_Policy::STATUS_ACTIVE) {
                $line .= '. '.$this->trans('You will be warned before writing to this address again.');
            }
            $lines[] = $line;
        }
        $banner = '<div class="alert alert-warning bounce_banner mt-2 mb-0 py-2" role="alert">'.
            '<i class="bi bi-envelope-exclamation me-2"></i>'.implode('<br />', $lines).
            ' <a class="ms-2" href="'.$this->build_page_url('bounces').'">'.$this->trans('Bounced addresses').'</a></div>';
        $this->concat('msg_headers', $banner);
    }
}

/**
 * Bounce history of the recipients being written to
 * @subpackage bounces/handler
 */
class Hm_Handler_check_bounce_recipients extends Hm_Handler_Module {
    public function process() {
        if (!$this->user_config->get('bounce_tracking_setting', true)) {
            return;
        }
        $service = get_bounce_service($this->config, $this->user_config);
        $flagged = [];
        foreach (['compose_to', 'compose_cc', 'compose_bcc'] as $field) {
            if (empty($this->request->post[$field])) {
                continue;
            }
            foreach (process_address_fld($this->request->post[$field]) as $recipient) {
                $address = $service->getAddress($recipient['email']);
                if ($address && ($address['hard_total'] > 0 || $service->isSuppressed($recipient['email']))) {
                    $flagged[$address['email']] = $address;
                }
            }
        }
        $this->out('bounce_recipients', array_values($flagged));
    }
}

/**
 * Compose warning for recipients that bounced
 * @subpackage bounces/output
 */
class Hm_Output_filter_bounce_recipients extends Hm_Output_Module {
    protected function output() {
        $lines = [];
        foreach ($this->get('bounce_recipients', []) as $address) {
            $line = '<b>'.$this->html_safe($address['email']).'</b>: '.
                sprintf($this->trans('%d permanent and %d temporary delivery failures'), $address['hard_total'], $address['soft_total']).
                ', '.$this->trans('last on').' '.$this->html_safe(date('Y-m-d', (int) $address['last_bounce']));
            if ($address['status'] === Hm_Bounce_Policy::STATUS_REMOVED) {
                $line .= '. '.$this->trans('This address keeps bouncing.');
            }
            $lines[] = $line;
        }
        $html = '';
        if ($lines) {
            $html = '<div class="alert alert-warning py-2 mb-2" role="alert"><i class="bi bi-envelope-exclamation me-2"></i>'.
                $this->trans('Some recipients bounced before').':<br />'.implode('<br />', $lines).'</div>';
        }
        $this->out('bounce_recipients_display', $html);
    }
}

/**
 * Placeholder for the compose warning, filled by the bounces JavaScript
 * @subpackage bounces/output
 */
class Hm_Output_bounce_recipients_container extends Hm_Output_Module {
    protected function output() {
        if (!$this->get('bounce_tracking_enabled', true)) {
            return '';
        }
        return '<div class="bounce_recipients_status"></div>';
    }
}

/**
 * @subpackage bounces/handler
 */
class Hm_Handler_load_bounce_tracking_setting extends Hm_Handler_Module {
    public function process() {
        $this->out('bounce_tracking_enabled', (bool) $this->user_config->get('bounce_tracking_setting', true));
    }
}

/**
 * Reset or delete an address from the bounces page
 * @subpackage bounces/handler
 */
class Hm_Handler_process_bounce_action extends Hm_Handler_Module {
    public function process() {
        list($success, $form) = $this->process_form(['bounce_email', 'bounce_action']);
        if (!$success) {
            return;
        }
        $service = get_bounce_service($this->config, $this->user_config);
        if ($form['bounce_action'] === 'reset' && $service->reset($form['bounce_email'])) {
            Hm_Msgs::add('Bounce history reset');
        } elseif ($form['bounce_action'] === 'delete' && $service->getAddress($form['bounce_email'])) {
            $service->delete($form['bounce_email']);
            Hm_Msgs::add('Address removed from the bounce list');
        } else {
            return;
        }
        $this->session->record_unsaved('Bounce list updated');
    }
}

/**
 * @subpackage bounces/handler
 */
class Hm_Handler_load_bounce_addresses extends Hm_Handler_Module {
    public function process() {
        $addresses = get_bounce_service($this->config, $this->user_config)->listAddresses();
        usort($addresses, function($a, $b) {
            return $b['last_bounce'] <=> $a['last_bounce'];
        });
        $this->out('bounce_addresses', $addresses);
    }
}

/**
 * Bounced addresses page
 * @subpackage bounces/output
 */
class Hm_Output_bounce_addresses_page extends Hm_Output_Module {
    protected function output() {
        $res = '<div class="bounces_page p-0"><div class="content_title px-3">'.$this->trans('Bounced addresses').'</div><div class="p-3">';
        $addresses = $this->get('bounce_addresses', []);
        if (!$addresses) {
            return $res.'<p class="text-muted">'.$this->trans('No bounced address recorded yet. Opening a delivery failure report adds the addresses it reports.').'</p></div></div>';
        }
        $statuses = [
            Hm_Bounce_Policy::STATUS_ACTIVE => $this->trans('Bounced'),
            Hm_Bounce_Policy::STATUS_PAUSED => $this->trans('Paused'),
            Hm_Bounce_Policy::STATUS_REMOVED => $this->trans('Keeps bouncing'),
        ];
        $res .= '<table class="table table-sm bounce_addresses"><thead><tr><th>'.$this->trans('Address').'</th><th>'.$this->trans('Status').
            '</th><th>'.$this->trans('Permanent').'</th><th>'.$this->trans('Temporary').'</th><th>'.$this->trans('Last bounce').'</th><th></th></tr></thead><tbody>';
        foreach ($addresses as $address) {
            $status = $statuses[$address['status']] ?? $address['status'];
            if ($address['status'] === Hm_Bounce_Policy::STATUS_PAUSED && $address['paused_until']) {
                $status .= ' '.$this->trans('until').' '.date('Y-m-d H:i', (int) $address['paused_until']);
            }
            $res .= '<tr><td>'.$this->html_safe($address['email']).'</td><td>'.$this->html_safe($status).'</td><td>'.
                (int) $address['hard_total'].'</td><td>'.(int) $address['soft_total'].'</td><td>'.
                $this->html_safe(date('Y-m-d H:i', (int) $address['last_bounce'])).'</td><td class="text-end text-nowrap">'.
                $this->actionForm($address['email'], 'reset', 'Reset', 'btn-outline-secondary').' '.
                $this->actionForm($address['email'], 'delete', 'Delete', 'btn-outline-danger').'</td></tr>';
        }
        return $res.'</tbody></table></div></div>';
    }

    private function actionForm($email, $action, $label, $class) {
        return '<form method="POST" class="d-inline"><input type="hidden" name="hm_page_key" value="'.$this->html_safe(Hm_Request_Key::generate()).'" />'.
            '<input type="hidden" name="bounce_email" value="'.$this->html_safe($email).'" />'.
            '<input type="hidden" name="bounce_action" value="'.$action.'" />'.
            '<button type="submit" class="btn btn-sm '.$class.'">'.$this->trans($label).'</button></form>';
    }
}

/**
 * @subpackage bounces/output
 */
class Hm_Output_bounces_page_link extends Hm_Output_Module {
    protected function output() {
        $res = '<li class="menu_bounces"><a class="unread_link" href="'.$this->build_page_url('bounces').'">';
        if (!$this->get('hide_folder_icons')) {
            $res .= '<i class="bi bi-envelope-exclamation me-2"></i>';
        }
        $res .= $this->trans('Bounced addresses').'</a></li>';
        if ($this->format == 'HTML5') {
            return $res;
        }
        $this->concat('formatted_folder_list', $res);
    }
}

/**
 * @subpackage bounces/handler
 */
class Hm_Handler_process_bounce_tracking_setting extends Hm_Handler_Module {
    public function process() {
        process_site_setting('bounce_tracking', $this, 'bounce_tracking_setting_callback', true, true);
    }
}

if (!hm_exists('bounce_tracking_setting_callback')) {
    function bounce_tracking_setting_callback($val) {
        return (bool) $val;
    }
}

/**
 * @subpackage bounces/output
 */
class Hm_Output_bounce_tracking_setting extends Hm_Output_Module {
    protected function output() {
        $settings = $this->get('user_settings', []);
        $enabled = !array_key_exists('bounce_tracking_setting', $settings) || $settings['bounce_tracking_setting'];
        return '<tr class="general_setting"><td><label for="bounce_tracking">'.$this->trans('Track bounced addresses').'</label></td>'.
            '<td><input class="form-check-input" type="checkbox" id="bounce_tracking" name="bounce_tracking" value="1"'.
            ($enabled ? ' checked="checked"' : '').' /></td></tr>';
    }
}
