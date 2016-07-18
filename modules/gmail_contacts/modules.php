<?php

/**
 * Gmail contact modules
 * @package modules
 * @subpackage gmail_contacts
 */

if (!defined('DEBUG_MODE')) { die(); }

require APP_PATH.'modules/gmail_contacts/hm-gmail-contacts.php';

/**
 * @subpackage gmail_contacts/handler
 */
class Hm_Handler_load_gmail_contacts extends Hm_Handler_Module {
    public function process() {
        $contacts = $this->get('contact_store');
        if ($this->module_is_supported('imap')) {
            $contacts = fetch_gmail_contacts($this->config, $contacts, $this->session);
        }
        $this->out('contact_store', $contacts);
    }
}
