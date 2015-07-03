<?php

/**
 * Contact modules
 * @package modules
 * @subpackage contacts
 */

if (!defined('DEBUG_MODE')) { die(); }

require APP_PATH.'modules/contacts/hm-contacts.php';

/**
 * @subpackage contacts/handler
 */
class Hm_Handler_process_send_to_contact extends Hm_Handler_Module {
    public function process() {
        if (array_key_exists('contact_id', $this->request->get)) {
            $contacts = new Hm_Contact_Store($this->user_config);
            $contact = $contacts->get($this->request->get['contact_id']);
            $to = sprintf('"%s" <%s>', $contact->value('display_name'), $contact->value('email_address'));
            $this->out('compose_draft', array('draft_to' => $to, 'draft_subject' => '', 'draft_body' => ''));
        }
    }
}

/**
 * @subpackage contacts/handler
 */
class Hm_Handler_load_contacts extends Hm_Handler_Module {
    public function process() {
        $this->out('contact_store', new Hm_Contact_Store($this->user_config));
    }
}

/**
 * @subpackage contacts/handler
 */
class Hm_Handler_process_delete_contact extends Hm_Handler_Module {
    public function process() {
        $contacts = $this->get('contact_store');
        list($success, $form) = $this->process_form(array('contact_id'));
        if ($success) {
            $contacts->delete($form['contact_id']);
            $contacts->save($this->user_config);
            $this->session->record_unsaved('Contact deleted');
            Hm_Msgs::add('Contact Deleted');
        }
    }
}

/**
 * @subpackage contacts/handler
 */
class Hm_Handler_process_add_contact extends Hm_Handler_Module {
    public function process() {
        $contacts = $this->get('contact_store');
        list($success, $form) = $this->process_form(array('contact_email', 'contact_name'));
        if ($success) {
            $details = array('email_address' => $form['contact_email'], 'display_name' => $form['contact_name']);
            if (array_key_exists('contact_phone', $this->request->post) && $this->request->post['contact_phone']) {
                $details['phone_number'] = $this->request->post['contact_phone'];
            }
            $contacts->add_contact($details);
            $contacts->save($this->user_config);
            $this->session->record_unsaved('Contact added');
            Hm_Msgs::add('Contact Added');
        }
    }
}

/**
 * @subpackage contacts/output
 */
class Hm_Output_contacts_page_link extends Hm_Output_Module {
    protected function output() {
        $res = '<li class="menu_contacts"><a class="unread_link" href="?page=contacts">'.
            '<img class="account_icon" src="'.$this->html_safe(Hm_Image_Sources::$people).'" alt="" width="16" height="16" /> '.$this->trans('Contacts').'</a></li>';
        if ($this->format == 'HTML5') {
            return $res;
        }
        $this->concat('formatted_folder_list', $res);
    }
}

/**
 * @subpackage contacts/output
 */
class Hm_Output_contacts_content_start extends Hm_Output_Module {
    protected function output() {
        return '<div class="contacts_content"><div class="content_title">'.$this->trans('Contacts').'</div>';
    }
}

/**
 * @subpackage contacts/output
 */
class Hm_Output_contacts_content_end extends Hm_Output_Module {
    protected function output() {
        return '</div>';
    }
}

/**
 * @subpackage contacts/output
 */
class Hm_Output_contacts_list extends Hm_Output_Module {
    protected function output() {
        $res = '<table class="contact_list">';
        $res .= '<tr><td colspan="4" class="contact_list_title"><div class="server_title">'.$this->trans('Your Contacts').'</div></td></tr>';
        $contacts = $this->get('contact_store');
        if ($contacts) {
            foreach ($contacts->page(1, 10) as $id => $contact) {
                $res .= '<tr class="contact_row_'.$this->html_safe($id).'">'.
                    '<td>'.$this->html_safe($contact->value('display_name')).'</td>'.
                    '<td>'.$this->html_safe($contact->value('email_address')).'</td>'.
                    '<td>'.$this->html_safe($contact->value('phone_number')).'</td>'.
                    '<td class="contact_controls">'.
                        '<a data-id="'.$this->html_safe($id).'" class="delete_contact" title="Delete"><img alt="'.$this->trans('Delete').'" width="16" height="16" src="'.Hm_Image_Sources::$circle_x.'" /></a>'.
                        '<a href="?page=compose&contact_id='.$this->html_safe($id).'" class="send_to_contact" title="Send to"><img alt="'.$this->trans('Send To').'" width="16" height="16" src="'.Hm_Image_Sources::$doc.'" /></a>'.
                    '</td>'.
                    '</tr>';
            }
        }
        $res .= '</table>';
        return $res;
    }
}

/**
 * @subpackage contacts/output
 */
class Hm_Output_contacts_content_add_form extends Hm_Output_Module {
    protected function output() {
        return '<div class="add_server"><div class="server_title">'.$this->trans('Add').'</div>'.
            '<form class="add_contact_form" method="POST">'.
            '<input type="hidden" name="hm_page_key" value="'.$this->html_safe(Hm_Request_Key::generate()).'" />'.
            '<label class="screen_reader" for="contact_email">'.$this->trans('E-mail Address').'</label>'.
            '<input autofocus required placeholder="'.$this->trans('E-mail Address').'" id="contact_email" type="email" name="contact_email" /> *<br />'.
            '<label class="screen_reader" for="contact_name">'.$this->trans('Full Name').'</label>'.
            '<input required placeholder="'.$this->trans('Full Name').'" id="contact_name" type="text" name="contact_name" /> *<br />'.
            '<label class="screen_reader" for="contact_phone">'.$this->trans('Telephone Number').'</label>'.
            '<input placeholder="'.$this->trans('Telephone Number').'" id="contact_phone" type="text" name="contact_phone" /><br />'.
            '<input class="add_contact_submit" type="submit" name="add_contact" value="'.$this->trans('Add').'" />'.
            '</form></div>';
    }
}

