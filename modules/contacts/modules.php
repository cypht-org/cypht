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
class Hm_Handler_autocomplete_contact extends Hm_Handler_Module {
    public function process() {
        list($success, $form) = $this->process_form(array('contact_value'));
        $results = array();
        if ($success) {
            $val = trim($form['contact_value']);
            $contacts = $this->get('contact_store');
            $contacts->sort('email_address');
            $results = array_slice($contacts->search(array(
                'display_name' => $val,
                'email_address' => $val
            )), 0, 10);
        }
        $this->out('contact_suggestions', $results);
    }
}

/**
 * @subpackage contacts/handler
 */
class Hm_Handler_find_message_contacts extends Hm_Handler_Module {
    public function process() {
        $contacts = array();
        $existing = $this->get('contact_store');
        $addr_headers = array('to', 'cc', 'bcc', 'sender', 'reply-to', 'from');
        $headers = $this->get('msg_headers', array());
        $addresses = array();
        foreach ($headers as $name => $value) {
            if (in_array(strtolower($name), $addr_headers, true)) {
                foreach (Hm_Address_Field::parse($value) as $vals) {
                    if (!$existing->search(array('email_address' => $vals['email']))) {
                        $addresses[] = $vals;
                    }
                }
            }
        }
        $this->out('contact_addresses', $addresses);
    }
}

/**
 * @subpackage contacts/handler
 */
class Hm_Handler_process_send_to_contact extends Hm_Handler_Module {
    public function process() {
        if (array_key_exists('contact_id', $this->request->get)) {
            $contacts = $this->get('contact_store');
            $contact = $contacts->get($this->request->get['contact_id']);
            if ($contact) {
                $to = sprintf('"%s" <%s>', $contact->value('display_name'), $contact->value('email_address'));
                $this->out('compose_draft', array('draft_to' => $to, 'draft_subject' => '', 'draft_body' => ''));
            }
        }
    }
}

/**
 * @subpackage contacts/handler
 */
class Hm_Handler_load_contacts extends Hm_Handler_Module {
    public function process() {
        $contacts = new Hm_Contact_Store();
        $page = 1;
        if (array_key_exists('contact_page', $this->request->get)) {
            $page = $this->request->get['contact_page'];
        }
        $this->out('contact_page', $page);
        $this->out('contact_store', $contacts, false);
    }
}

/**
 * @subpackage contacts/handler
 */
class Hm_Handler_process_add_contact_from_message extends Hm_Handler_Module {
    public function process() {
        list($success, $form) = $this->process_form(array('contact_value'));
        if ($success) {
            $addresses = Hm_Address_Field::parse($form['contact_value']);
            if (!empty($addresses)) {
                $contacts = $this->get('contact_store');
                foreach ($addresses as $vals) {
                    $contacts->add_contact(array('email_address' => $vals['email'], 'display_name' => $vals['name']));
                }
                $this->user_config->set('contacts', $contacts->export());
                $this->session->record_unsaved('Contact added');
                Hm_Msgs::add('Contact Added');
            }
        }
    }
}

/**
 * @subpackage contacts/output
 */
class Hm_Output_contacts_page_link extends Hm_Output_Module {
    protected function output() {
        $res = '<li class="menu_contacts"><a class="unread_link" href="?page=contacts">'.
            '<img class="account_icon" src="'.$this->html_safe(Hm_Image_Sources::$people).
            '" alt="" width="16" height="16" /> '.$this->trans('Contacts').'</a></li>';
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
class Hm_Output_add_message_contacts extends Hm_Output_Module {
    protected function output() {
        $addresses = $this->get('contact_addresses');
        $headers = $this->get('msg_headers');
        if (!empty($addresses)) {
            $res = '<div class="add_contact_row"><a href="#" onclick="$(\'.add_contact_controls\').toggle(); return false;">'.
                '<img width="20" height="20" src="'.Hm_Image_Sources::$people.'" alt="'.$this->trans('Add').'" title="'.
                $this->html_safe('Add Contact').'" /></a><span class="add_contact_controls"><select id="add_contact">';
            foreach ($addresses as $vals) {
                $res .= '<option value="'.$this->html_safe($vals['name']).' '.$this->html_safe($vals['email']).
                    '">'.$this->html_safe($vals['name']).' &lt;'.$this->html_safe($vals['email']).'&gt;</option>';
            }
            $res .= '</select> <input onclick="return add_contact_from_message_view()" class="add_contact_button" '.
                'type="button" value="'.$this->trans('Add').'"></span></div>';
            $headers = $headers.$res;
        }
        $this->out('msg_headers', $headers, false);
    }
}

/**
 * @subpackage contacts/output
 */
class Hm_Output_contacts_list extends Hm_Output_Module {
    protected function output() {
        if (count($this->get('contact_sources', array())) == 0) {
            return '<div class="no_contact_sources">'.$this->trans('No contact backends are enabled!').
                '<br />'.$this->trans('At least one backend must be enabled in the hm3.ini file to use contacts.').'</div>';
        }
        $per_page = 25;
        $current_page = $this->get('contact_page', 1);
        $res = '<table class="contact_list">';
        $res .= '<tr><td colspan="5" class="contact_list_title"><div class="server_title">'.$this->trans('Contacts').'</div></td></tr>';
        $contacts = $this->get('contact_store');
        $editable = $this->get('contact_edit', array());
        if ($contacts) {
            $total = count($contacts->dump());
            $contacts->sort('email_address');
            foreach ($contacts->page($current_page, $per_page) as $id => $contact) {
                $res .= '<tr class="contact_row_'.$this->html_safe($id).'">'.
                    '<td>'.($contact->value('source') ? $this->html_safe($contact->value('source')) : $this->trans('local')).'</td>'.
                    '<td>'.$this->html_safe($contact->value('display_name')).'</td>'.
                    '<td>'.$this->html_safe($contact->value('email_address')).'</td>'.
                    '<td>'.$this->html_safe($contact->value('phone_number')).'</td>'.
                    '<td class="contact_controls">';
                if (in_array($contact->value('source'), $editable, true)) {
                    $res .= '<a data-id="'.$this->html_safe($id).'" class="delete_contact" title="'.$this->trans('Delete').'">'.
                        '<img alt="'.$this->trans('Delete').'" width="16" height="16" src="'.Hm_Image_Sources::$circle_x.'" /></a>'.
                        '<a href="?page=contacts&amp;contact_id='.$this->html_safe($id).'&amp;contact_source='.$this->html_safe($contact->value('source')).'&amp;contact_page='.$current_page.
                        '" class="edit_contact" title="'.$this->trans('Edit').'"><img alt="'.$this->trans('Edit').
                        '" width="16" height="16" src="'.Hm_Image_Sources::$cog.'" /></a>';
                }
                $res .= '<a href="?page=compose&amp;contact_id='.$this->html_safe($id).'" class="send_to_contact" title="'.$this->trans('Send To').'">'.
                    '<img alt="'.$this->trans('Send To').'" width="16" height="16" src="'.Hm_Image_Sources::$doc.'" /></a></td></tr>';
            }
            $res .= '<tr><td class="contact_pages" colspan="5">';
            if ($current_page > 1) {
                $res .= '<a href="?page=contacts&contact_page='.($current_page-1).'">Previous</a>';
            }
            if ($total > ($current_page * $per_page)) {
                $res .= ' <a href="?page=contacts&contact_page='.($current_page+1).'">Next</a>';
            }
            $res .= '</td></tr>';
        }
        $res .= '</table>';
        return $res;
    }
}

/**
 * @subpackage contacts/output
 */
class Hm_Output_filter_autocomplete_list extends Hm_Output_Module {
    protected function output() {
        $suggestions = array();
        foreach ($this->get('contact_suggestions', array()) as $contact) {
            $suggestions[] = $this->html_safe(sprintf(
                '"%s" %s', $contact->value('display_name'), $contact->value('email_address')
            ));
        }
        $this->out('contact_suggestions', $suggestions);
    }
}

/**
 * @subpackage contacts/output
 */
class Hm_Output_contacts_form_start extends Hm_Output_Module {
    protected function output() {
        if (count($this->get('contact_sources', array())) == 0 || count($this->get('contact_edit', array())) == 0) {
            return '';
        }
        return '<div class="add_server"><form class="add_contact_form" method="POST">';
    }
}

/**
 * @subpackage contacts/output
 */
class Hm_Output_contacts_form_end extends Hm_Output_Module {
    protected function output() {
        if (count($this->get('contact_sources', array())) == 0 || count($this->get('contact_edit', array())) == 0) {
            return '';
        }
        return '</form></div>';
    }
}
