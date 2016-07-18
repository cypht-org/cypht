<?php

/**
 * Local contact modules
 * @package modules
 * @subpackage local_contacts
 */

if (!defined('DEBUG_MODE')) { die(); }

/**
 * @subpackage local_contacts/handler
 */
class Hm_Handler_process_add_contact extends Hm_Handler_Module {
    public function process() {
        $contacts = $this->get('contact_store');
        list($success, $form) = $this->process_form(array('contact_email', 'contact_name', 'add_contact'));
        if ($success) {
            $details = array('email_address' => $form['contact_email'], 'display_name' => $form['contact_name']);
            if (array_key_exists('contact_phone', $this->request->post) && $this->request->post['contact_phone']) {
                $details['phone_number'] = $this->request->post['contact_phone'];
            }
            $contacts->add_contact($details);
            $this->user_config->set('contacts', $contacts->export());
            $this->session->record_unsaved('Contact added');
            Hm_Msgs::add('Contact Added');
        }
    }
}

/**
 * @subpackage local_contacts/handler
 */
class Hm_Handler_process_edit_contact extends Hm_Handler_Module {
    public function process() {
        $contacts = $this->get('contact_store');
        list($success, $form) = $this->process_form(array('contact_id', 'contact_email', 'contact_name', 'edit_contact'));
        if ($success) {
            $details = array('email_address' => $form['contact_email'], 'display_name' => $form['contact_name']);
            if (array_key_exists('contact_phone', $this->request->post)) {
                $details['phone_number'] = $this->request->post['contact_phone'];
            }
            if ($contacts->update_contact($form['contact_id'], $details)) {
                $this->user_config->set('contacts', $contacts->export());
                $this->session->record_unsaved('Contact updated');
                Hm_Msgs::add('Contact Updated');
            }
        }
    }
}

/**
 * @subpackage local_contacts/handler
 */
class Hm_Handler_load_local_contacts extends Hm_Handler_Module {
    public function process() {
        $contacts = $this->get('contact_store');
        $contacts->import($this->user_config->get('contacts', array()));
        # TODO: split this out, add source to the form for other backends
        if (array_key_exists('contact_id', $this->request->get)) {
            $contact = $contacts->get($this->request->get['contact_id']);
            if (is_object($contact)) {
                $current = $contact->export();
                $current['id'] = $this->request->get['contact_id'];
                $this->out('current_contact', $current);
            }
        }
        $this->out('contact_store', $contacts);
    }
}
