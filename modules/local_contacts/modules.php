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
        # TODO: split this out
        if (array_key_exists('contact_id', $this->request->get)) {
            $contact = $contacts->get($this->request->get['contact_id']);
            if (is_object($contact)) {
                $current = $contact->export();
                $current['id'] = $this->request->get['contact_id'];
                $this->out('current_contact', $current);
            }
        }
        $this->out('contact_store', $contacts, false);
    }
}

/**
 * @subpackage contacts/output
 */
class Hm_Output_contacts_form extends Hm_Output_Module {
    protected function output() {

        $email = '';
        $name = '';
        $phone = '';
        $button = '<input class="add_contact_submit" type="submit" name="add_contact" value="'.$this->trans('Add').'" />';
        $title = $this->trans('Add Contact');
        $current = $this->get('current_contact', array());
        if (!empty($current)) {
            if (array_key_exists('email_address', $current)) {
                $email = $current['email_address'];
            }
            if (array_key_exists('display_name', $current)) {
                $name = $current['display_name'];
            }
            if (array_key_exists('phone_number', $current)) {
                $phone = $current['phone_number'];
            }
            $title = $this->trans('Update Contact');
            $button = '<input type="hidden" name="contact_id" value="'.$this->html_safe($current['id']).'" />'.
                '<input class="edit_contact_submit" type="submit" name="edit_contact" value="'.$this->trans('Update').'" />';
        }
        return '<div class="server_title">'.$title.'</div>'.
            '<input type="hidden" name="contact_source" value="local" />'.
            '<input type="hidden" name="hm_page_key" value="'.$this->html_safe(Hm_Request_Key::generate()).'" />'.
            '<label class="screen_reader" for="contact_email">'.$this->trans('E-mail Address').'</label>'.
            '<input autofocus required placeholder="'.$this->trans('E-mail Address').'" id="contact_email" type="email" name="contact_email" '.
            'value="'.$this->html_safe($email).'" /> *<br />'.
            '<label class="screen_reader" for="contact_name">'.$this->trans('Full Name').'</label>'.
            '<input required placeholder="'.$this->trans('Full Name').'" id="contact_name" type="text" name="contact_name" '.
            'value="'.$this->html_safe($name).'" /> *<br />'.
            '<label class="screen_reader" for="contact_phone">'.$this->trans('Telephone Number').'</label>'.
            '<input placeholder="'.$this->trans('Telephone Number').'" id="contact_phone" type="text" name="contact_phone" '.
            'value="'.$this->html_safe($phone).'" /><br />'.$button.' <input type="button" class="reset_contact" value="'.$this->trans('Reset').'" />';
    }
}
