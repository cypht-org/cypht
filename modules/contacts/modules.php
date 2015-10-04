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
            $contacts = new Hm_Contact_Store($this->user_config);
            $contacts = fetch_gmail_contacts($this->config, $contacts);
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
        $existing = new Hm_Contact_Store($this->user_config);
        $addr_headers = array('to', 'cc', 'bcc', 'sender', 'reply-to', 'from');
        $headers = $this->get('msg_headers');
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
            $contacts = new Hm_Contact_Store($this->user_config);
            $contacts = fetch_gmail_contacts($this->config, $contacts);
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
        $contacts = new Hm_Contact_Store($this->user_config);
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

/**
 * @subpackage contacts/handler
 */
class Hm_Handler_load_gmail_contacts extends Hm_Handler_Module {
    public function process() {
        if (strpos($this->config->get('modules', ''), 'imap') !== false) {
            $updated = false;
            $contact_store = new Hm_Contact_Store($this->user_config);
            $contact_store = fetch_gmail_contacts($this->config, $contact_store);
        }
        if (!empty($contact_store->dump())) {
            $this->out('gmail_contacts', $contact_store);
        }
        if ($updated > 0) {
            $servers = Hm_IMAP_List::dump(false, true);
            $this->user_config->set('imap_servers', $servers);
            $this->session->set('user_data', $this->user_config->dump());
        }
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
                $contacts->save($this->user_config);
                $this->session->record_unsaved('Contact added');
                Hm_Msgs::add('Contact Added');
            }
        }
    }
}

/**
 * @subpackage contacts/handler
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
                $contacts->save($this->user_config);
                $this->session->record_unsaved('Contact updated');
                Hm_Msgs::add('Contact Updated');
            }
        }
    }
}

/**
 * @subpackage contacts/handler
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
class Hm_Output_add_message_contacts extends Hm_Output_Module {
    protected function output() {
        $addresses = $this->get('contact_addresses');
        $headers = $this->get('msg_headers');
        if (!empty($addresses)) {
            $res = '<div class="add_contact_row"><a href="#" onclick="$(\'.add_contact_controls\').toggle(); return false;"><img width="20" height="20" src="'.Hm_Image_Sources::$people.'" alt="'.$this->trans('Add').'" title="'.$this->html_safe('Add Contact').'" /></a><span class="add_contact_controls"><select id="add_contact">';
            foreach ($addresses as $vals) {
                $res .= '<option value="'.$this->html_safe($vals['name']).' '.$this->html_safe($vals['email']).
                    '">'.$this->html_safe($vals['name']).' &lt;'.$this->html_safe($vals['email']).'&gt;</option>';
            }
            $res .= '</select> <input onclick="return add_contact_from_message_view()" class="add_contact_button" type="button" value="'.$this->trans('Add').'"></span></div>';
            $headers = $headers.$res;
        }
        $this->out('msg_headers', $headers);
    }
}

/**
 * @subpackage contacts/output
 */
class Hm_Output_gmail_contacts_list extends Hm_Output_Module {
    protected function output() {
        $contacts = $this->get('gmail_contacts');
        $res = '';
        if ($contacts) {
            $contacts->sort('email_address');
            $res .= '<table class="gmail_contacts contact_list">';
            $res .= '<tr><td colspan="5" class="contact_list_title"><div class="server_title">'.$this->trans('Gmail Contacts').'</div></td></tr>';
                foreach ($contacts->page(1, 20) as $id => $contact) {
                    if (!$contact->value('source')) {
                        continue;
                    }
                    $res .= '<tr class="contact_row_'.$this->html_safe($id).'">'.
                        '<td>'.$this->html_safe($contact->value('source')).'</td>'.
                        '<td>'.$this->html_safe($contact->value('display_name')).'</td>'.
                        '<td>'.$this->html_safe($contact->value('email_address')).'</td>'.
                        '<td>'.$this->html_safe($contact->value('phone_number')).'</td>'.
                        '<td class="contact_controls"><a href="?page=compose&amp;contact_id='.$this->html_safe($id).
                        '" class="send_to_contact" title="Send to"><img alt="'.$this->trans('Send To').
                        '" width="16" height="16" src="'.Hm_Image_Sources::$doc.'" /></a>'.
                        '</td>'.
                        '</tr>';
            }
            $res .= '</table>';
        }
        return $res;
    }
}

/**
 * @subpackage contacts/output
 */
class Hm_Output_contacts_list extends Hm_Output_Module {
    protected function output() {
        $res = '<table class="contact_list">';
        $res .= '<tr><td colspan="4" class="contact_list_title"><div class="server_title">'.$this->trans('Local Contacts').'</div></td></tr>';
        $contacts = $this->get('contact_store');
        $total = count($contacts->dump());
        $contacts->sort('email_address');
        if ($contacts) {
            foreach ($contacts->page(1, 20) as $id => $contact) {
                $res .= '<tr class="contact_row_'.$this->html_safe($id).'">'.
                    '<td>'.$this->html_safe($contact->value('display_name')).'</td>'.
                    '<td>'.$this->html_safe($contact->value('email_address')).'</td>'.
                    '<td>'.$this->html_safe($contact->value('phone_number')).'</td>'.
                    '<td class="contact_controls">'.
                        '<a data-id="'.$this->html_safe($id).'" class="delete_contact" title="Delete"><img alt="'.$this->trans('Delete').'" width="16" height="16" src="'.Hm_Image_Sources::$circle_x.'" /></a>'.
                        '<a href="?page=compose&amp;contact_id='.$this->html_safe($id).'" class="send_to_contact" title="Send to"><img alt="'.$this->trans('Send To').'" width="16" height="16" src="'.Hm_Image_Sources::$doc.'" /></a>'.
                        '<a href="?page=contacts&amp;contact_id='.$this->html_safe($id).'" class="delete_contact" title="Edit"><img alt="'.$this->trans('Edit').'" width="16" height="16" src="'.Hm_Image_Sources::$cog.'" /></a>'.
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
class Hm_Output_contacts_content_add_form extends Hm_Output_Module {
    protected function output() {

        $email = '';
        $name = '';
        $phone = '';
        $button = '<input class="add_contact_submit" type="submit" name="add_contact" value="'.$this->trans('Add').'" />';
        $title = $this->trans('Add Local Contact');
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
            $title = $this->trans('Update Local Contact');
            $button = '<input type="hidden" name="contact_id" value="'.$this->html_safe($current['id']).'" />'.
                '<input class="edit_contact_submit" type="submit" name="edit_contact" value="'.$this->trans('Update').'" />';
        }
        return '<div class="add_server"><div class="server_title">'.$title.'</div>'.
            '<form class="add_contact_form" method="POST">'.
            '<input type="hidden" name="hm_page_key" value="'.$this->html_safe(Hm_Request_Key::generate()).'" />'.
            '<label class="screen_reader" for="contact_email">'.$this->trans('E-mail Address').'</label>'.
            '<input autofocus required placeholder="'.$this->trans('E-mail Address').'" id="contact_email" type="email" name="contact_email" '.
            'value="'.$this->html_safe($email).'" /> *<br />'.
            '<label class="screen_reader" for="contact_name">'.$this->trans('Full Name').'</label>'.
            '<input required placeholder="'.$this->trans('Full Name').'" id="contact_name" type="text" name="contact_name" '.
            'value="'.$this->html_safe($name).'" /> *<br />'.
            '<label class="screen_reader" for="contact_phone">'.$this->trans('Telephone Number').'</label>'.
            '<input placeholder="'.$this->trans('Telephone Number').'" id="contact_phone" type="text" name="contact_phone" '.
            'value="'.$this->html_safe($phone).'" /><br />'.$button.' <input type="button" class="reset_contact" value="'.$this->trans('Reset').'" /></form></div>';
    }
}

/**
 * @subpackage contacts/functions
 */
function gmail_contacts_request($token, $url) {
    $result = array();
    $headers = array('Authorization: OAuth '.$token, 'GData-Version: 3.0');
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_USERAGENT, 'hm3');
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    return curl_exec($ch);
}

/**
 * @subpackage contacts/functions
 */
function parse_contact_xml($xml, $source) {
    $parser = new Hm_Gmail_Contact_XML($xml);
    $results = array();
    $exists = array();
    foreach ($parser->parse() as $contact) {
        if (!array_key_exists('email_address', $contact)) {
            continue;
        }
        if (in_array($contact['email_address'], $exists, true)) {
            continue;
        }
        if (!array_key_exists('display_name', $contact)) {
            $contact['display_name'] = '';
        }
        $exists[] = $contact['email_address'];
        $contact['source'] = $source;
        $results[] = $contact;
    }
    return $results;
}

/**
 * @subpackage contacts/functions
 */
function fetch_gmail_contacts($config, $contact_store) {
    foreach(Hm_IMAP_List::dump(false, true) as $id => $server) {
        if ($server['server'] == 'imap.gmail.com' && array_key_exists('auth', $server) && $server['auth'] == 'xoauth2') {
            $results = imap_refresh_oauth2_token($server, $config);
            if (!empty($results)) {
                if (Hm_IMAP_List::update_oauth2_token($server_id, $results[1], $results[0])) {
                    Hm_Debug::add(sprintf('Oauth2 token refreshed for IMAP server id %d', $server_id));
                    $updated++;
                    $server = Hm_IMAP_List::dump($id);
                }
            }
            $url = 'https://www.google.com/m8/feeds/contacts/'.$server['user'].'/full';
            $contacts = parse_contact_xml(gmail_contacts_request($server['pass'], $url), $server['name']);
            if (!empty($contacts)) {
                $contact_store->import($contacts);
            }
        }
    }
    elog($contact_store->dump());
    return $contact_store;
}

